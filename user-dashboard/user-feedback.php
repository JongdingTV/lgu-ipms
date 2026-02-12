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
<?php
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Feedback</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/user-dashboard/user-dashboard.css">
</head>
<body>
    <div id="sidebarBurgerBtn" style="position:fixed;top:24px;right:32px;z-index:200;display:flex;align-items:center;cursor:pointer;">
        <button class="navbar-menu-icon" title="Show/hide sidebar" style="background:none;border:none;padding:0;outline:none;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
    <?php include __DIR__ . '/user-sidebar.php'; ?>
    // ...existing code...

    <section class="main-content">
        <div class="dash-header">
            <h1>Submit Feedback</h1>
            <p>Help us improve our infrastructure and services</p>
        </div>

        <!-- User Feedback Form -->
        <div class="feedback-form modern-feedback-form">
            <div class="feedback-header">
                <div class="feedback-icon-bg">
                    <img src="feedback.png" alt="Feedback Icon" class="feedback-icon">
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



    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="user-feedback.js"></script>
    <script>
    // Remove duplicate logout modal if present
    // ...existing code...
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('navbar');
    const burgerBtn = document.getElementById('sidebarBurgerBtn');
    if (sidebar && burgerBtn) {
        burgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            if (sidebar.classList.contains('sidebar-open')) {
                sidebar.style.transform = 'translateX(0)';
            } else {
                sidebar.style.transform = 'translateX(-110%)';
            }
        });
        // Optional: close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
                sidebar.classList.remove('sidebar-open');
                sidebar.style.transform = 'translateX(-110%)';
            }
        });
    }
    // Initialize sidebar state
    sidebar && (sidebar.style.transform = 'translateX(0)');
});
    </script>
</body>
</html>
</parameter





