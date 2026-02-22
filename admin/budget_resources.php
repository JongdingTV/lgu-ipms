<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Protect page
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['admin','department_admin','super_admin']);
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

function budget_projects_order_sql(mysqli $db): string {
    static $hasCreatedAt = null;
    if ($hasCreatedAt === null) {
        $hasCreatedAt = false;
        $check = $db->prepare(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'projects'
               AND COLUMN_NAME = 'created_at'
             LIMIT 1"
        );
        if ($check) {
            $check->execute();
            $res = $check->get_result();
            $hasCreatedAt = $res && $res->num_rows > 0;
            if ($res) {
                $res->free();
            }
            $check->close();
        }
    }
    return $hasCreatedAt ? 'created_at DESC' : 'id DESC';
}

function budget_sync_projects_to_milestones(mysqli $db): void {
    $projects = [];
    $orderBy = budget_projects_order_sql($db);
    $res = $db->query("SELECT id, name, COALESCE(budget, 0) AS budget FROM projects ORDER BY {$orderBy}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $projects[] = [
                'name' => $name,
                'budget' => max(0, (float)($row['budget'] ?? 0)),
            ];
        }
        $res->free();
    }

    $milestoneByName = [];
    $msRes = $db->query("SELECT id, name FROM milestones ORDER BY id ASC");
    if ($msRes) {
        while ($row = $msRes->fetch_assoc()) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '' && !isset($milestoneByName[$name])) {
                $milestoneByName[$name] = (int)$row['id'];
            }
        }
        $msRes->free();
    }

    $insertStmt = $db->prepare("INSERT INTO milestones (name, allocated, spent) VALUES (?, ?, 0)");
    $updateStmt = $db->prepare("UPDATE milestones SET allocated = ? WHERE id = ?");

    foreach ($projects as $project) {
        $name = $project['name'];
        $allocated = $project['budget'];
        if (isset($milestoneByName[$name])) {
            $id = (int)$milestoneByName[$name];
            if ($updateStmt) {
                $updateStmt->bind_param('di', $allocated, $id);
                $updateStmt->execute();
            }
            continue;
        }
        if ($insertStmt) {
            $insertStmt->bind_param('sd', $name, $allocated);
            $insertStmt->execute();
        }
    }

    if ($insertStmt) {
        $insertStmt->close();
    }
    if ($updateStmt) {
        $updateStmt->close();
    }
}

// Handle API requests first (before rendering HTML)
$action = $_REQUEST['action'] ?? null;
if ($action) {
    try {
        if ($action === 'load_projects') {
            $orderBy = budget_projects_order_sql($db);
            $result = $db->query("SELECT id, code, name, status, COALESCE(budget, 0) AS budget FROM projects ORDER BY {$orderBy}");
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
            budget_sync_projects_to_milestones($db);
            budget_sync_spent($db);

            $globalBudget = 0.0;
            $totalRes = $db->query("SELECT COALESCE(SUM(budget), 0) AS total_budget FROM projects");
            if ($totalRes && ($totals = $totalRes->fetch_assoc())) {
                $globalBudget = (float)($totals['total_budget'] ?? 0);
                $totalRes->free();
            }

            $milestones = [];
            $milestoneRes = $db->query(
                "SELECT m.id, m.name, m.allocated, m.spent
                 FROM milestones m
                 INNER JOIN (SELECT DISTINCT name FROM projects WHERE TRIM(name) <> '') p ON p.name = m.name
                 ORDER BY m.id ASC"
            );
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
            $expenseRes = $db->query(
                "SELECT e.id, e.milestoneId, e.amount, e.description, e.date
                 FROM expenses e
                 INNER JOIN milestones m ON m.id = e.milestoneId
                 INNER JOIN (SELECT DISTINCT name FROM projects WHERE TRIM(name) <> '') p ON p.name = m.name
                 ORDER BY e.date DESC, e.id DESC"
            );
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

            budget_sync_spent($db);
            $checkStmt = $db->prepare("SELECT allocated, spent FROM milestones WHERE id = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('i', $milestoneId);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if (!$checkRes || $checkRes->num_rows === 0) {
                    if ($checkRes) {
                        $checkRes->free();
                    }
                    $checkStmt->close();
                    budget_json_response(['success' => false, 'message' => 'Selected project budget does not exist.'], 422);
                    $db->close();
                    exit;
                }
                $row = $checkRes->fetch_assoc();
                $allocated = (float)($row['allocated'] ?? 0);
                $spent = (float)($row['spent'] ?? 0);
                $remaining = max(0, $allocated - $spent);
                $checkRes->free();
                $checkStmt->close();
                if ($amount > $remaining) {
                    budget_json_response(['success' => false, 'message' => 'Expense exceeds remaining project budget.'], 422);
                    $db->close();
                    exit;
                }
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
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
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
            <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
                <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php" class="active"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Add Engineer</span></a>
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
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
            <p>Budgets are synced from registered projects. Track expenses per project and monitor remaining funds.</p>
        </div>

        <div class="br-tabs" role="tablist" aria-label="Budget module sections">
            <button type="button" class="br-tab active" data-panel="sources" role="tab" aria-selected="true">Project Budgets</button>
            <button type="button" class="br-tab" data-panel="expenses" role="tab" aria-selected="false">Track Expenses</button>
        </div>

        <div id="panel-sources" class="br-panel active">
        <div class="allocation-section">
            <h2>Project Budget Allocation</h2>
            <div class="br-table-tools">
                <input id="searchSources" type="search" placeholder="Search registered projects...">
            </div>
            <div class="table-wrap">
                <table id="milestonesTable" class="table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Amount (&#8369;)</th>
                            <th>Used (&#8369;)</th>
                            <th>Remaining (&#8369;)</th>
                            <th>% Consumed</th>
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
                    <option value="">Select project</option>
                </select>
                <input id="expenseAmount" type="number" min="0" step="0.01" placeholder="Amount Ã¢â€šÂ±" required>
                <input id="expenseDesc" type="text" placeholder="Description (optional)">
                <button type="button" id="addExpense">Add Expense</button>
            </form>
            <div class="br-table-tools">
                <input id="searchExpenses" type="search" placeholder="Search expenses by project or description...">
            </div>
            <div class="table-wrap">
                <table id="expensesTable" class="table">
                    <thead>
                        <tr><th>Date</th><th>Project</th><th>Description</th><th>Amount (Ã¢â€šÂ±)</th><th>Actions</th></tr>
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
                    <div id="summaryAllocated">Ã¢â€šÂ±0</div>
                    <small>Allocated</small>
                </div>
                <div class="stat">
                    <div id="summarySpent">Ã¢â€šÂ±0</div>
                    <small>Spent</small>
                </div>
                <div class="stat">
                    <div id="summaryRemaining">Ã¢â€šÂ±0</div>
                    <small>Remaining</small>
                </div>
                <div class="stat">
                    <div id="summaryConsumption">0%</div>
                    <small>Consumption</small>
                </div>
            </div>
            <div class="br-health-card">
                <div class="br-health-head">
                    <strong>Budget Health</strong>
                    <span id="budgetHealthTag" class="br-health-tag normal">Normal</span>
                </div>
                <div class="br-health-bar">
                    <div id="budgetHealthFill" class="br-health-fill" style="width:0%"></div>
                </div>
                <small id="budgetHealthText" class="br-health-text">No budget activity yet.</small>
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

        .main-content .br-table-tools {
            margin-bottom: 10px;
        }

        .main-content .br-table-tools input {
            width: 100%;
            min-height: 38px;
            border: 1px solid #cddced;
            border-radius: 10px;
            padding: 0 12px;
            color: #1f3858;
            background: #fff;
        }

        .main-content .br-table-tools input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
            outline: none;
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

        .main-content .br-btn-delete {
            border: 1px solid #fecaca;
            background: linear-gradient(135deg, #fff1f2, #ffe4e6);
            color: #b91c1c;
            border-radius: 10px;
            min-height: 32px;
            padding: 0 10px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            transition: all 0.18s ease;
        }

        .main-content .br-btn-delete:hover {
            background: linear-gradient(135deg, #fee2e2, #fecdd3);
            border-color: #fda4af;
            color: #991b1b;
            transform: translateY(-1px);
            box-shadow: 0 8px 14px rgba(220, 38, 38, 0.18);
        }

        .main-content .br-btn-delete:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);
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

        .main-content .br-health-card {
            border: 1px solid #d9e6f4;
            border-radius: 12px;
            background: linear-gradient(165deg, #ffffff, #f7fbff);
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .main-content .br-health-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .main-content .br-health-head strong {
            color: #1d3f64;
            font-size: 0.88rem;
        }

        .main-content .br-health-tag {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid #d0dbe8;
            color: #3f5f83;
            background: #f8fbff;
        }

        .main-content .br-health-tag.good {
            border-color: #86efac;
            background: #ecfdf5;
            color: #166534;
        }

        .main-content .br-health-tag.warn {
            border-color: #facc15;
            background: #fefce8;
            color: #854d0e;
        }

        .main-content .br-health-tag.danger {
            border-color: #fca5a5;
            background: #fef2f2;
            color: #991b1b;
        }

        .main-content .br-health-bar {
            width: 100%;
            height: 10px;
            background: #e7eef8;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 7px;
        }

        .main-content .br-health-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #22c55e 0%, #3b82f6 60%, #2563eb 100%);
        }

        .main-content .br-health-text {
            color: #5a7697;
            font-size: 0.78rem;
            font-weight: 600;
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

        .br-toast-stack {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: min(92vw, 360px);
        }

        .br-toast {
            border-radius: 12px;
            border: 1px solid #cfddee;
            background: #fff;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.18);
            padding: 10px 12px;
            animation: brToastIn 0.2s ease;
        }

        .br-toast-title {
            font-size: 0.84rem;
            font-weight: 700;
            color: #1f3f65;
            margin-bottom: 2px;
        }

        .br-toast-message {
            font-size: 0.78rem;
            color: #4e6784;
        }

        .br-toast.success {
            border-left: 4px solid #16a34a;
        }

        .br-toast.error {
            border-left: 4px solid #dc2626;
        }

        .br-toast.info {
            border-left: 4px solid #2563eb;
        }

        @keyframes brToastIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .br-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 10000;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .br-confirm-overlay.show {
            display: flex;
        }

        .br-confirm-dialog {
            width: min(460px, 100%);
            border-radius: 16px;
            border: 1px solid #fecaca;
            background: linear-gradient(165deg, #ffffff, #fff7f7);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.3);
            padding: 16px;
        }

        .br-confirm-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .br-confirm-icon {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .br-confirm-title {
            margin: 0;
            color: #7f1d1d;
            font-size: 1.02rem;
        }

        .br-confirm-message {
            margin: 0 0 10px;
            color: #4b5563;
            line-height: 1.45;
        }

        .br-confirm-item {
            border: 1px solid #fecaca;
            background: #fff;
            color: #991b1b;
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .br-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .br-confirm-cancel,
        .br-confirm-delete {
            min-height: 38px;
            border-radius: 10px;
            padding: 0 14px;
            font-weight: 700;
        }

        .br-confirm-cancel {
            border: 1px solid #cfd8e3;
            background: #eef2f7;
            color: #355678;
        }

        .br-confirm-delete {
            border: 1px solid #dc2626;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
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
        let activePanel = 'sources';
        let stateCache = { milestones: [], expenses: [] };
        const API_BASE = 'budget_resources.php';

        function byId(id) { return document.getElementById(id); }

        function notify(title, message, type) {
            const kind = type || 'info';
            let stack = document.querySelector('.br-toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.className = 'br-toast-stack';
                document.body.appendChild(stack);
            }

            const toast = document.createElement('div');
            toast.className = 'br-toast ' + kind;
            toast.innerHTML = '<div class="br-toast-title"></div><div class="br-toast-message"></div>';
            toast.querySelector('.br-toast-title').textContent = title;
            toast.querySelector('.br-toast-message').textContent = message;
            stack.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(6px)';
                toast.style.transition = 'all 0.2s ease';
                setTimeout(() => toast.remove(), 220);
            }, 2600);
        }

        function ensureConfirmDialog() {
            let overlay = document.querySelector('.br-confirm-overlay');
            if (overlay) return overlay;
            overlay = document.createElement('div');
            overlay.className = 'br-confirm-overlay';
            overlay.innerHTML = [
                '<div class="br-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="brConfirmTitle">',
                '  <div class="br-confirm-head">',
                '    <span class="br-confirm-icon">!</span>',
                '    <h3 id="brConfirmTitle" class="br-confirm-title">Delete item?</h3>',
                '  </div>',
                '  <p class="br-confirm-message">This action cannot be undone.</p>',
                '  <div class="br-confirm-item" id="brConfirmItem">Item</div>',
                '  <div class="br-confirm-actions">',
                '    <button type="button" class="br-confirm-cancel">Cancel</button>',
                '    <button type="button" class="br-confirm-delete">Delete Permanently</button>',
                '  </div>',
                '</div>'
            ].join('');
            document.body.appendChild(overlay);
            return overlay;
        }

        function confirmDelete(itemLabel, message) {
            const overlay = ensureConfirmDialog();
            const msgEl = overlay.querySelector('.br-confirm-message');
            const itemEl = overlay.querySelector('#brConfirmItem');
            const cancelBtn = overlay.querySelector('.br-confirm-cancel');
            const deleteBtn = overlay.querySelector('.br-confirm-delete');

            if (msgEl) msgEl.textContent = message || 'This action cannot be undone.';
            if (itemEl) itemEl.textContent = itemLabel || 'Selected item';

            return new Promise((resolve) => {
                const close = (result) => {
                    overlay.classList.remove('show');
                    cancelBtn.removeEventListener('click', onCancel);
                    deleteBtn.removeEventListener('click', onDelete);
                    overlay.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onEscape);
                    resolve(result);
                };
                const onCancel = () => close(false);
                const onDelete = () => close(true);
                const onBackdrop = (e) => { if (e.target === overlay) close(false); };
                const onEscape = (e) => { if (e.key === 'Escape') close(false); };

                cancelBtn.addEventListener('click', onCancel);
                deleteBtn.addEventListener('click', onDelete);
                overlay.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onEscape);

                overlay.classList.add('show');
                deleteBtn.focus();
            });
        }

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
                    switchPanel(this.getAttribute('data-panel') || 'sources');
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
            const q = (byId('searchSources')?.value || '').trim().toLowerCase();

            state.milestones.forEach((ms) => {
                if (q && String(ms.name || '').toLowerCase().indexOf(q) === -1) return;
                const spent = getSpentForMilestone(state, ms.id);
                const allocated = Number(ms.allocated || 0);
                const remaining = Math.max(0, allocated - spent);
                const consumed = allocated ? Math.round((spent / allocated) * 100) : 0;

                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + String(ms.name || '') + '</td>',
                    '<td>' + currency(allocated) + '</td>',
                    '<td>' + currency(spent) + '</td>',
                    '<td>' + currency(remaining) + '</td>',
                    '<td>' + consumed + '%</td>'
                ].join('');
                tbody.appendChild(tr);
            });
        }

        function renderExpenses(state) {
            const tbody = document.querySelector('#expensesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            const q = (byId('searchExpenses')?.value || '').trim().toLowerCase();

            state.expenses.slice().reverse().forEach((exp) => {
                const ms = state.milestones.find((m) => m.id === exp.milestoneId);
                const hay = ((ms ? ms.name : '') + ' ' + String(exp.description || '')).toLowerCase();
                if (q && hay.indexOf(q) === -1) return;
                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + new Date(exp.date || Date.now()).toLocaleString() + '</td>',
                    '<td>' + (ms ? ms.name : '(project removed)') + '</td>',
                    '<td>' + String(exp.description || '') + '</td>',
                    '<td>' + currency(exp.amount) + '</td>',
                    '<td><button class="br-btn-delete btnDeleteExpense" type="button" data-id="' + exp.id + '" data-desc="' + String(exp.description || '').replace(/"/g, '&quot;') + '">Delete</button></td>'
                ].join('');
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btnDeleteExpense').forEach((btn) => {
                btn.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    const desc = this.getAttribute('data-desc') || 'Expense entry';
                    const label = desc.trim() ? desc : 'Expense entry';
                    const ok = await confirmDelete(label, 'Delete this expense record permanently?');
                    if (!ok) return;
                    try {
                        await apiPost('delete_expense', { id: id });
                        await renderAllFromServer();
                        notify('Deleted', 'Expense removed successfully.', 'success');
                    } catch (err) {
                        console.error(err);
                        notify('Delete Failed', 'Failed to delete expense.', 'error');
                    }
                });
            });
        }

        function populateSourceSelect(state) {
            const select = byId('expenseMilestone');
            if (!select) return;
            select.innerHTML = '<option value="">Select project</option>';
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
            const remaining = Math.max(0, allocated - spent);
            const base = allocated || 0;
            const consumption = base ? Math.round((spent / base) * 100) : 0;

            const allocatedEl = byId('summaryAllocated');
            const spentEl = byId('summarySpent');
            const remainingEl = byId('summaryRemaining');
            const consumptionEl = byId('summaryConsumption');
            if (allocatedEl) allocatedEl.textContent = currency(allocated);
            if (spentEl) spentEl.textContent = currency(spent);
            if (remainingEl) remainingEl.textContent = currency(remaining);
            if (consumptionEl) consumptionEl.textContent = consumption + '%';

            const healthFill = byId('budgetHealthFill');
            const healthTag = byId('budgetHealthTag');
            const healthText = byId('budgetHealthText');
            if (healthFill) healthFill.style.width = Math.max(0, Math.min(100, consumption)) + '%';
            if (healthTag && healthText) {
                healthTag.className = 'br-health-tag';
                if (consumption >= 90) {
                    healthTag.classList.add('danger');
                    healthTag.textContent = 'Critical';
                    healthText.textContent = 'Budget is near or over limit. Consider immediate review.';
                } else if (consumption >= 70) {
                    healthTag.classList.add('warn');
                    healthTag.textContent = 'Warning';
                    healthText.textContent = 'Budget usage is high. Monitor next expenses closely.';
                } else if (base > 0) {
                    healthTag.classList.add('good');
                    healthTag.textContent = 'Healthy';
                    healthText.textContent = 'Budget is under control with safe remaining balance.';
                } else {
                    healthTag.classList.add('normal');
                    healthTag.textContent = 'Normal';
                    healthText.textContent = 'No budget activity yet.';
                }
            }
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
                ctx.fillText('Register projects with estimated budget to generate the graph.', padding, padding + 20);
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

        async function addExpenseEntry() {
            const sourceEl = byId('expenseMilestone');
            const amountEl = byId('expenseAmount');
            const descEl = byId('expenseDesc');
            if (!sourceEl || !amountEl || !descEl) return;
            const milestoneId = sourceEl.value;
            const amount = Math.max(0, Number(amountEl.value || 0));
            const description = descEl.value.trim();
            if (!milestoneId || !amount) return;
            const selected = stateCache.milestones.find((m) => String(m.id) === String(milestoneId));
            if (!selected) {
                notify('Invalid Project', 'Selected project budget is not available.', 'error');
                return;
            }
            const allocated = Number(selected.allocated || 0);
            const spent = getSpentForMilestone(stateCache, selected.id);
            const remaining = Math.max(0, allocated - spent);
            if (amount > remaining) {
                notify('Budget Exceeded', 'Expense exceeds remaining budget for this project.', 'error');
                return;
            }
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
                notify('Add Failed', 'Failed to add expense.', 'error');
            }
        }

        function exportCsv() {
            const state = stateCache;
            const rows = [];
            rows.push(['type', 'project_id', 'project_name', 'allocated', 'expense_id', 'expense_amount', 'description', 'date'].join(','));
            state.milestones.forEach((m) => {
                const expenses = state.expenses.filter((e) => e.milestoneId === m.id);
                if (!expenses.length) {
                    rows.push(['project', m.id, '"' + String(m.name).replace(/"/g, '""') + '"', m.allocated, '', '', '', ''].join(','));
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

        function init() {
            if (booted) return;
            booted = true;

            const required = ['expenseForm', 'milestonesTable', 'expensesTable', 'consumptionChart'];
            for (let i = 0; i < required.length; i++) {
                if (!byId(required[i])) {
                    console.warn('Budget module missing element:', required[i]);
                    return;
                }
            }

            const expenseForm = byId('expenseForm');
            const addExpense = byId('addExpense');
            const btnExport = byId('btnExportBudget');
            const searchSources = byId('searchSources');
            const searchExpenses = byId('searchExpenses');

            if (expenseForm) {
                expenseForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    addExpenseEntry();
                }, true);
            }

            if (addExpense) addExpense.addEventListener('click', addExpenseEntry);

            if (btnExport) btnExport.addEventListener('click', exportCsv);
            if (searchSources) searchSources.addEventListener('input', function () { renderMilestones(stateCache); });
            if (searchExpenses) searchExpenses.addEventListener('input', function () { renderExpenses(stateCache); });

            window.addEventListener('resize', function () {
                drawChart(stateCache);
            });

            initSectionTabs();
            renderAllFromServer().catch((err) => {
                console.error(err);
                notify('Load Failed', 'Failed to load budget data from database.', 'error');
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






