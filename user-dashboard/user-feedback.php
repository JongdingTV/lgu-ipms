<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userStmt = $db->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userRes = $userStmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : [];
$userStmt->close();

$userName = trim($_SESSION['user_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$userEmail = $user['email'] ?? '';
$canAccessFeedback = user_feedback_access_allowed($db, $userId);
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

function normalize_feedback_text(string $value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
}

function feedback_table_has_user_id(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'feedback'
           AND COLUMN_NAME = 'user_id'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function feedback_table_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'feedback'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function feedback_bind_params(mysqli_stmt $stmt, string $types, array &$values): bool
{
    $bindParams = [$types];
    foreach ($values as $i => &$value) {
        $bindParams[] = &$value;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

function feedback_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value);
    }
    return (int) strlen($value);
}

function feedback_clean_display_description(string $description): string
{
    // Remove system metadata lines from citizen-facing description view.
    $cleaned = preg_replace('/^\[(Photo Attachment Private|Google Maps Pin|Pinned Address|Complete Address)\].*$/mi', '', $description);
    $cleaned = (string) ($cleaned ?? '');
    $lines = preg_split("/\r\n|\n|\r/", $cleaned);
    $normalizedSeen = [];
    $result = [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') continue;
        $key = strtolower(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed);
        if (isset($normalizedSeen[$key])) continue;
        $normalizedSeen[$key] = true;
        $result[] = $trimmed;
    }
    if (empty($result)) return '-';
    return implode("\n", $result);
}

function feedback_extract_photo_files(string $description, string $photoPathRaw = ''): array
{
    $files = ['raw' => [], 'base' => []];
    $add = static function (string $value) use (&$files): void {
        $normalized = trim(rawurldecode(str_replace('\\', '/', trim($value))), " \t\n\r\0\x0B\"'");
        if ($normalized === '') {
            return;
        }
        if (preg_match('/\.(?:jpg|jpeg|png|webp)$/i', $normalized)) {
            $files['raw'][$normalized] = true;
        }
        $base = basename($normalized);
        if ($base !== '' && preg_match('/\.(?:jpg|jpeg|png|webp)$/i', $base)) {
            $files['base'][$base] = true;
        }
    };

    if ($photoPathRaw !== '') {
        $parts = preg_split('/[;,]/', $photoPathRaw) ?: [$photoPathRaw];
        foreach ($parts as $part) {
            $add((string) $part);
        }
    }

    if (preg_match_all('/\[Photo Attachment Private\]\s+([^\r\n]+)/i', $description, $m1)) {
        foreach ($m1[1] as $candidate) {
            $add((string) $candidate);
        }
    }
    if (preg_match_all('/\[Photo Attachment\]\s+([^\r\n]+)/i', $description, $m2)) {
        foreach ($m2[1] as $candidate) {
            $add((string) $candidate);
        }
    }

    $ordered = array_values(array_keys($files['raw']));
    foreach (array_keys($files['base']) as $base) {
        if (!in_array($base, $ordered, true)) {
            $ordered[] = $base;
        }
    }
    return $ordered;
}

function resolve_feedback_upload_dir(): ?string
{
    $candidates = [
        dirname(__DIR__) . '/../private_uploads/lgu-ipms/feedback',
        dirname(__DIR__) . '/private_uploads/lgu-ipms/feedback'
    ];

    foreach ($candidates as $candidate) {
        $dir = str_replace(['\\', '//'], ['/', '/'], $candidate);
        if (@is_dir($dir)) {
            return rtrim($dir, '/');
        }
        if (@mkdir($dir, 0755, true)) {
            return rtrim($dir, '/');
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    header('Content-Type: application/json');
    try {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token. Please refresh and try again.']);
        $db->close();
        exit;
    }
    if (!$canAccessFeedback) {
        echo json_encode(['success' => false, 'message' => 'Your account is pending ID verification. Feedback submission is locked until verification is approved.']);
        $db->close();
        exit;
    }
    if (is_rate_limited('user_feedback_submit', 8, 600)) {
        echo json_encode(['success' => false, 'message' => 'Too many submission attempts. Please wait a few minutes before trying again.']);
        $db->close();
        exit;
    }
    if (is_user_rate_limited('user_feedback_submit', 6, 600, $userId)) {
        echo json_encode(['success' => false, 'message' => 'Too many feedback attempts for your account. Please wait before trying again.']);
        $db->close();
        exit;
    }
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        record_attempt('user_feedback_submit');
        echo json_encode(['success' => false, 'message' => 'Invalid submission detected.']);
        $db->close();
        exit;
    }

    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $district = trim((string) ($_POST['district'] ?? ''));
    $barangay = trim((string) ($_POST['barangay'] ?? ''));
    $altName = trim((string) ($_POST['alt_name'] ?? ''));
    $completeAddress = trim((string) ($_POST['complete_address'] ?? ''));
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['feedback'] ?? '');
    $gpsLat = trim($_POST['gps_lat'] ?? '');
    $gpsLng = trim($_POST['gps_lng'] ?? '');
    $gpsAccuracy = trim($_POST['gps_accuracy'] ?? '');
    $gpsMapUrl = trim($_POST['gps_map_url'] ?? '');
    $gpsAddress = trim($_POST['gps_address'] ?? '');
    $status = 'Pending';

    $photoSavedName = '';
    $uploadedPhotos = [];
    if (isset($_FILES['photo'])) {
        $photoNames = $_FILES['photo']['name'] ?? [];
        $photoTmp = $_FILES['photo']['tmp_name'] ?? [];
        $photoErr = $_FILES['photo']['error'] ?? [];
        $photoSize = $_FILES['photo']['size'] ?? [];

        if (!is_array($photoNames)) {
            $photoNames = [$photoNames];
            $photoTmp = [$photoTmp];
            $photoErr = [$photoErr];
            $photoSize = [$photoSize];
        }

        $selectedIdx = [];
        foreach ($photoNames as $idx => $nm) {
            if ((int)($photoErr[$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $selectedIdx[] = $idx;
        }
        if (count($selectedIdx) > 3) {
            echo json_encode(['success' => false, 'message' => 'You can upload up to 3 photos only.']);
            $db->close();
            exit;
        }

        if (!empty($selectedIdx)) {
            $uploadDir = resolve_feedback_upload_dir();
            if ($uploadDir === null) {
                echo json_encode(['success' => false, 'message' => 'Unable to prepare upload directory.']);
                $db->close();
                exit;
            }

            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($selectedIdx as $idx) {
                $errCode = (int)($photoErr[$idx] ?? UPLOAD_ERR_NO_FILE);
                if ($errCode !== UPLOAD_ERR_OK) {
                    $errMsg = 'Photo upload failed. Please try another image.';
                    if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                        $errMsg = 'One of the photos is too large for server limits. Please use smaller images.';
                    } elseif ($errCode === UPLOAD_ERR_PARTIAL) {
                        $errMsg = 'A photo upload was interrupted. Please upload again.';
                    } elseif ($errCode === UPLOAD_ERR_NO_TMP_DIR || $errCode === UPLOAD_ERR_CANT_WRITE) {
                        $errMsg = 'Server storage is temporarily unavailable for photo upload.';
                    }
                    echo json_encode(['success' => false, 'message' => $errMsg]);
                    $db->close();
                    exit;
                }
                $fileName = (string)($photoNames[$idx] ?? '');
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $tmpName = (string)($photoTmp[$idx] ?? '');
                $size = (int)($photoSize[$idx] ?? 0);
                if (!in_array($ext, $allowedExt, true) || $size <= 0 || $size > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'Each photo must be JPG, PNG, or WEBP and up to 5MB only.']);
                    $db->close();
                    exit;
                }
                $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmpName) : '';
                if ($mime !== '' && strpos($mime, 'image/') !== 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid photo file type detected.']);
                    $db->close();
                    exit;
                }

                $savedName = 'feedback_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $savedPath = $uploadDir . '/' . $savedName;
                $saved = false;
                if (function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
                    $imgInfo = @getimagesize($tmpName);
                    $imgMime = (string) ($imgInfo['mime'] ?? '');
                    $src = null;
                    if ($imgMime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($tmpName);
                    elseif ($imgMime === 'image/png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($tmpName);
                    elseif ($imgMime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($tmpName);
                    if ($src) {
                        imagesavealpha($src, true);
                        if ($ext === 'jpg' || $ext === 'jpeg') $saved = @imagejpeg($src, $savedPath, 88);
                        elseif ($ext === 'png') $saved = @imagepng($src, $savedPath, 6);
                        elseif ($ext === 'webp' && function_exists('imagewebp')) $saved = @imagewebp($src, $savedPath, 85);
                        imagedestroy($src);
                    }
                }
                if (!$saved) $saved = move_uploaded_file($tmpName, $savedPath);
                if (!$saved) {
                    echo json_encode(['success' => false, 'message' => 'Unable to save uploaded photo.']);
                    $db->close();
                    exit;
                }
                $uploadedPhotos[] = $savedName;
            }
        }
    }
    if (!empty($uploadedPhotos)) {
        $photoSavedName = (string)$uploadedPhotos[0];
        foreach ($uploadedPhotos as $photoFile) {
            $description .= "\n\n[Photo Attachment Private] " . $photoFile;
        }
    }

    $mapLink = $gpsMapUrl !== '' ? $gpsMapUrl : null;
    if ($gpsLat !== '' && $gpsLng !== '') {
        $location .= ' | GPS: ' . $gpsLat . ', ' . $gpsLng;
        if ($gpsAccuracy !== '') {
            $location .= ' | Accuracy: ' . $gpsAccuracy . 'm';
        }
        if ($gpsMapUrl !== '') {
            $description .= "\n\n[Google Maps Pin] " . $gpsMapUrl;
        }
        if ($gpsAddress !== '') {
            $description .= "\n\n[Pinned Address] " . $gpsAddress;
        }
    }

    if ($subject === '' || $category === '' || $location === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        $db->close();
        exit;
    }
    if ($completeAddress === '' || feedback_strlen($completeAddress) < 8 || feedback_strlen($completeAddress) > 255) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid complete address (8-255 characters).']);
        $db->close();
        exit;
    }
    if (!preg_match('/^[1-6]$/', $district) || $barangay === '' || $altName === '') {
        echo json_encode(['success' => false, 'message' => 'Please select a valid district, barangay, and alternative name.']);
        $db->close();
        exit;
    }
    $serverLocation = 'District ' . $district . ' | ' . $barangay . ' | ' . $altName;
    if (stripos($location, $serverLocation) !== 0) {
        $location = $serverLocation;
    }
    $description .= "\n\n[Complete Address] " . $completeAddress;
    if (feedback_strlen($subject) > 100 || feedback_strlen($category) > 100 || feedback_strlen($location) > 255) {
        record_attempt('user_feedback_submit');
        echo json_encode(['success' => false, 'message' => 'Some fields are too long.']);
        $db->close();
        exit;
    }
    if (feedback_strlen($description) < 12 || feedback_strlen($description) > 5000) {
        record_attempt('user_feedback_submit');
        echo json_encode(['success' => false, 'message' => 'Feedback must be between 12 and 5000 characters.']);
        $db->close();
        exit;
    }

    $hasFeedbackUserId = feedback_table_has_user_id($db);
    $cooldownStmt = $hasFeedbackUserId
        ? $db->prepare('SELECT date_submitted FROM feedback WHERE user_id = ? ORDER BY date_submitted DESC LIMIT 1')
        : $db->prepare('SELECT date_submitted FROM feedback WHERE user_name = ? ORDER BY date_submitted DESC LIMIT 1');
    if ($cooldownStmt) {
        if ($hasFeedbackUserId) {
            $cooldownStmt->bind_param('i', $userId);
        } else {
            $cooldownStmt->bind_param('s', $userName);
        }
        $cooldownStmt->execute();
        $cooldownRes = $cooldownStmt->get_result();
        $latest = $cooldownRes ? $cooldownRes->fetch_assoc() : null;
        $cooldownStmt->close();

        if ($latest && !empty($latest['date_submitted'])) {
            $lastTs = strtotime((string) $latest['date_submitted']) ?: 0;
            if ($lastTs > 0 && (time() - $lastTs) < 90) {
                echo json_encode(['success' => false, 'message' => 'Please wait at least 90 seconds before submitting another feedback.']);
                $db->close();
                exit;
            }
        }
    }

    $dailyStmt = $hasFeedbackUserId
        ? $db->prepare('SELECT COUNT(*) AS total FROM feedback WHERE user_id = ? AND date_submitted >= (NOW() - INTERVAL 1 DAY)')
        : $db->prepare('SELECT COUNT(*) AS total FROM feedback WHERE user_name = ? AND date_submitted >= (NOW() - INTERVAL 1 DAY)');
    if ($dailyStmt) {
        if ($hasFeedbackUserId) {
            $dailyStmt->bind_param('i', $userId);
        } else {
            $dailyStmt->bind_param('s', $userName);
        }
        $dailyStmt->execute();
        $dailyRes = $dailyStmt->get_result();
        $dailyCount = (int) (($dailyRes ? $dailyRes->fetch_assoc()['total'] : 0) ?? 0);
        $dailyStmt->close();
        if ($dailyCount >= 5) {
            echo json_encode(['success' => false, 'message' => 'Daily submission limit reached (5 per 24 hours). Please try again tomorrow.']);
            $db->close();
            exit;
        }
    }

    $normSubject = normalize_feedback_text($subject);
    $normDescription = normalize_feedback_text($description);
    $dupeStmt = $hasFeedbackUserId
        ? $db->prepare('SELECT subject, description FROM feedback WHERE user_id = ? AND date_submitted >= (NOW() - INTERVAL 1 DAY)')
        : $db->prepare('SELECT subject, description FROM feedback WHERE user_name = ? AND date_submitted >= (NOW() - INTERVAL 1 DAY)');
    if ($dupeStmt) {
        if ($hasFeedbackUserId) {
            $dupeStmt->bind_param('i', $userId);
        } else {
            $dupeStmt->bind_param('s', $userName);
        }
        $dupeStmt->execute();
        $dupeRes = $dupeStmt->get_result();
        while ($dupeRes && ($row = $dupeRes->fetch_assoc())) {
            $existingSubject = normalize_feedback_text((string) ($row['subject'] ?? ''));
            $existingDescription = normalize_feedback_text((string) ($row['description'] ?? ''));
            if ($existingSubject === $normSubject && $existingDescription === $normDescription) {
                $dupeStmt->close();
                echo json_encode(['success' => false, 'message' => 'This looks like a duplicate feedback. Please update details before submitting again.']);
                $db->close();
                exit;
            }
        }
        $dupeStmt->close();
    }

    $hasDistrictCol = feedback_table_has_column($db, 'district');
    $hasBarangayCol = feedback_table_has_column($db, 'barangay');
    $hasAlternativeNameCol = feedback_table_has_column($db, 'alternative_name');
    $hasExactAddressCol = feedback_table_has_column($db, 'exact_address');
    $hasPhotoPathCol = feedback_table_has_column($db, 'photo_path');
    $hasMapLatCol = feedback_table_has_column($db, 'map_lat');
    $hasMapLngCol = feedback_table_has_column($db, 'map_lng');
    $hasMapLinkCol = feedback_table_has_column($db, 'map_link');

    $columns = [];
    $types = '';
    $values = [];
    if ($hasFeedbackUserId) {
        $columns[] = 'user_id';
        $types .= 'i';
        $values[] = $userId;
    }
    $columns = array_merge($columns, ['user_name', 'subject', 'category', 'location', 'description', 'status']);
    $types .= 'ssssss';
    $values = array_merge($values, [$userName, $subject, $category, $location, $description, $status]);
    if ($hasDistrictCol) {
        $columns[] = 'district';
        $types .= 's';
        $values[] = 'District ' . $district;
    }
    if ($hasBarangayCol) {
        $columns[] = 'barangay';
        $types .= 's';
        $values[] = $barangay;
    }
    if ($hasAlternativeNameCol) {
        $columns[] = 'alternative_name';
        $types .= 's';
        $values[] = $altName;
    }
    if ($hasExactAddressCol) {
        $columns[] = 'exact_address';
        $types .= 's';
        $values[] = $completeAddress;
    }
    if ($hasPhotoPathCol) {
        $columns[] = 'photo_path';
        $types .= 's';
        $values[] = $photoSavedName;
    }
    if ($hasMapLatCol && $gpsLat !== '' && is_numeric($gpsLat)) {
        $columns[] = 'map_lat';
        $types .= 'd';
        $values[] = (float) $gpsLat;
    }
    if ($hasMapLngCol && $gpsLng !== '' && is_numeric($gpsLng)) {
        $columns[] = 'map_lng';
        $types .= 'd';
        $values[] = (float) $gpsLng;
    }
    if ($hasMapLinkCol) {
        $columns[] = 'map_link';
        $types .= 's';
        $values[] = $mapLink;
    }

    $stmt = $db->prepare('INSERT INTO feedback (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to submit feedback right now.']);
        $db->close();
        exit;
    }

    feedback_bind_params($stmt, $types, $values);
    $ok = $stmt->execute();
    $stmt->close();
    record_attempt('user_feedback_submit');
    record_user_attempt('user_feedback_submit', $userId);

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Feedback submitted successfully.' : 'Failed to submit feedback.'
    ]);

    $db->close();
    exit;
    } catch (Throwable $e) {
        error_log('user-feedback submit error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Submission failed due to a server error. Please try again.'
        ]);
        $db->close();
        exit;
    }
}

$hasFeedbackUserId = feedback_table_has_user_id($db);
$hasFeedbackPhotoPath = feedback_table_has_column($db, 'photo_path');
$listFields = $hasFeedbackPhotoPath
    ? 'id, subject, category, location, description, status, date_submitted, photo_path'
    : 'id, subject, category, location, description, status, date_submitted';
$listSql = $hasFeedbackUserId
    ? "SELECT {$listFields} FROM feedback WHERE user_id = ? ORDER BY date_submitted DESC LIMIT 20"
    : "SELECT {$listFields} FROM feedback WHERE user_name = ? ORDER BY date_submitted DESC LIMIT 20";
$listStmt = $db->prepare($listSql);
if ($hasFeedbackUserId) {
    $listStmt->bind_param('i', $userId);
} else {
    $listStmt->bind_param('s', $userName);
}
$listStmt->execute();
$feedbackRows = $listStmt->get_result();
$listStmt->close();

$db->close();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Feedback - LGU IPMS</title>
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
    </div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="/assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
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
            <a href="user-feedback.php" class="active"><img src="/assets/images/admin/prioritization.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/assets/images/admin/person.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Submit Feedback</h1>
            <p>Share concerns and suggestions that will be reviewed by the admin prioritization team.</p>
        </div>

        <div class="card" style="margin-bottom:18px;">
            <h3 style="margin-bottom:12px;">Feedback Form</h3>
            <form id="userFeedbackForm" class="user-feedback-form" method="post" action="user-feedback.php" enctype="multipart/form-data">
                <input type="hidden" name="feedback_submit" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
                <?php if (!$canAccessFeedback): ?>
                    <div style="margin:0 0 14px;padding:12px 14px;border-radius:12px;border:1px solid #f59e0b;border-left:5px solid #d97706;background:linear-gradient(135deg,#fff7ed,#fffbeb);color:#7c2d12;box-shadow:0 6px 18px rgba(217,119,6,.12);">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <span aria-hidden="true" style="flex:0 0 auto;display:inline-flex;width:22px;height:22px;align-items:center;justify-content:center;border-radius:999px;background:#f59e0b;color:#fff;font-size:13px;font-weight:700;">!</span>
                            <div>
                                <strong style="display:block;margin-bottom:2px;">Verification Required</strong>
                                <span style="font-weight:600;">Reminder: Your ID verification is still pending. You can view this page, but form inputs are locked until your account is verified.</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <fieldset <?php echo $canAccessFeedback ? '' : 'disabled'; ?> style="border:0;padding:0;margin:0;">

                <div class="user-feedback-form-grid">
                    <div>
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="100" required>
                    </div>
                    <div>
                        <label for="district">District</label>
                        <select id="district" name="district" required>
                            <option value="">Select District</option>
                            <option value="1">District 1</option>
                            <option value="2">District 2</option>
                            <option value="3">District 3</option>
                            <option value="4">District 4</option>
                            <option value="5">District 5</option>
                            <option value="6">District 6</option>
                        </select>
                    </div>
                    <div>
                        <label for="barangay">Barangay</label>
                        <select id="barangay" name="barangay" required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div>
                        <label for="alt_name">Alternative Name</label>
                        <select id="alt_name" name="alt_name" required disabled>
                            <option value="">Select Alternative Name</option>
                        </select>
                        <input type="hidden" id="location" name="location" value="">
                    </div>
                    <div class="full">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Energy">Energy</option>
                            <option value="Water and Waste">Water and Waste</option>
                            <option value="Social Infrastructure">Social Infrastructure</option>
                            <option value="Public Buildings">Public Buildings</option>
                            <option value="Road and Traffic">Road and Traffic</option>
                            <option value="Drainage and Flooding">Drainage and Flooding</option>
                            <option value="Street Lighting">Street Lighting</option>
                            <option value="Public Safety">Public Safety</option>
                            <option value="Health Services">Health Services</option>
                            <option value="Education Facilities">Education Facilities</option>
                            <option value="Parks and Recreation">Parks and Recreation</option>
                            <option value="Sanitation">Sanitation</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="full">
                        <label for="complete_address">Complete Address</label>
                        <input type="text" id="complete_address" name="complete_address" maxlength="255" placeholder="House/Block/Lot, Street, Barangay, District, Quezon City" required>
                    </div>
                    <div class="full">
                        <label for="feedback">Suggestion / Concern</label>
                        <textarea id="feedback" name="feedback" rows="5" required></textarea>
                    </div>
                    <div class="full">
                        <label for="photo">Upload Photos (Optional, 1-3)</label>
                        <div id="feedbackPhotoRow" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="file" id="photo" name="photo[]" accept="image/jpeg,image/png,image/webp" multiple style="flex:1 1 280px;">
                            <button type="button" id="removePhotoBtn" class="ac-f84d9680">Remove Photo</button>
                        </div>
                        <small id="photoStatus" style="display:block;margin-top:6px;color:#64748b;">No photos selected.</small>
                    </div>
                    <div class="full">
                        <label>Map Pinpoint</label>
                        <div id="feedbackMapControls" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
                            <input type="text" id="mapSearchInput" placeholder="Search place or address" style="flex:1 1 320px;">
                            <button type="button" id="mapSearchBtn" class="ac-f84d9680">Search</button>
                            <button type="button" id="gpsPinBtn" class="ac-f84d9680">Use Current Location</button>
                            <button type="button" id="improveAccuracyBtn" class="ac-f84d9680">Improve Accuracy</button>
                        </div>
                        <div id="concernMap" style="height:320px;border:1px solid #d1d5db;border-radius:10px;"></div>
                        <small id="pinnedAddress" style="display:block;margin-top:8px;color:#334155;">No pinned address yet.</small>
                        <input type="hidden" id="gps_lat" name="gps_lat" value="">
                        <input type="hidden" id="gps_lng" name="gps_lng" value="">
                        <input type="hidden" id="gps_accuracy" name="gps_accuracy" value="">
                        <input type="hidden" id="gps_map_url" name="gps_map_url" value="">
                        <input type="hidden" id="gps_address" name="gps_address" value="">
                    </div>
                </div>

                <div class="feedback-submit-row">
                    <button type="submit" class="ac-f84d9680" <?php echo $canAccessFeedback ? '' : 'disabled'; ?>>Submit Feedback</button>
                </div>
                <div id="message" style="display:none;margin-top:10px;"></div>
                </fieldset>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom:12px;">Your Submissions</h3>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                <input type="search" id="feedbackInboxSearch" placeholder="Search subject, category, location..." style="flex:1 1 260px;min-height:38px;border:1px solid #dbe7f3;border-radius:10px;padding:8px 10px;">
                <select id="feedbackInboxStatus" style="min-height:38px;border:1px solid #dbe7f3;border-radius:10px;padding:8px 10px;">
                    <option value="">All status</option>
                    <option value="Pending">Pending</option>
                    <option value="Addressed">Addressed</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Closed">Closed</option>
                </select>
                <span id="feedbackInboxCount" style="font-size:.82rem;color:#64748b;">Showing 0</span>
            </div>
            <style>
                #photo {
                    width: 100%;
                    min-height: 42px;
                    border: 1px solid #cbd5e1;
                    border-radius: 10px;
                    background: #fff;
                    color: #334155;
                    padding: 6px 8px;
                    font-size: .9rem;
                }
                #photo:hover {
                    border-color: #94a3b8;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
                }
                #photo::file-selector-button {
                    appearance: none;
                    -webkit-appearance: none;
                    border: 1px solid #2d5f9a;
                    border-radius: 8px;
                    background: linear-gradient(135deg, #1d4e89, #3f83c9);
                    color: #fff;
                    font-weight: 600;
                    font-size: .85rem;
                    padding: 8px 12px;
                    margin-right: 10px;
                    cursor: pointer;
                    transition: all .2s ease;
                }
                #photo::file-selector-button:hover {
                    filter: brightness(1.05);
                    transform: translateY(-1px);
                }
                .feedback-inbox { border:1px solid #dbe7f3;border-radius:12px;overflow:hidden;background:#fff; }
                .feedback-inbox-head { display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 12px;background:#f8fbff;border-bottom:1px solid #dbe7f3; }
                .feedback-inbox-head strong { color:#1e3a8a;font-size:.92rem; }
                .feedback-inbox-list { max-height:520px;overflow:auto; }
                .feedback-mail-row { display:grid;grid-template-columns:12px 1fr auto;gap:10px;align-items:center;padding:10px 12px;border-bottom:1px solid #eef2f7; }
                .feedback-mail-row:hover { background:#f9fbff; }
                .feedback-mail-dot { width:8px;height:8px;border-radius:999px;background:#94a3b8; }
                .feedback-mail-dot.approved { background:#16a34a; }
                .feedback-mail-dot.pending { background:#f59e0b; }
                .feedback-mail-dot.cancelled { background:#dc2626; }
                .feedback-mail-dot.onhold { background:#0ea5e9; }
                .feedback-mail-main { min-width:0;display:flex;flex-direction:column;gap:4px; }
                .feedback-mail-subject { font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
                .feedback-mail-meta { color:#64748b;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
                .feedback-mail-actions { display:flex;align-items:center;gap:8px; }
                .feedback-mail-time { color:#64748b;font-size:.8rem;white-space:nowrap; }
                @media (max-width: 768px) {
                    .feedback-mail-row { grid-template-columns:12px 1fr; }
                    .feedback-mail-actions { grid-column:2 / 3;justify-content:flex-start;flex-wrap:wrap; }
                }
            </style>
            <div class="feedback-inbox" id="feedbackHistoryList">
                <div class="feedback-inbox-head">
                    <strong>Inbox View</strong>
                    <span style="font-size:.82rem;color:#64748b;">Newest submissions first</span>
                </div>
                <div class="feedback-inbox-list">
                    <?php if ($feedbackRows && $feedbackRows->num_rows > 0): ?>
                        <?php while ($row = $feedbackRows->fetch_assoc()): ?>
                            <?php
                            $statusValue = (string) ($row['status'] ?? 'Pending');
                            $statusLower = strtolower(trim($statusValue));
                            $statusClass = 'pending';
                            if (in_array($statusLower, ['addressed', 'resolved', 'completed'], true)) {
                                $statusClass = 'approved';
                            } elseif (in_array($statusLower, ['rejected', 'invalid', 'closed'], true)) {
                                $statusClass = 'cancelled';
                            } elseif ($statusLower === 'on-hold') {
                                $statusClass = 'onhold';
                            }
                            $desc = (string) ($row['description'] ?? '');
                            $displayDesc = feedback_clean_display_description($desc);
                            $photoPath = '';
                            $photoPathCol = trim((string) ($row['photo_path'] ?? ''));
                            $photoFiles = feedback_extract_photo_files($desc, $photoPathCol);
                            if (!empty($photoFiles)) {
                                $photoPath = '/user-dashboard/feedback-photo.php?feedback_id=' . (int)($row['id'] ?? 0) . '&photo_index=0&file=' . rawurlencode((string)$photoFiles[0]);
                            }
                            ?>
                            <div
                                class="feedback-mail-row"
                                data-feedback-open="1"
                                data-photo-url="<?php echo htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8'); ?>"
                                data-status-filter="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>"
                                data-search="<?php echo htmlspecialchars(strtolower((string) $row['subject'] . ' ' . (string) $row['category'] . ' ' . (string) $row['location']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-date="<?php echo htmlspecialchars(date('M d, Y h:i A', strtotime((string) $row['date_submitted'])), ENT_QUOTES, 'UTF-8'); ?>"
                                data-subject="<?php echo htmlspecialchars((string) $row['subject'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-category="<?php echo htmlspecialchars((string) $row['category'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-location="<?php echo htmlspecialchars((string) $row['location'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>"
                                data-description="<?php echo htmlspecialchars($displayDesc, ENT_QUOTES, 'UTF-8'); ?>"
                                style="cursor:pointer;"
                            >
                                <span class="feedback-mail-dot <?php echo $statusClass; ?>"></span>
                                <div class="feedback-mail-main">
                                    <div class="feedback-mail-subject"><?php echo htmlspecialchars((string) $row['subject']); ?></div>
                                    <div class="feedback-mail-meta">
                                        <?php echo htmlspecialchars((string) $row['category']); ?> | <?php echo htmlspecialchars((string) $row['location']); ?> | <?php echo htmlspecialchars($statusValue); ?>
                                    </div>
                                </div>
                                <div class="feedback-mail-actions">
                                    <span class="feedback-mail-time"><?php echo date('M d, Y', strtotime((string) $row['date_submitted'])); ?></span>
                                    <?php if ($photoPath !== ''): ?>
                                        <span style="font-size:.78rem;color:#1d4ed8;font-weight:600;">Attachment</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="padding:14px;color:#64748b;">No feedback submitted yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>


    <div id="feedbackPhotoModal" class="avatar-crop-modal" hidden style="z-index:1400;">
        <div class="avatar-crop-dialog" style="max-width:820px;width:min(92vw,820px);">
            <div class="avatar-crop-header">
                <h3>Submitted Photo</h3>
                <button type="button" class="avatar-crop-close" id="feedbackPhotoClose" aria-label="Close photo viewer">&times;</button>
            </div>
            <div class="avatar-crop-body" style="padding:12px;">
                <img id="feedbackPhotoPreview" src="" alt="Submitted feedback photo" style="max-height:70vh;width:auto;max-width:100%;display:block;margin:0 auto;border-radius:8px;">
            </div>
        </div>
    </div>

    <div id="feedbackDetailsModal" class="avatar-crop-modal" hidden style="z-index:1300;">
        <div class="avatar-crop-dialog" style="max-width:760px;width:min(94vw,760px);">
            <div class="avatar-crop-header">
                <h3>Feedback Details</h3>
                <button type="button" class="avatar-crop-close" id="feedbackDetailsClose" aria-label="Close details viewer">&times;</button>
            </div>
            <div class="avatar-crop-body" style="padding:14px;display:grid;gap:10px;">
                <div><strong>Date:</strong> <span id="fdDate">-</span></div>
                <div><strong>Subject:</strong> <span id="fdSubject">-</span></div>
                <div><strong>Category:</strong> <span id="fdCategory">-</span></div>
                <div><strong>Location:</strong> <span id="fdLocation">-</span></div>
                <div><strong>Status:</strong> <span id="fdStatus">-</span></div>
                <div>
                    <strong>Description:</strong>
                    <pre id="fdDescription" style="margin:8px 0 0;padding:10px;border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff;white-space:pre-wrap;word-break:break-word;max-height:320px;overflow:auto;">-</pre>
                </div>
                <div id="fdPhotoRow" style="display:none;">
                    <button type="button" id="fdViewPhotoBtn" class="ac-f84d9680">View Attached Photo</button>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="/user-dashboard/user-feedback.js?v=<?php echo filemtime(__DIR__ . '/user-feedback.js'); ?>"></script>
</body>
</html>









