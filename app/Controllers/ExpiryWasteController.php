<?php
/**
 * Restaurant POS & Management SaaS
 * Expiry and wastage dashboard.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\ExpiryWasteService;
use App\Services\SupplierPurchaseService;
use InvalidArgumentException;
use Throwable;

class ExpiryWasteController
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
        $ready = ExpiryWasteService::schemaReady();
        Response::view('expiry/index', [
            'pageTitle' => 'Expiry & Wastage Tracking',
            'restaurant' => $restaurant,
            'schema_ready' => $ready,
            'summary' => $ready ? ExpiryWasteService::getSummary($restaurantId, 30) : [],
            'batches' => $ready ? ExpiryWasteService::getBatches($restaurantId, 30) : [],
            'wasteRecords' => $ready ? ExpiryWasteService::getRecentWaste($restaurantId, 50) : [],
            'canManage' => Auth::can('inventory.adjust'),
            'canMigrate' => Auth::can('inventory.stock_in'),
            'flashes' => Session::getFlashes()
        ]);
    }

    public function migrate(Request $request): void
    {
        Auth::requirePermission('inventory.stock_in');
        if (!$this->validCsrf($request)) return;

        try {
            SupplierPurchaseService::ensureSchema();
            ExpiryWasteService::ensureSchema();
            Session::setFlash('success', 'Expiry and wastage tracking is ready. Existing ingredients, orders, and inventory records were preserved.');
        } catch (Throwable $e) {
            error_log('[Expiry Tracking Migration] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to prepare expiry tracking tables. Check database permissions and the PHP error log.');
        }
        Response::redirect('/expiry');
    }

    public function recordWaste(Request $request): void
    {
        Auth::requirePermission('inventory.adjust');
        if (!$this->validCsrf($request)) return;

        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Session::setFlash('warning', 'Choose a restaurant workspace before recording wastage.');
            Response::redirect('/dashboard');
            return;
        }

        if (!ExpiryWasteService::schemaReady()) {
            Session::setFlash('warning', 'Apply the expiry tracking database update before recording batch wastage.');
            Response::redirect('/expiry');
            return;
        }

        $batchId = filter_var($request->post('batch_id'), FILTER_VALIDATE_INT);
        $quantityRaw = $request->post('quantity', '');
        $reason = trim((string)$request->post('reason', ''));
        $notes = trim((string)$request->post('notes', ''));
        $user = Auth::user();
        $restaurantId = (int)$restaurant['id'];
        $userId = $user && isset($user['id'], $user['restaurant_id']) && (int)$user['restaurant_id'] === $restaurantId
            ? (int)$user['id'] : 0;

        try {
            if (!$batchId || $batchId <= 0) throw new InvalidArgumentException('Choose a valid ingredient batch.');
            if (!is_numeric($quantityRaw) || !is_finite((float)$quantityRaw) || (float)$quantityRaw <= 0) {
                throw new InvalidArgumentException('Enter a positive wastage quantity.');
            }
            if ($userId <= 0) throw new InvalidArgumentException('A staff account from this restaurant is required.');
            $record = ExpiryWasteService::recordWaste(
                $restaurantId,
                (int)$batchId,
                $userId,
                (float)$quantityRaw,
                $reason,
                $notes
            );
            Session::setFlash(
                'success',
                'Wastage recorded for ' . $record['ingredient_name'] . ': '
                . number_format((float)$record['quantity_base'], 4) . ' base units, cost '
                . '₱' . number_format((float)$record['total_cost'], 2) . '. Stock and batch balances were updated.'
            );
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Ingredient Wastage] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to record wastage. The stock change was rolled back.');
        }
        Response::redirect('/expiry');
    }

    private function validCsrf(Request $request): bool
    {
        if (CSRF::validate($request->csrfToken())) return true;
        Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
        Response::redirect('/expiry');
        return false;
    }
}
