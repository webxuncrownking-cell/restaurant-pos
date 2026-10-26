<?php
/**
 * Restaurant POS & Management SaaS
 * Application Front Controller
 * Compatible with PHP 8.2+, XAMPP, Apache, and cPanel
 */

declare(strict_types=1);

// 1. Load Global Application Configuration
require_once __DIR__ . '/config/config.php';

// 2. PSR-4 Compliant Autoloader for 'App\' Namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Auth;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\MenuController;
use App\Controllers\InventoryController;
use App\Controllers\ExpiryWasteController;
use App\Controllers\ProcurementController;
use App\Controllers\TableController;
use App\Controllers\QROrderController;
use App\Controllers\DeviceController;
use App\Controllers\ShiftController;
use App\Controllers\PosController;
use App\Controllers\ReportController;
use App\Controllers\BillingController;
use App\Controllers\RestaurantSettingsController;
use App\Controllers\KitchenController;
use App\Controllers\ReservationsController;
use App\Controllers\BillsController;

// 3. Initialize Session & Security
Session::start();

// 4. Capture Request & Build Router
$request = new Request();
$router = new Router();

// ----------------------------------------------------------------------------
// Phase 1 & 2 Routes Definition
// ----------------------------------------------------------------------------

// Root redirect
$router->get('/', function (Request $req) {
    if (Auth::check()) {
        Response::redirect('/dashboard');
    } else {
        Response::redirect('/login');
    }
});

// Authentication Routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/register', [RestaurantRegistrationController::class, 'show']);
$router->post('/register', [RestaurantRegistrationController::class, 'register']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/change-password', [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);
$router->get('/logout', [AuthController::class, 'logout']);

// Multi-Tenant Dashboard Routes
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/switch-tenant', [DashboardController::class, 'switchTenant']);

// Phase 2: Menu, Variants, Addons & Recipe BOM Routes
$router->get('/menu', [MenuController::class, 'index']);
$router->post('/menu/categories', [MenuController::class, 'createCategory']);
$router->get('/menu/products/create', [MenuController::class, 'showCreateProduct']);
$router->get('/menu/products/{id}/edit', [MenuController::class, 'showEditProduct']);
$router->post('/menu/products', [MenuController::class, 'createProduct']);
$router->post('/menu/products/image', [MenuController::class, 'saveProductImage']);
$router->post('/menu/products/status', [MenuController::class, 'setProductVisibility']);
$router->post('/menu/products/{id}', [MenuController::class, 'updateProduct']);
$router->get('/menu/products/{id}/recipe', [MenuController::class, 'showRecipeBOM']);
$router->post('/menu/products/{id}/recipe', [MenuController::class, 'saveRecipeBOM']);
$router->get('/menu/catalog.json', [MenuController::class, 'catalogJson']);

// Phase 2: Inventory & Double-Entry Ledger Routes
$router->get('/inventory', [InventoryController::class, 'index']);
$router->get('/inventory/stock-in', [InventoryController::class, 'showStockIn']);
$router->post('/inventory/stock-in', [InventoryController::class, 'processStockIn']);
$router->get('/inventory/adjustment', [InventoryController::class, 'showAdjustment']);
$router->post('/inventory/adjustment', [InventoryController::class, 'processAdjustment']);
$router->get('/inventory/ledger', [InventoryController::class, 'ledger']);

// Supplier and purchase order management
$router->get('/procurement', [ProcurementController::class, 'index']);

// Ingredient expiry tracking and wastage accounting
$router->get('/expiry', [ExpiryWasteController::class, 'index']);
$router->post('/expiry/migrate', [ExpiryWasteController::class, 'migrate']);
$router->post('/expiry/waste', [ExpiryWasteController::class, 'recordWaste']);
$router->post('/procurement/migrate', [ProcurementController::class, 'migrate']);
$router->post('/procurement/suppliers/create', [ProcurementController::class, 'createSupplier']);
$router->post('/procurement/suppliers/status', [ProcurementController::class, 'toggleSupplier']);
$router->post('/procurement/orders/create', [ProcurementController::class, 'createOrder']);
$router->post('/procurement/orders/action', [ProcurementController::class, 'orderAction']);
$router->post('/procurement/orders/receive', [ProcurementController::class, 'receive']);

// Phase 3: Tables & Floor Plan Routes
$router->get('/tables', [TableController::class, 'index']);
$router->post('/tables/create', [TableController::class, 'create']);
$router->post('/tables/status', [TableController::class, 'updateStatus']);
$router->post('/tables/transfer', [TableController::class, 'transferOrder']);
$router->post('/tables/merge', [TableController::class, 'mergeOrders']);
$router->post('/tables/delete', [TableController::class, 'delete']);
$router->post('/tables/qr/migrate', [TableController::class, 'migrateQr']);
$router->get('/tables/qr/{id}', [TableController::class, 'qrPage']);

// Public customer ordering pages opened from unique table QR codes.
$router->get('/order/{token}', [QROrderController::class, 'menu']);
$router->post('/order/{token}/submit', [QROrderController::class, 'submit']);
$router->get('/order/{token}/track/{trackingToken}', [QROrderController::class, 'trackStatus']);
$router->get('/order/{token}/track/{trackingToken}/status.json', [QROrderController::class, 'trackStatusJson']);

// Front-of-house reservations and waiting-list routes
$router->get('/reservations', [ReservationsController::class, 'index']);
$router->post('/reservations/migrate', [ReservationsController::class, 'migrate']);
$router->post('/reservations/create', [ReservationsController::class, 'createReservation']);
$router->post('/reservations/action', [ReservationsController::class, 'reservationAction']);
$router->post('/reservations/waitlist/create', [ReservationsController::class, 'createWaitlist']);
$router->post('/reservations/waitlist/action', [ReservationsController::class, 'waitlistAction']);

// Open bills, partial payments and guest share allocation
$router->get('/bills', [BillsController::class, 'index']);
$router->post('/bills/migrate', [BillsController::class, 'migrate']);
$router->post('/bills/split', [BillsController::class, 'createEqualSplits']);
$router->post('/bills/split-items', [BillsController::class, 'createItemSplits']);
$router->post('/bills/payment', [BillsController::class, 'collectPayment']);

// Phase 3: POS Terminals & Devices Routes
$router->get('/devices', [DeviceController::class, 'index']);
$router->post('/devices/register', [DeviceController::class, 'register']);
$router->post('/devices/rotate', [DeviceController::class, 'rotate']);
$router->post('/devices/status', [DeviceController::class, 'toggleStatus']);
$router->post('/devices/authorizations', [DeviceController::class, 'updateAuthorizations']);

// Phase 3: Cashier Shift Sessions & Cash Drawer Routes
$router->get('/shifts', [ShiftController::class, 'index']);
$router->get('/shifts/open', [ShiftController::class, 'showOpen']);
$router->post('/shifts/open', [ShiftController::class, 'open']);
$router->get('/shifts/active', [ShiftController::class, 'showActive']);
$router->post('/shifts/movement', [ShiftController::class, 'addMovement']);
$router->post('/shifts/close', [ShiftController::class, 'close']);

// Phase 4, 5 & 6: Offline-First POS Terminal & API Sync
$router->get('/kitchen', [KitchenController::class, 'index']);
$router->get('/kitchen/orders.json', [KitchenController::class, 'ordersJson']);
$router->post('/kitchen/status', [KitchenController::class, 'updateStatus']);
$router->post('/kitchen/item-status', [KitchenController::class, 'updateItemStatus']);
$router->get('/pos', [PosController::class, 'showTerminal']);
$router->get('/api/pos/bootstrap', [PosController::class, 'bootstrapApi']);
$router->post('/api/pos/sync', [PosController::class, 'syncApi']);

// Phase 7: Business Intelligence Reports & SaaS Billing Routes
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/data.json', [ReportController::class, 'dataJson']);
$router->get('/reports/export.csv', [ReportController::class, 'exportCsv']);
$router->get('/billing', [BillingController::class, 'index']);
$router->post('/billing/upgrade', [BillingController::class, 'upgrade']);

// Unified restaurant settings
$router->get('/settings', [RestaurantSettingsController::class, 'settings']);
$router->post('/settings', [RestaurantSettingsController::class, 'saveSettings']);
$router->post('/settings/migrate', [RestaurantSettingsController::class, 'migrateSettingsSchema']);
// Legacy paths remain compatible and redirect to the unified settings page.
$router->get('/settings/branding', [RestaurantSettingsController::class, 'branding']);
$router->post('/settings/branding', [RestaurantSettingsController::class, 'saveSettings']);
$router->get('/settings/favicon', [RestaurantSettingsController::class, 'favicon']);
$router->post('/settings/favicon', [RestaurantSettingsController::class, 'saveSettings']);

// Diagnostics & Setup Routes
$router->get('/install', [InstallController::class, 'index']);
$router->post('/install/migrate', [InstallController::class, 'runMigration']);
$router->get('/install/tests', [InstallController::class, 'runTests']);

// 5. Dispatch Request
$router->dispatch($request);
