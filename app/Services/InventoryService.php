<?php
/**
 * Restaurant POS & Management SaaS
 * Double-Entry Inventory Movements Ledger & Stock Management Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Record an inventory movement and update current stock balance atomically.
     */
    public static function recordMovement(
        int $restaurantId,
        int $ingredientId,
        string $referenceType,
        float $quantity,
        string $unit,
        ?float $newCostPerUnit = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $createdBy = null
    ): int {
        $allowedTypes = [
            'purchase_receipt',
            'order_sale',
            'order_cancellation',
            'order_refund',
            'manual_adjustment',
            'spoilage_waste'
        ];

        if (!in_array($referenceType, $allowedTypes, true)) {
            throw new InvalidArgumentException("Invalid reference type '{$referenceType}'.");
        }

        if (!is_finite($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException("Movement quantity must be a finite number greater than zero.");
        }
        if (trim($unit) === '') {
            throw new InvalidArgumentException('A stock unit is required.');
        }
        if ($newCostPerUnit !== null && (!is_finite($newCostPerUnit) || $newCostPerUnit < 0)) {
            throw new InvalidArgumentException('Unit cost must be a finite, non-negative amount.');
        }

        $pdo = Database::getConnection();
        $inExistingTx = $pdo->inTransaction();

        if (!$inExistingTx) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Fetch current ingredient to verify tenant ownership and get base unit
            $stmt = $pdo->prepare(
                "SELECT id, name, base_unit, current_stock, cost_per_base_unit 
                 FROM ingredients 
                 WHERE id = ? AND restaurant_id = ? 
                 FOR UPDATE"
            );
            $stmt->execute([$ingredientId, $restaurantId]);
            $ingredient = $stmt->fetch();

            if (!$ingredient) {
                throw new InvalidArgumentException("Ingredient #{$ingredientId} not found for restaurant #{$restaurantId}.");
            }

            // 2. Resolve created_by user
            if ($createdBy === null || $createdBy <= 0) {
                $user = Database::fetchOne(
                    "SELECT id FROM users
                     WHERE restaurant_id = ? AND status = 'active' AND deleted_at IS NULL
                     ORDER BY id ASC LIMIT 1",
                    [$restaurantId]
                );
                if (!$user) {
                    throw new InvalidArgumentException('No active staff account is available to record this inventory movement.');
                }
                $createdBy = (int)$user['id'];
            }

            // 3. Convert movement unit to ingredient's base unit
            $baseQty = UnitConversionService::convert($restaurantId, $quantity, $unit, $ingredient['base_unit'], $ingredientId);

            // 4. Calculate stock before & after
            $stockBefore = (float)$ingredient['current_stock'];
            $isAddition = in_array($referenceType, ['purchase_receipt', 'order_cancellation', 'order_refund'], true);
            $quantityChange = $isAddition ? $baseQty : -$baseQty;
            $stockAfter = $stockBefore + $quantityChange;

            // 5. Update ingredient current stock
            $updateSql = "UPDATE ingredients SET current_stock = ?";
            $params = [$stockAfter];

            if ($referenceType === 'purchase_receipt' && $newCostPerUnit !== null && $newCostPerUnit > 0) {
                $baseCostPerUnit = $newCostPerUnit;
                if ($unit !== $ingredient['base_unit']) {
                    $baseCostPerUnit = ($newCostPerUnit * $quantity) / $baseQty;
                }
                $updateSql .= ", cost_per_base_unit = ?";
                $params[] = $baseCostPerUnit;
            }

            $updateSql .= " WHERE id = ? AND restaurant_id = ?";
            $params[] = $ingredientId;
            $params[] = $restaurantId;

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);

            // 6. Insert double-entry ledger entry into inventory_movements
            $insertStmt = $pdo->prepare(
                "INSERT INTO inventory_movements (
                    restaurant_id, ingredient_id, reference_type, reference_id,
                    quantity_change, stock_before, stock_after, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $insertStmt->execute([
                $restaurantId,
                $ingredientId,
                $referenceType,
                $referenceId,
                $quantityChange,
                $stockBefore,
                $stockAfter,
                $notes,
                $createdBy
            ]);

            $movementId = (int)$pdo->lastInsertId();

            if (!$inExistingTx) {
                $pdo->commit();
            }

            return $movementId;
        } catch (\Throwable $e) {
            if (!$inExistingTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Process stock-in via packaged goods configuration from ingredient_unit_conversions.
     */
    public static function stockInByPackage(
        int $restaurantId,
        int $conversionId,
        float $packageCount,
        ?float $totalCost = null,
        ?string $notes = null,
        ?int $createdBy = null
    ): int {
        $conv = UnitConversionService::convertPackageToBase($restaurantId, $conversionId, $packageCount);
        $costPerBase = $conv['cost_per_base_unit'];
        if ($totalCost !== null && $conv['total_base_quantity'] > 0) {
            $costPerBase = $totalCost / $conv['total_base_quantity'];
        }

        $fullNotes = "Stock In via Package: {$packageCount}x {$conv['package_name']}";
        if ($notes) {
            $fullNotes .= " - " . $notes;
        }

        return self::recordMovement(
            $restaurantId,
            $conv['ingredient_id'],
            'purchase_receipt',
            $conv['total_base_quantity'],
            $conv['base_unit'],
            $costPerBase,
            null,
            $fullNotes,
            $createdBy
        );
    }

    /**
     * Get low stock ingredients below their configured alert threshold.
     */
    public static function getLowStockAlerts(int $restaurantId): array
    {
        return Database::fetchAll(
            "SELECT id, name, base_unit, current_stock, minimum_stock_alert, cost_per_base_unit,
                    (minimum_stock_alert - current_stock) as deficit
             FROM ingredients
             WHERE restaurant_id = ? 
               AND is_active = 1 
               AND current_stock <= minimum_stock_alert
             ORDER BY (current_stock / NULLIF(minimum_stock_alert, 0)) ASC, name ASC",
            [$restaurantId]
        );
    }

    /**
     * Fetch paginated inventory movements ledger with optional ingredient and type filtering.
     */
    public static function getLedger(
        int $restaurantId,
        ?int $ingredientId = null,
        ?string $referenceType = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $allowedTypes = [
            'order_sale',
            'order_cancellation',
            'order_refund',
            'manual_adjustment',
            'purchase_receipt',
            'spoilage_waste'
        ];
        if ($referenceType !== null && $referenceType !== '' && !in_array($referenceType, $allowedTypes, true)) {
            throw new InvalidArgumentException('Invalid inventory movement type.');
        }

        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        $sql = "SELECT m.*, i.name AS ingredient_name, i.base_unit,
                       i.cost_per_base_unit AS current_cost_per_base_unit,
                       u.username AS logged_by
                FROM inventory_movements m
                JOIN ingredients i ON m.ingredient_id = i.id AND m.restaurant_id = i.restaurant_id
                LEFT JOIN users u ON m.created_by = u.id AND m.restaurant_id = u.restaurant_id
                WHERE m.restaurant_id = ?";
        $params = [$restaurantId];

        if ($ingredientId !== null && $ingredientId > 0) {
            $sql .= " AND m.ingredient_id = ?";
            $params[] = $ingredientId;
        }
        if ($referenceType !== null && $referenceType !== '') {
            $sql .= " AND m.reference_type = ?";
            $params[] = $referenceType;
        }

        $sql .= " ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll($sql, $params);
    }
}
