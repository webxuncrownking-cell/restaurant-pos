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
}
