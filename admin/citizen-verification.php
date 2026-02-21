<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
set_no_cache_headers(); check_auth(); check_suspicious_activity();
if (!isset($db) || $db->connect_error) { die('Database connection failed'); }

function col_exists(mysqli $db, string $table, string $col): bool {
  $s=$db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if(!$s) return false; $s->bind_param('ss',$table,$col); $s->execute(); $r=$s->get_result(); $ok=$r&&$r->num_rows>0; if($r)$r->free(); $s->close(); return $ok;
}
function ensure_schema(mysqli $db): void {
  if(!col_exists($db,'users','verification_updated_at')) $db->query("ALTER TABLE users ADD COLUMN verification_updated_at DATETIME NULL AFTER verification_status");
  if(!col_exists($db,'users','verification_updated_by')) $db->query("ALTER TABLE users ADD COLUMN verification_updated_by INT NULL AFTER verification_updated_at");
  if(!col_exists($db,'users','verification_reject_reason')) $db->query("ALTER TABLE users ADD COLUMN verification_reject_reason VARCHAR(500) NULL AFTER verification_updated_by");
  $db->query("CREATE TABLE IF NOT EXISTS verification_audit_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,previous_status VARCHAR(20) NOT NULL,new_status VARCHAR(20) NOT NULL,reason VARCHAR(500) NULL,acted_by INT NULL,acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_user(user_id),INDEX idx_acted(acted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function actor_id(): int { return (int)($_SESSION['employee_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0); }
function apply_status(mysqli $db,int $uid,string $status,string $reason,int $actor,string &$err): bool {
  if($uid<=0||!in_array($status,['verified','rejected','pending'],true)){ $err='Invalid verification request.'; return false; }
  if($status==='rejected'&&trim($reason)===''){ $err='Reject reason is required.'; return false; }
  $db->begin_transaction();
  try {
    $p=$db->prepare('SELECT verification_status FROM users WHERE id=? LIMIT 1'); if(!$p) throw new RuntimeException('Unable to load user.');
    $p->bind_param('i',$uid); $p->execute(); $pr=$p->get_result(); $row=$pr?$pr->fetch_assoc():null; if($pr)$pr->free(); $p->close(); if(!$row) throw new RuntimeException('Citizen not found.');
    $prev=strtolower((string)($row['verification_status']??'pending'));
    $reasonDb=$status==='rejected'?trim($reason):null;
    $u=$db->prepare('UPDATE users SET verification_status=?,verification_updated_at=NOW(),verification_updated_by=?,verification_reject_reason=? WHERE id=?');
    if(!$u) throw new RuntimeException('Unable to prepare update.');
    $u->bind_param('sisi',$status,$actor,$reasonDb,$uid); if(!$u->execute()){ $u->close(); throw new RuntimeException('Update failed.'); } $u->close();
    $l=$db->prepare('INSERT INTO verification_audit_logs (user_id,previous_status,new_status,reason,acted_by) VALUES (?,?,?,?,?)');
    if($l){ $l->bind_param('isssi',$uid,$prev,$status,$reasonDb,$actor); $l->execute(); $l->close(); }
    $db->commit(); return true;
  } catch(Throwable $e){ $db->rollback(); $err=$e->getMessage(); return false; }
}
function dups(mysqli $db,string $field): array {
  $map=[]; $res=$db->query("SELECT LOWER(TRIM($field)) k,COUNT(*) c FROM users WHERE COALESCE(TRIM($field),'')<>'' GROUP BY LOWER(TRIM($field)) HAVING COUNT(*)>1");
  if($res){ while($r=$res->fetch_assoc()){ $k=(string)($r['k']??''); if($k!=='') $map[$k]=(int)($r['c']??0);} $res->free(); } return $map;
}
ensure_schema($db); $hasStatus=col_exists($db,'users','verification_status'); $csrfToken=generate_csrf_token(); $message=''; $error=''; $selectedUserId=(int)($_GET['selected_user']??0);
if($_SERVER['REQUEST_METHOD']==='POST' && $hasStatus){
  if(!verify_csrf_token($_POST['csrf_token']??'')){ $error='Invalid request token.'; }
  else {
    $scope=(string)($_POST['action_scope']??'single'); $status=(string)($_POST['status']??''); $reason=trim((string)($_POST['reject_reason']??'')); $actor=actor_id();
    if($scope==='batch'){
      $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',(string)($_POST['batch_user_ids']??''))),fn($n)=>$n>0))); $ids=array_slice($ids,0,200);
      if(!$ids) $error='Please select at least one citizen.'; else { $ok=0; foreach($ids as $id){ $e=''; if(apply_status($db,$id,$status,$reason,$actor,$e)) $ok++; else { $error=$e; break; } }
      if($error===''){ $message='Updated verification status for '.$ok.' citizen(s).'; $selectedUserId=(int)$ids[0]; }}
    } else {
      $uid=(int)($_POST['user_id']??0); if(apply_status($db,$uid,$status,$reason,$actor,$error)){ $message='Verification status updated.'; $selectedUserId=$uid; }
    }
  }
}
$users=[]; $res=$db->query("SELECT id,first_name,middle_name,last_name,suffix,email,mobile,birthdate,gender,civil_status,address,id_type,id_number,id_upload,verification_status,verification_updated_at,verification_reject_reason,created_at FROM users ORDER BY created_at DESC");
if($res){ while($row=$res->fetch_assoc()) $users[]=$row; $res->free(); }
$dupId=dups($db,'id_number'); $dupEmail=dups($db,'email'); $dupMobile=dups($db,'mobile'); $db->close();
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
        .citizen-table-wrap { overflow-x: auto; width: 100%; }
        .citizen-table-wrap .projects-table { min-width: 1100px; }
        @media (max-width: 860px) { .verify-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
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
        <a href="project_registration.php"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
        <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
        <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
        <a href="contractors.php"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers</a>
        <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
        <div class="nav-item-group">
            <a href="settings.php" class="nav-main-item active"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
            <div class="nav-submenu" id="userSubmenu">
                <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                <a href="citizen-verification.php" class="nav-submenu-item active"><span class="submenu-icon">ID</span><span>Citizen Verification</span></a>
            </div>
        </div>
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
<div class="verify-mail-detail"><div class="verify-detail-header"><div><h3 id="vmName">Select a citizen</h3><div class="ac-muted" id="vmTimeline">Last action: -</div></div><span class="status-badge pending" id="vmStatusBadge">Pending</span></div><div id="vmWarnings" class="warnings"></div><div class="verify-grid"><div class="verify-field"><label>Email</label><div id="vmEmail">-</div></div><div class="verify-field"><label>Mobile</label><div id="vmMobile">-</div></div><div class="verify-field"><label>Birthdate</label><div id="vmBirthdate">-</div></div><div class="verify-field"><label>Gender</label><div id="vmGender">-</div></div><div class="verify-field"><label>Civil Status</label><div id="vmCivilStatus">-</div></div><div class="verify-field"><label>Address</label><div id="vmAddress">-</div></div><div class="verify-field"><label>ID Type</label><div id="vmIdType">-</div></div><div class="verify-field"><label>ID Number</label><div id="vmIdNumber">-</div></div><div class="verify-field"><label>Registered</label><div id="vmCreated">-</div></div></div><div class="verify-id-preview"><div id="vmIdPreviewEmpty">No uploaded ID file.</div><img id="vmIdImage" alt="Citizen ID Preview" style="display:none;"><iframe id="vmIdPdf" title="Citizen ID PDF" style="display:none;"></iframe></div>
<form method="post" id="singleActionForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8'); ?>"><input type="hidden" name="action_scope" value="single"><input type="hidden" name="user_id" id="singleUserId" value="0"><input type="hidden" name="status" id="singleStatus" value=""><div class="verify-detail-actions"><div class="reject-box"><label class="ac-muted" for="rejectReason">Reject reason (required for reject)</label><textarea id="rejectReason" name="reject_reason" placeholder="Enter reason for rejection..."></textarea></div><div><button type="button" class="btn btn-primary" id="approveBtn">Approve</button> <button type="button" class="btn btn-danger" id="rejectBtn">Reject</button></div></div></form>
</div></div></div></section>
<form method="post" id="batchActionForm" style="display:none;"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8'); ?>"><input type="hidden" name="action_scope" value="batch"><input type="hidden" name="status" id="batchStatus"><input type="hidden" name="batch_user_ids" id="batchUserIds"><input type="hidden" name="reject_reason" id="batchRejectReason"></form>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script><script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
<script>
(function(){
var rows=[].slice.call(document.querySelectorAll('[data-citizen-row]')), vmWarnings=document.getElementById('vmWarnings'), vmStatusBadge=document.getElementById('vmStatusBadge'), vmTimeline=document.getElementById('vmTimeline'), vmIdImage=document.getElementById('vmIdImage'), vmIdPdf=document.getElementById('vmIdPdf'), vmIdPreviewEmpty=document.getElementById('vmIdPreviewEmpty'), singleUserId=document.getElementById('singleUserId'), singleStatus=document.getElementById('singleStatus'), rejectReason=document.getElementById('rejectReason'), searchInput=document.getElementById('citizenSearchInput'), statusFilter=document.getElementById('statusFilter'), sortOrder=document.getElementById('sortOrder'), selectAllVisible=document.getElementById('selectAllVisible');
function setText(id,v){var el=document.getElementById(id); if(el) el.textContent=v&&v!==''?v:'-';}
function openRow(row){if(!row)return; rows.forEach(function(r){r.classList.remove('active');}); row.classList.add('active'); setText('vmName',row.getAttribute('data-name')); setText('vmEmail',row.getAttribute('data-email')); setText('vmMobile',row.getAttribute('data-mobile')); setText('vmBirthdate',row.getAttribute('data-birthdate')); setText('vmGender',row.getAttribute('data-gender')); setText('vmCivilStatus',row.getAttribute('data-civil_status')); setText('vmAddress',row.getAttribute('data-address')); setText('vmIdType',row.getAttribute('data-id_type')); setText('vmIdNumber',row.getAttribute('data-id_number')); setText('vmCreated',row.getAttribute('data-created')); if(singleUserId) singleUserId.value=row.getAttribute('data-user_id')||'0'; if(vmStatusBadge){vmStatusBadge.className='status-badge '+(row.getAttribute('data-status_class')||'pending'); vmStatusBadge.textContent=row.getAttribute('data-status')||'Pending';} if(vmTimeline) vmTimeline.textContent='Last action: '+(row.getAttribute('data-ver-updated')||'-'); var w=row.getAttribute('data-warning')||''; if(vmWarnings){vmWarnings.textContent=w; vmWarnings.style.display=w?'block':'none';}
if(vmIdImage){vmIdImage.style.display='none';vmIdImage.src='';} if(vmIdPdf){vmIdPdf.style.display='none';vmIdPdf.src='';} var hasId=row.getAttribute('data-has_id')==='1'; if(vmIdPreviewEmpty) vmIdPreviewEmpty.style.display=hasId?'none':'block'; if(hasId){var url='user-id-file.php?user_id='+encodeURIComponent(row.getAttribute('data-user_id')||'0'); if(vmIdImage){vmIdImage.src=url; vmIdImage.style.display='block';}}
}
function visibleRows(){return rows.filter(function(r){return r.style.display!=='none';});}
function applySort(){var list=document.getElementById('verifyMailList'); if(!list) return; var mode=sortOrder?sortOrder.value:'pending_first'; rows.slice().sort(function(a,b){var an=(a.getAttribute('data-name')||'').toLowerCase(), bn=(b.getAttribute('data-name')||'').toLowerCase(), at=Number(a.getAttribute('data-created_ts')||0), bt=Number(b.getAttribute('data-created_ts')||0), as=(a.getAttribute('data-status_key')||''), bs=(b.getAttribute('data-status_key')||''); if(mode==='name_az') return an.localeCompare(bn); if(mode==='oldest') return at-bt; if(mode==='newest') return bt-at; if(as==='pending'&&bs!=='pending') return -1; if(as!=='pending'&&bs==='pending') return 1; return bt-at;}).forEach(function(row){list.appendChild(row);});}
function applyFilters(){var q=(searchInput&&searchInput.value?searchInput.value:'').toLowerCase().trim(), sf=statusFilter?statusFilter.value:'all'; rows.forEach(function(row){var t=(row.textContent||'').toLowerCase(), st=(row.getAttribute('data-status_key')||''), hasId=row.getAttribute('data-has_id')==='1'; var ok=sf==='all'||(sf==='no-id'?!hasId:st===sf); row.style.display=ok&&(q===''||t.indexOf(q)!==-1)?'':'none';}); applySort(); var a=document.querySelector('[data-citizen-row].active'); if(!a||a.style.display==='none') openRow(visibleRows()[0]||null);}
rows.forEach(function(row){row.addEventListener('click',function(e){if(e.target&&e.target.closest('.row-check')) return; openRow(row);});}); if(searchInput)searchInput.addEventListener('input',applyFilters); if(statusFilter)statusFilter.addEventListener('change',applyFilters); if(sortOrder)sortOrder.addEventListener('change',applyFilters);
if(selectAllVisible){selectAllVisible.addEventListener('change',function(){var checked=!!selectAllVisible.checked; visibleRows().forEach(function(row){var cb=row.querySelector('.row-check'); if(cb) cb.checked=checked;});});}
var approveBtn=document.getElementById('approveBtn'), rejectBtn=document.getElementById('rejectBtn'), singleForm=document.getElementById('singleActionForm');
if(approveBtn&&singleForm) approveBtn.addEventListener('click',function(){if(!singleUserId||singleUserId.value==='0')return; if(!confirm('Approve this citizen ID?'))return; singleStatus.value='verified'; singleForm.submit();});
if(rejectBtn&&singleForm) rejectBtn.addEventListener('click',function(){if(!singleUserId||singleUserId.value==='0')return; var r=(rejectReason&&rejectReason.value?rejectReason.value:'').trim(); if(r==='') return alert('Reject reason is required.'); if(!confirm('Reject this citizen ID?'))return; singleStatus.value='rejected'; singleForm.submit();});
function selectedIds(){return [].slice.call(document.querySelectorAll('.row-check:checked')).map(function(cb){return cb.value;});}
var batchForm=document.getElementById('batchActionForm'), batchStatus=document.getElementById('batchStatus'), batchUserIds=document.getElementById('batchUserIds'), batchRejectReason=document.getElementById('batchRejectReason'), batchApproveBtn=document.getElementById('batchApproveBtn'), batchRejectBtn=document.getElementById('batchRejectBtn');
if(batchApproveBtn&&batchForm) batchApproveBtn.addEventListener('click',function(){var ids=selectedIds(); if(!ids.length) return alert('Select at least one citizen first.'); if(!confirm('Approve selected IDs?')) return; batchStatus.value='verified'; batchUserIds.value=ids.join(','); batchRejectReason.value=''; batchForm.submit();});
if(batchRejectBtn&&batchForm) batchRejectBtn.addEventListener('click',function(){var ids=selectedIds(); if(!ids.length) return alert('Select at least one citizen first.'); var reason=prompt('Enter reject reason for selected citizens:'); if(reason===null) return; reason=reason.trim(); if(reason==='') return alert('Reject reason is required.'); if(!confirm('Reject selected IDs?')) return; batchStatus.value='rejected'; batchUserIds.value=ids.join(','); batchRejectReason.value=reason; batchForm.submit();});
applyFilters(); var selectedId=<?php echo (int)$selectedUserId; ?>; if(selectedId>0){var chosen=document.querySelector('[data-citizen-row][data-user_id="'+String(selectedId)+'"]'); if(chosen) openRow(chosen);} else {var firstPending=rows.find(function(r){return (r.getAttribute('data-status_key')||'')==='pending'&&r.style.display!=='none';}); openRow(firstPending||visibleRows()[0]||null);} 
})();
</script>
</body>
</html>
