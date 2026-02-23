<?php
// Import security functions - go up 2 levels to root
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/engineer-evaluation-service.php';

// Set no-cache headers to prevent back button access to protected pages
set_no_cache_headers();

// Check authentication - redirect to login if not authenticated
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.dashboard.view', ['admin','department_admin','super_admin']);

// Check for suspicious activity (user-agent changes, etc.)
check_suspicious_activity();

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

function dashboard_projects_has_created_at(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = 'created_at'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

// Get project statistics
$totalProjects = $db->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
$inProgressProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('Approved', 'For Approval')")->fetch_assoc()['count'];
$completedProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count'];
$totalBudget = $db->query("SELECT COALESCE(SUM(budget), 0) as total FROM projects")->fetch_assoc()['total'];
$pendingFeedback = 0;
$reviewedFeedback = 0;
$addressedFeedback = 0;
$feedbackCounts = $db->query("SELECT LOWER(COALESCE(status,'')) AS status_key, COUNT(*) AS total FROM feedback GROUP BY LOWER(COALESCE(status,''))");
if ($feedbackCounts) {
    while ($row = $feedbackCounts->fetch_assoc()) {
        $key = (string) ($row['status_key'] ?? '');
        $count = (int) ($row['total'] ?? 0);
        if ($key === 'pending') $pendingFeedback = $count;
        if ($key === 'reviewed') $reviewedFeedback = $count;
        if ($key === 'addressed') $addressedFeedback = $count;
    }
    $feedbackCounts->free();
}

$priorityCounts = [
    'crucial' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0
];
$priorityRes = $db->query("SELECT LOWER(COALESCE(priority,'')) AS priority_key, COUNT(*) AS total FROM projects GROUP BY LOWER(COALESCE(priority,''))");
if ($priorityRes) {
    while ($row = $priorityRes->fetch_assoc()) {
        $key = (string) ($row['priority_key'] ?? '');
        if (array_key_exists($key, $priorityCounts)) {
            $priorityCounts[$key] = (int) ($row['total'] ?? 0);
        }
    }
    $priorityRes->free();
}

$budgetTrendPoints = [];
$trendDateExpr = dashboard_projects_has_created_at($db) ? "COALESCE(created_at, NOW())" : "COALESCE(start_date, NOW())";
$budgetTrendSql = "SELECT DATE_FORMAT(" . $trendDateExpr . ", '%Y-%m') AS period, SUM(COALESCE(budget, 0)) AS total_budget FROM projects GROUP BY DATE_FORMAT(" . $trendDateExpr . ", '%Y-%m') ORDER BY period ASC LIMIT 12";
$budgetTrendRes = $db->query($budgetTrendSql);
if ($budgetTrendRes) {
    while ($row = $budgetTrendRes->fetch_assoc()) {
        $budgetTrendPoints[] = [
            'label' => (string) ($row['period'] ?? ''),
            'value' => (float) ($row['total_budget'] ?? 0)
        ];
    }
    $budgetTrendRes->free();
}

// Get recent projects
$recentOrder = dashboard_projects_has_created_at($db) ? 'created_at DESC' : 'id DESC';
$recentProjects = $db->query("SELECT id, name, location, status, budget FROM projects ORDER BY {$recentOrder} LIMIT 5");

$distributionCompletedPct = 0;
$distributionInProgressPct = 0;
$distributionOtherPct = 0;
if ((int) $totalProjects > 0) {
    $distributionCompletedPct = (int) round(((int) $completedProjects / (int) $totalProjects) * 100);
    $distributionInProgressPct = (int) round(((int) $inProgressProjects / (int) $totalProjects) * 100);
    $distributionOtherPct = max(0, 100 - $distributionCompletedPct - $distributionInProgressPct);
}

$monthlyActivityMap = [];
$monthlyActivityKeys = [];
for ($i = 5; $i >= 0; $i--) {
    $monthlyActivityKeys[] = date('Y-m', strtotime("-{$i} months"));
}

$monthlyDateExpr = dashboard_projects_has_created_at($db) ? "COALESCE(created_at, NOW())" : "COALESCE(start_date, NOW())";
$monthlyActivitySql = "SELECT DATE_FORMAT({$monthlyDateExpr}, '%Y-%m') AS period, COUNT(*) AS total
    FROM projects
    WHERE {$monthlyDateExpr} >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT({$monthlyDateExpr}, '%Y-%m')";
$monthlyActivityRes = $db->query($monthlyActivitySql);
if ($monthlyActivityRes) {
    while ($row = $monthlyActivityRes->fetch_assoc()) {
        $period = (string) ($row['period'] ?? '');
        $monthlyActivityMap[$period] = (int) ($row['total'] ?? 0);
    }
    $monthlyActivityRes->free();
}

$monthlyActivityValues = [];
foreach ($monthlyActivityKeys as $key) {
    $monthlyActivityValues[] = (int) ($monthlyActivityMap[$key] ?? 0);
}
$monthlyActivityTotal = array_sum($monthlyActivityValues);

$maxActivity = max(1, ...$monthlyActivityValues);
$monthlyPoints = [];
foreach ($monthlyActivityValues as $index => $value) {
    $x = (int) round(($index / 5) * 320);
    $y = (int) round(110 - (($value / $maxActivity) * 100));
    $monthlyPoints[] = $x . ',' . $y;
}
$monthlyPolylinePoints = implode(' ', $monthlyPoints);

$engineerEvaluationOverview = ee_build_dashboard_lists($db);

$db->close();
?>
<html>
<head>
        
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Dashboard</title>
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
    <link rel="stylesheet" href="../assets/css/dashboard-redesign-enhanced.css">
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
            <a href="dashboard.php" class="active"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
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
            <h1>Dashboard Overview</h1>
            <p>Infrastructure Project Management System</p>
            <?php if (!empty($_SESSION['is_super_admin']) || strtolower((string)($_SESSION['employee_role'] ?? '')) === 'super_admin'): ?>
            <?php endif; ?>
        </div>

        <!-- Key Metrics Section -->
        <div class="metrics-container">
            <div class="metric-card card">
                <img src="../assets/images/admin/chart.png" alt="Total Projects" class="metric-icon">
                <div class="metric-content">
                    <h3>Total Projects</h3>
                    <p class="metric-value"><?php echo $totalProjects; ?></p>
                    <span class="metric-status">Active & Completed</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="../assets/images/admin/sandclock.png" alt="In Progress" class="metric-icon">
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value"><?php echo $inProgressProjects; ?></p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card card">
                <img src="../assets/images/admin/check.png" alt="Completed" class="metric-icon">
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value"><?php echo $completedProjects; ?></p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card card" id="budgetCard" data-budget="<?php echo number_format($totalBudget, 2); ?>">
                <img src="../assets/images/admin/budget.png" alt="Total Budget" class="metric-icon">
                <div class="metric-content">
                    <h3>Total Budget</h3>
                    <div class="ac-9b373689">
                        <p class="metric-value ac-03320d86" id="budgetValue">Ã¢â€”ÂÃ¢â€”ÂÃ¢â€”ÂÃ¢â€”ÂÃ¢â€”ÂÃ¢â€”ÂÃ¢â€”ÂÃ¢â€”Â</p>
                        <button type="button" id="budgetVisibilityToggle" class="ac-d278272f" title="Hold to reveal budget" aria-label="Hold to reveal total budget">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ac-8a303121">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <span class="metric-status">Allocated funds</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-box card">
                <h3>Project Status Distribution</h3>
                <div class="chart-placeholder">
                    <div class="progress-bar" aria-hidden="true">
                        <div class="progress-fill" id="statusStackBar" style="width:100%;background:linear-gradient(90deg,#16a34a 0% <?php echo (int) $distributionCompletedPct; ?>%,#2563eb <?php echo (int) $distributionCompletedPct; ?>% <?php echo (int) ($distributionCompletedPct + $distributionInProgressPct); ?>%,#f59e0b <?php echo (int) ($distributionCompletedPct + $distributionInProgressPct); ?>% 100%);"></div>
                    </div>
                    <div class="status-legend">
                        <div class="legend-item">
                            <span class="legend-color ac-31e9dda2"></span>
                            <span id="completedPercent">Completed: <?php echo (int) $distributionCompletedPct; ?>%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background:#2563eb;"></span>
                            <span id="inProgressPercent">In Progress: <?php echo (int) $distributionInProgressPct; ?>%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background:#f59e0b;"></span>
                            <span id="otherPercent">Other: <?php echo (int) $distributionOtherPct; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-box card">
                <h3>Monthly Project Activity</h3>
                <div class="chart-placeholder">
                    <svg id="monthlyActivityChart" viewBox="0 0 320 120" role="img" aria-label="Monthly project activity chart">
                        <polyline id="monthlyActivityLine" fill="none" stroke="#1d4ed8" stroke-width="3" points="<?php echo htmlspecialchars($monthlyPolylinePoints, ENT_QUOTES, 'UTF-8'); ?>"></polyline>
                    </svg>
                    <p id="monthlyActivityText">Projects created in last 6 months: <?php echo (int) $monthlyActivityTotal; ?></p>
                </div>
            </div>
        </div>

        <div class="card dashboard-analytics-shell">
            <h3>Budget Trend and Priority Analytics</h3>
            <div class="dashboard-analytics-grid">
                <div class="dashboard-analytics-chart">
                    <div id="budgetTrendChart" data-points='<?php echo htmlspecialchars(json_encode($budgetTrendPoints), ENT_QUOTES, "UTF-8"); ?>'></div>
                </div>
                <div class="dashboard-analytics-kpis">
                    <article class="dashboard-analytics-kpi crucial">
                        <span>Crucial Priority</span>
                        <strong><?php echo (int) $priorityCounts['crucial']; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi high">
                        <span>High Priority</span>
                        <strong><?php echo (int) $priorityCounts['high']; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi medium">
                        <span>Medium Priority</span>
                        <strong><?php echo (int) $priorityCounts['medium']; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi low">
                        <span>Low Priority</span>
                        <strong><?php echo (int) $priorityCounts['low']; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi pending">
                        <span>Pending Concerns</span>
                        <strong><?php echo (int) $pendingFeedback; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi reviewed">
                        <span>Reviewed Concerns</span>
                        <strong><?php echo (int) $reviewedFeedback; ?></strong>
                    </article>
                    <article class="dashboard-analytics-kpi addressed">
                        <span>Addressed Concerns</span>
                        <strong><?php echo (int) $addressedFeedback; ?></strong>
                    </article>
                </div>
            </div>
        </div>

        <div class="engineer-eval-grid">
            <article class="engineer-eval-card">
                <h4>Top Performing Engineers</h4>
                <ul class="engineer-eval-list">
                    <?php if (!empty($engineerEvaluationOverview['top_performing'])): ?>
                        <?php foreach ($engineerEvaluationOverview['top_performing'] as $item): ?>
                            <li><strong><?php echo htmlspecialchars((string) ($item['display_name'] ?? 'Engineer')); ?></strong> - Score <?php echo number_format((float) ($item['performance_rating'] ?? 0), 1); ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No data yet.</li>
                    <?php endif; ?>
                </ul>
            </article>
            <article class="engineer-eval-card">
                <h4>High Risk Engineers</h4>
                <ul class="engineer-eval-list">
                    <?php if (!empty($engineerEvaluationOverview['high_risk'])): ?>
                        <?php foreach ($engineerEvaluationOverview['high_risk'] as $item): ?>
                            <li><strong><?php echo htmlspecialchars((string) ($item['display_name'] ?? 'Engineer')); ?></strong> - <?php echo htmlspecialchars((string) ($item['risk_level'] ?? 'High')); ?> (<?php echo number_format((float) ($item['risk_score'] ?? 0), 1); ?>)</li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No high-risk engineers.</li>
                    <?php endif; ?>
                </ul>
            </article>
            <article class="engineer-eval-card">
                <h4>Most Delayed Engineers</h4>
                <ul class="engineer-eval-list">
                    <?php if (!empty($engineerEvaluationOverview['most_delayed'])): ?>
                        <?php foreach ($engineerEvaluationOverview['most_delayed'] as $item): ?>
                            <li><strong><?php echo htmlspecialchars((string) ($item['display_name'] ?? 'Engineer')); ?></strong> - Delayed <?php echo (int) ($item['delayed_project_count'] ?? 0); ?> / <?php echo (int) ($item['past_project_count'] ?? 0); ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No delay records yet.</li>
                    <?php endif; ?>
                </ul>
            </article>
        </div>

        <!-- Recent Projects Section -->
        <div class="recent-projects card">
            <h3>Recent Projects</h3>
            <div class="table-wrap dashboard-table-wrap">
            <table class="projects-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Budget</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($recentProjects && $recentProjects->num_rows > 0) {
                        while ($project = $recentProjects->fetch_assoc()) {
                            $statusColor = 'pending';
                            if ($project['status'] === 'Completed') $statusColor = 'completed';
                            elseif ($project['status'] === 'Approved') $statusColor = 'approved';
                            elseif ($project['status'] === 'For Approval') $statusColor = 'pending';
                            elseif ($project['status'] === 'On-hold') $statusColor = 'onhold';
                            elseif ($project['status'] === 'Cancelled') $statusColor = 'cancelled';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                <td><span class="status-badge <?php echo $statusColor; ?>"><?php echo $project['status']; ?></span></td>
                                <td>
                                    <div class="progress-small">
                                        <div class="progress-fill-small ac-a8a5341d"></div>
                                    </div>
                                </td>
                                <td>Ã¢â€šÂ±<?php echo number_format($project['budget'], 2); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" class="ac-a004b216">No projects registered yet</td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats card">
            <div class="stat-item">
                <h4>Average Project Duration</h4>
                <p>0 months</p>
            </div>
            <div class="stat-item">
                <h4>On-Time Delivery Rate</h4>
                <p>0%</p>
            </div>
            <div class="stat-item">
                <h4>Budget Variance</h4>
                <p>0%</p>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <!-- Component Utilities: Dropdowns, Modals, Toast, Sidebar Toggle -->
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-dashboard-analytics.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-dashboard-analytics.js'); ?>"></script>
</body>
</html>

























