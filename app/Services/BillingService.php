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
        $plan = Database::fetchOne("SELECT * FROM subscription_plans WHERE id = :id AND is_active = 1", [':id' => $newPlanId]);
        if (!$plan) {
            throw new InvalidArgumentException('Selected plan does not exist or is inactive.');
        }

        $current = self::getCurrentSubscription($restaurantId);
        if ($current) {
            // Update plan on current subscription
            Database::query(
                "UPDATE subscriptions 
                 SET plan_id = :plan_id, 
                     billing_cycle = :billing_cycle, 
                     status = 'active', 
                     updated_at = NOW() 
                 WHERE restaurant_id = :restaurant_id AND id = :id",
                [
                    ':restaurant_id' => $restaurantId,
                    ':id' => $current['id'],
                    ':plan_id' => $newPlanId,
                    ':billing_cycle' => in_array($billingCycle, ['monthly', 'annually']) ? $billingCycle : 'monthly'
                ]
            );
        } else {
            // Create subscription
            $now = date('Y-m-d H:i:s');
            $periodEnd = date('Y-m-d H:i:s', strtotime($billingCycle === 'annually' ? '+1 year' : '+1 month'));

            Database::query(
                "INSERT INTO subscriptions (restaurant_id, plan_id, status, billing_cycle, current_period_start, current_period_end, created_at)
                 VALUES (:restaurant_id, :plan_id, 'active', :billing_cycle, :start, :end, NOW())",
                [
                    ':restaurant_id' => $restaurantId,
                    ':plan_id' => $newPlanId,
                    ':billing_cycle' => $billingCycle,
                    ':start' => $now,
                    ':end' => $periodEnd
                ]
            );
        }

        return true;
    }
}
