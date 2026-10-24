<?php
/**
 * Restaurant POS & Management SaaS
 * Per-table public QR ordering links.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;
use InvalidArgumentException;

class TableQrService
{
    private static ?bool $readyCache = null;

    public static function schemaReady(): bool
    {
        if (self::$readyCache !== null) return self::$readyCache;
        try {
            $pdo = Database::getConnection();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $columns = Database::fetchAll("PRAGMA table_info('restaurant_tables')");
                self::$readyCache = in_array('qr_token', array_column($columns, 'name'), true);
            } else {
                $row = Database::fetchOne(
                    "SELECT COUNT(*) AS total FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'restaurant_tables' AND column_name = 'qr_token'"
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
     * Safe, additive migration. Existing tables keep their IDs and settings.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getConnection();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $columns = Database::fetchAll("PRAGMA table_info('restaurant_tables')");
            if (!in_array('qr_token', array_column($columns, 'name'), true)) {
                $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN qr_token TEXT NULL");
            }
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_restaurant_tables_qr_token ON restaurant_tables(qr_token)");
            $pdo->exec("UPDATE restaurant_tables SET qr_token = lower(hex(randomblob(32))) WHERE qr_token IS NULL OR qr_token = ''");
        } else {
            $column = Database::fetchOne(
                "SELECT COUNT(*) AS total FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'restaurant_tables' AND column_name = 'qr_token'"
            );
            if ((int)($column['total'] ?? 0) === 0) {
                $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN qr_token CHAR(64) NULL AFTER table_number");
            }

            $index = Database::fetchOne(
                "SELECT COUNT(*) AS total FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'restaurant_tables' AND index_name = 'uq_restaurant_tables_qr_token'"
            );
            if ((int)($index['total'] ?? 0) === 0) {
                $pdo->exec("CREATE UNIQUE INDEX uq_restaurant_tables_qr_token ON restaurant_tables(qr_token)");
            }

            $pdo->exec(
                "UPDATE restaurant_tables
                 SET qr_token = LOWER(SHA2(CONCAT(UUID(), RAND(), restaurant_id, id), 256))
                 WHERE qr_token IS NULL OR qr_token = ''"
            );
        }

        self::$readyCache = null;
        if (!self::schemaReady()) throw new \RuntimeException('The table QR token column could not be prepared.');
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function getTableForManagement(int $restaurantId, int $tableId): ?array
    {
        if (!self::schemaReady()) return null;
        $table = Database::fetchOne(
            "SELECT id, restaurant_id, table_number, name, capacity, floor_zone, status, is_active, deleted_at, qr_token
             FROM restaurant_tables
             WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL AND is_active = 1
             LIMIT 1",
            [$restaurantId, $tableId]
        );
        if (!$table) return null;
        if (empty($table['qr_token'])) {
            $token = self::generateToken();
            Database::query(
                "UPDATE restaurant_tables SET qr_token = ?
                 WHERE restaurant_id = ? AND id = ? AND (qr_token IS NULL OR qr_token = '')",
                [$token, $restaurantId, $tableId]
            );
            $table = Database::fetchOne(
                "SELECT id, restaurant_id, table_number, name, capacity, floor_zone, status, is_active, deleted_at, qr_token
                 FROM restaurant_tables WHERE restaurant_id = ? AND id = ? LIMIT 1",
                [$restaurantId, $tableId]
            );
        }
        return $table;
    }

    public static function getTableByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token) || !self::schemaReady()) return null;
        return Database::fetchOne(
            "SELECT t.id AS table_id, t.restaurant_id, t.table_number, t.name AS table_name,
                    t.capacity, t.floor_zone, t.status AS table_status,
                    r.name AS restaurant_name, r.logo_path, r.receipt_header, r.receipt_footer
             FROM restaurant_tables t
             JOIN restaurants r ON r.id = t.restaurant_id
             WHERE t.qr_token = ? AND t.is_active = 1 AND t.deleted_at IS NULL
               AND r.status = 'active' AND r.deleted_at IS NULL
             LIMIT 1",
            [$token]
        );
    }
}
