-- One-time, non-destructive migration for existing databases.
-- Fresh databases already receive this column from sql/schema.sql.
-- Run only if restaurants.logo_path does not already exist:
ALTER TABLE restaurants
  ADD COLUMN logo_path VARCHAR(255) NULL AFTER name;
