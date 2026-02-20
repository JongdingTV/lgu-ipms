<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$file = trim((string) ($_GET['file'] ?? ''));
if ($userId <= 0 || $file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
    http_response_code(400);
    exit('Invalid request');
}

$stmt = $db->prepare(
    "SELECT 1
     FROM feedback
     WHERE (user_id = ? OR user_name = ?)
       AND LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0
     LIMIT 1"
);
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$stmt->bind_param('iss', $userId, $userName, $file);
$stmt->execute();
$res = $stmt->get_result();
$allowed = $res && $res->num_rows > 0;
$stmt->close();
$db->close();

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$baseDir = dirname(__DIR__, 3) . '/private_uploads/lgu-ipms/feedback';
$fullPath = $baseDir . '/' . $file;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Not found');
}

$mime = (string) (mime_content_type($fullPath) ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
