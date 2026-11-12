-- Provision standard tenant staff roles and least-privilege permissions.
-- Safe to run repeatedly. Existing role definitions are preserved.

INSERT INTO roles (restaurant_id, name, slug, description)
SELECT r.id, 'Restaurant Manager', 'manager', 'Manages floor, inventory, menu, shifts, and overrides'
FROM restaurants r
WHERE r.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM roles x WHERE x.restaurant_id = r.id AND x.slug = 'manager');

INSERT INTO roles (restaurant_id, name, slug, description)
SELECT r.id, 'Cashier / POS Staff', 'cashier', 'Operates the POS, processes payments, and manages cashier shifts'
FROM restaurants r
WHERE r.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM roles x WHERE x.restaurant_id = r.id AND x.slug = 'cashier');

INSERT INTO roles (restaurant_id, name, slug, description)
SELECT r.id, 'Kitchen Cook', 'kitchen', 'Views kitchen orders and updates preparation status'
FROM restaurants r
WHERE r.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM roles x WHERE x.restaurant_id = r.id AND x.slug = 'kitchen');

INSERT INTO roles (restaurant_id, name, slug, description)
SELECT r.id, 'Waiter / Floor Staff', 'waiter', 'Creates table orders and views table service status'
FROM restaurants r
WHERE r.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM roles x WHERE x.restaurant_id = r.id AND x.slug = 'waiter');

-- Manager permissions.
INSERT INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT ro.restaurant_id, ro.id, p.id
FROM roles ro
JOIN permissions p ON p.code IN (
 'pos.create_order','pos.process_payment','pos.apply_discount',
 'orders.cancel_void','orders.issue_refund',
 'tables.view_status','tables.manage_tables',
 'kitchen.view_kds','kitchen.update_state',
 'inventory.view_stock','inventory.stock_in','inventory.adjust',
 'menu.manage_catalog','menu.manage_recipes',
 'shifts.open_close','shifts.cash_drop',
 'reports.view_sales','reports.view_ledger'
)
WHERE ro.slug = 'manager'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Cashier permissions.
INSERT INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT ro.restaurant_id, ro.id, p.id
FROM roles ro
JOIN permissions p ON p.code IN (
 'pos.create_order','pos.process_payment',
 'tables.view_status','shifts.open_close','shifts.cash_drop'
)
WHERE ro.slug = 'cashier'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Kitchen permissions.
INSERT INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT ro.restaurant_id, ro.id, p.id
FROM roles ro
JOIN permissions p ON p.code IN ('kitchen.view_kds','kitchen.update_state')
WHERE ro.slug = 'kitchen'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Waiter permissions.
INSERT INTO role_permissions (restaurant_id, role_id, permission_id)
SELECT ro.restaurant_id, ro.id, p.id
FROM roles ro
JOIN permissions p ON p.code IN ('pos.create_order','tables.view_status')
WHERE ro.slug = 'waiter'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);
