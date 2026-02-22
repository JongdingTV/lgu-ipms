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
rbac_require_roles(['admin','department_admin','super_admin']);
$rbacAction = strtolower(trim((string)($_REQUEST['action'] ?? '')));
rbac_require_action_roles(
    $rbacAction,
    [
        'delete_project' => ['admin', 'super_admin'],
        'update_project' => ['admin', 'department_admin', 'super_admin'],
        'get_project' => ['admin', 'department_admin', 'super_admin'],
        'load_projects' => ['admin', 'department_admin', 'super_admin'],
        'load_project_timeline' => ['admin', 'department_admin', 'super_admin'],
    ],
    ['admin', 'department_admin', 'super_admin']
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
function load_projects_data(mysqli $db): array
{
    $queries = [
        "SELECT * FROM projects ORDER BY created_at DESC",
        "SELECT * FROM projects ORDER BY id DESC",
        "SELECT * FROM projects",
    ];

    $result = null;
    $lastError = '';
    foreach ($queries as $query) {
        $result = $db->query($query);
        if ($result) {
            break;
        }
        $lastError = $db->error;
    }

    if (!$result) {
        return ['projects' => [], 'error' => 'Failed to load projects: ' . $lastError];
    }

    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    $result->free();

    return ['projects' => $projects, 'error' => null];
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $data = load_projects_data($db);
    if (!empty($data['error'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $data['error']]);
        exit;
    }

    echo json_encode($data['projects']);
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
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
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
                        placeholder="Ã°Å¸â€Â Search projects by code, name or location..." 
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
                    <button id="exportCsv" class="ac-1974716d">Ã°Å¸â€œÂ¥ Export CSV</button>
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

    <style>
        .edit-project-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-in-out;
        }

        .edit-project-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .edit-project-modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-in-out;
        }

        .edit-project-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .edit-project-modal-header h2 {
            margin: 0;
            color: #1e3a8a;
            font-size: 20px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: #000;
        }

        .edit-project-modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #325071;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d4e2f2;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3762c8;
            box-shadow: 0 0 0 3px rgba(55, 98, 200, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .edit-project-modal-content {
                width: 95%;
            }
        }

        .edit-project-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 16px 20px;
            border-top: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .btn-cancel,
        .btn-save {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background-color: #e2e8f0;
            color: #325071;
        }

        .btn-cancel:hover {
            background-color: #cbd5e1;
        }

        .btn-save {
            background: linear-gradient(135deg, #1e3a8a 0%, #2c5282 100%);
            color: white;
        }

        .btn-save:hover {
            box-shadow: 0 4px 12px rgba(30, 58, 130, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .delete-confirm-modal {
            display: none;
            position: fixed;
            z-index: 2100;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .delete-confirm-modal.show {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }

        .delete-confirm-content {
            width: 100%;
            max-width: 480px;
            background: linear-gradient(180deg, #fff7f7 0%, #ffffff 100%);
            border: 1px solid #fecaca;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.32);
            overflow: hidden;
            transform: translateY(8px);
            animation: deleteModalIn 0.2s ease-out forwards;
        }

        .delete-confirm-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 20px 10px;
        }

        .delete-confirm-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .delete-confirm-header h2 {
            margin: 0;
            color: #7f1d1d;
            font-size: 20px;
        }

        .delete-confirm-body {
            padding: 0 20px 14px;
            color: #334155;
        }

        .delete-confirm-body p {
            margin: 0 0 10px;
            line-height: 1.5;
        }

        .delete-confirm-project {
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #991b1b;
            font-weight: 600;
            word-break: break-word;
        }

        .delete-confirm-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 20px 18px;
            border-top: 1px solid #fee2e2;
            background: #fff;
        }

        #deleteConfirmProceed {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            border: 1px solid #b91c1c;
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.28);
        }

        #deleteConfirmProceed:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(185, 28, 28, 0.35);
        }

        #deleteConfirmProceed:active {
            transform: translateY(0);
            box-shadow: 0 4px 10px rgba(185, 28, 28, 0.3);
        }

        #deleteConfirmProceed:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(254, 202, 202, 0.95), 0 10px 22px rgba(185, 28, 28, 0.35);
        }

        @keyframes deleteModalIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .timeline-project-name {
            margin: 0 0 12px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #d4e2f2;
            border-radius: 10px;
            color: #1e3a8a;
            font-weight: 600;
        }

        .timeline-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(120px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }

        .timeline-summary-card {
            border: 1px solid #dbe7f4;
            border-radius: 10px;
            background: #f8fbff;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-height: 58px;
        }

        .timeline-summary-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .timeline-summary-value {
            font-size: 14px;
            color: #1e3a8a;
            font-weight: 700;
            line-height: 1.2;
            word-break: break-word;
        }

        .timeline-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .timeline-toolbar label {
            font-size: 13px;
            font-weight: 600;
            color: #325071;
        }

        .timeline-range {
            min-width: 170px;
            padding: 8px 10px;
            border: 1px solid #d4e2f2;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #1e3a8a;
            background: #fff;
        }

        .timeline-search {
            flex: 1;
            min-width: 180px;
            padding: 8px 10px;
            border: 1px solid #d4e2f2;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #1e3a8a;
            background: #fff;
        }

        .timeline-export-btn {
            margin-left: auto;
            padding: 8px 12px;
            font-size: 13px;
            line-height: 1.1;
        }

        .timeline-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #325071;
            user-select: none;
            white-space: nowrap;
        }

        .timeline-count {
            margin: 0 0 10px;
            font-size: 12px;
            color: #64748b;
        }

        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 420px;
            overflow-y: auto;
            padding-right: 6px;
        }

        .timeline-item {
            border: 1px solid #dbe7f4;
            border-radius: 10px;
            padding: 10px 12px;
            background: #ffffff;
        }

        .timeline-item .status-badge {
            font-size: 12px;
            padding: 3px 8px;
        }

        .timeline-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }

        .timeline-item-status {
            font-weight: 700;
            color: #1e3a8a;
            font-size: 14px;
        }

        .timeline-item-date {
            color: #64748b;
            font-size: 12px;
            white-space: nowrap;
        }

        .timeline-item-meta {
            color: #334155;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .timeline-item-transition {
            margin-bottom: 6px;
            font-size: 12px;
            color: #1d4ed8;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .timeline-item-notes {
            color: #475569;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .timeline-empty {
            padding: 18px 12px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #c9d8ee;
            border-radius: 10px;
            background: #f8fbff;
        }

        .timeline-loadmore-wrap {
            padding-top: 12px;
            display: flex;
            justify-content: center;
        }

        .timeline-loadmore-btn {
            min-width: 160px;
        }

        @media (max-width: 540px) {
            .timeline-summary {
                grid-template-columns: 1fr 1fr;
            }

            .timeline-export-btn {
                margin-left: 0;
                width: 100%;
            }

            .timeline-search {
                width: 100%;
                min-width: 0;
            }
        }
    </style>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script>
    (function () {
        const table = document.getElementById('projectsTable');
        const tbody = table ? table.querySelector('tbody') : null;
        const msg = document.getElementById('formMessage');
        const searchInput = document.getElementById('searchProjects');
        const statusFilter = document.getElementById('filterStatus');
        const exportCsvBtn = document.getElementById('exportCsv');

        const editModal = document.getElementById('editProjectModal');
        const editForm = document.getElementById('editProjectForm');
        const editSaveBtn = document.querySelector('.btn-save');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const deleteConfirmProjectName = document.getElementById('deleteConfirmProjectName');
        const deleteConfirmCancel = document.getElementById('deleteConfirmCancel');
        const deleteConfirmProceed = document.getElementById('deleteConfirmProceed');
        const timelineModal = document.getElementById('projectTimelineModal');
        const timelineList = document.getElementById('timelineList');
        const timelineProjectName = document.getElementById('timelineProjectName');
        const closeTimelineModalBtn = document.getElementById('closeTimelineModalBtn');
        const timelineCloseFooterBtn = document.getElementById('timelineCloseFooterBtn');
        const timelineRange = document.getElementById('timelineRange');
        const timelineExportCsvBtn = document.getElementById('timelineExportCsvBtn');
        const timelineSearch = document.getElementById('timelineSearch');
        const timelineCount = document.getElementById('timelineCount');
        const timelineLoadMoreBtn = document.getElementById('timelineLoadMoreBtn');
        const timelineShowDuplicates = document.getElementById('timelineShowDuplicates');
        const timelineLatestStatus = document.getElementById('timelineLatestStatus');
        const timelineTotalChanges = document.getElementById('timelineTotalChanges');
        const timelineMostFrequent = document.getElementById('timelineMostFrequent');
        const timelineReviewers = document.getElementById('timelineReviewers');

        if (!table || !tbody) return;

        let allProjects = [];
        let pendingDeleteId = null;
        let currentTimelineEntries = [];
        let currentTimelineProjectId = null;
        let currentTimelineProjectName = '';
        let currentFilteredTimelineEntries = [];
        let timelineVisibleCount = 30;
        const timelinePageSize = 30;

        function showMsg(text, ok) {
            if (!msg) return;
            msg.textContent = text || '';
            msg.style.color = ok ? '#16a34a' : '#dc2626';
            msg.style.display = 'block';
            setTimeout(() => { msg.style.display = 'none'; }, 3000);
        }

        function apiUrls(suffix) {
            const urls = [];
            if (typeof window.getApiUrl === 'function') {
                urls.push(getApiUrl('admin/registered_projects.php' + suffix));
            }
            urls.push('registered_projects.php' + suffix);
            urls.push('/admin/registered_projects.php' + suffix);
            return urls;
        }

        function fetchJsonWithFallback(urls, options) {
            const tryFetch = (idx) => {
                if (idx >= urls.length) throw new Error('All API endpoints failed');
                return fetch(urls[idx], options)
                    .then((res) => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .catch(() => tryFetch(idx + 1));
            };
            return tryFetch(0);
        }

        function esc(v) {
            return String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function toKey(v) {
            return String(v || 'draft').toLowerCase().replace(/\s+/g, '');
        }

        function renderProjects(projects) {
            tbody.innerHTML = '';
            if (!projects.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#6b7280;">No projects found.</td></tr>';
                return;
            }

            projects.forEach((p) => {
                const row = document.createElement('tr');
                const createdDate = p.created_at ? new Date(p.created_at).toLocaleDateString() : 'N/A';
                const priority = p.priority || 'Medium';
                const priorityMap = { crucial: 100, high: 75, medium: 50, low: 25 };
                const priorityPct = priorityMap[String(priority).toLowerCase()] || 50;
                const status = p.status || 'Draft';
                row.innerHTML = `
                    <td>${esc(p.code)}</td>
                    <td>${esc(p.name)}</td>
                    <td>${esc(p.type)}</td>
                    <td>${esc(p.sector)}</td>
                    <td><span class="priority-badge ${toKey(priority)}">${esc(priority)} ${priorityPct}%</span></td>
                    <td><span class="status-badge ${toKey(status)}">${esc(status)}</span></td>
                    <td>${esc(createdDate)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-timeline" data-id="${esc(p.id)}" type="button">Timeline</button>
                            <button class="btn-edit" data-id="${esc(p.id)}" type="button">Edit</button>
                            <button class="btn-delete" data-id="${esc(p.id)}" type="button">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function renderTimelineEntries(entries) {
            if (!timelineList) return;
            if (!entries.length) {
                timelineList.innerHTML = '<div class="timeline-empty">No status history yet.</div>';
                if (timelineCount) timelineCount.textContent = '0 entries';
                if (timelineLoadMoreBtn) timelineLoadMoreBtn.style.display = 'none';
                updateTimelineSummary(entries);
                return;
            }

            const visible = entries.slice(0, timelineVisibleCount);
            timelineList.innerHTML = visible.map((entry, index) => {
                const changedAt = entry.changed_at ? new Date(entry.changed_at).toLocaleString() : 'N/A';
                const badgeClass = toKey(entry.status || 'draft');
                const previousEntry = entries[index + 1] || null;
                const currentStatus = String(entry.status || 'Unknown');
                const previousStatus = String(previousEntry ? (previousEntry.status || 'Unknown') : 'Initial');
                const hasTransition = previousEntry && previousStatus !== currentStatus;
                const transitionText = hasTransition
                    ? `${previousStatus} -> ${currentStatus}`
                    : `Set as ${currentStatus}`;
                return `
                    <div class="timeline-item">
                        <div class="timeline-item-head">
                            <span class="status-badge ${esc(badgeClass)}">${esc(entry.status || 'Unknown')}</span>
                            <span class="timeline-item-date">${esc(changedAt)}</span>
                        </div>
                        <div class="timeline-item-transition">${esc(transitionText)}</div>
                        <div class="timeline-item-meta">By: ${esc(entry.changed_by || 'System')}</div>
                        <div class="timeline-item-notes">${esc(entry.notes || 'No notes provided.')}</div>
                    </div>
                `;
            }).join('');

            if (timelineCount) {
                timelineCount.textContent = `${visible.length} of ${entries.length} entries`;
            }

            if (timelineLoadMoreBtn) {
                timelineLoadMoreBtn.style.display = visible.length < entries.length ? 'inline-flex' : 'none';
            }
            updateTimelineSummary(entries);
        }

        function updateTimelineSummary(entries) {
            const list = Array.isArray(entries) ? entries : [];
            if (timelineTotalChanges) timelineTotalChanges.textContent = String(list.length);

            if (timelineLatestStatus) {
                timelineLatestStatus.textContent = list.length ? String(list[0].status || 'Unknown') : '-';
            }

            if (timelineReviewers) {
                const reviewers = new Set(
                    list
                        .map((x) => String(x.changed_by || '').trim())
                        .filter((x) => x.length > 0)
                );
                timelineReviewers.textContent = String(reviewers.size);
            }

            if (timelineMostFrequent) {
                if (!list.length) {
                    timelineMostFrequent.textContent = '-';
                } else {
                    const freq = {};
                    list.forEach((x) => {
                        const key = String(x.status || 'Unknown').trim() || 'Unknown';
                        freq[key] = (freq[key] || 0) + 1;
                    });
                    let topStatus = '';
                    let topCount = -1;
                    Object.keys(freq).forEach((k) => {
                        if (freq[k] > topCount) {
                            topStatus = k;
                            topCount = freq[k];
                        }
                    });
                    timelineMostFrequent.textContent = topStatus ? `${topStatus} (${topCount})` : '-';
                }
            }
        }

        function filterTimelineEntries(entries, rangeValue) {
            if (!Array.isArray(entries)) return [];
            if (!rangeValue || rangeValue === 'all') return entries.slice();

            const days = Number(rangeValue);
            if (!Number.isFinite(days) || days <= 0) return entries.slice();

            const now = Date.now();
            const cutoff = now - (days * 24 * 60 * 60 * 1000);
            return entries.filter((entry) => {
                const dt = Date.parse(entry.changed_at || '');
                return Number.isFinite(dt) && dt >= cutoff;
            });
        }

        function removeConsecutiveDuplicateStatuses(entries) {
            if (!Array.isArray(entries) || entries.length < 2) return Array.isArray(entries) ? entries.slice() : [];
            const out = [];
            let lastStatus = null;
            entries.forEach((entry) => {
                const statusKey = String(entry.status || '').trim().toLowerCase();
                if (statusKey !== lastStatus) {
                    out.push(entry);
                    lastStatus = statusKey;
                }
            });
            return out;
        }

        function applyTimelineFilter() {
            const rangeValue = timelineRange ? timelineRange.value : 'all';
            const searchTerm = (timelineSearch ? timelineSearch.value : '').trim().toLowerCase();
            const ranged = filterTimelineEntries(currentTimelineEntries, rangeValue);
            const searched = !searchTerm ? ranged : ranged.filter((entry) => {
                const haystack = `${entry.status || ''} ${entry.notes || ''} ${entry.changed_by || ''}`.toLowerCase();
                return haystack.includes(searchTerm);
            });
            const showDuplicates = timelineShowDuplicates ? !!timelineShowDuplicates.checked : false;
            currentFilteredTimelineEntries = showDuplicates ? searched : removeConsecutiveDuplicateStatuses(searched);
            renderTimelineEntries(currentFilteredTimelineEntries);
        }

        function closeTimelineModal() {
            if (!timelineModal) return;
            timelineModal.classList.remove('show');
            timelineModal.setAttribute('aria-hidden', 'true');
        }

        function openTimelineModal(projectId, projectName) {
            if (!timelineModal || !timelineList) return;

            currentTimelineProjectId = Number(projectId) || null;
            currentTimelineProjectName = projectName || '';
            currentTimelineEntries = [];
            currentFilteredTimelineEntries = [];
            timelineVisibleCount = timelinePageSize;
            if (timelineRange) timelineRange.value = 'all';
            if (timelineSearch) timelineSearch.value = '';
            if (timelineShowDuplicates) timelineShowDuplicates.checked = false;
            timelineProjectName.textContent = projectName ? ('Project: ' + projectName) : 'Project timeline';
            timelineList.innerHTML = '<div class="timeline-empty">Loading timeline...</div>';
            timelineModal.classList.add('show');
            timelineModal.setAttribute('aria-hidden', 'false');

            const nonce = Date.now();
            const url = '?action=load_project_timeline&project_id=' + encodeURIComponent(projectId) + '&_=' + nonce;
            fetchJsonWithFallback(apiUrls(url), { credentials: 'same-origin' })
                .then((data) => {
                    if (!data || data.success === false) {
                        throw new Error((data && data.message) ? data.message : 'Failed timeline request');
                    }
                    currentTimelineEntries = Array.isArray(data.history) ? data.history : [];
                    applyTimelineFilter();
                })
                .catch(() => {
                    timelineList.innerHTML = '<div class="timeline-empty">Unable to load status timeline.</div>';
                });
        }

        function exportCurrentTimelineCsv() {
            if (!currentTimelineEntries.length) {
                showMsg('No timeline data to export.', false);
                return;
            }
            const rowsData = currentFilteredTimelineEntries.length ? currentFilteredTimelineEntries : [];
            if (!rowsData.length) {
                showMsg('No timeline entries for selected range.', false);
                return;
            }

            const headers = ['Project ID', 'Project Name', 'Status', 'Changed At', 'Changed By', 'Notes'];
            const rows = rowsData.map((item) => ([
                currentTimelineProjectId || '',
                currentTimelineProjectName || '',
                item.status || '',
                item.changed_at || '',
                item.changed_by || '',
                item.notes || ''
            ].map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')));

            const csv = [headers.join(','), ...rows].join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const dateTag = new Date().toISOString().slice(0, 10);
            link.href = URL.createObjectURL(blob);
            link.download = `project_timeline_${currentTimelineProjectId || 'unknown'}_${dateTag}.csv`;
            link.click();
        }

        function fillEditModal(project) {
            if (!editModal || !editForm) return;
            const setVal = (selector, value) => {
                const el = document.querySelector(selector);
                if (el) el.value = value || '';
            };
            setVal('#projectId', project.id);
            setVal('#projectCode', project.code);
            setVal('#projectName', project.name);
            setVal('#projectType', project.type);
            setVal('#projectSector', project.sector);
            setVal('#projectPriority', project.priority || 'Medium');
            setVal('#projectStatus', project.status || 'Draft');
            setVal('#projectDescription', project.description);
            editModal.classList.add('show');
        }

        function openEditProjectModal(id) {
            const project = allProjects.find((x) => Number(x.id) === Number(id));
            if (project) {
                fillEditModal(project);
                return;
            }

            const nonce = Date.now();
            const urls = apiUrls('?action=get_project&id=' + encodeURIComponent(id) + '&_=' + nonce);
            fetchJsonWithFallback(urls, { credentials: 'same-origin' })
                .then((data) => {
                    if (!data || data.success === false) throw new Error('Project not found');
                    fillEditModal(data);
                })
                .catch(() => showMsg('Project data not found. Please refresh.', false));
        }

        function closeEditProjectModal() {
            if (editModal) editModal.classList.remove('show');
        }

        function saveEditedProject() {
            if (!editForm) return;
            const formData = new FormData(editForm);
            formData.append('action', 'update_project');

            if (editSaveBtn) {
                editSaveBtn.disabled = true;
                editSaveBtn.textContent = 'Saving...';
            }

            fetchJsonWithFallback(apiUrls(''), { method: 'POST', body: formData })
                .then((data) => {
                    showMsg(data.message || (data.success ? 'Project updated.' : 'Update failed.'), !!data.success);
                    if (data.success) {
                        closeEditProjectModal();
                        loadProjects();
                    }
                })
                .catch(() => showMsg('Error saving project.', false))
                .finally(() => {
                    if (editSaveBtn) {
                        editSaveBtn.disabled = false;
                        editSaveBtn.textContent = 'Save Changes';
                    }
                });
        }

        function performDelete(id) {
            fetchJsonWithFallback(apiUrls(''), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_project&id=' + encodeURIComponent(id)
            })
            .then((data) => {
                showMsg(data.message || (data.success ? 'Project deleted.' : 'Delete failed.'), !!data.success);
                if (data.success) loadProjects();
            })
            .catch(() => showMsg('Error deleting project.', false));
        }

        function confirmDeleteProject(id, projectName) {
            const safeName = projectName || 'this project';

            if (!deleteConfirmModal || !deleteConfirmProjectName) {
                if (window.confirm(`Delete "${safeName}" permanently? This cannot be undone.`)) {
                    performDelete(id);
                }
                return;
            }

            pendingDeleteId = id;
            deleteConfirmProjectName.textContent = safeName;
            deleteConfirmModal.classList.add('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'false');
            if (deleteConfirmProceed) deleteConfirmProceed.focus();
        }

        function closeDeleteConfirmModal() {
            pendingDeleteId = null;
            if (!deleteConfirmModal) return;
            deleteConfirmModal.classList.remove('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'true');
        }

        function filterProjects() {
            const searchTerm = (searchInput ? searchInput.value : '').toLowerCase();
            const statusTerm = statusFilter ? statusFilter.value : '';
            const filtered = allProjects.filter((p) => {
                const matchesSearch = !searchTerm ||
                    (p.code || '').toLowerCase().includes(searchTerm) ||
                    (p.name || '').toLowerCase().includes(searchTerm) ||
                    (p.sector || '').toLowerCase().includes(searchTerm);
                const matchesStatus = !statusTerm || p.status === statusTerm;
                return matchesSearch && matchesStatus;
            });
            renderProjects(filtered);
        }

        function loadProjects() {
            const nonce = Date.now();
            fetchJsonWithFallback(apiUrls('?action=load_projects&_=' + nonce), { credentials: 'same-origin' })
                .then((projects) => {
                    if (!Array.isArray(projects)) throw new Error('Invalid payload');
                    allProjects = projects;
                    renderProjects(allProjects);
                })
                .catch(() => {
                    if (!tbody.querySelector('tr')) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error loading projects. Check console.</td></tr>';
                    }
                });
        }

        table.addEventListener('click', function (event) {
            const timelineBtn = event.target.closest('.btn-timeline');
            if (timelineBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const row = timelineBtn.closest('tr');
                const projectName = row ? (row.querySelector('td:nth-child(2)')?.textContent || '').trim() : '';
                openTimelineModal(timelineBtn.dataset.id, projectName);
                return;
            }

            const editBtn = event.target.closest('.btn-edit');
            if (editBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openEditProjectModal(editBtn.dataset.id);
                return;
            }

            const deleteBtn = event.target.closest('.btn-delete');
            if (deleteBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const row = deleteBtn.closest('tr');
                const projectName = row ? (row.querySelector('td:nth-child(2)')?.textContent || '').trim() : '';
                confirmDeleteProject(deleteBtn.dataset.id, projectName);
            }
        }, true);

        if (searchInput) searchInput.addEventListener('input', filterProjects);
        if (statusFilter) statusFilter.addEventListener('change', filterProjects);

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', function () {
                if (!allProjects.length) {
                    alert('No projects to export');
                    return;
                }
                const keys = ['code', 'name', 'type', 'sector', 'priority', 'status'];
                const headers = keys.map((k) => k.charAt(0).toUpperCase() + k.slice(1)).join(',');
                const rows = allProjects.map((p) =>
                    keys.map((k) => `"${String(p[k] || '').replace(/"/g, '""')}"`).join(',')
                );
                const csv = [headers, ...rows].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `projects_${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
            });
        }

        window.openEditModal = openEditProjectModal;
        window.closeEditModal = closeEditProjectModal;
        window.saveProject = saveEditedProject;
        window.confirmDeleteProject = confirmDeleteProject;

        if (editModal) {
            window.addEventListener('click', function (event) {
                if (event.target === editModal) closeEditProjectModal();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && editModal && editModal.classList.contains('show')) {
                closeEditProjectModal();
            }
            if (event.key === 'Escape' && deleteConfirmModal && deleteConfirmModal.classList.contains('show')) {
                closeDeleteConfirmModal();
            }
            if (event.key === 'Escape' && timelineModal && timelineModal.classList.contains('show')) {
                closeTimelineModal();
            }
        });

        if (deleteConfirmCancel) {
            deleteConfirmCancel.addEventListener('click', closeDeleteConfirmModal);
        }

        if (deleteConfirmProceed) {
            deleteConfirmProceed.addEventListener('click', function () {
                if (!pendingDeleteId) return;
                const idToDelete = pendingDeleteId;
                closeDeleteConfirmModal();
                performDelete(idToDelete);
            });
        }

        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('click', function (event) {
                if (event.target === deleteConfirmModal) {
                    closeDeleteConfirmModal();
                }
            });
        }

        if (closeTimelineModalBtn) {
            closeTimelineModalBtn.addEventListener('click', closeTimelineModal);
        }
        if (timelineCloseFooterBtn) {
            timelineCloseFooterBtn.addEventListener('click', closeTimelineModal);
        }
        if (timelineRange) {
            timelineRange.addEventListener('change', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineSearch) {
            timelineSearch.addEventListener('input', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineShowDuplicates) {
            timelineShowDuplicates.addEventListener('change', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineExportCsvBtn) {
            timelineExportCsvBtn.addEventListener('click', exportCurrentTimelineCsv);
        }
        if (timelineLoadMoreBtn) {
            timelineLoadMoreBtn.addEventListener('click', function () {
                timelineVisibleCount += timelinePageSize;
                renderTimelineEntries(currentFilteredTimelineEntries);
            });
        }
        if (timelineModal) {
            timelineModal.addEventListener('click', function (event) {
                if (event.target === timelineModal) {
                    closeTimelineModal();
                }
            });
        }

        loadProjects();
    })();
    </script>
</body>
</html>























