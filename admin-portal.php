<?php
/**
 * Hidden Admin Portal Access Point
 * Access this via: /admin-portal.php or /system-access.php
 * This is NOT indexed and harder to discover
 */

// Redirect to admin dashboard with additional checks
require_once dirname(__FILE__) . '/includes/security.php';
require_once dirname(__FILE__) . '/session-auth.php';
require_once dirname(__FILE__) . '/database.php';
require_once dirname(__FILE__) . '/config-path.php';

// Log all access attempts to this endpoint
log_security_event('admin_portal_access', [
    'user_id' => $_SESSION['employee_id'] ?? null,
    'timestamp' => date('Y-m-d H:i:s'),
    'path' => $_SERVER['REQUEST_URI']
]);

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    // Redirect to a decoy page instead of showing admin login
    header('Location: /public/index.php');
    exit;
}

// Check if employee has proper authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    log_security_event('unauthorized_admin_access_attempt', [
        'user_id' => $_SESSION['employee_id'] ?? null,
        'claimed_role' => $_SESSION['role'] ?? 'unknown'
    ]);
    header('Location: /public/index.php');
    exit;
}

// Redirect to actual admin dashboard
header('Location: /admin/index.php');
exit;
?>



