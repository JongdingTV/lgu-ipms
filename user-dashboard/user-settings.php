<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    die('Database connection failed: ' . ($db->connect_error ?? 'Unknown error'));
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$stmt = $db->prepare('SELECT id, first_name, middle_name, last_name, suffix, email, mobile, birthdate, gender, civil_status, address, id_type, id_number, id_upload, created_at, password FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    $db->close();
    destroy_session();
    header('Location: /user-dashboard/user-login.php');
    exit;
}

$userName = trim($_SESSION['user_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$userEmail = $user['email'] ?? '';
$displayFullName = trim(implode(' ', array_filter([
    trim((string) ($user['first_name'] ?? '')),
    trim((string) ($user['middle_name'] ?? '')),
    trim((string) ($user['last_name'] ?? '')),
    trim((string) ($user['suffix'] ?? ''))
], static fn($part) => $part !== '')));
if ($displayFullName === '') {
    $displayFullName = $userName;
}

$formatProfileValue = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }
    return ucwords(strtolower($value));
};

$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

function parse_id_upload_bundle(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['front' => '', 'back' => '', 'single' => ''];
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $front = trim((string) ($decoded['front'] ?? ''));
        $back = trim((string) ($decoded['back'] ?? ''));
        return ['front' => $front, 'back' => $back, 'single' => ''];
    }
    return ['front' => '', 'back' => '', 'single' => $raw];
}

$activeTab = $_GET['tab'] ?? 'profile';
if (!in_array($activeTab, ['profile', 'password'], true)) {
    $activeTab = 'profile';
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $uploadDir = dirname(__DIR__) . '/uploads/user-profile';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        if ($_POST['photo_action'] === 'remove') {
            $existing = glob($uploadDir . '/user_' . $userId . '.*') ?: [];
            foreach ($existing as $path) {
                @unlink($path);
            }
            $profileImageWebPath = '';
            $success = 'Profile photo removed.';
            $activeTab = 'profile';
        } elseif ($_POST['photo_action'] === 'upload') {
            if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Please select a valid image file.';
            } else {
                $tmpFile = $_FILES['profile_photo']['tmp_name'];
                $fileSize = (int) ($_FILES['profile_photo']['size'] ?? 0);
                if ($fileSize > 3 * 1024 * 1024) {
                    $errors[] = 'Profile photo must be 3MB or less.';
                } else {
                    $imageInfo = @getimagesize($tmpFile);
                    $mimeType = $imageInfo['mime'] ?? '';
                    $mimeMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($mimeMap[$mimeType])) {
                        $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
                    } else {
                        $existing = glob($uploadDir . '/user_' . $userId . '.*') ?: [];
                        foreach ($existing as $path) {
                            @unlink($path);
                        }

                        $ext = $mimeMap[$mimeType];
                        $target = $uploadDir . '/user_' . $userId . '.' . $ext;
                        if (!move_uploaded_file($tmpFile, $target)) {
                            $errors[] = 'Unable to save the profile photo.';
                        } else {
                            $profileImageWebPath = '/uploads/user-profile/' . basename($target);
                            $success = 'Profile photo updated successfully.';
                            $activeTab = 'profile';
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'All password fields are required.';
        } elseif (!password_verify($currentPassword, (string) $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (password_verify($newPassword, (string) $user['password'])) {
            $errors[] = 'New password must be different from your current password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'New password must contain at least one number.';
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
            $errors[] = 'New password must contain at least one special character.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateStmt->bind_param('si', $hashed, $userId);
            $ok = $updateStmt->execute();
            $updateStmt->close();

            if ($ok) {
                $success = 'Password updated successfully.';
                $activeTab = 'password';
            } else {
                $errors[] = 'Failed to update password. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $uploadDir = dirname(__DIR__) . '/uploads/user-id';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        if ($_POST['id_action'] === 'remove') {
            if (!empty($user['id_upload'])) {
                $bundle = parse_id_upload_bundle((string) $user['id_upload']);
                $toDelete = [];
                if ($bundle['front'] !== '') $toDelete[] = $bundle['front'];
                if ($bundle['back'] !== '') $toDelete[] = $bundle['back'];
                if ($bundle['single'] !== '') $toDelete[] = $bundle['single'];
                foreach ($toDelete as $pathWeb) {
                    $existingPath = dirname(__DIR__) . '/' . ltrim((string) $pathWeb, '/');
                    if (is_file($existingPath)) {
                        @unlink($existingPath);
                    }
                }
            }

            $clearStmt = $db->prepare('UPDATE users SET id_upload = NULL WHERE id = ?');
            $clearStmt->bind_param('i', $userId);
            $ok = $clearStmt->execute();
            $clearStmt->close();

            if ($ok) {
                $user['id_upload'] = null;
                $success = 'ID file removed.';
                $activeTab = 'profile';
            } else {
                $errors[] = 'Unable to remove ID file.';
            }
        } elseif ($_POST['id_action'] === 'upload') {
            $frontFile = $_FILES['id_file_front'] ?? null;
            $backFile = $_FILES['id_file_back'] ?? null;
            if (!$frontFile || !$backFile || (int)($frontFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($backFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Please upload both ID Front and ID Back images.';
            } else {
                $mimeMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                $processFile = static function (array $file, string $suffix) use ($mimeMap, $uploadDir, $userId): array {
                    $tmpFile = (string) ($file['tmp_name'] ?? '');
                    $fileSize = (int) ($file['size'] ?? 0);
                    if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                        return [false, '', 'Each ID image must be 5MB or less.'];
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpFile) : '';
                    if ($finfo) finfo_close($finfo);
                    if (!isset($mimeMap[$mimeType])) {
                        return [false, '', 'Only JPG, PNG, or WEBP images are allowed for ID verification.'];
                    }
                    $ext = $mimeMap[$mimeType];
                    $target = $uploadDir . '/user_' . $userId . '_' . $suffix . '.' . $ext;
                    if (!move_uploaded_file($tmpFile, $target)) {
                        return [false, '', 'Unable to save ID image file.'];
                    }
                    return [true, '/uploads/user-id/' . basename($target), ''];
                };

                // Remove old user_id files (front/back legacy/single).
                $existing = glob($uploadDir . '/user_' . $userId . '*.*') ?: [];
                foreach ($existing as $path) {
                    @unlink($path);
                }

                [$okFront, $frontPath, $frontErr] = $processFile($frontFile, 'front');
                if (!$okFront) {
                    $errors[] = $frontErr;
                } else {
                    [$okBack, $backPath, $backErr] = $processFile($backFile, 'back');
                    if (!$okBack) {
                        $errors[] = $backErr;
                    } else {
                        $bundleJson = json_encode(['front' => $frontPath, 'back' => $backPath], JSON_UNESCAPED_SLASHES);
                        $idStmt = $db->prepare('UPDATE users SET id_upload = ? WHERE id = ?');
                        $idStmt->bind_param('si', $bundleJson, $userId);
                        $ok = $idStmt->execute();
                        $idStmt->close();

                        if ($ok) {
                            $user['id_upload'] = $bundleJson;
                            $success = 'ID Front and Back uploaded successfully.';
                            $activeTab = 'profile';
                        } else {
                            $errors[] = 'Unable to save ID file references.';
                        }
                    }
                }
            }
        }
    }
}

$feedbackStats = ['total' => 0, 'pending' => 0];
$hasFeedbackUserIdStmt = $db->prepare(
    "SELECT 1
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'feedback'
       AND COLUMN_NAME = 'user_id'
     LIMIT 1"
);
$hasFeedbackUserId = false;
if ($hasFeedbackUserIdStmt) {
    $hasFeedbackUserIdStmt->execute();
    $hasFeedbackUserIdRes = $hasFeedbackUserIdStmt->get_result();
    $hasFeedbackUserId = $hasFeedbackUserIdRes && $hasFeedbackUserIdRes->num_rows > 0;
    if ($hasFeedbackUserIdRes) {
        $hasFeedbackUserIdRes->free();
    }
    $hasFeedbackUserIdStmt->close();
}

$statsStmt = $hasFeedbackUserId
    ? $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending FROM feedback WHERE user_id = ?')
    : $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending FROM feedback WHERE user_name = ?');
if ($hasFeedbackUserId) {
    $statsStmt->bind_param('i', $userId);
} else {
    $statsStmt->bind_param('s', $userName);
}
$statsStmt->execute();
$statsRes = $statsStmt->get_result();
if ($statsRes && $statsRes->num_rows === 1) {
    $feedbackStats = $statsRes->fetch_assoc();
}
$statsStmt->close();

$securityEvents = [];
$secStmt = $db->prepare('SELECT event_type, ip_address, description, timestamp FROM security_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10');
if ($secStmt) {
    $secStmt->bind_param('i', $userId);
    $secStmt->execute();
    $secRes = $secStmt->get_result();
    while ($secRes && ($secRow = $secRes->fetch_assoc())) {
        $securityEvents[] = $secRow;
    }
    if ($secRes) {
        $secRes->free();
    }
    $secStmt->close();
}

$db->close();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="/assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="/assets/css/form-redesign-base.css">
    <link rel="stylesheet" href="/assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="/user-dashboard/user-shell.css?v=<?php echo filemtime(__DIR__ . '/user-shell.css'); ?>">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        #idUploadWizardModal .avatar-crop-dialog {
            border-radius: 14px;
            border: 1px solid #dbe7f3;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.24);
        }
        #idUploadWizardModal .avatar-crop-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        #idWizardFrontPanel,
        #idWizardBackPanel {
            border: 1px solid #dbe7f3;
            border-radius: 12px;
            background: #f8fbff;
            padding: 14px;
        }
        #idWizardFrontPanel label,
        #idWizardBackPanel label {
            color: #1e3a8a;
            font-weight: 700;
            font-size: .92rem;
        }
        #idFrontPicked,
        #idBackPicked {
            min-height: 22px;
            font-size: .86rem;
            color: #334155;
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 6px 8px;
        }
        #idWizardNextBtn,
        #idWizardSubmitBtn {
            min-width: 120px;
        }
        #idWizardBackBtn {
            min-width: 90px;
        }
        #idUploadWizardModal button.profile-action-btn,
        #idUploadWizardModal #pickIdFrontBtn,
        #idUploadWizardModal #pickIdBackBtn,
        #idUploadWizardModal #idWizardNextBtn,
        #idUploadWizardModal #idWizardBackBtn,
        #idUploadWizardModal #idWizardSubmitBtn {
            appearance: none !important;
            -webkit-appearance: none !important;
            border-radius: 10px !important;
            border: 1px solid #cbd5e1 !important;
            min-height: 40px !important;
            padding: 8px 14px !important;
            font-size: .9rem !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            cursor: pointer !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08) !important;
            transition: all .2s ease !important;
        }
        #idUploadWizardModal #pickIdFrontBtn,
        #idUploadWizardModal #pickIdBackBtn,
        #idUploadWizardModal #idWizardNextBtn,
        #idUploadWizardModal #idWizardSubmitBtn {
            background: linear-gradient(135deg, #1d4e89, #3f83c9) !important;
            color: #fff !important;
            border-color: #2d5f9a !important;
        }
        #idUploadWizardModal #idWizardBackBtn {
            background: #fff !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }
        #idUploadWizardModal #pickIdFrontBtn:hover,
        #idUploadWizardModal #pickIdBackBtn:hover,
        #idUploadWizardModal #idWizardNextBtn:hover,
        #idUploadWizardModal #idWizardSubmitBtn:hover {
            filter: brightness(1.04) !important;
            transform: translateY(-1px);
        }
        #idUploadWizardModal #idWizardBackBtn:hover {
            background: #f8fafc !important;
            border-color: #64748b !important;
        }
        #idUploadWizardModal .id-wizard-actions {
            margin-top: 12px;
            display: flex;
            width: 100%;
            align-items: center;
            gap: 10px;
        }
        #idUploadWizardModal .front-actions {
            justify-content: flex-end;
        }
        #idUploadWizardModal .back-actions {
            justify-content: space-between;
        }
        #idUploadWizardModal .back-actions #idWizardSubmitBtn {
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
        <div class="nav-logo"><img src="/assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div>
        <div class="nav-user-profile">
            <?php if ($profileImageWebPath !== ''): ?>
                <img src="<?php echo htmlspecialchars($profileImageWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" class="user-profile-image">
            <?php else: ?>
                <div class="user-initial-badge" style="background: <?php echo htmlspecialchars($avatarColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="nav-user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="nav-user-email"><?php echo htmlspecialchars($userEmail ?: 'No email provided', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="nav-links">
            <a href="user-dashboard.php"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring Icon" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/assets/images/admin/prioritization.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php" class="active"><svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 3.63 17l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82L4.3 6.46A2 2 0 1 1 7.12 3.63l.06.06A1.65 1.65 0 0 0 9 4.6c.38 0 .74-.13 1-.37.27-.24.42-.58.42-.94V3a2 2 0 1 1 4 0v.09c0 .36.15.7.42.94.26.24.62.37 1 .37a1.65 1.65 0 0 0 1.82-.33l.06-.06A2 2 0 1 1 20.37 7.1l-.06.06A1.65 1.65 0 0 0 19.4 9c0 .38.13.74.37 1 .24.27.58.42.94.42H21a2 2 0 1 1 0 4h-.09c-.36 0-.7.15-.94.42-.24.26-.37.62-.37 1z"></path></svg> Settings</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer"><a href="/logout.php" class="btn-logout nav-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span>Logout</span></a></div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn"><a href="#" id="toggleSidebarShow" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></a></div>

    <section class="main-content settings-page">
        <div class="dash-header"><h1>User Settings</h1><p>Manage your profile and password.</p></div>

        <div class="settings-layout">
            <div class="card ac-e9b6d4ca settings-card">
                <div class="settings-tabs settings-switcher">
                    <a href="user-settings.php?tab=profile" class="tab-btn <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">Profile</a>
                    <a href="user-settings.php?tab=password" class="tab-btn <?php echo $activeTab === 'password' ? 'active' : ''; ?>">Change Password</a>
                </div>

                <?php if (!empty($errors)): ?><div class="settings-alert settings-alert-error" style="margin-top:12px;"><?php foreach ($errors as $err): ?><div><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
                <?php if (!empty($success)): ?><div class="settings-alert settings-alert-success" style="margin-top:12px;"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

                <?php if ($activeTab === 'profile'): ?>
                    <div class="settings-view">
                        <div class="settings-panel">
                            <h3 class="ac-b75fad00">Account Information</h3>
                            <div class="avatar-upload-section">
                                <div class="avatar-upload-meta">
                                    <strong>Profile Photo</strong>
                                    <span>Visible in your sidebar profile card</span>
                                </div>
                                <form method="post" action="user-settings.php?tab=profile" enctype="multipart/form-data" class="avatar-upload-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="photo_action" value="upload">
                                    <input type="file" id="profilePhotoInput" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                    <div class="avatar-upload-row">
                                        <label for="profilePhotoInput" class="avatar-upload-trigger" title="Choose profile photo">
                                            <?php if ($profileImageWebPath !== ''): ?>
                                                <img src="<?php echo htmlspecialchars($profileImageWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo" class="avatar-upload-image">
                                            <?php else: ?>
                                                <div class="avatar-upload-initial" style="background: <?php echo htmlspecialchars($avatarColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <span class="avatar-upload-camera" aria-hidden="true">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                            </span>
                                        </label>
                                        <div class="profile-btn-stack">
                                            <button type="submit" class="profile-action-btn profile-action-btn-primary">Upload Profile Photo</button>
                                            <?php if ($profileImageWebPath !== ''): ?>
                                                <button form="removePhotoForm" type="submit" class="profile-action-btn profile-action-btn-ghost">Remove Photo</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                                <?php if ($profileImageWebPath !== ''): ?>
                                    <form id="removePhotoForm" method="post" action="user-settings.php?tab=profile" class="profile-inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="photo_action" value="remove">
                                    </form>
                                <?php endif; ?>
                                <small style="display:block;color:#64748b;margin-top:6px;">Click the avatar to choose photo, then crop and confirm upload. Allowed: JPG, PNG, WEBP. Max: 3MB.</small>
                            </div>
                            <div class="settings-info-form" role="group" aria-label="Account information">
                                <div class="settings-info-field"><label>First Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Middle Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Last Name</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Suffix</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['suffix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Full Name</label><div class="settings-info-value"><?php echo htmlspecialchars($displayFullName, ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Email</label><div class="settings-info-value"><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Contact</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Birthdate</label><div class="settings-info-value"><?php echo !empty($user['birthdate']) ? date('M d, Y', strtotime((string) $user['birthdate'])) : '-'; ?></div></div>
                                <div class="settings-info-field settings-info-field-full"><label>Address</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Gender</label><div class="settings-info-value"><?php echo htmlspecialchars($formatProfileValue($user['gender'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>Civil Status</label><div class="settings-info-value"><?php echo htmlspecialchars($formatProfileValue($user['civil_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>ID Type</label><div class="settings-info-value"><?php echo htmlspecialchars($formatProfileValue($user['id_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field"><label>ID Number</label><div class="settings-info-value"><?php echo htmlspecialchars((string) ($user['id_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="settings-info-field settings-info-field-full">
                                    <label>ID Upload</label>
                                    <div class="settings-info-value">
                                        <?php
                                        $idBundle = parse_id_upload_bundle((string) ($user['id_upload'] ?? ''));
                                        $idFront = $idBundle['front'];
                                        $idBack = $idBundle['back'];
                                        $idSingle = $idBundle['single'];
                                        ?>
                                        <?php if ($idFront !== '' || $idBack !== '' || $idSingle !== ''): ?>
                                            <button
                                                type="button"
                                                class="profile-action-btn profile-action-btn-primary id-view-btn"
                                                data-id-url="<?php echo htmlspecialchars($idSingle, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-id-front-url="<?php echo htmlspecialchars($idFront, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-id-back-url="<?php echo htmlspecialchars($idBack, ENT_QUOTES, 'UTF-8'); ?>"
                                            >View Uploaded ID</button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                    <form id="idUploadForm" method="post" action="user-settings.php?tab=profile" enctype="multipart/form-data" class="id-upload-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id_action" value="upload">
                                        <button type="button" id="openIdUploadWizard" class="profile-action-btn profile-action-btn-primary">Upload Front and Back ID</button>
                                        <input type="file" id="idFrontInput" name="id_file_front" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required style="display:none;">
                                        <input type="file" id="idBackInput" name="id_file_back" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required style="display:none;">
                                        <?php if (!empty($user['id_upload'])): ?>
                                            <button form="removeIdForm" type="submit" class="profile-action-btn profile-action-btn-ghost">Remove ID</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if (!empty($user['id_upload'])): ?>
                                        <form id="removeIdForm" method="post" action="user-settings.php?tab=profile" class="profile-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_action" value="remove">
                                        </form>
                                    <?php endif; ?>
                                    <small style="display:block;color:#64748b;margin-top:6px;">Required: upload both ID Front and ID Back. Allowed: JPG, PNG, WEBP. Max: 5MB each. Upload starts automatically after both files are selected.</small>
                                </div>
                                <div class="settings-info-field"><label>Registered On</label><div class="settings-info-value"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime((string) $user['created_at'])) : '-'; ?></div></div>
                                <div class="settings-info-field"><label>Feedback Submitted</label><div class="settings-info-value"><?php echo (int) ($feedbackStats['total'] ?? 0); ?></div></div>
                                <div class="settings-info-field"><label>Pending Feedback</label><div class="settings-info-value"><?php echo (int) ($feedbackStats['pending'] ?? 0); ?></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card" style="margin-top:14px;">
                        <h4 style="margin:0 0 10px;">Security Activity</h4>
                        <p style="margin:0 0 12px;color:#64748b;font-size:.88rem;">Recent account events (login, password reset, suspicious activity).</p>
                        <div class="table-wrap">
                            <table class="projects-table">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Event</th>
                                        <th>IP</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($securityEvents)): ?>
                                        <?php foreach ($securityEvents as $event): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string) date('M d, Y h:i A', strtotime((string) ($event['timestamp'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($event['event_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($event['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($event['description'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="ac-a004b216">No recent security events.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="settings-card" style="margin-top:14px;">
                        <h4 style="margin:0 0 10px;">Privacy & Data Use</h4>
                        <ul style="margin:0;padding-left:18px;color:#475569;line-height:1.5;">
                            <li>Your ID is used for verification and anti-fraud checks only.</li>
                            <li>Project feedback records are tied to your account for auditing and response updates.</li>
                            <li>Security events (login/reset) are logged to protect your account.</li>
                            <li>If you need account data review/removal, contact LGU support.</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="settings-view">
                        <div class="settings-panel settings-password-panel">
                            <h3 class="ac-b75fad00">Change Your Password</h3>
                            <p class="settings-subtitle">Use at least 8 characters, including uppercase, number, and special symbol.</p>
                            <form method="post" action="user-settings.php?tab=password" class="settings-form" id="passwordChangeForm">
                                <input type="hidden" name="change_password" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ac-4a3180e2"><label class="ac-37c29296">Current Password</label><input class="ac-6f762f4a" type="password" name="current_password" required autocomplete="current-password"></div>
                                <div class="ac-4a3180e2"><label class="ac-37c29296">New Password</label><input class="ac-6f762f4a" type="password" id="newPassword" name="new_password" required autocomplete="new-password"><small id="passwordStrength" style="display:block;margin-top:6px;color:#475569;">Password strength: -</small></div>
                                <div class="ac-b75fad00"><label class="ac-37c29296">Confirm New Password</label><input class="ac-6f762f4a" type="password" name="confirm_password" required autocomplete="new-password"></div>
                                <button type="submit" class="ac-f84d9680">Update Password</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>

    <div class="avatar-crop-modal" id="avatarCropModal" hidden>
        <div class="avatar-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="avatarCropTitle">
            <div class="avatar-crop-header">
                <h3 id="avatarCropTitle">Crop Profile Photo</h3>
                <button type="button" class="avatar-crop-close" id="avatarCropClose" aria-label="Close cropper">&times;</button>
            </div>
            <div class="avatar-crop-body">
                <canvas id="avatarCropCanvas" width="320" height="320" aria-label="Profile crop preview"></canvas>
                <label for="avatarZoomRange">Zoom</label>
                <input type="range" id="avatarZoomRange" min="1" max="3" step="0.01" value="1.2">
                <small>Drag image to position it inside the circle.</small>
            </div>
            <div class="avatar-crop-actions">
                <button type="button" class="btn-clear-filters" id="avatarCropCancel">Cancel</button>
                <button type="button" class="ac-f84d9680" id="avatarCropApply">Use Cropped Photo</button>
            </div>
        </div>
    </div>


    <div class="avatar-crop-modal" id="idViewerModal" hidden>
        <div class="avatar-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="idViewerTitle" style="max-width:900px;width:min(94vw,900px);">
            <div class="avatar-crop-header">
                <h3 id="idViewerTitle">Uploaded ID (Front / Back)</h3>
                <button type="button" class="avatar-crop-close" id="idViewerClose" aria-label="Close ID viewer">&times;</button>
            </div>
            <div class="avatar-crop-body" style="padding:12px;">
                <div id="idViewerZoomControls" style="display:none;align-items:center;gap:8px;justify-content:flex-end;margin-bottom:8px;">
                    <button type="button" class="btn-clear-filters" id="idZoomOut">-</button>
                    <input type="range" id="idZoomRange" min="1" max="3" step="0.1" value="1" style="width:180px;">
                    <button type="button" class="btn-clear-filters" id="idZoomIn">+</button>
                    <button type="button" class="ac-f84d9680" id="idZoomReset">Reset</button>
                </div>
                <div id="idViewerImageWrap" style="display:none;max-height:74vh;overflow:auto;border:1px solid #dbe7f3;border-radius:8px;background:#fff;padding:10px;">
                    <div id="idViewerDualWrap" style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:10px;">
                        <img id="idViewerFrontImage" src="" alt="Uploaded ID Front" style="display:block;max-width:100%;height:auto;margin:0 auto;border-radius:8px;border:1px solid #e2e8f0;">
                        <img id="idViewerBackImage" src="" alt="Uploaded ID Back" style="display:block;max-width:100%;height:auto;margin:0 auto;border-radius:8px;border:1px solid #e2e8f0;">
                    </div>
                    <img id="idViewerImage" src="" alt="Uploaded ID" style="display:none;max-height:none;width:auto;max-width:none;margin:0 auto;border-radius:8px;transform-origin:center center;">
                </div>
                <iframe id="idViewerPdf" src="" title="Uploaded ID PDF" style="display:none;width:100%;height:74vh;border:1px solid #dbe7f3;border-radius:8px;background:#fff;"></iframe>
            </div>
        </div>
    </div>

    <div class="avatar-crop-modal" id="idUploadWizardModal" hidden>
        <div class="avatar-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="idUploadWizardTitle" style="max-width:640px;width:min(92vw,640px);">
            <div class="avatar-crop-header">
                <h3 id="idUploadWizardTitle">Upload Verification ID</h3>
                <button type="button" class="avatar-crop-close" id="idUploadWizardClose" aria-label="Close upload wizard">&times;</button>
            </div>
            <div class="avatar-crop-body" style="padding:14px;display:grid;gap:12px;">
                <div id="idWizardFrontPanel">
                    <label for="idFrontInput" style="display:block;font-weight:600;margin-bottom:6px;">Choose front side of ID</label>
                    <button type="button" id="pickIdFrontBtn" class="profile-action-btn profile-action-btn-primary">Choose Front Photo</button>
                    <div id="idFrontPicked" style="margin-top:8px;color:#64748b;">No file selected.</div>
                    <div class="id-wizard-actions front-actions">
                        <button type="button" id="idWizardNextBtn" class="profile-action-btn profile-action-btn-primary" style="display:none;">Next</button>
                    </div>
                </div>
                <div id="idWizardBackPanel" style="display:none;">
                    <label for="idBackInput" style="display:block;font-weight:600;margin-bottom:6px;">Choose back side of ID</label>
                    <button type="button" id="pickIdBackBtn" class="profile-action-btn profile-action-btn-primary">Choose Back Photo</button>
                    <div id="idBackPicked" style="margin-top:8px;color:#64748b;">No file selected.</div>
                    <div class="id-wizard-actions back-actions">
                        <button type="button" id="idWizardBackBtn" class="profile-action-btn profile-action-btn-ghost">Back</button>
                        <button type="button" id="idWizardSubmitBtn" class="profile-action-btn profile-action-btn-primary" style="display:none;">Upload ID</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/user-dashboard/user-settings.js?v=<?php echo filemtime(__DIR__ . '/user-settings.js'); ?>"></script>
</body>
</html>








