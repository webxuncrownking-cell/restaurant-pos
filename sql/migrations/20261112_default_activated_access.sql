-- Add an explicit subscription tier for newly activated accounts that have not purchased a plan.
-- Default Activated Access: one POS terminal, up to 50 products, one staff account (owner excluded).
-- Paid plan subscription remains separate and must be approved by the platform admin.

INSERT INTO subscription_plans
    (name, slug, description, price_monthly, price_annually, max_terminals, max_products, max_staff, features_json, is_active)
SELECT
    'Default Activated',
    'default-activated',
    'Activated account access before plan subscription',
    0.00,
    0.00,
    1,
    50,
    1,
    JSON_OBJECT(
        'offline_pwa', TRUE,
        'table_mgmt', FALSE,
        'recipe_bom', FALSE,
        'reports', TRUE,
        'multi_device', FALSE,
        'multi_terminal', FALSE,
        'staff_management', TRUE,
        'kitchen_display', FALSE,
        'advanced_reports', FALSE,
        'customer_crm', FALSE,
        'loyalty', FALSE,
        'combo_meals', FALSE,
        'scheduled_pricing', FALSE,
        'reservations', FALSE,
        'expenses', FALSE,
        'suppliers', FALSE,
        'purchase_orders', FALSE,
        'recipe_costing', FALSE,
        'expiry_wastage', FALSE,
        'api_access', FALSE
    ),
    1
WHERE NOT EXISTS (
    SELECT 1 FROM subscription_plans WHERE slug = 'default-activated'
);

-- Ensure its limits and feature flags stay aligned with this default access policy.
UPDATE subscription_plans
SET name = 'Default Activated',
    description = 'Activated account access before plan subscription',
    price_monthly = 0.00,
    price_annually = 0.00,
    max_terminals = 1,
    max_products = 50,
    max_staff = 1,
    features_json = JSON_OBJECT(
        'offline_pwa', TRUE,
        'table_mgmt', FALSE,
        'recipe_bom', FALSE,
        'reports', TRUE,
        'multi_device', FALSE,
        'multi_terminal', FALSE,
        'staff_management', TRUE,
        'kitchen_display', FALSE,
        'advanced_reports', FALSE,
        'customer_crm', FALSE,
        'loyalty', FALSE,
        'combo_meals', FALSE,
        'scheduled_pricing', FALSE,
        'reservations', FALSE,
        'expenses', FALSE,
        'suppliers', FALSE,
        'purchase_orders', FALSE,
        'recipe_costing', FALSE,
        'expiry_wastage', FALSE,
        'api_access', FALSE
    ),
    is_active = 1
WHERE slug = 'default-activated';

-- Attach only already activated restaurants that do not yet have any subscription.
INSERT INTO subscriptions
    (restaurant_id, plan_id, status, billing_cycle, current_period_start, current_period_end, grace_period_days)
SELECT
    r.id, p.id, 'active', 'monthly', NOW(), '9999-12-31 23:59:59', 0
FROM restaurants r
JOIN subscription_plans p ON p.slug = 'default-activated'
WHERE r.activation_status = 'activated'
  AND r.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.restaurant_id = r.id);

