-- Backfill missing columns for engineers table (safe for older schemas).
-- Run in phpMyAdmin.

SET @db_name = DATABASE();

-- Core identity columns
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='first_name'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN first_name VARCHAR(80) NOT NULL AFTER id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- engineer_documents compatibility columns (for mixed/legacy schemas)
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineer_documents' AND COLUMN_NAME='document_type'),
    'SELECT 1',
    'ALTER TABLE engineer_documents ADD COLUMN document_type VARCHAR(60) NULL AFTER engineer_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineer_documents' AND COLUMN_NAME='file_path'),
    'SELECT 1',
    'ALTER TABLE engineer_documents ADD COLUMN file_path VARCHAR(255) NULL AFTER document_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineer_documents' AND COLUMN_NAME='original_name'),
    'SELECT 1',
    'ALTER TABLE engineer_documents ADD COLUMN original_name VARCHAR(255) NULL AFTER file_path'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineer_documents' AND COLUMN_NAME='mime_type'),
    'SELECT 1',
    'ALTER TABLE engineer_documents ADD COLUMN mime_type VARCHAR(120) NULL AFTER original_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineer_documents' AND COLUMN_NAME='file_size'),
    'SELECT 1',
    'ALTER TABLE engineer_documents ADD COLUMN file_size BIGINT NULL AFTER mime_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='middle_name'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN middle_name VARCHAR(80) NULL AFTER first_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='last_name'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN last_name VARCHAR(80) NOT NULL AFTER middle_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='suffix'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='full_name'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN full_name VARCHAR(255) NOT NULL AFTER suffix'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Contact + licensing
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='email'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN email VARCHAR(150) NOT NULL AFTER contact_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='prc_license_number'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN prc_license_number VARCHAR(80) NOT NULL AFTER email'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='license_expiry_date'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN license_expiry_date DATE NOT NULL AFTER prc_license_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='specialization'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN specialization VARCHAR(120) NOT NULL AFTER license_expiry_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional fields used by the form
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='date_of_birth'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN date_of_birth DATE NULL AFTER full_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='gender'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN gender ENUM(''male'',''female'',''other'',''prefer_not'') NULL AFTER date_of_birth'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='civil_status'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN civil_status ENUM(''single'',''married'',''widowed'',''separated'') NULL AFTER gender'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='address'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN address TEXT NULL AFTER civil_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='contact_number'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN contact_number VARCHAR(30) NOT NULL AFTER address'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='years_experience'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN years_experience INT NOT NULL DEFAULT 0 AFTER specialization'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='position_title'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN position_title VARCHAR(120) NULL AFTER years_experience'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='skills_json'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN skills_json LONGTEXT NULL AFTER position_title'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='availability_status'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN availability_status VARCHAR(30) NOT NULL DEFAULT ''Available'' AFTER skills_json'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='highest_education'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN highest_education VARCHAR(150) NULL AFTER availability_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='school_university'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN school_university VARCHAR(200) NULL AFTER highest_education'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='certifications_trainings'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN certifications_trainings TEXT NULL AFTER school_university'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='past_projects_count'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN past_projects_count INT NOT NULL DEFAULT 0 AFTER certifications_trainings'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='notes'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN notes TEXT NULL AFTER past_projects_count'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='emergency_contact_name'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN emergency_contact_name VARCHAR(120) NULL AFTER notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='emergency_contact_number'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN emergency_contact_number VARCHAR(30) NULL AFTER emergency_contact_name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='engineers' AND COLUMN_NAME='emergency_contact_relationship'),
    'SELECT 1',
    'ALTER TABLE engineers ADD COLUMN emergency_contact_relationship VARCHAR(60) NULL AFTER emergency_contact_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
