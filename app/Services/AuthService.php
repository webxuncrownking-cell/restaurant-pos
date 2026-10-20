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

        // 2. Tenant Staff Authentication
        // If tenantSlug is provided, scope to that restaurant; otherwise search by unique username/email
        $params = [$login, $login];
        $slugCondition = "";

        if ($tenantSlug && trim($tenantSlug) !== '') {
            $slugCondition = "AND rest.slug = ?";
            $params[] = trim($tenantSlug);
        }

        $sql = "SELECT u.*, r.slug AS role_slug, r.name AS role_name, rest.status AS restaurant_status, rest.name AS restaurant_name
                FROM users u
                JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
                JOIN restaurants rest ON rest.id = u.restaurant_id
                WHERE (u.username = ? OR u.email = ?) AND u.deleted_at IS NULL {$slugCondition}
                LIMIT 1";

        $staffUser = Database::fetchOne($sql, $params);

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
