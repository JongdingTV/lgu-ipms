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

$file = trim((string) ($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
    http_response_code(400);
    exit('Invalid request');
}

function admin_feedback_photo_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'feedback'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

$hasPhotoPath = admin_feedback_photo_has_column($db, 'photo_path');
$sql = $hasPhotoPath
    ? "SELECT 1 FROM feedback WHERE photo_path = ? OR LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0 LIMIT 1"
    : "SELECT 1 FROM feedback WHERE LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0 LIMIT 1";
$stmt = $db->prepare($sql);
$allowed = false;
if ($stmt) {
    if ($hasPhotoPath) {
        $stmt->bind_param('ss', $file, $file);
    } else {
        $stmt->bind_param('s', $file);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $allowed = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
}
$db->close();

if (!$allowed) {
    http_response_code(404);
    exit('Not found');
}

$baseDirs = [
    str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../private_uploads/lgu-ipms/feedback'),
    str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/private_uploads/lgu-ipms/feedback'),
];

$fullPath = null;
foreach ($baseDirs as $baseDir) {
    $candidate = rtrim($baseDir, '/') . '/' . $file;
    if (@is_file($candidate)) {
        $fullPath = $candidate;
        break;
    }
}

if ($fullPath === null) {
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

