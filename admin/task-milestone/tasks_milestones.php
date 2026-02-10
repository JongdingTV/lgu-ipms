<?php
// Import security functions
require dirname(__DIR__, 2) . '/session-auth.php';
// Database connection
require dirname(__DIR__, 2) . '/database.php';
require dirname(__DIR__, 2) . '/config-path.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
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
    $db->close();
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
        <link rel="stylesheet" href="/assets/style.css" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Task & Milestone - LGU IPMS</title>    <link rel="icon" type="image/png" href="../logocityhall.png">    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
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
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="../project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../project-registration/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="../project-registration/project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="../project-registration/registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php" class="active"><img src="production.png" class="nav-icon">Task & Milestone</a>
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
            <a href="#" id="logoutBtn" style="display: flex; align-items: center; gap: 8px; color: #dc2626; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; padding: 10px 16px; border-radius: 6px;" 
               onmouseover="this.style.background='#fee2e2'; this.style.paddingLeft='18px';" 
               onmouseout="this.style.background='none'; this.style.paddingLeft='16px';"
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
                        <div class="progress-bar-fill" id="validationProgress" style="width: 0%"></div>
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
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
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
                });
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

    <script src="../shared-data.js?v=1"></script>
    <script src="../shared-toggle.js"></script>
    <script src="task-milestone.js?v=2"></script>
</body>
</html>
