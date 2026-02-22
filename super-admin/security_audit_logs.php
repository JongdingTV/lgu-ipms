<?php
require_once __DIR__ . '/auth.php';

$logs = [];
$failedAttempts = 0;
$lockedCount = 0;
$loggedInUser = (string)($_SESSION['employee_name'] ?? 'Super Admin');

if (isset($db) && !$db->connect_error) {
    $stmt = $db->prepare("SELECT employee_id, email, ip_address, login_time, status, reason FROM login_logs ORDER BY login_time DESC LIMIT 200");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM login_logs WHERE status = 'failed' AND login_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $failedAttempts = (int)($row['count'] ?? 0);
        $stmt->close();
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM locked_accounts WHERE locked_until > NOW()");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $lockedCount = (int)($row['count'] ?? 0);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Security Audit Logs - IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/super-admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/super-admin.css'); ?>">
</head>
<body class="super-admin-theme">
    <div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
        <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img"><span class="logo-text">Super Admin</span></div>
        <div class="nav-links">
            <a href="/super-admin/dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <a href="/super-admin/progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="/super-admin/employee_accounts.php"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
            <a href="/super-admin/dashboard.php"><img src="../assets/images/admin/check.png" class="nav-icon">Control Center</a>
            <a href="/super-admin/security_audit_logs.php" class="active"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Security Audit Logs</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer"><a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
    </header>
    <div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Security Audit Logs</h1>
            <p>Monitor login activity, failed attempts, and account lockouts.</p>
        </div>

        <div class="sa-audit-grid">
            <article class="sa-audit-item"><h4>Failed Logins (24h)</h4><p><?php echo (int)$failedAttempts; ?></p></article>
            <article class="sa-audit-item"><h4>Locked Accounts</h4><p><?php echo (int)$lockedCount; ?></p></article>
            <article class="sa-audit-item"><h4>Logged In User</h4><p><?php echo htmlspecialchars($loggedInUser, ENT_QUOTES, 'UTF-8'); ?></p></article>
        </div>

        <div class="recent-projects">
            <h3>Login Activity Log</h3>
            <div class="table-wrap">
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>IP Address</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5">No login activity recorded yet.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$log['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string)$log['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime((string)$log['login_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(trim((string)($log['reason'] ?? '')) !== '' ? (string)$log['reason'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>

