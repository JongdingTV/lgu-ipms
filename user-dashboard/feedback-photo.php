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
 $feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$photoIndex = max(0, (int) ($_GET['photo_index'] ?? 0));
if ($userId <= 0 || ($feedbackId <= 0 && ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)))) {
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
if (!function_exists('feedback_extract_photo_files_endpoint')) {
    function feedback_extract_photo_files_endpoint(string $description, string $photoPathRaw = ''): array
    {
        $files = [];
        $add = static function (string $value) use (&$files): void {
            $base = basename(str_replace('\\', '/', trim($value)));
            if ($base !== '' && preg_match('/^[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$/i', $base)) {
                $files[$base] = true;
            }
        };
        if ($photoPathRaw !== '') $add($photoPathRaw);
        if (preg_match_all('/\[Photo Attachment Private\]\s+([^\s]+\.(?:jpg|jpeg|png|webp))/i', $description, $m1)) {
            foreach ($m1[1] as $candidate) $add((string)$candidate);
        }
        if (preg_match_all('/\[Photo Attachment\]\s+([^\s]+\.(?:jpg|jpeg|png|webp))/i', $description, $m2)) {
            foreach ($m2[1] as $candidate) $add((string)$candidate);
        }
        return array_values(array_keys($files));
    }
}

$targetFile = '';
if ($feedbackId > 0) {
    $selectCols = ['id', 'description', 'user_name'];
    if ($hasPhotoPath) $selectCols[] = 'photo_path';
    if ($hasUserId) $selectCols[] = 'user_id';
    $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM feedback WHERE id = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $db->close();
        http_response_code(500);
        exit('Query failed');
    }
    $stmt->bind_param('i', $feedbackId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $db->close();
        http_response_code(404);
        exit('Not found');
    }

    // Ownership check: prefer user_id match when available, fallback to user_name.
    $ownerOk = false;
    if ($hasUserId && isset($row['user_id']) && (int)$row['user_id'] > 0) {
        $ownerOk = ((int)$row['user_id'] === $userId);
    }
    if (!$ownerOk && $userName !== '') {
        $ownerOk = (strcasecmp(trim((string)($row['user_name'] ?? '')), $userName) === 0);
    }
    if (!$ownerOk) {
        $db->close();
        http_response_code(403);
        exit('Forbidden');
    }
    $photos = feedback_extract_photo_files_endpoint((string)($row['description'] ?? ''), (string)($row['photo_path'] ?? ''));
    if (!empty($photos) && isset($photos[$photoIndex])) {
        $targetFile = (string)$photos[$photoIndex];
    } elseif ($file !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
        // Fallback: trust explicit file name only after ownership already passed.
        $targetFile = $file;
    } else {
        $db->close();
        http_response_code(404);
        exit('Not found');
    }
} else {
    $targetFile = $file;
}
$db->close();

function feedback_photo_candidate_dirs(): array
{
    return [
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../private_uploads/lgu-ipms/feedback'),
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/private_uploads/lgu-ipms/feedback'),
        // Fallback for deployments that store feedback images in public uploads.
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/uploads/feedback'),
        str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../uploads/feedback')
    ];
}

$fullPath = null;
foreach (feedback_photo_candidate_dirs() as $baseDir) {
    $candidate = rtrim($baseDir, '/') . '/' . $targetFile;
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
