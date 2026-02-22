-- Core RBAC + workflow tables for IPMS
-- Run in phpMyAdmin.

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_employee_id INT NULL,
    actor_role VARCHAR(50) NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id INT NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_employee_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    reviewer_id INT NULL,
    reviewer_role VARCHAR(50) NULL,
    notes TEXT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approvals_entity (entity_type, entity_id),
    INDEX idx_approvals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by INT NULL,
    notes TEXT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_status (project_id, status),
    CONSTRAINT fk_project_status_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    engineer_id INT NULL,
    contractor_id INT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_assign (project_id),
    CONSTRAINT fk_project_assign_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    contractor_id INT NULL,
    engineer_id INT NULL,
    report_status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    summary TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_report_project (project_id),
    INDEX idx_report_status (report_status),
    CONSTRAINT fk_report_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    document_type VARCHAR(80) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proj_docs_project (project_id),
    CONSTRAINT fk_proj_docs_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed roles (safe re-run)
INSERT IGNORE INTO roles (name, description) VALUES
('super_admin', 'Full system control'),
('department_admin', 'Department-level admin'),
('engineer', 'Engineering staff'),
('contractor', 'External contractor'),
('viewer', 'Read-only auditor');

-- Seed common permissions (extend as needed)
INSERT IGNORE INTO permissions (code, description) VALUES
('project.create', 'Create projects'),
('project.approve', 'Approve projects'),
('project.assign', 'Assign engineers/contractors'),
('project.update', 'Update project details'),
('project.view', 'View projects'),
('task.manage', 'Manage tasks'),
('milestone.manage', 'Manage milestones'),
('report.submit', 'Submit contractor reports'),
('report.review', 'Review contractor reports'),
('user.manage', 'Manage employee accounts'),
('audit.view', 'View audit logs');
