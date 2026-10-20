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
                $data['active_subscriptions'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM subscriptions WHERE status IN ('active','trial')")['c'] ?? 0);
                $data['total_devices'] = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM pos_devices WHERE status = 'active'")['c'] ?? 0);
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
