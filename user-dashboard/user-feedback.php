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
$userInitials = user_avatar_initials($userName);
$avatarColor = user_avatar_color($userEmail !== '' ? $userEmail : $userName);
$profileImageWebPath = user_profile_photo_web_path($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    header('Content-Type: application/json');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token. Please refresh and try again.']);
        $db->close();
        exit;
    }

    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['feedback'] ?? '');
    $gpsLat = trim($_POST['gps_lat'] ?? '');
    $gpsLng = trim($_POST['gps_lng'] ?? '');
    $gpsAccuracy = trim($_POST['gps_accuracy'] ?? '');
    $gpsMapUrl = trim($_POST['gps_map_url'] ?? '');
    $gpsAddress = trim($_POST['gps_address'] ?? '');
    $status = 'Pending';

    if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Photo upload failed. Please try another image.']);
            $db->close();
            exit;
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = (string) ($_FILES['photo']['name'] ?? '');
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $tmpName = (string) ($_FILES['photo']['tmp_name'] ?? '');
        $size = (int) ($_FILES['photo']['size'] ?? 0);

        if (!in_array($ext, $allowedExt, true) || $size <= 0 || $size > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Photo must be JPG, PNG, or WEBP and up to 5MB only.']);
            $db->close();
            exit;
        }

        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmpName) : '';
        if ($mime !== '' && strpos($mime, 'image/') !== 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid photo file type.']);
            $db->close();
            exit;
        }

        $uploadDir = dirname(__DIR__) . '/uploads/feedback';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Unable to prepare upload directory.']);
            $db->close();
            exit;
        }

        $savedName = 'feedback_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $savedPath = $uploadDir . '/' . $savedName;
        if (!move_uploaded_file($tmpName, $savedPath)) {
            echo json_encode(['success' => false, 'message' => 'Unable to save uploaded photo.']);
            $db->close();
            exit;
        }

        $photoWebPath = '/uploads/feedback/' . $savedName;
        $description .= "\n\n[Photo Attachment] " . $photoWebPath;
    }

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

    $stmt = $db->prepare('INSERT INTO feedback (user_name, subject, category, location, description, status) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Unable to submit feedback right now.']);
        $db->close();
        exit;
    }

    $stmt->bind_param('ssssss', $userName, $subject, $category, $location, $description, $status);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Feedback submitted successfully.' : 'Failed to submit feedback.'
    ]);

    $db->close();
    exit;
}

$listStmt = $db->prepare('SELECT subject, category, location, description, status, date_submitted FROM feedback WHERE user_name = ? ORDER BY date_submitted DESC LIMIT 20');
$listStmt->bind_param('s', $userName);
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
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
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
                        <label for="feedback">Suggestion / Concern</label>
                        <textarea id="feedback" name="feedback" rows="5" required></textarea>
                    </div>
                    <div class="full">
                        <label for="photo">Upload a Photo (Optional)</label>
                        <div id="feedbackPhotoRow" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" style="flex:1 1 280px;">
                            <button type="button" id="removePhotoBtn" class="ac-f84d9680">Remove Photo</button>
                        </div>
                        <small id="photoStatus" style="display:block;margin-top:6px;color:#64748b;">No photo selected.</small>
                    </div>
                    <div class="full">
                        <label>Map Pinpoint</label>
                        <div id="feedbackMapControls" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
                            <input type="text" id="mapSearchInput" placeholder="Search place or address" style="flex:1 1 320px;">
                            <button type="button" id="mapSearchBtn" class="ac-f84d9680">Search</button>
                            <button type="button" id="gpsPinBtn" class="ac-f84d9680">Use Current Location (GPS)</button>
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
                    <button type="submit" class="ac-f84d9680">Submit Feedback</button>
                </div>
                <div id="message" style="display:none;margin-top:10px;"></div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom:12px;">Your Submissions</h3>
            <div class="table-wrap">
                <table class="projects-table" id="feedbackHistoryList">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($feedbackRows && $feedbackRows->num_rows > 0): ?>
                            <?php while ($row = $feedbackRows->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime((string) $row['date_submitted'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['subject']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['category']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['location']); ?></td>
                                    <td><span class="status-badge pending"><?php echo htmlspecialchars((string) $row['status']); ?></span></td>
                                    <?php $desc = (string) ($row['description'] ?? ''); $photoPath = ''; if (preg_match('/\[Photo Attachment\]\s+(\/uploads\/feedback\/[\w\-.]+\.((jpg)|(jpeg)|(png)|(webp)))/i', $desc, $m)) { $photoPath = $m[1]; } ?>
                                    <td>
                                        <?php if ($photoPath !== ''): ?>
                                            <button type="button" class="ac-f84d9680 feedback-photo-view-btn" data-photo-url="<?php echo htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8'); ?>">View Photo</button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="ac-a004b216">No feedback submitted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>


    <div id="feedbackPhotoModal" class="avatar-crop-modal" hidden>
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
    <script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="/assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="/user-dashboard/user-shell.js?v=<?php echo filemtime(__DIR__ . '/user-shell.js'); ?>"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="/user-dashboard/user-feedback.js?v=<?php echo filemtime(__DIR__ . '/user-feedback.js'); ?>"></script>
</body>
</html>






