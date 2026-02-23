<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/includes/project-workflow.php';

// Protect page
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.projects.manage', ['admin','department_admin','super_admin']);
$rbacAction = strtolower(trim((string)($_REQUEST['action'] ?? '')));
rbac_require_action_matrix(
    $rbacAction,
    [
        'save_project' => 'admin.projects.manage',
        'delete_project' => 'admin.projects.delete',
        'load_projects' => 'admin.projects.read',
    ],
    'admin.projects.manage'
);
check_suspicious_activity();
if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

function project_has_column(mysqli $db, string $columnName): bool
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

$projectsHasCreatedAt = project_has_column($db, 'created_at');
$projectsHasPriorityPercent = project_has_column($db, 'priority_percent');
$projectsHasLicenseDoc = project_has_column($db, 'engineer_license_doc');
$projectsHasCertificationDoc = project_has_column($db, 'engineer_certification_doc');
$projectsHasCredentialsDoc = project_has_column($db, 'engineer_credentials_doc');

function build_db_debug_error(mysqli $db, string $context, string $stmtError = ''): string
{
    $parts = [];
    $parts[] = $context;
    $parts[] = 'db_errno=' . (int)$db->errno;
    $parts[] = 'db_error=' . ($db->error ?: 'n/a');
    if ($stmtError !== '') {
        $parts[] = 'stmt_error=' . $stmtError;
    }
    return implode(' | ', $parts);
}

function ensure_department_head_review_table(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS project_department_head_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL UNIQUE,
        decision_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        decision_note TEXT NULL,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_decision_status (decision_status),
        CONSTRAINT fk_dept_review_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_dept_review_employee FOREIGN KEY (decided_by) REFERENCES employees(id) ON DELETE SET NULL
    )");
}

function queue_project_for_department_head(mysqli $db, int $projectId): void
{
    if ($projectId <= 0) {
        return;
    }
    ensure_department_head_review_table($db);
    $stmt = $db->prepare(
        "INSERT INTO project_department_head_reviews (project_id, decision_status, decision_note, decided_by, decided_at)
         VALUES (?, 'Pending', NULL, NULL, NULL)
         ON DUPLICATE KEY UPDATE
            decision_status = 'Pending',
            decision_note = NULL,
            decided_by = NULL,
            decided_at = NULL"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $stmt->close();
}

function is_ajax_request(): bool
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    return false;
}

function respond_project_registration(bool $success, string $message, array $extra = []): void
{
    $payload = array_merge(['success' => $success, 'message' => $message], $extra);
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    // Fallback for normal form POST: redirect back to the form page.
    $query = $success
        ? 'saved=1&msg=' . rawurlencode($message)
        : 'error=' . rawurlencode($message);
    header('Location: project_registration.php?' . $query);
    exit;
}

function sanitize_relative_upload_path(string $path): string
{
    return ltrim(str_replace(['..\\', '../', '\\'], ['', '', '/'], $path), '/');
}

function handle_engineer_doc_upload(string $field, string $prefix): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $upload = $_FILES[$field];
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed uploading ' . $prefix . ' document.');
    }

    $tmpName = (string) ($upload['tmp_name'] ?? '');
    $original = (string) ($upload['name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

    if (!in_array($ext, ENGINEER_DOC_ALLOWED_EXT, true)) {
        throw new RuntimeException('Invalid file type for ' . $prefix . ' document. Allowed: PDF, JPG, JPEG, PNG.');
    }
    if ($size <= 0 || $size > ENGINEER_DOC_MAX_SIZE) {
        throw new RuntimeException('Invalid file size for ' . $prefix . ' document. Max file size is 5MB.');
    }
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid upload source for ' . $prefix . ' document.');
    }

    $dir = rtrim(UPLOADS_PATH, '/\\') . '/engineer-docs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Unable to create upload directory for engineer documents.');
    }

    $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $fullPath = $dir . '/' . $name;
    if (!move_uploaded_file($tmpName, $fullPath)) {
        throw new RuntimeException('Unable to save uploaded ' . $prefix . ' document.');
    }

    return sanitize_relative_upload_path('uploads/engineer-docs/' . $name);
}

function priority_to_percent(string $priority): float
{
    $map = [
        'crucial' => 100.0,
        'high' => 75.0,
        'medium' => 50.0,
        'low' => 25.0
    ];
    $key = strtolower(trim($priority));
    return $map[$key] ?? 50.0;
}

function bind_stmt_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    $bindParams = [$types];
    foreach ($params as $idx => &$value) {
        $bindParams[] = &$value;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(E_ALL);
    
    if ($_POST['action'] === 'save_project') {
        // Validate required fields
        if (empty($_POST['code']) || empty($_POST['name'])) {
            respond_project_registration(false, 'Project Code and Name are required');
        }
        
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $type = isset($_POST['type']) ? trim($_POST['type']) : '';
        $sector = isset($_POST['sector']) ? trim($_POST['sector']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
        $priorityPercent = priority_to_percent($priority);
        $province = isset($_POST['province']) ? trim($_POST['province']) : '';
        $barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $duration_months = !empty($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Draft';
        $normalizedStatus = pw_normalize_status($status);
        if ($normalizedStatus === null) {
            respond_project_registration(false, 'Invalid project status value.');
        }
        $status = $normalizedStatus;
        $isUpdate = isset($_POST['id']) && !empty($_POST['id']);

        // Workflow rule: every newly registered project must pass Department Head approval first.
        // Budget must stay blank (NULL) until Department Head approval.
        if (!$isUpdate) {
            $status = 'For Approval';
            $budget = null;
        }
        
        if ($budget !== null && $budget > MAX_PROJECT_BUDGET) {
            respond_project_registration(false, 'Budget exceeds the maximum allowed amount of PHP ' . number_format((float) MAX_PROJECT_BUDGET, 2) . '.');
        }

        $allowedPriority = ['Crucial', 'High', 'Medium', 'Low'];
        if (!in_array($priority, $allowedPriority, true)) {
            $priority = 'Medium';
            $priorityPercent = 50.0;
        }

        $licenseDoc = null;
        $certificationDoc = null;
        $credentialsDoc = null;

        try {
            $licenseDoc = handle_engineer_doc_upload('engineer_license_doc', 'license');
            $certificationDoc = handle_engineer_doc_upload('engineer_certification_doc', 'certification');
            $credentialsDoc = handle_engineer_doc_upload('engineer_credentials_doc', 'credentials');
        } catch (RuntimeException $e) {
            respond_project_registration(false, $e->getMessage());
        }
        

        $oldStatus = '';
        if ($isUpdate) {

            // Update existing project
            $id = (int)$_POST['id'];
            $transition = pw_validate_transition($db, $id, $status);
            if (!$transition['ok']) {
                respond_project_registration(false, (string)$transition['message']);
            }
            $oldStatus = (string)($transition['current'] ?? '');
            $status = (string)($transition['next'] ?? $status);
            $existingLicenseDoc = null;
            $existingCertificationDoc = null;
            $existingCredentialsDoc = null;
            if ($projectsHasLicenseDoc || $projectsHasCertificationDoc || $projectsHasCredentialsDoc) {
                $cols = ['id'];
                if ($projectsHasLicenseDoc) $cols[] = 'engineer_license_doc';
                if ($projectsHasCertificationDoc) $cols[] = 'engineer_certification_doc';
                if ($projectsHasCredentialsDoc) $cols[] = 'engineer_credentials_doc';
                $docStmt = $db->prepare('SELECT ' . implode(', ', $cols) . ' FROM projects WHERE id = ? LIMIT 1');
                if ($docStmt) {
                    $docStmt->bind_param('i', $id);
                    $docStmt->execute();
                    $docRow = $docStmt->get_result()->fetch_assoc();
                    $docStmt->close();
                    if ($docRow) {
                        $existingLicenseDoc = $docRow['engineer_license_doc'] ?? null;
                        $existingCertificationDoc = $docRow['engineer_certification_doc'] ?? null;
                        $existingCredentialsDoc = $docRow['engineer_credentials_doc'] ?? null;
                    }
                }
            }

            if ($projectsHasLicenseDoc && $licenseDoc === null) $licenseDoc = $existingLicenseDoc;
            if ($projectsHasCertificationDoc && $certificationDoc === null) $certificationDoc = $existingCertificationDoc;
            if ($projectsHasCredentialsDoc && $credentialsDoc === null) $credentialsDoc = $existingCredentialsDoc;

            $sql = "UPDATE projects SET code=?, name=?, type=?, sector=?, description=?, priority=?";
            $types = 'ssssss';
            $params = [$code, $name, $type, $sector, $description, $priority];
            if ($projectsHasPriorityPercent) {
                $sql .= ", priority_percent=?";
                $types .= 'd';
                $params[] = $priorityPercent;
            }
            $sql .= ", province=?, barangay=?, location=?, start_date=?, end_date=?, duration_months=?, budget=?, status=?";
            $types .= 'sssssids';
            $params = array_merge($params, [$province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $status]);

            if ($projectsHasLicenseDoc) {
                $sql .= ", engineer_license_doc=?";
                $types .= 's';
                $params[] = $licenseDoc;
            }
            if ($projectsHasCertificationDoc) {
                $sql .= ", engineer_certification_doc=?";
                $types .= 's';
                $params[] = $certificationDoc;
            }
            if ($projectsHasCredentialsDoc) {
                $sql .= ", engineer_credentials_doc=?";
                $types .= 's';
                $params[] = $credentialsDoc;
            }
            $sql .= " WHERE id=?";
            $types .= 'i';
            $params[] = $id;
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $debugError = build_db_debug_error($db, 'Failed to prepare project update');
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
            bind_stmt_params($stmt, $types, $params);
        } else {
            // Insert new project; support schemas with or without created_at.
            $columns = ['code', 'name', 'type', 'sector', 'description', 'priority'];
            $types = 'ssssss';
            $params = [$code, $name, $type, $sector, $description, $priority];
            if ($projectsHasPriorityPercent) {
                $columns[] = 'priority_percent';
                $types .= 'd';
                $params[] = $priorityPercent;
            }
            $columns = array_merge($columns, ['province', 'barangay', 'location', 'start_date', 'end_date', 'duration_months', 'budget', 'status']);
            $types .= 'sssssids';
            $params = array_merge($params, [$province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $status]);
            if ($projectsHasLicenseDoc) {
                $columns[] = 'engineer_license_doc';
                $types .= 's';
                $params[] = $licenseDoc;
            }
            if ($projectsHasCertificationDoc) {
                $columns[] = 'engineer_certification_doc';
                $types .= 's';
                $params[] = $certificationDoc;
            }
            if ($projectsHasCredentialsDoc) {
                $columns[] = 'engineer_credentials_doc';
                $types .= 's';
                $params[] = $credentialsDoc;
            }

            if ($projectsHasCreatedAt) {
                $sql = "INSERT INTO projects (" . implode(', ', $columns) . ", created_at) VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", NOW())";
                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    $debugError = build_db_debug_error($db, 'Failed to prepare project insert (with created_at)');
                    error_log('[project_registration] ' . $debugError);
                    respond_project_registration(false, $debugError);
                }
                bind_stmt_params($stmt, $types, $params);
            } else {
                $sql = "INSERT INTO projects (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
                $stmt = $db->prepare($sql);
                if (!$stmt) {
                    $debugError = build_db_debug_error($db, 'Failed to prepare project insert (without created_at)');
                    error_log('[project_registration] ' . $debugError);
                    respond_project_registration(false, $debugError);
                }
                bind_stmt_params($stmt, $types, $params);
            }
        }
        
        try {
            $executed = $stmt->execute();
            if ($executed) {
                $savedId = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : (int)$db->insert_id;

                $isUpdate = isset($_POST['id']) && !empty($_POST['id']);
                $actorId = (int)($_SESSION['employee_id'] ?? 0);
                if ($isUpdate && $oldStatus !== '' && $oldStatus !== $status) {
                    pw_log_status_history($db, $savedId, $status, $actorId, "Status changed from {$oldStatus} to {$status} via Project Registration.");
                }
                if (!$isUpdate) {
                    pw_log_status_history($db, $savedId, $status, $actorId, 'Project created with initial status.');
                }
                if (function_exists('rbac_audit')) {
                    rbac_audit($isUpdate ? 'project.update' : 'project.create', 'project', $savedId, [
                        'code' => $code,
                        'name' => $name,
                        'status' => $status,
                        'priority' => $priority,
                        'budget' => $budget
                    ]);
                }

                if (!$isUpdate) {
                    queue_project_for_department_head($db, $savedId);

                }
                $okMessage = $isUpdate ? 'Project has been updated successfully.' : 'Project has been added successfully.';
                respond_project_registration(true, $okMessage, ['project_id' => $savedId]);
            } else {
                $debugError = build_db_debug_error($db, 'Failed to save project', $stmt->error);
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                respond_project_registration(false, 'Project already exists. Please try again with a different Project Code.');
            } else {
                $debugError = build_db_debug_error($db, 'Failed to save project (exception)', $e->getMessage());
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
        }
        if ($stmt) $stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'delete_project') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM projects WHERE id=?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            if (function_exists('rbac_audit')) {
                rbac_audit('project.delete', 'project', $id, []);
            }
            respond_project_registration(true, 'Project deleted successfully');
        } else {
            respond_project_registration(false, 'Failed to delete project: ' . $db->error);
        }
        $stmt->close();
        exit;
    }
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $orderBy = $projectsHasCreatedAt ? 'created_at DESC' : 'id DESC';
    $result = $db->query("SELECT * FROM projects ORDER BY {$orderBy}");
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $result->free();
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
    <title>Project Registration - LGU IPMS</title>
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
    <link rel="stylesheet" href="../assets/css/form-redesign-base.css">
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
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
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
            <h1>Project Registration</h1>
            <p>Create new infrastructure projects</p>
        </div>

        <div class="recent-projects">
            <h3>New Project Form</h3>

            <form id="projectForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_project">
                <!-- Basic project details -->
                <fieldset>
                    <legend>Basic Project Details</legend>
                    <label for="projCode">Project Code / Reference ID</label>
                    <input type="text" id="projCode" name="code" required>

                    <label for="projName">Project Name</label>
                    <input type="text" id="projName" name="name" required>

                    <label for="projType">Project Type</label>
                    <select id="projType" name="type" required>
                        <option value="">-- Select --</option>
                        <option>New</option>
                        <option>Rehabilitation</option>
                        <option>Expansion</option>
                        <option>Maintenance</option>
                        <option>Infrastructure</option>
                    </select>

                    <label for="projSector">Sector</label>
                    <select id="projSector" name="sector" required>
                        <option value="">-- Select --</option>
                        <option>Road</option>
                        <option>Drainage</option>
                        <option>Building</option>
                        <option>Water</option>
                        <option>Sanitation</option>
                        <option>Community Development</option>
                        <option>Health</option>
                        <option>Public Works</option>
                        <option>Transportation</option>
                        <option>Other</option>
                    </select>

                    <label for="projDescription">Project Description / Objective</label>
                    <textarea id="projDescription" name="description" rows="3"></textarea>

                    <label for="projPriority">Priority Level</label>
                    <select id="projPriority" name="priority">
                        <option>Crucial</option>
                        <option>High</option>
                        <option selected>Medium</option>
                        <option>Low</option>
                    </select>
                </fieldset>

                <!-- Location -->
                <fieldset>
                    <legend>Location</legend>
                    <label for="province">Province / City / Municipality</label>
                    <input type="text" id="province" name="province" required>

                    <label for="barangay">Barangay(s)</label>
                    <input type="text" id="barangay" name="barangay">

                    <label for="projLocation">Exact Site / Address</label>
                    <input type="text" id="projLocation" name="location" required>
                </fieldset>

                <!-- Schedule -->
                <fieldset>
                    <legend>Schedule</legend>
                    <label for="startDate">Estimated Start Date</label>
                    <input type="date" id="startDate" name="start_date">

                    <label for="endDate">Estimated End Date</label>
                    <input type="date" id="endDate" name="end_date">

                    <label for="projDuration">Estimated Duration (months)</label>
                    <input type="number" id="projDuration" name="duration_months" min="0" required>
                </fieldset>

                <!-- Budget -->
                <fieldset>
                    <legend>Budget</legend>
                    <label for="projBudget">Total Estimated Cost</label>
                    <input type="number" id="projBudget" name="budget" min="0" step="0.01" placeholder="Set by Department Head after approval" disabled>
                    <small>Budget is intentionally left blank during registration and will be set after Department Head approval.</small>
                </fieldset>

                <!-- Status -->
                <fieldset>
                    <legend>Status</legend>
                    <label for="status">Approval Status</label>
                    <select id="status" name="status">
                        <option>Draft</option>
                        <option>For Approval</option>
                        <option>Approved</option>
                        <option>On-hold</option>
                        <option>Cancelled</option>
                    </select>
                </fieldset>

                <div class="ac-9374e842">
                    <button type="submit" id="submitBtn">
                        Create Project
                    </button>
                    <button type="button" id="resetBtn">
                        Reset
                    </button>
                </div>
            </form>

            <div id="formMessage" class="ac-133c5402"></div>
        </div>
    </section>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-project-registration.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-project-registration.js'); ?>"></script>
</body>
</html>























