<?php
/**
 * Restaurant POS & Management SaaS
 * Subscription Billing & Invoicing Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class BillingService
{
    /**
     * Get current subscription for a restaurant
     */
    public static function getCurrentSubscription(int $restaurantId): ?array
    {
        $sql = "SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.price_monthly, p.price_annually, p.features_json
                FROM subscriptions s
                JOIN subscription_plans p ON p.id = s.plan_id
                WHERE s.restaurant_id = :restaurant_id
                ORDER BY s.id DESC LIMIT 1";

        $sub = Database::fetchOne($sql, [':restaurant_id' => $restaurantId]);
        if ($sub && !empty($sub['features_json'])) {
            $sub['features'] = is_string($sub['features_json']) ? json_decode($sub['features_json'], true) : $sub['features_json'];
        }

        return $sub;
    }

    /**
     * List all available SaaS pricing plans
     */
    public static function getAvailablePlans(): array
    {
        $sql = "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC";
        $plans = Database::fetchAll($sql);

        foreach ($plans as &$p) {
            $p['features'] = is_string($p['features_json']) ? json_decode($p['features_json'], true) : $p['features_json'];
        }

        return $plans;
    }

    /**
     * List invoices for a restaurant
     */
    public static function getInvoices(int $restaurantId): array
    {
        $sql = "SELECT i.*, p.name as plan_name
                FROM platform_invoices i
                JOIN subscriptions s ON s.restaurant_id = i.restaurant_id AND s.id = i.subscription_id
                JOIN subscription_plans p ON p.id = s.plan_id
                WHERE i.restaurant_id = :restaurant_id
                ORDER BY i.created_at DESC";

        return Database::fetchAll($sql, [':restaurant_id' => $restaurantId]);
    }

    /**
     * Change / Upgrade subscription plan
     */
    public static function changePlan(int $restaurantId, int $newPlanId, string $billingCycle = 'monthly'): bool
    {
        // Never grant a paid tier before a payment has been submitted and verified
        // by a platform administrator. The manual subscription-payment workflow
        // is not enabled yet, so plan changes are intentionally unavailable.
        throw new \InvalidArgumentException(
            'Subscription upgrades are temporarily unavailable while payment verification is being configured. Your existing POS access is unchanged.'
        );
    }
}
