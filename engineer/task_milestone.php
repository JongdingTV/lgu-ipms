<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('engineer.workspace.view', ['engineer','admin','super_admin']);
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
$canTaskManage = in_array($role, rbac_roles_for('engineer.tasks.manage', ['engineer', 'admin', 'super_admin']), true);
$sidebarName = trim((string)($_SESSION['employee_name'] ?? 'Engineer'));
$sidebarInitial = strtoupper(substr($sidebarName !== '' ? $sidebarName : 'E', 0, 1));
$sidebarRoleLabel = ucwords(str_replace('_', ' ', (string)($_SESSION['employee_role'] ?? 'engineer')));
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
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="engineer.css?v=<?php echo filemtime(__DIR__ . '/engineer.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <style>
        .tm-shell { display: grid; gap: 18px; }
        .tm-header-card {
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 14px;
            background: linear-gradient(140deg, rgba(255,255,255,0.95), rgba(241,248,255,0.92));
            padding: 14px 16px;
        }
        .tm-project-select {
            display: grid;
            grid-template-columns: minmax(190px, 240px) minmax(260px, 520px);
            align-items: end;
            gap: 12px;
        }
        .tm-project-select .filter-group { margin: 0; }
        .tm-grid { display: grid; grid-template-columns: 1fr; gap: 18px; }
        .tm-card {
            border: 1px solid rgba(148, 163, 184, 0.32);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .tm-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px 10px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            background: linear-gradient(180deg, rgba(248, 251, 255, 0.95), rgba(255, 255, 255, 0.95));
        }
        .tm-card-head h3 { margin: 0; color: #0f172a; font-size: 1rem; }
        .tm-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e3a8a;
            background: #dbeafe;
            border: 1px solid #bfdbfe;
        }
        .tm-form {
            display: grid;
            grid-template-columns: 1.35fr 0.95fr 0.95fr 1.15fr auto;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: #fcfdff;
        }
        .tm-form input,
        .tm-form select {
            width: 100%;
            min-height: 42px;
            border: 1px solid rgba(148, 163, 184, 0.45);
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 0.9rem;
            color: #0f172a;
            background: #fff;
        }
        .tm-form input:focus,
        .tm-form select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.13);
        }
        .tm-btn {
            min-height: 42px;
            border: 0;
            border-radius: 10px;
            padding: 8px 14px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #1d4e89, #2563eb);
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
            white-space: nowrap;
        }
        .tm-btn:hover { filter: brightness(1.04); box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25); }
        .tm-btn:active { transform: translateY(1px); }
        .tm-btn:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
        .tm-table-wrap { padding: 12px 16px 16px; }
        .tm-table-wrap .table thead th {
            font-size: 0.8rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #334155;
            background: #f8fafc;
        }
        .tm-table-wrap .table tbody td { vertical-align: middle; }
        .tm-table-wrap .table tbody tr:hover { background: #f8fbff; }
        .tm-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.76rem;
            font-weight: 600;
            border: 1px solid transparent;
            min-width: 92px;
        }
        .tm-status.pending { color: #92400e; background: #fef3c7; border-color: #fde68a; }
        .tm-status.in-progress { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
        .tm-status.completed { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .tm-status.on-hold { color: #6b21a8; background: #f3e8ff; border-color: #e9d5ff; }
        .tm-empty-row td {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 16px 12px;
        }
        @media (max-width: 1200px) {
            .tm-form { grid-template-columns: 1fr 1fr; }
            .tm-form .tm-btn { grid-column: 1 / -1; }
            .tm-project-select { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .tm-table-wrap { overflow-x: auto; }
            .tm-table-wrap .table { min-width: 680px; }
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
        <span class="logo-text">IPMS Engineer</span>
    </div>
    <div class="nav-user-profile">
        <div class="user-initial-badge"><?php echo htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-name"><?php echo htmlspecialchars($sidebarName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="nav-user-email"><?php echo htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="nav-links">
        <a href="dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon" alt="">Dashboard Overview</a>
        <a href="assigned_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">My Assigned Projects</a>
        <a href="monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
        <a href="task_milestone.php" class="active"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Task & Milestone</a>
        <a href="submissions_validation.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Submissions for Validation</a>
        <a href="site_reports.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Site Reports</a>
        <a href="inspection_requests.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Inspection Requests</a>
        <a href="issues_risks.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Issues & Risks</a>
        <a href="documents.php"><img src="../assets/images/admin/list.png" class="nav-icon" alt="">Documents</a>
        <a href="messages.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Messages</a>
        <a href="notifications.php"><img src="../assets/images/admin/notifications.png" class="nav-icon" alt="">Notifications</a>
        <a href="profile.php"><img src="../assets/images/admin/person.png" class="nav-icon" alt="">Profile</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer"><a href="/engineer/logout.php" class="btn-logout nav-logout"><span>Logout</span></a></div>
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
        <h1>Task & Milestone Management</h1>
        <p>Engineer-managed task execution and milestone validation per project.</p>
    </div>

    <div class="pm-section card tm-shell">
        <div class="tm-header-card">
            <div class="pm-controls tm-project-select">
                <div class="filter-group">
                    <label for="projectSelect">Project</label>
                    <select id="projectSelect">
                        <option value="">Select project</option>
                    </select>
                </div>
            </div>
        </div>
        <?php if (!$canTaskManage): ?>
            <div class="ac-c8be1ccb">Read-only mode: You can view tasks/milestones but cannot add or update status.</div>
        <?php endif; ?>
        <div id="feedback" class="ac-c8be1ccb"></div>

        <div class="tm-grid">
        <div class="tm-card">
            <div class="tm-card-head">
                <h3>Tasks</h3>
                <span class="tm-chip" id="taskCountChip">0 items</span>
            </div>
            <div class="tm-form">
                <input id="taskTitle" type="text" placeholder="Task title">
                <input id="taskStart" type="date">
                <input id="taskEnd" type="date">
                <input id="taskNotes" type="text" placeholder="Notes (optional)">
                <button id="addTaskBtn" class="tm-btn" type="button" <?php echo $canTaskManage ? '' : 'disabled'; ?>>Add Task</button>
            </div>
            <div class="table-wrap tm-table-wrap">
            <table class="table">
                <thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th><th>Action</th></tr></thead>
                <tbody id="taskBody"><tr class="tm-empty-row"><td colspan="5">Select a project to view tasks.</td></tr></tbody>
            </table>
            </div>
        </div>

        <div class="tm-card">
            <div class="tm-card-head">
                <h3>Milestones</h3>
                <span class="tm-chip" id="mileCountChip">0 items</span>
            </div>
            <div class="tm-form">
                <input id="mileTitle" type="text" placeholder="Milestone title">
                <input id="mileDate" type="date">
                <input id="mileNotes" type="text" placeholder="Notes (optional)">
                <button id="addMileBtn" class="tm-btn" type="button" <?php echo $canTaskManage ? '' : 'disabled'; ?>>Add Milestone</button>
            </div>
            <div class="table-wrap tm-table-wrap">
            <table class="table">
                <thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th><th>Action</th></tr></thead>
                <tbody id="mileBody"><tr class="tm-empty-row"><td colspan="5">Select a project to view milestones.</td></tr></tbody>
            </table>
            </div>
        </div>
        </div>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var csrf = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    var canTaskManage = <?php echo json_encode($canTaskManage); ?>;

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
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch] || ch;
        });
    }
    function statusBadge(status) {
        var raw = String(status || 'Pending');
        var cls = raw.toLowerCase().replace(/\s+/g, '-');
        return '<span class="tm-status ' + esc(cls) + '">' + esc(raw) + '</span>';
    }
    function setEmptyRows(message) {
        document.getElementById('taskBody').innerHTML = '<tr class="tm-empty-row"><td colspan="5">' + esc(message) + '</td></tr>';
        document.getElementById('mileBody').innerHTML = '<tr class="tm-empty-row"><td colspan="5">' + esc(message) + '</td></tr>';
        document.getElementById('taskCountChip').textContent = '0 items';
        document.getElementById('mileCountChip').textContent = '0 items';
    }
    function projectId() { return document.getElementById('projectSelect').value; }
    function statusSelect(type, id, current) {
        if (!canTaskManage) {
            return statusBadge(current || 'Pending');
        }
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
        if (!canTaskManage) return;
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
        if (!pid) {
            setEmptyRows('Select a project to view records.');
            return;
        }
        document.getElementById('taskBody').innerHTML = '<tr class="tm-empty-row"><td colspan="5">Loading tasks...</td></tr>';
        document.getElementById('mileBody').innerHTML = '<tr class="tm-empty-row"><td colspan="5">Loading milestones...</td></tr>';
        apiGet('load_task_milestone', '&project_id=' + encodeURIComponent(pid)).then(function (j) {
            var d = j.data || {};
            var tasks = Array.isArray(d.tasks) ? d.tasks : [];
            var milestones = Array.isArray(d.milestones) ? d.milestones : [];
            var taskBody = document.getElementById('taskBody');
            var mileBody = document.getElementById('mileBody');
            document.getElementById('taskCountChip').textContent = String(tasks.length) + (tasks.length === 1 ? ' item' : ' items');
            document.getElementById('mileCountChip').textContent = String(milestones.length) + (milestones.length === 1 ? ' item' : ' items');
            taskBody.innerHTML = '';
            mileBody.innerHTML = '';

            tasks.forEach(function (t) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + esc(t.title) + '</td><td>' + statusBadge(t.status || 'Pending') + '</td><td>' + esc((t.planned_start || '') + ' - ' + (t.planned_end || '')) + '</td><td>' + esc((t.actual_start || '') + ' - ' + (t.actual_end || '')) + '</td><td>' + statusSelect('task-status', t.id, t.status || 'Pending') + '</td>';
                taskBody.appendChild(tr);
            });
            if (!tasks.length) {
                taskBody.innerHTML = '<tr class="tm-empty-row"><td colspan="5">No tasks yet for this project.</td></tr>';
            }

            milestones.forEach(function (m) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + esc(m.title) + '</td><td>' + statusBadge(m.status || 'Pending') + '</td><td>' + esc(m.planned_date || '') + '</td><td>' + esc(m.actual_date || '') + '</td><td>' + statusSelect('mile-status', m.id, m.status || 'Pending') + '</td>';
                mileBody.appendChild(tr);
            });
            if (!milestones.length) {
                mileBody.innerHTML = '<tr class="tm-empty-row"><td colspan="5">No milestones yet for this project.</td></tr>';
            }
            bindStatusActions();
        }).catch(function () {
            setEmptyRows('Failed to load task and milestone records.');
            msg(false, 'Unable to load data right now. Please try again.');
        });
    }

    document.getElementById('projectSelect').addEventListener('change', loadData);
    document.getElementById('addTaskBtn').addEventListener('click', function () {
        if (!canTaskManage) return msg(false, 'You are not allowed to add tasks.');
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
        if (!canTaskManage) return msg(false, 'You are not allowed to add milestones.');
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

    if (!canTaskManage) {
        ['taskTitle', 'taskStart', 'taskEnd', 'taskNotes', 'mileTitle', 'mileDate', 'mileNotes'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = true;
        });
    }
    loadProjects();
})();
</script>
<script src="engineer.js?v=<?php echo filemtime(__DIR__ . '/engineer.js'); ?>"></script>
<script src="engineer-enterprise.js?v=<?php echo filemtime(__DIR__ . '/engineer-enterprise.js'); ?>"></script>
</body>
</html>
