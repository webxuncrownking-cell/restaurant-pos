<?php
/**
 * Restaurant POS & Management SaaS
 * Cashier Shift Sessions & Cash Drawer Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

class ShiftService
{
    /**
     * Get active/open shift for a specific cashier and/or device
     */
    public static function getActiveShift(int $restaurantId, int $cashierId, ?int $deviceId = null): ?array
    {
        $sql = "SELECT s.*, d.device_name, u.full_name as cashier_name
                FROM cashier_shifts s
                JOIN pos_devices d ON d.restaurant_id = s.restaurant_id AND d.id = s.device_id
                JOIN users u ON u.restaurant_id = s.restaurant_id AND u.id = s.cashier_id
                WHERE s.restaurant_id = :restaurant_id 
                  AND s.cashier_id = :cashier_id 
                  AND s.status = 'open'";
        
        $params = [
            ':restaurant_id' => $restaurantId,
            ':cashier_id' => $cashierId
        ];

        if ($deviceId !== null) {
            $sql .= " AND s.device_id = :device_id";
            $params[':device_id'] = $deviceId;
        }

        $sql .= " ORDER BY s.opened_at DESC LIMIT 1";

        return Database::fetchOne($sql, $params);
    }

    /**
     * Get shift by ID
     */
    public static function getShiftById(int $restaurantId, int $shiftId): ?array
    {
        $sql = "SELECT s.*, d.device_name, u.full_name as cashier_name
                FROM cashier_shifts s
                JOIN pos_devices d ON d.restaurant_id = s.restaurant_id AND d.id = s.device_id
                JOIN users u ON u.restaurant_id = s.restaurant_id AND u.id = s.cashier_id
                WHERE s.restaurant_id = :restaurant_id AND s.id = :id";
        
        return Database::fetchOne($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $shiftId
        ]);
    }

    /**
     * List recent shifts for a restaurant
     */
    public static function getShifts(int $restaurantId, int $limit = 50): array
    {
        $sql = "SELECT s.*, d.device_name, u.full_name as cashier_name,
                       (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = s.restaurant_id AND o.shift_id = s.id) as order_count,
                       (SELECT COUNT(*) FROM cash_movements cm WHERE cm.restaurant_id = s.restaurant_id AND cm.shift_id = s.id) as movement_count
                FROM cashier_shifts s
                JOIN pos_devices d ON d.restaurant_id = s.restaurant_id AND d.id = s.device_id
                JOIN users u ON u.restaurant_id = s.restaurant_id AND u.id = s.cashier_id
                WHERE s.restaurant_id = :restaurant_id
                ORDER BY s.opened_at DESC
                LIMIT " . (int)$limit;
        
        return Database::fetchAll($sql, [':restaurant_id' => $restaurantId]);
    }

    /**
     * Open a new cashier shift session with starting float
     */
    public static function openShift(int $restaurantId, int $cashierId, int $deviceId, float $openingCash, ?string $notes = null): int
    {
        if ($openingCash < 0) {
            throw new InvalidArgumentException('Opening float cannot be negative.');
        }

        // Verify device exists and is active
        $device = Database::fetchOne(
            "SELECT id, status FROM pos_devices WHERE restaurant_id = :restaurant_id AND id = :id",
            [':restaurant_id' => $restaurantId, ':id' => $deviceId]
        );
        if (!$device || $device['status'] !== 'active') {
            throw new InvalidArgumentException('Selected device is not active or does not exist.');
        }

        // Check if cashier already has an open shift on this device
        $existing = self::getActiveShift($restaurantId, $cashierId, $deviceId);
        if ($existing) {
            throw new RuntimeException('You already have an open shift on this device (#Shift ' . $existing['id'] . ').');
        }

        $sql = "INSERT INTO cashier_shifts (restaurant_id, device_id, cashier_id, opening_cash, expected_cash, status, opened_at, notes)
                VALUES (:restaurant_id, :device_id, :cashier_id, :opening_cash, :expected_cash, 'open', NOW(), :notes)";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':device_id' => $deviceId,
            ':cashier_id' => $cashierId,
            ':opening_cash' => $openingCash,
            ':expected_cash' => $openingCash,
            ':notes' => $notes
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Record a cash movement (cash in, cash out, safe drop)
     */
    public static function recordCashMovement(int $restaurantId, int $shiftId, int $cashierId, string $type, float $amount, string $reason): int
    {
        if (!in_array($type, ['cash_in', 'cash_out', 'drop'])) {
            throw new InvalidArgumentException('Invalid cash movement type.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Movement amount must be greater than zero.');
        }
        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Reason is required for cash movements.');
        }

        $shift = self::getShiftById($restaurantId, $shiftId);
        if (!$shift || $shift['status'] !== 'open') {
            throw new RuntimeException('Shift is not currently open.');
        }

        $sql = "INSERT INTO cash_movements (restaurant_id, shift_id, cashier_id, movement_type, amount, reason, created_at)
                VALUES (:restaurant_id, :shift_id, :cashier_id, :type, :amount, :reason, NOW())";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':shift_id' => $shiftId,
            ':cashier_id' => $cashierId,
            ':type' => $type,
            ':amount' => $amount,
            ':reason' => trim($reason)
        ]);

        $movementId = (int)Database::lastInsertId();

        // Recalculate shift expected cash
        self::recalculateExpectedCash($restaurantId, $shiftId);

        return $movementId;
    }

    /**
     * Get cash movements for a shift
     */
    public static function getShiftMovements(int $restaurantId, int $shiftId): array
    {
        $sql = "SELECT cm.*, u.full_name as cashier_name
                FROM cash_movements cm
                JOIN users u ON u.restaurant_id = cm.restaurant_id AND u.id = cm.cashier_id
                WHERE cm.restaurant_id = :restaurant_id AND cm.shift_id = :shift_id
                ORDER BY cm.created_at ASC";
        
        return Database::fetchAll($sql, [
            ':restaurant_id' => $restaurantId,
            ':shift_id' => $shiftId
        ]);
    }

    /**
     * Calculate live expected cash breakdown for a shift
     */
    public static function calculateShiftSummary(int $restaurantId, int $shiftId): array
    {
        $shift = self::getShiftById($restaurantId, $shiftId);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $openingCash = (float)$shift['opening_cash'];

        // Cash sales total
        $salesRow = Database::fetchOne(
            "SELECT COALESCE(SUM(p.amount), 0) as total_cash_sales, COUNT(DISTINCT o.id) as paid_orders
             FROM payments p
             JOIN orders o ON o.restaurant_id = p.restaurant_id AND o.id = p.order_id
             WHERE p.restaurant_id = :restaurant_id 
               AND o.shift_id = :shift_id 
               AND p.payment_method = 'cash' 
               AND p.status = 'completed'",
            [':restaurant_id' => $restaurantId, ':shift_id' => $shiftId]
        );
        $totalCashSales = (float)($salesRow['total_cash_sales'] ?? 0);
        $paidOrdersCount = (int)($salesRow['paid_orders'] ?? 0);

        // Cash In movements
        $cashInRow = Database::fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM cash_movements 
             WHERE restaurant_id = :restaurant_id AND shift_id = :shift_id AND movement_type = 'cash_in'",
            [':restaurant_id' => $restaurantId, ':shift_id' => $shiftId]
        );
        $totalCashIn = (float)($cashInRow['total'] ?? 0);

        // Cash Out & Drops
        $cashOutRow = Database::fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM cash_movements 
             WHERE restaurant_id = :restaurant_id AND shift_id = :shift_id AND movement_type IN ('cash_out', 'drop')",
            [':restaurant_id' => $restaurantId, ':shift_id' => $shiftId]
        );
        $totalCashOut = (float)($cashOutRow['total'] ?? 0);

        $expectedCash = $openingCash + $totalCashSales + $totalCashIn - $totalCashOut;

        return [
            'shift' => $shift,
            'opening_cash' => $openingCash,
            'cash_sales' => $totalCashSales,
            'paid_orders_count' => $paidOrdersCount,
            'cash_in' => $totalCashIn,
            'cash_out' => $totalCashOut,
            'expected_cash' => round($expectedCash, 2)
        ];
    }

    /**
     * Recalculate and update the expected_cash column in cashier_shifts
     */
    public static function recalculateExpectedCash(int $restaurantId, int $shiftId): float
    {
        $summary = self::calculateShiftSummary($restaurantId, $shiftId);
        $expected = $summary['expected_cash'];

        Database::query(
            "UPDATE cashier_shifts SET expected_cash = :expected WHERE restaurant_id = :restaurant_id AND id = :id",
            [':restaurant_id' => $restaurantId, ':id' => $shiftId, ':expected' => $expected]
        );

        return $expected;
    }

    /**
     * Close a cashier shift with physical cash count audit
     */
    public static function closeShift(int $restaurantId, int $shiftId, float $closingCash, ?string $notes = null): array
    {
        $summary = self::calculateShiftSummary($restaurantId, $shiftId);
        $expectedCash = $summary['expected_cash'];
        $difference = round($closingCash - $expectedCash, 2);

        $sql = "UPDATE cashier_shifts 
                SET closing_cash = :closing_cash,
                    expected_cash = :expected_cash,
                    cash_difference = :difference,
                    status = 'closed',
                    closed_at = NOW(),
                    notes = CASE WHEN :notes IS NOT NULL AND :notes != '' 
                                 THEN CONCAT(COALESCE(notes, ''), '\n[Closing Note]: ', :notes)
                                 ELSE notes END
                WHERE restaurant_id = :restaurant_id AND id = :id AND status = 'open'";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $shiftId,
            ':closing_cash' => $closingCash,
            ':expected_cash' => $expectedCash,
            ':difference' => $difference,
            ':notes' => $notes
        ]);

        return [
            'shift_id' => $shiftId,
            'expected_cash' => $expectedCash,
            'closing_cash' => $closingCash,
            'difference' => $difference,
            'status' => 'closed'
        ];
    }
}
