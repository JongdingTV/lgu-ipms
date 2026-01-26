<?php
// Admin logout - redirects to admin login page
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

// Log the logout event
log_security_event('ADMIN_LOGOUT', 'Admin user successfully logged out');

// Destroy session
destroy_session();

// Clear admin verification flag
unset($_SESSION['admin_verified']);
unset($_SESSION['admin_verified_time']);

// Add no-cache headers
set_no_cache_headers();

// Redirect to admin login page
header('Location: /admin/index.php?logout=1');
exit;
?>
