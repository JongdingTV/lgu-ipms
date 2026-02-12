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

// Handle API requests first (before rendering HTML)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    // Create contractor_project_assignments table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    
    // Simple query first - support schemas with or without created_at.
    $hasCreatedAt = progress_projects_has_created_at($db);
    $selectCreatedAt = $hasCreatedAt ? ", created_at" : "";
    $orderBy = $hasCreatedAt ? "created_at DESC" : "id DESC";
    $result = $db->query("SELECT id, code, name, description, location, province, sector, budget, status, project_manager, start_date, end_date, duration_months{$selectCreatedAt} FROM projects ORDER BY {$orderBy} LIMIT 500");
    
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add a default progress value (can be 0-100)
            $row['progress'] = isset($row['progress']) ? $row['progress'] : 0;
            
            // Get assigned contractors for this project
            $contractorsQuery = $db->query("
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
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Contractor</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">&#128272;</span><span>Change Password</span></a>
                    <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">&#128274;</span><span>Security Logs</span></a>
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
                        <input id="pmSearch" type="search" placeholder="Search by code, name, or location...">
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
                                <option value="progress_desc">Progress (high to low)</option>
                                <option value="progress_asc">Progress (low to high)</option>
                            </select>
                        </div>

                        <button id="exportCsv" type="button" class="btn-export">Export CSV</button>
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

    <style>
        .main-content .dash-header {
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), rgba(14, 116, 144, 0) 44%), linear-gradient(145deg, #ffffff, #f7fbff);
            border: 1px solid #d9e7f7;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
            margin-bottom: 14px;
        }

        .main-content .dash-header h1 {
            margin: 0 0 4px;
            color: #12355b;
            font-size: 1.85rem;
            letter-spacing: 0.2px;
        }

        .main-content .dash-header p {
            margin: 0;
            color: #4d6480;
            font-weight: 500;
        }

        .main-content .pm-section.card {
            border-radius: 16px;
            border: 1px solid #d8e6f4;
            background: linear-gradient(165deg, #ffffff 0%, #f8fbff 72%);
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.1);
            padding: 16px;
        }

        .main-content .pm-stats-wrapper {
            display: grid;
            grid-template-columns: repeat(5, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .main-content .stat-box {
            border-radius: 14px;
            border: 1px solid #dae6f5;
            background: #ffffff;
            min-height: 88px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 14px 12px;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
            position: relative;
            overflow: hidden;
        }

        .main-content .stat-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background: #3b82f6;
            opacity: 0.85;
        }

        .main-content .stat-box.stat-total::before { background: #2563eb; }
        .main-content .stat-box.stat-approved::before { background: #16a34a; }
        .main-content .stat-box.stat-progress::before { background: #f59e0b; }
        .main-content .stat-box.stat-completed::before { background: #0ea5e9; }
        .main-content .stat-box.stat-contractors::before { background: #7c3aed; }

        .main-content .stat-number {
            font-size: 1.7rem;
            line-height: 1;
            font-weight: 700;
            color: #12355b;
            margin-bottom: 6px;
        }

        .main-content .stat-label {
            font-size: 0.8rem;
            color: #5a728f;
            font-weight: 600;
            letter-spacing: 0.35px;
            text-transform: uppercase;
        }

        .main-content .pm-controls-wrapper {
            position: sticky;
            top: 10px;
            z-index: 20;
            background: rgba(248, 251, 255, 0.92);
            backdrop-filter: blur(4px);
            border: 1px solid #dce8f6;
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 14px;
        }

        .main-content .pm-controls {
            gap: 12px;
        }

        .main-content .pm-left {
            min-width: 240px;
        }

        .main-content .pm-left label,
        .main-content .filter-group label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            color: #5f7691;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .main-content .pm-left input,
        .main-content .pm-controls select {
            border: 1px solid #cddced;
            background: #fff;
            border-radius: 10px;
            color: #1f3858;
            min-height: 38px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .main-content .pm-left input:focus,
        .main-content .pm-controls select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
        }

        .main-content .btn-export {
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid #bfd8f8;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1d4ed8;
            border-radius: 10px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .main-content .btn-export:hover {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            box-shadow: 0 8px 18px rgba(59, 130, 246, 0.22);
            transform: translateY(-1px);
        }

        .main-content .pm-content h3 {
            margin: 2px 0 12px;
            color: #1d3654;
            font-size: 1.02rem;
            letter-spacing: 0.2px;
        }

        .main-content .projects-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .main-content .project-card {
            border: 1px solid #d8e5f4;
            border-radius: 14px;
            background: linear-gradient(165deg, #ffffff, #f7fbff);
            padding: 14px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .main-content .project-card:hover {
            transform: translateY(-2px);
            border-color: #bfd6ef;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.14);
        }

        .main-content .project-card.risk-critical { border-left: 4px solid #dc2626; }
        .main-content .project-card.risk-high { border-left: 4px solid #f97316; }
        .main-content .project-card.risk-medium { border-left: 4px solid #f59e0b; }
        .main-content .project-card.risk-low { border-left: 4px solid #16a34a; }

        .main-content .project-title-section h4 {
            color: #14385c;
            margin-bottom: 8px;
        }

        .main-content .project-status {
            border-radius: 999px;
            padding: 4px 10px;
            font-weight: 700;
            font-size: 0.72rem;
            border: 1px solid #d7e3f2;
            background: #f8fbff;
            color: #315274;
        }

        .main-content .progress-container {
            margin-top: 10px;
            border: 1px solid #e0eaf5;
            background: #fbfdff;
            border-radius: 11px;
            padding: 10px;
        }

        .main-content .progress-bar {
            background: #e6eef8;
            border-radius: 999px;
            height: 8px;
        }

        .main-content .progress-fill {
            border-radius: 999px;
        }

        .main-content .contractors-section {
            margin-top: 10px;
            border-top: 1px dashed #d7e5f4;
            padding-top: 10px;
        }

        .main-content .contractors-title {
            color: #36597f;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .main-content .pm-empty .empty-state {
            border: 1px dashed #c7d9ee;
            background: #f7fbff;
            border-radius: 12px;
            min-height: 170px;
        }

        .main-content .pm-empty .empty-icon {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            border: 1px solid #cfe0f4;
            background: #fff;
            color: #5a7697;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        @media (max-width: 1240px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .main-content .projects-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .main-content .pm-stats-wrapper {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .main-content .pm-controls-wrapper {
                position: static;
            }
        }
    </style>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>

















