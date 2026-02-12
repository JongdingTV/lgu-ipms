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

// Handle POST request for deleting contractor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contractor') {
    header('Content-Type: application/json');

    $contractor_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($contractor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid contractor ID']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->bind_param("i", $contractor_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Contractor deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete contractor']);
    }

    $stmt->close();
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
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Contractors - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/table-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
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
            <a href="project_registration.php"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration    ▼</a>
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
                    <a href="registered_contractors.php" class="nav-submenu-item active">
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
            <h1>Registered Contractors</h1>
            <p>Review contractor records, assign projects, and monitor accreditation status.</p>
        </div>

        <div class="recent-projects contractor-page contractor-registry-shell">
            <div class="contractor-page-head">
                <h3>Contractor Registry</h3>
                <p>Search, filter, assign, and maintain active contractors in one workspace.</p>
            </div>

            <div class="contractors-filter contractor-toolbar">
                <input
                    type="search"
                    id="searchContractors"
                    placeholder="Search by company, license, email, or phone"
                >
                <select id="filterStatus">
                    <option value="">All Status</option>
                    <option>Active</option>
                    <option>Suspended</option>
                    <option>Blacklisted</option>
                </select>
                <div id="contractorsCount" class="contractor-count-pill">0 contractors</div>
            </div>

            <div class="contractors-section">
                <div id="formMessage" class="contractor-form-message" role="status" aria-live="polite"></div>
                
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

            <div class="projects-section contractor-project-bank" id="available-projects">
                <h3>Available Projects</h3>
                <p class="contractor-subtext">Projects listed below are available for assignment to selected contractors.</p>
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

<!-- Assignment Modal -->
    <div id="assignmentModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="assignmentTitle">
        <div class="contractor-modal-panel">
            <input type="hidden" id="assignContractorId" value="">
            <h2 id="assignmentTitle"></h2>
            <div id="projectsList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" data-onclick="closeAssignModal()" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="saveAssignments" data-onclick="saveAssignmentsHandler()" class="btn-contractor-primary">Save Assignments</button>
            </div>
        </div>
    </div>

    <!-- Projects View Modal -->
    <div id="projectsViewModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="projectsViewTitle">
        <div class="contractor-modal-panel">
            <h2 id="projectsViewTitle"></h2>
            <div id="projectsViewList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" data-onclick="closeProjectsModal()" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script>
    (function () {
        if (!location.pathname.endsWith('/registered_contractors.php')) return;

        const contractorsTbody = document.querySelector('#contractorsTable tbody');
        const projectsTbody = document.querySelector('#projectsTable tbody');
        const searchInput = document.getElementById('searchContractors');
        const statusFilter = document.getElementById('filterStatus');
        const countEl = document.getElementById('contractorsCount');
        const formMessage = document.getElementById('formMessage');

        let contractorsCache = [];

        function esc(v) {
            return String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function apiCandidates(query) {
            const list = ['registered_contractors.php?' + query, '/admin/registered_contractors.php?' + query];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_contractors.php?' + query));
            }
            return Array.from(new Set(list));
        }

        async function fetchJsonWithFallback(query) {
            const urls = apiCandidates(query);
            for (const url of urls) {
                try {
                    const res = await fetch(url, { credentials: 'same-origin' });
                    if (!res.ok) continue;
                    const text = await res.text();
                    return JSON.parse(text);
                } catch (_) {}
            }
            throw new Error('Unable to load data from API');
        }

        function renderContractors(rows) {
            if (!contractorsTbody) return;
            contractorsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];

            if (countEl) countEl.textContent = `${list.length} contractor${list.length === 1 ? '' : 's'}`;

            if (!list.length) {
                contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#6b7280;">No contractors found.</td></tr>';
                return;
            }

            for (const c of list) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${esc(c.company || 'N/A')}</strong></td>
                    <td>${esc(c.license || 'N/A')}</td>
                    <td>${esc(c.email || c.phone || 'N/A')}</td>
                    <td><span class="status-badge ${esc(String(c.status || '').toLowerCase().replace(/\s+/g, '-'))}">${esc(c.status || 'N/A')}</span></td>
                    <td>${c.rating ? Number(c.rating).toFixed(1) + '/5' : '-'}</td>
                    <td><button class="btn-view-projects" data-id="${esc(c.id)}">View Projects</button></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-assign" data-id="${esc(c.id)}">Assign Projects</button>
                            <button class="btn-delete" data-id="${esc(c.id)}">Delete</button>
                        </div>
                    </td>
                `;
                contractorsTbody.appendChild(tr);
            }
        }

        function renderProjects(rows) {
            if (!projectsTbody) return;
            projectsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];
            if (!list.length) {
                projectsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px; color:#6b7280;">No projects available.</td></tr>';
                return;
            }

            for (const p of list) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${esc(p.code || '')}</td>
                    <td>${esc(p.name || '')}</td>
                    <td>${esc(p.type || '')}</td>
                    <td>${esc(p.sector || '')}</td>
                    <td><span class="status-badge ${esc(String(p.status || '').toLowerCase().replace(/\s+/g, '-'))}">${esc(p.status || 'N/A')}</span></td>
                `;
                projectsTbody.appendChild(tr);
            }
        }

        function applyFilters() {
            const q = (searchInput?.value || '').trim().toLowerCase();
            const s = (statusFilter?.value || '').trim();
            const filtered = contractorsCache.filter((c) => {
                const hitSearch = !q || `${c.company || ''} ${c.license || ''} ${c.email || ''} ${c.phone || ''}`.toLowerCase().includes(q);
                const hitStatus = !s || String(c.status || '') === s;
                return hitSearch && hitStatus;
            });
            renderContractors(filtered);
        }

        let booted = false;
        async function boot() {
            if (booted) return;
            booted = true;
            try {
                const [contractors, projects] = await Promise.all([
                    fetchJsonWithFallback('action=load_contractors&_=' + Date.now()),
                    fetchJsonWithFallback('action=load_projects&_=' + Date.now())
                ]);
                contractorsCache = Array.isArray(contractors) ? contractors : [];
                renderContractors(contractorsCache);
                renderProjects(Array.isArray(projects) ? projects : []);
            } catch (err) {
                if (contractorsTbody) contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#c00;">Failed to load contractors data.</td></tr>';
                if (projectsTbody) projectsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px; color:#c00;">Failed to load projects data.</td></tr>';
                if (formMessage) {
                    formMessage.style.display = 'block';
                    formMessage.style.color = '#c00';
                    formMessage.textContent = err.message || 'Failed to load data.';
                }
            }
        }

        searchInput?.addEventListener('input', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);
        document.addEventListener('DOMContentLoaded', boot);
        if (document.readyState !== 'loading') boot();
    })();
    </script>
</body>
</html>




















