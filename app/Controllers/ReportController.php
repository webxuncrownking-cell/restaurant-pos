<?php
/**
 * Restaurant POS & Management SaaS
 * Reports & Analytics Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Services\ReportService;

class ReportController
{
    /**
     * Reports & Analytics Dashboard View
     */
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();

        $startDate = $request->get('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->get('end_date', date('Y-m-d'));

        $kpis = ReportService::getKpiSummary($restaurantId, $startDate, $endDate);
        $dailyTrend = ReportService::getDailySalesTrend($restaurantId, 30);
        $paymentMethods = ReportService::getPaymentMethodBreakdown($restaurantId, $startDate, $endDate);
        $topProducts = ReportService::getTopSellingProducts($restaurantId, 10);
        $cashierStats = ReportService::getCashierPerformance($restaurantId, $startDate, $endDate);

        Response::render('reports/index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'kpis' => $kpis,
            'dailyTrend' => $dailyTrend,
            'paymentMethods' => $paymentMethods,
            'topProducts' => $topProducts,
            'cashierStats' => $cashierStats
        ]);
    }

    /**
     * Async JSON endpoint for Chart.js updates
     */
    public function dataJson(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();

        $startDate = $request->get('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->get('end_date', date('Y-m-d'));

        Response::json([
            'kpis' => ReportService::getKpiSummary($restaurantId, $startDate, $endDate),
            'dailyTrend' => ReportService::getDailySalesTrend($restaurantId, 30),
            'paymentMethods' => ReportService::getPaymentMethodBreakdown($restaurantId, $startDate, $endDate)
        ]);
    }
}
