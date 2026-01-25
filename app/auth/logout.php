<?php
// Define root path for all includes
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Load configuration and auth
require_once CONFIG_PATH . '/app.php';
require_once INCLUDES_PATH . '/auth.php';

// Logout the user
logout('/public/index.php?logged_out=1');
?>