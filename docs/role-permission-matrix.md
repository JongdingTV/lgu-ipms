# Role Permission Matrix

Source of truth is `includes/permission-matrix.php`.  
This document is a human-readable companion for maintenance and smoke checks.

## Admin Workspace
- `admin.dashboard.view`: `admin`, `department_admin`, `super_admin`
- `admin.projects.manage`: `admin`, `department_admin`, `super_admin`
- `admin.progress.view`: `admin`, `department_admin`, `super_admin`
- `admin.progress.manage`: `admin`, `department_admin`, `super_admin`
- `admin.budget.manage`: `admin`, `department_admin`, `super_admin`
- `admin.budget.delete`: `admin`, `super_admin`
- `admin.prioritization.manage`: `admin`, `department_admin`, `super_admin`
- `admin.prioritization.read`: `admin`, `department_admin`, `super_admin`
- `admin.citizen_verification.manage`: `admin`, `department_admin`, `super_admin`
- `admin.notifications.read`: `admin`, `department_admin`, `super_admin`
- `admin.account.security.view`: `admin`, `department_admin`, `super_admin`
- `admin.account.security.manage`: `admin`, `department_admin`, `super_admin`
- `admin.engineers.manage`: `admin`, `department_admin`, `super_admin`
- `admin.engineers.delete`: `admin`, `super_admin`
- `admin.engineers.assign`: `admin`, `department_admin`, `super_admin`
- `admin.projects.delete`: `admin`, `super_admin`
- `admin.projects.read`: `admin`, `department_admin`, `super_admin`
- `admin.projects.export`: `admin`, `department_admin`, `super_admin`
- `admin.legacy.contractors_alias`: `admin`, `department_admin`, `super_admin`
- `admin.audit.view`: `admin`, `department_admin`, `super_admin`
- `admin.db_health.run`: `super_admin`

## Super Admin Workspace
- `super_admin.dashboard.view`: `super_admin`
- `super_admin.employee_accounts.view`: `super_admin`
- `super_admin.employee_accounts.manage`: `super_admin`
- `super_admin.audit.view`: `super_admin`
- `super_admin.progress.view`: `super_admin`
- `super_admin.projects.view`: `super_admin`

## Department Head Workspace
- `department_head.approvals.view`: `department_head`, `department_admin`, `admin`, `super_admin`
- `department_head.approvals.manage`: `department_head`, `department_admin`, `admin`, `super_admin`
- `department_head.notifications.read`: `department_head`, `department_admin`, `admin`, `super_admin`

## Engineer Workspace
- `engineer.workspace.view`: `engineer`, `admin`, `super_admin`
- `engineer.workspace.manage`: `engineer`, `admin`, `super_admin`
- `engineer.notifications.read`: `engineer`, `admin`, `super_admin`
- `engineer.progress.review`: `engineer`, `admin`, `super_admin`
- `engineer.status.review`: `engineer`, `admin`, `super_admin`
- `engineer.tasks.manage`: `engineer`, `admin`, `super_admin`

## Contractor Workspace
- `contractor.workspace.view`: `contractor`, `admin`, `super_admin`
- `contractor.workspace.manage`: `contractor`, `admin`, `super_admin`
- `contractor.notifications.read`: `contractor`, `admin`, `super_admin`
- `contractor.progress.submit`: `contractor`, `admin`, `super_admin`
- `contractor.status.request`: `contractor`, `admin`, `super_admin`
- `contractor.budget.read`: `contractor`, `admin`, `super_admin`
- `contractor.budget.manage`: `admin`, `super_admin`

## Maintenance Rules
1. Add/modify permissions in `includes/permission-matrix.php` first.
2. Guard page entry with `rbac_require_from_matrix(...)`.
3. Guard API actions with `rbac_require_action_matrix(...)`.
4. Keep UI visibility aligned with API permission checks.
5. For write actions, keep CSRF verification enabled.
