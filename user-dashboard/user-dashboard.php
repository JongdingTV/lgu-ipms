<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    die('Database connection failed: ' . ($db->connect_error ?? 'Unknown error'));
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$stmt = $db->prepare('SELECT first_name, last_name, email, address FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userRes = $stmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : [];
$stmt->close();

$userName = trim($_SESSION['user_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$userEmail = $user['email'] ?? '';
$canAccessFeedback = user_feedback_access_allowed($db, $userId);
$verificationStatus = user_feedback_verification_status($db, $userId);
$feedbackLockRequested = isset($_GET['feedback_locked']) && $_GET['feedback_locked'] === '1';
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

function user_dashboard_projects_has_created_at(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = 'created_at'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

function user_dashboard_extract_location_terms(string $address): array
{
    $address = strtolower(trim($address));
    if ($address === '') {
        return [];
    }
    $normalized = preg_replace('/[^a-z0-9,\-\s]/', ' ', $address) ?? '';
    $parts = preg_split('/[\s,]+/', $normalized) ?: [];
    $stop = [
        'quezon', 'city', 'metro', 'manila', 'philippines', 'barangay', 'brgy',
        'district', 'street', 'st', 'road', 'rd', 'avenue', 'ave', 'block', 'lot',
        'phase', 'unit', 'house', 'number', 'no', 'near', 'zone'
    ];
    $terms = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || strlen($part) < 4 || in_array($part, $stop, true) || ctype_digit($part)) {
            continue;
        }
        $terms[$part] = true;
        if (count($terms) >= 8) {
            break;
        }
    }
    return array_keys($terms);
}

$recentOrder = user_dashboard_projects_has_created_at($db) ? 'created_at DESC' : 'id DESC';
$hasBarangay = user_table_has_column($db, 'projects', 'barangay');
$selectBarangay = $hasBarangay ? ', barangay' : ", '' AS barangay";
$rawProjects = [];
$resProjects = $db->query("SELECT id, name, location, status, province{$selectBarangay} FROM projects ORDER BY {$recentOrder} LIMIT 500");
if ($resProjects) {
    while ($row = $resProjects->fetch_assoc()) {
        $rawProjects[] = $row;
    }
    $resProjects->free();
}

$locationTerms = user_dashboard_extract_location_terms((string)($user['address'] ?? ''));
$scopedProjects = [];
if (!empty($locationTerms)) {
    foreach ($rawProjects as $row) {
        $hay = strtolower(trim(
            (string)($row['location'] ?? '') . ' ' .
            (string)($row['province'] ?? '') . ' ' .
            (string)($row['barangay'] ?? '')
        ));
        foreach ($locationTerms as $term) {
            if ($term !== '' && strpos($hay, $term) !== false) {
                $scopedProjects[] = $row;
                break;
            }
        }
    }
}
$visibleProjects = (!empty($locationTerms) && !empty($scopedProjects)) ? $scopedProjects : $rawProjects;

$totalProjects = count($visibleProjects);
$inProgressProjects = 0;
$completedProjects = 0;
$closedProjects = 0;
foreach ($visibleProjects as $p) {
    $s = strtolower(trim((string)($p['status'] ?? '')));
    if ($s === 'completed') {
        $completedProjects++;
    } elseif ($s === 'cancelled') {
        $closedProjects++;
    } else {
        $inProgressProjects++;
    }
}
$recentProjects = array_slice($visibleProjects, 0, 5);
$dashboardUpdatedAt = date('M d, Y h:i A');

$feedbackStmt = null;
$hasFeedbackUserIdStmt = $db->prepare(
    "SELECT 1
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'feedback'
       AND COLUMN_NAME = 'user_id'
     LIMIT 1"
);
$hasFeedbackUserId = false;
if ($hasFeedbackUserIdStmt) {
    $hasFeedbackUserIdStmt->execute();
    $hasFeedbackUserIdRes = $hasFeedbackUserIdStmt->get_result();
    $hasFeedbackUserId = $hasFeedbackUserIdRes && $hasFeedbackUserIdRes->num_rows > 0;
    if ($hasFeedbackUserIdRes) {
        $hasFeedbackUserIdRes->free();
    }
    $hasFeedbackUserIdStmt->close();
}

$feedbackStmt = $hasFeedbackUserId
    ? $db->prepare('SELECT subject, category, status, date_submitted FROM feedback WHERE user_id = ? ORDER BY date_submitted DESC LIMIT 10')
    : $db->prepare('SELECT subject, category, status, date_submitted FROM feedback WHERE user_name = ? ORDER BY date_submitted DESC LIMIT 10');
if ($hasFeedbackUserId) {
    $feedbackStmt->bind_param('i', $userId);
} else {
    $feedbackStmt->bind_param('s', $userName);
}
$feedbackStmt->execute();
$userFeedback = $feedbackStmt->get_result();
$feedbackStmt->close();

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="/assets/css/dashboard-redesign-enhanced.css">
    <link rel="stylesheet" href="/assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="/user-dashboard/user-shell.css?v=<?php echo filemtime(__DIR__ . '/user-shell.css'); ?>">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div class="nav-logo">
            <img src="/assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-user-profile">
            <?php if ($profileImageWebPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($profileImageWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="user-profile-image">
            <?php else: ?>
                <div class="user-initial-badge" style="background: <?php echo htmlspecialchars($avatarColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="nav-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="nav-user-email"><?php echo htmlspecialchars($userEmail ?: 'No email provided', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="nav-links">
            <a href="user-dashboard.php" class="active"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring Icon" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/assets/images/admin/prioritization.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 3.63 17l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82L4.3 6.46A2 2 0 1 1 7.12 3.63l.06.06A1.65 1.65 0 0 0 9 4.6c.38 0 .74-.13 1-.37.27-.24.42-.58.42-.94V3a2 2 0 1 1 4 0v.09c0 .36.15.7.42.94.26.24.62.37 1 .37a1.65 1.65 0 0 0 1.82-.33l.06-.06A2 2 0 1 1 20.37 7.1l-.06.06A1.65 1.65 0 0 0 19.4 9c0 .38.13.74.37 1 .24.27.58.42.94.42H21a2 2 0 1 1 0 4h-.09c-.36 0-.7.15-.94.42-.24.26-.37.62-.37 1z"></path></svg> Settings</a>
        </div>

        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/logout.php" class="btn-logout nav-logout">
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

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>User Dashboard</h1>
            <p>Transparent project updates from the admin side.</p>
            <p style="margin:6px 0 0;font-size:.82rem;color:#64748b;">Last updated: <?php echo htmlspecialchars($dashboardUpdatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php if (!$canAccessFeedback): ?>
            <p style="margin:0 0 14px;color:#92400e;font-weight:600;">Your account can view all public project information. Verify your ID to unlock feedback submission.</p>
        <?php endif; ?>

        <div class="metrics-container">
            <div class="metric-card card">
                <img src="/assets/images/admin/chart.png" alt="Total Projects" class="metric-icon">
                <div class="metric-content">
                    <h3>Projects in Your Area</h3>
                    <p class="metric-value"><?php echo $totalProjects; ?></p>
                    <span class="metric-status">Active and Completed</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="/assets/images/admin/sandclock.png" alt="In Progress" class="metric-icon">
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value"><?php echo $inProgressProjects; ?></p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="/assets/images/admin/check.png" alt="Completed" class="metric-icon">
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value"><?php echo $completedProjects; ?></p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="/assets/images/admin/list.png" alt="Closed" class="metric-icon">
                <div class="metric-content">
                    <h3>Closed</h3>
                    <p class="metric-value"><?php echo $closedProjects; ?></p>
                    <span class="metric-status">Archived or cancelled</span>
                </div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-box card">
                <h3>Project Status Distribution</h3>
                <div class="chart-placeholder">
                    <div class="progress-bar" aria-hidden="true"><div class="progress-fill" id="statusStackBar" style="width:0%;"></div></div>
                    <div class="status-legend">
                        <div class="legend-item"><span class="legend-color" style="background:#16a34a;"></span><span id="completedPercent">Completed: 0%</span></div>
                        <div class="legend-item"><span class="legend-color" style="background:#2563eb;"></span><span id="inProgressPercent">In Progress: 0%</span></div>
                        <div class="legend-item"><span class="legend-color" style="background:#f59e0b;"></span><span id="otherPercent">Other: 0%</span></div>
                    </div>
                </div>
            </div>
            <div class="chart-box card">
                <h3>Monthly Project Activity</h3>
                <div class="chart-placeholder">
                    <svg id="monthlyActivityChart" viewBox="0 0 320 120" role="img" aria-label="Monthly project activity chart">
                        <polyline id="monthlyActivityLine" fill="none" stroke="#1d4ed8" stroke-width="3" points="0,110 320,110"></polyline>
                    </svg>
                    <p id="monthlyActivityText">No monthly activity yet.</p>
                </div>
            </div>
        </div>

        <div class="recent-projects card">
            <h3>Recent Projects</h3>
            <div class="table-wrap dashboard-table-wrap">
                <table class="projects-table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentProjects)): ?>
                            <?php foreach ($recentProjects as $project): ?>
                                <?php
                                $rawStatus = strtolower(trim((string) ($project['status'] ?? '')));
                                $publicStatus = 'Ongoing';
                                $statusColor = 'approved';
                                if ($rawStatus === 'completed') {
                                    $publicStatus = 'Completed';
                                    $statusColor = 'completed';
                                } elseif ($rawStatus === 'cancelled') {
                                    $publicStatus = 'Closed';
                                    $statusColor = 'cancelled';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['location']); ?></td>
                                    <td><span class="status-badge <?php echo $statusColor; ?>"><?php echo htmlspecialchars($publicStatus); ?></span></td>
                                    <td><div class="progress-small"><div class="progress-fill-small" style="width:0%;"></div></div></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="ac-a004b216">No projects registered yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="recent-projects card" style="margin-top:16px;">
            <h3>Your Feedback Review</h3>
            <div class="table-wrap">
                <table class="projects-table" id="userFeedbackTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($userFeedback && $userFeedback->num_rows > 0): ?>
                            <?php while ($fb = $userFeedback->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime((string) $fb['date_submitted'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $fb['subject']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $fb['category']); ?></td>
                                    <td><span class="status-badge pending"><?php echo htmlspecialchars((string) $fb['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="ac-a004b216">No feedback submitted yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php if (!$canAccessFeedback): ?>
    <div id="feedbackVerificationModal" style="position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;z-index:6000;padding:16px;">
        <div style="width:min(92vw,520px);background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 20px 40px rgba(0,0,0,.2);">
            <h3 style="margin:0 0 10px;color:#1e3a8a;">ID Verification Required</h3>
            <p style="margin:0 0 12px;color:#334155;line-height:1.5;">
                Your account is active, but Feedback is locked until your ID is verified by staff.
                Current status: <strong><?php echo htmlspecialchars(ucfirst($verificationStatus), ENT_QUOTES, 'UTF-8'); ?></strong>.
            </p>
            <p style="margin:0 0 14px;color:#64748b;font-size:.92rem;">Once verified, the Feedback section will automatically unlock.</p>
            <div style="display:flex;justify-content:flex-end;">
                <button type="button" id="closeFeedbackVerificationModal" class="ac-f84d9680">OK</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="/user-dashboard/user-dashboard.js?v=<?php echo filemtime(__DIR__ . '/user-dashboard.js'); ?>"></script>
    <?php if (!$canAccessFeedback): ?>
    <script>
    (function () {
        var modal = document.getElementById('feedbackVerificationModal');
        var closeBtn = document.getElementById('closeFeedbackVerificationModal');
        if (!modal || !closeBtn) return;

        var shouldOpen = <?php echo $feedbackLockRequested ? 'true' : 'false'; ?>;
        if (!shouldOpen) {
            try {
                if (!sessionStorage.getItem('verificationReminderShown')) {
                    shouldOpen = true;
                    sessionStorage.setItem('verificationReminderShown', '1');
                }
            } catch (e) {
                shouldOpen = true;
            }
        }

        if (shouldOpen) {
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>



