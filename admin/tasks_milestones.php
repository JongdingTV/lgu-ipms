<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
$allowedViewRoles = array_values(array_unique(array_merge(
    rbac_roles_for('admin.progress.view', ['admin', 'department_admin', 'super_admin']),
    rbac_roles_for('engineer.progress.review', ['engineer'])
)));
rbac_require_roles($allowedViewRoles);
check_suspicious_activity();

$employeeRole = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
$canValidate = in_array($employeeRole, array_values(array_unique(array_merge(
    rbac_roles_for('admin.progress.manage', ['admin', 'department_admin', 'super_admin']),
    rbac_roles_for('engineer.progress.review', ['engineer'])
))), true);
$csrfToken = generate_csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Task & Milestone - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/table-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-tasks-validation.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-tasks-validation.css'); ?>">
</head>
<body class="tasks-validation-page">
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div>
        <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php" class="active"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="registered_engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
                    <a href="applications_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Engineer Applications</span></a>
                    <a href="applications_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Contractor Applications</span></a>
                    <a href="verified_users.php" class="nav-submenu-item"><span class="submenu-icon">&#10003;</span><span>Verified Users</span></a>
                    <a href="rejected_users.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Rejected / Suspended</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/admin/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
    </div>

    <section class="main-content validation-workflow-page" data-can-validate="<?php echo $canValidate ? '1' : '0'; ?>" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="dash-header">
            <h1>Checking and Validation</h1>
            <p>Workflow-based deliverable validation with full audit trail, revision flow, and computed progress.</p>
        </div>

        <div class="validation-summary-grid">
            <article class="validation-summary-card"><span>Total Deliverables</span><strong id="sumTotal">0</strong></article>
            <article class="validation-summary-card approved"><span>Approved</span><strong id="sumApproved">0</strong></article>
            <article class="validation-summary-card pending"><span>Pending Review</span><strong id="sumPending">0</strong></article>
            <article class="validation-summary-card rejected"><span>Rejected / Returned</span><strong id="sumRejected">0</strong></article>
            <article class="validation-summary-card progress"><span>Overall Validation Progress</span><strong id="sumPercent">0%</strong></article>
        </div>

        <div class="validation-progress-rail"><div class="validation-progress-fill" id="overallValidationBar"></div></div>

        <div class="validation-controls card">
            <div class="validation-control-grid">
                <div class="filter-group full"><label for="tvSearch">Search</label><input id="tvSearch" type="search" placeholder="Search by project code, project name, or deliverable"></div>
                <div class="filter-group"><label for="tvStatus">Status</label><select id="tvStatus"><option value="">All Status</option><option value="Pending">Pending</option><option value="Submitted">Submitted</option><option value="For Approval">For Approval</option><option value="Approved">Approved</option><option value="Rejected">Rejected</option><option value="Needs Revision">Needs Revision</option></select></div>
                <div class="filter-group"><label for="tvSector">Sector</label><input id="tvSector" type="text" placeholder="Sector filter"></div>
                <div class="filter-group"><label for="tvDateField">Date Field</label><select id="tvDateField"><option value="submitted">Submitted Date</option><option value="validated">Validated Date</option></select></div>
                <div class="filter-group"><label for="tvDateFrom">Date From</label><input id="tvDateFrom" type="date"></div>
                <div class="filter-group"><label for="tvDateTo">Date To</label><input id="tvDateTo" type="date"></div>
                <div class="filter-group"><label for="tvSort">Sort</label><select id="tvSort"><option value="newest_submitted">Newest Submitted</option><option value="oldest_pending">Oldest Pending</option><option value="highest_priority">Highest Priority</option></select></div>
                <div class="filter-group"><label for="tvPerPage">Items Per Page</label><select id="tvPerPage"><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option></select></div>
            </div>
            <div class="validation-controls-actions">
                <button type="button" class="btn-clear-filters" id="tvApplyFilters">Apply</button>
                <button type="button" class="btn-clear-filters" id="tvClearFilters">Clear</button>
                <span class="pm-result-summary" id="tvResultMeta">Showing 0 items</span>
            </div>
        </div>

        <div id="tvFeedback" class="ac-c8be1ccb"></div>
        <div id="tvAccordion" class="validation-project-accordion"></div>
        <div class="pm-pagination-controls" id="tvPager"></div>
    </section>

    <div class="modal" id="validationDetailModal" hidden>
        <div class="modal-content validation-detail-modal-content">
            <div class="modal-header">
                <h3 id="validationDetailTitle">Validation Details</h3>
                <button class="close-modal" data-close-modal="validationDetailModal">&times;</button>
            </div>
            <div class="modal-body" id="validationDetailBody"></div>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-tasks-validation.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-tasks-validation.js'); ?>"></script>
</body>
</html>


