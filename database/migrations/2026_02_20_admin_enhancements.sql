-- Admin enhancement migration: prioritization, registration guards, and progress clarity

ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS priority_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER priority,
    ADD COLUMN IF NOT EXISTS engineer_license_doc VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS engineer_certification_doc VARCHAR(255) NULL AFTER engineer_license_doc,
    ADD COLUMN IF NOT EXISTS engineer_credentials_doc VARCHAR(255) NULL AFTER engineer_certification_doc;

ALTER TABLE feedback
    ADD COLUMN IF NOT EXISTS district VARCHAR(100) NULL AFTER location,
    ADD COLUMN IF NOT EXISTS barangay VARCHAR(150) NULL AFTER district,
    ADD COLUMN IF NOT EXISTS alternative_name VARCHAR(150) NULL AFTER barangay,
    ADD COLUMN IF NOT EXISTS exact_address VARCHAR(255) NULL AFTER alternative_name,
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS map_lat DECIMAL(10,7) NULL AFTER photo_path,
    ADD COLUMN IF NOT EXISTS map_lng DECIMAL(10,7) NULL AFTER map_lat,
    ADD COLUMN IF NOT EXISTS map_link VARCHAR(500) NULL AFTER map_lng;

CREATE INDEX IF NOT EXISTS idx_feedback_priority_loc ON feedback (district, barangay, alternative_name, category);
CREATE INDEX IF NOT EXISTS idx_feedback_status_date ON feedback (status, date_submitted);

CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    planned_start DATE NULL,
    planned_end DATE NULL,
    actual_start DATE NULL,
    actual_end DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_tasks_project (project_id),
    CONSTRAINT fk_project_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    planned_date DATE NULL,
    actual_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_milestones_project (project_id),
    CONSTRAINT fk_project_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
