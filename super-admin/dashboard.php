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
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/super-admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/super-admin.css'); ?>">
</head>
<body class="super-admin-theme">
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img">
            <span class="logo-text">Super Admin</span>
        </div>
        <div class="nav-links">
            <a href="/super-admin/dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <a href="/super-admin/progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="/super-admin/registered_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon">Registered Projects</a>
            <a href="employee_accounts.php"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
            <a href="dashboard.php" class="active"><img src="../assets/images/admin/check.png" class="nav-icon">Control Center</a>
            <a href="/admin/db-health-check.php"><img src="../assets/images/admin/check.png" class="nav-icon">System Health</a>
            <a href="/admin/audit-logs.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Security Audit Logs</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Super Admin Control Center</h1>
            <p>Same admin workspace with advanced security governance and account control.</p>
        </div>

        <div class="pm-stats-wrapper">
            <div class="stat-box stat-total">
                <div class="stat-number"><?php echo (int)$stats['employees']; ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-box stat-approved">
                <div class="stat-number"><?php echo (int)$stats['super_admins']; ?></div>
                <div class="stat-label">Super Admins</div>
            </div>
            <div class="stat-box stat-progress">
                <div class="stat-number"><?php echo (int)$stats['admins']; ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-box stat-completed">
                <div class="stat-number"><?php echo (int)$stats['inactive']; ?></div>
                <div class="stat-label">Inactive/Suspended</div>
            </div>
        </div>

        <div class="recent-projects" style="margin-top:16px;">
            <h3>Super Admin Actions</h3>
            <p style="margin-bottom:14px;color:#456286;">Manage employee accounts, reset credentials, set roles, and oversee system security.</p>
            <div class="feedback-actions">
                <a href="employee_accounts.php" class="view-btn">Open Employee Account Manager</a>
                <a href="/admin/audit-logs.php" class="view-btn">Open Audit Logs</a>
                <a href="/admin/db-health-check.php" class="view-btn">Run DB Health Check</a>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>
