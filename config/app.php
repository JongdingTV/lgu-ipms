<?php
/**
 * Application Configuration
 * 
 * Centralized configuration for the LGU IPMS application
 * Sets up constants, defines paths, and initializes configuration
 * 
 * @package LGU-IPMS
 * @subpackage Config
 * @version 1.0.0
 */

// Determine environment
$env = getenv('APP_ENV') ?? 'development';

// ========== ENVIRONMENT SETTINGS ==========
if (!defined('APP_ENV')) define('APP_ENV', $env);
if (!defined('APP_NAME')) define('APP_NAME', 'LGU Infrastructure Project Management System');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', APP_ENV === 'development');

// ========== APPLICATION PATHS ==========
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
if (!defined('APP_PATH')) define('APP_PATH', ROOT_PATH . 'app/');
if (!defined('API_PATH')) define('API_PATH', ROOT_PATH . 'api/');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', ROOT_PATH . 'includes/');
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', ROOT_PATH . 'config/');
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', ROOT_PATH . 'storage/');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', STORAGE_PATH . 'uploads/');
if (!defined('CACHE_PATH')) define('CACHE_PATH', STORAGE_PATH . 'cache/');

// ========== WEB PATHS (for links/redirects) ==========
if (!defined('ASSETS_URL')) define('ASSETS_URL', '/assets');
if (!defined('API_URL')) define('API_URL', '/api');
if (!defined('APP_URL')) define('APP_URL', getenv('APP_URL') ?? 'https://ipms.infragovservices.com');

// ========== DATABASE CONFIGURATION ==========
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?? 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?? 'ipms_root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?? 'G3P+JANpr2GK6fax');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?? 'ipms_lgu');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ========== SESSION CONFIGURATION ==========
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 30 * 60); // 30 minutes of inactivity
if (!defined('SESSION_SECURE')) define('SESSION_SECURE', APP_ENV === 'production');
if (!defined('SESSION_HTTP_ONLY')) define('SESSION_HTTP_ONLY', true);
if (!defined('SESSION_SAME_SITE')) define('SESSION_SAME_SITE', 'Lax');

// ========== SECURITY SETTINGS ==========
if (!defined('ENABLE_SECURITY_HEADERS')) define('ENABLE_SECURITY_HEADERS', true);
if (!defined('ENABLE_CSRF_PROTECTION')) define('ENABLE_CSRF_PROTECTION', true);
if (!defined('ENABLE_RATE_LIMITING')) define('ENABLE_RATE_LIMITING', APP_ENV === 'production');
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_ATTEMPT_TIMEOUT')) define('LOGIN_ATTEMPT_TIMEOUT', 15 * 60); // 15 minutes

// ========== FILE UPLOAD SETTINGS ==========
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx']);

// ========== APPLICATION CONSTANTS ==========
if (!defined('PROJECT_STATUS')) define('PROJECT_STATUS', [
    'Pending' => 'Pending',
    'Approved' => 'Approved',
    'In Progress' => 'In Progress',
    'Completed' => 'Completed',
    'On Hold' => 'On Hold',
    'Cancelled' => 'Cancelled'
]);

if (!defined('FEEDBACK_STATUS')) define('FEEDBACK_STATUS', [
    'New' => 'New',
    'Acknowledged' => 'Acknowledged',
    'In Progress' => 'In Progress',
    'Resolved' => 'Resolved',
    'Closed' => 'Closed'
]);

if (!defined('USER_ROLES')) define('USER_ROLES', [
    'admin' => 'Administrator',
    'staff' => 'Staff Member',
    'citizen' => 'Citizen',
    'contractor' => 'Contractor'
]);

// ========== LOAD ENVIRONMENT VARIABLES FROM .ENV ==========
if (file_exists(ROOT_PATH . '.env')) {
    $lines = file(ROOT_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue; // Skip invalid lines
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '\'"');
        
        if (!getenv($key)) {
            putenv($key . '=' . $value);
        }
    }
}

// ========== ERROR REPORTING ==========
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ========== TIMEZONE ==========
date_default_timezone_set('Asia/Manila'); // Philippines timezone

// ========== LOCALE ==========
setlocale(LC_TIME, 'en_PH.UTF-8', 'en_PH', 'en');
?>
