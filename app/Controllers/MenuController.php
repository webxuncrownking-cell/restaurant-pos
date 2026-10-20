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
use App\Services\MenuService;

class MenuController
{
    public function index(Request $request): void
    {
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
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $categories = MenuService::getCategories($restaurantId, true);
        $addonGroups = Database::fetchAll(
            "SELECT * FROM addon_groups WHERE restaurant_id = ? ORDER BY name ASC",
            [$restaurantId]
        );

        Response::view('menu/product_form', [
            'pageTitle'    => 'Add New Menu Item',
            'restaurant'   => $restaurant,
            'categories'   => $categories,
            'addonGroups'  => $addonGroups,
            'product'      => null,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function createProduct(Request $request): void
    {
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu?error=Invalid+security+token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
        $restaurantId = (int)$restaurant['id'];

        $name = trim((string)$request->post('name'));
        $categoryId = (int)$request->post('category_id');
        $basePrice = (float)$request->post('base_price');
        $sku = trim((string)$request->post('sku', ''));
        $description = trim((string)$request->post('description', ''));
        $trackInventory = $request->post('track_inventory') ? 1 : 0;
        $isTaxable = $request->post('is_taxable') ? 1 : 0;

        if ($name === '' || $categoryId <= 0 || $basePrice < 0) {
            Response::redirect('/menu/products/create?error=Please+fill+all+required+fields');
            return;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products (
                    restaurant_id, category_id, name, sku, description, 
                    base_price, is_active, track_inventory, is_taxable, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $restaurantId,
                $categoryId,
                $name,
                $sku ?: null,
                $description ?: null,
                $basePrice,
                $trackInventory,
                $isTaxable
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Handle optional variants
            $variants = $request->post('variants');
            if (is_array($variants)) {
                $variantStmt = $pdo->prepare(
                    "INSERT INTO product_variants (
                        restaurant_id, product_id, name, sku_suffix, price_adjustment, is_active
                    ) VALUES (?, ?, ?, ?, ?, 1)"
                );
                foreach ($variants as $v) {
                    $vName = trim((string)($v['name'] ?? ''));
                    if ($vName !== '') {
                        $vAdjustment = (float)($v['price_adjustment'] ?? 0);
                        $vSku = trim((string)($v['sku_suffix'] ?? ''));
                        $variantStmt->execute([
                            $restaurantId,
                            $productId,
                            $vName,
                            $vSku ?: null,
                            $vAdjustment
                        ]);
                    }
                }
            }

            // Handle optional addon group associations
            $addonGroupIds = $request->post('addon_group_ids');
            if (is_array($addonGroupIds)) {
                $pagStmt = $pdo->prepare(
                    "INSERT INTO product_addon_groups (restaurant_id, product_id, addon_group_id) 
                     VALUES (?, ?, ?)"
                );
                foreach ($addonGroupIds as $agId) {
                    $pagStmt->execute([$restaurantId, $productId, (int)$agId]);
                }
            }

            $pdo->commit();
            Response::redirect("/menu/products/{$productId}/recipe?created=1");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::redirect('/menu/products/create?error=' . urlencode($e->getMessage()));
        }
    }

    public function showRecipeBOM(Request $request, array $params): void
    {
        $restaurant = TenantContext::getRestaurant();
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

        $costInfo = MenuService::calculateProductCost($restaurantId, $productId);

        Response::view('menu/recipe_bom', [
            'pageTitle'    => 'Recipe Bill of Materials: ' . $product['name'],
            'restaurant'   => $restaurant,
            'product'      => $product,
            'ingredients'  => $ingredients,
            'costInfo'     => $costInfo,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function saveRecipeBOM(Request $request, array $params): void
    {
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/menu?error=Invalid+security+token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
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
        } catch (\Throwable $e) {
            Response::redirect("/menu/products/{$productId}/recipe?error=" . urlencode($e->getMessage()));
        }
    }

    public function catalogJson(Request $request): void
    {
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::json(['error' => 'No active tenant context'], 400);
            return;
        }

        $catalog = MenuService::getPosCatalog((int)$restaurant['id']);
        Response::json($catalog);
    }
}
