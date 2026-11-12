-- Ensure Kitchen and Waiter roles receive their expected permissions.
-- INSERT IGNORE makes this safe to run more than once.

INSERT IGNORE INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT r.restaurant_id, r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('kitchen.view_kds', 'kitchen.update_state')
WHERE r.slug = 'kitchen';

INSERT IGNORE INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT r.restaurant_id, r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN ('pos.create_order', 'tables.view_status')
WHERE r.slug = 'waiter';
