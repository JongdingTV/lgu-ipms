<?php
// Import security functions
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

/**
 * Load projects using only columns available in the current schema.
 * This avoids silent failures when deployments have slight table differences.
 *
 * @return array{projects: array<int, array<string, mixed>>, error: ?string}
 */
function load_projects_data(mysqli $db): array
{
    $preferredColumns = ['id', 'code', 'name', 'type', 'sector', 'priority', 'status', 'description', 'created_at'];
    $availableColumns = [];

    $columnsResult = $db->query("SHOW COLUMNS FROM projects");
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            if (!empty($column['Field'])) {
                $availableColumns[] = $column['Field'];
            }
        }
        $columnsResult->free();
    }

    if (!empty($availableColumns)) {
        $selectedColumns = array_values(array_intersect($preferredColumns, $availableColumns));
    } else {
        // Fallback if SHOW COLUMNS is blocked by permissions.
        $selectedColumns = $preferredColumns;
    }

    $selectClause = !empty($selectedColumns) ? implode(', ', $selectedColumns) : '*';
    $orderClause = in_array('created_at', $selectedColumns, true) ? ' ORDER BY created_at DESC' : '';
    if ($orderClause === '' && in_array('id', $selectedColumns, true)) {
        $orderClause = ' ORDER BY id DESC';
    }

    $query = "SELECT {$selectClause} FROM projects{$orderClause}";
    $result = $db->query($query);

    if (!$result) {
        return ['projects' => [], 'error' => 'Failed to load projects: ' . $db->error];
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
        $stmt = $db->prepare("UPDATE projects SET name=?, code=?, type=?, sector=?, priority=?, status=?, description=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $code, $type, $sector, $priority, $status, $description, $id);
        
        if ($stmt->execute()) {
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
            
            <!-- Project Registration with Submenu -->
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle">
                    <img src="../assets/images/admin/list.png" class="nav-icon">Project Registration
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item">
                        <span class="submenu-icon">➕</span>
                        <span>New Project</span>
                    </a>
                    <a href="registered_projects.php" class="nav-submenu-item active">
                        <span class="submenu-icon">📋</span>
                        <span>Registered Projects</span>
                    </a>
                </div>
            </div>
            
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            
            <!-- Contractors with Submenu -->
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle">
                    <img src="../assets/images/admin/contractors.png" class="nav-icon">Contractors
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">➕</span>
                        <span>Add Contractor</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">👷</span>
                        <span>Registered Contractors</span>
                    </a>
                </div>
            </div>
            
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                </div>
            </div>
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
                        placeholder="🔍 Search projects by code, name or location..." 
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
                    <button id="exportCsv" class="ac-1974716d">📥 Export CSV</button>
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
                                        <td><span class="priority-badge <?php echo strtolower(str_replace(' ', '', $p['priority'] ?? 'medium')); ?>"><?php echo htmlspecialchars($p['priority'] ?? 'Medium', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><span class="status-badge <?php echo strtolower(str_replace(' ', '', $p['status'] ?? 'draft')); ?>"><?php echo htmlspecialchars($p['status'] ?? 'Draft', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo !empty($p['created_at']) ? date('n/j/Y', strtotime($p['created_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="action-buttons">
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

        if (!table || !tbody) return;

        let allProjects = [];
        let pendingDeleteId = null;

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
                const status = p.status || 'Draft';
                row.innerHTML = `
                    <td>${esc(p.code)}</td>
                    <td>${esc(p.name)}</td>
                    <td>${esc(p.type)}</td>
                    <td>${esc(p.sector)}</td>
                    <td><span class="priority-badge ${toKey(priority)}">${esc(priority)}</span></td>
                    <td><span class="status-badge ${toKey(status)}">${esc(status)}</span></td>
                    <td>${esc(createdDate)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-edit" data-id="${esc(p.id)}" type="button">Edit</button>
                            <button class="btn-delete" data-id="${esc(p.id)}" type="button">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
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

        loadProjects();
    })();
    </script>
</body>
</html>


















