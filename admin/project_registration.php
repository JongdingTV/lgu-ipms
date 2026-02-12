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
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

function project_has_column(mysqli $db, string $columnName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

$projectsHasCreatedAt = project_has_column($db, 'created_at');

function build_db_debug_error(mysqli $db, string $context, string $stmtError = ''): string
{
    $parts = [];
    $parts[] = $context;
    $parts[] = 'db_errno=' . (int)$db->errno;
    $parts[] = 'db_error=' . ($db->error ?: 'n/a');
    if ($stmtError !== '') {
        $parts[] = 'stmt_error=' . $stmtError;
    }
    return implode(' | ', $parts);
}

function is_ajax_request(): bool
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    return false;
}

function respond_project_registration(bool $success, string $message, array $extra = []): void
{
    $payload = array_merge(['success' => $success, 'message' => $message], $extra);
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    // Fallback for normal form POST: redirect back to the form page.
    $query = $success
        ? 'saved=1&msg=' . rawurlencode($message)
        : 'error=' . rawurlencode($message);
    header('Location: project_registration.php?' . $query);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(E_ALL);
    
    if ($_POST['action'] === 'save_project') {
        // Validate required fields
        if (empty($_POST['code']) || empty($_POST['name'])) {
            respond_project_registration(false, 'Project Code and Name are required');
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
            if (!$stmt) {
                $debugError = build_db_debug_error($db, 'Failed to prepare project update');
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
            $stmt->bind_param('sssssssssssidssi', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status, $id);
        } else {
            // Insert new project; support schemas with or without created_at.
            if ($projectsHasCreatedAt) {
                $stmt = $db->prepare("INSERT INTO projects (code, name, type, sector, description, priority, province, barangay, location, start_date, end_date, duration_months, budget, project_manager, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    $debugError = build_db_debug_error($db, 'Failed to prepare project insert (with created_at)');
                    error_log('[project_registration] ' . $debugError);
                    respond_project_registration(false, $debugError);
                }
                $stmt->bind_param('sssssssssssidss', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status);
            } else {
                $stmt = $db->prepare("INSERT INTO projects (code, name, type, sector, description, priority, province, barangay, location, start_date, end_date, duration_months, budget, project_manager, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $debugError = build_db_debug_error($db, 'Failed to prepare project insert (without created_at)');
                    error_log('[project_registration] ' . $debugError);
                    respond_project_registration(false, $debugError);
                }
                $stmt->bind_param('sssssssssssidss', $code, $name, $type, $sector, $description, $priority, $province, $barangay, $location, $start_date, $end_date, $duration_months, $budget, $project_manager, $status);
            }
        }
        
        try {
            $executed = $stmt->execute();
            if ($executed) {
                $savedId = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : (int)$db->insert_id;
                respond_project_registration(true, 'Project saved successfully', ['project_id' => $savedId]);
            } else {
                $debugError = build_db_debug_error($db, 'Failed to save project', $stmt->error);
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                respond_project_registration(false, 'Project already exists. Please try again with a different Project Code.');
            } else {
                $debugError = build_db_debug_error($db, 'Failed to save project (exception)', $e->getMessage());
                error_log('[project_registration] ' . $debugError);
                respond_project_registration(false, $debugError);
            }
        }
        if ($stmt) $stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'delete_project') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM projects WHERE id=?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            respond_project_registration(true, 'Project deleted successfully');
        } else {
            respond_project_registration(false, 'Failed to delete project: ' . $db->error);
        }
        $stmt->close();
        exit;
    }
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $orderBy = $projectsHasCreatedAt ? 'created_at DESC' : 'id DESC';
    $result = $db->query("SELECT * FROM projects ORDER BY {$orderBy}");
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
    <title>Project Registration - LGU IPMS</title>
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
    <link rel="stylesheet" href="../assets/css/form-redesign-base.css">
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
                    <a href="contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">➕</span>
                        <span>Add Contractor</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">📋</span>
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
            <h1>Project Registration</h1>
            <p>Create new infrastructure projects</p>
        </div>

        <div class="recent-projects">
            <h3>New Project Form</h3>

            <form id="projectForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_project">
                <!-- Basic project details -->
                <fieldset>
                    <legend>Basic Project Details</legend>
                    <label for="projCode">Project Code / Reference ID</label>
                    <input type="text" id="projCode" name="code" required>

                    <label for="projName">Project Name</label>
                    <input type="text" id="projName" name="name" required>

                    <label for="projType">Project Type</label>
                    <select id="projType" name="type" required>
                        <option value="">-- Select --</option>
                        <option>New</option>
                        <option>Rehabilitation</option>
                        <option>Expansion</option>
                        <option>Maintenance</option>
                    </select>

                    <label for="projSector">Sector</label>
                    <select id="projSector" name="sector" required>
                        <option value="">-- Select --</option>
                        <option>Road</option>
                        <option>Drainage</option>
                        <option>Building</option>
                        <option>Water</option>
                        <option>Sanitation</option>
                        <option>Other</option>
                    </select>

                    <label for="projDescription">Project Description / Objective</label>
                    <textarea id="projDescription" name="description" rows="3"></textarea>

                    <label for="projPriority">Priority Level</label>
                    <select id="projPriority" name="priority">
                        <option>High</option>
                        <option>Medium</option>
                        <option>Low</option>
                    </select>
                </fieldset>

                <!-- Location -->
                <fieldset>
                    <legend>Location</legend>
                    <label for="province">Province / City / Municipality</label>
                    <input type="text" id="province" name="province" required>

                    <label for="barangay">Barangay(s)</label>
                    <input type="text" id="barangay" name="barangay">

                    <label for="projLocation">Exact Site / Address</label>
                    <input type="text" id="projLocation" name="location" required>
                </fieldset>

                <!-- Schedule -->
                <fieldset>
                    <legend>Schedule</legend>
                    <label for="startDate">Estimated Start Date</label>
                    <input type="date" id="startDate" name="start_date">

                    <label for="endDate">Estimated End Date</label>
                    <input type="date" id="endDate" name="end_date">

                    <label for="projDuration">Estimated Duration (months)</label>
                    <input type="number" id="projDuration" name="duration_months" min="0" required>
                </fieldset>

                <!-- Budget -->
                <fieldset>
                    <legend>Budget</legend>
                    <label for="projBudget">Total Estimated Cost</label>
                    <input type="number" id="projBudget" name="budget" min="0" step="0.01" required>
                </fieldset>

                <!-- Implementation -->
                <fieldset>
                    <legend>Implementation</legend>
                    <label for="projManager">Project Manager / Engineer In-Charge</label>
                    <input type="text" id="projManager" name="project_manager" placeholder="Name">
                </fieldset>

                <!-- Status -->
                <fieldset>
                    <legend>Status</legend>
                    <label for="status">Approval Status</label>
                    <select id="status" name="status">
                        <option>Draft</option>
                        <option>For Approval</option>
                        <option>Approved</option>
                        <option>On-hold</option>
                        <option>Cancelled</option>
                    </select>
                </fieldset>

                <div class="ac-9374e842">
                    <button type="submit" id="submitBtn">
                        Create Project
                    </button>
                    <button type="button" id="resetBtn">
                        Reset
                    </button>
                </div>
            </form>

            <div id="formMessage" class="ac-133c5402"></div>
        </div>
    </section>

    <div id="projectNoticeModal" class="project-notice-modal" aria-hidden="true">
        <div class="project-notice-card" role="alertdialog" aria-modal="true" aria-labelledby="projectNoticeTitle">
            <div class="project-notice-head">
                <div id="projectNoticeIcon" class="project-notice-icon">i</div>
                <h3 id="projectNoticeTitle">Notice</h3>
            </div>
            <p id="projectNoticeText" class="project-notice-text"></p>
            <div class="project-notice-actions">
                <button type="button" id="projectNoticeOk" class="btn-primary">OK</button>
            </div>
        </div>
    </div>

    <style>
        .project-notice-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 4000;
            padding: 16px;
        }

        .project-notice-modal.show {
            display: flex;
        }

        .project-notice-card {
            width: min(96vw, 460px);
            border-radius: 14px;
            border: 1px solid #dbe5f1;
            background: #fff;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.3);
            padding: 18px;
        }

        .project-notice-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .project-notice-icon {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: #fff;
            background: #1d4ed8;
        }

        .project-notice-card.success .project-notice-icon {
            background: #16a34a;
        }

        .project-notice-card.error .project-notice-icon {
            background: #dc2626;
        }

        .project-notice-head h3 {
            margin: 0;
            color: #0f172a;
            font-size: 1.15rem;
        }

        .project-notice-text {
            margin: 0;
            color: #334155;
            line-height: 1.45;
        }

        .project-notice-actions {
            margin-top: 14px;
            display: flex;
            justify-content: flex-end;
        }
    </style>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script>
    (function () {
        const params = new URLSearchParams(window.location.search);
        const saved = params.get('saved');
        const error = params.get('error');
        const savedMsg = params.get('msg') || 'Project has been added successfully.';
        const formMsg = document.getElementById('formMessage');
        const noticeModal = document.getElementById('projectNoticeModal');
        const noticeCard = noticeModal ? noticeModal.querySelector('.project-notice-card') : null;
        const noticeIcon = document.getElementById('projectNoticeIcon');
        const noticeTitle = document.getElementById('projectNoticeTitle');
        const noticeText = document.getElementById('projectNoticeText');
        const noticeOk = document.getElementById('projectNoticeOk');

        function showProjectNotice(message, type) {
            if (!noticeModal || !noticeCard || !noticeText || !noticeTitle || !noticeIcon) {
                alert(message);
                return;
            }
            noticeCard.classList.remove('success', 'error');
            if (type === 'success') {
                noticeCard.classList.add('success');
                noticeTitle.textContent = 'Success';
                noticeIcon.textContent = '✓';
            } else if (type === 'error') {
                noticeCard.classList.add('error');
                noticeTitle.textContent = 'Unable to Save';
                noticeIcon.textContent = '!';
            } else {
                noticeTitle.textContent = 'Notice';
                noticeIcon.textContent = 'i';
            }
            noticeText.textContent = message || '';
            noticeModal.classList.add('show');
            noticeModal.setAttribute('aria-hidden', 'false');
            if (noticeOk) noticeOk.focus();
        }

        function closeProjectNotice() {
            if (!noticeModal) return;
            noticeModal.classList.remove('show');
            noticeModal.setAttribute('aria-hidden', 'true');
        }

        window.showProjectNotice = showProjectNotice;

        if (saved === '1') {
            if (formMsg) {
                formMsg.textContent = savedMsg;
                formMsg.style.display = 'block';
                formMsg.style.color = '#0b5';
            }
            if (!window.__projectRegPopupShown) {
                window.__projectRegPopupShown = true;
                showProjectNotice(savedMsg, 'success');
            }
        } else if (error) {
            let errText = decodeURIComponent(error);
            if (/already exists|duplicate/i.test(errText)) {
                errText = 'Project already exists. Please try again with a different Project Code.';
            }
            if (formMsg) {
                formMsg.textContent = errText;
                formMsg.style.display = 'block';
                formMsg.style.color = '#f00';
            }
            if (!window.__projectRegPopupShown) {
                window.__projectRegPopupShown = true;
                showProjectNotice(errText, 'error');
            }
        }

        if (noticeOk) {
            noticeOk.addEventListener('click', closeProjectNotice);
        }
        if (noticeModal) {
            noticeModal.addEventListener('click', function (e) {
                if (e.target === noticeModal) closeProjectNotice();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeProjectNotice();
        });
    })();
    </script>
</body>
</html>


















