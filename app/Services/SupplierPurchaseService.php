<?php
/**
 * Restaurant POS & Management SaaS
 * Tenant-safe supplier and purchase order workflows.
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class SupplierPurchaseService
{
    public static function schemaReady(): bool
    {
        try {
            $pdo = Database::getInstance();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $rows = Database::fetchAll(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('suppliers','purchase_orders','purchase_order_items')"
                );
            } else {
                $rows = Database::fetchAll(
                    "SELECT table_name FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name IN ('suppliers','purchase_orders','purchase_order_items')"
                );
            }
            return count($rows) === 3;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Add missing purchasing tables only. This does not drop or rewrite stock or sales data.
     */
    public static function ensureSchema(): void
    {
        $pdo = Database::getInstance();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                contact_person TEXT NULL,
                phone TEXT NOT NULL,
                email TEXT NULL,
                address TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                supplier_id INTEGER NOT NULL,
                po_number TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','ordered','received','cancelled')),
                total_amount NUMERIC NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_by INTEGER NOT NULL,
                received_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (restaurant_id, po_number)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                restaurant_id INTEGER NOT NULL,
                purchase_order_id INTEGER NOT NULL,
                ingredient_id INTEGER NOT NULL,
                quantity_purchased NUMERIC NOT NULL,
                purchase_unit TEXT NOT NULL,
                multiplier_to_base NUMERIC NOT NULL,
                quantity_base NUMERIC NOT NULL,
                unit_cost NUMERIC NOT NULL,
                subtotal NUMERIC NOT NULL
            )");
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            contact_person VARCHAR(100) NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(100) NULL,
            address TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            deleted_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_sup_tenant_id (restaurant_id, id),
            KEY idx_supplier_active (restaurant_id, is_active, name),
            CONSTRAINT fk_supplier_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            supplier_id INT UNSIGNED NOT NULL,
            po_number VARCHAR(50) NOT NULL,
            status ENUM('draft', 'ordered', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            created_by INT UNSIGNED NOT NULL,
            received_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_po_tenant_id (restaurant_id, id),
            UNIQUE KEY uk_po_tenant_number (restaurant_id, po_number),
            KEY idx_po_tenant_status (restaurant_id, status, created_at),
            CONSTRAINT fk_po_supplier FOREIGN KEY (restaurant_id, supplier_id) REFERENCES suppliers (restaurant_id, id) ON DELETE RESTRICT,
            CONSTRAINT fk_po_creator FOREIGN KEY (restaurant_id, created_by) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            restaurant_id INT UNSIGNED NOT NULL,
            purchase_order_id INT UNSIGNED NOT NULL,
            ingredient_id INT UNSIGNED NOT NULL,
            quantity_purchased DECIMAL(12,4) NOT NULL,
            purchase_unit VARCHAR(50) NOT NULL,
            multiplier_to_base DECIMAL(14,6) NOT NULL,
            quantity_base DECIMAL(16,4) NOT NULL,
            unit_cost DECIMAL(12,4) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_po_item_tenant_order (restaurant_id, purchase_order_id),
            CONSTRAINT fk_po_item_order FOREIGN KEY (restaurant_id, purchase_order_id) REFERENCES purchase_orders (restaurant_id, id) ON DELETE CASCADE,
            CONSTRAINT fk_po_item_ingredient FOREIGN KEY (restaurant_id, ingredient_id) REFERENCES ingredients (restaurant_id, id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function getSuppliers(int $restaurantId, bool $includeInactive = true): array
    {
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM purchase_orders po
                        WHERE po.restaurant_id = s.restaurant_id AND po.supplier_id = s.id) AS purchase_order_count
                FROM suppliers s WHERE s.restaurant_id = ? AND s.deleted_at IS NULL";
        $params = [$restaurantId];
        if (!$includeInactive) $sql .= " AND s.is_active = 1";
        $sql .= " ORDER BY s.is_active DESC, s.name ASC";
        return Database::fetchAll($sql, $params);
    }

    public static function createSupplier(int $restaurantId, array $input): int
    {
        $name = trim((string)($input['name'] ?? ''));
        $contact = trim((string)($input['contact_person'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));

        if ($restaurantId <= 0) throw new InvalidArgumentException('Choose a restaurant workspace.');
        if ($name === '' || mb_strlen($name) > 150) throw new InvalidArgumentException('Supplier name is required and must be 150 characters or fewer.');
        if ($phone === '' || mb_strlen($phone) > 30) throw new InvalidArgumentException('Supplier phone is required and must be 30 characters or fewer.');
        if (mb_strlen($contact) > 100 || mb_strlen($email) > 100 || mb_strlen($address) > 5000) {
            throw new InvalidArgumentException('Supplier contact details exceed the allowed length.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid supplier email address.');
        }

        Database::query(
            "INSERT INTO suppliers (restaurant_id, name, contact_person, phone, email, address, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)",
            [$restaurantId, $name, $contact !== '' ? $contact : null, $phone, $email !== '' ? $email : null, $address !== '' ? $address : null]
        );
        return (int)Database::lastInsertId();
    }

    public static function setSupplierActive(int $restaurantId, int $supplierId, bool $active): void
    {
        $result = Database::query(
            "UPDATE suppliers SET is_active = ? WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL",
            [$active ? 1 : 0, $restaurantId, $supplierId]
        );
        if ($result->rowCount() !== 1) throw new InvalidArgumentException('Supplier not found for this restaurant.');
    }

    public static function getIngredientsForPurchase(int $restaurantId): array
    {
        $ingredients = Database::fetchAll(
            "SELECT id, name, sku, base_unit, current_stock, cost_per_base_unit
             FROM ingredients WHERE restaurant_id = ? AND is_active = 1 ORDER BY name ASC",
            [$restaurantId]
        );
        $units = Database::fetchAll(
            "SELECT ingredient_id, unit_name, multiplier_to_base
             FROM ingredient_unit_conversions
             WHERE restaurant_id = ? AND is_purchase_unit = 1
             ORDER BY unit_name ASC",
            [$restaurantId]
        );
        $unitsByIngredient = [];
        foreach ($ingredients as $ingredient) {
            $id = (int)$ingredient['id'];
            $unitsByIngredient[$id] = [[
                'name' => (string)$ingredient['base_unit'],
                'factor' => 1.0
            ]];
        }
        foreach ($units as $unit) {
            $id = (int)$unit['ingredient_id'];
            if (!isset($unitsByIngredient[$id])) continue;
            $name = trim((string)$unit['unit_name']);
            $factor = (float)$unit['multiplier_to_base'];
            if ($name === '' || !is_finite($factor) || $factor <= 0) continue;
            $alreadyExists = false;
            foreach ($unitsByIngredient[$id] as $knownUnit) {
                if (strcasecmp($knownUnit['name'], $name) === 0) $alreadyExists = true;
            }
            if (!$alreadyExists) $unitsByIngredient[$id][] = ['name' => $name, 'factor' => $factor];
        }
        foreach ($ingredients as &$ingredient) {
            $ingredient['purchase_units'] = $unitsByIngredient[(int)$ingredient['id']] ?? [];
        }
        unset($ingredient);
        return $ingredients;
    }

    /**
     * Create a draft PO from validated unit-conversion snapshots.
     * $items must contain ingredient_id, quantity, purchase_unit, and unit_cost.
     */
    public static function createPurchaseOrder(int $restaurantId, int $userId, int $supplierId, array $items, string $notes = ''): int
    {
        if ($restaurantId <= 0 || $userId <= 0 || $supplierId <= 0) throw new InvalidArgumentException('A valid workspace, staff account, and supplier are required.');
        if (count($items) < 1 || count($items) > 100) throw new InvalidArgumentException('A purchase order must contain 1 to 100 ingredient lines.');
        if (mb_strlen($notes) > 2000) throw new InvalidArgumentException('Purchase order notes must be 2,000 characters or fewer.');

        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $supplier = $pdo->prepare(
                "SELECT id, name FROM suppliers
                 WHERE restaurant_id = ? AND id = ? AND is_active = 1 AND deleted_at IS NULL" . $suffix
            );
            $supplier->execute([$restaurantId, $supplierId]);
            if (!$supplier->fetch(PDO::FETCH_ASSOC)) throw new InvalidArgumentException('Select an active supplier from this restaurant.');

            $normalized = [];
            $total = 0.0;
            foreach ($items as $item) {
                $ingredientId = filter_var($item['ingredient_id'] ?? null, FILTER_VALIDATE_INT);
                $quantityRaw = $item['quantity'] ?? null;
                $purchaseUnit = trim((string)($item['purchase_unit'] ?? ''));
                $unitCostRaw = $item['unit_cost'] ?? null;
                if (!$ingredientId || $ingredientId <= 0 || !is_numeric($quantityRaw) || !is_finite((float)$quantityRaw)
                    || (float)$quantityRaw <= 0 || (float)$quantityRaw > 1000000
                    || $purchaseUnit === '' || mb_strlen($purchaseUnit) > 50
                    || !is_numeric($unitCostRaw) || !is_finite((float)$unitCostRaw)
                    || (float)$unitCostRaw < 0 || (float)$unitCostRaw > 100000000) {
                    throw new InvalidArgumentException('Each PO line needs a valid ingredient, purchase unit, positive quantity, and non-negative unit cost.');
                }

                $ingredient = Database::fetchOne(
                    "SELECT id, name, base_unit FROM ingredients
                     WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                    [$restaurantId, (int)$ingredientId]
                );
                if (!$ingredient) throw new InvalidArgumentException('A selected ingredient is no longer active in this restaurant.');

                if (strcasecmp($purchaseUnit, (string)$ingredient['base_unit']) === 0) {
                    $factor = 1.0;
                } else {
                    $conversion = Database::fetchOne(
                        "SELECT multiplier_to_base FROM ingredient_unit_conversions
                         WHERE restaurant_id = ? AND ingredient_id = ? AND is_purchase_unit = 1 AND LOWER(unit_name) = LOWER(?)",
                        [$restaurantId, (int)$ingredientId, $purchaseUnit]
                    );
                    $factor = (float)($conversion['multiplier_to_base'] ?? 0);
                }
                if (!is_finite($factor) || $factor <= 0) {
                    throw new InvalidArgumentException("Purchase unit '{$purchaseUnit}' is not configured for the selected ingredient.");
                }

                $qty = round((float)$quantityRaw, 4);
                $unitCost = round((float)$unitCostRaw, 4);
                $qtyBase = round($qty * $factor, 4);
                $subtotal = round($qty * $unitCost, 2);
                if ($qtyBase <= 0 || !is_finite($qtyBase) || !is_finite($subtotal)) {
                    throw new InvalidArgumentException('The normalized purchase quantity or subtotal is invalid.');
                }
                $normalized[] = [
                    'ingredient_id' => (int)$ingredientId,
                    'ingredient_name' => (string)$ingredient['name'],
                    'quantity' => $qty,
                    'purchase_unit' => $purchaseUnit,
                    'factor' => $factor,
                    'quantity_base' => $qtyBase,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal
                ];
                $total += $subtotal;
            }

            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            Database::query(
                "INSERT INTO purchase_orders (restaurant_id, supplier_id, po_number, status, total_amount, notes, created_by)
                 VALUES (?, ?, ?, 'draft', ?, ?, ?)",
                [$restaurantId, $supplierId, $poNumber, round($total, 2), $notes !== '' ? $notes : null, $userId]
            );
            $poId = (int)Database::lastInsertId();

            foreach ($normalized as $line) {
                Database::query(
                    "INSERT INTO purchase_order_items
                        (restaurant_id, purchase_order_id, ingredient_id, quantity_purchased, purchase_unit,
                         multiplier_to_base, quantity_base, unit_cost, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $restaurantId, $poId, $line['ingredient_id'], $line['quantity'], $line['purchase_unit'],
                        $line['factor'], $line['quantity_base'], $line['unit_cost'], $line['subtotal']
                    ]
                );
            }
            $pdo->commit();
            return $poId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function getPurchaseOrders(int $restaurantId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $orders = Database::fetchAll(
            "SELECT po.*, s.name AS supplier_name, u.full_name AS created_by_name
             FROM purchase_orders po
             JOIN suppliers s ON s.restaurant_id = po.restaurant_id AND s.id = po.supplier_id
             LEFT JOIN users u ON u.restaurant_id = po.restaurant_id AND u.id = po.created_by
             WHERE po.restaurant_id = ?
             ORDER BY po.created_at DESC, po.id DESC LIMIT ?",
            [$restaurantId, $limit]
        );
        foreach ($orders as &$order) {
            $order['items'] = Database::fetchAll(
                "SELECT poi.*, i.name AS ingredient_name, i.base_unit
                 FROM purchase_order_items poi
                 JOIN ingredients i ON i.restaurant_id = poi.restaurant_id AND i.id = poi.ingredient_id
                 WHERE poi.restaurant_id = ? AND poi.purchase_order_id = ?
                 ORDER BY poi.id ASC",
                [$restaurantId, (int)$order['id']]
            );
        }
        unset($order);
        return $orders;
    }

    public static function updateOrderStatus(int $restaurantId, int $orderId, string $targetStatus): void
    {
        if (!in_array($targetStatus, ['ordered', 'cancelled'], true)) {
            throw new InvalidArgumentException('Choose a valid purchase order action.');
        }
        $pdo = Database::getConnection();
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT id, status FROM purchase_orders WHERE restaurant_id = ? AND id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new InvalidArgumentException('Purchase order not found for this restaurant.');

            $current = (string)$order['status'];
            if ($targetStatus === 'ordered' && $current !== 'draft') {
                throw new InvalidArgumentException('Only draft purchase orders can be marked ordered.');
            }
            if ($targetStatus === 'cancelled' && !in_array($current, ['draft', 'ordered'], true)) {
                throw new InvalidArgumentException('Only draft or ordered purchase orders can be cancelled.');
            }

            Database::query(
                "UPDATE purchase_orders SET status = ? WHERE restaurant_id = ? AND id = ? AND status = ?",
                [$targetStatus, $restaurantId, $orderId, $current]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Receive the whole order exactly once. Stock changes and status transition share one DB transaction.
     */
    public static function receivePurchaseOrder(int $restaurantId, int $orderId, int $userId): void
    {
        if ($restaurantId <= 0 || $orderId <= 0 || $userId <= 0) throw new InvalidArgumentException('A valid workspace, purchase order, and staff account are required.');
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $pdo->prepare(
                "SELECT id, po_number, status FROM purchase_orders
                 WHERE restaurant_id = ? AND id = ?" . $suffix
            );
            $stmt->execute([$restaurantId, $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new InvalidArgumentException('Purchase order not found for this restaurant.');
            if ((string)$order['status'] !== 'ordered') {
                throw new InvalidArgumentException('Only ordered purchase orders can be received. Received orders cannot be received twice.');
            }

            $lines = Database::fetchAll(
                "SELECT poi.*, i.name AS ingredient_name, i.base_unit
                 FROM purchase_order_items poi
                 JOIN ingredients i ON i.restaurant_id = poi.restaurant_id AND i.id = poi.ingredient_id
                 WHERE poi.restaurant_id = ? AND poi.purchase_order_id = ?
                 ORDER BY poi.id ASC",
                [$restaurantId, $orderId]
            );
            if (!$lines) throw new InvalidArgumentException('This purchase order has no ingredient lines.');

            foreach ($lines as $line) {
                $qtyBase = (float)$line['quantity_base'];
                $baseCost = $qtyBase > 0 ? (float)$line['subtotal'] / $qtyBase : 0.0;
                InventoryService::recordMovement(
                    $restaurantId,
                    (int)$line['ingredient_id'],
                    'purchase_receipt',
                    $qtyBase,
                    (string)$line['base_unit'],
                    $baseCost,
                    $orderId,
                    'PO ' . $order['po_number'] . ': ' . $line['quantity_purchased'] . ' ' . $line['purchase_unit'] . ' received',
                    $userId
                );
            }

            Database::query(
                "UPDATE purchase_orders SET status = 'received', received_at = CURRENT_TIMESTAMP
                 WHERE restaurant_id = ? AND id = ? AND status = 'ordered'",
                [$restaurantId, $orderId]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
