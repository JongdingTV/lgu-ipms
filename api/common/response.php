<?php
/**
 * API Response Handler
 * 
 * Standardized API response formatting for all endpoints
 * 
 * @package LGU-IPMS
 * @subpackage API
 * @version 1.0.0
 */

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

/**
 * Send success response
 * 
 * @param mixed $data Data to return
 * @param int $code HTTP status code (default 200)
 * @param string $message Optional success message
 * @return void
 */
function send_success($data = [], $code = 200, $message = 'Success') {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Send error response
 * 
 * @param string $error Error message
 * @param int $code HTTP status code (default 400)
 * @param array $details Optional error details
 * @return void
 */
function send_error($error, $code = 400, $details = []) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $error,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Send created response (HTTP 201)
 * 
 * @param mixed $data Created data
 * @param string $message Optional message
 * @return void
 */
function send_created($data = [], $message = 'Resource created successfully') {
    send_success($data, 201, $message);
}

/**
 * Send no content response (HTTP 204)
 * 
 * @return void
 */
function send_no_content() {
    http_response_code(204);
    exit;
}

/**
 * Send unauthorized response (HTTP 401)
 * 
 * @param string $message Error message
 * @return void
 */
function send_unauthorized($message = 'Unauthorized access') {
    send_error($message, 401);
}

/**
 * Send forbidden response (HTTP 403)
 * 
 * @param string $message Error message
 * @return void
 */
function send_forbidden($message = 'Access forbidden') {
    send_error($message, 403);
}

/**
 * Send not found response (HTTP 404)
 * 
 * @param string $message Error message
 * @return void
 */
function send_not_found($message = 'Resource not found') {
    send_error($message, 404);
}

/**
 * Send validation error response (HTTP 422)
 * 
 * @param array $errors Validation errors
 * @param string $message Error message
 * @return void
 */
function send_validation_error($errors = [], $message = 'Validation failed') {
    send_error($message, 422, $errors);
}

/**
 * Send server error response (HTTP 500)
 * 
 * @param string $message Error message
 * @return void
 */
function send_server_error($message = 'Internal server error') {
    send_error($message, 500);
}

/**
 * Get JSON request body
 * 
 * @return array Decoded JSON data
 */
function get_json_body() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Check allowed HTTP methods
 * 
 * @param string|array $methods Allowed methods (e.g., 'GET' or ['GET', 'POST'])
 * @return void
 */
function check_method($methods) {
    if (is_string($methods)) {
        $methods = [$methods];
    }
    
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        send_error(
            'Method not allowed. Expected: ' . implode(', ', $methods),
            405
        );
    }
}

/**
 * Check request is AJAX
 * 
 * @return bool True if AJAX request
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
?>
