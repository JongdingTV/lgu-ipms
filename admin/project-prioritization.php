<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();
if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $stmt = $db->prepare("SELECT id, name, description, priority, status, created_at FROM projects ORDER BY priority DESC, created_at DESC LIMIT 100");
    $projects = [];
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle feedback status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = intval($_POST['feedback_id']);
    $new_status = $_POST['new_status'];
    
    // Validate status value
    $allowed_statuses = ['Pending', 'Reviewed', 'Addressed'];
    if (!in_array($new_status, $allowed_statuses)) {
        header('Location: project-prioritization.php?status=invalid');
        exit;
    }
    
    $stmt = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $new_status, $feedback_id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header('Location: project-prioritization.php?status=updated');
            exit;
        }
        header('Location: project-prioritization.php?status=failed');
        exit;
    }
    header('Location: project-prioritization.php?status=failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project_from_priority'])) {
    $locationGroup = trim((string)($_POST['location_group'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $district = trim((string)($_POST['district'] ?? ''));
    $barangay = trim((string)($_POST['barangay'] ?? ''));
    $alternativeName = trim((string)($_POST['alternative_name'] ?? ''));
    $legacyLocation = trim((string)($_POST['location'] ?? ''));
    $priorityScore = (float)($_POST['priority_score'] ?? 0);
    $reportTotal = (int)($_POST['report_total'] ?? 0);

    if ($reportTotal < 1 || $locationGroup === '') {
        header('Location: project-prioritization.php?status=project_invalid');
        exit;
    }

    $projectCode = build_priority_project_code($db);
    $projectPriority = priority_label_from_score($priorityScore);
    $projectName = 'Priority ' . ($category !== '' ? $category : 'Infrastructure') . ' - ' . $locationGroup;
    if (mb_strlen($projectName) > 255) {
        $projectName = mb_substr($projectName, 0, 255);
    }
    $projectType = 'New';
    $projectSector = $category !== '' ? ucwords($category) : 'Infrastructure';
    $projectDescription = "Auto-generated from citizen feedback prioritization.\n"
        . "Location Cluster: " . $locationGroup . "\n"
        . "Category: " . ($category !== '' ? $category : 'Uncategorized') . "\n"
        . "Priority Score: " . number_format($priorityScore, 2) . "\n"
        . "Feedback Volume: " . $reportTotal . " report(s).";
    $projectProvince = $district !== '' ? $district : null;
    $projectBarangay = $barangay !== '' ? $barangay : null;
    $projectLocation = $alternativeName !== '' ? $alternativeName : ($legacyLocation !== '' ? $legacyLocation : $locationGroup);
    $projectStatus = 'Draft';

    $projectsHasPriorityPercent = project_has_column($db, 'priority_percent');
    $projectsHasCreatedAt = project_has_column($db, 'created_at');
    $priorityPercent = min(100.0, max(0.0, $priorityScore));

    $columns = ['code', 'name', 'type', 'sector', 'description', 'priority'];
    $types = 'ssssss';
    $params = [$projectCode, $projectName, $projectType, $projectSector, $projectDescription, $projectPriority];
    if ($projectsHasPriorityPercent) {
        $columns[] = 'priority_percent';
        $types .= 'd';
        $params[] = $priorityPercent;
    }
    $columns = array_merge($columns, ['province', 'barangay', 'location', 'start_date', 'end_date', 'duration_months', 'budget', 'status']);
    $types .= 'sssssids';
    $params = array_merge($params, [$projectProvince, $projectBarangay, $projectLocation, null, null, null, null, $projectStatus]);

    if ($projectsHasCreatedAt) {
        $sql = "INSERT INTO projects (" . implode(', ', $columns) . ", created_at) VALUES ("
            . implode(', ', array_fill(0, count($columns), '?')) . ", NOW())";
    } else {
        $sql = "INSERT INTO projects (" . implode(', ', $columns) . ") VALUES ("
            . implode(', ', array_fill(0, count($columns), '?')) . ")";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        header('Location: project-prioritization.php?status=project_failed');
        exit;
    }
    $bindParams = [$types];
    foreach ($params as $idx => &$value) {
        $bindParams[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        header('Location: project-prioritization.php?status=project_failed');
        exit;
    }
    header('Location: project-prioritization.php?status=project_created');
    exit;
}

function table_has_column(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function feedback_has_column(mysqli $db, string $column): bool
{
    return table_has_column($db, 'feedback', $column);
}

function project_has_column(mysqli $db, string $column): bool
{
    return table_has_column($db, 'projects', $column);
}

function normalize_priority(string $category): float
{
    $key = strtolower(trim($category));
    if ($key === 'critical' || $key === 'crucial') return 1.0;
    if ($key === 'high') return 0.8;
    if ($key === 'medium') return 0.55;
    if ($key === 'low') return 0.3;
    return 0.45;
}

function priority_label_from_score(float $score): string
{
    if ($score >= 80) return 'Crucial';
    if ($score >= 65) return 'High';
    if ($score >= 45) return 'Medium';
    return 'Low';
}

function build_priority_project_code(mysqli $db): string
{
    $prefix = 'PRIO-' . date('Ymd') . '-';
    for ($i = 1; $i <= 9999; $i++) {
        $candidate = $prefix . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare('SELECT id FROM projects WHERE code = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free();
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
    }
    return $prefix . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function clean_feedback_description(string $description): string
{
    $cleaned = preg_replace('/^\[(Photo Attachment Private|Google Maps Pin|Pinned Address|Complete Address)\].*$/mi', '', $description);
    $cleaned = preg_replace("/\n{3,}/", "\n\n", (string) $cleaned);
    $cleaned = trim((string) $cleaned);
    return $cleaned !== '' ? $cleaned : '-';
}

function feedback_map_embed_url(array $feedback): ?string
{
    $lat = null;
    $lng = null;

    $latRaw = trim((string) ($feedback['map_lat'] ?? ''));
    $lngRaw = trim((string) ($feedback['map_lng'] ?? ''));
    if ($latRaw !== '' && $lngRaw !== '' && is_numeric($latRaw) && is_numeric($lngRaw)) {
        $lat = (float) $latRaw;
        $lng = (float) $lngRaw;
    } else {
        $mapLink = trim((string) ($feedback['map_link'] ?? ''));
        if ($mapLink !== '') {
            if (preg_match('/[?&]mlat=([-0-9.]+).*?[?&]mlon=([-0-9.]+)/i', $mapLink, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
            } elseif (preg_match('/#map=\d+\/([-0-9.]+)\/([-0-9.]+)/i', $mapLink, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
            } elseif (preg_match('/[?&]q=([-0-9.]+),\s*([-0-9.]+)/i', $mapLink, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
            }
        }
    }

    if ($lat === null || $lng === null) {
        return null;
    }

    $delta = 0.004;
    $left = $lng - $delta;
    $right = $lng + $delta;
    $top = $lat + $delta;
    $bottom = $lat - $delta;

    return 'https://www.openstreetmap.org/export/embed.html?bbox='
        . rawurlencode((string) $left . ',' . (string) $bottom . ',' . (string) $right . ',' . (string) $top)
        . '&layer=mapnik&marker=' . rawurlencode((string) $lat . ',' . (string) $lng);
}

// Fetch feedback for display with pagination
$offset = 0;
$limit = 50;
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $offset = (intval($_GET['page']) - 1) * $limit;
}

$hasDistrict = feedback_has_column($db, 'district');
$hasBarangay = feedback_has_column($db, 'barangay');
$hasAlternativeName = feedback_has_column($db, 'alternative_name');
$hasExactAddress = feedback_has_column($db, 'exact_address');
$hasPhotoPath = feedback_has_column($db, 'photo_path');
$hasMapLat = feedback_has_column($db, 'map_lat');
$hasMapLng = feedback_has_column($db, 'map_lng');
$hasMapLink = feedback_has_column($db, 'map_link');

$selectFields = [
    'id',
    'user_name',
    'subject',
    'description',
    'category',
    'location',
    'status',
    'date_submitted'
];
if ($hasDistrict) $selectFields[] = 'district';
if ($hasBarangay) $selectFields[] = 'barangay';
if ($hasAlternativeName) $selectFields[] = 'alternative_name';
if ($hasExactAddress) $selectFields[] = 'exact_address';
if ($hasPhotoPath) $selectFields[] = 'photo_path';
if ($hasMapLat) $selectFields[] = 'map_lat';
if ($hasMapLng) $selectFields[] = 'map_lng';
if ($hasMapLink) $selectFields[] = 'map_link';

$stmt = $db->prepare("SELECT " . implode(', ', $selectFields) . " FROM feedback ORDER BY date_submitted DESC LIMIT ? OFFSET ?");
if ($stmt) {
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $feedback_result = $stmt->get_result();
    $feedbacks = [];
    while ($row = $feedback_result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    $stmt->close();
} else {
    $feedbacks = [];
}

// Feedback summary stats + weighted priority scoring across all feedback rows.
$analyticsFields = ['id', 'subject', 'category', 'location', 'status', 'date_submitted'];
if ($hasDistrict) $analyticsFields[] = 'district';
if ($hasBarangay) $analyticsFields[] = 'barangay';
if ($hasAlternativeName) $analyticsFields[] = 'alternative_name';
$analyticsRows = [];
$analyticsSql = "SELECT " . implode(', ', $analyticsFields) . " FROM feedback";
$analyticsResult = $db->query($analyticsSql);
if ($analyticsResult) {
    while ($row = $analyticsResult->fetch_assoc()) {
        $analyticsRows[] = $row;
    }
    $analyticsResult->free();
}

$totalInputs = count($analyticsRows);
$criticalInputs = 0;
$highInputs = 0;
$pendingInputs = 0;
$reviewedInputs = 0;
$addressedInputs = 0;
$oldestPendingDays = 0;
$topPriority = [
    'location_group' => 'No data',
    'category' => 'N/A',
    'total' => 0,
    'district' => '',
    'barangay' => '',
    'alternative_name' => '',
    'location' => '',
    'score' => 0
];

$clusterStats = [];
$nowTs = time();
foreach ($analyticsRows as $fb) {
    $categoryRaw = trim((string)($fb['category'] ?? ''));
    $subjectRaw = trim((string)($fb['subject'] ?? ''));
    $statusRaw = strtolower(trim((string)($fb['status'] ?? '')));
    $category = $categoryRaw !== '' ? $categoryRaw : ($subjectRaw !== '' ? $subjectRaw : 'Uncategorized');

    if (strtolower($categoryRaw) === 'critical') $criticalInputs++;
    if (strtolower($categoryRaw) === 'high') $highInputs++;
    if ($statusRaw === 'pending') $pendingInputs++;
    if ($statusRaw === 'reviewed') $reviewedInputs++;
    if ($statusRaw === 'addressed') $addressedInputs++;

    $ageDays = 0;
    $ts = strtotime((string)($fb['date_submitted'] ?? ''));
    if ($ts) {
        $ageDays = max(0, (int)floor(($nowTs - $ts) / 86400));
        if ($statusRaw === 'pending' && $ageDays > $oldestPendingDays) {
            $oldestPendingDays = $ageDays;
        }
    }

    $district = trim((string)($fb['district'] ?? ''));
    $barangay = trim((string)($fb['barangay'] ?? ''));
    $alternative = trim((string)($fb['alternative_name'] ?? ''));
    $location = trim((string)($fb['location'] ?? ''));
    $clusterKey = strtolower(($district !== '' ? $district : 'N/A') . '|' . ($barangay !== '' ? $barangay : 'N/A') . '|' . ($alternative !== '' ? $alternative : 'N/A') . '|' . ($location !== '' ? $location : 'No location') . '|' . strtolower($category));
    if (!isset($clusterStats[$clusterKey])) {
        $clusterStats[$clusterKey] = [
            'district' => $district,
            'barangay' => $barangay,
            'alternative_name' => $alternative,
            'location' => $location,
            'category' => $category,
            'total' => 0,
            'severity_total' => 0.0,
            'recency_total' => 0.0,
            'pending_total' => 0
        ];
    }

    $clusterStats[$clusterKey]['total']++;
    $clusterStats[$clusterKey]['severity_total'] += normalize_priority($categoryRaw !== '' ? $categoryRaw : $subjectRaw);
    $clusterStats[$clusterKey]['recency_total'] += max(0.0, 1.0 - min($ageDays, 180) / 180.0);
    if ($statusRaw === 'pending') {
        $clusterStats[$clusterKey]['pending_total']++;
    }
}

$maxVolume = 1;
foreach ($clusterStats as $cluster) {
    if ((int)$cluster['total'] > $maxVolume) {
        $maxVolume = (int)$cluster['total'];
    }
}

$bestCluster = null;
$bestScore = -1.0;
foreach ($clusterStats as $cluster) {
    $volumeNorm = min(1.0, (float)$cluster['total'] / (float)$maxVolume);
    $severityNorm = $cluster['total'] > 0 ? (float)$cluster['severity_total'] / (float)$cluster['total'] : 0.0;
    $recencyNorm = $cluster['total'] > 0 ? (float)$cluster['recency_total'] / (float)$cluster['total'] : 0.0;
    $pendingNorm = $cluster['total'] > 0 ? (float)$cluster['pending_total'] / (float)$cluster['total'] : 0.0;
    $score = (0.45 * $volumeNorm + 0.25 * $severityNorm + 0.20 * $recencyNorm + 0.10 * $pendingNorm) * 100.0;
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestCluster = $cluster;
    }
}

if ($bestCluster !== null) {
    $topPriority['district'] = $bestCluster['district'];
    $topPriority['barangay'] = $bestCluster['barangay'];
    $topPriority['alternative_name'] = $bestCluster['alternative_name'];
    $topPriority['location'] = $bestCluster['location'];
    $topPriority['category'] = $bestCluster['category'];
    $topPriority['total'] = (int)$bestCluster['total'];
    $topPriority['score'] = round($bestScore, 2);
    if ($hasDistrict || $hasBarangay || $hasAlternativeName) {
        $topPriority['location_group'] = trim(
            ($topPriority['district'] !== '' ? $topPriority['district'] : 'N/A') . ' / '
            . ($topPriority['barangay'] !== '' ? $topPriority['barangay'] : 'N/A') . ' / '
            . ($topPriority['alternative_name'] !== '' ? $topPriority['alternative_name'] : 'N/A')
        );
    } else {
        $topPriority['location_group'] = $topPriority['location'] !== '' ? $topPriority['location'] : 'No data';
    }
}

$db->close();
$status_flash = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
?>
<!doctype html>
<html>
<head>
        
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Project Prioritization - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    </head>
<body>
    <!-- Sidebar Toggle Button (Floating) -->
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <!-- Navbar menu icon - shows when sidebar is hidden -->
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
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
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                </div>
            </div>
            <a href="project-prioritization.php" class="active"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/admin/logout.php" class="btn-logout nav-logout">
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

    <!-- Toggle button to show sidebar -->
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Project Prioritization</h1>
            <p>Review and prioritize citizen inputs for infrastructure project planning</p>
        </div>

        <div class="inputs-section prioritization-page">
            <!-- Search & Filter Controls -->
            <div class="feedback-controls">
                <div class="search-group">
                    <label for="fbSearch">Search by Control Number or Name</label>
                    <input id="fbSearch" type="search" placeholder="e.g., CTL-00015 or John Doe">
                </div>
                <div class="search-group">
                    <label for="fbStatusFilter">Status</label>
                    <select id="fbStatusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="addressed">Addressed</option>
                    </select>
                </div>
                <div class="search-group">
                    <label for="fbCategoryFilter">Category</label>
                    <select id="fbCategoryFilter">
                        <option value="">All Categories</option>
                        <?php
                        $catSeen = [];
                        foreach ($feedbacks as $fb) {
                            $category = trim((string)($fb['category'] ?? ''));
                            if ($category === '') continue;
                            $key = strtolower($category);
                            if (isset($catSeen[$key])) continue;
                            $catSeen[$key] = true;
                            echo '<option value="' . htmlspecialchars($key) . '">' . htmlspecialchars($category) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="feedback-actions">
                    <button id="clearSearch" class="secondary">Clear</button>
                    <button id="exportData">Export CSV</button>
                    <span id="fbVisibleCount" class="feedback-visible-count">Showing 0 of 0</span>
                </div>
            </div>

            <div class="prioritization-insights">
                <article class="priority-kpi pending">
                    <span>Pending Queue</span>
                    <strong><?= (int)$pendingInputs ?></strong>
                </article>
                <article class="priority-kpi reviewed">
                    <span>Reviewed</span>
                    <strong><?= (int)$reviewedInputs ?></strong>
                </article>
                <article class="priority-kpi addressed">
                    <span>Addressed</span>
                    <strong><?= (int)$addressedInputs ?></strong>
                </article>
                <article class="priority-kpi aging">
                    <span>Oldest Pending</span>
                    <strong><?= (int)$oldestPendingDays ?> day<?= ((int)$oldestPendingDays === 1 ? '' : 's') ?></strong>
                </article>
                <article
                    id="priorityTopCard"
                    class="priority-kpi critical is-clickable"
                    role="button"
                    tabindex="0"
                    data-district="<?= htmlspecialchars(strtolower($topPriority['district'])) ?>"
                    data-barangay="<?= htmlspecialchars(strtolower($topPriority['barangay'])) ?>"
                    data-alternative-name="<?= htmlspecialchars(strtolower($topPriority['alternative_name'])) ?>"
                    data-location="<?= htmlspecialchars(strtolower($topPriority['location'])) ?>"
                    data-category="<?= htmlspecialchars(strtolower($topPriority['category'])) ?>"
                >
                    <span>Priority</span>
                    <strong><?= htmlspecialchars($topPriority['total'] . ' report' . ($topPriority['total'] === 1 ? '' : 's')) ?></strong>
                    <small><?= htmlspecialchars($topPriority['location_group']) ?></small>
                    <small><?= htmlspecialchars($topPriority['category']) ?></small>
                    <small>Weighted Score: <?= htmlspecialchars(number_format((float)$topPriority['score'], 2)) ?></small>
                    <small>Click to view matching reports</small>
                </article>
            </div>
            <div class="feedback-actions">
                <form method="post">
                    <input type="hidden" name="create_project_from_priority" value="1">
                    <input type="hidden" name="location_group" value="<?= htmlspecialchars($topPriority['location_group']) ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($topPriority['category']) ?>">
                    <input type="hidden" name="district" value="<?= htmlspecialchars($topPriority['district']) ?>">
                    <input type="hidden" name="barangay" value="<?= htmlspecialchars($topPriority['barangay']) ?>">
                    <input type="hidden" name="alternative_name" value="<?= htmlspecialchars($topPriority['alternative_name']) ?>">
                    <input type="hidden" name="location" value="<?= htmlspecialchars($topPriority['location']) ?>">
                    <input type="hidden" name="priority_score" value="<?= htmlspecialchars((string)$topPriority['score']) ?>">
                    <input type="hidden" name="report_total" value="<?= htmlspecialchars((string)$topPriority['total']) ?>">
                    <button type="submit" class="secondary" <?= ((int)$topPriority['total'] < 1 ? 'disabled' : '') ?>>
                        Add Top Priority To Project Registration
                    </button>
                </form>
            </div>

            <!-- Feedback Table -->
            <div class="card">
                <h2>User Feedback & Concerns</h2>
                <div class="table-wrap">
                    <table id="inputsTable" class="feedback-table">
                        <thead>
                            <tr>
                                <th>Control #</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Age</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="no-results">
                                        <div class="no-results-icon">Ã°Å¸â€œâ€¹</div>
                                        <div class="no-results-title">No Feedback Found</div>
                                        <div class="no-results-text">No feedback submitted yet. Please check back later.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedbacks as $fb): ?>
                                <?php
                                $fb_lc = array_change_key_case($fb, CASE_LOWER);
                                $feedbackId = (int) ($fb_lc['id'] ?? 0);
                                $controlNumber = $feedbackId > 0
                                    ? 'CTL-' . str_pad((string) $feedbackId, 5, '0', STR_PAD_LEFT)
                                    : 'CTL-NA';
                                $cleanDescription = clean_feedback_description((string) ($fb_lc['description'] ?? ''));
                                $mapEmbedUrl = feedback_map_embed_url($fb_lc);
                                $rowStatus = strtolower(trim((string)($fb_lc['status'] ?? '')));
                                $rowCategory = strtolower(trim((string)($fb_lc['category'] ?? '')));
                                $rowDistrict = strtolower(trim((string)($fb_lc['district'] ?? '')));
                                $rowBarangay = strtolower(trim((string)($fb_lc['barangay'] ?? '')));
                                $rowAlternativeName = strtolower(trim((string)($fb_lc['alternative_name'] ?? '')));
                                $rowLocation = strtolower(trim((string)($fb_lc['location'] ?? '')));
                                $rowDays = null;
                                if (!empty($fb_lc['date_submitted'])) {
                                    $rowTs = strtotime((string)$fb_lc['date_submitted']);
                                    if ($rowTs) {
                                        $rowDays = max(0, (int) floor((time() - $rowTs) / 86400));
                                    }
                                }
                                $ageClass = 'fresh';
                                if ($rowDays !== null && $rowDays >= 14) {
                                    $ageClass = 'critical';
                                } elseif ($rowDays !== null && $rowDays >= 7) {
                                    $ageClass = 'warning';
                                }
                                ?>
                                <tr class="<?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ? 'pending-row' : '' ?>"
                                    data-status="<?= htmlspecialchars($rowStatus) ?>"
                                    data-category="<?= htmlspecialchars($rowCategory) ?>"
                                    data-district="<?= htmlspecialchars($rowDistrict) ?>"
                                    data-barangay="<?= htmlspecialchars($rowBarangay) ?>"
                                    data-alternative-name="<?= htmlspecialchars($rowAlternativeName) ?>"
                                    data-location="<?= htmlspecialchars($rowLocation) ?>">
                                    <td><strong><?= htmlspecialchars($controlNumber) ?></strong></td>
                                    <td><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></td>
                                    <td><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></td>
                                    <td><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></td>
                                    <td><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></td>
                                    <td>
                                        <div><?= isset($fb_lc['exact_address']) && trim((string)$fb_lc['exact_address']) !== '' ? htmlspecialchars($fb_lc['exact_address']) : (isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-') ?></div>
                                        <button type="button" class="view-btn" data-address-modal="address-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">View Full Address</button>
                                    </td>
                                    <td>
                                        <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                            <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rowDays !== null): ?>
                                            <span class="age-badge <?= $ageClass ?>"><?= (int)$rowDays ?> day<?= ((int)$rowDays === 1 ? '' : 's') ?></span>
                                        <?php else: ?>
                                            <span class="age-badge fresh">Today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="copy-btn" data-copy-control="<?= htmlspecialchars($controlNumber) ?>">Copy #</button>
                                        <button type="button" class="edit-btn" data-edit-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Edit</button>
                                        <button type="button" class="view-btn" data-view-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($feedbacks)): ?>
                <div id="prioritizationModalRoot">
                    <?php foreach ($feedbacks as $fb): ?>
                        <?php
                        $fb_lc = array_change_key_case($fb, CASE_LOWER);
                        $feedbackId = (int) ($fb_lc['id'] ?? 0);
                        $controlNumber = $feedbackId > 0
                            ? 'CTL-' . str_pad((string) $feedbackId, 5, '0', STR_PAD_LEFT)
                            : 'CTL-NA';
                        $cleanDescription = clean_feedback_description((string) ($fb_lc['description'] ?? ''));
                        $mapEmbedUrl = feedback_map_embed_url($fb_lc);
                        ?>
                        <div id="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Update Feedback Status</h2>
                                    <button type="button" class="modal-close" data-close-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">&times;</button>
                                </div>
                                <form method="post" class="modal-form">
                                    <div class="modal-body">
                                        <div class="modal-field">
                                            <span class="modal-label">Control Number:</span>
                                            <div class="modal-value"><strong><?= htmlspecialchars($controlNumber) ?></strong></div>
                                        </div>
                                        <div class="modal-field">
                                            <span class="modal-label">From:</span>
                                            <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                        </div>
                                        <div class="modal-field">
                                            <span class="modal-label">Subject:</span>
                                            <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                        </div>
                                        <div class="modal-field">
                                            <label for="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal-label">Change Status:</label>
                                            <select id="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" name="new_status" class="status-dropdown">
                                                <option value="Pending" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ?'selected':'' ?>>Pending</option>
                                                <option value="Reviewed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Reviewed') ?'selected':'' ?>>Reviewed</option>
                                                <option value="Addressed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Addressed') ?'selected':'' ?>>Addressed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="feedback_id" value="<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">
                                        <button type="button" class="modal-btn modal-btn-close" data-close-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Cancel</button>
                                        <button type="submit" name="update_status" class="modal-btn modal-btn-action">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Feedback Details</h2>
                                    <button type="button" class="modal-close" data-close-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-field">
                                        <span class="modal-label">Control Number:</span>
                                        <div class="modal-value"><strong><?= htmlspecialchars($controlNumber) ?></strong></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Submitted By:</span>
                                        <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Date Submitted:</span>
                                        <div class="modal-value"><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Subject:</span>
                                        <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Category:</span>
                                        <div class="modal-value"><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Exact Address:</span>
                                        <div class="modal-value"><?= isset($fb_lc['exact_address']) && trim((string)$fb_lc['exact_address']) !== '' ? htmlspecialchars($fb_lc['exact_address']) : (isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-') ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">District:</span>
                                        <div class="modal-value"><?= isset($fb_lc['district']) && $fb_lc['district'] !== '' ? htmlspecialchars($fb_lc['district']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Barangay:</span>
                                        <div class="modal-value"><?= isset($fb_lc['barangay']) && $fb_lc['barangay'] !== '' ? htmlspecialchars($fb_lc['barangay']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Alternative Name:</span>
                                        <div class="modal-value"><?= isset($fb_lc['alternative_name']) && $fb_lc['alternative_name'] !== '' ? htmlspecialchars($fb_lc['alternative_name']) : '-' ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Full Address Breakdown:</span>
                                        <div class="modal-value">
                                            <?= htmlspecialchars(
                                                trim(
                                                    ((isset($fb_lc['district']) && $fb_lc['district'] !== '') ? $fb_lc['district'] : 'N/A')
                                                    . ' / '
                                                    . ((isset($fb_lc['barangay']) && $fb_lc['barangay'] !== '') ? $fb_lc['barangay'] : 'N/A')
                                                    . ' / '
                                                    . ((isset($fb_lc['alternative_name']) && $fb_lc['alternative_name'] !== '') ? $fb_lc['alternative_name'] : 'N/A')
                                                )
                                            ) ?>
                                        </div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Status:</span>
                                        <div class="modal-value">
                                            <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                                <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Message / Description:</span>
                                        <div class="modal-value"><?= htmlspecialchars($cleanDescription) ?></div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Citizen Photo:</span>
                                        <div class="modal-value">
                                            <?php
                                            $photoFile = '';
                                            $photoPathRaw = trim((string) ($fb_lc['photo_path'] ?? ''));
                                            if ($photoPathRaw !== '') {
                                                $photoFile = basename(str_replace('\\', '/', $photoPathRaw));
                                            }
                                            if ($photoFile === '' && preg_match('/\[Photo Attachment Private\]\s+([\w\-.]+\.(?:jpg|jpeg|png|webp))/i', (string) ($fb_lc['description'] ?? ''), $pm)) {
                                                $photoFile = (string) ($pm[1] ?? '');
                                            }
                                            ?>
                                            <?php if ($photoFile !== ''): ?>
                                                <button type="button" class="view-btn" data-photo-modal="photo-modal-<?= (int) ($fb_lc['id'] ?? 0) ?>">View Uploaded Photo</button>
                                            <?php else: ?>
                                                No photo attached.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="modal-field">
                                        <span class="modal-label">Pinned Map:</span>
                                        <div class="modal-value">
                                            <?php if ($mapEmbedUrl !== null): ?>
                                                <iframe class="feedback-map-preview" src="<?= htmlspecialchars($mapEmbedUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                                <?php if (!empty($fb_lc['map_lat']) && !empty($fb_lc['map_lng'])): ?>
                                                    <div class="feedback-map-coords"><?= htmlspecialchars((string) $fb_lc['map_lat']) ?>, <?= htmlspecialchars((string) $fb_lc['map_lng']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                No map pin available.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="modal-btn modal-btn-close" data-close-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Close</button>
                                </div>
                            </div>
                        </div>
                        <div id="address-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Full Address Details</h2>
                                    <button type="button" class="modal-close" data-close-modal="address-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-field"><span class="modal-label">Exact Address:</span><div class="modal-value"><?= isset($fb_lc['exact_address']) && trim((string)$fb_lc['exact_address']) !== '' ? htmlspecialchars($fb_lc['exact_address']) : (isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-') ?></div></div>
                                    <div class="modal-field"><span class="modal-label">District:</span><div class="modal-value"><?= isset($fb_lc['district']) && $fb_lc['district'] !== '' ? htmlspecialchars($fb_lc['district']) : '-' ?></div></div>
                                    <div class="modal-field"><span class="modal-label">Barangay:</span><div class="modal-value"><?= isset($fb_lc['barangay']) && $fb_lc['barangay'] !== '' ? htmlspecialchars($fb_lc['barangay']) : '-' ?></div></div>
                                    <div class="modal-field"><span class="modal-label">Alternative Name:</span><div class="modal-value"><?= isset($fb_lc['alternative_name']) && $fb_lc['alternative_name'] !== '' ? htmlspecialchars($fb_lc['alternative_name']) : '-' ?></div></div>
                                    <div class="modal-field">
                                        <span class="modal-label">Pinned Map / Coordinates:</span>
                                        <div class="modal-value">
                                            <?php if ($mapEmbedUrl !== null): ?>
                                                <iframe class="feedback-map-preview" src="<?= htmlspecialchars($mapEmbedUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                                <?php if (!empty($fb_lc['map_lat']) && !empty($fb_lc['map_lng'])): ?>
                                                    <div class="feedback-map-coords"><?= htmlspecialchars((string) $fb_lc['map_lat']) ?>, <?= htmlspecialchars((string) $fb_lc['map_lng']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Not provided.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="modal-btn modal-btn-close" data-close-modal="address-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Close</button>
                                </div>
                            </div>
                        </div>
                        <?php if ($photoFile !== ''): ?>
                        <div id="photo-modal-<?= (int) ($fb_lc['id'] ?? 0) ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Uploaded Photo</h2>
                                    <button type="button" class="modal-close" data-close-modal="photo-modal-<?= (int) ($fb_lc['id'] ?? 0) ?>">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <img
                                        class="feedback-photo-preview"
                                        src="/admin/feedback-photo.php?feedback_id=<?= (int) ($fb_lc['id'] ?? 0) ?>"
                                        alt="Feedback Photo"
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                    >
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="modal-btn modal-btn-close" data-close-modal="photo-modal-<?= (int) ($fb_lc['id'] ?? 0) ?>">Close</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="summary-section prioritization-summary">
            <div class="card">
                <h2>Feedback Summary</h2>
                <div class="summary">
                    <div class="stat">
                        <div id="totalInputs"><?= $totalInputs ?></div>
                        <small>Total Feedback</small>
                    </div>
                    <div class="stat">
                        <div id="criticalInputs" class="ac-6e512bc4"><?= $criticalInputs ?></div>
                        <small>Critical Priority</small>
                    </div>
                    <div class="stat">
                        <div id="highInputs" class="ac-aba882c6"><?= $highInputs ?></div>
                        <small>High Priority</small>
                    </div>
                    <div class="stat">
                        <div id="pendingInputs" class="ac-c476a1d0"><?= $pendingInputs ?></div>
                        <small>Pending Status</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-project-prioritization.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-project-prioritization.js'); ?>"></script>
</body>
</html>























