<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id']) || strtolower((string) ($_SESSION['employee_role'] ?? '')) !== 'contractor') {
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

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    json_out(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
}

if ($action === 'load_projects') {
    $rows = [];
    $res = $db->query("SELECT id, code, name, location, COALESCE(budget, 0) AS budget, status FROM projects ORDER BY id DESC LIMIT 500");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_budget_state') {
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
    json_out(['success' => true, 'data' => ['milestones' => $milestones]]);
}

if ($action === 'validate_project') {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $allowed = ['For Approval', 'Approved', 'On-hold', 'Completed'];
    if ($projectId <= 0 || !in_array($status, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid validation request.'], 422);
    }
    $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('si', $status, $projectId);
    $stmt->execute();
    $stmt->close();
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

json_out(['success' => false, 'message' => 'Unknown action.'], 400);

