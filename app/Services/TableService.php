<?php
/**
 * Restaurant POS & Management SaaS
 * Table Management & Floor Zones Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class TableService
{
    /**
     * Get all active tables for a restaurant, optionally filtered by floor zone
     */
    public static function getTables(int $restaurantId, ?string $floorZone = null, bool $includeDeleted = false): array
    {
        $sql = "SELECT id, restaurant_id, table_number, name, capacity, floor_zone, status, is_active, deleted_at 
                FROM restaurant_tables 
                WHERE restaurant_id = :restaurant_id";
        
        $params = [':restaurant_id' => $restaurantId];

        if (!$includeDeleted) {
            $sql .= " AND deleted_at IS NULL AND is_active = 1";
        }

        if ($floorZone !== null && $floorZone !== '' && $floorZone !== 'all') {
            $sql .= " AND floor_zone = :floor_zone";
            $params[':floor_zone'] = $floorZone;
        }

        $sql .= " ORDER BY floor_zone ASC, CAST(table_number AS UNSIGNED) ASC, table_number ASC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get distinct floor zones for a restaurant
     */
    public static function getFloorZones(int $restaurantId): array
    {
        $sql = "SELECT DISTINCT floor_zone 
                FROM restaurant_tables 
                WHERE restaurant_id = :restaurant_id AND deleted_at IS NULL AND floor_zone IS NOT NULL 
                ORDER BY floor_zone ASC";
        
        $rows = Database::fetchAll($sql, [':restaurant_id' => $restaurantId]);
        $zones = array_column($rows, 'floor_zone');
        
        if (empty($zones)) {
            $zones = ['Main Dining', 'Patio', 'Bar', 'VIP / Function Room'];
        }

        return $zones;
    }

    /**
     * Get single table by ID with tenant isolation
     */
    public static function getTableById(int $restaurantId, int $tableId): ?array
    {
        $sql = "SELECT * FROM restaurant_tables WHERE restaurant_id = :restaurant_id AND id = :id AND deleted_at IS NULL";
        return Database::fetchOne($sql, [':restaurant_id' => $restaurantId, ':id' => $tableId]);
    }

    /**
     * Create a new table
     */
    public static function createTable(int $restaurantId, array $data): int
    {
        $tableNumber = trim($data['table_number'] ?? '');
        $name = trim($data['name'] ?? ('Table ' . $tableNumber));
        $capacity = max(1, (int)($data['capacity'] ?? 4));
        $floorZone = trim($data['floor_zone'] ?? 'Main Dining');
        $status = $data['status'] ?? 'available';

        if (empty($tableNumber)) {
            throw new InvalidArgumentException('Table number is required.');
        }

        // Check unique table_number within tenant
        $existing = Database::fetchOne(
            "SELECT id FROM restaurant_tables WHERE restaurant_id = :restaurant_id AND table_number = :table_number AND deleted_at IS NULL",
            [':restaurant_id' => $restaurantId, ':table_number' => $tableNumber]
        );
        if ($existing) {
            throw new InvalidArgumentException("Table number '{$tableNumber}' already exists in this restaurant.");
        }

        $sql = "INSERT INTO restaurant_tables (restaurant_id, table_number, name, capacity, floor_zone, status, is_active)
                VALUES (:restaurant_id, :table_number, :name, :capacity, :floor_zone, :status, 1)";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':table_number' => $tableNumber,
            ':name' => $name,
            ':capacity' => $capacity,
            ':floor_zone' => $floorZone,
            ':status' => in_array($status, ['available', 'occupied', 'reserved', 'cleaning']) ? $status : 'available'
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Update table details
     */
    public static function updateTable(int $restaurantId, int $tableId, array $data): bool
    {
        $tableNumber = trim($data['table_number'] ?? '');
        $name = trim($data['name'] ?? '');
        $capacity = max(1, (int)($data['capacity'] ?? 4));
        $floorZone = trim($data['floor_zone'] ?? 'Main Dining');
        $status = $data['status'] ?? 'available';

        if (empty($tableNumber)) {
            throw new InvalidArgumentException('Table number is required.');
        }

        // Check uniqueness excluding self
        $existing = Database::fetchOne(
            "SELECT id FROM restaurant_tables WHERE restaurant_id = :restaurant_id AND table_number = :table_number AND id != :id AND deleted_at IS NULL",
            [':restaurant_id' => $restaurantId, ':table_number' => $tableNumber, ':id' => $tableId]
        );
        if ($existing) {
            throw new InvalidArgumentException("Table number '{$tableNumber}' already exists.");
        }

        $sql = "UPDATE restaurant_tables 
                SET table_number = :table_number,
                    name = :name,
                    capacity = :capacity,
                    floor_zone = :floor_zone,
                    status = :status
                WHERE restaurant_id = :restaurant_id AND id = :id";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $tableId,
            ':table_number' => $tableNumber,
            ':name' => $name ?: ('Table ' . $tableNumber),
            ':capacity' => $capacity,
            ':floor_zone' => $floorZone,
            ':status' => in_array($status, ['available', 'occupied', 'reserved', 'cleaning']) ? $status : 'available'
        ]);

        return true;
    }

    /**
     * Quick status update (e.g. from POS or floor plan: available, occupied, reserved, cleaning)
     */
    public static function updateStatus(int $restaurantId, int $tableId, string $status): bool
    {
        if (!in_array($status, ['available', 'occupied', 'reserved', 'cleaning'])) {
            throw new InvalidArgumentException('Invalid table status.');
        }

        $sql = "UPDATE restaurant_tables SET status = :status WHERE restaurant_id = :restaurant_id AND id = :id AND deleted_at IS NULL";
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $tableId,
            ':status' => $status
        ]);

        return true;
    }

    /**
     * Soft delete table (Zero Cascade)
     */
    public static function softDelete(int $restaurantId, int $tableId): bool
    {
        $sql = "UPDATE restaurant_tables SET deleted_at = NOW(), is_active = 0 WHERE restaurant_id = :restaurant_id AND id = :id";
        Database::query($sql, [':restaurant_id' => $restaurantId, ':id' => $tableId]);
        return true;
    }
    /**
     * Active dine-in tickets that can be moved between floor tables.
     */
    public static function getActiveDineInOrders(int $restaurantId): array
    {
        return Database::fetchAll(
            "SELECT o.id, o.order_number, o.order_status, o.created_at, o.table_id,
                    t.table_number, t.name AS table_name
             FROM orders o
             JOIN restaurant_tables t
               ON t.restaurant_id = o.restaurant_id AND t.id = o.table_id
             WHERE o.restaurant_id = ?
               AND o.order_type = 'dine_in'
               AND o.order_status IN ('pending','preparing','ready','served')
               AND o.table_id IS NOT NULL
             ORDER BY o.created_at ASC, o.id ASC",
            [$restaurantId]
        );
    }

    /**
     * Transfer one active dine-in ticket and record the change in order history.
     * The destination must be an empty, available table in the same restaurant.
     */
    public static function transferActiveDineInOrder(int $restaurantId, int $orderId, int $destinationTableId, int $changedByUserId): array
    {
        if ($restaurantId <= 0 || $orderId <= 0 || $destinationTableId <= 0 || $changedByUserId <= 0) {
            throw new InvalidArgumentException('Choose a valid order and destination table.');
        }

        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSuffix = $driver === 'sqlite' ? '' : ' FOR UPDATE';

        try {
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare(
                "SELECT o.id, o.table_id, o.order_status, o.order_type,
                        t.table_number AS source_table_number
                 FROM orders o
                 JOIN restaurant_tables t
                   ON t.restaurant_id = o.restaurant_id AND t.id = o.table_id
                 WHERE o.restaurant_id = ? AND o.id = ?
                   AND o.order_type = 'dine_in'
                   AND o.order_status IN ('pending','preparing','ready','served')
                 " . $lockSuffix
            );
            $orderStmt->execute([$restaurantId, $orderId]);
            $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Only an active dine-in ticket assigned to a table can be transferred.');
            }

            $sourceTableId = (int)$order['table_id'];
            if ($sourceTableId === $destinationTableId) {
                throw new InvalidArgumentException('Choose a different destination table.');
            }

            $tableStmt = $pdo->prepare(
                "SELECT id, table_number, status
                 FROM restaurant_tables
                 WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL AND is_active = 1
                 " . $lockSuffix
            );
            $tableStmt->execute([$restaurantId, $destinationTableId]);
            $destination = $tableStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$destination || $destination['status'] !== 'available') {
                throw new InvalidArgumentException('The destination table must be active and marked available.');
            }

            $occupiedByTicket = $pdo->prepare(
                "SELECT id FROM orders
                 WHERE restaurant_id = ? AND table_id = ?
                   AND order_status IN ('pending','preparing','ready','served')
                 LIMIT 1" . $lockSuffix
            );
            $occupiedByTicket->execute([$restaurantId, $destinationTableId]);
            if ($occupiedByTicket->fetch(\PDO::FETCH_ASSOC)) {
                throw new InvalidArgumentException('The destination table already has an active ticket.');
            }

            $update = $pdo->prepare(
                "UPDATE orders SET table_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ? AND table_id = ?
                   AND order_status IN ('pending','preparing','ready','served')"
            );
            $update->execute([$destinationTableId, $restaurantId, $orderId, $sourceTableId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The ticket changed while it was being transferred. Refresh the floor plan and try again.');
            }

            $pdo->prepare(
                "UPDATE restaurant_tables SET status = 'occupied'
                 WHERE restaurant_id = ? AND id = ?"
            )->execute([$restaurantId, $destinationTableId]);

            $remaining = $pdo->prepare(
                "SELECT COUNT(*) FROM orders
                 WHERE restaurant_id = ? AND table_id = ?
                   AND order_status IN ('pending','preparing','ready','served')"
            );
            $remaining->execute([$restaurantId, $sourceTableId]);
            if ((int)$remaining->fetchColumn() === 0) {
                $pdo->prepare(
                    "UPDATE restaurant_tables SET status = 'available'
                     WHERE restaurant_id = ? AND id = ? AND status = 'occupied'"
                )->execute([$restaurantId, $sourceTableId]);
            }

            $note = 'Table transfer from #' . (string)$order['source_table_number']
                . ' to #' . (string)$destination['table_number']
                . ' by staff #' . $changedByUserId;
            $history = $pdo->prepare(
                "INSERT INTO order_status_history
                    (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $history->execute([
                $restaurantId, $orderId, (string)$order['order_status'],
                (string)$order['order_status'], $changedByUserId, $note
            ]);

            $pdo->commit();
            return [
                'order_id' => $orderId,
                'source_table_id' => $sourceTableId,
                'destination_table_id' => $destinationTableId,
                'destination_table_number' => (string)$destination['table_number']
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Merge two still-new dine-in tickets into one kitchen ticket.
     * Restrictions protect paid accounting records: same table, cashier, shift,
     * and device; pending status only; no void/refund/conflict records.
     */
    public static function mergeActiveDineInTickets(int $restaurantId, int $targetOrderId, int $sourceOrderId, int $changedByUserId): array
    {
        if ($restaurantId <= 0 || $targetOrderId <= 0 || $sourceOrderId <= 0 || $changedByUserId <= 0) {
            throw new InvalidArgumentException('Choose two valid tickets to merge.');
        }
        if ($targetOrderId === $sourceOrderId) {
            throw new InvalidArgumentException('Choose two different tickets.');
        }

        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSuffix = $driver === 'sqlite' ? '' : ' FOR UPDATE';

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, table_id, order_type, order_status, payment_status, cashier_id, device_id, shift_id,
                        subtotal, discount_amount, tax_amount, total_amount, paid_amount, change_amount, notes
                 FROM orders
                 WHERE restaurant_id = ? AND id IN (?, ?)
                 ORDER BY id" . $lockSuffix
            );
            $stmt->execute([$restaurantId, $targetOrderId, $sourceOrderId]);
            $orders = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $order) {
                $orders[(int)$order['id']] = $order;
            }

            if (count($orders) !== 2) {
                throw new InvalidArgumentException('Both tickets must belong to this restaurant.');
            }
            $target = $orders[$targetOrderId];
            $source = $orders[$sourceOrderId];

            foreach ([$target, $source] as $order) {
                if ($order['order_type'] !== 'dine_in' || $order['order_status'] !== 'pending' || $order['payment_status'] !== 'paid') {
                    throw new InvalidArgumentException('Only paid, not-yet-preparing dine-in tickets can be merged.');
                }
                if (empty($order['table_id'])) {
                    throw new InvalidArgumentException('Both tickets must belong to the same dining table.');
                }
            }

            if ((int)$target['table_id'] !== (int)$source['table_id']) {
                throw new InvalidArgumentException('Both tickets must be assigned to the same table.');
            }
            foreach (['cashier_id', 'device_id'] as $field) {
                if ((int)$target[$field] !== (int)$source[$field]) {
                    throw new InvalidArgumentException('Tickets must belong to the same cashier and POS device.');
                }
            }
            if ((int)($target['shift_id'] ?? 0) !== (int)($source['shift_id'] ?? 0)) {
                throw new InvalidArgumentException('Tickets must belong to the same cashier shift.');
            }

            foreach (['void_records', 'refund_records', 'inventory_conflicts'] as $tableName) {
                // Table names come from this fixed internal allowlist.
                $audit = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE restaurant_id = ? AND order_id IN (?, ?)");
                $audit->execute([$restaurantId, $targetOrderId, $sourceOrderId]);
                if ((int)$audit->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Tickets with voids, refunds, or inventory conflicts cannot be merged.');
                }
            }

            // Preserve every line and addon snapshot; inventory is not deducted again.
            $pdo->prepare("UPDATE order_items SET order_id = ? WHERE restaurant_id = ? AND order_id = ?")
                ->execute([$targetOrderId, $restaurantId, $sourceOrderId]);
            $pdo->prepare("UPDATE payments SET order_id = ? WHERE restaurant_id = ? AND order_id = ?")
                ->execute([$targetOrderId, $restaurantId, $sourceOrderId]);
            $pdo->prepare(
                "UPDATE inventory_movements SET reference_id = ?
                 WHERE restaurant_id = ? AND reference_type = 'order_sale' AND reference_id = ?"
            )->execute([$targetOrderId, $restaurantId, $sourceOrderId]);

            $combinedNotes = trim((string)($target['notes'] ?? ''));
            $sourceNotes = trim((string)($source['notes'] ?? ''));
            $mergeNote = 'Merged order #' . $sourceOrderId;
            if ($sourceNotes !== '') $mergeNote .= ': ' . $sourceNotes;
            $combinedNotes = trim($combinedNotes . ($combinedNotes !== '' ? "\n" : '') . $mergeNote);

            $pdo->prepare(
                "UPDATE orders
                 SET subtotal = ?, discount_amount = ?, tax_amount = ?, total_amount = ?,
                     paid_amount = ?, change_amount = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ? AND order_status = 'pending'"
            )->execute([
                round((float)$target['subtotal'] + (float)$source['subtotal'], 2),
                round((float)$target['discount_amount'] + (float)$source['discount_amount'], 2),
                round((float)$target['tax_amount'] + (float)$source['tax_amount'], 2),
                round((float)$target['total_amount'] + (float)$source['total_amount'], 2),
                round((float)$target['paid_amount'] + (float)$source['paid_amount'], 2),
                round((float)$target['change_amount'] + (float)$source['change_amount'], 2),
                $combinedNotes, $restaurantId, $targetOrderId
            ]);

            $pdo->prepare(
                "UPDATE orders SET order_status = 'cancelled', updated_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ? AND order_status = 'pending'"
            )->execute([$restaurantId, $sourceOrderId]);

            $history = $pdo->prepare(
                "INSERT INTO order_status_history
                    (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $history->execute([
                $restaurantId, $targetOrderId, 'pending', 'pending', $changedByUserId,
                'Merged order #' . $sourceOrderId . ' into ticket #' . $targetOrderId
            ]);
            $history->execute([
                $restaurantId, $sourceOrderId, 'pending', 'cancelled', $changedByUserId,
                'Merged into ticket #' . $targetOrderId
            ]);

            $pdo->commit();
            return [
                'target_order_id' => $targetOrderId,
                'source_order_id' => $sourceOrderId,
                'merged_total' => round((float)$target['total_amount'] + (float)$source['total_amount'], 2)
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

}
