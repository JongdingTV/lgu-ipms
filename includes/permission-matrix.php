<?php
/**
 * IPMS Role/Action Permission Matrix (reference source of truth)
 *
 * Keep this file synchronized with endpoint-level `rbac_require_action_roles(...)`
 * checks as modules evolve.
 */

function ipms_permission_matrix(): array
{
    return [
        'admin.registered_projects' => [
            'load_projects' => ['admin', 'department_admin', 'super_admin'],
            'get_project' => ['admin', 'department_admin', 'super_admin'],
            'load_project_timeline' => ['admin', 'department_admin', 'super_admin'],
            'update_project' => ['admin', 'department_admin', 'super_admin'],
            'delete_project' => ['admin', 'super_admin'],
        ],
        'admin.project_registration' => [
            'load_projects' => ['admin', 'department_admin', 'super_admin'],
            'save_project' => ['admin', 'department_admin', 'super_admin'],
            'delete_project' => ['admin', 'super_admin'],
        ],
        'admin.progress_monitoring' => [
            'load_projects' => ['admin', 'department_admin', 'super_admin'],
            'load_status_requests' => ['admin', 'department_admin', 'super_admin'],
            'admin_decide_status_request' => ['admin', 'department_admin', 'super_admin'],
        ],
        'admin.registered_engineers' => [
            'load_contractors' => ['admin', 'department_admin', 'super_admin'],
            'load_projects' => ['admin', 'department_admin', 'super_admin'],
            'load_contractor_documents' => ['admin', 'department_admin', 'super_admin'],
            'load_approval_history' => ['admin', 'department_admin', 'super_admin'],
            'load_evaluation_overview' => ['admin', 'department_admin', 'super_admin'],
            'recommended_engineers' => ['admin', 'department_admin', 'super_admin'],
            'get_assigned_projects' => ['admin', 'department_admin', 'super_admin'],
            'verify_contractor_document' => ['admin', 'department_admin', 'super_admin'],
            'update_contractor_approval' => ['admin', 'department_admin', 'super_admin'],
            'assign_contractor' => ['admin', 'department_admin', 'super_admin'],
            'unassign_contractor' => ['admin', 'department_admin', 'super_admin'],
            'evaluate_contractor' => ['admin', 'department_admin', 'super_admin'],
            'delete_contractor' => ['admin', 'super_admin'],
        ],
        'admin.legacy' => [
            'manage_employees' => ['super_admin'],
            'session_debug' => ['super_admin'],
            'audit_logs' => ['admin', 'department_admin', 'super_admin'],
            'change_password' => ['admin', 'department_admin', 'super_admin'],
        ],
        'admin.engineers_api' => [
            'list_engineers' => ['admin', 'department_admin', 'super_admin'],
            'create_with_docs' => ['admin', 'department_admin', 'super_admin'],
            'create_engineer' => ['admin', 'department_admin', 'super_admin'],
            'update_engineer' => ['admin', 'department_admin', 'super_admin'],
            'delete_engineer' => ['admin', 'super_admin'],
        ],
        'admin.notifications_api' => [
            'read_notifications' => ['admin', 'department_admin', 'super_admin'],
        ],
        'admin.db_health_check' => [
            'read_schema_health' => ['super_admin'],
        ],
        'super_admin' => [
            'all' => ['super_admin'],
        ],
    ];
}
