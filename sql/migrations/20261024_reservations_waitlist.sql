-- Additive, non-destructive migration for Reservations & Waiting List.
-- Run through the application button or execute this SQL once in the existing restaurant_pos database.
-- CREATE TABLE IF NOT EXISTS preserves all existing restaurant, menu, and sales data.

CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `party_size` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `reservation_at` DATETIME NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  `table_id` INT UNSIGNED NULL,
  `status` ENUM('booked','seated','cancelled','no_show') NOT NULL DEFAULT 'booked',
  `notes` VARCHAR(500) NULL,
  `created_by_user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reservations_tenant_time` (`restaurant_id`, `reservation_at`, `status`),
  KEY `idx_reservations_tenant_table` (`restaurant_id`, `table_id`, `status`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `table_id`) REFERENCES `restaurant_tables` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `created_by_user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `waitlist_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `party_size` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `estimated_wait_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('waiting','seated','left','cancelled') NOT NULL DEFAULT 'waiting',
  `table_id` INT UNSIGNED NULL,
  `notes` VARCHAR(500) NULL,
  `created_by_user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `seated_at` DATETIME NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_waitlist_tenant_status` (`restaurant_id`, `status`, `created_at`),
  FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `table_id`) REFERENCES `restaurant_tables` (`restaurant_id`, `id`) ON DELETE RESTRICT,
  FOREIGN KEY (`restaurant_id`, `created_by_user_id`) REFERENCES `users` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
