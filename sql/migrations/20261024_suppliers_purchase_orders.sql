-- Safe additive migration for Supplier & Purchase Order Management.
-- All statements use CREATE TABLE IF NOT EXISTS. No stock, sales, or order rows are changed.

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  KEY `idx_supplier_active` (`restaurant_id`, `is_active`, `name`),
  CONSTRAINT `fk_supplier_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  KEY `idx_po_tenant_status` (`restaurant_id`, `status`, `created_at`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`restaurant_id`, `supplier_id`) REFERENCES `suppliers` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_po_creator` FOREIGN KEY (`restaurant_id`, `created_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  KEY `idx_po_item_tenant_order` (`restaurant_id`, `purchase_order_id`),
  CONSTRAINT `fk_po_item_order` FOREIGN KEY (`restaurant_id`, `purchase_order_id`) REFERENCES `purchase_orders` (`restaurant_id`, `id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_item_ingredient` FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
