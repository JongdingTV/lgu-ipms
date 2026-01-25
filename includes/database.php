<?php
/**
 * Database Connection Handler
 * 
 * Establishes and manages database connection
 * Provides error handling and ensures consistent charset
 * 
 * @package LGU-IPMS
 * @subpackage Database
 * @version 1.0.0
 */

// Load configuration if not already loaded
if (!defined('DB_HOST')) {
    require_once dirname(__FILE__) . '/app.php';
}

// Create database connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($db->connect_error) {
    // Log error securely
    error_log('Database connection failed: ' . $db->connect_error);
    
    // Show user-friendly error
    if (DEBUG_MODE) {
        die('Database connection failed: ' . $db->connect_error);
    } else {
        die('Database connection failed. Please try again later.');
    }
}

// Set charset to UTF-8
if (!$db->set_charset(DB_CHARSET)) {
    error_log('Error setting charset: ' . $db->error);
    if (DEBUG_MODE) {
        die('Error setting charset: ' . $db->error);
    }
}

// Enable error reporting for development
if (DEBUG_MODE) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

/**
 * Get a prepared statement safely
 * 
 * @param string $query SQL query with ? placeholders
 * @return mysqli_stmt|false Prepared statement or false on error
 */
function prepare_query($query) {
    global $db;
    $stmt = $db->prepare($query);
    if (!$stmt) {
        error_log('Prepare error: ' . $db->error);
        return false;
    }
    return $stmt;
}

/**
 * Execute a query with parameters
 * 
 * @param string $query SQL query
 * @param string $types Types of parameters (e.g., 'iss' for int, string, string)
 * @param array $params Parameters to bind
 * @return mysqli_result|bool Query result or false on error
 */
function execute_query($query, $types, ...$params) {
    global $db;
    $stmt = $db->prepare($query);
    
    if (!$stmt) {
        error_log('Prepare error: ' . $db->error);
        return false;
    }
    
    if (!$stmt->bind_param($types, ...$params)) {
        error_log('Bind error: ' . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log('Execute error: ' . $stmt->error);
        return false;
    }
    
    return $stmt->get_result();
}

/**
 * Close database connection on script end
 */
register_shutdown_function(function() {
    global $db;
    if ($db) {
        $db->close();
    }
});
?>
