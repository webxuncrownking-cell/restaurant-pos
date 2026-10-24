-- QR Customer Order Status Tracking
-- Additive migration. Existing orders and kitchen statuses are preserved.
CREATE TABLE IF NOT EXISTS `qr_order_tracking` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `table_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_order_tracking_token` (`token_hash`),
  UNIQUE KEY `uq_qr_order_tracking_order` (`restaurant_id`, `order_id`),
  KEY `idx_qr_order_tracking_table` (`restaurant_id`, `table_id`, `order_id`),
  CONSTRAINT `fk_qr_tracking_order` FOREIGN KEY (`restaurant_id`, `order_id`)
    REFERENCES `orders` (`restaurant_id`, `id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_tracking_table` FOREIGN KEY (`restaurant_id`, `table_id`)
    REFERENCES `restaurant_tables` (`restaurant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
