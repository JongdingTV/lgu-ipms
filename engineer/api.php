<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('engineer.workspace.view', ['engineer','admin','super_admin']);
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}
$role = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['engineer', 'admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
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

function ensure_progress_review_table(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS project_progress_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        submitted_progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        work_details TEXT NOT NULL,
        validation_notes TEXT NOT NULL,
        proof_image_path VARCHAR(255) NOT NULL,
        discrepancy_flag TINYINT(1) NOT NULL DEFAULT 0,
        discrepancy_note VARCHAR(255) NULL,
        review_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        review_note TEXT NULL,
        submitted_by INT NOT NULL,
        reviewed_by INT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_project_submitted (project_id, submitted_at),
        INDEX idx_review_status (review_status),
        CONSTRAINT fk_progress_submission_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_progress_submission_submitter FOREIGN KEY (submitted_by) REFERENCES employees(id) ON DELETE CASCADE
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

function engineer_table_exists(mysqli $db, string $table): bool {
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

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
rbac_require_action_matrix(
    $action !== '' ? $action : 'load_monitoring',
    [
        'load_monitoring' => 'engineer.workspace.view',
        'load_notifications' => 'engineer.notifications.read',
        'load_progress_submissions' => 'engineer.progress.review',
        'decide_progress' => 'engineer.progress.review',
        'load_status_requests' => 'engineer.status.review',
        'engineer_decide_status_request' => 'engineer.status.review',
        'load_task_milestone' => 'engineer.workspace.view',
        'add_task' => 'engineer.tasks.manage',
        'update_task_status' => 'engineer.tasks.manage',
        'add_milestone' => 'engineer.tasks.manage',
        'update_milestone_status' => 'engineer.tasks.manage',
    ],
    'engineer.workspace.view'
);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    json_out(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
}

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

if ($action === 'load_monitoring' || $action === '') {
    $sql = "SELECT
            p.id,
            p.code,
            p.name,
            p.status,
            p.location,
            COALESCE(p.budget, 0) AS budget,
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
        LIMIT 500";

    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_notifications') {
    $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
    $employeeEmail = '';
    if ($employeeId > 0 && engineer_table_exists($db, 'employees')) {
        $emp = $db->prepare("SELECT email FROM employees WHERE id = ? LIMIT 1");
        if ($emp) {
            $emp->bind_param('i', $employeeId);
            $emp->execute();
            $row = $emp->get_result()->fetch_assoc();
            $employeeEmail = trim((string) ($row['email'] ?? ''));
            $emp->close();
        }
    }

    $engineerIds = [];
    if ($employeeId > 0) {
        $engineerIds[$employeeId] = true; // Backward compatibility for deployments using employee_id directly
    }
    if (engineer_table_exists($db, 'engineers')) {
        $byLink = $db->prepare("SELECT id FROM engineers WHERE employee_id = ?");
        if ($byLink && $employeeId > 0) {
            $byLink->bind_param('i', $employeeId);
            $byLink->execute();
            $res = $byLink->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $eid = (int) ($r['id'] ?? 0);
                if ($eid > 0) $engineerIds[$eid] = true;
            }
            $byLink->close();
        }
        if ($employeeEmail !== '') {
            $byEmail = $db->prepare("SELECT id FROM engineers WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
            if ($byEmail) {
                $byEmail->bind_param('s', $employeeEmail);
                $byEmail->execute();
                $res = $byEmail->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $eid = (int) ($r['id'] ?? 0);
                    if ($eid > 0) $engineerIds[$eid] = true;
                }
                $byEmail->close();
            }
        }
    }

    $items = [];
    $latestId = 0;
    if (!empty($engineerIds) && engineer_table_exists($db, 'projects')) {
        $idList = implode(',', array_map('intval', array_keys($engineerIds)));

        if (engineer_table_exists($db, 'project_assignments')) {
            $sql = "SELECT p.id, p.code, p.name, p.created_at
                    FROM project_assignments pa
                    INNER JOIN projects p ON p.id = pa.project_id
                    WHERE pa.engineer_id IN ({$idList})
                    ORDER BY p.id DESC
                    LIMIT 50";
            $res = $db->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $pid = (int) ($row['id'] ?? 0);
                    if ($pid <= 0) continue;
                    $nid = 710000000 + $pid;
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

        // Compatibility with deployments using contractor_project_assignments for engineer selection.
        if (engineer_table_exists($db, 'contractor_project_assignments')) {
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
                    $nid = 720000000 + $pid;
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
    }

    json_out(['success' => true, 'latest_id' => $latestId, 'items' => array_values($items)]);
}

if ($action === 'load_progress_submissions') {
    ensure_progress_review_table($db);
    $sql = "SELECT
                pps.id AS submission_id,
                pps.project_id,
                p.code,
                p.name,
                pps.submitted_progress_percent AS progress_percent,
                pps.work_details,
                pps.validation_notes,
                pps.proof_image_path,
                pps.discrepancy_flag,
                pps.discrepancy_note,
                DATE_FORMAT(pps.submitted_at, '%b %d, %Y %h:%i %p') AS submitted_at,
                CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS submitted_by,
                pps.review_status AS decision_status,
                pps.review_note AS decision_note
            FROM project_progress_submissions pps
            INNER JOIN projects p ON p.id = pps.project_id
            LEFT JOIN employees e ON e.id = pps.submitted_by
            ORDER BY pps.submitted_at DESC
            LIMIT 400";
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'decide_progress') {
    ensure_progress_review_table($db);
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $decision = trim((string) ($_POST['decision_status'] ?? ''));
    $note = trim((string) ($_POST['decision_note'] ?? ''));
    $allowed = ['Approved', 'Rejected'];

    if ($submissionId <= 0 || $projectId <= 0 || !in_array($decision, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid decision payload.'], 422);
    }

    $decidedBy = (int) ($_SESSION['employee_id'] ?? 0);
    if ($decidedBy <= 0) {
        json_out(['success' => false, 'message' => 'Invalid session.'], 403);
    }

    $pick = $db->prepare("SELECT submitted_progress_percent, review_status
                          FROM project_progress_submissions
                          WHERE id = ? AND project_id = ?
                          LIMIT 1");
    if (!$pick) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $pick->bind_param('ii', $submissionId, $projectId);
    $pick->execute();
    $pickedRes = $pick->get_result();
    $submission = $pickedRes ? $pickedRes->fetch_assoc() : null;
    if ($pickedRes) $pickedRes->free();
    $pick->close();
    if (!$submission) {
        json_out(['success' => false, 'message' => 'Submission not found.'], 404);
    }

    $db->begin_transaction();
    try {
        $up = $db->prepare("UPDATE project_progress_submissions
                            SET review_status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW()
                            WHERE id = ? AND project_id = ?");
        if (!$up) {
            throw new RuntimeException('Database error.');
        }
        $up->bind_param('ssiii', $decision, $note, $decidedBy, $submissionId, $projectId);
        $up->execute();
        $up->close();

        if ($decision === 'Approved') {
            $officialProgress = (float)($submission['submitted_progress_percent'] ?? 0);
            $ins = $db->prepare("INSERT INTO project_progress_updates (project_id, progress_percent, updated_by) VALUES (?, ?, ?)");
            if (!$ins) {
                throw new RuntimeException('Unable to write official progress.');
            }
            $ins->bind_param('idi', $projectId, $officialProgress, $decidedBy);
            $ins->execute();
            $ins->close();
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.progress_decision', 'project_progress_submission', $submissionId, [
            'project_id' => $projectId,
            'decision_status' => $decision,
            'decision_note' => $note,
        ]);
    }
    json_out(['success' => true]);
}

if ($action === 'load_status_requests') {
    ensure_status_request_table($db);
    $rows = [];
    $sql = "SELECT sr.id, sr.project_id, p.code, p.name, sr.requested_status, sr.contractor_note, sr.requested_at, sr.engineer_decision, sr.engineer_note, sr.admin_decision
            FROM project_status_requests sr
            INNER JOIN projects p ON p.id = sr.project_id
            ORDER BY sr.requested_at DESC
            LIMIT 200";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'engineer_decide_status_request') {
    ensure_status_request_table($db);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = trim((string) ($_POST['engineer_decision'] ?? ''));
    $note = trim((string) ($_POST['engineer_note'] ?? ''));
    $allowed = ['Approved', 'Rejected'];
    if ($requestId <= 0 || !in_array($decision, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid decision.'], 422);
    }
    $engineerId = (int) ($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("UPDATE project_status_requests
                          SET engineer_decision = ?, engineer_note = ?, engineer_decided_by = ?, engineer_decided_at = NOW()
                          WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('ssii', $decision, $note, $engineerId, $requestId);
    $stmt->execute();
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.status_request_decision', 'project_status_request', $requestId, [
            'decision_status' => $decision,
            'decision_note' => $note,
        ]);
    }
    json_out(['success' => true]);
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
    $taskId = (int) $db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.task_create', 'project_task', $taskId, [
            'project_id' => $projectId,
            'title' => $title,
        ]);
    }
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
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.task_status_update', 'project_task', $taskId, [
            'status' => $status,
        ]);
    }
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
    $milestoneId = (int) $db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.milestone_create', 'project_milestone', $milestoneId, [
            'project_id' => $projectId,
            'title' => $title,
        ]);
    }
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
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.milestone_status_update', 'project_milestone', $milestoneId, [
            'status' => $status,
        ]);
    }
    json_out(['success' => true]);
}

json_out(['success' => false, 'message' => 'Unknown action.'], 400);
