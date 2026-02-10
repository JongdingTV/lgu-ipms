<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// AJAX handler for feedback submission
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_PARSE);
    
    // Check authentication
    check_auth();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    // Get user info from database
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($user['first_name'] . ' ' . $user['last_name']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
        if ($db->connect_error) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $category = $_POST['category'];
        $location = $_POST['street'] . ', ' . $_POST['barangay'];
        $description = $_POST['feedback'];
        $status = 'Pending';
        $stmt = $db->prepare("INSERT INTO feedback (user_name, subject, category, location, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $user_name, $subject, $category, $location, $description, $status);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Feedback submitted!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error submitting feedback.']);
        }
        $stmt->close();
        $db->close();
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Normal page load
$msg = '';
set_no_cache_headers();
check_auth();
check_suspicious_activity();
// Get user info from database
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($user['first_name'] . ' ' . $user['last_name']);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    if ($db->connect_error) {
        $msg = 'Database connection failed.';
    } else {
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $category = $_POST['category'];
        $location = $_POST['street'] . ', ' . $_POST['barangay'];
        $description = $_POST['feedback'];
        $status = 'Pending';
        $stmt = $db->prepare("INSERT INTO feedback (user_name, subject, category, location, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $user_name, $subject, $category, $location, $description, $status);
        if ($stmt->execute()) {
            $msg = 'Feedback submitted!';
        } else {
            $msg = 'Error submitting feedback.';
        }
        $stmt->close();
    }
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - User Dashboard</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="user-dashboard.css">
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
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
            <a href="user-dashboard.php"><img src="../admin/dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="../admin/progress-monitoring/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php" class="active"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-user">
            <?php
            $profile_img = '';
            $user_email = isset($user['email']) ? $user['email'] : '';
            $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : 'User');
            $initials = '';
            if ($user_name) {
                $parts = explode(' ', $user_name);
                foreach ($parts as $p) {
                    if ($p) $initials .= strtoupper($p[0]);
                }
            }
            if (!function_exists('stringToColor')) {
                function stringToColor($str) {
                    $colors = [
                        '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3',
                        '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39',
                        '#FFEB3B', '#FFC107', '#FF9800', '#FF5722', '#795548', '#607D8B'
                    ];
                    $hash = 0;
                    for ($i = 0; $i < strlen($str); $i++) {
                        $hash = ord($str[$i]) + (($hash << 5) - $hash);
                    }
                    $index = abs($hash) % count($colors);
                    return $colors[$index];
                }
            }
            $bgcolor = stringToColor($user_name);
            ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;min-width:110px;">
                <?php if ($profile_img): ?>
                    <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon">
                <?php else: ?>
                    <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;color:#fff;font-weight:600;font-size:1.1em;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                <div style="font-weight:600;font-size:1.05em;line-height:1.2;margin-top:2px;"> <?php echo htmlspecialchars($user_name); ?> </div>
                <div style="font-size:0.97em;color:#64748b;line-height:1.1;"> <?php echo htmlspecialchars($user_email); ?> </div>
            </div>
            <a href="#" class="nav-logout logout-btn" id="logoutLink" style="background:#ef4444;color:#fff;padding:6px 16px;border-radius:6px;font-weight:500;margin-left:12px;">Logout</a>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <!-- Toggle button to show sidebar -->
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
            <h1>Submit Feedback</h1>
            <p>Help us improve our infrastructure and services</p>
        </div>

        <!-- User Feedback Form -->
        <div class="feedback-form modern-feedback-form">
            <div class="feedback-header">
                <div class="feedback-icon-bg">
                    <img src="../dashboard/feedback.png" alt="Feedback Icon" class="feedback-icon">
                </div>
                <div>
                    <h2 class="feedback-title">Submit Your Feedback or Suggestion</h2>
                    <p class="feedback-desc">We value your input! Please fill out the form below to help us improve our services and infrastructure.</p>
                </div>
            </div>
            <form id="userFeedbackForm" method="post" action="">
                <div class="form-row">
                    <div class="input-box">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="100" placeholder="Enter subject (e.g. Road Repair Request)" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-box">
                        <label for="street">Street</label>
                        <input type="text" id="street" name="street" placeholder="Enter street name" required>
                    </div>
                    <div class="input-box">
                        <label for="barangay">Barangay</label>
                        <input type="text" id="barangay" name="barangay" placeholder="Enter barangay" required>
                    </div>
                </div>
                <div class="input-box">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="transportation">Transportation (roads, bridges, airports, railways)</option>
                        <option value="energy">Energy (power generation/transmission)</option>
                        <option value="water-waste">Water & Waste (supply, sanitation, drainage)</option>
                        <option value="social-infrastructure">Social Infrastructure (schools, hospitals, etc.)</option>
                        <option value="public-buildings">Public Buildings (park, irrigation systems, gov buildings)</option>
                    </select>
                </div>
                <div class="input-box">
                    <label for="photo">Photo Attachment (Optional)</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                </div>
                <div class="input-box">
                    <label for="feedback">Suggestion, Feedback, Concern</label>
                    <textarea id="feedback" name="feedback" rows="5" placeholder="Enter your suggestion, feedback, or concern here..." required></textarea>
                </div>
                <button type="submit" class="submit-btn">Submit Feedback</button>
            </form>
            <div id="message" class="message" style="display:<?php echo !empty($msg) ? 'block' : 'none'; ?>; margin-top: 18px; <?php echo !empty($msg) ? ($msg === 'Feedback submitted!' ? 'background:#d1fae5;color:#065f46;' : 'background:#fee2e2;color:#991b1b;') : 'background:#e0f2fe;color:#2563eb;'; ?>"><?php if (!empty($msg)) echo htmlspecialchars($msg); ?></div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>



    <script src="../shared-data.js"></script>
    <script src="../shared-toggle.js"></script>
    <script src="user-feedback.js"></script>
    <script>
    // Remove duplicate logout modal if present
    // ...existing code...
    </script>
</body>
</html>
</parameter