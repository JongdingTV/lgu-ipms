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

function registered_projects_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function registered_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function registered_table_has_column(mysqli $db, string $table, string $column): bool
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

function registered_pick_column(mysqli $db, string $table, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (registered_table_has_column($db, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function ensure_assignment_table(mysqli $db): bool
{
    if (registered_table_exists($db, 'contractor_project_assignments')) {
        return true;
    }

    try {
        $ok = $db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            contractor_id INT NOT NULL,
            project_id INT NOT NULL,
            assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (contractor_id, project_id)
        )");
        return (bool) $ok;
    } catch (Throwable $e) {
        error_log('ensure_assignment_table error: ' . $e->getMessage());
        return false;
    }
}

// Handle GET request for loading Engineers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractors') {
    header('Content-Type: application/json');
    
    $companyCol = registered_pick_column($db, 'contractors', ['company', 'company_name', 'name']);
    $licenseCol = registered_pick_column($db, 'contractors', ['license', 'license_number', 'prc_license_no']);
    $emailCol = registered_pick_column($db, 'contractors', ['email', 'contact_email']);
    $phoneCol = registered_pick_column($db, 'contractors', ['phone', 'contact_number', 'mobile']);
    $statusCol = registered_pick_column($db, 'contractors', ['status']);
    $ratingCol = registered_pick_column($db, 'contractors', ['rating']);

    $selectParts = ['id'];
    $selectParts[] = $companyCol ? "{$companyCol} AS company" : "'' AS company";
    $selectParts[] = $licenseCol ? "{$licenseCol} AS license" : "'' AS license";
    $selectParts[] = $emailCol ? "{$emailCol} AS email" : "'' AS email";
    $selectParts[] = $phoneCol ? "{$phoneCol} AS phone" : "'' AS phone";
    $selectParts[] = $statusCol ? "{$statusCol} AS status" : "'active' AS status";
    $selectParts[] = $ratingCol ? "{$ratingCol} AS rating" : "0 AS rating";

    $Engineers = [];

    try {
        $result = $db->query("SELECT " . implode(', ', $selectParts) . " FROM contractors ORDER BY id DESC LIMIT 100");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $Engineers[] = $row;
            }
            $result->free();
        }
    } catch (Throwable $e) {
        error_log("Engineers query error: " . $e->getMessage());
        echo json_encode([]);
        exit;
    }
    
    echo json_encode($Engineers);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $hasCreatedAt = registered_projects_has_column($db, 'created_at');
    $hasPriorityPercent = registered_projects_has_column($db, 'priority_percent');
    $hasExactAddress = registered_projects_has_column($db, 'location');
    $hasType = registered_projects_has_column($db, 'type');
    $hasProvince = registered_projects_has_column($db, 'province');
    $hasBarangay = registered_projects_has_column($db, 'barangay');
    $hasBudget = registered_projects_has_column($db, 'budget');
    $hasStartDate = registered_projects_has_column($db, 'start_date');
    $hasEndDate = registered_projects_has_column($db, 'end_date');
    $hasDurationMonths = registered_projects_has_column($db, 'duration_months');
    $hasLicenseDoc = registered_projects_has_column($db, 'engineer_license_doc');
    $hasCertDoc = registered_projects_has_column($db, 'engineer_certification_doc');
    $hasCredDoc = registered_projects_has_column($db, 'engineer_credentials_doc');
    $hasTaskTable = registered_table_exists($db, 'project_tasks');
    $hasMilestoneTable = registered_table_exists($db, 'project_milestones');
    $orderBy = $hasCreatedAt ? 'created_at DESC' : 'id DESC';
    $prioritySelect = $hasPriorityPercent ? 'priority_percent' : '0 AS priority_percent';
    $projectSelect = [
        'id',
        'code',
        'name',
        $hasType ? 'type' : "'' AS type",
        'sector',
        'status',
        'priority',
        $prioritySelect,
        'location',
        $hasProvince ? 'province' : "'' AS province",
        $hasBarangay ? 'barangay' : "'' AS barangay",
        $hasBudget ? 'budget' : '0 AS budget',
        $hasStartDate ? 'start_date' : 'NULL AS start_date',
        $hasEndDate ? 'end_date' : 'NULL AS end_date',
        $hasDurationMonths ? 'duration_months' : 'NULL AS duration_months',
        $hasLicenseDoc ? 'engineer_license_doc' : "'' AS engineer_license_doc",
        $hasCertDoc ? 'engineer_certification_doc' : "'' AS engineer_certification_doc",
        $hasCredDoc ? 'engineer_credentials_doc' : "'' AS engineer_credentials_doc"
    ];
    try {
        $result = $db->query("SELECT " . implode(', ', $projectSelect) . " FROM projects ORDER BY {$orderBy}");
    } catch (Throwable $e) {
        error_log('registered_contractors load_projects query error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['allocated_budget'] = (float) ($row['budget'] ?? 0);
            $row['spent_budget'] = 0.0;
            $row['remaining_budget'] = max(0, $row['allocated_budget'] - $row['spent_budget']);
            $row['location_exact'] = $hasExactAddress ? (string) ($row['location'] ?? '') : '';
            $row['full_address'] = trim(((string) ($row['province'] ?? '')) . ' / ' . ((string) ($row['barangay'] ?? '')) . ' / ' . ((string) ($row['location'] ?? '')));
            $row['task_summary'] = ['total' => 0, 'completed' => 0];
            $row['milestone_summary'] = ['total' => 0, 'completed' => 0];
            if ($hasTaskTable) {
                $taskRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_tasks WHERE project_id = " . (int)$row['id']);
                if ($taskRes) {
                    $taskRow = $taskRes->fetch_assoc();
                    $taskRes->free();
                    $row['task_summary'] = ['total' => (int)($taskRow['total'] ?? 0), 'completed' => (int)($taskRow['completed'] ?? 0)];
                }
            }
            if ($hasMilestoneTable) {
                $mileRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_milestones WHERE project_id = " . (int)$row['id']);
                if ($mileRes) {
                    $mileRow = $mileRes->fetch_assoc();
                    $mileRes->free();
                    $row['milestone_summary'] = ['total' => (int)($mileRow['total'] ?? 0), 'completed' => (int)($mileRow['completed'] ?? 0)];
                }
            }
            $row['documents'] = array_values(array_filter([
                $row['engineer_license_doc'] ?? '',
                $row['engineer_certification_doc'] ?? '',
                $row['engineer_credentials_doc'] ?? ''
            ]));
            $projects[] = $row;
        }
        $result->free();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle POST request for assigning Engineer to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_contractor') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        if (!ensure_assignment_table($db)) {
            echo json_encode(['success' => false, 'message' => 'Assignment table is missing and cannot be created.']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO contractor_project_assignments (contractor_id, project_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer assigned to project successfully']);
        } else {
            if (strpos($stmt->error, 'Duplicate') !== false) {
                echo json_encode(['success' => false, 'message' => 'This Engineer is already assigned to this project']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign Engineer: ' . $stmt->error]);
            }
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer or project ID']);
    }
    exit;
}

// Handle POST request for removing Engineer from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unassign_contractor') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        if (!registered_table_exists($db, 'contractor_project_assignments')) {
            echo json_encode(['success' => true, 'message' => 'No assignment found']);
            exit;
        }
        $stmt = $db->prepare("DELETE FROM contractor_project_assignments WHERE contractor_id=? AND project_id=?");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer unassigned from project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unassign Engineer']);
        }
        $stmt->close();
    }
    exit;
}

// Handle POST request for deleting Engineer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contractor') {
    header('Content-Type: application/json');

    $contractor_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($contractor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer ID']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->bind_param("i", $contractor_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Engineer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete Engineer']);
    }

    $stmt->close();
    exit;
}

// Handle GET request for loading assigned projects for a Engineer
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_assigned_projects') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : 0;
    
    if ($contractor_id > 0) {
        if (!registered_table_exists($db, 'contractor_project_assignments')) {
            echo json_encode([]);
            exit;
        }

        $hasPriorityPercent = registered_projects_has_column($db, 'priority_percent');
        $hasType = registered_projects_has_column($db, 'type');
        $hasProvince = registered_projects_has_column($db, 'province');
        $hasBarangay = registered_projects_has_column($db, 'barangay');
        $hasBudget = registered_projects_has_column($db, 'budget');
        $hasStartDate = registered_projects_has_column($db, 'start_date');
        $hasEndDate = registered_projects_has_column($db, 'end_date');
        $hasDurationMonths = registered_projects_has_column($db, 'duration_months');
        $hasLicenseDoc = registered_projects_has_column($db, 'engineer_license_doc');
        $hasCertDoc = registered_projects_has_column($db, 'engineer_certification_doc');
        $hasCredDoc = registered_projects_has_column($db, 'engineer_credentials_doc');
        $prioritySelect = $hasPriorityPercent ? 'p.priority_percent' : '0 AS priority_percent';
        try {
            $stmt = $db->prepare("SELECT p.id, p.code, p.name, " . ($hasType ? "p.type" : "'' AS type") . ", p.sector, p.status, p.priority, " . $prioritySelect . ", p.location, " . ($hasProvince ? "p.province" : "'' AS province") . ", " . ($hasBarangay ? "p.barangay" : "'' AS barangay") . ", " . ($hasBudget ? "p.budget" : "0 AS budget") . ", " . ($hasStartDate ? "p.start_date" : "NULL AS start_date") . ", " . ($hasEndDate ? "p.end_date" : "NULL AS end_date") . ", " . ($hasDurationMonths ? "p.duration_months" : "NULL AS duration_months") . ", " . ($hasLicenseDoc ? "p.engineer_license_doc" : "'' AS engineer_license_doc") . ", " . ($hasCertDoc ? "p.engineer_certification_doc" : "'' AS engineer_certification_doc") . ", " . ($hasCredDoc ? "p.engineer_credentials_doc" : "'' AS engineer_credentials_doc") . " FROM projects p 
                               INNER JOIN contractor_project_assignments cpa ON p.id = cpa.project_id 
                               WHERE cpa.contractor_id = ?");
        } catch (Throwable $e) {
            error_log('registered_contractors get_assigned_projects prepare error: ' . $e->getMessage());
            echo json_encode([]);
            exit;
        }
        if (!$stmt) {
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param("i", $contractor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        
        $hasTaskTable = registered_table_exists($db, 'project_tasks');
        $hasMilestoneTable = registered_table_exists($db, 'project_milestones');
        while ($row = $result->fetch_assoc()) {
            $row['allocated_budget'] = (float) ($row['budget'] ?? 0);
            $row['spent_budget'] = 0.0;
            $row['remaining_budget'] = max(0, $row['allocated_budget'] - $row['spent_budget']);
            $row['location_exact'] = (string) ($row['location'] ?? '');
            $row['full_address'] = trim(((string) ($row['province'] ?? '')) . ' / ' . ((string) ($row['barangay'] ?? '')) . ' / ' . ((string) ($row['location'] ?? '')));
            $row['task_summary'] = ['total' => 0, 'completed' => 0];
            $row['milestone_summary'] = ['total' => 0, 'completed' => 0];
            if ($hasTaskTable) {
                $taskRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_tasks WHERE project_id = " . (int)$row['id']);
                if ($taskRes) {
                    $taskRow = $taskRes->fetch_assoc();
                    $taskRes->free();
                    $row['task_summary'] = ['total' => (int)($taskRow['total'] ?? 0), 'completed' => (int)($taskRow['completed'] ?? 0)];
                }
            }
            if ($hasMilestoneTable) {
                $mileRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_milestones WHERE project_id = " . (int)$row['id']);
                if ($mileRes) {
                    $mileRow = $mileRes->fetch_assoc();
                    $mileRes->free();
                    $row['milestone_summary'] = ['total' => (int)($mileRow['total'] ?? 0), 'completed' => (int)($mileRow['completed'] ?? 0)];
                }
            }
            $row['documents'] = array_values(array_filter([
                $row['engineer_license_doc'] ?? '',
                $row['engineer_certification_doc'] ?? '',
                $row['engineer_credentials_doc'] ?? ''
            ]));
            $projects[] = $row;
        }
        
        echo json_encode($projects);
        $stmt->close();
    } else {
        echo json_encode([]);
    }
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Engineers - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/table-redesign-base.css">
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
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="project_registration.php"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration    â–¼</a>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            
            <!-- Engineers with Submenu -->
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle">
                    <img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers
                    <span class="dropdown-arrow">â–¼</span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">âž•</span>
                        <span>Add Engineer</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">ðŸ‘·</span>
                        <span>Registered Engineers</span>
                    </a>
                </div>
            </div>
            
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
                        <a href="citizen-verification.php" class="nav-citizen-verification"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
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
            <h1>Registered Engineers</h1>
            <p>Review Engineer records, assign projects, and monitor accreditation status.</p>
        </div>

        <div class="recent-projects contractor-page contractor-registry-shell">
            <div class="contractor-page-head">
                <div>
                    <h3>Engineer Registry</h3>
                    <p>Search, filter, assign, and maintain active Engineers in one workspace.</p>
                </div>
                <div class="contractor-head-tools">
                    <span id="contractorLastSync" class="contractor-last-sync">Last synced: --</span>
                    <button type="button" id="refreshContractorsBtn" class="btn-contractor-secondary">Refresh</button>
                    <button type="button" id="exportContractorsCsvBtn" class="btn-contractor-primary">Export CSV</button>
                </div>
            </div>

            <div class="contractors-filter contractor-toolbar">
                <input
                    type="search"
                    id="searchContractors"
                    placeholder="Search by company, license, email, or phone"
                >
                <select id="filterStatus">
                    <option value="">All Status</option>
                    <option>Active</option>
                    <option>Suspended</option>
                    <option>Blacklisted</option>
                </select>
                <div id="contractorsCount" class="contractor-count-pill">0 Engineers</div>
            </div>

            <div class="contractor-stats-grid">
                <article class="contractor-stat-card">
                    <span>Total Engineers</span>
                    <strong id="contractorStatTotal">0</strong>
                </article>
                <article class="contractor-stat-card is-active">
                    <span>Active</span>
                    <strong id="contractorStatActive">0</strong>
                </article>
                <article class="contractor-stat-card is-suspended">
                    <span>Suspended</span>
                    <strong id="contractorStatSuspended">0</strong>
                </article>
                <article class="contractor-stat-card is-blacklisted">
                    <span>Blacklisted</span>
                    <strong id="contractorStatBlacklisted">0</strong>
                </article>
                <article class="contractor-stat-card is-rating">
                    <span>Average Rating</span>
                    <strong id="contractorStatAvgRating">0.0</strong>
                </article>
            </div>

            <div class="contractors-section">
                <div id="formMessage" class="contractor-form-message" role="status" aria-live="polite"></div>
                
                <div class="table-wrap">
                    <table id="contractorsTable" class="table">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>License Number</th>
                                <th>Contact Email</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Projects Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="projects-section contractor-project-bank" id="available-projects">
                <h3>Available Projects</h3>
                <p class="contractor-subtext">Projects listed below are available for assignment to selected engineers.</p>
                <div class="table-wrap">
                    <table id="projectsTable" class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Sector</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

<!-- Assignment Modal -->
    <div id="assignmentModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="assignmentTitle">
        <div class="contractor-modal-panel">
            <input type="hidden" id="assignContractorId" value="">
            <h2 id="assignmentTitle"></h2>
            <div id="projectsList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="assignCancelBtn" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="saveAssignments" class="btn-contractor-primary">Save Assignments</button>
            </div>
        </div>
    </div>

    <!-- Projects View Modal -->
    <div id="projectsViewModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="projectsViewTitle">
        <div class="contractor-modal-panel">
            <h2 id="projectsViewTitle"></h2>
            <div id="projectsViewList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="projectsCloseBtn" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>

    <div id="contractorDeleteModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="contractorDeleteTitle">
        <div class="contractor-modal-panel contractor-delete-panel">
            <div class="contractor-delete-head">
                <span class="contractor-delete-icon">!</span>
                <h2 id="contractorDeleteTitle">Delete Engineer?</h2>
            </div>
            <p class="contractor-delete-message">This Engineer and all related assignment records will be permanently deleted.</p>
            <div id="contractorDeleteName" class="contractor-delete-name"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="contractorDeleteCancel" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="contractorDeleteConfirm" class="btn-contractor-danger">Delete Permanently</button>
            </div>
        </div>
    </div>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-registered-engineers.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-registered-engineers.js'); ?>"></script>
</body>
</html>
























