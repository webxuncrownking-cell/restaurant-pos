-- ServeFlow POS: one-time activation and manual payment verification
-- Safe additive migration. Does not drop, recreate, or rewrite existing operational tables.
-- Run once against the production database after taking a database backup.

ALTER TABLE restaurants
  ADD COLUMN activation_status ENUM('pending','activated','rejected') NOT NULL DEFAULT 'activated' AFTER status,
  ADD COLUMN activation_fee DECIMAL(10,2) NOT NULL DEFAULT 399.00 AFTER activation_status,
  ADD COLUMN activated_at DATETIME NULL AFTER activation_fee,
  ADD COLUMN activation_verified_by INT UNSIGNED NULL AFTER activated_at;

-- Existing restaurants are grandfathered as activated. New registrations explicitly set pending.
UPDATE restaurants
SET activation_status = 'activated',
    activated_at = COALESCE(activated_at, created_at)
WHERE activation_status = 'activated' AND activated_at IS NULL;

CREATE TABLE IF NOT EXISTS activation_payment_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('gcash','bank_transfer','cash_deposit','other') NOT NULL,
  transaction_reference VARCHAR(120) NOT NULL,
  payer_name VARCHAR(120) NOT NULL,
  proof_file_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_by INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  reviewer_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activation_submission_restaurant (restaurant_id, status, created_at),
  KEY idx_activation_submission_status (status, created_at),
  CONSTRAINT fk_activation_submission_restaurant
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_activation_submission_user
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_activation_submission_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES platform_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_platform_settings_user
    FOREIGN KEY (updated_by) REFERENCES platform_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_settings (setting_key, setting_value)
VALUES
  ('activation_fee_php', '399.00'),
  ('activation_payment_instructions', 'Contact ServeFlow support for current payment instructions.')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
