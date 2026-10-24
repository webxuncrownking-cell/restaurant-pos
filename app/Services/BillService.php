<?php
/**
 * Restaurant POS & Management SaaS
 * Open bills, idempotent payments, and equal-share split settlement.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use PDO;

class BillService
{
    public static function schemaReady(): bool
    {
        try {
            $pdo = Database::getInstance();
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $tables = Database::fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('order_bill_splits','bill_split_payment_allocations','bill_split_items')");
                $paymentColumns = Database::fetchAll("PRAGMA table_info(payments)");
                $hasPaymentUuid = count(array_filter($paymentColumns, static fn(array $column): bool => ($column['name'] ?? '') === 'client_payment_uuid')) > 0;
                return count($tables) === 3 && $hasPaymentUuid;
            }
            $tables = Database::fetchAll(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('order_bill_splits','bill_split_payment_allocations','bill_split_items')"
            );
            $column = Database::fetchOne(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'client_payment_uuid'"
            );
            return count($tables) === 3 && $column !== null;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Additive migration only: it never drops or rewrites existing orders or payments.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $paymentColumns = Database::fetchAll("PRAGMA table_info(payments)");
            $hasPaymentUuid = count(array_filter($paymentColumns, static fn(array $column): bool => ($column['name'] ?? '') === 'client_payment_uuid')) > 0;
            if (!$hasPaymentUuid) $pdo->exec("ALTER TABLE payments ADD COLUMN client_payment_uuid TEXT NULL");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uk_payment_client_uuid ON payments (restaurant_id, client_payment_uuid)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS order_bill_splits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL,
                split_number INTEGER NOT NULL,
                split_label TEXT NOT NULL,
                amount_due NUMERIC NOT NULL,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (restaurant_id, order_id, split_number)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bill_split_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL,
                split_id INTEGER NOT NULL,
                order_item_id INTEGER NOT NULL,
                allocated_quantity NUMERIC NOT NULL,
                allocated_amount NUMERIC NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (restaurant_id, order_id, order_item_id)
            )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS bill_split_payment_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL,
                split_id INTEGER NOT NULL,
                payment_id INTEGER NOT NULL,
                allocated_amount NUMERIC NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (restaurant_id, split_id, payment_id)
            )");
            return;
        }

        $paymentColumn = Database::fetchOne(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'client_payment_uuid'"
        );
        if (!$paymentColumn) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN client_payment_uuid VARCHAR(36) NULL");
        }
        $paymentIndex = Database::fetchOne(
            "SELECT index_name FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'payments' AND index_name = 'uk_payment_client_uuid'"
        );
        if (!$paymentIndex) {
            $pdo->exec("CREATE UNIQUE INDEX uk_payment_client_uuid ON payments (restaurant_id, client_payment_uuid)");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_bill_splits (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            split_number SMALLINT UNSIGNED NOT NULL,
            split_label VARCHAR(100) NOT NULL,
            amount_due DECIMAL(10,2) NOT NULL,
            created_by_user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_bill_split_tenant_id (restaurant_id, id),
            UNIQUE KEY uk_bill_split_tenant_order_id (restaurant_id, order_id, id),
            UNIQUE KEY uk_bill_split_order_number (restaurant_id, order_id, split_number),
            CONSTRAINT fk_bill_split_order FOREIGN KEY (restaurant_id, order_id) REFERENCES orders (restaurant_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_bill_split_creator FOREIGN KEY (restaurant_id, created_by_user_id) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // This table must be created by the MySQL path (not inside the SQLite branch).
        $pdo->exec("CREATE TABLE IF NOT EXISTS bill_split_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            split_id INT UNSIGNED NOT NULL,
            order_item_id INT UNSIGNED NOT NULL,
            allocated_quantity DECIMAL(8,2) NOT NULL,
            allocated_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_bill_split_item_once (restaurant_id, order_id, order_item_id),
            KEY idx_bill_split_items_share (restaurant_id, order_id, split_id),
            CONSTRAINT fk_bill_split_item_share FOREIGN KEY (restaurant_id, order_id, split_id)
                REFERENCES order_bill_splits (restaurant_id, order_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_bill_split_item_order_item FOREIGN KEY (restaurant_id, order_id, order_item_id)
                REFERENCES order_items (restaurant_id, order_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bill_split_payment_allocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            split_id INT UNSIGNED NOT NULL,
            payment_id INT UNSIGNED NOT NULL,
            allocated_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_bill_allocation_split_payment (restaurant_id, split_id, payment_id),
            KEY idx_bill_allocation_order (restaurant_id, order_id),
            CONSTRAINT fk_bill_allocation_split FOREIGN KEY (restaurant_id, order_id, split_id) REFERENCES order_bill_splits (restaurant_id, order_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_bill_allocation_payment FOREIGN KEY (restaurant_id, order_id, payment_id) REFERENCES payments (restaurant_id, order_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function getOpenBills(int $restaurantId): array
    {
        $bills = Database::fetchAll(
            "SELECT o.id, o.order_number, o.order_type, o.order_status, o.payment_status,
                    o.total_amount, o.created_at, o.table_id,
                    t.table_number, t.name AS table_name,
                    u.full_name AS cashier_name,
                    COALESCE((SELECT SUM(p.amount) FROM payments p
                              WHERE p.restaurant_id = o.restaurant_id AND p.order_id = o.id AND p.is_verified = 1), 0) AS amount_paid
             FROM orders o
             LEFT JOIN restaurant_tables t ON t.restaurant_id = o.restaurant_id AND t.id = o.table_id
             LEFT JOIN users u ON u.restaurant_id = o.restaurant_id AND u.id = o.cashier_id
             WHERE o.restaurant_id = ?
               AND o.payment_status IN ('unpaid','partially_paid')
               AND o.order_status NOT IN ('cancelled')
             ORDER BY o.created_at ASC, o.id ASC",
            [$restaurantId]
        );

        foreach ($bills as &$bill) {
            $bill['total_amount'] = round((float)$bill['total_amount'], 2);
            $bill['amount_paid'] = round((float)$bill['amount_paid'], 2);
            $bill['balance_due'] = max(0, round($bill['total_amount'] - $bill['amount_paid'], 2));
            $bill['items'] = Database::fetchAll(
                "SELECT id, product_name, variant_name, quantity, unit_price, subtotal, special_instructions
                 FROM order_items WHERE restaurant_id = ? AND order_id = ? ORDER BY id ASC",
                [$restaurantId, (int)$bill['id']]
            );
            $bill['splits'] = self::getSplits($restaurantId, (int)$bill['id']);
        }
        unset($bill);
        return $bills;
    }

    public static function getSplits(int $restaurantId, int $orderId): array
    {
        $splits = Database::fetchAll(
            "SELECT s.id, s.order_id, s.split_number, s.split_label, s.amount_due,
                    COALESCE(SUM(a.allocated_amount), 0) AS amount_paid
             FROM order_bill_splits s
             LEFT JOIN bill_split_payment_allocations a
               ON a.restaurant_id = s.restaurant_id AND a.order_id = s.order_id AND a.split_id = s.id
             WHERE s.restaurant_id = ? AND s.order_id = ?
             GROUP BY s.id, s.order_id, s.split_number, s.split_label, s.amount_due
             ORDER BY s.split_number ASC",
            [$restaurantId, $orderId]
        );
        foreach ($splits as &$split) {
            $split['amount_due'] = round((float)$split['amount_due'], 2);
            $split['amount_paid'] = round((float)$split['amount_paid'], 2);
            $split['balance_due'] = max(0, round($split['amount_due'] - $split['amount_paid'], 2));
            $split['items'] = Database::fetchAll(
                "SELECT oi.product_name, oi.variant_name, bsi.allocated_quantity, bsi.allocated_amount
                 FROM bill_split_items bsi
                 JOIN order_items oi ON oi.restaurant_id = bsi.restaurant_id AND oi.order_id = bsi.order_id AND oi.id = bsi.order_item_id
                 WHERE bsi.restaurant_id = ? AND bsi.order_id = ? AND bsi.split_id = ?
                 ORDER BY oi.id ASC",
                [$restaurantId, $orderId, (int)$split['id']]
            );
        }
        unset($split);
        return $splits;
    }

    public static function createEqualSplits(int $restaurantId, int $orderId, int $userId, int $count, array $labels = []): array
    {
        if ($count < 2 || $count > 12) {
            throw new InvalidArgumentException('Split the bill into 2 to 12 shares.');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT id, order_type, order_status, payment_status, total_amount, table_id
                 FROM orders WHERE restaurant_id = ? AND id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['order_status'] === 'cancelled') {
                throw new InvalidArgumentException('Open bill not found for this restaurant.');
            }
            if ($order['order_type'] !== 'dine_in' || empty($order['table_id'])) {
                throw new InvalidArgumentException('Only a dine-in bill assigned to a table can be split.');
            }
            if ($order['payment_status'] !== 'unpaid') {
                throw new InvalidArgumentException('A bill can only be split before any payment has been collected.');
            }
            $paymentStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE restaurant_id = ? AND order_id = ?");
            $paymentStmt->execute([$restaurantId, $orderId]);
            if ((int)$paymentStmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('This bill already has payment history and cannot be split retroactively.');
            }
            $splitStmt = $pdo->prepare("SELECT COUNT(*) FROM order_bill_splits WHERE restaurant_id = ? AND order_id = ?");
            $splitStmt->execute([$restaurantId, $orderId]);
            if ((int)$splitStmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('This bill has already been split.');
            }

            $totalCents = (int)round((float)$order['total_amount'] * 100);
            if ($totalCents <= 0) throw new InvalidArgumentException('A zero-value bill does not need splitting.');
            $baseCents = intdiv($totalCents, $count);
            $remainder = $totalCents % $count;
            for ($i = 1; $i <= $count; $i++) {
                $label = trim((string)($labels[$i - 1] ?? ''));
                if ($label === '') $label = 'Guest ' . $i;
                if (mb_strlen($label) > 100) throw new InvalidArgumentException('Share names must be 100 characters or fewer.');
                $shareCents = $baseCents + ($i <= $remainder ? 1 : 0);
                Database::query(
                    "INSERT INTO order_bill_splits (restaurant_id, order_id, split_number, split_label, amount_due, created_by_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$restaurantId, $orderId, $i, $label, $shareCents / 100, $userId]
                );
            }
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                Database::query(
                    "INSERT INTO order_status_history (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$restaurantId, $orderId, (string)$order['order_status'], (string)$order['order_status'], $userId, 'Bill split into ' . $count . ' equal shares']
                );
            }
            $pdo->commit();
            return self::getSplits($restaurantId, $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Assign each order line to one named guest share. Each line's discounted
     * amount is allocated proportionally in integer cents so shares sum exactly.
     */
    public static function createItemSplits(
        int $restaurantId,
        int $orderId,
        int $userId,
        int $count,
        array $labels,
        array $itemAssignments
    ): array {
        if ($count < 2 || $count > 12) {
            throw new InvalidArgumentException('Split the bill into 2 to 12 guest shares.');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT id, order_type, order_status, payment_status, total_amount, table_id
                 FROM orders WHERE restaurant_id = ? AND id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['order_status'] === 'cancelled') {
                throw new InvalidArgumentException('Open bill not found for this restaurant.');
            }
            if ($order['order_type'] !== 'dine_in' || empty($order['table_id'])) {
                throw new InvalidArgumentException('Only dine-in bills assigned to a table can be split by items.');
            }
            if ($order['payment_status'] !== 'unpaid') {
                throw new InvalidArgumentException('Items can only be split before any payment is collected.');
            }

            $paymentsStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE restaurant_id = ? AND order_id = ?");
            $paymentsStmt->execute([$restaurantId, $orderId]);
            if ((int)$paymentsStmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('This bill already has payment history and cannot be split retroactively.');
            }
            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM order_bill_splits WHERE restaurant_id = ? AND order_id = ?");
            $existsStmt->execute([$restaurantId, $orderId]);
            if ((int)$existsStmt->fetchColumn() > 0) {
                throw new InvalidArgumentException('This bill has already been split.');
            }

            $items = Database::fetchAll(
                "SELECT id, quantity, subtotal, product_name
                 FROM order_items WHERE restaurant_id = ? AND order_id = ? ORDER BY id ASC",
                [$restaurantId, $orderId]
            );
            if (count($items) < $count) {
                throw new InvalidArgumentException('There must be at least one order line for every guest share. Split the bill into fewer shares or separate item quantities on the ticket before sending it to the kitchen.');
            }
            $grossCents = 0;
            foreach ($items as &$item) {
                $item['line_cents'] = max(0, (int)round((float)$item['subtotal'] * 100));
                $grossCents += $item['line_cents'];
                $assignment = filter_var($itemAssignments[(string)$item['id']] ?? null, FILTER_VALIDATE_INT);
                if (!$assignment || $assignment < 1 || $assignment > $count) {
                    throw new InvalidArgumentException('Assign every order line to a guest share before creating the item split.');
                }
                $item['assigned_share'] = (int)$assignment;
            }
            unset($item);

            $totalCents = (int)round((float)$order['total_amount'] * 100);
            if ($grossCents <= 0 || $totalCents <= 0) {
                throw new InvalidArgumentException('A zero-value bill does not need splitting.');
            }

            $allocatedCents = 0;
            foreach ($items as &$item) {
                $numerator = $totalCents * (int)$item['line_cents'];
                $item['allocated_cents'] = intdiv($numerator, $grossCents);
                $item['remainder'] = $numerator % $grossCents;
                $allocatedCents += $item['allocated_cents'];
            }
            unset($item);

            $remainingCents = $totalCents - $allocatedCents;
            $remainderOrder = array_keys($items);
            usort($remainderOrder, static fn(int $a, int $b): int =>
                $items[$b]['remainder'] <=> $items[$a]['remainder']
                    ?: $items[$a]['id'] <=> $items[$b]['id']
            );
            for ($i = 0; $i < $remainingCents; $i++) {
                $items[$remainderOrder[$i]]['allocated_cents']++;
            }

            $shareCents = array_fill(1, $count, 0);
            $shareItemCounts = array_fill(1, $count, 0);
            foreach ($items as $item) {
                $shareNumber = (int)$item['assigned_share'];
                $shareCents[$shareNumber] += (int)$item['allocated_cents'];
                $shareItemCounts[$shareNumber]++;
            }
            for ($share = 1; $share <= $count; $share++) {
                if ($shareItemCounts[$share] === 0 || $shareCents[$share] <= 0) {
                    throw new InvalidArgumentException('Every guest share must have at least one order line with a positive balance.');
                }
            }

            $splitIds = [];
            for ($share = 1; $share <= $count; $share++) {
                $label = trim((string)($labels[$share - 1] ?? ''));
                if ($label === '') $label = 'Guest ' . $share;
                if (mb_strlen($label) > 100) throw new InvalidArgumentException('Share names must be 100 characters or fewer.');
                Database::query(
                    "INSERT INTO order_bill_splits (restaurant_id, order_id, split_number, split_label, amount_due, created_by_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$restaurantId, $orderId, $share, $label, $shareCents[$share] / 100, $userId]
                );
                $splitIds[$share] = (int)Database::lastInsertId();
            }

            foreach ($items as $item) {
                Database::query(
                    "INSERT INTO bill_split_items
                        (restaurant_id, order_id, split_id, order_item_id, allocated_quantity, allocated_amount)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $restaurantId, $orderId, $splitIds[(int)$item['assigned_share']],
                        (int)$item['id'], (float)$item['quantity'], (int)$item['allocated_cents'] / 100
                    ]
                );
            }

            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                Database::query(
                    "INSERT INTO order_status_history (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$restaurantId, $orderId, (string)$order['order_status'], (string)$order['order_status'], $userId, 'Bill split by item lines into ' . $count . ' guest shares']
                );
            }
            $pdo->commit();
            return self::getSplits($restaurantId, $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function recordPayment(
        int $restaurantId,
        int $orderId,
        int $userId,
        string $paymentUuid,
        float $amount,
        float $tenderedAmount,
        string $method,
        ?int $splitId = null
    ): array {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $paymentUuid)) {
            throw new InvalidArgumentException('The payment request identifier is invalid. Refresh the page and try again.');
        }
        if (!in_array($method, ['cash', 'card', 'qr_online', 'other'], true)) {
            throw new InvalidArgumentException('Choose a supported payment method.');
        }
        $amount = round($amount, 2);
        $tenderedAmount = round($tenderedAmount, 2);
        if (!is_finite($amount) || $amount <= 0 || !is_finite($tenderedAmount)) {
            throw new InvalidArgumentException('Enter a valid payment amount greater than zero.');
        }
        if ($method !== 'cash') $tenderedAmount = $amount;
        if ($tenderedAmount < $amount) throw new InvalidArgumentException('Cash tendered cannot be less than the payment amount.');

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            $duplicate = Database::fetchOne(
                "SELECT id, order_id, payment_method, amount FROM payments
                 WHERE restaurant_id = ? AND client_payment_uuid = ?",
                [$restaurantId, $paymentUuid]
            );
            if ($duplicate) {
                if ((int)$duplicate['order_id'] !== $orderId
                    || (string)$duplicate['payment_method'] !== $method
                    || abs((float)$duplicate['amount'] - $amount) >= 0.01) {
                    throw new InvalidArgumentException('This payment request identifier was already used for a different payment.');
                }
                $pdo->commit();
                return ['status' => 'already_recorded', 'payment_id' => (int)$duplicate['id']];
            }

            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT id, order_status, payment_status, total_amount
                 FROM orders WHERE restaurant_id = ? AND id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['order_status'] === 'cancelled') {
                throw new InvalidArgumentException('Open bill not found for this restaurant.');
            }
            if (!in_array($order['payment_status'], ['unpaid', 'partially_paid'], true)) {
                throw new InvalidArgumentException('This bill is already fully settled.');
            }

            $paidRow = Database::fetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS paid FROM payments
                 WHERE restaurant_id = ? AND order_id = ? AND is_verified = 1",
                [$restaurantId, $orderId]
            );
            $paid = round((float)($paidRow['paid'] ?? 0), 2);
            $balance = round((float)$order['total_amount'] - $paid, 2);
            if ($balance <= 0.009) throw new InvalidArgumentException('This bill is already fully settled.');
            if ($amount - $balance > 0.009) throw new InvalidArgumentException('Payment amount cannot exceed the remaining balance.');

            $splits = self::getSplits($restaurantId, $orderId);
            if ($splits) {
                if (!$splitId) throw new InvalidArgumentException('Choose the guest share receiving this payment.');
                $split = null;
                foreach ($splits as $candidate) if ((int)$candidate['id'] === $splitId) $split = $candidate;
                if (!$split) throw new InvalidArgumentException('The selected share does not belong to this bill.');
                if ($split['balance_due'] <= 0.009) throw new InvalidArgumentException('That guest share is already settled.');
                if ($amount - (float)$split['balance_due'] > 0.009) throw new InvalidArgumentException('Payment amount cannot exceed this guest share balance.');
            } elseif ($splitId !== null) {
                throw new InvalidArgumentException('This bill has no split shares.');
            }

            $change = round($tenderedAmount - $amount, 2);
            Database::query(
                "INSERT INTO payments
                    (restaurant_id, order_id, cashier_id, payment_method, amount, tendered_amount, change_amount, client_payment_uuid, paid_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$restaurantId, $orderId, $userId, $method, $amount, $tenderedAmount, $change, $paymentUuid]
            );
            $paymentId = (int)Database::lastInsertId();

            if ($splitId !== null) {
                Database::query(
                    "INSERT INTO bill_split_payment_allocations
                        (restaurant_id, order_id, split_id, payment_id, allocated_amount)
                     VALUES (?, ?, ?, ?, ?)",
                    [$restaurantId, $orderId, $splitId, $paymentId, $amount]
                );
            }

            $newPaid = round($paid + $amount, 2);
            $total = round((float)$order['total_amount'], 2);
            $newPaymentStatus = $newPaid + 0.009 >= $total ? 'paid' : 'partially_paid';
            Database::query(
                "UPDATE orders
                 SET paid_amount = ?, change_amount = change_amount + ?, payment_status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ?",
                [$newPaid, $change, $newPaymentStatus, $restaurantId, $orderId]
            );

            Database::query(
                "INSERT INTO order_status_history
                    (restaurant_id, order_id, from_status, to_status, changed_by_user_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $restaurantId, $orderId, (string)$order['order_status'], (string)$order['order_status'], $userId,
                    'Payment #' . $paymentId . ' received: ' . number_format($amount, 2, '.', '')
                    . '; payment status ' . $order['payment_status'] . ' → ' . $newPaymentStatus
                ]
            );

            $pdo->commit();
            return [
                'status' => 'recorded',
                'payment_id' => $paymentId,
                'amount_paid' => $newPaid,
                'balance_due' => max(0, round($total - $newPaid, 2)),
                'payment_status' => $newPaymentStatus
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
