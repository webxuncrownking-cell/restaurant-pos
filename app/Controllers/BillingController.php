<?php
/**
 * Restaurant POS & Management SaaS
 * Subscription Billing & Invoicing Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Core\CSRF;
use App\Services\BillingService;
use Exception;

class BillingController
{
    /**
     * Subscription overview and plan comparison
     */
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();

        $subscription = BillingService::getCurrentSubscription($restaurantId);
        $plans = BillingService::getAvailablePlans();
        $invoices = BillingService::getInvoices($restaurantId);
        $graceDays = TenantContext::getGraceDaysRemaining();

        Response::render('billing/index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'invoices' => $invoices,
            'graceDays' => $graceDays
        ]);
    }

    /**
     * Change / Upgrade plan POST action
     */
    public function upgrade(Request $request): void
    {
        Auth::requireAuth();
        CSRF::validateToken($request->post('_csrf_token'));
        $restaurantId = TenantContext::getRestaurantId();

        $planId = (int)$request->post('plan_id');
        $cycle = (string)$request->post('billing_cycle', 'monthly');

        try {
            BillingService::changePlan($restaurantId, $planId, $cycle);
            $_SESSION['flash_success'] = 'Subscription updated successfully.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        Response::redirect('/billing');
    }
}
