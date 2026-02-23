# Deep Manual Role Walkthrough (Runtime Regression)

Use this when doing final browser verification on live/staging.

## Test Accounts
- `super_admin`
- `admin`
- `department_admin`
- `engineer`
- `contractor`

## Global Checks (All Roles)
1. Login succeeds and lands on role workspace.
2. Open DevTools Console: no JS errors on initial load.
3. Open DevTools Network: API responses are JSON for API routes.
4. Logout works and protected pages redirect to login.
5. Forbidden page direct URL returns redirect or access denied behavior.

## Super Admin
1. Open `super-admin/dashboard.php`.
2. Validate Employee Accounts CRUD.
3. Validate role/status updates and reset password flow.
4. Check security logs page loads and paginates.
5. Confirm super admin-only actions are hidden for non-super-admin sessions.

## Admin
1. Open `admin/dashboard.php`, `admin/project_registration.php`, `admin/registered_projects.php`.
2. Create a project (with CSRF token present in request).
3. Edit/update project status and verify workflow history entries.
4. Open `admin/registered_engineers.php`:
5. Verify status update actions, assignment modal, timeline/history modal.
6. Confirm API-backed lists load without JSON parse errors.

## Department Admin
1. Open department-head dashboard pages.
2. Verify list paging/filtering and no unauthorized management actions.
3. Attempt admin-only endpoint directly and confirm denial.

## Engineer
1. Open `engineer/monitoring.php`, `engineer/task_milestone.php`, `engineer/profile.php`.
2. Review contractor submission approve/reject flow.
3. Confirm progress update appears after approval only.
4. Change password flow validates current password and policy.

## Contractor
1. Open `contractor/dashboard.php`, `contractor/progress_monitoring.php`, `contractor/profile.php`.
2. Submit progress with proof image.
3. Verify submission appears as pending review for engineer.
4. Confirm contractor cannot edit budget/priority/assignment endpoints directly.

## Expected Security Behaviors
- All mutating POST actions require CSRF.
- RBAC checks enforced server-side (not only hidden buttons).
- Rate-limit responses shown for repeated sensitive attempts.

## Pass/Fail Criteria
- PASS: no PHP fatal/parse errors, no major JS console errors, no unauthorized action succeeds.
- FAIL: any role performs disallowed action, any critical API returns HTML/parse error, or workflow data mismatch.
