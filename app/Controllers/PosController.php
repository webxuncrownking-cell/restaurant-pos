<?php
/**
 * Restaurant POS & Management SaaS
 * POS Terminal & Sync Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Core\Database;
use App\Services\PosSyncService;
use App\Services\ShiftService;
use App\Services\DeviceService;
use App\Services\TableService;
use App\Services\MenuService;
use Exception;

class PosController
{
    /**
     * Render the touch-optimized POS terminal view
     */
    public function showTerminal(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        // Get or choose default active device for this terminal
        $devices = DeviceService::getDevices($restaurantId);
        $activeDevice = $devices[0] ?? null;

        // Check active shift
        $activeShift = ShiftService::getActiveShift($restaurantId, (int)$user['id']);

        // Catalog, tables, and active tenant-scoped customers for optional order association.
        $catalog = MenuService::getPosCatalog($restaurantId);
        $tables = TableService::getTables($restaurantId);
        $floorZones = TableService::getFloorZones($restaurantId);
        $customers = [];
        try {
            $customers = Database::fetchAll(
                "SELECT id, full_name, phone, loyalty_points FROM restaurant_customers WHERE restaurant_id = ? AND is_active = 1 ORDER BY full_name ASC LIMIT 500",
                [$restaurantId]
            );
        } catch (\\Throwable $ignored) {
            // CRM is optional until the customer directory has initialized its schema.
        }

        Response::render('pos/terminal', [
            'user' => $user,
            'devices' => $devices,
            'activeDevice' => $activeDevice,
            'activeShift' => $activeShift,
            'catalog' => $catalog,
            'tables' => $tables,
            'floorZones' => $floorZones,
            'customers' => $customers
        ]);
    }

    /**
     * Bootstrap API for offline terminal priming (JSON)
     */
    public function bootstrapApi(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $deviceId = (int)$request->get('device_id', 1);

        try {
            $data = PosSyncService::getBootstrapPayload($restaurantId, $deviceId);
            Response::json($data);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Idempotent Offline Orders Sync Endpoint (JSON)
     */
    public function syncApi(Request $request): void
    {
        $restaurantId = TenantContext::getRestaurantId();

        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (!$payload) {
            Response::json(['error' => 'Invalid JSON payload.'], 400);
            return;
        }

        try {
            // Process queued orders independently. A single invalid order must not
            // prevent later orders from syncing successfully.
            if (isset($payload['orders']) && is_array($payload['orders'])) {
                if (count($payload['orders']) > 100) {
                    Response::json(['error' => 'Sync batches may contain at most 100 orders.'], 413);
                    return;
                }
                Response::json(PosSyncService::processOrderSyncBatch($restaurantId, array_values($payload['orders'])));
                return;
            }

            $res = PosSyncService::processOrderSync($restaurantId, $payload);
            Response::json(['status' => 'success', 'result' => $res]);
        } catch (\Throwable $e) {
            error_log('[POS Sync API] Request failed: ' . $e);
            Response::json(['error' => 'Unable to process this sync request. Check the server log and retry.'], 500);
        }
    }
}
