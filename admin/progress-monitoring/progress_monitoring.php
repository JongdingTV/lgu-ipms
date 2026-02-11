<?php
// Import security functions first
require dirname(__DIR__, 2) . '/session-auth.php';
// Database connection
require dirname(__DIR__, 2) . '/database.php';
require dirname(__DIR__, 2) . '/config-path.php';

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
    
    // Simple query first - use safe column selection
    $result = $db->query("SELECT id, code, name, description, location, province, sector, budget, status, project_manager, start_date, end_date, duration_months, created_at FROM projects ORDER BY created_at DESC LIMIT 500");
    
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
        <link rel="stylesheet" href="/assets/style.css" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - LGU IPMS</title>
        <link rel="icon" type="image/png" href="/logocityhall.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        .nav-item-group { position: relative; display: inline-block; }
        .nav-main-item { display: flex !important; align-items: center; gap: 8px; padding: 10px 16px !important; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: all 0.2s ease; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .nav-main-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px !important; }
        .nav-main-item.active { background: #eff6ff; color: #1e40af; font-weight: 600; }
        .nav-icon { width: 20px; height: 20px; display: inline-block; margin-right: 4px; }
        .dropdown-arrow { display: inline-block; margin-left: 4px; transition: transform 0.3s ease; }
        .nav-item-group.open .dropdown-arrow { transform: rotate(180deg); }
        .nav-submenu { position: absolute; top: 100%; left: 0; background: white; border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); min-width: 220px; margin-top: 8px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; overflow: hidden; }
        .nav-item-group.open .nav-submenu { opacity: 1; visibility: visible; transform: translateY(0); }
        .nav-submenu-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; border-left: 3px solid transparent; white-space: nowrap; }
        .nav-submenu-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px; border-left-color: #3b82f6; }
        .nav-submenu-item.active { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; font-weight: 600; }
        .submenu-icon { font-size: 1.1rem; flex-shrink: 0; }
        .nav-submenu-item span:last-child { flex: 1; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
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
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="../project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../project-registration/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="../project-registration/project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="../project-registration/registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php" class="active"><img src="monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="../contractors/contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../contractors/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="../contractors/contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="../contractors/registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Contractors</span></a>
                </div>
            </div>
            <a href="../project-prioritization/project-prioritization.php"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="../settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="../dashboard/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="../settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="../settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                </div>
            </div>
        </div>
        <div class="nav-divider"></div>
        <div style="padding: 10px 16px; margin-top: auto;">
            <a href="#" id="logoutBtn" style="display: flex; align-items: center; gap: 8px; color: #dc2626; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; padding: 10px 16px; border-radius: 6px; cursor: pointer; pointer-events: auto;" 
               onmouseover="this.style.background='#fee2e2'; this.style.paddingLeft='18px';" 
               onmouseout="this.style.background='none'; this.style.paddingLeft='16px';">>
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

    <script>
        // ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '../logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });
    </script>

    <script src="/assets/js/shared/shared-data.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="progress-monitoring.js?v=<?php echo time(); ?>"></script>
</body>
</html>



