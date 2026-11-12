-- ServeFlow: 24-hour provisional access after activation payment submission.
-- Safe to run more than once on MySQL 8+ / MariaDB 10.4+.
SET @serveflow_trial_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'restaurants'
      AND COLUMN_NAME = 'activation_trial_until'
);
SET @serveflow_trial_ddl = IF(
    @serveflow_trial_column_exists = 0,
    'ALTER TABLE restaurants ADD COLUMN activation_trial_until DATETIME NULL AFTER activation_status',
    'SELECT "activation_trial_until already exists"'
);
PREPARE serveflow_trial_stmt FROM @serveflow_trial_ddl;
EXECUTE serveflow_trial_stmt;
DEALLOCATE PREPARE serveflow_trial_stmt;
