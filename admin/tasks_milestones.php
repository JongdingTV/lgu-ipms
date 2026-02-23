<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Protect page
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.progress.view', ['admin','department_admin','super_admin']);
rbac_require_action_matrix(
    strtolower(trim((string)($_REQUEST['action'] ?? ''))),
    [
        'load_projects' => 'admin.progress.view',
    ],
    'admin.progress.view'
);
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading projects
function tasks_has_created_at(mysqli $db): bool
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
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $exists;
}

function tasks_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $args = [$types];
    foreach ($params as $k => $v) {
        $args[] = &$params[$k];
    }
    return call_user_func_array([$stmt, 'bind_param'], $args);
}

function tasks_load_projects(mysqli $db, array $options = []): array
{
    $hasCreatedAt = tasks_has_created_at($db);
    $page = max(1, (int)($options['page'] ?? 1));
    $perPage = (int)($options['per_page'] ?? 20);
    if ($perPage < 1) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;
    $q = strtolower(trim((string)($options['q'] ?? '')));
    $status = trim((string)($options['status'] ?? ''));

    $where = [];
    $types = '';
    $params = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(LOWER(COALESCE(code, \'\')) LIKE ? OR LOWER(COALESCE(name, \'\')) LIKE ? OR LOWER(COALESCE(sector, \'\')) LIKE ? OR LOWER(COALESCE(location, \'\')) LIKE ?)';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '') {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
    $orderBy = $hasCreatedAt ? 'created_at DESC' : 'id DESC';

    try {
        $countSql = "SELECT COUNT(*) AS total FROM projects{$whereSql}";
        $countStmt = $db->prepare($countSql);
        if (!$countStmt) {
            return ['projects' => [], 'error' => 'Failed to prepare project count', 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
        }
        if (!tasks_bind_dynamic($countStmt, $types, $params)) {
            $countStmt->close();
            return ['projects' => [], 'error' => 'Failed to bind count parameters', 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total = 0;
        if ($countRes) {
            $row = $countRes->fetch_assoc();
            $total = (int)($row['total'] ?? 0);
            $countRes->free();
        }
        $countStmt->close();

        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT * FROM projects{$whereSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $dataStmt = $db->prepare($dataSql);
        if (!$dataStmt) {
            return ['projects' => [], 'error' => 'Failed to prepare project list', 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $totalPages];
        }
        $dataTypes = $types . 'ii';
        $dataParams = $params;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;
        if (!tasks_bind_dynamic($dataStmt, $dataTypes, $dataParams)) {
            $dataStmt->close();
            return ['projects' => [], 'error' => 'Failed to bind list parameters', 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $totalPages];
        }
        $dataStmt->execute();
        $result = $dataStmt->get_result();
        $projects = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            $result->free();
        }
        $dataStmt->close();

        return [
            'projects' => $projects,
            'error' => null,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    } catch (Throwable $e) {
        return ['projects' => [], 'error' => 'Failed to load projects: ' . $e->getMessage(), 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    $data = tasks_load_projects($db, [
        'page' => (int)($_GET['page'] ?? 1),
        'per_page' => (int)($_GET['per_page'] ?? 20),
        'q' => (string)($_GET['q'] ?? ''),
        'status' => (string)($_GET['status'] ?? ''),
    ]);
    if (!empty($data['error'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $data['error']]);
        $db->close();
        exit;
    }
    if (isset($_GET['v2']) && $_GET['v2'] === '1') {
        echo json_encode([
            'success' => true,
            'data' => $data['projects'],
            'meta' => [
                'page' => (int)($data['page'] ?? 1),
                'per_page' => (int)($data['per_page'] ?? 20),
                'total' => (int)($data['total'] ?? count($data['projects'])),
                'total_pages' => (int)($data['total_pages'] ?? 1),
                'has_prev' => ((int)($data['page'] ?? 1)) > 1,
                'has_next' => ((int)($data['page'] ?? 1)) < ((int)($data['total_pages'] ?? 1)),
            ]
        ]);
    } else {
        echo json_encode($data['projects']);
    }
    $db->close();
    exit;
}

$db->close();
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
            <a href="tasks_milestones.php" class="active"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="/engineer/create.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Engineer Account Creation</span></a>
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
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
            <h1>Checking and Validation</h1>
            <p>Monitor and validate project deliverables. Visualize completion and validation status as a percentage.</p>
        </div>

        <div class="recent-projects">
            <div class="validation-summary">
                <h3>Validation Progress</h3>
                <div class="progress-bar-container">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill ac-034359d7" id="validationProgress"></div>
                    </div>
                    <span id="validationPercent">0%</span>
                </div>
            </div>
            <div class="tasks-section">
                <h3>Validation Items</h3>
                <div class="table-wrap">
                    <table id="tasksTable" class="table">
                        <thead>
                            <tr><th>Deliverable</th><th>Status</th><th>Validated</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>






















