<?php
/**
 * Session Authentication & Security Management
 * Provides protection against unauthorized access and session hijacking
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // Set secure session configuration
    ini_set('session.cookie_httponly', 1);      // Prevent JavaScript access to session cookie
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', 1);       // Don't accept uninitialized session IDs
    ini_set('session.cookie_samesite', 'Lax');   // Prevent CSRF
    
    session_start();
}

// Session timeout configuration (30 minutes of inactivity)
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes in seconds
define('REMEMBER_DEVICE_DAYS', 10);
define('REMEMBER_COOKIE_NAME', 'lgu_remember_device');

function set_auth_cookie(string $name, string $value, int $expiresAt): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clear_remember_device_cookie(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        set_auth_cookie(REMEMBER_COOKIE_NAME, '', time() - 3600);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

function ensure_remember_device_table(): void
{
    global $db;
    if (!isset($db) || !($db instanceof mysqli)) {
        return;
    }

    $db->query("CREATE TABLE IF NOT EXISTS user_remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selector VARCHAR(24) NOT NULL UNIQUE,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_user_expires (user_id, expires_at),
        CONSTRAINT fk_user_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

function remember_user_device(int $userId, int $days = REMEMBER_DEVICE_DAYS): bool
{
    global $db;
    if (!isset($db) || !($db instanceof mysqli) || $userId <= 0) {
        return false;
    }

    ensure_remember_device_table();

    $selector = bin2hex(random_bytes(6));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = time() + ($days * 86400);
    $expiresSql = date('Y-m-d H:i:s', $expiresAt);

    $stmt = $db->prepare("INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isss', $userId, $selector, $tokenHash, $expiresSql);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return false;
    }

    set_auth_cookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, $expiresAt);
    $_SESSION['remember_until'] = $expiresAt;
    return true;
}

function clear_remember_device_token_for_current_user(): void
{
    global $db;
    if (!isset($db) || !($db instanceof mysqli)) {
        clear_remember_device_cookie();
        return;
    }

    ensure_remember_device_table();

    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $parts = explode(':', (string) $_COOKIE[REMEMBER_COOKIE_NAME], 2);
        $selector = $parts[0] ?? '';
        if ($selector !== '') {
            $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
            if ($stmt) {
                $stmt->bind_param('s', $selector);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    clear_remember_device_cookie();
}

function try_auto_login_from_remember_cookie(): bool
{
    global $db;
    if (isset($_SESSION['user_id']) || empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }
    if (!isset($db) || !($db instanceof mysqli)) {
        return false;
    }

    $parts = explode(':', (string) $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    $selector = $parts[0] ?? '';
    $validator = $parts[1] ?? '';
    if ($selector === '' || $validator === '') {
        clear_remember_device_cookie();
        return false;
    }

    ensure_remember_device_table();

    $stmt = $db->prepare("SELECT t.user_id, t.token_hash, t.expires_at, u.first_name, u.last_name
                          FROM user_remember_tokens t
                          JOIN users u ON u.id = t.user_id
                          WHERE t.selector = ?
                          LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        clear_remember_device_cookie();
        return false;
    }

    $expiresTs = strtotime((string) $row['expires_at']);
    $validHash = hash_equals((string) $row['token_hash'], hash('sha256', $validator));
    if ($expiresTs === false || $expiresTs < time() || !$validHash) {
        $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
        if ($stmt) {
            $stmt->bind_param('s', $selector);
            $stmt->execute();
            $stmt->close();
        }
        clear_remember_device_cookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['user_id'];
    $_SESSION['user_name'] = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    $_SESSION['user_type'] = 'citizen';
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['remember_until'] = $expiresTs;

    $update = $db->prepare("UPDATE user_remember_tokens SET last_used_at = NOW() WHERE selector = ?");
    if ($update) {
        $update->bind_param('s', $selector);
        $update->execute();
        $update->close();
    }
    return true;
}

/**
 * Check if user is authenticated
 * Validates session, checks timeout, and verifies user exists
 */
function check_auth() {
    // Check if session has a user_id (for citizen) or employee_id (for admin/employee)
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
        try_auto_login_from_remember_cookie();
    }

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
        header('Location: ' . get_login_url());
        exit();
    }

    if (isset($_SESSION['user_id'], $_SESSION['remember_until'])) {
        if (time() > (int) $_SESSION['remember_until']) {
            clear_remember_device_token_for_current_user();
            destroy_session();
            header('Location: ' . get_login_url() . '?expired=1');
            exit();
        }
    }

    // Session timeout
    if (!isset($_SESSION['remember_until']) && isset($_SESSION['last_activity'])) {
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
    $employee_dirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring',
                      'budget-resources', 'task-milestone', 'project-prioritization',
                      'contractor', 'engineer'];
    $is_employee_route = false;
    foreach ($employee_dirs as $dir) {
        if (strpos($request_uri, '/' . $dir . '/') !== false) {
            $is_employee_route = true;
            break;
        }
    }
    // Build login URL based on user type
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
    
    if ($user_type === 'employee' || strpos($request_uri, '/admin') !== false || $is_employee_route) {
        // Admin/Employee login
        return $protocol . $host . '/admin/index.php';
    } else {
        // Citizen/User login
        return $protocol . $host . '/user-dashboard/user-login.php';
    }
}

/**
 * Destroy session completely
 */
function destroy_session() {
    clear_remember_device_token_for_current_user();

    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $isHttps,
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
    
    $user_id = $_SESSION['employee_id'] ?? ($_SESSION['user_id'] ?? null);
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
 * Check user-specific rate limit independent of IP.
 */
function is_user_rate_limited($action_type = 'generic', $max_attempts = 5, $time_window = 300, $user_id = null) {
    global $db;
    $uid = $user_id !== null ? (int) $user_id : (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }

    $cutoff_time = time() - (int) $time_window;
    $db->query("CREATE TABLE IF NOT EXISTS user_rate_limiting (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        attempt_time INT NOT NULL,
        INDEX idx_user_action_time (user_id, action_type, attempt_time)
    )");

    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM user_rate_limiting WHERE user_id = ? AND action_type = ? AND attempt_time > ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isi', $uid, $action_type, $cutoff_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['count' => 0];
    $stmt->close();
    return (int) ($row['count'] ?? 0) >= $max_attempts;
}

/**
 * Record user-specific attempt.
 */
function record_user_attempt($action_type = 'generic', $user_id = null) {
    global $db;
    $uid = $user_id !== null ? (int) $user_id : (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }

    $timestamp = time();
    $stmt = $db->prepare("INSERT INTO user_rate_limiting (user_id, action_type, attempt_time) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isi', $uid, $action_type, $timestamp);
        $stmt->execute();
        $stmt->close();
    }

    $cutoff = time() - 3600;
    $db->query("DELETE FROM user_rate_limiting WHERE attempt_time < $cutoff");
}

/**
 * Check for suspicious activity
 */
function check_suspicious_activity() {
    $currentUserAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    // Some requests/proxies can omit user-agent; do not hard-fail the session for this.
    if ($currentUserAgent === '') {
        return;
    }

    $currentHash = hash('sha256', $currentUserAgent);
    $storedHash = $_SESSION['user_agent_hash'] ?? null;

    // Backward compatibility with older sessions that stored raw user-agent.
    if ($storedHash === null && isset($_SESSION['user_agent']) && is_string($_SESSION['user_agent']) && $_SESSION['user_agent'] !== '') {
        $storedHash = hash('sha256', $_SESSION['user_agent']);
    }

    if ($storedHash === null) {
        $_SESSION['user_agent_hash'] = $currentHash;
        $_SESSION['user_agent'] = $currentUserAgent;
        return;
    }

    if (!hash_equals((string) $storedHash, $currentHash)) {
        // Log and refresh fingerprint instead of forcing logout on transient browser/network variation.
        log_security_event('SUSPICIOUS_ACTIVITY', 'User-Agent changed during session (tolerated fingerprint refresh)');
    }

    $_SESSION['user_agent_hash'] = $currentHash;
    $_SESSION['user_agent'] = $currentUserAgent;
}
?>



