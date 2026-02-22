<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
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

$employeeName = (string) ($_SESSION['employee_name'] ?? 'Contractor');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Dashboard - LGU IPMS</title>
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
        <a href="dashboard.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Validation & Budget</a>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Progress Monitoring</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Contractor Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?>. Manage project validation, budgets, and expenses.</p>
    </div>
    <div class="contractor-help">
        <strong>Simple flow:</strong> 1) Update Budget/Expense/Progress, 2) Send Status Request to Engineer, 3) Wait for Engineer + Admin decision.
    </div>

    <div class="contractor-stats">
        <div class="contractor-stat-card">
            <div class="label">Total Projects</div>
            <div class="value" id="statTotal">0</div>
        </div>
        <div class="contractor-stat-card">
            <div class="label">Approved</div>
            <div class="value" id="statApproved">0</div>
        </div>
        <div class="contractor-stat-card">
            <div class="label">Total Budget</div>
            <div class="value" id="statBudget">PHP 0</div>
        </div>
        <div class="contractor-stat-card">
            <div class="label">Total Spent</div>
            <div class="value" id="statSpent">PHP 0</div>
        </div>
    </div>

    <div class="pm-section card">
        <div class="pm-controls-wrapper">
            <div class="pm-controls">
                <div class="pm-top-row">
                    <div class="pm-left">
                        <label for="searchInput">Search Projects</label>
                        <input id="searchInput" type="search" placeholder="Search by code, name, status, location...">
                    </div>
                    <div class="pm-right">
                        <div class="filter-group">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                                <option value="">All Status</option>
                                <option value="For Approval">For Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="On-hold">On-hold</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="projectsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th>Status Validation</th>
                        <th>Expense</th>
                        <th>Progress</th>
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
    const state = { projects: [], milestones: [], totalSpent: 0 };

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showMessage(type, text) {
        const box = document.getElementById('feedback');
        if (!box) return;
        box.className = type === 'ok' ? 'ac-0b2b14a3' : 'ac-aabba7cf';
        box.textContent = text;
    }

    async function apiGet(action) {
        const res = await fetch('/contractor/api.php?action=' + encodeURIComponent(action), { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Request failed');
        return res.json();
    }

    async function apiPost(action, payload) {
        const body = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (k) { body.set(k, String(payload[k])); });
        body.set('csrf_token', csrfToken);
        const res = await fetch('/contractor/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const json = await res.json();
        if (!res.ok || json.success === false) {
            throw new Error((json && json.message) ? json.message : 'Request failed');
        }
        return json;
    }

    function milestoneIdByProjectName(name) {
        const m = state.milestones.find(function (row) {
            return String(row.name || '').toLowerCase() === String(name || '').toLowerCase();
        });
        return m ? m.id : 0;
    }

    function renderStats() {
        var projects = state.projects || [];
        var total = projects.length;
        var approved = projects.filter(function (p) { return String(p.status || '').toLowerCase() === 'approved'; }).length;
        var totalBudget = projects.reduce(function (sum, p) { return sum + Number(p.budget || 0); }, 0);
        document.getElementById('statTotal').textContent = String(total);
        document.getElementById('statApproved').textContent = String(approved);
        document.getElementById('statBudget').textContent = 'PHP ' + totalBudget.toLocaleString();
        document.getElementById('statSpent').textContent = 'PHP ' + Number(state.totalSpent || 0).toLocaleString();
    }

    function renderTable() {
        const tbody = document.querySelector('#projectsTable tbody');
        if (!tbody) return;
        const q = (document.getElementById('searchInput').value || '').trim().toLowerCase();
        const statusFilter = (document.getElementById('statusFilter').value || '').trim().toLowerCase();
        tbody.innerHTML = '';

        function statusChip(status) {
            var raw = String(status || 'Draft');
            var key = raw.toLowerCase();
            var cls = 'default';
            if (key === 'approved') cls = 'approved';
            else if (key === 'for approval') cls = 'for-approval';
            else if (key === 'on-hold') cls = 'on-hold';
            else if (key === 'completed') cls = 'completed';
            return '<span class="status-chip ' + cls + '">' + esc(raw) + '</span>';
        }

        state.projects.forEach(function (p) {
            const hay = [p.code, p.name, p.status, p.location].join(' ').toLowerCase();
            if (q && hay.indexOf(q) === -1) return;
            if (statusFilter && String(p.status || '').toLowerCase() !== statusFilter) return;

            const tr = document.createElement('tr');
            tr.innerHTML = [
                '<td>' + esc(p.code) + '</td>',
                '<td><strong>' + esc(p.name) + '</strong><br><small>' + esc(p.location || 'N/A') + '</small></td>',
                '<td>' + statusChip(p.status || 'Draft') + '</td>',
                '<td>',
                '  <div class="section-title">Current</div>',
                '  <div><strong>PHP ' + Number(p.budget || 0).toLocaleString() + '</strong></div>',
                '  <div class="section-title">Update</div>',
                '  <input class="contractor-input" data-type="budget" data-id="' + p.id + '" type="number" min="0" step="0.01" placeholder="New budget">',
                '  <button class="contractor-btn btn-warning" type="button" data-action="budget" data-id="' + p.id + '">Update Budget</button>',
                '</td>',
                '<td>',
                '  <div class="section-title">Send To Engineer</div>',
                '  <select class="contractor-select" data-type="validate" data-id="' + p.id + '">',
                '    <option value="">Select target status</option>',
                '    <option value="For Approval">For Approval</option>',
                '    <option value="On-hold">On-hold</option>',
                '    <option value="Completed">Completed</option>',
                '  </select>',
                '  <input class="contractor-input" data-type="status-note" data-id="' + p.id + '" type="text" placeholder="Reason / note for engineer/admin">',
                '  <button class="contractor-btn btn-warning" type="button" data-action="status-request" data-id="' + p.id + '">Send Request</button>',
                '</td>',
                '<td>',
                '  <div class="section-title">Log Expense</div>',
                '  <input class="contractor-input" data-type="amount" data-id="' + p.id + '" type="number" min="0" step="0.01" placeholder="Amount">',
                '  <input class="contractor-input" data-type="desc" data-id="' + p.id + '" type="text" placeholder="Description">',
                '  <button class="contractor-btn btn-neutral" type="button" data-action="expense" data-id="' + p.id + '">Log Expense</button>',
                '</td>',
                '<td>',
                '  <div class="section-title">Current</div>',
                '  <div>Current: ' + Number(p.progress_percent || 0).toFixed(2) + '%</div>',
                '  <small>' + esc(p.progress_updated_at || 'No updates yet') + '</small><br>',
                '  <a class="contractor-link-btn" href="progress_monitoring.php?project_id=' + encodeURIComponent(String(p.id)) + '">Update Progress</a>',
                '</td>'
            ].join('');
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('button[data-action="status-request"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const statusEl = document.querySelector('select[data-type="validate"][data-id="' + id + '"]');
                const noteEl = document.querySelector('input[data-type="status-note"][data-id="' + id + '"]');
                if (!statusEl || !statusEl.value) {
                    showMessage('err', 'Select a target status first.');
                    return;
                }
                try {
                    await apiPost('submit_status_request', {
                        project_id: id,
                        requested_status: statusEl.value,
                        note: noteEl ? noteEl.value : ''
                    });
                    if (noteEl) noteEl.value = '';
                    statusEl.value = '';
                    showMessage('ok', 'Status request submitted to Engineer/Admin.');
                } catch (e) {
                    showMessage('err', e.message);
                }
            });
        });

        tbody.querySelectorAll('button[data-action="budget"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const budget = document.querySelector('input[data-type="budget"][data-id="' + id + '"]');
                try {
                    await apiPost('update_budget', {
                        project_id: id,
                        budget: Number(budget.value || 0)
                    });
                    budget.value = '';
                    showMessage('ok', 'Project budget updated.');
                    await load();
                } catch (e) {
                    showMessage('err', e.message);
                }
            });
        });

        tbody.querySelectorAll('button[data-action="expense"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const amount = document.querySelector('input[data-type="amount"][data-id="' + id + '"]');
                const desc = document.querySelector('input[data-type="desc"][data-id="' + id + '"]');
                const project = state.projects.find(function (p) { return String(p.id) === String(id); });
                const milestoneId = project ? milestoneIdByProjectName(project.name) : 0;
                try {
                    await apiPost('update_expense', {
                        milestone_id: milestoneId,
                        amount: Number(amount.value || 0),
                        description: desc.value || ''
                    });
                    amount.value = '';
                    desc.value = '';
                    showMessage('ok', 'Expense updated successfully.');
                    await load();
                } catch (e) {
                    showMessage('err', e.message);
                }
            });
        });

    }

    async function load() {
        const [projectsJson, budgetJson] = await Promise.all([
            apiGet('load_projects'),
            apiGet('load_budget_state')
        ]);
        state.projects = Array.isArray(projectsJson.data) ? projectsJson.data : [];
        state.milestones = Array.isArray((budgetJson.data || {}).milestones) ? budgetJson.data.milestones : [];
        state.totalSpent = Number((budgetJson.data || {}).total_spent || 0);
        renderStats();
        renderTable();
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    document.getElementById('statusFilter').addEventListener('change', renderTable);
    load().catch(function () {
        showMessage('err', 'Failed to load contractor data.');
    });
})();
</script>
<script src="contractor-enterprise.js?v=<?php echo filemtime(__DIR__ . '/contractor-enterprise.js'); ?>"></script>
</body>
</html>
