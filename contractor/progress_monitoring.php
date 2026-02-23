<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['contractor','admin','super_admin']);
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
        .pm-controls {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .filter-group textarea,
        .filter-group input[type="file"] {
            width: 100%;
            border: 1px solid #c8d8ea;
            border-radius: 10px;
            background: #fff;
            padding: 10px 12px;
            font: inherit;
            color: #0f172a;
        }
        .filter-group textarea { min-height: 86px; resize: vertical; }
        .filter-group textarea:focus,
        .filter-group input[type="file"]:focus,
        .filter-group input[type="number"]:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #2f73b5;
            box-shadow: 0 0 0 3px rgba(47,115,181,.16);
        }
        .contractor-btn.btn-success {
            height: 44px;
            border-radius: 10px;
            border: none;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg,#16416f,#2f73b5);
            box-shadow: 0 7px 16px rgba(22,65,111,.25);
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease;
        }
        .contractor-btn.btn-success:hover { transform: translateY(-1px); filter: brightness(1.03); }
        .contractor-btn.btn-success:active { transform: translateY(0); box-shadow: 0 4px 11px rgba(22,65,111,.2); }
        @media (max-width: 1100px) {
            .pm-controls { grid-template-columns: 1fr; }
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
                    <select id="projectSelect"></select>
                </div>
                <div class="filter-group">
                    <label for="progressInput">Progress %</label>
                    <input id="progressInput" type="number" min="0" max="100" step="1" placeholder="0-100">
                </div>
                <div class="filter-group" style="min-width:260px;">
                    <label for="workDetails">Work Details</label>
                    <textarea id="workDetails" rows="2" placeholder="What work was completed today?"></textarea>
                </div>
                <div class="filter-group" style="min-width:260px;">
                    <label for="validationNotes">Validation Information</label>
                    <textarea id="validationNotes" rows="2" placeholder="Site notes, manpower, materials, milestones..."></textarea>
                </div>
                <div class="filter-group">
                    <label for="proofImage">Proof Photo</label>
                    <input id="proofImage" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </div>
                <button id="saveProgress" class="contractor-btn btn-success" type="button">Submit for Engineer Review</button>
            </div>
        </div>
        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap module-grid">
            <h3 class="contractor-table-title">Progress History</h3>
            <p class="contractor-subtle">Flow: Contractor submits update with proof -> Engineer reviews -> Engineer approves -> Official progress is updated.</p>
            <table class="table">
                <thead><tr><th>Date</th><th>Progress</th><th>Status</th><th>Discrepancy</th><th>Updated By</th></tr></thead>
                <tbody id="historyBody"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var csrf = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    var preselectProjectId = <?php echo json_encode((string) ($_GET['project_id'] ?? '')); ?>;
    var projects = [];

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
    document.getElementById('projectSelect').addEventListener('change', loadHistory);
    document.getElementById('saveProgress').addEventListener('click', function () {
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
    loadProjects();
})();
</script>
<script src="contractor.js?v=<?php echo filemtime(__DIR__ . '/contractor.js'); ?>"></script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>

