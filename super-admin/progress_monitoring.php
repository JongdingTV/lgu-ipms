<?php
require_once __DIR__ . '/auth.php';

$projects = [];
if (isset($db) && !$db->connect_error) {
    $res = $db->query("SELECT id, code, name, status, start_date, end_date, location FROM projects ORDER BY created_at DESC LIMIT 100");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $projects[] = $row;
        }
        $res->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Progress Monitoring - IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body class="super-admin-theme">
    <div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
        <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img"><span class="logo-text">Super Admin</span></div>
        <div class="nav-links">
            <a href="/super-admin/dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <a href="/super-admin/progress_monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="/super-admin/employee_accounts.php"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer"><a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
    </header>
    <div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

    <section class="main-content">
        <div class="dash-header"><h1>Progress Monitoring</h1><p>Super Admin progress monitoring view.</p></div>
        <div class="recent-projects">
            <h3>Tracked Projects</h3>
            <div class="table-wrap">
                <table class="feedback-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Location</th><th>Status</th><th>Progress</th><th>Timeline</th></tr></thead>
                    <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="6">No projects found.</td></tr>
                    <?php else: foreach ($projects as $p): 
                        $status = strtolower((string)($p['status'] ?? ''));
                        $progress = $status === 'completed' ? 100 : ($status === 'in progress' ? 55 : 0);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($p['code'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($p['name'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($p['location'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($p['status'] ?? '-')); ?></td>
                            <td><?php echo (int)$progress; ?>%</td>
                            <td><?php echo htmlspecialchars((string)($p['start_date'] ?? '-')); ?> to <?php echo htmlspecialchars((string)($p['end_date'] ?? '-')); ?></td>
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

