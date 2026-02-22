<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['admin','department_admin','super_admin']);
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
    <style>
        body.citizen-verification-page,
        body.citizen-verification-page *:not(svg):not(path) { font-family:'Poppins',sans-serif !important; }

        .verify-toolbar { display:grid; grid-template-columns:minmax(220px,1.2fr) minmax(160px,.7fr); gap:10px; margin:8px 0 14px; }
        .verify-input, .verify-select { border:1px solid #cbd5e1; border-radius:10px; padding:10px 12px; font-size:.92rem; }
        .verify-layout { display:grid; grid-template-columns:minmax(320px,42%) minmax(460px,58%); gap:14px; min-height:70vh; }
        .verify-list { border:1px solid #dbe7f3; border-radius:12px; background:#fff; overflow:auto; }
        .verify-row { padding:12px; border-bottom:1px solid #eef2f7; cursor:pointer; }
        .verify-row:hover { background:#f8fbff; }
        .verify-row.active { background:#e8f0fe; border-left:4px solid #2563eb; padding-left:8px; }
        .verify-row-top { display:flex; justify-content:space-between; gap:10px; margin-bottom:4px; }
        .verify-name { font-weight:700; color:#0f172a; }
        .verify-date { font-size:.82rem; color:#64748b; white-space:nowrap; }
        .verify-meta { display:flex; gap:8px; align-items:center; color:#475569; font-size:.86rem; margin-bottom:3px; }
        .verify-sub { font-size:.84rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .verify-detail { border:1px solid #dbe7f3; border-radius:12px; background:#fff; padding:14px; overflow:auto; }
        .verify-detail-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:12px; }
        .verify-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .verify-field { border:1px solid #dbe7f3; border-radius:10px; background:#f8fbff; padding:10px; }
        .verify-field label { display:block; font-size:.74rem; color:#475569; font-weight:700; text-transform:uppercase; margin-bottom:4px; }
        .verify-field div { color:#0f172a; font-weight:600; word-break:break-word; }

        .verify-id-preview { margin-top:12px; border:1px solid #dbe7f3; border-radius:10px; background:#fff; padding:8px; min-height:260px; }
        .verify-id-preview img { max-width:100%; max-height:56vh; display:block; margin:0 auto; border-radius:8px; }
        .verify-id-preview iframe { width:100%; height:56vh; border:0; border-radius:8px; }
        .verify-actions { margin-top:12px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }

        .empty-state { padding:16px; color:#64748b; }

        @media (max-width: 1100px) { .verify-layout { grid-template-columns:1fr; min-height:auto; } }
        @media (max-width: 860px) { .verify-grid, .verify-toolbar { grid-template-columns:1fr; } }
    </style>
</head>
<body class="citizen-verification-page">
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
                    <a href="engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
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
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));
    var search = document.getElementById('verifySearch');
    var filter = document.getElementById('verifyStatus');
    var userIdInput = document.getElementById('vmUserId');
    var idImg = document.getElementById('vmIdImage');
    var idPdf = document.getElementById('vmIdPdf');
    var idEmpty = document.getElementById('vmIdEmpty');

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value && value !== '' ? value : '-';
    }

    function openRow(row) {
        if (!row) return;
        rows.forEach(function (r) { r.classList.remove('active'); });
        row.classList.add('active');

        setText('vmName', row.getAttribute('data-name'));
        setText('vmEmail', row.getAttribute('data-email'));
        setText('vmMobile', row.getAttribute('data-mobile'));
        setText('vmBirthdate', row.getAttribute('data-birthdate'));
        setText('vmGender', row.getAttribute('data-gender'));
        setText('vmCivilStatus', row.getAttribute('data-civil_status'));
        setText('vmAddress', row.getAttribute('data-address'));
        setText('vmIdType', row.getAttribute('data-id_type'));
        setText('vmIdNumber', row.getAttribute('data-id_number'));
        setText('vmCreated', row.getAttribute('data-created'));

        var badge = document.getElementById('vmStatusBadge');
        if (badge) {
            badge.textContent = row.getAttribute('data-status') || 'Pending';
            badge.className = 'status-badge ' + (row.getAttribute('data-status_class') || 'pending');
        }
        if (userIdInput) userIdInput.value = row.getAttribute('data-user_id') || '0';

        if (idImg) { idImg.src = ''; idImg.style.display = 'none'; }
        if (idPdf) { idPdf.src = ''; idPdf.style.display = 'none'; }

        var file = row.getAttribute('data-id_upload') || '';
        if (!file) {
            if (idEmpty) idEmpty.style.display = 'block';
            return;
        }
        if (idEmpty) idEmpty.style.display = 'none';

        var lower = file.toLowerCase();
        if (lower.endsWith('.pdf')) {
            if (idPdf) { idPdf.src = file; idPdf.style.display = 'block'; }
        } else {
            if (idImg) { idImg.src = file; idImg.style.display = 'block'; }
        }
    }

    function applyFilter() {
        var q = (search && search.value ? search.value : '').toLowerCase().trim();
        var s = filter ? filter.value : 'all';

        rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            var status = (row.getAttribute('data-status_key') || '').toLowerCase();
            var okStatus = s === 'all' || status === s;
            var okQuery = q === '' || text.indexOf(q) !== -1;
            row.style.display = okStatus && okQuery ? '' : 'none';
        });

        var active = document.querySelector('.verify-row.active');
        if (!active || active.style.display === 'none') {
            var firstVisible = rows.find(function (r) { return r.style.display !== 'none'; });
            if (firstVisible) openRow(firstVisible);
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () { openRow(row); });
    });
    if (search) search.addEventListener('input', applyFilter);
    if (filter) filter.addEventListener('change', applyFilter);

    var selectedId = <?php echo (int) $selectedUserId; ?>;
    if (selectedId > 0) {
        var selected = document.querySelector('[data-row][data-user_id="' + String(selectedId) + '"]');
        if (selected) openRow(selected);
    } else {
        var first = rows[0];
        if (first) openRow(first);
    }
})();
</script>
</body>
</html>


