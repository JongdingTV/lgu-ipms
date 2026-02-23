<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('department_head.approvals.view', ['department_head', 'department_admin', 'admin', 'super_admin']);
check_suspicious_activity();

if (!isset($_SESSION['employee_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$role = strtolower(trim((string)($_SESSION['employee_role'] ?? '')));
if (!in_array($role, ['department_head', 'department_admin', 'admin', 'super_admin'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function dept_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function dept_has_col(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function dept_pick_existing_column(mysqli $db, string $table, array $candidates): ?string
{
    foreach ($candidates as $col) {
        $col = trim((string)$col);
        if ($col !== '' && dept_has_col($db, $table, $col)) {
            return $col;
        }
    }
    return null;
}

function dept_has_table(mysqli $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function dept_bind(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || !$params) return true;
    $args = [$types];
    foreach ($params as $k => $v) $args[] = &$params[$k];
    return call_user_func_array([$stmt, 'bind_param'], $args);
}

function dept_ensure_tables(mysqli $db): void
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
        INDEX idx_decision_status (decision_status)
    )");
    $db->query("CREATE TABLE IF NOT EXISTS decision_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        decision_type VARCHAR(50) NOT NULL,
        notes TEXT NULL,
        decided_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_decision_logs_project_time (project_id, created_at)
    )");
    if (!dept_has_col($db, 'projects', 'priority_level')) $db->query("ALTER TABLE projects ADD COLUMN priority_level VARCHAR(20) NOT NULL DEFAULT 'Medium' AFTER priority");
    if (!dept_has_col($db, 'projects', 'approved_by')) $db->query("ALTER TABLE projects ADD COLUMN approved_by INT NULL AFTER priority_level");
    if (!dept_has_col($db, 'projects', 'approved_date')) $db->query("ALTER TABLE projects ADD COLUMN approved_date DATETIME NULL AFTER approved_by");
    if (!dept_has_col($db, 'projects', 'rejection_reason')) $db->query("ALTER TABLE projects ADD COLUMN rejection_reason TEXT NULL AFTER approved_date");
}

function dept_log_decision(mysqli $db, int $projectId, string $type, string $notes, int $by): void
{
    $stmt = $db->prepare("INSERT INTO decision_logs (project_id, decision_type, notes, decided_by, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) return;
    $stmt->bind_param('issi', $projectId, $type, $notes, $by);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = (string)generate_csrf_token();
    $requestToken = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        dept_json(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
    }
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
rbac_require_action_matrix($action !== '' ? $action : 'load_projects', [
    'load_notifications' => 'department_head.notifications.read',
    'load_projects' => 'department_head.approvals.view',
    'decide_project' => 'department_head.approvals.manage',
    'load_monitoring' => 'department_head.monitoring.view',
    'load_priority_projects' => 'department_head.priority.manage',
    'set_project_priority' => 'department_head.priority.manage',
    'load_risk_alerts' => 'department_head.risk.view',
    'load_decision_logs' => 'department_head.decisions.view',
    'load_reports_summary' => 'department_head.reports.view',
    'export_report' => 'department_head.reports.export',
], 'department_head.approvals.view');

dept_ensure_tables($db);

if ($action === 'load_notifications') {
    $items = [];
    $res = $db->query("SELECT p.id, p.code, p.name, p.created_at FROM projects p LEFT JOIN project_department_head_reviews r ON r.project_id=p.id WHERE (r.project_id IS NULL OR r.decision_status='Pending') AND LOWER(COALESCE(p.status,'')) IN ('for approval','pending','draft') ORDER BY p.id DESC LIMIT 30");
    $latest = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nid = 900000000 + (int)$row['id'];
            $items[] = ['id' => $nid, 'level' => 'warning', 'title' => ($row['code'] ?? 'Project') . ' - ' . ($row['name'] ?? ''), 'message' => 'Waiting for Department Head approval', 'created_at' => (string)($row['created_at'] ?? '')];
            if ($nid > $latest) $latest = $nid;
        }
        $res->free();
    }
    dept_json(['success' => true, 'latest_id' => $latest, 'items' => $items]);
}

if ($action === 'load_projects') {
    $mode = strtolower(trim((string)($_GET['mode'] ?? 'pending')));
    $q = strtolower(trim((string)($_GET['q'] ?? '')));
    $sql = $mode === 'reviewed'
        ? "SELECT p.*, COALESCE(r.decision_status,'Pending') AS decision_status, COALESCE(r.decision_note,'') AS decision_note, COALESCE(CONCAT(e.first_name,' ',e.last_name),'') AS decided_by_name, r.decided_at FROM projects p JOIN project_department_head_reviews r ON r.project_id=p.id LEFT JOIN employees e ON e.id=r.decided_by WHERE r.decision_status IN ('Approved','Rejected')"
        : "SELECT p.*, COALESCE(r.decision_status,'Pending') AS decision_status, COALESCE(r.decision_note,'') AS decision_note, COALESCE(CONCAT(e.first_name,' ',e.last_name),'') AS decided_by_name, r.decided_at FROM projects p LEFT JOIN project_department_head_reviews r ON r.project_id=p.id LEFT JOIN employees e ON e.id=r.decided_by WHERE (r.project_id IS NULL OR r.decision_status='Pending') AND LOWER(COALESCE(p.status,'')) IN ('for approval','pending','draft')";
    $params = [];
    $types = '';
    if ($q !== '') {
        $sql .= " AND (LOWER(COALESCE(p.code,'')) LIKE ? OR LOWER(COALESCE(p.name,'')) LIKE ? OR LOWER(COALESCE(p.location,'')) LIKE ?)";
        $like = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY p.id DESC LIMIT 150";
    $stmt = $db->prepare($sql);
    if (!$stmt) dept_json(['success' => false, 'message' => 'Unable to load project queue.'], 500);
    if (!dept_bind($stmt, $types, $params)) { $stmt->close(); dept_json(['success' => false, 'message' => 'Unable to bind queue query.'], 500); }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
    if ($res) $res->free();
    $stmt->close();
    dept_json(['success' => true, 'data' => $rows]);
}

if ($action === 'decide_project') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $decision = trim((string)($_POST['decision_status'] ?? ''));
    $note = trim((string)($_POST['decision_note'] ?? ''));
    $budget = max(0, (float)($_POST['budget_amount'] ?? 0));
    if ($projectId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) dept_json(['success' => false, 'message' => 'Invalid decision payload.'], 422);
    if ($decision === 'Approved' && $budget <= 0) dept_json(['success' => false, 'message' => 'Please provide valid budget before approval.'], 422);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO project_department_head_reviews (project_id, decision_status, decision_note, decided_by, decided_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE decision_status=VALUES(decision_status), decision_note=VALUES(decision_note), decided_by=VALUES(decided_by), decided_at=VALUES(decided_at)");
        if (!$stmt) throw new RuntimeException('Unable to save decision.');
        $stmt->bind_param('issi', $projectId, $decision, $note, $employeeId);
        $stmt->execute();
        $stmt->close();
        $status = $decision === 'Approved' ? 'Approved' : 'Rejected';
        $up = $db->prepare("UPDATE projects SET status=?, budget=?, approved_by=?, approved_date=CASE WHEN ?='Approved' THEN NOW() ELSE NULL END, rejection_reason=CASE WHEN ?='Rejected' THEN ? ELSE NULL END WHERE id=?");
        if ($up) {
            $effBudget = $decision === 'Approved' ? $budget : 0;
            $up->bind_param('sdisssi', $status, $effBudget, $employeeId, $decision, $decision, $note, $projectId);
            $up->execute();
            $up->close();
        }
        dept_log_decision($db, $projectId, strtolower($decision), $note, $employeeId);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        dept_json(['success' => false, 'message' => $e->getMessage()], 500);
    }
    rbac_audit('department_head.project_decision', 'project', $projectId, ['decision' => $decision, 'note' => $note, 'budget' => $budget]);
    dept_json(['success' => true]);
}

if ($action === 'load_monitoring') {
    try {
        $rows = [];
        $progressExpr = dept_has_table($db, 'project_progress_updates') ? "COALESCE(pp.progress_percent,0)" : "0";
        $progressJoin = dept_has_table($db, 'project_progress_updates')
            ? "LEFT JOIN (
                SELECT x.project_id, x.progress_percent
                FROM project_progress_updates x
                JOIN (
                    SELECT project_id, MAX(created_at) mc
                    FROM project_progress_updates
                    GROUP BY project_id
                ) y ON y.project_id=x.project_id AND y.mc=x.created_at
            ) pp ON pp.project_id=p.id"
            : "";

        $engineerDisplayExpr = "NULL";
        $contractorDisplayExpr = "NULL";
        $engineerNameExpr = "COALESCE(pa_engineer.engineer_name, cpa_engineer.engineer_name, '-') AS assigned_engineer";
        $contractorNameExpr = "COALESCE(pa_contractor.contractor_name, '-') AS assigned_contractor";

        if (dept_has_table($db, 'engineers')) {
            $engFirst = dept_pick_existing_column($db, 'engineers', ['first_name']);
            $engLast = dept_pick_existing_column($db, 'engineers', ['last_name']);
            if ($engFirst && $engLast) {
                $engineerDisplayExpr = "TRIM(CONCAT(COALESCE(e.`{$engFirst}`,''), ' ', COALESCE(e.`{$engLast}`,'')))";
            } else {
                $engNameSingle = dept_pick_existing_column($db, 'engineers', ['full_name', 'name', 'company_name']);
                if ($engNameSingle) {
                    $engineerDisplayExpr = "COALESCE(e.`{$engNameSingle}`,'')";
                }
            }
        }

        if (dept_has_table($db, 'contractors')) {
            $contractorNameCol = dept_pick_existing_column($db, 'contractors', ['company_name', 'name', 'contractor_name']);
            if ($contractorNameCol) {
                $contractorDisplayExpr = "COALESCE(c.`{$contractorNameCol}`,'')";
            }
        }

        $joins = [];
        if (dept_has_table($db, 'project_assignments') && dept_has_table($db, 'engineers') && dept_has_col($db, 'project_assignments', 'engineer_id')) {
            $joins[] = "LEFT JOIN (
                SELECT pa.project_id, MAX({$engineerDisplayExpr}) AS engineer_name
                FROM project_assignments pa
                LEFT JOIN engineers e ON e.id = pa.engineer_id
                GROUP BY pa.project_id
            ) pa_engineer ON pa_engineer.project_id = p.id";
        } else {
            $joins[] = "LEFT JOIN (SELECT NULL AS project_id, NULL AS engineer_name) pa_engineer ON pa_engineer.project_id = p.id";
        }

        if (dept_has_table($db, 'project_assignments') && dept_has_table($db, 'contractors') && dept_has_col($db, 'project_assignments', 'contractor_id')) {
            $joins[] = "LEFT JOIN (
                SELECT pa.project_id, MAX({$contractorDisplayExpr}) AS contractor_name
                FROM project_assignments pa
                LEFT JOIN contractors c ON c.id = pa.contractor_id
                GROUP BY pa.project_id
            ) pa_contractor ON pa_contractor.project_id = p.id";
        } else {
            $joins[] = "LEFT JOIN (SELECT NULL AS project_id, NULL AS contractor_name) pa_contractor ON pa_contractor.project_id = p.id";
        }

        if (dept_has_table($db, 'contractor_project_assignments') && dept_has_table($db, 'engineers') && dept_has_col($db, 'contractor_project_assignments', 'contractor_id')) {
            $joins[] = "LEFT JOIN (
                SELECT cpa.project_id, MAX({$engineerDisplayExpr}) AS engineer_name
                FROM contractor_project_assignments cpa
                LEFT JOIN engineers e ON e.id = cpa.contractor_id
                GROUP BY cpa.project_id
            ) cpa_engineer ON cpa_engineer.project_id = p.id";
        } else {
            $joins[] = "LEFT JOIN (SELECT NULL AS project_id, NULL AS engineer_name) cpa_engineer ON cpa_engineer.project_id = p.id";
        }

        $where = ["1=1"];
        $types = '';
        $params = [];
        $search = strtolower(trim((string)($_GET['search'] ?? '')));
        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $district = strtolower(trim((string)($_GET['district'] ?? '')));
        $barangay = strtolower(trim((string)($_GET['barangay'] ?? '')));
        $priority = strtolower(trim((string)($_GET['priority'] ?? '')));
        $engineer = strtolower(trim((string)($_GET['engineer'] ?? '')));
        $contractor = strtolower(trim((string)($_GET['contractor'] ?? '')));

        if ($search !== '') {
            $where[] = "(LOWER(COALESCE(p.code,'')) LIKE ? OR LOWER(COALESCE(p.name,'')) LIKE ? OR LOWER(COALESCE(p.location,'')) LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== '') {
            $where[] = "LOWER(COALESCE(p.status,'')) = ?";
            $types .= 's';
            $params[] = $status;
        }
        if ($district !== '') {
            $where[] = "LOWER(COALESCE(p.location,'')) LIKE ?";
            $types .= 's';
            $params[] = '%' . $district . '%';
        }
        if ($barangay !== '') {
            $where[] = "LOWER(COALESCE(p.location,'')) LIKE ?";
            $types .= 's';
            $params[] = '%' . $barangay . '%';
        }
        if ($priority !== '') {
            $where[] = "LOWER(COALESCE(p.priority_level, COALESCE(p.priority,'medium'))) = ?";
            $types .= 's';
            $params[] = $priority;
        }
        if ($engineer !== '') {
            $where[] = "LOWER(COALESCE(pa_engineer.engineer_name, cpa_engineer.engineer_name, '')) LIKE ?";
            $types .= 's';
            $params[] = '%' . $engineer . '%';
        }
        if ($contractor !== '') {
            $where[] = "LOWER(COALESCE(pa_contractor.contractor_name, '')) LIKE ?";
            $types .= 's';
            $params[] = '%' . $contractor . '%';
        }

        $sql = "SELECT
                    p.id,
                    p.code,
                    p.name,
                    p.status,
                    COALESCE(p.location,'') AS location,
                    COALESCE(p.priority_level,COALESCE(p.priority,'Medium')) AS priority_level,
                    " . (dept_has_col($db, 'projects', 'start_date') ? "p.start_date" : "NULL") . " AS start_date,
                    " . (dept_has_col($db, 'projects', 'end_date') ? "p.end_date" : "NULL") . " AS end_date,
                    {$progressExpr} AS progress_percent,
                    {$engineerNameExpr},
                    {$contractorNameExpr},
                    CASE WHEN " . (dept_has_col($db, 'projects', 'end_date') ? "p.end_date IS NOT NULL AND p.end_date < CURDATE() AND LOWER(COALESCE(p.status,'')) NOT IN ('completed','cancelled')" : "0=1") . " THEN 1 ELSE 0 END AS is_delayed
                FROM projects p
                {$progressJoin}
                " . implode("\n", $joins) . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.id DESC
                LIMIT 300";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            dept_json(['success' => false, 'message' => 'Unable to load monitoring data.'], 500);
        }
        if (!dept_bind($stmt, $types, $params)) {
            $stmt->close();
            dept_json(['success' => false, 'message' => 'Unable to bind monitoring filters.'], 500);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        if ($res) {
            $res->free();
        }
        $stmt->close();
        dept_json(['success' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        error_log('department-head load_monitoring error: ' . $e->getMessage());
        dept_json(['success' => false, 'message' => 'Unable to load monitoring data for this deployment schema.'], 500);
    }
}

if ($action === 'load_priority_projects') {
    $rows = [];
    $res = $db->query("SELECT id, code, name, status, COALESCE(location,'') AS location, COALESCE(priority_level,COALESCE(priority,'Medium')) AS priority_level FROM projects WHERE LOWER(COALESCE(status,''))='approved' ORDER BY id DESC LIMIT 300");
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $res->free();
    }
    dept_json(['success' => true, 'data' => $rows]);
}

if ($action === 'set_project_priority') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $priority = trim((string)($_POST['priority_level'] ?? ''));
    if ((int)($_POST['set_urgent'] ?? 0) === 1) $priority = 'Critical';
    if ($projectId <= 0 || !in_array($priority, ['Low', 'Medium', 'High', 'Critical'], true)) dept_json(['success' => false, 'message' => 'Invalid priority payload.'], 422);
    $stmt = $db->prepare("UPDATE projects SET priority_level=?, priority=? WHERE id=? AND LOWER(COALESCE(status,''))='approved'");
    if (!$stmt) dept_json(['success' => false, 'message' => 'Unable to update priority.'], 500);
    $stmt->bind_param('ssi', $priority, $priority, $projectId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected < 1) dept_json(['success' => false, 'message' => 'Only approved projects can be prioritized.'], 422);
    $by = (int)($_SESSION['employee_id'] ?? 0);
    dept_log_decision($db, $projectId, 'priority_change', 'Priority set to ' . $priority, $by);
    rbac_audit('department_head.priority_change', 'project', $projectId, ['priority' => $priority]);
    dept_json(['success' => true, 'message' => 'Priority updated.']);
}

if ($action === 'load_risk_alerts') {
    $alerts = [];
    $delayed = $db->query("SELECT id, code, name FROM projects WHERE " . (dept_has_col($db, 'projects', 'end_date') ? "end_date IS NOT NULL AND end_date < CURDATE() AND LOWER(COALESCE(status,'')) NOT IN ('completed','cancelled')" : "0=1") . " ORDER BY id DESC LIMIT 100");
    if ($delayed) { while ($r = $delayed->fetch_assoc()) $alerts[] = ['project_id' => (int)$r['id'], 'project_name' => ($r['code'] ?? '') . ' - ' . ($r['name'] ?? ''), 'issue_type' => 'Delayed project', 'severity_level' => 'High', 'recommended_action' => 'Rebaseline schedule and monitor weekly.']; $delayed->free(); }
    if (dept_has_table($db, 'project_validation_items')) {
        $rej = $db->query("SELECT p.id, p.code, p.name, COUNT(*) rc FROM project_validation_items v JOIN projects p ON p.id=v.project_id WHERE v.current_status IN ('Rejected','Needs Revision') GROUP BY p.id,p.code,p.name");
        if ($rej) { while ($r = $rej->fetch_assoc()) $alerts[] = ['project_id' => (int)$r['id'], 'project_name' => ($r['code'] ?? '') . ' - ' . ($r['name'] ?? ''), 'issue_type' => 'Rejected deliverables', 'severity_level' => ((int)$r['rc'] >= 3 ? 'Critical' : 'Medium'), 'recommended_action' => 'Require revision and validate resubmission.']; $rej->free(); }
    }
    if (dept_has_table($db, 'project_progress_updates')) {
        $low = $db->query("SELECT p.id, p.code, p.name, COALESCE(pp.progress_percent,0) pr FROM projects p LEFT JOIN (SELECT x.project_id, x.progress_percent FROM project_progress_updates x JOIN (SELECT project_id, MAX(created_at) mc FROM project_progress_updates GROUP BY project_id) y ON y.project_id=x.project_id AND y.mc=x.created_at) pp ON pp.project_id=p.id WHERE LOWER(COALESCE(p.status,'')) IN ('approved','for approval','ongoing','in progress') AND COALESCE(pp.progress_percent,0) < 20");
        if ($low) { while ($r = $low->fetch_assoc()) $alerts[] = ['project_id' => (int)$r['id'], 'project_name' => ($r['code'] ?? '') . ' - ' . ($r['name'] ?? ''), 'issue_type' => 'Low progress', 'severity_level' => ((float)$r['pr'] < 10 ? 'High' : 'Medium'), 'recommended_action' => 'Investigate blockers and require recovery plan.']; $low->free(); }
    }
    if (dept_has_table($db, 'expenses') && dept_has_table($db, 'milestones')) {
        $ov = $db->query("SELECT p.id, p.code, p.name FROM projects p LEFT JOIN milestones m ON m.name=p.name LEFT JOIN expenses e ON e.milestoneId=m.id GROUP BY p.id,p.code,p.name,p.budget HAVING COALESCE(p.budget,0) > 0 AND COALESCE(SUM(e.amount),0) > COALESCE(p.budget,0)");
        if ($ov) { while ($r = $ov->fetch_assoc()) $alerts[] = ['project_id' => (int)$r['id'], 'project_name' => ($r['code'] ?? '') . ' - ' . ($r['name'] ?? ''), 'issue_type' => 'Overbudget', 'severity_level' => 'Critical', 'recommended_action' => 'Hold non-critical expenses and submit variance note.']; $ov->free(); }
    }
    dept_json(['success' => true, 'data' => $alerts]);
}

if ($action === 'load_decision_logs') {
    $rows = [];
    $res = $db->query("SELECT d.id, d.project_id, d.decision_type, d.notes, d.created_at, p.code, p.name AS project_name, TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) AS approved_by FROM decision_logs d LEFT JOIN projects p ON p.id=d.project_id LEFT JOIN employees e ON e.id=d.decided_by ORDER BY d.created_at DESC, d.id DESC LIMIT 500");
    if ($res) { while ($row = $res->fetch_assoc()) $rows[] = $row; $res->free(); }
    dept_json(['success' => true, 'data' => $rows]);
}

if ($action === 'load_reports_summary') {
    $data = ['total_projects' => 0, 'approved_projects' => 0, 'ongoing_projects' => 0, 'delayed_projects' => 0, 'budget_total' => 0, 'budget_spent' => 0];
    $res = $db->query("SELECT COUNT(*) total_projects, SUM(CASE WHEN LOWER(COALESCE(status,''))='approved' THEN 1 ELSE 0 END) approved_projects, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('ongoing','in progress','approved','for approval') THEN 1 ELSE 0 END) ongoing_projects, SUM(CASE WHEN " . (dept_has_col($db, 'projects', 'end_date') ? "end_date IS NOT NULL AND end_date < CURDATE() AND LOWER(COALESCE(status,'')) NOT IN ('completed','cancelled')" : "0=1") . " THEN 1 ELSE 0 END) delayed_projects, COALESCE(SUM(budget),0) budget_total FROM projects");
    if ($res && ($row = $res->fetch_assoc())) { $data = array_merge($data, $row); $res->free(); }
    if (dept_has_table($db, 'expenses')) {
        $exp = $db->query("SELECT COALESCE(SUM(amount),0) spent FROM expenses");
        if ($exp && ($row = $exp->fetch_assoc())) { $data['budget_spent'] = (float)($row['spent'] ?? 0); $exp->free(); }
    }
    dept_json(['success' => true, 'data' => $data]);
}

if ($action === 'export_report') {
    $reportType = strtolower(trim((string)($_GET['report_type'] ?? 'monthly')));
    $format = strtolower(trim((string)($_GET['format'] ?? 'excel')));
    if (!in_array($reportType, ['monthly', 'budget', 'progress', 'delayed'], true)) $reportType = 'monthly';
    if (!in_array($format, ['excel', 'pdf'], true)) $format = 'excel';
    $sql = "SELECT p.code, p.name, p.status, COALESCE(p.location,'') location, COALESCE(p.priority_level,COALESCE(p.priority,'Medium')) priority_level, COALESCE(p.budget,0) budget, " . (dept_has_col($db, 'projects', 'start_date') ? "p.start_date" : "NULL") . " start_date, " . (dept_has_col($db, 'projects', 'end_date') ? "p.end_date" : "NULL") . " end_date FROM projects p";
    if ($reportType === 'delayed') $sql .= " WHERE " . (dept_has_col($db, 'projects', 'end_date') ? "p.end_date IS NOT NULL AND p.end_date < CURDATE() AND LOWER(COALESCE(p.status,'')) NOT IN ('completed','cancelled')" : "0=1");
    $sql .= " ORDER BY p.id DESC LIMIT 2000";
    $rows = [];
    $res = $db->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) $rows[] = $row; $res->free(); }
    $titleMap = ['monthly' => 'Monthly Report', 'budget' => 'Budget Utilization Report', 'progress' => 'Progress Summary', 'delayed' => 'Delayed Projects Report'];
    $title = $titleMap[$reportType];
    if ($format === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . strtolower(str_replace(' ', '_', $title)) . '.html"');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left}th{background:#f1f5f9}</style></head><body><h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><p>Generated: ' . date('Y-m-d H:i:s') . '</p><table><thead><tr><th>Code</th><th>Project</th><th>Status</th><th>Location</th><th>Priority</th><th>Budget</th><th>Start</th><th>End</th></tr></thead><tbody>';
        foreach ($rows as $r) echo '<tr><td>' . htmlspecialchars((string)$r['code'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$r['location'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$r['priority_level'], ENT_QUOTES, 'UTF-8') . '</td><td>' . number_format((float)$r['budget'], 2) . '</td><td>' . htmlspecialchars((string)$r['start_date'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string)$r['end_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        echo '</tbody></table><script>window.print();</script></body></html>';
        exit;
    }
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Project', 'Status', 'Location', 'Priority', 'Budget', 'Start Date', 'End Date']);
    foreach ($rows as $r) fputcsv($out, [(string)$r['code'], (string)$r['name'], (string)$r['status'], (string)$r['location'], (string)$r['priority_level'], number_format((float)$r['budget'], 2, '.', ''), (string)$r['start_date'], (string)$r['end_date']]);
    fclose($out);
    exit;
}

dept_json(['success' => false, 'message' => 'Unknown action.'], 400);
