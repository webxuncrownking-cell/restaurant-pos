<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use Throwable;

final class ComboService
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $pdo = Database::getConnection();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_combo_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1, created_by INTEGER NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(restaurant_id, product_id)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_combo_components (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL, combo_product_id INTEGER NOT NULL,
                component_product_id INTEGER NOT NULL, quantity REAL NOT NULL DEFAULT 1,
                UNIQUE(restaurant_id, combo_product_id, component_product_id)
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_combo_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, restaurant_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_restaurant_combo_product (restaurant_id, product_id),
                KEY idx_combo_active (restaurant_id, is_active),
                CONSTRAINT fk_combo_product_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_combo_components (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, restaurant_id INT UNSIGNED NOT NULL,
                combo_product_id INT UNSIGNED NOT NULL, component_product_id INT UNSIGNED NOT NULL,
                quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_combo_component (restaurant_id, combo_product_id, component_product_id),
                KEY idx_combo_component_product (restaurant_id, component_product_id),
                CONSTRAINT fk_combo_component_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        self::$schemaReady = true;
    }

    public static function getComponentsForProduct(int $restaurantId, int $productId): array
    {
        self::ensureSchema();
        return Database::fetchAll(
            "SELECT cc.component_product_id AS id, cc.quantity, p.name, p.product_type, p.direct_stock_ingredient_id, p.base_price, p.cost_price
             FROM restaurant_combo_components cc
             JOIN restaurant_combo_products cp ON cp.restaurant_id = cc.restaurant_id AND cp.product_id = cc.combo_product_id AND cp.is_active = 1
             JOIN products p ON p.restaurant_id = cc.restaurant_id AND p.id = cc.component_product_id AND p.is_active = 1
             WHERE cc.restaurant_id = ? AND cc.combo_product_id = ? ORDER BY p.name",
            [$restaurantId, $productId]
        );
    }

    public static function getComponentsMap(int $restaurantId): array
    {
        self::ensureSchema();
        $rows = Database::fetchAll(
            "SELECT cc.combo_product_id, cc.component_product_id AS id, cc.quantity, p.name, p.product_type, p.direct_stock_ingredient_id
             FROM restaurant_combo_components cc
             JOIN restaurant_combo_products cp ON cp.restaurant_id = cc.restaurant_id AND cp.product_id = cc.combo_product_id AND cp.is_active = 1
             JOIN products p ON p.restaurant_id = cc.restaurant_id AND p.id = cc.component_product_id AND p.is_active = 1
             WHERE cc.restaurant_id = ? ORDER BY cc.combo_product_id, p.name",
            [$restaurantId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['combo_product_id']][] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'quantity' => (float)$row['quantity'],
                'product_type' => (string)$row['product_type'],
                'direct_stock_ingredient_id' => !empty($row['direct_stock_ingredient_id']) ? (int)$row['direct_stock_ingredient_id'] : null
            ];
        }
        return $map;
    }

    public static function save(int $restaurantId, int $userId, int $comboProductId, array $componentIds, array $quantities): void
    {
        self::ensureSchema();
        if ($restaurantId <= 0 || $userId <= 0 || $comboProductId <= 0) {
            throw new InvalidArgumentException('Choose a valid combo menu item.');
        }
        $combo = Database::fetchOne("SELECT id FROM products WHERE restaurant_id = ? AND id = ? AND is_active = 1", [$restaurantId, $comboProductId]);
        if (!$combo) throw new InvalidArgumentException('The combo must use an active menu item in this restaurant.');
        $usedAsComponent = Database::fetchOne(
            "SELECT id FROM restaurant_combo_components WHERE restaurant_id = ? AND component_product_id = ? LIMIT 1",
            [$restaurantId, $comboProductId]
        );
        if ($usedAsComponent) throw new InvalidArgumentException('A menu item already used inside another combo cannot itself become a combo.');
        $pairs = [];
        foreach ($componentIds as $index => $rawId) {
            $qtyRaw = $quantities[$index] ?? null;
            if (($rawId === '' || $rawId === null) && ($qtyRaw === '' || $qtyRaw === null)) continue;
            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0 || $id === $comboProductId || !is_numeric($qtyRaw) || !is_finite((float)$qtyRaw) || (float)$qtyRaw <= 0 || (float)$qtyRaw > 999) {
                throw new InvalidArgumentException('Each combo component needs a different active menu item and a positive quantity.');
            }
            if (isset($pairs[$id])) throw new InvalidArgumentException('A component can appear only once; combine its quantity.');
            $pairs[$id] = (float)$qtyRaw;
        }
        if (count($pairs) < 2 || count($pairs) > 20) {
            throw new InvalidArgumentException('A combo must contain between 2 and 20 different component menu items.');
        }
        foreach ($pairs as $id => $qty) {
            $product = Database::fetchOne(
                "SELECT id FROM products WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                [$restaurantId, $id]
            );
            if (!$product) throw new InvalidArgumentException('A combo component is inactive or belongs to another restaurant.');
            $nested = Database::fetchOne(
                "SELECT id FROM restaurant_combo_products WHERE restaurant_id = ? AND product_id = ? AND is_active = 1",
                [$restaurantId, $id]
            );
            if ($nested) throw new InvalidArgumentException('Nested combos are not supported. Use individual menu items as components.');
            $variant = Database::fetchOne(
                "SELECT id FROM product_variants WHERE restaurant_id = ? AND product_id = ? AND is_active = 1 LIMIT 1",
                [$restaurantId, $id]
            );
            $addon = Database::fetchOne(
                "SELECT pag.group_id AS id FROM product_addon_group_assignments pag JOIN product_addon_groups g ON g.restaurant_id=pag.restaurant_id AND g.id=pag.group_id WHERE pag.restaurant_id = ? AND pag.product_id = ? LIMIT 1",
                [$restaurantId, $id]
            );
            if ($variant || $addon) throw new InvalidArgumentException('Combo components must be standard menu items without variants or add-on groups for reliable stock tracking.');
        }

        Database::beginTransaction();
        try {
            $existing = Database::fetchOne("SELECT id FROM restaurant_combo_products WHERE restaurant_id = ? AND product_id = ?", [$restaurantId, $comboProductId]);
            if ($existing) {
                Database::query("UPDATE restaurant_combo_products SET is_active = 1 WHERE restaurant_id = ? AND product_id = ?", [$restaurantId, $comboProductId]);
                Database::query("DELETE FROM restaurant_combo_components WHERE restaurant_id = ? AND combo_product_id = ?", [$restaurantId, $comboProductId]);
            } else {
                Database::query("INSERT INTO restaurant_combo_products (restaurant_id, product_id, is_active, created_by) VALUES (?, ?, 1, ?)", [$restaurantId, $comboProductId, $userId]);
            }
            foreach ($pairs as $id => $qty) {
                Database::query("INSERT INTO restaurant_combo_components (restaurant_id, combo_product_id, component_product_id, quantity) VALUES (?, ?, ?, ?)", [$restaurantId, $comboProductId, $id, $qty]);
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function remove(int $restaurantId, int $comboProductId): void
    {
        self::ensureSchema();
        Database::beginTransaction();
        try {
            Database::query("DELETE FROM restaurant_combo_components WHERE restaurant_id = ? AND combo_product_id = ?", [$restaurantId, $comboProductId]);
            Database::query("DELETE FROM restaurant_combo_products WHERE restaurant_id = ? AND product_id = ?", [$restaurantId, $comboProductId]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
