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

@ini_set('display_errors', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}

$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$file = trim((string) ($_GET['file'] ?? ''));
if ($feedbackId <= 0 && ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file))) {
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
$photoPathRaw = '';
$descriptionRaw = '';

if ($feedbackId > 0) {
    $selectParts = ['description'];
    if ($hasPhotoPath) {
        $selectParts[] = 'photo_path';
    }
    $stmt = $db->prepare('SELECT ' . implode(', ', $selectParts) . ' FROM feedback WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $feedbackId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $photoPathRaw = trim((string) ($row['photo_path'] ?? ''));
            $descriptionRaw = (string) ($row['description'] ?? '');
        }
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }

    if ($file === '' && $photoPathRaw !== '') {
        $file = basename(str_replace('\\', '/', $photoPathRaw));
    }

    if ($file === '' && preg_match('/\[Photo Attachment Private\]\s+([\w\-.]+\.(?:jpg|jpeg|png|webp))/i', $descriptionRaw, $m)) {
        $file = (string) ($m[1] ?? '');
    }
} else {
    $sql = $hasPhotoPath
        ? "SELECT photo_path, description FROM feedback
           WHERE photo_path = ?
              OR photo_path LIKE ?
              OR REPLACE(photo_path, '\\\\', '/') LIKE ?
              OR LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0
           LIMIT 1"
        : "SELECT description FROM feedback
           WHERE LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0
           LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $likeTail = '%/' . $file;
            $stmt->bind_param('ssss', $file, $likeTail, $likeTail, $file);
        } else {
            $stmt->bind_param('s', $file);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $photoPathRaw = trim((string) ($row['photo_path'] ?? ''));
            $descriptionRaw = (string) ($row['description'] ?? '');
            if ($file === '' && $photoPathRaw !== '') {
                $file = basename(str_replace('\\', '/', $photoPathRaw));
            }
        }
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }
}

$db->close();

if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
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

