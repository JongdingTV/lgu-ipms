-- Add approval workflow fields for engineers and contractors.
-- Run in phpMyAdmin.

ALTER TABLE engineers
    ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER id,
    ADD COLUMN verified_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN approved_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN rejected_at DATETIME NULL DEFAULT NULL;

CREATE INDEX idx_engineers_approval_status ON engineers (approval_status);

ALTER TABLE contractors
    ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ADD COLUMN verified_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN approved_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN rejected_at DATETIME NULL DEFAULT NULL;

CREATE INDEX idx_contractors_approval_status ON contractors (approval_status);
