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
    <link rel="stylesheet" href="contractor.css?v=<?php echo filemtime(__DIR__ . '/contractor.css'); ?>">
    <link rel="stylesheet" href="contractor-dashboard.css?v=<?php echo filemtime(__DIR__ . '/contractor-dashboard.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
<header class="nav" id="navbar">
    <div class="nav-logo">
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS Contractor</span>
    </div>
    <div class="nav-links">
        <a href="dashboard.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Validation & Budget</a>
        <a href="progress_monitoring.php" class="active"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Progress Monitoring</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
</header>

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
                <button id="saveProgress" class="contractor-btn btn-success" type="button">Validate & Save Progress</button>
            </div>
        </div>
        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap module-grid">
            <h3 class="contractor-table-title">Progress History</h3>
            <p class="contractor-subtle">Track all validated progress updates per project.</p>
            <table class="table">
                <thead><tr><th>Date</th><th>Progress</th><th>Updated By</th></tr></thead>
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
                tr.innerHTML = '<td>' + (r.created_at || '') + '</td><td>' + Number(r.progress_percent || 0).toFixed(2) + '%</td><td>' + (r.updated_by || '') + '</td>';
                tbody.appendChild(tr);
            });
        });
    }
    document.getElementById('projectSelect').addEventListener('change', loadHistory);
    document.getElementById('saveProgress').addEventListener('click', function () {
        var pid = document.getElementById('projectSelect').value;
        var progress = document.getElementById('progressInput').value;
        if (!pid) return msg(false, 'Select a project first.');
        apiPost('update_progress', { project_id: pid, progress: progress }).then(function (j) {
            if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed to save progress.');
            msg(true, 'Progress saved.');
            document.getElementById('progressInput').value = '';
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
