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
    <title>Engineer Applications - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-applications.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-applications.css'); ?>">
</head>
<body data-page="applications-engineers">
<div class="sidebar-toggle-wrapper">
    <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
</div>
<header class="nav" id="navbar">
    <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div>
    <div class="nav-links">
        <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <div class="nav-item-group">
            <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu" id="projectRegSubmenu">
                <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
            </div>
        </div>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Progress Monitoring</a>
        <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon" alt="">Budget & Resources</a>
        <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Task & Milestone</a>
        <div class="nav-item-group">
            <a href="#" class="nav-main-item active" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon" alt="">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu show" id="contractorsSubmenu">
                <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
                <a href="applications_engineers.php" class="nav-submenu-item active"><span class="submenu-icon">&#128203;</span><span>Engineer Applications</span></a>
                <a href="applications_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Contractor Applications</span></a>
                <a href="verified_users.php" class="nav-submenu-item"><span class="submenu-icon">&#10003;</span><span>Verified Users</span></a>
                <a href="rejected_users.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Rejected / Suspended</span></a>
            </div>
        </div>
        <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon" alt="">Project Prioritization</a>
        <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Citizen Verification</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

<section class="main-content">
    <div class="dash-header"><h1>Engineer Applications</h1><p>Review, verify, approve, reject, and suspend engineer registrations.</p></div>

    <div class="applications-container">
        <div class="app-summary-grid">
            <div class="app-summary-card"><span class="label">Pending</span><span class="value" id="sumPending">0</span></div>
            <div class="app-summary-card"><span class="label">Under Review</span><span class="value" id="sumUnderReview">0</span></div>
            <div class="app-summary-card"><span class="label">Verified</span><span class="value" id="sumVerified">0</span></div>
            <div class="app-summary-card"><span class="label">Approved</span><span class="value" id="sumApproved">0</span></div>
            <div class="app-summary-card"><span class="label">Rejected</span><span class="value" id="sumRejected">0</span></div>
        </div>

        <div class="pm-section card">
            <div class="app-filter-grid">
                <div><label for="appSearch">Search</label><input id="appSearch" type="search" placeholder="Name, email, specialization"></div>
                <div><label for="appStatus">Status</label><select id="appStatus"><option value="">All</option><option value="pending">Pending</option><option value="under_review">Under Review</option><option value="verified">Verified</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="suspended">Suspended</option></select></div>
                <div><label for="appSpecialization">Specialization</label><input id="appSpecialization" type="text" placeholder="e.g. Civil"></div>
                <div><label for="appArea">District / Area</label><input id="appArea" type="text" placeholder="District/Barangay"></div>
                <div><label for="appDateSubmitted">Date Submitted</label><input id="appDateSubmitted" type="date"></div>
                <div class="app-filter-actions"><button type="button" class="btn-primary" id="appApplyFilters">Apply</button><button type="button" class="btn-secondary" id="appResetFilters">Reset</button></div>
            </div>
            <div id="appFeedback" class="ac-c8be1ccb"></div>
            <div class="table-wrap">
                <table class="table app-main-table" id="appTable">
                    <thead><tr><th>Applicant</th><th>Type</th><th>Status</th><th>Submitted</th><th>Area / Specialization</th><th>Email</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>window.ADMIN_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="../assets/js/admin-nav-fix.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-nav-fix.js'); ?>"></script>
<script src="../assets/js/admin-applications.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-applications.js'); ?>"></script>
</body>
</html>


