<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('engineer.workspace.view', ['engineer','admin','super_admin']);
check_suspicious_activity();
$csrfToken = generate_csrf_token();
$name = (string)($_SESSION['employee_name'] ?? 'Engineer');
$initial = strtoupper(substr($name !== '' ? $name : 'E', 0, 1));
$roleLabel = ucwords(str_replace('_', ' ', (string)($_SESSION['employee_role'] ?? 'engineer')));
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Assigned Projects - Engineer</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="stylesheet" href="../assets/css/design-system.css"><link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
<link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
<link rel="stylesheet" href="../assets/css/admin-component-overrides.css"><link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
<link rel="stylesheet" href="engineer.css?v=<?php echo filemtime(__DIR__ . '/engineer.css'); ?>"><link rel="stylesheet" href="../assets/css/engineer-module.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/engineer-module.css'); ?>">
</head><body>
<header class="nav" id="navbar"><div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="" class="logo-img"><span class="logo-text">IPMS Engineer</span></div>
<div class="nav-user-profile"><div class="user-initial-badge"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div><div class="nav-user-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div><div class="nav-user-email"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div></div>
<div class="nav-links">
<a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
<a href="assigned_projects.php" class="active"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Assigned Projects</a>
<a href="task_milestone.php"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Tasks & Milestones</a>
<a href="submissions_validation.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Submissions for Validation</a>
<a href="site_reports.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Site Reports</a>
<a href="inspection_requests.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Inspection Requests</a>
<a href="issues_risks.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Issues & Risks</a>
<a href="documents.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Documents</a>
<a href="messages.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a>
<a href="notifications.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Notifications</a>
<a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a></div>
<div class="nav-divider"></div><div class="nav-action-footer"><a href="/engineer/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div></header>
<section class="main-content em-shell" data-engineer-module data-module="assigned-projects" data-csrf="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="dash-header"><h1>My Assigned Projects</h1><p>Project list with search, filters, quick actions, and quick view.</p></div>
<div class="em-card"><div class="em-toolbar"><input id="emSearch" class="em-input" placeholder="Search code, name, location"><select id="emStatus" class="em-select"><option value="">All Status</option><option>Approved</option><option>For Approval</option><option>Completed</option></select><select id="emPriority" class="em-select"><option value="">All Priority</option><option>Crucial</option><option>High</option><option>Medium</option><option>Low</option></select><button id="emPrev" class="em-btn secondary" type="button">Prev</button><button id="emNext" class="em-btn secondary" type="button">Next</button><span id="emPagination"></span></div></div>
<div class="em-card em-table-wrap"><table class="em-table"><thead><tr><th>Code</th><th>Name</th><th>Location</th><th>Priority</th><th>Timeline</th><th>Progress</th><th>Status</th><th>Contractor</th><th>Action</th></tr></thead><tbody id="emAssignedProjectsBody"><tr><td colspan="9">Loading...</td></tr></tbody></table></div>
<div class="em-modal" id="emQuickViewModal"><div class="em-modal-card"><button class="em-btn secondary" id="emQuickViewClose" type="button">Close</button><div id="emQuickViewBody"></div></div></div>
</section>
<script src="engineer.js?v=<?php echo filemtime(__DIR__ . '/engineer.js'); ?>"></script><script src="engineer-enterprise.js?v=<?php echo filemtime(__DIR__ . '/engineer-enterprise.js'); ?>"></script><script src="../assets/js/engineer-module.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/engineer-module.js'); ?>"></script>
</body></html>
