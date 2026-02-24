<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.view', ['contractor','admin','super_admin']);
check_suspicious_activity();
$csrfToken = generate_csrf_token();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /contractor/index.php');
    exit;
}
$role = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['contractor', 'admin', 'super_admin'], true)) {
    header('Location: /contractor/index.php');
    exit;
}
$employeeName = (string)($_SESSION['employee_name'] ?? 'Contractor');
$sidebarInitial = strtoupper(substr($employeeName !== '' ? $employeeName : 'C', 0, 1));
$sidebarRoleLabel = ucwords(str_replace('_', ' ', (string)($_SESSION['employee_role'] ?? 'contractor')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Messages - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
    <link rel="stylesheet" href="../assets/css/project-messages.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/project-messages.css'); ?>">
</head>
<body>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Contractor</span></div>
    <div class="nav-user-profile">
        <div class="user-initial-badge"><?php echo htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-name"><?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-email"><?php echo htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard</a>
        <a href="my_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Projects</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Submit Progress</a>
        <a href="deliverables.php"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Deliverables</a>
        <a href="expenses.php"><img src="../assets/images/admin/budget.png" class="nav-icon" alt="">Expenses / Billing</a>
        <a href="requests.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Requests</a>
        <a href="issues.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Issues</a>
        <a href="messages.php" class="active"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a>
        <a href="notifications.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Notifications</a>
        <a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
</header>

<section class="main-content" data-messages-root data-api-base="/contractor/api.php" data-role="contractor" data-user-id="<?php echo (int)($_SESSION['employee_id'] ?? 0); ?>" data-csrf="<?php echo htmlspecialchars((string)($csrfToken), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dash-header">
        <h1>Project Messages</h1>
        <p>Communicate with your assigned engineer per project.</p>
    </div>
    <div class="messages-layout">
        <aside class="messages-panel">
            <div class="messages-sidebar-head">
                <input id="messageProjectSearch" class="messages-search" type="search" placeholder="Search assigned projects...">
            </div>
            <div id="messageProjectList" class="messages-project-list"></div>
        </aside>
        <section class="messages-panel messages-thread">
            <div class="messages-thread-head">
                <strong id="messageThreadTitle">Select a project</strong>
                <input id="messageThreadSearch" class="messages-search" type="search" placeholder="Search in conversation...">
            </div>
            <div id="messageFeed" class="messages-feed"><div class="messages-empty">Pick a project to open messages.</div></div>
            <div class="messages-composer">
                <input id="messageText" class="messages-text" type="text" placeholder="Type a message...">
                <input id="messageFile" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                <button id="messageSendBtn" class="messages-btn" type="button">Send</button>
            </div>
        </section>
    </div>
</section>
<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
<script src="../assets/js/project-messages.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/project-messages.js'); ?>"></script>
</body>
</html>


