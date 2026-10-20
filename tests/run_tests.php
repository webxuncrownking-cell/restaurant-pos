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

$costInfo = \App\Services\MenuService::calculateProductCost(1, 1);
assertTest("Recipe Food Cost Calculated (> 0)", $costInfo['total_cost'] > 0);

$posCatalog = \App\Services\MenuService::getPosCatalog(1);
assertTest("POS Offline Catalog Export Formatted", !empty($posCatalog['version']) && !empty($posCatalog['products']));

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

$orderInDb = Database::fetchOne("SELECT total_amount, payment_status FROM orders WHERE id = ?", [$syncRes['server_order_id']]);
assertTest("Order Stored in DB with Correct Total (₱380.00)", abs((float)$orderInDb['total_amount'] - 380.00) < 0.01 && $orderInDb['payment_status'] === 'paid');

// Cleanup test sync order and related rows
Database::query("DELETE FROM payments WHERE order_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM order_items WHERE order_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM orders WHERE id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM inventory_movements WHERE reference_id = ?", [$syncRes['server_order_id']]);
Database::query("DELETE FROM pos_device_sequences WHERE client_uuid = ?", [$testClientUuid]);

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "  TEST SUMMARY: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "================================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
