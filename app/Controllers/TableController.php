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
use App\Services\TableQrService;
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
        $allTables = TableService::getTables($restaurantId);
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
            'allTables' => $allTables,
            'qrSchemaReady' => TableQrService::schemaReady(),
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
     * Enable QR token storage for existing and future tables.
     */
    public function migrateQr(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        if (!Auth::isPlatformAdmin() && !in_array(Auth::roleSlug(), ['owner', 'manager'], true)) {
            Response::forbidden('Only a restaurant owner or manager can enable table QR ordering.');
            return;
        }

        try {
            TableQrService::ensureSchema();
            $_SESSION['flash_success'] = 'Table QR ordering is enabled. Every active table now has its own secure QR link.';
        } catch (Exception $e) {
            error_log('[Table QR Setup] ' . $e);
            $_SESSION['flash_error'] = 'Unable to enable table QR ordering. Check the PHP error log and database permissions.';
        }
        Response::redirect('/tables');
    }

    /**
     * Display a table-specific QR code for printing and sharing.
     */
    public function qrPage(Request $request, array $params): void
    {
        Auth::requireAuth();
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $tableId = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if ($restaurantId <= 0 || !$tableId || $tableId < 1) {
            Response::notFound('Dining table not found.');
            return;
        }
        if (!TableQrService::schemaReady()) {
            $_SESSION['flash_warning'] = 'Enable table QR ordering before opening a table QR code.';
            Response::redirect('/tables');
            return;
        }

        $table = TableQrService::getTableForManagement($restaurantId, (int)$tableId);
        if (!$table) {
            Response::notFound('Dining table not found or inactive.');
            return;
        }
        $restaurant = TenantContext::getRestaurant();
        $baseUrl = rtrim((string)BASE_URL, '/');
        $defaultUrl = $baseUrl . '/order/' . rawurlencode((string)$table['qr_token']);
        Response::render('tables/qr', [
            'table' => $table,
            'restaurant' => $restaurant ?? [],
            'baseUrl' => $baseUrl,
            'defaultUrl' => $defaultUrl
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
                throw new \InvalidArgumentException('Choose an order and destination table.');
            }
            $result = TableService::transferActiveDineInOrder($restaurantId, (int)$orderId, (int)$destinationTableId, (int)Auth::id());
            $_SESSION['flash_success'] = 'Order transferred to Table #' . $result['destination_table_number'] . '.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/tables');
    }

    /**
     * Merge two compatible, new dine-in tickets while preserving payment totals.
     */
    public function mergeOrders(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $targetOrderId = filter_var($request->post('target_order_id'), FILTER_VALIDATE_INT);
        $sourceOrderId = filter_var($request->post('source_order_id'), FILTER_VALIDATE_INT);

        try {
            if (!$targetOrderId || !$sourceOrderId || $restaurantId <= 0) {
                throw new \InvalidArgumentException('Choose the ticket to keep and the ticket to merge into it.');
            }
            $result = TableService::mergeActiveDineInTickets(
                $restaurantId, (int)$targetOrderId, (int)$sourceOrderId, (int)Auth::id()
            );
            $_SESSION['flash_success'] = 'Tickets merged. Combined total: ₱' . number_format((float)$result['merged_total'], 2) . '.';
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
