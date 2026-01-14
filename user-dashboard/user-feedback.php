<?php
// Import security functions
require '../session-auth.php';
require '../database.php';

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
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
        require '../database.php';
        require '../config-path.php';
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
set_no_cache_headers();
check_auth();
check_suspicious_activity();

require '../database.php';
require '../config-path.php';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    $db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
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
        $db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - User Dashboard</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="user-dashboard.css">
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="user-dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard</a>
            <a href="user-progress-monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php" class="active"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="settings.png" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="#" class="nav-logout" id="logoutLink">Logout</a>
        </div>
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
    </header>

    <!-- Toggle button to show sidebar -->
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
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
                    <img src="../dashboard/person.png" alt="Feedback Icon" class="feedback-icon">
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

    <div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;padding:24px 28px;max-width:360px;width:90%;box-shadow:0 10px 30px rgba(15,23,42,0.35);text-align:center;">
            <h2 style="margin-bottom:10px;font-size:1.1rem;color:#111827;">Confirm Logout</h2>
            <p style="font-size:0.9rem;color:#4b5563;margin-bottom:18px;">Are you sure you want to log out of your citizen account?</p>
            <div style="display:flex;justify-content:center;gap:12px;">
                <button id="cancelLogout" style="padding:8px 16px;border-radius:999px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:0.9rem;cursor:pointer;">Cancel</button>
                <button id="confirmLogout" style="padding:8px 16px;border-radius:999px;border:none;background:#ef4444;color:#fff;font-size:0.9rem;cursor:pointer;">Logout</button>
            </div>
        </div>
    </div>

    <script src="../shared-data.js"></script>
    <script src="user-feedback.js"></script>
    <script>
    (function() {
        const logoutLink = document.getElementById('logoutLink');
        const modal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelLogout');
        const confirmBtn = document.getElementById('confirmLogout');
        if (!logoutLink || !modal || !cancelBtn || !confirmBtn) return;

        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
        });

        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        confirmBtn.addEventListener('click', function() {
            window.location.href = '../logout.php';
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>
</parameter