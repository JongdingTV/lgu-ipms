<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.citizen_verification.manage', ['admin','department_admin','super_admin']);
check_suspicious_activity();

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0 || !isset($db) || $db->connect_error) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $db->prepare('SELECT id_upload FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(404);
    exit('Not found');
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if ($res) $res->free();
$stmt->close();
$db->close();

$stored = trim((string) ($row['id_upload'] ?? ''));
if ($stored === '') {
    http_response_code(404);
    exit('Not found');
}

$base = basename(str_replace('\\', '/', $stored));
if ($base === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp|pdf)$/i', $base)) {
    http_response_code(404);
    exit('Not found');
}

$candidates = [
    dirname(__DIR__) . '/uploads/user-id/' . $base,
    dirname(__DIR__) . '/uploads/user-ids/' . $base,
    dirname(__DIR__) . '/' . ltrim($stored, '/'),
];

$fullPath = '';
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $fullPath = $candidate;
        break;
    }
}

if ($fullPath === '') {
    http_response_code(404);
    exit('Not found');
}

$ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
if ($ext === 'png') $mime = 'image/png';
if ($ext === 'webp') $mime = 'image/webp';
if ($ext === 'pdf') $mime = 'application/pdf';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
