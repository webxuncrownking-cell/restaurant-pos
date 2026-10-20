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
            $prod['variants'] = self::getProductVariants($restaurantId, (int)$prod['id']);
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
            "SELECT r.*, i.name as ingredient_name, i.base_unit as base_ingredient_unit, 
                    i.cost_per_base_unit as ingredient_cost_per_base_unit,
                    v.name as variant_name
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
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            if ($variantId === null) {
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM recipes WHERE restaurant_id = ? AND product_id = ? AND variant_id IS NULL"
                );
                $deleteStmt->execute([$restaurantId, $productId]);
            } else {
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM recipes WHERE restaurant_id = ? AND product_id = ? AND variant_id = ?"
                );
                $deleteStmt->execute([$restaurantId, $productId, $variantId]);
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO recipes (
                    restaurant_id, product_id, variant_id, ingredient_id, quantity_required
                ) VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($ingredientsList as $item) {
                $ingId = (int)($item['ingredient_id'] ?? 0);
                $qty = (float)($item['quantity'] ?? 0);
                $unit = trim((string)($item['unit'] ?? ''));

                if ($ingId <= 0 || $qty <= 0) {
                    continue;
                }

                $ing = Database::fetchOne(
                    "SELECT base_unit FROM ingredients WHERE id = ? AND restaurant_id = ?",
                    [$ingId, $restaurantId]
                );

                if (!$ing) continue;

                $baseUnit = $ing['base_unit'];
                $baseQty = $unit !== '' && $unit !== $baseUnit
                    ? UnitConversionService::convert($restaurantId, $qty, $unit, $baseUnit, $ingId)
                    : $qty;

                $insertStmt->execute([
                    $restaurantId,
                    $productId,
                    $variantId,
                    $ingId,
                    $baseQty
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
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
        $recipeItems = self::getRecipeBOM($restaurantId, $productId);
        
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
            $placeholders = implode(',', array_fill(0, count($addonIds), '?'));
            $addons = Database::fetchAll(
                "SELECT pa.*, i.name as ingredient_name, i.base_unit as base_ingredient_unit, 
                        i.cost_per_base_unit as ingredient_cost_per_base_unit
                 FROM product_addons pa
                 JOIN ingredients i ON pa.ingredient_id = i.id AND pa.restaurant_id = i.restaurant_id
                 WHERE pa.restaurant_id = ? AND pa.id IN ({$placeholders})",
                array_merge([$restaurantId], $addonIds)
            );

            foreach ($addons as $addonItem) {
                $baseQty = (float)$addonItem['deduction_quantity'];
                $baseUnit = $addonItem['base_ingredient_unit'];
                $costPerBaseUnit = (float)$addonItem['ingredient_cost_per_base_unit'];

                $itemCost = $baseQty * $costPerBaseUnit;
                $totalCost += $itemCost;

                $breakdown[] = [
                    'ingredient_id' => $addonItem['ingredient_id'],
                    'name'          => 'Add-on: ' . $addonItem['name'],
                    'quantity'      => $baseQty,
                    'unit'          => $baseUnit,
                    'normalized'    => $baseQty . ' ' . $baseUnit,
                    'cost'          => round($itemCost, 4)
                ];
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
        $categories = self::getCategories($restaurantId, true);
        $products = self::getProducts($restaurantId, null, true);

        return [
            'version'       => date('YmdHis'),
            'restaurant_id' => $restaurantId,
            'categories'    => $categories,
            'products'      => $products
        ];
    }
}
