<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['contractor','admin','super_admin']);
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /contractor/index.php');
    exit;
}
$employeeId = (int)$_SESSION['employee_id'];
$role = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['contractor', 'admin', 'super_admin'], true)) {
    header('Location: /contractor/index.php');
    exit;
}

$errors = [];
$success = '';

function cp_has_col(mysqli $db, string $table, string $col): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

$hasAccountEmployeeId = cp_has_col($db, 'contractors', 'account_employee_id');
$hasContractorType = cp_has_col($db, 'contractors', 'contractor_type');
$hasLicenseExp = cp_has_col($db, 'contractors', 'license_expiration_date');
$hasTin = cp_has_col($db, 'contractors', 'tin');
$hasContactFirst = cp_has_col($db, 'contractors', 'contact_person_first_name');
$hasContactLast = cp_has_col($db, 'contractors', 'contact_person_last_name');
$hasContactRole = cp_has_col($db, 'contractors', 'contact_person_role');

$employee = null;
$stmtEmp = $db->prepare("SELECT id, first_name, last_name, email, password, role FROM employees WHERE id = ? LIMIT 1");
if ($stmtEmp) {
    $stmtEmp->bind_param('i', $employeeId);
    $stmtEmp->execute();
    $resEmp = $stmtEmp->get_result();
    $employee = $resEmp ? $resEmp->fetch_assoc() : null;
    if ($resEmp) $resEmp->free();
    $stmtEmp->close();
}
if (!$employee) {
    header('Location: /contractor/index.php');
    exit;
}

$contractor = null;
if ($hasAccountEmployeeId) {
    $stmtCtr = $db->prepare("SELECT * FROM contractors WHERE account_employee_id = ? LIMIT 1");
    if ($stmtCtr) {
        $stmtCtr->bind_param('i', $employeeId);
        $stmtCtr->execute();
        $resCtr = $stmtCtr->get_result();
        $contractor = $resCtr ? $resCtr->fetch_assoc() : null;
        if ($resCtr) $resCtr->free();
        $stmtCtr->close();
    }
}
if (!$contractor) {
    $stmtCtr = $db->prepare("SELECT * FROM contractors WHERE email = ? ORDER BY id DESC LIMIT 1");
    if ($stmtCtr) {
        $email = (string)$employee['email'];
        $stmtCtr->bind_param('s', $email);
        $stmtCtr->execute();
        $resCtr = $stmtCtr->get_result();
        $contractor = $resCtr ? $resCtr->fetch_assoc() : null;
        if ($resCtr) $resCtr->free();
        $stmtCtr->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update_profile') {
            if (is_rate_limited('contractor_profile_update', 8, 600)) {
                $errors[] = 'Too many profile update attempts. Please wait.';
            } else {
                $firstName = trim((string)($_POST['first_name'] ?? ''));
                $lastName = trim((string)($_POST['last_name'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $company = trim((string)($_POST['company'] ?? ''));
                $license = trim((string)($_POST['license'] ?? ''));
                $licenseExp = trim((string)($_POST['license_expiration_date'] ?? ''));
                $spec = trim((string)($_POST['specialization'] ?? ''));
                $years = max(0, (int)($_POST['experience'] ?? 0));
                $contractorType = strtolower(trim((string)($_POST['contractor_type'] ?? 'company')));
                $tin = trim((string)($_POST['tin'] ?? ''));
                $contactFirst = trim((string)($_POST['contact_person_first_name'] ?? ''));
                $contactLast = trim((string)($_POST['contact_person_last_name'] ?? ''));
                $contactRole = trim((string)($_POST['contact_person_role'] ?? 'Owner'));

                if ($firstName === '' || $lastName === '') $errors[] = 'First and last name are required.';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
                if (!preg_match('/^(09\d{9}|\+639\d{9})$/', str_replace([' ', '-'], '', $phone))) $errors[] = 'Mobile format must be 09XXXXXXXXX or +639XXXXXXXXX.';
                if ($company === '') $errors[] = 'Company name is required.';
                if ($license === '') $errors[] = 'License number is required.';
                if ($hasLicenseExp && ($licenseExp === '' || strtotime($licenseExp) === false || strtotime($licenseExp) <= strtotime(date('Y-m-d')))) $errors[] = 'License expiry must be a valid future date.';
                if ($spec === '') $errors[] = 'Specialization is required.';
                if ($address === '') $errors[] = 'Address is required.';
                if ($hasTin && $tin !== '' && !preg_match('/^\d{3}-\d{3}-\d{3}(-\d{3})?$/', $tin)) $errors[] = 'TIN format must be 000-000-000 or 000-000-000-000.';
                if (!in_array($contractorType, ['company', 'individual'], true)) $contractorType = 'company';

                if (!$errors) {
                    $dupEmp = $db->prepare("SELECT id FROM employees WHERE email = ? AND id <> ? LIMIT 1");
                    if ($dupEmp) {
                        $dupEmp->bind_param('si', $email, $employeeId);
                        $dupEmp->execute();
                        $dupRes = $dupEmp->get_result();
                        $exists = $dupRes && $dupRes->num_rows > 0;
                        if ($dupRes) $dupRes->free();
                        $dupEmp->close();
                        if ($exists) $errors[] = 'Email already used by another account.';
                    }
                }

                if (!$errors) {
                    $dupLic = $db->prepare("SELECT id FROM contractors WHERE license = ? AND id <> ? LIMIT 1");
                    if ($dupLic) {
                        $ctrId = (int)($contractor['id'] ?? 0);
                        $dupLic->bind_param('si', $license, $ctrId);
                        $dupLic->execute();
                        $dupRes = $dupLic->get_result();
                        $exists = $dupRes && $dupRes->num_rows > 0;
                        if ($dupRes) $dupRes->free();
                        $dupLic->close();
                        if ($exists) $errors[] = 'License number already used by another contractor.';
                    }
                }

                if (!$errors) {
                    $db->begin_transaction();
                    try {
                        $upEmp = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                        if (!$upEmp) throw new RuntimeException('Unable to prepare employee update.');
                        $upEmp->bind_param('sssi', $firstName, $lastName, $email, $employeeId);
                        if (!$upEmp->execute()) throw new RuntimeException('Unable to update employee account.');
                        $upEmp->close();

                        if ($contractor) {
                            $sql = "UPDATE contractors SET company = ?, owner = ?, license = ?, email = ?, phone = ?, address = ?, specialization = ?, experience = ?";
                            $types = 'sssssssi';
                            $owner = trim($firstName . ' ' . $lastName);
                            $params = [$company, $owner, $license, $email, $phone, $address, $spec, $years];
                            if ($hasContractorType) { $sql .= ", contractor_type = ?"; $types .= 's'; $params[] = $contractorType; }
                            if ($hasLicenseExp) { $sql .= ", license_expiration_date = ?"; $types .= 's'; $params[] = $licenseExp; }
                            if ($hasTin) { $sql .= ", tin = ?"; $types .= 's'; $params[] = $tin; }
                            if ($hasContactFirst) { $sql .= ", contact_person_first_name = ?"; $types .= 's'; $params[] = $contactFirst === '' ? $firstName : $contactFirst; }
                            if ($hasContactLast) { $sql .= ", contact_person_last_name = ?"; $types .= 's'; $params[] = $contactLast === '' ? $lastName : $contactLast; }
                            if ($hasContactRole) { $sql .= ", contact_person_role = ?"; $types .= 's'; $params[] = $contactRole; }
                            if ($hasAccountEmployeeId) { $sql .= ", account_employee_id = ?"; $types .= 'i'; $params[] = $employeeId; }
                            $sql .= " WHERE id = ?";
                            $types .= 'i';
                            $params[] = (int)$contractor['id'];

                            $upCtr = $db->prepare($sql);
                            if (!$upCtr) throw new RuntimeException('Unable to prepare contractor profile update.');
                            $bind = [$types];
                            foreach ($params as $k => $v) $bind[] = &$params[$k];
                            call_user_func_array([$upCtr, 'bind_param'], $bind);
                            if (!$upCtr->execute()) throw new RuntimeException('Unable to update contractor profile.');
                            $upCtr->close();
                        }

                        $db->commit();
                        $_SESSION['employee_name'] = trim($firstName . ' ' . $lastName);
                        $success = 'Profile updated successfully.';
                    } catch (Throwable $e) {
                        $db->rollback();
                        $errors[] = $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'change_password') {
            if (is_rate_limited('contractor_change_password', 6, 900)) {
                $errors[] = 'Too many password change attempts. Please wait.';
            } else {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');
                if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                    $errors[] = 'All password fields are required.';
                } elseif (!password_verify($currentPassword, (string)$employee['password'])) {
                    $errors[] = 'Current password is incorrect.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors[] = 'New password and confirmation do not match.';
                } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
                    $errors[] = 'Password must be at least 8 chars and include uppercase, lowercase, number, and symbol.';
                } elseif (password_verify($newPassword, (string)$employee['password'])) {
                    $errors[] = 'New password must be different from current password.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $up = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
                    if ($up) {
                        $up->bind_param('si', $hash, $employeeId);
                        if ($up->execute()) {
                            $success = 'Password changed successfully.';
                            $employee['password'] = $hash;
                        } else {
                            $errors[] = 'Unable to change password right now.';
                        }
                        $up->close();
                    } else {
                        $errors[] = 'Unable to prepare password update.';
                    }
                }
            }
        }
    }
}

// reload latest profile after post
$stmtEmp2 = $db->prepare("SELECT id, first_name, last_name, email, password, role FROM employees WHERE id = ? LIMIT 1");
if ($stmtEmp2) {
    $stmtEmp2->bind_param('i', $employeeId);
    $stmtEmp2->execute();
    $resEmp2 = $stmtEmp2->get_result();
    $row = $resEmp2 ? $resEmp2->fetch_assoc() : null;
    if ($row) $employee = $row;
    if ($resEmp2) $resEmp2->free();
    $stmtEmp2->close();
}
if ($hasAccountEmployeeId) {
    $stmtCtr2 = $db->prepare("SELECT * FROM contractors WHERE account_employee_id = ? LIMIT 1");
    if ($stmtCtr2) {
        $stmtCtr2->bind_param('i', $employeeId);
        $stmtCtr2->execute();
        $resCtr2 = $stmtCtr2->get_result();
        $row = $resCtr2 ? $resCtr2->fetch_assoc() : null;
        if ($row) $contractor = $row;
        if ($resCtr2) $resCtr2->free();
        $stmtCtr2->close();
    }
}

$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Profile - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Contractor</span></div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <a href="dashboard.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Validation & Budget</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Progress Monitoring</a>
        <a href="profile.php" class="active"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

<section class="main-content">
    <div class="dash-header">
        <h1>My Profile</h1>
        <p>Manage your contractor account details and security settings.</p>
    </div>
    <?php if (!empty($errors)): ?><div class="ac-aabba7cf"><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="ac-0b2b14a3"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="recent-projects card">
        <h3>Account and Company Profile</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update_profile">
            <div class="inline-form">
                <input type="text" name="first_name" placeholder="First Name" value="<?php echo htmlspecialchars((string)($employee['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="text" name="last_name" placeholder="Last Name" value="<?php echo htmlspecialchars((string)($employee['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars((string)($employee['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="text" name="phone" placeholder="Mobile (09XXXXXXXXX)" value="<?php echo htmlspecialchars((string)($contractor['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="text" name="company" placeholder="Company Name" value="<?php echo htmlspecialchars((string)($contractor['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if ($hasContractorType): ?><select name="contractor_type"><option value="company" <?php echo strtolower((string)($contractor['contractor_type'] ?? 'company')) === 'company' ? 'selected' : ''; ?>>Company</option><option value="individual" <?php echo strtolower((string)($contractor['contractor_type'] ?? '')) === 'individual' ? 'selected' : ''; ?>>Individual</option></select><?php endif; ?>
                <input type="text" name="license" placeholder="License Number" value="<?php echo htmlspecialchars((string)($contractor['license'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <?php if ($hasLicenseExp): ?><input type="date" name="license_expiration_date" value="<?php echo htmlspecialchars((string)($contractor['license_expiration_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <?php if ($hasTin): ?><input type="text" name="tin" placeholder="TIN" value="<?php echo htmlspecialchars((string)($contractor['tin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <input type="text" name="specialization" placeholder="Specialization" value="<?php echo htmlspecialchars((string)($contractor['specialization'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                <input type="number" name="experience" min="0" placeholder="Years of Experience" value="<?php echo htmlspecialchars((string)($contractor['experience'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($hasContactFirst): ?><input type="text" name="contact_person_first_name" placeholder="Contact First Name" value="<?php echo htmlspecialchars((string)($contractor['contact_person_first_name'] ?? $employee['first_name']), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <?php if ($hasContactLast): ?><input type="text" name="contact_person_last_name" placeholder="Contact Last Name" value="<?php echo htmlspecialchars((string)($contractor['contact_person_last_name'] ?? $employee['last_name']), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <?php if ($hasContactRole): ?><input type="text" name="contact_person_role" placeholder="Contact Role" value="<?php echo htmlspecialchars((string)($contractor['contact_person_role'] ?? 'Owner'), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                <textarea name="address" placeholder="Business Address" required><?php echo htmlspecialchars((string)($contractor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="ac-eef2e445">Save Profile</button>
        </form>
    </div>

    <div class="recent-projects card">
        <h3>Change Password</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="inline-form">
                <input type="password" name="current_password" placeholder="Current Password" required>
                <input type="password" name="new_password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            </div>
            <button type="submit" class="ac-eef2e445">Update Password</button>
        </form>
    </div>
</section>

<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>
