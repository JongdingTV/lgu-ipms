-- Database setup for LGU IPMS
CREATE DATABASE IF NOT EXISTS lgu_ipms;
USE lgu_ipms;

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
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Insert a test user (password: test123, hashed)
-- INSERT INTO users (first_name, last_name, email, password) VALUES ('Test', 'User', 'test@lgu.gov.ph', '$2y$10$examplehashedpassword');

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