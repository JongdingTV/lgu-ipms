<?php
require_once __DIR__ . '/auth.php';

$totalProjects = 0;
$inProgress = 0;
$completed = 0;
$totalBudget = 0.0;
$recentProjects = [];

if (isset($db) && !$db->connect_error) {
    $res = $db->query("SELECT COUNT(*) AS total FROM projects");
    if ($res) {
        $row = $res->fetch_assoc();
        $totalProjects = (int)($row['total'] ?? 0);
        $res->free();
    }

    $res = $db->query("SELECT COUNT(*) AS total FROM projects WHERE LOWER(status) = 'in progress'");
    if ($res) {
        $row = $res->fetch_assoc();
        $inProgress = (int)($row['total'] ?? 0);
        $res->free();
    }

    $res = $db->query("SELECT COUNT(*) AS total FROM projects WHERE LOWER(status) = 'completed'");
    if ($res) {
        $row = $res->fetch_assoc();
        $completed = (int)($row['total'] ?? 0);
        $res->free();
    }

    $res = $db->query("SELECT COALESCE(SUM(budget), 0) AS total_budget FROM projects");
    if ($res) {
        $row = $res->fetch_assoc();
        $totalBudget = (float)($row['total_budget'] ?? 0);
        $res->free();
    }

    $res = $db->query("SELECT id, code, name, location, status, budget, created_at FROM projects ORDER BY created_at DESC LIMIT 8");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $recentProjects[] = $row;
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
    <title>Super Admin Dashboard Overview - IPMS</title>
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
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img"><span class="logo-text">Super Admin</span></div>
        <div class="nav-links">
            <a href="/super-admin/dashboard_overview.php" class="active"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <a href="/super-admin/progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="/super-admin/employee_accounts.php"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
            <a href="/super-admin/dashboard.php"><img src="../assets/images/admin/check.png" class="nav-icon">Control Center</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer"><a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </a>
    </header>
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Dashboard Overview</h1>
            <p>Super Admin view of core project metrics.</p>
        </div>

        <div class="sa-kpi-grid">
            <article class="sa-kpi-card">
                <p class="sa-kpi-value"><?php echo (int)$totalProjects; ?></p>
                <p class="sa-kpi-label">Total Projects</p>
            </article>
            <article class="sa-kpi-card">
                <p class="sa-kpi-value"><?php echo (int)$inProgress; ?></p>
                <p class="sa-kpi-label">In Progress</p>
            </article>
            <article class="sa-kpi-card">
                <p class="sa-kpi-value"><?php echo (int)$completed; ?></p>
                <p class="sa-kpi-label">Completed</p>
            </article>
            <article class="sa-kpi-card">
                <p class="sa-kpi-value">PHP <?php echo number_format($totalBudget, 2); ?></p>
                <p class="sa-kpi-label">Total Budget</p>
            </article>
        </div>

        <div class="recent-projects" style="margin-top:16px;">
            <h3>Recent Projects</h3>
            <div class="table-wrap">
                <table class="feedback-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Location</th><th>Status</th><th>Budget</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentProjects)): ?>
                        <tr><td colspan="6">No projects found.</td></tr>
                    <?php else: foreach ($recentProjects as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($row['code'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['name'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['location'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['status'] ?? '-')); ?></td>
                            <td>PHP <?php echo number_format((float)($row['budget'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['created_at'] ?? '-')); ?></td>
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
