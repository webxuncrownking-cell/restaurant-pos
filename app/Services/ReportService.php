<?php
/**
 * Restaurant POS & Management SaaS
 * Business intelligence, profitability, and financial reconciliation reports.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class ReportService
{
    public static function getKpiSummary(int $restaurantId, string $startDate, string $endDate): array
    {
        $row = Database::fetchOne(
            "SELECT COUNT(id) AS total_orders,
                    COALESCE(SUM(total_amount), 0) AS gross_sales,
                    COALESCE(SUM(subtotal), 0) AS subtotal_sales,
                    COALESCE(SUM(discount_amount), 0) AS total_discounts,
                    COALESCE(SUM(tax_amount), 0) AS total_tax,
                    COALESCE(AVG(total_amount), 0) AS average_order_value
             FROM orders
             WHERE restaurant_id = :restaurant_id
               AND order_status <> 'cancelled'
               AND payment_status = 'paid'
               AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );

        return [
            'total_orders' => (int)($row['total_orders'] ?? 0),
            'gross_sales' => (float)($row['gross_sales'] ?? 0),
            'subtotal_sales' => (float)($row['subtotal_sales'] ?? 0),
            'total_discounts' => (float)($row['total_discounts'] ?? 0),
            'total_tax' => (float)($row['total_tax'] ?? 0),
            'average_order_value' => (float)($row['average_order_value'] ?? 0)
        ];
    }

    public static function getDailySalesTrend(int $restaurantId, int $days = 14): array
    {
        $days = max(1, min(730, $days));
        return Database::fetchAll(
            "SELECT DATE(created_at) AS sale_date, COUNT(id) AS order_count,
                    COALESCE(SUM(total_amount), 0) AS daily_revenue
             FROM orders
             WHERE restaurant_id = :restaurant_id
               AND order_status <> 'cancelled'
               AND payment_status = 'paid'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY sale_date ASC",
            [':restaurant_id' => $restaurantId, ':days' => $days]
        );
    }

    public static function getDailySalesTrendForRange(int $restaurantId, string $startDate, string $endDate): array
    {
        return Database::fetchAll(
            "SELECT DATE(created_at) AS sale_date, COUNT(id) AS order_count,
                    COALESCE(SUM(total_amount), 0) AS daily_revenue
             FROM orders
             WHERE restaurant_id = :restaurant_id
               AND order_status <> 'cancelled'
               AND payment_status = 'paid'
               AND DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY DATE(created_at)
             ORDER BY sale_date ASC",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
    }

    public static function getPaymentMethodBreakdown(int $restaurantId, string $startDate, string $endDate): array
    {
        return Database::fetchAll(
            "SELECT payment_method, COUNT(id) AS transaction_count,
                    COALESCE(SUM(amount), 0) AS total_amount
             FROM payments
             WHERE restaurant_id = :restaurant_id
               AND is_verified = 1
               AND DATE(paid_at) BETWEEN :start_date AND :end_date
             GROUP BY payment_method
             ORDER BY total_amount DESC",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
    }

    /**
     * Product sales grouped by historical order-line name and category.
     * Order-level discounts are apportioned across lines by their share of subtotal.
     * Cost uses the cost snapshot stored at sale time, plus snapshotted add-on costs.
     */
    public static function getTopSellingProducts(
        int $restaurantId,
        int $limit = 10,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $limit = max(1, min(100, $limit));
        $dateSql = '';
        $params = [':restaurant_id' => $restaurantId];
        if ($startDate !== null && $endDate !== null) {
            $dateSql = ' AND DATE(o.created_at) BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }

        return Database::fetchAll(
            "SELECT oi.product_id, oi.product_name AS item_name,
                    COALESCE(c.name, 'Uncategorized') AS category_name,
                    SUM(oi.quantity) AS total_quantity_sold,
                    SUM(CASE WHEN o.subtotal > 0
                        THEN CASE WHEN (oi.subtotal - COALESCE(oi.discount_amount, 0)
                             - ((oi.subtotal / o.subtotal) * o.discount_amount)) > 0
                             THEN oi.subtotal - COALESCE(oi.discount_amount, 0)
                                  - ((oi.subtotal / o.subtotal) * o.discount_amount)
                             ELSE 0 END
                        ELSE 0 END) AS total_revenue,
                    SUM((oi.cost_price * oi.quantity) + COALESCE(addons.addon_cost, 0)) AS estimated_food_cost,
                    SUM(
                        CASE WHEN o.subtotal > 0
                          THEN CASE WHEN (oi.subtotal - COALESCE(oi.discount_amount, 0)
                               - ((oi.subtotal / o.subtotal) * o.discount_amount)) > 0
                               THEN oi.subtotal - COALESCE(oi.discount_amount, 0)
                                    - ((oi.subtotal / o.subtotal) * o.discount_amount)
                               ELSE 0 END
                          ELSE 0 END
                        - ((oi.cost_price * oi.quantity) + COALESCE(addons.addon_cost, 0))
                    ) AS estimated_gross_profit
             FROM order_items oi
             JOIN orders o ON o.restaurant_id = oi.restaurant_id AND o.id = oi.order_id
             LEFT JOIN products p ON p.restaurant_id = oi.restaurant_id AND p.id = oi.product_id
             LEFT JOIN categories c ON c.restaurant_id = p.restaurant_id AND c.id = p.category_id
             LEFT JOIN (
                SELECT restaurant_id, order_item_id, SUM(cost_price * quantity) AS addon_cost
                FROM order_item_addons
                GROUP BY restaurant_id, order_item_id
             ) addons ON addons.restaurant_id = oi.restaurant_id AND addons.order_item_id = oi.id
             WHERE oi.restaurant_id = :restaurant_id
               AND o.order_status <> 'cancelled'
               AND o.payment_status = 'paid'{$dateSql}
             GROUP BY oi.product_id, oi.product_name, c.name
             ORDER BY total_revenue DESC
             LIMIT {$limit}",
            $params
        );
    }

    /**
     * Simple explainable menu demand estimate from paid historical orders.
     * Uses calendar days in the selected period, including zero-sales days.
     */
    public static function getProductDemandForecast(int $restaurantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        $calendarDays = max(1, (int)$start->diff($end)->days + 1);
        $rows = Database::fetchAll(
            "SELECT oi.product_id, oi.product_name AS item_name,
                    COALESCE(SUM(oi.quantity), 0) AS units_sold,
                    COUNT(DISTINCT DATE(o.created_at)) AS active_sales_days
             FROM order_items oi
             JOIN orders o ON o.restaurant_id = oi.restaurant_id AND o.id = oi.order_id
             WHERE oi.restaurant_id = ?
               AND o.order_status <> 'cancelled'
               AND o.payment_status = 'paid'
               AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY oi.product_id, oi.product_name
             ORDER BY units_sold DESC
             LIMIT {$limit}",
            [$restaurantId, $startDate, $endDate]
        );
        foreach ($rows as &$row) {
            $units = (float)($row['units_sold'] ?? 0);
            $row['calendar_days_used'] = $calendarDays;
            $row['average_daily_units'] = round($units / $calendarDays, 2);
            $row['estimated_next_7_days_units'] = round(($units / $calendarDays) * 7, 1);
        }
        unset($row);
        return $rows;
    }

    public static function getProductProfitability(int $restaurantId, string $startDate, string $endDate): array
    {
        $rows = self::getTopSellingProducts($restaurantId, 100, $startDate, $endDate);
        foreach ($rows as &$row) {
            $revenue = (float)$row['total_revenue'];
            $cost = (float)$row['estimated_food_cost'];
            $profit = (float)$row['estimated_gross_profit'];
            $row['food_cost_pct'] = $revenue > 0 ? round(($cost / $revenue) * 100, 2) : 0.0;
            $row['gross_margin_pct'] = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;
        }
        unset($row);
        return $rows;
    }

    public static function getCashierPerformance(int $restaurantId, string $startDate, string $endDate): array
    {
        return Database::fetchAll(
            "SELECT u.id AS user_id, u.full_name AS cashier_name,
                    COUNT(o.id) AS orders_handled,
                    COALESCE(SUM(o.total_amount), 0) AS total_collected
             FROM orders o
             JOIN users u ON u.restaurant_id = o.restaurant_id AND u.id = o.cashier_id
             WHERE o.restaurant_id = :restaurant_id
               AND o.order_status <> 'cancelled'
               AND o.payment_status = 'paid'
               AND DATE(o.created_at) BETWEEN :start_date AND :end_date
             GROUP BY u.id, u.full_name
             ORDER BY total_collected DESC",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
    }

    public static function getFinancialAdjustments(int $restaurantId, string $startDate, string $endDate): array
    {
        $voids = Database::fetchOne(
            "SELECT COUNT(*) AS void_count FROM void_records
             WHERE restaurant_id = :restaurant_id AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
        $refunds = Database::fetchOne(
            "SELECT COUNT(*) AS refund_count, COALESCE(SUM(amount), 0) AS refund_amount
             FROM refund_records
             WHERE restaurant_id = :restaurant_id AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
        $shifts = Database::fetchOne(
            "SELECT COUNT(*) AS closed_shifts,
                    COALESCE(SUM(CASE WHEN cash_difference > 0 THEN cash_difference ELSE 0 END), 0) AS cash_over,
                    COALESCE(SUM(CASE WHEN cash_difference < 0 THEN ABS(cash_difference) ELSE 0 END), 0) AS cash_short,
                    COALESCE(SUM(ABS(COALESCE(cash_difference, 0))), 0) AS total_cash_variance
             FROM cashier_shifts
             WHERE restaurant_id = :restaurant_id AND status = 'closed'
               AND DATE(closed_at) BETWEEN :start_date AND :end_date",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );

        $wasteCount = 0;
        $wasteCost = 0.0;
        if (class_exists(ExpiryWasteService::class) && ExpiryWasteService::schemaReady()) {
            $waste = Database::fetchOne(
                "SELECT COUNT(*) AS waste_count, COALESCE(SUM(total_cost), 0) AS waste_cost
                 FROM ingredient_waste_records
                 WHERE restaurant_id = :restaurant_id AND DATE(created_at) BETWEEN :start_date AND :end_date",
                [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
            );
            $wasteCount = (int)($waste['waste_count'] ?? 0);
            $wasteCost = (float)($waste['waste_cost'] ?? 0);
        }

        return [
            'void_count' => (int)($voids['void_count'] ?? 0),
            'refund_count' => (int)($refunds['refund_count'] ?? 0),
            'refund_amount' => (float)($refunds['refund_amount'] ?? 0),
            'waste_count' => $wasteCount,
            'waste_cost' => $wasteCost,
            'closed_shifts' => (int)($shifts['closed_shifts'] ?? 0),
            'cash_over' => (float)($shifts['cash_over'] ?? 0),
            'cash_short' => (float)($shifts['cash_short'] ?? 0),
            'total_cash_variance' => (float)($shifts['total_cash_variance'] ?? 0)
        ];
    }

    public static function getShiftReconciliation(int $restaurantId, string $startDate, string $endDate, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT cs.id AS shift_id, cs.status, cs.opening_cash, cs.expected_cash,
                    cs.closing_cash, cs.cash_difference, cs.opened_at, cs.closed_at,
                    u.full_name AS cashier_name
             FROM cashier_shifts cs
             JOIN users u ON u.restaurant_id = cs.restaurant_id AND u.id = cs.cashier_id
             WHERE cs.restaurant_id = :restaurant_id AND cs.status = 'closed'
               AND DATE(cs.closed_at) BETWEEN :start_date AND :end_date
             ORDER BY cs.closed_at DESC, cs.id DESC
             LIMIT {$limit}",
            [':restaurant_id' => $restaurantId, ':start_date' => $startDate, ':end_date' => $endDate]
        );
    }
}
