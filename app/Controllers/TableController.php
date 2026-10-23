<?php
/**
 * Restaurant POS & Management SaaS
 * Table Management & Floor Plan Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Core\CSRF;
use App\Services\TableService;
use Exception;

class TableController
{
    /**
     * Display table management list & floor plan
     */
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $zone = $request->get('zone');

        $tables = TableService::getTables($restaurantId, $zone);
        $floorZones = TableService::getFloorZones($restaurantId);
        $activeOrders = TableService::getActiveDineInOrders($restaurantId);

        // Count totals
        $totalTables = count($tables);
        $availableCount = 0;
        $occupiedCount = 0;
        $reservedCount = 0;
        $cleaningCount = 0;

        foreach ($tables as $t) {
            if ($t['status'] === 'available') $availableCount++;
            elseif ($t['status'] === 'occupied') $occupiedCount++;
            elseif ($t['status'] === 'reserved') $reservedCount++;
            elseif ($t['status'] === 'cleaning') $cleaningCount++;
        }

        Response::render('tables/index', [
            'tables' => $tables,
            'activeOrders' => $activeOrders,
            'floorZones' => $floorZones,
            'currentZone' => $zone ?: 'all',
            'stats' => [
                'total' => $totalTables,
                'available' => $availableCount,
                'occupied' => $occupiedCount,
                'reserved' => $reservedCount,
                'cleaning' => $cleaningCount,
            ]
        ]);
    }

    /**
     * Create table POST action
     */
    public function create(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        try {
            TableService::createTable($restaurantId, [
                'table_number' => $request->post('table_number'),
                'name' => $request->post('name'),
                'capacity' => (int)$request->post('capacity', 4),
                'floor_zone' => $request->post('floor_zone', 'Main Dining'),
                'status' => $request->post('status', 'available'),
            ]);

            $_SESSION['flash_success'] = 'Table created successfully.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/tables');
    }

    /**
     * Update table status (quick toggle from UI)
     */
    public function updateStatus(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $tableId = (int)$request->post('table_id');
        $status = (string)$request->post('status');

        try {
            TableService::updateStatus($restaurantId, $tableId, $status);
            $_SESSION['flash_success'] = 'Table status updated to ' . ucfirst($status) . '.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/tables');
    }

    /**
     * Transfer an active dine-in ticket to an available floor table.
     */
    public function transferOrder(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $orderId = filter_var($request->post('order_id'), FILTER_VALIDATE_INT);
        $destinationTableId = filter_var($request->post('destination_table_id'), FILTER_VALIDATE_INT);

        try {
            if (!$orderId || !$destinationTableId || $restaurantId <= 0) {
                throw new \\InvalidArgumentException('Choose an order and destination table.');
            }
            $result = TableService::transferActiveDineInOrder($restaurantId, (int)$orderId, (int)$destinationTableId, (int)Auth::id());
            $_SESSION['flash_success'] = 'Order transferred to Table #' . $result['destination_table_number'] . '.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/tables');
    }

    /**
     * Delete table (soft delete)
     */
    public function delete(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $tableId = (int)$request->post('table_id');

        try {
            TableService::softDelete($restaurantId, $tableId);
            $_SESSION['flash_success'] = 'Table deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/tables');
    }
}
