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

        // Catalog & Tables
        $catalog = MenuService::getPosCatalog($restaurantId);
        $tables = TableService::getTables($restaurantId);
        $floorZones = TableService::getFloorZones($restaurantId);

        Response::render('pos/terminal', [
            'user' => $user,
            'devices' => $devices,
            'activeDevice' => $activeDevice,
            'activeShift' => $activeShift,
            'catalog' => $catalog,
            'tables' => $tables,
            'floorZones' => $floorZones
        ]);
    }

    /**
     * Bootstrap API for offline terminal priming (JSON)
     */
    public function bootstrapApi(Request $request): void
    {
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
            // Check if batch of orders or single order
            if (isset($payload['orders']) && is_array($payload['orders'])) {
                $results = [];
                foreach ($payload['orders'] as $orderData) {
                    $results[] = PosSyncService::processOrderSync($restaurantId, $orderData);
                }
                Response::json(['status' => 'success', 'results' => $results]);
            } else {
                $res = PosSyncService::processOrderSync($restaurantId, $payload);
                Response::json(['status' => 'success', 'result' => $res]);
            }
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
