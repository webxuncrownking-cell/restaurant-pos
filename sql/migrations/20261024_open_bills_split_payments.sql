-- Additive migration for Open Bills, partial payments, and equal-share bill splitting.
-- Safe for existing data: it does not change or delete existing orders/payment rows.
-- The application can also apply this migration from the Open Bills page.

ALTER TABLE payments ADD COLUMN client_payment_uuid VARCHAR(36) NULL;
CREATE UNIQUE INDEX uk_payment_client_uuid ON payments (restaurant_id, client_payment_uuid);

CREATE TABLE IF NOT EXISTS order_bill_splits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  split_number SMALLINT UNSIGNED NOT NULL,
  split_label VARCHAR(100) NOT NULL,
  amount_due DECIMAL(10,2) NOT NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bill_split_tenant_id (restaurant_id, id),
  UNIQUE KEY uk_bill_split_tenant_order_id (restaurant_id, order_id, id),
  UNIQUE KEY uk_bill_split_order_number (restaurant_id, order_id, split_number),
  FOREIGN KEY (restaurant_id, order_id) REFERENCES orders (restaurant_id, id) ON DELETE RESTRICT,
  FOREIGN KEY (restaurant_id, created_by_user_id) REFERENCES users (restaurant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bill_split_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  split_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  allocated_quantity DECIMAL(8,2) NOT NULL,
  allocated_amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bill_split_item_once (restaurant_id, order_id, order_item_id),
  KEY idx_bill_split_items_share (restaurant_id, order_id, split_id),
  FOREIGN KEY (restaurant_id, order_id, split_id) REFERENCES order_bill_splits (restaurant_id, order_id, id) ON DELETE RESTRICT,
  FOREIGN KEY (restaurant_id, order_id, order_item_id) REFERENCES order_items (restaurant_id, order_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bill_split_payment_allocations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  split_id INT UNSIGNED NOT NULL,
  payment_id INT UNSIGNED NOT NULL,
  allocated_amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bill_allocation_split_payment (restaurant_id, split_id, payment_id),
  KEY idx_bill_allocation_order (restaurant_id, order_id),
  FOREIGN KEY (restaurant_id, order_id, split_id) REFERENCES order_bill_splits (restaurant_id, order_id, id) ON DELETE RESTRICT,
  FOREIGN KEY (restaurant_id, order_id, payment_id) REFERENCES payments (restaurant_id, order_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
