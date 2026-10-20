-- ============================================================================
-- RESTAURANT POS & MANAGEMENT SAAS - SEED DATA SCRIPT
-- Provisions Platform Admin, Subscription Plans, Two Test Tenants (Mang Juan & Aling Nena),
-- Roles, Permissions, Staff, POS Devices, Tables, Menu, Ingredients & Recipes.
-- ============================================================================

-- 1. Platform Superadmin (Platform Level)
-- Password: AdminPassword2026! (bcrypt hash cost 12)
INSERT INTO `platform_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `must_change_password`)
VALUES (
  1,
  'platform_admin',
  'superadmin@resto-pos.local',
  '$2y$12$1iYjyi0MCXK29HwpITdPLeSnR8D.53tTsFbzXMBVARktiMB0BXGei', -- AdminPassword2026!
  'SaaS System Administrator',
  'super_admin',
  'active',
  1
) ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`);

-- 2. SaaS Subscription Plans
INSERT INTO `subscription_plans` (`id`, `name`, `slug`, `description`, `price_monthly`, `price_annually`, `max_terminals`, `max_products`, `features_json`, `is_active`)
VALUES
(1, 'Starter Carinderia', 'starter', 'Essential offline POS for single-terminal eateries', 990.00, 9900.00, 1, 50, '{"offline_pwa":true,"table_mgmt":false,"recipe_bom":true,"reports":true,"multi_device":false}', 1),
(2, 'Standard Restaurant', 'standard', 'Complete multi-terminal POS with table management & recipe tracking', 1990.00, 19900.00, 3, 200, '{"offline_pwa":true,"table_mgmt":true,"recipe_bom":true,"reports":true,"multi_device":true}', 1),
(3, 'Enterprise / Franchise', 'enterprise', 'Unlimited terminals, multi-branch analytics & priority sync', 3990.00, 39900.00, 10, 1000, '{"offline_pwa":true,"table_mgmt":true,"recipe_bom":true,"reports":true,"multi_device":true,"api_access":true}', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Tenant Restaurants
-- Restaurant 1: Mang Juan's Carinderia
INSERT INTO `restaurants` (`id`, `name`, `slug`, `business_registration_number`, `contact_email`, `contact_phone`, `currency_symbol`, `timezone`, `status`, `pos_primary_color`, `pos_secondary_color`, `receipt_header`, `receipt_footer`)
VALUES (
  1,
  'Mang Juan\'s Carinderia',
  'mang-juan',
  'BN-2026-PH-00123',
  'info@mangjuan.local',
  '+63 917 123 4567',
  'PHP',
  'Asia/Manila',
  'active',
  '#d97706', -- Amber/Warm Orange
  '#059669', -- Emerald Green
  'MANG JUAN\'S CARINDERIA\nAuthentic Filipino Home Cooking\n123 Rizal Ave, Sampaloc, Manila\nTel: (02) 8123-4567\nTIN: 123-456-789-000',
  'Maraming Salamat po sa Inyong Pagtangkilik!\nPlease come again!'
) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Restaurant 2: Aling Nena's Binalot (For multi-tenant isolation verification)
INSERT INTO `restaurants` (`id`, `name`, `slug`, `business_registration_number`, `contact_email`, `contact_phone`, `currency_symbol`, `timezone`, `status`, `pos_primary_color`, `pos_secondary_color`, `receipt_header`, `receipt_footer`)
VALUES (
  2,
  'Aling Nena\'s Binalot',
  'aling-nena',
  'BN-2026-PH-00456',
  'orders@alingnena.local',
  '+63 920 987 6543',
  'PHP',
  'Asia/Manila',
  'active',
  '#2563eb', -- Blue
  '#16a34a', -- Green
  'ALING NENA\'S BINALOT\nTraditional Banana Leaf Meals\n456 Quezon Blvd, Quezon City\nTel: (02) 8987-6543',
  'Diyos Mabalos! Balik po kayo muli!'
) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Subscriptions for Both Tenants
INSERT INTO `subscriptions` (`id`, `restaurant_id`, `plan_id`, `status`, `billing_cycle`, `current_period_start`, `current_period_end`, `grace_period_days`)
VALUES
(1, 1, 2, 'active', 'monthly', NOW() - INTERVAL 5 DAY, NOW() + INTERVAL 25 DAY, 7),
(2, 2, 1, 'active', 'monthly', NOW() - INTERVAL 10 DAY, NOW() + INTERVAL 20 DAY, 7)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Invoices for Both Tenants
INSERT INTO `platform_invoices` (`id`, `restaurant_id`, `subscription_id`, `invoice_number`, `amount`, `tax_amount`, `total_amount`, `status`, `due_date`, `paid_at`)
VALUES
(1, 1, 1, 'INV-2026-0001', 1990.00, 238.80, 2228.80, 'paid', CURDATE() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY),
(2, 2, 2, 'INV-2026-0002', 990.00, 118.80, 1108.80, 'paid', CURDATE() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 5. System Permissions (Canonical Module Registry)
INSERT INTO `permissions` (`id`, `module`, `action`, `code`, `description`)
VALUES
(1,  'pos',       'create_order',     'pos.create_order',     'Create orders and ring up sales in POS terminal'),
(2,  'pos',       'process_payment',  'pos.process_payment',  'Tender cash and finalize sales in POS'),
(3,  'pos',       'apply_discount',   'pos.apply_discount',   'Apply custom discounts to order items'),
(4,  'orders',    'cancel_void',      'orders.cancel_void',   'Void or cancel order items and open tickets'),
(5,  'orders',    'issue_refund',     'orders.issue_refund',  'Process partial or full payment refunds'),
(6,  'tables',    'view_status',      'tables.view_status',   'View floor plan and live occupancy'),
(7,  'tables',    'manage_tables',    'tables.manage_tables', 'Create, update table layout and zones'),
(8,  'kitchen',   'view_kds',         'kitchen.view_kds',     'View live kitchen display tickets'),
(9,  'kitchen',   'update_item_state','kitchen.update_state', 'Mark order items cooking or ready'),
(10, 'inventory', 'view_stock',       'inventory.view_stock', 'Inspect ingredient stock balances and warnings'),
(11, 'inventory', 'stock_in',         'inventory.stock_in',   'Record stock replenishment and purchases'),
(12, 'inventory', 'adjust_waste',     'inventory.adjust',     'Log inventory waste, spoilage, or manual adjustments'),
(13, 'menu',      'manage_catalog',   'menu.manage_catalog',  'Create and update categories and products'),
(14, 'menu',      'manage_recipes',   'menu.manage_recipes',  'Define recipe Bill of Materials (BOM)'),
(15, 'shifts',    'open_close',       'shifts.open_close',    'Open cash register float and close cashier shift'),
(16, 'shifts',    'cash_movements',   'shifts.cash_drop',     'Record mid-shift cash drops or cash in'),
(17, 'reports',   'view_sales',       'reports.view_sales',   'View daily, weekly, and monthly sales reports'),
(18, 'reports',   'view_ledger',      'reports.view_ledger',  'View inventory ledger and movement audits'),
(19, 'users',     'manage_staff',     'users.manage_staff',   'Create staff accounts, PINs, and role mappings'),
(20, 'settings',  'update_profile',   'settings.update',      'Update restaurant profile, colors, and receipts')
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`);

-- 6. Roles for Restaurant 1 (Mang Juan) and Restaurant 2 (Aling Nena)
INSERT INTO `roles` (`id`, `restaurant_id`, `name`, `slug`, `description`)
VALUES
-- Restaurant 1 Roles
(1, 1, 'Owner / Super Admin', 'owner',    'Full administrative control over restaurant operations'),
(2, 1, 'Restaurant Manager',   'manager',  'Manages floor, inventory, menu, shifts, and overrides'),
(3, 1, 'Cashier / POS Staff',  'cashier',  'Operates terminal, opens shift, rings sales, tenders cash'),
(4, 1, 'Kitchen Cook',         'kitchen',  'Operates Kitchen Display System (KDS)'),
(5, 1, 'Waiter / Floor Staff', 'waiter',   'Creates table orders and updates service states'),
-- Restaurant 2 Roles
(6, 2, 'Owner / Super Admin', 'owner',    'Full administrative control over restaurant operations'),
(7, 2, 'Cashier / POS Staff',  'cashier',  'Operates terminal and takes orders')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Role Permissions Mapping for Restaurant 1
-- Owner gets all 20 permissions
INSERT INTO `role_permissions` (`restaurant_id`, `role_id`, `permission_id`)
SELECT 1, 1, p.id FROM `permissions` p
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- Manager gets permissions (1-18)
INSERT INTO `role_permissions` (`restaurant_id`, `role_id`, `permission_id`)
SELECT 1, 2, p.id FROM `permissions` p WHERE p.id IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18)
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- Cashier gets (1,2,6,15,16)
INSERT INTO `role_permissions` (`restaurant_id`, `role_id`, `permission_id`)
SELECT 1, 3, p.id FROM `permissions` p WHERE p.id IN (1,2,6,15,16)
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- Role Permissions Mapping for Restaurant 2
INSERT INTO `role_permissions` (`restaurant_id`, `role_id`, `permission_id`)
SELECT 2, 6, p.id FROM `permissions` p
ON DUPLICATE KEY UPDATE `permission_id` = VALUES(`permission_id`);

-- 7. Staff Users for Restaurant 1
-- Password: RestoStaff2026! (bcrypt hash)
-- Maria has an offline PIN: 1234
-- Salt: a1b2c3d4e5f60718293a4b5c6d7e8f90 (16-byte hex)
-- Hash: PBKDF2-HMAC-SHA256 of "1234" with salt and 100,000 iterations:
-- 7b7e68cfb0f1464b54e7416e752945d81b376d5e1654407b99c8bf49b0e1279a
INSERT INTO `users` (`id`, `restaurant_id`, `role_id`, `username`, `email`, `password_hash`, `offline_pin_salt`, `offline_pin_hash`, `full_name`, `status`, `must_change_password`)
VALUES
(
  1, 1, 1,
  'juan_admin',
  'juan@mangjuan.local',
  '$2y$12$2hjqyKsvHEk2aPcASA9pOu1lgkq5fYxav65BRa5i2XDG4l2uYUL/G', -- RestoStaff2026!
  NULL, NULL,
  'Juan Dela Cruz (Owner)',
  'active',
  1
),
(
  2, 1, 2,
  'pedro_manager',
  'pedro@mangjuan.local',
  '$2y$12$2hjqyKsvHEk2aPcASA9pOu1lgkq5fYxav65BRa5i2XDG4l2uYUL/G',
  'f0e1d2c3b4a5968778695a4b3c2d1e0f',
  'ca1be9e2534f95e439dd905233c2cadf4be34117c0c9a790965f412ceb14ce23',
  'Pedro Gomez (Manager)',
  'active',
  1
),
(
  3, 1, 3,
  'maria_cashier',
  'maria@mangjuan.local',
  '$2y$12$2hjqyKsvHEk2aPcASA9pOu1lgkq5fYxav65BRa5i2XDG4l2uYUL/G',
  'a1b2c3d4e5f60718293a4b5c6d7e8f90',
  'ca1be9e2534f95e439dd905233c2cadf4be34117c0c9a790965f412ceb14ce23',
  'Maria Santos (Cashier)',
  'active',
  0
),
-- Staff User for Restaurant 2 (Aling Nena)
(
  4, 2, 6,
  'nena_admin',
  'nena@alingnena.local',
  '$2y$12$2hjqyKsvHEk2aPcASA9pOu1lgkq5fYxav65BRa5i2XDG4l2uYUL/G',
  NULL, NULL,
  'Nena Santos (Owner)',
  'active',
  1
)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `offline_pin_hash` = VALUES(`offline_pin_hash`);

-- 8. Registered POS Devices for Restaurant 1
-- Token: "terminal-01-secure-secret-2026"
-- SHA-256: 3c5e0031893c52e8d35cfb07897d9560f64be872ec72e016f4ec51ff2088f158
INSERT INTO `pos_devices` (`id`, `restaurant_id`, `device_uuid`, `device_name`, `device_token_hash`, `status`, `last_sync_at`)
VALUES (
  1,
  1,
  'd3b07384-d113-4663-8a9e-874288001001',
  'Cashier Counter Terminal 1 (Touchscreen)',
  '3c5e0031893c52e8d35cfb07897d9560f64be872ec72e016f4ec51ff2088f158',
  'active',
  NOW()
) ON DUPLICATE KEY UPDATE `device_name` = VALUES(`device_name`);

-- Assign Maria and Pedro as authorized offline operators for Terminal 1
INSERT INTO `pos_device_staff_authorizations` (`restaurant_id`, `device_id`, `user_id`, `is_allowed_offline`)
VALUES
(1, 1, 2, 1),
(1, 1, 3, 1)
ON DUPLICATE KEY UPDATE `is_allowed_offline` = 1;

-- 9. Tables for Restaurant 1 and Restaurant 2
INSERT INTO `restaurant_tables` (`id`, `restaurant_id`, `table_number`, `name`, `capacity`, `floor_zone`, `status`, `is_active`)
VALUES
-- Restaurant 1 Tables
(1, 1, 'T-01', 'Table 1', 4, 'Main Dining', 'available', 1),
(2, 1, 'T-02', 'Table 2', 4, 'Main Dining', 'available', 1),
(3, 1, 'T-03', 'Table 3', 6, 'Main Dining', 'available', 1),
(4, 1, 'T-04', 'Table 4', 2, 'Alfresco Patio', 'available', 1),
(5, 1, 'T-05', 'VIP Room', 8, 'Private Dining', 'available', 1),
-- Restaurant 2 Tables (Isolated IDs)
(6, 2, 'T-101', 'Table 101', 4, 'Dining Hall', 'available', 1),
(7, 2, 'T-102', 'Table 102', 4, 'Dining Hall', 'available', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 10. Raw Ingredients with Base Units for Restaurant 1
INSERT INTO `ingredients` (`id`, `restaurant_id`, `name`, `sku`, `base_unit`, `cost_per_base_unit`, `current_stock`, `minimum_stock_alert`, `is_active`)
VALUES
(1, 1, 'Pork Belly (Liempo)',       'ING-PK-01', 'g',   0.320000, 25000.0000,  5000.0000, 1), -- 25,000 g = 25 kg
(2, 1, 'Silver Swan Soy Sauce',     'ING-SS-01', 'ml',  0.080000, 10000.0000,  2000.0000, 1), -- 10,000 ml = 10 L
(3, 1, 'Datu Puti White Vinegar',   'ING-VN-01', 'ml',  0.060000, 10000.0000,  2000.0000, 1), -- 10,000 ml = 10 L
(4, 1, 'Fresh Garlic (Bawang)',     'ING-GL-01', 'g',   0.180000,  3000.0000,  1000.0000, 1), -- 3,000 g = 3 kg
(5, 1, 'Black Peppercorns',         'ING-PC-01', 'g',   0.450000,  1000.0000,   200.0000, 1), -- 1,000 g = 1 kg
(6, 1, 'Golden Fiesta Cooking Oil', 'ING-OL-01', 'ml',  0.120000, 15000.0000,  3000.0000, 1), -- 15,000 ml = 15 L
(7, 1, 'Coca-Cola Regular (330ml Can)', 'ING-CC-01', 'pcs', 28.000000, 72.0000, 24.0000, 1),   -- 72 cans
(8, 1, 'Sinandomeng White Rice',    'ING-RC-01', 'g',   0.055000, 50000.0000, 10000.0000, 1), -- 50,000 g = 50 kg
(9, 1, 'Farm Fresh Eggs (Medium)',  'ING-EG-01', 'pcs',  7.500000, 120.0000,   30.0000, 1)    -- 120 eggs
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Ingredient Packaging Conversions
INSERT INTO `ingredient_unit_conversions` (`id`, `restaurant_id`, `ingredient_id`, `unit_name`, `multiplier_to_base`, `is_purchase_unit`)
VALUES
(1, 1, 1, 'kg',        1000.000000, 1),
(2, 1, 2, 'bottle_1L', 1000.000000, 1),
(3, 1, 3, 'bottle_1L', 1000.000000, 1),
(4, 1, 6, 'gallon',    3785.410000, 1),
(5, 1, 7, 'box_24',      24.000000, 1),
(6, 1, 8, 'sack_25kg', 25000.000000, 1),
(7, 1, 9, 'tray_30',     30.000000, 1)
ON DUPLICATE KEY UPDATE `multiplier_to_base` = VALUES(`multiplier_to_base`);

-- 11. Menu Categories for Restaurant 1
INSERT INTO `categories` (`id`, `restaurant_id`, `name`, `sort_order`, `is_active`)
VALUES
(1, 1, 'Signature Viands', 1, 1),
(2, 1, 'Rice Meals',       2, 1),
(3, 1, 'Cold Drinks',      3, 1),
(4, 1, 'Sides & Extras',   4, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 12. Products for Restaurant 1
INSERT INTO `products` (`id`, `restaurant_id`, `category_id`, `name`, `sku`, `barcode`, `base_price`, `cost_price`, `tax_rate`, `product_type`, `direct_stock_ingredient_id`, `has_variants`, `is_active`)
VALUES
(1, 1, 1, 'Special Pork Adobo',       'PRD-ADB-01', '480001640101', 180.00,  85.00, 12.00, 'recipe', NULL, 1, 1),
(2, 1, 2, 'Adobo Rice Meal Bowl',     'PRD-RCE-01', '480001640102', 125.00,  55.00, 12.00, 'recipe', NULL, 0, 1),
(3, 1, 3, 'Coca-Cola Can (330ml)',    'PRD-COK-01', '480198111001',  45.00,  28.00, 12.00, 'direct', 7,    0, 1),
(4, 1, 4, 'Extra Garlic Fried Rice',  'PRD-GFR-01', '480001640104',  35.00,  12.00, 12.00, 'recipe', NULL, 0, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Product Variants for Special Pork Adobo
INSERT INTO `product_variants` (`id`, `restaurant_id`, `product_id`, `name`, `sku`, `additional_price`, `cost_price`, `is_active`)
VALUES
(1, 1, 1, 'Regular Solo (1 Pax)',     'VAR-ADB-REG',   0.00,  85.00, 1),
(2, 1, 1, 'Family Platter (3-4 Pax)', 'VAR-ADB-PLT', 170.00, 170.00, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Product Addon Groups & Addons
INSERT INTO `product_addon_groups` (`id`, `restaurant_id`, `name`, `is_required`)
VALUES (1, 1, 'Choose Your Extras', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `product_addon_group_assignments` (`restaurant_id`, `product_id`, `group_id`)
VALUES
(1, 1, 1),
(1, 2, 1)
ON DUPLICATE KEY UPDATE `group_id` = VALUES(`group_id`);

INSERT INTO `product_addons` (`id`, `restaurant_id`, `group_id`, `name`, `price`, `cost_price`, `ingredient_id`, `deduction_quantity`, `is_active`)
VALUES
(1, 1, 1, 'Extra Adobo Sauce', 15.00,  2.50, 2, 40.0000, 1), -- Deducts 40 ml soy sauce base
(2, 1, 1, 'Sunny Side-up Egg', 20.00,  7.50, 9,  1.0000, 1)  -- Deducts 1 egg
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 13. Recipes (Bill of Materials) with Stored Generated Column Uniqueness
-- Base Recipe for Adobo Solo (variant_id = 1)
INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
VALUES
(1, 1, 1, 1, 250.0000), -- 250g Pork Belly
(1, 1, 1, 2,  50.0000), -- 50ml Soy Sauce
(1, 1, 1, 3,  30.0000), -- 30ml Vinegar
(1, 1, 1, 4,  15.0000), -- 15g Garlic
(1, 1, 1, 5,   2.0000), -- 2g Peppercorn
(1, 1, 1, 6,  10.0000)  -- 10ml Oil
ON DUPLICATE KEY UPDATE `quantity_required` = VALUES(`quantity_required`);

-- Recipe for Adobo Family Platter (variant_id = 2)
INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
VALUES
(1, 1, 2, 1, 600.0000), -- 600g Pork Belly
(1, 1, 2, 2, 120.0000), -- 120ml Soy Sauce
(1, 1, 2, 3,  70.0000), -- 70ml Vinegar
(1, 1, 2, 4,  35.0000), -- 35g Garlic
(1, 1, 2, 5,   5.0000), -- 5g Peppercorn
(1, 1, 2, 6,  25.0000)  -- 25ml Oil
ON DUPLICATE KEY UPDATE `quantity_required` = VALUES(`quantity_required`);

-- Recipe for Base Product without Variants (Adobo Rice Meal, variant_id = NULL)
INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
VALUES
(1, 2, NULL, 1, 150.0000), -- 150g Pork
(1, 2, NULL, 2,  30.0000), -- 30ml Soy Sauce
(1, 2, NULL, 3,  20.0000), -- 20ml Vinegar
(1, 2, NULL, 8, 200.0000)  -- 200g Rice
ON DUPLICATE KEY UPDATE `quantity_required` = VALUES(`quantity_required`);

-- Recipe for Garlic Rice (variant_id = NULL)
INSERT INTO `recipes` (`restaurant_id`, `product_id`, `variant_id`, `ingredient_id`, `quantity_required`)
VALUES
(1, 4, NULL, 8, 200.0000), -- 200g Rice
(1, 4, NULL, 4,  15.0000), -- 15g Garlic
(1, 4, NULL, 6,  10.0000)  -- 10ml Oil
ON DUPLICATE KEY UPDATE `quantity_required` = VALUES(`quantity_required`);

-- Initial Published Catalog Snapshot for Terminal 1 Bootstrap
INSERT INTO `restaurant_catalog_versions` (`id`, `restaurant_id`, `version_uuid`, `snapshot_hash`, `snapshot_json`)
VALUES (
  1,
  1,
  'c1a1a1a1-2026-4000-8000-000000000001',
  '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
  '{"catalog_version":"1.0.0","products_count":4,"categories_count":4}'
) ON DUPLICATE KEY UPDATE `version_uuid` = VALUES(`version_uuid`);
