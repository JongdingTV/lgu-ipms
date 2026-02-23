-- Validation workflow module (Task & Milestone checking)
-- Adds deliverable-level submissions, decisions, and audit trail.

CREATE TABLE IF NOT EXISTS project_validation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    deliverable_type VARCHAR(20) NOT NULL DEFAULT 'manual',
    deliverable_ref_id INT NULL,
    deliverable_name VARCHAR(255) NOT NULL,
    weight DECIMAL(7,2) NOT NULL DEFAULT 1.00,
    current_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    last_submission_id INT NULL,
    submitted_by INT NULL,
    submitted_at DATETIME NULL,
    validated_by INT NULL,
    validated_at DATETIME NULL,
    validator_remarks TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_validation_source (project_id, deliverable_type, deliverable_ref_id),
    INDEX idx_validation_project_status (project_id, current_status),
    INDEX idx_validation_submitted (submitted_by, submitted_at),
    INDEX idx_validation_validated (validated_by, validated_at),
    CONSTRAINT fk_validation_item_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_validation_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    version_no INT NOT NULL DEFAULT 1,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    change_summary TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    submitted_by INT NOT NULL,
    submitted_role VARCHAR(30) NOT NULL DEFAULT 'contractor',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validation_result VARCHAR(30) NULL,
    validated_by INT NULL,
    validated_at DATETIME NULL,
    validator_remarks TEXT NULL,
    INDEX idx_validation_submission_item (item_id, version_no),
    INDEX idx_validation_submission_submitter (submitted_by, submitted_at),
    INDEX idx_validation_submission_result (validation_result),
    CONSTRAINT fk_validation_submission_item FOREIGN KEY (item_id) REFERENCES project_validation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_validation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    submission_id INT NULL,
    action_type VARCHAR(40) NOT NULL,
    previous_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    remarks TEXT NULL,
    acted_by INT NOT NULL,
    acted_role VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NULL,
    acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_validation_logs_item_time (item_id, acted_at),
    INDEX idx_validation_logs_actor (acted_by, acted_at),
    CONSTRAINT fk_validation_log_item FOREIGN KEY (item_id) REFERENCES project_validation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

