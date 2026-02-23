<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require dirname(__DIR__) . '/config/email.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $activeRole = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
    if (in_array($activeRole, ['contractor', 'admin', 'super_admin'], true)) {
        header('Location: /contractor/dashboard_overview.php');
        exit;
    }
}

$errors = [];
$success = '';
$info = '';

if (empty($_SESSION['contractor_create_token'])) {
    $_SESSION['contractor_create_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['contractor_create_token'];

$form = [
    'contractor_type' => 'company',
    'company_name' => '',
    'contact_first_name' => '',
    'contact_last_name' => '',
    'contact_role' => 'Owner',
    'license_number' => '',
    'license_expiry_date' => '',
    'tin' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'specialization' => '',
    'years_experience' => '0'
];

function cc_table_has_column(mysqli $db, string $table, string $column): bool
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

function cc_clear_pending(): void
{
    unset(
        $_SESSION['contractor_create_pending'],
        $_SESSION['contractor_create_otp_code'],
        $_SESSION['contractor_create_otp_expires'],
        $_SESSION['contractor_create_otp_attempts'],
        $_SESSION['contractor_create_otp_email'],
        $_SESSION['contractor_create_otp_sent_at']
    );
}

function cc_check_duplicates(mysqli $db, string $email, string $license): array
{
    $errors = [];
    $stmt = $db->prepare('SELECT id FROM employees WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free();
        $stmt->close();
        if ($exists) $errors[] = 'Email already exists.';
    }

    $stmt = $db->prepare('SELECT id FROM contractors WHERE license = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $license);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free();
        $stmt->close();
        if ($exists) $errors[] = 'License number already exists.';
    }
    return $errors;
}

function cc_send_otp(string $email, string $name): bool
{
    $code = (string) random_int(100000, 999999);
    if (!send_verification_code($email, $code, $name === '' ? 'Contractor' : $name)) {
        return false;
    }
    $_SESSION['contractor_create_otp_code'] = $code;
    $_SESSION['contractor_create_otp_expires'] = time() + 600;
    $_SESSION['contractor_create_otp_attempts'] = 0;
    $_SESSION['contractor_create_otp_email'] = $email;
    $_SESSION['contractor_create_otp_sent_at'] = time();
    return true;
}

function cc_validate(array $input): array
{
    $errors = [];
    $data = [];
    $data['contractor_type'] = strtolower(trim((string)($input['contractor_type'] ?? 'company')));
    if (!in_array($data['contractor_type'], ['company', 'individual'], true)) $data['contractor_type'] = 'company';

    foreach (['company_name','contact_first_name','contact_last_name','contact_role','license_number','license_expiry_date','tin','email','phone','address','specialization'] as $key) {
        $data[$key] = trim((string)($input[$key] ?? ''));
    }
    $data['email'] = strtolower($data['email']);
    $data['years_experience'] = max(0, (int)($input['years_experience'] ?? 0));

    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');

    if ($data['company_name'] === '') $errors[] = 'Company/contractor name is required.';
    if ($data['contact_first_name'] === '' || $data['contact_last_name'] === '') $errors[] = 'Contact first and last name are required.';
    if ($data['license_number'] === '') $errors[] = 'License number is required.';
    if ($data['license_expiry_date'] === '' || strtotime($data['license_expiry_date']) === false || strtotime($data['license_expiry_date']) <= strtotime(date('Y-m-d'))) $errors[] = 'License expiry date must be in the future.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', str_replace([' ', '-'], '', $data['phone']))) $errors[] = 'Mobile format must be 09XXXXXXXXX or +639XXXXXXXXX.';
    if ($data['address'] === '') $errors[] = 'Business address is required.';
    if ($data['specialization'] === '') $errors[] = 'Specialization is required.';
    if ($data['tin'] !== '' && !preg_match('/^\d{3}-\d{3}-\d{3}(-\d{3})?$/', $data['tin'])) $errors[] = 'TIN format must be 000-000-000 or 000-000-000-000.';

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 chars and include uppercase, lowercase, number, and symbol.';
    }
    if ($password !== $confirm) $errors[] = 'Password and confirmation do not match.';

    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    return ['errors' => $errors, 'data' => $data];
}

function cc_create_account(mysqli $db, array $data): int
{
    $owner = trim($data['contact_first_name'] . ' ' . $data['contact_last_name']);
    $notes = 'Self-registered contractor account with OTP (' . date('Y-m-d H:i:s') . ')';

    $db->begin_transaction();
    try {
        $emp = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'contractor')");
        if (!$emp) throw new RuntimeException('Failed to prepare employee insert.');
        $emp->bind_param('ssss', $data['contact_first_name'], $data['contact_last_name'], $data['email'], $data['password_hash']);
        if (!$emp->execute()) throw new RuntimeException('Failed to create login account: ' . $emp->error);
        $employeeId = (int)$db->insert_id;
        $emp->close();

        $columns = ['company', 'owner', 'license', 'email', 'phone', 'address', 'specialization', 'experience', 'rating', 'status', 'notes'];
        $types = 'sssssssidss';
        $values = [$data['company_name'], $owner, $data['license_number'], $data['email'], $data['phone'], $data['address'], $data['specialization'], $data['years_experience'], 0.0, 'Active', $notes];

        if (cc_table_has_column($db, 'contractors', 'contractor_type')) { $columns[] = 'contractor_type'; $types .= 's'; $values[] = $data['contractor_type']; }
        if (cc_table_has_column($db, 'contractors', 'license_expiration_date')) { $columns[] = 'license_expiration_date'; $types .= 's'; $values[] = $data['license_expiry_date']; }
        if (cc_table_has_column($db, 'contractors', 'tin')) { $columns[] = 'tin'; $types .= 's'; $values[] = $data['tin']; }
        if (cc_table_has_column($db, 'contractors', 'contact_person_first_name')) { $columns[] = 'contact_person_first_name'; $types .= 's'; $values[] = $data['contact_first_name']; }
        if (cc_table_has_column($db, 'contractors', 'contact_person_last_name')) { $columns[] = 'contact_person_last_name'; $types .= 's'; $values[] = $data['contact_last_name']; }
        if (cc_table_has_column($db, 'contractors', 'contact_person_role')) { $columns[] = 'contact_person_role'; $types .= 's'; $values[] = $data['contact_role']; }
        if (cc_table_has_column($db, 'contractors', 'account_employee_id')) { $columns[] = 'account_employee_id'; $types .= 'i'; $values[] = $employeeId; }

        $sql = 'INSERT INTO contractors (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $ctr = $db->prepare($sql);
        if (!$ctr) throw new RuntimeException('Failed to prepare contractor insert.');
        $ctr->bind_param($types, ...$values);
        if (!$ctr->execute()) throw new RuntimeException('Failed to save contractor profile: ' . $ctr->error);
        $contractorId = (int)$db->insert_id;
        $ctr->close();

        $db->commit();
        return $contractorId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

$otpPending = isset($_SESSION['contractor_create_pending'], $_SESSION['contractor_create_otp_code'], $_SESSION['contractor_create_otp_expires'], $_SESSION['contractor_create_otp_email']);
if ($otpPending && is_array($_SESSION['contractor_create_pending'])) {
    foreach ($form as $k => $v) {
        if (isset($_SESSION['contractor_create_pending'][$k])) $form[$k] = (string)$_SESSION['contractor_create_pending'][$k];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'start_registration') {
            if (is_rate_limited('contractor_create', 5, 600)) {
                $errors[] = 'Too many registration attempts. Try again later.';
            } else {
                $validated = cc_validate($_POST);
                $errors = array_merge($errors, $validated['errors']);
                $data = $validated['data'];
                foreach ($form as $k => $v) if (isset($data[$k])) $form[$k] = (string)$data[$k];
                if (!$errors) $errors = array_merge($errors, cc_check_duplicates($db, $data['email'], $data['license_number']));

                if (!$errors) {
                    $_SESSION['contractor_create_pending'] = $data;
                    $fullName = trim($data['contact_first_name'] . ' ' . $data['contact_last_name']);
                    if (cc_send_otp($data['email'], $fullName)) {
                        $otpPending = true;
                        $info = 'OTP sent to ' . $data['email'] . '. Enter it below to complete registration.';
                    } else {
                        cc_clear_pending();
                        $otpPending = false;
                        $errors[] = 'Unable to send OTP email right now.';
                    }
                }
            }
        }

        if ($action === 'verify_otp') {
            if (!$otpPending) {
                $errors[] = 'No pending registration found.';
            } elseif (is_rate_limited('contractor_create_otp', 10, 600)) {
                $errors[] = 'Too many OTP attempts. Please wait and retry.';
            } else {
                $otpInput = trim((string)($_POST['otp_code'] ?? ''));
                $stored = (string)($_SESSION['contractor_create_otp_code'] ?? '');
                $expires = (int)($_SESSION['contractor_create_otp_expires'] ?? 0);
                $attempts = (int)($_SESSION['contractor_create_otp_attempts'] ?? 0);

                if (time() > $expires) {
                    cc_clear_pending();
                    $otpPending = false;
                    $errors[] = 'OTP expired. Register again.';
                } elseif (!preg_match('/^\d{6}$/', $otpInput)) {
                    $errors[] = 'Enter the 6-digit OTP.';
                } elseif (!hash_equals($stored, $otpInput)) {
                    $_SESSION['contractor_create_otp_attempts'] = $attempts + 1;
                    if ($_SESSION['contractor_create_otp_attempts'] >= 5) {
                        cc_clear_pending();
                        $otpPending = false;
                        $errors[] = 'Too many incorrect OTP attempts. Register again.';
                    } else {
                        $errors[] = 'Invalid OTP code.';
                    }
                } else {
                    $data = $_SESSION['contractor_create_pending'];
                    $errors = array_merge($errors, cc_check_duplicates($db, (string)$data['email'], (string)$data['license_number']));
                    if (!$errors) {
                        try {
                            cc_create_account($db, $data);
                            cc_clear_pending();
                            $otpPending = false;
                            $_SESSION['contractor_create_token'] = bin2hex(random_bytes(32));
                            $csrfToken = $_SESSION['contractor_create_token'];
                            $success = 'Account created successfully. You can now sign in.';
                        } catch (Throwable $e) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
        }

        if ($action === 'resend_otp') {
            if (!$otpPending || !isset($_SESSION['contractor_create_pending'])) {
                $errors[] = 'No pending OTP to resend.';
            } elseif (is_rate_limited('contractor_create_resend', 4, 600)) {
                $errors[] = 'Too many OTP resend attempts. Please wait.';
            } else {
                $lastSent = (int)($_SESSION['contractor_create_otp_sent_at'] ?? 0);
                if ($lastSent > 0 && (time() - $lastSent) < 45) {
                    $errors[] = 'Please wait before requesting another OTP.';
                } else {
                    $data = $_SESSION['contractor_create_pending'];
                    $name = trim((string)$data['contact_first_name'] . ' ' . (string)$data['contact_last_name']);
                    if (cc_send_otp((string)$data['email'], $name)) {
                        $info = 'A new OTP was sent to ' . (string)$data['email'] . '.';
                    } else {
                        $errors[] = 'Unable to resend OTP right now.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Contractor Account Create</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<style>
:root{--page-navy:#0f2a4a;--page-blue:#1d4e89;--page-sky:#3f83c9;--page-muted:#475569;--page-danger:#b91c1c;--page-danger-bg:#fee2e2;--page-border:rgba(15,23,42,.12)}
*{box-sizing:border-box}
body.user-signup-page{min-height:100vh;margin:0;display:flex;flex-direction:column;padding-top:88px;color:#0f172a;background:radial-gradient(circle at 15% 15%,rgba(63,131,201,.28),transparent 40%),radial-gradient(circle at 85% 85%,rgba(29,78,137,.26),transparent 45%),linear-gradient(125deg,rgba(7,20,36,.72),rgba(15,42,74,.68)),url('/cityhall.jpeg') center/cover fixed no-repeat}
body.user-signup-page .nav{position:fixed;inset:0 0 auto 0;width:100%;height:78px;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(90deg,rgba(255,255,255,.94),rgba(247,251,255,.98));border-bottom:1px solid var(--page-border);box-shadow:0 12px 30px rgba(2,6,23,.12);z-index:30}
body.user-signup-page .nav-logo{display:inline-flex;align-items:center;gap:10px;font-size:.98rem;font-weight:700;color:var(--page-navy)}
body.user-signup-page .nav-logo img{width:44px;height:44px;object-fit:contain}
body.user-signup-page .home-btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 16px;border-radius:10px;border:1px solid rgba(29,78,137,.22);text-decoration:none;font-weight:600;color:var(--page-blue);background:#fff}
body.user-signup-page .wrapper{width:100%;flex:1;display:flex;justify-content:center;align-items:flex-start;padding:30px 16px 36px}
body.user-signup-page .card{width:100%;max-width:920px;background:rgba(255,255,255,.95);border:1px solid rgba(255,255,255,.75);border-radius:20px;padding:30px 26px;box-shadow:0 24px 56px rgba(2,6,23,.3)}
body.user-signup-page .card-header{text-align:center;margin-bottom:18px}
body.user-signup-page .icon-top{width:72px;height:72px;object-fit:contain;margin:2px auto 10px;display:block}
body.user-signup-page .title{margin:0 0 6px;font-size:1.7rem;line-height:1.2;color:var(--page-navy)}
body.user-signup-page .subtitle{margin:0;color:var(--page-muted)}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.form-grid .full{grid-column:1 / -1}.input-box{text-align:left}
.input-box label{display:block;font-size:.86rem;color:#1e293b;margin-bottom:6px}
.input-box input,.input-box select,.input-box textarea{width:100%;min-height:46px;border-radius:11px;border:1px solid rgba(148,163,184,.45);background:#fff;padding:10px 12px;font-size:.95rem;color:#0f172a;outline:none}
.input-box textarea{min-height:88px;resize:vertical}
.input-box input:focus,.input-box select:focus,.input-box textarea:focus{border-color:var(--page-sky);box-shadow:0 0 0 4px rgba(63,131,201,.15)}
.actions{margin-top:18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.btn-primary{min-width:170px;height:46px;border:0;border-radius:11px;background:linear-gradient(135deg,#1d4e89,#3f83c9);color:#fff;font-size:.98rem;font-weight:600;cursor:pointer}
.btn-secondary{min-width:130px;height:46px;border:1px solid rgba(148,163,184,.55);border-radius:11px;background:#fff;color:#0f172a;font-size:.95rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.step-header{display:flex;gap:8px;align-items:center;justify-content:center;margin:10px 0 16px}
.step-dot{width:28px;height:28px;border-radius:999px;border:1px solid #cbd5e1;color:#64748b;display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:.84rem;background:#fff}
.step-dot.active{background:linear-gradient(135deg,#1d4e89,#3f83c9);color:#fff;border-color:transparent}
.error-box{margin-top:14px;padding:10px 12px;border-radius:10px;text-align:left;background:var(--page-danger-bg);color:var(--page-danger);font-size:.89rem;border:1px solid rgba(185,28,28,.2)}
.info-box{margin-top:12px;padding:10px 12px;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:.89rem;border:1px solid rgba(59,130,246,.24)}
.ok-box{margin-top:12px;padding:10px 12px;border-radius:10px;background:#dcfce7;color:#166534;font-size:.89rem;border:1px solid #bbf7d0}
@media (max-width:860px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}
</style>
</head>
<body class="user-signup-page">
<header class="nav">
    <div class="nav-logo"><img src="/assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="/contractor/index.php" class="home-btn">Back to Login</a>
</header>
<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <img src="/assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU Logo">
            <h2 class="title"><?php echo $otpPending ? 'Verify OTP' : 'Create Contractor Account'; ?></h2>
            <p class="subtitle"><?php echo $otpPending ? 'Enter the OTP sent to your email to activate account.' : 'Register your contractor profile and secure your login.'; ?></p>
        </div>
        <div class="step-header"><span class="step-dot active">1</span><span class="step-dot <?php echo $otpPending ? 'active' : ''; ?>">2</span></div>

        <?php if (!empty($errors)): ?><div class="error-box"><?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
        <?php if ($info !== ''): ?><div class="info-box"><?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="ok-box"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <?php if ($otpPending): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-grid">
                    <div class="input-box full">
                        <label>Email</label>
                        <input type="text" value="<?php echo htmlspecialchars((string)($_SESSION['contractor_create_otp_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="input-box">
                        <label for="otp_code">One-Time Password (6 digits)</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="\d{6}" required placeholder="123456">
                    </div>
                    <div class="input-box">
                        <label>Expires In</label>
                        <input type="text" readonly value="<?php echo max(0, ((int)($_SESSION['contractor_create_otp_expires'] ?? 0) - time())); ?> seconds">
                    </div>
                </div>
                <div class="actions"><button type="submit" class="btn-primary">Verify & Create Account</button></div>
            </form>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit" class="btn-secondary">Resend OTP</button>
            </form>
        <?php else: ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="start_registration">
                <div class="form-grid">
                    <div class="input-box"><label>Contractor Type</label><select name="contractor_type" required><option value="company" <?php echo $form['contractor_type'] === 'company' ? 'selected' : ''; ?>>Company</option><option value="individual" <?php echo $form['contractor_type'] === 'individual' ? 'selected' : ''; ?>>Individual</option></select></div>
                    <div class="input-box"><label>Company / Contractor Name</label><input type="text" name="company_name" required value="<?php echo htmlspecialchars($form['company_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Contact First Name</label><input type="text" name="contact_first_name" required value="<?php echo htmlspecialchars($form['contact_first_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Contact Last Name</label><input type="text" name="contact_last_name" required value="<?php echo htmlspecialchars($form['contact_last_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Contact Role</label><input type="text" name="contact_role" value="<?php echo htmlspecialchars($form['contact_role'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>License Number</label><input type="text" name="license_number" required value="<?php echo htmlspecialchars($form['license_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>License Expiry Date</label><input type="date" name="license_expiry_date" required value="<?php echo htmlspecialchars($form['license_expiry_date'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>TIN (optional)</label><input type="text" name="tin" placeholder="000-000-000" value="<?php echo htmlspecialchars($form['tin'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Mobile Number</label><input type="text" name="phone" required placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="<?php echo htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box"><label>Specialization</label><select name="specialization" required><option value="">-- Select --</option><?php foreach (['Civil Works', 'Road Works', 'Drainage', 'Electrical', 'Water System', 'Building Construction', 'Maintenance'] as $spec): ?><option value="<?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['specialization'] === $spec ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                    <div class="input-box"><label>Years of Experience</label><input type="number" name="years_experience" min="0" value="<?php echo htmlspecialchars($form['years_experience'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="input-box full"><label>Business Address</label><textarea name="address" rows="3" required><?php echo htmlspecialchars($form['address'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    <div class="input-box"><label>Password</label><input type="password" name="password" required></div>
                    <div class="input-box"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn-primary">Send OTP</button>
                    <a href="/contractor/index.php" class="btn-secondary">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
