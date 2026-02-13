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
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

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
    <link rel="icon" type="image/png" href="/logocityhall.png">
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
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
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
        </div>

        <div class="pm-section card">
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
        .main-content .pm-section.card {
            border-radius: 16px;
            border: 1px solid #d8e6f4;
            background: linear-gradient(165deg, #ffffff 0%, #f8fbff 72%);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.1);
            padding: 16px;
        }

        .main-content .pm-stats-wrapper {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
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

        .main-content .pm-controls-wrapper {
            border: 1px solid #d8e6f4;
            border-radius: 14px;
            background: #ffffff;
            padding: 12px;
            margin-bottom: 14px;
        }

        .main-content .pm-content {
            border-radius: 14px;
            border: 1px solid #d8e6f4;
            background: #ffffff;
            padding: 14px;
        }

        .main-content .projects-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 14px;
        }

        .main-content .project-card {
            border-radius: 14px;
            border: 1px solid #dbe7f3;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            background: #ffffff;
        }

        .main-content .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
        }

        .main-content .progress-bar {
            height: 9px;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .main-content .progress-fill {
            border-radius: 999px;
            background: linear-gradient(90deg, #0ea5e9, #2563eb);
        }

        @media (max-width: 768px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }
    </style>

    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="/user-dashboard/user-progress-monitoring.js?v=<?php echo filemtime(__DIR__ . '/user-progress-monitoring.js'); ?>"></script>
</body>
</html>
