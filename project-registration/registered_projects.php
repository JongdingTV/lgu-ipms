<?php
// Import security functions
require '../session-auth.php';
// Database connection
require '../database.php';
require '../config-path.php';

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

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $db->query("SELECT id, code, name, type, sector, priority, status, created_at FROM projects ORDER BY created_at DESC");
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

$db->close();
?>
<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="../assets/style.css" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Projects - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            
            <!-- Project Registration with Submenu -->
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle">
                    <img src="list.png" class="nav-icon">Project Registration
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
            
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <a href="../contractors/contractors.php"><img src="../contractors/contractors.png" class="nav-icon">Contractors    ▼</a>
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
            <h1>Registered Projects</h1>
            <p>View and manage all registered infrastructure projects</p>
        </div>

        <div class="recent-projects">
            <!-- Filter Section -->
            <div class="projects-filter">
                <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
                    <input 
                        type="search" 
                        id="searchProjects" 
                        placeholder="🔍 Search projects by code, name or location..." 
                        style="flex: 1; min-width: 200px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem;"
                    >
                    <select 
                        id="filterStatus" 
                        style="padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.9rem; cursor: pointer;"
                    >
                        <option value="">All Status</option>
                        <option>Draft</option>
                        <option>For Approval</option>
                        <option>Approved</option>
                        <option>On-hold</option>
                        <option>Cancelled</option>
                    </select>
                    <button id="exportCsv" style="padding: 10px 20px; background: #3762c8; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">📥 Export CSV</button>
                </div>
            </div>

            <!-- Registered Projects Table -->
            <div class="projects-section">
                <div id="formMessage" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; display: none; font-weight: 500;"></div>
                
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js?v=<?php echo time(); ?>"></script>
    <script src="project-reg.js?v=<?php echo time(); ?>"></script>
    <script>
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

        // Load projects from DB
        let allProjects = [];
        const msg = document.getElementById('formMessage');

        function loadProjects() {
            console.log('loadProjects called');
            console.log('getApiUrl available?', typeof window.getApiUrl);
            console.log('APP_ROOT value:', window.APP_ROOT);
            
            if (typeof window.getApiUrl !== 'function') {
                console.error('❌ getApiUrl function not available!');
                const tbody = document.querySelector('#projectsTable tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error: API configuration not loaded. Please refresh the page.</td></tr>';
                return;
            }
            
            fetch(getApiUrl('project-registration/registered_projects.php?action=load_projects&_=' + Date.now()))
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(projects => {
                    console.log('Projects loaded:', projects);
                    allProjects = projects;
                    renderProjects(projects);
                })
                .catch(error => {
                    console.error('Error loading projects:', error);
                    const tbody = document.querySelector('#projectsTable tbody');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error loading projects. Check console.</td></tr>';
                });
        }

        function renderProjects(projects = allProjects) {
            const tbody = document.querySelector('#projectsTable tbody');
            if (!tbody) {
                console.error('Table tbody not found');
                return;
            }
            tbody.innerHTML = '';
            
            if (!projects.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#6b7280;">No projects found.</td></tr>';
                return;
            }
            
            projects.forEach((p) => {
                const row = document.createElement('tr');
                const createdDate = p.created_at ? new Date(p.created_at).toLocaleDateString() : 'N/A';
                row.innerHTML = `
                    <td>${p.code || ''}</td>
                    <td>${p.name || ''}</td>
                    <td>${p.type || ''}</td>
                    <td>${p.sector || ''}</td>
                    <td>${p.priority || 'Medium'}</td>
                    <td>${p.status || 'Draft'}</td>
                    <td>${createdDate}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-delete" data-id="${p.id}">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Wire up delete buttons
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const projectRow = this.closest('tr');
                    const projectName = projectRow.querySelector('td:nth-child(2)').textContent;
                    
                    showConfirmation({
                        title: 'Delete Project',
                        message: 'This project and all associated data will be permanently deleted. This action cannot be undone.',
                        itemName: `Project: ${projectName}`,
                        icon: '🗑️',
                        confirmText: 'Delete Permanently',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            fetch(getApiUrl('project-registration/registered_projects.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_project&id=${encodeURIComponent(id)}`
                            })
                            .then(res => res.json())
                            .then(data => {
                                msg.textContent = data.message;
                                msg.style.color = data.success ? '#16a34a' : '#dc2626';
                                msg.style.display = 'block';
                                setTimeout(() => { msg.style.display = 'none'; }, 3000);
                                loadProjects();
                            });
                        }
                    });
                });
            });
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchProjects');
        const statusFilter = document.getElementById('filterStatus');

        function filterProjects() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = statusFilter.value;

            const filtered = allProjects.filter(p => {
                const matchesSearch = !searchTerm || 
                    (p.code || '').toLowerCase().includes(searchTerm) ||
                    (p.name || '').toLowerCase().includes(searchTerm) ||
                    (p.sector || '').toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusTerm || p.status === statusTerm;
                
                return matchesSearch && matchesStatus;
            });

            renderProjects(filtered);
        }

        if (searchInput) searchInput.addEventListener('input', filterProjects);
        if (statusFilter) statusFilter.addEventListener('change', filterProjects);

        // Export CSV functionality
        const exportCsvBtn = document.getElementById('exportCsv');
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => {
                if (!allProjects.length) {
                    alert('No projects to export');
                    return;
                }

                const keys = ['code', 'name', 'type', 'sector', 'priority', 'status'];
                const headers = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(',');
                const rows = allProjects.map(p => 
                    keys.map(k => `"${String(p[k] || '').replace(/"/g, '""')}"`)
                        .join(',')
                );
                
                const csv = [headers, ...rows].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `projects_${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
            });
        }

        // Dropdown navigation toggle
        const projectRegToggle = document.getElementById('projectRegToggle');
        const navItemGroup = projectRegToggle?.closest('.nav-item-group');
        
        if (projectRegToggle) {
            // Keep dropdown open by default
            navItemGroup.classList.add('open');
            
            projectRegToggle.addEventListener('click', function(e) {
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

        // Load projects when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadProjects);
        } else {
            loadProjects();
        }
    </script>
</body>
</html>
