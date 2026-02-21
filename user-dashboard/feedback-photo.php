<?php
if (ob_get_level() === 0) {
    ob_start();
}

require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    exit('Database connection failed');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$employeeId = (int) ($_SESSION['employee_id'] ?? 0);
$isAdminSession = $employeeId > 0;
$file = trim((string) ($_GET['file'] ?? ''));
$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$debugMode = $isAdminSession && (string)($_GET['debug'] ?? '') === '1';
if (($userId <= 0 && !$isAdminSession) || ($file === '' && $feedbackId <= 0)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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

function feedback_extract_photo_marker(string $description): ?string
{
    if (preg_match('/\[(Photo Attachment Private)\]\s*(.*?)(?=\s*\[[^\]]+\]|$)/is', $description, $matches)) {
        $candidate = basename(trim((string) ($matches[2] ?? '')));
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $candidate)) {
            return $candidate;
        }
    }
    return null;
}

$hasUserId = feedback_photo_has_column($db, 'user_id');
$hasPhotoPath = feedback_photo_has_column($db, 'photo_path');
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$resolvedFile = '';
$rawPhotoPath = '';

if ($feedbackId > 0) {
    $select = ['id', 'description', 'user_name'];
    if ($hasUserId) {
        $select[] = 'user_id';
    }
    if ($hasPhotoPath) {
        $select[] = 'photo_path';
    }

    $stmt = $db->prepare('SELECT ' . implode(', ', $select) . ' FROM feedback WHERE id = ? LIMIT 1');
    if (!$stmt) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        exit('Query failed');
    }
    $stmt->bind_param('i', $feedbackId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(404);
        exit('Not found');
    }

    if (!$isAdminSession) {
        $ownerOk = false;
        if ($hasUserId && isset($row['user_id'])) {
            $ownerOk = ((int) $row['user_id'] === $userId);
        }
        if (!$ownerOk) {
            $ownerOk = ((string) ($row['user_name'] ?? '') === $userName);
        }
        if (!$ownerOk) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(403);
            exit('Forbidden');
        }
    }

    if ($hasPhotoPath) {
        $rawPhotoPath = trim((string) ($row['photo_path'] ?? ''));
        $candidate = basename($rawPhotoPath);
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $candidate)) {
            $resolvedFile = $candidate;
        }
    }
    if ($resolvedFile === '') {
        $resolvedFile = (string) (feedback_extract_photo_marker((string) ($row['description'] ?? '')) ?? '');
    }
} else {
    if (!preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$/i', $file)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(400);
        exit('Invalid request');
    }
    $resolvedFile = $file;
}

if ($resolvedFile === '') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    exit('Not found');
}

$photoMatchSql = $hasPhotoPath
    ? "(photo_path = ? OR LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0)"
    : "LOCATE(CONCAT('[Photo Attachment Private] ', ?), description) > 0";

if ($isAdminSession) {
    $sql = "SELECT 1 FROM feedback WHERE {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('ss', $resolvedFile, $resolvedFile);
        } else {
            $stmt->bind_param('s', $resolvedFile);
        }
    }
} elseif ($hasUserId) {
    $sql = "SELECT 1 FROM feedback WHERE (user_id = ? OR user_name = ?) AND {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('isss', $userId, $userName, $resolvedFile, $resolvedFile);
        } else {
            $stmt->bind_param('iss', $userId, $userName, $resolvedFile);
        }
    }
} else {
    $sql = "SELECT 1 FROM feedback WHERE user_name = ? AND {$photoMatchSql} LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        if ($hasPhotoPath) {
            $stmt->bind_param('sss', $userName, $resolvedFile, $resolvedFile);
        } else {
            $stmt->bind_param('ss', $userName, $resolvedFile);
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
if ($rawPhotoPath !== '' && (strpos($rawPhotoPath, '/') !== false || strpos($rawPhotoPath, '\\') !== false)) {
    $directPath = str_replace(['\\', '//'], ['/', '/'], $rawPhotoPath);
    if (@is_file($directPath)) {
        $fullPath = $directPath;
    }
}

$pathChecks = [];
foreach (feedback_photo_candidate_dirs() as $baseDir) {
    $candidate = rtrim($baseDir, '/') . '/' . $resolvedFile;
    $exists = @is_file($candidate);
    $pathChecks[] = ['path' => $candidate, 'exists' => $exists];
    if ($exists) {
        $fullPath = $candidate;
        break;
    }
}

if ($fullPath === null) {
    if ($debugMode) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Photo file not found on server',
            'feedback_id' => $feedbackId,
            'resolved_file' => $resolvedFile,
            'raw_photo_path' => $rawPhotoPath,
            'path_checks' => $pathChecks
        ], JSON_PRETTY_PRINT);
        exit;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    exit('Not found');
}

$mime = (string) (mime_content_type($fullPath) ?: 'application/octet-stream');
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
