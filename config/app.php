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
define('APP_ENV', $env);
define('APP_NAME', 'LGU Infrastructure Project Management System');
define('APP_VERSION', '1.0.0');
define('DEBUG_MODE', APP_ENV === 'development');

// ========== APPLICATION PATHS ==========
define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('APP_PATH', ROOT_PATH . 'app/');
define('API_PATH', ROOT_PATH . 'api/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('STORAGE_PATH', ROOT_PATH . 'storage/');
define('UPLOADS_PATH', STORAGE_PATH . 'uploads/');
define('CACHE_PATH', STORAGE_PATH . 'cache/');

// ========== WEB PATHS (for links/redirects) ==========
define('ASSETS_URL', '/assets');
define('API_URL', '/api');
define('APP_URL', getenv('APP_URL') ?? 'https://ipms.infragovservices.com');

// ========== DATABASE CONFIGURATION ==========
define('DB_HOST', getenv('DB_HOST') ?? 'localhost');
define('DB_USER', getenv('DB_USER') ?? 'ipms_root');
define('DB_PASS', getenv('DB_PASS') ?? 'G3P+JANpr2GK6fax');
define('DB_NAME', getenv('DB_NAME') ?? 'ipms_lgu');
define('DB_CHARSET', 'utf8mb4');

// ========== SESSION CONFIGURATION ==========
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes of inactivity
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Lax');

// ========== SECURITY SETTINGS ==========
define('ENABLE_SECURITY_HEADERS', true);
define('ENABLE_CSRF_PROTECTION', true);
define('ENABLE_RATE_LIMITING', APP_ENV === 'production');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 15 * 60); // 15 minutes

// ========== FILE UPLOAD SETTINGS ==========
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx']);

// ========== APPLICATION CONSTANTS ==========
define('PROJECT_STATUS', [
    'Pending' => 'Pending',
    'Approved' => 'Approved',
    'In Progress' => 'In Progress',
    'Completed' => 'Completed',
    'On Hold' => 'On Hold',
    'Cancelled' => 'Cancelled'
]);

define('FEEDBACK_STATUS', [
    'New' => 'New',
    'Acknowledged' => 'Acknowledged',
    'In Progress' => 'In Progress',
    'Resolved' => 'Resolved',
    'Closed' => 'Closed'
]);

define('USER_ROLES', [
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
