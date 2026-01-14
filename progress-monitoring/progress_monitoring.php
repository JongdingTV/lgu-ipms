<?php
// Database connection
require '../database.php';
require '../config-path.php';
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle API requests first (before rendering HTML)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    // Create contractor_project_assignments table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    
    // Simple query first - use safe column selection
    $result = $conn->query("SELECT id, code, name, description, location, province, sector, budget, status, project_manager, start_date, end_date, duration_months, created_at FROM projects ORDER BY created_at DESC LIMIT 500");
    
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add a default progress value (can be 0-100)
            $row['progress'] = isset($row['progress']) ? $row['progress'] : 0;
            
            // Get assigned contractors for this project
            $contractorsQuery = $conn->query("
                SELECT c.id, c.company, c.rating 
                FROM contractors c
                INNER JOIN contractor_project_assignments cpa ON c.id = cpa.contractor_id
                WHERE cpa.project_id = " . intval($row['id'])
            );
            
            $contractors = [];
            if ($contractorsQuery) {
                while ($contractor = $contractorsQuery->fetch_assoc()) {
                    $contractors[] = $contractor;
                }
                $contractorsQuery->free();
            }
            
            $row['assigned_contractors'] = $contractors;
            $projects[] = $row;
        }
        $result->free();
    }
    
    echo json_encode($projects);
    exit;
}

$conn->close();
?>
<!doctype html>
<html>
<head>
        <link rel="stylesheet" href="../assets/style.css" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - LGU IPMS</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo get_app_config_script(); ?>
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="../project-registration/project_registration.php"><img src="../project-registration/list.png" class="nav-icon">Project Registration   ▼</a>
            <a href="progress_monitoring.php" class="active"><img src="monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <a href="../contractors/contractors.php"><img src="../contractors/contractors.png" class="nav-icon">Contractors     ▼</a>
            <a href="../project-prioritization/project-prioritization.php"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome <?php echo isset($_SESSION['employee_name']) ? $_SESSION['employee_name'] : 'Admin'; ?></span>
            <a href="../index.php" class="nav-logout">Logout</a>
        </div>
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
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
                    <div class="stat-label">Assigned Contractors</div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="pm-controls-wrapper">
                <div class="pm-controls">
                    <div class="pm-left">
                        <label for="pmSearch">Search Projects</label>
                        <input id="pmSearch" type="search" placeholder="🔍 Search by code, name or location...">
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
                            <label for="pmContractorFilter">Has Contractors</label>
                            <select id="pmContractorFilter" title="Filter by contractors">
                                <option value="">All Projects</option>
                                <option value="assigned">With Contractors</option>
                                <option value="unassigned">No Contractors</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmSort">Sort</label>
                            <select id="pmSort" title="Sort">
                                <option value="createdAt_desc">Newest</option>
                                <option value="createdAt_asc">Oldest</option>
                                <option value="progress_desc">Progress (high → low)</option>
                                <option value="progress_asc">Progress (low → high)</option>
                            </select>
                        </div>

                        <button id="exportCsv" type="button" class="btn-export">📥 Export CSV</button>
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

                <div id="pmEmpty" class="pm-empty" style="display:none;">
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No projects match your filters</p>
                        <small>Try adjusting your search criteria</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js?v=1"></script>
    <script src="progress-monitoring.js?v=2"></script>
</body>
</html>
