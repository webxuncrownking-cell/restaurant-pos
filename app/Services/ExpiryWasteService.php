<?php
/**
 * Restaurant POS & Management SaaS
 * Expiry batches and wastage accounting.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

class ExpiryWasteService
{
    private static array $schemaCache = [];

    public static function schemaReady(): bool
    {
        $restaurantCheckKey = 'ready';
        if (array_key_exists($restaurantCheckKey, self::$schemaCache)) return self::$schemaCache[$restaurantCheckKey];
        try {
            $pdo = Database::getConnection();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $rows = Database::fetchAll(
                    "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('ingredient_batches','ingredient_waste_records')"
                );
            } else {
                $rows = Database::fetchAll(
                    "SELECT table_name FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name IN ('ingredient_batches','ingredient_waste_records')"
                );
            }
            self::$schemaCache[$restaurantCheckKey] = count($rows) === 2;
            return self::$schemaCache[$restaurantCheckKey];
        } catch (Throwable $e) {
            self::$schemaCache[$restaurantCheckKey] = false;
            return false;
        }
    }

    /**
     * Additive schema update. Existing inventory and order rows are never rewritten.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getConnection();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                ingredient_id INTEGER NOT NULL,
                purchase_order_id INTEGER NOT NULL,
                purchase_order_item_id INTEGER NOT NULL,
                batch_code TEXT NULL,
                received_quantity NUMERIC NOT NULL,
                remaining_quantity NUMERIC NOT NULL,
                cost_per_base_unit NUMERIC NOT NULL DEFAULT 0,
                expiry_date TEXT NULL,
                received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER NOT NULL,
                UNIQUE (restaurant_id, purchase_order_item_id)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_waste_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                batch_id INTEGER NOT NULL,
                ingredient_id INTEGER NOT NULL,
                quantity_base NUMERIC NOT NULL,
                unit_cost NUMERIC NOT NULL,
                total_cost NUMERIC NOT NULL,
                reason TEXT NOT NULL,
                notes TEXT NULL,
                recorded_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_batches (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                restaurant_id INT UNSIGNED NOT NULL,
                ingredient_id INT UNSIGNED NOT NULL,
                purchase_order_id INT UNSIGNED NOT NULL,
                purchase_order_item_id INT UNSIGNED NOT NULL,
                batch_code VARCHAR(100) NULL,
                received_quantity DECIMAL(16,4) NOT NULL,
                remaining_quantity DECIMAL(16,4) NOT NULL,
                cost_per_base_unit DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
                expiry_date DATE NULL,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ingredient_batch_tenant_id (restaurant_id, id),
                UNIQUE KEY uk_ingredient_batch_po_line (restaurant_id, purchase_order_item_id),
                KEY idx_ingredient_batch_expiry (restaurant_id, expiry_date, remaining_quantity),
                KEY idx_ingredient_batch_stock (restaurant_id, ingredient_id, remaining_quantity),
                CONSTRAINT fk_ingredient_batch_ingredient FOREIGN KEY (restaurant_id, ingredient_id) REFERENCES ingredients (restaurant_id, id) ON DELETE RESTRICT,
                CONSTRAINT fk_ingredient_batch_purchase_order FOREIGN KEY (restaurant_id, purchase_order_id) REFERENCES purchase_orders (restaurant_id, id) ON DELETE RESTRICT,
                CONSTRAINT fk_ingredient_batch_creator FOREIGN KEY (restaurant_id, created_by) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_waste_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                restaurant_id INT UNSIGNED NOT NULL,
                batch_id BIGINT UNSIGNED NOT NULL,
                ingredient_id INT UNSIGNED NOT NULL,
                quantity_base DECIMAL(16,4) NOT NULL,
                unit_cost DECIMAL(14,6) NOT NULL,
                total_cost DECIMAL(12,2) NOT NULL,
                reason ENUM('expired','spoiled','damaged','contaminated','other') NOT NULL,
                notes VARCHAR(255) NULL,
                recorded_by INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ingredient_waste_recent (restaurant_id, created_at),
                KEY idx_ingredient_waste_batch (restaurant_id, batch_id),
                CONSTRAINT fk_ingredient_waste_batch FOREIGN KEY (restaurant_id, batch_id) REFERENCES ingredient_batches (restaurant_id, id) ON DELETE RESTRICT,
                CONSTRAINT fk_ingredient_waste_ingredient FOREIGN KEY (restaurant_id, ingredient_id) REFERENCES ingredients (restaurant_id, id) ON DELETE RESTRICT,
                CONSTRAINT fk_ingredient_waste_user FOREIGN KEY (restaurant_id, recorded_by) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        self::$schemaCache = [];
    }

    public static function createReceiptBatch(
        int $restaurantId,
        int $ingredientId,
        int $purchaseOrderId,
        int $purchaseOrderItemId,
        float $quantityBase,
        float $costPerBaseUnit,
        int $createdBy,
        ?string $batchCode,
        ?string $expiryDate
    ): int {
        if ($restaurantId <= 0 || $ingredientId <= 0 || $purchaseOrderId <= 0 || $purchaseOrderItemId <= 0 || $createdBy <= 0
            || !is_finite($quantityBase) || $quantityBase <= 0
            || !is_finite($costPerBaseUnit) || $costPerBaseUnit < 0) {
            throw new InvalidArgumentException('The received ingredient batch details are invalid.');
        }
        $batchCode = trim((string)$batchCode);
        if (mb_strlen($batchCode) > 100) throw new InvalidArgumentException('Batch or lot codes must be 100 characters or fewer.');
        $expiryDate = self::validatedExpiryDate($expiryDate);

        Database::query(
            "INSERT INTO ingredient_batches
                (restaurant_id, ingredient_id, purchase_order_id, purchase_order_item_id, batch_code,
                 received_quantity, remaining_quantity, cost_per_base_unit, expiry_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $restaurantId, $ingredientId, $purchaseOrderId, $purchaseOrderItemId,
                $batchCode !== '' ? $batchCode : null, round($quantityBase, 4), round($quantityBase, 4),
                round($costPerBaseUnit, 6), $expiryDate, $createdBy
            ]
        );
        return (int)Database::lastInsertId();
    }

    private static function validatedExpiryDate(?string $expiryDate): ?string
    {
        $expiryDate = trim((string)$expiryDate);
        if ($expiryDate === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiryDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $expiryDate) {
            throw new InvalidArgumentException('Enter expiry dates in YYYY-MM-DD format.');
        }
        return $expiryDate;
    }

    public static function getBatches(int $restaurantId, int $alertDays = 30): array
    {
        $alertDays = max(1, min(365, $alertDays));
        $today = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime('+' . $alertDays . ' days'));
        return Database::fetchAll(
            "SELECT b.*, i.name AS ingredient_name, i.sku, i.base_unit,
                    po.po_number, s.name AS supplier_name,
                    CASE
                      WHEN b.expiry_date IS NULL THEN 'no_expiry'
                      WHEN b.expiry_date < ? THEN 'expired'
                      WHEN b.expiry_date <= ? THEN 'expiring'
                      ELSE 'safe'
                    END AS expiry_status,
                    CASE WHEN b.expiry_date IS NULL THEN NULL ELSE DATEDIFF(b.expiry_date, ?) END AS days_until_expiry,
                    ROUND(b.remaining_quantity * b.cost_per_base_unit, 2) AS remaining_value
             FROM ingredient_batches b
             JOIN ingredients i ON i.restaurant_id = b.restaurant_id AND i.id = b.ingredient_id
             LEFT JOIN purchase_orders po ON po.restaurant_id = b.restaurant_id AND po.id = b.purchase_order_id
             LEFT JOIN suppliers s ON s.restaurant_id = po.restaurant_id AND s.id = po.supplier_id
             WHERE b.restaurant_id = ? AND b.remaining_quantity > 0
             ORDER BY CASE WHEN b.expiry_date IS NULL THEN 1 ELSE 0 END, b.expiry_date ASC, b.received_at ASC, b.id ASC",
            [$today, $cutoff, $today, $restaurantId]
        );
    }

    public static function getRecentWaste(int $restaurantId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT w.*, i.name AS ingredient_name, i.base_unit, b.batch_code, b.expiry_date,
                    u.full_name AS recorded_by_name
             FROM ingredient_waste_records w
             JOIN ingredients i ON i.restaurant_id = w.restaurant_id AND i.id = w.ingredient_id
             JOIN ingredient_batches b ON b.restaurant_id = w.restaurant_id AND b.id = w.batch_id
             JOIN users u ON u.restaurant_id = w.restaurant_id AND u.id = w.recorded_by
             WHERE w.restaurant_id = ?
             ORDER BY w.created_at DESC, w.id DESC LIMIT ?",
            [$restaurantId, $limit]
        );
    }

    public static function getSummary(int $restaurantId, int $alertDays = 30): array
    {
        $today = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime('+' . max(1, min(365, $alertDays)) . ' days'));
        $summary = Database::fetchOne(
            "SELECT
               COALESCE(SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END), 0) AS expired_batches,
               COALESCE(SUM(CASE WHEN expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END), 0) AS expiring_batches,
               COALESCE(SUM(CASE WHEN expiry_date < ? THEN remaining_quantity * cost_per_base_unit ELSE 0 END), 0) AS expired_value,
               COALESCE(SUM(CASE WHEN expiry_date >= ? AND expiry_date <= ? THEN remaining_quantity * cost_per_base_unit ELSE 0 END), 0) AS expiring_value
             FROM ingredient_batches WHERE restaurant_id = ? AND remaining_quantity > 0 AND expiry_date IS NOT NULL",
            [$today, $today, $cutoff, $today, $today, $cutoff, $restaurantId]
        );
        $waste = Database::fetchOne(
            "SELECT COUNT(*) AS waste_count, COALESCE(SUM(total_cost),0) AS waste_cost
             FROM ingredient_waste_records WHERE restaurant_id = ? AND created_at >= ?",
            [$restaurantId, date('Y-m-01 00:00:00')]
        );
        return [
            'expired_batches' => (int)($summary['expired_batches'] ?? 0),
            'expiring_batches' => (int)($summary['expiring_batches'] ?? 0),
            'expired_value' => round((float)($summary['expired_value'] ?? 0), 2),
            'expiring_value' => round((float)($summary['expiring_value'] ?? 0), 2),
            'month_waste_count' => (int)($waste['waste_count'] ?? 0),
            'month_waste_cost' => round((float)($waste['waste_cost'] ?? 0), 2)
        ];
    }

    public static function recordWaste(
        int $restaurantId,
        int $batchId,
        int $userId,
        float $quantity,
        string $reason,
        ?string $notes = null
    ): array {
        $allowedReasons = ['expired','spoiled','damaged','contaminated','other'];
        if ($restaurantId <= 0 || $batchId <= 0 || $userId <= 0 || !is_finite($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException('Select a valid batch and enter a positive waste quantity.');
        }
        if (!in_array($reason, $allowedReasons, true)) throw new InvalidArgumentException('Choose a valid reason for the waste.');
        $notes = trim((string)$notes);
        if (mb_strlen($notes) > 255) throw new InvalidArgumentException('Waste notes must be 255 characters or fewer.');

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT b.id, b.ingredient_id, b.batch_code, b.expiry_date, b.remaining_quantity,
                        b.cost_per_base_unit, i.name AS ingredient_name, i.base_unit, i.current_stock
                 FROM ingredient_batches b
                 JOIN ingredients i ON i.restaurant_id = b.restaurant_id AND i.id = b.ingredient_id
                 WHERE b.restaurant_id = ? AND b.id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$batch) throw new InvalidArgumentException('Ingredient batch not found for this restaurant.');

            $quantity = round($quantity, 4);
            $remaining = round((float)$batch['remaining_quantity'], 4);
            $currentStock = round((float)$batch['current_stock'], 4);
            if ($quantity - $remaining > 0.00009) throw new InvalidArgumentException('Waste quantity exceeds the remaining quantity in this batch.');
            if ($quantity - $currentStock > 0.00009) throw new InvalidArgumentException('Waste quantity exceeds the current ingredient stock.');

            InventoryService::recordMovement(
                $restaurantId,
                (int)$batch['ingredient_id'],
                'spoilage_waste',
                $quantity,
                (string)$batch['base_unit'],
                null,
                $batchId,
                'Batch ' . ($batch['batch_code'] ?: ('#' . $batchId)) . ': ' . $reason . ($notes !== '' ? ' - ' . $notes : ''),
                $userId
            );

            Database::query(
                "UPDATE ingredient_batches SET remaining_quantity = remaining_quantity - ?
                 WHERE restaurant_id = ? AND id = ? AND remaining_quantity >= ?",
                [$quantity, $restaurantId, $batchId, $quantity]
            );
            $unitCost = round((float)$batch['cost_per_base_unit'], 6);
            $totalCost = round($quantity * $unitCost, 2);
            Database::query(
                "INSERT INTO ingredient_waste_records
                    (restaurant_id, batch_id, ingredient_id, quantity_base, unit_cost, total_cost, reason, notes, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$restaurantId, $batchId, (int)$batch['ingredient_id'], $quantity, $unitCost, $totalCost, $reason, $notes !== '' ? $notes : null, $userId]
            );
            $wasteId = (int)Database::lastInsertId();
            $pdo->commit();
            return [
                'waste_id' => $wasteId,
                'ingredient_name' => (string)$batch['ingredient_name'],
                'quantity_base' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'remaining_quantity' => max(0, round($remaining - $quantity, 4))
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Consume the tracked part of a stock-sale movement, earliest expiry first.
     * Legacy/unbatched stock remains consumable; only lot balances are adjusted.
     */
    public static function consumeTrackedBatchesForSale(
        int $restaurantId,
        int $ingredientId,
        float $quantityBase,
        float $stockBefore
    ): void {
        if ($quantityBase <= 0 || !self::schemaReady()) return;
        $pdo = Database::getConnection();
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $pdo->prepare(
            "SELECT id, remaining_quantity, expiry_date FROM ingredient_batches
             WHERE restaurant_id = ? AND ingredient_id = ? AND remaining_quantity > 0
             ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC, received_at ASC, id ASC" . $suffix
        );
        $stmt->execute([$restaurantId, $ingredientId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalTracked = 0.0;
        $eligible = [];
        $today = date('Y-m-d');
        foreach ($batches as $batch) {
            $available = max(0.0, round((float)$batch['remaining_quantity'], 4));
            $totalTracked += $available;
            // Never issue stock from an expired lot into a new order.
            if (empty($batch['expiry_date']) || (string)$batch['expiry_date'] >= $today) {
                $eligible[] = $batch;
            }
        }

        // Stock predating lot tracking (or added by a manual adjustment) remains
        // usable as unbatched stock. Once that cushion is exhausted, sales must
        // not consume expired lots; record them as wastage first.
        $untrackedAvailable = max(0.0, round($stockBefore - $totalTracked, 4));
        $remainingToConsume = round($quantityBase, 4);
        foreach ($eligible as $batch) {
            if ($remainingToConsume <= 0.00009) break;
            $available = max(0.0, round((float)$batch['remaining_quantity'], 4));
            $deduct = min($available, $remainingToConsume);
            if ($deduct <= 0) continue;
            Database::query(
                "UPDATE ingredient_batches SET remaining_quantity = remaining_quantity - ?
                 WHERE restaurant_id = ? AND id = ? AND remaining_quantity >= ?",
                [$deduct, $restaurantId, (int)$batch['id'], $deduct]
            );
            $remainingToConsume = round($remainingToConsume - $deduct, 4);
        }

        if ($remainingToConsume - $untrackedAvailable > 0.00009) {
            throw new InvalidArgumentException(
                'This ingredient only has expired tracked batches remaining. Record the expired stock as wastage before selling more.'
            );
        }
    }
}
