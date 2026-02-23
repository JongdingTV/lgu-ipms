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
rbac_require_from_matrix('admin.budget.manage', ['admin','department_admin','super_admin']);
rbac_require_action_matrix(
    strtolower(trim((string)($_REQUEST['action'] ?? ''))),
    [
        'load_projects' => 'admin.budget.manage',
        'load_budget_state' => 'admin.budget.manage',
        'load_milestones' => 'admin.budget.manage',
        'load_expenses' => 'admin.budget.manage',
        'set_global_budget' => 'admin.budget.manage',
        'add_milestone' => 'admin.budget.manage',
        'update_milestone_alloc' => 'admin.budget.manage',
        'delete_milestone' => 'admin.budget.delete',
        'add_expense' => 'admin.budget.manage',
        'delete_expense' => 'admin.budget.delete',
    ],
    'admin.budget.manage'
);
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

function budget_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $args = [$types];
    foreach ($params as $k => $v) {
        $args[] = &$params[$k];
    }
    return call_user_func_array([$stmt, 'bind_param'], $args);
}

function budget_pagination_meta(int $page, int $perPage, int $total): array
{
    $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $totalPages));
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
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

        if ($action === 'load_milestones') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 15);
            if ($perPage < 1) $perPage = 15;
            if ($perPage > 100) $perPage = 100;
            $q = strtolower(trim((string)($_GET['q'] ?? '')));

            $whereSql = " WHERE EXISTS (SELECT 1 FROM projects p WHERE TRIM(p.name) <> '' AND p.name = m.name) ";
            $types = '';
            $params = [];
            if ($q !== '') {
                $whereSql .= " AND LOWER(COALESCE(m.name, '')) LIKE ? ";
                $types .= 's';
                $params[] = '%' . $q . '%';
            }

            $countSql = "SELECT COUNT(*) AS total FROM milestones m {$whereSql}";
            $countStmt = $db->prepare($countSql);
            if (!$countStmt) {
                budget_json_response(['success' => false, 'message' => 'Failed to prepare milestones count query.'], 500);
                $db->close();
                exit;
            }
            if (!budget_bind_dynamic($countStmt, $types, $params)) {
                $countStmt->close();
                budget_json_response(['success' => false, 'message' => 'Failed to bind milestones count query.'], 500);
                $db->close();
                exit;
            }
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $total = 0;
            if ($countRes) {
                $row = $countRes->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
                $countRes->free();
            }
            $countStmt->close();
            $meta = budget_pagination_meta($page, $perPage, $total);
            $offset = ($meta['page'] - 1) * $meta['per_page'];

            $dataSql = "SELECT m.id, m.name, m.allocated, m.spent,
                               (m.allocated - m.spent) AS remaining,
                               CASE WHEN m.allocated > 0 THEN ROUND((m.spent / m.allocated) * 100, 0) ELSE 0 END AS consumed_percent
                        FROM milestones m
                        {$whereSql}
                        ORDER BY m.id ASC
                        LIMIT ? OFFSET ?";
            $dataStmt = $db->prepare($dataSql);
            if (!$dataStmt) {
                budget_json_response(['success' => false, 'message' => 'Failed to prepare milestones list query.'], 500);
                $db->close();
                exit;
            }
            $dataTypes = $types . 'ii';
            $dataParams = $params;
            $dataParams[] = $meta['per_page'];
            $dataParams[] = $offset;
            if (!budget_bind_dynamic($dataStmt, $dataTypes, $dataParams)) {
                $dataStmt->close();
                budget_json_response(['success' => false, 'message' => 'Failed to bind milestones list query.'], 500);
                $db->close();
                exit;
            }
            $dataStmt->execute();
            $res = $dataStmt->get_result();
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'name' => (string)($row['name'] ?? ''),
                        'allocated' => (float)($row['allocated'] ?? 0),
                        'spent' => (float)($row['spent'] ?? 0),
                        'remaining' => (float)($row['remaining'] ?? 0),
                        'consumed_percent' => (float)($row['consumed_percent'] ?? 0),
                    ];
                }
                $res->free();
            }
            $dataStmt->close();

            budget_json_response([
                'success' => true,
                'data' => $rows,
                'meta' => $meta
            ]);
            $db->close();
            exit;
        }

        if ($action === 'load_expenses') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 15);
            if ($perPage < 1) $perPage = 15;
            if ($perPage > 100) $perPage = 100;
            $q = strtolower(trim((string)($_GET['q'] ?? '')));

            $whereSql = " WHERE EXISTS (SELECT 1 FROM projects p WHERE TRIM(p.name) <> '' AND p.name = m.name) ";
            $types = '';
            $params = [];
            if ($q !== '') {
                $whereSql .= " AND (
                    LOWER(COALESCE(m.name, '')) LIKE ?
                    OR LOWER(COALESCE(e.description, '')) LIKE ?
                ) ";
                $types .= 'ss';
                $like = '%' . $q . '%';
                $params[] = $like;
                $params[] = $like;
            }

            $countSql = "SELECT COUNT(*) AS total
                         FROM expenses e
                         INNER JOIN milestones m ON m.id = e.milestoneId
                         {$whereSql}";
            $countStmt = $db->prepare($countSql);
            if (!$countStmt) {
                budget_json_response(['success' => false, 'message' => 'Failed to prepare expenses count query.'], 500);
                $db->close();
                exit;
            }
            if (!budget_bind_dynamic($countStmt, $types, $params)) {
                $countStmt->close();
                budget_json_response(['success' => false, 'message' => 'Failed to bind expenses count query.'], 500);
                $db->close();
                exit;
            }
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $total = 0;
            if ($countRes) {
                $row = $countRes->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
                $countRes->free();
            }
            $countStmt->close();
            $meta = budget_pagination_meta($page, $perPage, $total);
            $offset = ($meta['page'] - 1) * $meta['per_page'];

            $dataSql = "SELECT e.id, e.milestoneId, e.amount, e.description, e.date, m.name AS project_name
                        FROM expenses e
                        INNER JOIN milestones m ON m.id = e.milestoneId
                        {$whereSql}
                        ORDER BY e.date DESC, e.id DESC
                        LIMIT ? OFFSET ?";
            $dataStmt = $db->prepare($dataSql);
            if (!$dataStmt) {
                budget_json_response(['success' => false, 'message' => 'Failed to prepare expenses list query.'], 500);
                $db->close();
                exit;
            }
            $dataTypes = $types . 'ii';
            $dataParams = $params;
            $dataParams[] = $meta['per_page'];
            $dataParams[] = $offset;
            if (!budget_bind_dynamic($dataStmt, $dataTypes, $dataParams)) {
                $dataStmt->close();
                budget_json_response(['success' => false, 'message' => 'Failed to bind expenses list query.'], 500);
                $db->close();
                exit;
            }
            $dataStmt->execute();
            $res = $dataStmt->get_result();
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'milestoneId' => (int)($row['milestoneId'] ?? 0),
                        'amount' => (float)($row['amount'] ?? 0),
                        'description' => (string)($row['description'] ?? ''),
                        'date' => (string)($row['date'] ?? ''),
                        'project_name' => (string)($row['project_name'] ?? ''),
                    ];
                }
                $res->free();
            }
            $dataStmt->close();

            budget_json_response([
                'success' => true,
                'data' => $rows,
                'meta' => $meta
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
                    <a href="/engineer/create.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>Engineer Account Creation</span></a>
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

    <link rel="stylesheet" href="../assets/css/admin-budget-resources.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-budget-resources.css'); ?>">
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-budget-resources.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-budget-resources.js'); ?>"></script>
</body>
</html>






