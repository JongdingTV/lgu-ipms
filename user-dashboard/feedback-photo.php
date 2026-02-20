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
$employeeId = (int) ($_SESSION['employee_id'] ?? 0);
$isAdminSession = $employeeId > 0;
$file = trim((string) ($_GET['file'] ?? ''));
if (($userId <= 0 && !$isAdminSession) || $file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
    http_response_code(400);
    exit('Invalid request');
}

function feedback_photo_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'feedback'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $exists;
}

$hasUserId = feedback_photo_has_column($db, 'user_id');
$hasPhotoPath = feedback_photo_has_column($db, 'photo_path');
$userName = trim((string) ($_SESSION['user_name'] ?? ''));

$photoMatchSql = $hasPhotoPath
    ? "(photo_path = ? OR LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0)"
    : "LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0";

if ($isAdminSession) {
    $sql = "SELECT 1 FROM feedback WHERE {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('ss', $file, $file);
        } else {
            $stmt->bind_param('s', $file);
        }
    }
} elseif ($hasUserId) {
    $sql = "SELECT 1 FROM feedback WHERE (user_id = ? OR user_name = ?) AND {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('isss', $userId, $userName, $file, $file);
        } else {
            $stmt->bind_param('iss', $userId, $userName, $file);
        }
    }
} else {
    $sql = "SELECT 1 FROM feedback WHERE user_name = ? AND {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('sss', $userName, $file, $file);
        } else {
            $stmt->bind_param('ss', $userName, $file);
        }
    }
}

$allowed = false;
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $allowed = $res && $res->num_rows > 0;
    $stmt->close();
}
$db->close();

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

function feedback_photo_candidate_dirs(): array
{
    return [
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../private_uploads/lgu-ipms/feedback'),
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/private_uploads/lgu-ipms/feedback')
    ];
}

$fullPath = null;
foreach (feedback_photo_candidate_dirs() as $baseDir) {
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
