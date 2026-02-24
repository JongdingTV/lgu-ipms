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
$activeTab = (string)($_GET['tab'] ?? 'profile');
if (!in_array($activeTab, ['profile', 'password'], true)) {
    $activeTab = 'profile';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'change_password') {
    $activeTab = 'password';
}
$fullName = trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''));
$sidebarInitial = strtoupper(substr($fullName !== '' ? $fullName : 'Contractor', 0, 1));
$sidebarRoleLabel = ucwords(str_replace('_', ' ', (string)($employee['role'] ?? 'contractor')));
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
</head>
<body>
<div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>
<header class="nav" id="navbar">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS Contractor</span></div>
    <div class="nav-user-profile">
        <div class="user-initial-badge"><?php echo htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-email"><?php echo htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard</a>
        <a href="my_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Assigned Projects</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Submit Progress</a>
        <a href="deliverables.php"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Deliverables</a>
        <a href="expenses.php"><img src="../assets/images/admin/budget.png" class="nav-icon" alt="">Expenses / Billing</a>
        <a href="requests.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Requests</a>
        <a href="issues.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Issues</a>
        <a href="messages.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a>
        <a href="notifications.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Notifications</a>
        <a href="profile.php" class="active"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
</header>
<div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

<section class="main-content settings-page">
    <div class="dash-header">
        <h1>My Profile</h1>
        <p>Manage your contractor account details and security settings.</p>
    </div>
    <?php if (!empty($errors)): ?><div class="ac-aabba7cf"><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="ac-0b2b14a3"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <div class="settings-layout">
        <div class="card ac-e9b6d4ca settings-card">
            <div class="settings-tabs settings-switcher">
                <a href="profile.php?tab=profile" class="tab-btn <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">Profile</a>
                <a href="profile.php?tab=password" class="tab-btn <?php echo $activeTab === 'password' ? 'active' : ''; ?>">Change Password</a>
            </div>

            <?php if ($activeTab === 'profile'): ?>
                <div class="settings-view">
                    <div class="settings-panel">
                        <h3 class="ac-b75fad00">Account and Company Profile</h3>
                        <p class="settings-subtitle">Registration details are view-only and cannot be edited.</p>
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
                </div>
            <?php else: ?>
                <div class="settings-view">
                    <div class="settings-panel settings-password-panel">
                        <h3 class="ac-b75fad00">Change Password</h3>
                        <p class="settings-subtitle">Use a strong password with uppercase, lowercase, number, and symbol.</p>
                        <form method="post" class="settings-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="change_password">
                            <div class="ac-37c29296">
                                <div class="ac-6f762f4a">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" required>
                                </div>
                                <div class="ac-6f762f4a">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" required>
                                </div>
                                <div class="ac-6f762f4a">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="ac-f84d9680">Update Password</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>


