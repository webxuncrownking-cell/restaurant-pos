<?php
/**
 * Restaurant POS & Management SaaS
 * Inventory & Stock Movements Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\CSRF;
use App\Core\Auth;
use App\Core\Database;
use App\Services\InventoryService;

class InventoryController
{
    public function index(Request $request): void
    {
        Auth::requirePermission('inventory.view_stock');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $ingredients = Database::fetchAll(
            "SELECT i.*, 
                    (SELECT COUNT(*) FROM ingredient_unit_conversions WHERE ingredient_id = i.id AND restaurant_id = i.restaurant_id) as packaging_count
             FROM ingredients i
             WHERE i.restaurant_id = ?
             ORDER BY (i.current_stock <= i.minimum_stock_alert) DESC, i.name ASC",
            [$restaurantId]
        );

        $lowStock = InventoryService::getLowStockAlerts($restaurantId);

        Response::view('inventory/index', [
            'pageTitle'    => 'Inventory Management',
            'restaurant'   => $restaurant,
            'ingredients'  => $ingredients,
            'lowStock'     => $lowStock,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function showStockIn(Request $request): void
    {
        Auth::requirePermission('inventory.stock_in');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $ingredients = Database::fetchAll(
            "SELECT * FROM ingredients WHERE restaurant_id = ? AND is_active = 1 ORDER BY name ASC",
            [$restaurantId]
        );

        $packages = Database::fetchAll(
            "SELECT c.*, i.name as ingredient_name, i.base_unit 
             FROM ingredient_unit_conversions c
             JOIN ingredients i ON c.ingredient_id = i.id AND c.restaurant_id = i.restaurant_id
             WHERE c.restaurant_id = ?
             ORDER BY i.name ASC, c.unit_name ASC",
            [$restaurantId]
        );

        Response::view('inventory/stock_in', [
            'pageTitle'    => 'Receive Goods / Stock In',
            'restaurant'   => $restaurant,
            'ingredients'  => $ingredients,
            'packages'     => $packages,
            'csrf_token'   => CSRF::generate()
        ]);
    }

    public function processStockIn(Request $request): void
    {
        Auth::requirePermission('inventory.stock_in');
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/inventory?error=Invalid+security+token');
            return;
        }

        $restaurant = TenantContext::getRestaurant();
        $restaurantId = (int)$restaurant['id'];
        $user = Auth::user();
        $userId = ($user && isset($user['restaurant_id']) && (int)$user['restaurant_id'] === $restaurantId)
            ? (int)$user['id']
            : null;

        $mode = (string)$request->post('mode', '');
        if (!in_array($mode, ['package', 'base'], true)) {
            Response::redirect('/inventory/stock-in?error=Please+choose+a+stock-in+method');
            return;
        }
        $notes = trim((string)$request->post('notes', ''));

        try {
            if ($mode === 'package') {
                $conversionId = (int)$request->post('package_id');
                $packageCount = (float)$request->post('package_count');
                $totalCost = $request->post('total_cost') ? (float)$request->post('total_cost') : null;

                if ($conversionId <= 0 || $packageCount <= 0) {
                    throw new \InvalidArgumentException("Please select a packaging unit and specify a positive quantity.");
                }

                InventoryService::stockInByPackage(
                    $restaurantId,
                    $conversionId,
                    $packageCount,
                    $totalCost,
                    $notes ?: 'Stock In Form',
                    $userId
                );
            } else {
                $ingredientId = (int)$request->post('ingredient_id');
                $quantity = (float)$request->post('quantity');
                $unit = trim((string)$request->post('unit', ''));
                $costPerUnit = $request->post('cost_per_unit') ? (float)$request->post('cost_per_unit') : null;

                if ($ingredientId <= 0 || $quantity <= 0 || $unit === '') {
                    throw new \InvalidArgumentException("Please select an ingredient, specify quantity and unit.");
                }

                InventoryService::recordMovement(
                    $restaurantId,
                    $ingredientId,
                    'purchase_receipt',
                    $quantity,
                    $unit,
                    $costPerUnit,
                    null,
                    $notes ?: 'Manual Stock In',
                    $userId
                );
            }

            Response::redirect('/inventory?success=Stock+successfully+received');
        } catch (\InvalidArgumentException $e) {
            Response::redirect('/inventory/stock-in?error=' . urlencode($e->getMessage()));
        } catch (\Throwable $e) {
            error_log('Inventory stock-in failed: ' . $e);
            Response::redirect('/inventory/stock-in?error=' . urlencode('Stock could not be recorded. Please verify the selected ingredient, unit, and quantity.'));
        }
    }

    public function ledger(Request $request): void
    {
        Auth::requirePermission('inventory.view_stock');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Response::redirect('/dashboard');
            return;
        }

        $restaurantId = (int)$restaurant['id'];
        $ingredientId = $request->get('ingredient_id') ? (int)$request->get('ingredient_id') : null;
        $referenceType = $request->get('type') ? trim((string)$request->get('type')) : null;
        $allowedTypes = ['purchase_receipt', 'order_sale', 'order_cancellation', 'order_refund', 'manual_adjustment', 'spoilage_waste'];
        if ($referenceType !== null && !in_array($referenceType, $allowedTypes, true)) {
            $referenceType = null;
        }

        $ingredients = Database::fetchAll(
            "SELECT id, name FROM ingredients WHERE restaurant_id = ? ORDER BY name ASC",
            [$restaurantId]
        );

        $movements = InventoryService::getLedger($restaurantId, $ingredientId, $referenceType, 100);

        Response::view('inventory/ledger', [
            'pageTitle'    => 'Inventory Movement Ledger',
            'restaurant'   => $restaurant,
            'ingredients'  => $ingredients,
            'movements'    => $movements,
            'selectedIng'  => $ingredientId,
            'selectedType' => $referenceType
        ]);
    }
}
