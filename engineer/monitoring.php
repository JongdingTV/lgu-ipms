<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['engineer','admin','super_admin']);
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

$employeeName = (string) ($_SESSION['employee_name'] ?? 'Engineer');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Engineer Monitoring - LGU IPMS</title>
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
        <a href="monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
        <a href="task_milestone.php"><img src="../assets/images/admin/production.png" class="nav-icon" alt="">Task & Milestone</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/engineer/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Engineer Monitoring</h1>
        <p>Welcome, <?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?>. Live view of project status and progress updates.</p>
    </div>

    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls">
                <div class="pm-top-row">
                    <div class="pm-left">
                        <label for="searchInput">Search Projects</label>
                        <input id="searchInput" type="search" placeholder="Search by code, name, status, location...">
                    </div>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table" id="projectsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th>Progress</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="pm-section card">
        <div class="dash-header">
            <h2>Progress Decision Queue</h2>
            <p>Review contractor progress submissions and decide.</p>
        </div>
        <div id="decisionFeedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="decisionTable">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Submitted Progress</th>
                        <th>Submitted At</th>
                        <th>Submitted By</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="pm-section card">
        <div class="dash-header">
            <h2>Status Request Review</h2>
            <p>Contractor status requests for engineering recommendation.</p>
        </div>
        <div id="statusReqFeedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="statusReqTable">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Requested Status</th>
                        <th>Contractor Note</th>
                        <th>Engineer Decision</th>
                        <th>Admin Decision</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    const csrfToken = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? '')); ?>;
    const state = { projects: [], submissions: [], statusRequests: [] };

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function load() {
        const [monitoringRes, submissionsRes, statusReqRes] = await Promise.all([
            fetch('/engineer/api.php?action=load_monitoring', { credentials: 'same-origin' }),
            fetch('/engineer/api.php?action=load_progress_submissions', { credentials: 'same-origin' }),
            fetch('/engineer/api.php?action=load_status_requests', { credentials: 'same-origin' })
        ]);
        if (monitoringRes.ok) {
            const json = await monitoringRes.json();
            state.projects = Array.isArray(json.data) ? json.data : [];
        }
        if (submissionsRes.ok) {
            const json = await submissionsRes.json();
            state.submissions = Array.isArray(json.data) ? json.data : [];
        }
        if (statusReqRes && statusReqRes.ok) {
            const json = await statusReqRes.json();
            state.statusRequests = Array.isArray(json.data) ? json.data : [];
        }
        render();
        renderDecisionQueue();
        renderStatusRequestQueue();
    }

    function render() {
        const tbody = document.querySelector('#projectsTable tbody');
        if (!tbody) return;
        const q = (document.getElementById('searchInput').value || '').trim().toLowerCase();
        tbody.innerHTML = '';

        state.projects.forEach(function (p) {
            const hay = [p.code, p.name, p.status, p.location].join(' ').toLowerCase();
            if (q && hay.indexOf(q) === -1) return;
            const tr = document.createElement('tr');
            tr.innerHTML = [
                '<td>' + esc(p.code) + '</td>',
                '<td><strong>' + esc(p.name) + '</strong><br><small>' + esc(p.location || 'N/A') + '</small></td>',
                '<td>' + esc(p.status || 'Draft') + '</td>',
                '<td>PHP ' + Number(p.budget || 0).toLocaleString() + '</td>',
                '<td>' + Number(p.progress_percent || 0).toFixed(2) + '%</td>',
                '<td>' + esc(p.progress_updated_at || 'No updates yet') + '</td>'
            ].join('');
            tbody.appendChild(tr);
        });
    }

    async function decideProgress(updateId, projectId, decisionStatus, note) {
        const body = new URLSearchParams();
        body.set('update_id', String(updateId));
        body.set('project_id', String(projectId));
        body.set('decision_status', String(decisionStatus));
        body.set('decision_note', String(note || ''));
        body.set('csrf_token', csrfToken);
        const res = await fetch('/engineer/api.php?action=decide_progress', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const json = await res.json();
        if (!res.ok || json.success === false) {
            throw new Error((json && json.message) ? json.message : 'Decision failed');
        }
    }

    function showDecisionMessage(ok, text) {
        const box = document.getElementById('decisionFeedback');
        if (!box) return;
        box.className = ok ? 'ac-0b2b14a3' : 'ac-aabba7cf';
        box.textContent = text;
    }

    function renderDecisionQueue() {
        const tbody = document.querySelector('#decisionTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        state.submissions.forEach(function (s) {
            const tr = document.createElement('tr');
            tr.innerHTML = [
                '<td><strong>' + esc((s.code || '') + ' - ' + (s.name || '')) + '</strong></td>',
                '<td>' + Number(s.progress_percent || 0).toFixed(2) + '%</td>',
                '<td>' + esc(s.submitted_at || '') + '</td>',
                '<td>' + esc(s.submitted_by || '') + '</td>',
                '<td>',
                '  <div><small>Current: ' + esc(s.decision_status || 'Pending') + '</small></div>',
                '  <select data-type="decision" data-update="' + s.update_id + '" data-project="' + s.project_id + '">',
                '    <option value="">Set decision</option>',
                '    <option value="Approved">Approve</option>',
                '    <option value="Rejected">Reject</option>',
                '  </select>',
                '  <input data-type="note" data-update="' + s.update_id + '" type="text" placeholder="Decision note">',
                '  <button data-action="decide" data-update="' + s.update_id + '" data-project="' + s.project_id + '">Save</button>',
                '</td>'
            ].join('');
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('button[data-action="decide"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const updateId = this.getAttribute('data-update');
                const projectId = this.getAttribute('data-project');
                const decisionEl = document.querySelector('select[data-type="decision"][data-update="' + updateId + '"]');
                const noteEl = document.querySelector('input[data-type="note"][data-update="' + updateId + '"]');
                if (!decisionEl || !decisionEl.value) {
                    showDecisionMessage(false, 'Select a decision first.');
                    return;
                }
                try {
                    await decideProgress(updateId, projectId, decisionEl.value, noteEl ? noteEl.value : '');
                    showDecisionMessage(true, 'Progress decision saved.');
                    await load();
                } catch (e) {
                    showDecisionMessage(false, e.message);
                }
            });
        });
    }

    async function decideStatusRequest(requestId, decision, note) {
        const body = new URLSearchParams();
        body.set('request_id', String(requestId));
        body.set('engineer_decision', String(decision));
        body.set('engineer_note', String(note || ''));
        body.set('csrf_token', csrfToken);
        const res = await fetch('/engineer/api.php?action=engineer_decide_status_request', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const json = await res.json();
        if (!res.ok || json.success === false) {
            throw new Error((json && json.message) ? json.message : 'Decision failed');
        }
    }

    function showStatusReqMessage(ok, text) {
        const box = document.getElementById('statusReqFeedback');
        if (!box) return;
        box.className = ok ? 'ac-0b2b14a3' : 'ac-aabba7cf';
        box.textContent = text;
    }

    function renderStatusRequestQueue() {
        const tbody = document.querySelector('#statusReqTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        state.statusRequests.forEach(function (r) {
            const tr = document.createElement('tr');
            tr.innerHTML = [
                '<td><strong>' + esc((r.code || '') + ' - ' + (r.name || '')) + '</strong></td>',
                '<td>' + esc(r.requested_status || '') + '</td>',
                '<td>' + esc(r.contractor_note || '') + '</td>',
                '<td>',
                '  <div><small>Current: ' + esc(r.engineer_decision || 'Pending') + '</small></div>',
                '  <select data-type="req-decision" data-id="' + r.id + '">',
                '    <option value="">Set decision</option>',
                '    <option value="Approved">Approve</option>',
                '    <option value="Rejected">Reject</option>',
                '  </select>',
                '  <input data-type="req-note" data-id="' + r.id + '" type="text" placeholder="Engineer note">',
                '  <button data-action="req-decide" data-id="' + r.id + '">Save</button>',
                '</td>',
                '<td>' + esc(r.admin_decision || 'Pending') + '</td>'
            ].join('');
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('button[data-action="req-decide"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const decision = document.querySelector('select[data-type="req-decision"][data-id="' + id + '"]');
                const note = document.querySelector('input[data-type="req-note"][data-id="' + id + '"]');
                if (!decision || !decision.value) {
                    showStatusReqMessage(false, 'Select a decision first.');
                    return;
                }
                try {
                    await decideStatusRequest(id, decision.value, note ? note.value : '');
                    showStatusReqMessage(true, 'Engineer recommendation saved.');
                    await load();
                } catch (e) {
                    showStatusReqMessage(false, e.message);
                }
            });
        });
    }

    document.getElementById('searchInput').addEventListener('input', render);
    load();
})();
</script>
<script src="engineer.js?v=<?php echo filemtime(__DIR__ . '/engineer.js'); ?>"></script>
<script src="engineer-enterprise.js?v=<?php echo filemtime(__DIR__ . '/engineer-enterprise.js'); ?>"></script>
</body>
</html>
