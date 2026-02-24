<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('admin.applications.view', ['admin','department_admin','super_admin']);
check_suspicious_activity();
$csrfToken = generate_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verified Users - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-applications.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-applications.css'); ?>">
</head>
<body data-page="applications-verified">
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div>
    <div class="nav-links">
        <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <div class="nav-item-group">
            <a href="applications_engineers.php" class="nav-main-item" id="applicationsToggle"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Applications<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu" id="contractorsSubmenu">
                <a href="applications_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Engineer Applications</span></a>
                <a href="applications_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Contractor Applications</span></a>
            </div>
        </div>
        <a href="verified_users.php" class="active"><img src="../assets/images/admin/check.png" class="nav-icon" alt="">Verified Users</a>
        <a href="rejected_users.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Rejected / Suspended</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>
<section class="main-content">
    <div class="dash-header"><h1>Verified Users</h1><p>Active approved engineer and contractor accounts.</p></div>
    <div class="pm-section card">
        <div class="app-filter-grid">
            <div><label for="appVerifiedType">Type</label><select id="appVerifiedType"><option value="engineer">Engineers</option><option value="contractor">Contractors</option></select></div>
        </div>
        <div id="appFeedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap"><table class="table app-main-table" id="verifiedTable"><thead><tr><th>Name / Company</th><th>Email</th><th>Specialization</th><th>Status</th><th>Approved At</th></tr></thead><tbody></tbody></table></div>
    </div>
</section>
<script>window.ADMIN_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="../assets/js/admin-applications.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-applications.js'); ?>"></script>
</body>
</html>


