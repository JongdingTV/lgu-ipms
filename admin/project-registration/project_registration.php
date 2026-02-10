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
        <link rel="stylesheet" href="/assets/style.css" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Project Registration - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
        <div class="nav-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
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
                    <a href="registered_projects.php" class="nav-submenu-item">
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
                <a href="../contractors/contractors.php" class="nav-main-item" id="contractorsToggle">
                    <img src="../contractors/contractors.png" class="nav-icon">Contractors
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="../contractors/contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">➕</span>
                        <span>Add Contractor</span>
                    </a>
                    <a href="../contractors/registered_contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">📋</span>
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
            <a href="../logout.php" style="display: flex; align-items: center; gap: 8px; color: #dc2626; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; padding: 10px 16px; border-radius: 6px;" 
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
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
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

                <div style="margin-top:12px;">
                    <button type="submit" id="submitBtn">
                        Create Project
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

    <script src="../shared-data.js"></script>
    <script src="../shared-toggle.js"></script>

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

        // --- AJAX-based Project Registration ---
        const form = document.getElementById('projectForm');
        const msg = document.getElementById('formMessage');
        const resetBtn = document.getElementById('resetBtn');
        let editProjectId = null;

        // Sidebar toggle handlers (unchanged)
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
        function loadSavedProjects() {
            // Add cache-busting param to always get fresh data
            fetch(getApiUrl('project-registration/project_registration.php?action=load_projects&_=' + Date.now()))
                .then(res => res.json())
                .then(projects => {
                    console.log('Fetched projects:', projects); // DEBUG
                    const tbody = document.querySelector('#projectsTable tbody');
                    const projectCount = document.getElementById('projectCount');
                    
                    // Update project count
                    projectCount.textContent = `${projects.length} ${projects.length === 1 ? 'project' : 'projects'}`;
                    
                    tbody.innerHTML = '';
                    if (!projects.length) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#6b7280;">No projects registered yet.</td></tr>';
                        return;
                    }
                    projects.forEach((p, i) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${p.code || ''}</td>
                            <td>${p.name || ''}</td>
                            <td>${p.type || ''}</td>
                            <td>${p.sector || ''}</td>
                            <td>${p.priority || 'Medium'}</td>
                            <td>${p.status || 'Draft'}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" data-id="${p.id}">Edit</button>
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
                                    fetch(getApiUrl('project-registration/project_registration.php'), {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: `action=delete_project&id=${encodeURIComponent(id)}`
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        msg.textContent = data.message;
                                        msg.style.display = 'block';
                                        msg.style.color = data.success ? '#dc2626' : '#f00';
                                        setTimeout(() => { msg.style.display = 'none'; }, 3000);
                                        loadSavedProjects();
                                    });
                                }
                            });
                        });
                    });

                    // Wire up edit buttons
                    document.querySelectorAll('.btn-edit').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const id = this.dataset.id;
                            // Find project in loaded list
                            const project = projects.find(p => p.id == id);
                            if (!project) return;
                            document.getElementById('projCode').value = project.code || '';
                            document.getElementById('projName').value = project.name || '';
                            document.getElementById('projType').value = project.type || '';
                            document.getElementById('projSector').value = project.sector || '';
                            document.getElementById('projDescription').value = project.description || '';
                            document.getElementById('projPriority').value = project.priority || 'Medium';
                            document.getElementById('province').value = project.province || '';
                            document.getElementById('barangay').value = project.barangay || '';
                            document.getElementById('projLocation').value = project.location || '';
                            document.getElementById('startDate').value = project.start_date || '';
                            document.getElementById('endDate').value = project.end_date || '';
                            document.getElementById('projDuration').value = project.duration_months || '';
                            document.getElementById('projBudget').value = project.budget || '';
                            document.getElementById('projManager').value = project.project_manager || '';
                            document.getElementById('status').value = project.status || 'Draft';
                            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            editProjectId = id;
                            const submitBtn = form.querySelector('button[type="submit"]');
                            submitBtn.innerHTML = 'Update Project';
                        });
                    });
                });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_project');
            fd.append('code', document.getElementById('projCode').value);
            fd.append('name', document.getElementById('projName').value);
            fd.append('type', document.getElementById('projType').value);
            fd.append('sector', document.getElementById('projSector').value);
            fd.append('description', document.getElementById('projDescription').value);
            fd.append('priority', document.getElementById('projPriority').value);
            fd.append('province', document.getElementById('province').value);
            fd.append('barangay', document.getElementById('barangay').value);
            fd.append('location', document.getElementById('projLocation').value);
            fd.append('start_date', document.getElementById('startDate').value);
            fd.append('end_date', document.getElementById('endDate').value);
            fd.append('duration_months', document.getElementById('projDuration').value);
            fd.append('budget', document.getElementById('projBudget').value);
            fd.append('project_manager', document.getElementById('projManager').value);
            fd.append('status', document.getElementById('status').value);
            if (editProjectId) {
                fd.append('id', editProjectId);
            }
            fetch(getApiUrl('project-registration/project_registration.php'), {
                method: 'POST',
                body: fd
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP Error: ' + res.status);
                }
                return res.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    msg.textContent = data.message;
                    msg.style.display = 'block';
                    msg.style.color = data.success ? '#0b5' : '#f00';
                    if (data.success) {
                        form.reset();
                        editProjectId = null;
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = 'Create Project';
                        // Reload the projects table without full page reload
                        loadSavedProjects();
                    }
                    setTimeout(() => { msg.style.display = 'none'; }, 3000);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Raw response:', text);
                    msg.textContent = 'Error: Invalid response from server. Check browser console.';
                    msg.style.display = 'block';
                    msg.style.color = '#f00';
                    setTimeout(() => { msg.style.display = 'none'; }, 5000);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                msg.textContent = 'Error: ' + error.message;
                msg.style.display = 'block';
                msg.style.color = '#f00';
                setTimeout(() => { msg.style.display = 'none'; }, 3000);
            });
        });

        resetBtn.addEventListener('click', function(){
            form.reset();
            msg.style.display = 'none';
            editProjectId = null;
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = 'Create Project';
        });

        // Load projects on page load
        document.addEventListener('DOMContentLoaded', function(){
            loadSavedProjects();
        });

        // Dropdown toggle handlers
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
</body>
</html>
