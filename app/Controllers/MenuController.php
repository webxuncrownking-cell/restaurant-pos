<?php
/**
 * Restaurant POS & Management SaaS
 * Menu, Product, Variants & Recipe BOM Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Auth;
use App\Services\MenuService;

class MenuController
{
    public function index(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $categories = MenuService::getCategories($restaurantId, false);
        $selectedCat = $request->get('category_id') ? (int)$request->get('category_id') : null;
        $products = MenuService::getProducts($restaurantId, $selectedCat, false);

        Response::view('menu/index', [
            'pageTitle'    => 'Menu Management',
            'restaurant'   => $restaurant,
            'categories'   => $categories,
            'products'     => $products,
            'selectedCat'  => $selectedCat,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function showCreateProduct(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $categories = MenuService::getCategories($restaurantId, true);
        $addonGroups = Database::fetchAll(
            "SELECT * FROM product_addon_groups WHERE restaurant_id = ? ORDER BY name ASC",
            [$restaurantId]
        );
        $directStockIngredients = Database::fetchAll(
            "SELECT id, name, base_unit, current_stock, cost_per_base_unit
             FROM ingredients WHERE restaurant_id = ? AND is_active = 1 ORDER BY name ASC",
            [$restaurantId]
        );

        Response::view('menu/product_form', [
            'pageTitle'    => 'Add New Menu Item',
            'restaurant'   => $restaurant,
            'categories'   => $categories,
            'addonGroups'  => $addonGroups,
            'directStockIngredients' => $directStockIngredients,
            'product'      => null,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function createProduct(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu/products/create?error=Invalid+security+token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }
        $restaurantId = (int)$restaurant['id'];

        $name = trim((string)$request->post('name', ''));
        $categoryId = (int)$request->post('category_id', 0);
        $sku = strtoupper(trim((string)$request->post('sku', '')));
        $basePriceRaw = $request->post('base_price', '');
        $costPriceRaw = $request->post('cost_price', '0');
        $taxRateRaw = $request->post('tax_rate', '12');
        $productType = (string)$request->post('product_type', 'recipe');
        $directStockIngredientId = (int)$request->post('direct_stock_ingredient_id', 0);

        if ($name === '' || mb_strlen($name) > 150 || $categoryId <= 0 ||
            !is_numeric($basePriceRaw) || !is_numeric($costPriceRaw) || !is_numeric($taxRateRaw)) {
            Response::redirect('/menu/products/create?error=Please+enter+valid+required+product+details');
            return;
        }

        $basePrice = (float)$basePriceRaw;
        $costPrice = (float)$costPriceRaw;
        $taxRate = (float)$taxRateRaw;
        if (!is_finite($basePrice) || $basePrice < 0 || !is_finite($costPrice) || $costPrice < 0 ||
            !is_finite($taxRate) || $taxRate < 0 || $taxRate > 100) {
            Response::redirect('/menu/products/create?error=Prices+must+be+non-negative+and+tax+must+be+0+to+100');
            return;
        }
        if (!in_array($productType, ['direct', 'recipe'], true)) {
            Response::redirect('/menu/products/create?error=Choose+a+valid+product+inventory+type');
            return;
        }
        if ($productType === 'direct' && $directStockIngredientId <= 0) {
            Response::redirect('/menu/products/create?error=Direct-stock+products+must+be+linked+to+an+ingredient');
            return;
        }
        if ($sku === '') {
            $sku = 'PRD-' . strtoupper(bin2hex(random_bytes(5)));
        }
        if (!preg_match('/^[A-Z0-9._-]{1,50}$/', $sku)) {
            Response::redirect('/menu/products/create?error=SKU+must+contain+only+letters%2C+numbers%2C+dot%2C+dash%2C+or+underscore');
            return;
        }

        $category = Database::fetchOne(
            "SELECT id FROM categories WHERE id = ? AND restaurant_id = ? AND is_active = 1",
            [$categoryId, $restaurantId]
        );
        if (!$category) {
            Response::redirect('/menu/products/create?error=Select+an+active+category+from+your+restaurant');
            return;
        }

        if ($productType === 'direct') {
            $ingredient = Database::fetchOne(
                "SELECT id FROM ingredients WHERE id = ? AND restaurant_id = ? AND is_active = 1",
                [$directStockIngredientId, $restaurantId]
            );
            if (!$ingredient) {
                Response::redirect('/menu/products/create?error=Select+an+active+stock+ingredient+from+your+restaurant');
                return;
            }
        } else {
            $directStockIngredientId = 0;
        }

        $existingSku = Database::fetchOne(
            "SELECT id FROM products WHERE restaurant_id = ? AND sku = ?",
            [$restaurantId, $sku]
        );
        if ($existingSku) {
            Response::redirect('/menu/products/create?error=That+SKU+already+exists+in+your+menu');
            return;
        }

        $variantsInput = $request->post('variants', []);
        $variants = [];
        if (is_array($variantsInput)) {
            if (count($variantsInput) > 50) {
                Response::redirect('/menu/products/create?error=A+product+can+have+up+to+50+variants');
                return;
            }
            foreach ($variantsInput as $variantInput) {
                if (!is_array($variantInput)) {
                    continue;
                }
                $variantName = trim((string)($variantInput['name'] ?? ''));
                if ($variantName === '') {
                    continue;
                }
                $adjustmentRaw = $variantInput['price_adjustment'] ?? '0';
                if (mb_strlen($variantName) > 100 || !is_numeric($adjustmentRaw)) {
                    Response::redirect('/menu/products/create?error=Check+the+variant+name+and+price+adjustment');
                    return;
                }
                $adjustment = (float)$adjustmentRaw;
                if (!is_finite($adjustment) || ($basePrice + $adjustment) < 0) {
                    Response::redirect('/menu/products/create?error=Variant+prices+cannot+be+negative');
                    return;
                }
                $variants[] = ['name' => $variantName, 'additional_price' => $adjustment];
            }
        }

        $addonGroupIds = $request->post('addon_group_ids', []);
        if (!is_array($addonGroupIds)) {
            $addonGroupIds = [];
        }
        $addonGroupIds = array_values(array_unique(array_filter(array_map('intval', $addonGroupIds), static fn(int $id): bool => $id > 0)));
        if (count($addonGroupIds) > 100) {
            Response::redirect('/menu/products/create?error=Too+many+add-on+groups+selected');
            return;
        }
        foreach ($addonGroupIds as $groupId) {
            $group = Database::fetchOne(
                "SELECT id FROM product_addon_groups WHERE id = ? AND restaurant_id = ?",
                [$groupId, $restaurantId]
            );
            if (!$group) {
                Response::redirect('/menu/products/create?error=An+add-on+group+is+not+available+for+this+restaurant');
                return;
            }
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products
                    (restaurant_id, category_id, name, sku, base_price, cost_price, tax_rate,
                     product_type, direct_stock_ingredient_id, has_variants, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([
                $restaurantId, $categoryId, $name, $sku, $basePrice, $costPrice, $taxRate,
                $productType, $productType === 'direct' ? $directStockIngredientId : null,
                $variants ? 1 : 0
            ]);
            $productId = (int)$pdo->lastInsertId();

            $variantStmt = $pdo->prepare(
                "INSERT INTO product_variants
                    (restaurant_id, product_id, name, sku, additional_price, cost_price, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            foreach ($variants as $index => $variant) {
                $variantSku = substr($sku, 0, 38) . '-V' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT);
                $variantStmt->execute([
                    $restaurantId, $productId, $variant['name'], $variantSku,
                    $variant['additional_price'], max(0, $costPrice)
                ]);
            }

            if ($addonGroupIds) {
                $assignmentStmt = $pdo->prepare(
                    "INSERT INTO product_addon_group_assignments (restaurant_id, product_id, group_id)
                     VALUES (?, ?, ?)"
                );
                foreach ($addonGroupIds as $groupId) {
                    $assignmentStmt->execute([$restaurantId, $productId, $groupId]);
                }
            }

            $pdo->commit();
            Response::redirect("/menu/products/{$productId}/recipe?created=1");
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Menu product creation failed: ' . $e);
            Response::redirect('/menu/products/create?error=Unable+to+save+this+product.+Check+SKU+uniqueness+and+the+submitted+values');
        }
    }

    public function createCategory(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu?error=Invalid+security+token');
            return;
        }
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $name = trim((string)$request->post('name', ''));
        if ($name === '' || mb_strlen($name) > 100) {
            Response::redirect('/menu?error=Enter+a+category+name+of+1+to+100+characters');
            return;
        }
        $restaurantId = (int)$restaurant['id'];
        $duplicate = Database::fetchOne(
            "SELECT id FROM categories WHERE restaurant_id = ? AND LOWER(name) = LOWER(?)",
            [$restaurantId, $name]
        );
        if ($duplicate) {
            Response::redirect('/menu?error=That+category+already+exists');
            return;
        }

        try {
            $sortOrder = (int)Database::fetchColumn(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories WHERE restaurant_id = ?",
                [$restaurantId]
            );
            Database::query(
                "INSERT INTO categories (restaurant_id, name, sort_order, is_active) VALUES (?, ?, ?, 1)",
                [$restaurantId, $name, $sortOrder]
            );
            Response::redirect('/menu?category_created=1');
        } catch (\Throwable $e) {
            error_log('Menu category creation failed: ' . $e);
            Response::redirect('/menu?error=Unable+to+create+the+category');
        }
    }

    public function setProductVisibility(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu?error=Invalid+security+token');
            return;
        }
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $productId = (int)$request->post('product_id', 0);
        $isActive = (string)$request->post('is_active', '') === '1' ? 1 : 0;
        if ($productId <= 0) {
            Response::redirect('/menu?error=Invalid+product');
            return;
        }

        $affected = Database::query(
            "UPDATE products SET is_active = ? WHERE id = ? AND restaurant_id = ?",
            [$isActive, $productId, (int)$restaurant['id']]
        )->rowCount();
        Response::redirect('/menu?visibility_updated=1');
    }

    public function showRecipeBOM(Request $request, array $params): void
    {
        Auth::requirePermission('menu.manage_recipes');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }
        $restaurantId = (int)$restaurant['id'];
        $productId = (int)($params['id'] ?? 0);

        $product = MenuService::getProduct($restaurantId, $productId);
        if (!$product) {
            Response::notFound("Product not found.");
            return;
        }

        $ingredients = Database::fetchAll(
            "SELECT * FROM ingredients WHERE restaurant_id = ? AND is_active = 1 ORDER BY name ASC",
            [$restaurantId]
        );

        $unitConversions = Database::fetchAll(
            "SELECT ingredient_id, unit_name, multiplier_to_base
             FROM ingredient_unit_conversions
             WHERE restaurant_id = ?
             ORDER BY ingredient_id ASC, unit_name ASC",
            [$restaurantId]
        );

        $selectedVariantId = $request->get('variant_id') !== null && $request->get('variant_id') !== ''
            ? (int)$request->get('variant_id')
            : null;
        if ($selectedVariantId !== null && !array_filter($product['variants'], static fn(array $variant): bool => (int)$variant['id'] === $selectedVariantId)) {
            $selectedVariantId = null;
        }
        $costInfo = MenuService::calculateProductCost($restaurantId, $productId, $selectedVariantId ?? 0);

        Response::view('menu/recipe_bom', [
            'pageTitle'    => 'Recipe Bill of Materials: ' . $product['name'],
            'restaurant'   => $restaurant,
            'product'      => $product,
            'ingredients'  => $ingredients,
            'unitConversions' => $unitConversions,
            'costInfo'     => $costInfo,
            'selectedVariantId' => $selectedVariantId,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function saveRecipeBOM(Request $request, array $params): void
    {
        Auth::requirePermission('menu.manage_recipes');
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu?error=Invalid+security+token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }
        $restaurantId = (int)$restaurant['id'];
        $productId = (int)($params['id'] ?? 0);

        $variantId = $request->post('variant_id') ? (int)$request->post('variant_id') : null;
        $items = $request->post('items');

        if (!is_array($items)) {
            $items = [];
        }

        try {
            MenuService::saveRecipeBOM($restaurantId, $productId, $variantId, $items);
            Response::redirect("/menu/products/{$productId}/recipe?saved=1");
        } catch (\InvalidArgumentException $e) {
            Response::redirect("/menu/products/{$productId}/recipe?error=" . urlencode($e->getMessage()));
        } catch (\Throwable $e) {
            error_log('Recipe BOM save failed: ' . $e);
            Response::redirect("/menu/products/{$productId}/recipe?error=Unable+to+save+recipe.+Please+check+the+ingredients+and+units");
        }
    }

    public function catalogJson(Request $request): void
    {
        Auth::requirePermission('pos.create_order');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::json(['error' => 'No active tenant context'], 400);
            return;
        }

        $catalog = MenuService::getPosCatalog((int)$restaurant['id']);
        Response::json($catalog);
    }
}
