<?php
// Import security functions first
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require dirname(__DIR__) . '/includes/project-workflow.php';

// Set no-cache headers to prevent back button access
set_no_cache_headers();

// Check authentication
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.progress.view', ['admin','department_admin','super_admin']);
$rbacAction = strtolower(trim((string)($_REQUEST['action'] ?? '')));
rbac_require_action_matrix(
    $rbacAction,
    [
        'load_status_requests' => 'admin.progress.view',
        'admin_decide_status_request' => 'admin.progress.manage',
        'load_projects' => 'admin.progress.view',
        'export_projects_csv' => 'admin.projects.export',
    ],
    'admin.progress.view'
);

// Check for suspicious activity
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

function progress_projects_has_created_at(mysqli $db): bool
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

function progress_project_has_column(mysqli $db, string $columnName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function progress_table_has_column(mysqli $db, string $tableName, string $columnName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function progress_table_exists(mysqli $db, string $tableName): bool
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
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) $result->free();
    $stmt->close();
    return $exists;
}

function progress_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bindArgs = [$types];
    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bindArgs);
}

function progress_ensure_status_request_table(mysqli $db): void
{
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
        INDEX idx_project_time (project_id, requested_at)
    )");
}

// Handle API requests first (before rendering HTML)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_status_requests') {
    header('Content-Type: application/json');
    progress_ensure_status_request_table($db);
    $rows = [];
    $sql = "SELECT sr.id, sr.project_id, p.code, p.name, sr.requested_status, sr.contractor_note, sr.requested_at,
                   sr.engineer_decision, sr.engineer_note, sr.admin_decision, sr.admin_note
            FROM project_status_requests sr
            INNER JOIN projects p ON p.id = sr.project_id
            ORDER BY sr.requested_at DESC
            LIMIT 250";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'admin_decide_status_request') {
    header('Content-Type: application/json');
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    progress_ensure_status_request_table($db);
    $id = (int) ($_POST['request_id'] ?? 0);
    $decision = trim((string) ($_POST['admin_decision'] ?? ''));
    $note = trim((string) ($_POST['admin_note'] ?? ''));
    $allowed = ['Approved', 'Rejected'];
    if ($id <= 0 || !in_array($decision, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid decision']);
        exit;
    }
    $adminId = (int) ($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("UPDATE project_status_requests
                          SET admin_decision = ?, admin_note = ?, admin_decided_by = ?, admin_decided_at = NOW()
                          WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    $stmt->bind_param('ssii', $decision, $note, $adminId, $id);
    $stmt->execute();
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('project_status_request.admin_decision', 'project_status_request', $id, [
            'decision' => $decision,
            'note' => $note
        ]);
    }

    if ($decision === 'Approved') {
        $s = $db->prepare("SELECT project_id, requested_status FROM project_status_requests WHERE id = ? LIMIT 1");
        if ($s) {
            $s->bind_param('i', $id);
            $s->execute();
            $r = $s->get_result();
            $row = $r ? $r->fetch_assoc() : null;
            $s->close();
            if ($row) {
                $pid = (int) ($row['project_id'] ?? 0);
                $requestedStatus = (string) ($row['requested_status'] ?? 'Draft');
                $transition = pw_validate_transition($db, $pid, $requestedStatus);
                if ($transition['ok']) {
                    $newStatus = (string)($transition['next'] ?? 'Draft');
                    $u = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
                    if ($u) {
                        $u->bind_param('si', $newStatus, $pid);
                        $u->execute();
                        $u->close();
                        $actorId = (int)($_SESSION['employee_id'] ?? 0);
                        $fromStatus = (string)($transition['current'] ?? '');
                        pw_log_status_history(
                            $db,
                            $pid,
                            $newStatus,
                            $actorId,
                            "Status request approved in Progress Monitoring. {$fromStatus} -> {$newStatus}"
                        );
                        if (function_exists('rbac_audit')) {
                            rbac_audit('project.status_update_approved', 'project', $pid, [
                                'from' => $fromStatus,
                                'to' => $newStatus,
                                'request_id' => $id
                            ]);
                        }
                    }
                } else {
                    // If transition is invalid, automatically mark decision as rejected with reason.
                    $invalidReason = (string)($transition['message'] ?? 'Invalid transition');
                    $fixStmt = $db->prepare("UPDATE project_status_requests
                                             SET admin_decision = 'Rejected',
                                                 admin_note = CONCAT(COALESCE(admin_note,''), CASE WHEN COALESCE(admin_note,'') = '' THEN '' ELSE '\n' END, ?)
                                             WHERE id = ?");
                    if ($fixStmt) {
                        $fixStmt->bind_param('si', $invalidReason, $id);
                        $fixStmt->execute();
                        $fixStmt->close();
                    }
                }
            }
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $hasCreatedAt = progress_projects_has_created_at($db);
    $hasPriorityPercent = progress_project_has_column($db, 'priority_percent');
    $hasDurationMonths = progress_project_has_column($db, 'duration_months');
    $hasStartDate = progress_project_has_column($db, 'start_date');
    $hasEndDate = progress_project_has_column($db, 'end_date');
    $hasTaskTable = progress_table_exists($db, 'project_tasks');
    $hasMilestoneTable = progress_table_exists($db, 'project_milestones');
    $hasAssignmentsTable = progress_table_exists($db, 'contractor_project_assignments');
    $hasProgressUpdatesTable = progress_table_exists($db, 'project_progress_updates');
    $contractorCompanyCol = progress_table_has_column($db, 'contractors', 'company') ? 'company' : (progress_table_has_column($db, 'contractors', 'company_name') ? 'company_name' : (progress_table_has_column($db, 'contractors', 'name') ? 'name' : null));
    $contractorRatingCol = progress_table_has_column($db, 'contractors', 'rating') ? 'rating' : null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 12);
    if ($perPage < 1) {
        $perPage = 12;
    }
    if ($perPage > 50) {
        $perPage = 50;
    }
    $offset = ($page - 1) * $perPage;
    $query = strtolower(trim((string) ($_GET['q'] ?? '')));
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    $sectorFilter = trim((string) ($_GET['sector'] ?? ''));
    $progressBand = trim((string) ($_GET['progress_band'] ?? ''));
    $contractorMode = trim((string) ($_GET['contractor_mode'] ?? ''));
    $sort = trim((string) ($_GET['sort'] ?? 'createdAt_desc'));

    $selectFields = [
        'p.id',
        'p.code',
        'p.name',
        'p.description',
        'p.location',
        'p.province',
        'p.sector',
        'p.budget',
        'p.status',
        'p.priority'
    ];
    $selectFields[] = $hasPriorityPercent ? 'p.priority_percent' : '0 AS priority_percent';
    $selectFields[] = $hasStartDate ? 'p.start_date' : 'NULL AS start_date';
    $selectFields[] = $hasEndDate ? 'p.end_date' : 'NULL AS end_date';
    $selectFields[] = $hasDurationMonths ? 'p.duration_months' : 'NULL AS duration_months';
    $selectFields[] = $hasCreatedAt ? 'p.created_at' : 'NULL AS created_at';
    $selectFields[] = 'COALESCE(lp.progress_percent, 0) AS progress_calc';
    $selectFields[] = 'lp.created_at AS progress_updated_at';

    $progressJoin = '';
    if ($hasProgressUpdatesTable) {
        $progressJoin = "LEFT JOIN (
            SELECT p1.project_id, p1.progress_percent, p1.created_at
            FROM project_progress_updates p1
            INNER JOIN (
                SELECT project_id, MAX(created_at) AS max_created
                FROM project_progress_updates
                GROUP BY project_id
            ) p2 ON p1.project_id = p2.project_id AND p1.created_at = p2.max_created
        ) lp ON lp.project_id = p.id";
    } else {
        $progressJoin = "LEFT JOIN (SELECT NULL AS project_id, 0 AS progress_percent, NULL AS created_at) lp ON lp.project_id = p.id";
    }

    $whereClauses = [];
    $bindTypes = '';
    $bindParams = [];

    if ($query !== '') {
        $like = '%' . $query . '%';
        $whereClauses[] = '(LOWER(COALESCE(p.code, \'\')) LIKE ? OR LOWER(COALESCE(p.name, \'\')) LIKE ? OR LOWER(COALESCE(p.location, \'\')) LIKE ?)';
        $bindTypes .= 'sss';
        $bindParams[] = $like;
        $bindParams[] = $like;
        $bindParams[] = $like;
    }
    if ($statusFilter !== '') {
        $whereClauses[] = 'p.status = ?';
        $bindTypes .= 's';
        $bindParams[] = $statusFilter;
    }
    if ($sectorFilter !== '') {
        $whereClauses[] = 'p.sector = ?';
        $bindTypes .= 's';
        $bindParams[] = $sectorFilter;
    }
    if ($progressBand !== '') {
        $parts = explode('-', $progressBand);
        if (count($parts) === 2) {
            $min = max(0, min(100, (float) $parts[0]));
            $max = max(0, min(100, (float) $parts[1]));
            if ($min > $max) {
                $tmp = $min;
                $min = $max;
                $max = $tmp;
            }
            $whereClauses[] = 'COALESCE(lp.progress_percent, 0) BETWEEN ? AND ?';
            $bindTypes .= 'dd';
            $bindParams[] = $min;
            $bindParams[] = $max;
        }
    }
    if ($contractorMode === 'assigned') {
        $whereClauses[] = $hasAssignmentsTable
            ? 'EXISTS (SELECT 1 FROM contractor_project_assignments cpa WHERE cpa.project_id = p.id)'
            : '1 = 0';
    } elseif ($contractorMode === 'unassigned') {
        $whereClauses[] = $hasAssignmentsTable
            ? 'NOT EXISTS (SELECT 1 FROM contractor_project_assignments cpa WHERE cpa.project_id = p.id)'
            : '1 = 1';
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }
    $fromSql = "FROM projects p {$progressJoin} {$whereSql}";

    $sortMap = [
        'createdAt_desc' => $hasCreatedAt ? 'p.created_at DESC' : 'p.id DESC',
        'createdAt_asc' => $hasCreatedAt ? 'p.created_at ASC' : 'p.id ASC',
        'progress_desc' => 'progress_calc DESC',
        'progress_asc' => 'progress_calc ASC'
    ];
    $orderBy = $sortMap[$sort] ?? ($hasCreatedAt ? 'p.created_at DESC' : 'p.id DESC');

    $totalFiltered = 0;
    $stats = [
        'total' => 0,
        'approved' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'assigned_engineers' => 0
    ];
    $projects = [];

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) AS total {$fromSql}");
        if (!$countStmt) {
            throw new RuntimeException('Failed to prepare project count query');
        }
        if (!progress_bind_dynamic($countStmt, $bindTypes, $bindParams)) {
            throw new RuntimeException('Failed to bind project count query');
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        if ($countRes) {
            $countRow = $countRes->fetch_assoc();
            $totalFiltered = (int) ($countRow['total'] ?? 0);
            $countRes->free();
        }
        $countStmt->close();

        $assignedExpr = $hasAssignmentsTable
            ? 'SUM(CASE WHEN EXISTS (SELECT 1 FROM contractor_project_assignments cpa WHERE cpa.project_id = p.id) THEN 1 ELSE 0 END)'
            : '0';
        $statsStmt = $db->prepare("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN p.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN COALESCE(lp.progress_percent, 0) > 0 AND COALESCE(lp.progress_percent, 0) < 100 THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN COALESCE(lp.progress_percent, 0) >= 100 OR p.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                {$assignedExpr} AS assigned_engineers
            {$fromSql}");
        if ($statsStmt && progress_bind_dynamic($statsStmt, $bindTypes, $bindParams)) {
            $statsStmt->execute();
            $statsRes = $statsStmt->get_result();
            if ($statsRes) {
                $statsRow = $statsRes->fetch_assoc();
                if ($statsRow) {
                    $stats = [
                        'total' => (int) ($statsRow['total'] ?? 0),
                        'approved' => (int) ($statsRow['approved'] ?? 0),
                        'in_progress' => (int) ($statsRow['in_progress'] ?? 0),
                        'completed' => (int) ($statsRow['completed'] ?? 0),
                        'assigned_engineers' => (int) ($statsRow['assigned_engineers'] ?? 0)
                    ];
                }
                $statsRes->free();
            }
            $statsStmt->close();
        }

        $dataSql = "SELECT " . implode(', ', $selectFields) . " {$fromSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $dataStmt = $db->prepare($dataSql);
        if (!$dataStmt) {
            throw new RuntimeException('Failed to prepare project data query');
        }
        $dataTypes = $bindTypes . 'ii';
        $dataParams = $bindParams;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;
        if (!progress_bind_dynamic($dataStmt, $dataTypes, $dataParams)) {
            throw new RuntimeException('Failed to bind project data query');
        }
        $dataStmt->execute();
        $result = $dataStmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
            $row['progress'] = (float) ($row['progress_calc'] ?? 0);
            unset($row['progress_calc']);
            $updateDate = $row['progress_updated_at'] ?? ($row['created_at'] ?? $row['start_date'] ?? null);
            $status = (string)($row['status'] ?? 'Draft');
            $row['process_update'] = $status . ($updateDate ? ' (' . date('M d, Y', strtotime((string)$updateDate)) . ')' : '');
            
            // Get Assigned Engineers for this project
            $contractorsQuery = false;
            if ($hasAssignmentsTable) {
                $contractorsQuery = $db->query("
                    SELECT c.id, " . ($contractorCompanyCol ? "c.{$contractorCompanyCol}" : "''") . " AS company, " . ($contractorRatingCol ? "c.{$contractorRatingCol}" : "0") . " AS rating
                    FROM contractors c
                    INNER JOIN contractor_project_assignments cpa ON c.id = cpa.contractor_id
                    WHERE cpa.project_id = " . intval($row['id'])
                );
            }
            
            $Engineers = [];
            if ($contractorsQuery) {
                while ($Engineer = $contractorsQuery->fetch_assoc()) {
                    $Engineers[] = $Engineer;
                }
                $contractorsQuery->free();
            }
            
            $row['assigned_contractors'] = $Engineers;
            $row['task_summary'] = [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
                'actual' => 0
            ];
            $row['milestone_summary'] = [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
                'actual' => 0
            ];

            if ($hasTaskTable) {
                $taskSql = "SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN planned_start IS NOT NULL OR planned_end IS NOT NULL THEN 1 ELSE 0 END) AS planned, SUM(CASE WHEN actual_start IS NOT NULL OR actual_end IS NOT NULL THEN 1 ELSE 0 END) AS actual FROM project_tasks WHERE project_id = " . intval($row['id']);
                $taskRes = $db->query($taskSql);
                if ($taskRes) {
                    $taskStats = $taskRes->fetch_assoc();
                    $taskRes->free();
                    if ($taskStats) {
                        $row['task_summary'] = [
                            'total' => (int) ($taskStats['total'] ?? 0),
                            'completed' => (int) ($taskStats['completed'] ?? 0),
                            'planned' => (int) ($taskStats['planned'] ?? 0),
                            'actual' => (int) ($taskStats['actual'] ?? 0)
                        ];
                    }
                }
            }
            if ($hasMilestoneTable) {
                $mileSql = "SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN planned_date IS NOT NULL THEN 1 ELSE 0 END) AS planned, SUM(CASE WHEN actual_date IS NOT NULL THEN 1 ELSE 0 END) AS actual FROM project_milestones WHERE project_id = " . intval($row['id']);
                $mileRes = $db->query($mileSql);
                if ($mileRes) {
                    $mileStats = $mileRes->fetch_assoc();
                    $mileRes->free();
                    if ($mileStats) {
                        $row['milestone_summary'] = [
                            'total' => (int) ($mileStats['total'] ?? 0),
                            'completed' => (int) ($mileStats['completed'] ?? 0),
                            'planned' => (int) ($mileStats['planned'] ?? 0),
                            'actual' => (int) ($mileStats['actual'] ?? 0)
                        ];
                    }
                }
            }
            $projects[] = $row;
        }
            $result->free();
        }
        $dataStmt->close();
    } catch (Throwable $e) {
        error_log('progress_monitoring load_projects error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load projects',
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1, 'has_prev' => false, 'has_next' => false],
            'stats' => $stats
        ]);
        exit;
    }

    $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    echo json_encode([
        'success' => true,
        'data' => $projects,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalFiltered,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ],
        'stats' => $stats
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_projects_csv') {
    $hasCreatedAt = progress_projects_has_created_at($db);
    $hasAssignmentsTable = progress_table_exists($db, 'contractor_project_assignments');
    $hasProgressUpdatesTable = progress_table_exists($db, 'project_progress_updates');

    $query = strtolower(trim((string) ($_GET['q'] ?? '')));
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    $sectorFilter = trim((string) ($_GET['sector'] ?? ''));
    $progressBand = trim((string) ($_GET['progress_band'] ?? ''));
    $contractorMode = trim((string) ($_GET['contractor_mode'] ?? ''));
    $sort = trim((string) ($_GET['sort'] ?? 'createdAt_desc'));

    $progressJoin = '';
    if ($hasProgressUpdatesTable) {
        $progressJoin = "LEFT JOIN (
            SELECT p1.project_id, p1.progress_percent, p1.created_at
            FROM project_progress_updates p1
            INNER JOIN (
                SELECT project_id, MAX(created_at) AS max_created
                FROM project_progress_updates
                GROUP BY project_id
            ) p2 ON p1.project_id = p2.project_id AND p1.created_at = p2.max_created
        ) lp ON lp.project_id = p.id";
    } else {
        $progressJoin = "LEFT JOIN (SELECT NULL AS project_id, 0 AS progress_percent, NULL AS created_at) lp ON lp.project_id = p.id";
    }

    $whereClauses = [];
    $bindTypes = '';
    $bindParams = [];

    if ($query !== '') {
        $like = '%' . $query . '%';
        $whereClauses[] = '(LOWER(COALESCE(p.code, \'\')) LIKE ? OR LOWER(COALESCE(p.name, \'\')) LIKE ? OR LOWER(COALESCE(p.location, \'\')) LIKE ?)';
        $bindTypes .= 'sss';
        $bindParams[] = $like;
        $bindParams[] = $like;
        $bindParams[] = $like;
    }
    if ($statusFilter !== '') {
        $whereClauses[] = 'p.status = ?';
        $bindTypes .= 's';
        $bindParams[] = $statusFilter;
    }
    if ($sectorFilter !== '') {
        $whereClauses[] = 'p.sector = ?';
        $bindTypes .= 's';
        $bindParams[] = $sectorFilter;
    }
    if ($progressBand !== '') {
        $parts = explode('-', $progressBand);
        if (count($parts) === 2) {
            $min = max(0, min(100, (float) $parts[0]));
            $max = max(0, min(100, (float) $parts[1]));
            if ($min > $max) {
                $tmp = $min;
                $min = $max;
                $max = $tmp;
            }
            $whereClauses[] = 'COALESCE(lp.progress_percent, 0) BETWEEN ? AND ?';
            $bindTypes .= 'dd';
            $bindParams[] = $min;
            $bindParams[] = $max;
        }
    }
    if ($contractorMode === 'assigned') {
        $whereClauses[] = $hasAssignmentsTable
            ? 'EXISTS (SELECT 1 FROM contractor_project_assignments cpa WHERE cpa.project_id = p.id)'
            : '1 = 0';
    } elseif ($contractorMode === 'unassigned') {
        $whereClauses[] = $hasAssignmentsTable
            ? 'NOT EXISTS (SELECT 1 FROM contractor_project_assignments cpa WHERE cpa.project_id = p.id)'
            : '1 = 1';
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $sortMap = [
        'createdAt_desc' => $hasCreatedAt ? 'p.created_at DESC' : 'p.id DESC',
        'createdAt_asc' => $hasCreatedAt ? 'p.created_at ASC' : 'p.id ASC',
        'progress_desc' => 'progress_calc DESC',
        'progress_asc' => 'progress_calc ASC'
    ];
    $orderBy = $sortMap[$sort] ?? ($hasCreatedAt ? 'p.created_at DESC' : 'p.id DESC');

    $fromSql = "FROM projects p {$progressJoin} {$whereSql}";
    $sql = "SELECT p.code, p.name, p.status, p.sector, p.location, p.budget,
                   COALESCE(lp.progress_percent, 0) AS progress_calc,
                   p.start_date, p.end_date,
                   (
                     SELECT COUNT(*)
                     FROM contractor_project_assignments cpa
                     WHERE cpa.project_id = p.id
                   ) AS engineers_count
            {$fromSql}
            ORDER BY {$orderBy}";

    $rows = [];
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare export query');
        }
        if (!progress_bind_dynamic($stmt, $bindTypes, $bindParams)) {
            throw new RuntimeException('Failed to bind export query');
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
    } catch (Throwable $e) {
        error_log('progress_monitoring export_projects_csv error: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to export CSV']);
        exit;
    }

    $filename = 'progress-monitoring-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Project Name', 'Status', 'Sector', 'Location', 'Budget', 'Progress', 'Engineers', 'Start Date', 'End Date']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string) ($r['code'] ?? ''),
            (string) ($r['name'] ?? ''),
            (string) ($r['status'] ?? ''),
            (string) ($r['sector'] ?? ''),
            (string) ($r['location'] ?? ''),
            (float) ($r['budget'] ?? 0),
            (string) ((float) ($r['progress_calc'] ?? 0)) . '%',
            (int) ($r['engineers_count'] ?? 0),
            (string) ($r['start_date'] ?? ''),
            (string) ($r['end_date'] ?? '')
        ]);
    }
    fclose($out);
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
        
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - LGU IPMS</title>
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
        <link rel="stylesheet" href="../assets/css/admin-progress-monitoring.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-progress-monitoring.css'); ?>">
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
            <a href="progress_monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="registered_engineers.php" class="nav-main-item" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
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
            <h1>Progress Monitoring</h1>
            <p>Track and manage project progress in real-time</p>
        </div>

        <div class="pm-section card">
            <!-- Statistics Summary -->
            <div class="pm-stats-wrapper">
                <div class="stat-box stat-total">
                    <div class="stat-number" id="statTotal">0</div>
                    <div class="stat-label">Total Projects</div>
                </div>
                <div class="stat-box stat-approved">
                    <div class="stat-number" id="statApproved">0</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-box stat-progress">
                    <div class="stat-number" id="statInProgress">0</div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-box stat-completed">
                    <div class="stat-number" id="statCompleted">0</div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-box stat-contractors">
                    <div class="stat-number" id="statContractors">0</div>
                    <div class="stat-label">Assigned Engineers</div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="pm-controls-wrapper">
                <div class="pm-controls">
                    <div class="pm-top-row">
                        <div class="pm-left">
                            <label for="pmSearch">Search Projects</label>
                            <input id="pmSearch" type="search" placeholder="Search by code, name, or location...">
                        </div>
                        <button id="exportCsv" type="button" class="btn-export">Export CSV</button>
                    </div>

                    <div class="pm-right">
                        <div class="filter-group">
                            <label for="pmStatusFilter">Status</label>
                            <select id="pmStatusFilter" title="Filter by status">
                                <option value="">All Status</option>
                                <option>Draft</option>
                                <option>For Approval</option>
                                <option>Approved</option>
                                <option>On-hold</option>
                                <option>Cancelled</option>
                                <option>Completed</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmSectorFilter">Sector</label>
                            <select id="pmSectorFilter" title="Filter by sector">
                                <option value="">All Sectors</option>
                                <option>Road</option>
                                <option>Drainage</option>
                                <option>Building</option>
                                <option>Water</option>
                                <option>Sanitation</option>
                                <option>Other</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmProgressFilter">Progress</label>
                            <select id="pmProgressFilter" title="Filter by progress">
                                <option value="">All Progress</option>
                                <option value="0-25">0-25%</option>
                                <option value="25-50">25-50%</option>
                                <option value="50-75">50-75%</option>
                                <option value="75-100">75-100%</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmContractorFilter">Has Engineers</label>
                            <select id="pmContractorFilter" title="Filter by engineers">
                                <option value="">All Projects</option>
                                <option value="assigned">With Engineers</option>
                                <option value="unassigned">No Engineers</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="pmSort">Sort</label>
                            <select id="pmSort" title="Sort">
                                <option value="createdAt_desc">Newest</option>
                                <option value="createdAt_asc">Oldest</option>
                                <option value="progress_desc">Progress (high to low)</option>
                                <option value="progress_asc">Progress (low to high)</option>
                            </select>
                        </div>
                    </div>

                    <div class="pm-bottom-row">
                        <div id="pmQuickFilters" class="pm-quick-filters" aria-label="Quick status filters">
                            <button type="button" data-status="" class="active">All</button>
                            <button type="button" data-status="For Approval">For Approval</button>
                            <button type="button" data-status="Approved">Approved</button>
                            <button type="button" data-status="On-hold">On-hold</button>
                            <button type="button" data-status="Completed">Completed</button>
                        </div>
                        <div class="pm-utility-row">
                            <span id="pmResultSummary" class="pm-result-summary">Showing 0 of 0 projects</span>
                            <button id="pmClearFilters" type="button" class="btn-clear-filters">Clear Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Display -->
            <div class="pm-content">
                <h3>Tracked Projects</h3>
                <div id="projectsList" class="projects-list">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Loading projects...</p>
                    </div>
                </div>

                <div id="pmEmpty" class="pm-empty ac-c8be1ccb">
                    <div class="empty-state">
                        <div class="empty-icon">No Match</div>
                        <p>No projects match your filters</p>
                        <small>Try adjusting your search criteria</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>









