-- Create password_resets table for secure password reset functionality
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for token cleanup
CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM password_resets WHERE expires_at < NOW();
