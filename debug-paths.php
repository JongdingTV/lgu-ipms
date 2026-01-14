<?php
// Debug script to check path configuration
header('Content-Type: application/json');

require 'config-path.php';

$debug = [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET',
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
];

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
