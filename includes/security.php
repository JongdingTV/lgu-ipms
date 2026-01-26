<?php
/**
 * Enhanced Security Headers for Government System
 * Include this file at the top of every PHP file
 */

// Prevent caching for sensitive pages
function set_no_cache_headers() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
}

// Set comprehensive security headers
function set_security_headers() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff', true);
    
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN', true);
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block', true);
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin', true);
    
    // Permissions Policy - disable sensitive APIs
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()', true);
    
    // Strict Transport Security
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';", true);
    
    // Hide server information
    header_remove('Server');
    header_remove('X-Powered-By');
}

// Prevent directory traversal attacks
function sanitize_path($path) {
    $path = realpath($path);
    if ($path === false) {
        return null;
    }
    return $path;
}

// Rate limiting helper
function check_rate_limit($identifier, $max_requests = 100, $time_window = 3600) {
    $cache_key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'first_time' => time()
        ];
        return true;
    }
    
    $elapsed = time() - $_SESSION[$cache_key]['first_time'];
    
    if ($elapsed > $time_window) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'first_time' => time()
        ];
        return true;
    }
    
    $_SESSION[$cache_key]['count']++;
    
    if ($_SESSION[$cache_key]['count'] > $max_requests) {
        return false;
    }
    
    return true;
}

// Validate and sanitize input
function sanitize_input($input, $type = 'text') {
    if (is_array($input)) {
        return array_map(function($value) use ($type) {
            return sanitize_input($value, $type);
        }, $input);
    }
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'text':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

// Log security events
function log_security_event($event_type, $details = []) {
    $log_file = dirname(__DIR__) . '/storage/logs/security.log';
    
    // Ensure log directory exists
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0750, true);
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = [
        'timestamp' => $timestamp,
        'event_type' => $event_type,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'details' => json_encode($details)
    ];
    
    $log_line = json_encode($log_entry) . PHP_EOL;
    
    error_log($log_line, 3, $log_file);
}

// Initialize security for all pages
set_security_headers();

// Start session with secure settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}
