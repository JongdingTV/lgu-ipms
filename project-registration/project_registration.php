<!doctype html>
<?php
// Database connection
$conn = new mysqli('localhost:3307', 'root', '', 'lgu_ipms');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_project') {
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $sector = trim($_POST['sector']);
        $description = trim($_POST['description']);
        $priority = trim($_POST['priority']);
        $province = trim($_POST['province']);
        $barangay = trim($_POST['barangay']);
        $location = trim($_POST['location']);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $duration_months = !empty($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $project_manager = trim($_POST['project_manager']);
        $status = trim($_POST['status']);
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing project
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE projects SET code=?, name=?, type=?, sector=?, description=?, priority=?, province=?, barangay=?, location=?, start_date=?, end_date=?, duration_months=?, budget=?, project_manager=?, status=? WHERE id=?");
            $stmt->bind_param('sssssssssssidssi', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status, $id);
        } else {
            // Insert new project
            $stmt = $conn->prepare("INSERT INTO projects (code, name, type, sector, description, priority, province, barangay, location, start_date, end_date, duration_months, budget, project_manager, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssssssssidss', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save project: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'delete_project') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM projects WHERE id=?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete project: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
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

$conn->close();
?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Project Registration - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="project-reg.css">
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="project_registration.php" class="active"><img src="list.png" class="nav-icon">Project Registration</a>
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <a href="../contractors/contractors.php"><img src="../contractors/contractors.png" class="nav-icon">Contractors</a>
            <a href="../project-prioritization/project-prioritization.php"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome, User</span>
            <a href="../login.php" class="nav-logout">Logout</a>
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

            <!-- Registered Projects Table -->
            <div class="projects-section">
                <h3>Registered Projects</h3>
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

    <script src="../shared-data.js"></script>

    <script>
        // simple client-only demo: store projects in localStorage
        const form = document.getElementById('projectForm');
        const msg = document.getElementById('formMessage');
        const savedDiv = document.getElementById('savedProjects');
        const resetBtn = document.getElementById('resetBtn');

        // Sidebar toggle handlers (ensure arrow works even if external script not loaded)
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

        function fileListNames(input) {
            if (!input || !input.files) return [];
            return Array.from(input.files).map(f => f.name);
        }

        function loadSavedProjects() {
            const projects = (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) 
                ? IPMS_DATA.getProjects() 
                : JSON.parse(localStorage.getItem('projects')||'[]');
            const tbody = document.querySelector('#projectsTable tbody');
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
                            <button class="btn-edit" data-index="${i}">Edit</button>
                            <button class="btn-delete" data-index="${i}">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Wire up delete buttons
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    const projects = JSON.parse(localStorage.getItem('projects')||'[]');
                    const project = projects[index];
                    
                    if (confirm(`Are you sure you want to delete project "${project.name}"?\n\nThis action cannot be undone.`)) {
                        projects.splice(index, 1);
                        if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.saveProjects) {
                            IPMS_DATA.saveProjects(projects);
                        } else {
                            localStorage.setItem('projects', JSON.stringify(projects));
                        }
                        msg.textContent = 'Project deleted successfully.';
                        msg.style.display = 'block';
                        msg.style.color = '#dc2626';
                        setTimeout(() => {
                            msg.style.display = 'none';
                            msg.style.color = '#0b5';
                        }, 3000);
                        loadSavedProjects();
                    }
                });
            });

            // Wire up edit buttons
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    const projects = JSON.parse(localStorage.getItem('projects')||'[]');
                    const project = projects[index];
                    
                    // Populate form with project data for editing
                    document.getElementById('projCode').value = project.code || '';
                    document.getElementById('projName').value = project.name || '';
                    document.getElementById('projType').value = project.type || '';
                    document.getElementById('projSector').value = project.sector || '';
                    document.getElementById('projDescription').value = project.description || '';
                    document.getElementById('projPriority').value = project.priority || 'Medium';
                    document.getElementById('province').value = project.province || '';
                    document.getElementById('barangay').value = project.barangay || '';
                    document.getElementById('projLocation').value = project.location || '';
                    document.getElementById('startDate').value = project.startDate || '';
                    document.getElementById('endDate').value = project.endDate || '';
                    document.getElementById('projDuration').value = project.durationMonths || '';
                    document.getElementById('projBudget').value = project.budget || '';
                    document.getElementById('projManager').value = project.projectManager || '';
                    document.getElementById('status').value = project.status || 'Draft';

                    // Scroll to form
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Store the index for update mode
                    form.dataset.editIndex = index;
                    
                    // Change button text
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = 'Update Project';
                });
            });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            const projects = (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) 
                ? IPMS_DATA.getProjects() 
                : JSON.parse(localStorage.getItem('projects')||'[]');
            const editIndex = form.dataset.editIndex;

            const p = {
                code: document.getElementById('projCode').value,
                name: document.getElementById('projName').value,
                type: document.getElementById('projType').value,
                sector: document.getElementById('projSector').value,
                description: document.getElementById('projDescription').value,
                priority: document.getElementById('projPriority').value,

                province: document.getElementById('province').value,
                barangay: document.getElementById('barangay').value,
                location: document.getElementById('projLocation').value,

                startDate: document.getElementById('startDate').value,
                endDate: document.getElementById('endDate').value,
                durationMonths: Number(document.getElementById('projDuration').value),

                budget: Number(document.getElementById('projBudget').value),

                projectManager: document.getElementById('projManager').value,

                status: document.getElementById('status').value,

                createdAt: new Date().toISOString()
            };

            if (editIndex !== undefined) {
                // Update existing project
                projects[parseInt(editIndex)] = p;
                if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.saveProjects) {
                    IPMS_DATA.saveProjects(projects);
                } else {
                    localStorage.setItem('projects', JSON.stringify(projects));
                }
                msg.textContent = 'Project updated successfully.';
                msg.style.color = '#f59e0b';
                delete form.dataset.editIndex;
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = 'Create Project';
            } else {
                // Create new project
                projects.push(p);
                if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.saveProjects) {
                    IPMS_DATA.saveProjects(projects);
                } else {
                    localStorage.setItem('projects', JSON.stringify(projects));
                }
                msg.textContent = 'Project created successfully.';
                msg.style.color = '#0b5';
            }
            
            msg.style.display = 'block';
            form.reset();
            loadSavedProjects();
            
            // Hide message after 3 seconds
            setTimeout(() => {
                msg.style.display = 'none';
            }, 3000);
        });

        resetBtn.addEventListener('click', function(){
            form.reset();
            msg.style.display = 'none';

            // Clear edit mode if active
            if (form.dataset.editIndex !== undefined) {
                delete form.dataset.editIndex;
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = 'Create Project';
            }
        });

        // initialize
        document.addEventListener('DOMContentLoaded', function(){
            loadSavedProjects();
        });
    </script>
</body>
</html>
