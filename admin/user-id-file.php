<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
set_no_cache_headers();
check_auth();
check_suspicious_activity();

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0 || !isset($db) || $db->connect_error) {
    http_response_code(404);
    exit;
}

$stmt = $db->prepare('SELECT id_upload FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(404);
    exit;
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if ($res) $res->free();
$stmt->close();
$db->close();

$stored = (string) ($row['id_upload'] ?? '');
if ($stored === '') {
    http_response_code(404);
    exit;
}

$path = str_replace('\\', '/', ltrim($stored, '/'));
$baseName = basename($path);
if ($baseName === '' || $baseName !== basename($baseName)) {
    http_response_code(404);
    exit;
}

$candidates = [
    dirname(__DIR__) . '/uploads/user-ids/' . $baseName,
    dirname(__DIR__) . '/uploads/user-id/' . $baseName,
];
$file = '';
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $file = $candidate;
        break;
    }
}
if ($file === '') {
    http_response_code(404);
    exit;
}

$ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (in_array($ext, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
if ($ext === 'png') $mime = 'image/png';
if ($ext === 'webp') $mime = 'image/webp';
if ($ext === 'pdf') $mime = 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: inline; filename="' . $baseName . '"');
readfile($file);
