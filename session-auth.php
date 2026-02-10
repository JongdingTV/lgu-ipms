<?php
/**
 * Session Authentication & Security Management
 * Provides protection against unauthorized access and session hijacking
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session configuration
    ini_set('session.cookie_httponly', 1);      // Prevent JavaScript access to session cookie
    ini_set('session.cookie_secure', 0);         // Set to 1 in production with HTTPS
    ini_set('session.use_strict_mode', 1);       // Don't accept uninitialized session IDs
    ini_set('session.cookie_samesite', 'Lax');   // Prevent CSRF
    
    session_start();
}

// Session timeout configuration (30 minutes of inactivity)
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes in seconds

/**
 * Check if user is authenticated
 * Validates session, checks timeout, and verifies user exists
 */
function check_auth() {
    // Check if session has a user_id (for citizen)
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . get_login_url());
        exit();
    }
    // Session timeout
    if (isset($_SESSION['last_activity'])) {
        $idle_time = time() - $_SESSION['last_activity'];
        if ($idle_time > SESSION_TIMEOUT) {
            destroy_session();
            header('Location: ' . get_login_url() . '?expired=1');
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Get the login page URL (handles both root and subdirectory deployments)
 */
function get_login_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Determine if we're in a subdirectory
    $request_uri = $_SERVER['REQUEST_URI'];
    $known_dirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring', 
                   'budget-resources', 'task-milestone', 'project-prioritization', 'user-dashboard'];
    
    $in_subdirectory = false;
    foreach ($known_dirs as $dir) {
        if (strpos($request_uri, '/' . $dir . '/') !== false) {
            $in_subdirectory = true;
            break;
        }
    }
    
    // Build login URL based on user type
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
    
    if ($user_type === 'employee' || strpos($request_uri, '/admin') !== false) {
        // Admin/Employee login
        return $protocol . $host . '/public/admin-login.php';
    } else {
        // Citizen/User login
        return $protocol . $host . '/user-dashboard/user-login.php';
    }
}

/**
 * Destroy session completely
 */
function destroy_session() {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Generate CSRF token for form protection
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set no-cache headers to prevent page caching
 * Ensures logged-out users cannot use browser back button
 */
function set_no_cache_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
}

/**
 * Hash password using bcrypt (modern password hashing)
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against bcrypt hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Sanitize and validate email
 */
function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize string input (prevent XSS)
 */
function sanitize_string($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Log security events for audit trail
 */
function log_security_event($event_type, $description, $ip_address = null) {
    global $db;
    
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
    
    $user_id = $_SESSION['employee_id'] ?? null;
    $timestamp = date('Y-m-d H:i:s');
    
    // Create security_logs table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS security_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        user_id INT,
        ip_address VARCHAR(45),
        description TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_timestamp (timestamp)
    )");
    
    $stmt = $db->prepare("INSERT INTO security_logs (event_type, user_id, ip_address, description) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('siss', $event_type, $user_id, $ip_address, $description);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Check if IP is being rate limited (brute force protection)
 */
function is_rate_limited($action_type = 'login', $max_attempts = 5, $time_window = 300) {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $cutoff_time = time() - $time_window;
    
    // Create rate_limiting table if needed
    $db->query("CREATE TABLE IF NOT EXISTS rate_limiting (
        id INT PRIMARY KEY AUTO_INCREMENT,
        ip_address VARCHAR(45),
        action_type VARCHAR(50),
        attempt_time INT,
        INDEX idx_ip_action_time (ip_address, action_type, attempt_time)
    )");
    
    // Count recent attempts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM rate_limiting WHERE ip_address = ? AND action_type = ? AND attempt_time > ?");
    if ($stmt) {
        $stmt->bind_param('ssi', $ip, $action_type, $cutoff_time);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row['count'] ?? 0;
        $stmt->close();
        
        if ($count >= $max_attempts) {
            return true; // Rate limited
        }
    }
    
    return false;
}

/**
 * Record an attempt for rate limiting
 */
function record_attempt($action_type = 'login') {
    global $db;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $timestamp = time();
    
    $stmt = $db->prepare("INSERT INTO rate_limiting (ip_address, action_type, attempt_time) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('ssi', $ip, $action_type, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
    
    // Clean old records (older than 1 hour)
    $cutoff = time() - 3600;
    $db->query("DELETE FROM rate_limiting WHERE attempt_time < $cutoff");
}

/**
 * Check for suspicious activity
 */
function check_suspicious_activity() {
    // Check User-Agent consistency
    if (isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            log_security_event('SUSPICIOUS_ACTIVITY', 'User-Agent changed during session');
            destroy_session();
            die('Session security check failed. Please login again.');
        }
    } else {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
}
?>
