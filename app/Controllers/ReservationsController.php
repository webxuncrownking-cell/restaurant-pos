<?php
/**
 * Restaurant POS & Management SaaS
 * Reservation calendar and waiting-list controller.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Services\ReservationsService;
use App\Services\TableService;
use InvalidArgumentException;
use Throwable;

class ReservationsController
{
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($restaurantId <= 0) {
            Session::setFlash('warning', 'Choose a restaurant workspace before managing reservations.');
            Response::redirect('/dashboard');
        }

        $ready = ReservationsService::schemaReady();
        Response::render('reservations/index', 200, [
            'schema_ready' => $ready,
            'reservations' => $ready ? ReservationsService::getReservations($restaurantId) : [],
            'waitlist' => $ready ? ReservationsService::getWaitlist($restaurantId) : [],
            'tables' => TableService::getTables($restaurantId),
            'flashes' => Session::getFlashes(),
            'csrf_token' => CSRF::getToken(),
            'title' => 'Reservations & Waiting List - Restaurant POS'
        ]);
    }

    public function migrate(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);

        if (!Auth::isPlatformAdmin() && Auth::roleSlug() !== 'owner' && Auth::roleSlug() !== 'manager') {
            Response::forbidden('Only a restaurant owner or manager can apply database updates.');
        }

        try {
            ReservationsService::ensureSchema();
            Session::setFlash('success', 'Reservations and waiting-list tables are ready. Existing restaurant, menu, and sales data was not changed.');
        } catch (Throwable $e) {
            error_log('[Reservations Migration] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to create reservations tables. Check database permissions and the PHP error log.');
        }
        Response::redirect('/reservations');
    }

    public function createReservation(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        try {
            ReservationsService::createReservation($restaurantId, (int)Auth::id(), $request->all());
            Session::setFlash('success', 'Reservation saved.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Reservations] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to save the reservation. Check the details and try again.');
        }
        Response::redirect('/reservations');
    }

    public function reservationAction(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        $reservationId = filter_var($request->post('reservation_id'), FILTER_VALIDATE_INT);
        $tableIdRaw = $request->post('table_id');
        $tableId = $tableIdRaw !== null && $tableIdRaw !== '' ? filter_var($tableIdRaw, FILTER_VALIDATE_INT) : null;
        $target = trim((string)$request->post('status', ''));

        try {
            if (!$reservationId || ($tableIdRaw !== null && $tableIdRaw !== '' && (!$tableId || $tableId <= 0))) {
                throw new InvalidArgumentException('Choose a valid reservation and table.');
            }
            ReservationsService::updateReservationStatus($restaurantId, (int)$reservationId, (int)Auth::id(), $target, $tableId ? (int)$tableId : null);
            $messages = ['seated' => 'Guest seated and table marked occupied.', 'cancelled' => 'Reservation cancelled.', 'no_show' => 'Reservation marked as no-show.'];
            Session::setFlash('success', $messages[$target] ?? 'Reservation updated.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Reservations] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to update this reservation.');
        }
        Response::redirect('/reservations');
    }

    public function createWaitlist(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        try {
            ReservationsService::addWaitlist($restaurantId, (int)Auth::id(), $request->all());
            Session::setFlash('success', 'Guest added to the waiting list.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Waitlist] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to add the guest to the waiting list.');
        }
        Response::redirect('/reservations');
    }

    public function waitlistAction(Request $request): void
    {
        $restaurantId = $this->authorize();
        $this->validateCsrf($request);
        $this->requireSchema();

        $entryId = filter_var($request->post('entry_id'), FILTER_VALIDATE_INT);
        $tableIdRaw = $request->post('table_id');
        $tableId = $tableIdRaw !== null && $tableIdRaw !== '' ? filter_var($tableIdRaw, FILTER_VALIDATE_INT) : null;
        $target = trim((string)$request->post('status', ''));

        try {
            if (!$entryId || ($tableIdRaw !== null && $tableIdRaw !== '' && (!$tableId || $tableId <= 0))) {
                throw new InvalidArgumentException('Choose a valid waiting-list entry and table.');
            }
            ReservationsService::updateWaitlistStatus($restaurantId, (int)$entryId, $target, $tableId ? (int)$tableId : null);
            $messages = ['seated' => 'Guest seated and removed from the active waitlist.', 'left' => 'Guest removed from the waiting list.', 'cancelled' => 'Waiting-list entry cancelled.'];
            Session::setFlash('success', $messages[$target] ?? 'Waiting-list entry updated.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Waitlist] ' . $e->getMessage());
            Session::setFlash('danger', 'Unable to update this waiting-list entry.');
        }
        Response::redirect('/reservations');
    }

    private function authorize(): int
    {
        Auth::requireAuth();
        if (Auth::isPlatformAdmin()) {
            $tenantId = TenantContext::getRestaurantId();
        } else {
            $tenantId = TenantContext::getRestaurantId();
        }
        if (!$tenantId) {
            Session::setFlash('warning', 'Choose a restaurant workspace before managing reservations.');
            Response::redirect('/dashboard');
        }
        return (int)$tenantId;
    }

    private function validateCsrf(Request $request): void
    {
        if (!CSRF::validate($request->csrfToken())) {
            Session::setFlash('danger', 'Your security token expired. Refresh the page and try again.');
            Response::redirect('/reservations');
        }
    }

    private function requireSchema(): void
    {
        if (!ReservationsService::schemaReady()) {
            Session::setFlash('warning', 'Apply the reservations database update before using this feature.');
            Response::redirect('/reservations');
        }
    }
}
