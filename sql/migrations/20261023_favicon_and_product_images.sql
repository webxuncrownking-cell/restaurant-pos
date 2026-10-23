-- Run once on existing databases. Fresh installs receive these columns from sql/schema.sql.
ALTER TABLE restaurants ADD COLUMN favicon_path VARCHAR(255) NULL AFTER logo_path;
ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER barcode;
