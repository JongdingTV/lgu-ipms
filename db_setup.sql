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