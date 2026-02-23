<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.citizen_verification.manage', ['admin','department_admin','super_admin']);
$rbacAction = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? 'update_verification_status'
    : 'view_citizen_verification';
rbac_require_action_matrix(
    $rbacAction,
    [
        'view_citizen_verification' => 'admin.citizen_verification.manage',
        'update_verification_status' => 'admin.citizen_verification.manage',
    ],
    'admin.citizen_verification.manage'
);
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    die('Database connection failed: ' . ($db->connect_error ?? 'Unknown error'));
}

function cv_users_has_verification_status(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'verification_status'
         LIMIT 1"
    );
    if (!$stmt) return false;

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) $result->free();
    $stmt->close();
    return $exists;
}

$message = '';
$error = '';
$hasVerificationStatus = cv_users_has_verification_status($db);
$csrfToken = generate_csrf_token();
$selectedUserId = (int) ($_GET['selected_user'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasVerificationStatus) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');

        if ($userId <= 0 || !in_array($status, ['verified', 'rejected', 'pending'], true)) {
            $error = 'Invalid verification request.';
        } else {
            $stmt = $db->prepare('UPDATE users SET verification_status = ? WHERE id = ?');
            if (!$stmt) {
                $error = 'Unable to prepare update query.';
            } else {
                $stmt->bind_param('si', $status, $userId);
                if ($stmt->execute()) {
                    $message = 'Verification status updated.';
                    $selectedUserId = $userId;
                } else {
                    $error = 'Failed to update verification status.';
                }
                $stmt->close();
            }
        }
    }
}

$users = [];
$sql = $hasVerificationStatus
    ? "SELECT id, first_name, middle_name, last_name, suffix, email, mobile, birthdate, gender, civil_status, address, id_type, id_number, id_upload, verification_status, created_at FROM users ORDER BY created_at DESC"
    : "SELECT id, first_name, middle_name, last_name, suffix, email, mobile, birthdate, gender, civil_status, address, id_type, id_number, id_upload, created_at FROM users ORDER BY created_at DESC";
$res = $db->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $res->free();
}
$db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Citizen Verification - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-citizen-verification.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-citizen-verification.css'); ?>">
</head>
<body class="citizen-verification-page" data-selected-user="<?php echo (int) $selectedUserId; ?>">
<header class="nav" id="navbar">
    <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <div class="nav-logo">
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS</span>
    </div>
            <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item active"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
        <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Citizen Verification</h1>
        <p>Simple review queue. Select a citizen, inspect details, then approve or reject.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="recent-projects card">
        <div class="verify-toolbar">
            <input id="verifySearch" class="verify-input" type="search" placeholder="Search name, email, ID type, ID number">
            <select id="verifyStatus" class="verify-select">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="verify-layout">
            <div class="verify-list" id="verifyList">
                <?php if (count($users) === 0): ?>
                    <div class="empty-state">No citizen accounts found.</div>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $fullName = trim((string) (($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' . ($u['suffix'] ?? '')));
                        $status = strtolower((string) ($u['verification_status'] ?? 'pending'));
                        $badgeClass = $status === 'verified' ? 'approved' : ($status === 'rejected' ? 'cancelled' : 'pending');
                        ?>
                        <div class="verify-row"
                             data-row
                             data-user_id="<?php echo (int) $u['id']; ?>"
                             data-name="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>"
                             data-email="<?php echo htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-mobile="<?php echo htmlspecialchars((string) ($u['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-birthdate="<?php echo htmlspecialchars((string) ($u['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-gender="<?php echo htmlspecialchars((string) ($u['gender'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-civil_status="<?php echo htmlspecialchars((string) ($u['civil_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-address="<?php echo htmlspecialchars((string) ($u['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-id_type="<?php echo htmlspecialchars(strtoupper((string) ($u['id_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                             data-id_number="<?php echo htmlspecialchars(strtoupper((string) ($u['id_number'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                             data-id_upload="<?php echo htmlspecialchars((string) ($u['id_upload'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                             data-status="<?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>"
                             data-status_key="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                             data-status_class="<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>"
                             data-created="<?php echo !empty($u['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?>"
                        >
                            <div class="verify-row-top">
                                <span class="verify-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="verify-date"><?php echo !empty($u['created_at']) ? htmlspecialchars(date('M d', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?></span>
                            </div>
                            <div class="verify-meta">
                                <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><?php echo htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="verify-sub"><?php echo htmlspecialchars(strtoupper((string) ($u['id_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(strtoupper((string) ($u['id_number'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="verify-detail">
                <div class="verify-detail-head">
                    <h3 id="vmName" style="margin:0;">Select a citizen</h3>
                    <span class="status-badge pending" id="vmStatusBadge">Pending</span>
                </div>

                <div class="verify-grid">
                    <div class="verify-field"><label>Email</label><div id="vmEmail">-</div></div>
                    <div class="verify-field"><label>Mobile</label><div id="vmMobile">-</div></div>
                    <div class="verify-field"><label>Birthdate</label><div id="vmBirthdate">-</div></div>
                    <div class="verify-field"><label>Gender</label><div id="vmGender">-</div></div>
                    <div class="verify-field"><label>Civil Status</label><div id="vmCivilStatus">-</div></div>
                    <div class="verify-field"><label>Address</label><div id="vmAddress">-</div></div>
                    <div class="verify-field"><label>ID Type</label><div id="vmIdType">-</div></div>
                    <div class="verify-field"><label>ID Number</label><div id="vmIdNumber">-</div></div>
                    <div class="verify-field"><label>Registered</label><div id="vmCreated">-</div></div>
                </div>

                <div class="verify-id-preview">
                    <div id="vmIdEmpty">No uploaded ID file.</div>
                    <div id="vmIdDual" class="verify-id-dual" style="display:none;">
                        <img id="vmIdFrontImage" alt="Citizen ID Front" style="display:none;">
                        <img id="vmIdBackImage" alt="Citizen ID Back" style="display:none;">
                    </div>
                    <img id="vmIdImage" alt="Citizen ID Preview" style="display:none;">
                    <iframe id="vmIdPdf" title="Citizen ID PDF" style="display:none;"></iframe>
                </div>

                <?php if ($hasVerificationStatus): ?>
                    <form method="post" id="verifyActionForm" class="verify-actions">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="user_id" id="vmUserId" value="0">
                        <button class="btn btn-primary" type="submit" name="status" value="verified">Approve</button>
                        <button class="btn btn-danger" type="submit" name="status" value="rejected">Reject</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
<script src="../assets/js/admin-citizen-verification.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-citizen-verification.js'); ?>"></script>
</body>
</html>


