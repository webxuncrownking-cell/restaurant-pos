-- ============================================================================
-- RESTAURANT POS & MANAGEMENT SAAS - DATABASE SCHEMA
-- Compatible with MySQL 8.0+ and MariaDB 10.4+ (InnoDB Engine, utf8mb4)
-- Enforces 100% Tenant-Safe Composite Foreign Keys
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. SAAS PLATFORM & TENANTS
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS `sync_logs`;
DROP TABLE IF EXISTS `general_audit_logs`;
DROP TABLE IF EXISTS `inventory_conflicts`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `refund_records`;
DROP TABLE IF EXISTS `void_records`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_item_addons`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cash_movements`;
DROP TABLE IF EXISTS `cashier_shifts`;
DROP TABLE IF EXISTS `recipes`;
DROP TABLE IF EXISTS `product_addons`;
DROP TABLE IF EXISTS `product_addon_group_assignments`;
DROP TABLE IF EXISTS `product_addon_groups`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `ingredient_unit_conversions`;
DROP TABLE IF EXISTS `ingredients`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `restaurant_tables`;
DROP TABLE IF EXISTS `pos_device_staff_authorizations`;
DROP TABLE IF EXISTS `pos_device_sequences`;
DROP TABLE IF EXISTS `offline_authorization_leases`;
DROP TABLE IF EXISTS `restaurant_catalog_versions`;
DROP TABLE IF EXISTS `pos_devices`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `webhook_events`;
DROP TABLE IF EXISTS `platform_payments`;
DROP TABLE IF EXISTS `platform_invoices`;
DROP TABLE IF EXISTS `subscriptions`;
DROP TABLE IF EXISTS `subscription_plans`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `platform_users`;

SET FOREIGN_KEY_CHECKS = 1;

-- Platform Superadmins (SaaS Operator Level)
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

-- Tenant Restaurants
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

-- Immutable Server-Owned Catalog Snapshots
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

-- SaaS Subscription Pricing Plans
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

-- Restaurant Subscriptions
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

-- Platform Billing Invoices (Tenant-Safe Composite FK)
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
  FOREIGN KEY (`restaurant_id`, `subscription_id`) REFERENCES `subscriptions` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform Payments (Tenant-Safe Composite FK)
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
  FOREIGN KEY (`restaurant_id`, `invoice_id`) REFERENCES `platform_invoices` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent Payment Webhook Events
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

-- ----------------------------------------------------------------------------
-- 2. ROLES, PERMISSIONS, STAFF & REGISTERED POS DEVICES
-- ----------------------------------------------------------------------------

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
  `device_token_hash` CHAR(64) NOT NULL,
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

-- ----------------------------------------------------------------------------
-- 3. TABLES, SUPPLIERS, INGREDIENTS & UNIT CONVERSIONS
-- ----------------------------------------------------------------------------

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

-- ----------------------------------------------------------------------------
-- 4. MENU: CATEGORIES, PRODUCTS, VARIANTS, ADDONS & RECIPES
-- ----------------------------------------------------------------------------

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

-- Recipe Bill of Materials with Stored Generated Column (Handles NULL variant_id uniqueness)
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

-- ----------------------------------------------------------------------------
-- 5. CASHIER SHIFTS, CASH MOVEMENTS, ORDERS & PAYMENTS
-- ----------------------------------------------------------------------------

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
  `canonical_payload_hash` CHAR(64) NULL,
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

-- ----------------------------------------------------------------------------
-- 6. VOIDS, REFUNDS, INVENTORY MOVEMENTS, CONFLICTS & AUDIT LOGS
-- ----------------------------------------------------------------------------

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
