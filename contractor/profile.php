<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.view', ['contractor','admin','super_admin']);
$rbacAction = 'view_profile';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'change_password') {
    $rbacAction = 'change_password';
}
rbac_require_action_matrix(
    $rbacAction,
    [
        'view_profile' => 'contractor.workspace.view',
        'change_password' => 'contractor.workspace.manage',
    ],
    'contractor.workspace.view'
);
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
        if ($action === 'change_password') {
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
    <link rel="stylesheet" href="/assets/css/form-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="/user-dashboard/user-shell.css?v=<?php echo filemtime(dirname(__DIR__) . '/user-dashboard/user-shell.css'); ?>">
    <style>
        .nav-user-profile { display:flex; align-items:center; gap:10px; margin:8px 12px 14px; padding:10px; border:1px solid rgba(148,163,184,.22); border-radius:12px; background:rgba(248,251,255,.8); }
        .nav-user-badge { width:36px; height:36px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; background:linear-gradient(135deg,#1d4e89,#3f83c9); flex:0 0 36px; }
        .nav-user-meta { min-width:0; }
        .nav-user-name { color:#0f2a4a; font-weight:700; font-size:.9rem; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .nav-user-email { color:#64748b; font-size:.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .profile-layout { display:grid; grid-template-columns: 320px 1fr; gap:18px; align-items:start; }
        .profile-side {
            border-radius:16px;
            border:1px solid #dbe7f3;
            background:#fff;
            padding:18px;
            position:sticky;
            top:14px;
        }
        .profile-avatar { width:72px; height:72px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; background:linear-gradient(135deg,#1d4e89,#3f83c9); margin-bottom:10px; font-size:1.15rem; }
        .profile-name { margin:0; color:#0f2a4a; font-size:1.25rem; line-height:1.2; }
        .profile-role { margin:4px 0 0; color:#64748b; font-size:.92rem; }
        .profile-meta { margin-top:14px; display:grid; gap:10px; }
        .profile-meta-item { padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fbff; }
        .profile-meta-item label { display:block; color:#64748b; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .profile-meta-item div { color:#0f2a4a; font-weight:600; font-size:.92rem; word-break:break-word; }
        .profile-main { display:grid; gap:16px; }
        .settings-card { border-radius:14px; border:1px solid #dbe7f3; background:#fff; padding:16px; box-shadow:0 4px 14px rgba(15,23,42,.06); }
        .settings-info-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:10px; }
        .settings-info-field { border:1px solid #dbe7f3; border-radius:10px; background:#fff; padding:10px 12px; min-height:64px; }
        .settings-info-field label { display:block; color:#64748b; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
        .settings-info-value { color:#0f2a4a; font-size:.92rem; font-weight:600; word-break:break-word; }
        .settings-info-field-full { grid-column:1 / -1; }
        .profile-btn {
            height:44px;
            border:none;
            border-radius:11px;
            padding:0 16px;
            font-weight:700;
            color:#fff;
            background:linear-gradient(135deg,#16416f,#2f73b5);
            cursor:pointer;
            box-shadow:0 6px 16px rgba(22,65,111,.26);
            transition:transform .15s ease, box-shadow .2s ease, filter .2s ease;
        }
        .profile-btn:hover { transform:translateY(-1px); filter:brightness(1.03); box-shadow:0 8px 18px rgba(22,65,111,.30); }
        .profile-btn:active { transform:translateY(0); box-shadow:0 4px 12px rgba(22,65,111,.24); }
        .password-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:10px 0 12px; }
        .password-grid input { width:100%; height:44px; border:1px solid #c8d8ea; border-radius:10px; padding:0 12px; }
        .password-grid input:focus { outline:none; border-color:#2f73b5; box-shadow:0 0 0 3px rgba(47,115,181,.15); }
        @media (max-width: 1000px) {
            .profile-layout { grid-template-columns:1fr; }
            .profile-side { position:static; }
            .settings-info-form, .password-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Contractor</span></div>
    <div class="nav-user-profile">
        <div class="nav-user-badge"><?php echo htmlspecialchars(strtoupper(substr((string)($employee['first_name'] ?? 'C'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-meta">
            <div class="nav-user-name"><?php echo htmlspecialchars(trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="nav-user-email"><?php echo htmlspecialchars((string)($employee['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
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

    <div class="profile-layout">
        <aside class="profile-side">
            <div class="profile-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)($employee['first_name'] ?? 'C'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
            <h3 class="profile-name"><?php echo htmlspecialchars(trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="profile-role"><?php echo htmlspecialchars(ucfirst((string)($employee['role'] ?? 'contractor')), ENT_QUOTES, 'UTF-8'); ?> Account</p>
            <div class="profile-meta">
                <div class="profile-meta-item"><label>Email</label><div><?php echo htmlspecialchars((string)($employee['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                <div class="profile-meta-item"><label>Mobile</label><div><?php echo htmlspecialchars((string)($contractor['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                <div class="profile-meta-item"><label>Company</label><div><?php echo htmlspecialchars((string)($contractor['company'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div></div>
            </div>
        </aside>

        <div class="profile-main">
            <div class="settings-card">
                <h3>Account and Company Profile</h3>
                <p style="margin:6px 0 0;color:#64748b;">Registration details are view-only and cannot be edited.</p>
                <div class="settings-info-form">
                    <div class="settings-info-field"><label>First Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($employee['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>Last Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($employee['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>Contractor Type</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['contractor_type'] ?? 'company'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>Company / Contractor Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>License Number</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['license'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <?php if ($hasLicenseExp): ?><div class="settings-info-field"><label>License Expiry</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['license_expiration_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?>
                    <?php if ($hasTin): ?><div class="settings-info-field"><label>TIN</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['tin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?>
                    <div class="settings-info-field"><label>Specialization</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['specialization'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>Experience</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['experience'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> years</div></div>
                    <div class="settings-info-field"><label>Email</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($employee['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <div class="settings-info-field"><label>Mobile Number</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                    <?php if ($hasContactFirst): ?><div class="settings-info-field"><label>Contact First Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['contact_person_first_name'] ?? $employee['first_name']), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?>
                    <?php if ($hasContactLast): ?><div class="settings-info-field"><label>Contact Last Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['contact_person_last_name'] ?? $employee['last_name']), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?>
                    <?php if ($hasContactRole): ?><div class="settings-info-field"><label>Contact Role</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['contact_person_role'] ?? 'Owner'), ENT_QUOTES, 'UTF-8'); ?></div></div><?php endif; ?>
                    <div class="settings-info-field settings-info-field-full"><label>Business Address</label><div class="settings-info-value"><?php echo htmlspecialchars((string)($contractor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                </div>
            </div>
            <div class="settings-card">
                <h3>Change Password</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="password-grid">
                        <input type="password" name="current_password" placeholder="Current Password" required>
                        <input type="password" name="new_password" placeholder="New Password" required>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    </div>
                    <button type="submit" class="profile-btn">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</section>

<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>
