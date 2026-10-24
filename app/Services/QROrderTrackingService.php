<?php
/**
 * Restaurant POS & Management SaaS
 * Secure public tracking links for QR-submitted table orders.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class QROrderTrackingService
{
    private static ?bool $readyCache = null;

    public static function schemaReady(): bool
    {
        if (self::$readyCache !== null) return self::$readyCache;
        try {
            $pdo = Database::getConnection();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $tables = Database::fetchAll("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'qr_order_tracking'");
                self::$readyCache = !empty($tables);
            } else {
                $row = Database::fetchOne(
                    "SELECT COUNT(*) AS total FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = 'qr_order_tracking'"
                );
                self::$readyCache = (int)($row['total'] ?? 0) > 0;
            }
            return self::$readyCache;
        } catch (Throwable $e) {
            self::$readyCache = false;
            return false;
        }
    }

    /**
     * Safe additive setup; can be applied automatically when an order is submitted.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getConnection();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS qr_order_tracking (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    restaurant_id INTEGER NOT NULL,
                    order_id INTEGER NOT NULL,
                    table_id INTEGER NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (restaurant_id, order_id)
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_qr_order_tracking_table ON qr_order_tracking(restaurant_id, table_id, order_id)");
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS qr_order_tracking (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    restaurant_id INT UNSIGNED NOT NULL,
                    order_id INT UNSIGNED NOT NULL,
                    table_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_qr_order_tracking_token (token_hash),
                    UNIQUE KEY uq_qr_order_tracking_order (restaurant_id, order_id),
                    KEY idx_qr_order_tracking_table (restaurant_id, table_id, order_id),
                    CONSTRAINT fk_qr_tracking_order FOREIGN KEY (restaurant_id, order_id)
                        REFERENCES orders (restaurant_id, id) ON DELETE CASCADE,
                    CONSTRAINT fk_qr_tracking_table FOREIGN KEY (restaurant_id, table_id)
                        REFERENCES restaurant_tables (restaurant_id, id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        self::$readyCache = null;
        if (!self::schemaReady()) {
            throw new RuntimeException('QR customer tracking storage could not be prepared.');
        }
    }

    /**
     * Return the raw token to the guest once; only its SHA-256 hash is stored.
     */
    public static function createTrackingToken(int $restaurantId, int $orderId, int $tableId): string
    {
        if ($restaurantId < 1 || $orderId < 1 || $tableId < 1) {
            throw new RuntimeException('A valid restaurant order and table are required for tracking.');
        }
        self::ensureSchema();

        $order = Database::fetchOne(
            "SELECT id, table_id, order_type
             FROM orders
             WHERE restaurant_id = ? AND id = ?
             LIMIT 1",
            [$restaurantId, $orderId]
        );
        if (!$order || (int)$order['table_id'] !== $tableId || (string)$order['order_type'] !== 'dine_in') {
            throw new RuntimeException('The order does not belong to this dining table.');
        }

        $existing = Database::fetchOne(
            "SELECT id FROM qr_order_tracking WHERE restaurant_id = ? AND order_id = ?",
            [$restaurantId, $orderId]
        );
        if ($existing) {
            throw new RuntimeException('A customer tracking link already exists for this order.');
        }

        $token = bin2hex(random_bytes(32));
        Database::query(
            "INSERT INTO qr_order_tracking (restaurant_id, order_id, table_id, token_hash)
             VALUES (?, ?, ?, ?)",
            [$restaurantId, $orderId, $tableId, hash('sha256', $token)]
        );
        return $token;
    }

    public static function getTrackedOrder(int $restaurantId, int $tableId, string $token): ?array
    {
        if ($restaurantId < 1 || $tableId < 1 || !preg_match('/^[a-f0-9]{64}$/i', $token) || !self::schemaReady()) {
            return null;
        }
        return Database::fetchOne(
            "SELECT o.id, o.order_number, o.order_status, o.payment_status,
                    o.total_amount, o.paid_amount, o.created_at, o.updated_at,
                    t.table_number, t.name AS table_name, r.name AS restaurant_name,
                    r.logo_path
             FROM qr_order_tracking qt
             JOIN orders o ON o.restaurant_id = qt.restaurant_id AND o.id = qt.order_id
             JOIN restaurant_tables t ON t.restaurant_id = qt.restaurant_id AND t.id = qt.table_id
             JOIN restaurants r ON r.id = qt.restaurant_id
             WHERE qt.restaurant_id = ? AND qt.table_id = ? AND qt.token_hash = ?
             LIMIT 1",
            [$restaurantId, $tableId, hash('sha256', strtolower($token))]
        );
    }

    public static function publicStatusSnapshot(array $order): array
    {
        $allowedStatuses = ['pending', 'preparing', 'ready', 'served', 'completed', 'cancelled'];
        $allowedPayments = ['unpaid', 'partially_paid', 'paid', 'refunded'];
        $status = in_array((string)($order['order_status'] ?? ''), $allowedStatuses, true)
            ? (string)$order['order_status'] : 'pending';
        $payment = in_array((string)($order['payment_status'] ?? ''), $allowedPayments, true)
            ? (string)$order['payment_status'] : 'unpaid';
        return [
            'order_number' => (string)($order['order_number'] ?? ''),
            'order_status' => $status,
            'status_label' => self::statusLabel($status),
            'payment_status' => $payment,
            'payment_label' => self::paymentLabel($payment),
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'paid_amount' => (float)($order['paid_amount'] ?? 0),
            'created_at' => (string)($order['created_at'] ?? ''),
            'updated_at' => (string)($order['updated_at'] ?? ''),
            'is_terminal' => in_array($status, ['served', 'completed', 'cancelled'], true)
        ];
    }

    public static function statusLabel(string $status): string
    {
        return [
            'pending' => 'Order received',
            'preparing' => 'Preparing your order',
            'ready' => 'Ready for pickup / service',
            'served' => 'Served',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ][$status] ?? 'Order received';
    }

    public static function paymentLabel(string $status): string
    {
        return [
            'unpaid' => 'Unpaid — pay at the cashier',
            'partially_paid' => 'Partially paid',
            'paid' => 'Paid',
            'refunded' => 'Refunded'
        ][$status] ?? 'Payment status unavailable';
    }
}
