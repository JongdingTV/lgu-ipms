<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedRoles = ['admin', 'department_admin', 'super_admin'];
$currentRole = strtolower((string)($_SESSION['employee_role'] ?? ''));
if ($currentRole === '' || !in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied.');
}

require __DIR__ . '/registered_contractors.php';
