<?php
// Database connection
$conn = new mysqli('localhost:3307', 'root', '', 'lgu_ipms');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading contractors
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractors') {
    header('Content-Type: application/json');
    
    // Match the actual database column names from contractors-api.php
    $result = $conn->query("SELECT id, company, license, email, phone, status, rating FROM contractors ORDER BY id DESC LIMIT 100");
    $contractors = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contractors[] = $row;
        }
        $result->free();
    } else {
        error_log("Contractors query error: " . $conn->error);
    }
    
    echo json_encode($contractors);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $conn->query("SELECT id, code, name, type, sector, status FROM projects ORDER BY created_at DESC");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contractor') {
    header('Content-Type: application/json');
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contractors WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Contractor deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete contractor: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid contractor ID']);
    }
    exit;
}

$conn->close();
?>
<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="../assets/style.css" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Contractors - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="../project-registration/project_registration.php"><img src="../project-registration/list.png" class="nav-icon">Project Registration</a>
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
            fetch('registered_contractors.php?action=load_contractors&_=' + Date.now())
                .then(res => res.json())
                .then(contractors => {
                    console.log('Contractors loaded:', contractors);
                    allContractors = contractors;
                    renderContractors(contractors);
                })
                .catch(error => {
                    console.error('Error loading contractors:', error);
                    const tbody = document.querySelector('#contractorsTable tbody');
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#c00;">Error loading contractors. Check console.</td></tr>';
                });
        }

        // Load projects from database
        function loadProjects() {
            fetch('registered_contractors.php?action=load_projects&_=' + Date.now())
                .then(res => res.json())
                .then(projects => {
                    allProjects = projects;
                    renderProjects(projects);
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

        searchInput.addEventListener('input', filterContractors);
        statusFilter.addEventListener('change', filterContractors);

        // Render contractors table
        function renderContractors(contractors) {
            const tbody = document.querySelector('#contractorsTable tbody');
            tbody.innerHTML = '';
            
            if (!contractors.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">No contractors found.</td></tr>';
                return;
            }

            contractors.forEach(c => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${c.company || 'N/A'}</strong></td>
                    <td>${c.license || 'N/A'}</td>
                    <td>${c.email || c.phone || 'N/A'}</td>
                    <td><span class="status-badge ${(c.status || '').toLowerCase()}">${c.status || 'N/A'}</span></td>
                    <td>${c.rating ? '⭐ ' + c.rating + '/5' : '—'}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-delete" data-id="${c.id}">🗑️ Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
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
                            fetch('registered_contractors.php', {
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
            tbody.innerHTML = '';
            
            if (!projects.length) {
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

        // Load data on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadContractors();
            loadProjects();
        });
    </script>
</body>
</html>
