<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
set_no_cache_headers();
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}
// Get user info from database
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($user['first_name'] . ' ' . $user['last_name']);
$gender_display = isset($user['gender']) ? $user['gender'] : '';
$civil_status_display = isset($user['civil_status']) ? $user['civil_status'] : '';
$user_email = isset($user['email']) ? $user['email'] : '';
$errors = $errors ?? [];
$success = $success ?? '';
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/user-dashboard/user-dashboard.css">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <!-- Sidebar with logo and IPMS at the top -->
    <aside class="nav" id="navbar">
        <div class="nav-logo" style="display:flex;flex-direction:row;align-items:center;justify-content:center;padding:18px 0 8px 0;gap:10px;">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" style="width:48px;height:48px;" />
            <span class="logo-text" style="font-size:1.5em;font-weight:700;letter-spacing:1px;">IPMS</span>
        </div>
        <!-- No burger button or header, sidebar only -->
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
            <a href="user-settings.php" class="active"><img src="settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </nav>
        <div style="margin-top:auto;padding:18px 0 0 0;display:flex;justify-content:center;">
            <a href="/logout.php" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    </aside>

    <section class="main-content">
        <div class="dash-header">
            <h1>User Settings</h1>
            <p>Manage your account information and change your password.</p>
        </div>
        <div class="settings-container">
            <div class="user-info-box">
                <h2>Account Information</h2>
                <form class="user-info-form" autocomplete="off" style="pointer-events:none;">
                    <div class="input-box">
                        <label>Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Contact No.</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Address</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Barangay</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Gender</label>
                        <input type="text" value="<?php echo htmlspecialchars($gender_display); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Civil Status</label>
                        <input type="text" value="<?php echo htmlspecialchars($civil_status_display); ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>Registration Date</label>
                        <input type="text" value="<?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : ''; ?>" readonly />
                    </div>
                    <div class="input-box">
                        <label>User Type</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['user_type'] ?? ''); ?>" readonly />
                    </div>
                </form>
            </div>
            <div class="password-change-box">
                <h2>Change Password</h2>
                <form method="post" action="">
                    <div class="input-box">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="currentPassword" required>
                    </div>
                    <div class="input-box">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" required>
                    </div>
                    <div class="input-box">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                    </div>
                    <button type="submit" class="submit-btn">Change Password</button>
                </form>
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>
    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="user-dashboard.js"></script>
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

        // Remove duplicate logout modal if present
        const modals = document.querySelectorAll('#logoutModal');
        if (modals.length > 1) {
            for (let i = 1; i < modals.length; i++) {
                modals[i].remove();
            }
        }
    })();
    </script>
</body>
</html>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    </aside>
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
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
                    sidebar.classList.remove('sidebar-open');
                    sidebar.style.transform = 'translateX(-110%)';
                }
            });
        }
        sidebar && (sidebar.style.transform = 'translateX(0)');
    });
    </script>
</body>
</html>






