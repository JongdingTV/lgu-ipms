<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require dirname(__DIR__) . '/includes/project-workflow.php';

// Set no-cache headers to prevent back button access
set_no_cache_headers();

// Check authentication
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.projects.manage', ['admin','department_admin','super_admin']);
$csrfToken = generate_csrf_token();
$rbacAction = strtolower(trim((string)($_REQUEST['action'] ?? '')));
rbac_require_action_matrix(
    $rbacAction,
    [
        'delete_project' => 'admin.projects.delete',
        'update_project' => 'admin.projects.manage',
        'get_project' => 'admin.projects.read',
        'load_projects' => 'admin.projects.read',
        'export_projects_csv' => 'admin.projects.export',
        'load_project_timeline' => 'admin.projects.read',
    ],
    'admin.projects.manage'
);

// Check for suspicious activity
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

/**
 * Load projects with robust query fallbacks.
 * This avoids empty screens when schema/permissions vary across environments.
 *
 * @return array{projects: array<int, array<string, mixed>>, error: ?string}
 */
function registered_projects_has_created_at(mysqli $db): bool
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

function registered_projects_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bindArgs = [$types];
    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bindArgs);
}

function load_projects_data(mysqli $db, array $options = []): array
{
    $hasCreatedAt = registered_projects_has_created_at($db);
    $page = max(1, (int) ($options['page'] ?? 1));
    $perPage = (int) ($options['per_page'] ?? 20);
    if ($perPage < 1) {
        $perPage = 20;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }
    $offset = ($page - 1) * $perPage;
    $q = strtolower(trim((string) ($options['q'] ?? '')));
    $status = trim((string) ($options['status'] ?? ''));
    $sort = trim((string) ($options['sort'] ?? 'createdAt_desc'));

    $where = [];
    $types = '';
    $params = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(LOWER(COALESCE(code, \'\')) LIKE ? OR LOWER(COALESCE(name, \'\')) LIKE ? OR LOWER(COALESCE(location, \'\')) LIKE ? OR LOWER(COALESCE(sector, \'\')) LIKE ?)';
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
    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sortMap = [
        'createdAt_desc' => $hasCreatedAt ? 'created_at DESC' : 'id DESC',
        'createdAt_asc' => $hasCreatedAt ? 'created_at ASC' : 'id ASC',
        'name_asc' => 'name ASC',
        'name_desc' => 'name DESC',
    ];
    $orderBy = $sortMap[$sort] ?? ($hasCreatedAt ? 'created_at DESC' : 'id DESC');

    try {
        $countSql = "SELECT COUNT(*) AS total FROM projects {$whereSql}";
        $countStmt = $db->prepare($countSql);
        if (!$countStmt) {
            return ['projects' => [], 'error' => 'Failed to prepare project count query', 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
        }
        if (!registered_projects_bind_dynamic($countStmt, $types, $params)) {
            $countStmt->close();
            return ['projects' => [], 'error' => 'Failed to bind project count query', 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total = 0;
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $total = (int) ($countRow['total'] ?? 0);
            $countRes->free();
        }
        $countStmt->close();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $dataSql = "SELECT * FROM projects {$whereSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $dataStmt = $db->prepare($dataSql);
        if (!$dataStmt) {
            return ['projects' => [], 'error' => 'Failed to prepare project data query', 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $totalPages];
        }
        $dataTypes = $types . 'ii';
        $dataParams = $params;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;
        if (!registered_projects_bind_dynamic($dataStmt, $dataTypes, $dataParams)) {
            $dataStmt->close();
            return ['projects' => [], 'error' => 'Failed to bind project data query', 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $totalPages];
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
            'total_pages' => $totalPages
        ];
    } catch (Throwable $e) {
        return ['projects' => [], 'error' => 'Failed to load projects: ' . $e->getMessage(), 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
    }
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $data = load_projects_data($db, [
        'page' => (int) ($_GET['page'] ?? 1),
        'per_page' => (int) ($_GET['per_page'] ?? 20),
        'q' => (string) ($_GET['q'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'sort' => (string) ($_GET['sort'] ?? 'createdAt_desc'),
    ]);
    if (!empty($data['error'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $data['error']]);
        exit;
    }

    if (isset($_GET['v2']) && $_GET['v2'] === '1') {
        echo json_encode([
            'success' => true,
            'data' => $data['projects'],
            'meta' => [
                'page' => (int) ($data['page'] ?? 1),
                'per_page' => (int) ($data['per_page'] ?? 20),
                'total' => (int) ($data['total'] ?? count($data['projects'])),
                'total_pages' => (int) ($data['total_pages'] ?? 1),
                'has_prev' => ((int) ($data['page'] ?? 1)) > 1,
                'has_next' => ((int) ($data['page'] ?? 1)) < ((int) ($data['total_pages'] ?? 1))
            ]
        ]);
        exit;
    }

    echo json_encode($data['projects']); // legacy fallback
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_projects_csv') {
    $data = load_projects_data($db, [
        'page' => 1,
        'per_page' => 100000,
        'q' => (string) ($_GET['q'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'sort' => (string) ($_GET['sort'] ?? 'createdAt_desc'),
    ]);

    if (!empty($data['error'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $data['error']]);
        exit;
    }

    $filename = 'registered-projects-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Project Code', 'Project Name', 'Type', 'Sector', 'Priority', 'Status', 'Date Created']);
    foreach ($data['projects'] as $p) {
        $created = !empty($p['created_at']) ? date('Y-m-d H:i:s', strtotime((string) $p['created_at'])) : '';
        fputcsv($out, [
            (string) ($p['code'] ?? ''),
            (string) ($p['name'] ?? ''),
            (string) ($p['type'] ?? ''),
            (string) ($p['sector'] ?? ''),
            (string) ($p['priority'] ?? ''),
            (string) ($p['status'] ?? ''),
            $created
        ]);
    }
    fclose($out);
    exit;
}

// Handle GET request for single project (edit)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_project') {
    header('Content-Type: application/json');
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            echo json_encode($result->fetch_assoc());
        } else {
            echo json_encode(['success' => false, 'message' => 'Project not found']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    }
    exit;
}

// Handle GET request for project status timeline
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_project_timeline') {
    header('Content-Type: application/json');

    $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
        exit;
    }

    $sql = "
        SELECT
            h.status,
            h.notes,
            h.changed_at,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), e.email, 'System') AS changed_by
        FROM project_status_history h
        LEFT JOIN employees e ON e.id = h.changed_by
        WHERE h.project_id = ?
        ORDER BY h.changed_at DESC, h.id DESC
        LIMIT 150
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to prepare timeline query']);
        exit;
    }

    $stmt->bind_param("i", $projectId);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Unable to load timeline']);
        exit;
    }

    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'status' => (string)($row['status'] ?? ''),
            'notes' => (string)($row['notes'] ?? ''),
            'changed_at' => (string)($row['changed_at'] ?? ''),
            'changed_by' => (string)($row['changed_by'] ?? 'System'),
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// Handle UPDATE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project') {
    header('Content-Type: application/json');
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $sector = isset($_POST['sector']) ? $_POST['sector'] : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    
    if ($id > 0 && !empty($name)) {
        $transition = pw_validate_transition($db, $id, (string)$status);
        if (!$transition['ok']) {
            echo json_encode(['success' => false, 'message' => (string)$transition['message']]);
            exit;
        }
        $oldStatus = (string)($transition['current'] ?? '');
        $status = (string)($transition['next'] ?? 'Draft');

        $stmt = $db->prepare("UPDATE projects SET name=?, code=?, type=?, sector=?, priority=?, status=?, description=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $code, $type, $sector, $priority, $status, $description, $id);
        
        if ($stmt->execute()) {
            if ($oldStatus !== '' && $oldStatus !== $status) {
                $actorId = (int)($_SESSION['employee_id'] ?? 0);
                pw_log_status_history($db, $id, $status, $actorId, "Status changed from {$oldStatus} to {$status} via Registered Projects.");
            }
            if (function_exists('rbac_audit')) {
                rbac_audit('project.update', 'project', $id, [
                    'code' => $code,
                    'name' => $name,
                    'status' => $status,
                    'priority' => $priority
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update project: ' . $db->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid project data']);
    }
    exit;
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project') {
    header('Content-Type: application/json');
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM projects WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if (function_exists('rbac_audit')) {
                rbac_audit('project.delete', 'project', $id, []);
            }
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete project: ' . $db->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    }
    exit;
}

// Initial data for server-rendered fallback (non-AJAX page view).
$initialProjects = [];
$initialLoadError = null;
$initialData = load_projects_data($db);
$initialProjects = $initialData['projects'];
$initialLoadError = $initialData['error'];

$db->close();
?>
<!doctype html>
<html>
<head>
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Projects - LGU IPMS</title>
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
                <a href="project_registration.php" class="nav-main-item active" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu show" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="registered_engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
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
            <h1>Registered Projects</h1>
            <p>View and manage all registered infrastructure projects</p>
        </div>

        <div class="recent-projects">
            <!-- Filter Section -->
            <div class="projects-filter">
                <div class="ac-c59ce897">
                    <input 
                        type="search" 
                        id="searchProjects" 
                        placeholder="Search projects by code, name or location..." 
                        class="ac-54b56ade"
                    >
                    <select 
                        id="filterStatus" 
                        class="ac-5c727874"
                    >
                        <option value="">All Status</option>
                        <option>Draft</option>
                        <option>For Approval</option>
                        <option>Approved</option>
                        <option>On-hold</option>
                        <option>Cancelled</option>
                    </select>
                    <button id="exportCsv" class="ac-1974716d">Export CSV</button>
                </div>
            </div>

            <!-- Registered Projects Table -->
            <div class="projects-section">
                <div id="formMessage" class="ac-2be89d81"></div>
                
                <div class="table-wrap">
                    <table id="projectsTable" class="table">
                        <thead>
                            <tr>
                                <th>Project Code</th>
                                <th>Project Name</th>
                                <th>Type</th>
                                <th>Sector</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($initialLoadError)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:20px; color:#c00;">
                                        <?php echo htmlspecialchars($initialLoadError, ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                </tr>
                            <?php elseif (count($initialProjects) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:20px; color:#6b7280;">No projects found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($initialProjects as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($p['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($p['sector'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php
                                        $priorityLevel = (string)($p['priority'] ?? 'Medium');
                                        $priorityMap = ['crucial' => 100, 'high' => 75, 'medium' => 50, 'low' => 25];
                                        $priorityPct = $priorityMap[strtolower($priorityLevel)] ?? 50;
                                        ?>
                                        <td><span class="priority-badge <?php echo strtolower(str_replace(' ', '', $priorityLevel)); ?>"><?php echo htmlspecialchars($priorityLevel . ' ' . $priorityPct . '%', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><span class="status-badge <?php echo strtolower(str_replace(' ', '', $p['status'] ?? 'draft')); ?>"><?php echo htmlspecialchars($p['status'] ?? 'Draft', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo !empty($p['created_at']) ? date('n/j/Y', strtotime($p['created_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-timeline" data-id="<?php echo (int)($p['id'] ?? 0); ?>" type="button">Timeline</button>
                                                <button class="btn-edit" data-id="<?php echo (int)($p['id'] ?? 0); ?>">Edit</button>
                                                <button class="btn-delete" data-id="<?php echo (int)($p['id'] ?? 0); ?>">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="edit-project-modal">
        <div class="edit-project-modal-content">
            <div class="edit-project-modal-header">
                <h2>Edit Project</h2>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="edit-project-modal-body">
                <form id="editProjectForm">
                    <input type="hidden" id="projectId" name="id">
                    <input type="hidden" id="projectCsrfToken" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="form-group">
                        <label for="projectCode">Project Code</label>
                        <input type="text" id="projectCode" name="code" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectName">Project Name</label>
                        <input type="text" id="projectName" name="name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="projectType">Type</label>
                            <input type="text" id="projectType" name="type">
                        </div>
                        <div class="form-group">
                            <label for="projectSector">Sector</label>
                            <input type="text" id="projectSector" name="sector">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="projectPriority">Priority</label>
                            <select id="projectPriority" name="priority">
                                <option value="">-- Select Priority --</option>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="projectStatus">Status</label>
                            <select id="projectStatus" name="status">
                                <option value="">-- Select Status --</option>
                                <option value="Draft">Draft</option>
                                <option value="For Approval">For Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="On-hold">On-hold</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectDescription">Description</label>
                        <textarea id="projectDescription" name="description" rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="edit-project-modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button class="btn-save" onclick="saveProject()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="delete-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle" aria-hidden="true">
        <div class="delete-confirm-content">
            <div class="delete-confirm-header">
                <div class="delete-confirm-icon" aria-hidden="true">!</div>
                <h2 id="deleteConfirmTitle">Delete Project?</h2>
            </div>
            <div class="delete-confirm-body">
                <p id="deleteConfirmMessage">This project and all associated data will be permanently deleted.</p>
                <div id="deleteConfirmProjectName" class="delete-confirm-project"></div>
            </div>
            <div class="delete-confirm-footer">
                <button type="button" id="deleteConfirmCancel" class="btn-cancel">Cancel</button>
                <button type="button" id="deleteConfirmProceed" class="btn-delete">Delete Permanently</button>
            </div>
        </div>
    </div>

    <!-- Project Status Timeline Modal -->
    <div id="projectTimelineModal" class="edit-project-modal" role="dialog" aria-modal="true" aria-labelledby="projectTimelineTitle" aria-hidden="true">
        <div class="edit-project-modal-content">
            <div class="edit-project-modal-header">
                <h2 id="projectTimelineTitle">Project Status Timeline</h2>
                <button class="close-modal" type="button" id="closeTimelineModalBtn">&times;</button>
            </div>
            <div class="edit-project-modal-body">
                <div id="timelineProjectName" class="timeline-project-name"></div>
                <div class="timeline-summary" id="timelineSummary">
                    <div class="timeline-summary-card">
                        <span class="timeline-summary-label">Latest Status</span>
                        <span class="timeline-summary-value" id="timelineLatestStatus">-</span>
                    </div>
                    <div class="timeline-summary-card">
                        <span class="timeline-summary-label">Total Changes</span>
                        <span class="timeline-summary-value" id="timelineTotalChanges">0</span>
                    </div>
                    <div class="timeline-summary-card">
                        <span class="timeline-summary-label">Most Frequent</span>
                        <span class="timeline-summary-value" id="timelineMostFrequent">-</span>
                    </div>
                    <div class="timeline-summary-card">
                        <span class="timeline-summary-label">Reviewers</span>
                        <span class="timeline-summary-value" id="timelineReviewers">0</span>
                    </div>
                </div>
                <div class="timeline-toolbar">
                    <label for="timelineRange">Show:</label>
                    <select id="timelineRange" class="timeline-range">
                        <option value="all">All history</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <input type="search" id="timelineSearch" class="timeline-search" placeholder="Search status, notes, or reviewer">
                    <label class="timeline-toggle">
                        <input type="checkbox" id="timelineShowDuplicates">
                        Show duplicate logs
                    </label>
                    <button type="button" id="timelineExportCsvBtn" class="btn-save timeline-export-btn">Export CSV</button>
                </div>
                <div id="timelineCount" class="timeline-count"></div>
                <div id="timelineList" class="timeline-list">
                    <div class="timeline-empty">Loading timeline...</div>
                </div>
                <div class="timeline-loadmore-wrap">
                    <button type="button" id="timelineLoadMoreBtn" class="btn-cancel timeline-loadmore-btn" style="display:none;">Load More</button>
                </div>
            </div>
            <div class="edit-project-modal-footer">
                <button type="button" class="btn-cancel" id="timelineCloseFooterBtn">Close</button>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="../assets/css/admin-registered-projects.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-registered-projects.css'); ?>">
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-registered-projects.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-registered-projects.js'); ?>"></script>
</body>
</html>

























