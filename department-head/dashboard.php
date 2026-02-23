<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['department_head', 'department_admin', 'admin', 'super_admin']);
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /department-head/index.php');
    exit;
}

$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['department_head', 'department_admin', 'admin', 'super_admin'], true)) {
    header('Location: /department-head/index.php');
    exit;
}

$employeeName = (string) ($_SESSION['employee_name'] ?? 'Department Head');
$csrfToken = (string) generate_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Department Head Dashboard - LGU IPMS</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="department-head.css?v=<?php echo filemtime(__DIR__ . '/department-head.css'); ?>">
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
<div class="sidebar-toggle-wrapper">
    <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
</div>
<header class="nav" id="navbar">
    <div class="nav-logo">
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS Department Head</span>
    </div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <a href="dashboard.php" class="active"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Project Approval</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/department-head/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </a>
</header>
<div class="toggle-btn" id="showSidebarBtn">
    <a href="#" id="toggleSidebarShow" title="Show sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </a>
</div>

<section class="main-content">
    <div class="dash-header">
        <h1>Department Head Approval</h1>
        <p>Welcome, <?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?>. Review projects before admin assigns contractors and engineers.</p>
    </div>

    <div class="dept-stats">
        <div class="dept-stat-card"><div class="label">Pending Review</div><div class="value" id="statPending">0</div></div>
        <div class="dept-stat-card"><div class="label">Approved</div><div class="value" id="statApproved">0</div></div>
        <div class="dept-stat-card"><div class="label">Rejected</div><div class="value" id="statRejected">0</div></div>
        <div class="dept-stat-card"><div class="label">Total Reviewed</div><div class="value" id="statReviewed">0</div></div>
    </div>

    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls">
                <div class="pm-top-row">
                    <div class="pm-left">
                        <label for="searchInput">Search Projects</label>
                        <input id="searchInput" type="search" placeholder="Search by code, name, location...">
                    </div>
                    <div class="pm-right">
                        <div class="filter-group">
                            <label for="statusFilter">Queue</label>
                            <select id="statusFilter">
                                <option value="pending">Pending Review</option>
                                <option value="reviewed">Reviewed History</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="projectsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project</th>
                        <th>Current Status</th>
                        <th>Department Head Decision</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
window.DEPARTMENT_HEAD_CSRF = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="department-head.js?v=<?php echo filemtime(__DIR__ . '/department-head.js'); ?>"></script>
</body>
</html>

