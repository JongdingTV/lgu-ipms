<?php
// Include security functions
require __DIR__ . '/session-auth.php';
require __DIR__ . '/database.php';

// Log the logout event
log_security_event('USER_LOGOUT', 'User successfully logged out');

// Destroy session using our secure function
destroy_session();
set_no_cache_headers();

// Redirect based on session type
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee') {
	header('Location: /public/admin-login.php?logout=1');
	exit;
} else {
	header('Location: /user-dashboard/user-login.php?logout=1');
	exit;
}


