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
        <a href="#"><img src="../assets/images/admin/monitoring.png" class="nav-icon" alt="">Project Monitoring</a>
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
</section>

<script>
(function () {
    'use strict';
    const state = { projects: [] };

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function load() {
        const res = await fetch('/engineer/api.php?action=load_monitoring', { credentials: 'same-origin' });
        if (!res.ok) return;
        const json = await res.json();
        state.projects = Array.isArray(json.data) ? json.data : [];
        render();
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

    document.getElementById('searchInput').addEventListener('input', render);
    load();
})();
</script>
<script src="engineer.js?v=<?php echo filemtime(__DIR__ . '/engineer.js'); ?>"></script>
<script src="engineer-enterprise.js?v=<?php echo filemtime(__DIR__ . '/engineer-enterprise.js'); ?>"></script>
</body>
</html>
