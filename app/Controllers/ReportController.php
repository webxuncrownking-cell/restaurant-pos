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
            'expenseSummary' => $expenseSummary
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
