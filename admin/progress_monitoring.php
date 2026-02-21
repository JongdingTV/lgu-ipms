<?php
// Import security functions first
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Set no-cache headers to prevent back button access
set_no_cache_headers();

// Check authentication
check_auth();

// Check for suspicious activity
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

function progress_projects_has_created_at(mysqli $db): bool
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

function progress_project_has_column(mysqli $db, string $columnName): bool
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
    $stmt->bind_param('s', $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function progress_table_has_column(mysqli $db, string $tableName, string $columnName): bool
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
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function progress_table_exists(mysqli $db, string $tableName): bool
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
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) $result->free();
    $stmt->close();
    return $exists;
}

// Handle API requests first (before rendering HTML)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    // Build schema-safe SELECT (no fatal error if migration is not yet applied).
    $hasCreatedAt = progress_projects_has_created_at($db);
    $hasPriorityPercent = progress_project_has_column($db, 'priority_percent');
    $hasDurationMonths = progress_project_has_column($db, 'duration_months');
    $hasStartDate = progress_project_has_column($db, 'start_date');
    $hasEndDate = progress_project_has_column($db, 'end_date');
    $hasTaskTable = progress_table_exists($db, 'project_tasks');
    $hasMilestoneTable = progress_table_exists($db, 'project_milestones');
    $hasAssignmentsTable = progress_table_exists($db, 'contractor_project_assignments');
    $contractorCompanyCol = progress_table_has_column($db, 'contractors', 'company') ? 'company' : (progress_table_has_column($db, 'contractors', 'company_name') ? 'company_name' : (progress_table_has_column($db, 'contractors', 'name') ? 'name' : null));
    $contractorRatingCol = progress_table_has_column($db, 'contractors', 'rating') ? 'rating' : null;

    $selectFields = [
        'id',
        'code',
        'name',
        'description',
        'location',
        'province',
        'sector',
        'budget',
        'status',
        'priority'
    ];
    $selectFields[] = $hasPriorityPercent ? 'priority_percent' : '0 AS priority_percent';
    $selectFields[] = $hasStartDate ? 'start_date' : 'NULL AS start_date';
    $selectFields[] = $hasEndDate ? 'end_date' : 'NULL AS end_date';
    $selectFields[] = $hasDurationMonths ? 'duration_months' : 'NULL AS duration_months';
    if ($hasCreatedAt) {
        $selectFields[] = 'created_at';
    }

    $orderBy = $hasCreatedAt ? "created_at DESC" : "id DESC";
    $projects = [];

    try {
        $result = $db->query("SELECT " . implode(', ', $selectFields) . " FROM projects ORDER BY {$orderBy} LIMIT 500");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
            // Keep progress neutral unless an explicit progress field is available from schema/data.
            $row['progress'] = isset($row['progress']) ? (float)$row['progress'] : 0;
            $updateDate = $row['created_at'] ?? $row['start_date'] ?? null;
            $status = (string)($row['status'] ?? 'Draft');
            $row['process_update'] = $status . ($updateDate ? ' (' . date('M d, Y', strtotime((string)$updateDate)) . ')' : '');
            
            // Get Assigned Engineers for this project
            $contractorsQuery = false;
            if ($hasAssignmentsTable) {
                $contractorsQuery = $db->query("
                    SELECT c.id, " . ($contractorCompanyCol ? "c.{$contractorCompanyCol}" : "''") . " AS company, " . ($contractorRatingCol ? "c.{$contractorRatingCol}" : "0") . " AS rating
                    FROM contractors c
                    INNER JOIN contractor_project_assignments cpa ON c.id = cpa.contractor_id
                    WHERE cpa.project_id = " . intval($row['id'])
                );
            }
            
            $Engineers = [];
            if ($contractorsQuery) {
                while ($Engineer = $contractorsQuery->fetch_assoc()) {
                    $Engineers[] = $Engineer;
                }
                $contractorsQuery->free();
            }
            
            $row['assigned_contractors'] = $Engineers;
            $row['task_summary'] = [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
                'actual' => 0
            ];
            $row['milestone_summary'] = [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
                'actual' => 0
            ];

            if ($hasTaskTable) {
                $taskSql = "SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN planned_start IS NOT NULL OR planned_end IS NOT NULL THEN 1 ELSE 0 END) AS planned, SUM(CASE WHEN actual_start IS NOT NULL OR actual_end IS NOT NULL THEN 1 ELSE 0 END) AS actual FROM project_tasks WHERE project_id = " . intval($row['id']);
                $taskRes = $db->query($taskSql);
                if ($taskRes) {
                    $taskStats = $taskRes->fetch_assoc();
                    $taskRes->free();
                    if ($taskStats) {
                        $row['task_summary'] = [
                            'total' => (int) ($taskStats['total'] ?? 0),
                            'completed' => (int) ($taskStats['completed'] ?? 0),
                            'planned' => (int) ($taskStats['planned'] ?? 0),
                            'actual' => (int) ($taskStats['actual'] ?? 0)
                        ];
                    }
                }
            }
            if ($hasMilestoneTable) {
                $mileSql = "SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN planned_date IS NOT NULL THEN 1 ELSE 0 END) AS planned, SUM(CASE WHEN actual_date IS NOT NULL THEN 1 ELSE 0 END) AS actual FROM project_milestones WHERE project_id = " . intval($row['id']);
                $mileRes = $db->query($mileSql);
                if ($mileRes) {
                    $mileStats = $mileRes->fetch_assoc();
                    $mileRes->free();
                    if ($mileStats) {
                        $row['milestone_summary'] = [
                            'total' => (int) ($mileStats['total'] ?? 0),
                            'completed' => (int) ($mileStats['completed'] ?? 0),
                            'planned' => (int) ($mileStats['planned'] ?? 0),
                            'actual' => (int) ($mileStats['actual'] ?? 0)
                        ];
                    }
                }
            }
            $projects[] = $row;
        }
            $result->free();
        }
    } catch (Throwable $e) {
        error_log('progress_monitoring load_projects error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }

    echo json_encode($projects);
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
        
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - LGU IPMS</title>
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
        <link rel="stylesheet" href="../assets/css/admin-progress-monitoring.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-progress-monitoring.css'); ?>">
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
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
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
            <h1>Progress Monitoring</h1>
            <p>Track and manage project progress in real-time</p>
        </div>

        <div class="pm-section card">
            <!-- Statistics Summary -->
            <div class="pm-stats-wrapper">
                <div class="stat-box stat-total">
                    <div class="stat-number" id="statTotal">0</div>
                    <div class="stat-label">Total Projects</div>
                </div>
                <div class="stat-box stat-approved">
                    <div class="stat-number" id="statApproved">0</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-box stat-progress">
                    <div class="stat-number" id="statInProgress">0</div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-box stat-completed">
                    <div class="stat-number" id="statCompleted">0</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-box stat-contractors">
                    <div class="stat-number" id="statContractors">0</div>
                    <div class="stat-label">Assigned Engineers</div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="pm-controls-wrapper">
                <div class="pm-controls">
                    <div class="pm-top-row">
                        <div class="pm-left">
                            <label for="pmSearch">Search Projects</label>
                            <input id="pmSearch" type="search" placeholder="Search by code, name, or location...">
                        </div>
                        <button id="exportCsv" type="button" class="btn-export">Export CSV</button>
                    </div>

                    <div class="pm-right">
                        <div class="filter-group">
                            <label for="pmStatusFilter">Status</label>
                            <select id="pmStatusFilter" title="Filter by status">
                                <option value="">All Status</option>
                                <option>Draft</option>
                                <option>For Approval</option>
                                <option>Approved</option>
                                <option>On-hold</option>
                                <option>Cancelled</option>
                                <option>Completed</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmSectorFilter">Sector</label>
                            <select id="pmSectorFilter" title="Filter by sector">
                                <option value="">All Sectors</option>
                                <option>Road</option>
                                <option>Drainage</option>
                                <option>Building</option>
                                <option>Water</option>
                                <option>Sanitation</option>
                                <option>Other</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmProgressFilter">Progress</label>
                            <select id="pmProgressFilter" title="Filter by progress">
                                <option value="">All Progress</option>
                                <option value="0-25">0-25%</option>
                                <option value="25-50">25-50%</option>
                                <option value="50-75">50-75%</option>
                                <option value="75-100">75-100%</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmContractorFilter">Has Engineers</label>
                            <select id="pmContractorFilter" title="Filter by engineers">
                                <option value="">All Projects</option>
                                <option value="assigned">With Engineers</option>
                                <option value="unassigned">No Engineers</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmSort">Sort</label>
                            <select id="pmSort" title="Sort">
                                <option value="createdAt_desc">Newest</option>
                                <option value="createdAt_asc">Oldest</option>
                                <option value="progress_desc">Progress (high to low)</option>
                                <option value="progress_asc">Progress (low to high)</option>
                            </select>
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

            <!-- Projects Display -->
            <div class="pm-content">
                <h3>Tracked Projects</h3>
                <div id="projectsList" class="projects-list">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Loading projects...</p>
                    </div>
                </div>

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

    

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>





