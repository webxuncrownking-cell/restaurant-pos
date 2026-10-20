<?php
/**
 * Restaurant POS & Management SaaS
 * POS Offline Bootstrap, Idempotent Synchronization & Inventory Deduction Engine
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;
use Exception;

class PosSyncService
{
    /**
     * Get or create the latest immutable catalog snapshot version
     */
    public static function getOrCreateCatalogVersion(int $restaurantId): array
    {
        // Check for latest catalog version
        $latest = Database::fetchOne(
            "SELECT * FROM restaurant_catalog_versions WHERE restaurant_id = :restaurant_id ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId]
        );

        if ($latest) {
            return $latest;
        }

        // Build new version snapshot
        $posCatalog = MenuService::getPosCatalog($restaurantId);
        $jsonStr = json_encode($posCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshotHash = hash('sha256', $jsonStr);
        $versionUuid = DeviceService::generateUuidV4();

        Database::query(
            "INSERT INTO restaurant_catalog_versions (restaurant_id, version_uuid, snapshot_hash, snapshot_json, created_at)
             VALUES (:restaurant_id, :version_uuid, :snapshot_hash, :snapshot_json, NOW())",
            [
                ':restaurant_id' => $restaurantId,
                ':version_uuid' => $versionUuid,
                ':snapshot_hash' => $snapshotHash,
                ':snapshot_json' => $jsonStr
            ]
        );

        $id = (int)Database::lastInsertId();

        return [
            'id' => $id,
            'restaurant_id' => $restaurantId,
            'version_uuid' => $versionUuid,
            'snapshot_hash' => $snapshotHash,
            'snapshot_json' => $jsonStr
        ];
    }

    /**
     * Generate or fetch an active offline authorization lease for a device
     */
    public static function getOrCreateOfflineLease(int $restaurantId, int $deviceId, int $catalogVersionId): array
    {
        $existing = Database::fetchOne(
            "SELECT * FROM offline_authorization_leases 
             WHERE restaurant_id = :restaurant_id AND device_id = :device_id AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId, ':device_id' => $deviceId]
        );

        if ($existing) {
            return $existing;
        }

        // Get subscription ID for restaurant
        $sub = Database::fetchOne(
            "SELECT id FROM subscriptions WHERE restaurant_id = :restaurant_id AND status = 'active' ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId]
        );
        $subscriptionId = $sub ? (int)$sub['id'] : 1;

        $leaseId = 'lease_' . bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $graceExpiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));

        $rawPayload = "{$restaurantId}:{$deviceId}:{$subscriptionId}:{$catalogVersionId}:{$leaseId}:{$expiresAt}";
        $signature = base64_encode(hash_hmac('sha256', $rawPayload, 'offline_lease_secret_key_restaurant_pos', true));

        Database::query(
            "INSERT INTO offline_authorization_leases 
             (restaurant_id, lease_id, device_id, subscription_id, catalog_version_id, issued_at, expires_at, grace_expires_at, signature_base64, created_at)
             VALUES (:restaurant_id, :lease_id, :device_id, :subscription_id, :catalog_version_id, :issued_at, :expires_at, :grace_expires_at, :signature, NOW())",
            [
                ':restaurant_id' => $restaurantId,
                ':lease_id' => $leaseId,
                ':device_id' => $deviceId,
                ':subscription_id' => $subscriptionId,
                ':catalog_version_id' => $catalogVersionId,
                ':issued_at' => $now,
                ':expires_at' => $expiresAt,
                ':grace_expires_at' => $graceExpiresAt,
                ':signature' => $signature
            ]
        );

        return [
            'lease_id' => $leaseId,
            'device_id' => $deviceId,
            'catalog_version_id' => $catalogVersionId,
            'expires_at' => $expiresAt,
            'grace_expires_at' => $graceExpiresAt,
            'signature' => $signature
        ];
    }

    /**
     * Build full bootstrap payload for a POS device
     */
    public static function getBootstrapPayload(int $restaurantId, int $deviceId): array
    {
        $restaurant = Database::fetchOne("SELECT id, name, code, address, phone, email, tin FROM restaurants WHERE id = :id", [':id' => $restaurantId]);
        $device = DeviceService::getDeviceById($restaurantId, $deviceId);
        if (!$device || $device['status'] !== 'active') {
            throw new RuntimeException('Device not found or not active.');
        }

        $catalogVersion = self::getOrCreateCatalogVersion($restaurantId);
        $lease = self::getOrCreateOfflineLease($restaurantId, $deviceId, (int)$catalogVersion['id']);
        $tables = TableService::getTables($restaurantId);

        // Fetch authorized staff with offline PIN credentials
        $staffMembers = Database::fetchAll(
            "SELECT u.id, u.username, u.full_name, r.name as role_name,
                    COALESCE(u.offline_pin_salt, '5f4dcc3b5aa765d61d8327deb882cf99') as pin_salt,
                    COALESCE(u.offline_pin_hash, 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f') as pin_hash
             FROM pos_device_staff_authorizations sa
             JOIN users u ON u.restaurant_id = sa.restaurant_id AND u.id = sa.user_id
             JOIN roles r ON r.restaurant_id = u.restaurant_id AND r.id = u.role_id
             WHERE sa.restaurant_id = :restaurant_id AND sa.device_id = :device_id AND sa.is_allowed_offline = 1 AND u.is_active = 1",
            [':restaurant_id' => $restaurantId, ':device_id' => $deviceId]
        );

        $catalogData = json_decode($catalogVersion['snapshot_json'], true) ?: [];

        return [
            'restaurant' => $restaurant,
            'device' => [
                'id' => (int)$device['id'],
                'uuid' => $device['device_uuid'],
                'name' => $device['device_name']
            ],
            'lease' => $lease,
            'catalog_version' => [
                'id' => (int)$catalogVersion['id'],
                'uuid' => $catalogVersion['version_uuid'],
                'hash' => $catalogVersion['snapshot_hash']
            ],
            'catalog' => $catalogData,
            'tables' => $tables,
            'staff' => $staffMembers
        ];
    }

    /**
     * Process and synchronize an offline order idempotently
     */
    public static function processOrderSync(int $restaurantId, array $orderPayload): array
    {
        $clientUuid = trim($orderPayload['client_uuid'] ?? '');
        if (empty($clientUuid)) {
            throw new InvalidArgumentException('Missing client_uuid in order payload.');
        }

        // 1. Idempotency Check: if order with this client_uuid already exists, return existing record
        $existingOrder = Database::fetchOne(
            "SELECT id, order_number, total_amount, payment_status, created_at 
             FROM orders 
             WHERE restaurant_id = :restaurant_id AND client_uuid = :client_uuid",
            [':restaurant_id' => $restaurantId, ':client_uuid' => $clientUuid]
        );

        if ($existingOrder) {
            return [
                'status' => 'already_synced',
                'server_order_id' => (int)$existingOrder['id'],
                'order_number' => $existingOrder['order_number'],
                'client_uuid' => $clientUuid
            ];
        }

        // 2. Extract payload fields
        $deviceId = (int)($orderPayload['device_id'] ?? 1);
        $shiftId = !empty($orderPayload['shift_id']) ? (int)$orderPayload['shift_id'] : null;
        $orderNumber = $orderPayload['order_number'] ?? ('ORD-' . date('Ymd') . '-' . substr($clientUuid, 0, 8));
        $orderType = $orderPayload['order_type'] ?? 'dine_in';
        $tableId = !empty($orderPayload['table_id']) ? (int)$orderPayload['table_id'] : null;
        $cashierId = (int)($orderPayload['cashier_id'] ?? 1);
        $offlineLeaseId = $orderPayload['offline_lease_id'] ?? null;
        $catalogVersionId = !empty($orderPayload['catalog_version_id']) ? (int)$orderPayload['catalog_version_id'] : 1;
        $sequenceNumber = !empty($orderPayload['sequence_number']) ? (int)$orderPayload['sequence_number'] : 1;
        $offlineCreatedAt = $orderPayload['created_at'] ?? date('Y-m-d H:i:s');

        $subtotal = (float)($orderPayload['subtotal'] ?? 0);
        $discountAmount = (float)($orderPayload['discount_amount'] ?? 0);
        $taxAmount = (float)($orderPayload['tax_amount'] ?? 0);
        $totalAmount = (float)($orderPayload['total_amount'] ?? 0);
        $paidAmount = (float)($orderPayload['paid_amount'] ?? $totalAmount);
        $changeAmount = (float)($orderPayload['change_amount'] ?? 0);

        $items = $orderPayload['items'] ?? [];
        $payment = $orderPayload['payment'] ?? [
            'payment_method' => 'cash',
            'amount' => $totalAmount,
            'tendered_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'client_payment_uuid' => DeviceService::generateUuidV4()
        ];

        // 3. Begin Transaction
        Database::beginTransaction();

        try {
            // Record device sequence
            try {
                Database::query(
                    "INSERT IGNORE INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at, created_at)
                     VALUES (:restaurant_id, :device_id, :sequence_number, :client_uuid, :client_reported_at, NOW())",
                    [
                        ':restaurant_id' => $restaurantId,
                        ':device_id' => $deviceId,
                        ':sequence_number' => $sequenceNumber,
                        ':client_uuid' => $clientUuid,
                        ':client_reported_at' => $offlineCreatedAt
                    ]
                );
            } catch (Exception $e) {
                // Ignore duplicate sequence logs if already handled
            }

            // Insert Order
            $orderSql = "INSERT INTO orders (
                restaurant_id, client_uuid, device_id, shift_id, order_number, order_type, table_id, cashier_id,
                offline_lease_id, catalog_version_id, order_status, payment_status, subtotal, discount_amount,
                tax_amount, total_amount, paid_amount, change_amount, offline_created_at, synced_at, created_at
            ) VALUES (
                :restaurant_id, :client_uuid, :device_id, :shift_id, :order_number, :order_type, :table_id, :cashier_id,
                :offline_lease_id, :catalog_version_id, 'completed', 'paid', :subtotal, :discount_amount,
                :tax_amount, :total_amount, :paid_amount, :change_amount, :offline_created_at, NOW(), NOW()
            )";

            Database::query($orderSql, [
                ':restaurant_id' => $restaurantId,
                ':client_uuid' => $clientUuid,
                ':device_id' => $deviceId,
                ':shift_id' => $shiftId,
                ':order_number' => $orderNumber,
                ':order_type' => in_array($orderType, ['dine_in', 'takeout', 'delivery']) ? $orderType : 'dine_in',
                ':table_id' => $tableId,
                ':cashier_id' => $cashierId,
                ':offline_lease_id' => $offlineLeaseId,
                ':catalog_version_id' => $catalogVersionId,
                ':subtotal' => $subtotal,
                ':discount_amount' => $discountAmount,
                ':tax_amount' => $taxAmount,
                ':total_amount' => $totalAmount,
                ':paid_amount' => $paidAmount,
                ':change_amount' => $changeAmount,
                ':offline_created_at' => $offlineCreatedAt
            ]);

            $orderId = (int)Database::lastInsertId();

            // Insert Order Items and perform Recipe BOM stock deductions
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
                $itemName = $item['name'] ?? 'Product';
                $variantName = $item['variant_name'] ?? null;
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $qty = (float)($item['quantity'] ?? 1);
                $itemSubtotal = $unitPrice * $qty;

                Database::query(
                    "INSERT INTO order_items (restaurant_id, order_id, product_id, variant_id, item_name, variant_name, unit_price, quantity, subtotal, total_amount)
                     VALUES (:restaurant_id, :order_id, :product_id, :variant_id, :item_name, :variant_name, :unit_price, :quantity, :subtotal, :total_amount)",
                    [
                        ':restaurant_id' => $restaurantId,
                        ':order_id' => $orderId,
                        ':product_id' => $productId,
                        ':variant_id' => $variantId,
                        ':item_name' => $itemName,
                        ':variant_name' => $variantName,
                        ':unit_price' => $unitPrice,
                        ':quantity' => $qty,
                        ':subtotal' => $itemSubtotal,
                        ':total_amount' => $itemSubtotal
                    ]
                );

                $orderItemId = (int)Database::lastInsertId();

                // Add-ons
                if (!empty($item['addons'])) {
                    foreach ($item['addons'] as $addon) {
                        Database::query(
                            "INSERT INTO order_item_addons (restaurant_id, order_item_id, addon_id, addon_name, price)
                             VALUES (:restaurant_id, :order_item_id, :addon_id, :addon_name, :price)",
                            [
                                ':restaurant_id' => $restaurantId,
                                ':order_item_id' => $orderItemId,
                                ':addon_id' => (int)$addon['id'],
                                ':addon_name' => $addon['name'],
                                ':price' => (float)($addon['price'] ?? 0)
                            ]
                        );
                    }
                }

                // Inventory Deduction Engine (Recipe BOM)
                $product = Database::fetchOne("SELECT product_type, direct_stock_ingredient_id FROM products WHERE restaurant_id = :restaurant_id AND id = :id", [
                    ':restaurant_id' => $restaurantId,
                    ':id' => $productId
                ]);

                if ($product) {
                    if ($product['product_type'] === 'recipe') {
                        // Deduct all recipe ingredients
                        $recipeIngredients = Database::fetchAll(
                            "SELECT ingredient_id, quantity_required FROM recipes 
                             WHERE restaurant_id = :restaurant_id AND product_id = :product_id AND (variant_id IS NULL OR variant_id = :variant_id)",
                            [
                                ':restaurant_id' => $restaurantId,
                                ':product_id' => $productId,
                                ':variant_id' => $variantId
                            ]
                        );

                        foreach ($recipeIngredients as $rIng) {
                            $ingId = (int)$rIng['ingredient_id'];
                            $reqBaseQty = (float)$rIng['quantity_required'] * $qty;

                            $ing = Database::fetchOne("SELECT base_unit FROM ingredients WHERE restaurant_id = :restaurant_id AND id = :id", [
                                ':restaurant_id' => $restaurantId,
                                ':id' => $ingId
                            ]);
                            $baseUnit = $ing['base_unit'] ?? 'g';

                            InventoryService::recordMovement(
                                $restaurantId,
                                $ingId,
                                'order_sale',
                                $reqBaseQty,
                                $baseUnit,
                                $orderId,
                                null,
                                "Sale: {$qty}x {$itemName} (Order #{$orderNumber})",
                                $cashierId
                            );
                        }
                    } elseif ($product['product_type'] === 'direct' && !empty($product['direct_stock_ingredient_id'])) {
                        $ingId = (int)$product['direct_stock_ingredient_id'];
                        $ing = Database::fetchOne("SELECT base_unit FROM ingredients WHERE restaurant_id = :restaurant_id AND id = :id", [
                            ':restaurant_id' => $restaurantId,
                            ':id' => $ingId
                        ]);
                        $baseUnit = $ing['base_unit'] ?? 'pcs';

                        InventoryService::recordMovement(
                            $restaurantId,
                            $ingId,
                            'order_sale',
                            $qty,
                            $baseUnit,
                            $orderId,
                            null,
                            "Direct Sale: {$qty}x {$itemName} (Order #{$orderNumber})",
                            $cashierId
                        );
                    }
                }
            }

            // Insert Payment
            $clientPayUuid = $payment['client_payment_uuid'] ?? DeviceService::generateUuidV4();
            Database::query(
                "INSERT INTO payments (restaurant_id, order_id, client_payment_uuid, payment_method, amount, tendered_amount, change_amount, status, paid_at, created_at)
                 VALUES (:restaurant_id, :order_id, :client_payment_uuid, :payment_method, :amount, :tendered_amount, :change_amount, 'completed', :paid_at, NOW())",
                [
                    ':restaurant_id' => $restaurantId,
                    ':order_id' => $orderId,
                    ':client_payment_uuid' => $clientPayUuid,
                    ':payment_method' => $payment['payment_method'] ?? 'cash',
                    ':amount' => (float)($payment['amount'] ?? $totalAmount),
                    ':tendered_amount' => (float)($payment['tendered_amount'] ?? $paidAmount),
                    ':change_amount' => (float)($payment['change_amount'] ?? $changeAmount),
                    ':paid_at' => $offlineCreatedAt
                ]
            );

            // Update Device last_sync_at
            Database::query(
                "UPDATE pos_devices SET last_sync_at = NOW() WHERE restaurant_id = :restaurant_id AND id = :id",
                [':restaurant_id' => $restaurantId, ':id' => $deviceId]
            );

            Database::commit();

            return [
                'status' => 'synced',
                'server_order_id' => $orderId,
                'order_number' => $orderNumber,
                'client_uuid' => $clientUuid
            ];
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
