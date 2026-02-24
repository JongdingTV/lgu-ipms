<?php
// Include security functions
require __DIR__ . '/session-auth.php';
require __DIR__ . '/database.php';

// Log the logout event
log_security_event('USER_LOGOUT', 'User successfully logged out');

// Explicit logout must clear remembered-device token to prevent immediate auto-login.
destroy_session();

// Add no-cache headers to ensure page isn't cached
set_no_cache_headers();

// Redirect to login page
header('Location: user-dashboard/user-login.php?logout=1');
exit;


