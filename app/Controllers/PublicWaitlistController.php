<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReservationsService;
use InvalidArgumentException;
use Throwable;

class PublicWaitlistController
{
    public function show(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        $restaurant = ReservationsService::getPublicWaitlistRestaurant($token);
        if (!$restaurant || !ReservationsService::schemaReady()) {
            Response::notFound('This waiting-list QR code is invalid or no longer active.');
        }
        Response::render('public_waitlist/join', [
            'restaurant' => $restaurant,
            'token' => $token,
            'csrf_token' => CSRF::getToken(),
            'error' => trim((string)$request->get('error', '')),
        ], 'none');
    }

    public function join(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect('/waitlist/' . rawurlencode($token) . '?error=' . rawurlencode('Your session expired. Please try again.'));
        }
        try {
            $entry = ReservationsService::createPublicWaitlistEntry($token, $request->all());
            Response::redirect('/waitlist/' . rawurlencode($token) . '/status/' . rawurlencode($entry['tracking_token']));
        } catch (InvalidArgumentException $e) {
            Response::redirect('/waitlist/' . rawurlencode($token) . '?error=' . rawurlencode($e->getMessage()));
        } catch (Throwable $e) {
            error_log('[Public Waitlist] ' . $e->getMessage());
            Response::redirect('/waitlist/' . rawurlencode($token) . '?error=' . rawurlencode('We could not add you to the waiting list. Please ask a staff member for help.'));
        }
    }

    public function cancel(Request $request, array $params): void
    {
        $linkToken = strtolower(trim((string)($params['token'] ?? '')));
        $trackingToken = strtolower(trim((string)($params['trackingToken'] ?? '')));
        $statusUrl = '/waitlist/' . rawurlencode($linkToken) . '/status/' . rawurlencode($trackingToken);

        if (!CSRF::validate($request->csrfToken())) {
            Response::redirect($statusUrl . '?error=' . rawurlencode('Your session expired. Please refresh the page and try again.'));
        }
        try {
            $cancelled = ReservationsService::cancelPublicWaitlistEntry($linkToken, $trackingToken);
            if (!$cancelled) Response::redirect($statusUrl . '?error=' . rawurlencode('This queue entry can no longer be cancelled online. Please ask a staff member for help.'));
            Response::redirect($statusUrl);
        } catch (Throwable $e) {
            error_log('[Public Waitlist Cancel] ' . $e->getMessage());
            Response::redirect($statusUrl . '?error=' . rawurlencode('We could not cancel your queue entry. Please ask a staff member for help.'));
        }
    }

    public function status(Request $request, array $params): void
    {
        $linkToken = strtolower(trim((string)($params['token'] ?? '')));
        $trackingToken = strtolower(trim((string)($params['trackingToken'] ?? '')));
        $status = ReservationsService::getPublicWaitlistStatus($linkToken, $trackingToken);
        if (!$status) {
            Response::notFound('This waiting-list status link is invalid or no longer active.');
        }
        Response::render('public_waitlist/status', ['waitlistStatus' => $status, 'linkToken' => $linkToken, 'trackingToken' => $trackingToken, 'csrf_token' => CSRF::getToken(), 'error' => trim((string)$request->get('error', ''))], 'none');
    }
}
