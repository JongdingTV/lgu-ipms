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
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    error_reporting(E_ALL);
    
    if ($_POST['action'] === 'save_project') {
        // Validate required fields
        if (empty($_POST['code']) || empty($_POST['name'])) {
            echo json_encode(['success' => false, 'message' => 'Project Code and Name are required']);
            exit;
        }
        
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $type = isset($_POST['type']) ? trim($_POST['type']) : '';
        $sector = isset($_POST['sector']) ? trim($_POST['sector']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
        $province = isset($_POST['province']) ? trim($_POST['province']) : '';
        $barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $duration_months = !empty($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $project_manager = isset($_POST['project_manager']) ? trim($_POST['project_manager']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Draft';
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing project
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE projects SET code=?, name=?, type=?, sector=?, description=?, priority=?, province=?, barangay=?, location=?, start_date=?, end_date=?, duration_months=?, budget=?, project_manager=?, status=? WHERE id=?");
            $stmt->bind_param('sssssssssssidssi', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status, $id);
        } else {
            // Insert new project and set created_at explicitly
            $stmt = $db->prepare("INSERT INTO projects (code, name, type, sector, description, priority, province, barangay, location, start_date, end_date, duration_months, budget, project_manager, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssssssssssidss', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save project: ' . $stmt->error]);
        }
        if ($stmt) $stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'delete_project') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM projects WHERE id=?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete project: ' . $db->error]);
        }
        $stmt->close();
        exit;
    }
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $db->query("SELECT * FROM projects ORDER BY created_at DESC");
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
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
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
                    <a href="registered_projects.php" class="nav-submenu-item">
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
                    <a href="contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">➕</span>
                        <span>Add Contractor</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">📋</span>
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
            <h1>Project Registration</h1>
            <p>Create new infrastructure projects</p>
        </div>

        <div class="recent-projects">
            <h3>New Project Form</h3>

            <form id="projectForm" enctype="multipart/form-data">
                <!-- Basic project details -->
                <fieldset>
                    <legend>Basic Project Details</legend>
                    <label for="projCode">Project Code / Reference ID</label>
                    <input type="text" id="projCode" required>

                    <label for="projName">Project Name</label>
                    <input type="text" id="projName" required>

                    <label for="projType">Project Type</label>
                    <select id="projType" required>
                        <option value="">-- Select --</option>
                        <option>New</option>
                        <option>Rehabilitation</option>
                        <option>Expansion</option>
                        <option>Maintenance</option>
                    </select>

                    <label for="projSector">Sector</label>
                    <select id="projSector" required>
                        <option value="">-- Select --</option>
                        <option>Road</option>
                        <option>Drainage</option>
                        <option>Building</option>
                        <option>Water</option>
                        <option>Sanitation</option>
                        <option>Other</option>
                    </select>

                    <label for="projDescription">Project Description / Objective</label>
                    <textarea id="projDescription" rows="3"></textarea>

                    <label for="projPriority">Priority Level</label>
                    <select id="projPriority">
                        <option>High</option>
                        <option>Medium</option>
                        <option>Low</option>
                    </select>
                </fieldset>

                <!-- Location -->
                <fieldset>
                    <legend>Location</legend>
                    <label for="province">Province / City / Municipality</label>
                    <input type="text" id="province" required>

                    <label for="barangay">Barangay(s)</label>
                    <input type="text" id="barangay">

                    <label for="projLocation">Exact Site / Address</label>
                    <input type="text" id="projLocation" required>
                </fieldset>

                <!-- Schedule -->
                <fieldset>
                    <legend>Schedule</legend>
                    <label for="startDate">Estimated Start Date</label>
                    <input type="date" id="startDate">

                    <label for="endDate">Estimated End Date</label>
                    <input type="date" id="endDate">

                    <label for="projDuration">Estimated Duration (months)</label>
                    <input type="number" id="projDuration" min="0" required>
                </fieldset>

                <!-- Budget -->
                <fieldset>
                    <legend>Budget</legend>
                    <label for="projBudget">Total Estimated Cost</label>
                    <input type="number" id="projBudget" min="0" step="0.01" required>
                </fieldset>

                <!-- Implementation -->
                <fieldset>
                    <legend>Implementation</legend>
                    <label for="projManager">Project Manager / Engineer In-Charge</label>
                    <input type="text" id="projManager" placeholder="Name">
                </fieldset>

                <!-- Status -->
                <fieldset>
                    <legend>Status</legend>
                    <label for="status">Approval Status</label>
                    <select id="status">
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

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/component-utilities.js"></script>
</body>
</html>

















