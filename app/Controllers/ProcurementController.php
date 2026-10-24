<?php
/**
 * Restaurant POS & Management SaaS
 * Supplier, purchase order, and stock receipt controller.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Session;
use App\Services\SupplierPurchaseService;
use App\Services\ExpiryWasteService;
use InvalidArgumentException;
use Throwable;

class ProcurementController
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
        $ready = SupplierPurchaseService::schemaReady();
        Response::view('procurement/index', [
            'pageTitle' => 'Suppliers & Purchase Orders',
            'restaurant' => $restaurant,
            'schema_ready' => $ready,
            'suppliers' => $ready ? SupplierPurchaseService::getSuppliers($restaurantId) : [],
            'ingredients' => $ready ? SupplierPurchaseService::getIngredientsForPurchase($restaurantId) : [],
            'purchaseOrders' => $ready ? SupplierPurchaseService::getPurchaseOrders($restaurantId) : [],
            'canManage' => Auth::can('inventory.stock_in'),
            'expiry_tracking_ready' => ExpiryWasteService::schemaReady(),
            'csrf_token' => CSRF::generate(),
            'flashes' => Session::getFlashes()
        ]);
    }

    public function migrate(Request $request): void
    {
        Auth::requirePermission('inventory.stock_in');
        if (!$this->validCsrf($request)) return;
        try {
            SupplierPurchaseService::ensureSchema();
            Session::setFlash('success', 'Supplier and purchase order tables are ready. Existing stock, orders, and sales data were preserved.');
        } catch (Throwable $e) {
            error_log('[Procurement Migration] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to prepare purchasing tables. Check database permissions and the PHP error log.');
        }
        Response::redirect('/procurement');
    }

    public function createSupplier(Request $request): void
    {
        $restaurantId = $this->authorizeWrite();
        if (!$this->validCsrf($request)) return;
        try {
            $id = SupplierPurchaseService::createSupplier($restaurantId, $request->all());
            Session::setFlash('success', 'Supplier created successfully.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Supplier Create] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to save this supplier. Check the supplier details and try again.');
        }
        Response::redirect('/procurement');
    }

    public function toggleSupplier(Request $request): void
    {
        $restaurantId = $this->authorizeWrite();
        if (!$this->validCsrf($request)) return;
        $supplierId = filter_var($request->post('supplier_id'), FILTER_VALIDATE_INT);
        $active = (string)$request->post('is_active', '') === '1';

        try {
            if (!$supplierId || $supplierId <= 0) throw new InvalidArgumentException('Choose a valid supplier.');
            SupplierPurchaseService::setSupplierActive($restaurantId, (int)$supplierId, $active);
            Session::setFlash('success', $active ? 'Supplier reactivated.' : 'Supplier deactivated. Existing purchase orders were preserved.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Supplier Status] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to update supplier status.');
        }
        Response::redirect('/procurement');
    }

    public function createOrder(Request $request): void
    {
        $restaurantId = $this->authorizeWrite();
        if (!$this->validCsrf($request)) return;
        $userId = $this->tenantUserId($restaurantId);
        if (!$userId) {
            Session::setFlash('danger', 'Use a staff account belonging to this restaurant to create a purchase order.');
            Response::redirect('/procurement');
            return;
        }

        $supplierId = filter_var($request->post('supplier_id'), FILTER_VALIDATE_INT);
        $ingredientIds = $request->post('ingredient_ids', []);
        $quantities = $request->post('quantities', []);
        $units = $request->post('purchase_units', []);
        $unitCosts = $request->post('unit_costs', []);
        $notes = trim((string)$request->post('notes', ''));

        try {
            if (!$supplierId || $supplierId <= 0) throw new InvalidArgumentException('Choose a supplier.');
            if (!is_array($ingredientIds) || !is_array($quantities) || !is_array($units) || !is_array($unitCosts)) {
                throw new InvalidArgumentException('Purchase order lines are invalid. Refresh and try again.');
            }

            $items = [];
            $lineCount = max(count($ingredientIds), count($quantities), count($units), count($unitCosts));
            if ($lineCount > 100) throw new InvalidArgumentException('A purchase order can contain at most 100 lines.');
            for ($i = 0; $i < $lineCount; $i++) {
                $ingredientId = $ingredientIds[$i] ?? '';
                $quantity = $quantities[$i] ?? '';
                $unit = $units[$i] ?? '';
                $cost = $unitCosts[$i] ?? '';
                if (trim((string)$ingredientId) === '' && trim((string)$quantity) === '' && trim((string)$unit) === '' && trim((string)$cost) === '') {
                    continue;
                }
                $items[] = [
                    'ingredient_id' => $ingredientId,
                    'quantity' => $quantity,
                    'purchase_unit' => $unit,
                    'unit_cost' => $cost
                ];
            }

            SupplierPurchaseService::createPurchaseOrder(
                $restaurantId, $userId, (int)$supplierId, $items, $notes
            );
            Session::setFlash('success', 'Draft purchase order created. Mark it Ordered when you send it to the supplier.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Purchase Order Create] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to create the purchase order. No stock was changed.');
        }
        Response::redirect('/procurement');
    }

    public function orderAction(Request $request): void
    {
        $restaurantId = $this->authorizeWrite();
        if (!$this->validCsrf($request)) return;
        $orderId = filter_var($request->post('purchase_order_id'), FILTER_VALIDATE_INT);
        $status = trim((string)$request->post('status', ''));

        try {
            if (!$orderId || $orderId <= 0) throw new InvalidArgumentException('Choose a valid purchase order.');
            SupplierPurchaseService::updateOrderStatus($restaurantId, (int)$orderId, $status);
            Session::setFlash('success', $status === 'ordered' ? 'Purchase order marked as Ordered.' : 'Purchase order cancelled. Stock was not changed.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Purchase Order Status] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to update purchase order status.');
        }
        Response::redirect('/procurement');
    }

    public function receive(Request $request): void
    {
        $restaurantId = $this->authorizeWrite();
        if (!$this->validCsrf($request)) return;
        $userId = $this->tenantUserId($restaurantId);
        if (!$userId) {
            Session::setFlash('danger', 'Use a staff account belonging to this restaurant to receive stock.');
            Response::redirect('/procurement');
            return;
        }

        $orderId = filter_var($request->post('purchase_order_id'), FILTER_VALIDATE_INT);
        try {
            if (!$orderId || $orderId <= 0) throw new InvalidArgumentException('Choose a valid purchase order.');
            $batchInfo = $request->post('batch_info', []);
            if (!is_array($batchInfo)) $batchInfo = [];
            SupplierPurchaseService::receivePurchaseOrder($restaurantId, (int)$orderId, $userId, $batchInfo);
            Session::setFlash('success', 'Delivery received. Stock balances and inventory ledger were updated once for every purchase-order line.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Purchase Order Receipt] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to receive this delivery. The receipt was rolled back; check the PO status before retrying.');
        }
        Response::redirect('/procurement');
    }

    private function authorizeWrite(): int
    {
        Auth::requirePermission('inventory.stock_in');
        $restaurant = TenantContext::getRestaurant();
        if (!$restaurant) {
            Session::setFlash('warning', 'Choose a restaurant workspace before managing suppliers or purchases.');
            Response::redirect('/dashboard');
        }
        return (int)$restaurant['id'];
    }

    private function tenantUserId(int $restaurantId): ?int
    {
        $user = Auth::user();
        if (!$user || !isset($user['id'], $user['restaurant_id']) || (int)$user['restaurant_id'] !== $restaurantId) {
            return null;
        }
        return (int)$user['id'];
    }

    private function validCsrf(Request $request): bool
    {
        if (CSRF::validate($request->csrfToken())) return true;
        Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
        Response::redirect('/procurement');
        return false;
    }
}
