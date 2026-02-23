<?php
/**
 * Centralized RBAC documentation map.
 *
 * This file is intentionally simple and framework-free so legacy pages
 * can use the same source of truth for allowed roles per module/action.
 */
return [
    'admin.dashboard.view' => ['admin', 'department_admin', 'super_admin'],
    'admin.projects.manage' => ['admin', 'department_admin', 'super_admin'],
    'admin.progress.view' => ['admin', 'department_admin', 'super_admin'],
    'admin.budget.manage' => ['admin', 'department_admin', 'super_admin'],
    'admin.prioritization.manage' => ['admin', 'department_admin', 'super_admin'],
    'admin.citizen_verification.manage' => ['admin', 'department_admin', 'super_admin'],
    'admin.engineers.manage' => ['admin', 'department_admin', 'super_admin'],
    'admin.engineers.assign' => ['admin', 'department_admin', 'super_admin'],
    'admin.legacy.contractors_alias' => ['admin', 'department_admin', 'super_admin'],
    'admin.audit.view' => ['admin', 'department_admin', 'super_admin'],
    'admin.db_health.run' => ['super_admin'],
    'super_admin.dashboard.view' => ['super_admin'],
    'super_admin.employee_accounts.manage' => ['super_admin'],
    'super_admin.audit.view' => ['super_admin'],
    'super_admin.progress.view' => ['super_admin'],
];

