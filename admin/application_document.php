<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('admin.applications.view', ['admin','department_admin','super_admin']);
check_suspicious_activity();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt = $db->prepare("SELECT file_path, original_name, mime_type FROM application_documents WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Unable to load document.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if ($res) $res->free();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Document not found.');
}

$relative = ltrim((string)($row['file_path'] ?? ''), '/');
$base = realpath(dirname(__DIR__) . '/storage/private_uploads/applications');
if (!$base) {
    http_response_code(404);
    exit('Storage path not found.');
}
$fullPath = realpath($base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
if (!$fullPath || strpos($fullPath, $base) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('Document file not found.');
}

$mime = (string)($row['mime_type'] ?? 'application/octet-stream');
$name = (string)($row['original_name'] ?? basename($fullPath));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
readfile($fullPath);
exit;
