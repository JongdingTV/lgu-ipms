<?php
/**
 * Authentication & Authorization Functions
 * 
 * Manages user authentication, authorization, and session security
 * 
 * @package LGU-IPMS
 * @subpackage Auth
 * @version 1.0.0
 */

// Ensure config is loaded
if (!defined('SESSION_TIMEOUT')) {
    require_once dirname(__FILE__) . '/../config/app.php';
}

// ========== SESSION INITIALIZATION ==========

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session configuration
    ini_set('session.cookie_httponly', SESSION_HTTP_ONLY ? 1 : 0);
    ini_set('session.cookie_secure', SESSION_SECURE ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', SESSION_SAME_SITE);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    
    session_start();
}

// ========== AUTHENTICATION FUNCTIONS ==========

/**
 * Check if user is authenticated
 * Validates session, checks timeout, and verifies user exists
 * 
 * @return void Redirects to login if not authenticated
 */
function check_auth() {
    if (!is_authenticated()) {
        redirect_to_login();
    }
}

/**
 * Check if user is currently authenticated
 * 
 * @return bool True if user is authenticated, false otherwise
 */
function is_authenticated() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $idle_time = time() - $_SESSION['last_activity'];
        
        if ($idle_time > SESSION_TIMEOUT) {
            destroy_session();
            return false;
        }
    }
    
    // Validate session token/fingerprint
    if (!validate_session_fingerprint()) {
        destroy_session();
        return false;
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Check user role/permission
 * 
 * @param string $role Required role (admin, staff, citizen, contractor)
 * @return bool True if user has required role
 */
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require specific role
 * Redirects to error page if user doesn't have required role
 * 
 * @param string $role Required role
 * @return void Dies with error if unauthorized
 */
function require_role($role) {
    if (!has_role($role)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied. Required role: ' . htmlspecialchars($role));
    }
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin
 */
function is_admin() {
    return has_role('admin');
}

/**
 * Redirect to login page
 * 
 * @return void
 */
function redirect_to_login() {
    // Determine login page based on user type
    $login_page = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'citizen' 
        ? '/app/auth/user-login.php'
        : '/app/auth/login.php';
    
    header('Location: ' . $login_page . '?expired=1');
    exit();
}

/**
 * Validate session fingerprint
 * Prevents session hijacking by verifying consistent user-agent
 * 
 * @return bool True if session fingerprint is valid
 */
function validate_session_fingerprint() {
    $user_agent = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $user_agent;
        return true;
    }
    
    return $_SESSION['user_agent'] === $user_agent;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not authenticated
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? $_SESSION['employee_id'] ?? null;
}

/**
 * Get current user name
 * 
 * @return string User name or 'Guest'
 */
function get_user_name() {
    return $_SESSION['user_name'] ?? $_SESSION['employee_name'] ?? 'Guest';
}

/**
 * Get current user email
 * 
 * @return string User email or empty string
 */
function get_user_email() {
    return $_SESSION['user_email'] ?? '';
}

// ========== SESSION DESTRUCTION ==========

/**
 * Destroy session and clear all data
 * 
 * @return void
 */
function destroy_session() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Logout current user
 * 
 * @return void
 */
function logout() {
    destroy_session();
    header('Location: /public/index.php?logged_out=1');
    exit();
}

// ========== SECURITY HEADERS ==========

/**
 * Set security headers to prevent common attacks
 * 
 * @return void
 */
function set_security_headers() {
    if (!ENABLE_SECURITY_HEADERS) {
        return;
    }
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy (basic)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;");
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Feature Policy
    header('Feature-Policy: camera \'none\'; microphone \'none\'; geolocation \'none\';');
}

/**
 * Set no-cache headers to prevent caching of sensitive pages
 * 
 * @return void
 */
function set_no_cache_headers() {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Check for suspicious activity
 * Validates session fingerprint and detects potential attacks
 * 
 * @return void
 */
function check_suspicious_activity() {
    if (!validate_session_fingerprint()) {
        error_log('Suspicious activity: Session hijacking attempt detected for user ' . get_user_id());
        destroy_session();
        redirect_to_login();
    }
}

// Set security headers on every page load
set_security_headers();

// ========== DATABASE AUTHENTICATION ==========

/**
 * Authenticate employee user
 * 
 * @param string $email Employee email
 * @param string $password Employee password
 * @return array ['success' => bool, 'user_id' => int, 'user_name' => string, 'error' => string]
 */
function authenticate_employee($email, $password) {
    global $db;
    
    if (!isset($db) || $db->connect_error) {
        return ['success' => false, 'error' => 'Database connection failed'];
    }
    
    $email = trim($email);
    
    $stmt = $db->prepare("SELECT id, password, first_name, last_name FROM employees WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error'];
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $employee = $result->fetch_assoc();
    
    // Check password
    if ($email === 'admin@lgu.gov.ph') {
        // Allow plain password for admin account (development only)
        $valid = ($password === 'admin123' || password_verify($password, $employee['password']));
    } else {
        // Use password_verify for all other accounts
        $valid = password_verify($password, $employee['password']);
    }
    
    if (!$valid) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $stmt->close();
    
    return [
        'success' => true,
        'user_id' => (int)$employee['id'],
        'user_name' => trim($employee['first_name'] . ' ' . $employee['last_name'])
    ];
}

/**
 * Authenticate citizen user
 * 
 * @param string $email Citizen email
 * @param string $password Citizen password
 * @return array ['success' => bool, 'user_id' => int, 'user_name' => string, 'error' => string]
 */
function authenticate_citizen($email, $password) {
    global $db;
    
    if (!isset($db) || $db->connect_error) {
        return ['success' => false, 'error' => 'Database connection failed'];
    }
    
    $email = trim($email);
    
    $stmt = $db->prepare("SELECT id, password, first_name, last_name FROM citizens WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error'];
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $citizen = $result->fetch_assoc();
    
    // Check password
    $valid = password_verify($password, $citizen['password']);
    
    if (!$valid) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $stmt->close();
    
    return [
        'success' => true,
        'user_id' => (int)$citizen['id'],
        'user_name' => trim($citizen['first_name'] . ' ' . $citizen['last_name'])
    ];
}
?>
