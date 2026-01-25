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
    <link rel="stylesheet" href="/assets/style.css" />
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
        <div class="nav-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            
            <!-- Project Registration with Submenu -->
            <div class="nav-item-group">
                <a href="../project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle">
                    <img src="../project-registration/list.png" class="nav-icon">Project Registration
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="../project-registration/project_registration.php" class="nav-submenu-item">
                        <span class="submenu-icon">➕</span>
                        <span>New Project</span>
                    </a>
                    <a href="../project-registration/registered_projects.php" class="nav-submenu-item">
                        <span class="submenu-icon">📋</span>
                        <span>Registered Projects</span>
                    </a>
                </div>
            </div>
            
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            
            <!-- Contractors with Submenu -->
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle">
                    <img src="contractors.png" class="nav-icon">Contractors
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
                        <div style="flex: 1;">
                            <label for="ctrNotes">Notes</label>
                            <textarea id="ctrNotes" rows="2"></textarea>
                        </div>
                    </div>
                </fieldset>

                <div style="margin-top:12px;">
                    <button type="submit" id="submitBtn">
                        Create Contractor
                    </button>
                    <button type="button" id="resetBtn">
                        Reset
                    </button>
                </div>
            </form>

            <div id="formMessage" style="margin-top:12px;color:#0b5;display:none;"></div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script>
        // Set active submenu item based on current URL
        const currentPage = window.location.pathname;
        const currentFileName = currentPage.split('/').pop() || 'index.php';
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.classList.remove('active');
            const href = item.getAttribute('href');
            const hrefFileName = href.split('/').pop();
            if (hrefFileName === currentFileName || currentPage.includes(hrefFileName)) {
                item.classList.add('active');
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
    <script src="contractors.js?v=3"></script>
</body>
</html>
