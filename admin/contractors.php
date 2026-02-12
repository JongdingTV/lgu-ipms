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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $db->query("SELECT id, code, name FROM projects ORDER BY created_at DESC");
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
    <title>Contractors - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260212j">
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
            <h1>Contractors</h1>
            <p>Manage contractor information</p>
        </div>

        <div class="recent-projects">
            <h3>Add/Edit Contractor</h3>

            <form id="contractorForm" enctype="multipart/form-data">
                <!-- Basic contractor details -->
                <fieldset>
                    <legend>Basic Information</legend>
                    <div>
                        <div>
                            <label for="ctrCompany">Company Name</label>
                            <input type="text" id="ctrCompany" required>
                        </div>
                        <div>
                            <label for="ctrOwner">Owner Name</label>
                            <input type="text" id="ctrOwner">
                        </div>
                        <div>
                            <label for="ctrLicense">License Number</label>
                            <input type="text" id="ctrLicense" required>
                        </div>
                    </div>
                    <div>
                        <div>
                            <label for="ctrEmail">Email</label>
                            <input type="email" id="ctrEmail">
                        </div>
                        <div>
                            <label for="ctrPhone">Phone Number</label>
                            <input type="tel" id="ctrPhone">
                        </div>
                    </div>
                </fieldset>

                <!-- Additional details -->
                <fieldset>
                    <legend>Additional Details</legend>
                    <div>
                        <div>
                            <label for="ctrAddress">Address</label>
                            <input type="text" id="ctrAddress" required>
                        </div>
                        <div>
                            <label for="ctrSpecialization">Specialization</label>
                            <select id="ctrSpecialization">
                                <option value="">-- Select --</option>
                                <option>Construction</option>
                                <option>Plumbing</option>
                                <option>Electrical</option>
                                <option>Civil Engineering</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="ctrExperience">Years of Experience</label>
                            <input type="number" id="ctrExperience" min="0">
                        </div>
                    </div>
                    <div>
                        <div>
                            <label for="ctrRating">Rating (1-5)</label>
                            <input type="number" id="ctrRating" min="1" max="5" step="0.1">
                        </div>
                        <div>
                            <label for="ctrStatus">Status</label>
                            <select id="ctrStatus">
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Blacklisted">Blacklisted</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="ac-ee3d55bf">
                            <label for="ctrNotes">Notes</label>
                            <textarea id="ctrNotes" rows="2"></textarea>
                        </div>
                    </div>
                </fieldset>

                <div class="ac-9374e842">
                    <button type="submit" id="submitBtn">
                        Create Contractor
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
    <script src="../assets/js/admin.js?v=20260212j"></script>
    <script src="../assets/js/component-utilities.js"></script>
</body>
</html>















