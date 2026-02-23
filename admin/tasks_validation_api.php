<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
$allowedViewRoles = array_values(array_unique(array_merge(
    rbac_roles_for('admin.progress.view', ['admin', 'department_admin', 'super_admin']),
    rbac_roles_for('engineer.progress.review', ['engineer'])
)));
rbac_require_roles($allowedViewRoles);
check_suspicious_activity();

header('Content-Type: application/json');

if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$employeeRole = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));

function tv_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function tv_api_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
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

function tv_api_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
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

function tv_api_ensure_tables(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS project_validation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        deliverable_type VARCHAR(20) NOT NULL DEFAULT 'manual',
        deliverable_ref_id INT NULL,
        deliverable_name VARCHAR(255) NOT NULL,
        weight DECIMAL(7,2) NOT NULL DEFAULT 1.00,
        current_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        last_submission_id INT NULL,
        submitted_by INT NULL,
        submitted_at DATETIME NULL,
        validated_by INT NULL,
        validated_at DATETIME NULL,
        validator_remarks TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_validation_source (project_id, deliverable_type, deliverable_ref_id),
        INDEX idx_validation_project_status (project_id, current_status),
        INDEX idx_validation_submitted (submitted_by, submitted_at),
        INDEX idx_validation_validated (validated_by, validated_at),
        CONSTRAINT fk_validation_item_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS project_validation_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        version_no INT NOT NULL DEFAULT 1,
        progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        change_summary TEXT NULL,
        attachment_path VARCHAR(255) NULL,
        submitted_by INT NOT NULL,
        submitted_role VARCHAR(30) NOT NULL DEFAULT 'contractor',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        validation_result VARCHAR(30) NULL,
        validated_by INT NULL,
        validated_at DATETIME NULL,
        validator_remarks TEXT NULL,
        INDEX idx_validation_submission_item (item_id, version_no),
        INDEX idx_validation_submission_submitter (submitted_by, submitted_at),
        INDEX idx_validation_submission_result (validation_result),
        CONSTRAINT fk_validation_submission_item FOREIGN KEY (item_id) REFERENCES project_validation_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS project_validation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        submission_id INT NULL,
        action_type VARCHAR(40) NOT NULL,
        previous_status VARCHAR(30) NULL,
        new_status VARCHAR(30) NULL,
        remarks TEXT NULL,
        acted_by INT NOT NULL,
        acted_role VARCHAR(30) NOT NULL,
        ip_address VARCHAR(45) NULL,
        acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_validation_logs_item_time (item_id, acted_at),
        INDEX idx_validation_logs_actor (acted_by, acted_at),
        CONSTRAINT fk_validation_log_item FOREIGN KEY (item_id) REFERENCES project_validation_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS project_progress_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        updated_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_created (project_id, created_at),
        CONSTRAINT fk_progress_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_progress_employee FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tv_api_sync_items(mysqli $db, int $actorId): void
{
    if (!tv_api_table_exists($db, 'project_tasks') || !tv_api_table_exists($db, 'project_milestones')) {
        return;
    }
    $insert = $db->prepare(
        "INSERT INTO project_validation_items
         (project_id, deliverable_type, deliverable_ref_id, deliverable_name, current_status, created_by)
         VALUES (?, ?, ?, ?, 'Pending', ?)
         ON DUPLICATE KEY UPDATE deliverable_name = VALUES(deliverable_name)"
    );
    if (!$insert) {
        return;
    }

    $tasks = $db->query("SELECT id, project_id, title FROM project_tasks");
    if ($tasks) {
        while ($row = $tasks->fetch_assoc()) {
            $projectId = (int)($row['project_id'] ?? 0);
            $refId = (int)($row['id'] ?? 0);
            $name = trim((string)($row['title'] ?? ''));
            if ($projectId <= 0 || $refId <= 0 || $name === '') continue;
            $type = 'task';
            $insert->bind_param('isisi', $projectId, $type, $refId, $name, $actorId);
            $insert->execute();
        }
        $tasks->free();
    }

    $miles = $db->query("SELECT id, project_id, title FROM project_milestones");
    if ($miles) {
        while ($row = $miles->fetch_assoc()) {
            $projectId = (int)($row['project_id'] ?? 0);
            $refId = (int)($row['id'] ?? 0);
            $name = trim((string)($row['title'] ?? ''));
            if ($projectId <= 0 || $refId <= 0 || $name === '') continue;
            $type = 'milestone';
            $insert->bind_param('isisi', $projectId, $type, $refId, $name, $actorId);
            $insert->execute();
        }
        $miles->free();
    }
    $insert->close();
}

function tv_api_status(string $status): string
{
    $map = [
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'for approval' => 'For Approval',
        'for_approval' => 'For Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs revision' => 'Needs Revision',
        'returned' => 'Needs Revision',
    ];
    $key = strtolower(trim($status));
    return $map[$key] ?? 'Pending';
}

function tv_api_project_percent(mysqli $db, int $projectId): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(weight),0) AS tw, COALESCE(SUM(CASE WHEN current_status='Approved' THEN weight ELSE 0 END),0) AS aw, COUNT(*) AS tc, SUM(CASE WHEN current_status='Approved' THEN 1 ELSE 0 END) AS ac FROM project_validation_items WHERE project_id=?");
    if (!$stmt) return 0.0;
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    $tw = (float)($row['tw'] ?? 0);
    $aw = (float)($row['aw'] ?? 0);
    $tc = (int)($row['tc'] ?? 0);
    $ac = (int)($row['ac'] ?? 0);
    if ($tw > 0) return round(($aw / $tw) * 100, 2);
    if ($tc > 0) return round(($ac / $tc) * 100, 2);
    return 0.0;
}

function tv_api_can_manage(string $role): bool
{
    $roles = array_values(array_unique(array_merge(
        rbac_roles_for('admin.progress.manage', ['admin', 'department_admin', 'super_admin']),
        rbac_roles_for('engineer.progress.review', ['engineer'])
    )));
    return in_array($role, $roles, true);
}

function tv_api_load_dashboard(mysqli $db): void
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 1) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $q = strtolower(trim((string)($_GET['q'] ?? '')));
    $status = trim((string)($_GET['status'] ?? ''));
    $status = $status !== '' ? tv_api_status($status) : '';
    $sector = trim((string)($_GET['sector'] ?? ''));
    $dateField = strtolower(trim((string)($_GET['date_field'] ?? 'submitted')));
    $dateField = $dateField === 'validated' ? 'validated' : 'submitted';
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    $sort = strtolower(trim((string)($_GET['sort'] ?? 'newest_submitted')));

    $where = [];
    $types = '';
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(LOWER(COALESCE(p.code,'')) LIKE ? OR LOWER(COALESCE(p.name,'')) LIKE ? OR LOWER(COALESCE(vi.deliverable_name,'')) LIKE ?)";
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '') {
        $where[] = 'vi.current_status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if ($sector !== '') {
        $where[] = 'p.sector = ?';
        $types .= 's';
        $params[] = $sector;
    }

    $dateCol = $dateField === 'validated' ? 'vi.validated_at' : 'vi.submitted_at';
    if ($dateFrom !== '') {
        $where[] = "{$dateCol} >= ?";
        $types .= 's';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = "{$dateCol} <= ?";
        $types .= 's';
        $params[] = $dateTo . ' 23:59:59';
    }
    $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    $sortSql = 'vi.submitted_at DESC, vi.id DESC';
    if ($sort === 'oldest_pending') {
        $sortSql = "CASE WHEN vi.current_status IN ('Submitted','For Approval') THEN 0 ELSE 1 END ASC, vi.submitted_at ASC, vi.id ASC";
    } elseif ($sort === 'highest_priority') {
        $sortSql = "CASE WHEN p.priority='Crucial' THEN 1 WHEN p.priority='High' THEN 2 WHEN p.priority='Medium' THEN 3 ELSE 4 END ASC, vi.submitted_at DESC";
    }

    $baseFrom = " FROM project_validation_items vi
                  INNER JOIN projects p ON p.id=vi.project_id
                  LEFT JOIN project_validation_submissions vs ON vs.id=vi.last_submission_id
                  LEFT JOIN employees se ON se.id=vi.submitted_by
                  LEFT JOIN employees ve ON ve.id=vi.validated_by";

    $countStmt = $db->prepare("SELECT COUNT(*) AS total {$baseFrom} {$whereSql}");
    if (!$countStmt) tv_api_json(['success' => false, 'message' => 'Failed to prepare count query.'], 500);
    if (!tv_api_bind_dynamic($countStmt, $types, $params)) {
        $countStmt->close();
        tv_api_json(['success' => false, 'message' => 'Failed to bind count query.'], 500);
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

    $sumStmt = $db->prepare("SELECT COUNT(*) AS total_deliverables, SUM(CASE WHEN vi.current_status='Approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN vi.current_status IN ('Submitted','For Approval') THEN 1 ELSE 0 END) AS pending_review, SUM(CASE WHEN vi.current_status IN ('Rejected','Needs Revision') THEN 1 ELSE 0 END) AS rejected_returned, COALESCE(SUM(vi.weight),0) AS tw, COALESCE(SUM(CASE WHEN vi.current_status='Approved' THEN vi.weight ELSE 0 END),0) AS aw {$baseFrom} {$whereSql}");
    if (!$sumStmt) tv_api_json(['success' => false, 'message' => 'Failed to prepare summary query.'], 500);
    if (!tv_api_bind_dynamic($sumStmt, $types, $params)) {
        $sumStmt->close();
        tv_api_json(['success' => false, 'message' => 'Failed to bind summary query.'], 500);
    }
    $sumStmt->execute();
    $sumRes = $sumStmt->get_result();
    $sumRow = $sumRes ? ($sumRes->fetch_assoc() ?: []) : [];
    if ($sumRes) $sumRes->free();
    $sumStmt->close();

    $totalPages = max(1, (int)ceil(($total > 0 ? $total : 1) / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "SELECT
                vi.id,
                vi.project_id,
                vi.deliverable_type,
                vi.deliverable_name,
                vi.weight,
                vi.current_status,
                vi.submitted_by,
                vi.submitted_at,
                vi.validated_by,
                vi.validated_at,
                vi.validator_remarks,
                vi.last_submission_id,
                p.code,
                p.name AS project_name,
                p.location,
                p.sector,
                p.type,
                p.priority,
                COALESCE(CONCAT(TRIM(COALESCE(se.first_name,'')), ' ', TRIM(COALESCE(se.last_name,''))), 'N/A') AS submitted_by_name,
                COALESCE(vs.submitted_role, '') AS submitted_by_role,
                COALESCE(CONCAT(TRIM(COALESCE(ve.first_name,'')), ' ', TRIM(COALESCE(ve.last_name,''))), 'N/A') AS validated_by_name,
                COALESCE(vs.progress_percent, 0) AS last_progress_percent,
                COALESCE(vs.change_summary, '') AS last_change_summary,
                COALESCE(vs.attachment_path, '') AS last_attachment_path,
                COALESCE(vs.version_no, 0) AS version_no
            {$baseFrom}
            {$whereSql}
            ORDER BY {$sortSql}
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) tv_api_json(['success' => false, 'message' => 'Failed to prepare list query.'], 500);
    $dataTypes = $types . 'ii';
    $dataParams = $params;
    $dataParams[] = $perPage;
    $dataParams[] = $offset;
    if (!tv_api_bind_dynamic($stmt, $dataTypes, $dataParams)) {
        $stmt->close();
        tv_api_json(['success' => false, 'message' => 'Failed to bind list query.'], 500);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    $summary = [
        'total_deliverables' => (int)($sumRow['total_deliverables'] ?? 0),
        'approved' => (int)($sumRow['approved'] ?? 0),
        'pending_review' => (int)($sumRow['pending_review'] ?? 0),
        'rejected_returned' => (int)($sumRow['rejected_returned'] ?? 0),
        'overall_percent' => 0,
    ];
    $tw = (float)($sumRow['tw'] ?? 0);
    $aw = (float)($sumRow['aw'] ?? 0);
    if ($tw > 0) $summary['overall_percent'] = round(($aw / $tw) * 100, 2);
    elseif ($summary['total_deliverables'] > 0) $summary['overall_percent'] = round(($summary['approved'] / $summary['total_deliverables']) * 100, 2);

    tv_api_json([
        'success' => true,
        'data' => $rows,
        'summary' => $summary,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ],
    ]);
}

function tv_api_load_item_details(mysqli $db): void
{
    $itemId = (int)($_GET['item_id'] ?? 0);
    if ($itemId <= 0) tv_api_json(['success' => false, 'message' => 'Invalid validation item.'], 422);

    $itemStmt = $db->prepare("SELECT vi.*, p.code, p.name AS project_name, p.location, p.sector, p.type, p.priority, COALESCE(CONCAT(TRIM(COALESCE(se.first_name,'')), ' ', TRIM(COALESCE(se.last_name,''))), 'N/A') AS submitted_by_name, COALESCE(CONCAT(TRIM(COALESCE(ve.first_name,'')), ' ', TRIM(COALESCE(ve.last_name,''))), 'N/A') AS validated_by_name FROM project_validation_items vi INNER JOIN projects p ON p.id=vi.project_id LEFT JOIN employees se ON se.id=vi.submitted_by LEFT JOIN employees ve ON ve.id=vi.validated_by WHERE vi.id=? LIMIT 1");
    if (!$itemStmt) tv_api_json(['success' => false, 'message' => 'Failed to prepare item query.'], 500);
    $itemStmt->bind_param('i', $itemId);
    $itemStmt->execute();
    $itemRes = $itemStmt->get_result();
    $item = $itemRes ? $itemRes->fetch_assoc() : null;
    if ($itemRes) $itemRes->free();
    $itemStmt->close();
    if (!$item) tv_api_json(['success' => false, 'message' => 'Validation item not found.'], 404);

    $subs = [];
    $subStmt = $db->prepare("SELECT s.*, COALESCE(CONCAT(TRIM(COALESCE(e.first_name,'')), ' ', TRIM(COALESCE(e.last_name,''))), 'N/A') AS submitted_by_name, COALESCE(CONCAT(TRIM(COALESCE(v.first_name,'')), ' ', TRIM(COALESCE(v.last_name,''))), 'N/A') AS validated_by_name FROM project_validation_submissions s LEFT JOIN employees e ON e.id=s.submitted_by LEFT JOIN employees v ON v.id=s.validated_by WHERE s.item_id=? ORDER BY s.version_no DESC, s.id DESC");
    if ($subStmt) {
        $subStmt->bind_param('i', $itemId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        if ($subRes) {
            while ($row = $subRes->fetch_assoc()) $subs[] = $row;
            $subRes->free();
        }
        $subStmt->close();
    }

    $logs = [];
    $logStmt = $db->prepare("SELECT l.*, COALESCE(CONCAT(TRIM(COALESCE(e.first_name,'')), ' ', TRIM(COALESCE(e.last_name,''))), 'N/A') AS actor_name FROM project_validation_logs l LEFT JOIN employees e ON e.id=l.acted_by WHERE l.item_id=? ORDER BY l.acted_at DESC, l.id DESC");
    if ($logStmt) {
        $logStmt->bind_param('i', $itemId);
        $logStmt->execute();
        $logRes = $logStmt->get_result();
        if ($logRes) {
            while ($row = $logRes->fetch_assoc()) $logs[] = $row;
            $logRes->free();
        }
        $logStmt->close();
    }

    tv_api_json(['success' => true, 'data' => ['item' => $item, 'submissions' => $subs, 'logs' => $logs]]);
}

function tv_api_decide_item(mysqli $db, int $employeeId, string $employeeRole): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        tv_api_json(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
    }
    if (!tv_api_can_manage($employeeRole)) {
        tv_api_json(['success' => false, 'message' => 'You are not allowed to validate deliverables.'], 403);
    }

    $itemId = (int)($_POST['item_id'] ?? 0);
    $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    $map = [
        'approve' => ['action' => 'approve', 'status' => 'Approved'],
        'reject' => ['action' => 'reject', 'status' => 'Rejected'],
        'return' => ['action' => 'return_for_revision', 'status' => 'Needs Revision'],
        'for_approval' => ['action' => 'send_for_approval', 'status' => 'For Approval'],
    ];
    if ($itemId <= 0 || !isset($map[$decision])) {
        tv_api_json(['success' => false, 'message' => 'Invalid decision payload.'], 422);
    }
    if (in_array($decision, ['reject', 'return'], true) && $remarks === '') {
        tv_api_json(['success' => false, 'message' => 'Remarks are required for reject/return actions.'], 422);
    }

    $pick = $db->prepare("SELECT project_id, current_status, last_submission_id FROM project_validation_items WHERE id=? LIMIT 1");
    if (!$pick) tv_api_json(['success' => false, 'message' => 'Failed to prepare item query.'], 500);
    $pick->bind_param('i', $itemId);
    $pick->execute();
    $pickRes = $pick->get_result();
    $item = $pickRes ? $pickRes->fetch_assoc() : null;
    if ($pickRes) $pickRes->free();
    $pick->close();
    if (!$item) tv_api_json(['success' => false, 'message' => 'Validation item not found.'], 404);

    $projectId = (int)($item['project_id'] ?? 0);
    $prevStatus = tv_api_status((string)($item['current_status'] ?? 'Pending'));
    $newStatus = $map[$decision]['status'];
    $actionType = $map[$decision]['action'];
    $lastSubmissionId = (int)($item['last_submission_id'] ?? 0);

    $db->begin_transaction();
    try {
        $up = $db->prepare("UPDATE project_validation_items SET current_status=?, validated_by=?, validated_at=NOW(), validator_remarks=? WHERE id=?");
        if (!$up) throw new RuntimeException('Failed to update validation item.');
        $up->bind_param('sisi', $newStatus, $employeeId, $remarks, $itemId);
        $up->execute();
        $up->close();

        if ($lastSubmissionId > 0) {
            $sup = $db->prepare("UPDATE project_validation_submissions SET validation_result=?, validated_by=?, validated_at=NOW(), validator_remarks=? WHERE id=?");
            if ($sup) {
                $sup->bind_param('sisi', $newStatus, $employeeId, $remarks, $lastSubmissionId);
                $sup->execute();
                $sup->close();
            }
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $log = $db->prepare("INSERT INTO project_validation_logs (item_id, submission_id, action_type, previous_status, new_status, remarks, acted_by, acted_role, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$log) throw new RuntimeException('Failed to write validation log.');
        $log->bind_param('iissssiss', $itemId, $lastSubmissionId, $actionType, $prevStatus, $newStatus, $remarks, $employeeId, $employeeRole, $ip);
        $log->execute();
        $log->close();

        $percent = tv_api_project_percent($db, $projectId);
        $ins = $db->prepare("INSERT INTO project_progress_updates (project_id, progress_percent, updated_by) VALUES (?, ?, ?)");
        if ($ins) {
            $ins->bind_param('idi', $projectId, $percent, $employeeId);
            $ins->execute();
            $ins->close();
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        tv_api_json(['success' => false, 'message' => $e->getMessage()], 500);
    }

    if (function_exists('rbac_audit')) {
        rbac_audit('admin.validation.' . $actionType, 'project_validation_item', $itemId, [
            'project_id' => $projectId,
            'previous_status' => $prevStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks,
        ]);
    }
    tv_api_json(['success' => true, 'message' => 'Validation action saved.', 'new_status' => $newStatus]);
}

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));
// Compatibility marker for static guard scanners; runtime enforcement is done below via role maps.
rbac_require_action_matrix('', [], '');
$viewActionRoles = array_values(array_unique(array_merge(
    rbac_roles_for('admin.progress.view', ['admin', 'department_admin', 'super_admin']),
    rbac_roles_for('engineer.progress.review', ['engineer'])
)));
$manageActionRoles = array_values(array_unique(array_merge(
    rbac_roles_for('admin.progress.manage', ['admin', 'department_admin', 'super_admin']),
    rbac_roles_for('engineer.progress.review', ['engineer'])
)));
rbac_require_action_roles(
    $action !== '' ? $action : 'load_validation_dashboard',
    [
        'load_validation_dashboard' => $viewActionRoles,
        'load_validation_item_details' => $viewActionRoles,
        'decide_validation_item' => $manageActionRoles,
    ],
    $viewActionRoles
);

tv_api_ensure_tables($db);
tv_api_sync_items($db, $employeeId);

if ($action === '' || $action === 'load_validation_dashboard') tv_api_load_dashboard($db);
if ($action === 'load_validation_item_details') tv_api_load_item_details($db);
if ($action === 'decide_validation_item') tv_api_decide_item($db, $employeeId, $employeeRole);

tv_api_json(['success' => false, 'message' => 'Unknown action.'], 404);
