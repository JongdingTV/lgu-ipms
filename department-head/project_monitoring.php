<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('department_head.monitoring.view', ['department_head', 'department_admin', 'admin', 'super_admin']);
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /department-head/index.php');
    exit;
}
$csrfToken = (string) generate_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Department Head - Project Monitoring</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="department-head.css?v=<?php echo filemtime(__DIR__ . '/department-head.css'); ?>">
</head>
<body data-page="project-monitoring">
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Department Head</span></div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard</a>
        <a href="dashboard.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Project Approval</a>
        <a href="project_monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
        <a href="priority_control.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon" alt="">Priority Control</a>
        <a href="risk_alerts.php"><img src="../assets/images/admin/checking.png" class="nav-icon" alt="">Risk Alerts</a>
        <a href="reports.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Reports</a>
        <a href="decision_logs.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Decision Logs</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/department-head/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

<section class="main-content">
    <div class="dash-header">
        <h1>Project Monitoring</h1>
        <p>Oversight view of active and governed infrastructure projects.</p>
    </div>
    <div class="dept-stats">
        <div class="dept-stat-card"><div class="label">Total Projects</div><div class="value" id="dhMonTotal">0</div></div>
        <div class="dept-stat-card"><div class="label">Active</div><div class="value" id="dhMonActive">0</div></div>
        <div class="dept-stat-card"><div class="label">Delayed</div><div class="value" id="dhMonDelayed">0</div></div>
        <div class="dept-stat-card"><div class="label">Completed</div><div class="value" id="dhMonCompleted">0</div></div>
    </div>
    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls dept-module-filters">
                <div class="filter-group"><label for="dhSearch">Search</label><input id="dhSearch" type="search" placeholder="Code, project, location"></div>
                <div class="filter-group"><label for="dhStatus">Status</label><select id="dhStatus"><option value="">All</option><option>Approved</option><option>For Approval</option><option>Ongoing</option><option>Delayed</option><option>Completed</option></select></div>
                <div class="filter-group"><label for="dhDistrict">District</label><input id="dhDistrict" type="text" placeholder="District"></div>
                <div class="filter-group"><label for="dhBarangay">Barangay</label><input id="dhBarangay" type="text" placeholder="Barangay"></div>
                <div class="filter-group"><label for="dhEngineer">Engineer</label><input id="dhEngineer" type="text" placeholder="Engineer"></div>
                <div class="filter-group"><label for="dhContractor">Contractor</label><input id="dhContractor" type="text" placeholder="Contractor"></div>
                <div class="filter-group"><label for="dhPriority">Priority</label><select id="dhPriority"><option value="">All</option><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select></div>
                <div class="filter-group filter-actions"><button type="button" id="dhMonitoringApply" class="dept-btn details">Apply Filters</button></div>
            </div>
        </div>
        <div id="dhFeedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="dhMonitoringTable">
                <thead><tr><th>Code</th><th>Project</th><th>Status</th><th>Progress</th><th>Priority</th><th>Engineer</th><th>Contractor</th><th>Start</th><th>End</th><th>Delay</th><th>Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>window.DEPARTMENT_HEAD_CSRF = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="module-pages.js?v=<?php echo filemtime(__DIR__ . '/module-pages.js'); ?>"></script>
</body>
</html>
