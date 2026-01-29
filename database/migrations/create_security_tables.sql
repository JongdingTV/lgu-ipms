-- Create login_attempts table for account lockout tracking
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `success` BOOLEAN DEFAULT FALSE,
  INDEX idx_email (email),
  INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create login_logs table for audit trail
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT,
  `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `logout_time` DATETIME NULL,
  `status` ENUM('success', 'failed', 'locked') DEFAULT 'success',
  `reason` VARCHAR(255) NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_id (employee_id),
  INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create locked_accounts table for account lockout
CREATE TABLE IF NOT EXISTS `locked_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `locked_until` DATETIME NOT NULL,
  `reason` VARCHAR(255),
  `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create session_logs table for session tracking
CREATE TABLE IF NOT EXISTS `session_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `session_id` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_activity` DATETIME,
  `status` ENUM('active', 'expired', 'logged_out') DEFAULT 'active',
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_id (employee_id),
  INDEX idx_session_id (session_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto cleanup of old login attempts (keep last 30 days)
CREATE EVENT IF NOT EXISTS cleanup_old_login_attempts
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Auto cleanup of old login logs (keep last 90 days)
CREATE EVENT IF NOT EXISTS cleanup_old_login_logs
ON SCHEDULE EVERY 1 DAY
DO
  DELETE FROM login_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Auto unlock accounts that have passed lockout period
CREATE EVENT IF NOT EXISTS unlock_expired_accounts
ON SCHEDULE EVERY 5 MINUTE
DO
  DELETE FROM locked_accounts WHERE locked_until < NOW();
