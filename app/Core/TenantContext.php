<?php
/**
 * Restaurant POS & Management SaaS
 * Server-Side Tenant Isolation Context
 */

declare(strict_types=1);

namespace App\Core;

class TenantContext
{
    private static ?array $activeRestaurant = null;
    private static ?int $activeRestaurantId = null;

    /**
     * Set the active tenant context for the current request.
     */
    public static function setTenant(int $restaurantId, ?array $restaurantData = null): void
    {
        self::$activeRestaurantId = $restaurantId;
        self::$activeRestaurant = $restaurantData;
    }

    /**
     * Get the current active tenant ID.
     */
    public static function getRestaurantId(): ?int
    {
        if (self::$activeRestaurantId !== null) {
            return self::$activeRestaurantId;
        }

        // 1. Resolve from session
        Session::start();
        $sessionTenantId = Session::get('restaurant_id');
        if ($sessionTenantId !== null) {
            self::$activeRestaurantId = (int)$sessionTenantId;
            return self::$activeRestaurantId;
        }

        return null;
    }

    /**
     * Retrieve the full restaurant record with subscription details.
     */
    public static function getRestaurant(): ?array
    {
        if (self::$activeRestaurant !== null) {
            return self::$activeRestaurant;
        }

        $tenantId = self::getRestaurantId();
        if (!$tenantId) {
            return null;
        }

        try {
            $sql = "SELECT r.*, s.status AS subscription_status, s.current_period_end, s.grace_period_days, sp.name AS plan_name
                    FROM restaurants r
                    LEFT JOIN subscriptions s ON s.restaurant_id = r.id
                    LEFT JOIN subscription_plans sp ON sp.id = s.plan_id
                    WHERE r.id = ? AND r.deleted_at IS NULL
                    LIMIT 1";
            self::$activeRestaurant = Database::fetchOne($sql, [$tenantId]);
            return self::$activeRestaurant;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Verify whether the tenant's subscription is currently active or in valid grace period.
     */
    public static function isSubscriptionValid(): bool
    {
        $resto = self::getRestaurant();
        if (!$resto) {
            return false;
        }

        $status = $resto['subscription_status'] ?? 'pending_setup';
        if ($status === 'active' || $status === 'trial') {
            return true;
        }

        // Check if within grace period
        $periodEnd = strtotime($resto['current_period_end'] ?? 'now');
        $graceDays = (int)($resto['grace_period_days'] ?? 7);
        $graceExpiry = $periodEnd + ($graceDays * 86400);

        return time() <= $graceExpiry;
    }

    /**
     * Calculate remaining days in grace period.
     */
    public static function getGraceDaysRemaining(): int
    {
        $resto = self::getRestaurant();
        if (!$resto || empty($resto['current_period_end'])) {
            return 0;
        }

        $periodEnd = strtotime($resto['current_period_end']);
        $graceDays = (int)($resto['grace_period_days'] ?? 7);
        $graceExpiry = $periodEnd + ($graceDays * 86400);

        $diff = $graceExpiry - time();
        return max(0, (int)ceil($diff / 86400));
    }
}
