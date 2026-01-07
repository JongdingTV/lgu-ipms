<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - User Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="user-dashboard.css">
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
            <a href="user-feedback.php" class="active"><img src="../dashboard/person.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="../dashboard/person.png" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../login.php" class="nav-logout">Logout</a>
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
            <h1>Feedback</h1>
            <p>Submit your feedback or suggestions and track your submissions</p>
        </div>

        <!-- Feedback Submissions Section -->
        <div class="feedback-history" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <h3 style="color: #1e3a8a; margin-bottom: 15px; font-size: 1.1em;">My Submitted Feedback</h3>
            <div id="feedbackHistoryList"></div>
        </div>

        <!-- User Feedback Form -->
        <div class="feedback-form" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
            <form id="userFeedbackForm" enctype="multipart/form-data">
            <h3 style="color: #1e3a8a; margin-bottom: 15px; font-size: 1.1em;">Submit Your Feedback or Suggestion</h3>
                <div class="form-row">
                    <div class="input-box">
                        <label for="street">Street</label>
                        <input type="text" id="street" name="street" placeholder="Enter street name" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif;">
                    </div>
                    <div class="input-box">
                        <label for="barangay">Barangay</label>
                        <input type="text" id="barangay" name="barangay" placeholder="Enter barangay" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif;">
                    </div>
                </div>
                <div class="input-box">
                    <label for="category">Category</label>
                    <select id="category" name="category" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif;">
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
                    <input type="file" id="photo" name="photo" accept="image/*" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif;">
                </div>
                <div class="input-box">
                    <label for="feedback">Suggestion, Feedback, Concern</label>
                    <textarea id="feedback" name="feedback" rows="5" placeholder="Enter your suggestion, feedback, or concern here..." required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif; resize: vertical;"></textarea>
                </div>
                <button type="submit" class="submit-btn" style="background: linear-gradient(90deg, #1e3a8a, #2563eb); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s ease;">Submit Feedback</button>
            </form>
            <div id="message" class="message" style="display: none; margin-top: 15px; padding: 12px; border-radius: 8px; font-weight: 500;"></div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js"></script>
    <script src="user-feedback.js"></script>
</body>
</html>
