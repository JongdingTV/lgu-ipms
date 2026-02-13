<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userStmt = $db->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : [];
$userStmt->close();

$userName = trim($_SESSION['user_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$userEmail = $user['email'] ?? '';
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    header('Content-Type: application/json');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token. Please refresh and try again.']);
        $db->close();
        exit;
    }

    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['feedback'] ?? '');
    $status = 'Pending';

    if ($subject === '' || $category === '' || $location === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        $db->close();
        exit;
    }

    $stmt = $db->prepare('INSERT INTO feedback (user_name, subject, category, location, description, status) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to submit feedback right now.']);
        $db->close();
        exit;
    }

    $stmt->bind_param('ssssss', $userName, $subject, $category, $location, $description, $status);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Feedback submitted successfully.' : 'Failed to submit feedback.'
    ]);

    $db->close();
    exit;
}

$listStmt = $db->prepare('SELECT subject, category, location, status, date_submitted FROM feedback WHERE user_name = ? ORDER BY date_submitted DESC LIMIT 20');
$listStmt->bind_param('s', $userName);
$listStmt->execute();
$feedbackRows = $listStmt->get_result();
$listStmt->close();

$db->close();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Feedback - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="/assets/css/form-redesign-base.css">
    <link rel="stylesheet" href="/assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="/user-dashboard/user-shell.css?v=<?php echo filemtime(__DIR__ . '/user-shell.css'); ?>">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
    </div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
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
            <a href="user-dashboard.php"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring Icon" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php" class="active"><img src="/assets/images/admin/prioritization.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/assets/images/admin/person.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Submit Feedback</h1>
            <p>Share concerns and suggestions for local projects.</p>
        </div>

        <div class="card" style="margin-bottom:18px;">
            <h3 style="margin-bottom:12px;">Feedback Form</h3>
            <form id="userFeedbackForm" method="post" action="user-feedback.php" enctype="multipart/form-data">
                <input type="hidden" name="feedback_submit" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="ac-a2374ef4">
                    <label class="ac-37c29296" for="subject">Subject</label>
                    <input class="ac-6f762f4a" type="text" id="subject" name="subject" maxlength="100" required>
                </div>

                <div class="ac-a2374ef4">
                    <label class="ac-37c29296" for="location">Location</label>
                    <input class="ac-6f762f4a" type="text" id="location" name="location" required>
                </div>

                <div class="ac-a2374ef4">
                    <label class="ac-37c29296" for="category">Category</label>
                    <select class="ac-6f762f4a" id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Energy">Energy</option>
                        <option value="Water and Waste">Water and Waste</option>
                        <option value="Social Infrastructure">Social Infrastructure</option>
                        <option value="Public Buildings">Public Buildings</option>
                    </select>
                </div>

                <div class="ac-a2374ef4">
                    <label class="ac-37c29296" for="feedback">Suggestion / Concern</label>
                    <textarea class="ac-6f762f4a" id="feedback" name="feedback" rows="5" required></textarea>
                </div>

                <button type="submit" class="ac-f84d9680">Submit Feedback</button>
                <div id="message" style="display:none;margin-top:10px;"></div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom:12px;">Your Submissions</h3>
            <div class="table-wrap">
                <table class="projects-table" id="feedbackHistoryList">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($feedbackRows && $feedbackRows->num_rows > 0): ?>
                            <?php while ($row = $feedbackRows->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime((string) $row['date_submitted'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['subject']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['category']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['location']); ?></td>
                                    <td><span class="status-badge pending"><?php echo htmlspecialchars((string) $row['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="ac-a004b216">No feedback submitted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="/user-dashboard/user-feedback.js?v=<?php echo filemtime(__DIR__ . '/user-feedback.js'); ?>"></script>
</body>
</html>
