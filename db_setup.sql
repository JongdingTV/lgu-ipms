USE ipms_lgu;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    suffix VARCHAR(10),
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile VARCHAR(20),
    birthdate DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not'),
    civil_status ENUM('single', 'married', 'widowed', 'separated'),
    address TEXT,
    id_type VARCHAR(50),
    id_number VARCHAR(50),
    id_upload VARCHAR(255),
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'Employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Insert a test user (password: test123, hashed)
-- INSERT INTO users (first_name, last_name, email, password) VALUES ('Test', 'User', 'test@lgu.gov.ph', '$2y$10$examplehashedpassword');

-- Insert a test employee (password: admin123, hashed)
INSERT INTO employees (first_name, last_name, email, password) VALUES ('Admin', 'User', 'admin@lgu.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- budget module total budget 
CREATE TABLE IF NOT EXISTS project_settings (
    id INT PRIMARY KEY,
    total_budget DECIMAL(15, 2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    allocated DECIMAL(15, 2) DEFAULT 0,
    spent DECIMAL(15, 2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    milestoneId INT,
    amount DECIMAL(15, 2) NOT NULL,
    description TEXT,
    date DATETIME,
    FOREIGN KEY (milestoneId) REFERENCES milestones(id) ON DELETE CASCADE
);

-- Initialize the budget row
INSERT IGNORE INTO project_settings (id, total_budget) VALUES (1, 0);

-- Project Registration Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50),
    sector VARCHAR(50),
    description TEXT,
    priority VARCHAR(20) DEFAULT 'Medium',
    province VARCHAR(100),
    barangay VARCHAR(100),
    location VARCHAR(255),
    start_date DATE,
    end_date DATE,
    duration_months INT,
    budget DECIMAL(15, 2),
    project_manager VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contractors Table
CREATE TABLE IF NOT EXISTS contractors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    owner VARCHAR(100),
    license VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    specialization VARCHAR(100),
    experience INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Feedback Table
CREATE TABLE IF NOT EXISTS user_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    street VARCHAR(255),
    barangay VARCHAR(255),
    category VARCHAR(100) NOT NULL,
    feedback TEXT NOT NULL,
    photo_path VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Pending',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Password Reset Tokens Table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Security and ownership hardening migrations
ALTER TABLE feedback
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id;

UPDATE feedback f
JOIN users u
  ON LOWER(TRIM(f.user_name)) = LOWER(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)))
SET f.user_id = u.id
WHERE f.user_id IS NULL;

ALTER TABLE feedback
    ADD INDEX IF NOT EXISTS idx_feedback_user_id_date (user_id, date_submitted),
    ADD CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE users
    ADD UNIQUE KEY uniq_users_mobile (mobile),
    ADD UNIQUE KEY uniq_users_id_pair (id_type, id_number);

CREATE TABLE IF NOT EXISTS user_rate_limiting (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempt_time INT NOT NULL,
    INDEX idx_user_action_time (user_id, action_type, attempt_time)
);
