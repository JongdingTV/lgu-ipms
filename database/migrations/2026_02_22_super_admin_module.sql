-- Super Admin module compatibility migration
-- Adds role/status columns to employees table if missing.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'employees'
          AND COLUMN_NAME = 'role'
    ),
    'SELECT 1',
    "ALTER TABLE employees ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'employee' AFTER password"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'employees'
          AND COLUMN_NAME = 'account_status'
    ),
    'SELECT 1',
    "ALTER TABLE employees ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'employees'
          AND INDEX_NAME = 'idx_employees_role_status'
    ),
    'SELECT 1',
    'CREATE INDEX idx_employees_role_status ON employees (role, account_status)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Promote legacy main admin account to super admin role
UPDATE employees
SET role = 'super_admin'
WHERE LOWER(email) = 'admin@lgu.gov.ph';

