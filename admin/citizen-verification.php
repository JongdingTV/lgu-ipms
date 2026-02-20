<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
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

$message = '';
$error = '';
$hasVerificationStatus = cv_users_has_verification_status($db);
$csrfToken = generate_csrf_token();

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
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

$db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Citizen Verification - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <style>
        .verify-actions { display:flex; gap:8px; align-items:center; }
        .verify-actions form { margin:0; }
        .verify-actions .btn { min-height:34px; padding:0 10px; border-radius:8px; }
        .verify-actions .btn-view { background:#1e40af; color:#fff; border:1px solid #1e40af; }
        .verify-modal-backdrop { position:fixed; inset:0; background:rgba(2,6,23,.62); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .verify-modal { width:min(960px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:14px; border:1px solid #dbe7f3; box-shadow:0 20px 48px rgba(15,23,42,.35); }
        .verify-modal-head { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid #e2e8f0; }
        .verify-modal-body { padding:14px; display:grid; gap:14px; }
        .verify-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .verify-field { border:1px solid #dbe7f3; border-radius:10px; background:#f8fbff; padding:10px; }
        .verify-field label { display:block; font-size:.74rem; color:#475569; font-weight:700; text-transform:uppercase; margin-bottom:4px; }
        .verify-field div { color:#0f172a; font-weight:600; word-break:break-word; }
        .verify-id-preview { border:1px solid #dbe7f3; border-radius:10px; background:#fff; padding:8px; min-height:280px; }
        .verify-id-preview img { max-width:100%; max-height:70vh; display:block; margin:0 auto; border-radius:8px; }
        .verify-id-preview iframe { width:100%; height:70vh; border:0; border-radius:8px; }
        .citizen-table-wrap { overflow-x: auto; width: 100%; max-width: 100%; }
        .citizen-table-wrap .projects-table { min-width: 840px; width: 100%; table-layout: auto; }
        @media (max-width: 860px) { .verify-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body class="citizen-verification-page">
<header class="nav" id="navbar">
    <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>
    <div class="nav-logo">
        <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS</span>
    </div>
    <div class="nav-links">
        <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
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
            <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu" id="contractorsSubmenu">
                <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
                <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
            </div>
        </div>
        <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
        <a href="citizen-verification.php" class="nav-citizen-verification active"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Citizen Verification</h1>
        <p>Review uploaded IDs and approve or reject citizen accounts.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!$hasVerificationStatus): ?>
        <div class="alert alert-danger">Missing column: <code>verification_status</code>. Run database update first.</div>
    <?php endif; ?>

    <div class="recent-projects card">
        <h3>Registered Citizens</h3>
        <div class="table-wrap dashboard-table-wrap citizen-table-wrap">
            <table class="projects-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>ID Type</th>
                    <th>ID Number</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $fullName = trim((string) (($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? '')));
                        $status = strtolower((string) ($u['verification_status'] ?? 'pending'));
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper((string) ($u['id_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper((string) ($u['id_number'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="status-badge <?php echo $status === 'verified' ? 'approved' : ($status === 'rejected' ? 'cancelled' : 'pending'); ?>"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo !empty($u['created_at']) ? htmlspecialchars(date('M d, Y', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                            <td>
                                <?php if ($hasVerificationStatus): ?>
                                    <div class="verify-actions">
                                        <button
                                            class="btn btn-view"
                                            type="button"
                                            data-open-user
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
                                            data-created="<?php echo !empty($u['created_at']) ? htmlspecialchars(date('M d, Y h:i A', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?>"
                                        >View Details</button>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                            <button class="btn btn-primary" type="submit" name="status" value="verified">Approve</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                            <button class="btn btn-danger" type="submit" name="status" value="rejected">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="ac-a004b216">No citizen accounts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div id="userVerifyModalBackdrop" class="verify-modal-backdrop">
    <div class="verify-modal" role="dialog" aria-modal="true" aria-labelledby="verifyModalTitle">
        <div class="verify-modal-head">
            <h3 id="verifyModalTitle">Citizen Information</h3>
            <button type="button" id="closeUserVerifyModal" class="btn btn-secondary">Close</button>
        </div>
        <div class="verify-modal-body">
            <div class="verify-grid">
                <div class="verify-field"><label>Full Name</label><div id="vmName">-</div></div>
                <div class="verify-field"><label>Email</label><div id="vmEmail">-</div></div>
                <div class="verify-field"><label>Mobile</label><div id="vmMobile">-</div></div>
                <div class="verify-field"><label>Birthdate</label><div id="vmBirthdate">-</div></div>
                <div class="verify-field"><label>Gender</label><div id="vmGender">-</div></div>
                <div class="verify-field"><label>Civil Status</label><div id="vmCivilStatus">-</div></div>
                <div class="verify-field"><label>Address</label><div id="vmAddress">-</div></div>
                <div class="verify-field"><label>Status</label><div id="vmStatus">-</div></div>
                <div class="verify-field"><label>ID Type</label><div id="vmIdType">-</div></div>
                <div class="verify-field"><label>ID Number</label><div id="vmIdNumber">-</div></div>
                <div class="verify-field"><label>Registered</label><div id="vmCreated">-</div></div>
            </div>
            <div class="verify-id-preview" id="vmIdPreviewWrap">
                <div id="vmIdPreviewEmpty">No uploaded ID file.</div>
                <img id="vmIdImage" alt="Citizen ID Preview" style="display:none;">
                <iframe id="vmIdPdf" title="Citizen ID PDF" style="display:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
<script>
(function () {
    var backdrop = document.getElementById('userVerifyModalBackdrop');
    var closeBtn = document.getElementById('closeUserVerifyModal');
    var imageEl = document.getElementById('vmIdImage');
    var pdfEl = document.getElementById('vmIdPdf');
    var emptyEl = document.getElementById('vmIdPreviewEmpty');

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val && val !== '' ? val : '-';
    }

    function closeModal() {
        if (!backdrop) return;
        backdrop.style.display = 'none';
        if (imageEl) { imageEl.src = ''; imageEl.style.display = 'none'; }
        if (pdfEl) { pdfEl.src = ''; pdfEl.style.display = 'none'; }
        if (emptyEl) emptyEl.style.display = 'block';
    }

    document.querySelectorAll('[data-open-user]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setText('vmName', btn.getAttribute('data-name') || '');
            setText('vmEmail', btn.getAttribute('data-email') || '');
            setText('vmMobile', btn.getAttribute('data-mobile') || '');
            setText('vmBirthdate', btn.getAttribute('data-birthdate') || '');
            setText('vmGender', btn.getAttribute('data-gender') || '');
            setText('vmCivilStatus', btn.getAttribute('data-civil_status') || '');
            setText('vmAddress', btn.getAttribute('data-address') || '');
            setText('vmStatus', btn.getAttribute('data-status') || '');
            setText('vmIdType', btn.getAttribute('data-id_type') || '');
            setText('vmIdNumber', btn.getAttribute('data-id_number') || '');
            setText('vmCreated', btn.getAttribute('data-created') || '');

            var idFile = btn.getAttribute('data-id_upload') || '';
            if (imageEl) imageEl.style.display = 'none';
            if (pdfEl) pdfEl.style.display = 'none';
            if (emptyEl) emptyEl.style.display = idFile ? 'none' : 'block';

            if (idFile) {
                var lower = idFile.toLowerCase();
                if (lower.endsWith('.pdf')) {
                    if (pdfEl) { pdfEl.src = idFile; pdfEl.style.display = 'block'; }
                } else {
                    if (imageEl) { imageEl.src = idFile; imageEl.style.display = 'block'; }
                }
            }

            if (backdrop) backdrop.style.display = 'flex';
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeModal();
        });
    }
})();
</script>
</body>
</html>

