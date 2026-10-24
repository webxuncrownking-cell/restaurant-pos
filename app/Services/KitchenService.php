<?php
/**
 * Restaurant POS & Management SaaS
 * Tenant-safe live kitchen tickets and order status workflow.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

class KitchenService
{
    private const ITEM_TRANSITIONS = [
        'pending' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'served',
    ];

    public static function ensureItemStatusSchema(): void
    {
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS kitchen_item_status (
                restaurant_id INTEGER NOT NULL,
                order_item_id INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                changed_by_user_id INTEGER NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (restaurant_id, order_item_id)
            )");
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS kitchen_item_status (
            restaurant_id INT UNSIGNED NOT NULL,
            order_item_id INT UNSIGNED NOT NULL,
            status ENUM('pending','preparing','ready','served') NOT NULL DEFAULT 'pending',
            changed_by_user_id INT UNSIGNED NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (restaurant_id, order_item_id),
            KEY idx_kitchen_item_status_queue (restaurant_id, status),
            CONSTRAINT fk_kitchen_item_status_item FOREIGN KEY (restaurant_id, order_item_id)
              REFERENCES order_items (restaurant_id, id) ON DELETE CASCADE,
            CONSTRAINT fk_kitchen_item_status_user FOREIGN KEY (restaurant_id, changed_by_user_id)
              REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private const TRANSITIONS = [
        'pending' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'served',
        'served' => 'completed',
    ];

    /**
     * Return active kitchen tickets, including item notes and add-ons.
     * Every query is restricted to the selected restaurant.
     */
    public static function getActiveTickets(int $restaurantId): array
    {
        if ($restaurantId <= 0) return [];

        // Ensure the additive item-status table exists before the ticket query joins it.
        self::ensureItemStatusSchema();

        $orders = Database::fetchAll(
            "SELECT o.id, o.order_number, o.order_type, o.table_id, o.order_status,
                    o.payment_status, o.notes, o.created_at, o.updated_at,
                    COALESCE(t.table_number, '') AS table_number,
                    COALESCE(t.name, '') AS table_name,
                    COALESCE(u.full_name, u.username, 'Staff') AS cashier_name
             FROM orders o
             LEFT JOIN restaurant_tables t
               ON t.id = o.table_id AND t.restaurant_id = o.restaurant_id
             LEFT JOIN users u
               ON u.id = o.cashier_id AND u.restaurant_id = o.restaurant_id
             WHERE o.restaurant_id = ?
               AND o.order_status IN ('pending', 'preparing', 'ready', 'served')
             ORDER BY CASE o.order_status
                        WHEN 'pending' THEN 1
                        WHEN 'preparing' THEN 2
                        WHEN 'ready' THEN 3
                        WHEN 'served' THEN 4
                        ELSE 5
                      END,
                      o.created_at ASC, o.id ASC",
            [$restaurantId]
        );

        if (!$orders) return [];

        $orderIds = array_map(static fn(array $order): int => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $items = Database::fetchAll(
            "SELECT oi.id, oi.order_id, oi.product_name, oi.variant_name, oi.quantity,
                    oi.special_instructions,
                    COALESCE(kis.status, 'pending') AS kitchen_status,
                    GROUP_CONCAT(
                      CONCAT(a.addon_name, CASE WHEN a.quantity > 1 THEN CONCAT(' ×', a.quantity) ELSE '' END)
                      ORDER BY a.id SEPARATOR ', '
                    ) AS addons
             FROM order_items oi
             LEFT JOIN order_item_addons a
               ON a.order_item_id = oi.id AND a.restaurant_id = oi.restaurant_id
             LEFT JOIN kitchen_item_status kis
               ON kis.order_item_id = oi.id AND kis.restaurant_id = oi.restaurant_id
             WHERE oi.restaurant_id = ? AND oi.order_id IN ($placeholders)
             GROUP BY oi.id, oi.order_id, oi.product_name, oi.variant_name,
                      oi.quantity, oi.special_instructions, kis.status
             ORDER BY oi.id ASC",
            array_merge([$restaurantId], $orderIds)
        );

        $itemsByOrder = [];
        foreach ($items as $item) {
            $orderId = (int)$item['order_id'];
            $itemsByOrder[$orderId][] = $item;
        }

        foreach ($orders as &$order) {
            $orderId = (int)$order['id'];
            $order['id'] = $orderId;
            $order['items'] = $itemsByOrder[$orderId] ?? [];
            $order['elapsed_seconds'] = max(0, time() - (int)(strtotime((string)$order['created_at']) ?: time()));
        }
        unset($order);

        return $orders;
    }

    /**
     * Stable signature for lightweight polling; elapsed seconds are intentionally excluded.
     */
    public static function ticketSignature(array $tickets): string
    {
        foreach ($tickets as &$ticket) {
            unset($ticket['elapsed_seconds']);
        }
        unset($ticket);

        return hash('sha256', (string)json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Advance a ticket by exactly one step and write an audit event atomically.
     */
    /**
     * Update one item without changing the order-level ticket workflow.
     */
    public static function updateItemStatus(int $restaurantId, int $orderId, int $itemId, string $targetStatus, int $changedByUserId): array
    {
        if ($restaurantId <= 0 || $orderId <= 0 || $itemId <= 0 || $changedByUserId <= 0) {
            throw new InvalidArgumentException('A valid restaurant, order, item, and signed-in staff account are required.');
        }
        self::ensureItemStatusSchema();
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            $pdo->beginTransaction();
            $lockSuffix = $driver === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT oi.id, o.order_status
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id AND o.restaurant_id = oi.restaurant_id
                 WHERE oi.restaurant_id = ? AND oi.order_id = ? AND oi.id = ?" . $lockSuffix
            );
            $stmt->execute([$restaurantId, $orderId, $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new RuntimeException('The kitchen item could not be found for this restaurant and order.');
            if (!in_array((string)$item['order_status'], ['pending', 'preparing', 'ready', 'served'], true)) {
                throw new InvalidArgumentException('Items can only be updated while the order is active in the kitchen.');
            }
            $currentStmt = $pdo->prepare(
                "SELECT status FROM kitchen_item_status WHERE restaurant_id = ? AND order_item_id = ?" . $lockSuffix
            );
            $currentStmt->execute([$restaurantId, $itemId]);
            $currentStatus = (string)($currentStmt->fetchColumn() ?: 'pending');
            $expectedNext = self::ITEM_TRANSITIONS[$currentStatus] ?? null;
            if ($expectedNext === null || $targetStatus !== $expectedNext) {
                throw new InvalidArgumentException('That item status change is not allowed. Refresh the kitchen display and use the next available action.');
            }
            if ($driver === 'sqlite') {
                $upsert = $pdo->prepare(
                    "INSERT INTO kitchen_item_status (restaurant_id, order_item_id, status, changed_by_user_id, updated_at)
                     VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                     ON CONFLICT(restaurant_id, order_item_id) DO UPDATE SET
                       status = excluded.status, changed_by_user_id = excluded.changed_by_user_id, updated_at = CURRENT_TIMESTAMP"
                );
            } else {
                $upsert = $pdo->prepare(
                    "INSERT INTO kitchen_item_status (restaurant_id, order_item_id, status, changed_by_user_id)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status), changed_by_user_id = VALUES(changed_by_user_id)"
                );
            }
            $upsert->execute([$restaurantId, $itemId, $targetStatus, $changedByUserId]);
            $pdo->commit();
            return ['order_id' => $orderId, 'item_id' => $itemId, 'from_status' => $currentStatus, 'to_status' => $targetStatus];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateTicketStatus(int $restaurantId, int $orderId, string $targetStatus, int $changedByUserId): array
    {
        if ($restaurantId <= 0 || $orderId <= 0 || $changedByUserId <= 0) {
            throw new InvalidArgumentException('A valid restaurant, order, and signed-in staff account are required.');
        }

        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            $pdo->beginTransaction();

            $lockSuffix = $driver === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                'SELECT id, order_status, order_type, table_id FROM orders WHERE restaurant_id = ? AND id = ?' . $lockSuffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('The kitchen ticket could not be found for this restaurant.');
            }

            $currentStatus = (string)$order['order_status'];
            $expectedNext = self::TRANSITIONS[$currentStatus] ?? null;
            if ($expectedNext === null || $targetStatus !== $expectedNext) {
                throw new InvalidArgumentException('That status change is not allowed. Refresh the kitchen display and use the next available action.');
            }

            $update = $pdo->prepare(
                'UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ? AND order_status = ?'
            );
            $update->execute([$targetStatus, $restaurantId, $orderId, $currentStatus]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The ticket changed on another screen. Refresh the kitchen display before updating it again.');
            }

            $history = $pdo->prepare(
                'INSERT INTO order_status_history
                    (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $history->execute([
                $restaurantId, $orderId, $currentStatus, $targetStatus,
                $changedByUserId, 'Updated via Kitchen Display'
            ]);

            // Release the table only when every active ticket assigned to it has completed.
            // If a booked reservation is due within two hours, restore the Reserved state.
            if ($targetStatus === 'completed' && $order['order_type'] === 'dine_in' && !empty($order['table_id'])) {
                $tableId = (int)$order['table_id'];
                $activeStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM orders
                     WHERE restaurant_id = ? AND table_id = ?
                       AND order_status IN ('pending','preparing','ready','served')"
                );
                $activeStmt->execute([$restaurantId, $tableId]);
                if ((int)$activeStmt->fetchColumn() === 0) {
                    $reservationDue = false;
                    try {
                        $reservationStmt = $pdo->prepare(
                            "SELECT COUNT(*) FROM reservations
                             WHERE restaurant_id = ? AND table_id = ? AND status = 'booked'
                               AND reservation_at >= NOW()
                               AND reservation_at < DATE_ADD(NOW(), INTERVAL 2 HOUR)"
                        );
                        $reservationStmt->execute([$restaurantId, $tableId]);
                        $reservationDue = (int)$reservationStmt->fetchColumn() > 0;
                    } catch (Throwable $ignored) {
                        // Older installations may not yet have run the Reservations migration.
                    }

                    $newTableStatus = $reservationDue ? 'reserved' : 'available';
                    $tableStmt = $pdo->prepare(
                        "UPDATE restaurant_tables SET status = ?
                         WHERE restaurant_id = ? AND id = ? AND status = 'occupied'"
                    );
                    $tableStmt->execute([$newTableStatus, $restaurantId, $tableId]);
                }
            }

            $pdo->commit();
            return ['order_id' => $orderId, 'from_status' => $currentStatus, 'to_status' => $targetStatus];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
