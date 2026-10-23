-- Run once on an existing database to enable restaurant tagline settings.
ALTER TABLE restaurants ADD COLUMN tagline VARCHAR(180) NULL AFTER name;
