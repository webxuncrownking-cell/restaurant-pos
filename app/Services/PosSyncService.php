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
use Throwable;

class PosSyncService
{
    private static bool $customerLinkSchemaReady = false;

    private static function ensureCustomerLinkSchema(): void
    {
        if (self::$customerLinkSchemaReady) return;
        $pdo = Database::getConnection();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS order_customer_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL, order_id INTEGER NOT NULL,
                customer_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(restaurant_id, order_id)
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS customer_loyalty_ledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL, customer_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL, entry_type TEXT NOT NULL, points INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(restaurant_id, order_id, entry_type)
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS order_customer_links (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                restaurant_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_order_customer (restaurant_id, order_id),
                KEY idx_customer_orders (restaurant_id, customer_id),
                CONSTRAINT fk_order_customer_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS customer_loyalty_ledger (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                restaurant_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL, entry_type VARCHAR(24) NOT NULL,
                points INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_customer_order_entry (restaurant_id, order_id, entry_type),
                KEY idx_customer_loyalty (restaurant_id, customer_id, created_at),
                CONSTRAINT fk_loyalty_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        self::$customerLinkSchemaReady = true;
    }

    /**
     * Get or create the latest immutable catalog snapshot version
     */
    public static function getOrCreateCatalogVersion(int $restaurantId): array
    {
        $latest = Database::fetchOne(
            "SELECT * FROM restaurant_catalog_versions WHERE restaurant_id = :restaurant_id ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId]
        );

        // Recompute the snapshot hash on every bootstrap so changes such as
        // product photos are published to active POS devices. Normalize the
        // display-only timestamp, otherwise it would force a new version on
        // every request even when the catalog itself has not changed.
        $posCatalog = MenuService::getPosCatalog($restaurantId);
        $hashPayload = $posCatalog;
        $hashPayload['version'] = 'catalog-snapshot';
        $hashJson = json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($hashJson)) {
            throw new RuntimeException('Unable to serialize the POS catalog snapshot.');
        }
        $normalizedCatalogHash = hash('sha256', $hashJson);

        if ($latest) {
            $latestPayload = json_decode((string)($latest['snapshot_json'] ?? ''), true);
            if (is_array($latestPayload)) {
                $latestPayload['version'] = 'catalog-snapshot';
                $latestNormalizedJson = json_encode($latestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($latestNormalizedJson) && hash_equals(hash('sha256', $latestNormalizedJson), $normalizedCatalogHash)) {
                    return $latest;
                }
            }
        }

        $jsonStr = json_encode($posCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonStr)) {
            throw new RuntimeException('Unable to serialize the POS catalog snapshot.');
        }
        // Keep snapshot_hash equal to the hash of the exact stored JSON.
        $snapshotHash = hash('sha256', $jsonStr);
        $versionUuid = DeviceService::generateUuidV4();

        Database::query(
            "INSERT INTO restaurant_catalog_versions (restaurant_id, version_uuid, snapshot_hash, snapshot_json, published_at)
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
    /**
     * Read the offline lease signing key from server configuration.
     * The legacy fallback preserves existing installations until an environment
     * secret is configured; production deployments should set POS_OFFLINE_LEASE_SECRET.
     */
    private static function getOfflineLeaseSigningKey(): string
    {
        $configured = getenv('POS_OFFLINE_LEASE_SECRET');
        if (!is_string($configured) || trim($configured) === '') {
            $configured = $_ENV['POS_OFFLINE_LEASE_SECRET'] ?? $_SERVER['POS_OFFLINE_LEASE_SECRET'] ?? '';
        }
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        error_log('[Restaurant POS] POS_OFFLINE_LEASE_SECRET is not configured; using the legacy signing key. Configure a strong server-side secret.');
        return 'offline_lease_secret_key_restaurant_pos';
    }

    public static function getOrCreateOfflineLease(int $restaurantId, int $deviceId, int $catalogVersionId): array
    {
        $existing = Database::fetchOne(
            "SELECT * FROM offline_authorization_leases 
             WHERE restaurant_id = :restaurant_id AND device_id = :device_id AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId, ':device_id' => $deviceId]
        );

        if ($existing) {
            return [
                'lease_id' => $existing['lease_id'],
                'device_id' => (int)$existing['device_id'],
                'catalog_version_id' => (int)$existing['catalog_version_id'],
                'expires_at' => $existing['expires_at'],
                'grace_expires_at' => $existing['grace_expires_at'],
                'signature' => $existing['signature_base64']
            ];
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
        $signature = base64_encode(hash_hmac('sha256', $rawPayload, self::getOfflineLeaseSigningKey(), true));

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
        $restaurant = Database::fetchOne(
            "SELECT id, name, slug AS code, receipt_header AS address,
                    contact_phone AS phone, contact_email AS email,
                    business_registration_number AS tin
             FROM restaurants
             WHERE id = :id AND status = 'active' AND deleted_at IS NULL",
            [':id' => $restaurantId]
        );
        $device = DeviceService::getDeviceById($restaurantId, $deviceId);
        if (!$device || $device['status'] !== 'active') {
            throw new RuntimeException('Device not found or not active.');
        }

        $catalogVersion = self::getOrCreateCatalogVersion($restaurantId);
        $lease = self::getOrCreateOfflineLease($restaurantId, $deviceId, (int)$catalogVersion['id']);
        $tables = TableService::getTables($restaurantId);

        // Only provision staff who are explicitly authorized for offline use
        // and have a real PIN configured. Never substitute a shared demo PIN/hash.
        $staffMembers = Database::fetchAll(
            "SELECT u.id, u.username, u.full_name, r.name as role_name,
                    u.offline_pin_salt AS pin_salt,
                    u.offline_pin_hash AS pin_hash
             FROM pos_device_staff_authorizations sa
             JOIN users u ON u.restaurant_id = sa.restaurant_id AND u.id = sa.user_id
             JOIN roles r ON r.restaurant_id = u.restaurant_id AND r.id = u.role_id
             WHERE sa.restaurant_id = :restaurant_id
               AND sa.device_id = :device_id
               AND sa.is_allowed_offline = 1
               AND u.status = 'active'
               AND u.deleted_at IS NULL
               AND u.offline_pin_salt IS NOT NULL
               AND u.offline_pin_hash IS NOT NULL",
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
     * Process each queued order independently. A bad ticket must not block
     * later valid tickets in the same batch. Successful items remain idempotent
     * if the response is lost and the client retries the batch.
     */
    public static function processOrderSyncBatch(int $restaurantId, array $orders): array
    {
        $results = [];
        foreach ($orders as $orderPayload) {
            $clientUuid = is_array($orderPayload)
                ? trim((string)($orderPayload['client_uuid'] ?? ''))
                : '';
            try {
                if (!is_array($orderPayload)) {
                    throw new InvalidArgumentException('Order payload must be an object.');
                }
                $results[] = self::processOrderSync($restaurantId, $orderPayload);
            } catch (Throwable $e) {
                error_log('[POS Sync] Order ' . ($clientUuid !== '' ? $clientUuid : '(missing client UUID)') . ' failed: ' . $e);
                $results[] = [
                    'status' => 'failed',
                    'client_uuid' => $clientUuid,
                    'server_order_id' => null,
                    'error' => $e instanceof InvalidArgumentException
                        ? $e->getMessage()
                        : 'Server could not process this order. It remains queued locally; retry after checking the server log.'
                ];
            }
        }

        $hasFailures = count(array_filter(
            $results,
            static fn(array $result): bool => !in_array($result['status'] ?? '', ['synced', 'already_synced'], true)
        )) > 0;

        return [
            'status' => $hasFailures ? 'partial' : 'success',
            'results' => $results,
            'processed_count' => count($results),
            'failed_count' => count(array_filter(
                $results,
                static fn(array $result): bool => !in_array($result['status'] ?? '', ['synced', 'already_synced'], true)
            ))
        ];
    }

    /**
     * Process and synchronize an offline order idempotently
     */
    public static function processOrderSync(int $restaurantId, array $orderPayload): array
    {
        $clientUuid = trim((string)($orderPayload['client_uuid'] ?? ''));
        if ($restaurantId <= 0 || $clientUuid === '' || strlen($clientUuid) > 36) {
            throw new InvalidArgumentException('A valid restaurant and client order UUID are required.');
        }

        $customerId = (int)($orderPayload['customer_id'] ?? 0);
        if ($customerId < 0) {
            throw new InvalidArgumentException('The selected customer is invalid.');
        }
        if ($customerId > 0) {
            self::ensureCustomerLinkSchema();
            $customer = Database::fetchOne(
                "SELECT id FROM restaurant_customers WHERE restaurant_id = ? AND id = ? LIMIT 1",
                [$restaurantId, $customerId]
            );
            if (!$customer) {
                throw new InvalidArgumentException('The selected customer does not belong to this restaurant.');
            }
        }

        // Idempotency: repeated delivery of the same client order must never create a second sale.
        $existingOrder = Database::fetchOne(
            "SELECT id, order_number, total_amount, payment_status, created_at
             FROM orders
             WHERE restaurant_id = ? AND client_uuid = ?
             LIMIT 1",
            [$restaurantId, $clientUuid]
        );
        if ($existingOrder) {
            return [
                'status' => 'already_synced',
                'server_order_id' => (int)$existingOrder['id'],
                'order_number' => $existingOrder['order_number'],
                'client_uuid' => $clientUuid
            ];
        }

        $deviceId = (int)($orderPayload['device_id'] ?? 0);
        $cashierId = (int)($orderPayload['cashier_id'] ?? 0);
        if ($deviceId <= 0 || $cashierId <= 0) {
            throw new InvalidArgumentException('The order must identify a valid POS device and cashier.');
        }

        $device = Database::fetchOne(
            "SELECT id FROM pos_devices WHERE restaurant_id = ? AND id = ? AND status = 'active'",
            [$restaurantId, $deviceId]
        );
        if (!$device) {
            throw new InvalidArgumentException('The POS device is not active for this restaurant.');
        }

        $cashier = Database::fetchOne(
            "SELECT id FROM users WHERE restaurant_id = ? AND id = ? AND status = 'active' AND deleted_at IS NULL",
            [$restaurantId, $cashierId]
        );
        if (!$cashier) {
            throw new InvalidArgumentException('The cashier is not active for this restaurant.');
        }

        $orderNumber = trim((string)($orderPayload['order_number'] ?? ''));
        if ($orderNumber === '') {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . substr(str_replace('-', '', $clientUuid), 0, 12);
        }
        if (strlen($orderNumber) > 50) {
            throw new InvalidArgumentException('The order number is too long.');
        }

        $orderType = (string)($orderPayload['order_type'] ?? 'dine_in');
        if (!in_array($orderType, ['dine_in', 'takeout', 'delivery'], true)) {
            throw new InvalidArgumentException('The order type is invalid.');
        }

        $tableId = !empty($orderPayload['table_id']) ? (int)$orderPayload['table_id'] : null;
        $saveAsOpen = ($orderPayload['save_as_open'] ?? false) === true;
        if ($saveAsOpen && ($orderType !== 'dine_in' || $tableId === null)) {
            throw new InvalidArgumentException('Open unpaid tickets require a selected dine-in table.');
        }
        if ($saveAsOpen && is_array($orderPayload['payment'] ?? null)) {
            throw new InvalidArgumentException('Open tickets cannot include a payment payload.');
        }
        if ($tableId !== null) {
            $table = Database::fetchOne(
                "SELECT id FROM restaurant_tables WHERE restaurant_id = ? AND id = ? AND is_active = 1 AND deleted_at IS NULL",
                [$restaurantId, $tableId]
            );
            if (!$table) {
                throw new InvalidArgumentException('The selected table does not belong to this restaurant or is inactive.');
            }
        }

        $shiftId = !empty($orderPayload['shift_id']) ? (int)$orderPayload['shift_id'] : null;
        if ($shiftId !== null) {
            $shift = Database::fetchOne(
                "SELECT id FROM cashier_shifts WHERE restaurant_id = ? AND id = ?",
                [$restaurantId, $shiftId]
            );
            if (!$shift) {
                throw new InvalidArgumentException('The selected cashier shift does not belong to this restaurant.');
            }
        }

        // The browser's catalog.version is a display timestamp, not the MySQL catalog-version ID.
        // Resolve a valid tenant-scoped catalog snapshot when the client did not send its DB ID.
        $catalogVersionId = !empty($orderPayload['catalog_version_id']) && is_numeric($orderPayload['catalog_version_id'])
            ? (int)$orderPayload['catalog_version_id']
            : 0;
        $catalogVersion = $catalogVersionId > 0
            ? Database::fetchOne(
                "SELECT id, snapshot_json FROM restaurant_catalog_versions WHERE restaurant_id = ? AND id = ?",
                [$restaurantId, $catalogVersionId]
            )
            : null;
        if (!$catalogVersion) {
            $catalogVersion = self::getOrCreateCatalogVersion($restaurantId);
            $catalogVersionId = (int)$catalogVersion['id'];
        }
        $catalogSnapshot = json_decode((string)($catalogVersion['snapshot_json'] ?? ''), true);
        if (!is_array($catalogSnapshot)) {
            throw new InvalidArgumentException('The order references an invalid catalog snapshot. Refresh the POS catalog and retry synchronization.');
        }
        $snapshotProducts = [];
        foreach (($catalogSnapshot['products'] ?? []) as $snapshotProduct) {
            if (is_array($snapshotProduct) && isset($snapshotProduct['id'])) {
                $snapshotProducts[(int)$snapshotProduct['id']] = $snapshotProduct;
            }
        }

        $offlineLeaseId = trim((string)($orderPayload['offline_lease_id'] ?? ''));
        if ($offlineLeaseId === '') {
            $offlineLeaseId = null;
        } else {
            $lease = Database::fetchOne(
                "SELECT lease_id, expires_at, grace_expires_at, revoked_at
                 FROM offline_authorization_leases
                 WHERE restaurant_id = ? AND device_id = ? AND lease_id = ?
                   AND revoked_at IS NULL AND grace_expires_at >= NOW()
                 LIMIT 1",
                [$restaurantId, $deviceId, $offlineLeaseId]
            );
            if (!$lease) {
                throw new InvalidArgumentException(
                    'The offline authorization lease is revoked, expired beyond its grace period, or not valid for this device. Reconnect the POS to refresh authorization before syncing.'
                );
            }
        }

        $sequenceNumber = !empty($orderPayload['sequence_number']) && is_numeric($orderPayload['sequence_number'])
            ? (int)$orderPayload['sequence_number']
            : null;
        if ($sequenceNumber !== null && $sequenceNumber <= 0) {
            $sequenceNumber = null;
        }

        $clientReportedAtRaw = (string)($orderPayload['created_at'] ?? '');
        try {
            $clientReportedAt = $clientReportedAtRaw !== ''
                ? (new \DateTimeImmutable($clientReportedAtRaw))->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $clientReportedAt = date('Y-m-d H:i:s');
        }

        $itemsInput = $orderPayload['items'] ?? [];
        if (!is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 100) {
            throw new InvalidArgumentException('An order must contain between 1 and 100 line items.');
        }

        // Rebuild item prices from the tenant's database instead of trusting browser-supplied prices.
        $normalizedItems = [];
        $subtotal = 0.0;
        foreach ($itemsInput as $itemInput) {
            if (!is_array($itemInput)) {
                throw new InvalidArgumentException('One of the order items is malformed.');
            }

            $productId = (int)($itemInput['product_id'] ?? 0);
            $quantityRaw = $itemInput['quantity'] ?? null;
            if ($productId <= 0 || !is_numeric($quantityRaw) || !is_finite((float)$quantityRaw) || (float)$quantityRaw <= 0) {
                throw new InvalidArgumentException('Each order item must have a valid product and positive quantity.');
            }
            $quantity = (float)$quantityRaw;

            $product = Database::fetchOne(
                "SELECT id, name, base_price, cost_price, tax_rate, product_type, direct_stock_ingredient_id
                 FROM products
                 WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                [$restaurantId, $productId]
            );
            if (!$product) {
                throw new InvalidArgumentException('An order contains an inactive product or a product from another restaurant.');
            }

            $variantId = !empty($itemInput['variant_id']) ? (int)$itemInput['variant_id'] : null;
            $variant = null;
            if ($variantId !== null) {
                $variant = Database::fetchOne(
                    "SELECT id, name, additional_price, cost_price
                     FROM product_variants
                     WHERE restaurant_id = ? AND product_id = ? AND id = ? AND is_active = 1",
                    [$restaurantId, $productId, $variantId]
                );
                if (!$variant) {
                    throw new InvalidArgumentException('An order contains an inactive or mismatched product variant.');
                }
            }

            $addonsInput = $itemInput['addons'] ?? [];
            if (!is_array($addonsInput) || count($addonsInput) > 50) {
                throw new InvalidArgumentException('An order item contains an invalid add-on list.');
            }
            $addons = [];
            $addonIds = [];
            $addonPriceTotal = 0.0;
            foreach ($addonsInput as $addonInput) {
                $addonId = is_array($addonInput) ? (int)($addonInput['id'] ?? 0) : 0;
                if ($addonId <= 0 || in_array($addonId, $addonIds, true)) {
                    throw new InvalidArgumentException('An order item contains an invalid or duplicate add-on.');
                }

                $addon = Database::fetchOne(
                    "SELECT pa.id, pa.group_id, pa.name, pa.price, pa.cost_price, pa.ingredient_id, pa.deduction_quantity,
                            i.base_unit AS ingredient_base_unit
                     FROM product_addons pa
                     JOIN product_addon_group_assignments pag
                       ON pag.restaurant_id = pa.restaurant_id
                      AND pag.group_id = pa.group_id
                      AND pag.product_id = ?
                     LEFT JOIN ingredients i
                       ON i.restaurant_id = pa.restaurant_id AND i.id = pa.ingredient_id
                     WHERE pa.restaurant_id = ? AND pa.id = ? AND pa.is_active = 1",
                    [$productId, $restaurantId, $addonId]
                );
                if (!$addon) {
                    throw new InvalidArgumentException('An order contains an add-on that is not available for the selected product.');
                }

                $addonEntry = [
                    'id' => (int)$addon['id'],
                    'group_id' => (int)$addon['group_id'],
                    'name' => (string)$addon['name'],
                    'price' => (float)$addon['price'],
                    'cost_price' => (float)$addon['cost_price'],
                    'ingredient_id' => !empty($addon['ingredient_id']) ? (int)$addon['ingredient_id'] : null,
                    'deduction_quantity' => (float)$addon['deduction_quantity'],
                    'ingredient_base_unit' => $addon['ingredient_base_unit']
                ];
                $addons[] = $addonEntry;
                $addonIds[] = $addonEntry['id'];
                $addonPriceTotal += $addonEntry['price'];
            }

            if (($orderPayload['require_addon_groups'] ?? false) === true) {
                $requiredGroups = Database::fetchAll(
                    "SELECT g.id, g.name
                     FROM product_addon_groups g
                     JOIN product_addon_group_assignments pag
                       ON pag.restaurant_id = g.restaurant_id AND pag.group_id = g.id
                     WHERE g.restaurant_id = ? AND pag.product_id = ? AND g.is_required = 1",
                    [$restaurantId, $productId]
                );
                $selectedGroupIds = array_map(static fn(array $addon): int => (int)$addon['group_id'], $addons);
                foreach ($requiredGroups as $requiredGroup) {
                    if (!in_array((int)$requiredGroup['id'], $selectedGroupIds, true)) {
                        throw new InvalidArgumentException(
                            'Choose an option for the required add-on group: ' . (string)$requiredGroup['name'] . '.'
                        );
                    }
                }
            }

            // Use the immutable catalog snapshot's scheduled base price when the
            // order was created from a catalog that carried an active pricing rule.
            // This preserves the price for offline orders synced after the rule ends.
            $snapshotProduct = $snapshotProducts[$productId] ?? null;
            $snapshotBasePrice = is_array($snapshotProduct) && isset($snapshotProduct['scheduled_base_price'])
                ? (float)$snapshotProduct['scheduled_base_price']
                : (float)$product['base_price'];
            $unitPrice = round(
                $snapshotBasePrice
                + (float)($variant['additional_price'] ?? 0)
                + $addonPriceTotal,
                2
            );
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineSubtotal;

            $snapshotComboComponents = is_array($snapshotProduct['combo_components'] ?? null) ? $snapshotProduct['combo_components'] : [];
            $comboComponents = [];
            if (!empty($snapshotProduct['is_combo']) && count($snapshotComboComponents) >= 2) {
                foreach ($snapshotComboComponents as $snapshotComponent) {
                    $componentId = (int)($snapshotComponent['id'] ?? 0);
                    $componentProduct = $componentId > 0
                        ? Database::fetchOne("SELECT id, name, product_type, direct_stock_ingredient_id FROM products WHERE restaurant_id = ? AND id = ?", [$restaurantId, $componentId])
                        : null;
                    if (!$componentProduct) {
                        throw new InvalidArgumentException('A combo component in the order catalog no longer exists in this restaurant.');
                    }
                    $comboComponents[] = [
                        'id' => $componentId,
                        'name' => (string)($snapshotComponent['name'] ?? $componentProduct['name']),
                        'quantity' => (float)($snapshotComponent['quantity'] ?? 0),
                        'product_type' => (string)$componentProduct['product_type'],
                        'direct_stock_ingredient_id' => !empty($componentProduct['direct_stock_ingredient_id']) ? (int)$componentProduct['direct_stock_ingredient_id'] : null
                    ];
                }
            } else {
                $comboComponents = ComboService::getComponentsForProduct($restaurantId, $productId);
            }
            $costInfo = MenuService::calculateProductCost($restaurantId, $productId, $variantId, $addonIds);
            $comboCostBreakdown = [];
            if ($comboComponents) {
                $comboCost = 0.0;
                foreach ($comboComponents as $component) {
                    $componentCost = MenuService::calculateProductCost($restaurantId, (int)$component['id'], null, []);
                    $extendedCost = (float)$componentCost['total_cost'] * (float)$component['quantity'];
                    $comboCost += $extendedCost;
                    $comboCostBreakdown[] = [
                        'product_id' => (int)$component['id'],
                        'name' => (string)$component['name'],
                        'quantity_per_combo' => (float)$component['quantity'],
                        'unit_cost' => (float)$componentCost['total_cost'],
                        'extended_cost' => round($extendedCost, 4)
                    ];
                }
                $costInfo['total_cost'] = round($comboCost, 4);
                $costInfo['breakdown'] = $comboCostBreakdown;
            }
            $specialInstructions = trim((string)($itemInput['special_instructions'] ?? ''));
            if (strlen($specialInstructions) > 500) {
                throw new InvalidArgumentException('Special instructions must be 500 characters or fewer.');
            }
            if ($comboComponents) {
                $included = array_map(
                    static fn(array $component): string => (string)$component['name'] . ' × ' . rtrim(rtrim(number_format((float)$component['quantity'], 3, '.', ''), '0'), '.'),
                    $comboComponents
                );
                $comboNote = 'Combo includes: ' . implode(', ', $included);
                $specialInstructions = $specialInstructions !== '' ? $comboNote . ' | ' . $specialInstructions : $comboNote;
                $specialInstructions = substr($specialInstructions, 0, 500);
            }
            $recipeSnapshot = json_encode([
                'cost_breakdown' => $costInfo['breakdown'],
                'combo_components' => $comboCostBreakdown,
                'selected_addons' => array_map(
                    static fn(array $addon): array => [
                        'id' => $addon['id'],
                        'name' => $addon['name'],
                        'quantity' => $quantity,
                        'unit_price' => $addon['price']
                    ],
                    $addons
                )
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($recipeSnapshot === false) {
                throw new RuntimeException('Unable to create the order item snapshot.');
            }

            $normalizedItems[] = [
                'product_id' => $productId,
                'product_name' => (string)$product['name'],
                'variant_id' => $variantId,
                'variant_name' => $variant['name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => (float)$costInfo['total_cost'],
                'tax_rate' => (float)$product['tax_rate'],
                'subtotal' => $lineSubtotal,
                'recipe_snapshot_json' => $recipeSnapshot,
                'addons' => $addons,
                'combo_components' => $comboComponents,
                'special_instructions' => $specialInstructions !== '' ? $specialInstructions : null,
                'product_type' => $comboComponents ? 'combo' : (string)$product['product_type'],
                'direct_stock_ingredient_id' => !$comboComponents && !empty($product['direct_stock_ingredient_id'])
                    ? (int)$product['direct_stock_ingredient_id']
                    : null
            ];
        }
        $subtotal = round($subtotal, 2);

        $discountRaw = $orderPayload['discount_amount'] ?? 0;
        if (!is_numeric($discountRaw) || !is_finite((float)$discountRaw)) {
            throw new InvalidArgumentException('The order discount is invalid.');
        }
        $discountAmount = round((float)$discountRaw, 2);
        if ($discountAmount < 0 || $discountAmount > $subtotal) {
            throw new InvalidArgumentException('The order discount must be between zero and the subtotal.');
        }

        $totalAmount = round($subtotal - $discountAmount, 2);
        // The POS displays VAT as included in the post-discount amount.
        $taxAmount = round($totalAmount - ($totalAmount / 1.12), 2);
        $paymentInput = is_array($orderPayload['payment'] ?? null) ? $orderPayload['payment'] : [];
        if ($saveAsOpen) {
            $paidAmount = 0.0;
            $changeAmount = 0.0;
            $paymentMethod = 'cash';
        } else {
            $paidAmountRaw = $paymentInput['tendered_amount'] ?? ($orderPayload['paid_amount'] ?? $totalAmount);
            if (!is_numeric($paidAmountRaw) || !is_finite((float)$paidAmountRaw)) {
                throw new InvalidArgumentException('The cash tendered amount is invalid.');
            }
            $paidAmount = round((float)$paidAmountRaw, 2);
            if ($paidAmount < $totalAmount) {
                throw new InvalidArgumentException('The cashier tendered less than the total due.');
            }
            $changeAmount = round($paidAmount - $totalAmount, 2);
            $paymentMethod = (string)($paymentInput['payment_method'] ?? 'cash');
            if (!in_array($paymentMethod, ['cash', 'card', 'qr_online', 'other'], true)) {
                throw new InvalidArgumentException('The payment method is invalid.');
            }
        }

        $payloadJson = json_encode($orderPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException('Unable to calculate the order payload hash.');
        }
        $payloadHash = hash('sha256', $payloadJson);
        $isOfflineSync = $offlineLeaseId !== null ? 1 : 0;
        $reconciliationStatus = $isOfflineSync ? 'needs_review' : 'not_applicable';

        Database::beginTransaction();
        try {
            // Check again within the transaction before creating a new order.
            $existingOrder = Database::fetchOne(
                "SELECT id, order_number FROM orders WHERE restaurant_id = ? AND client_uuid = ? LIMIT 1",
                [$restaurantId, $clientUuid]
            );
            if ($existingOrder) {
                Database::commit();
                return [
                    'status' => 'already_synced',
                    'server_order_id' => (int)$existingOrder['id'],
                    'order_number' => $existingOrder['order_number'],
                    'client_uuid' => $clientUuid
                ];
            }

            if ($sequenceNumber !== null) {
                Database::query(
                    "INSERT IGNORE INTO pos_device_sequences
                     (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$restaurantId, $deviceId, $sequenceNumber, $clientUuid, $clientReportedAt]
                );
            }

            Database::query(
                "INSERT INTO orders (
                    restaurant_id, client_uuid, device_id, shift_id, order_number, order_type, table_id, cashier_id,
                    order_status, payment_status, subtotal, discount_amount, tax_amount, total_amount, paid_amount,
                    change_amount, notes, is_offline_sync, offline_lease_id, device_sequence_number, catalog_version_id,
                    canonical_payload_hash, offline_reconciliation_status, client_reported_at, server_received_at,
                    synced_at, created_at
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW()
                 )",
                [
                    $restaurantId,
                    $clientUuid,
                    $deviceId,
                    $shiftId,
                    $orderNumber,
                    $orderType,
                    $tableId,
                    $cashierId,
                    $saveAsOpen ? 'unpaid' : 'paid',
                    $subtotal,
                    $discountAmount,
                    $taxAmount,
                    $totalAmount,
                    $paidAmount,
                    $changeAmount,
                    $orderNotes !== '' ? $orderNotes : null,
                    $isOfflineSync,
                    $offlineLeaseId,
                    $sequenceNumber,
                    $catalogVersionId,
                    $payloadHash,
                    $reconciliationStatus,
                    $clientReportedAt
                ]
            );
            $orderId = (int)Database::lastInsertId();

            if ($customerId > 0) {
                Database::query(
                    "INSERT INTO order_customer_links (restaurant_id, order_id, customer_id) VALUES (?, ?, ?)",
                    [$restaurantId, $orderId, $customerId]
                );
                // Award one point per complete PHP 100 on paid POS sales. The unique ledger
                // key makes the award idempotent per order; open bills earn points when paid.
                if (!$saveAsOpen) {
                    $earnedPoints = (int)floor($totalAmount / 100);
                    if ($earnedPoints > 0) {
                        Database::query(
                            "INSERT INTO customer_loyalty_ledger (restaurant_id, customer_id, order_id, entry_type, points) VALUES (?, ?, ?, 'earn', ?)",
                            [$restaurantId, $customerId, $orderId, $earnedPoints]
                        );
                        Database::query(
                            "UPDATE restaurant_customers SET loyalty_points = loyalty_points + ?, updated_at = CURRENT_TIMESTAMP WHERE restaurant_id = ? AND id = ?",
                            [$earnedPoints, $restaurantId, $customerId]
                        );
                    }
                }
            }

            if ($saveAsOpen && $orderType === 'dine_in' && $tableId !== null) {
                Database::query(
                    "UPDATE restaurant_tables SET status = 'occupied' WHERE restaurant_id = ? AND id = ? AND deleted_at IS NULL",
                    [$restaurantId, $tableId]
                );
            }

            foreach ($normalizedItems as $item) {
                Database::query(
                    "INSERT INTO order_items (
                        restaurant_id, order_id, product_id, variant_id, product_name, variant_name, quantity,
                        unit_price, cost_price, tax_rate, discount_amount, subtotal, recipe_snapshot_json, special_instructions
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?)",
                    [
                        $restaurantId,
                        $orderId,
                        $item['product_id'],
                        $item['variant_id'],
                        $item['product_name'],
                        $item['variant_name'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['cost_price'],
                        $item['tax_rate'],
                        $item['subtotal'],
                        $item['recipe_snapshot_json'],
                        $item['special_instructions']
                    ]
                );
                $orderItemId = (int)Database::lastInsertId();

                foreach ($item['addons'] as $addon) {
                    $addonQuantity = (float)$item['quantity'];
                    $addonSubtotal = round((float)$addon['price'] * $addonQuantity, 2);
                    $deductionSnapshot = round((float)$addon['deduction_quantity'] * $addonQuantity, 4);
                    Database::query(
                        "INSERT INTO order_item_addons (
                            restaurant_id, order_item_id, addon_id, addon_name, unit_price, cost_price, quantity, subtotal,
                            deduction_quantity_snapshot, ingredient_id_snapshot
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $restaurantId,
                            $orderItemId,
                            $addon['id'],
                            $addon['name'],
                            $addon['price'],
                            $addon['cost_price'],
                            $addonQuantity,
                            $addonSubtotal,
                            $deductionSnapshot,
                            $addon['ingredient_id']
                        ]
                    );
                }

                // Combo lines deduct each included component's recipe or direct stock.
                if ($item['product_type'] === 'combo') {
                    if (count($item['combo_components']) < 2) {
                        throw new InvalidArgumentException('A combo is missing active component items. Update the combo definition before selling it.');
                    }
                    foreach ($item['combo_components'] as $component) {
                        $componentProductId = (int)$component['id'];
                        $componentQuantity = (float)$component['quantity'] * (float)$item['quantity'];
                        if ((string)$component['product_type'] === 'recipe') {
                            $recipeIngredients = Database::fetchAll(
                                "SELECT ingredient_id, quantity_required FROM recipes
                                 WHERE restaurant_id = ? AND product_id = ? AND variant_id IS NULL",
                                [$restaurantId, $componentProductId]
                            );
                            foreach ($recipeIngredients as $recipeIngredient) {
                                $ingredientId = (int)$recipeIngredient['ingredient_id'];
                                $requiredQuantity = (float)$recipeIngredient['quantity_required'] * $componentQuantity;
                                $ingredient = Database::fetchOne(
                                    "SELECT base_unit FROM ingredients WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                                    [$restaurantId, $ingredientId]
                                );
                                if (!$ingredient) throw new InvalidArgumentException('A combo component recipe references an inactive or missing ingredient.');
                                InventoryService::recordMovement(
                                    $restaurantId, $ingredientId, 'order_sale', $requiredQuantity,
                                    (string)$ingredient['base_unit'], null, $orderId,
                                    "Combo sale: {$item['quantity']}x {$item['product_name']} includes {$componentQuantity}x {$component['name']} (Order #{$orderNumber})",
                                    $cashierId
                                );
                            }
                        } elseif ((string)$component['product_type'] === 'direct' && !empty($component['direct_stock_ingredient_id'])) {
                            $ingredientId = (int)$component['direct_stock_ingredient_id'];
                            $ingredient = Database::fetchOne(
                                "SELECT base_unit FROM ingredients WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                                [$restaurantId, $ingredientId]
                            );
                            if (!$ingredient) throw new InvalidArgumentException('A combo direct-stock component references an inactive or missing ingredient.');
                            InventoryService::recordMovement(
                                $restaurantId, $ingredientId, 'order_sale', $componentQuantity,
                                (string)$ingredient['base_unit'], null, $orderId,
                                "Combo sale: {$item['quantity']}x {$item['product_name']} includes {$componentQuantity}x {$component['name']} (Order #{$orderNumber})",
                                $cashierId
                            );
                        }
                    }
                } elseif ($item['product_type'] === 'recipe') {
                    $recipeIngredients = Database::fetchAll(
                        "SELECT ingredient_id, quantity_required
                         FROM recipes
                         WHERE restaurant_id = ? AND product_id = ? AND (variant_id IS NULL OR variant_id = ?)",
                        [$restaurantId, $item['product_id'], $item['variant_id']]
                    );
                    foreach ($recipeIngredients as $recipeIngredient) {
                        $ingredientId = (int)$recipeIngredient['ingredient_id'];
                        $requiredQuantity = (float)$recipeIngredient['quantity_required'] * (float)$item['quantity'];
                        $ingredient = Database::fetchOne(
                            "SELECT base_unit FROM ingredients WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                            [$restaurantId, $ingredientId]
                        );
                        if (!$ingredient) {
                            throw new InvalidArgumentException('A recipe references an inactive or missing ingredient.');
                        }
                        InventoryService::recordMovement(
                            $restaurantId,
                            $ingredientId,
                            'order_sale',
                            $requiredQuantity,
                            (string)$ingredient['base_unit'],
                            null,
                            $orderId,
                            "Sale: {$item['quantity']}x {$item['product_name']} (Order #{$orderNumber})",
                            $cashierId
                        );
                    }
                } elseif ($item['product_type'] === 'direct' && $item['direct_stock_ingredient_id'] !== null) {
                    $ingredientId = (int)$item['direct_stock_ingredient_id'];
                    $ingredient = Database::fetchOne(
                        "SELECT base_unit FROM ingredients WHERE restaurant_id = ? AND id = ? AND is_active = 1",
                        [$restaurantId, $ingredientId]
                    );
                    if (!$ingredient) {
                        throw new InvalidArgumentException('A direct-stock product references an inactive or missing ingredient.');
                    }
                    InventoryService::recordMovement(
                        $restaurantId,
                        $ingredientId,
                        'order_sale',
                        (float)$item['quantity'],
                        (string)$ingredient['base_unit'],
                        null,
                        $orderId,
                        "Direct sale: {$item['quantity']}x {$item['product_name']} (Order #{$orderNumber})",
                        $cashierId
                    );
                }

                // Deduct ingredient-backed add-ons once per sold menu-item unit.
                foreach ($item['addons'] as $addon) {
                    if (empty($addon['ingredient_id']) || (float)$addon['deduction_quantity'] <= 0) {
                        continue;
                    }
                    $ingredientId = (int)$addon['ingredient_id'];
                    $ingredientUnit = (string)($addon['ingredient_base_unit'] ?? '');
                    if ($ingredientUnit === '') {
                        throw new InvalidArgumentException('An add-on references a missing inventory ingredient.');
                    }
                    InventoryService::recordMovement(
                        $restaurantId,
                        $ingredientId,
                        'order_sale',
                        (float)$addon['deduction_quantity'] * (float)$item['quantity'],
                        $ingredientUnit,
                        null,
                        $orderId,
                        "Add-on sale: {$item['quantity']}x {$addon['name']} for {$item['product_name']} (Order #{$orderNumber})",
                        $cashierId
                    );
                }
            }

            if (!$saveAsOpen) {
                Database::query(
                    "INSERT INTO payments (
                        restaurant_id, order_id, cashier_id, payment_method, amount, tendered_amount, change_amount, paid_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $restaurantId,
                        $orderId,
                        $cashierId,
                        $paymentMethod,
                        $totalAmount,
                        $paidAmount,
                        $changeAmount,
                        $clientReportedAt
                    ]
                );
            }

            Database::query(
                "UPDATE pos_devices SET last_sync_at = NOW() WHERE restaurant_id = ? AND id = ?",
                [$restaurantId, $deviceId]
            );

            Database::query(
                "UPDATE orders SET inventory_deducted_at = NOW() WHERE restaurant_id = ? AND id = ?",
                [$restaurantId, $orderId]
            );

            Database::commit();

            return [
                'status' => 'synced',
                'server_order_id' => $orderId,
                'order_number' => $orderNumber,
                'client_uuid' => $clientUuid
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
