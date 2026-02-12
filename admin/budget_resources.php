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

function budget_json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
}

function budget_sync_spent(mysqli $db): void {
    $sql = "UPDATE milestones m
            LEFT JOIN (
                SELECT milestoneId, COALESCE(SUM(amount), 0) AS total_spent
                FROM expenses
                GROUP BY milestoneId
            ) e ON e.milestoneId = m.id
            SET m.spent = COALESCE(e.total_spent, 0)";
    $db->query($sql);
}

// Handle API requests first (before rendering HTML)
$action = $_REQUEST['action'] ?? null;
if ($action) {
    try {
        if ($action === 'load_projects') {
            $result = $db->query("SELECT id, code, name, budget FROM projects ORDER BY created_at DESC");
            $projects = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $projects[] = $row;
                }
                $result->free();
            }
            budget_json_response($projects);
            $db->close();
            exit;
        }

        if ($action === 'load_budget_state') {
            budget_sync_spent($db);

            $globalBudget = 0.0;
            $settingsRes = $db->query("SELECT total_budget FROM project_settings ORDER BY id ASC LIMIT 1");
            if ($settingsRes && ($settings = $settingsRes->fetch_assoc())) {
                $globalBudget = (float) ($settings['total_budget'] ?? 0);
                $settingsRes->free();
            }

            $milestones = [];
            $milestoneRes = $db->query("SELECT id, name, allocated, spent FROM milestones ORDER BY id ASC");
            if ($milestoneRes) {
                while ($row = $milestoneRes->fetch_assoc()) {
                    $milestones[] = [
                        'id' => (int) $row['id'],
                        'name' => (string) $row['name'],
                        'allocated' => (float) ($row['allocated'] ?? 0),
                        'spent' => (float) ($row['spent'] ?? 0),
                    ];
                }
                $milestoneRes->free();
            }

            $expenses = [];
            $expenseRes = $db->query("SELECT id, milestoneId, amount, description, date FROM expenses ORDER BY date DESC, id DESC");
            if ($expenseRes) {
                while ($row = $expenseRes->fetch_assoc()) {
                    $expenses[] = [
                        'id' => (int) $row['id'],
                        'milestoneId' => (int) ($row['milestoneId'] ?? 0),
                        'amount' => (float) ($row['amount'] ?? 0),
                        'description' => (string) ($row['description'] ?? ''),
                        'date' => $row['date'] ?? null,
                    ];
                }
                $expenseRes->free();
            }

            budget_json_response([
                'success' => true,
                'data' => [
                    'globalBudget' => $globalBudget,
                    'milestones' => $milestones,
                    'expenses' => $expenses,
                ]
            ]);
            $db->close();
            exit;
        }

        if ($action === 'set_global_budget') {
            $budget = max(0, (float) ($_POST['budget'] ?? 0));
            $stmt = $db->prepare("INSERT INTO project_settings (id, total_budget) VALUES (1, ?) ON DUPLICATE KEY UPDATE total_budget = VALUES(total_budget)");
            $stmt->bind_param('d', $budget);
            $stmt->execute();
            $stmt->close();
            budget_json_response(['success' => true, 'budget' => $budget]);
            $db->close();
            exit;
        }

        if ($action === 'add_milestone') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $allocated = max(0, (float) ($_POST['allocated'] ?? 0));
            if ($name === '') {
                budget_json_response(['success' => false, 'message' => 'Source name is required.'], 422);
                $db->close();
                exit;
            }
            $stmt = $db->prepare("INSERT INTO milestones (name, allocated, spent) VALUES (?, ?, 0)");
            $stmt->bind_param('sd', $name, $allocated);
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
            budget_json_response(['success' => true, 'id' => (int) $id]);
            $db->close();
            exit;
        }

        if ($action === 'update_milestone_alloc') {
            $id = (int) ($_POST['id'] ?? 0);
            $allocated = max(0, (float) ($_POST['allocated'] ?? 0));
            $stmt = $db->prepare("UPDATE milestones SET allocated = ? WHERE id = ?");
            $stmt->bind_param('di', $allocated, $id);
            $stmt->execute();
            $stmt->close();
            budget_json_response(['success' => true]);
            $db->close();
            exit;
        }

        if ($action === 'delete_milestone') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmtExp = $db->prepare("DELETE FROM expenses WHERE milestoneId = ?");
            $stmtExp->bind_param('i', $id);
            $stmtExp->execute();
            $stmtExp->close();

            $stmt = $db->prepare("DELETE FROM milestones WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            budget_json_response(['success' => true]);
            $db->close();
            exit;
        }

        if ($action === 'add_expense') {
            $milestoneId = (int) ($_POST['milestoneId'] ?? 0);
            $amount = max(0, (float) ($_POST['amount'] ?? 0));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($milestoneId <= 0 || $amount <= 0) {
                budget_json_response(['success' => false, 'message' => 'Invalid expense data.'], 422);
                $db->close();
                exit;
            }

            $stmt = $db->prepare("INSERT INTO expenses (milestoneId, amount, description, date) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('ids', $milestoneId, $amount, $description);
            $stmt->execute();
            $stmt->close();
            budget_sync_spent($db);
            budget_json_response(['success' => true]);
            $db->close();
            exit;
        }

        if ($action === 'delete_expense') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            budget_sync_spent($db);
            budget_json_response(['success' => true]);
            $db->close();
            exit;
        }

        budget_json_response(['success' => false, 'message' => 'Unknown action.'], 400);
        $db->close();
        exit;
    } catch (Throwable $e) {
        budget_json_response(['success' => false, 'message' => $e->getMessage()], 500);
        $db->close();
        exit;
    }
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

        <div class="br-tabs" role="tablist" aria-label="Budget module sections">
            <button type="button" class="br-tab active" data-panel="budget" role="tab" aria-selected="true">Set Project Budget</button>
            <button type="button" class="br-tab" data-panel="sources" role="tab" aria-selected="false">Source Funds</button>
            <button type="button" class="br-tab" data-panel="expenses" role="tab" aria-selected="false">Track Expenses</button>
        </div>

        <div id="panel-budget" class="br-panel active">
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
        </div>

        <div id="panel-sources" class="br-panel">
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
        </div>

        <div id="panel-expenses" class="br-panel">
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

        .main-content .br-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .main-content .br-tab {
            border: 1px solid #cbddef;
            background: #fff;
            color: #36597d;
            border-radius: 999px;
            min-height: 36px;
            padding: 0 12px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            transition: all 0.18s ease;
        }

        .main-content .br-tab:hover {
            background: #eef5ff;
            border-color: #9fc0e3;
            color: #1f4f83;
        }

        .main-content .br-tab.active {
            color: #fff;
            border-color: #1d4ed8;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.22);
        }

        .main-content .br-panel {
            display: none;
            animation: fadeInPanel 0.22s ease;
        }

        .main-content .br-panel.active {
            display: block;
        }

        @keyframes fadeInPanel {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
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
            .main-content .br-tabs {
                display: grid;
                grid-template-columns: 1fr;
            }

            .main-content .br-tab {
                width: 100%;
                border-radius: 10px;
            }

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

        let booted = false;
        let activePanel = 'budget';
        let stateCache = { globalBudget: 0, milestones: [], expenses: [] };
        const API_BASE = 'budget_resources.php';

        function byId(id) { return document.getElementById(id); }

        function switchPanel(panelKey) {
            const tabs = document.querySelectorAll('.br-tab[data-panel]');
            const panels = document.querySelectorAll('.br-panel[id^="panel-"]');
            tabs.forEach((tab) => {
                const isActive = tab.getAttribute('data-panel') === panelKey;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.id === 'panel-' + panelKey;
                panel.classList.toggle('active', isActive);
            });
            activePanel = panelKey;
        }

        function initSectionTabs() {
            const tabs = document.querySelectorAll('.br-tab[data-panel]');
            if (!tabs.length) return;
            tabs.forEach((tab) => {
                tab.addEventListener('click', function () {
                    switchPanel(this.getAttribute('data-panel') || 'budget');
                });
            });
            switchPanel(activePanel);
        }

        function currency(value) {
            const num = Number(value || 0);
            return 'PHP ' + num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function getApiUrlLocal(action) {
            if (typeof window.getApiUrl === 'function') {
                return window.getApiUrl('admin/' + API_BASE + '?action=' + encodeURIComponent(action));
            }
            return API_BASE + '?action=' + encodeURIComponent(action);
        }

        async function apiGet(action) {
            const url = getApiUrlLocal(action);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }

        async function apiPost(action, data) {
            const url = getApiUrlLocal(action);
            const body = new URLSearchParams();
            Object.keys(data || {}).forEach((k) => body.set(k, String(data[k] ?? '')));
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (json && json.success === false) throw new Error(json.message || 'Request failed');
            return json;
        }

        async function refreshState() {
            const json = await apiGet('load_budget_state');
            if (!json || json.success === false || !json.data) {
                throw new Error((json && json.message) ? json.message : 'Failed to load budget state');
            }
            stateCache = {
                globalBudget: Number(json.data.globalBudget || 0),
                milestones: Array.isArray(json.data.milestones) ? json.data.milestones : [],
                expenses: Array.isArray(json.data.expenses) ? json.data.expenses : []
            };
            return stateCache;
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
                input.addEventListener('change', async function () {
                    const id = this.getAttribute('data-id');
                    const value = Math.max(0, Number(this.value || 0));
                    try {
                        await apiPost('update_milestone_alloc', { id: id, allocated: value });
                        await renderAllFromServer();
                    } catch (err) {
                        console.error(err);
                        alert('Failed to update source allocation.');
                    }
                });
            });

            tbody.querySelectorAll('.btnDeleteSource').forEach((btn) => {
                btn.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    try {
                        await apiPost('delete_milestone', { id: id });
                        await renderAllFromServer();
                    } catch (err) {
                        console.error(err);
                        alert('Failed to delete source.');
                    }
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
                btn.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    try {
                        await apiPost('delete_expense', { id: id });
                        await renderAllFromServer();
                    } catch (err) {
                        console.error(err);
                        alert('Failed to delete expense.');
                    }
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
            const state = stateArg || stateCache;
            const globalBudget = byId('globalBudget');
            if (globalBudget) globalBudget.value = state.globalBudget || '';
            renderMilestones(state);
            renderExpenses(state);
            populateSourceSelect(state);
            renderSummary(state);
            drawChart(state);
        }

        async function renderAllFromServer() {
            const state = await refreshState();
            renderAll(state);
        }

        async function addSource() {
            const nameEl = byId('milestoneName');
            const allocEl = byId('milestoneAlloc');
            if (!nameEl || !allocEl) return;
            const name = nameEl.value.trim();
            const allocated = Math.max(0, Number(allocEl.value || 0));
            if (!name) return;
            try {
                await apiPost('add_milestone', { name: name, allocated: allocated });
                nameEl.value = '';
                allocEl.value = '';
                await renderAllFromServer();
            } catch (err) {
                console.error(err);
                alert('Failed to add source fund.');
            }
        }

        async function addExpenseEntry() {
            const sourceEl = byId('expenseMilestone');
            const amountEl = byId('expenseAmount');
            const descEl = byId('expenseDesc');
            if (!sourceEl || !amountEl || !descEl) return;
            const milestoneId = sourceEl.value;
            const amount = Math.max(0, Number(amountEl.value || 0));
            const description = descEl.value.trim();
            if (!milestoneId || !amount) return;
            try {
                await apiPost('add_expense', {
                    milestoneId: milestoneId,
                    amount: amount,
                    description: description
                });
                sourceEl.value = '';
                amountEl.value = '';
                descEl.value = '';
                await renderAllFromServer();
            } catch (err) {
                console.error(err);
                alert('Failed to add expense.');
            }
        }

        function exportCsv() {
            const state = stateCache;
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
                    return apiPost('set_global_budget', { budget: Number(project.budget || 0) })
                        .then(() => renderAllFromServer())
                        .then(() => {
                            alert('Imported budget from project: ' + (project.name || project.code || 'Project'));
                        });
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
                globalBudget.addEventListener('change', async function () {
                    try {
                        await apiPost('set_global_budget', { budget: Math.max(0, Number(this.value || 0)) });
                        await renderAllFromServer();
                    } catch (err) {
                        console.error(err);
                        alert('Failed to save global budget.');
                    }
                });
            }

            if (btnExport) btnExport.addEventListener('click', exportCsv);
            if (btnImport) btnImport.addEventListener('click', importFromProject);

            window.addEventListener('resize', function () {
                drawChart(stateCache);
            });

            initSectionTabs();
            renderAllFromServer().catch((err) => {
                console.error(err);
                alert('Failed to load budget data from database.');
            });
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


















