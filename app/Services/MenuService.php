<?php
/**
 * Restaurant POS & Management SaaS
 * Menu, Variants, Add-ons & Recipe Bill of Materials (BOM) Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class MenuService
{
    /**
     * Get all active categories for a restaurant.
     */
    public static function getCategories(int $restaurantId, bool $activeOnly = true): array
    {
        $sql = "SELECT c.*, COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND c.restaurant_id = p.restaurant_id
                WHERE c.restaurant_id = ?";
        if ($activeOnly) {
            $sql .= " AND c.is_active = 1";
        }
        $sql .= " GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC";

        return Database::fetchAll($sql, [$restaurantId]);
    }

    /**
     * Get products with category details, variants count, and cost calculation.
     */
    public static function getProducts(
        int $restaurantId, 
        ?int $categoryId = null, 
        bool $activeOnly = true
    ): array {
        $sql = "SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id AND p.restaurant_id = c.restaurant_id
                WHERE p.restaurant_id = ?";
        $params = [$restaurantId];

        if ($categoryId !== null) {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }

        if ($activeOnly) {
            $sql .= " AND p.is_active = 1";
        }

        $sql .= " ORDER BY c.sort_order ASC, p.name ASC";
        $products = Database::fetchAll($sql, $params);

        // Enrich with variants and estimated food cost
        foreach ($products as &$prod) {
            $prod['variants'] = array_values(array_filter(
                self::getProductVariants($restaurantId, (int)$prod['id']),
                static fn(array $variant): bool => !empty($variant['is_active'])
            ));
            $prod['addon_groups'] = self::getProductAddonGroups($restaurantId, (int)$prod['id']);
            $costInfo = self::calculateProductCost($restaurantId, (int)$prod['id'], null);
            $prod['base_cost'] = $costInfo['total_cost'];
            $basePrice = (float)$prod['base_price'];
            $prod['profit_margin'] = $basePrice > 0 
                ? round((($basePrice - $prod['base_cost']) / $basePrice) * 100, 1) 
                : 0.0;
        }

        return $products;
    }

    /**
     * Get a single product by ID with full details (variants, addon groups, recipe BOM).
     */
    public static function getProduct(int $restaurantId, int $productId): ?array
    {
        $product = Database::fetchOne(
            "SELECT p.*, c.name as category_name 
             FROM products p
             JOIN categories c ON p.category_id = c.id AND p.restaurant_id = c.restaurant_id
             WHERE p.id = ? AND p.restaurant_id = ?",
            [$productId, $restaurantId]
        );

        if (!$product) {
            return null;
        }

        $product['variants'] = self::getProductVariants($restaurantId, $productId);
        $product['addon_groups'] = self::getProductAddonGroups($restaurantId, $productId);
        $product['recipe_bom'] = self::getRecipeBOM($restaurantId, $productId);

        return $product;
    }

    /**
     * Get variants for a product.
     */
    public static function getProductVariants(int $restaurantId, int $productId): array
    {
        return Database::fetchAll(
            "SELECT * FROM product_variants 
             WHERE product_id = ? AND restaurant_id = ? 
             ORDER BY additional_price ASC, name ASC",
            [$productId, $restaurantId]
        );
    }

    /**
     * Get add-on groups and options assigned to a product.
     */
    public static function getProductAddonGroups(int $restaurantId, int $productId): array
    {
        $groups = Database::fetchAll(
            "SELECT g.* 
             FROM product_addon_groups g
             JOIN product_addon_group_assignments pag ON g.id = pag.group_id AND g.restaurant_id = pag.restaurant_id
             WHERE pag.product_id = ? AND pag.restaurant_id = ?
             ORDER BY g.name ASC",
            [$productId, $restaurantId]
        );

        foreach ($groups as &$grp) {
            $grp['options'] = Database::fetchAll(
                "SELECT * FROM product_addons 
                 WHERE group_id = ? AND restaurant_id = ? AND is_active = 1 
                 ORDER BY price ASC, name ASC",
                [(int)$grp['id'], $restaurantId]
            );
        }

        return $groups;
    }

    /**
     * Get recipe Bill of Materials (BOM) for a product (both base and variant-specific ingredients).
     */
    public static function getRecipeBOM(int $restaurantId, int $productId): array
    {
        return Database::fetchAll(
            "SELECT r.*, i.name AS ingredient_name, i.base_unit AS base_ingredient_unit,
                    i.base_unit AS unit, i.cost_per_base_unit AS ingredient_cost_per_base_unit,
                    i.cost_per_base_unit AS cost_per_unit, v.name AS variant_name
             FROM recipes r
             JOIN ingredients i ON r.ingredient_id = i.id AND r.restaurant_id = i.restaurant_id
             LEFT JOIN product_variants v ON r.variant_id = v.id AND r.restaurant_id = v.restaurant_id
             WHERE r.product_id = ? AND r.restaurant_id = ?
             ORDER BY r.variant_id IS NOT NULL ASC, i.name ASC",
            [$productId, $restaurantId]
        );
    }

    /**
     * Save or update Recipe Bill of Materials for a product.
     */
    public static function saveRecipeBOM(
        int $restaurantId,
        int $productId,
        ?int $variantId,
        array $ingredientsList
    ): void {
        if ($restaurantId <= 0 || $productId <= 0) {
            throw new \InvalidArgumentException('A valid restaurant and product are required.');
        }

        $product = Database::fetchOne(
            "SELECT id, product_type FROM products WHERE id = ? AND restaurant_id = ? AND is_active = 1",
            [$productId, $restaurantId]
        );
        if (!$product) {
            throw new \InvalidArgumentException('The selected product is not active or does not belong to this restaurant.');
        }

        if ($variantId !== null) {
            $variant = Database::fetchOne(
                "SELECT id FROM product_variants
                 WHERE id = ? AND product_id = ? AND restaurant_id = ? AND is_active = 1",
                [$variantId, $productId, $restaurantId]
            );
            if (!$variant) {
                throw new \InvalidArgumentException('The selected variant is not active or does not belong to this product.');
            }
        }

        if (count($ingredientsList) > 200) {
            throw new \InvalidArgumentException('A recipe cannot contain more than 200 ingredient rows.');
        }

        // Validate and normalize everything before deleting the saved recipe. A malformed
        // submission must never erase the current BOM.
        $normalizedItems = [];
        $seenIngredientIds = [];
        foreach ($ingredientsList as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Invalid ingredient row in recipe.');
            }

            $ingId = filter_var($item['ingredient_id'] ?? null, FILTER_VALIDATE_INT);
            $qtyRaw = $item['quantity'] ?? null;
            $qty = is_numeric($qtyRaw) ? (float)$qtyRaw : 0.0;
            $unit = trim((string)($item['unit'] ?? ''));

            // Blank rows from dynamic form UI are ignored; partially-filled rows are rejected.
            if (($ingId === false || $ingId === null || $ingId <= 0) && ($qtyRaw === null || trim((string)$qtyRaw) === '')) {
                continue;
            }
            if ($ingId === false || $ingId === null || $ingId <= 0 || !is_finite($qty) || $qty <= 0 || $unit === '') {
                throw new \InvalidArgumentException('Each recipe row needs an ingredient, a positive quantity, and a unit.');
            }
            if (isset($seenIngredientIds[(int)$ingId])) {
                throw new \InvalidArgumentException('An ingredient can appear only once per recipe. Combine its quantities into one row.');
            }

            $ing = Database::fetchOne(
                "SELECT id, base_unit FROM ingredients
                 WHERE id = ? AND restaurant_id = ? AND is_active = 1",
                [(int)$ingId, $restaurantId]
            );
            if (!$ing) {
                throw new \InvalidArgumentException('A selected ingredient is unavailable for this restaurant.');
            }

            $baseUnit = (string)$ing['base_unit'];
            $baseQty = UnitConversionService::convert($restaurantId, $qty, $unit, $baseUnit, (int)$ingId);
            if (!is_finite($baseQty) || $baseQty <= 0) {
                throw new \InvalidArgumentException('Recipe quantity must normalize to a positive base quantity.');
            }

            $seenIngredientIds[(int)$ingId] = true;
            $normalizedItems[] = [(int)$ingId, $baseQty];
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $deleteSql = "DELETE FROM recipes WHERE restaurant_id = ? AND product_id = ?";
            $deleteParams = [$restaurantId, $productId];
            if ($variantId === null) {
                $deleteSql .= " AND variant_id IS NULL";
            } else {
                $deleteSql .= " AND variant_id = ?";
                $deleteParams[] = $variantId;
            }
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute($deleteParams);

            if ($normalizedItems) {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO recipes
                        (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
                     VALUES (?, ?, ?, ?, ?)"
                );
                foreach ($normalizedItems as [$ingId, $baseQty]) {
                    $insertStmt->execute([$restaurantId, $productId, $variantId, $ingId, $baseQty]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Calculate total recipe cost for a product + optional variant + optional addon IDs.
     */
    public static function calculateProductCost(
        int $restaurantId, 
        int $productId, 
        ?int $variantId = null,
        array $addonIds = []
    ): array {
        $product = Database::fetchOne(
            "SELECT product_type, direct_stock_ingredient_id, cost_price FROM products WHERE restaurant_id = ? AND id = ?",
            [$restaurantId, $productId]
        );
        if (!$product) {
            throw new \InvalidArgumentException('Product not found for this restaurant.');
        }

        $recipeItems = self::getRecipeBOM($restaurantId, $productId);
        if ($product['product_type'] === 'direct' && empty($recipeItems) && !empty($product['direct_stock_ingredient_id'])) {
            $ingredient = Database::fetchOne(
                "SELECT id, name, base_unit, cost_per_base_unit FROM ingredients
                 WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                [$restaurantId, (int)$product['direct_stock_ingredient_id']]
            );
            if ($ingredient) {
                $qty = 1.0;
                $cost = $qty * (float)$ingredient['cost_per_base_unit'];
                return [
                    'total_cost' => round($cost, 2),
                    'breakdown' => [[
                        'ingredient_id' => (int)$ingredient['id'],
                        'name' => $ingredient['name'],
                        'quantity' => $qty,
                        'unit' => $ingredient['base_unit'],
                        'normalized' => $qty . ' ' . $ingredient['base_unit'],
                        'cost' => round($cost, 4)
                    ]]
                ];
            }
        }
        
        // If there is no recipe BOM, use the stored estimate rather than showing a misleading zero cost.
        if (empty($recipeItems)) {
            $fallbackCost = (float)$product['cost_price'];
            if ($fallbackCost > 0) {
                return [
                    'total_cost' => round($fallbackCost, 2),
                    'breakdown' => [[
                        'ingredient_id' => null,
                        'name' => 'Configured product cost (no recipe saved)',
                        'quantity' => 1,
                        'unit' => 'product',
                        'normalized' => '1 product',
                        'cost' => round($fallbackCost, 4)
                    ]]
                ];
            }
        }

        // If variantId is not specified and all recipes are variant-specific, default to primary variant
        if ($variantId === null && !empty($recipeItems)) {
            $hasBase = false;
            foreach ($recipeItems as $it) {
                if ($it['variant_id'] === null) {
                    $hasBase = true;
                    break;
                }
            }
            if (!$hasBase && !empty($recipeItems[0]['variant_id'])) {
                $variantId = (int)$recipeItems[0]['variant_id'];
            }
        }

        $totalCost = 0.0;
        $breakdown = [];

        foreach ($recipeItems as $item) {
            if ($variantId !== null) {
                if ($item['variant_id'] !== null && (int)$item['variant_id'] !== $variantId) {
                    continue;
                }
            } else {
                if ($item['variant_id'] !== null) {
                    continue;
                }
            }

            $baseQty = (float)$item['quantity_required'];
            $baseUnit = $item['base_ingredient_unit'];
            $costPerBaseUnit = (float)$item['ingredient_cost_per_base_unit'];

            $itemCost = $baseQty * $costPerBaseUnit;
            $totalCost += $itemCost;

            $breakdown[] = [
                'ingredient_id' => $item['ingredient_id'],
                'name'          => $item['ingredient_name'],
                'quantity'      => $baseQty,
                'unit'          => $baseUnit,
                'normalized'    => $baseQty . ' ' . $baseUnit,
                'cost'          => round($itemCost, 4)
            ];
        }

        // Add add-ons ingredient costs
        if (!empty($addonIds)) {
            $uniqueAddonIds = array_values(array_unique(array_filter(array_map('intval', $addonIds), static fn(int $id): bool => $id > 0)));
            if ($uniqueAddonIds) {
                $placeholders = implode(',', array_fill(0, count($uniqueAddonIds), '?'));
                $addons = Database::fetchAll(
                    "SELECT pa.*, i.name AS ingredient_name, i.base_unit AS base_ingredient_unit,
                            i.cost_per_base_unit AS ingredient_cost_per_base_unit
                     FROM product_addons pa
                     JOIN product_addon_group_assignments pag
                       ON pag.restaurant_id = pa.restaurant_id AND pag.group_id = pa.group_id
                     LEFT JOIN ingredients i
                       ON pa.ingredient_id = i.id AND pa.restaurant_id = i.restaurant_id
                     WHERE pa.restaurant_id = ? AND pag.product_id = ? AND pa.is_active = 1
                       AND pa.id IN ({$placeholders})",
                    array_merge([$restaurantId, $productId], $uniqueAddonIds)
                );

                foreach ($addons as $addonItem) {
                    $hasIngredient = !empty($addonItem['ingredient_id']) && $addonItem['base_ingredient_unit'] !== null;
                    $baseQty = $hasIngredient ? (float)$addonItem['deduction_quantity'] : 1.0;
                    $baseUnit = $hasIngredient ? (string)$addonItem['base_ingredient_unit'] : 'addon';
                    $itemCost = $hasIngredient
                        ? $baseQty * (float)$addonItem['ingredient_cost_per_base_unit']
                        : (float)$addonItem['cost_price'];
                    $totalCost += $itemCost;

                    $breakdown[] = [
                        'ingredient_id' => $hasIngredient ? (int)$addonItem['ingredient_id'] : null,
                        'name'          => 'Add-on: ' . $addonItem['name'],
                        'quantity'      => $baseQty,
                        'unit'          => $baseUnit,
                        'normalized'    => $baseQty . ' ' . $baseUnit,
                        'cost'          => round($itemCost, 4)
                    ];
                }
            }
        }

        return [
            'total_cost' => round($totalCost, 2),
            'breakdown'  => $breakdown
        ];
    }

    /**
     * Complete exportable Menu Catalog for POS offline IndexedDB sync.
     */
    public static function getPosCatalog(int $restaurantId): array
    {
        $restaurantSql = "SELECT id, name, contact_phone AS phone, contact_email AS email,
                                  business_registration_number AS tin, receipt_header AS address,
                                  receipt_footer";
        try {
            // Tagline is optional on older databases until the migration is applied.
            $restaurant = Database::fetchOne(
                $restaurantSql . ", tagline, logo_path FROM restaurants WHERE id = ? AND status = 'active' AND deleted_at IS NULL",
                [$restaurantId]
            );
        } catch (\Throwable $e) {
            $restaurant = Database::fetchOne(
                $restaurantSql . " FROM restaurants WHERE id = ? AND status = 'active' AND deleted_at IS NULL",
                [$restaurantId]
            );
        }

        $categories = self::getCategories($restaurantId, true);
        $products = self::getProducts($restaurantId, null, true);
        foreach ($products as &$product) {
            $imagePath = trim((string)($product['image_path'] ?? ''));
            $product['image_url'] = $imagePath !== '' ? BASE_URL . '/' . ltrim($imagePath, '/') : '';
        }
        unset($product);

        return [
            'version'       => date('YmdHis'),
            'restaurant_id' => $restaurantId,
            'restaurant'    => $restaurant ?: [],
            'categories'    => $categories,
            'products'      => $products
        ];
    }
}
