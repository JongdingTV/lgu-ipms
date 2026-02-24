<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.engineers.manage', ['admin','department_admin','super_admin']);
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

$source = strtolower(trim((string)($_GET['source'] ?? '')));
$candidateTables = [];
if ($source === 'engineer') {
    $candidateTables = ['engineer_documents', 'contractor_documents'];
} elseif ($source === 'contractor') {
    $candidateTables = ['contractor_documents', 'engineer_documents'];
} else {
    $candidateTables = ['engineer_documents', 'contractor_documents'];
}

$pickColumn = static function (mysqli $dbConn, string $table, array $candidates): ?string {
    foreach ($candidates as $column) {
        $check = $dbConn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        if (!$check) {
            continue;
        }
        $check->bind_param('ss', $table, $column);
        $check->execute();
        $checkRes = $check->get_result();
        $exists = $checkRes && $checkRes->num_rows > 0;
        if ($checkRes) {
            $checkRes->free();
        }
        $check->close();
        if ($exists) {
            return $column;
        }
    }
    return null;
};

$tableExists = static function (mysqli $dbConn, string $table): bool {
    $check = $dbConn->prepare(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    if (!$check) {
        return false;
    }
    $check->bind_param('s', $table);
    $check->execute();
    $checkRes = $check->get_result();
    $exists = $checkRes && $checkRes->num_rows > 0;
    if ($checkRes) {
        $checkRes->free();
    }
    $check->close();
    return $exists;
};

$doc = null;
foreach ($candidateTables as $table) {
    if (!$tableExists($db, $table)) {
        continue;
    }
    $pathCol = $pickColumn($db, $table, ['file_path', 'path', 'document_path', 'storage_path']);
    if (!$pathCol) {
        continue;
    }
    $nameCol = $pickColumn($db, $table, ['original_name', 'filename', 'file_name', 'name']);
    $mimeCol = $pickColumn($db, $table, ['mime_type', 'mime']);
    $selectName = $nameCol ? "{$nameCol} AS original_name" : "'' AS original_name";
    $selectMime = $mimeCol ? "{$mimeCol} AS mime_type" : "'' AS mime_type";

    $stmt = $db->prepare("SELECT {$pathCol} AS file_path, {$selectName}, {$selectMime} FROM {$table} WHERE id = ? LIMIT 1");
    if (!$stmt) {
        continue;
    }
    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    if ($row && !empty($row['file_path'])) {
        $doc = $row;
        break;
    }
}
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
