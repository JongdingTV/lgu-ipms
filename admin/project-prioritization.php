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
        header('Location: project-prioritization.php?status=invalid');
        exit;
    }
    
    $stmt = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $new_status, $feedback_id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header('Location: project-prioritization.php?status=updated');
            exit;
        }
        header('Location: project-prioritization.php?status=failed');
        exit;
    }
    header('Location: project-prioritization.php?status=failed');
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
$reviewedInputs = 0;
$addressedInputs = 0;
$oldestPendingDays = 0;
foreach ($feedbacks as $fb) {
    if (isset($fb['category']) && strtolower($fb['category']) === 'critical') $criticalInputs++;
    if (isset($fb['category']) && strtolower($fb['category']) === 'high') $highInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'pending') $pendingInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'reviewed') $reviewedInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'addressed') $addressedInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'pending' && !empty($fb['date_submitted'])) {
        $ts = strtotime($fb['date_submitted']);
        if ($ts) {
            $age = (int) floor((time() - $ts) / 86400);
            if ($age > $oldestPendingDays) $oldestPendingDays = $age;
        }
    }
}

$db->close();
$status_flash = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
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
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    
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
            <h1>Project Prioritization</h1>
            <p>Review and prioritize citizen inputs for infrastructure project planning</p>
        </div>

        <div class="inputs-section prioritization-page">
            <!-- Search & Filter Controls -->
            <div class="feedback-controls">
                <div class="search-group">
                    <label for="fbSearch">Search by Control Number or Name</label>
                    <input id="fbSearch" type="search" placeholder="e.g., CTL-001 or John Doe">
                </div>
                <div class="search-group">
                    <label for="fbStatusFilter">Status</label>
                    <select id="fbStatusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="addressed">Addressed</option>
                    </select>
                </div>
                <div class="search-group">
                    <label for="fbCategoryFilter">Category</label>
                    <select id="fbCategoryFilter">
                        <option value="">All Categories</option>
                        <?php
                        $catSeen = [];
                        foreach ($feedbacks as $fb) {
                            $category = trim((string)($fb['category'] ?? ''));
                            if ($category === '') continue;
                            $key = strtolower($category);
                            if (isset($catSeen[$key])) continue;
                            $catSeen[$key] = true;
                            echo '<option value="' . htmlspecialchars($key) . '">' . htmlspecialchars($category) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="feedback-actions">
                    <button id="clearSearch" class="secondary">Clear</button>
                    <button id="exportData">Export CSV</button>
                    <span id="fbVisibleCount" class="feedback-visible-count">Showing 0 of 0</span>
                </div>
            </div>

            <div class="prioritization-insights">
                <article class="priority-kpi pending">
                    <span>Pending Queue</span>
                    <strong><?= (int)$pendingInputs ?></strong>
                </article>
                <article class="priority-kpi reviewed">
                    <span>Reviewed</span>
                    <strong><?= (int)$reviewedInputs ?></strong>
                </article>
                <article class="priority-kpi addressed">
                    <span>Addressed</span>
                    <strong><?= (int)$addressedInputs ?></strong>
                </article>
                <article class="priority-kpi aging">
                    <span>Oldest Pending</span>
                    <strong><?= (int)$oldestPendingDays ?> day<?= ((int)$oldestPendingDays === 1 ? '' : 's') ?></strong>
                </article>
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
                                <th>Age</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="no-results">
                                        <div class="no-results-icon">📋</div>
                                        <div class="no-results-title">No Feedback Found</div>
                                        <div class="no-results-text">No feedback submitted yet. Please check back later.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1; foreach ($feedbacks as $fb): ?>
                                <?php
                                $fb_lc = array_change_key_case($fb, CASE_LOWER);
                                $rowStatus = strtolower(trim((string)($fb_lc['status'] ?? '')));
                                $rowCategory = strtolower(trim((string)($fb_lc['category'] ?? '')));
                                $rowDays = null;
                                if (!empty($fb_lc['date_submitted'])) {
                                    $rowTs = strtotime((string)$fb_lc['date_submitted']);
                                    if ($rowTs) {
                                        $rowDays = max(0, (int) floor((time() - $rowTs) / 86400));
                                    }
                                }
                                $ageClass = 'fresh';
                                if ($rowDays !== null && $rowDays >= 14) {
                                    $ageClass = 'critical';
                                } elseif ($rowDays !== null && $rowDays >= 7) {
                                    $ageClass = 'warning';
                                }
                                ?>
                                <tr class="<?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ? 'pending-row' : '' ?>"
                                    data-status="<?= htmlspecialchars($rowStatus) ?>"
                                    data-category="<?= htmlspecialchars($rowCategory) ?>">
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
                                        <?php if ($rowDays !== null): ?>
                                            <span class="age-badge <?= $ageClass ?>"><?= (int)$rowDays ?> day<?= ((int)$rowDays === 1 ? '' : 's') ?></span>
                                        <?php else: ?>
                                            <span class="age-badge fresh">Today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="copy-btn" data-copy-control="CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?>">Copy #</button>
                                        <button type="button" class="edit-btn" data-edit-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Edit</button>
                                        <button type="button" class="view-btn" data-view-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">View Details</button>
                                    </td>
                                </tr>
                            <?php $count++; endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($feedbacks)): ?>
                <div id="prioritizationModalRoot">
                    <?php $count = 1; foreach ($feedbacks as $fb): ?>
                        <?php $fb_lc = array_change_key_case($fb, CASE_LOWER); ?>
                        <div id="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Update Feedback Status</h2>
                                    <button type="button" class="modal-close" data-close-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">&times;</button>
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
                                        <button type="button" class="modal-btn modal-btn-close" data-close-modal="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Cancel</button>
                                        <button type="submit" name="update_status" class="modal-btn modal-btn-action">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Feedback Details</h2>
                                    <button type="button" class="modal-close" data-close-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">&times;</button>
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
                                    <button type="button" class="modal-btn modal-btn-close" data-close-modal="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">Close</button>
                                </div>
                            </div>
                        </div>
                    <?php $count++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="summary-section prioritization-summary">
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

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script>
    (function () {
        if (!location.pathname.endsWith('/project-prioritization.php')) return;

        function openModalById(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            document.querySelectorAll('.modal.show').forEach((m) => m.classList.remove('show'));
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModalById(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('show');
            if (!document.querySelector('.modal.show')) {
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('click', function (e) {
            const editBtn = e.target.closest('[data-edit-modal]');
            if (editBtn) {
                openModalById(editBtn.getAttribute('data-edit-modal'));
                return;
            }

            const viewBtn = e.target.closest('[data-view-modal]');
            if (viewBtn) {
                openModalById(viewBtn.getAttribute('data-view-modal'));
                return;
            }

            const closeBtn = e.target.closest('[data-close-modal]');
            if (closeBtn) {
                closeModalById(closeBtn.getAttribute('data-close-modal'));
                return;
            }

            const overlay = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
            if (overlay) {
                overlay.classList.remove('show');
                if (!document.querySelector('.modal.show')) document.body.style.overflow = '';
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.modal.show').forEach((m) => m.classList.remove('show'));
            document.body.style.overflow = '';
        });

        // Expose compatibility functions used elsewhere
        window.openModal = openModalById;
        window.closeModal = closeModalById;
        window.openEditModal = openModalById;
        window.closeEditModal = closeModalById;

        const searchInput = document.getElementById('fbSearch');
        const statusFilter = document.getElementById('fbStatusFilter');
        const categoryFilter = document.getElementById('fbCategoryFilter');
        const visibleCount = document.getElementById('fbVisibleCount');
        const rows = Array.from(document.querySelectorAll('#inputsTable tbody tr')).filter((row) => !row.querySelector('.no-results'));

        function showTinyToast(title, text, isError) {
            const toast = document.createElement('div');
            toast.className = 'prioritization-toast ' + (isError ? 'is-error' : 'is-success');
            toast.innerHTML = '<strong>' + title + '</strong><span>' + text + '</span>';
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 220);
            }, 2000);
        }

        function applyFeedbackFilters() {
            const query = (searchInput?.value || '').trim().toLowerCase();
            const status = (statusFilter?.value || '').trim().toLowerCase();
            const category = (categoryFilter?.value || '').trim().toLowerCase();
            let shown = 0;

            rows.forEach((row) => {
                const haystack = row.textContent.toLowerCase();
                const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                const rowCategory = (row.getAttribute('data-category') || '').toLowerCase();
                const matchQuery = !query || haystack.includes(query);
                const matchStatus = !status || rowStatus === status;
                const matchCategory = !category || rowCategory === category;
                const visible = matchQuery && matchStatus && matchCategory;
                row.style.display = visible ? '' : 'none';
                if (visible) shown++;
            });

            if (visibleCount) {
                visibleCount.textContent = 'Showing ' + shown + ' of ' + rows.length;
            }
        }

        const statusFlash = <?php echo json_encode($status_flash); ?>;
        if (statusFlash) {
            const toast = document.createElement('div');
            toast.className = 'prioritization-toast ' + (statusFlash === 'updated' ? 'is-success' : 'is-error');
            toast.innerHTML = statusFlash === 'updated'
                ? '<strong>Success</strong><span>Feedback status has been updated.</span>'
                : statusFlash === 'invalid'
                    ? '<strong>Invalid Status</strong><span>Please choose a valid status and try again.</span>'
                    : '<strong>Update Failed</strong><span>Unable to save changes. Please try again.</span>';
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 220);
            }, 3200);

            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('status');
            history.replaceState({}, '', cleanUrl.toString());
        }

        document.addEventListener('click', function (e) {
            const copyBtn = e.target.closest('[data-copy-control]');
            if (!copyBtn) return;
            const controlNumber = copyBtn.getAttribute('data-copy-control');
            if (!controlNumber) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(controlNumber).then(() => {
                    showTinyToast('Control Number Copied', controlNumber, false);
                }).catch(() => {
                    showTinyToast('Copy Failed', 'Please copy manually.', true);
                });
            } else {
                showTinyToast('Copy Not Supported', 'Your browser does not support clipboard.', true);
            }
        });

        searchInput?.addEventListener('input', applyFeedbackFilters);
        statusFilter?.addEventListener('change', applyFeedbackFilters);
        categoryFilter?.addEventListener('change', applyFeedbackFilters);

        const clearBtn = document.getElementById('clearSearch');
        clearBtn?.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (categoryFilter) categoryFilter.value = '';
            applyFeedbackFilters();
        });

        applyFeedbackFilters();
    })();
    </script>
</body>
</html>


















