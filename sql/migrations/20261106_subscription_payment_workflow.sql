-- ServeFlow POS: manual subscription payment verification
-- Run after 20261105_activation_workflow.sql. Additive only.
CREATE TABLE IF NOT EXISTS subscription_payment_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurant_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  billing_cycle ENUM('monthly','annually') NOT NULL DEFAULT 'monthly',
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('gcash','bank_transfer','cash_deposit','other') NOT NULL,
  transaction_reference VARCHAR(120) NOT NULL,
  payer_name VARCHAR(120) NOT NULL,
  proof_file_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  pending_restaurant_id INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN restaurant_id ELSE NULL END) STORED,
  submitted_by INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  reviewer_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_one_pending_subscription_payment (pending_restaurant_id),
  KEY idx_subscription_payment_restaurant (restaurant_id,status,created_at),
  KEY idx_subscription_payment_status (status,created_at),
  CONSTRAINT fk_subscription_payment_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_payment_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_payment_user FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_payment_reviewer FOREIGN KEY (reviewed_by) REFERENCES platform_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
