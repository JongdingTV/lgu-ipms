# Release Prep Checklist (IPMS)

## 1) Pre-Release Backup
1. Put app in maintenance/read-only mode (if available).
2. Backup DB:
```bash
mysqldump -u <user> -p --routines --triggers --single-transaction <db_name> > backup_pre_release.sql
```
3. Backup uploaded files:
```bash
tar -czf uploads_pre_release.tar.gz uploads/
```
4. Backup current code revision/tag.

## 2) Migration Order
Run in this order:
1. `database/migrations/2026_02_23_ipms_core_rbac_workflow.sql`
2. `database/migrations/2026_02_23_project_workflow_history.sql`
3. `database/migrations/2026_02_20_admin_enhancements_compat.sql`
4. `database/migrations/2026_02_21_engineer_hiring_module.sql`
5. `database/migrations/2026_02_21_engineers_registration.sql`
6. `database/migrations/2026_02_22_super_admin_module.sql`
7. `database/migrations/2026_02_22_engineers_account_access_optional.sql`
8. `database/migrations/2026_02_22_engineers_link_employees.sql`
9. `database/migrations/2026_02_23_engineer_contractor_approval_status.sql`
10. `database/migrations/2026_02_23_engineers_schema_backfill.sql`
11. `database/migrations/2026_02_23_performance_indexes_compat.sql`

Notes:
- If a migration already ran, skip safely (most scripts are compatibility-safe).
- Always run on staging first.

## 3) Post-Migration Validation
1. Run PHP syntax checks on changed modules.
2. Run automation checks:
```bash
php scripts/check-rbac-consistency.php
php scripts/check-security-guards.php
php scripts/check-ui-api-action-parity.php
```
3. Open critical pages:
- Admin Dashboard
- Project Registration
- Registered Engineers
- Progress Monitoring
- Super Admin Employee Accounts
4. Confirm no API returns HTML where JSON is expected.

## 4) Rollback Plan
If critical failure occurs:
1. Disable writes / maintenance mode on.
2. Revert code to previous stable tag/commit.
3. Restore database:
```bash
mysql -u <user> -p <db_name> < backup_pre_release.sql
```
4. Restore uploads archive if needed.
5. Re-run smoke checks and verify login + key workflows.

## 5) Go/No-Go Gates
- Go only if:
1. No fatal/parse errors in logs.
2. RBAC checks pass.
3. CSRF-protected forms still submit correctly.
4. Role walkthrough major paths pass.
- No-Go if:
1. Any unauthorized action is possible.
2. Any critical list/form endpoint fails JSON/DB validation.
