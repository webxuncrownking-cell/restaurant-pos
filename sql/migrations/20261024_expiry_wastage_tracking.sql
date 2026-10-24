-- Safe additive migration for ingredient expiry batches and wastage accounting.
-- Existing stock balances, orders, purchase orders, and inventory movements are preserved.

CREATE TABLE IF NOT EXISTS `ingredient_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `purchase_order_item_id` INT UNSIGNED NOT NULL,
  `batch_code` VARCHAR(100) NULL,
  `received_quantity` DECIMAL(16,4) NOT NULL,
  `remaining_quantity` DECIMAL(16,4) NOT NULL,
  `cost_per_base_unit` DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
  `expiry_date` DATE NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ingredient_batch_tenant_id` (`restaurant_id`, `id`),
  UNIQUE KEY `uk_ingredient_batch_po_line` (`restaurant_id`, `purchase_order_item_id`),
  KEY `idx_ingredient_batch_expiry` (`restaurant_id`, `expiry_date`, `remaining_quantity`),
  KEY `idx_ingredient_batch_stock` (`restaurant_id`, `ingredient_id`, `remaining_quantity`),
  CONSTRAINT `fk_ingredient_batch_ingredient` FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ingredient_batch_purchase_order` FOREIGN KEY (`restaurant_id`, `purchase_order_id`) REFERENCES `purchase_orders` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ingredient_batch_creator` FOREIGN KEY (`restaurant_id`, `created_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ingredient_waste_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `quantity_base` DECIMAL(16,4) NOT NULL,
  `unit_cost` DECIMAL(14,6) NOT NULL,
  `total_cost` DECIMAL(12,2) NOT NULL,
  `reason` ENUM('expired','spoiled','damaged','contaminated','other') NOT NULL,
  `notes` VARCHAR(255) NULL,
  `recorded_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ingredient_waste_recent` (`restaurant_id`, `created_at`),
  KEY `idx_ingredient_waste_batch` (`restaurant_id`, `batch_id`),
  CONSTRAINT `fk_ingredient_waste_batch` FOREIGN KEY (`restaurant_id`, `batch_id`) REFERENCES `ingredient_batches` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ingredient_waste_ingredient` FOREIGN KEY (`restaurant_id`, `ingredient_id`) REFERENCES `ingredients` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ingredient_waste_user` FOREIGN KEY (`restaurant_id`, `recorded_by`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
