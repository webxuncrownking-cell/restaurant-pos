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
$router->post('/login', [AuthController::class, 'login']);
$router->get('/change-password', [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);
$router->get('/logout', [AuthController::class, 'logout']);

// Multi-Tenant Dashboard Routes
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/switch-tenant', [DashboardController::class, 'switchTenant']);

// Phase 2: Menu, Variants, Addons & Recipe BOM Routes
$router->get('/menu', [MenuController::class, 'index']);
$router->get('/menu/products/create', [MenuController::class, 'showCreateProduct']);
$router->post('/menu/products', [MenuController::class, 'createProduct']);
$router->get('/menu/products/{id}/recipe', [MenuController::class, 'showRecipeBOM']);
$router->post('/menu/products/{id}/recipe', [MenuController::class, 'saveRecipeBOM']);
$router->get('/menu/catalog.json', [MenuController::class, 'catalogJson']);

// Phase 2: Inventory & Double-Entry Ledger Routes
$router->get('/inventory', [InventoryController::class, 'index']);
$router->get('/inventory/stock-in', [InventoryController::class, 'showStockIn']);
$router->post('/inventory/stock-in', [InventoryController::class, 'processStockIn']);
$router->get('/inventory/ledger', [InventoryController::class, 'ledger']);

// Diagnostics & Setup Routes
$router->get('/install', [InstallController::class, 'index']);
$router->post('/install/migrate', [InstallController::class, 'runMigration']);
$router->get('/install/tests', [InstallController::class, 'runTests']);

// 5. Dispatch Request
$router->dispatch($request);
