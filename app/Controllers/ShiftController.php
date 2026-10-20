<?php
/**
 * Restaurant POS & Management SaaS
 * Cashier Shift Sessions Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Core\CSRF;
use App\Services\ShiftService;
use App\Services\DeviceService;
use Exception;

class ShiftController
{
    /**
     * Shifts overview page
     */
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        $activeShift = ShiftService::getActiveShift($restaurantId, (int)$user['id']);
        $recentShifts = ShiftService::getShifts($restaurantId, 30);
        $devices = DeviceService::getDevices($restaurantId);

        Response::render('shifts/index', [
            'activeShift' => $activeShift,
            'recentShifts' => $recentShifts,
            'devices' => $devices,
            'user' => $user
        ]);
    }

    /**
     * Open shift form
     */
    public function showOpen(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        $activeShift = ShiftService::getActiveShift($restaurantId, (int)$user['id']);
        if ($activeShift) {
            Response::redirect('/shifts/active');
            return;
        }

        $devices = DeviceService::getDevices($restaurantId);

        Response::render('shifts/open', [
            'devices' => $devices,
            'user' => $user
        ]);
    }

    /**
     * Open shift POST action
     */
    public function open(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        $deviceId = (int)$request->post('device_id');
        $openingCash = (float)$request->post('opening_cash', 0.0);
        $notes = $request->post('notes');

        try {
            $shiftId = ShiftService::openShift($restaurantId, (int)$user['id'], $deviceId, $openingCash, $notes);
            $_SESSION['flash_success'] = "Shift #{$shiftId} opened successfully with float ₱" . number_format($openingCash, 2);
            Response::redirect('/shifts/active');
            return;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/shifts/open');
        }
    }

    /**
     * Active shift dashboard & cash drawer reconciliation
     */
    public function showActive(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        $activeShift = ShiftService::getActiveShift($restaurantId, (int)$user['id']);
        if (!$activeShift) {
            $_SESSION['flash_info'] = 'No open shift found. Please open a shift first.';
            Response::redirect('/shifts/open');
            return;
        }

        $summary = ShiftService::calculateShiftSummary($restaurantId, (int)$activeShift['id']);
        $movements = ShiftService::getShiftMovements($restaurantId, (int)$activeShift['id']);

        Response::render('shifts/active', [
            'activeShift' => $activeShift,
            'summary' => $summary,
            'movements' => $movements,
            'user' => $user
        ]);
    }

    /**
     * Record cash movement (cash in, cash out, drop)
     */
    public function addMovement(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();
        $user = Auth::user();

        $shiftId = (int)$request->post('shift_id');
        $type = (string)$request->post('movement_type');
        $amount = (float)$request->post('amount', 0.0);
        $reason = (string)$request->post('reason', '');

        try {
            ShiftService::recordCashMovement($restaurantId, $shiftId, (int)$user['id'], $type, $amount, $reason);
            $_SESSION['flash_success'] = "Cash movement recorded: " . strtoupper(str_replace('_', ' ', $type)) . " ₱" . number_format($amount, 2);
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/shifts/active');
    }

    /**
     * Close shift audit POST action
     */
    public function close(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $shiftId = (int)$request->post('shift_id');
        $closingCash = (float)$request->post('closing_cash', 0.0);
        $notes = $request->post('notes');

        try {
            $result = ShiftService::closeShift($restaurantId, $shiftId, $closingCash, $notes);
            $diffMsg = $result['difference'] == 0 ? "Exact balance! No variance." : 
                       ($result['difference'] > 0 ? "Overage: +₱" . number_format($result['difference'], 2) : "Shortage: -₱" . number_format(abs($result['difference']), 2));
            
            $_SESSION['flash_success'] = "Shift #{$shiftId} closed successfully. {$diffMsg}";
            Response::redirect('/shifts');
            return;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/shifts/active');
        }
    }
}
