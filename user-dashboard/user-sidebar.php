<?php
// Shared User Sidebar Include
if (!isset($user)) {
    // Try to get user from session or fallback
    $user = [];
    if (isset($_SESSION['user_name'])) {
        $user['name'] = $_SESSION['user_name'];
    }
    if (isset($_SESSION['user_email'])) {
        $user['email'] = $_SESSION['user_email'];
    }
}
$user_name = isset($user['name']) ? $user['name'] : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User');
$user_email = isset($user['email']) ? $user['email'] : (isset($user['email']) ? $user['email'] : '');
$profile_img = isset($user['profile_img']) ? $user['profile_img'] : '';
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
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($profile_img): ?>
            <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon">
        <?php else: ?>
            <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;color:#fff;font-weight:600;font-size:1.1em;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <?php echo $initials; ?>
            </div>
        <?php endif; ?>
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
    </div>
    <ul class="sidebar-menu">
        <li><a href="user-dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'user-dashboard.php') ? 'active' : ''; ?>">
            <img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="icon"> Dashboard Overview</a></li>
        <li><a href="user-progress-monitoring.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'user-progress-monitoring.php') ? 'active' : ''; ?>">
            <img src="../progress-monitoring/monitoring.png" alt="Progress Monitoring" class="icon"> Progress Monitoring</a></li>
        <li><a href="user-feedback.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'user-feedback.php') ? 'active' : ''; ?>">
            <img src="feedback.png" alt="Feedback Icon" class="icon"> Feedback</a></li>
        <li><a href="user-settings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'user-settings.php') ? 'active' : ''; ?>">
            <img src="settings.png" alt="Settings Icon" class="icon"> Settings</a></li>
    </ul>
    <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
        <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
    </button>
</aside>
<div class="toggle-btn" id="showSidebarBtn">
    <a href="#" id="toggleSidebarShow">
        <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
    </a>
</div>
