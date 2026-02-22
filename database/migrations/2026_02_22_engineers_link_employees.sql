-- Link engineer records to employee accounts (one-to-one optional).
-- Run in phpMyAdmin.

ALTER TABLE engineers
    ADD COLUMN employee_id INT NULL AFTER id;

ALTER TABLE engineers
    ADD CONSTRAINT fk_engineers_employee
    FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE SET NULL;

CREATE UNIQUE INDEX uq_engineers_employee ON engineers (employee_id);
