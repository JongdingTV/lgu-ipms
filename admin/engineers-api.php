<?php
require dirname(__DIR__) . '/session-auth.php';
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['admin','department_admin','super_admin']);
check_suspicious_activity();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

if ($db->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$postedAction = strtolower(trim((string)($_POST['action'] ?? '')));
$rbacAction = '';
if ($method === 'GET') {
    $rbacAction = 'list_engineers';
} elseif ($method === 'POST') {
    $rbacAction = $postedAction !== '' ? $postedAction : 'create_engineer';
} elseif ($method === 'PUT') {
    $rbacAction = 'update_engineer';
} elseif ($method === 'DELETE') {
    $rbacAction = 'delete_engineer';
}

rbac_require_action_roles(
    $rbacAction,
    [
        'list_engineers' => ['admin', 'department_admin', 'super_admin'],
        'create_with_docs' => ['admin', 'department_admin', 'super_admin'],
        'create_engineer' => ['admin', 'department_admin', 'super_admin'],
        'update_engineer' => ['admin', 'department_admin', 'super_admin'],
        'delete_engineer' => ['admin', 'super_admin'],
    ],
    ['admin', 'department_admin', 'super_admin']
);

function capi_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function capi_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function capi_normalize_text($value): string
{
    return trim((string)($value ?? ''));
}

function capi_safe_filename(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name);
    return trim((string)$name, '_');
}

function capi_store_uploaded_doc(array $file, int $contractorId, string $docType): ?array
{
    if (!isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $maxSize = 5 * 1024 * 1024;
    if ((int)($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File exceeds max size (5MB).');
    }

    $tmpPath = (string)$file['tmp_name'];
    $mime = (string)(mime_content_type($tmpPath) ?: '');
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Invalid file type. Allowed: PDF, JPG, PNG.');
    }

    $root = realpath(dirname(__DIR__));
    if (!$root) {
        throw new RuntimeException('Application root not found.');
    }
    $uploadDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'engineers';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Failed to create upload directory.');
    }

    $ext = $allowed[$mime];
    $base = capi_safe_filename((string)($file['name'] ?? 'document'));
    $random = bin2hex(random_bytes(6));
    $filename = sprintf('eng_%d_%s_%s_%s.%s', $contractorId, strtolower($docType), date('Ymd_His'), $random, $ext);
    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $absolutePath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return [
        'document_type' => $docType,
        'file_path' => 'uploads/engineers/' . $filename,
        'original_name' => (string)($file['name'] ?? ''),
        'mime_type' => $mime,
        'file_size' => (int)($file['size'] ?? 0),
    ];
}

if ($method === 'GET') {
    $result = $db->query("SELECT * FROM contractors ORDER BY created_at DESC");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    echo json_encode($rows);
    $db->close();
    exit;
}

if ($method === 'POST') {
    $action = capi_normalize_text($_POST['action'] ?? '');

    if ($action === 'create_with_docs') {
        $fullName = capi_normalize_text($_POST['full_name'] ?? '');
        $company = capi_normalize_text($_POST['company'] ?? '');
        $owner = capi_normalize_text($_POST['owner'] ?? '');
        $license = capi_normalize_text($_POST['license'] ?? '');
        $licenseExpiry = capi_normalize_text($_POST['license_expiration_date'] ?? '');
        $email = capi_normalize_text($_POST['email'] ?? '');
        $phone = capi_normalize_text($_POST['phone'] ?? '');
        $address = capi_normalize_text($_POST['address'] ?? '');
        $specialization = capi_normalize_text($_POST['specialization'] ?? '');
        $certifications = capi_normalize_text($_POST['certifications_text'] ?? '');
        $experience = max(0, (int)($_POST['experience'] ?? 0));
        $rating = max(0, min(5, (float)($_POST['rating'] ?? 0)));
        $status = capi_normalize_text($_POST['status'] ?? 'Active');
        $complianceStatus = capi_normalize_text($_POST['compliance_status'] ?? 'Compliant');
        $notes = capi_normalize_text($_POST['notes'] ?? '');

        if ($fullName === '' || $license === '' || $licenseExpiry === '') {
            echo json_encode(['success' => false, 'error' => 'Full name, license number, and license expiration date are required.']);
            $db->close();
            exit;
        }
        if (strtotime($licenseExpiry) === false || strtotime($licenseExpiry) < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'error' => 'License is expired or invalid. Please upload a valid active license.']);
            $db->close();
            exit;
        }

        if (!isset($_FILES['license_document'], $_FILES['resume_document'], $_FILES['certificate_document'])) {
            echo json_encode(['success' => false, 'error' => 'License, resume, and certificate uploads are required.']);
            $db->close();
            exit;
        }
        if (!capi_table_exists($db, 'contractor_documents')) {
            echo json_encode(['success' => false, 'error' => 'Document table is missing. Run migration 2026_02_21_engineer_hiring_module.sql first.']);
            $db->close();
            exit;
        }

        // Duplicate guard: same license number
        $dupStmt = $db->prepare("SELECT id FROM contractors WHERE license = ? LIMIT 1");
        if ($dupStmt) {
            $dupStmt->bind_param('s', $license);
            $dupStmt->execute();
            $dupRes = $dupStmt->get_result();
            $exists = $dupRes && $dupRes->num_rows > 0;
            if ($dupRes) $dupRes->free();
            $dupStmt->close();
            if ($exists) {
                echo json_encode(['success' => false, 'error' => 'License number already exists.']);
                $db->close();
                exit;
            }
        }

        $columns = ['company', 'owner', 'license', 'email', 'phone', 'address', 'specialization', 'experience', 'rating', 'status', 'notes'];
        $values = [$company, $owner, $license, $email, $phone, $address, $specialization, $experience, $rating, $status, $notes];
        $types = 'sssssssidss';

        if (capi_column_exists($db, 'contractors', 'full_name')) {
            $columns[] = 'full_name';
            $values[] = $fullName;
            $types .= 's';
        }
        if (capi_column_exists($db, 'contractors', 'license_expiration_date')) {
            $columns[] = 'license_expiration_date';
            $values[] = $licenseExpiry;
            $types .= 's';
        }
        if (capi_column_exists($db, 'contractors', 'certifications_text')) {
            $columns[] = 'certifications_text';
            $values[] = $certifications;
            $types .= 's';
        }
        if (capi_column_exists($db, 'contractors', 'compliance_status')) {
            $columns[] = 'compliance_status';
            $values[] = $complianceStatus;
            $types .= 's';
        }

        $sql = "INSERT INTO contractors (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Failed to prepare insert: ' . $db->error]);
            $db->close();
            exit;
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            echo json_encode(['success' => false, 'error' => 'Failed to create engineer: ' . $err]);
            $db->close();
            exit;
        }
        $contractorId = (int)$db->insert_id;
        $stmt->close();

        try {
            $docs = [];
            $docs[] = capi_store_uploaded_doc($_FILES['license_document'], $contractorId, 'license');
            $docs[] = capi_store_uploaded_doc($_FILES['resume_document'], $contractorId, 'resume');
            $docs[] = capi_store_uploaded_doc($_FILES['certificate_document'], $contractorId, 'certificate');
            $docs = array_values(array_filter($docs));

            foreach ($docs as $doc) {
                $ins = $db->prepare(
                    "INSERT INTO contractor_documents (contractor_id, document_type, file_path, original_name, mime_type, file_size, expires_on)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                if ($ins) {
                    $expiresOn = $doc['document_type'] === 'license' ? $licenseExpiry : null;
                    $ins->bind_param(
                        'issssis',
                        $contractorId,
                        $doc['document_type'],
                        $doc['file_path'],
                        $doc['original_name'],
                        $doc['mime_type'],
                        $doc['file_size'],
                        $expiresOn
                    );
                    $ins->execute();
                    $ins->close();
                }
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            $db->close();
            exit;
        }

        echo json_encode(['success' => true, 'id' => $contractorId]);
        if (function_exists('rbac_audit')) {
            rbac_audit('engineer.create', 'engineer', $contractorId, [
                'full_name' => $fullName,
                'license' => $license,
                'status' => $status
            ]);
        }
        $db->close();
        exit;
    }

    // Legacy JSON create fallback
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid JSON input']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare("INSERT INTO contractors (company, owner, license, email, phone, address, specialization, experience, rating, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
        $db->close();
        exit;
    }
    $company = (string)($data['company'] ?? '');
    $owner = (string)($data['owner'] ?? '');
    $license = (string)($data['license'] ?? '');
    $email = (string)($data['email'] ?? '');
    $phone = (string)($data['phone'] ?? '');
    $address = (string)($data['address'] ?? '');
    $specialization = (string)($data['specialization'] ?? '');
    $experience = (int)($data['experience'] ?? 0);
    $rating = (float)($data['rating'] ?? 0);
    $status = (string)($data['status'] ?? 'Active');
    $notes = (string)($data['notes'] ?? '');
    $stmt->bind_param('sssssssidss', $company, $owner, $license, $email, $phone, $address, $specialization, $experience, $rating, $status, $notes);
    if ($stmt->execute()) {
        if (function_exists('rbac_audit')) {
            $createdId = (int)$db->insert_id;
            rbac_audit('engineer.create_legacy', 'engineer', $createdId, [
                'company' => $company,
                'license' => $license,
                'status' => $status
            ]);
        }
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
    } else {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    }
    $stmt->close();
    $db->close();
    exit;
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $stmt = $db->prepare("UPDATE contractors SET company=?, owner=?, license=?, email=?, phone=?, address=?, specialization=?, experience=?, rating=?, status=?, notes=? WHERE id=?");
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
        $db->close();
        exit;
    }
    $company = (string)($data['company'] ?? '');
    $owner = (string)($data['owner'] ?? '');
    $license = (string)($data['license'] ?? '');
    $email = (string)($data['email'] ?? '');
    $phone = (string)($data['phone'] ?? '');
    $address = (string)($data['address'] ?? '');
    $specialization = (string)($data['specialization'] ?? '');
    $experience = (int)($data['experience'] ?? 0);
    $rating = (float)($data['rating'] ?? 0);
    $status = (string)($data['status'] ?? 'Active');
    $notes = (string)($data['notes'] ?? '');
    $stmt->bind_param('sssssssisdsi', $company, $owner, $license, $email, $phone, $address, $specialization, $experience, $rating, $status, $notes, $id);
    if ($stmt->execute()) {
        if (function_exists('rbac_audit')) {
            rbac_audit('engineer.update', 'engineer', $id, [
                'company' => $company,
                'license' => $license,
                'status' => $status
            ]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => $stmt->error]);
    }
    $stmt->close();
    $db->close();
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM contractors WHERE id=?");
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
        $db->close();
        exit;
    }
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if (function_exists('rbac_audit')) {
            rbac_audit('engineer.delete', 'engineer', $id, []);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => $stmt->error]);
    }
    $stmt->close();
    $db->close();
    exit;
}

echo json_encode(['error' => 'Invalid request method']);
$db->close();
