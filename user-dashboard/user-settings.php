<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$conn = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($user['first_name'] . ' ' . $user['last_name']);

// Format gender and civil status for display
$gender_display = ucfirst(str_replace('_', ' ', $user['gender'] ?? ''));
$civil_status_display = ucfirst(str_replace('_', ' ', $user['civil_status'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = trim($_POST['currentPassword']);
    $new_password = trim($_POST['newPassword']);
    $confirm_password = trim($_POST['confirmPassword']);

    $errors = [];
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed_password, $user_id);
        if ($stmt->execute()) {
            $success = 'Password changed successfully. You will be logged out for security.';
            session_destroy();
            header('refresh:3;url=../login.php');
        } else {
            $errors[] = 'Failed to update password.';
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../assets/style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - User Dashboard</title>
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
            <a href="user-feedback.php"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php" class="active"><img src="settings.png" class="nav-icon"> Settings</a>
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
            <h1>Settings</h1>
            <p>Manage your account information</p>
        </div>

        <div class="settings-form">
            <form id="userSettingsForm" method="post">
                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-row">
                        <div class="input-box">
                            <label for="firstName">First Name</label>
                            <input id="firstName" name="firstName" type="text" readonly value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        <div class="input-box">
                            <label for="middleName">Middle Name</label>
                            <input id="middleName" name="middleName" type="text" readonly value="<?php echo htmlspecialchars($user['middle_name']); ?>">
                        </div>
                        <div class="input-box">
                            <label for="lastName">Last Name</label>
                            <input id="lastName" name="lastName" type="text" readonly value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                        <div class="input-box">
                            <label for="suffix">Suffix</label>
                            <input id="suffix" name="suffix" type="text" readonly value="<?php echo htmlspecialchars($user['suffix']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Contact Information</h3>
                    <div class="form-row">
                        <div class="input-box">
                            <label for="email">Email Address</label>
                            <input id="email" name="email" type="email" readonly value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="input-box">
                            <label for="mobile">Mobile Number</label>
                            <input id="mobile" name="mobile" type="tel" readonly value="<?php echo htmlspecialchars($user['mobile']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Personal Details</h3>
                    <div class="form-row">
                        <div class="input-box">
                            <label for="birthdate">Birthdate</label>
                            <input id="birthdate" name="birthdate" type="date" readonly value="<?php echo htmlspecialchars($user['birthdate']); ?>">
                        </div>
                        <div class="input-box">
                            <label for="gender">Gender</label>
                            <input id="gender" name="gender" type="text" readonly value="<?php echo htmlspecialchars($gender_display); ?>">
                        </div>
                        <div class="input-box">
                            <label for="civilStatus">Civil Status</label>
                            <input id="civilStatus" name="civilStatus" type="text" readonly value="<?php echo htmlspecialchars($civil_status_display); ?>">
                        </div>
                    </div>
                    <div class="input-box full-width">
                        <label for="address">Address / Barangay</label>
                        <input id="address" name="address" type="text" readonly value="<?php echo htmlspecialchars($user['address']); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Change Password</h3>
                    <div class="input-box">
                        <label for="currentPassword">Current Password</label>
                        <input id="currentPassword" name="currentPassword" type="password" required autocomplete="current-password">
                    </div>
                    <div class="input-box">
                        <label for="newPassword">New Password</label>
                        <input id="newPassword" name="newPassword" type="password" required autocomplete="new-password">
                    </div>
                    <div class="input-box">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input id="confirmPassword" name="confirmPassword" type="password" required autocomplete="new-password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">Change Password</button>
                    <button type="button" class="cancel-btn" onclick="window.location.href='user-dashboard.php'">Cancel</button>
                </div>
            </form>
            <div id="settingsMessage" class="message" style="display: none;"></div>
            <?php if (!empty($errors)): ?>
            <div class="message" style="color: red; margin-top: 10px;">
                <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
            <div class="message" style="color: green; margin-top: 10px;">
                <p><?php echo $success; ?></p>
            </div>
            <?php endif; ?>
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
    <script src="user-settings.js"></script>
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
