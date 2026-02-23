<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('department_head.reports.view', ['department_head', 'department_admin', 'admin', 'super_admin']);
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
    <title>Department Head - Reports</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="department-head.css?v=<?php echo filemtime(__DIR__ . '/department-head.css'); ?>">
</head>
<body data-page="reports">
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Department Head</span></div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard</a>
        <a href="dashboard.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Project Approval</a>
        <a href="project_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
        <a href="priority_control.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon" alt="">Priority Control</a>
        <a href="risk_alerts.php"><img src="../assets/images/admin/checking.png" class="nav-icon" alt="">Risk Alerts</a>
        <a href="reports.php" class="active"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Reports</a>
        <a href="decision_logs.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Decision Logs</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/department-head/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

<section class="main-content">
    <div class="dash-header"><h1>Reports</h1><p>Generate governance summaries for monthly, budget, progress, and delayed projects.</p></div>

    <div class="dept-stats" id="dhReportSummaryCards">
        <div class="dept-stat-card"><div class="label">Total Projects</div><div class="value" id="dhSummaryTotal">0</div></div>
        <div class="dept-stat-card"><div class="label">Approved</div><div class="value" id="dhSummaryApproved">0</div></div>
        <div class="dept-stat-card"><div class="label">Ongoing</div><div class="value" id="dhSummaryOngoing">0</div></div>
        <div class="dept-stat-card"><div class="label">Delayed</div><div class="value" id="dhSummaryDelayed">0</div></div>
    </div>

    <div class="pm-section card dept-report-grid">
        <article class="dept-report-card" data-report-type="monthly">
            <strong>Monthly Report</strong>
            <span>Project count, statuses, and governance overview.</span>
            <div class="dept-report-actions">
                <button type="button" data-export-format="pdf" class="dept-btn details">PDF</button>
                <button type="button" data-export-format="excel" class="dept-btn approve">Excel</button>
            </div>
        </article>
        <article class="dept-report-card" data-report-type="budget">
            <strong>Budget Utilization Report</strong>
            <span>Allocated budget with spending context.</span>
            <div class="dept-report-actions">
                <button type="button" data-export-format="pdf" class="dept-btn details">PDF</button>
                <button type="button" data-export-format="excel" class="dept-btn approve">Excel</button>
            </div>
        </article>
        <article class="dept-report-card" data-report-type="progress">
            <strong>Progress Summary</strong>
            <span>Project lifecycle status and completion pacing.</span>
            <div class="dept-report-actions">
                <button type="button" data-export-format="pdf" class="dept-btn details">PDF</button>
                <button type="button" data-export-format="excel" class="dept-btn approve">Excel</button>
            </div>
        </article>
        <article class="dept-report-card" data-report-type="delayed">
            <strong>Delayed Projects Report</strong>
            <span>Timeline risks and escalation targets.</span>
            <div class="dept-report-actions">
                <button type="button" data-export-format="pdf" class="dept-btn details">PDF</button>
                <button type="button" data-export-format="excel" class="dept-btn approve">Excel</button>
            </div>
        </article>
    </div>

    <div id="dhFeedback" class="ac-c8be1ccb"></div>
</section>

<script>window.DEPARTMENT_HEAD_CSRF = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="module-pages.js?v=<?php echo filemtime(__DIR__ . '/module-pages.js'); ?>"></script>
</body>
</html>
