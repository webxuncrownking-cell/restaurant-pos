<?php
/**
 * Restaurant POS & Management SaaS
 * Device Registration & Staff Authorizations Service
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class DeviceService
{
    /**
     * Generate standard UUIDv4
     */
    public static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate raw device secret token (64 hex characters)
     */
    public static function generateRawToken(): string
    {
        return 'devtok_' . bin2hex(random_bytes(28));
    }

    /**
     * Hash token using SHA-256 for secure storage
     */
    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * List all registered devices for a restaurant with staff authorization counts
     */
    public static function getDevices(int $restaurantId): array
    {
        $sql = "SELECT d.*, 
                       (SELECT COUNT(*) FROM pos_device_staff_authorizations sa 
                        WHERE sa.restaurant_id = d.restaurant_id AND sa.device_id = d.id) as staff_count,
                       (SELECT COUNT(*) FROM cashier_shifts cs 
                        WHERE cs.restaurant_id = d.restaurant_id AND cs.device_id = d.id AND cs.status = 'open') as open_shifts
                FROM pos_devices d
                WHERE d.restaurant_id = :restaurant_id
                ORDER BY d.created_at DESC";
        
        return Database::fetchAll($sql, [':restaurant_id' => $restaurantId]);
    }

    /**
     * Get device by ID
     */
    public static function getDeviceById(int $restaurantId, int $deviceId): ?array
    {
        $sql = "SELECT * FROM pos_devices WHERE restaurant_id = :restaurant_id AND id = :id";
        return Database::fetchOne($sql, [':restaurant_id' => $restaurantId, ':id' => $deviceId]);
    }

    /**
     * Register a new POS terminal device
     * Returns an array with device ID, UUID, and the plain token (only shown once during registration)
     */
    public static function registerDevice(int $restaurantId, string $deviceName): array
    {
        $deviceName = trim($deviceName);
        if (empty($deviceName)) {
            throw new InvalidArgumentException('Device name is required.');
        }

        $uuid = self::generateUuidV4();
        $rawToken = self::generateRawToken();
        $tokenHash = self::hashToken($rawToken);

        $sql = "INSERT INTO pos_devices (restaurant_id, device_uuid, device_name, device_token_hash, status, created_at)
                VALUES (:restaurant_id, :device_uuid, :device_name, :token_hash, 'active', NOW())";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':device_uuid' => $uuid,
            ':device_name' => $deviceName,
            ':token_hash' => $tokenHash
        ]);

        $deviceId = (int)Database::lastInsertId();

        // By default, authorize all active cashiers and managers in this restaurant
        $staffMembers = Database::fetchAll(
            "SELECT id FROM users
             WHERE restaurant_id = :restaurant_id AND status = 'active' AND deleted_at IS NULL",
            [':restaurant_id' => $restaurantId]
        );
        foreach ($staffMembers as $staff) {
            self::authorizeStaff($restaurantId, $deviceId, (int)$staff['id'], true);
        }

        return [
            'device_id' => $deviceId,
            'device_uuid' => $uuid,
            'device_name' => $deviceName,
            'plain_token' => $rawToken,
        ];
    }

    /**
     * Rotate device authentication credentials
     */
    public static function rotateCredentials(int $restaurantId, int $deviceId): string
    {
        $newRawToken = self::generateRawToken();
        $tokenHash = self::hashToken($newRawToken);

        $sql = "UPDATE pos_devices 
                SET device_token_hash = :token_hash, credential_rotated_at = NOW() 
                WHERE restaurant_id = :restaurant_id AND id = :id";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $deviceId,
            ':token_hash' => $tokenHash
        ]);

        return $newRawToken;
    }

    /**
     * Update device status (active, revoked, pending)
     */
    public static function setDeviceStatus(int $restaurantId, int $deviceId, string $status): bool
    {
        if (!in_array($status, ['active', 'revoked', 'pending'])) {
            throw new InvalidArgumentException('Invalid device status.');
        }

        $sql = "UPDATE pos_devices SET status = :status WHERE restaurant_id = :restaurant_id AND id = :id";
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':id' => $deviceId,
            ':status' => $status
        ]);

        return true;
    }

    /**
     * Authorize or update offline access for a staff member on a specific device
     */
    public static function authorizeStaff(int $restaurantId, int $deviceId, int $userId, bool $allowedOffline = true): bool
    {
        $sql = "INSERT INTO pos_device_staff_authorizations (restaurant_id, device_id, user_id, is_allowed_offline, authorized_at)
                VALUES (:restaurant_id, :device_id, :user_id, :is_allowed_offline, NOW())
                ON DUPLICATE KEY UPDATE is_allowed_offline = VALUES(is_allowed_offline)";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':device_id' => $deviceId,
            ':user_id' => $userId,
            ':is_allowed_offline' => $allowedOffline ? 1 : 0
        ]);

        return true;
    }

    /**
     * Revoke staff member authorization from a specific device
     */
    public static function deauthorizeStaff(int $restaurantId, int $deviceId, int $userId): bool
    {
        $sql = "DELETE FROM pos_device_staff_authorizations 
                WHERE restaurant_id = :restaurant_id AND device_id = :device_id AND user_id = :user_id";
        
        Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':device_id' => $deviceId,
            ':user_id' => $userId
        ]);

        return true;
    }

    /**
     * Get list of authorized staff members for a specific device
     */
    public static function getDeviceStaff(int $restaurantId, int $deviceId): array
    {
        $sql = "SELECT u.id as user_id, u.full_name, u.username, r.name as role_name,
                       sa.is_allowed_offline, sa.authorized_at
                FROM pos_device_staff_authorizations sa
                JOIN users u ON u.restaurant_id = sa.restaurant_id AND u.id = sa.user_id
                JOIN roles r ON r.restaurant_id = u.restaurant_id AND r.id = u.role_id
                WHERE sa.restaurant_id = :restaurant_id AND sa.device_id = :device_id
                ORDER BY u.full_name ASC";
        
        return Database::fetchAll($sql, [
            ':restaurant_id' => $restaurantId,
            ':device_id' => $deviceId
        ]);
    }
}
