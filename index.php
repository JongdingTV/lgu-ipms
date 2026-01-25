<?php
// Define root path for all includes
define('ROOT_PATH', dirname(__FILE__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Check if user is logged in
require_once CONFIG_PATH . '/app.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';

// If user is authenticated, redirect to their dashboard
if (is_authenticated()) {
	if (get_current_user_type() === 'employee') {
		header('Location: /app/admin/dashboard.php');
	} else {
		header('Location: /app/user/dashboard.php');
	}
	exit;
}

// Otherwise, redirect to public homepage
header('Location: /public/index.php');
exit;
?>
