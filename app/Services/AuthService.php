<?php
/**
 * Restaurant POS & Management SaaS
 * Authentication Business Logic Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;

class AuthService
{
    /**
     * Authenticate credentials against either platform superadmins or tenant staff.
     */
    public function authenticate(string $login, string $password, ?string $tenantSlug = null): array
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return ['success' => false, 'error' => 'Please provide both username/email and password.'];
        }

        // 1. Check Platform Superadmin table first
        $platformUser = Database::fetchOne(
            "SELECT * FROM platform_users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1",
            [$login, $login]
        );

        if ($platformUser && password_verify($password, $platformUser['password_hash'])) {
            return [
                'success' => true,
                'is_platform' => true,
                'user' => $platformUser
            ];
        }

        // Resolve staff by globally unique username/email; reject ambiguous legacy accounts.
        $matches = Database::fetchAll(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name,
                    rest.status AS restaurant_status, rest.name AS restaurant_name,
                    rest.activation_status
             FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             JOIN restaurants rest ON rest.id = u.restaurant_id
             WHERE (u.username = ? OR u.email = ?) AND u.deleted_at IS NULL
             ORDER BY u.id ASC LIMIT 2",
            [$login, $login]
        );
        if (count($matches) > 1) {
            return ['success' => false, 'error' => 'This login matches more than one restaurant account. Please contact ServeFlow support.'];
        }
        $staffUser = $matches[0] ?? null;

        if (!$staffUser) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }

        if ($staffUser['status'] !== 'active') {
            return ['success' => false, 'error' => 'This staff account is currently inactive. Contact your manager.'];
        }

        if ($staffUser['restaurant_status'] === 'suspended') {
            return ['success' => false, 'error' => 'This restaurant account is currently suspended. Please contact platform support.'];
        }

        if (!password_verify($password, $staffUser['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }

        return [
            'success' => true,
            'is_platform' => false,
            'user' => $staffUser
        ];
    }


    /**
     * Authenticate staff by username/email and PIN without a tenant slug.
     * Only one exact PIN match is accepted across all matching staff accounts.
     */
    public function authenticateWithPin(string $login, string $pin): array
    {
        $login = trim($login);
        if ($login === '' || !preg_match('/^[0-9]{4,6}$/D', $pin)) {
            return ['success' => false, 'error' => 'Enter your staff username/email and a 4–6 digit PIN.'];
        }

        $staffUsers = Database::fetchAll(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name,
                    rest.status AS restaurant_status, rest.activation_status,
                    rest.name AS restaurant_name
             FROM users u
             JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
             JOIN restaurants rest ON rest.id = u.restaurant_id
             WHERE (u.username = ? OR u.email = ?)
               AND u.deleted_at IS NULL AND u.status = 'active'
               AND rest.status <> 'suspended'
             ORDER BY u.id ASC LIMIT 20",
            [$login, $login]
        );

        $matchedUser = null;
        foreach ($staffUsers as $staffUser) {
            $saltHex = (string)($staffUser['offline_pin_salt'] ?? '');
            $storedHash = (string)($staffUser['offline_pin_hash'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/iD', $saltHex)
                || !preg_match('/^[a-f0-9]{64}$/iD', $storedHash)) {
                continue;
            }
            $salt = hex2bin($saltHex);
            if ($salt === false) continue;
            $candidate = hash_pbkdf2('sha256', $pin, $salt, 10000, 64);
            if (hash_equals(strtolower($storedHash), strtolower($candidate))) {
                if ($matchedUser !== null) {
                    return ['success' => false, 'error' => 'This PIN matches more than one staff account. Please contact your restaurant manager.'];
                }
                $matchedUser = $staffUser;
            }
        }

        if ($matchedUser === null) {
            return ['success' => false, 'error' => 'Invalid staff login or PIN. Check your details and try again.'];
        }
        return ['success' => true, 'is_platform' => false, 'user' => $matchedUser];
    }
    /**
     * Update password and clear must_change_password flag.
     */
    public function updatePassword(int $userId, string $newPassword, bool $isPlatform): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters long.'];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($isPlatform) {
            Database::query(
                "UPDATE platform_users SET password_hash = ?, must_change_password = 0 WHERE id = ?",
                [$hash, $userId]
            );
        } else {
            $tenantId = Auth::getRestaurantId();
            Database::query(
                "UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ? AND restaurant_id = ?",
                [$hash, $userId, $tenantId]
            );
        }

        return ['success' => true];
    }

    /**
     * Record general audit log entry.
     */
    public function logAudit(string $action, string $module, ?int $entityId = null, ?array $old = null, ?array $new = null): void
    {
        $tenantId = Auth::getRestaurantId();
        if (!$tenantId) {
            return;
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userId = Auth::id();
            $oldJson = $old ? json_encode($old) : null;
            $newJson = $new ? json_encode($new) : null;

            Database::query(
                "INSERT INTO general_audit_logs (restaurant_id, user_id, action, module, entity_id, old_values_json, new_values_json, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $userId, $action, $module, $entityId, $oldJson, $newJson, $ip]
            );
        } catch (\Throwable $e) {
            // Silently log audit failure to not disrupt primary flow
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}
