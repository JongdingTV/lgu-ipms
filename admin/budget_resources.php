<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle API requests first (before rendering HTML)
if (isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $db->query("SELECT id, code, name, budget FROM projects ORDER BY created_at DESC");
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $result->free();
    }
    
    echo json_encode($projects);
    $db->close();
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Budget & Resources - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/table-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    </head>
<body>
    <!-- Sidebar Toggle Button (Floating) -->
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <!-- Navbar menu icon - shows when sidebar is hidden -->
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php" class="active"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Contractors</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="settings.php" class="nav-main-item" id="userMenuToggle" data-section="user"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                </div>
            </div>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/admin/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <!-- Toggle button to show sidebar -->
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Budget & Resources</h1>
            <p>Manage your project budget efficiently: set total budget, define source funds for departments, track expenses, and monitor consumption.</p>
        </div>

        <div class="budget-section">
            <h2>Set Project Budget</h2>
            <div class="controls-bar">
                <div class="left">
                    <label for="globalBudget"><strong>Project Total Budget (₱)</strong></label>
                    <input id="globalBudget" type="number" min="0" step="0.01" placeholder="Enter total project budget">
                </div>
                <div class="right">
                    <button id="btnImport" class="export-btn" type="button">Import from Project</button>
                    <button id="btnExportBudget" class="export-btn" type="button">Export CSV</button>
                </div>
            </div>
        </div>

        <div class="allocation-section">
            <h2>Source Funds</h2>
            <form id="milestoneForm" class="inline-form">
                <input id="milestoneName" type="text" placeholder="Source name (e.g., National Grant)" required>
                <input id="milestoneAlloc" type="number" min="0" step="0.01" placeholder="Amount ₱" required>
                <button type="button" id="addMilestone">Add Source</button>
            </form>
            <div class="table-wrap">
                <table id="milestonesTable" class="table">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Amount (₱)</th>
                            <th>Used (₱)</th>
                            <th>Remaining (₱)</th>
                            <th>% Consumed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="expense-section">
            <h2>Track Expenses</h2>
            <form id="expenseForm" class="inline-form">
                <select id="expenseMilestone" required>
                    <option value="">Select source</option>
                </select>
                <input id="expenseAmount" type="number" min="0" step="0.01" placeholder="Amount ₱" required>
                <input id="expenseDesc" type="text" placeholder="Description (optional)">
                <button type="button" id="addExpense">Add Expense</button>
            </form>
            <div class="table-wrap">
                <table id="expensesTable" class="table">
                    <thead>
                        <tr><th>Date</th><th>Milestone</th><th>Description</th><th>Amount (₱)</th><th>Actions</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="summary-section">
            <h2>Budget Overview</h2>
            <div class="summary">
                <div class="stat">
                    <div id="summaryAllocated">₱0</div>
                    <small>Allocated</small>
                </div>
                <div class="stat">
                    <div id="summarySpent">₱0</div>
                    <small>Spent</small>
                </div>
                <div class="stat">
                    <div id="summaryRemaining">₱0</div>
                    <small>Remaining</small>
                </div>
                <div class="stat">
                    <div id="summaryConsumption">0%</div>
                    <small>Consumption</small>
                </div>
            </div>
            <h3>Budget Consumption Graph</h3>
            <div class="chart-row">
                <canvas id="consumptionChart" width="800" height="280" aria-label="Budget consumption chart"></canvas>
            </div>
        </div>
    </section>

    <style>
        .main-content .dash-header {
            background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.2), rgba(37, 99, 235, 0) 46%), linear-gradient(145deg, #ffffff, #f7fbff);
            border: 1px solid #d9e7f7;
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 14px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        }

        .main-content .dash-header h1 {
            margin: 0 0 4px;
            color: #173a62;
            font-size: 1.9rem;
        }

        .main-content .dash-header p {
            margin: 0;
            color: #4f6987;
            font-weight: 500;
        }

        .main-content .budget-section,
        .main-content .allocation-section,
        .main-content .expense-section,
        .main-content .summary-section {
            border: 1px solid #d8e6f4;
            border-radius: 16px;
            background: linear-gradient(165deg, #ffffff 0%, #f8fbff 72%);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
            padding: 16px;
            margin-bottom: 14px;
        }

        .main-content .budget-section h2,
        .main-content .allocation-section h2,
        .main-content .expense-section h2,
        .main-content .summary-section h2 {
            margin: 0 0 12px;
            color: #1a3f67;
            font-size: 1.05rem;
            letter-spacing: 0.25px;
        }

        .main-content .controls-bar {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .main-content .controls-bar .left {
            min-width: 0;
        }

        .main-content .controls-bar .left label {
            display: block;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5f7894;
            margin-bottom: 6px;
        }

        .main-content #globalBudget,
        .main-content .inline-form input,
        .main-content .inline-form select {
            width: 100%;
            min-height: 40px;
            border: 1px solid #cddced;
            border-radius: 10px;
            padding: 0 12px;
            color: #1f3858;
            background: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .main-content #globalBudget:focus,
        .main-content .inline-form input:focus,
        .main-content .inline-form select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
        }

        .main-content .controls-bar .right {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .main-content .export-btn,
        .main-content #addMilestone,
        .main-content #addExpense {
            min-height: 40px;
            border-radius: 10px;
            border: 1px solid #1d4ed8;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.2px;
            padding: 0 14px;
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2);
        }

        .main-content .export-btn:hover,
        .main-content #addMilestone:hover,
        .main-content #addExpense:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 12px 20px rgba(29, 78, 216, 0.26);
            transform: translateY(-1px);
        }

        .main-content .inline-form {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            gap: 10px;
            margin-bottom: 12px;
            align-items: end;
        }

        .main-content .table-wrap {
            border: 1px solid #d8e6f4;
            border-radius: 12px;
            overflow: auto;
            background: #fff;
        }

        .main-content .table-wrap table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse;
        }

        .main-content .table-wrap thead th {
            background: #eef5ff;
            color: #35567a;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.45px;
            border-bottom: 1px solid #d2e2f5;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .main-content .table-wrap th,
        .main-content .table-wrap td {
            padding: 11px 10px;
            border-bottom: 1px solid #e7eff9;
            color: #234667;
        }

        .main-content .table-wrap tbody tr:hover {
            background: #f8fbff;
        }

        .main-content .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .main-content .summary .stat {
            border: 1px solid #d8e6f4;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.07);
            position: relative;
            overflow: hidden;
        }

        .main-content .summary .stat::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: #3b82f6;
        }

        .main-content .summary .stat:nth-child(2)::before { background: #ef4444; }
        .main-content .summary .stat:nth-child(3)::before { background: #16a34a; }
        .main-content .summary .stat:nth-child(4)::before { background: #f59e0b; }

        .main-content .summary .stat > div {
            font-size: 1.55rem;
            font-weight: 700;
            color: #153a63;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .main-content .summary .stat small {
            color: #607995;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 700;
            font-size: 0.72rem;
        }

        .main-content .summary-section h3 {
            margin: 0 0 10px;
            color: #244a73;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
        }

        .main-content .chart-row {
            border: 1px solid #d8e6f4;
            border-radius: 12px;
            background: linear-gradient(165deg, #ffffff, #f9fcff);
            padding: 10px;
        }

        .main-content #consumptionChart {
            width: 100%;
            height: 280px;
            display: block;
        }

        @media (max-width: 1080px) {
            .main-content .summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-content .inline-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .main-content .controls-bar {
                grid-template-columns: 1fr;
            }

            .main-content .controls-bar .right {
                justify-content: stretch;
            }

            .main-content .controls-bar .right .export-btn {
                width: 100%;
            }

            .main-content .inline-form {
                grid-template-columns: 1fr;
            }

            .main-content .summary {
                grid-template-columns: 1fr;
            }

            .main-content #consumptionChart {
                height: 230px;
            }
        }
    </style>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script>
    (function () {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (!path.endsWith('/admin/budget_resources.php')) return;

        const KEY = 'lgu_budget_module_v1';
        let booted = false;

        function byId(id) { return document.getElementById(id); }

        function currency(value) {
            const num = Number(value || 0);
            return 'PHP ' + num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function uid(prefix) {
            return prefix + Math.random().toString(36).slice(2, 10);
        }

        function loadState() {
            try {
                const raw = localStorage.getItem(KEY);
                if (!raw) return { globalBudget: 0, milestones: [], expenses: [] };
                const parsed = JSON.parse(raw);
                return {
                    globalBudget: Number(parsed.globalBudget || 0),
                    milestones: Array.isArray(parsed.milestones) ? parsed.milestones : [],
                    expenses: Array.isArray(parsed.expenses) ? parsed.expenses : []
                };
            } catch (e) {
                console.warn('Budget state is corrupted; resetting storage.', e);
                localStorage.removeItem(KEY);
                return { globalBudget: 0, milestones: [], expenses: [] };
            }
        }

        function saveState(state) {
            localStorage.setItem(KEY, JSON.stringify(state));
        }

        function getSpentForMilestone(state, milestoneId) {
            return state.expenses
                .filter((exp) => exp.milestoneId === milestoneId)
                .reduce((sum, exp) => sum + Number(exp.amount || 0), 0);
        }

        function renderMilestones(state) {
            const tbody = document.querySelector('#milestonesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            state.milestones.forEach((ms) => {
                const spent = getSpentForMilestone(state, ms.id);
                const allocated = Number(ms.allocated || 0);
                const remaining = Math.max(0, allocated - spent);
                const consumed = allocated ? Math.round((spent / allocated) * 100) : 0;

                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + String(ms.name || '') + '</td>',
                    '<td><input class="allocInput" data-id="' + ms.id + '" type="number" min="0" step="0.01" value="' + allocated + '"></td>',
                    '<td>' + currency(spent) + '</td>',
                    '<td>' + currency(remaining) + '</td>',
                    '<td>' + consumed + '%</td>',
                    '<td><button class="btn-small btn-danger btnDeleteSource" type="button" data-id="' + ms.id + '">Delete</button></td>'
                ].join('');
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.allocInput').forEach((input) => {
                input.addEventListener('change', function () {
                    const id = this.getAttribute('data-id');
                    const value = Math.max(0, Number(this.value || 0));
                    const next = loadState();
                    const item = next.milestones.find((m) => m.id === id);
                    if (!item) return;
                    item.allocated = value;
                    saveState(next);
                    renderAll(next);
                });
            });

            tbody.querySelectorAll('.btnDeleteSource').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const next = loadState();
                    next.milestones = next.milestones.filter((m) => m.id !== id);
                    next.expenses = next.expenses.filter((e) => e.milestoneId !== id);
                    saveState(next);
                    renderAll(next);
                });
            });
        }

        function renderExpenses(state) {
            const tbody = document.querySelector('#expensesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            state.expenses.slice().reverse().forEach((exp) => {
                const ms = state.milestones.find((m) => m.id === exp.milestoneId);
                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + new Date(exp.date || Date.now()).toLocaleString() + '</td>',
                    '<td>' + (ms ? ms.name : '(source removed)') + '</td>',
                    '<td>' + String(exp.description || '') + '</td>',
                    '<td>' + currency(exp.amount) + '</td>',
                    '<td><button class="btn-small btn-danger btnDeleteExpense" type="button" data-id="' + exp.id + '">Delete</button></td>'
                ].join('');
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btnDeleteExpense').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const next = loadState();
                    next.expenses = next.expenses.filter((e) => e.id !== id);
                    saveState(next);
                    renderAll(next);
                });
            });
        }

        function populateSourceSelect(state) {
            const select = byId('expenseMilestone');
            if (!select) return;
            select.innerHTML = '<option value="">Select source</option>';
            state.milestones.forEach((m) => {
                const option = document.createElement('option');
                option.value = m.id;
                option.textContent = m.name;
                select.appendChild(option);
            });
        }

        function renderSummary(state) {
            const allocated = state.milestones.reduce((sum, m) => sum + Number(m.allocated || 0), 0);
            const spent = state.expenses.reduce((sum, e) => sum + Number(e.amount || 0), 0);
            const remaining = Math.max(0, Number(state.globalBudget || 0) - spent);
            const base = Number(state.globalBudget || 0) || allocated || 0;
            const consumption = base ? Math.round((spent / base) * 100) : 0;

            const allocatedEl = byId('summaryAllocated');
            const spentEl = byId('summarySpent');
            const remainingEl = byId('summaryRemaining');
            const consumptionEl = byId('summaryConsumption');
            if (allocatedEl) allocatedEl.textContent = currency(allocated);
            if (spentEl) spentEl.textContent = currency(spent);
            if (remainingEl) remainingEl.textContent = currency(remaining);
            if (consumptionEl) consumptionEl.textContent = consumption + '%';
        }

        function drawChart(state) {
            const canvas = byId('consumptionChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const dpr = window.devicePixelRatio || 1;
            const cssWidth = Math.max(320, canvas.clientWidth || 800);
            const cssHeight = Math.max(220, canvas.clientHeight || 280);
            canvas.width = Math.floor(cssWidth * dpr);
            canvas.height = Math.floor(cssHeight * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, cssWidth, cssHeight);

            const padding = 18;
            const leftLabel = 180;
            const rightLabel = 110;
            const chartX = padding + leftLabel;
            const chartY = padding;
            const chartW = Math.max(120, cssWidth - leftLabel - rightLabel - padding * 2);
            const chartH = cssHeight - padding * 2;
            const ms = state.milestones || [];

            if (!ms.length) {
                ctx.fillStyle = '#6b7280';
                ctx.font = '14px Poppins, sans-serif';
                ctx.fillText('Add source funds to generate the consumption graph.', padding, padding + 20);
                return;
            }

            const maxVal = Math.max(1, ...ms.map((m) => Number(m.allocated || 0)));
            const gap = 10;
            const barH = Math.max(16, Math.floor((chartH - gap * (ms.length - 1)) / ms.length));

            ms.forEach((m, i) => {
                const y = chartY + i * (barH + gap);
                const allocated = Number(m.allocated || 0);
                const spent = getSpentForMilestone(state, m.id);
                const allocW = (allocated / maxVal) * chartW;
                const spentW = allocated > 0 ? Math.min(allocW, (spent / allocated) * allocW) : 0;

                ctx.fillStyle = '#ecf2ff';
                ctx.fillRect(chartX, y, chartW, barH);
                ctx.fillStyle = '#3b82f6';
                ctx.fillRect(chartX, y, allocW, barH);
                ctx.fillStyle = '#16a34a';
                ctx.fillRect(chartX, y, spentW, barH);

                ctx.fillStyle = '#1f3f65';
                ctx.font = '600 12px Poppins, sans-serif';
                ctx.fillText(String(m.name || ''), padding, y + barH / 2 + 4);

                ctx.fillStyle = '#0f172a';
                ctx.font = '500 11px Poppins, sans-serif';
                ctx.fillText(currency(allocated), chartX + 6, y + barH / 2 + 4);

                ctx.fillStyle = '#0f766e';
                ctx.fillText(currency(spent), chartX + chartW + 10, y + barH / 2 + 4);
            });
        }

        function renderAll(stateArg) {
            const state = stateArg || loadState();
            const globalBudget = byId('globalBudget');
            if (globalBudget) globalBudget.value = state.globalBudget || '';
            renderMilestones(state);
            renderExpenses(state);
            populateSourceSelect(state);
            renderSummary(state);
            drawChart(state);
        }

        function addSource() {
            const nameEl = byId('milestoneName');
            const allocEl = byId('milestoneAlloc');
            if (!nameEl || !allocEl) return;
            const name = nameEl.value.trim();
            const allocated = Math.max(0, Number(allocEl.value || 0));
            if (!name) return;
            const state = loadState();
            state.milestones.push({ id: uid('m'), name: name, allocated: allocated });
            saveState(state);
            nameEl.value = '';
            allocEl.value = '';
            renderAll(state);
        }

        function addExpenseEntry() {
            const sourceEl = byId('expenseMilestone');
            const amountEl = byId('expenseAmount');
            const descEl = byId('expenseDesc');
            if (!sourceEl || !amountEl || !descEl) return;
            const milestoneId = sourceEl.value;
            const amount = Math.max(0, Number(amountEl.value || 0));
            const description = descEl.value.trim();
            if (!milestoneId || !amount) return;
            const state = loadState();
            state.expenses.push({
                id: uid('e'),
                milestoneId: milestoneId,
                amount: amount,
                description: description,
                date: new Date().toISOString()
            });
            saveState(state);
            sourceEl.value = '';
            amountEl.value = '';
            descEl.value = '';
            renderAll(state);
        }

        function exportCsv() {
            const state = loadState();
            const rows = [];
            rows.push(['type', 'source_id', 'source_name', 'allocated', 'expense_id', 'expense_amount', 'description', 'date'].join(','));
            state.milestones.forEach((m) => {
                const expenses = state.expenses.filter((e) => e.milestoneId === m.id);
                if (!expenses.length) {
                    rows.push(['source', m.id, '"' + String(m.name).replace(/"/g, '""') + '"', m.allocated, '', '', '', ''].join(','));
                    return;
                }
                expenses.forEach((e) => {
                    rows.push(['expense', m.id, '"' + String(m.name).replace(/"/g, '""') + '"', m.allocated, e.id, e.amount, '"' + String(e.description || '').replace(/"/g, '""') + '"', e.date].join(','));
                });
            });
            const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const href = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = href;
            a.download = 'budget-resources-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(href);
        }

        function importFromProject() {
            const urlCandidates = [];
            if (typeof window.getApiUrl === 'function') {
                urlCandidates.push(window.getApiUrl('admin/budget_resources.php?action=load_projects'));
            }
            urlCandidates.push('budget_resources.php?action=load_projects');
            urlCandidates.push('/admin/budget_resources.php?action=load_projects');

            const tryFetch = function (idx) {
                if (idx >= urlCandidates.length) {
                    return Promise.reject(new Error('Unable to reach project API.'));
                }
                return fetch(urlCandidates[idx], { credentials: 'same-origin' })
                    .then((res) => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .catch(() => tryFetch(idx + 1));
            };

            tryFetch(0)
                .then((projects) => {
                    if (!Array.isArray(projects) || !projects.length) {
                        alert('No projects available to import.');
                        return;
                    }
                    const project = projects[0];
                    const state = loadState();
                    state.globalBudget = Number(project.budget || 0);
                    saveState(state);
                    renderAll(state);
                    alert('Imported budget from project: ' + (project.name || project.code || 'Project'));
                })
                .catch((error) => {
                    console.error(error);
                    alert('Failed to import budget from project list.');
                });
        }

        function init() {
            if (booted) return;
            booted = true;

            const required = ['milestoneForm', 'expenseForm', 'milestonesTable', 'expensesTable', 'consumptionChart'];
            for (let i = 0; i < required.length; i++) {
                if (!byId(required[i])) {
                    console.warn('Budget module missing element:', required[i]);
                    return;
                }
            }

            const milestoneForm = byId('milestoneForm');
            const expenseForm = byId('expenseForm');
            const addMilestone = byId('addMilestone');
            const addExpense = byId('addExpense');
            const globalBudget = byId('globalBudget');
            const btnExport = byId('btnExportBudget');
            const btnImport = byId('btnImport');

            if (milestoneForm) {
                milestoneForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    addSource();
                }, true);
            }
            if (expenseForm) {
                expenseForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    addExpenseEntry();
                }, true);
            }

            if (addMilestone) addMilestone.addEventListener('click', addSource);
            if (addExpense) addExpense.addEventListener('click', addExpenseEntry);

            if (globalBudget) {
                globalBudget.addEventListener('change', function () {
                    const state = loadState();
                    state.globalBudget = Math.max(0, Number(this.value || 0));
                    saveState(state);
                    renderAll(state);
                });
            }

            if (btnExport) btnExport.addEventListener('click', exportCsv);
            if (btnImport) btnImport.addEventListener('click', importFromProject);

            window.addEventListener('resize', function () {
                drawChart(loadState());
            });

            renderAll(loadState());
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
</body>
</html>


















