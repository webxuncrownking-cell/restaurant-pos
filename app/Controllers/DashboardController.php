<?php
/**
 * Restaurant POS & Management SaaS
 * Multi-Tenant Dashboard Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantContext;

class DashboardController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        $isPlatform = Auth::isPlatformAdmin();
        $flashes = Session::getFlashes();

        $data = [
            'user' => $user,
            'is_platform' => $isPlatform,
            'flashes' => $flashes,
            'title' => 'Dashboard - Restaurant POS & Management'
        ];

        if ($isPlatform) {
            // Platform Superadmin Overview Metrics
            try {
                $data['total_restaurants'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM restaurants WHERE deleted_at IS NULL")['c'] ?? 0);
                $data['active_tenants'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM restaurants WHERE deleted_at IS NULL AND status = 'active'")['c'] ?? 0);
                $data['active_subscriptions'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM subscriptions WHERE status IN ('active','trial')")['c'] ?? 0);
                $data['total_devices'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM pos_devices WHERE status = 'active'")['c'] ?? 0);

                // SaaS recurring revenue estimate; this is not the same as cash collected.
                $mrr = Database::fetchOne(
                    "SELECT COALESCE(SUM(CASE
                        WHEN LOWER(s.billing_cycle) IN ('annual', 'annually', 'yearly')
                            THEN COALESCE(sp.price_annually, 0) / 12
                        ELSE COALESCE(sp.price_monthly, 0)
                    END), 0) AS amount
                    FROM subscriptions s
                    INNER JOIN subscription_plans sp ON sp.id = s.plan_id
                    WHERE s.status = 'active'"
                );
                $data['estimated_mrr'] = (float)($mrr['amount'] ?? 0);

                $monthOrders = Database::fetchOne(
                    "SELECT COUNT(*) AS orders_count,
                            COUNT(DISTINCT restaurant_id) AS active_restaurants
                     FROM orders
                     WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                       AND order_status <> 'cancelled'"
                );
                $data['orders_this_month'] = (int)($monthOrders['orders_count'] ?? 0);
                $data['active_restaurants_this_month'] = (int)($monthOrders['active_restaurants'] ?? 0);

                $data['restaurants'] = Database::fetchAll("SELECT r.*, s.status AS sub_status, sp.name AS plan_name FROM restaurants r LEFT JOIN subscriptions s ON s.restaurant_id = r.id LEFT JOIN subscription_plans sp ON sp.id = s.plan_id WHERE r.deleted_at IS NULL ORDER BY r.id ASC");
            } catch (\Throwable $e) {
                $data['db_error'] = $e->getMessage();
            }
        } else {
            // Tenant Staff Overview
            $restaurantId = Auth::getRestaurantId();
            $restaurant = TenantContext::getRestaurant();

            $data['restaurant'] = $restaurant;
            $data['grace_days'] = TenantContext::getGraceDaysRemaining();
            $data['is_sub_valid'] = TenantContext::isSubscriptionValid();

            try {
                $data['tables_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM restaurant_tables WHERE restaurant_id = ? AND deleted_at IS NULL", [$restaurantId])['c'] ?? 0);
                $data['products_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM products WHERE restaurant_id = ? AND is_active = 1", [$restaurantId])['c'] ?? 0);
                $data['ingredients_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE restaurant_id = ? AND is_active = 1", [$restaurantId])['c'] ?? 0);
                $data['devices_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM pos_devices WHERE restaurant_id = ? AND status = 'active'", [$restaurantId])['c'] ?? 0);
                $data['staff_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM users WHERE restaurant_id = ? AND status = 'active' AND deleted_at IS NULL", [$restaurantId])['c'] ?? 0);
                
                // Low stock alerts count
                $data['low_stock_count'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM ingredients WHERE restaurant_id = ? AND current_stock <= minimum_stock_alert AND is_active = 1", [$restaurantId])['c'] ?? 0);

                // Fetch registered devices for this tenant
                $data['devices'] = Database::fetchAll("SELECT * FROM pos_devices WHERE restaurant_id = ? ORDER BY id ASC", [$restaurantId]);
            } catch (\Throwable $e) {
                $data['db_error'] = $e->getMessage();
            }
        }

        Response::render('dashboard/index', $data, 'app');
    }


    public function platformAnalytics(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) {
            Response::forbidden("Only platform superadmins can view platform analytics.");
        }

        $data = ['title' => 'Platform Analytics', 'analytics_error' => null];
        try {
            $data['total_restaurants'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM restaurants WHERE deleted_at IS NULL")['c'] ?? 0);
            $data['active_restaurants'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM restaurants WHERE deleted_at IS NULL AND status = 'active'")['c'] ?? 0);
            $data['subscription_counts'] = Database::fetchAll(
                "SELECT status, COUNT(*) AS total FROM subscriptions GROUP BY status ORDER BY total DESC"
            );
            $mrr = Database::fetchOne(
                "SELECT COALESCE(SUM(CASE
                    WHEN LOWER(s.billing_cycle) IN ('annual', 'annually', 'yearly')
                        THEN COALESCE(sp.price_annually, 0) / 12
                    ELSE COALESCE(sp.price_monthly, 0)
                END), 0) AS amount
                FROM subscriptions s
                INNER JOIN subscription_plans sp ON sp.id = s.plan_id
                WHERE s.status = 'active'"
            );
            $data['estimated_mrr'] = (float)($mrr['amount'] ?? 0);
            $data['plan_distribution'] = Database::fetchAll(
                "SELECT sp.name AS plan_name, COUNT(*) AS total
                 FROM subscriptions s
                 INNER JOIN subscription_plans sp ON sp.id = s.plan_id
                 WHERE s.status IN ('active', 'trial')
                 GROUP BY sp.id, sp.name
                 ORDER BY total DESC, sp.name ASC"
            );
            $data['monthly_orders'] = Database::fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                        COUNT(*) AS total_orders,
                        COUNT(DISTINCT restaurant_id) AS active_restaurants
                 FROM orders
                 WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                   AND order_status <> 'cancelled'
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month_key ASC"
            );
            $data['actual_collected_total'] = (float)(Database::fetchOne(
                "SELECT COALESCE(SUM(CASE WHEN status = 'succeeded' THEN amount WHEN status = 'refunded' THEN -amount ELSE 0 END), 0) AS amount FROM platform_payments"
            )['amount'] ?? 0);
            $data['actual_collected_month'] = (float)(Database::fetchOne(
                "SELECT COALESCE(SUM(CASE WHEN status = 'succeeded' THEN amount WHEN status = 'refunded' THEN -amount ELSE 0 END), 0) AS amount
                 FROM platform_payments
                 WHERE status IN ('succeeded', 'refunded') AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                   AND paid_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
            )['amount'] ?? 0);
            $data['outstanding_invoices'] = Database::fetchOne(
                "SELECT COUNT(*) AS invoice_count, COALESCE(SUM(total_amount), 0) AS amount
                 FROM platform_invoices WHERE status = 'open'"
            ) ?? ['invoice_count' => 0, 'amount' => 0];
            $data['monthly_collections'] = Database::fetchAll(
                "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month_key,
                        COALESCE(SUM(CASE WHEN status = 'succeeded' THEN amount WHEN status = 'refunded' THEN -amount ELSE 0 END), 0) AS collected,
                        SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) AS payment_count
                 FROM platform_payments
                 WHERE status IN ('succeeded', 'refunded') AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                 GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
                 ORDER BY month_key ASC"
            );
            $data['monthly_registrations'] = Database::fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS new_restaurants
                 FROM restaurants
                 WHERE deleted_at IS NULL AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month_key ASC"
            );
            $data['inactive_restaurants'] = max(0, (int)$data['total_restaurants'] - (int)$data['active_restaurants']);
        } catch (\Throwable $e) {
            $data['analytics_error'] = 'Some analytics could not be loaded. Check database schema and application logs.';
        }

        Response::render('dashboard/platform-analytics', $data, 'app');
    }

    public function restaurantAccounts(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) {
            Response::forbidden("Only platform superadmins can manage restaurant accounts.");
        }

        $data = ['title' => 'Restaurant Accounts', 'restaurants' => [], 'accounts_error' => null];
        try {
            $data['restaurants'] = Database::fetchAll(
                "SELECT r.*, s.status AS sub_status, sp.name AS plan_name
                 FROM restaurants r
                 LEFT JOIN subscriptions s ON s.restaurant_id = r.id
                 LEFT JOIN subscription_plans sp ON sp.id = s.plan_id
                 WHERE r.deleted_at IS NULL
                 ORDER BY r.id ASC"
            );
        } catch (\Throwable $e) {
            $data['accounts_error'] = 'Restaurant accounts could not be loaded. Check database schema and application logs.';
        }

        Response::render('dashboard/restaurant-accounts', $data, 'app');
    }


    public function exportPlatformCsv(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) {
            Response::forbidden("Only platform superadmins can export platform reports.");
        }

        $type = strtolower(trim((string)$request->get('type', 'restaurants')));
        switch ($type) {
            case 'revenue':
                $filename = 'serveflow-revenue-report.csv';
                $headers = ['Restaurant', 'Invoice Number', 'Payment Reference', 'Payment Method', 'Payment Status', 'Amount (PHP)', 'Paid At', 'Recorded At'];
                $rows = Database::fetchAll(
                    "SELECT r.name AS restaurant_name, i.invoice_number, p.transaction_reference, p.payment_method, p.status,
                            p.amount, p.paid_at, p.created_at
                     FROM platform_payments p
                     INNER JOIN restaurants r ON r.id = p.restaurant_id
                     LEFT JOIN platform_invoices i ON i.id = p.invoice_id AND i.restaurant_id = p.restaurant_id
                     ORDER BY COALESCE(p.paid_at, p.created_at) DESC"
                );
                break;
            case 'subscriptions':
                $filename = 'serveflow-subscriptions-report.csv';
                $headers = ['Restaurant', 'Plan', 'Subscription Status', 'Billing Cycle', 'Period Start', 'Period End', 'Created At'];
                $rows = Database::fetchAll(
                    "SELECT r.name AS restaurant_name, sp.name AS plan_name, s.status, s.billing_cycle,
                            s.current_period_start, s.current_period_end, s.created_at
                     FROM subscriptions s
                     INNER JOIN restaurants r ON r.id = s.restaurant_id
                     INNER JOIN subscription_plans sp ON sp.id = s.plan_id
                     ORDER BY s.created_at DESC"
                );
                break;
            case 'restaurants':
                $filename = 'serveflow-restaurant-accounts.csv';
                $headers = ['Restaurant ID', 'Restaurant', 'Slug', 'Email', 'Phone', 'Account Status', 'Created At'];
                $rows = Database::fetchAll(
                    "SELECT id, name, slug, contact_email, contact_phone, status, created_at
                     FROM restaurants WHERE deleted_at IS NULL ORDER BY created_at DESC"
                );
                break;
            default:
                Response::redirect('/platform-analytics');
                return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $stream = fopen('php://output', 'w');
        if ($stream === false) return;
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $values = array_values($row);
            foreach ($values as &$value) {
                if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($stream, $values);
        }
        fclose($stream);
    }


    private function ensurePlatformAuditTable(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS platform_audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            platform_user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT UNSIGNED NULL,
            details_json JSON NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_platform_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function logPlatformAction(string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        $this->ensurePlatformAuditTable();
        Database::query(
            "INSERT INTO platform_audit_logs (platform_user_id, action, entity_type, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [null, $action, $entityType, $entityId, json_encode($details), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]
        );
    }

    public function updateRestaurantStatus(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden("Only platform superadmins can change restaurant status.");
        if (!\App\Core\CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Refresh and try again.');
            Response::redirect('/restaurant-accounts');
        }
        $id = (int)$request->post('restaurant_id', 0);
        $status = (string)$request->post('status', '');
        if ($id < 1 || !in_array($status, ['active', 'suspended'], true)) {
            Session::setFlash('danger', 'Invalid restaurant or status.');
            Response::redirect('/restaurant-accounts');
        }
        $old = Database::fetchOne("SELECT id, name, status FROM restaurants WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$old || in_array($old['status'], ['cancelled', 'archived'], true)) {
            Session::setFlash('danger', 'Restaurant account not found or cannot be changed.');
            Response::redirect('/restaurant-accounts');
        }
        Database::query("UPDATE restaurants SET status = ? WHERE id = ?", [$status, $id]);
        $this->logPlatformAction('restaurant.status_changed', 'restaurant', $id, ['name' => $old['name'], 'from' => $old['status'], 'to' => $status]);
        Session::setFlash('success', 'Restaurant account status updated.');
        Response::redirect('/restaurant-accounts');
    }

    public function platformSubscriptions(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden("Only platform superadmins can manage subscriptions.");
        $data = ['title' => 'Plans & Subscriptions', 'subscriptions' => [], 'plans' => [], 'flashes' => Session::getFlashes(), 'page_error' => null];
        try {
            $data['subscriptions'] = Database::fetchAll("SELECT s.id AS subscription_id, s.restaurant_id, s.status, s.billing_cycle, s.trial_ends_at, s.current_period_end, s.grace_period_days, r.name AS restaurant_name, sp.name AS plan_name, sp.id AS plan_id FROM subscriptions s JOIN restaurants r ON r.id=s.restaurant_id JOIN subscription_plans sp ON sp.id=s.plan_id WHERE r.deleted_at IS NULL ORDER BY s.updated_at DESC");
            $data['plans'] = Database::fetchAll("SELECT id, name, price_monthly, price_annually, is_active FROM subscription_plans ORDER BY price_monthly, name");
        } catch (\Throwable $e) {
            $data['page_error'] = 'Subscription data could not be loaded. Check application logs.';
        }
        Response::render('dashboard/platform-subscriptions', $data, 'app');
    }

    public function updatePlatformSubscription(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden("Only platform superadmins can update subscriptions.");
        if (!\App\Core\CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Refresh and try again.');
            Response::redirect('/platform-subscriptions');
        }
        $id = (int)$request->post('subscription_id', 0);
        $planId = (int)$request->post('plan_id', 0);
        $status = (string)$request->post('status', '');
        $days = (int)$request->post('extend_trial_days', 0);
        if ($id < 1 || $planId < 1 || !in_array($status, ['trial','active','past_due','suspended','cancelled','expired'], true) || $days < 0 || $days > 365) {
            Session::setFlash('danger', 'Invalid subscription changes.');
            Response::redirect('/platform-subscriptions');
        }
        $old = Database::fetchOne("SELECT id, restaurant_id, plan_id, status FROM subscriptions WHERE id = ?", [$id]);
        $plan = Database::fetchOne("SELECT id FROM subscription_plans WHERE id = ? AND is_active = 1", [$planId]);
        if (!$old || !$plan) {
            Session::setFlash('danger', 'Subscription or active plan not found.');
            Response::redirect('/platform-subscriptions');
        }
        if ($days > 0) {
            Database::query("UPDATE subscriptions SET plan_id=?, status=?, trial_ends_at=DATE_ADD(COALESCE(trial_ends_at,NOW()), INTERVAL ? DAY), current_period_end=DATE_ADD(current_period_end, INTERVAL ? DAY), updated_at=NOW() WHERE id=?", [$planId,$status,$days,$days,$id]);
        } else {
            Database::query("UPDATE subscriptions SET plan_id=?, status=?, updated_at=NOW() WHERE id=?", [$planId,$status,$id]);
        }
        $this->logPlatformAction('subscription.updated', 'subscription', $id, ['restaurant_id'=>(int)$old['restaurant_id'],'old_plan_id'=>(int)$old['plan_id'],'new_plan_id'=>$planId,'old_status'=>$old['status'],'new_status'=>$status,'extended_days'=>$days]);
        Session::setFlash('success', 'Subscription updated.');
        Response::redirect('/platform-subscriptions');
    }

    public function platformHealth(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden("Only platform superadmins can view platform health.");
        $data = ['title'=>'Platform Health','flashes'=>Session::getFlashes(),'health_error'=>null];
        try {
            $data['db_status'] = Database::testConnection();
            $data['recent_devices'] = Database::fetchAll("SELECT d.id, d.restaurant_id, d.status, d.last_sync_at, r.name AS restaurant_name FROM pos_devices d JOIN restaurants r ON r.id=d.restaurant_id WHERE r.deleted_at IS NULL ORDER BY d.last_sync_at DESC LIMIT 30");
            $data['expiring_subscriptions'] = Database::fetchAll("SELECT s.id,s.status,s.current_period_end,s.trial_ends_at,r.name AS restaurant_name FROM subscriptions s JOIN restaurants r ON r.id=s.restaurant_id WHERE r.deleted_at IS NULL AND ((s.status='trial' AND s.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 14 DAY)) OR (s.status='active' AND s.current_period_end BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 14 DAY)) OR s.status='past_due') ORDER BY s.current_period_end LIMIT 50");
            $this->ensurePlatformAuditTable();
            $data['recent_actions'] = Database::fetchAll("SELECT * FROM platform_audit_logs ORDER BY created_at DESC LIMIT 50");
        } catch (\Throwable $e) {
            $data['health_error'] = 'Some health information could not be loaded. Check application logs.';
        }
        Response::render('dashboard/platform-health', $data, 'app');
    }

    public function switchTenant(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) {
            Response::forbidden("Only platform superadmins can switch tenant views.");
        }

        $targetId = (int)$request->get('id', 0);
        if ($targetId > 0) {
            $resto = Database::fetchOne("SELECT * FROM restaurants WHERE id = ? AND deleted_at IS NULL", [$targetId]);
            if ($resto) {
                Session::set('restaurant_id', $targetId);
                TenantContext::setTenant($targetId, $resto);
                Session::setFlash('info', "Switched inspection context to '{$resto['name']}'.");
            }
        } else {
            Session::remove('restaurant_id');
            Session::setFlash('info', "Returned to global SaaS Platform overview.");
        }

        Response::redirect('/dashboard');
    }
}
