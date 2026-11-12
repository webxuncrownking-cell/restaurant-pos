-- Add and enforce staff-account limits, then define clear subscription-tier differences.
-- Run once against the live ServeFlow database.

ALTER TABLE subscription_plans
    ADD COLUMN max_staff SMALLINT UNSIGNED NOT NULL DEFAULT 2 AFTER max_products;

UPDATE subscription_plans
SET
    max_staff = CASE slug
        WHEN 'starter' THEN 2
        WHEN 'standard' THEN 10
        WHEN 'enterprise' THEN 0
        ELSE max_staff
    END,
    max_terminals = CASE slug
        WHEN 'starter' THEN 1
        WHEN 'standard' THEN 3
        WHEN 'enterprise' THEN 10
        ELSE max_terminals
    END,
    max_products = CASE slug
        WHEN 'starter' THEN 50
        WHEN 'standard' THEN 200
        WHEN 'enterprise' THEN 1000
        ELSE max_products
    END,
    features_json = JSON_SET(
        COALESCE(features_json, JSON_OBJECT()),
        '$.offline_pwa', TRUE,
        '$.table_mgmt', slug <> 'starter',
        '$.recipe_bom', slug <> 'starter',
        '$.reports', TRUE,
        '$.multi_device', slug <> 'starter',
        '$.multi_terminal', slug <> 'starter',
        '$.staff_management', TRUE,
        '$.kitchen_display', slug <> 'starter',
        '$.advanced_reports', slug <> 'starter',
        '$.customer_crm', slug = 'enterprise',
        '$.loyalty', slug = 'enterprise',
        '$.combo_meals', slug <> 'starter',
        '$.scheduled_pricing', slug <> 'starter',
        '$.reservations', slug <> 'starter',
        '$.expenses', slug <> 'starter',
        '$.suppliers', slug <> 'starter',
        '$.purchase_orders', slug <> 'starter',
        '$.recipe_costing', slug <> 'starter',
        '$.expiry_wastage', slug <> 'starter',
        '$.api_access', slug = 'enterprise'
    )
WHERE slug IN ('starter', 'standard', 'enterprise');
