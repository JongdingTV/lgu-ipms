<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('department_head.approvals.view', ['department_head', 'department_admin', 'admin', 'super_admin']);
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['department_head', 'department_admin', 'admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function ensure_department_head_table(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS project_department_head_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL UNIQUE,
        decision_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        decision_note TEXT NULL,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_decision_status (decision_status),
        CONSTRAINT fk_dept_review_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_dept_review_employee FOREIGN KEY (decided_by) REFERENCES employees(id) ON DELETE SET NULL
    )");
}

function dept_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $ok;
}

function dept_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = (string) generate_csrf_token();
    $requestToken = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        out(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
    }
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
rbac_require_action_matrix(
    $action !== '' ? $action : 'load_projects',
    [
        'load_notifications' => 'department_head.notifications.read',
        'load_projects' => 'department_head.approvals.view',
        'decide_project' => 'department_head.approvals.manage',
    ],
    'department_head.approvals.view'
);
ensure_department_head_table($db);

if ($action === 'load_notifications') {
    $items = [];
    $latestId = 0;

    if (!dept_table_exists($db, 'projects')) {
        out(['success' => true, 'latest_id' => 0, 'items' => []]);
    }

    $sql = "SELECT
                p.id,
                p.code,
                p.name,
                p.created_at,
                COALESCE(r.decision_status, 'Pending') AS decision_status
            FROM projects p
            LEFT JOIN project_department_head_reviews r ON r.project_id = p.id
            WHERE (r.project_id IS NULL OR r.decision_status = 'Pending')
              AND LOWER(COALESCE(p.status, '')) IN ('for approval', 'pending', 'draft')
            ORDER BY p.id DESC
            LIMIT 30";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pid = (int) ($row['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $nid = 900000000 + $pid;
            $items[] = [
                'id' => $nid,
                'level' => 'warning',
                'title' => trim((string) ($row['code'] ?? 'Project')) . ' - ' . trim((string) ($row['name'] ?? 'Project')),
                'message' => 'New project is waiting for Department Head approval.',
                'created_at' => (string) ($row['created_at'] ?? '')
            ];
            if ($nid > $latestId) {
                $latestId = $nid;
            }
        }
        $res->free();
    }

    out(['success' => true, 'latest_id' => $latestId, 'items' => $items]);
}

if ($action === 'load_projects') {
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'pending')));
    $q = strtolower(trim((string) ($_GET['q'] ?? '')));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 25);
    if ($perPage < 1) $perPage = 25;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;
    $rows = [];

    $searchSql = '';
    $types = '';
    $params = [];
    if ($q !== '') {
        $searchSql = " AND (
            LOWER(COALESCE(p.code, '')) LIKE ?
            OR LOWER(COALESCE(p.name, '')) LIKE ?
            OR LOWER(COALESCE(p.location, '')) LIKE ?
        )";
        $like = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sqlPending = "SELECT
            p.*,
            COALESCE(r.decision_status, 'Pending') AS decision_status,
            COALESCE(r.decision_note, '') AS decision_note,
            COALESCE(CONCAT(e.first_name, ' ', e.last_name), '') AS decided_by_name,
            r.decided_at
        FROM projects p
        LEFT JOIN project_department_head_reviews r ON r.project_id = p.id
        LEFT JOIN employees e ON e.id = r.decided_by
        WHERE (r.project_id IS NULL OR r.decision_status = 'Pending')
          AND LOWER(COALESCE(p.status, '')) IN ('for approval', 'pending', 'draft')
          {$searchSql}
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?";

    $sqlReviewed = "SELECT
            p.*,
            COALESCE(r.decision_status, 'Pending') AS decision_status,
            COALESCE(r.decision_note, '') AS decision_note,
            COALESCE(CONCAT(e.first_name, ' ', e.last_name), '') AS decided_by_name,
            r.decided_at
        FROM projects p
        JOIN project_department_head_reviews r ON r.project_id = p.id
        LEFT JOIN employees e ON e.id = r.decided_by
        WHERE r.decision_status IN ('Approved', 'Rejected')
          {$searchSql}
        ORDER BY r.decided_at DESC, p.id DESC
        LIMIT ? OFFSET ?";

    $sql = $mode === 'reviewed' ? $sqlReviewed : $sqlPending;
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        out(['success' => false, 'message' => 'Unable to prepare project queue query.'], 500);
    }
    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $perPage;
    $paramsData[] = $offset;
    if (!dept_bind_dynamic($stmt, $typesData, $paramsData)) {
        $stmt->close();
        out(['success' => false, 'message' => 'Unable to bind project queue query.'], 500);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    out([
        'success' => true,
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'has_next' => count($rows) === $perPage
        ]
    ]);
}

if ($action === 'decide_project') {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $decision = trim((string) ($_POST['decision_status'] ?? ''));
    $note = trim((string) ($_POST['decision_note'] ?? ''));
    $budgetAmount = max(0, (float) ($_POST['budget_amount'] ?? 0));
    $allowed = ['Approved', 'Rejected'];

    if ($projectId <= 0 || !in_array($decision, $allowed, true)) {
        out(['success' => false, 'message' => 'Invalid decision request.'], 422);
    }
    if ($decision === 'Approved' && $budgetAmount <= 0) {
        out(['success' => false, 'message' => 'Please enter a valid budget before approving the project.'], 422);
    }

    $employeeId = (int) $_SESSION['employee_id'];
    $stmt = $db->prepare(
        "INSERT INTO project_department_head_reviews (project_id, decision_status, decision_note, decided_by, decided_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            decision_status = VALUES(decision_status),
            decision_note = VALUES(decision_note),
            decided_by = VALUES(decided_by),
            decided_at = VALUES(decided_at)"
    );
    if (!$stmt) {
        out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('issi', $projectId, $decision, $note, $employeeId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        out(['success' => false, 'message' => 'Unable to save decision.'], 500);
    }

    // Workflow: after department head approval, project is marked Approved for admin assignment.
    $newProjectStatus = $decision === 'Approved' ? 'Approved' : 'Rejected';
    $up = $db->prepare('UPDATE projects SET status = ?, budget = ? WHERE id = ?');
    if ($up) {
        $effectiveBudget = $decision === 'Approved' ? $budgetAmount : 0;
        $up->bind_param('sdi', $newProjectStatus, $effectiveBudget, $projectId);
        $up->execute();
        $up->close();
    }
    if (function_exists('rbac_audit')) {
        rbac_audit('department_head.project_decision', 'project', $projectId, [
            'decision_status' => $decision,
            'decision_note' => $note,
            'budget_amount' => $decision === 'Approved' ? $budgetAmount : 0,
        ]);
    }

    out(['success' => true]);
}

out(['success' => false, 'message' => 'Unknown action.'], 400);
