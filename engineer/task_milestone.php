<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    header('Location: /engineer/index.php');
    exit;
}
$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['engineer', 'admin', 'super_admin'], true)) {
    header('Location: /engineer/index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Engineer Task & Milestone - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="engineer.css?v=<?php echo filemtime(__DIR__ . '/engineer.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
<header class="nav" id="navbar">
    <div class="nav-logo">
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS Engineer</span>
    </div>
    <div class="nav-links">
        <a href="monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
        <a href="task_milestone.php" class="active"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Task & Milestone</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/engineer/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Task & Milestone Management</h1>
        <p>Engineer-managed task execution and milestone validation per project.</p>
    </div>

    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls">
                <div class="filter-group">
                    <label for="projectSelect">Project</label>
                    <select id="projectSelect"></select>
                </div>
            </div>
        </div>
        <div id="feedback" class="ac-c8be1ccb"></div>

        <div class="table-wrap">
            <h3>Tasks</h3>
            <div class="pm-controls">
                <input id="taskTitle" type="text" placeholder="Task title">
                <input id="taskStart" type="date">
                <input id="taskEnd" type="date">
                <input id="taskNotes" type="text" placeholder="Notes (optional)">
                <button id="addTaskBtn" class="btn-export" type="button">Add Task</button>
            </div>
            <table class="table">
                <thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th><th>Action</th></tr></thead>
                <tbody id="taskBody"></tbody>
            </table>
        </div>

        <div class="table-wrap">
            <h3>Milestones</h3>
            <div class="pm-controls">
                <input id="mileTitle" type="text" placeholder="Milestone title">
                <input id="mileDate" type="date">
                <input id="mileNotes" type="text" placeholder="Notes (optional)">
                <button id="addMileBtn" class="btn-export" type="button">Add Milestone</button>
            </div>
            <table class="table">
                <thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th><th>Action</th></tr></thead>
                <tbody id="mileBody"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var csrf = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;

    function apiGet(action, extra) {
        var q = '/engineer/api.php?action=' + encodeURIComponent(action) + (extra || '');
        return fetch(q, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function apiPost(action, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) { body.set(k, String(data[k])); });
        body.set('csrf_token', csrf);
        return fetch('/engineer/api.php?action=' + encodeURIComponent(action), {
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
    function projectId() { return document.getElementById('projectSelect').value; }
    function statusSelect(type, id, current) {
        var all = ['Pending', 'In Progress', 'Completed', 'On-hold'];
        return '<select data-type="' + type + '" data-id="' + id + '">' + all.map(function (s) {
            return '<option value="' + s + '"' + (s === current ? ' selected' : '') + '>' + s + '</option>';
        }).join('') + '</select>';
    }
    function loadProjects() {
        return apiGet('load_monitoring').then(function (j) {
            var rows = Array.isArray(j.data) ? j.data : [];
            var s = document.getElementById('projectSelect');
            s.innerHTML = '<option value="">Select project</option>';
            rows.forEach(function (p) {
                var o = document.createElement('option');
                o.value = p.id;
                o.textContent = p.code + ' - ' + p.name;
                s.appendChild(o);
            });
        });
    }
    function bindStatusActions() {
        document.querySelectorAll('select[data-type="task-status"]').forEach(function (el) {
            el.addEventListener('change', function () {
                apiPost('update_task_status', { task_id: this.getAttribute('data-id'), status: this.value }).then(function (j) {
                    if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed task update.');
                    msg(true, 'Task status updated.');
                    loadData();
                });
            });
        });
        document.querySelectorAll('select[data-type="mile-status"]').forEach(function (el) {
            el.addEventListener('change', function () {
                apiPost('update_milestone_status', { milestone_id: this.getAttribute('data-id'), status: this.value }).then(function (j) {
                    if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed milestone update.');
                    msg(true, 'Milestone status updated.');
                    loadData();
                });
            });
        });
    }
    function loadData() {
        var pid = projectId();
        document.getElementById('taskBody').innerHTML = '';
        document.getElementById('mileBody').innerHTML = '';
        if (!pid) return;
        apiGet('load_task_milestone', '&project_id=' + encodeURIComponent(pid)).then(function (j) {
            var d = j.data || {};
            var tasks = Array.isArray(d.tasks) ? d.tasks : [];
            var milestones = Array.isArray(d.milestones) ? d.milestones : [];
            var taskBody = document.getElementById('taskBody');
            var mileBody = document.getElementById('mileBody');

            tasks.forEach(function (t) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + t.title + '</td><td>' + (t.status || '') + '</td><td>' + (t.planned_start || '') + ' - ' + (t.planned_end || '') + '</td><td>' + (t.actual_start || '') + ' - ' + (t.actual_end || '') + '</td><td>' + statusSelect('task-status', t.id, t.status || 'Pending') + '</td>';
                taskBody.appendChild(tr);
            });

            milestones.forEach(function (m) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + m.title + '</td><td>' + (m.status || '') + '</td><td>' + (m.planned_date || '') + '</td><td>' + (m.actual_date || '') + '</td><td>' + statusSelect('mile-status', m.id, m.status || 'Pending') + '</td>';
                mileBody.appendChild(tr);
            });
            bindStatusActions();
        });
    }

    document.getElementById('projectSelect').addEventListener('change', loadData);
    document.getElementById('addTaskBtn').addEventListener('click', function () {
        var pid = projectId();
        if (!pid) return msg(false, 'Select a project first.');
        apiPost('add_task', {
            project_id: pid,
            title: document.getElementById('taskTitle').value,
            planned_start: document.getElementById('taskStart').value,
            planned_end: document.getElementById('taskEnd').value,
            notes: document.getElementById('taskNotes').value
        }).then(function (j) {
            if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed to add task.');
            msg(true, 'Task added.');
            document.getElementById('taskTitle').value = '';
            document.getElementById('taskStart').value = '';
            document.getElementById('taskEnd').value = '';
            document.getElementById('taskNotes').value = '';
            loadData();
        });
    });
    document.getElementById('addMileBtn').addEventListener('click', function () {
        var pid = projectId();
        if (!pid) return msg(false, 'Select a project first.');
        apiPost('add_milestone', {
            project_id: pid,
            title: document.getElementById('mileTitle').value,
            planned_date: document.getElementById('mileDate').value,
            notes: document.getElementById('mileNotes').value
        }).then(function (j) {
            if (!j || j.success === false) return msg(false, (j && j.message) || 'Failed to add milestone.');
            msg(true, 'Milestone added.');
            document.getElementById('mileTitle').value = '';
            document.getElementById('mileDate').value = '';
            document.getElementById('mileNotes').value = '';
            loadData();
        });
    });

    loadProjects();
})();
</script>
<script src="engineer-enterprise.js?v=<?php echo filemtime(__DIR__ . '/engineer-enterprise.js'); ?>"></script>
</body>
</html>
