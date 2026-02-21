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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Citizen Verification</title>
<link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>"><link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>"><link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
<style>
.verify-toolbar{display:grid;grid-template-columns:minmax(220px,1.2fr) repeat(3,minmax(140px,.7fr));gap:10px;margin:8px 0 14px}.verify-search,.verify-select{border:1px solid #cbd5e1;border-radius:999px;padding:10px 14px}.verify-mail-layout{display:grid;grid-template-columns:minmax(340px,42%) minmax(480px,58%);gap:14px;min-height:72vh}.verify-mail-list{border:1px solid #dbe7f3;border-radius:12px;overflow:auto;background:#fff}.verify-mail-row{display:grid;grid-template-columns:26px 1fr;gap:8px;padding:12px 14px;border-bottom:1px solid #edf2f7;cursor:pointer}.verify-mail-row.active{background:#e8f0fe;border-left:4px solid #2563eb;padding-left:10px}.verify-mail-content{display:grid;gap:4px}.verify-mail-top{display:flex;justify-content:space-between}.verify-mail-name{font-weight:700}.verify-mail-date{color:#64748b;font-size:.82rem}.verify-mail-meta{display:flex;gap:8px;align-items:center;color:#334155;font-size:.86rem}.verify-mail-subject{color:#475569;font-size:.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.verify-mail-detail{border:1px solid #dbe7f3;border-radius:12px;background:#fff;padding:14px;overflow:auto}.verify-detail-header{display:flex;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding-bottom:10px;margin-bottom:12px}.verify-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.verify-field{border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff;padding:10px}.verify-field label{display:block;font-size:.74rem;color:#475569;font-weight:700;text-transform:uppercase;margin-bottom:4px}.verify-id-preview{border:1px solid #dbe7f3;border-radius:10px;background:#fff;padding:8px;min-height:240px;margin-top:12px}.verify-id-preview img,.verify-id-preview iframe{max-width:100%;width:100%;max-height:52vh;border:0;border-radius:8px}.verify-detail-actions{display:flex;gap:10px;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;margin-top:12px}.reject-box{flex:1 1 320px}.reject-box textarea{width:100%;min-height:84px;border:1px solid #cbd5e1;border-radius:10px;padding:10px}.batch-bar{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff;margin-bottom:10px}.warnings{margin-top:10px;padding:10px;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:.86rem;display:none}.ac-muted{color:#64748b;font-size:.84rem}@media(max-width:1100px){.verify-mail-layout{grid-template-columns:1fr;min-height:auto}}@media(max-width:860px){.verify-grid,.verify-toolbar{grid-template-columns:1fr}}
</style></head><body>
<header class="nav" id="navbar"><button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button><div class="nav-logo"><img src="../logocityhall.png" alt="City Hall Logo" class="logo-img"><span class="logo-text">IPMS</span></div><div class="nav-links"><a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a><a href="project_registration.php"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration</a><a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a><a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a><a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a><a href="contractors.php"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers</a><a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a><div class="nav-item-group"><a href="settings.php" class="nav-main-item active"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">?</span></a><div class="nav-submenu" id="userSubmenu"><a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">??</span><span>Change Password</span></a><a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">??</span><span>Security Logs</span></a><a href="citizen-verification.php" class="nav-submenu-item active"><span class="submenu-icon">ID</span><span>Citizen Verification</span></a></div></div></div><div class="nav-divider"></div><div class="nav-action-footer"><a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div></header>
<section class="main-content"><div class="dash-header"><h1>Citizen Verification</h1><p>Gmail-style queue with audit trail, filters, sorting, batch actions, and secure preview.</p></div><?php if($message!==''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?><?php if($error!==''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?>
<div class="recent-projects card"><div class="batch-bar"><strong>Verification Queue</strong><div><button type="button" class="btn btn-secondary" id="batchApproveBtn">Approve Selected</button> <button type="button" class="btn btn-danger" id="batchRejectBtn">Reject Selected</button></div></div>
<div class="verify-toolbar"><input type="search" id="citizenSearchInput" class="verify-search" placeholder="Search name, email, ID type, ID number..."><select id="statusFilter" class="verify-select"><option value="all">All statuses</option><option value="pending">Pending</option><option value="verified">Verified</option><option value="rejected">Rejected</option><option value="no-id">No ID upload</option></select><select id="sortOrder" class="verify-select"><option value="pending_first">Pending first</option><option value="newest">Newest</option><option value="oldest">Oldest</option><option value="name_az">Name A-Z</option></select><label class="ac-muted"><input type="checkbox" id="selectAllVisible"> Select visible</label></div>
<div class="verify-mail-layout"><div class="verify-mail-list" id="verifyMailList">
<?php if(count($users)>0): foreach($users as $u): $fullName=trim((string)(($u['first_name']??'').' '.($u['middle_name']??'').' '.($u['last_name']??'').' '.($u['suffix']??''))); $status=strtolower((string)($u['verification_status']??'pending')); $badgeClass=$status==='verified'?'approved':($status==='rejected'?'cancelled':'pending'); $w=[]; $k1=strtolower(trim((string)($u['id_number']??''))); $k2=strtolower(trim((string)($u['email']??''))); $k3=strtolower(trim((string)($u['mobile']??''))); if($k1!==''&&isset($dupId[$k1]))$w[]='Duplicate ID number ('.$dupId[$k1].' accounts)'; if($k2!==''&&isset($dupEmail[$k2]))$w[]='Duplicate email ('.$dupEmail[$k2].' accounts)'; if($k3!==''&&isset($dupMobile[$k3]))$w[]='Duplicate mobile ('.$dupMobile[$k3].' accounts)'; ?>
<div class="verify-mail-row" data-citizen-row data-user_id="<?php echo (int)$u['id']; ?>" data-name="<?php echo htmlspecialchars($fullName,ENT_QUOTES,'UTF-8'); ?>" data-email="<?php echo htmlspecialchars((string)($u['email']??''),ENT_QUOTES,'UTF-8'); ?>" data-mobile="<?php echo htmlspecialchars((string)($u['mobile']??''),ENT_QUOTES,'UTF-8'); ?>" data-birthdate="<?php echo htmlspecialchars((string)($u['birthdate']??''),ENT_QUOTES,'UTF-8'); ?>" data-gender="<?php echo htmlspecialchars((string)($u['gender']??''),ENT_QUOTES,'UTF-8'); ?>" data-civil_status="<?php echo htmlspecialchars((string)($u['civil_status']??''),ENT_QUOTES,'UTF-8'); ?>" data-address="<?php echo htmlspecialchars((string)($u['address']??''),ENT_QUOTES,'UTF-8'); ?>" data-id_type="<?php echo htmlspecialchars(strtoupper((string)($u['id_type']??'')),ENT_QUOTES,'UTF-8'); ?>" data-id_number="<?php echo htmlspecialchars(strtoupper((string)($u['id_number']??'')),ENT_QUOTES,'UTF-8'); ?>" data-has_id="<?php echo !empty($u['id_upload'])?'1':'0'; ?>" data-status="<?php echo htmlspecialchars(ucfirst($status),ENT_QUOTES,'UTF-8'); ?>" data-status_key="<?php echo htmlspecialchars($status,ENT_QUOTES,'UTF-8'); ?>" data-status_class="<?php echo htmlspecialchars($badgeClass,ENT_QUOTES,'UTF-8'); ?>" data-created="<?php echo !empty($u['created_at'])?htmlspecialchars(date('M d, Y h:i A',strtotime((string)$u['created_at'])),ENT_QUOTES,'UTF-8'):'-'; ?>" data-created_ts="<?php echo !empty($u['created_at'])?(int)strtotime((string)$u['created_at']):0; ?>" data-ver-updated="<?php echo !empty($u['verification_updated_at'])?htmlspecialchars(date('M d, Y h:i A',strtotime((string)$u['verification_updated_at'])),ENT_QUOTES,'UTF-8'):'-'; ?>" data-warning="<?php echo htmlspecialchars(implode(' | ',$w),ENT_QUOTES,'UTF-8'); ?>"><div><input type="checkbox" class="row-check" value="<?php echo (int)$u['id']; ?>"></div><div class="verify-mail-content"><div class="verify-mail-top"><span class="verify-mail-name"><?php echo htmlspecialchars($fullName,ENT_QUOTES,'UTF-8'); ?></span><span class="verify-mail-date"><?php echo !empty($u['created_at'])?htmlspecialchars(date('M d',strtotime((string)$u['created_at'])),ENT_QUOTES,'UTF-8'):'-'; ?></span></div><div class="verify-mail-meta"><span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($status),ENT_QUOTES,'UTF-8'); ?></span><span><?php echo htmlspecialchars((string)($u['email']??''),ENT_QUOTES,'UTF-8'); ?></span></div><div class="verify-mail-subject"><?php echo htmlspecialchars(strtoupper((string)($u['id_type']??'')),ENT_QUOTES,'UTF-8'); ?> • <?php echo htmlspecialchars(strtoupper((string)($u['id_number']??'')),ENT_QUOTES,'UTF-8'); ?></div></div></div>
<?php endforeach; else: ?><div class="verify-mail-row"><div></div><div>No citizen accounts found.</div></div><?php endif; ?>
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
</script></body></html>
