<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.view', ['contractor','admin','super_admin']);
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /contractor/index.php');
    exit;
}
$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['contractor', 'admin', 'super_admin'], true)) {
    header('Location: /contractor/index.php');
    exit;
}
$canProgressSubmit = in_array($role, rbac_roles_for('contractor.progress.submit', ['contractor', 'admin', 'super_admin']), true);
$sidebarName = trim((string)($_SESSION['employee_name'] ?? 'Contractor'));
$sidebarInitial = strtoupper(substr($sidebarName !== '' ? $sidebarName : 'C', 0, 1));
$sidebarRoleLabel = ucwords(str_replace('_', ' ', (string)($_SESSION['employee_role'] ?? 'contractor')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Progress Monitoring - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
    <link rel="stylesheet" href="contractor-dashboard.css?v=<?php echo filemtime(__DIR__ . '/contractor-dashboard.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <style>
        .flow-note {
            margin: 0 0 14px;
            border: 1px solid #dbe7f3;
            background: #f8fbff;
            border-radius: 12px;
            padding: 12px 14px;
            color: #0f2a4a;
            font-size: .92rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="sidebar-toggle-wrapper">
    <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
</div>
<header class="nav" id="navbar">
    <div class="nav-logo">
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS Contractor</span>
    </div>
    <div class="nav-user-profile">
        <div class="user-initial-badge"><?php echo htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-name"><?php echo htmlspecialchars($sidebarName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-email"><?php echo htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <a href="dashboard.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Validation & Budget</a>
        <a href="progress_monitoring.php" class="active"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Progress Monitoring</a>
        <a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
    <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </a>
</header>
<div class="toggle-btn" id="showSidebarBtn">
    <a href="#" id="toggleSidebarShow" title="Show sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </a>
</div>

<section class="main-content">
    <div class="dash-header">
        <h1>Progress Monitoring</h1>
        <p>Update and review validated project progress history.</p>
    </div>
    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls">
                <div class="filter-group">
                    <label for="projectSelect">Project</label>
                    <select id="projectSelect" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>></select>
                </div>
                <div class="filter-group">
                    <label for="progressInput">Progress %</label>
                    <input id="progressInput" type="number" min="0" max="100" step="1" placeholder="0-100" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>
                </div>
                <div class="filter-group filter-group-wide">
                    <label for="workDetails">Work Details</label>
                    <textarea id="workDetails" rows="2" placeholder="What work was completed today?" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>></textarea>
                </div>
                <div class="filter-group filter-group-wide">
                    <label for="validationNotes">Validation Information</label>
                    <textarea id="validationNotes" rows="2" placeholder="Site notes, manpower, materials, milestones..." <?php echo $canProgressSubmit ? '' : 'disabled'; ?>></textarea>
                </div>
                <div class="filter-group">
                    <label for="proofImage">Proof Photo</label>
                    <input id="proofImage" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>
                </div>
                <button id="saveProgress" class="contractor-btn btn-success" type="button" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>Submit for Engineer Review</button>
            </div>
        </div>
        <?php if (!$canProgressSubmit): ?>
            <div class="flow-note"><strong>Read-only mode:</strong> Your role can view this page but cannot submit progress updates.</div>
        <?php endif; ?>
        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap module-grid">
            <h3 class="contractor-table-title">Progress History</h3>
            <p class="contractor-subtle">Flow: Contractor submits update with proof -> Engineer reviews -> Engineer approves -> Official progress is updated.</p>
            <table class="table">
                <thead><tr><th>Date</th><th>Progress</th><th>Status</th><th>Discrepancy</th><th>Updated By</th></tr></thead>
                <tbody id="historyBody"></tbody>
            </table>
        </div>
        <div class="table-wrap module-grid validation-items-section">
            <h3 class="contractor-table-title">Deliverable Validation</h3>
            <p class="contractor-subtle">Select a deliverable and submit or resubmit revision details for engineer validation.</p>
            <div class="validation-submit-grid">
                <div class="filter-group filter-group-wide">
                    <label for="validationItemSelect">Deliverable</label>
                    <select id="validationItemSelect" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>
                        <option value="">Select deliverable</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="validationProgressInput">Progress %</label>
                    <input id="validationProgressInput" type="number" min="0" max="100" step="1" placeholder="0-100" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>
                </div>
                <div class="filter-group filter-group-wide">
                    <label for="validationSummary">Change Summary</label>
                    <textarea id="validationSummary" rows="2" placeholder="Describe what changed for this deliverable..." <?php echo $canProgressSubmit ? '' : 'disabled'; ?>></textarea>
                </div>
                <div class="filter-group">
                    <label for="validationProofImage">Proof Photo (Optional)</label>
                    <input id="validationProofImage" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>
                </div>
                <button id="submitValidationItem" class="contractor-btn btn-primary" type="button" <?php echo $canProgressSubmit ? '' : 'disabled'; ?>>Submit Deliverable Update</button>
            </div>
            <table class="table">
                <thead><tr><th>Deliverable</th><th>Type</th><th>Status</th><th>Version</th><th>Progress</th><th>Last Submitted</th><th>Validator Remarks</th></tr></thead>
                <tbody id="validationItemsBody"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var csrf = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    var canProgressSubmit = <?php echo json_encode($canProgressSubmit); ?>;
    var preselectProjectId = <?php echo json_encode((string) ($_GET['project_id'] ?? '')); ?>;
    var projects = [];
    var validationItems = [];

    function apiGet(action, extra) {
        var q = '/contractor/api.php?action=' + encodeURIComponent(action) + (extra || '');
        return fetch(q, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function apiPost(action, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) { body.set(k, String(data[k])); });
        body.set('csrf_token', csrf);
        return fetch('/contractor/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    function apiPostForm(action, formData) {
        formData.append('csrf_token', csrf);
        return fetch('/contractor/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (r) { return r.json(); });
    }
    function msg(ok, t) {
        var box = document.getElementById('feedback');
        box.className = ok ? 'ac-0b2b14a3' : 'ac-aabba7cf';
        box.textContent = t;
    }
    function loadProjects() {
        return apiGet('load_projects').then(function (j) {
            projects = Array.isArray(j.data) ? j.data : [];
            var s = document.getElementById('projectSelect');
            s.innerHTML = '<option value="">Select project</option>';
            projects.forEach(function (p) {
                var o = document.createElement('option');
                o.value = p.id;
                o.textContent = p.code + ' - ' + p.name;
                s.appendChild(o);
            });
            if (preselectProjectId) {
                s.value = preselectProjectId;
                preselectProjectId = '';
                loadHistory();
            }
        });
    }
    function loadHistory() {
        var pid = document.getElementById('projectSelect').value;
        var tbody = document.getElementById('historyBody');
        tbody.innerHTML = '';
        if (!pid) return;
        apiGet('load_progress_history', '&project_id=' + encodeURIComponent(pid)).then(function (j) {
            var rows = Array.isArray(j.data) ? j.data : [];
            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                var status = r.review_status || 'Pending';
                var discrepancy = Number(r.discrepancy_flag || 0) === 1 ? ('Flagged: ' + (r.discrepancy_note || 'Needs review')) : 'None';
                tr.innerHTML = '<td>' + (r.created_at || '') + '</td><td>' + Number(r.progress_percent || 0).toFixed(2) + '%</td><td>' + status + '</td><td>' + discrepancy + '</td><td>' + (r.updated_by || '') + '</td>';
                tbody.appendChild(tr);
            });
        });
    }
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function statusClass(status) {
        var key = String(status || '').trim().toLowerCase();
        if (key === 'approved') return 'approved';
        if (key === 'submitted' || key === 'for approval' || key === 'for_approval') return 'submitted';
        if (key === 'rejected') return 'rejected';
        if (key === 'needs revision' || key === 'needs_revision' || key === 'returned') return 'revision';
        if (key === 'pending') return 'pending';
        return 'default';
    }
    function loadValidationItems() {
        var pid = document.getElementById('projectSelect').value;
        var tbody = document.getElementById('validationItemsBody');
        var select = document.getElementById('validationItemSelect');
        tbody.innerHTML = '';
        select.innerHTML = '<option value="">Select deliverable</option>';
        validationItems = [];
        if (!pid) {
            return;
        }
        apiGet('load_validation_items', '&project_id=' + encodeURIComponent(pid)).then(function (j) {
            var rows = Array.isArray(j.data) ? j.data : [];
            validationItems = rows;
            if (!rows.length) {
                var trEmpty = document.createElement('tr');
                trEmpty.innerHTML = '<td colspan="7">No deliverables available for this project yet.</td>';
                tbody.appendChild(trEmpty);
                return;
            }
            rows.forEach(function (r) {
                var option = document.createElement('option');
                option.value = r.id;
                option.textContent = (r.deliverable_name || 'Deliverable') + ' (' + (r.deliverable_type || 'Item') + ')';
                select.appendChild(option);

                var tr = document.createElement('tr');
                var currentStatus = r.current_status || 'Pending';
                tr.innerHTML = '<td>' + escapeHtml(r.deliverable_name || '') + '</td>'
                    + '<td>' + escapeHtml(r.deliverable_type || '') + '</td>'
                    + '<td><span class="status-chip validation-status-chip ' + statusClass(currentStatus) + '">' + escapeHtml(currentStatus) + '</span></td>'
                    + '<td>v' + Number(r.version_no || 0) + '</td>'
                    + '<td>' + Number(r.progress_percent || 0).toFixed(2) + '%</td>'
                    + '<td>' + escapeHtml(r.submitted_at || '-') + '</td>'
                    + '<td>' + escapeHtml(r.validator_remarks || '-') + '</td>';
                tbody.appendChild(tr);
            });
        }).catch(function () {
            var trErr = document.createElement('tr');
            trErr.innerHTML = '<td colspan="7">Failed to load validation items.</td>';
            tbody.appendChild(trErr);
        });
    }
    function loadProjectViews() {
        loadHistory();
        loadValidationItems();
    }
    document.getElementById('projectSelect').addEventListener('change', loadProjectViews);
    document.getElementById('saveProgress').addEventListener('click', function () {
        if (!canProgressSubmit) return msg(false, 'You are not allowed to submit progress updates.');
        var pid = document.getElementById('projectSelect').value;
        var progress = document.getElementById('progressInput').value;
        var details = document.getElementById('workDetails').value.trim();
        var info = document.getElementById('validationNotes').value.trim();
        var proof = document.getElementById('proofImage').files[0];
        if (!pid) return msg(false, 'Select a project first.');
        if (!details || details.length < 10) return msg(false, 'Add work details (at least 10 characters).');
        if (!info || info.length < 10) return msg(false, 'Add validation information (at least 10 characters).');
        if (!proof) return msg(false, 'Attach a proof photo.');
        var fd = new FormData();
        fd.append('project_id', pid);
        fd.append('progress', progress);
        fd.append('work_details', details);
        fd.append('validation_notes', info);
        fd.append('proof_image', proof);
        apiPostForm('update_progress', fd).then(function (j) {
            if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed to save progress.');
            msg(true, (j && j.message) || 'Progress submitted for engineer review.');
            document.getElementById('progressInput').value = '';
            document.getElementById('workDetails').value = '';
            document.getElementById('validationNotes').value = '';
            document.getElementById('proofImage').value = '';
            loadHistory();
        });
    });
    document.getElementById('submitValidationItem').addEventListener('click', function () {
        if (!canProgressSubmit) return msg(false, 'You are not allowed to submit deliverable updates.');
        var pid = document.getElementById('projectSelect').value;
        var itemId = document.getElementById('validationItemSelect').value;
        var progress = document.getElementById('validationProgressInput').value;
        var summary = document.getElementById('validationSummary').value.trim();
        var proof = document.getElementById('validationProofImage').files[0];
        if (!pid) return msg(false, 'Select a project first.');
        if (!itemId) return msg(false, 'Select a deliverable first.');
        if (progress === '') return msg(false, 'Enter progress percentage.');
        if (!summary || summary.length < 5) return msg(false, 'Add change summary (at least 5 characters).');
        var fd = new FormData();
        fd.append('item_id', itemId);
        fd.append('progress_percent', progress);
        fd.append('change_summary', summary);
        if (proof) {
            fd.append('proof_image', proof);
        }
        apiPostForm('submit_validation_item', fd).then(function (j) {
            if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed to submit deliverable update.');
            msg(true, (j && j.message) || 'Deliverable update submitted for validation.');
            document.getElementById('validationProgressInput').value = '';
            document.getElementById('validationSummary').value = '';
            document.getElementById('validationProofImage').value = '';
            loadValidationItems();
            loadHistory();
        });
    });
    loadProjects();
})();
</script>
<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>
