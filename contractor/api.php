<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['contractor','admin','super_admin']);
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}
$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['contractor', 'admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function ensure_progress_table(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS project_progress_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        updated_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_created (project_id, created_at),
        CONSTRAINT fk_progress_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_progress_employee FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE CASCADE
    )");
}

function ensure_task_milestone_tables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS project_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        planned_start DATE NULL,
        planned_end DATE NULL,
        actual_start DATE NULL,
        actual_end DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_status (project_id, status),
        CONSTRAINT fk_project_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");

    $db->query("CREATE TABLE IF NOT EXISTS project_milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        planned_date DATE NULL,
        actual_date DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_status (project_id, status),
        CONSTRAINT fk_project_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
}

function ensure_status_request_table(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS project_status_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        requested_status VARCHAR(50) NOT NULL,
        contractor_note TEXT NULL,
        requested_by INT NOT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        engineer_decision VARCHAR(20) DEFAULT 'Pending',
        engineer_note TEXT NULL,
        engineer_decided_by INT NULL,
        engineer_decided_at DATETIME NULL,
        admin_decision VARCHAR(20) DEFAULT 'Pending',
        admin_note TEXT NULL,
        admin_decided_by INT NULL,
        admin_decided_at DATETIME NULL,
        INDEX idx_project_time (project_id, requested_at),
        CONSTRAINT fk_status_req_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_status_req_requested_by FOREIGN KEY (requested_by) REFERENCES employees(id) ON DELETE CASCADE
    )");
}

function contractor_table_exists(mysqli $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function contractor_sync_projects_to_milestones(mysqli $db): void {
    $projects = [];
    $res = $db->query("SELECT name, COALESCE(budget, 0) AS budget FROM projects ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $projects[$name] = max(0, (float) ($row['budget'] ?? 0));
        }
        $res->free();
    }

    $existing = [];
    $msRes = $db->query("SELECT id, name FROM milestones");
    if ($msRes) {
        while ($row = $msRes->fetch_assoc()) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '' && !isset($existing[$name])) {
                $existing[$name] = (int) $row['id'];
            }
        }
        $msRes->free();
    }

    $insert = $db->prepare("INSERT INTO milestones (name, allocated, spent) VALUES (?, ?, 0)");
    $update = $db->prepare("UPDATE milestones SET allocated = ? WHERE id = ?");
    foreach ($projects as $name => $budget) {
        if (isset($existing[$name])) {
            $id = (int) $existing[$name];
            if ($update) {
                $update->bind_param('di', $budget, $id);
                $update->execute();
            }
            continue;
        }
        if ($insert) {
            $insert->bind_param('sd', $name, $budget);
            $insert->execute();
        }
    }
    if ($insert) {
        $insert->close();
    }
    if ($update) {
        $update->close();
    }
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

$engineerOwnedActions = [
    'load_task_milestone',
    'add_task',
    'update_task_status',
    'add_milestone',
    'update_milestone_status'
];
if (in_array($action, $engineerOwnedActions, true)) {
    json_out(['success' => false, 'message' => 'Task and Milestone are managed by Engineer module.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    json_out(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
}

if ($action === 'load_projects') {
    ensure_progress_table($db);
    $rows = [];
    $res = $db->query("SELECT
            p.id,
            p.code,
            p.name,
            p.location,
            COALESCE(p.budget, 0) AS budget,
            p.status,
            COALESCE(pp.progress_percent, 0) AS progress_percent,
            DATE_FORMAT(pp.created_at, '%b %d, %Y %h:%i %p') AS progress_updated_at
        FROM projects p
        LEFT JOIN (
            SELECT p1.project_id, p1.progress_percent, p1.created_at
            FROM project_progress_updates p1
            INNER JOIN (
                SELECT project_id, MAX(created_at) AS max_created
                FROM project_progress_updates
                GROUP BY project_id
            ) p2 ON p1.project_id = p2.project_id AND p1.created_at = p2.max_created
        ) pp ON pp.project_id = p.id
        ORDER BY p.id DESC
        LIMIT 500");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_notifications') {
    $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
    $employeeEmail = '';
    if ($employeeId > 0 && contractor_table_exists($db, 'employees')) {
        $emp = $db->prepare("SELECT email FROM employees WHERE id = ? LIMIT 1");
        if ($emp) {
            $emp->bind_param('i', $employeeId);
            $emp->execute();
            $row = $emp->get_result()->fetch_assoc();
            $employeeEmail = trim((string) ($row['email'] ?? ''));
            $emp->close();
        }
    }

    $contractorIds = [];
    if ($employeeId > 0) {
        $contractorIds[$employeeId] = true; // Backward compatibility for deployments using employee_id directly
    }
    if ($employeeEmail !== '' && contractor_table_exists($db, 'contractors')) {
        $byEmail = $db->prepare("SELECT id FROM contractors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
        if ($byEmail) {
            $byEmail->bind_param('s', $employeeEmail);
            $byEmail->execute();
            $res = $byEmail->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $cid = (int) ($r['id'] ?? 0);
                if ($cid > 0) $contractorIds[$cid] = true;
            }
            $byEmail->close();
        }
    }

    $items = [];
    $latestId = 0;
    if (!empty($contractorIds) && contractor_table_exists($db, 'contractor_project_assignments') && contractor_table_exists($db, 'projects')) {
        $idList = implode(',', array_map('intval', array_keys($contractorIds)));
        $sql = "SELECT p.id, p.code, p.name, p.created_at
                FROM contractor_project_assignments cpa
                INNER JOIN projects p ON p.id = cpa.project_id
                WHERE cpa.contractor_id IN ({$idList})
                ORDER BY p.id DESC
                LIMIT 50";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $pid = (int) ($row['id'] ?? 0);
                if ($pid <= 0) continue;
                $nid = 700000000 + $pid;
                $items[$nid] = [
                    'id' => $nid,
                    'level' => 'info',
                    'title' => trim((string) ($row['code'] ?? 'Project')) . ' - ' . trim((string) ($row['name'] ?? 'Project')),
                    'message' => 'You were selected for this project.',
                    'created_at' => (string) ($row['created_at'] ?? '')
                ];
                if ($nid > $latestId) $latestId = $nid;
            }
            $res->free();
        }
    }

    json_out(['success' => true, 'latest_id' => $latestId, 'items' => array_values($items)]);
}

if ($action === 'load_budget_state') {
    contractor_sync_projects_to_milestones($db);
    $milestones = [];
    $msRes = $db->query("SELECT id, name, allocated, spent FROM milestones ORDER BY id ASC");
    if ($msRes) {
        while ($row = $msRes->fetch_assoc()) {
            $milestones[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'allocated' => (float) ($row['allocated'] ?? 0),
                'spent' => (float) ($row['spent'] ?? 0),
            ];
        }
        $msRes->free();
    }
    $totalSpent = 0.0;
    foreach ($milestones as $m) {
        $totalSpent += (float) ($m['spent'] ?? 0);
    }
    json_out(['success' => true, 'data' => ['milestones' => $milestones, 'total_spent' => $totalSpent]]);
}

if ($action === 'submit_status_request') {
    ensure_status_request_table($db);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $status = trim((string) ($_POST['requested_status'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    $allowed = ['For Approval', 'Approved', 'On-hold', 'Completed'];
    if ($projectId <= 0 || !in_array($status, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid status request.'], 422);
    }
    $requestedBy = (int) $_SESSION['employee_id'];
    $stmt = $db->prepare("INSERT INTO project_status_requests (project_id, requested_status, contractor_note, requested_by) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('issi', $projectId, $status, $note, $requestedBy);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

if ($action === 'load_status_requests') {
    ensure_status_request_table($db);
    $projectId = (int) ($_GET['project_id'] ?? 0);
    $rows = [];
    $sql = "SELECT id, project_id, requested_status, contractor_note, requested_at, engineer_decision, engineer_note, admin_decision, admin_note
            FROM project_status_requests";
    if ($projectId > 0) {
        $sql .= " WHERE project_id = " . $projectId;
    }
    $sql .= " ORDER BY requested_at DESC LIMIT 200";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'update_budget') {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $budget = max(0, (float) ($_POST['budget'] ?? 0));
    if ($projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid project.'], 422);
    }

    $stmt = $db->prepare("UPDATE projects SET budget = ? WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('di', $budget, $projectId);
    $stmt->execute();
    $stmt->close();

    contractor_sync_projects_to_milestones($db);
    json_out(['success' => true]);
}

if ($action === 'update_expense') {
    $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($milestoneId <= 0 || $amount <= 0) {
        json_out(['success' => false, 'message' => 'Invalid expense data.'], 422);
    }

    $check = $db->prepare("SELECT allocated, spent FROM milestones WHERE id = ? LIMIT 1");
    if (!$check) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $check->bind_param('i', $milestoneId);
    $check->execute();
    $res = $check->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $check->close();
    if (!$row) {
        json_out(['success' => false, 'message' => 'Project milestone not found.'], 422);
    }

    $allocated = (float) ($row['allocated'] ?? 0);
    $spent = (float) ($row['spent'] ?? 0);
    if ($amount > max(0, $allocated - $spent)) {
        json_out(['success' => false, 'message' => 'Expense exceeds remaining budget.'], 422);
    }

    $stmt = $db->prepare("INSERT INTO expenses (milestoneId, amount, description, date) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('ids', $milestoneId, $amount, $description);
    $stmt->execute();
    $stmt->close();

    $db->query("UPDATE milestones m
                LEFT JOIN (
                    SELECT milestoneId, COALESCE(SUM(amount),0) AS total_spent
                    FROM expenses
                    GROUP BY milestoneId
                ) e ON e.milestoneId = m.id
                SET m.spent = COALESCE(e.total_spent,0)");
    json_out(['success' => true]);
}

if ($action === 'update_progress') {
    ensure_progress_table($db);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $progress = (float) ($_POST['progress'] ?? -1);
    if ($projectId <= 0 || $progress < 0 || $progress > 100) {
        json_out(['success' => false, 'message' => 'Progress must be between 0 and 100.'], 422);
    }

    $employeeId = (int) $_SESSION['employee_id'];
    $stmt = $db->prepare("INSERT INTO project_progress_updates (project_id, progress_percent, updated_by) VALUES (?, ?, ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('idi', $projectId, $progress, $employeeId);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

if ($action === 'load_progress_history') {
    ensure_progress_table($db);
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid project.'], 422);
    }
    $rows = [];
    $stmt = $db->prepare("SELECT ppu.id, ppu.progress_percent, ppu.created_at, CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS updated_by
                          FROM project_progress_updates ppu
                          LEFT JOIN employees e ON e.id = ppu.updated_by
                          WHERE ppu.project_id = ?
                          ORDER BY ppu.created_at DESC
                          LIMIT 200");
    if ($stmt) {
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_task_milestone') {
    ensure_task_milestone_tables($db);
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid project.'], 422);
    }

    $tasks = [];
    $t = $db->prepare("SELECT id, title, status, planned_start, planned_end, actual_start, actual_end, notes, created_at
                       FROM project_tasks WHERE project_id = ? ORDER BY id DESC");
    if ($t) {
        $t->bind_param('i', $projectId);
        $t->execute();
        $r = $t->get_result();
        while ($r && ($row = $r->fetch_assoc())) {
            $tasks[] = $row;
        }
        $t->close();
    }

    $milestones = [];
    $m = $db->prepare("SELECT id, title, status, planned_date, actual_date, notes, created_at
                       FROM project_milestones WHERE project_id = ? ORDER BY id DESC");
    if ($m) {
        $m->bind_param('i', $projectId);
        $m->execute();
        $r = $m->get_result();
        while ($r && ($row = $r->fetch_assoc())) {
            $milestones[] = $row;
        }
        $m->close();
    }

    json_out(['success' => true, 'data' => ['tasks' => $tasks, 'milestones' => $milestones]]);
}

if ($action === 'add_task') {
    ensure_task_milestone_tables($db);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $plannedStart = trim((string) ($_POST['planned_start'] ?? ''));
    $plannedEnd = trim((string) ($_POST['planned_end'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($projectId <= 0 || $title === '') {
        json_out(['success' => false, 'message' => 'Project and title are required.'], 422);
    }
    $stmt = $db->prepare("INSERT INTO project_tasks (project_id, title, status, planned_start, planned_end, notes) VALUES (?, ?, 'Pending', NULLIF(?,''), NULLIF(?,''), ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('issss', $projectId, $title, $plannedStart, $plannedEnd, $notes);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

if ($action === 'update_task_status') {
    ensure_task_milestone_tables($db);
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $allowed = ['Pending', 'In Progress', 'Completed', 'On-hold'];
    if ($taskId <= 0 || !in_array($status, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid task update.'], 422);
    }
    $stmt = $db->prepare("UPDATE project_tasks
                          SET status = ?,
                              actual_start = CASE WHEN ? = 'In Progress' AND actual_start IS NULL THEN CURDATE() ELSE actual_start END,
                              actual_end = CASE WHEN ? = 'Completed' THEN CURDATE() ELSE actual_end END
                          WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('sssi', $status, $status, $status, $taskId);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

if ($action === 'add_milestone') {
    ensure_task_milestone_tables($db);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $plannedDate = trim((string) ($_POST['planned_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if ($projectId <= 0 || $title === '') {
        json_out(['success' => false, 'message' => 'Project and title are required.'], 422);
    }
    $stmt = $db->prepare("INSERT INTO project_milestones (project_id, title, status, planned_date, notes) VALUES (?, ?, 'Pending', NULLIF(?,''), ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('isss', $projectId, $title, $plannedDate, $notes);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

if ($action === 'update_milestone_status') {
    ensure_task_milestone_tables($db);
    $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $allowed = ['Pending', 'In Progress', 'Completed', 'On-hold'];
    if ($milestoneId <= 0 || !in_array($status, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid milestone update.'], 422);
    }
    $stmt = $db->prepare("UPDATE project_milestones
                          SET status = ?,
                              actual_date = CASE WHEN ? = 'Completed' THEN CURDATE() ELSE actual_date END
                          WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('ssi', $status, $status, $milestoneId);
    $stmt->execute();
    $stmt->close();
    json_out(['success' => true]);
}

json_out(['success' => false, 'message' => 'Unknown action.'], 400);
