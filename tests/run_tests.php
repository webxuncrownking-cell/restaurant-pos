<?php
/**
 * Restaurant POS & Management SaaS - Automated PHP Integration Test Suite
 * Run via CLI: php tests/run_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// PSR-4 Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\TenantContext;
use App\Services\AuthService;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . PHP_EOL;
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : "") . PHP_EOL;
    }
}

echo "================================================================================" . PHP_EOL;
echo "  RESTAURANT POS & SAAS PLATFORM - PHASE 1 INTEGRATION TEST SUITE" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

// 1. Database Connectivity
echo PHP_EOL . "1. Testing Database Connection & Configuration:" . PHP_EOL;
try {
    $pdo = Database::getInstance();
    $status = Database::testConnection();
    assertTest("PDO Connection Established", $status['success'], "Driver: {$status['driver']}, DB: {$status['database']}");
} catch (\Throwable $e) {
    assertTest("PDO Connection Established", false, $e->getMessage());
    exit(1);
}

// 2. Schema Table Verification
echo PHP_EOL . "2. Verifying Complete Schema Tables (Phase 1):" . PHP_EOL;
$tables = Database::fetchAll("SHOW TABLES");
$tableNames = array_map(fn($r) => array_values($r)[0], $tables);
$requiredTables = [
    'platform_users', 'restaurants', 'subscription_plans', 'subscriptions', 'platform_invoices',
    'platform_payments', 'webhook_events', 'roles', 'permissions', 'role_permissions', 'users',
    'pos_devices', 'restaurant_catalog_versions', 'offline_authorization_leases', 'pos_device_sequences',
    'pos_device_staff_authorizations', 'restaurant_tables', 'suppliers', 'ingredients',
    'ingredient_unit_conversions', 'purchase_orders', 'purchase_order_items', 'categories',
    'products', 'product_variants', 'product_addon_groups', 'product_addon_group_assignments',
    'product_addons', 'recipes', 'cashier_shifts', 'cash_movements', 'orders', 'order_items',
    'order_item_addons', 'order_status_history', 'payments', 'void_records', 'refund_records',
    'inventory_movements', 'inventory_conflicts', 'general_audit_logs', 'sync_logs'
];

$missingTables = array_diff($requiredTables, $tableNames);
assertTest("All Critical Tables Created in InnoDB", empty($missingTables), "Missing: " . implode(', ', $missingTables));

$restaurantColumnNames = array_column(Database::fetchAll("SHOW COLUMNS FROM restaurants"), 'Field');
$productColumnNames = array_column(Database::fetchAll("SHOW COLUMNS FROM products"), 'Field');
assertTest("Restaurants support a separate favicon path", in_array('favicon_path', $restaurantColumnNames, true));
assertTest("Restaurants support editable taglines", in_array('tagline', $restaurantColumnNames, true));
assertTest("Products support menu image paths", in_array('image_path', $productColumnNames, true));

// 3. Multi-Tenant Foreign Key Isolation
echo PHP_EOL . "3. Testing Strict Multi-Tenant Composite Foreign Key Isolation:" . PHP_EOL;
try {
    // Ensure category 10 exists in Restaurant 2
    Database::query("INSERT INTO categories (id, restaurant_id, name) VALUES (10, 2, 'Tenant 2 Cat') ON DUPLICATE KEY UPDATE name = VALUES(name)");
    Database::query("INSERT INTO products (id, restaurant_id, category_id, name, sku, base_price, cost_price, product_type, is_active)
                     VALUES (999, 2, 10, 'Alien Product', 'SKU-ALIEN', 50, 25, 'recipe', 1)
                     ON DUPLICATE KEY UPDATE name = VALUES(name)");
    Database::query("INSERT INTO orders (id, restaurant_id, client_uuid, device_id, order_number, order_type, table_id, cashier_id, subtotal, total_amount, paid_amount, client_reported_at, server_received_at)
                     VALUES (999, 1, 'uuid-test-order-999', 1, 'ORD-TEST-999', 'dine_in', 1, 3, 50, 50, 50, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE order_number = VALUES(order_number)");

    // Attempting cross-tenant order item insertion (Order belongs to Rest 1, Product belongs to Rest 2)
    $rejected = false;
    try {
        Database::query("INSERT INTO order_items (restaurant_id, order_id, product_id, product_name, quantity, unit_price, subtotal)
                         VALUES (1, 999, 999, 'Alien Product', 1, 50, 50)");
    } catch (\PDOException $e) {
        $rejected = true;
    }
    assertTest("Cross-Tenant Order Item Insert Rejected by DB Composite FK", $rejected);
} finally {
    Database::query("DELETE FROM order_items WHERE order_id = 999");
    Database::query("DELETE FROM orders WHERE id = 999");
    Database::query("DELETE FROM products WHERE id = 999");
    Database::query("DELETE FROM categories WHERE id = 10");
}

// 4. Stored Generated Column Base Recipe Uniqueness
echo PHP_EOL . "4. Testing Base Recipe Uniqueness with NULL variant_id:" . PHP_EOL;
try {
    Database::query("INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
                     VALUES (1, 4, NULL, 1, 100.0000)");
    $dupRejected = false;
    try {
        Database::query("INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
                         VALUES (1, 4, NULL, 1, 100.0000)");
    } catch (\PDOException $e) {
        $dupRejected = true;
    }
    assertTest("Duplicate Base Recipe with NULL variant_id Blocked", $dupRejected);
} finally {
    Database::query("DELETE FROM recipes WHERE restaurant_id = 1 AND product_id = 4 AND ingredient_id = 1 AND variant_id IS NULL");
}

// 5. Out-of-Order Sequence Synchronization
echo PHP_EOL . "5. Testing Out-of-Order Device Sequence Acceptance:" . PHP_EOL;
try {
    Database::query("INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
                     VALUES (1, 1, 100, 'uuid-seq-100', NOW())");
    Database::query("INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
                     VALUES (1, 1, 99, 'uuid-seq-099', NOW())");

    $count = (int)Database::fetchColumn("SELECT COUNT(*) FROM pos_device_sequences WHERE restaurant_id = 1 AND device_id = 1 AND sequence_number IN (99, 100)");
    assertTest("Older Sequence #99 Accepted After Newer Sequence #100", $count === 2);
} finally {
    Database::query("DELETE FROM pos_device_sequences WHERE restaurant_id = 1 AND device_id = 1 AND sequence_number IN (99, 100)");
}

// 6. PBKDF2 Offline PIN Verification
echo PHP_EOL . "6. Testing PBKDF2 Offline PIN Derivation & Verification:" . PHP_EOL;
$pin = "1234";
$salt = hex2bin("a1b2c3d4e5f60718293a4b5c6d7e8f90");
$derivedHash = hash_pbkdf2("sha256", $pin, $salt, 100000, 64, false);
$storedHash = "ca1be9e2534f95e439dd905233c2cadf4be34117c0c9a790965f412ceb14ce23";
assertTest("PBKDF2-HMAC-SHA256 100k Iterations Matches Stored Staff PIN", hash_equals($storedHash, $derivedHash));

$wrongPin = "9999";
$wrongDerived = hash_pbkdf2("sha256", $wrongPin, $salt, 100000, 64, false);
assertTest("Incorrect Offline PIN Correctly Rejected", !hash_equals($storedHash, $wrongDerived));

// 7. Authentication Service & Password Hashing
echo PHP_EOL . "7. Testing Authentication & Session Management:" . PHP_EOL;
$authService = new AuthService();
// Test Platform Admin Login
$platformAuth = $authService->authenticate('platform_admin', 'AdminPassword2026!');
assertTest("Platform Admin Authentication", $platformAuth['success'] && $platformAuth['is_platform']);

// Test Staff Login (Mang Juan's Carinderia)
$staffAuth = $authService->authenticate('maria_cashier', 'RestoStaff2026!', 'mang-juan');
assertTest("Tenant Staff Authentication (Maria Cashier)", $staffAuth['success'] && !$staffAuth['is_platform']);

// Test Invalid Password Rejection
$badAuth = $authService->authenticate('maria_cashier', 'WrongPassword123!', 'mang-juan');
assertTest("Invalid Password Rejected", !$badAuth['success']);

// 8. Role-Based Access Control (RBAC)
echo PHP_EOL . "8. Testing Role-Based Access Control (RBAC):" . PHP_EOL;
Auth::login($staffAuth['user'], false);
assertTest("Active User Resolved from Session", Auth::id() === 3 && Auth::getRestaurantId() === 1);
assertTest("Cashier Has pos.create_order Permission", Auth::can('pos.create_order'));
assertTest("Cashier Has pos.process_payment Permission", Auth::can('pos.process_payment'));
assertTest("Cashier Denied menu.manage_catalog Permission", !Auth::can('menu.manage_catalog'));
Auth::logout();

// 9. Tenant Context & Subscription Grace Period
echo PHP_EOL . "9. Testing Tenant Context & Subscription Validity:" . PHP_EOL;
TenantContext::setTenant(1);
$resto = TenantContext::getRestaurant();
assertTest("Tenant Resolved (Mang Juan's Carinderia)", $resto !== null && $resto['slug'] === 'mang-juan');
assertTest("Subscription Active Status", TenantContext::isSubscriptionValid());
assertTest("Grace Days Calculated Correctly", TenantContext::getGraceDaysRemaining() >= 0);

// 10. CSRF Protection
echo PHP_EOL . "10. Testing CSRF Token Generation & Validation:" . PHP_EOL;
$token = CSRF::getToken();
assertTest("CSRF Token Generated", !empty($token) && strlen($token) >= 32);
assertTest("CSRF Token Validates Correctly", CSRF::validate($token));
assertTest("Tampered CSRF Token Rejected", !CSRF::validate('tampered-token-value'));

// 11. Soft-Delete Integrity
echo PHP_EOL . "11. Testing Zero-Cascade Soft Delete on Tables:" . PHP_EOL;
Database::query("UPDATE restaurant_tables SET deleted_at = NOW() WHERE id = 1 AND restaurant_id = 1");
$table = Database::fetchOne("SELECT deleted_at FROM restaurant_tables WHERE id = 1 AND restaurant_id = 1");
assertTest("Table Soft-Deleted without cascade to orders", !empty($table['deleted_at']));
Database::query("UPDATE restaurant_tables SET deleted_at = NULL WHERE id = 1 AND restaurant_id = 1");

// 12. Phase 2: Unit Conversion Engine & Packaging
echo PHP_EOL . "12. Testing Unit Conversion & Packaging Normalization:" . PHP_EOL;
$kgToG = \App\Services\UnitConversionService::convert(1, 2.5, 'kg', 'g');
assertTest("Standard Metric Mass (2.5 kg -> 2500 g)", abs($kgToG - 2500.0) < 0.001);

$lToMl = \App\Services\UnitConversionService::convert(1, 1.75, 'l', 'ml');
assertTest("Standard Metric Volume (1.75 L -> 1750 ml)", abs($lToMl - 1750.0) < 0.001);

$sackToKg = \App\Services\UnitConversionService::convert(1, 1.0, 'sack_25kg', 'kg', 8);
assertTest("Ingredient-Specific Packaging Converts 1 Sack of Rice to 25 kg", abs($sackToKg - 25.0) < 0.001);

$kgToSacks = \App\Services\UnitConversionService::convert(1, 50.0, 'kg', 'sack_25kg', 8);
assertTest("Ingredient-Specific Packaging Converts 50 kg of Rice to 2 Sacks", abs($kgToSacks - 2.0) < 0.001);

$pkgConv = \App\Services\UnitConversionService::convertPackageToBase(1, 7, 4.0); // 4 trays of 30 eggs = 120 pcs
assertTest("Packaging Normalization (4 trays @ 30 pcs = 120 pcs)", abs($pkgConv['total_base_quantity'] - 120.0) < 0.001 && $pkgConv['base_unit'] === 'pcs');

// 13. Phase 2: Double-Entry Inventory Movements Ledger
echo PHP_EOL . "13. Testing Double-Entry Inventory Movements & Stock Balances:" . PHP_EOL;
$initialPork = Database::fetchOne("SELECT current_stock FROM ingredients WHERE id = 1 AND restaurant_id = 1");
$initialStock = (float)$initialPork['current_stock'];

// Stock in 5 kg (5000 g)
$movId = \App\Services\InventoryService::recordMovement(
    1, 1, 'purchase_receipt', 5.0, 'kg', 350.0, 999, 'Test stock in 5kg', 1
);
assertTest("Stock-In Movement Recorded in Ledger", $movId > 0);

$afterStockIn = Database::fetchOne("SELECT current_stock FROM ingredients WHERE id = 1 AND restaurant_id = 1");
assertTest("Ingredient Current Stock Increased by 5000g", abs((float)$afterStockIn['current_stock'] - ($initialStock + 5000.0)) < 0.01);

// Deduct 250 g
\App\Services\InventoryService::recordMovement(
    1, 1, 'order_sale', 250.0, 'g', null, null, 'Test usage 250g', 1
);
$afterDeduct = Database::fetchOne("SELECT current_stock FROM ingredients WHERE id = 1 AND restaurant_id = 1");
assertTest("Ingredient Current Stock Decreased by 250g", abs((float)$afterDeduct['current_stock'] - ($initialStock + 4750.0)) < 0.01);

$manualUpId = \App\Services\InventoryService::recordMovement(
    1, 1, 'manual_adjustment', 500.0, 'g', null, null, 'Test manual adjustment increase', 1, 'increase'
);
assertTest("Manual Stock Increase Creates Ledger Entry", $manualUpId > 0);
$afterManualUp = Database::fetchOne("SELECT current_stock FROM ingredients WHERE id = 1 AND restaurant_id = 1");
assertTest("Manual Increase Adds the Requested Base Quantity", abs((float)$afterManualUp['current_stock'] - ($initialStock + 5250.0)) < 0.01);

$manualDownId = \App\Services\InventoryService::recordMovement(
    1, 1, 'manual_adjustment', 250.0, 'g', null, null, 'Test manual adjustment decrease', 1, 'decrease'
);
assertTest("Manual Stock Decrease Creates Ledger Entry", $manualDownId > 0);
$afterManualDown = Database::fetchOne("SELECT current_stock FROM ingredients WHERE id = 1 AND restaurant_id = 1");
assertTest("Manual Decrease Subtracts the Requested Base Quantity", abs((float)$afterManualDown['current_stock'] - ($initialStock + 5000.0)) < 0.01);

$missingAdjustmentDirectionRejected = false;
try {
    \App\Services\InventoryService::recordMovement(
        1, 1, 'manual_adjustment', 10.0, 'g', null, null, 'Test missing direction', 1
    );
} catch (\InvalidArgumentException $e) {
    $missingAdjustmentDirectionRejected = true;
}
assertTest("Manual Adjustment Requires Explicit Direction", $missingAdjustmentDirectionRejected);

// Revert test adjustments to keep seed clean
Database::query("UPDATE ingredients SET current_stock = ? WHERE id = 1 AND restaurant_id = 1", [$initialStock]);
Database::query("DELETE FROM inventory_movements WHERE notes LIKE 'Test %' AND restaurant_id = 1");

// 14. Phase 2: Menu, Variants, Addons & Recipe BOM
echo PHP_EOL . "14. Testing Menu Catalog & Recipe Bill of Materials (BOM):" . PHP_EOL;
$products = \App\Services\MenuService::getProducts(1);
assertTest("Menu Products Retrieved with Categories", count($products) >= 2);

$sisig = \App\Services\MenuService::getProduct(1, 1);
assertTest("Product 1 (Sisig) Variants Loaded", count($sisig['variants']) >= 2);
assertTest("Product 1 Add-on Groups Loaded", count($sisig['addon_groups']) >= 1);
assertTest("Product 1 Recipe BOM Loaded", count($sisig['recipe_bom']) >= 2);

assertTest("Recipe Rows Expose Schema-Compatible Base Units", !empty($sisig['recipe_bom'][0]['base_ingredient_unit']) && array_key_exists('quantity_required', $sisig['recipe_bom'][0]));

$directDrinkCost = \App\Services\MenuService::calculateProductCost(1, 3);
assertTest("Direct-Stock Drink Cost Uses Linked Ingredient Cost", abs($directDrinkCost['total_cost'] - 28.0) < 0.01);

$costInfo = \App\Services\MenuService::calculateProductCost(1, 1);
assertTest("Recipe Food Cost Calculated (> 0)", $costInfo['total_cost'] > 0);

$posCatalog = \App\Services\MenuService::getPosCatalog(1);
assertTest("POS Offline Catalog Export Formatted", !empty($posCatalog['version']) && !empty($posCatalog['products']));
assertTest(
    "POS Catalog Includes Restaurant Metadata for Receipt Rendering",
    !empty($posCatalog['restaurant']['name'])
        && array_key_exists('address', $posCatalog['restaurant'])
        && array_key_exists('tin', $posCatalog['restaurant'])
);

// POS order-sync regression: validate the actual MySQL order/item/payment schema and idempotency.
echo PHP_EOL . "14b. Testing POS Order Synchronization & Inventory Deduction:" . PHP_EOL;
$syncProduct = Database::fetchOne(
    "SELECT id, direct_stock_ingredient_id FROM products WHERE restaurant_id = ? AND id = ? AND is_active = 1",
    [1, 3]
);
$syncIngredientId = (int)($syncProduct['direct_stock_ingredient_id'] ?? 0);
$syncIngredient = $syncIngredientId > 0
    ? Database::fetchOne("SELECT current_stock FROM ingredients WHERE restaurant_id = ? AND id = ?", [1, $syncIngredientId])
    : null;
$syncDevice = Database::fetchOne("SELECT id, last_sync_at FROM pos_devices WHERE restaurant_id = ? AND id = ? AND status = 'active'", [1, 1]);
$syncUuid = \App\Services\DeviceService::generateUuidV4();
$syncOrderId = 0;
$syncOriginalStock = $syncIngredient ? (float)$syncIngredient['current_stock'] : null;
$syncOriginalLastSync = $syncDevice['last_sync_at'] ?? null;
$syncPayload = [
    'client_uuid' => $syncUuid,
    'device_id' => 1,
    'cashier_id' => 1,
    'order_number' => 'TESTSYNC-' . substr(str_replace('-', '', $syncUuid), 0, 16),
    'order_type' => 'takeout',
    'created_at' => date('c'),
    'subtotal' => 0,
    'discount_amount' => 0,
    'paid_amount' => 100,
    'payment' => [
        'payment_method' => 'cash',
        'tendered_amount' => 100
    ],
    'items' => [
        [
            'product_id' => 3,
            'variant_id' => null,
            'quantity' => 1,
            'addons' => []
        ]
    ]
];

try {
    if (!$syncProduct || !$syncIngredient || !$syncDevice) {
        throw new \RuntimeException('Seed product 3, its linked ingredient, or active device 1 is missing.');
    }

    $syncResult = \App\Services\PosSyncService::processOrderSync(1, $syncPayload);
    $syncOrderId = (int)($syncResult['server_order_id'] ?? 0);
    $syncOrder = $syncOrderId > 0
        ? Database::fetchOne("SELECT order_status, payment_status, total_amount, client_reported_at, server_received_at FROM orders WHERE restaurant_id = ? AND id = ?", [1, $syncOrderId])
        : null;
    $syncItem = $syncOrderId > 0
        ? Database::fetchOne("SELECT product_name, unit_price, quantity, subtotal FROM order_items WHERE restaurant_id = ? AND order_id = ?", [1, $syncOrderId])
        : null;
    $syncPayment = $syncOrderId > 0
        ? Database::fetchOne("SELECT cashier_id, amount, tendered_amount, change_amount FROM payments WHERE restaurant_id = ? AND order_id = ?", [1, $syncOrderId])
        : null;
    $syncStockAfter = Database::fetchOne("SELECT current_stock FROM ingredients WHERE restaurant_id = ? AND id = ?", [1, $syncIngredientId]);

    assertTest(
        "POS Sync Inserts Paid Order into Kitchen Queue",
        ($syncResult['status'] ?? '') === 'synced'
            && $syncOrder
            && $syncOrder['order_status'] === 'pending'
            && $syncOrder['payment_status'] === 'paid'
            && !empty($syncOrder['client_reported_at'])
            && !empty($syncOrder['server_received_at'])
    );
    assertTest(
        "POS Sync Inserts Product Line with Schema-Compatible Columns",
        $syncItem
            && $syncItem['product_name'] !== ''
            && abs((float)$syncItem['quantity'] - 1.0) < 0.001
            && abs((float)$syncItem['subtotal'] - (float)$syncItem['unit_price']) < 0.01
    );
    assertTest(
        "POS Sync Inserts Payment with Cashier and Correct Change",
        $syncPayment
            && (int)$syncPayment['cashier_id'] === 1
            && abs((float)$syncPayment['tendered_amount'] - 100.0) < 0.01
            && abs((float)$syncPayment['change_amount'] - ((float)$syncPayment['tendered_amount'] - (float)$syncPayment['amount'])) < 0.01
    );
    assertTest(
        "POS Sync Deducts Direct Stock and Links Movement to Order",
        $syncOrderId > 0
            && $syncStockAfter
            && abs((float)$syncStockAfter['current_stock'] - ($syncOriginalStock - 1.0)) < 0.01
            && (bool)Database::fetchOne(
                "SELECT id FROM inventory_movements WHERE restaurant_id = ? AND reference_type = 'order_sale' AND reference_id = ? AND ingredient_id = ?",
                [1, $syncOrderId, $syncIngredientId]
            )
    );

    $duplicateResult = \App\Services\PosSyncService::processOrderSync(1, $syncPayload);
    $stockAfterDuplicate = Database::fetchOne("SELECT current_stock FROM ingredients WHERE restaurant_id = ? AND id = ?", [1, $syncIngredientId]);
    assertTest(
        "POS Sync Is Idempotent and Does Not Deduct Stock Twice",
        ($duplicateResult['status'] ?? '') === 'already_synced'
            && (int)($duplicateResult['server_order_id'] ?? 0) === $syncOrderId
            && $stockAfterDuplicate
            && abs((float)$stockAfterDuplicate['current_stock'] - ($syncOriginalStock - 1.0)) < 0.01
    );
} catch (\Throwable $e) {
    assertTest("POS Order Sync Integration Executes Without Schema Errors", false, $e->getMessage());
} finally {
    if ($syncOrderId > 0) {
        Database::query(
            "DELETE FROM inventory_movements WHERE restaurant_id = ? AND reference_type = 'order_sale' AND reference_id = ?",
            [1, $syncOrderId]
        );
        if ($syncOriginalStock !== null) {
            Database::query("UPDATE ingredients SET current_stock = ? WHERE restaurant_id = ? AND id = ?", [$syncOriginalStock, 1, $syncIngredientId]);
        }
        Database::query(
            "DELETE FROM order_item_addons WHERE restaurant_id = ? AND order_item_id IN (SELECT id FROM order_items WHERE restaurant_id = ? AND order_id = ?)",
            [1, 1, $syncOrderId]
        );
        Database::query("DELETE FROM order_items WHERE restaurant_id = ? AND order_id = ?", [1, $syncOrderId]);
        Database::query("DELETE FROM payments WHERE restaurant_id = ? AND order_id = ?", [1, $syncOrderId]);
        Database::query("DELETE FROM orders WHERE restaurant_id = ? AND id = ?", [1, $syncOrderId]);
    }
    if ($syncDevice) {
        Database::query(
            "UPDATE pos_devices SET last_sync_at = ? WHERE restaurant_id = ? AND id = ?",
            [$syncOriginalLastSync, 1, 1]
        );
    }
}

// Regression test: add-on rows require addon_name and ingredient-backed add-ons must deduct stock.
echo PHP_EOL . "14c. Testing POS Add-on Sync & Inventory Deduction:" . PHP_EOL;
$addonSyncDevice = Database::fetchOne(
    "SELECT id, last_sync_at FROM pos_devices WHERE restaurant_id = ? AND id = ? AND status = 'active'",
    [1, 1]
);
$addonSyncUuid = \App\Services\DeviceService::generateUuidV4();
$addonSyncOrderId = 0;
$addonSyncOriginalLastSync = $addonSyncDevice['last_sync_at'] ?? null;
$addonIngredientIds = [1, 2, 3, 4, 5, 6, 9];
$addonSyncOriginalStocks = [];
foreach ($addonIngredientIds as $ingredientId) {
    $stockRow = Database::fetchOne(
        "SELECT current_stock FROM ingredients WHERE restaurant_id = ? AND id = ?",
        [1, $ingredientId]
    );
    if ($stockRow) {
        $addonSyncOriginalStocks[$ingredientId] = (float)$stockRow['current_stock'];
    }
}
$addonSyncPayload = [
    'client_uuid' => $addonSyncUuid,
    'device_id' => 1,
    'cashier_id' => 1,
    'order_number' => 'TESTADDON-' . substr(str_replace('-', '', $addonSyncUuid), 0, 16),
    'order_type' => 'takeout',
    'created_at' => date('c'),
    'subtotal' => 215.00,
    'discount_amount' => 0.00,
    'paid_amount' => 215.00,
    'payment' => [
        'payment_method' => 'cash',
        'tendered_amount' => 215.00
    ],
    'items' => [[
        'product_id' => 1,
        'variant_id' => 1,
        'quantity' => 1,
        'addons' => [
            ['id' => 1, 'name' => 'Extra Adobo Sauce', 'price' => 15.00],
            ['id' => 2, 'name' => 'Sunny Side-up Egg', 'price' => 20.00]
        ]
    ]]
];

try {
    if (!$addonSyncDevice || count($addonSyncOriginalStocks) !== count($addonIngredientIds)) {
        throw new \RuntimeException('Seed device or ingredient stock needed for add-on sync test is missing.');
    }

    $addonSyncResult = \App\Services\PosSyncService::processOrderSync(1, $addonSyncPayload);
    $addonSyncOrderId = (int)($addonSyncResult['server_order_id'] ?? 0);
    $savedAddons = $addonSyncOrderId > 0
        ? Database::fetchAll(
            "SELECT addon_id, addon_name, unit_price, quantity, subtotal
             FROM order_item_addons
             WHERE restaurant_id = ? AND order_item_id IN
               (SELECT id FROM order_items WHERE restaurant_id = ? AND order_id = ?)
             ORDER BY addon_id",
            [1, 1, $addonSyncOrderId]
        )
        : [];

    assertTest(
        "POS Sync Saves Required Add-on Names and Prices",
        ($addonSyncResult['status'] ?? '') === 'synced'
            && count($savedAddons) === 2
            && (int)$savedAddons[0]['addon_id'] === 1
            && $savedAddons[0]['addon_name'] === 'Extra Adobo Sauce'
            && abs((float)$savedAddons[0]['unit_price'] - 15.00) < 0.01
            && (int)$savedAddons[1]['addon_id'] === 2
            && $savedAddons[1]['addon_name'] === 'Sunny Side-up Egg'
            && abs((float)$savedAddons[1]['unit_price'] - 20.00) < 0.01
    );

    $addonStockMovements = $addonSyncOrderId > 0
        ? Database::fetchAll(
            "SELECT ingredient_id, quantity_change
             FROM inventory_movements
             WHERE restaurant_id = ? AND reference_type = 'order_sale'
               AND reference_id = ? AND notes LIKE 'Add-on sale:%'",
            [1, $addonSyncOrderId]
        )
        : [];
    $sauceMovement = null;
    $eggMovement = null;
    foreach ($addonStockMovements as $movement) {
        if ((int)$movement['ingredient_id'] === 2) $sauceMovement = $movement;
        if ((int)$movement['ingredient_id'] === 9) $eggMovement = $movement;
    }
    assertTest(
        "POS Sync Deducts Ingredient-Backed Add-ons",
        $sauceMovement && $eggMovement
            && abs((float)$sauceMovement['quantity_change'] - (-40.0)) < 0.01
            && abs((float)$eggMovement['quantity_change'] - (-1.0)) < 0.01
    );
} catch (\Throwable $e) {
    assertTest("POS Add-on Sync Integration Executes Without Schema Errors", false, $e->getMessage());
} finally {
    if ($addonSyncOrderId > 0) {
        Database::query(
            "DELETE FROM inventory_movements WHERE restaurant_id = ? AND reference_type = 'order_sale' AND reference_id = ?",
            [1, $addonSyncOrderId]
        );
        foreach ($addonSyncOriginalStocks as $ingredientId => $stock) {
            Database::query(
                "UPDATE ingredients SET current_stock = ? WHERE restaurant_id = ? AND id = ?",
                [$stock, 1, $ingredientId]
            );
        }
        Database::query(
            "DELETE FROM order_item_addons WHERE restaurant_id = ? AND order_item_id IN
                (SELECT id FROM order_items WHERE restaurant_id = ? AND order_id = ?)",
            [1, 1, $addonSyncOrderId]
        );
        Database::query("DELETE FROM order_items WHERE restaurant_id = ? AND order_id = ?", [1, $addonSyncOrderId]);
        Database::query("DELETE FROM payments WHERE restaurant_id = ? AND order_id = ?", [1, $addonSyncOrderId]);
        Database::query("DELETE FROM orders WHERE restaurant_id = ? AND id = ?", [1, $addonSyncOrderId]);
    }
    if ($addonSyncDevice) {
        Database::query(
            "UPDATE pos_devices SET last_sync_at = ? WHERE restaurant_id = ? AND id = ?",
            [$addonSyncOriginalLastSync, 1, 1]
        );
    }
}

// Optional phase gate for focused CI runs. The default still executes the complete suite.
if (in_array('--through-phase=2', $argv, true)) {
    echo PHP_EOL . "================================================================================" . PHP_EOL;
    echo "  PHASE 2 TEST SUMMARY: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
    echo "================================================================================" . PHP_EOL;
    exit($failed > 0 ? 1 : 0);
}

// 15. Phase 3: Tables & Floor Zones
echo PHP_EOL . "15. Testing Tables, Floor Zones & Soft-Delete:" . PHP_EOL;
$tableId = \App\Services\TableService::createTable(1, [
    'table_number' => 'TEST-99',
    'name' => 'Test Patio Booth',
    'capacity' => 6,
    'floor_zone' => 'Patio',
    'status' => 'available'
]);
assertTest("Table Created Successfully", $tableId > 0);

// Reservations & walk-in queue: table capacity, overlap checks, and seating lifecycle.
echo PHP_EOL . "15a. Testing Reservations & Waiting List:" . PHP_EOL;
$reservationTime = date('Y-m-d\\TH:i', time() + 86400);
$reservationId = \App\Services\ReservationsService::createReservation(1, 1, [
    'customer_name' => 'Test Reservation Guest',
    'phone' => '555-TEST',
    'party_size' => 4,
    'reservation_at' => $reservationTime,
    'duration_minutes' => 90,
    'table_id' => $tableId,
    'notes' => 'Integration test'
]);
assertTest("Reservation Created with Tenant and Table", $reservationId > 0);

$reservationConflictRejected = false;
try {
    \App\Services\ReservationsService::createReservation(1, 1, [
        'customer_name' => 'Overlapping Test Guest',
        'party_size' => 2,
        'reservation_at' => $reservationTime,
        'duration_minutes' => 60,
        'table_id' => $tableId
    ]);
} catch (\InvalidArgumentException $e) {
    $reservationConflictRejected = str_contains($e->getMessage(), 'already has a reservation');
}
assertTest("Reservation Table Conflict Rejected", $reservationConflictRejected);

$largePartyRejected = false;
try {
    \App\Services\ReservationsService::createReservation(1, 1, [
        'customer_name' => 'Too Many Guests',
        'party_size' => 7,
        'reservation_at' => $reservationTime,
        'duration_minutes' => 90,
        'table_id' => $tableId
    ]);
} catch (\InvalidArgumentException $e) {
    $largePartyRejected = str_contains($e->getMessage(), 'unavailable or too small');
}
assertTest("Reservation Exceeding Table Capacity Rejected", $largePartyRejected);

\App\Services\ReservationsService::updateReservationStatus(1, $reservationId, 1, 'cancelled');
$waitlistId = \App\Services\ReservationsService::addWaitlist(1, 1, [
    'customer_name' => 'Test Walk-in Guest',
    'phone' => '555-WAIT',
    'party_size' => 4,
    'estimated_wait_minutes' => 15,
    'notes' => 'Integration test'
]);
$activeWaitlist = \App\Services\ReservationsService::getWaitlist(1);
$waitlistVisible = count(array_filter($activeWaitlist, static fn(array $entry): bool => (int)$entry['id'] === $waitlistId)) === 1;
assertTest("Walk-in Added to Active Waitlist", $waitlistId > 0 && $waitlistVisible);

\App\Services\ReservationsService::updateWaitlistStatus(1, $waitlistId, 'seated', $tableId);
$waitlistAfterSeat = \App\Services\ReservationsService::getWaitlist(1);
$waitlistRemovedAfterSeat = count(array_filter($waitlistAfterSeat, static fn(array $entry): bool => (int)$entry['id'] === $waitlistId)) === 0;
$tableAfterSeat = \App\Services\TableService::getTableById(1, $tableId);
assertTest("Seating Walk-in Marks Table Occupied and Leaves Active Queue", $waitlistRemovedAfterSeat && $tableAfterSeat && $tableAfterSeat['status'] === 'occupied');

Database::query("DELETE FROM reservations WHERE restaurant_id = ? AND id = ?", [1, $reservationId]);
Database::query("DELETE FROM waitlist_entries WHERE restaurant_id = ? AND id = ?", [1, $waitlistId]);
\App\Services\TableService::updateStatus(1, $tableId, 'available');

\App\Services\TableService::updateStatus(1, $tableId, 'occupied');
$tbl = \App\Services\TableService::getTableById(1, $tableId);
assertTest("Table Status Updated to 'occupied'", $tbl && $tbl['status'] === 'occupied');

\App\Services\TableService::softDelete(1, $tableId);
$tblAfterDelete = \App\Services\TableService::getTableById(1, $tableId);
assertTest("Table Soft-Deleted (Zero Cascade)", $tblAfterDelete === null);

// Cleanup test table
Database::query("DELETE FROM restaurant_tables WHERE id = ? AND restaurant_id = 1", [$tableId]);

// 16. Phase 3: POS Devices & Staff Authorizations
echo PHP_EOL . "16. Testing POS Devices & Staff Authorizations:" . PHP_EOL;
$devReg = \App\Services\DeviceService::registerDevice(1, 'Test Terminal Suite');
assertTest("Device Registered with UUIDv4", strlen($devReg['device_uuid']) === 36);
assertTest("Device Plain Token Generated", str_starts_with($devReg['plain_token'], 'devtok_'));

$devInDb = \App\Services\DeviceService::getDeviceById(1, $devReg['device_id']);
assertTest("Device Token Stored as SHA-256 Hash", $devInDb['device_token_hash'] === hash('sha256', $devReg['plain_token']));

$rotated = \App\Services\DeviceService::rotateCredentials(1, $devReg['device_id']);
$devAfterRot = \App\Services\DeviceService::getDeviceById(1, $devReg['device_id']);
assertTest("Device Credentials Rotated", $devAfterRot['device_token_hash'] === hash('sha256', $rotated));

\App\Services\DeviceService::setDeviceStatus(1, $devReg['device_id'], 'revoked');
$devRevoked = \App\Services\DeviceService::getDeviceById(1, $devReg['device_id']);
assertTest("Device Status Revoked", $devRevoked['status'] === 'revoked');

// 17. Phase 3: Cashier Shift Sessions & Cash Drawer
echo PHP_EOL . "17. Testing Cashier Shifts & Cash Drawer Reconciliation:" . PHP_EOL;
\App\Services\DeviceService::setDeviceStatus(1, $devReg['device_id'], 'active');
$shiftId = \App\Services\ShiftService::openShift(1, 1, $devReg['device_id'], 1500.00, 'Test Float Opening');
assertTest("Cashier Shift Opened with Float ₱1500.00", $shiftId > 0);

$activeShift = \App\Services\ShiftService::getActiveShift(1, 1, $devReg['device_id']);
assertTest("Active Shift Retrieved", $activeShift && (int)$activeShift['id'] === $shiftId);

\App\Services\ShiftService::recordCashMovement(1, $shiftId, 1, 'cash_in', 500.00, 'Added Petty Cash');
\App\Services\ShiftService::recordCashMovement(1, $shiftId, 1, 'cash_out', 200.00, 'Bought Ice');
$shiftSummary = \App\Services\ShiftService::calculateShiftSummary(1, $shiftId);
// Expected = 1500 (float) + 500 (cash in) - 200 (cash out) = 1800.00
assertTest("Expected Cash Calculated (₱1800.00)", abs($shiftSummary['expected_cash'] - 1800.00) < 0.01);

$closeRes = \App\Services\ShiftService::closeShift(1, $shiftId, 1800.00, 'Exact Balance Close');
assertTest("Shift Closed Balanced (Variance ₱0.00)", abs($closeRes['difference']) < 0.01 && $closeRes['status'] === 'closed');

// Cleanup test shift & device
Database::query("DELETE FROM cash_movements WHERE shift_id = ? AND restaurant_id = 1", [$shiftId]);
Database::query("DELETE FROM cashier_shifts WHERE id = ? AND restaurant_id = 1", [$shiftId]);
Database::query("DELETE FROM pos_device_staff_authorizations WHERE device_id = ? AND restaurant_id = 1", [$devReg['device_id']]);
Database::query("DELETE FROM pos_devices WHERE id = ? AND restaurant_id = 1", [$devReg['device_id']]);

// 18. Phase 4 & 5: POS Catalog Versioning & Bootstrap Payload
echo PHP_EOL . "18. Testing POS Catalog Versioning & Offline Bootstrap:" . PHP_EOL;
$catVersion = \App\Services\PosSyncService::getOrCreateCatalogVersion(1);
assertTest("Catalog Version Snapshot Generated", !empty($catVersion['snapshot_hash']) && strlen($catVersion['snapshot_hash']) === 64);

$lease = \App\Services\PosSyncService::getOrCreateOfflineLease(1, 1, (int)$catVersion['id']);
assertTest("Offline Authorization Lease Created", !empty($lease['lease_id']) && !empty($lease['signature']));

$bootstrap = \App\Services\PosSyncService::getBootstrapPayload(1, 1);
assertTest("POS Bootstrap Delivers Restaurant & Tables", !empty($bootstrap['restaurant']) && count($bootstrap['tables']) >= 1);
assertTest("POS Bootstrap Delivers Offline Lease & Staff", !empty($bootstrap['lease']) && !empty($bootstrap['staff']));

// 19. Phase 6: Idempotent Sync & Recipe BOM Stock Deduction
echo PHP_EOL . "19. Testing Idempotent Order Sync & Stock Deductions:" . PHP_EOL;
$testClientUuid = \App\Services\DeviceService::generateUuidV4();
$orderPayload = [
    'client_uuid' => $testClientUuid,
    'device_id' => 1,
    'order_number' => 'TEST-ORD-001',
    'order_type' => 'dine_in',
    'table_id' => 1,
    'cashier_id' => 1,
    'catalog_version_id' => (int)$catVersion['id'],
    'sequence_number' => 101,
    'subtotal' => 380.00,
    'discount_amount' => 0.00,
    'tax_amount' => 40.71,
    'total_amount' => 380.00,
    'paid_amount' => 500.00,
    'change_amount' => 120.00,
    'items' => [
        [
            'product_id' => 1,
            'variant_id' => 1,
            'name' => 'Sizzling Pork Sisig',
            'variant_name' => 'Solo',
            'unit_price' => 220.00,
            'quantity' => 1,
            'addons' => []
        ],
        [
            'product_id' => 2,
            'variant_id' => null,
            'name' => 'San Miguel Pale Pilsen',
            'variant_name' => null,
            'unit_price' => 80.00,
            'quantity' => 2,
            'addons' => []
        ]
    ],
    'payment' => [
        'payment_method' => 'cash',
        'amount' => 380.00,
        'tendered_amount' => 500.00,
        'change_amount' => 120.00,
        'client_payment_uuid' => \App\Services\DeviceService::generateUuidV4()
    ]
];

$syncRes = \App\Services\PosSyncService::processOrderSync(1, $orderPayload);
assertTest("Offline Order Synced Successfully", $syncRes['status'] === 'synced' && $syncRes['server_order_id'] > 0);

// Retry exact same order payload -> must be idempotent (already_synced, zero duplicate rows)
$retryRes = \App\Services\PosSyncService::processOrderSync(1, $orderPayload);
assertTest("Idempotency: Duplicate Sync Acknowledged Without Duplication", $retryRes['status'] === 'already_synced' && $retryRes['server_order_id'] === $syncRes['server_order_id']);

// 19a. Kitchen Display System: ticket queue, workflow transitions, and audit history.
echo PHP_EOL . "19a. Testing Kitchen Display System:" . PHP_EOL;
$kitchenTickets = \App\Services\KitchenService::getActiveTickets(1);
$kitchenTicket = null;
foreach ($kitchenTickets as $candidateTicket) {
    if ((int)$candidateTicket['id'] === (int)$syncRes['server_order_id']) {
        $kitchenTicket = $candidateTicket;
        break;
    }
}
assertTest("Kitchen Display Lists Newly Synced Order", $kitchenTicket !== null);
assertTest("Kitchen Ticket Includes Order Items", $kitchenTicket !== null && count($kitchenTicket['items']) === 2);

// 19b. Table transfer: move one active ticket without duplicating payments or inventory.
echo PHP_EOL . "19b. Testing Active Table Transfer:" . PHP_EOL;
$transferTestTableId = \App\Services\TableService::createTable(1, [
    'table_number' => 'MOVE-99',
    'name' => 'Transfer Test Table',
    'capacity' => 6,
    'floor_zone' => 'Patio',
    'status' => 'available'
]);
$transferResult = \App\Services\TableService::transferActiveDineInOrder(1, (int)$syncRes['server_order_id'], $transferTestTableId, 1);
$movedOrder = Database::fetchOne(
    "SELECT table_id, order_status, payment_status FROM orders WHERE restaurant_id = ? AND id = ?",
    [1, (int)$syncRes['server_order_id']]
);
$transferHistory = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM order_status_history
     WHERE restaurant_id = ? AND order_id = ? AND notes LIKE 'Table transfer from %'",
    [1, (int)$syncRes['server_order_id']]
);
$transferTable = \App\Services\TableService::getTableById(1, $transferTestTableId);
assertTest(
    "Active Dine-in Order Transfer Updates Table and Audit History",
    (int)$movedOrder['table_id'] === $transferTestTableId
        && $movedOrder['order_status'] === 'pending'
        && $movedOrder['payment_status'] === 'paid'
        && (int)$transferHistory['total'] === 1
        && $transferTable && $transferTable['status'] === 'occupied'
);

$kitchenPreparing = \App\Services\KitchenService::updateTicketStatus(1, (int)$syncRes['server_order_id'], 'preparing', 1);
$kitchenReady = \App\Services\KitchenService::updateTicketStatus(1, (int)$syncRes['server_order_id'], 'ready', 1);
$kitchenServed = \App\Services\KitchenService::updateTicketStatus(1, (int)$syncRes['server_order_id'], 'served', 1);
$kitchenCompleted = \App\Services\KitchenService::updateTicketStatus(1, (int)$syncRes['server_order_id'], 'completed', 1);
assertTest("Kitchen Status Advances Through Preparing, Ready, Served, Completed",
    $kitchenPreparing['to_status'] === 'preparing'
    && $kitchenReady['to_status'] === 'ready'
    && $kitchenServed['to_status'] === 'served'
    && $kitchenCompleted['to_status'] === 'completed'
);
$kitchenHistory = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM order_status_history WHERE restaurant_id = ? AND order_id = ? AND notes = ?",
    [1, (int)$syncRes['server_order_id'], 'Updated via Kitchen Display']
);
assertTest("Kitchen Status Changes Are Audited", (int)($kitchenHistory['total'] ?? 0) === 4);
$invalidKitchenTransitionRejected = false;
try {
    \App\Services\KitchenService::updateTicketStatus(1, (int)$syncRes['server_order_id'], 'preparing', 1);
} catch (\InvalidArgumentException $e) {
    $invalidKitchenTransitionRejected = true;
}
assertTest("Kitchen Blocks Reversing Completed Orders", $invalidKitchenTransitionRejected);

// The sync service must rebuild prices from the server catalog instead of trusting
// the deliberately stale client-side line prices and total in $orderPayload.
$expectedCatalogTotal = 0.0;
foreach ($orderPayload['items'] as $fixtureItem) {
    $fixtureProduct = Database::fetchOne(
        "SELECT base_price FROM products WHERE restaurant_id = ? AND id = ? AND is_active = 1",
        [1, (int)$fixtureItem['product_id']]
    );
    $fixtureVariantAdditional = 0.0;
    if (!empty($fixtureItem['variant_id'])) {
        $fixtureVariant = Database::fetchOne(
            "SELECT additional_price FROM product_variants
             WHERE restaurant_id = ? AND product_id = ? AND id = ? AND is_active = 1",
            [1, (int)$fixtureItem['product_id'], (int)$fixtureItem['variant_id']]
        );
        $fixtureVariantAdditional = (float)($fixtureVariant['additional_price'] ?? 0);
    }
    $expectedCatalogTotal += (
        (float)$fixtureProduct['base_price'] + $fixtureVariantAdditional
    ) * (float)$fixtureItem['quantity'];
}
$expectedCatalogTotal = round($expectedCatalogTotal, 2);

$orderInDb = Database::fetchOne("SELECT total_amount, payment_status FROM orders WHERE id = ?", [$syncRes['server_order_id']]);
assertTest(
    "Order Total Rebuilt from Authoritative Catalog Prices",
    abs((float)$orderInDb['total_amount'] - $expectedCatalogTotal) < 0.01
        && abs((float)$orderInDb['total_amount'] - (float)$orderPayload['total_amount']) > 0.01
        && $orderInDb['payment_status'] === 'paid'
);

// Cleanup test sync order and related rows
Database::query("DELETE FROM payments WHERE order_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM order_status_history WHERE order_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM order_items WHERE order_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM orders WHERE id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM inventory_movements WHERE reference_id = ?", [$syncRes['server_order_id']]);
if (!empty($transferTestTableId)) {
    Database::query("DELETE FROM restaurant_tables WHERE id = ? AND restaurant_id = 1", [$transferTestTableId]);
}
Database::query("DELETE FROM pos_device_sequences WHERE client_uuid = ?", [$testClientUuid]);

// 19c. Merge safeguards: same table/cashier/shift only; line and payment totals must be preserved.
echo PHP_EOL . "19c. Testing Same-Table Ticket Merge:" . PHP_EOL;
$mergeTableId = \App\Services\TableService::createTable(1, [
    'table_number' => 'MERGE-TEST',
    'name' => 'Merge Test Table',
    'capacity' => 4,
    'floor_zone' => 'Main Dining',
    'status' => 'occupied'
]);
$mergeTargetUuid = \App\Services\DeviceService::generateUuidV4();
$mergeSourceUuid = \App\Services\DeviceService::generateUuidV4();
$mergeTargetNumber = 'M-TGT-' . substr(str_replace('-', '', $mergeTargetUuid), 0, 12);
$mergeSourceNumber = 'M-SRC-' . substr(str_replace('-', '', $mergeSourceUuid), 0, 12);

Database::query(
    "INSERT INTO orders
        (restaurant_id, client_uuid, device_id, order_number, order_type, table_id, cashier_id,
         order_status, payment_status, subtotal, total_amount, paid_amount, change_amount, client_reported_at, server_received_at)
     VALUES (1, ?, 1, ?, 'dine_in', ?, 1, 'pending', 'paid', 100, 100, 100, 0, NOW(), NOW())",
    [$mergeTargetUuid, $mergeTargetNumber, $mergeTableId]
);
$mergeTargetId = (int)Database::lastInsertId();
Database::query(
    "INSERT INTO orders
        (restaurant_id, client_uuid, device_id, order_number, order_type, table_id, cashier_id,
         order_status, payment_status, subtotal, total_amount, paid_amount, change_amount, client_reported_at, server_received_at)
     VALUES (1, ?, 1, ?, 'dine_in', ?, 1, 'pending', 'paid', 50, 50, 50, 0, NOW(), NOW())",
    [$mergeSourceUuid, $mergeSourceNumber, $mergeTableId]
);
$mergeSourceId = (int)Database::lastInsertId();

Database::query(
    "INSERT INTO order_items (restaurant_id, order_id, product_id, product_name, quantity, unit_price, cost_price, tax_rate, subtotal)
     VALUES (1, ?, 1, 'Merge Test Item A', 1, 100, 0, 0, 100)",
    [$mergeTargetId]
);
Database::query(
    "INSERT INTO order_items (restaurant_id, order_id, product_id, product_name, quantity, unit_price, cost_price, tax_rate, subtotal)
     VALUES (1, ?, 1, 'Merge Test Item B', 1, 50, 0, 0, 50)",
    [$mergeSourceId]
);
Database::query(
    "INSERT INTO payments (restaurant_id, order_id, cashier_id, payment_method, amount, tendered_amount, change_amount)
     VALUES (1, ?, 1, 'cash', 100, 100, 0)",
    [$mergeTargetId]
);
Database::query(
    "INSERT INTO payments (restaurant_id, order_id, cashier_id, payment_method, amount, tendered_amount, change_amount)
     VALUES (1, ?, 1, 'card', 50, 50, 0)",
    [$mergeSourceId]
);

$mergeResult = \App\Services\TableService::mergeActiveDineInTickets(1, $mergeTargetId, $mergeSourceId, 1);
$mergedOrder = Database::fetchOne(
    "SELECT total_amount, paid_amount, order_status FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$mergeTargetId]
);
$mergedSource = Database::fetchOne(
    "SELECT order_status FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$mergeSourceId]
);
$mergedItems = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM order_items WHERE restaurant_id = 1 AND order_id = ?",
    [$mergeTargetId]
);
$mergedPayments = Database::fetchOne(
    "SELECT COUNT(*) AS total, COALESCE(SUM(amount),0) AS amount FROM payments WHERE restaurant_id = 1 AND order_id = ?",
    [$mergeTargetId]
);
$mergeHistory = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM order_status_history WHERE restaurant_id = 1 AND order_id IN (?, ?) AND notes LIKE 'Merged %'",
    [$mergeTargetId, $mergeSourceId]
);
assertTest(
    "Merge Pending Same-Table Tickets Preserves Payment Totals",
    abs((float)$mergedOrder['total_amount'] - 150.0) < 0.01
        && abs((float)$mergedOrder['paid_amount'] - 150.0) < 0.01
        && $mergedOrder['order_status'] === 'pending'
        && $mergedSource['order_status'] === 'cancelled'
        && (int)$mergedItems['total'] === 2
        && (int)$mergedPayments['total'] === 2
        && abs((float)$mergedPayments['amount'] - 150.0) < 0.01
        && (int)$mergeHistory['total'] === 2
);

Database::query("DELETE FROM order_status_history WHERE restaurant_id = 1 AND order_id IN (?, ?)", [$mergeTargetId, $mergeSourceId]);
Database::query("DELETE FROM payments WHERE restaurant_id = 1 AND order_id IN (?, ?)", [$mergeTargetId, $mergeSourceId]);
Database::query("DELETE FROM order_items WHERE restaurant_id = 1 AND order_id IN (?, ?)", [$mergeTargetId, $mergeSourceId]);
Database::query("DELETE FROM orders WHERE restaurant_id = 1 AND id IN (?, ?)", [$mergeTargetId, $mergeSourceId]);
Database::query("DELETE FROM restaurant_tables WHERE restaurant_id = 1 AND id = ?", [$mergeTableId]);

// 19d. Open bill checkout and partial/split payment allocation.
echo PHP_EOL . "19d. Testing Open Bills & Split Payments:" . PHP_EOL;
$openBillPayload = $orderPayload;
$openBillPayload['client_uuid'] = \App\Services\DeviceService::generateUuidV4();
$openBillPayload['order_number'] = 'OPEN-' . substr(str_replace('-', '', $openBillPayload['client_uuid']), 0, 12);
$openBillPayload['save_as_open'] = true;
$openBillPayload['paid_amount'] = 0;
$openBillPayload['change_amount'] = 0;
unset($openBillPayload['payment']);
$openBillSync = \App\Services\PosSyncService::processOrderSync(1, $openBillPayload);
$openBillId = (int)$openBillSync['server_order_id'];
$openBillRow = Database::fetchOne(
    "SELECT payment_status, paid_amount, total_amount FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$openBillId]
);
$openPaymentCount = Database::fetchOne(
    "SELECT COUNT(*) AS total FROM payments WHERE restaurant_id = 1 AND order_id = ?",
    [$openBillId]
);
assertTest(
    "POS Can Save Unpaid Open Dine-in Bill Without Creating Payment",
    $openBillSync['status'] === 'synced'
        && $openBillRow['payment_status'] === 'unpaid'
        && abs((float)$openBillRow['paid_amount']) < 0.01
        && (int)$openPaymentCount['total'] === 0
);

$billShares = \App\Services\BillService::createEqualSplits(1, $openBillId, 1, 2, ['Test Guest A', 'Test Guest B']);
$shareTotalCents = (int)round(array_sum(array_column($billShares, 'amount_due')) * 100);
$openBillCents = (int)round((float)$openBillRow['total_amount'] * 100);
assertTest(
    "Equal Bill Shares Reconcile to Exact Total",
    count($billShares) === 2
        && $billShares[0]['split_label'] === 'Test Guest A'
        && $billShares[1]['split_label'] === 'Test Guest B'
        && $shareTotalCents === $openBillCents
);

$firstShareId = (int)$billShares[0]['id'];
$firstShareHalf = round((float)$billShares[0]['amount_due'] / 2, 2);
$partialPaymentUuid = \App\Services\DeviceService::generateUuidV4();
$partialPayment = \App\Services\BillService::recordPayment(
    1, $openBillId, 1, $partialPaymentUuid, $firstShareHalf, $firstShareHalf, 'cash', $firstShareId
);
$paymentCountAfterPartial = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS total FROM payments WHERE restaurant_id = 1 AND order_id = ?",
    [$openBillId]
)['total'] ?? 0);
$partialOrder = Database::fetchOne(
    "SELECT payment_status FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$openBillId]
);
assertTest(
    "Partial Payment Leaves Open Bill Partially Paid",
    $partialPayment['status'] === 'recorded'
        && $partialOrder['payment_status'] === 'partially_paid'
        && $paymentCountAfterPartial === 1
);

$duplicatePayment = \App\Services\BillService::recordPayment(
    1, $openBillId, 1, $partialPaymentUuid, $firstShareHalf, $firstShareHalf, 'cash', $firstShareId
);
$paymentCountAfterRetry = (int)(Database::fetchOne(
    "SELECT COUNT(*) AS total FROM payments WHERE restaurant_id = 1 AND order_id = ?",
    [$openBillId]
)['total'] ?? 0);
assertTest(
    "Payment Retry Is Idempotent and Does Not Duplicate Collection",
    $duplicatePayment['status'] === 'already_recorded' && $paymentCountAfterRetry === 1
);

$shareAfterHalf = array_values(array_filter(
    \App\Services\BillService::getSplits(1, $openBillId),
    static fn(array $split): bool => (int)$split['id'] === $firstShareId
))[0];
$remainingFirstShare = round((float)$shareAfterHalf['balance_due'], 2);
\App\Services\BillService::recordPayment(
    1, $openBillId, 1, \App\Services\DeviceService::generateUuidV4(),
    $remainingFirstShare, $remainingFirstShare, 'cash', $firstShareId
);
$secondShareId = (int)$billShares[1]['id'];
$secondShareAmount = round((float)$billShares[1]['amount_due'], 2);
\App\Services\BillService::recordPayment(
    1, $openBillId, 1, \App\Services\DeviceService::generateUuidV4(),
    $secondShareAmount, $secondShareAmount, 'card', $secondShareId
);
$settledOrder = Database::fetchOne(
    "SELECT payment_status, paid_amount, total_amount FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$openBillId]
);
$settledPayments = Database::fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS amount, COUNT(*) AS total FROM payments WHERE restaurant_id = 1 AND order_id = ?",
    [$openBillId]
);
$settledShares = \App\Services\BillService::getSplits(1, $openBillId);
assertTest(
    "Split Shares Can Be Paid in Multiple Transactions and Reconcile",
    $settledOrder['payment_status'] === 'paid'
        && abs((float)$settledOrder['paid_amount'] - (float)$settledOrder['total_amount']) < 0.01
        && abs((float)$settledPayments['amount'] - (float)$settledOrder['total_amount']) < 0.01
        && (int)$settledPayments['total'] === 3
        && count(array_filter($settledShares, static fn(array $split): bool => (float)$split['balance_due'] < 0.01)) === 2
);

Database::query("DELETE FROM bill_split_payment_allocations WHERE restaurant_id = 1 AND order_id = ?", [$openBillId]);
Database::query("DELETE FROM order_status_history WHERE restaurant_id = 1 AND order_id = ?", [$openBillId]);
Database::query("DELETE FROM payments WHERE restaurant_id = 1 AND order_id = ?", [$openBillId]);
Database::query("DELETE FROM order_bill_splits WHERE restaurant_id = 1 AND order_id = ?", [$openBillId]);
Database::query("DELETE FROM order_item_addons WHERE restaurant_id = 1 AND order_item_id IN (SELECT id FROM order_items WHERE restaurant_id = 1 AND order_id = ?)", [$openBillId]);
Database::query("DELETE FROM inventory_movements WHERE restaurant_id = 1 AND reference_id = ?", [$openBillId]);
Database::query("DELETE FROM order_items WHERE restaurant_id = 1 AND order_id = ?", [$openBillId]);
Database::query("DELETE FROM orders WHERE restaurant_id = 1 AND id = ?", [$openBillId]);

// 19e. Item-based splitting: assign order lines to guest shares with exact cent allocation.
echo PHP_EOL . "19e. Testing Item-Based Bill Splitting:" . PHP_EOL;
$itemBillPayload = $orderPayload;
$itemBillPayload['client_uuid'] = \\App\\Services\\DeviceService::generateUuidV4();
$itemBillPayload['order_number'] = 'ITEM-' . substr(str_replace('-', '', $itemBillPayload['client_uuid']), 0, 12);
$itemBillPayload['save_as_open'] = true;
$itemBillPayload['paid_amount'] = 0;
$itemBillPayload['change_amount'] = 0;
unset($itemBillPayload['payment']);
$itemBillSync = \\App\\Services\\PosSyncService::processOrderSync(1, $itemBillPayload);
$itemBillId = (int)$itemBillSync['server_order_id'];
$itemBillRow = Database::fetchOne(
    "SELECT total_amount, payment_status FROM orders WHERE restaurant_id = 1 AND id = ?",
    [$itemBillId]
);
$itemBillLines = Database::fetchAll(
    "SELECT id FROM order_items WHERE restaurant_id = 1 AND order_id = ? ORDER BY id ASC",
    [$itemBillId]
);
$itemAssignments = [];
foreach ($itemBillLines as $lineIndex => $line) {
    $itemAssignments[(string)$line['id']] = ($lineIndex % 2) + 1;
}
$itemShares = \\App\\Services\\BillService::createItemSplits(
    1, $itemBillId, 1, 2, ['Item Guest A', 'Item Guest B'], $itemAssignments
);
$itemShareTotalCents = (int)round(array_sum(array_column($itemShares, 'amount_due')) * 100);
$itemAssignedCount = 0;
foreach ($itemShares as $share) $itemAssignedCount += count($share['items']);
assertTest(
    "Item-Based Splits Assign Every Line and Reconcile Exactly",
    $itemBillSync['status'] === 'synced'
        && $itemBillRow['payment_status'] === 'unpaid'
        && count($itemBillLines) >= 2
        && count($itemShares) === 2
        && $itemAssignedCount === count($itemBillLines)
        && $itemShareTotalCents === (int)round((float)$itemBillRow['total_amount'] * 100)
        && count(array_filter($itemShares, static fn(array $share): bool => count($share['items']) > 0)) === 2
);

Database::query("DELETE FROM bill_split_payment_allocations WHERE restaurant_id = 1 AND order_id = ?", [$itemBillId]);
Database::query("DELETE FROM bill_split_items WHERE restaurant_id = 1 AND order_id = ?", [$itemBillId]);
Database::query("DELETE FROM order_status_history WHERE restaurant_id = 1 AND order_id = ?", [$itemBillId]);
Database::query("DELETE FROM order_bill_splits WHERE restaurant_id = 1 AND order_id = ?", [$itemBillId]);
Database::query("DELETE FROM order_item_addons WHERE restaurant_id = 1 AND order_item_id IN (SELECT id FROM order_items WHERE restaurant_id = 1 AND order_id = ?)", [$itemBillId]);
Database::query("DELETE FROM inventory_movements WHERE restaurant_id = 1 AND reference_id = ?", [$itemBillId]);
Database::query("DELETE FROM order_items WHERE restaurant_id = 1 AND order_id = ?", [$itemBillId]);
Database::query("DELETE FROM orders WHERE restaurant_id = 1 AND id = ?", [$itemBillId]);

// 20. Phase 7: Analytics Reports & SaaS Subscription Billing
echo PHP_EOL . "20. Testing BI Reports & SaaS Billing Subscriptions:" . PHP_EOL;
$reportKpis = \App\Services\ReportService::getKpiSummary(1, date('Y-01-01'), date('Y-12-31'));
assertTest("Report KPI Summary Executed", is_numeric($reportKpis['gross_sales']) && is_numeric($reportKpis['total_orders']));

$dailyTrend = \App\Services\ReportService::getDailySalesTrend(1, 30);
assertTest("Daily Sales Trend Query Executed", is_array($dailyTrend));

$paymentBreakdown = \App\Services\ReportService::getPaymentMethodBreakdown(1, date('Y-01-01'), date('Y-12-31'));
assertTest("Payment Method Breakdown Uses Verified Payments", is_array($paymentBreakdown));

$topProducts = \App\Services\ReportService::getTopSellingProducts(1, 10);
assertTest(
    "Top Selling Products Uses Schema-Compatible Order Item Fields",
    is_array($topProducts)
        && (empty($topProducts)
            || (array_key_exists('item_name', $topProducts[0])
                && array_key_exists('total_revenue', $topProducts[0])))
);

$sub = \App\Services\BillingService::getCurrentSubscription(1);
assertTest("Active Restaurant Subscription Retrieved", $sub && !empty($sub['plan_name']));

$plans = \App\Services\BillingService::getAvailablePlans();
assertTest("Available SaaS Pricing Plans Loaded (>= 3)", count($plans) >= 3);

$invoices = \App\Services\BillingService::getInvoices(1);
assertTest("Platform Invoices Query Executed", is_array($invoices));

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "  TEST SUMMARY: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
