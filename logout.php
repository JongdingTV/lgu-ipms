<?php
// Include security functions
require 'session-auth.php';
require 'database.php';

// Log the logout event
log_security_event('USER_LOGOUT', 'User successfully logged out');

// Destroy session using our secure function
destroy_session();

// Add no-cache headers to ensure page isn't cached
set_no_cache_headers();

// Redirect to login page
header('Location: user-dashboard/user-login.php?logout=1');
exit;


