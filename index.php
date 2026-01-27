<?php
// Load configuration and auth
require_once __DIR__ . '/config/app.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';

if (is_authenticated()) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
        header('Location: /admin/index.php');
    } else {
        header('Location: /user-dashboard/user-dashboard.php');
    }
    exit;
}

// Otherwise, redirect to public homepage
header('Location: /public/index.php');
exit;
?>
