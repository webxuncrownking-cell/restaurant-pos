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

    /**
     * Check a server-owned plan entitlement. Standard POS workflows must never
     * call this for checkout, order processing, KDS, or basic stock operations.
     */
    public static function hasFeature(int $restaurantId, string $feature): bool
    {
        if ($restaurantId <= 0) return false;

        $subscription = Database::fetchOne(
            "SELECT s.status, s.current_period_end, s.grace_period_days,
                    p.slug AS plan_slug, p.features_json
             FROM subscriptions s
             JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.restaurant_id = ?
             ORDER BY s.id DESC LIMIT 1",
            [$restaurantId]
        );

        if (!$subscription) return false;
        $status = (string)($subscription['status'] ?? '');
        $periodEnd = strtotime((string)($subscription['current_period_end'] ?? ''));
        if (!$periodEnd) return false;

        $graceDays = max(0, (int)($subscription['grace_period_days'] ?? 0));
        $withinPaidPeriod = $periodEnd >= time();
        $withinGrace = in_array($status, ['active', 'past_due'], true)
            && time() <= ($periodEnd + ($graceDays * 86400));
        $isTrial = $status === 'trial' && $periodEnd >= time();

        if (!$withinPaidPeriod && !$withinGrace && !$isTrial) return false;
        if (!in_array($status, ['active', 'trial', 'past_due'], true)) return false;

        $features = $subscription['features_json'] ?? '{}';
        if (is_string($features)) {
            $features = json_decode($features, true);
        }
        if (!is_array($features)) $features = [];

        // Feature aliases keep older plan JSON compatible without granting
        // unrelated premium modules.
        $aliases = [
            'advanced_reports' => ['advanced_reports', 'reports'],
            'recipe_costing' => ['recipe_costing', 'recipe_bom'],
            'multi_terminal' => ['multi_terminal', 'multi_device'],
        ];
        $keys = $aliases[$feature] ?? [$feature];
        foreach ($keys as $key) {
            if (array_key_exists($key, $features)) {
                return $features[$key] === true || $features[$key] === 1 || $features[$key] === '1';
            }
        }

        // New ServeFlow tiers can use canonical slugs. No fallback is granted
        // to unknown/legacy plan names: the plan JSON remains authoritative.
        $planSlug = strtolower((string)($subscription['plan_slug'] ?? ''));
        $tierFeatures = [
            'pro' => [
                'advanced_reports', 'customer_crm', 'loyalty', 'combo_meals',
                'scheduled_pricing', 'reservations', 'expenses'
            ],
            'business' => [
                'advanced_reports', 'customer_crm', 'loyalty', 'combo_meals',
                'scheduled_pricing', 'reservations', 'expenses', 'suppliers',
                'purchase_orders', 'recipe_costing', 'expiry_wastage', 'multi_terminal'
            ],
        ];
        return in_array($feature, $tierFeatures[$planSlug] ?? [], true);
    }

    /**
     * Return an entitlement snapshot for navigation and feature screens.
     */
    public static function getEntitlements(int $restaurantId): array
    {
        $features = [
            'advanced_reports', 'customer_crm', 'loyalty', 'combo_meals',
            'scheduled_pricing', 'reservations', 'expenses', 'suppliers',
            'purchase_orders', 'recipe_costing', 'expiry_wastage', 'multi_terminal'
        ];
        $result = [];
        foreach ($features as $feature) {
            $result[$feature] = self::hasFeature($restaurantId, $feature);
        }
        return $result;
    }


    public static function requireFeature(int $restaurantId, string $feature, string $label): void
    {
        if (!\\App\\Core\\Auth::check()) {
            \\App\\Core\\Response::redirect('/login');
        }
        if (\\App\\Core\\Auth::isPlatformAdmin()) return;
        if (!self::hasFeature($restaurantId, $feature)) {
            \App\Core\Response::forbidden(
                $label . ' is not included in your current subscription or the subscription has expired. Your existing POS, orders, and basic inventory remain available. Visit Billing to review available plans.'
            );
        }
    }

}
