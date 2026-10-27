<?php
/**
 * Restaurant POS & Management SaaS
 * Reports & Analytics controller.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Auth;
use App\Services\ReportService;
use DateTimeImmutable;

class ReportController
{
    public function index(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        [$startDate, $endDate] = $this->getDateRange($request);

        $kpis = ReportService::getKpiSummary($restaurantId, $startDate, $endDate);
        $dailyTrend = ReportService::getDailySalesTrendForRange($restaurantId, $startDate, $endDate);
        $paymentMethods = ReportService::getPaymentMethodBreakdown($restaurantId, $startDate, $endDate);
        $topProducts = ReportService::getTopSellingProducts($restaurantId, 10, $startDate, $endDate);
        $profitability = ReportService::getProductProfitability($restaurantId, $startDate, $endDate);
        $cashierStats = ReportService::getCashierPerformance($restaurantId, $startDate, $endDate);
        $adjustments = ReportService::getFinancialAdjustments($restaurantId, $startDate, $endDate);
        $shiftReconciliation = ReportService::getShiftReconciliation($restaurantId, $startDate, $endDate);
        // Expense tracking is an optional module on existing installations.
        // Keep the reports page usable before its table has been prepared.
        $expenseSummary = ['total' => 0.0, 'count' => 0, 'available' => false];
        try {
            $expenseRow = \\App\\Core\\Database::fetchOne(
                "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS entries
                 FROM restaurant_expenses
                 WHERE restaurant_id = ? AND status = 'active' AND expense_date BETWEEN ? AND ?",
                [$restaurantId, $startDate, $endDate]
            );
            if (is_array($expenseRow)) {
                $expenseSummary = [
                    'total' => (float)($expenseRow['total'] ?? 0),
                    'count' => (int)($expenseRow['entries'] ?? 0),
                    'available' => true
                ];
            }
        } catch (\\Throwable $ignored) {
            // Table not created yet; expense total remains unavailable, not zero sales.
        }

        // Explainable sales insights from existing tenant-scoped report data (no external AI service).
        $insights = [];
        $dailyValues = array_map(static fn(array $row): float => (float)($row['daily_revenue'] ?? 0), $dailyTrend);
        $dailyCount = count($dailyValues);
        if ($dailyCount >= 4) {
            $mid = (int)floor($dailyCount / 2);
            $older = array_slice($dailyValues, 0, $mid);
            $recent = array_slice($dailyValues, $mid);
            $olderAvg = array_sum($older) / max(1, count($older));
            $recentAvg = array_sum($recent) / max(1, count($recent));
            if ($olderAvg > 0) {
                $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;
                $insights[] = [
                    'type' => $change >= 0 ? 'success' : 'warning',
                    'title' => $change >= 0 ? 'Sales trend is improving' : 'Sales trend is softening',
                    'detail' => 'Average revenue on the later recorded sales days is ' . number_format(abs($change), 1) . '% ' . ($change >= 0 ? 'higher' : 'lower') . ' than the earlier days in this selected period.'
                ];
            }
        }
        $lowMarginItems = array_values(array_filter($profitability, static fn(array $item): bool => (float)($item['total_revenue'] ?? 0) > 0 && (float)($item['gross_margin_pct'] ?? 0) < 25));
        if ($lowMarginItems) {
            usort($lowMarginItems, static fn(array $a, array $b): int => (float)$a['gross_margin_pct'] <=> (float)$b['gross_margin_pct']);
            $item = $lowMarginItems[0];
            $insights[] = [
                'type' => 'warning',
                'title' => 'Review a low-margin menu item',
                'detail' => (string)$item['item_name'] . ' has an estimated gross margin of ' . number_format((float)$item['gross_margin_pct'], 1) . '%. Review ingredient costs, portion sizes, and selling price.'
            ];
        }
        if (!empty($expenseSummary['available']) && (float)$kpis['gross_sales'] > 0) {
            $expenseRatio = ((float)$expenseSummary['total'] / (float)$kpis['gross_sales']) * 100;
            $insights[] = [
                'type' => $expenseRatio >= 30 ? 'warning' : 'info',
                'title' => 'Operating expense share',
                'detail' => 'Recorded operating expenses are ' . number_format($expenseRatio, 1) . '% of gross sales for this period. This is a monitoring indicator, not a net-profit calculation.'
            ];
        }
        // Inventory risk signals are descriptive, not a demand forecast. They use
        // the restaurant's current stock thresholds and fail closed on legacy schemas.
        try {
            $stockRisk = Database::fetchOne(
                "SELECT COUNT(*) AS low_count,
                        COALESCE(SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END),0) AS out_count
                 FROM ingredients
                 WHERE restaurant_id = ? AND is_active = 1 AND current_stock <= minimum_stock_alert",
                [$restaurantId]
            );
            $lowCount = (int)($stockRisk['low_count'] ?? 0);
            $outCount = (int)($stockRisk['out_count'] ?? 0);
            if ($outCount > 0) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Ingredients out of stock',
                    'detail' => $outCount . ' active ingredient(s) currently have zero or negative stock. Review stock levels before accepting orders that depend on them.'
                ];
            } elseif ($lowCount > 0) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Low-stock ingredients',
                    'detail' => $lowCount . ' active ingredient(s) are at or below their configured minimum-stock alert. Review purchasing and upcoming demand.'
                ];
            }
        } catch (\\Throwable $e) {
            // Older installs may not yet have the inventory schema.
        }

        if (!$insights) {
            $insights[] = [
                'type' => 'info',
                'title' => 'More sales history needed',
                'detail' => 'Record more paid orders and menu cost data to generate useful trend and margin indicators for this period.'
            ];
        }

        // Simple, explainable baseline estimate from recent recorded sales.
        // This is not machine learning and does not claim to account for seasonality.
        $forecast = ['available' => false, 'average_daily_sales' => 0.0, 'estimated_next_7_days' => 0.0, 'days_used' => 0, 'basis' => ''];
        $forecastRows = array_slice($dailyTrend, -7);
        $forecastValues = array_map(static fn(array $row): float => max(0.0, (float)($row['daily_revenue'] ?? 0)), $forecastRows);
        if (count($forecastValues) >= 5) {
            $averageDaily = array_sum($forecastValues) / count($forecastValues);
            $forecast = [
                'available' => true,
                'average_daily_sales' => round($averageDaily, 2),
                'estimated_next_7_days' => round($averageDaily * 7, 2),
                'days_used' => count($forecastValues),
                'basis' => 'Average of the latest ' . count($forecastValues) . ' recorded sales days in the selected range. Assumes the same average each day; does not adjust for weekdays, holidays, events, stockouts, or seasonality.'
            ];
        }

        Response::render('reports/index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'kpis' => $kpis,
            'dailyTrend' => $dailyTrend,
            'paymentMethods' => $paymentMethods,
            'topProducts' => $topProducts,
            'profitability' => $profitability,
            'cashierStats' => $cashierStats,
            'adjustments' => $adjustments,
            'shiftReconciliation' => $shiftReconciliation,
            'expenseSummary' => $expenseSummary,
            'insights' => $insights,
            'forecast' => $forecast
        ]);
    }

    public function dataJson(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        [$startDate, $endDate] = $this->getDateRange($request);

        Response::json([
            'kpis' => ReportService::getKpiSummary($restaurantId, $startDate, $endDate),
            'dailyTrend' => ReportService::getDailySalesTrendForRange($restaurantId, $startDate, $endDate),
            'paymentMethods' => ReportService::getPaymentMethodBreakdown($restaurantId, $startDate, $endDate),
            'financialAdjustments' => ReportService::getFinancialAdjustments($restaurantId, $startDate, $endDate)
        ]);
    }

    /**
     * Export only the selected tenant's data and requested date window.
     */
    public function exportCsv(Request $request): void
    {
        Auth::requireAuth();
        $restaurantId = TenantContext::getRestaurantId();
        [$startDate, $endDate] = $this->getDateRange($request);
        $type = strtolower(trim((string)$request->get('type', 'sales')));

        switch ($type) {
            case 'profitability':
                $filename = 'menu-profitability';
                $headers = ['Menu Item', 'Category', 'Units Sold', 'Net Line Revenue (PHP)', 'Estimated Food Cost (PHP)', 'Estimated Gross Profit (PHP)', 'Food Cost %', 'Gross Margin %'];
                $rows = array_map(static fn(array $r): array => [
                    $r['item_name'], $r['category_name'], $r['total_quantity_sold'], $r['total_revenue'],
                    $r['estimated_food_cost'], $r['estimated_gross_profit'], $r['food_cost_pct'], $r['gross_margin_pct']
                ], ReportService::getProductProfitability($restaurantId, $startDate, $endDate));
                break;
            case 'cashiers':
                $filename = 'cashier-performance';
                $headers = ['Cashier', 'Orders Handled', 'Collected (PHP)'];
                $rows = array_map(static fn(array $r): array => [$r['cashier_name'], $r['orders_handled'], $r['total_collected']],
                    ReportService::getCashierPerformance($restaurantId, $startDate, $endDate));
                break;
            case 'reconciliation':
                $filename = 'cash-reconciliation';
                $headers = ['Shift ID', 'Cashier', 'Opened At', 'Closed At', 'Opening Cash (PHP)', 'Expected Cash (PHP)', 'Closing Cash (PHP)', 'Cash Difference (PHP)'];
                $rows = array_map(static fn(array $r): array => [
                    $r['shift_id'], $r['cashier_name'], $r['opened_at'], $r['closed_at'], $r['opening_cash'],
                    $r['expected_cash'], $r['closing_cash'], $r['cash_difference']
                ], ReportService::getShiftReconciliation($restaurantId, $startDate, $endDate, 200));
                break;
            case 'expenses':
                $filename = 'operating-expenses';
                $headers = ['Date', 'Category', 'Description', 'Payment Method', 'Reference', 'Amount (PHP)', 'Status', 'Created At'];
                try {
                    $expenseRows = \\App\\Core\\Database::fetchAll(
                        "SELECT expense_date, category, description, payment_method, reference_number, amount, status, created_at
                         FROM restaurant_expenses
                         WHERE restaurant_id = ? AND expense_date BETWEEN ? AND ?
                         ORDER BY expense_date DESC, id DESC LIMIT 5000",
                        [$restaurantId, $startDate, $endDate]
                    );
                    $rows = array_map(static fn(array $r): array => [
                        $r['expense_date'], $r['category'], $r['description'], $r['payment_method'],
                        $r['reference_number'], $r['amount'], $r['status'], $r['created_at']
                    ], $expenseRows);
                } catch (\\Throwable $e) {
                    Response::redirect('/reports?start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate));
                    return;
                }
                break;
            case 'sales':
                $filename = 'daily-sales';
                $headers = ['Date', 'Orders', 'Sales Revenue (PHP)'];
                $rows = array_map(static fn(array $r): array => [$r['sale_date'], $r['order_count'], $r['daily_revenue']],
                    ReportService::getDailySalesTrendForRange($restaurantId, $startDate, $endDate));
                break;
            default:
                Response::redirect('/reports?start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate));
                return;
        }

        $filename .= '-' . $startDate . '-to-' . $endDate . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $stream = fopen('php://output', 'w');
        if ($stream === false) return;
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $safeRow = [];
            foreach ($row as $value) {
                if (is_string($value) && preg_match('/^[\=\+\-\@\t\r]/', $value)) $value = "'" . $value;
                $safeRow[] = $value;
            }
            fputcsv($stream, $safeRow);
        }
        fclose($stream);
    }

    private function getDateRange(Request $request): array
    {
        $today = date('Y-m-d');
        $defaultStart = date('Y-m-d', strtotime('-30 days'));
        $start = $this->validDate((string)$request->get('start_date', $defaultStart)) ?? $defaultStart;
        $end = $this->validDate((string)$request->get('end_date', $today)) ?? $today;

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        // Keep ad-hoc reports from accidentally querying years of data.
        $startObject = new DateTimeImmutable($start);
        $endObject = new DateTimeImmutable($end);
        if ($startObject->diff($endObject)->days > 730) {
            $start = $endObject->modify('-730 days')->format('Y-m-d');
        }

        return [$start, $end];
    }

    private function validDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }
}
