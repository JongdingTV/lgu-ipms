<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['admin','department_admin','super_admin']);
check_suspicious_activity();

if (!($db instanceof mysqli) || $db->connect_error) {
    http_response_code(500);
    exit('Database connection failed.');
}

$docId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($docId <= 0) {
    http_response_code(400);
    exit('Invalid document id.');
}

$stmt = $db->prepare("SELECT file_path, original_name, mime_type FROM contractor_documents WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Unable to read document.');
}
$stmt->bind_param('i', $docId);
$stmt->execute();
$res = $stmt->get_result();
$doc = $res ? $res->fetch_assoc() : null;
if ($res) $res->free();
$stmt->close();
$db->close();

if (!$doc || empty($doc['file_path'])) {
    http_response_code(404);
    exit('Document not found.');
}

$appRoot = realpath(dirname(__DIR__));
$storageRoot = realpath($appRoot . DIRECTORY_SEPARATOR . 'storage');
if (!$appRoot || !$storageRoot) {
    http_response_code(500);
    exit('Storage path unavailable.');
}

$relativePath = str_replace(['..', '\\'], ['', '/'], (string)$doc['file_path']);
$fullPath = realpath($storageRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/'));
if (!$fullPath || strpos($fullPath, $storageRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$mimeType = (string)($doc['mime_type'] ?? '');
if ($mimeType === '') {
    $mimeType = (string)(mime_content_type($fullPath) ?: 'application/octet-stream');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename((string)($doc['original_name'] ?? basename($fullPath))) . '"');
readfile($fullPath);
exit;

