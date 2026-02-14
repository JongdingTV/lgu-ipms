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
                $existingPath = dirname(__DIR__) . '/' . ltrim((string) $user['id_upload'], '/');
                if (is_file($existingPath)) {
                    @unlink($existingPath);
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
            if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Please select a valid ID file.';
            } else {
                $tmpFile = $_FILES['id_file']['tmp_name'];
                $fileSize = (int) ($_FILES['id_file']['size'] ?? 0);
                if ($fileSize > 5 * 1024 * 1024) {
                    $errors[] = 'ID file must be 5MB or less.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpFile) : '';
                    if ($finfo) {
                        finfo_close($finfo);
                    }

                    $mimeMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'application/pdf' => 'pdf'
                    ];

                    if (!isset($mimeMap[$mimeType])) {
                        $errors[] = 'Only JPG, PNG, WEBP, or PDF files are allowed for ID upload.';
                    } else {
                        $existing = glob($uploadDir . '/user_' . $userId . '.*') ?: [];
                        foreach ($existing as $path) {
                            @unlink($path);
                        }

                        $ext = $mimeMap[$mimeType];
                        $target = $uploadDir . '/user_' . $userId . '.' . $ext;
                        if (!move_uploaded_file($tmpFile, $target)) {
                            $errors[] = 'Unable to save the ID file.';
                        } else {
                            $webPath = '/uploads/user-id/' . basename($target);
                            $idStmt = $db->prepare('UPDATE users SET id_upload = ? WHERE id = ?');
                            $idStmt->bind_param('si', $webPath, $userId);
                            $ok = $idStmt->execute();
                            $idStmt->close();

                            if ($ok) {
                                $user['id_upload'] = $webPath;
                                $success = 'ID file uploaded successfully.';
                                $activeTab = 'profile';
                            } else {
                                $errors[] = 'Unable to save ID file reference.';
                            }
                        }
                    }
                }
            }
        }
    }
}

$feedbackStats = ['total' => 0, 'pending' => 0];
$statsStmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending FROM feedback WHERE user_name = ?');
$statsStmt->bind_param('s', $userName);
$statsStmt->execute();
$statsRes = $statsStmt->get_result();
if ($statsRes && $statsRes->num_rows === 1) {
    $feedbackStats = $statsRes->fetch_assoc();
}
$statsStmt->close();

$db->close();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
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
</head>
<body>
    <div class="sidebar-toggle-wrapper"><button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg></button></div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
        <div class="nav-logo"><img src="/logocityhall.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div>
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
            <a href="user-settings.php" class="active"><img src="/assets/images/admin/person.png" alt="Settings Icon" class="nav-icon"> Settings</a>
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
                                        <?php if (!empty($user['id_upload'])): ?>
                                            <a href="<?php echo htmlspecialchars((string) $user['id_upload'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View Uploaded File</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="user-settings.php?tab=profile" enctype="multipart/form-data" class="id-upload-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id_action" value="upload">
                                        <input type="file" name="id_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                                        <div class="profile-btn-stack">
                                            <button type="submit" class="profile-action-btn profile-action-btn-primary">Upload ID</button>
                                            <?php if (!empty($user['id_upload'])): ?>
                                                <button form="removeIdForm" type="submit" class="profile-action-btn profile-action-btn-ghost">Remove ID</button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                    <?php if (!empty($user['id_upload'])): ?>
                                        <form id="removeIdForm" method="post" action="user-settings.php?tab=profile" class="profile-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_action" value="remove">
                                        </form>
                                    <?php endif; ?>
                                    <small style="display:block;color:#64748b;margin-top:6px;">Allowed: JPG, PNG, WEBP, PDF. Max: 5MB.</small>
                                </div>
                                <div class="settings-info-field"><label>Registered On</label><div class="settings-info-value"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime((string) $user['created_at'])) : '-'; ?></div></div>
                                <div class="settings-info-field"><label>Feedback Submitted</label><div class="settings-info-value"><?php echo (int) ($feedbackStats['total'] ?? 0); ?></div></div>
                                <div class="settings-info-field"><label>Pending Feedback</label><div class="settings-info-value"><?php echo (int) ($feedbackStats['pending'] ?? 0); ?></div></div>
                            </div>
                        </div>
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

    <script src="/user-dashboard/user-settings.js?v=<?php echo filemtime(__DIR__ . '/user-settings.js'); ?>"></script>
</body>
</html>

