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

// Create Engineer_project_assignments table if it doesn't exist
$db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contractor_id INT NOT NULL,
    project_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (contractor_id, project_id),
    FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
)");

// Handle GET request for loading Engineers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractors') {
    header('Content-Type: application/json');
    
    // Match the actual database column names from contractors-api.php
    $result = $db->query("SELECT id, company, license, email, phone, status, rating FROM contractors ORDER BY id DESC LIMIT 100");
    $Engineers = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $Engineers[] = $row;
        }
        $result->free();
    } else {
        error_log("Engineers query error: " . $db->error);
    }
    
    echo json_encode($Engineers);
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

// Handle POST request for assigning Engineer to project
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
            echo json_encode(['success' => true, 'message' => 'Engineer assigned to project successfully']);
        } else {
            if (strpos($stmt->error, 'Duplicate') !== false) {
                echo json_encode(['success' => false, 'message' => 'This Engineer is already assigned to this project']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign Engineer: ' . $stmt->error]);
            }
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer or project ID']);
    }
    exit;
}

// Handle POST request for removing Engineer from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unassign_contractor') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        $stmt = $db->prepare("DELETE FROM contractor_project_assignments WHERE contractor_id=? AND project_id=?");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Engineer unassigned from project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unassign Engineer']);
        }
        $stmt->close();
    }
    exit;
}

// Handle POST request for deleting Engineer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contractor') {
    header('Content-Type: application/json');

    $contractor_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($contractor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer ID']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->bind_param("i", $contractor_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Engineer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete Engineer']);
    }

    $stmt->close();
    exit;
}

// Handle GET request for loading assigned projects for a Engineer
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
    <title>Registered Engineers - LGU IPMS</title>
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
            
            <!-- Engineers with Submenu -->
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle">
                    <img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">➕</span>
                        <span>Add Engineer</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">👷</span>
                        <span>Registered Engineers</span>
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
            <h1>Registered Engineers</h1>
            <p>Review Engineer records, assign projects, and monitor accreditation status.</p>
        </div>

        <div class="recent-projects contractor-page contractor-registry-shell">
            <div class="contractor-page-head">
                <div>
                    <h3>Engineer Registry</h3>
                    <p>Search, filter, assign, and maintain active Engineers in one workspace.</p>
                </div>
                <div class="contractor-head-tools">
                    <span id="contractorLastSync" class="contractor-last-sync">Last synced: --</span>
                    <button type="button" id="refreshContractorsBtn" class="btn-contractor-secondary">Refresh</button>
                    <button type="button" id="exportContractorsCsvBtn" class="btn-contractor-primary">Export CSV</button>
                </div>
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
                <div id="contractorsCount" class="contractor-count-pill">0 Engineers</div>
            </div>

            <div class="contractor-stats-grid">
                <article class="contractor-stat-card">
                    <span>Total Engineers</span>
                    <strong id="contractorStatTotal">0</strong>
                </article>
                <article class="contractor-stat-card is-active">
                    <span>Active</span>
                    <strong id="contractorStatActive">0</strong>
                </article>
                <article class="contractor-stat-card is-suspended">
                    <span>Suspended</span>
                    <strong id="contractorStatSuspended">0</strong>
                </article>
                <article class="contractor-stat-card is-blacklisted">
                    <span>Blacklisted</span>
                    <strong id="contractorStatBlacklisted">0</strong>
                </article>
                <article class="contractor-stat-card is-rating">
                    <span>Average Rating</span>
                    <strong id="contractorStatAvgRating">0.0</strong>
                </article>
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
                <p class="contractor-subtext">Projects listed below are available for assignment to selected engineers.</p>
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
                <button type="button" id="assignCancelBtn" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="saveAssignments" class="btn-contractor-primary">Save Assignments</button>
            </div>
        </div>
    </div>

    <!-- Projects View Modal -->
    <div id="projectsViewModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="projectsViewTitle">
        <div class="contractor-modal-panel">
            <h2 id="projectsViewTitle"></h2>
            <div id="projectsViewList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="projectsCloseBtn" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>

    <div id="contractorDeleteModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="contractorDeleteTitle">
        <div class="contractor-modal-panel contractor-delete-panel">
            <div class="contractor-delete-head">
                <span class="contractor-delete-icon">!</span>
                <h2 id="contractorDeleteTitle">Delete Engineer?</h2>
            </div>
            <p class="contractor-delete-message">This Engineer and all related assignment records will be permanently deleted.</p>
            <div id="contractorDeleteName" class="contractor-delete-name"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="contractorDeleteCancel" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="contractorDeleteConfirm" class="btn-contractor-danger">Delete Permanently</button>
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
        const lastSyncEl = document.getElementById('contractorLastSync');
        const refreshBtn = document.getElementById('refreshContractorsBtn');
        const exportCsvBtn = document.getElementById('exportContractorsCsvBtn');
        const statTotalEl = document.getElementById('contractorStatTotal');
        const statActiveEl = document.getElementById('contractorStatActive');
        const statSuspendedEl = document.getElementById('contractorStatSuspended');
        const statBlacklistedEl = document.getElementById('contractorStatBlacklisted');
        const statAvgRatingEl = document.getElementById('contractorStatAvgRating');

        const assignmentModal = document.getElementById('assignmentModal');
        const projectsViewModal = document.getElementById('projectsViewModal');
        const assignmentTitle = document.getElementById('assignmentTitle');
        const projectsListEl = document.getElementById('projectsList');
        const projectsViewTitle = document.getElementById('projectsViewTitle');
        const projectsViewList = document.getElementById('projectsViewList');
        const assignContractorId = document.getElementById('assignContractorId');
        const saveAssignmentsBtn = document.getElementById('saveAssignments');
        const assignCancelBtn = document.getElementById('assignCancelBtn');
        const projectsCloseBtn = document.getElementById('projectsCloseBtn');

        const contractorDeleteModal = document.getElementById('contractorDeleteModal');
        const contractorDeleteName = document.getElementById('contractorDeleteName');
        const contractorDeleteCancel = document.getElementById('contractorDeleteCancel');
        const contractorDeleteConfirm = document.getElementById('contractorDeleteConfirm');

        let contractorsCache = [];
        let projectsCache = [];
        let visibleContractors = [];
        let currentAssignedIds = [];

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

        function postApiCandidates() {
            const list = ['registered_contractors.php', '/admin/registered_contractors.php'];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_contractors.php'));
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

        async function postJsonWithFallback(formBody) {
            const urls = postApiCandidates();
            for (const url of urls) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formBody,
                        credentials: 'same-origin'
                    });
                    if (!res.ok) continue;
                    return await res.json();
                } catch (_) {}
            }
            throw new Error('Unable to save data to API');
        }

        function renderContractors(rows) {
            if (!contractorsTbody) return;
            contractorsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];
            visibleContractors = list;

            if (countEl) countEl.textContent = `${list.length} Engineer${list.length === 1 ? '' : 's'}`;

            if (!list.length) {
                contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#6b7280;">No Engineers found.</td></tr>';
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

        function updateStats(rows) {
            const list = Array.isArray(rows) ? rows : [];
            let active = 0;
            let suspended = 0;
            let blacklisted = 0;
            let ratingSum = 0;
            let ratingCount = 0;

            for (const c of list) {
                const status = String(c.status || '').toLowerCase();
                if (status === 'active') active += 1;
                if (status === 'suspended') suspended += 1;
                if (status === 'blacklisted') blacklisted += 1;
                const r = Number(c.rating);
                if (Number.isFinite(r) && r > 0) {
                    ratingSum += r;
                    ratingCount += 1;
                }
            }

            if (statTotalEl) statTotalEl.textContent = String(list.length);
            if (statActiveEl) statActiveEl.textContent = String(active);
            if (statSuspendedEl) statSuspendedEl.textContent = String(suspended);
            if (statBlacklistedEl) statBlacklistedEl.textContent = String(blacklisted);
            if (statAvgRatingEl) statAvgRatingEl.textContent = ratingCount ? (ratingSum / ratingCount).toFixed(1) : '0.0';
        }

        function updateLastSync() {
            if (!lastSyncEl) return;
            const now = new Date();
            const stamp = now.toLocaleString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            lastSyncEl.textContent = `Last synced: ${stamp}`;
        }

        function exportVisibleContractorsCsv() {
            const list = Array.isArray(visibleContractors) ? visibleContractors : [];
            if (!list.length) {
                setMessage('No Engineers to export.', true);
                return;
            }
            const escCsv = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
            const header = ['Company', 'License', 'Email', 'Phone', 'Status', 'Rating'];
            const rows = list.map((c) => [
                c.company || '',
                c.license || '',
                c.email || '',
                c.phone || '',
                c.status || '',
                c.rating || ''
            ]);
            const csv = [header, ...rows].map((r) => r.map(escCsv).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `Engineers-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
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

        function setMessage(text, isError) {
            if (!formMessage) return;
            formMessage.style.display = 'block';
            formMessage.style.color = isError ? '#c00' : '#166534';
            formMessage.textContent = text;
            setTimeout(() => { formMessage.style.display = 'none'; }, 3000);
        }

        function openAssignModal(contractorId, contractorName) {
            if (!assignmentModal) return;
            assignContractorId.value = contractorId;
            assignmentTitle.textContent = `Assign "${contractorName}" to Projects`;
            projectsListEl.innerHTML = '<p style="text-align:center; color:#6b7280;">Loading projects...</p>';
            assignmentModal.style.display = 'flex';
            loadProjectsForAssignment(contractorId);
        }

        function closeAssignModal() {
            if (assignmentModal) assignmentModal.style.display = 'none';
        }

        function openProjectsModal(contractorId, contractorName) {
            if (!projectsViewModal) return;
            projectsViewTitle.textContent = `Projects Assigned to ${contractorName}`;
            projectsViewList.innerHTML = '<p style="text-align:center; color:#6b7280;">Loading projects...</p>';
            projectsViewModal.style.display = 'flex';

            fetchJsonWithFallback(`action=get_assigned_projects&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`)
                .then((rows) => {
                    const list = Array.isArray(rows) ? rows : [];
                    if (!list.length) {
                        projectsViewList.innerHTML = '<p style="text-align:center; color:#6b7280; padding:12px 0;">No Projects Assigned</p>';
                        return;
                    }
                    projectsViewList.innerHTML = list.map((p) => `
                        <div style="padding:12px 14px; margin:8px 0; background:#f0f9ff; border-left:4px solid #3b82f6; border-radius:8px;">
                            <strong>${esc(p.code || '')}</strong> - ${esc(p.name || '')}
                        </div>
                    `).join('');
                })
                .catch(() => {
                    projectsViewList.innerHTML = '<p style="color:#c00;">Failed to load assigned projects.</p>';
                });
        }

        function closeProjectsModal() {
            if (projectsViewModal) projectsViewModal.style.display = 'none';
        }

        function closeDeleteModal() {
            if (contractorDeleteModal) contractorDeleteModal.style.display = 'none';
        }

        function confirmDeleteContractor(contractorName) {
            return new Promise((resolve) => {
                if (!contractorDeleteModal) {
                    resolve(window.confirm(`Delete ${contractorName}? This cannot be undone.`));
                    return;
                }
                contractorDeleteName.textContent = contractorName || 'Selected Engineer';
                contractorDeleteModal.style.display = 'flex';

                const cancel = () => {
                    closeDeleteModal();
                    contractorDeleteCancel?.removeEventListener('click', onCancel);
                    contractorDeleteConfirm?.removeEventListener('click', onConfirm);
                    contractorDeleteModal?.removeEventListener('click', onBackdrop);
                    resolve(false);
                };
                const confirm = () => {
                    closeDeleteModal();
                    contractorDeleteCancel?.removeEventListener('click', onCancel);
                    contractorDeleteConfirm?.removeEventListener('click', onConfirm);
                    contractorDeleteModal?.removeEventListener('click', onBackdrop);
                    resolve(true);
                };
                const onCancel = () => cancel();
                const onConfirm = () => confirm();
                const onBackdrop = (e) => { if (e.target === contractorDeleteModal) cancel(); };

                contractorDeleteCancel?.addEventListener('click', onCancel);
                contractorDeleteConfirm?.addEventListener('click', onConfirm);
                contractorDeleteModal?.addEventListener('click', onBackdrop);
            });
        }

        async function loadProjectsForAssignment(contractorId) {
            try {
                const [assigned, allProjects] = await Promise.all([
                    fetchJsonWithFallback(`action=get_assigned_projects&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`),
                    fetchJsonWithFallback(`action=load_projects&_=${Date.now()}`)
                ]);
                const assignedSet = new Set((Array.isArray(assigned) ? assigned : []).map((p) => String(p.id)));
                currentAssignedIds = Array.from(assignedSet);
                projectsCache = Array.isArray(allProjects) ? allProjects : [];

                if (!projectsCache.length) {
                    projectsListEl.innerHTML = '<p style="text-align:center; color:#6b7280;">No projects available.</p>';
                    return;
                }

                projectsListEl.innerHTML = projectsCache.map((p) => {
                    const pid = String(p.id);
                    const checked = assignedSet.has(pid) ? 'checked' : '';
                    return `
                        <label style="display:flex; gap:10px; align-items:flex-start; padding:10px 12px; border:1px solid #dbe6f3; border-radius:8px; margin:8px 0; background:#fff;">
                            <input type="checkbox" class="project-checkbox" value="${esc(pid)}" ${checked} style="margin-top:3px;">
                            <span>
                                <strong>${esc(p.code || '')}</strong> - ${esc(p.name || '')}
                                <br><small style="color:#64748b;">${esc(p.type || 'N/A')} • ${esc(p.sector || 'N/A')}</small>
                            </span>
                        </label>
                    `;
                }).join('');
            } catch (_) {
                projectsListEl.innerHTML = '<p style="color:#c00;">Failed to load projects for assignment.</p>';
            }
        }

        async function saveAssignmentsHandler() {
            const contractorId = assignContractorId?.value;
            if (!contractorId) return;
            if (!saveAssignmentsBtn) return;

            saveAssignmentsBtn.disabled = true;
            saveAssignmentsBtn.textContent = 'Saving...';

            try {
                const checkedNow = Array.from(document.querySelectorAll('.project-checkbox:checked')).map((el) => String(el.value));
                const prevSet = new Set(currentAssignedIds);
                const nextSet = new Set(checkedNow);
                const toAssign = checkedNow.filter((id) => !prevSet.has(id));
                const toUnassign = currentAssignedIds.filter((id) => !nextSet.has(id));

                for (const id of toAssign) {
                    await postJsonWithFallback(`action=assign_contractor&contractor_id=${encodeURIComponent(contractorId)}&project_id=${encodeURIComponent(id)}`);
                }
                for (const id of toUnassign) {
                    await postJsonWithFallback(`action=unassign_contractor&contractor_id=${encodeURIComponent(contractorId)}&project_id=${encodeURIComponent(id)}`);
                }

                closeAssignModal();
                setMessage('Assignments updated successfully.', false);
            } catch (e) {
                setMessage(e.message || 'Failed to update assignments.', true);
            } finally {
                saveAssignmentsBtn.disabled = false;
                saveAssignmentsBtn.textContent = 'Save Assignments';
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
        async function loadAllData() {
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing...';
            }
            try {
                const [Engineers, projects] = await Promise.all([
                    fetchJsonWithFallback('action=load_contractors&_=' + Date.now()),
                    fetchJsonWithFallback('action=load_projects&_=' + Date.now())
                ]);
                contractorsCache = Array.isArray(Engineers) ? Engineers : [];
                projectsCache = Array.isArray(projects) ? projects : [];
                updateStats(contractorsCache);
                updateLastSync();
                renderContractors(contractorsCache);
                renderProjects(projectsCache);
            } catch (err) {
                if (contractorsTbody) contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#c00;">Failed to load Engineers data.</td></tr>';
                if (projectsTbody) projectsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px; color:#c00;">Failed to load projects data.</td></tr>';
                if (formMessage) {
                    formMessage.style.display = 'block';
                    formMessage.style.color = '#c00';
                    formMessage.textContent = err.message || 'Failed to load data.';
                }
            } finally {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = 'Refresh';
                }
            }
        }

        async function boot() {
            if (booted) return;
            booted = true;
            await loadAllData();
        }

        searchInput?.addEventListener('input', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);
        contractorsTbody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const contractorId = btn.getAttribute('data-id');
            const row = btn.closest('tr');
            const contractorName = row ? row.querySelector('td:first-child')?.textContent.trim() : 'Engineer';
            if (!contractorId) return;

            if (btn.classList.contains('btn-view-projects')) {
                openProjectsModal(contractorId, contractorName || 'Engineer');
                return;
            }
            if (btn.classList.contains('btn-assign')) {
                openAssignModal(contractorId, contractorName || 'Engineer');
                return;
            }
            if (btn.classList.contains('btn-delete')) {
                const proceed = await confirmDeleteContractor(contractorName);
                if (!proceed) return;

                try {
                    const result = await postJsonWithFallback(`action=delete_contractor&id=${encodeURIComponent(contractorId)}`);
                    if (!result || result.success === false) throw new Error((result && result.message) || 'Delete failed');
                    contractorsCache = contractorsCache.filter((c) => String(c.id) !== String(contractorId));
                    updateStats(contractorsCache);
                    applyFilters();
                    setMessage('Engineer deleted successfully.', false);
                } catch (err) {
                    setMessage(err.message || 'Failed to delete Engineer.', true);
                }
            }
        });

        saveAssignmentsBtn?.addEventListener('click', saveAssignmentsHandler);
        refreshBtn?.addEventListener('click', loadAllData);
        exportCsvBtn?.addEventListener('click', exportVisibleContractorsCsv);
        assignCancelBtn?.addEventListener('click', closeAssignModal);
        projectsCloseBtn?.addEventListener('click', closeProjectsModal);
        assignmentModal?.addEventListener('click', (e) => { if (e.target === assignmentModal) closeAssignModal(); });
        projectsViewModal?.addEventListener('click', (e) => { if (e.target === projectsViewModal) closeProjectsModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            closeAssignModal();
            closeProjectsModal();
            closeDeleteModal();
        });

        window.closeAssignModal = closeAssignModal;
        window.closeProjectsModal = closeProjectsModal;
        window.saveAssignmentsHandler = saveAssignmentsHandler;

        document.addEventListener('DOMContentLoaded', boot);
        if (document.readyState !== 'loading') boot();
    })();
    </script>
</body>
</html>




















