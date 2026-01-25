<?php
// Import security functions
require dirname(__DIR__, 2) . '/session-auth.php';
// Database connection
require dirname(__DIR__, 2) . '/database.php';
require dirname(__DIR__, 2) . '/config-path.php';

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
    <link rel="stylesheet" href="/assets/style.css" />
    <?php echo get_app_config_script(); ?>
    <script src="../security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        .nav-item-group { position: relative; display: inline-block; }
        .nav-main-item { display: flex !important; align-items: center; gap: 8px; padding: 10px 16px !important; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: all 0.2s ease; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .nav-main-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px !important; }
        .nav-main-item.active { background: #eff6ff; color: #1e40af; font-weight: 600; }
        .nav-icon { width: 20px; height: 20px; display: inline-block; margin-right: 4px; }
        .dropdown-arrow { display: inline-block; margin-left: 4px; transition: transform 0.3s ease; }
        .nav-item-group.open .dropdown-arrow { transform: rotate(180deg); }
        .nav-submenu { position: absolute; top: 100%; left: 0; background: white; border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); min-width: 220px; margin-top: 8px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; overflow: hidden; }
        .nav-item-group.open .nav-submenu { opacity: 1; visibility: visible; transform: translateY(0); }
        .nav-submenu-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; border-left: 3px solid transparent; white-space: nowrap; }
        .nav-submenu-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px; border-left-color: #3b82f6; }
        .nav-submenu-item.active { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; font-weight: 600; }
        .submenu-icon { font-size: 1.1rem; flex-shrink: 0; }
        .nav-submenu-item span:last-child { flex: 1; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="../project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../project-registration/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="../project-registration/project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="../project-registration/registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php" class="active"><img src="budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="../contractors/contractors.php" class="nav-main-item" id="contractorsToggle"><img src="../contractors/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="../contractors/contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="../contractors/registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>Registered Contractors</span></a>
                </div>
            </div>
            <a href="../project-prioritization/project-prioritization.php"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome <?php echo isset($_SESSION['employee_name']) ? $_SESSION['employee_name'] : 'Admin'; ?></span>
            <a href="../index.php" class="nav-logout">Logout</a>
        </div>
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
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
                <button type="submit" id="addMilestone">Add Source</button>
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
                <button type="submit" id="addExpense">Add Expense</button>
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

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script>
        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });
    </script>

    <script src="../shared-data.js?v=1"></script>
    <script src="budget-resources.js?v=2"></script>
</body>
</html>
