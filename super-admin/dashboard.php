<?php
require_once __DIR__ . '/auth.php';

$stats = [
    'employees' => 0,
    'super_admins' => 0,
    'admins' => 0,
    'inactive' => 0
];

if (isset($db) && !$db->connect_error) {
    $hasRole = super_admin_has_column($db, 'role');
    $hasStatus = super_admin_has_column($db, 'account_status');

    $res = $db->query("SELECT COUNT(*) AS total FROM employees");
    if ($res) {
        $row = $res->fetch_assoc();
        $stats['employees'] = (int)($row['total'] ?? 0);
        $res->free();
    }

    if ($hasRole) {
        $res = $db->query("SELECT LOWER(role) AS role_name, COUNT(*) AS total FROM employees GROUP BY LOWER(role)");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $roleName = strtolower((string)($row['role_name'] ?? ''));
                if ($roleName === 'super_admin') $stats['super_admins'] = (int)$row['total'];
                if ($roleName === 'admin') $stats['admins'] = (int)$row['total'];
            }
            $res->free();
        }
    }

    if ($hasStatus) {
        $res = $db->query("SELECT COUNT(*) AS total FROM employees WHERE LOWER(account_status) <> 'active'");
        if ($res) {
            $row = $res->fetch_assoc();
            $stats['inactive'] = (int)($row['total'] ?? 0);
            $res->free();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
    <header class="sidebar">
        <div class="nav-logo">
            <img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img">
            <span class="logo-text">Super Admin</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="active"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Overview</a>
            <a href="employee_accounts.php"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
            <a href="/admin/dashboard.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Go to Admin Panel</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
        </div>
    </header>

    <section class="main-content">
        <div class="dash-header">
            <h1>Super Admin Control Center</h1>
            <p>High-privilege management for employee accounts and security configuration.</p>
        </div>

        <div class="metrics-grid">
            <div class="metric-card"><h4>Total Employees</h4><p><?php echo (int)$stats['employees']; ?></p></div>
            <div class="metric-card"><h4>Super Admins</h4><p><?php echo (int)$stats['super_admins']; ?></p></div>
            <div class="metric-card"><h4>Admins</h4><p><?php echo (int)$stats['admins']; ?></p></div>
            <div class="metric-card"><h4>Inactive/Suspended</h4><p><?php echo (int)$stats['inactive']; ?></p></div>
        </div>

        <div class="recent-projects" style="margin-top:16px;">
            <h3>Super Admin Actions</h3>
            <p style="margin-bottom:14px;color:#456286;">Manage employee accounts, reset credentials, set roles, and control account status.</p>
            <a href="employee_accounts.php" class="btn">Open Employee Account Manager</a>
        </div>
    </section>
</body>
</html>

