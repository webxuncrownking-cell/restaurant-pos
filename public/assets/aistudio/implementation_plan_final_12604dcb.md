# Restaurant POS & Management SaaS: Architecture & Engineering Plan

A multi-tenant, offline-first Restaurant Point of Sale (POS) and SaaS Management System built with **PHP 8.2+ (pure OOP, standard PHP)**, **MySQL 8+ / MariaDB (InnoDB, PDO prepared statements)**, **Bootstrap 5 & Vanilla JS**, **PWA Service Worker & IndexedDB**, and **Chart.js**.

Designed for dual environments: local development and testing on **XAMPP / Apache**, and online production deployment on **cPanel / Apache / HTTPS**.

---

## User Review & Critical Decisions

> [!IMPORTANT]
> **Summary of Final Architectural Enhancements:**
> 1. **Complete Tenant-Safe Composite Foreign Keys:** Enforced `(restaurant_id, id)` composite foreign keys across all modules without exception:
>    - `platform_invoices` -> `subscriptions (restaurant_id, id)`
>    - `platform_payments` -> `platform_invoices (restaurant_id, id)`
>    - `general_audit_logs` -> `users (restaurant_id, id)`
>    - `void_records` -> `orders (restaurant_id, id)`, `order_items (restaurant_id, id)`, and `users (restaurant_id, id)`
>    - `inventory_conflicts` -> `users (restaurant_id, id)` for `resolved_by`
> 2. **Sequence-Aware Out-of-Order Synchronization:** Introduced `pos_device_sequences` table tracking every accepted sequence per device. Legitimate earlier sequences that arrive *after* later sequences (due to network retries) are fully accepted. Duplicate sequence numbers with conflicting `client_uuid`s are detected and flagged as **Sequence Reuse Conflicts**.
> 3. **Separation of Timestamps:** Orders record `client_reported_at` (sale time), `server_received_at` (HTTP receipt), and `synced_at` (database transaction commit time), alongside `offline_lease_id` and `device_sequence_number`.
> 4. **Hardened Synchronization Engine:**
>    - Per-order conflict records are held in a **temporary buffer** and committed to the final response array **only after** the database transaction successfully commits.
>    - Duplicate-key race conditions immediately trigger an explicit `$db->rollBack()`, query the existing order by `(restaurant_id, client_uuid)`, and acknowledge it safely.
>    - Invalid or missing UUIDs return explicit error entries in the `failed` array rather than being silently skipped.
>    - Detailed stack traces are logged privately to the server log; clients receive clean, sanitized error messages.
> 5. **Browser Storage Security & Device Revocation:** Explicitly documented that client-side IndexedDB databases provide application-level isolation within the browser origin, not a tamper-proof OS security boundary. Added a **Device Revocation & Emergency Recovery Workflow** that purges local credentials while exporting unsynced sales.
> 6. **Two-Tenant Verification Test Suite:** Complete test queries demonstrating that cross-tenant relationships fail physically at the database level using two distinct test restaurants (Mang Juan vs. Aling Nena).
> 7. **Secure Administrator Provisioning:** Seed script enforces a `must_change_password` flag for initial admin accounts, with zero plaintext default passwords in production configurations.
> 8. **Authenticated Registered POS Devices:** Device UUIDs are identifiers only. Sync requires a registered device token over HTTPS, verified against a server-stored SHA-256 hash, with rotation and revocation.
> 9. **Cross-Record Financial Integrity:** Refunds must reference a payment from the same order, and item-level voids must reference an item from the same order, enforced by composite foreign keys and service validation.
> 10. **Idempotency Payload Integrity & Offline Sale Validation:** The server computes canonical payload hashes; reused UUIDs with changed contents are rejected. Transaction totals and inventory are recalculated against immutable server-owned catalog versions when possible, and unverifiable transactions are retained for review rather than silently altered.

---

## 1. System Architecture & Component Diagram

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER (Browser / PWA)                              │
│  ┌───────────────────────┐  ┌─────────────────────────┐  ┌───────────────────────────┐ │
│  │   Offline App Shell   │  │ IndexedDB (Isolated)    │  │   Service Worker (sw.js)  │ │
│  │   - Bootstrap 5 CSS   │  │ RestaurantPOS_DB_       │  │   - App Shell Precaching  │ │
│  │   - Vanilla JS Engine │  │ {tenant_id}_{device_id} │  │   - Static Asset Cache    │ │
│  │   - POS Touch Grid    │  │ - Signed Offline Lease  │  │   - NO Private Data Cache │ │
│  │   - Monotonic Counter │  │ - Authorized Staff PINs │  │   - Network-First for API │ │
│  │   - 15m Lock Screen   │  │ - Offline Orders Queue  │  │   - Emergency JSON Backup │ │
│  └───────────┬───────────┘  └────────────┬────────────┘  └─────────────┬─────────────┘ │
└──────────────┼───────────────────────────┼─────────────────────────────┼───────────────┘
               │                           │                             │
               │ HTTPS / JSON API (Bearer / Session + CSRF)              │
               ▼                           ▼                             ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                           ROUTER, SECURITY & SAAS MIDDLEWARE                           │
│  - Front Controller (index.php)               - Multi-Tenant Scoping Middleware        │
│  - CSRF Token Verification                    - Role & Permission Enforcement (RBAC)   │
│  - Device Verification (Device-UUID header)   - Signed Lease & Grace Period Validator  │
│  - Webhook Signature & Idempotency Guard      - Rate Limiting                          │
└──────────────────────────────────────────┬─────────────────────────────────────────────┘
                                           │
                                           ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 SERVICE / DOMAIN LAYER                                 │
│  ┌────────────────────────┐  ┌────────────────────────┐  ┌───────────────────────────┐ │
│  │   SaaS Billing Engine  │  │   Sync & Idempotency   │  │   Order & State Machine   │ │
│  │   - Plans & Subscript. │  │   - Per-Order ACID Tx  │  │   - 6-Stage Order Lifecycle│ │
│  │   - Invoices & Webhooks│  │   - Sequence Tracker   │  │   - Status History Logger │ │
│  │   - Lease Issuance     │  │   - Conflict Buffer    │  │   - Table Occupancy Lock  │ │
│  └────────────────────────┘  └────────────────────────┘  └───────────────────────────┘ │
│  ┌────────────────────────┐  ┌────────────────────────┐  ┌───────────────────────────┐ │
│  │   Inventory Service    │  │   Recipe BOM Engine    │  │   Auth & Key Derivation   │ │
│  │   - Unit Normalization │  │   - Sale-Time Snapshot │  │   - Bcrypt Server Passwords│ │
│  │   - Ingredient Pack CF │  │   - Add-on Deductions  │  │   - PBKDF2 Client Deriv.  │ │
│  │   - Double-Entry Ledger│  │   - Stored GenCol Key  │  │   - ECDSA Lease Signer    │ │
│  └────────────────────────┘  └────────────────────────┘  └───────────────────────────┘ │
└──────────────────────────────────────────┬─────────────────────────────────────────────┘
                                           │
                                           ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              DATA ACCESS & INNODB ENGINE                               │
│  - Database.php (PDO Singleton, UTF8MB4, STRICT_ALL_TABLES, Emulation Off)             │
│  - Multi-Tenant Composite Foreign Keys: (restaurant_id, entity_id, [sub_id])           │
│  - Stored Generated Columns for Recipe Uniqueness                                      │
│  - Soft Deletes & Strict ON DELETE RESTRICT on Financial / Ledger Records             │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Sequence Tracking, Out-of-Order Synchronization & Timestamps

### 1. Handling Out-of-Order Sync Safely
In real-world mobile networks, batch retries or network routing can cause Order #10 to reach the server before Order #9. 
- **Rule:** We **do not** reject Order #9 merely because Order #10 arrived first.
- Every registered terminal has its sequences recorded in `pos_device_sequences`.
- If a sequence number has never been seen for that device, it is inserted and accepted.
- If a sequence number has already been seen:
  - If the stored `client_uuid` matches: It is recognized as an idempotent retry.
  - If the stored `client_uuid` is different: It is rejected and flagged as a **Sequence Reuse Conflict** (potential device counter corruption or replay attack).

```sql
CREATE TABLE `pos_device_sequences` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `sequence_number` BIGINT UNSIGNED NOT NULL,
  `client_uuid` VARCHAR(36) NOT NULL,
  `client_reported_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`restaurant_id`, `device_id`, `sequence_number`),
  UNIQUE KEY `uk_device_seq_uuid` (`restaurant_id`, `device_id`, `client_uuid`),
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Precise Timestamp Separation
The `orders` table tracks three distinct temporal markers:
1. `client_reported_at`: The local device timestamp when the cashier confirmed the order.
2. `server_received_at`: The server timestamp when the HTTP request arrived.
3. `synced_at`: The server timestamp when the MySQL database transaction committed.

---

## 3. Browser Storage Security & Device Revocation Model

### Application-Level Separation vs. Hardware Security
- **IndexedDB Isolation:** Naming databases `RestaurantPOS_DB_{tenant_id}_{device_uuid}` provides application-level segregation across tenants and devices within the same browser origin.
- **Security Reality:** It is **not** an OS-level cryptographic container or hardware TPM. Anyone with administrative operating system access or browser DevTools can inspect local storage. Client-side role checks and PIN verifications are operational UX controls; **all business authorization is authoritatively enforced on the server during sync**.

### Device Pairing, Credential Lifecycle & Emergency Recovery Workflow
1. During authenticated device pairing, generate a cryptographically random 256-bit token using a secure random generator. Display it once to the administrator/device, send it only over HTTPS, and store only its SHA-256 hash in `pos_devices.device_token_hash`. Do not seed a production token in SQL or source code.
2. Every sync/ping request must present the credential using a dedicated header (for example, `X-POS-Device-Token`) over HTTPS. Verify the hash with a constant-time comparison; never accept the UUID alone as authorization. Rotate the token when re-pairing and revoke the prior credential.
3. When an administrator revokes a device in the back-office (`pos_devices.status = 'revoked'`), any subsequent sync or ping request returns HTTP 403 `DEVICE_REVOKED`.
2. Upon receiving `DEVICE_REVOKED`:
   - The client application immediately wipes cached staff credentials and PIN salts from IndexedDB.
   - Any unsynchronized orders in `orders_queue` are **not** wiped. Instead, the POS automatically triggers an **Emergency Encrypted JSON Backup Download** prompt: *"This terminal has been deregistered. Unsynced transactions have been downloaded to a backup file for administrative audit."*

---

## 4. Complete Normalized Database Schema (MySQL 8+ / MariaDB)

Strict dependency order with complete composite foreign keys across all modules:

```sql
-- ============================================================================
-- 1. SAAS PLATFORM & TENANTS
-- ============================================================================

CREATE TABLE `platform_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('super_admin', 'support_agent', 'finance_admin') NOT NULL DEFAULT 'support_agent',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `business_registration_number` VARCHAR(50) NULL,
  `contact_email` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(30) NOT NULL,
  `currency_symbol` VARCHAR(10) NOT NULL DEFAULT 'PHP',
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Manila',
  `status` ENUM('pending_setup', 'active', 'suspended', 'cancelled', 'archived') NOT NULL DEFAULT 'pending_setup',
  `pos_primary_color` VARCHAR(20) DEFAULT '#0d6efd',
  `pos_secondary_color` VARCHAR(20) DEFAULT '#198754',
  `receipt_header` TEXT NULL,
  `receipt_footer` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_restaurants_status` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable server-owned catalog snapshots used by offline terminals.
-- Each bootstrap refers to a published version; never overwrite a published snapshot.
CREATE TABLE `restaurant_catalog_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `version_uuid` CHAR(36) NOT NULL,
  `snapshot_hash` CHAR(64) NOT NULL,
  `snapshot_json` JSON NOT NULL,
  `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_catalog_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_catalog_tenant_version` (`restaurant_id`, `version_uuid`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscription_plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_annually` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_terminals` SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  `max_products` INT UNSIGNED NOT NULL DEFAULT 100,
  `features_json` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscriptions` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `status` ENUM('trial', 'active', 'past_due', 'suspended', 'cancelled', 'expired') NOT NULL DEFAULT 'trial',
  `billing_cycle` ENUM('monthly', 'annually') NOT NULL DEFAULT 'monthly',
  `trial_ends_at` DATETIME NULL,
  `current_period_start` DATETIME NOT NULL,
  `current_period_end` DATETIME NOT NULL,
  `grace_period_days` INT UNSIGNED NOT NULL DEFAULT 7,
  `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
  `canceled_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sub_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT,
  INDEX `idx_subs_tenant_status` (`restaurant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `platform_invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `amount` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('draft', 'open', 'paid', 'uncollectible', 'void') NOT NULL DEFAULT 'open',
  `due_date` DATE NOT NULL,
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inv_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  -- Tenant-safe foreign key: can only reference subscriptions belonging to the same restaurant!
  FOREIGN KEY (`restaurant_id`, `subscription_id`) REFERENCES `subscriptions` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `platform_payments` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `payment_method` ENUM('card', 'gcash', 'paymaya', 'bank_transfer', 'manual_check') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
  `status` ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `raw_gateway_response` JSON NULL,
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  -- Tenant-safe foreign key: can only reference invoices belonging to the same restaurant!
  FOREIGN KEY (`restaurant_id`, `invoice_id`) REFERENCES `platform_invoices` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhook_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gateway` VARCHAR(50) NOT NULL,
  `event_id` VARCHAR(100) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `payload` JSON NOT NULL,
  `status` ENUM('pending', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL,
  `processed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_gateway_event` (`gateway`, `event_id`),
  INDEX `idx_webhook_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ROLES, PERMISSIONS, USERS & REGISTERED POS DEVICES
-- ============================================================================

CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_role_tenant_slug` (`restaurant_id`, `slug`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `code` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`restaurant_id`, `role_id`, `permission_id`),
  FOREIGN KEY (`restaurant_id`, `role_id`) REFERENCES `roles` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pos_devices` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_uuid` VARCHAR(36) NOT NULL,
  `device_name` VARCHAR(100) NOT NULL,
  `device_token_hash` CHAR(64) NOT NULL, -- SHA-256 of a random 256-bit device token; raw token is shown once only
  `credential_rotated_at` DATETIME NULL,
  `status` ENUM('active', 'revoked', 'pending') NOT NULL DEFAULT 'active',
  `last_sync_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dev_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_dev_tenant_uuid` (`restaurant_id`, `device_uuid`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pos_device_sequences` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `sequence_number` BIGINT UNSIGNED NOT NULL,
  `client_uuid` VARCHAR(36) NOT NULL,
  `client_reported_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`restaurant_id`, `device_id`, `sequence_number`),
  UNIQUE KEY `uk_device_seq_uuid` (`restaurant_id`, `device_id`, `client_uuid`),
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server-issued, device-bound offline leases. The signed token is verified independently;
-- the database row enables revocation, subscription checks, and audit/reconciliation.
CREATE TABLE `offline_authorization_leases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `lease_id` VARCHAR(50) NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED NOT NULL,
  `catalog_version_id` BIGINT UNSIGNED NOT NULL,
  `issued_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `grace_expires_at` DATETIME NOT NULL,
  `last_server_sequence_at_issue` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `signature_base64` TEXT NOT NULL,
  `revoked_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lease_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_lease_tenant_lease` (`restaurant_id`, `lease_id`),
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `subscription_id`) REFERENCES `subscriptions` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `catalog_version_id`) REFERENCES `restaurant_catalog_versions` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `offline_pin_salt` VARCHAR(64) NULL,
  `offline_pin_hash` VARCHAR(64) NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_user_tenant_username` (`restaurant_id`, `username`),
  FOREIGN KEY (`restaurant_id`, `role_id`) REFERENCES `roles` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pos_device_staff_authorizations` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `is_allowed_offline` TINYINT(1) NOT NULL DEFAULT 1,
  `authorized_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`restaurant_id`, `device_id`, `user_id`),
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABLES, SUPPLIERS, INGREDIENTS & UNIT CONVERSIONS
-- ============================================================================

CREATE TABLE `restaurant_tables` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `table_number` VARCHAR(20) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  `floor_zone` VARCHAR(50) DEFAULT 'Main Dining',
  `status` ENUM('available', 'occupied', 'reserved', 'cleaning') DEFAULT 'available',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tbl_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_tbl_tenant_number` (`restaurant_id`, `table_number`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sup_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredients` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `base_unit` ENUM('g', 'ml', 'pcs') NOT NULL,
  `cost_per_base_unit` DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
  `current_stock` DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
  `minimum_stock_alert` DECIMAL(16,4) NOT NULL DEFAULT 500.0000,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ing_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_ing_tenant_sku` (`restaurant_id`, `sku`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredient_unit_conversions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `multiplier_to_base` DECIMAL(14,6) NOT NULL,
  `is_purchase_unit` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uk_ing_unit` (`restaurant_id`, `ingredient_id`, `unit_name`),
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `po_number` VARCHAR(50) NOT NULL,
  `status` ENUM('draft', 'ordered', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `received_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_po_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_po_tenant_number` (`restaurant_id`, `po_number`),
  FOREIGN KEY (`restaurant_id`, `supplier_id`) REFERENCES `suppliers` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `created_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `quantity_purchased` DECIMAL(12,4) NOT NULL,
  `purchase_unit` VARCHAR(50) NOT NULL,
  `multiplier_to_base` DECIMAL(14,6) NOT NULL,
  `quantity_base` DECIMAL(16,4) NOT NULL,
  `unit_cost` DECIMAL(12,4) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`restaurant_id`, `purchase_order_id`) REFERENCES `purchase_orders` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. MENU: CATEGORIES, PRODUCTS, VARIANTS, ADDONS & RECIPES
-- ============================================================================

CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cat_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `barcode` VARCHAR(50) NULL,
  `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 12.00,
  `product_type` ENUM('direct', 'recipe') NOT NULL DEFAULT 'recipe',
  `direct_stock_ingredient_id` INT UNSIGNED NULL,
  `has_variants` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prod_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_prod_tenant_sku` (`restaurant_id`, `sku`),
  FOREIGN KEY (`restaurant_id`, `category_id`) REFERENCES `categories` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `direct_stock_ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_variants` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `additional_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_var_tenant_prod_id` (`restaurant_id`, `product_id`, `id`),
  UNIQUE KEY `uk_var_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`, `product_id`) REFERENCES `products` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_addon_groups` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grp_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_addon_group_assignments` (
  `restaurant_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`restaurant_id`, `product_id`, `group_id`),
  FOREIGN KEY (`restaurant_id`, `product_id`) REFERENCES `products` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `group_id`) REFERENCES `product_addon_groups` (`restaurant_id`, `id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_addons` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `ingredient_id` INT UNSIGNED NULL,
  `deduction_quantity` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_addon_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`, `group_id`) REFERENCES `product_addon_groups` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recipes` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `quantity_required` DECIMAL(14,4) NOT NULL,
  `recipe_variant_key` INT UNSIGNED GENERATED ALWAYS AS (COALESCE(variant_id, 0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recipe_spec` (`restaurant_id`, `product_id`, `recipe_variant_key`, `ingredient_id`),
  FOREIGN KEY (`restaurant_id`, `product_id`) REFERENCES `products` (`restaurant_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `product_id`, `variant_id`) REFERENCES `product_variants` (`restaurant_id`, `product_id`, `id`) ON DELETE CASCADE,
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. CASHIER SHIFTS, ORDERS & PAYMENTS
-- ============================================================================

CREATE TABLE `cashier_shifts` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `cashier_id` INT UNSIGNED NOT NULL,
  `opening_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `expected_cash` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash` DECIMAL(10,2) NULL,
  `cash_difference` DECIMAL(10,2) NULL,
  `status` ENUM('open', 'closed') DEFAULT 'open',
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shift_tenant_id` (`restaurant_id`, `id`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `cashier_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_movements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `shift_id` INT UNSIGNED NOT NULL,
  `cashier_id` INT UNSIGNED NOT NULL,
  `movement_type` ENUM('cash_in', 'cash_out', 'drop') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `shift_id`) REFERENCES `cashier_shifts` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `cashier_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `client_uuid` VARCHAR(36) NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `shift_id` INT UNSIGNED NULL,
  `order_number` VARCHAR(50) NOT NULL,
  `order_type` ENUM('dine_in', 'takeout', 'delivery') NOT NULL DEFAULT 'dine_in',
  `table_id` INT UNSIGNED NULL,
  `cashier_id` INT UNSIGNED NOT NULL,
  `order_status` ENUM('pending', 'preparing', 'ready', 'served', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid', 'partially_paid', 'paid', 'refunded') NOT NULL DEFAULT 'paid',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `is_offline_sync` TINYINT(1) NOT NULL DEFAULT 0,
  `offline_lease_id` VARCHAR(50) NULL,
  `device_sequence_number` BIGINT UNSIGNED NULL,
  `catalog_version_id` BIGINT UNSIGNED NULL,
  `canonical_payload_hash` CHAR(64) NULL, -- computed by the server from normalized transaction fields
  `offline_reconciliation_status` ENUM('not_applicable', 'verified', 'needs_review') NOT NULL DEFAULT 'not_applicable',
  `client_reported_at` DATETIME NOT NULL,
  `server_received_at` DATETIME NOT NULL,
  `synced_at` DATETIME NULL,
  `inventory_deducted_at` DATETIME NULL,
  `inventory_reversed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_tenant_client_uuid` (`restaurant_id`, `client_uuid`),
  UNIQUE KEY `uk_tenant_order_number` (`restaurant_id`, `order_number`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `offline_lease_id`) REFERENCES `offline_authorization_leases` (`restaurant_id`, `lease_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `catalog_version_id`) REFERENCES `restaurant_catalog_versions` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `shift_id`) REFERENCES `cashier_shifts` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `table_id`) REFERENCES `restaurant_tables` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `cashier_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `variant_name` VARCHAR(100) NULL,
  `quantity` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `recipe_snapshot_json` JSON NULL,
  `special_instructions` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_item_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_item_tenant_order_id` (`restaurant_id`, `order_id`, `id`),
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `product_id`) REFERENCES `products` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `product_id`, `variant_id`) REFERENCES `product_variants` (`restaurant_id`, `product_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_item_addons` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_item_id` INT UNSIGNED NOT NULL,
  `addon_id` INT UNSIGNED NOT NULL,
  `addon_name` VARCHAR(100) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `deduction_quantity_snapshot` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `ingredient_id_snapshot` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`restaurant_id`, `order_item_id`) REFERENCES `order_items` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `addon_id`) REFERENCES `product_addons` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_status_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30) NOT NULL,
  `to_status` VARCHAR(30) NOT NULL,
  `changed_by_user_id` INT UNSIGNED NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `changed_by_user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  INDEX `idx_history_tenant_order` (`restaurant_id`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `cashier_id` INT UNSIGNED NOT NULL,
  `payment_method` ENUM('cash', 'card', 'qr_online', 'other') NOT NULL DEFAULT 'cash',
  `amount` DECIMAL(10,2) NOT NULL,
  `tendered_amount` DECIMAL(10,2) NOT NULL,
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `transaction_reference` VARCHAR(100) NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pay_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_pay_tenant_order_id` (`restaurant_id`, `order_id`, `id`),
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `cashier_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. VOIDS, REFUNDS, INVENTORY MOVEMENTS, CONFLICTS & AUDIT LOGS
-- ============================================================================

CREATE TABLE `void_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `order_item_id` INT UNSIGNED NULL,
  `authorized_by_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `was_offline` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `order_id`, `order_item_id`) REFERENCES `order_items` (`restaurant_id`, `order_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `authorized_by_user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refund_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `authorized_by_user_id` INT UNSIGNED NOT NULL,
  `refund_method` ENUM('cash', 'original_payment', 'credit') NOT NULL DEFAULT 'cash',
  `reason` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `order_id`, `payment_id`) REFERENCES `payments` (`restaurant_id`, `order_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `authorized_by_user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_movements` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `reference_type` ENUM(
    'order_sale',
    'order_cancellation',
    'order_refund',
    'manual_adjustment',
    'purchase_receipt',
    'spoilage_waste'
  ) NOT NULL,
  `reference_id` INT UNSIGNED NULL,
  `quantity_change` DECIMAL(16,4) NOT NULL,
  `stock_before` DECIMAL(16,4) NOT NULL,
  `stock_after` DECIMAL(16,4) NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `created_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  INDEX `idx_movements_tenant_date` (`restaurant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_conflicts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `requested_quantity` DECIMAL(16,4) NOT NULL,
  `available_quantity` DECIMAL(16,4) NOT NULL,
  `status` ENUM('negative_stock_allowed', 'resolved', 'under_review') DEFAULT 'negative_stock_allowed',
  `conflict_notes` TEXT NOT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`, `order_id`) REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `resolved_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `general_audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `old_values_json` JSON NULL,
  `new_values_json` JSON NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sync_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED NOT NULL,
  `sync_type` ENUM('orders_batch', 'catalog_bootstrap') NOT NULL,
  `orders_synced_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `conflicts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `device_id`) REFERENCES `pos_devices` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Hardened Synchronization Engine Implementation

### Required validation rules

- Authenticate the registered device with a high-entropy random device token sent over HTTPS. Store only `SHA-256(token)` in `pos_devices.device_token_hash`; compare hashes with `hash_equals()`. The raw token is generated during device pairing, shown once, and can be rotated or revoked. A device UUID alone is never authentication.
- Compute `canonical_payload_hash` on the server from a validated, normalized whitelist of transaction fields. Never accept a client-supplied hash as proof. Preserve array order for order lines, sort associative object keys recursively, normalize decimal values as strings, and exclude transport-only fields.
- A repeated `(restaurant_id, client_uuid)` is acknowledged only when the server-computed hash matches the stored hash. The hash covers the lease ID, sequence, catalog version, order type/table, ordered line items, variants, add-ons, sale-time amounts, discounts, taxes, payment/tender/change details, and authorized overrides after normalization. If the UUID is reused with different contents, return an `IDEMPOTENCY_CONFLICT`, log it, and require review.
- Validate `offline_lease_id`, device/restaurant binding, lease signature, revocation state, sequence number, and the immutable server-owned `catalog_version_id`. The client-reported sale timestamp is evidence for reconciliation, not proof of the true physical sale time.
- Recalculate totals and ingredient deductions against the immutable catalog snapshot when possible. Never trust client-provided stock deductions. If an offline cash transaction is plausible but a value cannot be verified, preserve the submitted sale record with `offline_reconciliation_status = 'needs_review'`; do not silently change totals or discard cash sales. Keep it out of “verified” reporting until reviewed.
- Commit per order. Add UUIDs and that order's conflict details to the response only after its transaction commits. On a duplicate-key race, roll back first, read the existing order, and compare its canonical hash. Never treat an unrelated unique-key violation as a successful duplicate.

### Illustrative service flow

The following is a design-level implementation outline; helper methods must be implemented and covered by automated tests as part of Phase 6. Error details are logged server-side and never returned verbatim to clients.

```php
public function processBatch(
    int $restaurantId,
    string $deviceUuid,
    string $presentedDeviceToken,
    array $orders
): array {
    $syncedUuids = [];
    $failedOrders = [];
    $allConflicts = [];

    $device = $this->findActiveDevice($restaurantId, $deviceUuid);
    if (!$device || $device['status'] !== 'active') {
        return ['status' => 'error', 'code' => 'DEVICE_REVOKED'];
    }

    // Device UUID alone is not sufficient. Store only a SHA-256 hash of a
    // cryptographically random 256-bit token on the server.
    $presentedHash = hash('sha256', $presentedDeviceToken);
    if (!hash_equals($device['device_token_hash'], $presentedHash)) {
        return ['status' => 'error', 'code' => 'DEVICE_AUTH_FAILED'];
    }

    foreach ($orders as $orderData) {
        $clientUuid = $orderData['client_uuid'] ?? null;
        if (!is_string($clientUuid) || !$this->isValidUuidV4($clientUuid)) {
            $failedOrders[] = [
                'client_uuid' => is_string($clientUuid) ? $clientUuid : null,
                'code' => 'INVALID_CLIENT_UUID'
            ];
            continue;
        }

        $orderConflicts = []; // Never leak conflicts from an uncommitted order.
        $payloadHash = null;

        try {
            $normalized = $this->validateAndNormalizePayload(
                $restaurantId,
                (int)$device['id'],
                $orderData
            );
            $payloadHash = $this->computeCanonicalPayloadHash($normalized);

            $this->db->beginTransaction();

            $existing = $this->findOrderByClientUuidForUpdate($restaurantId, $clientUuid);
            if ($existing) {
                $this->db->commit(); // No-op transaction ends here.
                if (isset($existing['canonical_payload_hash']) && hash_equals($existing['canonical_payload_hash'], $payloadHash)) {
                    $syncedUuids[] = $clientUuid;
                } else {
                    $this->recordIdempotencyConflict($restaurantId, (int)$device['id'], $clientUuid);
                    $failedOrders[] = [
                        'client_uuid' => $clientUuid,
                        'code' => 'IDEMPOTENCY_CONFLICT'
                    ];
                }
                continue;
            }

            // Checks and writes below must be in the same per-order transaction:
            // lease/device/catalog validation, sequence claim, order, items/add-ons,
            // payment, inventory movements, conflict rows, and status history.
            $lease = $this->lockAndValidateLease($restaurantId, (int)$device['id'], $normalized);
            $this->claimDeviceSequenceOrThrow($restaurantId, (int)$device['id'], $normalized, $clientUuid);

            // Verify submitted line items against the immutable server-owned catalog
            // version. Recalculate totals and stock deductions from server data.
            // If legitimate cash-sale details cannot be fully verified, persist the
            // record as needs_review and preserve the submitted values for audit.
            $validatedOrder = $this->validateAgainstCatalogAndRecalculate($restaurantId, $lease, $normalized);
            $orderId = $this->insertOrderRecord(
                $restaurantId,
                (int)$device['id'],
                $validatedOrder,
                $payloadHash
            );
            $this->insertOrderItemsAndAddons($restaurantId, $orderId, $validatedOrder);
            $this->insertPayments($restaurantId, $orderId, $validatedOrder['payment'] ?? []);
            $orderConflicts = $this->deductInventoryFromServerRecipeSnapshot(
                $restaurantId,
                $orderId,
                $validatedOrder
            );
            $initialStatus = $validatedOrder['order_status'] ?? 'pending';
            $this->logStatusTransition($restaurantId, $orderId, 'created', $initialStatus, $validatedOrder['cashier_id'], 'Offline transaction synchronized');

            $this->db->commit();
            $syncedUuids[] = $clientUuid;
            $allConflicts = array_merge($allConflicts, $orderConflicts);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // If a concurrent request won a unique-key race, acknowledge only after
            // re-reading the committed order and comparing the normalized payload hash.
            $existing = $this->findOrderByClientUuid($restaurantId, $clientUuid);
            if ($existing && $payloadHash !== null &&
                isset($existing['canonical_payload_hash']) && hash_equals($existing['canonical_payload_hash'], $payloadHash)) {
                $syncedUuids[] = $clientUuid;
                continue;
            }

            error_log('Offline sync failure [' . $clientUuid . ']: ' . $e->getMessage());
            $failedOrders[] = [
                'client_uuid' => $clientUuid,
                'code' => $this->safePublicErrorCode($e) // Maps known domain errors to a small allowlist; otherwise ORDER_SYNC_FAILED
            ];
        }
    }

    return [
        'status' => 'completed_with_possible_failures',
        'synced_uuids' => $syncedUuids,
        'failed' => $failedOrders,
        'conflicts' => $allConflicts
    ];
}
```

The method names above define required service responsibilities, not additional public endpoints. The implementation must not assume a `SELECT ... FOR UPDATE` on a missing row alone prevents races; unique constraints remain the final concurrency guard, followed by rollback, re-read, and hash comparison. The `safePublicErrorCode()` helper must return only documented client-safe codes and must never forward exception messages.

---

## 6. Two-Tenant Verification & Integrity Test Suite (MySQL/MariaDB)

This suite describes required verification scenarios for two test restaurants. The SQL fragments below are examples, not a single unguarded script. Implement them as repeatable integration tests with isolated fixtures, assertion of expected errors, and cleanup/rollback so one expected failure does not stop all subsequent tests. Use two test restaurants:
- `restaurant_id = 1`: **Mang Juan's Carinderia**
- `restaurant_id = 2`: **Aling Nena's Binalot**

```sql
-- ============================================================================
-- VERIFICATION TEST SUITE (Run against fresh database with seed data)
-- ============================================================================

-- TEST 1: Cross-Tenant Order Item Rejection
-- Attempt to insert an order in Restaurant 1 referencing a product from Restaurant 2:
-- MUST FAIL with MySQL error 1452: Cannot add or update a child row (foreign key constraint fails)
INSERT INTO order_items (restaurant_id, order_id, product_id, product_name, quantity, unit_price, subtotal)
VALUES (1, 1, 102, 'Aling Nena Kare-Kare', 1.00, 180.00, 180.00);

-- TEST 2: Cross-Tenant Table Assignment Rejection
-- Attempt to assign a Restaurant 2 table to an order in Restaurant 1:
-- MUST FAIL with foreign key constraint violation:
UPDATE orders SET table_id = 201 WHERE id = 1 AND restaurant_id = 1;

-- TEST 3: Cross-Tenant Invoice & Subscription Integrity
-- Attempt to create an invoice in Restaurant 1 referencing a subscription from Restaurant 2:
-- MUST FAIL with foreign key constraint violation:
INSERT INTO platform_invoices (restaurant_id, subscription_id, invoice_number, amount, total_amount, due_date)
VALUES (1, 2, 'INV-TEST-FAIL-01', 999.00, 999.00, CURDATE());

-- TEST 4: Base Recipe Duplicate Prevention (NULL variant_id with Stored GenCol)
-- Insert base recipe ingredient (variant_id IS NULL)
INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
VALUES (1, 1, NULL, 1, 250.0000);
-- Re-attempt inserting exact same base recipe ingredient (MUST FAIL with Duplicate entry 'uk_recipe_spec'):
INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
VALUES (1, 1, NULL, 1, 250.0000);

-- TEST 5: Sequence Tracking & Out-of-Order Synchronization
-- Sequence #10 arrives first:
INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
VALUES (1, 1, 10, '00000000-0000-4000-8000-000000000010', NOW());
-- Earlier sequence #9 arrives later (MUST SUCCEED without error):
INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
VALUES (1, 1, 9, '00000000-0000-4000-8000-000000000009', NOW());
-- Sequence #10 reused with a DIFFERENT UUID (MUST FAIL with Duplicate entry on PRIMARY KEY):
INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
VALUES (1, 1, 10, 'ffffffff-ffff-4fff-8fff-ffffffffffff', NOW());

-- TEST 6: Soft Delete Safety & Zero-Cascade Protection
-- Soft-deleting a table preserves all historical orders referencing it:
UPDATE restaurant_tables SET deleted_at = NOW() WHERE id = 1 AND restaurant_id = 1;
-- Order remains intact and queryable:
SELECT id, order_number, table_id FROM orders WHERE id = 1;
```

### Additional application-level security and reconciliation tests

In addition to the SQL examples above, the automated test suite must cover:

1. **Device credential:** correct token succeeds; wrong token, rotated token, revoked device, and a device paired to another tenant fail.
2. **Refund/order matching:** a refund pointing to a payment from a different order in the same restaurant fails; valid partial refunds cannot exceed the refundable amount.
3. **Void/item matching:** an item-level void pointing to an order item from a different order fails.
4. **Idempotency payload:** same UUID and same normalized payload is acknowledged once; same UUID with changed quantity, price, tender, discount, or item is rejected as `IDEMPOTENCY_CONFLICT`.
5. **Sequence reuse and ordering:** out-of-order unique sequences succeed; same sequence plus same UUID is an idempotent retry; same sequence with a different UUID is flagged.
6. **Partial failures:** if an order fails after its inventory conflict rows have been calculated, both the order and its conflict rows roll back; later independent orders in the batch can still be processed.
7. **Sale validation:** a transaction matching its immutable catalog version is recalculated consistently; manipulated totals or deductions are flagged/rejected according to policy; no client-supplied stock deduction is used as authority.
8. **Offline expiry:** a valid lease created while a subscription was active can be reviewed and synchronized after reconnection; an untrusted client timestamp alone does not prove eligibility.

Expected-failure SQL statements should be executed by a test harness that captures the expected foreign-key/unique-constraint error and rolls back the test transaction. Do not run failure examples as an unguarded seed script.

---

## 7. Explicit Documentation of System Scope & Final Limitations

1. **Browser Sandboxing Boundary:**
   - IndexedDB is segregated per origin and database name (`RestaurantPOS_DB_{tenant}_{device}`).
   - While effective for multi-tenant and multi-device partition on shared browser profiles, browser local storage cannot prevent someone with physical device root/DevTools access from tampering with local memory.
   - **Mitigation:** Server-authoritative device authentication, idempotency hashes, monotonic sequence tracking, immutable catalog versions, and signed lease validation gate synchronization.
   - **Important limitation:** These controls detect many inconsistencies but cannot prove, with a browser alone, that a reported offline cash sale physically occurred at the exact client-reported time. When evidence conflicts, preserve the transaction for reconciliation and flag it for review; do not silently alter or automatically treat it as verified.
2. **Offline Payment Restrictions:**
   - **Cash transactions only** are verified offline.
   - Online payment processing (GCash, PayMaya, Visa/Mastercard gateway authorizations) requires live HTTP communication with payment gateways and is strictly disabled while offline.
3. **Multi-Device Offline Peer Synchronization (Deferred to Phase 8):**
   - In Phase 1–7, each device operates offline against its own local IndexedDB queue.
   - Real-time offline table state sharing between multiple cashier tablets without an internet connection or local LAN server is a Phase 8 architecture roadmap item.
4. **Hardware Thermal Printing:**
   - Universal baseline is standard browser print (`window.print()` with CSS `@media print` 80mm/58mm).
   - Direct WebUSB/Web Serial ESC/POS raw byte printing is supported on Chromium-based desktop and Android browsers, but is not supported on iOS Safari (WebKit restriction).

---

## 8. Phased Implementation Roadmap

```
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 1: Multi-Tenant SaaS Foundation & Normalized Schema               │
│ - Verified MySQL schema.sql & seed.sql execution.                      │
│ - Database.php PDO singleton with strict UTF8MB4 & error handling.     │
│ - Multi-tenant resolver, SessionManager, CSRF token engine.            │
│ - Base Bootstrap 5 layout, login page, and XAMPP verification.         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 2: Menu, Variants, Add-ons & Unit-Standardized Inventory Ledger  │
│ - Unit conversion engine (g, ml, pcs) and ingredient packaging factors.│
│ - Categories, Products, Variants, and Add-on group assignments.        │
│ - Recipe BOM builder with stored generated column key uniqueness.      │
│ - Double-entry inventory movements ledger with stock-in form.          │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 3: Tables, Device Registration & Cashier Shift Sessions          │
│ - Soft-deletable table management with floor zones.                    │
│ - Device registration & staff authorization mapping.                   │
│ - Cashier shift sessions (open float, closing audit, cash movements).  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 4: PWA Service Worker & Isolated IndexedDB Layer                 │
│ - Service Worker (sw.js) precaching app shell & static assets only.    │
│ - Isolated IndexedDB (RestaurantPOS_DB_{tenant}_{device}).             │
│ - Bootstrap sync endpoint delivering targeted staff credentials.       │
│ - Emergency JSON export for unsynced transactions.                     │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 5: Offline POS Terminal, PBKDF2 Auth & Thermal Receipts          │
│ - Touch-screen POS UI with variant/addon drawers.                      │
│ - PBKDF2-HMAC-SHA256 offline PIN derivation & 5-attempt lockout.       │
│ - Local order queueing with client UUIDv4 and sale-time snapshots.     │
│ - Offline receipt generation (80mm/58mm CSS print layout).             │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 6: Idempotent Sync, Race Protection & Inventory Conflict Engine  │
│ - Per-order ACID sync engine with SELECT ... FOR UPDATE deduplication. │
│ - Inventory conflict resolution & negative stock manager alerts.       │
│ - Valid-sale sync post-subscription expiry verification.               │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ PHASE 7: SaaS Billing, Reports, Chart.js & Production Hardening        │
│ - Subscription plans, invoices, and webhook handling.                  │
│ - Chart.js sales breakdown, cashier performance, and tax analytics.    │
│ - Production cPanel/HTTPS deployment audit.                            │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Awaiting Approval to Begin Phase 1

The final review corrections are incorporated:
- 100% tenant-safe composite foreign keys across platform invoices, payments, audit logs, voids, and conflicts.
- Sequence-aware out-of-order synchronization tracking via `pos_device_sequences`.
- Hardened sync engine with per-order conflict isolation, safe duplicate-key rollback, and private error logging.
- Honest browser security disclosures with device revocation and emergency recovery.
- Complete two-tenant test suite verifying foreign key boundaries and race condition handling.
- Secure administrator setup with password change enforcement.
- Registered device token authentication, rotation, and revocation; device UUID alone is not trusted.
- Server-computed canonical payload hashes for idempotency and explicit handling of mismatched retries.
- Same-order foreign key integrity for refunds and item-level voids.
- Immutable server-owned catalog versions and a review state for offline transactions whose details cannot be independently verified.

**Approval gate:** Treat the code shown in the plan as an architectural outline, not proof that the system has already been implemented or tested. Before proceeding beyond Phase 1, import the actual generated `schema.sql` and `seed.sql` into a clean XAMPP database, execute the tests, and review the results. Implement Phase 1 only after approval; then wait for successful local testing before starting Phase 2.
