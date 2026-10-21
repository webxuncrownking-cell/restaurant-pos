<?php
/**
 * Restaurant POS & Management SaaS
 * User Authentication, Session State & RBAC Enforcer
 */

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    /**
     * Check if a user is currently logged in.
     */
    public static function check(): bool
    {
        return Session::has('user_id');
    }

    /**
     * Check if the authenticated user is a SaaS Platform Superadmin.
     */
    public static function isPlatformAdmin(): bool
    {
        return Session::get('is_platform_admin') === true;
    }

    /**
     * Retrieve the currently authenticated user record.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$user !== null) {
            return self::$user;
        }

        $userId = (int)Session::get('user_id');
        $isPlatform = Session::get('is_platform_admin') === true;

        try {
            if ($isPlatform) {
                $sql = "SELECT id, username, email, full_name, role, status, must_change_password
                        FROM platform_users WHERE id = ? AND status = 'active' LIMIT 1";
                self::$user = Database::fetchOne($sql, [$userId]);
            } else {
                $tenantId = (int)Session::get('restaurant_id');
                $sql = "SELECT u.id, u.restaurant_id, u.role_id, u.username, u.email, u.full_name, u.status, u.must_change_password,
                               r.name AS role_name, r.slug AS role_slug, rest.name AS restaurant_name
                        FROM users u
                        JOIN roles r ON r.id = u.role_id AND r.restaurant_id = u.restaurant_id
                        JOIN restaurants rest ON rest.id = u.restaurant_id
                        WHERE u.id = ? AND u.restaurant_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
                        LIMIT 1";
                self::$user = Database::fetchOne($sql, [$userId, $tenantId]);
            }
            return self::$user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function id(): ?int
    {
        return Session::has('user_id') ? (int)Session::get('user_id') : null;
    }

    public static function getRestaurantId(): ?int
    {
        return TenantContext::getRestaurantId();
    }

    public static function roleSlug(): ?string
    {
        $user = self::user();
        return $user['role_slug'] ?? ($user['role'] ?? null);
    }

    /**
     * Verify if the active user possesses a specific permission code.
     */
    public static function can(string $permissionCode): bool
    {
        if (self::isPlatformAdmin()) {
            return true; // Superadmins possess all permissions
        }

        $user = self::user();
        if (!$user) {
            return false;
        }

        // Restaurant Owners (slug 'owner') possess all tenant permissions
        if (($user['role_slug'] ?? '') === 'owner') {
            return true;
        }

        if (self::$permissions === null) {
            self::loadPermissions((int)$user['role_id'], (int)$user['restaurant_id']);
        }

        return in_array($permissionCode, self::$permissions, true);
    }

    private static function loadPermissions(int $roleId, int $restaurantId): void
    {
        try {
            $sql = "SELECT p.code
                    FROM role_permissions rp
                    JOIN permissions p ON p.id = rp.permission_id
                    WHERE rp.role_id = ? AND rp.restaurant_id = ?";
            $rows = Database::fetchAll($sql, [$roleId, $restaurantId]);
            self::$permissions = array_column($rows, 'code');
        } catch (\Throwable $e) {
            self::$permissions = [];
        }
    }

    /**
     * Backwards-compatible alias for legacy controllers.
     */
    public static function requireAuth(): void
    {
        self::requireLogin();
    }

    /**
     * Guard middleware requiring active login.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::setFlash('warning', 'Please sign in to access this page.');
            Response::redirect('/login');
        }

        // Enforce password change if flagged
        $user = self::user();
        if ($user && !empty($user['must_change_password'])) {
            $currentPath = (new Request())->getPath();
            if ($currentPath !== '/change-password' && $currentPath !== '/logout') {
                Session::setFlash('info', 'For security, you must update your password before proceeding.');
                Response::redirect('/change-password');
            }
        }
    }

    /**
     * Guard middleware requiring specific role or permission.
     */
    public static function requirePermission(string $permissionCode): void
    {
        self::requireLogin();
        if (!self::can($permissionCode)) {
            Response::forbidden("Action requires permission: '{$permissionCode}'.");
        }
    }

    /**
     * Authenticate and bind user into session.
     */
    public static function login(array $userData, bool $isPlatform = false): void
    {
        Session::start();
        Session::regenerateId(true);
        CSRF::regenerate();

        Session::set('user_id', (int)$userData['id']);
        Session::set('is_platform_admin', $isPlatform);
        Session::set('username', $userData['username']);
        Session::set('full_name', $userData['full_name']);

        if (!$isPlatform && isset($userData['restaurant_id'])) {
            Session::set('restaurant_id', (int)$userData['restaurant_id']);
            TenantContext::setTenant((int)$userData['restaurant_id']);
        }

        self::$user = null;
        self::$permissions = null;
    }

    /**
     * Log out and wipe session state.
     */
    public static function logout(): void
    {
        self::$user = null;
        self::$permissions = null;
        Session::destroy();
    }
}
