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
    
    
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260212d">
</head>
<body>
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
        <div class="ac-723b1a7b">
            <a href="/admin/logout.php" class="ac-bb30b003">
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
    <script src="../assets/js/admin.js"></script>
</body>
</html>















