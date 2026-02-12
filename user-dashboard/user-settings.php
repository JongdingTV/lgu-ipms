<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();

require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
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
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
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

$db->close();
?>
<!DOCTYPE html>
    <aside class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" style="width:48px;height:48px;margin-bottom:4px;" />
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-user" style="border-top:none;padding-top:0;margin-bottom:8px;">
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
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                <?php if ($profile_img): ?>
                    <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon" style="width:48px;height:48px;" />
                <?php else: ?>
                    <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;color:#fff;font-weight:600;font-size:1.1em;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                <div style="font-weight:700;font-size:1.08em;line-height:1.2;margin-top:2px;text-align:center;"> <?php echo htmlspecialchars($user_name); ?> </div>
                <div style="font-size:0.97em;color:#64748b;line-height:1.1;text-align:center;"> <?php echo htmlspecialchars($user_email); ?> </div>
            </div>
        </div>
        <hr style="width:80%;margin:10px auto 16px auto;border:0;border-top:1.5px solid #e5e7eb;" />
        <nav class="nav-links">
            <a href="user-dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="../assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php" class="active"><img src="settings.png" class="nav-icon"> Settings</a>
        </nav>
        <div style="margin-top:auto;padding:18px 0 0 0;display:flex;justify-content:center;">
            <a href="#" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    </aside>
    // ...existing code...

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



    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="user-settings.js"></script>
    <script>
    (function() {
        // Remove duplicate logout modal if present
        // ...existing code...
    })();
    </script>
</body>
</html>






