<?php
/**
 * Restaurant POS & Management SaaS
 * Device Registration & Terminal Management Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\DeviceService;
use Exception;

class DeviceController
{
    /**
     * Display registered devices and staff mappings
     */
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();

        $devices = DeviceService::getDevices($restaurantId);
        
        // Load staff members for assignment modal
        $staffMembers = Database::fetchAll(
            "SELECT u.id, u.full_name, u.username, r.name as role_name 
             FROM users u 
             JOIN roles r ON r.restaurant_id = u.restaurant_id AND r.id = u.role_id
             WHERE u.restaurant_id = :restaurant_id AND u.is_active = 1
             ORDER BY u.full_name ASC",
            [':restaurant_id' => $restaurantId]
        );

        Response::render('devices/index', [
            'devices' => $devices,
            'staffMembers' => $staffMembers
        ]);
    }

    /**
     * Register a new POS terminal device
     */
    public function register(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        try {
            $deviceName = (string)$request->post('device_name', '');
            $result = DeviceService::registerDevice($restaurantId, $deviceName);

            $_SESSION['new_device_credentials'] = $result;
            $_SESSION['flash_success'] = "Device '{$result['device_name']}' registered successfully! Please store the device pairing token securely.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/devices');
    }

    /**
     * Rotate device secret token
     */
    public function rotate(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $deviceId = (int)$request->post('device_id');

        try {
            $newToken = DeviceService::rotateCredentials($restaurantId, $deviceId);
            $_SESSION['rotated_token'] = [
                'device_id' => $deviceId,
                'token' => $newToken
            ];
            $_SESSION['flash_success'] = "Device credentials rotated successfully! Make sure to update the device terminal.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/devices');
    }

    /**
     * Toggle device status (active / revoked)
     */
    public function toggleStatus(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $deviceId = (int)$request->post('device_id');
        $status = (string)$request->post('status');

        try {
            DeviceService::setDeviceStatus($restaurantId, $deviceId, $status);
            $_SESSION['flash_success'] = "Device status updated to " . ucfirst($status) . ".";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/devices');
    }

    /**
     * Update staff authorizations for a device
     */
    public function updateAuthorizations(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $deviceId = (int)$request->post('device_id');
        $selectedUserIds = (array)$request->post('user_ids', []);

        try {
            // First clear existing authorizations for this device
            Database::query(
                "DELETE FROM pos_device_staff_authorizations WHERE restaurant_id = :restaurant_id AND device_id = :device_id",
                [':restaurant_id' => $restaurantId, ':device_id' => $deviceId]
            );

            // Insert new authorizations
            foreach ($selectedUserIds as $userId) {
                DeviceService::authorizeStaff($restaurantId, $deviceId, (int)$userId, true);
            }

            $_SESSION['flash_success'] = "Device staff authorizations updated successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/devices');
    }
}
