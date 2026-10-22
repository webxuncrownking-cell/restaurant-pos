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
            "SELECT u.id, u.full_name, u.username, r.name as role_name,
                    CASE WHEN u.offline_pin_salt IS NOT NULL AND u.offline_pin_hash IS NOT NULL
                         THEN 1 ELSE 0 END AS offline_pin_configured
             FROM users u
             JOIN roles r ON r.restaurant_id = u.restaurant_id AND r.id = u.role_id
             WHERE u.restaurant_id = :restaurant_id
               AND u.status = 'active' AND u.deleted_at IS NULL
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
        $isAjax = $request->isAjax();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $deviceId = (int)$request->post('device_id');
        $selectedUserIds = array_values(array_unique(array_filter(
            array_map('intval', (array)$request->post('user_ids', [])),
            static fn(int $id): bool => $id > 0
        )));
        $pinInputs = (array)$request->post('offline_pins', []);

        // Entering a PIN implicitly selects that staff member for offline
        // authorization; this prevents silent failures when the checkbox was
        // accidentally left unchecked in the modal.
        foreach ($pinInputs as $rawUserId => $rawPin) {
            if (trim((string)$rawPin) !== '') {
                $selectedUserIds[] = (int)$rawUserId;
            }
        }
        $selectedUserIds = array_values(array_unique(array_filter(
            array_map('intval', $selectedUserIds),
            static fn(int $id): bool => $id > 0
        )));

        try {
            if ($deviceId <= 0 || !DeviceService::getDeviceById($restaurantId, $deviceId)) {
                throw new Exception('The selected POS device was not found for this restaurant.');
            }

            // Validate every submitted PIN before changing any authorization rows.
            $pinUpdates = [];
            foreach ($pinInputs as $rawUserId => $rawPin) {
                $userId = (int)$rawUserId;
                $pin = trim((string)$rawPin);
                if ($pin === '') {
                    continue; // A blank field preserves the existing PIN.
                }
                if ($userId <= 0 || !in_array($userId, $selectedUserIds, true)) {
                    throw new Exception('Select a staff member before setting their offline PIN.');
                }
                if (!preg_match('/^[0-9]{4,6}$/D', $pin)) {
                    throw new Exception('Offline PINs must contain 4 to 6 digits.');
                }

                $staff = Database::fetchOne(
                    "SELECT id FROM users
                     WHERE restaurant_id = :restaurant_id
                       AND id = :user_id
                       AND status = 'active'
                       AND deleted_at IS NULL",
                    [':restaurant_id' => $restaurantId, ':user_id' => $userId]
                );
                if (!$staff) {
                    throw new Exception('A selected staff member is no longer active for this restaurant.');
                }

                // The browser verifies this exact PBKDF2-HMAC-SHA256 format.
                $saltHex = bin2hex(random_bytes(16));
                $saltBytes = hex2bin($saltHex);
                if ($saltBytes === false) {
                    throw new Exception('Unable to prepare the offline PIN salt.');
                }
                $pinHash = hash_pbkdf2('sha256', $pin, $saltBytes, 10000, 64);
                $pinUpdates[$userId] = ['salt' => $saltHex, 'hash' => $pinHash];
            }

            Database::beginTransaction();
            try {
                Database::query(
                    "DELETE FROM pos_device_staff_authorizations
                     WHERE restaurant_id = :restaurant_id AND device_id = :device_id",
                    [':restaurant_id' => $restaurantId, ':device_id' => $deviceId]
                );

                foreach ($selectedUserIds as $userId) {
                    DeviceService::authorizeStaff($restaurantId, $deviceId, $userId, true);
                }

                foreach ($pinUpdates as $userId => $credentials) {
                    Database::query(
                        "UPDATE users
                         SET offline_pin_salt = :salt, offline_pin_hash = :hash
                         WHERE restaurant_id = :restaurant_id AND id = :user_id",
                        [
                            ':salt' => $credentials['salt'],
                            ':hash' => $credentials['hash'],
                            ':restaurant_id' => $restaurantId,
                            ':user_id' => $userId
                        ]
                    );
                }

                Database::commit();
            } catch (\Throwable $e) {
                Database::rollBack();
                throw $e;
            }

            $message = 'Device staff authorizations updated successfully.';
            if (count($pinUpdates) > 0) {
                $message .= ' Offline PIN saved for ' . count($pinUpdates) . ' staff member(s).';
            }
            // Re-read configured status after commit so the UI confirms
            // that the credentials persisted, not merely that UPDATE ran.
            $configuredRows = Database::fetchAll(
                "SELECT id, offline_pin_salt, offline_pin_hash
                 FROM users
                 WHERE restaurant_id = :restaurant_id AND status = 'active' AND deleted_at IS NULL",
                [':restaurant_id' => $restaurantId]
            );
            $configuredUserIds = [];
            foreach ($configuredRows as $row) {
                if (!empty($row['offline_pin_salt'])
                    && preg_match('/^[a-f0-9]{32}$/i', (string)$row['offline_pin_salt'])
                    && !empty($row['offline_pin_hash'])
                    && preg_match('/^[a-f0-9]{64}$/i', (string)$row['offline_pin_hash'])) {
                    $configuredUserIds[] = (int)$row['id'];
                }
            }

            $expectedPinUserIds = array_map('intval', array_keys($pinUpdates));
            $missingPinUserIds = array_values(array_diff($expectedPinUserIds, $configuredUserIds));
            if (!empty($missingPinUserIds)) {
                throw new \RuntimeException(
                    'The authorization form was processed, but the saved PIN could not be verified for staff ID(s): '
                    . implode(', ', $missingPinUserIds)
                    . '. No PIN was reported as configured; check the local database schema and PHP error log.'
                );
            }

            if ($isAjax) {
                Response::json([
                    'status' => 'success',
                    'message' => $message,
                    'configured_user_ids' => $configuredUserIds,
                    'authorized_user_ids' => $selectedUserIds
                ]);
            }

            $_SESSION['flash_success'] = $message;
        } catch (\Throwable $e) {
            if ($isAjax) {
                Response::json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/devices');
    }
}
