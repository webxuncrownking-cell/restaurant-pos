<?php
/**
 * Restaurant POS & Management SaaS
 * Kitchen Display System controller.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\KitchenService;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

class KitchenController
{
    public function index(Request $request): void
    {
        $restaurantId = $this->authorizeKitchen();
        $tickets = KitchenService::getActiveTickets($restaurantId);
        $counts = ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'served' => 0];
        $delayedCount = 0;

        foreach ($tickets as $ticket) {
            $status = (string)$ticket['order_status'];
            if (isset($counts[$status])) $counts[$status]++;
            if (in_array($status, ['pending', 'preparing'], true) && (int)$ticket['elapsed_seconds'] >= 900) {
                $delayedCount++;
            }
        }

        // Render directly because some existing XAMPP installations use a legacy
        // Response::render() signature. This view includes its own header/navbar/footer.
        $viewData = [
            'tickets' => $tickets,
            'counts' => $counts,
            'delayed_count' => $delayedCount,
            'ticket_signature' => KitchenService::ticketSignature($tickets),
            'flashes' => Session::getFlashes(),
            'title' => 'Kitchen Display - Restaurant POS',
        ];
        http_response_code(200);
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        extract($viewData, EXTR_SKIP);
        require APP_ROOT . '/app/Views/kitchen/index.php';
        return;
    }

    public function ordersJson(Request $request): void
    {
        $restaurantId = $this->authorizeKitchen();
        $tickets = KitchenService::getActiveTickets($restaurantId);
        Response::json([
            'signature' => KitchenService::ticketSignature($tickets),
            'active_count' => count($tickets),
            'server_time' => time()
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $restaurantId = $this->authorizeKitchen(true);

        if (!CSRF::validate($request->csrfToken())) {
            Session::setFlash('danger', 'Your security token expired. Refresh the kitchen display and try again.');
            Response::redirect('/kitchen');
        }

        $orderId = filter_var($request->post('order_id'), FILTER_VALIDATE_INT);
        $targetStatus = trim((string)$request->post('status', ''));
        if (!$orderId || $orderId <= 0) {
            Session::setFlash('danger', 'Select a valid kitchen ticket.');
            Response::redirect('/kitchen');
        }

        try {
            KitchenService::updateTicketStatus(
                $restaurantId,
                (int)$orderId,
                $targetStatus,
                (int)Auth::id()
            );
            $messages = [
                'preparing' => 'Order moved to preparing.',
                'ready' => 'Order marked ready.',
                'served' => 'Order marked served.',
                'completed' => 'Order completed.'
            ];
            Session::setFlash('success', $messages[$targetStatus] ?? 'Kitchen ticket updated.');
        } catch (InvalidArgumentException|RuntimeException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Kitchen Display] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to update this kitchen ticket. Check the application log and try again.');
        }

        Response::redirect('/kitchen');
    }

    private function authorizeKitchen(bool $forUpdate = false): int
    {
        Auth::requireLogin();
        $role = (string)(Auth::roleSlug() ?? '');
        $canView = in_array($role, ['owner', 'manager', 'kitchen'], true)
            || Auth::can('kitchen.view_kds');
        if (Auth::isPlatformAdmin()) $canView = true;

        if (!$canView) {
            Response::forbidden('Kitchen Display access is available to kitchen staff, managers, and restaurant owners.');
        }

        if ($forUpdate) {
            if (Auth::isPlatformAdmin()) {
                Response::forbidden('Kitchen ticket changes must be made by a staff account belonging to the selected restaurant.');
            }
            $canUpdate = in_array($role, ['owner', 'manager', 'kitchen'], true)
                || Auth::can('kitchen.update_state');
            if (!$canUpdate) {
                Response::forbidden('You have read-only access to Kitchen Display.');
            }
        }

        $restaurantId = TenantContext::getRestaurantId();
        if (!$restaurantId) {
            Session::setFlash('warning', 'Select a restaurant workspace before opening Kitchen Display.');
            Response::redirect('/dashboard');
        }

        return (int)$restaurantId;
    }
}
