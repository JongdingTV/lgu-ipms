<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $role = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
    if (in_array($role, ['engineer', 'admin', 'super_admin'], true)) {
        header('Location: /engineer/dashboard_overview.php');
        exit;
    }
}

$errors = [];
$success = '';
if (empty($_SESSION['engineer_application_token'])) {
    $_SESSION['engineer_application_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['engineer_application_token'];

function enga_store_doc(array $file, string $docType): array
{
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    $max = 5 * 1024 * 1024;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $docType . '.');
    }
    if ((int)($file['size'] ?? 0) > $max) {
        throw new RuntimeException($docType . ' exceeds 5MB.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (string)(mime_content_type($tmp) ?: '');
    if (!isset($allowed[$mime])) {
        throw new RuntimeException($docType . ' invalid file type. Allowed: PDF/JPG/PNG.');
    }

    $root = realpath(dirname(__DIR__));
    if (!$root) throw new RuntimeException('Unable to resolve root path.');

    $relativeDir = 'applications/engineer/' . date('Y') . '/' . date('m');
    $targetDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to prepare upload path.');
    }

    $filename = 'eng_app_' . strtolower($docType) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Unable to save file for ' . $docType . '.');
    }

    return [
        'doc_type' => $docType,
        'file_path' => 'engineer/' . date('Y') . '/' . date('m') . '/' . $filename,
        'original_name' => (string)($file['name'] ?? ''),
        'mime_type' => $mime,
        'file_size' => (int)($file['size'] ?? 0)
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid security token.';
    }

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $assignedArea = trim((string)($_POST['assigned_area'] ?? ''));
    $prcNo = trim((string)($_POST['prc_license_no'] ?? ''));
    $prcExpiry = trim((string)($_POST['prc_expiry'] ?? ''));
    $yearsExperience = max(0, (int)($_POST['years_experience'] ?? 0));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '' || $department === '' || $position === '' || $specialization === '' || $assignedArea === '' || $prcNo === '') {
        $errors[] = 'Please complete all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if ($prcExpiry === '' || strtotime($prcExpiry) === false || strtotime($prcExpiry) <= strtotime(date('Y-m-d'))) $errors[] = 'PRC expiry must be a future date.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Password and confirmation do not match.';

    if (!isset($_FILES['prc_doc']) || ($_FILES['prc_doc']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'PRC ID/License file is required.';
    }
    if (!isset($_FILES['gov_id_doc']) || ($_FILES['gov_id_doc']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Government/Office ID document is required.';
    }

    if (!$errors) {
        try {
            $docs = [];
            $docs[] = enga_store_doc($_FILES['prc_doc'], 'PRC_ID');
            $docs[] = enga_store_doc($_FILES['gov_id_doc'], 'GOV_ID');

            if (isset($_FILES['cert_doc']) && ($_FILES['cert_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $docs[] = enga_store_doc($_FILES['cert_doc'], 'CERTIFICATE');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $db->begin_transaction();
            $ins = $db->prepare("INSERT INTO engineer_applications (full_name, email, phone, department, position, specialization, assigned_area, prc_license_no, prc_expiry, years_experience, status, account_password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())");
            if (!$ins) throw new RuntimeException('Unable to submit application.');
            $ins->bind_param('sssssssssis', $fullName, $email, $phone, $department, $position, $specialization, $assignedArea, $prcNo, $prcExpiry, $yearsExperience, $passwordHash);
            if (!$ins->execute()) {
                $msg = $ins->error;
                $ins->close();
                throw new RuntimeException('Unable to submit application: ' . $msg);
            }
            $appId = (int)$db->insert_id;
            $ins->close();

            $docIns = $db->prepare("INSERT INTO application_documents (application_type, application_id, doc_type, file_path, original_name, mime_type, file_size, uploaded_at) VALUES ('engineer', ?, ?, ?, ?, ?, ?, NOW())");
            if (!$docIns) throw new RuntimeException('Unable to store document records.');
            foreach ($docs as $d) {
                $docType = (string)$d['doc_type'];
                $path = (string)$d['file_path'];
                $orig = (string)$d['original_name'];
                $mime = (string)$d['mime_type'];
                $size = (int)$d['file_size'];
                $docIns->bind_param('issssi', $appId, $docType, $path, $orig, $mime, $size);
                if (!$docIns->execute()) {
                    $msg = $docIns->error;
                    $docIns->close();
                    throw new RuntimeException('Unable to save document metadata: ' . $msg);
                }
            }
            $docIns->close();

            $log = $db->prepare("INSERT INTO application_logs (application_type, application_id, action, remarks, created_at) VALUES ('engineer', ?, 'pending', 'Application submitted by applicant', NOW())");
            if ($log) {
                $log->bind_param('i', $appId);
                $log->execute();
                $log->close();
            }

            $db->commit();
            $success = 'Application submitted successfully. Please wait for Admin review and approval before signing in.';
            $_SESSION['engineer_application_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['engineer_application_token'];
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Engineer Application - LGU IPMS</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/public-applications.css">
</head>
<body class="public-apply-page">
<header class="public-apply-nav"><div class="brand"><img src="/assets/images/icons/ipms-icon.png" alt="IPMS">IPMS Engineer Application</div><a href="/engineer/index.php" class="back-link">Back to Login</a></header>
<div class="public-apply-wrap"><div class="public-apply-card">
<div class="public-apply-head"><h1>Engineer Application Form</h1><p>Submit your profile and required documents for admin verification and approval.</p></div>
<?php if (!empty($errors)): ?><div class="ac-aabba7cf public-msg"><?php foreach ($errors as $er): ?><div><?php echo htmlspecialchars($er, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="ac-0b2b14a3 public-msg"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="public-apply-grid">
<div class="public-apply-field"><label>Full Name *</label><input type="text" name="full_name" required></div>
<div class="public-apply-field"><label>Email *</label><input type="email" name="email" required></div>
<div class="public-apply-field"><label>Phone *</label><input type="text" name="phone" required></div>
<div class="public-apply-field"><label>Department / Office *</label><input type="text" name="department" required></div>
<div class="public-apply-field"><label>Position *</label><input type="text" name="position" required></div>
<div class="public-apply-field"><label>Specialization *</label><input type="text" name="specialization" required></div>
<div class="public-apply-field"><label>Assigned Area (District/Barangay) *</label><input type="text" name="assigned_area" required></div>
<div class="public-apply-field"><label>Years of Experience *</label><input type="number" name="years_experience" min="0" value="0" required></div>
<div class="public-apply-field"><label>PRC License Number *</label><input type="text" name="prc_license_no" required></div>
<div class="public-apply-field"><label>PRC Expiry Date *</label><input type="date" name="prc_expiry" required></div>
<div class="public-apply-field"><label>Password *</label><input type="password" name="password" minlength="8" required></div>
<div class="public-apply-field"><label>Confirm Password *</label><input type="password" name="confirm_password" minlength="8" required></div>
<div class="public-apply-field"><label>PRC ID / License (PDF/JPG/PNG, max 5MB) *</label><input type="file" name="prc_doc" accept=".pdf,.jpg,.jpeg,.png" required></div>
<div class="public-apply-field"><label>Government/Office ID (PDF/JPG/PNG, max 5MB) *</label><input type="file" name="gov_id_doc" accept=".pdf,.jpg,.jpeg,.png" required></div>
<div class="public-apply-field full"><label>Certificates (optional, PDF/JPG/PNG, max 5MB)</label><input type="file" name="cert_doc" accept=".pdf,.jpg,.jpeg,.png"></div>
</div>
<div class="public-apply-actions"><a class="public-apply-btn secondary" href="/engineer/index.php">Cancel</a><button type="submit" class="public-apply-btn primary">Submit Application</button></div>
</form>
</div></div>
</body>
</html>
