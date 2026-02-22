<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userStmt = $db->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : [];
$userStmt->close();
$userName = trim($_SESSION['user_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$userEmail = $user['email'] ?? '';
$canAccessFeedback = user_feedback_access_allowed($db, $userId);
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);
$progressUpdatedAt = date('M d, Y h:i A');

function user_progress_has_created_at(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = 'created_at'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

function user_progress_has_progress_column(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = 'progress'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'load_projects') {
    header('Content-Type: application/json');

    $hasCreatedAt = user_progress_has_created_at($db);
    $hasProgress = user_progress_has_progress_column($db);
    $selectCreatedAt = $hasCreatedAt ? ', created_at' : '';
    $selectProgress = $hasProgress ? ', COALESCE(progress, 0) AS progress' : ', 0 AS progress';
    $orderBy = $hasCreatedAt ? 'created_at DESC' : 'id DESC';

    $result = $db->query("SELECT id, code, name, description, location, province, sector, budget, status, start_date, end_date, duration_months{$selectCreatedAt}{$selectProgress} FROM projects ORDER BY {$orderBy} LIMIT 500");

    $projects = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!isset($row['progress'])) {
                $row['progress'] = 0;
            }
            $projects[] = $row;
        }
        $result->free();
    }

    echo json_encode($projects);
    $db->close();
    exit;
}

$db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - User View</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="/assets/css/table-redesign-base.css">
    <link rel="stylesheet" href="/assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="/user-dashboard/user-shell.css?v=<?php echo filemtime(__DIR__ . '/user-shell.css'); ?>">
    <?php echo get_app_config_script(); ?>
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
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div class="nav-logo">
            <img src="/assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-user-profile">
            <?php if ($profileImageWebPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($profileImageWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="user-profile-image">
            <?php else: ?>
                <div class="user-initial-badge" style="background: <?php echo htmlspecialchars($avatarColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="nav-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="nav-user-email"><?php echo htmlspecialchars($userEmail ?: 'No email provided', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="nav-links">
            <a href="user-dashboard.php"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php" class="active"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring Icon" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/assets/images/admin/prioritization.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/assets/images/admin/person.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </div>

        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
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
            <h1>Progress Monitoring</h1>
            <p>Track projects in your area, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="margin:6px 0 0;font-size:.82rem;color:#64748b;">Last updated: <?php echo htmlspecialchars($progressUpdatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="pm-section card">
            <?php if (!$canAccessFeedback): ?>
                <p style="margin:0 0 14px;color:#92400e;font-weight:600;">You can view all public project details. Verify your ID to unlock feedback submission.</p>
            <?php endif; ?>
            <div class="pm-stats-wrapper">
                <div class="stat-box stat-total"><div class="stat-number" id="statTotal">0</div><div class="stat-label">Total Projects</div></div>
                <div class="stat-box stat-approved"><div class="stat-number" id="statApproved">0</div><div class="stat-label">Approved</div></div>
                <div class="stat-box stat-progress"><div class="stat-number" id="statInProgress">0</div><div class="stat-label">In Progress</div></div>
                <div class="stat-box stat-completed"><div class="stat-number" id="statCompleted">0</div><div class="stat-label">Completed</div></div>
            </div>

            <div class="pm-controls-wrapper">
                <div class="pm-controls">
                    <div class="pm-top-row">
                        <div class="pm-left">
                            <label for="pmSearch">Search Projects</label>
                            <input id="pmSearch" type="search" placeholder="Search by code, name, or location...">
                        </div>
                    </div>

                    <div class="pm-right">
                        <div class="filter-group">
                            <label for="pmStatusFilter">Status</label>
                            <select id="pmStatusFilter"><option value="">All Status</option><option>Draft</option><option>For Approval</option><option>Approved</option><option>On-hold</option><option>Cancelled</option><option>Completed</option></select>
                        </div>
                        <div class="filter-group">
                            <label for="pmSectorFilter">Sector</label>
                            <select id="pmSectorFilter"><option value="">All Sectors</option><option>Road</option><option>Drainage</option><option>Building</option><option>Water</option><option>Sanitation</option><option>Other</option></select>
                        </div>
                        <div class="filter-group">
                            <label for="pmProgressFilter">Progress</label>
                            <select id="pmProgressFilter"><option value="">All Progress</option><option value="0-25">0-25%</option><option value="25-50">25-50%</option><option value="50-75">50-75%</option><option value="75-100">75-100%</option></select>
                        </div>
                        <div class="filter-group">
                            <label for="pmSort">Sort</label>
                            <select id="pmSort"><option value="createdAt_desc">Newest</option><option value="createdAt_asc">Oldest</option><option value="progress_desc">Progress (high to low)</option><option value="progress_asc">Progress (low to high)</option></select>
                        </div>
                    </div>

                    <div class="pm-bottom-row">
                        <div id="pmQuickFilters" class="pm-quick-filters" aria-label="Quick status filters">
                            <button type="button" data-status="" class="active">All</button>
                            <button type="button" data-status="For Approval">For Approval</button>
                            <button type="button" data-status="Approved">Approved</button>
                            <button type="button" data-status="On-hold">On-hold</button>
                            <button type="button" data-status="Completed">Completed</button>
                        </div>
                        <div class="pm-utility-row">
                            <span id="pmResultSummary" class="pm-result-summary">Showing 0 of 0 projects</span>
                            <button id="pmClearFilters" type="button" class="btn-clear-filters">Clear Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pm-content">
                <h3>Tracked Projects</h3>
                <div id="projectsList" class="projects-list"></div>
                <div id="pmEmpty" class="pm-empty ac-c8be1ccb">
                    <div class="empty-state">
                        <div class="empty-icon">No Match</div>
                        <p>No projects match your filters</p>
                        <small>Try adjusting your search criteria</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .main-content .dash-header {
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), rgba(14, 116, 144, 0) 44%), linear-gradient(145deg, #ffffff, #f7fbff);
            border: 1px solid #d9e7f7;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
            margin-bottom: 14px;
        }

        .main-content .dash-header h1 {
            margin: 0 0 4px;
            color: #12355b;
            font-size: 1.85rem;
            letter-spacing: 0.2px;
        }

        .main-content .dash-header p {
            margin: 0;
            color: #4d6480;
            font-weight: 500;
        }

        .main-content .pm-section.card {
            border-radius: 16px;
            border: 1px solid #d8e6f4;
            background: linear-gradient(165deg, #ffffff 0%, #f8fbff 72%);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.1);
            padding: 16px;
        }

        .main-content .pm-stats-wrapper {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .main-content .stat-box {
            border-radius: 14px;
            border: 1px solid #dae6f5;
            background: #ffffff;
            min-height: 88px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 14px 12px;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
            position: relative;
            overflow: hidden;
        }

        .main-content .stat-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background: #3b82f6;
            opacity: 0.85;
        }

        .main-content .stat-box.stat-total::before { background: #2563eb; }
        .main-content .stat-box.stat-approved::before { background: #16a34a; }
        .main-content .stat-box.stat-progress::before { background: #f59e0b; }
        .main-content .stat-box.stat-completed::before { background: #0ea5e9; }
        .main-content .stat-box.stat-contractors::before { background: #7c3aed; }

        .main-content .stat-number {
            font-size: 1.7rem;
            line-height: 1;
            font-weight: 700;
            color: #12355b;
            margin-bottom: 6px;
        }

        .main-content .stat-label {
            font-size: 0.8rem;
            color: #5a728f;
            font-weight: 600;
            letter-spacing: 0.35px;
            text-transform: uppercase;
        }

        .main-content .pm-controls-wrapper {
            position: sticky;
            top: 10px;
            z-index: 20;
            background: rgba(248, 251, 255, 0.92);
            backdrop-filter: blur(4px);
            border: 1px solid #dce8f6;
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        .main-content .pm-controls {
            display: grid;
            gap: 12px;
            min-width: 0;
        }

        .main-content .pm-top-row {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: 10px;
            align-items: end;
            min-width: 0;
        }

        .main-content .pm-left {
            min-width: 0;
        }

        .main-content .pm-right {
            display: grid;
            grid-template-columns: repeat(4, minmax(130px, 1fr));
            gap: 10px;
            align-items: end;
            min-width: 0;
        }

        .main-content .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .main-content .pm-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .main-content .pm-quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .main-content .pm-quick-filters button {
            border: 1px solid #c8d9ec;
            background: #fff;
            color: #355678;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            transition: all 0.18s ease;
        }

        .main-content .pm-quick-filters button:hover {
            border-color: #93b8e1;
            background: #eef5ff;
            color: #1d4f86;
        }

        .main-content .pm-quick-filters button.active {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            border-color: #1e40af;
            color: #fff;
            box-shadow: 0 8px 16px rgba(29, 78, 216, 0.24);
        }

        .main-content .pm-utility-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .main-content .pm-result-summary {
            color: #516f90;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .main-content .btn-clear-filters {
            border: 1px solid #cfdced;
            background: #fff;
            color: #365679;
            border-radius: 9px;
            min-height: 36px;
            padding: 0 12px;
            font-weight: 700;
        }

        .main-content .btn-clear-filters:hover {
            border-color: #98b9dd;
            background: #eef5ff;
            color: #214c7d;
        }

        .main-content .pm-left label,
        .main-content .filter-group label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            color: #5f7691;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .main-content .pm-left input,
        .main-content .pm-controls select {
            width: 100%;
            border: 1px solid #cddced;
            background: #fff;
            border-radius: 10px;
            color: #1f3858;
            min-height: 38px;
            padding: 0 12px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .main-content .pm-left input:focus,
        .main-content .pm-controls select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
        }

        .main-content .pm-content h3 {
            margin: 2px 0 12px;
            color: #1d3654;
            font-size: 1.02rem;
            letter-spacing: 0.2px;
        }

        .main-content .projects-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .main-content .project-card {
            border: 1px solid #d8e5f4;
            border-radius: 16px;
            background: linear-gradient(165deg, #ffffff, #f7fbff);
            padding: 14px 14px 12px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .main-content .project-card:hover {
            transform: translateY(-2px);
            border-color: #8bb8e5;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.14);
        }

        .main-content .project-card.risk-critical { border-left: 5px solid #dc2626; }
        .main-content .project-card.risk-high { border-left: 5px solid #f97316; }
        .main-content .project-card.risk-medium { border-left: 5px solid #f59e0b; }
        .main-content .project-card.risk-low { border-left: 5px solid #16a34a; }

        .main-content .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
        }

        .main-content .project-title-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
            min-width: 0;
        }

        .main-content .project-code-badge {
            display: inline-flex;
            align-items: center;
            border: 1px solid #c6dbf1;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.25px;
            color: #355c86;
            background: #edf5ff;
            margin-bottom: 6px;
        }

        .main-content .project-title-section h4 {
            color: #14385c;
            margin: 0;
            font-size: 1.2rem;
            line-height: 1.15;
        }

        .main-content .project-description {
            margin: 8px 0 12px;
            color: #516783;
            font-size: 0.86rem;
            line-height: 1.45;
            min-height: 38px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .main-content .project-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 12px;
        }

        .main-content .project-meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
            background: rgba(237, 245, 255, 0.45);
            border: 1px solid #d8e7f8;
            border-radius: 10px;
            padding: 8px 10px;
        }

        .main-content .project-meta-label {
            font-size: 0.68rem;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            color: #6a819d;
            font-weight: 700;
        }

        .main-content .project-meta-value {
            color: #1f3f65;
            font-weight: 600;
            line-height: 1.25;
        }

        .main-content .project-status {
            border-radius: 999px;
            padding: 5px 11px;
            font-weight: 700;
            font-size: 0.72rem;
            border: 1px solid #c8d7ea;
            background: #f8fbff;
            color: #29496d;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }

        .main-content .project-status.draft {
            background: #eef2f7;
            border-color: #d4dee9;
            color: #4a5d75;
        }

        .main-content .project-status.for-approval {
            background: #fff7e6;
            border-color: #f5d28a;
            color: #8a5a08;
        }

        .main-content .project-status.approved {
            background: #e9f8ef;
            border-color: #9edbb6;
            color: #136b3a;
        }

        .main-content .project-status.on-hold {
            background: #fff1f2;
            border-color: #f7b4be;
            color: #9f1239;
        }

        .main-content .project-status.cancelled {
            background: #f3f4f6;
            border-color: #d2d6dc;
            color: #4b5563;
        }

        .main-content .project-status.completed {
            background: #e8f4ff;
            border-color: #9bc8f6;
            color: #0b4c8c;
        }

        .main-content .progress-container {
            margin-top: 12px;
            border: 1px solid #e0eaf5;
            background: #fbfdff;
            border-radius: 11px;
            padding: 10px;
        }

        .main-content .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            color: #3f5f83;
            font-size: 0.82rem;
        }

        .main-content .progress-bar {
            background: #e6eef8;
            border-radius: 999px;
            height: 8px;
        }

        .main-content .progress-fill {
            border-radius: 999px;
            background: linear-gradient(90deg, #22c55e 0%, #3b82f6 55%, #2563eb 100%);
        }

        .main-content .pm-empty .empty-state {
            border: 1px dashed #c7d9ee;
            background: #f7fbff;
            border-radius: 12px;
            min-height: 170px;
        }

        .main-content .pm-empty .empty-icon {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            border: 1px solid #cfe0f4;
            background: #fff;
            color: #5a7697;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        @media (max-width: 1240px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .main-content .pm-right {
                grid-template-columns: repeat(3, minmax(150px, 1fr));
            }

            .main-content .projects-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-content .pm-top-row {
                grid-template-columns: 1fr;
            }

            .main-content .pm-right {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }

            .main-content .pm-bottom-row {
                align-items: flex-start;
            }

            .main-content .pm-utility-row {
                margin-left: 0;
            }

            .main-content .pm-controls-wrapper {
                position: static;
            }
        }

        @media (max-width: 620px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: 1fr;
            }

            .main-content .pm-right {
                grid-template-columns: 1fr;
            }

            .main-content .pm-bottom-row,
            .main-content .pm-utility-row {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .main-content .project-meta {
                grid-template-columns: 1fr;
            }

            .main-content .btn-clear-filters {
                width: 100%;
            }
        }
    </style>

    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="/user-dashboard/user-progress-monitoring.js?v=<?php echo filemtime(__DIR__ . '/user-progress-monitoring.js'); ?>"></script>
</body>
</html>


