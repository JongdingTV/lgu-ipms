<?php
require_once dirname(__DIR__) . '/session-auth.php';
require_once dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /super-admin/index.php');
    exit;
}

function super_admin_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'employees'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $exists;
}

$sessionRole = strtolower((string)($_SESSION['employee_role'] ?? ''));
$sessionIsSuperAdmin = !empty($_SESSION['is_super_admin']) || $sessionRole === 'super_admin';

$employeeId = (int)$_SESSION['employee_id'];
$isSuperAdmin = $sessionIsSuperAdmin;

if (isset($db) && !$db->connect_error) {
    $hasRoleColumn = super_admin_has_column($db, 'role');
    if ($hasRoleColumn) {
        $stmt = $db->prepare("SELECT email, role, account_status FROM employees WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $res = $stmt->get_result();
            $emp = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($emp) {
                $dbRole = strtolower(trim((string)($emp['role'] ?? '')));
                $status = strtolower(trim((string)($emp['account_status'] ?? 'active')));
                $isSuperAdmin = $dbRole === 'super_admin';
                $_SESSION['employee_role'] = $dbRole !== '' ? $dbRole : $sessionRole;
                $_SESSION['is_super_admin'] = $isSuperAdmin;
                if ($status !== '' && $status !== 'active') {
                    destroy_session();
                    header('Location: /super-admin/index.php?error=inactive');
                    exit;
                }
            }
        }
    } else {
        // Fallback for legacy schema without role column
        $stmt = $db->prepare("SELECT email FROM employees WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $res = $stmt->get_result();
            $emp = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $isSuperAdmin = $emp && strtolower((string)$emp['email']) === 'admin@lgu.gov.ph';
            $_SESSION['is_super_admin'] = $isSuperAdmin;
            $_SESSION['employee_role'] = $isSuperAdmin ? 'super_admin' : 'employee';
        }
    }
}

if (!$isSuperAdmin) {
    header('Location: /admin/dashboard.php?error=super_admin_only');
    exit;
}

