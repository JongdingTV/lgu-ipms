<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.manage', ['contractor','admin','super_admin']);
check_suspicious_activity();
$csrfToken = generate_csrf_token();

$employeeName = (string)($_SESSION['employee_name'] ?? 'Contractor');
$sidebarInitial = strtoupper(substr($employeeName !== '' ? $employeeName : 'C', 0, 1));
$sidebarRoleLabel = ucwords(str_replace('_', ' ', (string)($_SESSION['employee_role'] ?? 'contractor')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Deliverables - Contractor</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
    <link rel="stylesheet" href="../assets/css/contractor-module.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/contractor-module.css'); ?>">
</head>
<body>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Contractor</span></div>
    <div class="nav-user-profile"><div class="user-initial-badge"><?php echo htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></div><div class="nav-user-name"><?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?></div><div class="nav-user-email"><?php echo htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard</a>
        <a href="my_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Assigned Projects</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Submit Progress</a>
        <a href="deliverables.php" class="active"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Deliverables</a>
        <a href="expenses.php"><img src="../assets/images/admin/budget.png" class="nav-icon" alt="">Expenses / Billing</a>
        <a href="requests.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Requests</a>
        <a href="issues.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Issues</a>
        <a href="messages.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a>
        <a href="notifications.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Notifications</a>
        <a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
</header>
<section class="main-content" data-contractor-module data-module="deliverables" data-csrf="<?php echo htmlspecialchars((string)($csrfToken), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dash-header"><h1>Deliverables</h1><p>Submit deliverables for project validation.</p></div>
    <div class="cm-card"><div class="cm-form"><select id="cmProject" class="cm-select"></select><input id="cmDeliverableType" class="cm-input" placeholder="Deliverable Type"><input id="cmMilestoneRef" class="cm-input" placeholder="Milestone Reference"><input id="cmFile" type="file" class="cm-input" accept=".pdf,.jpg,.jpeg,.png"><textarea id="cmRemarks" class="cm-textarea full" placeholder="Remarks"></textarea><button id="cmSubmitDeliverable" class="cm-btn" type="button">Submit Deliverable</button></div><div id="cmFeedback" class="cm-feedback"></div></div>
    <div class="cm-card cm-table-wrap"><table class="cm-table"><thead><tr><th>Project</th><th>Type</th><th>Milestone</th><th>Status</th><th>Date</th></tr></thead><tbody id="cmDeliverablesBody"><tr><td colspan="5">Loading...</td></tr></tbody></table></div>
</section>
<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
<script src="../assets/js/contractor-module.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/contractor-module.js'); ?>"></script>
</body>
</html>


