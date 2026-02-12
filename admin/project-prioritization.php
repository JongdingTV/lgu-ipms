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
    
    $stmt = $db->prepare("SELECT id, name, description, priority, status, created_at FROM projects ORDER BY priority DESC, created_at DESC LIMIT 100");
    $projects = [];
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle feedback status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = intval($_POST['feedback_id']);
    $new_status = $_POST['new_status'];
    
    // Validate status value
    $allowed_statuses = ['Pending', 'Reviewed', 'Addressed'];
    if (!in_array($new_status, $allowed_statuses)) {
        header('Location: project-prioritization.php?error=invalid_status');
        exit;
    }
    
    $stmt = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $new_status, $feedback_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: project-prioritization.php');
    exit;
}

// Fetch feedback for display with pagination
$offset = 0;
$limit = 50;
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $offset = (intval($_GET['page']) - 1) * $limit;
}

$stmt = $db->prepare("SELECT id, user_name, subject, description, category, location, status, date_submitted FROM feedback ORDER BY date_submitted DESC LIMIT ? OFFSET ?");
if ($stmt) {
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $feedback_result = $stmt->get_result();
    $feedbacks = [];
    while ($row = $feedback_result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    $stmt->close();
} else {
    $feedbacks = [];
}

// Feedback summary stats
$totalInputs = count($feedbacks);
$criticalInputs = 0;
$highInputs = 0;
$pendingInputs = 0;
foreach ($feedbacks as $fb) {
    if (isset($fb['category']) && strtolower($fb['category']) === 'critical') $criticalInputs++;
    if (isset($fb['category']) && strtolower($fb['category']) === 'high') $highInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'pending') $pendingInputs++;
}

$db->close();
?>
<!doctype html>
<html>
<head>
        
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Project Prioritization - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260212d">
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
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Contractors</span></a>
                </div>
            </div>
            <a href="project-prioritization.php" class="active"><img src="../assets/images/admin/prioritization.png" alt="Priority Icon" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                </div>
            </div>
        </div>
        <div class="nav-divider"></div>
        <div class="ac-723b1a7b">
            <a href="/admin/logout.php" class="ac-bb30b003">
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
            <h1>Project Prioritization</h1>
            <p>Review and prioritize citizen inputs for infrastructure project planning</p>
        </div>

        <div class="inputs-section">
            <!-- Search & Filter Controls -->
            <div class="feedback-controls">
                <div class="search-group">
                    <label for="fbSearch">Search by Control Number or Name</label>
                    <input id="fbSearch" type="search" placeholder="e.g., CTL-001 or John Doe">
                </div>
                <div class="feedback-actions">
                    <button id="clearSearch" class="secondary">Clear</button>
                    <button id="exportData">Export CSV</button>
                </div>
            </div>

            <!-- Feedback Table -->
            <div class="card">
                <h2>User Feedback & Concerns</h2>
                <div class="table-wrap">
                    <table id="inputsTable" class="feedback-table">
                        <thead>
                            <tr>
                                <th>Control #</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-results">
                                        <div class="no-results-icon">📋</div>
                                        <div class="no-results-title">No Feedback Found</div>
                                        <div class="no-results-text">No feedback submitted yet. Please check back later.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1; foreach ($feedbacks as $fb): ?>
                                <?php $fb_lc = array_change_key_case($fb, CASE_LOWER); ?>
                                <tr class="<?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ? 'pending-row' : '' ?>">
                                    <td><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></td>
                                    <td><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></td>
                                    <td><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></td>
                                    <td><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></td>
                                    <td><?= isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                            <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="edit-btn" data-onclick="openEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Edit</button>
                                        <button class="view-btn" data-onclick="openModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">View Details</button>
                                    </td>
                                </tr>

                                <!-- Edit Modal for Status -->
                                <div id="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2>Update Feedback Status</h2>
                                            <button class="modal-close" data-onclick="closeEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">&times;</button>
                                        </div>
                                        <form method="post" class="modal-form">
                                            <div class="modal-body">
                                                <div class="modal-field">
                                                    <span class="modal-label">Control Number:</span>
                                                    <div class="modal-value"><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></div>
                                                </div>
                                                <div class="modal-field">
                                                    <span class="modal-label">From:</span>
                                                    <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                                </div>
                                                <div class="modal-field">
                                                    <span class="modal-label">Subject:</span>
                                                    <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                                </div>
                                                <div class="modal-field">
                                                    <label for="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal-label">Change Status:</label>
                                                    <select id="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" name="new_status" class="status-dropdown">
                                                        <option value="Pending" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ?'selected':'' ?>>Pending</option>
                                                        <option value="Reviewed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Reviewed') ?'selected':'' ?>>Reviewed</option>
                                                        <option value="Addressed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Addressed') ?'selected':'' ?>>Addressed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="feedback_id" value="<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">
                                                <button type="button" class="modal-btn modal-btn-close" data-onclick="closeEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Cancel</button>
                                                <button type="submit" name="update_status" class="modal-btn modal-btn-action">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Modal for this feedback -->
                                <div id="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2>Feedback Details</h2>
                                            <button class="modal-close" data-onclick="closeModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="modal-field">
                                                <span class="modal-label">Control Number:</span>
                                                <div class="modal-value"><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Submitted By:</span>
                                                <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Date Submitted:</span>
                                                <div class="modal-value"><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Subject:</span>
                                                <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Category:</span>
                                                <div class="modal-value"><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Location:</span>
                                                <div class="modal-value"><?= isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Status:</span>
                                                <div class="modal-value">
                                                    <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                                        <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Message / Description:</span>
                                                <div class="modal-value"><?= isset($fb_lc['description']) ? htmlspecialchars($fb_lc['description']) : '-' ?></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="modal-btn modal-btn-close" data-onclick="closeModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php $count++; endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="card">
                <h2>Feedback Summary</h2>
                <div class="summary">
                    <div class="stat">
                        <div id="totalInputs"><?= $totalInputs ?></div>
                        <small>Total Feedback</small>
                    </div>
                    <div class="stat">
                        <div id="criticalInputs" class="ac-6e512bc4"><?= $criticalInputs ?></div>
                        <small>Critical Priority</small>
                    </div>
                    <div class="stat">
                        <div id="highInputs" class="ac-aba882c6"><?= $highInputs ?></div>
                        <small>High Priority</small>
                    </div>
                    <div class="stat">
                        <div id="pendingInputs" class="ac-c476a1d0"><?= $pendingInputs ?></div>
                        <small>Pending Status</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>
    <script src="../assets/js/admin.js"></script>
</body>
</html>















