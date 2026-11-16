<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Request;

class OrdersController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();
        $role = (string)(Auth::roleSlug() ?? '');
        if (!in_array($role, ['owner', 'manager', 'cashier'], true) && !Auth::can('orders.view_all')) {
            Response::forbidden('You do not have permission to view restaurant orders.');
        }

        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($restaurantId <= 0) {
            Response::forbidden('Select a restaurant workspace before viewing orders.');
        }

        $search = trim((string)$request->get('search', ''));
        $status = trim((string)$request->get('status', ''));
        $paymentStatus = trim((string)$request->get('payment_status', ''));
        $allowedStatuses = ['pending', 'preparing', 'ready', 'served', 'completed', 'cancelled'];
        $allowedPayment = ['unpaid', 'partially_paid', 'paid', 'refunded'];

        $where = ['o.restaurant_id = ?'];
        $params = [$restaurantId];
        if ($search !== '') {
            $where[] = "(o.order_number LIKE ? OR CAST(o.id AS CHAR) LIKE ? OR COALESCE(t.table_number, '') LIKE ? OR COALESCE(u.full_name, u.username, '') LIKE ?)";
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (in_array($status, $allowedStatuses, true)) {
            $where[] = 'o.order_status = ?';
            $params[] = $status;
        } else {
            $status = '';
        }
        if (in_array($paymentStatus, $allowedPayment, true)) {
            $where[] = 'o.payment_status = ?';
            $params[] = $paymentStatus;
        } else {
            $paymentStatus = '';
        }

        $orders = Database::fetchAll(
            "SELECT o.id, o.order_number, o.order_type, o.order_status, o.payment_status,
                    o.total_amount, o.created_at, COALESCE(t.table_number, '') AS table_number,
                    COALESCE(u.full_name, u.username, 'Staff') AS cashier_name,
                    COALESCE(items.item_count, 0) AS item_count,
                    COALESCE(payments.amount_paid, 0) AS amount_paid
             FROM orders o
             LEFT JOIN restaurant_tables t
               ON t.restaurant_id = o.restaurant_id AND t.id = o.table_id
             LEFT JOIN users u
               ON u.restaurant_id = o.restaurant_id AND u.id = o.cashier_id
             LEFT JOIN (
                SELECT restaurant_id, order_id, SUM(quantity) AS item_count
                FROM order_items GROUP BY restaurant_id, order_id
             ) items ON items.restaurant_id = o.restaurant_id AND items.order_id = o.id
             LEFT JOIN (
                SELECT restaurant_id, order_id, SUM(amount) AS amount_paid
                FROM payments WHERE is_verified = 1 GROUP BY restaurant_id, order_id
             ) payments ON payments.restaurant_id = o.restaurant_id AND payments.order_id = o.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT 200",
            $params
        );

        Response::render('orders/index', [
            'title' => 'All Orders',
            'orders' => $orders,
            'filters' => ['search' => $search, 'status' => $status, 'payment_status' => $paymentStatus],
        ], 'app');
    }
}
