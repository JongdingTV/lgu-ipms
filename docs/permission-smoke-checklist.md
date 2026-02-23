# Permission Smoke Checklist

Use this checklist after RBAC updates to quickly verify page and API behavior by role.

## Roles
- `super_admin`
- `admin` / `department_admin`
- `department_head`
- `engineer`
- `contractor`

## Core rule
- A role should only see buttons/actions it is allowed to execute.
- If an action is blocked, API must return `403` with JSON error.

## Super Admin
1. Open `super-admin/dashboard.php`: should load.
2. Open `super-admin/employee_accounts.php`: should load.
3. Verify employee create/edit/status actions work.
4. Open admin pages:
 - `admin/dashboard.php`
 - `admin/project_registration.php`
 - `admin/registered_projects.php`
 - `admin/registered_engineers.php`
Expected: allowed.

## Admin / Department Admin
1. Open:
 - `admin/dashboard.php`
 - `admin/project_registration.php`
 - `admin/registered_projects.php`
 - `admin/registered_engineers.php`
2. Department head queue:
 - `department-head/dashboard.php`
Expected: view/manage approval actions per matrix.
3. `super-admin/employee_accounts.php` should be denied.

## Department Head
1. Open `department-head/dashboard.php`: should load.
2. In project table:
 - `View Details` always visible.
 - `Approve/Reject` visible only when manage permission allows.
3. Notifications panel:
 - if notifications permission exists: loads alerts.
 - otherwise: informative read-only fallback message.
4. Admin manage pages should deny.

## Engineer
1. Open:
 - `engineer/dashboard_overview.php`
 - `engineer/monitoring.php`
 - `engineer/task_milestone.php`
2. `engineer/monitoring.php`:
 - submission review actions visible only with progress review permission.
3. `engineer/task_milestone.php`:
 - if manage permission missing, inputs/buttons disabled and status is read-only.

## Contractor
1. Open:
 - `contractor/dashboard.php`
 - `contractor/progress_monitoring.php`
2. `contractor/dashboard.php`:
 - expense controls visible only with workspace manage permission.
 - progress module link visible only with progress submit permission.
3. `contractor/progress_monitoring.php`:
 - submit inputs/button disabled when submit permission is missing.
 - history view remains readable.

## Quick API checks (DevTools/Network)
- `department-head/api.php?action=decide_project`
- `engineer/api.php?action=decide_progress`
- `engineer/api.php?action=add_task`
- `contractor/api.php?action=update_progress`
- `contractor/api.php?action=update_expense`

Expected for unauthorized role:
- HTTP `403`
- JSON: `{"success": false, ...}`

## CSRF checks
For `POST/PUT/DELETE` endpoints above, submit without token:
- Expected: token error (`419`/`403`) and JSON error response.
