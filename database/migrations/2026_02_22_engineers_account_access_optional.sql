-- Make engineer account access fields optional now managed by Super Admin.
-- Run in phpMyAdmin.

ALTER TABLE engineers
    MODIFY COLUMN username VARCHAR(80) NULL,
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    MODIFY COLUMN role ENUM('Engineer','Admin') NULL DEFAULT NULL;
