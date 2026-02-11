<?php
// Import security functions - go up 2 levels to root
require dirname(__DIR__, 2) . '/session-auth.php';
// Database connection
require dirname(__DIR__, 2) . '/database.php';
require dirname(__DIR__, 2) . '/config-path.php';

// Set no-cache headers to prevent back button access to protected pages
set_no_cache_headers();

// Check authentication - redirect to login if not authenticated
check_auth();

// Check for suspicious activity (user-agent changes, etc.)
check_suspicious_activity();

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

// Get project statistics
$totalProjects = $db->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
$inProgressProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('Approved', 'For Approval')")->fetch_assoc()['count'];
$completedProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count'];
$totalBudget = $db->query("SELECT COALESCE(SUM(budget), 0) as total FROM projects")->fetch_assoc()['total'];

// Get recent projects
$recentProjects = $db->query("SELECT id, name, location, status, budget FROM projects ORDER BY created_at DESC LIMIT 5");
$db->close();
?>
<html>
<head>
        <link rel="stylesheet" href="/assets/style.css" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Dashboard</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            <a href="dashboard.php" class="active" data-section="dashboard"><img src="dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="../project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle" data-section="projects"><img src="../project-registration/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="../project-registration/project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="../project-registration/registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="../progress-monitoring/progress_monitoring.php" data-section="monitoring"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php" data-section="budget"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php" data-section="tasks"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="../contractors/contractors.php" class="nav-main-item" id="contractorsToggle" data-section="contractors"><img src="../contractors/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="../contractors/contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="../contractors/registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="../project-prioritization/project-prioritization.php" data-section="priorities"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="../settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
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
               onmouseout="this.style.background='none'; this.style.paddingLeft='16px';">
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
            <h1>Dashboard Overview</h1>
            <p>Infrastructure Project Management System</p>
        </div>

        <!-- Key Metrics Section -->
        <div class="metrics-container">
            <div class="metric-card card">
                <img src="chart.png" alt="Total Projects" class="metric-icon">
                <div class="metric-content">
                    <h3>Total Projects</h3>
                    <p class="metric-value"><?php echo $totalProjects; ?></p>
                    <span class="metric-status">Active & Completed</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="sandclock.png" alt="In Progress" class="metric-icon">
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value"><?php echo $inProgressProjects; ?></p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="check.png" alt="Completed" class="metric-icon">
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value"><?php echo $completedProjects; ?></p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card card" id="budgetCard" data-budget="<?php echo number_format($totalBudget, 2); ?>">
                <img src="budget.png" alt="Total Budget" class="metric-icon">
                <div class="metric-content">
                    <h3>Total Budget</h3>
                    <div style="display: flex; align-items: center; gap: 8px; position: relative; z-index: 100; min-height: 32px;">
                        <p class="metric-value" id="budgetValue" style="margin-bottom: 0; font-size: 1.4em; flex-wrap: wrap; word-wrap: break-word; white-space: normal; line-height: 1.3;">●●●●●●●●</p>
                        <span id="budgetVisibilityToggle" style="background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: #666; transition: all 0.2s ease; opacity: 0.7; pointer-events: auto; z-index: 101; position: relative; flex-shrink: 0;" title="Hold to reveal budget">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="pointer-events: none;">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </span>
                    </div>
                    <span class="metric-status">Allocated funds</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-box card">
                <h3>Project Status Distribution</h3>
                <div class="chart-placeholder">
                    <div class="status-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #10b981;"></span>
                            <span>Completed: 0%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #f59e0b;"></span>
                            <span>In Progress: 0%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #ef4444;"></span>
                            <span>Delayed: 0%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-box card">
                <h3>Budget Utilization</h3>
                <div class="chart-placeholder">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.9em; color: #666;">Budget utilization: 0% Used</p>
                </div>
            </div>
        </div>

        <!-- Recent Projects Section -->
        <div class="recent-projects card">
            <h3>Recent Projects</h3>
            <table class="projects-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Budget</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($recentProjects && $recentProjects->num_rows > 0) {
                        while ($project = $recentProjects->fetch_assoc()) {
                            $statusColor = 'pending';
                            if ($project['status'] === 'Completed') $statusColor = 'completed';
                            elseif ($project['status'] === 'Approved') $statusColor = 'approved';
                            elseif ($project['status'] === 'For Approval') $statusColor = 'pending';
                            elseif ($project['status'] === 'On-hold') $statusColor = 'onhold';
                            elseif ($project['status'] === 'Cancelled') $statusColor = 'cancelled';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                <td><span class="status-badge <?php echo $statusColor; ?>"><?php echo $project['status']; ?></span></td>
                                <td>
                                    <div class="progress-small">
                                        <div class="progress-fill-small" style="width: 0%;"></div>
                                    </div>
                                </td>
                                <td>₱<?php echo number_format($project['budget'], 2); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: #999;">No projects registered yet</td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats card">
            <div class="stat-item">
                <h4>Average Project Duration</h4>
                <p>0 months</p>
            </div>
            <div class="stat-item">
                <h4>On-Time Delivery Rate</h4>
                <p>0%</p>
            </div>
            <div class="stat-item">
                <h4>Budget Variance</h4>
                <p>0%</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ============================================
            // LOGOUT CONFIRMATION
            // ============================================
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

            // ============================================
            // NAVBAR SEARCH FUNCTIONALITY
            // ============================================
            const navSearch = document.getElementById('navSearch');
            const navLinks = document.querySelector('.nav-links');
            
            if (navSearch && navLinks) {
                navSearch.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    const links = navLinks.querySelectorAll('a');
                    
                    links.forEach(link => {
                        const text = link.textContent.toLowerCase();
                        const parent = link.closest('.nav-item-group') || link.parentElement;
                        
                        if (text.includes(query)) {
                            parent.style.display = '';
                            if (link.classList.contains('nav-main-item')) {
                                link.closest('.nav-item-group').querySelector('.nav-submenu').style.display = 'block';
                            }
                        } else if (query && !text.includes(query)) {
                            parent.style.display = 'none';
                        } else if (!query) {
                            parent.style.display = '';
                            if (link.classList.contains('nav-main-item')) {
                                link.closest('.nav-item-group').querySelector('.nav-submenu').style.display = 'none';
                            }
                        }
                    });
                });
            }

            // ============================================
            // BUDGET VISIBILITY TOGGLE
            // ============================================
            const budgetCard = document.getElementById('budgetCard');
            const budgetValue = document.getElementById('budgetValue');
            const budgetBtn = document.getElementById('budgetVisibilityToggle');
            let budgetRevealTimer;
            let isRevealing = false;

            if (budgetBtn && budgetValue && budgetCard) {
                // Add hover styles
                budgetBtn.addEventListener('mouseenter', function() {
                    this.style.color = '#333';
                    this.style.opacity = '1';
                });
                
                budgetBtn.addEventListener('mouseleave', function() {
                    if (!isRevealing) {
                        this.style.color = '#666';
                        this.style.opacity = '0.7';
                    }
                });
                
                // Mouse down - start timer
                budgetBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    startReveal();
                });
                
                // Touch start
                budgetBtn.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    startReveal();
                });
                
                // Mouse up - end reveal
                document.addEventListener('mouseup', function() {
                    endReveal();
                });
                
                // Touch end
                document.addEventListener('touchend', function() {
                    endReveal();
                });
                
                function startReveal() {
                    clearTimeout(budgetRevealTimer);
                    budgetRevealTimer = setTimeout(() => {
                        if (!isRevealing) {
                            isRevealing = true;
                            const actualBudget = budgetCard.getAttribute('data-budget');
                            budgetValue.textContent = '₱' + actualBudget;
                            budgetBtn.style.color = '#3b82f6';
                            budgetBtn.style.opacity = '1';
                        }
                    }, 300);
                }

                function endReveal() {
                    clearTimeout(budgetRevealTimer);
                    if (isRevealing) {
                        isRevealing = false;
                        budgetValue.textContent = '●●●●●●●●';
                        budgetBtn.style.color = '#666';
                        budgetBtn.style.opacity = '0.7';
                    }
                }
            }

            // ============================================
            // DROPDOWN NAVIGATION
            // ============================================
            const projectRegToggle = document.getElementById('projectRegToggle');
            const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
            const contractorsToggle = document.getElementById('contractorsToggle');
            const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuGroup = userMenuToggle ? userMenuToggle.closest('.nav-item-group') : null;
            
            if (projectRegToggle && projectRegGroup) {
                projectRegToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    projectRegGroup.classList.toggle('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }
            
            if (contractorsToggle && contractorsGroup) {
                contractorsToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    contractorsGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }

            if (userMenuToggle && userMenuGroup) {
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userMenuGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-item-group')) {
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                }
            });
            
            document.querySelectorAll('.nav-submenu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            });
        });
    </script>

    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="dashboard.js"></script>
</body>
</html>



