<?php
/**
 * Restaurant POS & Management SaaS
 * Business Intelligence, Chart.js Analytics & Reporting Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class ReportService
{
    /**
     * Get aggregate KPI metrics for a given date range
     */
    public static function getKpiSummary(int $restaurantId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    COUNT(id) as total_orders,
                    COALESCE(SUM(total_amount), 0) as gross_sales,
                    COALESCE(SUM(subtotal), 0) as subtotal_sales,
                    COALESCE(SUM(discount_amount), 0) as total_discounts,
                    COALESCE(SUM(tax_amount), 0) as total_tax,
                    COALESCE(AVG(total_amount), 0) as average_order_value
                FROM orders
                WHERE restaurant_id = :restaurant_id 
                  AND order_status != 'cancelled'
                  AND DATE(created_at) BETWEEN :start_date AND :end_date";

        $row = Database::fetchOne($sql, [
            ':restaurant_id' => $restaurantId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        return [
            'total_orders' => (int)($row['total_orders'] ?? 0),
            'gross_sales' => (float)($row['gross_sales'] ?? 0),
            'subtotal_sales' => (float)($row['subtotal_sales'] ?? 0),
            'total_discounts' => (float)($row['total_discounts'] ?? 0),
            'total_tax' => (float)($row['total_tax'] ?? 0),
            'average_order_value' => (float)($row['average_order_value'] ?? 0)
        ];
    }

    /**
     * Daily sales trend for Chart.js line/bar chart
     */
    public static function getDailySalesTrend(int $restaurantId, int $days = 14): array
    {
        $sql = "SELECT 
                    DATE(created_at) as sale_date,
                    COUNT(id) as order_count,
                    COALESCE(SUM(total_amount), 0) as daily_revenue
                FROM orders
                WHERE restaurant_id = :restaurant_id
                  AND order_status != 'cancelled'
                  AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY sale_date ASC";

        return Database::fetchAll($sql, [
            ':restaurant_id' => $restaurantId,
            ':days' => $days
        ]);
    }

    /**
     * Payment method breakdown for Chart.js donut chart
     */
    public static function getPaymentMethodBreakdown(int $restaurantId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    payment_method,
                    COUNT(id) as transaction_count,
                    COALESCE(SUM(amount), 0) as total_amount
                FROM payments
                WHERE restaurant_id = :restaurant_id
                  AND is_verified = 1
                  AND DATE(paid_at) BETWEEN :start_date AND :end_date
                GROUP BY payment_method
                ORDER BY total_amount DESC";

        return Database::fetchAll($sql, [
            ':restaurant_id' => $restaurantId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
    }

    /**
     * Top selling products by revenue and quantity
     */
    public static function getTopSellingProducts(int $restaurantId, int $limit = 10): array
    {
        $sql = "SELECT 
                    oi.product_id,
                    oi.product_name AS item_name,
                    c.name as category_name,
                    SUM(oi.quantity) as total_quantity_sold,
                    SUM(oi.subtotal) as total_revenue
                FROM order_items oi
                JOIN orders o ON o.restaurant_id = oi.restaurant_id AND o.id = oi.order_id
                JOIN products p ON p.restaurant_id = oi.restaurant_id AND p.id = oi.product_id
                JOIN categories c ON c.restaurant_id = p.restaurant_id AND c.id = p.category_id
                WHERE oi.restaurant_id = :restaurant_id
                  AND o.order_status != 'cancelled'
                GROUP BY oi.product_id, oi.product_name, c.name
                ORDER BY total_revenue DESC
                LIMIT " . (int)$limit;

        return Database::fetchAll($sql, [':restaurant_id' => $restaurantId]);
    }

    /**
     * Cashier performance breakdown
     */
    public static function getCashierPerformance(int $restaurantId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    u.id as user_id,
                    u.full_name as cashier_name,
                    COUNT(o.id) as orders_handled,
                    COALESCE(SUM(o.total_amount), 0) as total_collected
                FROM orders o
                JOIN users u ON u.restaurant_id = o.restaurant_id AND u.id = o.cashier_id
                WHERE o.restaurant_id = :restaurant_id
                  AND o.order_status != 'cancelled'
                  AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                GROUP BY u.id, u.full_name
                ORDER BY total_collected DESC";

        return Database::fetchAll($sql, [
            ':restaurant_id' => $restaurantId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
    }
}
