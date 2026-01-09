<?php
session_start();

// Clear all session data and cookies related to login
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
// Also clear remembered device token
setcookie('remember_device', '', time() - 3600, '/', '', false, true);

session_destroy();

header('Location: login.php');
exit;

