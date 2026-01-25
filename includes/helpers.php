<?php
/**
 * Helper Functions
 * 
 * Utility functions for common operations throughout the application
 * 
 * @package LGU-IPMS
 * @subpackage Helpers
 * @version 1.0.0
 */

// Ensure config is loaded
if (!defined('APP_NAME')) {
    require_once dirname(__FILE__) . '/../config/app.php';
}

// ========== PATH HELPERS ==========

/**
 * Get absolute URL for a given path
 * 
 * @param string $path Relative path (e.g., '/assets/css/main.css')
 * @return string Absolute URL
 */
function url($path = '') {
    return APP_URL . $path;
}

/**
 * Get asset URL with cache-busting
 * 
 * @param string $path Asset path relative to /assets (e.g., 'css/main.css')
 * @return string Asset URL with version
 */
function asset($path) {
    $file = ROOT_PATH . 'assets/' . ltrim($path, '/');
    
    if (file_exists($file)) {
        $version = filemtime($file);
        return ASSETS_URL . '/' . ltrim($path, '/') . '?v=' . $version;
    }
    
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Get relative asset URL (for navigation between pages)
 * Useful when you're in a nested page
 * 
 * @param string $path Path within app folder (e.g., 'auth/login.php')
 * @return string Relative path
 */
function asset_url($path) {
    return '/app/' . ltrim($path, '/');
}

/**
 * Get image URL
 * 
 * @param string $image Image filename
 * @param string $folder Subfolder (e.g., 'icons', 'gallery', 'uploads')
 * @return string Image URL
 */
function image($image, $folder = '') {
    if ($folder) {
        return ASSETS_URL . '/images/' . $folder . '/' . $image;
    }
    return ASSETS_URL . '/images/' . $image;
}

/**
 * Redirect to a URL
 * 
 * @param string $path Redirect path (e.g., '/app/dashboard.php')
 * @param int $code HTTP status code (default 302)
 * @return void
 */
function redirect($path, $code = 302) {
    header('Location: ' . $path, true, $code);
    exit();
}

// ========== STRING HELPERS ==========

/**
 * Truncate string to specified length
 * 
 * @param string $string String to truncate
 * @param int $length Maximum length
 * @param string $append String to append if truncated (default '...')
 * @return string Truncated string
 */
function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length) . $append;
}

/**
 * Convert string to slug format
 * 
 * @param string $string String to slugify
 * @return string Slugified string
 */
function slug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Sanitize HTML input
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML entities
 * 
 * @param string $text Text to escape
 * @return string Escaped text
 */
function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ========== VALIDATION HELPERS ==========

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if valid email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Philippine format)
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid phone
 */
function is_valid_phone($phone) {
    return preg_match('/^\+?63[0-9]{9,10}$|^0[0-9]{9,10}$|^\+?[0-9\s-]{7,15}$/', $phone) === 1;
}

/**
 * Validate URL
 * 
 * @param string $url URL to validate
 * @return bool True if valid URL
 */
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate integer
 * 
 * @param mixed $value Value to validate
 * @return bool True if valid integer
 */
function is_valid_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

// ========== FORMAT HELPERS ==========

/**
 * Format currency (Philippine Peso)
 * 
 * @param float $amount Amount to format
 * @param bool $symbol Include PHP symbol (default true)
 * @return string Formatted currency
 */
function format_currency($amount, $symbol = true) {
    $formatted = number_format($amount, 2, '.', ',');
    return $symbol ? '₱' . $formatted : $formatted;
}

/**
 * Format date/time
 * 
 * @param string|int $datetime DateTime string or timestamp
 * @param string $format Format string (default 'Y-m-d H:i:s')
 * @return string Formatted datetime
 */
function format_date($datetime, $format = 'Y-m-d H:i:s') {
    if (is_numeric($datetime)) {
        $timestamp = (int)$datetime;
    } else {
        $timestamp = strtotime($datetime);
    }
    
    if ($timestamp === false) {
        return '';
    }
    
    return date($format, $timestamp);
}

/**
 * Format date to readable format (e.g., "January 15, 2024")
 * 
 * @param string|int $datetime DateTime string or timestamp
 * @return string Readable date
 */
function format_date_readable($datetime) {
    return format_date($datetime, 'F j, Y');
}

/**
 * Format time ago (e.g., "2 hours ago")
 * 
 * @param string|int $datetime DateTime string or timestamp
 * @return string Time ago string
 */
function time_ago($datetime) {
    if (is_numeric($datetime)) {
        $timestamp = (int)$datetime;
    } else {
        $timestamp = strtotime($datetime);
    }
    
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return floor($diff / 604800) . ' weeks ago';
    }
}

/**
 * Format file size (bytes to human readable)
 * 
 * @param int $bytes File size in bytes
 * @return string Readable file size
 */
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// ========== ARRAY HELPERS ==========

/**
 * Get array value with default
 * 
 * @param array $array Array to search
 * @param string $key Key to find
 * @param mixed $default Default value if not found
 * @return mixed Value or default
 */
function array_get($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Flatten multi-dimensional array
 * 
 * @param array $array Array to flatten
 * @return array Flattened array
 */
function array_flatten($array) {
    $result = [];
    
    foreach ($array as $item) {
        if (is_array($item)) {
            $result = array_merge($result, array_flatten($item));
        } else {
            $result[] = $item;
        }
    }
    
    return $result;
}

// ========== DATABASE HELPERS ==========

/**
 * Get project statistics
 * 
 * @return array Project statistics
 */
function get_project_stats() {
    global $db;
    
    return [
        'total' => $db->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'],
        'approved' => $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Approved'")->fetch_assoc()['count'],
        'in_progress' => $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'In Progress'")->fetch_assoc()['count'],
        'completed' => $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count']
    ];
}

/**
 * Get total budget across all projects
 * 
 * @return float Total budget
 */
function get_total_budget() {
    global $db;
    $result = $db->query("SELECT COALESCE(SUM(budget), 0) as total FROM projects");
    return $result->fetch_assoc()['total'];
}

// ========== LOGGING HELPERS ==========

/**
 * Log message to file
 * 
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function log_message($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $log_file = STORAGE_PATH . 'logs/' . date('Y-m-d') . '.log';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Log error message
 * 
 * @param string $message Error message
 * @return void
 */
function log_error($message) {
    log_message($message, 'error');
}

/**
 * Log info message
 * 
 * @param string $message Info message
 * @return void
 */
function log_info($message) {
    log_message($message, 'info');
}

/**
 * Log warning message
 * 
 * @param string $message Warning message
 * @return void
 */
function log_warning($message) {
    log_message($message, 'warning');
}
?>
