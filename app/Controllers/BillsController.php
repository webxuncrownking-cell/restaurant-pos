<?php
/**
 * Restaurant POS & Management SaaS
 * Open bill and split-payment controller.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\BillService;
use InvalidArgumentException;
use Throwable;

class BillsController
{
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($restaurantId <= 0) {
            Session::setFlash('warning', 'Choose a restaurant workspace before opening bills.');
            Response::redirect('/dashboard');
        }

        $schemaReady = BillService::schemaReady();
        Response::render('bills/index', [
            'schema_ready' => $schemaReady,
            'bills' => $schemaReady ? BillService::getOpenBills($restaurantId) : [],
            'flashes' => Session::getFlashes(),
            'title' => 'Open Bills & Split Payments - Restaurant POS'
        ]);
    }

    public function migrate(Request $request): void
    {
        $this->authorize();
        $this->validateCsrf($request);

        $role = (string)(Auth::roleSlug() ?? '');
        if (!Auth::isPlatformAdmin() && !in_array($role, ['owner', 'manager'], true)) {
            Response::forbidden('Only a restaurant owner or manager can apply database updates.');
        }

        try {
            BillService::ensureSchema();
            Session::setFlash('success', 'Open Bills and split-payment tables are ready. Existing orders and payment records were preserved.');
        } catch (Throwable $e) {
            error_log('[Bills Migration] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to prepare the bill-splitting tables. Check database permissions and the PHP error log.');
        }
        Response::redirect('/bills');
    }

    public function createEqualSplits(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        $orderId = filter_var($request->post('order_id'), FILTER_VALIDATE_INT);
        $count = filter_var($request->post('split_count'), FILTER_VALIDATE_INT);
        $rawLabels = trim((string)$request->post('split_labels', ''));
        $labels = $rawLabels === '' ? [] : preg_split('/\r?\n/', $rawLabels);

        try {
            if (!$orderId || $orderId <= 0 || !$count || $count < 2 || $count > 12) {
                throw new InvalidArgumentException('Choose a bill and split it into 2 to 12 shares.');
            }
            BillService::createEqualSplits($restaurantId, (int)$orderId, (int)Auth::id(), (int)$count, is_array($labels) ? $labels : []);
            Session::setFlash('success', 'Equal bill shares created. Each guest can now pay all or part of their share.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Bills Split] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to split this bill.');
        }
        Response::redirect('/bills');
    }

    public function collectPayment(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        $orderId = filter_var($request->post('order_id'), FILTER_VALIDATE_INT);
        $splitIdRaw = $request->post('split_id');
        $splitId = $splitIdRaw !== null && $splitIdRaw !== '' ? filter_var($splitIdRaw, FILTER_VALIDATE_INT) : null;
        $amountRaw = $request->post('amount');
        $tenderedRaw = $request->post('tendered_amount', $amountRaw);
        $method = trim((string)$request->post('payment_method', 'cash'));
        $uuid = trim((string)$request->post('payment_uuid', ''));

        try {
            if (!$orderId || $orderId <= 0) {
                throw new InvalidArgumentException('Choose a valid open bill.');
            }
            if ($splitIdRaw !== null && $splitIdRaw !== '' && (!$splitId || $splitId <= 0)) {
                throw new InvalidArgumentException('Choose a valid guest share.');
            }
            if (!is_numeric($amountRaw) || !is_numeric($tenderedRaw)) {
                throw new InvalidArgumentException('Enter a valid payment amount.');
            }
            $result = BillService::recordPayment(
                $restaurantId,
                (int)$orderId,
                (int)Auth::id(),
                $uuid,
                (float)$amountRaw,
                (float)$tenderedRaw,
                $method,
                $splitId ? (int)$splitId : null
            );

            if (($result['status'] ?? '') === 'already_recorded') {
                Session::setFlash('info', 'This payment was already recorded; no duplicate payment was created.');
            } else {
                $balance = (float)($result['balance_due'] ?? 0);
                Session::setFlash(
                    $balance <= 0.009 ? 'success' : 'info',
                    $balance <= 0.009
                        ? 'Payment recorded. The bill is now fully paid.'
                        : 'Payment recorded. Remaining balance: ₱' . number_format($balance, 2) . '.'
                );
            }
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Bill Payment] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to record this payment. Check the payment history before retrying.');
        }
        Response::redirect('/bills');
    }

    private function authorize(): int
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        if (!$restaurantId) {
            Session::setFlash('warning', 'Choose a restaurant workspace before opening bills.');
            Response::redirect('/dashboard');
        }
        return (int)$restaurantId;
    }

    private function validateCsrf(Request $request): void
    {
        if (!CSRF::validate($request->csrfToken())) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/bills');
        }
    }

    private function requireSchema(): void
    {
        if (!BillService::schemaReady()) {
            Session::setFlash('warning', 'Apply the Open Bills database update before using partial payments.');
            Response::redirect('/bills');
        }
    }
}
