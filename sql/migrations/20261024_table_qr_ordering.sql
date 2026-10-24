-- Safe additive migration for QR-based table ordering.
-- Existing table IDs, orders, and status are preserved.
ALTER TABLE restaurant_tables ADD COLUMN qr_token CHAR(64) NULL;
CREATE UNIQUE INDEX uq_restaurant_tables_qr_token ON restaurant_tables(qr_token);
UPDATE restaurant_tables
SET qr_token = LOWER(SHA2(CONCAT(UUID(), RAND(), restaurant_id, id), 256))
WHERE qr_token IS NULL OR qr_token = '';
