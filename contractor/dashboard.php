<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($_SESSION['employee_id']) || strtolower((string) ($_SESSION['employee_role'] ?? '')) !== 'contractor') {
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
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
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
        <a href="#"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Validation</a>
        <a href="#"><img src="../assets/images/admin/budget.png" class="nav-icon" alt="">Expense Updates</a>
        <a href="#"><img src="../assets/images/admin/chart.png" class="nav-icon" alt="">Progress Updates</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/contractor/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Contractor Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8'); ?>. Validate projects and update spending/progress.</p>
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
        <div id="feedback" class="ac-c8be1ccb"></div>
        <div class="table-wrap">
            <table class="table" id="projectsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th>Validate</th>
                        <th>Expense Update</th>
                        <th>Progress Update</th>
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
    const state = { projects: [], milestones: [] };

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

    function renderTable() {
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
                '<td>',
                '  <select data-type="validate" data-id="' + p.id + '">',
                '    <option value="">Set status</option>',
                '    <option value="For Approval">For Approval</option>',
                '    <option value="Approved">Approved</option>',
                '    <option value="On-hold">On-hold</option>',
                '    <option value="Completed">Completed</option>',
                '  </select>',
                '</td>',
                '<td>',
                '  <input data-type="amount" data-id="' + p.id + '" type="number" min="0" step="0.01" placeholder="Amount">',
                '  <input data-type="desc" data-id="' + p.id + '" type="text" placeholder="Description">',
                '  <button type="button" data-action="expense" data-id="' + p.id + '">Save</button>',
                '</td>',
                '<td>',
                '  <input data-type="progress" data-id="' + p.id + '" type="number" min="0" max="100" step="1" placeholder="%">',
                '  <button type="button" data-action="progress" data-id="' + p.id + '">Save</button>',
                '</td>'
            ].join('');
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('select[data-type="validate"]').forEach(function (el) {
            el.addEventListener('change', async function () {
                if (!this.value) return;
                try {
                    await apiPost('validate_project', { project_id: this.getAttribute('data-id'), status: this.value });
                    showMessage('ok', 'Project status updated.');
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

        tbody.querySelectorAll('button[data-action="progress"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const progress = document.querySelector('input[data-type="progress"][data-id="' + id + '"]');
                try {
                    await apiPost('update_progress', { project_id: id, progress: Number(progress.value || 0) });
                    progress.value = '';
                    showMessage('ok', 'Progress updated successfully.');
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
        renderTable();
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    load().catch(function () {
        showMessage('err', 'Failed to load contractor data.');
    });
})();
</script>
</body>
</html>

