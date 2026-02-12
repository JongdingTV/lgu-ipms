<?php
// Unified sidebar for all user pages
if (!isset($user)) {
    // Fallback: try to get user from session if not set
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
    $user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
    $profile_img = '';
} else {
    $profile_img = '';
    $user_email = isset($user['email']) ? $user['email'] : '';
    $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : 'User');
}
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
<!-- Unified Sidebar/Header for User Pages -->
<div style="display:flex;align-items:center;gap:10px;padding:16px 0 16px 24px;">
    <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" style="width:48px;height:48px;" />
    <span class="logo-text" style="font-size:1.5em;font-weight:700;letter-spacing:1px;">IPMS</span>
</div>
<aside class="nav" id="navbar">
    <!-- nav-logo moved above, nothing here now -->
    <div class="nav-user" style="border-top:none;padding-top:0;margin-bottom:8px;">
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
    <hr style="width:80%;margin:10px auto 16px auto;border:0;border-top:1.5px solid #e5e7eb;" />
    <nav class="nav-links">
        <a href="user-dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'user-dashboard.php' ? 'active' : '' ?>"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
        <a href="user-progress-monitoring.php" class="<?= basename($_SERVER['PHP_SELF']) == 'user-progress-monitoring.php' ? 'active' : '' ?>"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
        <a href="user-feedback.php" class="<?= basename($_SERVER['PHP_SELF']) == 'user-feedback.php' ? 'active' : '' ?>"><img src="/user-dashboard/feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
        <a href="user-settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'user-settings.php' ? 'active' : '' ?>"><img src="/user-dashboard/settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
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
