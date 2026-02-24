<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $role = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
    if (in_array($role, ['contractor', 'admin', 'super_admin'], true)) {
        header('Location: /contractor/dashboard_overview.php');
        exit;
    }
}

$errors = [];
$success = '';
if (empty($_SESSION['contractor_application_token'])) {
    $_SESSION['contractor_application_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['contractor_application_token'];

function ctra_store_doc(array $file, string $docType): array
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

    $relativeDir = 'applications/contractor/' . date('Y') . '/' . date('m');
    $targetDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to prepare upload path.');
    }

    $filename = 'ctr_app_' . strtolower($docType) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Unable to save file for ' . $docType . '.');
    }

    return [
        'doc_type' => $docType,
        'file_path' => 'contractor/' . date('Y') . '/' . date('m') . '/' . $filename,
        'original_name' => (string)($file['name'] ?? ''),
        'mime_type' => $mime,
        'file_size' => (int)($file['size'] ?? 0)
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid security token.';
    }

    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $yearsInBusiness = max(0, (int)($_POST['years_in_business'] ?? 0));
    $assignedArea = trim((string)($_POST['assigned_area'] ?? ''));
    $licenseNo = trim((string)($_POST['license_no'] ?? ''));
    $licenseExpiry = trim((string)($_POST['license_expiry'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($companyName === '' || $contactPerson === '' || $email === '' || $phone === '' || $address === '' || $specialization === '') {
        $errors[] = 'Please complete all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Password and confirmation do not match.';

    if (!isset($_FILES['pcab_doc']) || ($_FILES['pcab_doc']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'PCAB/contractor license document is required.';
    }
    if (!isset($_FILES['permit_doc']) || ($_FILES['permit_doc']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Business/Mayor permit document is required.';
    }

    if (!$errors) {
        try {
            $docs = [];
            $docs[] = ctra_store_doc($_FILES['pcab_doc'], 'PCAB_LICENSE');
            $docs[] = ctra_store_doc($_FILES['permit_doc'], 'BUSINESS_PERMIT');
            if (isset($_FILES['bir_doc']) && ($_FILES['bir_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $docs[] = ctra_store_doc($_FILES['bir_doc'], 'BIR_DTI_SEC');
            }
            if (isset($_FILES['profile_doc']) && ($_FILES['profile_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $docs[] = ctra_store_doc($_FILES['profile_doc'], 'COMPANY_PROFILE');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $db->begin_transaction();
            $ins = $db->prepare("INSERT INTO contractor_applications (company_name, contact_person, email, phone, address, specialization, years_in_business, assigned_area, license_no, license_expiry, status, account_password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())");
            if (!$ins) throw new RuntimeException('Unable to submit application.');
            $ins->bind_param('ssssssissss', $companyName, $contactPerson, $email, $phone, $address, $specialization, $yearsInBusiness, $assignedArea, $licenseNo, $licenseExpiry, $passwordHash);
            if (!$ins->execute()) {
                $msg = $ins->error;
                $ins->close();
                throw new RuntimeException('Unable to submit application: ' . $msg);
            }
            $appId = (int)$db->insert_id;
            $ins->close();

            $docIns = $db->prepare("INSERT INTO application_documents (application_type, application_id, doc_type, file_path, original_name, mime_type, file_size, uploaded_at) VALUES ('contractor', ?, ?, ?, ?, ?, ?, NOW())");
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

            $log = $db->prepare("INSERT INTO application_logs (application_type, application_id, action, remarks, created_at) VALUES ('contractor', ?, 'pending', 'Application submitted by applicant', NOW())");
            if ($log) {
                $log->bind_param('i', $appId);
                $log->execute();
                $log->close();
            }

            $db->commit();
            $success = 'Application submitted successfully. Please wait for Admin review and approval before signing in.';
            $_SESSION['contractor_application_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['contractor_application_token'];
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
<title>Contractor Application - LGU IPMS</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/public-applications.css">
</head>
<body class="public-apply-page">
<header class="public-apply-nav"><div class="brand"><img src="/assets/images/icons/ipms-icon.png" alt="IPMS">IPMS Contractor Application</div><a href="/contractor/index.php" class="back-link">Back to Login</a></header>
<div class="public-apply-wrap"><div class="public-apply-card">
<div class="public-apply-head"><h1>Contractor Application Form</h1><p>Submit your company profile and required credentials for admin verification and approval.</p></div>
<?php if (!empty($errors)): ?><div class="ac-aabba7cf public-msg"><?php foreach ($errors as $er): ?><div><?php echo htmlspecialchars($er, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="ac-0b2b14a3 public-msg"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="public-apply-grid">
<div class="public-apply-field"><label>Company Name *</label><input type="text" name="company_name" required></div>
<div class="public-apply-field"><label>Contact Person *</label><input type="text" name="contact_person" required></div>
<div class="public-apply-field"><label>Email *</label><input type="email" name="email" required></div>
<div class="public-apply-field"><label>Phone *</label><input type="text" name="phone" required></div>
<div class="public-apply-field full"><label>Office Address *</label><textarea name="address" required></textarea></div>
<div class="public-apply-field"><label>Specialization *</label><input type="text" name="specialization" required></div>
<div class="public-apply-field"><label>Years in Business *</label><input type="number" name="years_in_business" min="0" value="0" required></div>
<div class="public-apply-field"><label>Assigned Area (optional)</label><input type="text" name="assigned_area"></div>
<div class="public-apply-field"><label>License Number *</label><input type="text" name="license_no" required></div>
<div class="public-apply-field"><label>License Expiry (optional)</label><input type="date" name="license_expiry"></div>
<div class="public-apply-field"><label>Password *</label><input type="password" name="password" minlength="8" required></div>
<div class="public-apply-field"><label>Confirm Password *</label><input type="password" name="confirm_password" minlength="8" required></div>
<div class="public-apply-field"><label>PCAB/Contractor License (PDF/JPG/PNG, max 5MB) *</label><input type="file" name="pcab_doc" accept=".pdf,.jpg,.jpeg,.png" required></div>
<div class="public-apply-field"><label>Business/Mayor Permit (PDF/JPG/PNG, max 5MB) *</label><input type="file" name="permit_doc" accept=".pdf,.jpg,.jpeg,.png" required></div>
<div class="public-apply-field"><label>BIR/DTI/SEC Registration (optional)</label><input type="file" name="bir_doc" accept=".pdf,.jpg,.jpeg,.png"></div>
<div class="public-apply-field"><label>Company Profile (optional)</label><input type="file" name="profile_doc" accept=".pdf,.jpg,.jpeg,.png"></div>
</div>
<div class="public-apply-actions"><a class="public-apply-btn secondary" href="/contractor/index.php">Cancel</a><button type="submit" class="public-apply-btn primary">Submit Application</button></div>
</form>
</div></div>
</body>
</html>
