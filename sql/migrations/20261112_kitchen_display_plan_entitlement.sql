-- Kitchen Display is a premium module.
-- Starter: hidden/disabled. Standard and Enterprise: enabled.
UPDATE subscription_plans
SET features_json = JSON_SET(
    COALESCE(features_json, JSON_OBJECT()),
    '$.kitchen_display',
    CASE
        WHEN slug IN ('standard', 'enterprise') THEN TRUE
        ELSE FALSE
    END
)
WHERE is_active = 1;
