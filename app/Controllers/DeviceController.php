<?php
/**
 * Restaurant POS & Management SaaS
 * Device Registration & Terminal Management Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BillingService;

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
     * Owner-only PIN setup for staff terminal sign-in.
     * Kept separate from premium multi-terminal device management.
     */
    public function pinSettings(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner can manage staff terminal PINs.');
        }

        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $canManageStaff = BillingService::hasFeature($restaurantId, 'staff_management');
        $staffLimit = BillingService::getStaffLimit($restaurantId);
        $staffCountRow = Database::fetchOne(
            "SELECT COUNT(*) AS total
             FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             WHERE u.restaurant_id = :restaurant_id
               AND u.status = 'active' AND u.deleted_at IS NULL
               AND r.slug <> 'owner'",
            [':restaurant_id' => $restaurantId]
        );
        $staffCount = (int)($staffCountRow['total'] ?? 0);
        $staffMembers = Database::fetchAll(
            "SELECT u.id, u.full_name, u.username, u.email, u.status, r.name AS role_name,
                    CASE WHEN u.offline_pin_salt IS NOT NULL AND u.offline_pin_hash IS NOT NULL
                         THEN 1 ELSE 0 END AS pin_configured
             FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             WHERE u.restaurant_id = :restaurant_id AND u.deleted_at IS NULL AND r.slug <> 'owner'
             ORDER BY u.full_name ASC",
            [':restaurant_id' => $restaurantId]
        );
        $staffRoles = Database::fetchAll(
            "SELECT id, name, slug FROM roles
             WHERE restaurant_id = :restaurant_id AND slug IN ('manager', 'cashier')
             ORDER BY CASE slug WHEN 'manager' THEN 1 WHEN 'cashier' THEN 2 ELSE 3 END",
            [':restaurant_id' => $restaurantId]
        );

        Response::render('devices/staff_pins', [
            'staffMembers' => $staffMembers,
            'staffRoles' => $staffRoles,
            'canManageStaff' => $canManageStaff,
            'staffLimit' => $staffLimit,
            'staffCount' => $staffCount
        ]);
    }

    /**
     * Create a tenant-scoped staff account. Owners cannot create another owner.
     */
    public function createStaff(Request $request): void
    {
        Auth::requireLogin();
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if (!BillingService::hasFeature($restaurantId, 'staff_management')) {
            Response::forbidden('Staff management is not included in your current subscription.');
        }
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner can create staff accounts.');
        }
        CSRF::validateToken($request->post('_csrf_token'));

        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $fullName = trim((string)$request->post('full_name', ''));
        $username = trim((string)$request->post('username', ''));
        // Staff accounts do not require an email address. The users table
        // requires a value, so use a non-deliverable internal placeholder.
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');
        $roleId = (int)$request->post('role_id', 0);
        $pin = trim((string)$request->post('pin', ''));

        if ($restaurantId <= 0 || $fullName === '' || strlen($fullName) > 100 ||
            !preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $username) ||
            ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) ||
            strlen($password) < 8 || strlen($password) > 200 ||
            $roleId <= 0 || ($pin !== '' && !preg_match('/^[0-9]{4,6}$/D', $pin))) {
            $_SESSION['flash_error'] = 'Enter a valid name, username (3–50 characters), password (at least 8 characters), role, and optional 4–6 digit PIN.';
            Response::redirect('/staff-pins');
        }

        if ($email === '') {
            $email = strtolower($username) . '@staff.invalid';
        }

        $role = Database::fetchOne(
            "SELECT id FROM roles WHERE id = :role_id AND restaurant_id = :restaurant_id AND slug IN ('manager', 'cashier') LIMIT 1",
            [':role_id' => $roleId, ':restaurant_id' => $restaurantId]
        );
        if (!$role) {
            $_SESSION['flash_error'] = 'Choose a valid staff role for this restaurant.';
            Response::redirect('/staff-pins');
        }

        $staffLimit = BillingService::getStaffLimit($restaurantId);
        if ($staffLimit > 0) {
            $staffCountRow = Database::fetchOne(
                "SELECT COUNT(*) AS total
                 FROM users u
                 JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
                 WHERE u.restaurant_id = :restaurant_id
                   AND u.status = 'active' AND u.deleted_at IS NULL
                   AND r.slug <> 'owner'",
                [':restaurant_id' => $restaurantId]
            );
            $staffCount = (int)($staffCountRow['total'] ?? 0);
            if ($staffCount >= $staffLimit) {
                $_SESSION['flash_error'] = "Your current subscription allows up to {$staffLimit} active staff accounts. Upgrade your plan to add more.";
                Response::redirect('/staff-pins');
                return;
            }
        }

        $duplicate = Database::fetchOne(
            "SELECT id FROM users
             WHERE deleted_at IS NULL
               AND (username = :username OR email = :email) LIMIT 1",
            [':username' => $username, ':email' => $email]
        );
        if ($duplicate) {
            $_SESSION['flash_error'] = 'That username or email is already used by an account in this restaurant.';
            Response::redirect('/staff-pins');
        }

        $pinSalt = null;
        $pinHash = null;
        if ($pin !== '') {
            $pinSalt = bin2hex(random_bytes(16));
            $pinHash = hash_pbkdf2('sha256', $pin, hex2bin($pinSalt), 10000, 64);
        }

        Database::query(
            "INSERT INTO users
                (restaurant_id, role_id, username, email, password_hash, offline_pin_salt,
                 offline_pin_hash, full_name, status, must_change_password)
             VALUES
                (:restaurant_id, :role_id, :username, :email, :password_hash, :pin_salt,
                 :pin_hash, :full_name, 'active', 1)",
            [
                ':restaurant_id' => $restaurantId,
                ':role_id' => $roleId,
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                ':pin_salt' => $pinSalt,
                ':pin_hash' => $pinHash,
                ':full_name' => $fullName
            ]
        );

        $_SESSION['flash_success'] = 'Staff account created. The staff member must change the temporary password at first sign-in.';
        Response::redirect('/staff-pins');
    }

    /**
     * Save a 4-6 digit staff terminal PIN using the same PBKDF2 format
     * used by the existing offline terminal authorization feature.
     */
    public function saveStaffPin(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner can manage staff terminal PINs.');
        }
        CSRF::validateToken($request->post('_csrf_token'));

        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $userId = (int)$request->post('user_id', 0);
        $pin = trim((string)$request->post('pin', ''));

        if ($userId <= 0 || !preg_match('/^[0-9]{4,6}$/D', $pin)) {
            $_SESSION['flash_error'] = 'Choose a staff member and enter a PIN containing 4 to 6 digits.';
            Response::redirect('/staff-pins');
        }

        $staff = Database::fetchOne(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             WHERE u.id = :user_id AND u.restaurant_id = :restaurant_id
               AND u.status = 'active' AND u.deleted_at IS NULL AND r.slug <> 'owner'",
            [':user_id' => $userId, ':restaurant_id' => $restaurantId]
        );
        if (!$staff) {
            $_SESSION['flash_error'] = 'The selected staff account is not active in this restaurant.';
            Response::redirect('/staff-pins');
        }

        $saltHex = bin2hex(random_bytes(16));
        $saltBytes = hex2bin($saltHex);
        $pinHash = hash_pbkdf2('sha256', $pin, $saltBytes, 10000, 64);
        Database::query(
            "UPDATE users SET offline_pin_salt = :salt, offline_pin_hash = :hash
             WHERE id = :user_id AND restaurant_id = :restaurant_id",
            [':salt' => $saltHex, ':hash' => $pinHash, ':user_id' => $userId, ':restaurant_id' => $restaurantId]
        );

        $_SESSION['flash_success'] = 'Staff terminal PIN saved successfully.';
        Response::redirect('/staff-pins');
    }

    /**
     * Update a staff account's basic details and optional terminal PIN.
     */
    public function updateStaffDetails(Request $request): void
    {
        Auth::requireLogin();
        if (Auth::isPlatformAdmin() || Auth::roleSlug() !== 'owner') {
            Response::forbidden('Only the restaurant owner can manage staff accounts.');
        }
        CSRF::validateToken($request->post('_csrf_token'));

        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $userId = (int)$request->post('user_id', 0);
        $fullName = trim((string)$request->post('full_name', ''));
        $username = trim((string)$request->post('username', ''));
        $email = trim((string)$request->post('email', ''));
        $status = (string)$request->post('status', 'active');
        $pin = trim((string)$request->post('pin', ''));

        if ($userId <= 0 || $fullName === '' || $username === ''
            || strlen($fullName) > 150 || strlen($username) > 80
            || !in_array($status, ['active', 'inactive'], true)
            || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190))
            || ($pin !== '' && !preg_match('/^[0-9]{4,6}$/D', $pin))) {
            $_SESSION['flash_error'] = 'Please check the staff details. Name and username are required, and the PIN must contain 4 to 6 digits.';
            Response::redirect('/staff-pins');
        }

        $staff = Database::fetchOne(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             WHERE u.id = :user_id AND u.restaurant_id = :restaurant_id
               AND u.deleted_at IS NULL AND r.slug <> 'owner'",
            [':user_id' => $userId, ':restaurant_id' => $restaurantId]
        );
        if (!$staff) {
            $_SESSION['flash_error'] = 'The selected staff account was not found in this restaurant.';
            Response::redirect('/staff-pins');
        }

        $duplicate = Database::fetchOne(
            "SELECT id FROM users
             WHERE restaurant_id = :restaurant_id AND id <> :user_id AND deleted_at IS NULL
               AND (username = :username OR (:email_check <> '' AND email = :email)) LIMIT 1",
            [
                ':restaurant_id' => $restaurantId,
                ':user_id' => $userId,
                ':username' => $username,
                ':email_check' => $email,
                ':email' => $email
            ]
        );
        if ($duplicate) {
            $_SESSION['flash_error'] = 'That username or email address is already used by another account in this restaurant.';
            Response::redirect('/staff-pins');
        }

        $params = [
            ':full_name' => $fullName,
            ':username' => $username,
            ':email' => $email !== '' ? $email : null,
            ':status' => $status,
            ':user_id' => $userId,
            ':restaurant_id' => $restaurantId
        ];
        $sql = "UPDATE users
                SET full_name = :full_name, username = :username, email = :email, status = :status
                WHERE id = :user_id AND restaurant_id = :restaurant_id";

        if ($pin !== '') {
            $saltHex = bin2hex(random_bytes(16));
            $saltBytes = hex2bin($saltHex);
            $pinHash = hash_pbkdf2('sha256', $pin, $saltBytes, 10000, 64);
            $sql = "UPDATE users
                    SET full_name = :full_name, username = :username, email = :email, status = :status,
                        offline_pin_salt = :pin_salt, offline_pin_hash = :pin_hash
                    WHERE id = :user_id AND restaurant_id = :restaurant_id";
            $params[':pin_salt'] = $saltHex;
            $params[':pin_hash'] = $pinHash;
        }

        Database::query($sql, $params);
        $_SESSION['flash_success'] = 'Staff details updated successfully.';
        Response::redirect('/staff-pins');
    }

    /**
     * Display registered devices and staff mappings
     */
    public function index(Request $request): void
    {
        BillingService::requireFeature((int)(TenantContext::getRestaurantId() ?? 0), 'multi_terminal', 'Multi-terminal management');
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
        BillingService::requireFeature((int)(TenantContext::getRestaurantId() ?? 0), 'multi_terminal', 'Multi-terminal management');
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
        BillingService::requireFeature((int)(TenantContext::getRestaurantId() ?? 0), 'multi_terminal', 'Multi-terminal management');
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
        BillingService::requireFeature((int)(TenantContext::getRestaurantId() ?? 0), 'multi_terminal', 'Multi-terminal management');
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
        BillingService::requireFeature((int)(TenantContext::getRestaurantId() ?? 0), 'multi_terminal', 'Multi-terminal management');
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

            // Clear any flash from a previous failed attempt so stale errors
            // cannot appear beside the current successful save result.
            unset($_SESSION['flash_error']);
            if ($isAjax) {
                unset($_SESSION['flash_success']);
                Response::json([
                    'status' => 'success',
                    'message' => $message,
                    'configured_user_ids' => $configuredUserIds,
                    'authorized_user_ids' => $selectedUserIds
                ]);
            }

            $_SESSION['flash_success'] = $message;
        } catch (\Throwable $e) {
            unset($_SESSION['flash_success']);
            if ($isAjax) {
                unset($_SESSION['flash_error']);
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
