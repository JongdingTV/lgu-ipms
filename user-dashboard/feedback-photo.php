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

$userId = (int) ($_SESSION['user_id'] ?? 0);
$file = trim((string) ($_GET['file'] ?? ''));
$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$photoIndex = max(0, (int) ($_GET['photo_index'] ?? 0));
if ($userId <= 0 || ($feedbackId <= 0 && ($file === '' || !preg_match('/^[A-Za-z0-9._()\- ]+\.(jpg|jpeg|png|webp)$/i', $file)))) {
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
        $files = ['raw' => [], 'base' => []];
        $add = static function (string $value) use (&$files): void {
            $normalized = trim(rawurldecode(str_replace('\\', '/', trim($value))), " \t\n\r\0\x0B\"'");
            if ($normalized === '') {
                return;
            }
            if (preg_match('/\.(?:jpg|jpeg|png|webp)$/i', $normalized)) {
                $files['raw'][$normalized] = true;
            }
            $base = basename($normalized);
            if ($base !== '' && preg_match('/\.(?:jpg|jpeg|png|webp)$/i', $base)) {
                $files['base'][$base] = true;
            }
        };
        if ($photoPathRaw !== '') {
            $parts = preg_split('/[;,]/', $photoPathRaw) ?: [$photoPathRaw];
            foreach ($parts as $part) {
                $add((string) $part);
            }
        }
        if (preg_match_all('/\[Photo Attachment Private\]\s+([^\r\n]+)/i', $description, $m1)) {
            foreach ($m1[1] as $candidate) {
                $add((string) $candidate);
            }
        }
        if (preg_match_all('/\[Photo Attachment\]\s+([^\r\n]+)/i', $description, $m2)) {
            foreach ($m2[1] as $candidate) {
                $add((string) $candidate);
            }
        }
        $ordered = array_values(array_keys($files['raw']));
        foreach (array_keys($files['base']) as $base) {
            if (!in_array($base, $ordered, true)) {
                $ordered[] = $base;
            }
        }
        return $ordered;
    }
}

$targetFile = '';
$photoPathRaw = '';
if ($feedbackId > 0) {
    $selectCols = ['id', 'description', 'user_name'];
    if ($hasPhotoPath) $selectCols[] = 'photo_path';
    if ($hasUserId) $selectCols[] = 'user_id';
    $sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM feedback WHERE id = ?';
    if ($hasUserId) {
        // Permit legacy rows with NULL/0 user_id if user_name still matches current account.
        $sql .= ' AND (user_id = ? OR ((user_id IS NULL OR user_id = 0) AND user_name = ?))';
    } else {
        $sql .= ' AND user_name = ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $db->close();
        http_response_code(500);
        exit('Query failed');
    }
    if ($hasUserId) {
        $stmt->bind_param('iis', $feedbackId, $userId, $userName);
    } else {
        $stmt->bind_param('is', $feedbackId, $userName);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $db->close();
        http_response_code(403);
        exit('Forbidden');
    }
    $photoPathRaw = trim((string)($row['photo_path'] ?? ''));
    $photos = feedback_extract_photo_files_endpoint((string)($row['description'] ?? ''), (string)($row['photo_path'] ?? ''));
    if (!empty($photos) && isset($photos[$photoIndex])) {
        $targetFile = (string)$photos[$photoIndex];
    } elseif ($file !== '' && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file)) {
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
$candidates = [];

// 1) Respect explicit stored path first, if available.
if ($photoPathRaw !== '') {
    $normalized = str_replace(['\\', '//'], ['/', '/'], $photoPathRaw);
    $parts = preg_split('/[;,]/', $normalized) ?: [$normalized];
    foreach ($parts as $pathPart) {
        $part = trim((string) $pathPart);
        if ($part === '') {
            continue;
        }
        if (preg_match('#^/[A-Za-z]:/#', $part) || preg_match('#^[A-Za-z]:/#', $part)) {
            $candidates[] = $part;
        } else {
            $candidates[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/' . ltrim($part, '/'));
            $candidates[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../' . ltrim($part, '/'));
        }
    }
}

// 2) Fallback to filename lookup in known directories.
if ($targetFile !== '') {
    $targetBase = basename(str_replace('\\', '/', $targetFile));
    $targetRaw = str_replace(['\\', '//'], ['/', '/'], $targetFile);
    if (strpos($targetRaw, '/') !== false) {
        $candidates[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/' . ltrim($targetRaw, '/'));
        $candidates[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../' . ltrim($targetRaw, '/'));
    }
    foreach (feedback_photo_candidate_dirs() as $baseDir) {
        $candidates[] = rtrim($baseDir, '/') . '/' . $targetFile;
        $candidates[] = rtrim($baseDir, '/') . '/' . $targetBase;
    }
}

// 3) Try all candidate file paths.
foreach ($candidates as $candidate) {
    if (@is_file($candidate)) {
        $fullPath = $candidate;
        break;
    }
}

// 4) Last-resort DB lookup by file token using ownership constraints (matches legacy path formats).
if ($fullPath === null && $targetFile !== '') {
    require dirname(__DIR__) . '/database.php';
    if (isset($db) && !($db->connect_error ?? false)) {
        $fileToken = basename(str_replace('\\', '/', $targetFile));
        $selectParts = ['photo_path', 'description'];
        if ($hasUserId) $selectParts[] = 'user_id';
        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM feedback WHERE ';
        if ($hasUserId) {
            $sql .= '(user_id = ? OR ((user_id IS NULL OR user_id = 0) AND user_name = ?)) AND ';
        } else {
            $sql .= 'user_name = ? AND ';
        }
        $sql .= '('
            . 'photo_path = ? OR photo_path LIKE ? OR REPLACE(photo_path, \'\\\\\', \'/\') LIKE ? '
            . 'OR LOCATE(CONCAT(\'[Photo Attachment Private] \', ?), description) > 0 '
            . 'OR LOCATE(CONCAT(\'[Photo Attachment] \', ?), description) > 0'
            . ') LIMIT 1';

        $stmt = $db->prepare($sql);
        if ($stmt) {
            $likeTail = '%/' . $fileToken;
            if ($hasUserId) {
                $stmt->bind_param('issssss', $userId, $userName, $fileToken, $likeTail, $likeTail, $fileToken, $fileToken);
            } else {
                $stmt->bind_param('ssssss', $userName, $fileToken, $likeTail, $likeTail, $fileToken, $fileToken);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($res) {
                $res->free();
            }
            if ($row) {
                $rowPhoto = trim((string) ($row['photo_path'] ?? ''));
                $scanTargets = feedback_extract_photo_files_endpoint((string) ($row['description'] ?? ''), $rowPhoto);
                if ($rowPhoto !== '') {
                    $scanTargets[] = $rowPhoto;
                }
                $scanTargets[] = $fileToken;

                foreach ($scanTargets as $scan) {
                    $scanRaw = str_replace(['\\', '//'], ['/', '/'], (string) $scan);
                    $scanBase = basename($scanRaw);
                    $probe = [];
                    if (strpos($scanRaw, '/') !== false) {
                        $probe[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/' . ltrim($scanRaw, '/'));
                        $probe[] = str_replace(['\\', '//'], ['/', '/'], dirname(__DIR__) . '/../' . ltrim($scanRaw, '/'));
                    }
                    foreach (feedback_photo_candidate_dirs() as $baseDir) {
                        $probe[] = rtrim($baseDir, '/') . '/' . $scanRaw;
                        $probe[] = rtrim($baseDir, '/') . '/' . $scanBase;
                    }
                    foreach ($probe as $candidate) {
                        if (@is_file($candidate)) {
                            $fullPath = $candidate;
                            break 2;
                        }
                    }
                }
            }
        }
        $db->close();
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
