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

// Create contractor_project_assignments table if it doesn't exist
$db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contractor_id INT NOT NULL,
    project_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (contractor_id, project_id),
    FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
)");

// Handle GET request for loading contractors
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractors') {
    header('Content-Type: application/json');
    
    // Match the actual database column names from contractors-api.php
    $result = $db->query("SELECT id, company, license, email, phone, status, rating FROM contractors ORDER BY id DESC LIMIT 100");
    $contractors = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contractors[] = $row;
        }
        $result->free();
    } else {
        error_log("Contractors query error: " . $db->error);
    }
    
    echo json_encode($contractors);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $db->query("SELECT id, code, name, type, sector, status FROM projects ORDER BY created_at DESC");
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

// Handle POST request for assigning contractor to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_contractor') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        // Create table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contractor_id INT NOT NULL,
            project_id INT NOT NULL,
            assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (contractor_id, project_id),
            FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )");
        
        $stmt = $db->prepare("INSERT INTO contractor_project_assignments (contractor_id, project_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Contractor assigned to project successfully']);
        } else {
            if (strpos($stmt->error, 'Duplicate') !== false) {
                echo json_encode(['success' => false, 'message' => 'This contractor is already assigned to this project']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign contractor: ' . $stmt->error]);
            }
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid contractor or project ID']);
    }
    exit;
}

// Handle POST request for removing contractor from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unassign_contractor') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        $stmt = $db->prepare("DELETE FROM contractor_project_assignments WHERE contractor_id=? AND project_id=?");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Contractor unassigned from project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unassign contractor']);
        }
        $stmt->close();
    }
    exit;
}

// Handle GET request for loading assigned projects for a contractor
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_assigned_projects') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : 0;
    
    if ($contractor_id > 0) {
        $stmt = $db->prepare("SELECT p.id, p.code, p.name FROM projects p 
                               INNER JOIN contractor_project_assignments cpa ON p.id = cpa.project_id 
                               WHERE cpa.contractor_id = ?");
        $stmt->bind_param("i", $contractor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        
        echo json_encode($projects);
        $stmt->close();
    } else {
        echo json_encode([]);
    }
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
    <title>Registered Contractors - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        /* Dropdown Navigation Styling */
        .nav-item-group {
            position: relative;
        }

        .nav-main-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            color: rgba(255, 255, 255, 0.9) !important;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            white-space: nowrap;
            transition: all 0.3s ease;
            border-radius: 6px;
        }

        .nav-main-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }

        .dropdown-arrow {
            font-size: 0.7rem;
            transition: transform 0.3s ease;
            display: inline-block;
            margin-left: 4px;
        }

        .nav-item-group.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .nav-submenu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            margin-top: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow: hidden;
        }

        .nav-item-group.open .nav-submenu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .nav-submenu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }

        .nav-submenu-item:hover {
            background: #f3f4f6;
            color: #1f2937;
            padding-left: 18px;
            border-left-color: #3b82f6;
        }

        .nav-submenu-item.active {
            background: #eff6ff;
            color: #1e40af;
            border-left-color: #3b82f6;
            font-weight: 600;
        }

        .submenu-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .nav-submenu-item span:last-child {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-assign {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
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
            <a href="../project-registration/project_registration.php"><img src="../project-registration/list.png" class="nav-icon">Project Registration    ▼</a>
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
                    <a href="registered_contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">👷</span>
                        <span>Registered Contractors</span>
                    </a>
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
            <h1>Registered Contractors</h1>
            <p>View and manage registered contractors</p>
        </div>

        <div class="recent-projects">
            <!-- Filter Section -->
            <div class="contractors-filter" style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
                <input 
                    type="search" 
                    id="searchContractors" 
                    placeholder="🔍 Search contractors by company, license or contact..." 
                    style="flex: 1; min-width: 200px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem;"
                >
                <select 
                    id="filterStatus" 
                    style="padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem; cursor: pointer;"
                >
                    <option value="">All Status</option>
                    <option>Active</option>
                    <option>Suspended</option>
                    <option>Blacklisted</option>
                </select>
            </div>

            <!-- Registered Contractors Table -->
            <div class="contractors-section">
                <div id="formMessage" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; display: none; font-weight: 500;"></div>
                
                <div class="table-wrap">
                    <table id="contractorsTable" class="table">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>License Number</th>
                                <th>Contact Email</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Projects Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Available Projects Section (Inside Registered Contractors) -->
            <div class="projects-section" style="margin-top:30px;" id="available-projects">
                <h3>Available Projects</h3>
                <div class="table-wrap">
                    <table id="projectsTable" class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Sector</th>
                                <th>Status</th>
                            </tr>
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

    <script src="../shared-data.js?v=1"></script>
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

        // Sidebar toggle handlers
        const sidebarToggle = document.getElementById('toggleSidebar');
        const sidebarShow = document.getElementById('toggleSidebarShow');
        function toggleSidebarHandler(e) {
            e.preventDefault();
            const navbar = document.getElementById('navbar');
            const toggleBtn = document.getElementById('showSidebarBtn');
            if (navbar) navbar.classList.toggle('hidden');
            document.body.classList.toggle('sidebar-hidden');
            if (toggleBtn) toggleBtn.classList.toggle('show');
        }
        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebarHandler);
        if (sidebarShow) sidebarShow.addEventListener('click', toggleSidebarHandler);

        let allContractors = [];
        let allProjects = [];

        // Load contractors from database
        function loadContractors() {
            console.log('loadContractors called');
            const url = getApiUrl('contractors/registered_contractors.php?action=load_contractors&_=' + Date.now());
            console.log('Fetching from:', url);
            
            fetch(url)
                .then(res => {
                    console.log('Response received, status:', res.status);
                    console.log('Response ok:', res.ok);
                    return res.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const contractors = JSON.parse(text);
                        console.log('Contractors parsed:', contractors);
                        allContractors = contractors;
                        renderContractors(contractors);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Text was:', text);
                    }
                })
                .catch(error => {
                    console.error('Error loading contractors:', error);
                    const tbody = document.querySelector('#contractorsTable tbody');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#c00;">Error: ' + error.message + '</td></tr>';
                });
        }

        // Load projects from database
        function loadProjects() {
            console.log('loadProjects called');
            const url = getApiUrl('contractors/registered_contractors.php?action=load_projects&_=' + Date.now());
            console.log('Fetching projects from:', url);
            
            fetch(url)
                .then(res => {
                    console.log('Projects response status:', res.status);
                    return res.text();
                })
                .then(text => {
                    console.log('Projects response text:', text);
                    try {
                        const projects = JSON.parse(text);
                        console.log('Projects parsed:', projects);
                        allProjects = projects;
                        renderProjects(projects);
                    } catch (e) {
                        console.error('Projects JSON parse error:', e);
                    }
                })
                .catch(error => console.error('Error loading projects:', error));
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchContractors');
        const statusFilter = document.getElementById('filterStatus');

        function filterContractors() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = statusFilter.value;

            const filtered = allContractors.filter(c => {
                const matchesSearch = !searchTerm || 
                    (c.company || '').toLowerCase().includes(searchTerm) ||
                    (c.license || '').toLowerCase().includes(searchTerm) ||
                    (c.email || '').toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusTerm || c.status === statusTerm;
                
                return matchesSearch && matchesStatus;
            });

            renderContractors(filtered);
        }

        if (searchInput) searchInput.addEventListener('input', filterContractors);
        if (statusFilter) statusFilter.addEventListener('change', filterContractors);

        // Render contractors table
        function renderContractors(contractors) {
            console.log('renderContractors called with:', contractors);
            const tbody = document.querySelector('#contractorsTable tbody');
            if (!tbody) {
                console.error('Cannot find #contractorsTable tbody');
                return;
            }
            tbody.innerHTML = '';
            
            if (!contractors || !contractors.length) {
                console.log('No contractors to display');
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">No contractors found.</td></tr>';
                return;
            }
            console.log('Rendering', contractors.length, 'contractors');

            contractors.forEach(c => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${c.company || 'N/A'}</strong></td>
                    <td>${c.license || 'N/A'}</td>
                    <td>${c.email || c.phone || 'N/A'}</td>
                    <td><span class="status-badge ${(c.status || '').toLowerCase()}">${c.status || 'N/A'}</span></td>
                    <td>${c.rating ? '⭐ ' + c.rating + '/5' : '—'}</td>
                    <td>
                        <button class="btn-view-projects" data-id="${c.id}" style="padding: 8px 14px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease;">View Projects</button>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-assign" data-id="${c.id}">Assign Projects</button>
                            <button class="btn-delete" data-id="${c.id}">🗑️ Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Wire up view projects buttons
            document.querySelectorAll('#contractorsTable .btn-view-projects').forEach(btn => {
                btn.addEventListener('click', function() {
                    const contractorId = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    openProjectsModal(contractorId, contractorName);
                });
            });

            // Wire up assign buttons
            document.querySelectorAll('#contractorsTable .btn-assign').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Assign button clicked');
                    const contractorId = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    console.log('Opening modal for contractor:', contractorName, 'ID:', contractorId);
                    openAssignModal(contractorId, contractorName);
                });
            });

            // Wire up delete buttons
            document.querySelectorAll('#contractorsTable .btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    
                    showConfirmation({
                        title: 'Delete Contractor',
                        message: 'This contractor and all associated records will be permanently deleted. This action cannot be undone.',
                        itemName: `Contractor: ${contractorName}`,
                        icon: '🗑️',
                        confirmText: 'Delete Permanently',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            fetch(getApiUrl('contractors/registered_contractors.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_contractor&id=${encodeURIComponent(id)}`
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    loadContractors();
                                }
                            });
                        }
                    });
                });
            });
        }

        // Render projects table
        function renderProjects(projects) {
            const tbody = document.querySelector('#projectsTable tbody');
            if (!tbody) {
                console.error('Projects table tbody not found');
                return;
            }
            tbody.innerHTML = '';
            
            if (!projects || !projects.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#6b7280;">No projects available.</td></tr>';
                return;
            }

            projects.forEach(p => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${p.code || ''}</td>
                    <td>${p.name || ''}</td>
                    <td>${p.type || ''}</td>
                    <td>${p.sector || ''}</td>
                    <td><span class="status-badge ${(p.status || '').toLowerCase()}">${p.status || 'N/A'}</span></td>
                `;
                tbody.appendChild(row);
            });
        }

        // Dropdown navigation toggle
        const contractorsToggle = document.getElementById('contractorsToggle');
        const navItemGroup = contractorsToggle?.closest('.nav-item-group');
        
        if (contractorsToggle && navItemGroup) {
            // Keep dropdown open by default
            navItemGroup.classList.add('open');
            
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                navItemGroup.classList.toggle('open');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!navItemGroup.contains(e.target)) {
                    navItemGroup.classList.remove('open');
                }
            });
        }

        // Assignment Modal Functions
        function openAssignModal(contractorId, contractorName) {
            console.log('openAssignModal called with:', contractorId, contractorName);
            const modal = document.getElementById('assignmentModal');
            console.log('Modal element:', modal);
            if (!modal) {
                console.error('assignmentModal not found in DOM');
                return;
            }
            document.getElementById('assignContractorId').value = contractorId;
            document.getElementById('assignmentTitle').textContent = `Assign "${contractorName}" to Projects`;
            
            // Load available projects
            loadProjectsForAssignment(contractorId);
            modal.style.display = 'flex';
            console.log('Modal display set to flex');
        }

        function closeAssignModal() {
            document.getElementById('assignmentModal').style.display = 'none';
        }

        function openProjectsModal(contractorId, contractorName) {
            console.log('openProjectsModal called for:', contractorName);
            const modal = document.getElementById('projectsViewModal');
            if (!modal) {
                console.error('projectsViewModal not found');
                return;
            }
            
            document.getElementById('projectsViewTitle').textContent = `Projects Assigned to ${contractorName}`;
            const projectsList = document.getElementById('projectsViewList');
            projectsList.innerHTML = '<p style="text-align: center; color: #999;">Loading projects...</p>';
            
            // Get assigned projects
            fetch(getApiUrl(`contractors/registered_contractors.php?action=get_assigned_projects&contractor_id=${contractorId}`))
                .then(res => res.text())
                .then(text => {
                    try {
                        const projects = JSON.parse(text);
                        console.log('Assigned projects:', projects);
                        projectsList.innerHTML = '';
                        
                        if (!projects || projects.length === 0) {
                            projectsList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No Projects Assigned</p>';
                            return;
                        }
                        
                        projects.forEach(p => {
                            const div = document.createElement('div');
                            div.style.cssText = 'padding: 12px 16px; margin: 10px 0; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 6px;';
                            div.innerHTML = `
                                <div><strong>${p.code}</strong> - ${p.name}</div>
                                <small style="color: #666;">${p.type || 'N/A'} • ${p.sector || 'N/A'}</small>
                            `;
                            projectsList.appendChild(div);
                        });
                    } catch (e) {
                        console.error('Error parsing projects:', e);
                        projectsList.innerHTML = '<p style="color: red;">Error loading projects</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    projectsList.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                });
            
            modal.style.display = 'flex';
        }

        function closeProjectsModal() {
            const modal = document.getElementById('projectsViewModal');
            if (modal) modal.style.display = 'none';
        }

        function loadProjectsForAssignment(contractorId) {
            console.log('loadProjectsForAssignment called with contractorId:', contractorId);
            const projectsList = document.getElementById('projectsList');
            projectsList.innerHTML = '<p style="text-align: center; color: #999;">Loading projects...</p>';
            
            // Get already assigned projects
            fetch(getApiUrl(`contractors/registered_contractors.php?action=get_assigned_projects&contractor_id=${contractorId}`))
                .then(res => res.text())
                .then(text => {
                    console.log('Assigned projects response:', text);
                    try {
                        const assignedProjects = JSON.parse(text);
                        const assignedIds = assignedProjects.map(p => p.id);
                        console.log('Assigned IDs:', assignedIds);
                        
                        // Get all available projects
                        return fetch(getApiUrl('contractors/registered_contractors.php?action=load_projects'))
                            .then(res => res.text())
                            .then(text => {
                                console.log('All projects response:', text);
                                const projects = JSON.parse(text);
                                console.log('Projects parsed:', projects);
                                
                                projectsList.innerHTML = '';
                                
                                if (!projects || !projects.length) {
                                    projectsList.innerHTML = '<p style="text-align: center; color: #999;">No projects available</p>';
                                    return;
                                }
                                
                                projects.forEach(p => {
                                    const projectId = String(p.id);
                                    const isAssigned = assignedIds.map(id => String(id)).includes(projectId);
                                    console.log(`Project ${projectId}: assigned=${isAssigned}`);
                                    
                                    const div = document.createElement('div');
                                    div.style.cssText = 'padding: 10px; margin: 8px 0; background: #f9fafb; border-radius: 6px; border-left: 3px solid ' + (isAssigned ? '#10b981' : '#e5e7eb') + ';';
                                    div.innerHTML = `
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <input type="checkbox" class="project-checkbox" data-project-id="${projectId}" ${isAssigned ? 'checked' : ''} style="width: 18px; height: 18px; cursor: pointer;">
                                            <div>
                                                <strong>${p.code}</strong> - ${p.name}
                                                <br><small style="color: #999;">${p.type || 'N/A'} • ${p.sector || 'N/A'}</small>
                                            </div>
                                        </div>
                                    `;
                                    projectsList.appendChild(div);
                                });
                            });
                    } catch (e) {
                        console.error('JSON parse error for assigned projects:', e, 'Text:', text);
                        projectsList.innerHTML = '<p style="text-align: center; color: red;">Error loading assigned projects</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading assigned projects:', error);
                    projectsList.innerHTML = '<p style="text-align: center; color: red;">Error: ' + error.message + '</p>';
                });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('assignmentModal');
            if (e.target === modal) {
                closeAssignModal();
            }
        });

        // Save assignments handler function
        async function saveAssignmentsHandler() {
            console.log('=== SAVE ASSIGNMENTS HANDLER CALLED ===');
            const saveBtn = document.getElementById('saveAssignments');
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Saving...';
            
            const contractorId = document.getElementById('assignContractorId').value;
            console.log('Contractor ID:', contractorId);
            
            if (!contractorId) {
                console.error('No contractor ID');
                alert('Error: No contractor selected');
                saveBtn.disabled = false;
                saveBtn.textContent = '✓ Save Assignments';
                return;
            }
            
            const checkboxes = document.querySelectorAll('.project-checkbox');
            console.log('Found', checkboxes.length, 'checkboxes');
            
            if (checkboxes.length === 0) {
                console.error('No checkboxes found');
                alert('No projects to assign');
                saveBtn.disabled = false;
                saveBtn.textContent = '✓ Save Assignments';
                return;
            }

            let successCount = 0;
            let failCount = 0;
            
            // Process each checkbox
            for (let i = 0; i < checkboxes.length; i++) {
                const checkbox = checkboxes[i];
                const projectId = String(checkbox.getAttribute('data-project-id')).trim();
                const isChecked = checkbox.checked;
                
                console.log(`Processing checkbox ${i}: projectId="${projectId}", checked=${isChecked}`);
                
                if (!projectId) {
                    console.error(`Checkbox ${i} has no projectId`);
                    failCount++;
                    continue;
                }

                const action = isChecked ? 'assign_contractor' : 'unassign_contractor';
                const body = `action=${action}&contractor_id=${contractorId}&project_id=${projectId}`;
                
                console.log(`Sending request: ${action}`, body);
                
                try {
                    const response = await fetch(getApiUrl('contractors/registered_contractors.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body
                    });
                    
                    const text = await response.text();
                    console.log(`Response for ${action}:`, text);
                    
                    const data = JSON.parse(text);
                    if (data.success) {
                        successCount++;
                        console.log(`Success: ${action} project ${projectId}`);
                    } else {
                        failCount++;
                        console.error(`Failed: ${action} project ${projectId}:`, data.message);
                    }
                } catch (err) {
                    failCount++;
                    console.error(`Error processing ${action}:`, err);
                }
            }

            console.log('=== ALL PROCESSING COMPLETE ===');
            console.log('Success:', successCount, 'Fail:', failCount);
            
            // Show notification
            showSuccessNotification('✅ Assignments Saved!', `Successfully updated ${successCount} project(s)`);
            
            // Close and refresh
            setTimeout(() => {
                closeAssignModal();
                loadContractors();
            }, 1500);
            
            saveBtn.disabled = false;
            saveBtn.textContent = '✓ Save Assignments';
        }

        // Success notification function
        function showSuccessNotification(title, message) {
            const notification = document.createElement('div');
            notification.id = 'successNotification';
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 20px 30px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
                z-index: 2000;
                min-width: 300px;
                animation: slideIn 0.4s ease-out;
                font-family: 'Poppins', sans-serif;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 24px;">✓</div>
                    <div>
                        <div style="font-weight: 700; font-size: 16px;">${title}</div>
                        <div style="font-size: 14px; opacity: 0.95; margin-top: 4px;">${message}</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Add animation styles
            const style = document.createElement('style');
            if (!document.getElementById('notificationStyles')) {
                style.id = 'notificationStyles';
                style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.4s ease-out';
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, 4000);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                loadContractors();
                loadProjects();
            });
        } else {
            loadContractors();
            loadProjects();
        }
    </script>

    <!-- Assignment Modal -->
    <div id="assignmentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
        <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <input type="hidden" id="assignContractorId" value="">
            <h2 id="assignmentTitle" style="margin: 0 0 20px 0; color: #1f2937;"></h2>
            <div id="projectsList" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeAssignModal()" style="padding: 10px 20px; border: 2px solid #e5e7eb; background: white; color: #6b7280; border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button id="saveAssignments" onclick="saveAssignmentsHandler()" style="padding: 10px 20px; background: #3762c8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">✓ Save Assignments</button>
            </div>
        </div>
    </div>

    <!-- Projects View Modal -->
    <div id="projectsViewModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px;">
        <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <h2 id="projectsViewTitle" style="margin: 0 0 20px 0; color: #1f2937;"></h2>
            <div id="projectsViewList" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeProjectsModal()" style="padding: 10px 20px; background: #3762c8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
    <script src="../shared-data.js?v=1"></script>
    <script src="../shared-toggle.js"></script>
</body>
</html>
