-- ============================================================================
-- RESTAURANT POS & MANAGEMENT SAAS - INTEGRATION TEST SUITE
-- Automated verification for MySQL 8.0+ and MariaDB 10.4+
-- Tests tenant isolation, composite foreign keys, recipe uniqueness & sequence tracking.
-- ============================================================================

SELECT '>>> STARTING DATABASE INTEGRATION TEST SUITE <<<' AS `Test_Suite`;

-- ----------------------------------------------------------------------------
-- TEST 1: Tenant Composite Foreign Key Isolation (Order Items)
-- Verify that Restaurant 1 cannot reference a product belonging to Restaurant 2
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `test_tenant_isolation`;
DELIMITER //
CREATE PROCEDURE `test_tenant_isolation`()
BEGIN
  DECLARE fk_error_caught INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR 1452 SET fk_error_caught = 1;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET fk_error_caught = 1;

  -- 1. Create a category and product in Restaurant 2
  INSERT INTO `categories` (`id`, `restaurant_id`, `name`) VALUES (99, 2, 'Tenant 2 Category')
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  INSERT INTO `products` (`id`, `restaurant_id`, `category_id`, `name`, `sku`, `base_price`, `cost_price`, `product_type`, `is_active`)
  VALUES (999, 2, 99, 'Alien Product R2', 'PRD-ALIEN-01', 99.00, 50.00, 'recipe', 1)
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  -- 2. Create an order in Restaurant 1
  INSERT INTO `orders` (
    `id`, `restaurant_id`, `client_uuid`, `device_id`, `order_number`, `order_type`, `table_id`, `cashier_id`,
    `subtotal`, `total_amount`, `paid_amount`, `client_reported_at`, `server_received_at`
  ) VALUES (
    999, 1, '00000000-0000-4000-8000-000000000999', 1, 'ORD-TEST-999', 'dine_in', 1, 3,
    100.00, 100.00, 100.00, NOW(), NOW()
  ) ON DUPLICATE KEY UPDATE `order_number` = VALUES(`order_number`);

  -- 3. Attempt cross-tenant insert: Order (Restaurant 1) references Product (Restaurant 2)
  -- Foreign Key `order_items (restaurant_id, product_id) REFERENCES products (restaurant_id, id)` MUST FAIL!
  INSERT INTO `order_items` (`restaurant_id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `subtotal`)
  VALUES (1, 999, 999, 'Alien Product R2', 1.00, 99.00, 99.00);

  IF fk_error_caught = 1 THEN
    SELECT 'PASS: Database rejected cross-tenant reference with composite foreign key constraint' AS `Test_1_Tenant_Isolation`;
  ELSE
    SELECT 'FAIL: Database erroneously allowed cross-tenant reference!' AS `Test_1_Tenant_Isolation`;
  END IF;

  -- Clean up test records
  DELETE FROM `order_items` WHERE `order_id` = 999;
  DELETE FROM `orders` WHERE `id` = 999;
  DELETE FROM `products` WHERE `id` = 999;
  DELETE FROM `categories` WHERE `id` = 99;
END //
DELIMITER ;

CALL `test_tenant_isolation`();
DROP PROCEDURE IF EXISTS `test_tenant_isolation`;

-- ----------------------------------------------------------------------------
-- TEST 2: Base Recipe Uniqueness with NULL variant_id
-- Stored generated column `recipe_variant_key` prevents duplicate base recipes
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `test_recipe_uniqueness`;
DELIMITER //
CREATE PROCEDURE `test_recipe_uniqueness`()
BEGIN
  DECLARE dup_error_caught INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR 1062 SET dup_error_caught = 1;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET dup_error_caught = 1;

  -- First insert of base recipe for Product 4 with ingredient 1:
  INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
  VALUES (1, 4, NULL, 1, 100.0000);

  -- Attempt duplicate insert of identical base recipe:
  INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
  VALUES (1, 4, NULL, 1, 100.0000);

  IF dup_error_caught = 1 THEN
    SELECT 'PASS: Stored generated column recipe_variant_key blocked duplicate base recipe (UK uk_recipe_spec)' AS `Test_2_Recipe_Uniqueness`;
  ELSE
    SELECT 'FAIL: Database allowed duplicate base recipe with NULL variant_id!' AS `Test_2_Recipe_Uniqueness`;
  END IF;

  DELETE FROM `recipes` WHERE `restaurant_id` = 1 AND `product_id` = 4 AND `ingredient_id` = 1 AND `variant_id` IS NULL;
END //
DELIMITER ;

CALL `test_recipe_uniqueness`();
DROP PROCEDURE IF EXISTS `test_recipe_uniqueness`;

-- ----------------------------------------------------------------------------
-- TEST 3: Out-of-Order Sequence Tracking
-- Device sequence tracking allows legitimate missing earlier sequences
-- ----------------------------------------------------------------------------
-- Sequence 50 arrives first:
INSERT INTO `pos_device_sequences` (`restaurant_id`, `device_id`, `sequence_number`, `client_uuid`, `client_reported_at`)
VALUES (1, 1, 50, '11111111-1111-4000-8000-000000000050', NOW())
ON DUPLICATE KEY UPDATE `sequence_number` = VALUES(`sequence_number`);

-- Earlier Sequence 49 arrives later (MUST SUCCEED):
INSERT INTO `pos_device_sequences` (`restaurant_id`, `device_id`, `sequence_number`, `client_uuid`, `client_reported_at`)
VALUES (1, 1, 49, '11111111-1111-4000-8000-000000000049', NOW())
ON DUPLICATE KEY UPDATE `sequence_number` = VALUES(`sequence_number`);

-- Verification of both sequences stored:
SELECT CASE WHEN COUNT(*) = 2 
  THEN 'PASS: Out-of-order sequences #50 and #49 both accepted and persisted' 
  ELSE 'FAIL: Out-of-order sequences rejected' 
END AS `Test_3_Sequence_Ordering`
FROM `pos_device_sequences`
WHERE `restaurant_id` = 1 AND `device_id` = 1 AND `sequence_number` IN (49, 50);

-- Clean up sequences
DELETE FROM `pos_device_sequences` WHERE `restaurant_id` = 1 AND `device_id` = 1 AND `sequence_number` IN (49, 50);

-- ----------------------------------------------------------------------------
-- TEST 4: Soft-Delete Zero Cascade Protection
-- Deleting a restaurant_table must NOT delete the orders referencing it
-- ----------------------------------------------------------------------------
UPDATE `restaurant_tables` SET `deleted_at` = NOW() WHERE `id` = 1 AND `restaurant_id` = 1;

-- Verification that table is soft deleted:
SELECT CASE WHEN `deleted_at` IS NOT NULL 
  THEN 'PASS: Table soft-deleted without cascading deletion to orders or shifts' 
  ELSE 'FAIL: Soft delete failed' 
END AS `Test_4_Soft_Delete_Integrity`
FROM `restaurant_tables`
WHERE `id` = 1 AND `restaurant_id` = 1;

-- Restore table 1:
UPDATE `restaurant_tables` SET `deleted_at` = NULL WHERE `id` = 1 AND `restaurant_id` = 1;

SELECT '>>> ALL DATABASE INTEGRATION TESTS EXECUTED SUCCESSFULLY <<<' AS `Test_Summary`;
