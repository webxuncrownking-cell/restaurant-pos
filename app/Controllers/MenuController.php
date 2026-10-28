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
        $search = trim((string)$request->get('search', ''));
        $products = MenuService::getProducts($restaurantId, $selectedCat, false, $search);

        Response::view('menu/index', [
            'pageTitle'    => 'Menu Management',
            'restaurant'   => $restaurant,
            'categories'   => $categories,
            'products'     => $products,
            'selectedCat'  => $selectedCat,
            'search'       => $search,
            'product_image_column_ready' => $this->hasProductImageColumn(),
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
        $this->ensureKitchenStationColumn();
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
            'product_image_column_ready' => $this->hasProductImageColumn(),
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function showEditProduct(Request $request, array $params): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) { Response::redirect('/dashboard'); return; }
        $restaurantId = (int)$restaurant['id'];
        $this->ensureKitchenStationColumn();
        $productId = (int)($params['id'] ?? 0);
        $product = MenuService::getProduct($restaurantId, $productId);
        if (!$product) { Response::notFound('Menu item not found.'); return; }

        $categories = MenuService::getCategories($restaurantId, false);
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
            'pageTitle' => 'Edit Menu Item: ' . $product['name'],
            'restaurant' => $restaurant,
            'categories' => $categories,
            'addonGroups' => $addonGroups,
            'directStockIngredients' => $directStockIngredients,
            'product' => $product,
            'product_image_column_ready' => $this->hasProductImageColumn(),
            'csrf_token' => CSRF::generate()
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
        $this->ensureKitchenStationColumn();
        $imageColumnReady = $this->hasProductImageColumn();
        $imageUpload = $_FILES['product_image'] ?? null;
        $hasImageUpload = is_array($imageUpload) && (int)($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasImageUpload && !$imageColumnReady) {
            Response::redirect('/menu/products/create?error=Run+the+product+image+database+migration+before+uploading+images');
            return;
        }

        $name = trim((string)$request->post('name', ''));
        $categoryId = (int)$request->post('category_id', 0);
        $sku = strtoupper(trim((string)$request->post('sku', '')));
        $basePriceRaw = $request->post('base_price', '');
        $costPriceRaw = $request->post('cost_price', '0');
        $taxRateRaw = $request->post('tax_rate', '12');
        $productType = (string)$request->post('product_type', 'recipe');
        $directStockIngredientId = (int)$request->post('direct_stock_ingredient_id', 0);
        $kitchenStation = trim((string)$request->post('kitchen_station', 'Hot Kitchen'));
        if ($kitchenStation === '' || mb_strlen($kitchenStation) > 80) $kitchenStation = 'Hot Kitchen';

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

        $productImagePath = null;
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            if ($hasImageUpload) {
                $productImagePath = $this->storeUploadedProductImage($imageUpload, $restaurantId);
            }

            if ($imageColumnReady) {
                $stmt = $pdo->prepare(
                    "INSERT INTO products
                        (restaurant_id, category_id, name, sku, base_price, cost_price, tax_rate,
                         product_type, direct_stock_ingredient_id, has_variants, is_active, image_path, kitchen_station)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
                );
                $stmt->execute([
                    $restaurantId, $categoryId, $name, $sku, $basePrice, $costPrice, $taxRate,
                    $productType, $productType === 'direct' ? $directStockIngredientId : null,
                    $variants ? 1 : 0, $productImagePath, $kitchenStation
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO products
                        (restaurant_id, category_id, name, sku, base_price, cost_price, tax_rate,
                         product_type, direct_stock_ingredient_id, has_variants, is_active, kitchen_station)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
                );
                $stmt->execute([
                    $restaurantId, $categoryId, $name, $sku, $basePrice, $costPrice, $taxRate,
                    $productType, $productType === 'direct' ? $directStockIngredientId : null,
                    $variants ? 1 : 0, $kitchenStation
                ]);
            }
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
            if ($productImagePath !== null) {
                $this->deleteStoredProductImage($productImagePath);
            }
            error_log('Menu product creation failed: ' . $e);
            Response::redirect('/menu/products/create?error=Unable+to+save+this+product.+Check+SKU+uniqueness+and+the+submitted+values');
        }
    }

    public function updateProduct(Request $request, array $params): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $productId = (int)($params['id'] ?? 0);
        $redirectEdit = static function (string $message) use ($productId): void {
            Response::redirect('/menu/products/' . $productId . '/edit?error=' . urlencode($message));
        };
        if (!CSRF::validate($request->csrfToken())) {
            $redirectEdit('Invalid security token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) { Response::redirect('/dashboard'); return; }
        $restaurantId = (int)$restaurant['id'];
        $this->ensureKitchenStationColumn();
        $current = MenuService::getProduct($restaurantId, $productId);
        if (!$current) { Response::redirect('/menu?error=Menu+item+not+found'); return; }

        $imageColumnReady = $this->hasProductImageColumn();
        $imageUpload = $_FILES['product_image'] ?? null;
        $hasImageUpload = is_array($imageUpload)
            && (int)($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasImageUpload && !$imageColumnReady) {
            $redirectEdit('Run the product image database migration before uploading images');
            return;
        }

        $name = trim((string)$request->post('name', ''));
        $categoryId = (int)$request->post('category_id', 0);
        $sku = strtoupper(trim((string)$request->post('sku', '')));
        if ($sku === '') $sku = strtoupper(trim((string)$current['sku']));
        $basePriceRaw = $request->post('base_price', '');
        $costPriceRaw = $request->post('cost_price', '0');
        $taxRateRaw = $request->post('tax_rate', '12');
        $productType = (string)$request->post('product_type', 'recipe');
        $directStockIngredientId = (int)$request->post('direct_stock_ingredient_id', 0);
        $kitchenStation = trim((string)$request->post('kitchen_station', 'Hot Kitchen'));
        if ($kitchenStation === '' || mb_strlen($kitchenStation) > 80) $kitchenStation = 'Hot Kitchen';

        if ($name === '' || mb_strlen($name) > 150 || $categoryId <= 0 ||
            !is_numeric($basePriceRaw) || !is_numeric($costPriceRaw) || !is_numeric($taxRateRaw)) {
            $redirectEdit('Please enter valid required product details');
            return;
        }
        $basePrice = (float)$basePriceRaw;
        $costPrice = (float)$costPriceRaw;
        $taxRate = (float)$taxRateRaw;
        if (!is_finite($basePrice) || $basePrice < 0 || !is_finite($costPrice) || $costPrice < 0 ||
            !is_finite($taxRate) || $taxRate < 0 || $taxRate > 100) {
            $redirectEdit('Prices must be non-negative and tax must be 0 to 100');
            return;
        }
        if (!in_array($productType, ['direct', 'recipe'], true)) {
            $redirectEdit('Choose a valid product inventory type');
            return;
        }
        if ($productType === 'direct' && $directStockIngredientId <= 0) {
            $redirectEdit('Direct-stock products must be linked to an ingredient');
            return;
        }
        if (!preg_match('/^[A-Z0-9._-]{1,50}$/', $sku)) {
            $redirectEdit('SKU must contain only letters, numbers, dot, dash, or underscore');
            return;
        }

        $category = Database::fetchOne(
            "SELECT id FROM categories WHERE id = ? AND restaurant_id = ? AND (is_active = 1 OR id = ?)",
            [$categoryId, $restaurantId, (int)$current['category_id']]
        );
        if (!$category) { $redirectEdit('Select an active category from your restaurant'); return; }
        if ($productType === 'direct') {
            $ingredient = Database::fetchOne(
                "SELECT id FROM ingredients WHERE id = ? AND restaurant_id = ? AND is_active = 1",
                [$directStockIngredientId, $restaurantId]
            );
            if (!$ingredient) { $redirectEdit('Select an active stock ingredient from your restaurant'); return; }
        } else {
            $directStockIngredientId = 0;
        }

        $existingSku = Database::fetchOne(
            "SELECT id FROM products WHERE restaurant_id = ? AND sku = ? AND id <> ?",
            [$restaurantId, $sku, $productId]
        );
        if ($existingSku) { $redirectEdit('That SKU already exists in your menu'); return; }

        $variantsInput = $request->post('variants', []);
        if (!is_array($variantsInput) || count($variantsInput) > 50) {
            $redirectEdit('A product can have up to 50 variants');
            return;
        }
        $variants = [];
        foreach ($variantsInput as $variantInput) {
            if (!is_array($variantInput)) continue;
            $variantName = trim((string)($variantInput['name'] ?? ''));
            if ($variantName === '') continue;
            $adjustmentRaw = $variantInput['price_adjustment'] ?? '0';
            if (mb_strlen($variantName) > 100 || !is_numeric($adjustmentRaw)) {
                $redirectEdit('Check the variant name and price adjustment');
                return;
            }
            $adjustment = (float)$adjustmentRaw;
            if (!is_finite($adjustment) || $basePrice + $adjustment < 0) {
                $redirectEdit('Variant prices cannot be negative');
                return;
            }
            $variantIdRaw = $variantInput['id'] ?? '';
            $variantId = $variantIdRaw === '' ? 0 : filter_var($variantIdRaw, FILTER_VALIDATE_INT);
            if ($variantId === false || ($variantId !== 0 && $variantId < 1)) {
                $redirectEdit('Invalid variant reference');
                return;
            }
            $variants[] = ['id' => (int)$variantId, 'name' => $variantName, 'additional_price' => $adjustment];
        }

        $addonGroupIds = $request->post('addon_group_ids', []);
        if (!is_array($addonGroupIds)) $addonGroupIds = [];
        $addonGroupIds = array_values(array_unique(array_filter(array_map('intval', $addonGroupIds), static fn(int $id): bool => $id > 0)));
        if (count($addonGroupIds) > 100) { $redirectEdit('Too many add-on groups selected'); return; }
        foreach ($addonGroupIds as $groupId) {
            $group = Database::fetchOne(
                "SELECT id FROM product_addon_groups WHERE id = ? AND restaurant_id = ?",
                [$groupId, $restaurantId]
            );
            if (!$group) { $redirectEdit('An add-on group is not available for this restaurant'); return; }
        }

        $oldPath = trim((string)($current['image_path'] ?? ''));
        $newPath = $oldPath !== '' ? $oldPath : null;
        if (!$hasImageUpload && (string)$request->post('remove_image', '') === '1') $newPath = null;
        $uploadedPath = null;
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            if ($hasImageUpload) {
                $uploadedPath = $this->storeUploadedProductImage($imageUpload, $restaurantId);
                $newPath = $uploadedPath;
            }

            if ($imageColumnReady) {
                Database::query(
                    "UPDATE products SET category_id = ?, name = ?, sku = ?, base_price = ?, cost_price = ?, tax_rate = ?,
                     product_type = ?, direct_stock_ingredient_id = ?, has_variants = ?, image_path = ?, kitchen_station = ?
                     WHERE restaurant_id = ? AND id = ?",
                    [$categoryId, $name, $sku, $basePrice, $costPrice, $taxRate,
                     $productType, $productType === 'direct' ? $directStockIngredientId : null,
                     $variants ? 1 : 0, $newPath, $kitchenStation, $restaurantId, $productId]
                );
            } else {
                Database::query(
                    "UPDATE products SET category_id = ?, name = ?, sku = ?, base_price = ?, cost_price = ?, tax_rate = ?,
                     product_type = ?, direct_stock_ingredient_id = ?, has_variants = ?, kitchen_station = ?
                     WHERE restaurant_id = ? AND id = ?",
                    [$categoryId, $name, $sku, $basePrice, $costPrice, $taxRate,
                     $productType, $productType === 'direct' ? $directStockIngredientId : null,
                     $variants ? 1 : 0, $kitchenStation, $restaurantId, $productId]
                );
            }

            $existingVariantRows = Database::fetchAll(
                "SELECT id FROM product_variants WHERE restaurant_id = ? AND product_id = ?",
                [$restaurantId, $productId]
            );
            $existingVariantIds = array_map(static fn(array $row): int => (int)$row['id'], $existingVariantRows);
            Database::query(
                "UPDATE product_variants SET is_active = 0 WHERE restaurant_id = ? AND product_id = ?",
                [$restaurantId, $productId]
            );

            foreach ($variants as $variant) {
                if ($variant['id'] > 0) {
                    if (!in_array($variant['id'], $existingVariantIds, true)) {
                        throw new \InvalidArgumentException('A submitted variant does not belong to this menu item.');
                    }
                    Database::query(
                        "UPDATE product_variants SET name = ?, additional_price = ?, cost_price = ?, is_active = 1
                         WHERE restaurant_id = ? AND product_id = ? AND id = ?",
                        [$variant['name'], $variant['additional_price'], $costPrice, $restaurantId, $productId, $variant['id']]
                    );
                } else {
                    $variantSku = substr($sku, 0, 35) . '-V' . strtoupper(bin2hex(random_bytes(4)));
                    Database::query(
                        "INSERT INTO product_variants
                         (restaurant_id, product_id, name, sku, additional_price, cost_price, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, 1)",
                        [$restaurantId, $productId, $variant['name'], $variantSku, $variant['additional_price'], $costPrice]
                    );
                }
            }

            Database::query(
                "DELETE FROM product_addon_group_assignments WHERE restaurant_id = ? AND product_id = ?",
                [$restaurantId, $productId]
            );
            foreach ($addonGroupIds as $groupId) {
                Database::query(
                    "INSERT INTO product_addon_group_assignments (restaurant_id, product_id, group_id) VALUES (?, ?, ?)",
                    [$restaurantId, $productId, $groupId]
                );
            }

            $pdo->commit();
            if ($oldPath !== '' && $oldPath !== $newPath) $this->deleteStoredProductImage($oldPath);
            Response::redirect('/menu?product_updated=1');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($uploadedPath !== null && $uploadedPath !== $oldPath) $this->deleteStoredProductImage($uploadedPath);
            error_log('Menu product update failed: ' . $e);
            $message = $e instanceof \InvalidArgumentException
                ? $e->getMessage()
                : 'Unable to update this menu item. Check SKU uniqueness and submitted values.';
            $redirectEdit($message);
        }
    }

    public function saveProductImage(Request $request): void
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
        $restaurantId = (int)$restaurant['id'];
        if (!$this->hasProductImageColumn()) {
            Response::redirect('/menu?error=Run+the+product+image+database+migration+before+uploading+images');
            return;
        }

        $productId = (int)$request->post('product_id', 0);
        $product = Database::fetchOne(
            'SELECT id, image_path FROM products WHERE id = ? AND restaurant_id = ?',
            [$productId, $restaurantId]
        );
        if (!$product) {
            Response::redirect('/menu?error=Product+not+found+for+this+restaurant');
            return;
        }

        $upload = $_FILES['product_image'] ?? null;
        $hasUpload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $removeImage = (string)$request->post('remove_image', '') === '1';
        if (!$hasUpload && !$removeImage) {
            Response::redirect('/menu?error=Choose+a+product+image+or+select+Remove+image');
            return;
        }

        $oldPath = trim((string)($product['image_path'] ?? ''));
        $newPath = $oldPath !== '' ? $oldPath : null;
        $uploadedPath = null;
        try {
            if ($hasUpload) {
                $uploadedPath = $this->storeUploadedProductImage($upload, $restaurantId);
                $newPath = $uploadedPath;
            } elseif ($removeImage) {
                $newPath = null;
            }

            Database::query(
                'UPDATE products SET image_path = ? WHERE id = ? AND restaurant_id = ?',
                [$newPath, $productId, $restaurantId]
            );

            if ($oldPath !== '' && $oldPath !== $newPath) {
                $this->deleteStoredProductImage($oldPath);
            }
            Response::redirect('/menu?image_updated=1');
        } catch (\Throwable $e) {
            if ($uploadedPath !== null && $uploadedPath !== $oldPath) {
                $this->deleteStoredProductImage($uploadedPath);
            }
            error_log('Menu product image update failed: ' . $e->getMessage());
            Response::redirect('/menu?error=Unable+to+save+product+image.+Check+upload+folder+permissions');
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
    private function ensureKitchenStationColumn(): void
    {
        $pdo = Database::getConnection();
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'kitchen_station'")->fetchAll(\PDO::FETCH_ASSOC);
        if (!$columns) {
            $pdo->exec("ALTER TABLE products ADD COLUMN kitchen_station VARCHAR(80) NOT NULL DEFAULT 'Hot Kitchen'");
        }
    }

    private function hasProductImageColumn(): bool
    {
        try {
            $pdo = Database::getConnection();
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $columns = Database::fetchAll('PRAGMA table_info(products)');
                return in_array('image_path', array_column($columns, 'name'), true);
            }

            return Database::fetchOne("SHOW COLUMNS FROM products LIKE 'image_path'") !== null;
        } catch (\Throwable $e) {
            error_log('Menu image schema inspection failed: ' . $e->getMessage());
            return false;
        }
    }

    private function storeUploadedProductImage(array $upload, int $restaurantId): string
    {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new \RuntimeException('The product image is too large. Choose an image under 3 MB.');
            }
            throw new \RuntimeException('The product image upload did not complete. Please choose the file again.');
        }

        $tmpPath = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('The uploaded product image could not be verified.');
        }
        if ($size < 1 || $size > 3145728) {
            throw new \RuntimeException('The product image must be 3 MB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new \RuntimeException('Use a JPG, PNG, or WebP product image. SVG files are not accepted.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
            throw new \RuntimeException('The uploaded product image is not a valid image.');
        }
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 3000 || $height > 3000) {
            throw new \RuntimeException('Product image dimensions must not exceed 3000 × 3000 pixels.');
        }

        $relativeDirectory = 'public/uploads/menu-products';
        $absoluteDirectory = APP_ROOT . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('The product image upload directory could not be created. Check folder permissions.');
        }
        if (!is_writable($absoluteDirectory)) {
            throw new \RuntimeException('The product image folder is not writable by the XAMPP Apache/PHP process.');
        }

        $filename = 'product-' . $restaurantId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $relativePath = $relativeDirectory . '/' . $filename;
        if (!move_uploaded_file($tmpPath, APP_ROOT . '/' . $relativePath)) {
            throw new \RuntimeException('The product image could not be saved. Check write permission for public/uploads.');
        }

        return $relativePath;
    }

    private function deleteStoredProductImage(string $relativePath): void
    {
        $prefix = 'public/uploads/menu-products/';
        if (!str_starts_with($relativePath, $prefix)) return;

        $filename = substr($relativePath, strlen($prefix));
        if (!preg_match('/^product-[0-9]+-[a-f0-9]{24}\.(?:jpg|png|webp)$/i', $filename)) return;

        $absolutePath = APP_ROOT . '/' . $prefix . $filename;
        if (is_file($absolutePath)) @unlink($absolutePath);
    }

}
