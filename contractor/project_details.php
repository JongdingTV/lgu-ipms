<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.view', ['contractor','admin','super_admin']);
check_suspicious_activity();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Project Details - Contractor</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="stylesheet" href="../assets/css/design-system.css">
<link rel="stylesheet" href="../assets/css/components.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
<link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
<link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
<link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
<link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
<link rel="stylesheet" href="../assets/css/contractor-module.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/contractor-module.css'); ?>">
</head><body>
<header class="nav" id="navbar"><div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="" class="logo-img"><span class="logo-text">IPMS Contractor</span></div><div class="nav-links"><a href="my_projects.php" class="active"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Projects</a><a href="messages.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a><a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a></div><div class="nav-divider"></div><div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div></header>
<section class="main-content" data-contractor-module data-module="project-details" data-csrf="<?php echo htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<div class="dash-header"><h1>Project Details</h1><p>Overview, milestones, documents/messages, and progress history.</p></div>
<div id="cmProjectDetails" class="cm-grid"></div>
</section>
<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="../assets/js/contractor-module.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/contractor-module.js'); ?>"></script>
</body></html>
