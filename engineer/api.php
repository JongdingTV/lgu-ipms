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

function engineer_table_has_column(mysqli $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function engineer_normalize_role(string $role): string {
    $normalized = strtolower(trim($role));
    if ($normalized === '') return '';
    $map = [
        'superadmin' => 'super_admin',
        'super admin' => 'super_admin',
        'department head' => 'department_head',
        'department-head' => 'department_head',
        'dept_head' => 'department_head',
        'dept head' => 'department_head',
        'project_engineer' => 'engineer',
        'municipal_engineer' => 'engineer',
        'city_engineer' => 'engineer',
        'accredited_contractor' => 'contractor',
        'private_contractor' => 'contractor'
    ];
    return $map[$normalized] ?? $normalized;
}

function messaging_ensure_tables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS project_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_message_at DATETIME NULL,
        CONSTRAINT fk_pc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS project_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        project_id INT NOT NULL,
        sender_user_id INT NOT NULL,
        sender_role VARCHAR(30) NOT NULL,
        message_text TEXT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_pm_project_time (project_id, created_at),
        CONSTRAINT fk_pm_conv FOREIGN KEY (conversation_id) REFERENCES project_conversations(id) ON DELETE CASCADE,
        CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS project_message_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(120) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pma_msg FOREIGN KEY (message_id) REFERENCES project_messages(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS message_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_read (message_id, user_id),
        CONSTRAINT fk_mr_msg FOREIGN KEY (message_id) REFERENCES project_messages(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS messaging_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        reference_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function messaging_log(mysqli $db, int $projectId, int $userId, string $action, ?int $refId = null): void {
    $stmt = $db->prepare("INSERT INTO messaging_audit_logs (project_id, user_id, action, reference_id) VALUES (?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param('iisi', $projectId, $userId, $action, $refId);
    $stmt->execute();
    $stmt->close();
}

function engineer_assigned_project_ids(mysqli $db): array {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $employeeEmail = '';
    if ($employeeId > 0 && engineer_table_exists($db, 'employees')) {
        $emp = $db->prepare("SELECT email FROM employees WHERE id = ? LIMIT 1");
        if ($emp) {
            $emp->bind_param('i', $employeeId);
            $emp->execute();
            $row = $emp->get_result()->fetch_assoc();
            $employeeEmail = trim((string)($row['email'] ?? ''));
            $emp->close();
        }
    }
    $engineerIds = [];
    if ($employeeId > 0) $engineerIds[$employeeId] = true;
    if (engineer_table_exists($db, 'engineers')) {
        $byLink = $db->prepare("SELECT id FROM engineers WHERE employee_id = ?");
        if ($byLink && $employeeId > 0) {
            $byLink->bind_param('i', $employeeId);
            $byLink->execute();
            $res = $byLink->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $eid = (int)($r['id'] ?? 0);
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
                    $eid = (int)($r['id'] ?? 0);
                    if ($eid > 0) $engineerIds[$eid] = true;
                }
                $byEmail->close();
            }
        }
    }
    $projectIds = [];
    if (!empty($engineerIds) && engineer_table_exists($db, 'project_assignments')) {
        $idList = implode(',', array_map('intval', array_keys($engineerIds)));
        $res = $db->query("SELECT DISTINCT project_id FROM project_assignments WHERE engineer_id IN ({$idList})");
        if ($res) {
            while ($r = $res->fetch_assoc()) $projectIds[] = (int)($r['project_id'] ?? 0);
            $res->free();
        }
    }
    if (!empty($engineerIds) && engineer_table_exists($db, 'contractor_project_assignments')) {
        $idList = implode(',', array_map('intval', array_keys($engineerIds)));
        $res = $db->query("SELECT DISTINCT project_id FROM contractor_project_assignments WHERE contractor_id IN ({$idList})");
        if ($res) {
            while ($r = $res->fetch_assoc()) $projectIds[] = (int)($r['project_id'] ?? 0);
            $res->free();
        }
    }
    return array_values(array_filter(array_unique($projectIds)));
}

function engineer_chat_identity_ids(mysqli $db): array {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $ids = [];
    if ($employeeId > 0) $ids[$employeeId] = true;
    if (engineer_table_exists($db, 'engineers')) {
        if (engineer_table_has_column($db, 'engineers', 'employee_id') && $employeeId > 0) {
            $stmt = $db->prepare("SELECT id FROM engineers WHERE employee_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && ($r = $res->fetch_assoc())) {
                    $eid = (int)($r['id'] ?? 0);
                    if ($eid > 0) $ids[$eid] = true;
                }
                if ($res) $res->free();
                $stmt->close();
            }
        }
    }
    return array_map('intval', array_keys($ids));
}

function engineer_has_project_access(mysqli $db, int $projectId): bool {
    return $projectId > 0 && in_array($projectId, engineer_assigned_project_ids($db), true);
}

function messaging_ensure_conversation(mysqli $db, int $projectId): int {
    $stmt = $db->prepare("SELECT id FROM project_conversations WHERE project_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
        if ($row) return (int)$row['id'];
    }
    $ins = $db->prepare("INSERT INTO project_conversations (project_id, last_message_at) VALUES (?, NOW())");
    if (!$ins) throw new RuntimeException('Failed to create conversation.');
    $ins->bind_param('i', $projectId);
    $ins->execute();
    $id = (int)$db->insert_id;
    $ins->close();
    return $id;
}

function messaging_store_attachment(array $file): ?array {
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Attachment upload failed.');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) throw new RuntimeException('Attachment must be 10MB or less.');
    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (string)(mime_content_type($tmp) ?: '');
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only PDF/JPG/PNG are allowed.');
    $dir = dirname(__DIR__) . '/uploads/project-messages';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Failed to prepare upload directory.');
    $name = 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Failed to save attachment.');
    return ['path' => 'uploads/project-messages/' . $name, 'name' => (string)($file['name'] ?? $name), 'type' => $mime, 'size' => $size];
}

function direct_messages_ensure_table(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT NOT NULL,
        sender_role VARCHAR(30) NOT NULL,
        receiver_user_id INT NOT NULL,
        receiver_role VARCHAR(30) NOT NULL,
        message_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_dm_pair_time (sender_user_id, receiver_user_id, created_at),
        INDEX idx_dm_receiver (receiver_user_id, receiver_role, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
rbac_require_action_matrix(
    $action !== '' ? $action : 'load_monitoring',
    [
        'load_monitoring' => 'engineer.workspace.view',
        'load_notifications' => 'engineer.notifications.read',
        'load_message_projects' => 'engineer.workspace.view',
        'load_project_messages' => 'engineer.workspace.view',
        'send_project_message' => 'engineer.workspace.manage',
        'delete_project_message' => 'engineer.workspace.manage',
        'mark_project_messages_read' => 'engineer.workspace.view',
        'load_chat_contacts' => 'engineer.workspace.view',
        'load_direct_messages' => 'engineer.workspace.view',
        'send_direct_message' => 'engineer.workspace.manage',
        'delete_direct_conversation' => 'engineer.workspace.manage',
        'load_progress_submissions' => 'engineer.progress.review',
        'decide_progress' => 'engineer.progress.review',
        'load_status_requests' => 'engineer.status.review',
        'engineer_decide_status_request' => 'engineer.status.review',
        'load_task_milestone' => 'engineer.workspace.view',
        'add_task' => 'engineer.tasks.manage',
        'update_task_status' => 'engineer.tasks.manage',
        'add_milestone' => 'engineer.tasks.manage',
        'update_milestone_status' => 'engineer.tasks.manage',
        'load_engineer_dashboard' => 'engineer.workspace.view',
        'load_assigned_projects' => 'engineer.workspace.view',
        'load_validation_queue' => 'engineer.progress.review',
        'decide_validation_item' => 'engineer.progress.review',
        'create_site_report' => 'engineer.workspace.manage',
        'load_site_reports' => 'engineer.workspace.view',
        'load_inspection_requests_center' => 'engineer.status.review',
        'decide_inspection_request' => 'engineer.status.review',
        'create_issue_risk' => 'engineer.workspace.manage',
        'load_issues_risks' => 'engineer.workspace.view',
        'upload_project_document' => 'engineer.workspace.manage',
        'load_project_documents' => 'engineer.workspace.view',
        'load_engineer_notifications_center' => 'engineer.notifications.read',
        'load_project_quick_view' => 'engineer.workspace.view',
    ],
    'engineer.workspace.view'
);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    json_out(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
}

if ($action === 'load_chat_contacts') {
    direct_messages_ensure_table($db);
    $rows = [];
    $seen = [];
    if (engineer_table_exists($db, 'employees')) {
        $sql = "SELECT id, COALESCE(first_name,'') AS first_name, COALESCE(last_name,'') AS last_name, COALESCE(email,'') AS email, COALESCE(role,'') AS role";
        $sql .= " FROM employees ORDER BY id DESC LIMIT 600";
        $res = $db->query($sql);
        while ($res && ($e = $res->fetch_assoc())) {
            if (engineer_normalize_role((string)($e['role'] ?? '')) !== 'contractor') continue;
            $userId = (int)($e['id'] ?? 0);
            if ($userId <= 0 || isset($seen[$userId])) continue;
            $seen[$userId] = true;
            $displayName = trim((string)($e['first_name'] ?? '') . ' ' . (string)($e['last_name'] ?? ''));
            $email = trim((string)($e['email'] ?? ''));

            if ($displayName === '' && engineer_table_exists($db, 'contractors')) {
                $stmtProfile = $db->prepare(
                    "SELECT
                        COALESCE(NULLIF(company,''), NULLIF(company_name,''), NULLIF(owner,''), '') AS profile_name,
                        COALESCE(email,'') AS profile_email
                     FROM contractors
                     WHERE account_employee_id = ? OR employee_id = ?
                     LIMIT 1"
                );
                if ($stmtProfile) {
                    $stmtProfile->bind_param('ii', $userId, $userId);
                    $stmtProfile->execute();
                    $profile = $stmtProfile->get_result()->fetch_assoc();
                    $stmtProfile->close();
                    if ($profile) {
                        $profileName = trim((string)($profile['profile_name'] ?? ''));
                        if ($profileName !== '') $displayName = $profileName;
                        if ($email === '') $email = trim((string)($profile['profile_email'] ?? ''));
                    }
                }
            }

            if ($displayName === '') $displayName = 'Contractor #' . $userId;
            $rows[] = [
                'user_id' => $userId,
                'display_name' => $displayName,
                'email' => $email,
                'role_label' => 'Contractor'
            ];
        }
        if ($res) $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_direct_messages') {
    direct_messages_ensure_table($db);
    $me = (int)($_SESSION['employee_id'] ?? 0);
    $contactId = (int)($_GET['contact_user_id'] ?? 0);
    if ($me <= 0 || $contactId <= 0) json_out(['success' => false, 'message' => 'Invalid chat contact.'], 422);
    $selfIds = engineer_chat_identity_ids($db);
    if (empty($selfIds)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $selfIds));
    $sql = "SELECT id, sender_user_id, sender_role, receiver_user_id, receiver_role, message_text, created_at
            FROM direct_messages
            WHERE is_deleted = 0
              AND (
                (sender_role = 'contractor' AND sender_user_id = {$contactId} AND receiver_role = 'engineer' AND receiver_user_id IN ({$idList}))
                OR
                (sender_role = 'engineer' AND sender_user_id IN ({$idList}) AND receiver_role = 'contractor' AND receiver_user_id = {$contactId})
              )
            ORDER BY created_at ASC
            LIMIT 600";
    $res = $db->query($sql);
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $rows[] = $r;
    }
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'send_direct_message') {
    direct_messages_ensure_table($db);
    $me = (int)($_SESSION['employee_id'] ?? 0);
    $contactId = (int)($_POST['contact_user_id'] ?? 0);
    $text = trim((string)($_POST['message_text'] ?? ''));
    if ($me <= 0 || $contactId <= 0 || $text === '') json_out(['success' => false, 'message' => 'Invalid message payload.'], 422);
    if (strlen($text) > 4000) $text = substr($text, 0, 4000);
    $stmt = $db->prepare("INSERT INTO direct_messages (sender_user_id, sender_role, receiver_user_id, receiver_role, message_text) VALUES (?, 'engineer', ?, 'contractor', ?)");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iis', $me, $contactId, $text);
    $ok = $stmt->execute();
    $msgId = (int)$db->insert_id;
    $stmt->close();
    json_out(['success' => (bool)$ok, 'message_id' => $msgId]);
}

if ($action === 'delete_direct_conversation') {
    direct_messages_ensure_table($db);
    $contactId = (int)($_POST['contact_user_id'] ?? 0);
    if ($contactId <= 0) json_out(['success' => false, 'message' => 'Invalid contact.'], 422);
    $selfIds = engineer_chat_identity_ids($db);
    if (empty($selfIds)) json_out(['success' => true, 'affected' => 0]);
    $idList = implode(',', array_map('intval', $selfIds));
    $sql = "UPDATE direct_messages
            SET is_deleted = 1
            WHERE is_deleted = 0
              AND (
                (sender_role = 'contractor' AND sender_user_id = {$contactId} AND receiver_role = 'engineer' AND receiver_user_id IN ({$idList}))
                OR
                (sender_role = 'engineer' AND sender_user_id IN ({$idList}) AND receiver_role = 'contractor' AND receiver_user_id = {$contactId})
              )";
    $ok = $db->query($sql);
    json_out(['success' => (bool)$ok, 'affected' => (int)$db->affected_rows]);
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
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) {
        json_out(['success' => true, 'data' => []]);
    }
    $idList = implode(',', array_map('intval', $assigned));
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
        WHERE p.id IN ({$idList})
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

if ($action === 'load_message_projects') {
    messaging_ensure_tables($db);
    $projectIds = engineer_assigned_project_ids($db);
    if (empty($projectIds)) json_out(['success' => true, 'data' => []]);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $idList = implode(',', array_map('intval', $projectIds));
    $sql = "SELECT p.id, p.code, p.name,
                   COALESCE(pc.last_message_at, p.created_at, NOW()) AS last_message_at,
                   (SELECT pm.message_text FROM project_messages pm WHERE pm.project_id = p.id AND pm.is_deleted = 0 ORDER BY pm.created_at DESC LIMIT 1) AS last_message_text,
                   (SELECT COUNT(*)
                    FROM project_messages um
                    LEFT JOIN message_reads mr ON mr.message_id = um.id AND mr.user_id = {$employeeId}
                    WHERE um.project_id = p.id AND um.sender_user_id <> {$employeeId} AND um.is_deleted = 0 AND mr.id IS NULL) AS unread_count
            FROM projects p
            LEFT JOIN project_conversations pc ON pc.project_id = p.id
            WHERE p.id IN ({$idList})
            ORDER BY COALESCE(pc.last_message_at, p.created_at, NOW()) DESC";
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_project_messages') {
    messaging_ensure_tables($db);
    $projectId = (int)($_GET['project_id'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Access denied.'], 403);
    $convId = messaging_ensure_conversation($db, $projectId);
    $sql = "SELECT pm.id, pm.sender_user_id, pm.sender_role, pm.message_text, pm.message_type, pm.created_at,
                   CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS sender_name,
                   a.file_path, a.file_name, a.file_type, a.file_size
            FROM project_messages pm
            LEFT JOIN employees e ON e.id = pm.sender_user_id
            LEFT JOIN project_message_attachments a ON a.message_id = pm.id
            WHERE pm.conversation_id = ? AND pm.is_deleted = 0";
    $types = 'i';
    $params = [$convId];
    if ($q !== '') {
        $sql .= " AND pm.message_text LIKE ?";
        $types .= 's';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY pm.created_at ASC";
    $stmt = $db->prepare($sql);
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
    if ($res) $res->free();
    $stmt->close();
    messaging_log($db, $projectId, (int)($_SESSION['employee_id'] ?? 0), 'view_thread', null);
    json_out(['success' => true, 'conversation_id' => $convId, 'data' => $rows]);
}

if ($action === 'send_project_message') {
    messaging_ensure_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $text = trim((string)($_POST['message_text'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Access denied.'], 403);
    $attachment = null;
    try {
        $attachment = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null;
    } catch (Throwable $e) {
        json_out(['success' => false, 'message' => $e->getMessage()], 422);
    }
    if ($text === '' && !$attachment) json_out(['success' => false, 'message' => 'Message is empty.'], 422);
    $text = strip_tags($text);
    if (strlen($text) > 4000) $text = substr($text, 0, 4000);
    $convId = messaging_ensure_conversation($db, $projectId);
    $senderId = (int)($_SESSION['employee_id'] ?? 0);
    $type = $attachment ? 'file' : 'text';
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO project_messages (conversation_id, project_id, sender_user_id, sender_role, message_text, message_type) VALUES (?, ?, ?, 'engineer', ?, ?)");
        if (!$stmt) throw new RuntimeException('Failed to save message.');
        $stmt->bind_param('iiiss', $convId, $projectId, $senderId, $text, $type);
        $stmt->execute();
        $messageId = (int)$db->insert_id;
        $stmt->close();
        if ($attachment) {
            $insA = $db->prepare("INSERT INTO project_message_attachments (message_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            if ($insA) {
                $insA->bind_param('isssi', $messageId, $attachment['path'], $attachment['name'], $attachment['type'], $attachment['size']);
                $insA->execute();
                $insA->close();
            }
            messaging_log($db, $projectId, $senderId, 'upload_file', $messageId);
        }
        $up = $db->prepare("UPDATE project_conversations SET last_message_at = NOW() WHERE id = ?");
        if ($up) {
            $up->bind_param('i', $convId);
            $up->execute();
            $up->close();
        }
        messaging_log($db, $projectId, $senderId, 'send_message', $messageId);
        $db->commit();
        json_out(['success' => true, 'message_id' => $messageId]);
    } catch (Throwable $e) {
        $db->rollback();
        json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'delete_project_message') {
    messaging_ensure_tables($db);
    $messageId = (int)($_POST['message_id'] ?? 0);
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    if ($messageId <= 0) json_out(['success' => false, 'message' => 'Invalid message.'], 422);
    $stmt = $db->prepare("SELECT project_id, sender_user_id FROM project_messages WHERE id = ? LIMIT 1");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    if (!$row) json_out(['success' => false, 'message' => 'Message not found.'], 404);
    $projectId = (int)($row['project_id'] ?? 0);
    if ((int)($row['sender_user_id'] ?? 0) !== $userId || !engineer_has_project_access($db, $projectId)) {
        json_out(['success' => false, 'message' => 'Not allowed.'], 403);
    }
    $up = $db->prepare("UPDATE project_messages SET is_deleted = 1 WHERE id = ?");
    if (!$up) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $up->bind_param('i', $messageId);
    $up->execute();
    $up->close();
    messaging_log($db, $projectId, $userId, 'delete_message', $messageId);
    json_out(['success' => true]);
}

if ($action === 'mark_project_messages_read') {
    messaging_ensure_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Access denied.'], 403);
    $db->query("INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
                SELECT pm.id, {$userId}, NOW()
                FROM project_messages pm
                WHERE pm.project_id = {$projectId} AND pm.sender_user_id <> {$userId} AND pm.is_deleted = 0");
    json_out(['success' => true]);
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
    $inspectedProgressRaw = trim((string)($_POST['inspected_progress'] ?? ''));

    if ($submissionId <= 0 || $projectId <= 0 || !in_array($decision, $allowed, true)) {
        json_out(['success' => false, 'message' => 'Invalid decision payload.'], 422);
    }

    $decidedBy = (int) ($_SESSION['employee_id'] ?? 0);
    if ($decidedBy <= 0) {
        json_out(['success' => false, 'message' => 'Invalid session.'], 403);
    }

    $pick = $db->prepare("SELECT submitted_progress_percent, review_status, discrepancy_flag, discrepancy_note
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

    $submittedProgress = (float)($submission['submitted_progress_percent'] ?? 0);
    $officialProgress = $submittedProgress;
    if ($decision === 'Approved') {
        if ($inspectedProgressRaw === '' || !is_numeric($inspectedProgressRaw)) {
            json_out(['success' => false, 'message' => 'Engineer inspected progress is required for approval.'], 422);
        }
        $officialProgress = (float)$inspectedProgressRaw;
        if ($officialProgress < 0 || $officialProgress > 100) {
            json_out(['success' => false, 'message' => 'Engineer inspected progress must be between 0 and 100.'], 422);
        }
    }

    $mismatchDetected = false;
    $mismatchNote = '';
    if ($decision === 'Approved' && abs($officialProgress - $submittedProgress) >= 0.01) {
        $mismatchDetected = true;
        $mismatchNote = 'Progress mismatch detected (Contractor: ' . number_format($submittedProgress, 2) . '%, Engineer: ' . number_format($officialProgress, 2) . '%).';
    }

    $db->begin_transaction();
    try {
        $noteToSave = $note;
        $discrepancyFlagToSave = (int)($submission['discrepancy_flag'] ?? 0);
        $discrepancyNoteToSave = (string)($submission['discrepancy_note'] ?? '');
        if ($mismatchDetected) {
            $discrepancyFlagToSave = 1;
            $discrepancyNoteToSave = $mismatchNote;
            if ($noteToSave === '') {
                $noteToSave = $mismatchNote;
            } else {
                $noteToSave .= ' | ' . $mismatchNote;
            }
        }

        $up = $db->prepare("UPDATE project_progress_submissions
                            SET review_status = ?, review_note = ?, discrepancy_flag = ?, discrepancy_note = ?, reviewed_by = ?, reviewed_at = NOW()
                            WHERE id = ? AND project_id = ?");
        if (!$up) {
            throw new RuntimeException('Database error.');
        }
        $up->bind_param('ssisiii', $decision, $noteToSave, $discrepancyFlagToSave, $discrepancyNoteToSave, $decidedBy, $submissionId, $projectId);
        $up->execute();
        $up->close();

        if ($decision === 'Approved') {
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
            'inspected_progress' => $officialProgress,
            'mismatch_detected' => $mismatchDetected ? 1 : 0,
        ]);
    }
    json_out([
        'success' => true,
        'message' => $mismatchDetected ? 'Progress mismatch detected. Official progress updated to engineer-inspected value.' : 'Progress review saved successfully.',
        'mismatch_detected' => $mismatchDetected,
        'official_progress' => $officialProgress
    ]);
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

function ensure_engineer_module_tables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS engineer_site_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        engineer_id INT NOT NULL,
        observed_progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        notes TEXT NOT NULL,
        issues_found TEXT NULL,
        recommendation TEXT NULL,
        report_datetime DATETIME NOT NULL,
        attachment_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_esr_project_time (project_id, report_datetime),
        CONSTRAINT fk_esr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS engineer_inspection_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        contractor_id INT NULL,
        request_type VARCHAR(50) NOT NULL,
        proposed_datetime DATETIME NULL,
        details TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        engineer_decision VARCHAR(20) NULL,
        engineer_remarks TEXT NULL,
        scheduled_datetime DATETIME NULL,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eir_project_status (project_id, status),
        CONSTRAINT fk_eir_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS engineer_issues_risks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        engineer_id INT NOT NULL,
        issue_type VARCHAR(60) NOT NULL,
        severity_level VARCHAR(20) NOT NULL DEFAULT 'Low',
        status VARCHAR(20) NOT NULL DEFAULT 'Open',
        description TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eirisk_project_status (project_id, status),
        CONSTRAINT fk_eirisk_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS project_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        uploaded_by INT NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        category VARCHAR(80) NOT NULL DEFAULT 'General',
        tags VARCHAR(255) NULL,
        file_path VARCHAR(255) NOT NULL,
        is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pdoc_project_time (project_id, created_at),
        CONSTRAINT fk_pdoc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    $db->query("CREATE TABLE IF NOT EXISTS engineer_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_user_id INT NOT NULL,
        project_id INT NULL,
        title VARCHAR(180) NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_enotif_user_time (recipient_user_id, created_at)
    )");
}

function engineer_ensure_contractor_submission_tables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS contractor_deliverables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        deliverable_type VARCHAR(120) NOT NULL,
        milestone_reference VARCHAR(120) NULL,
        remarks TEXT NULL,
        file_path VARCHAR(255) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Submitted',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query("CREATE TABLE IF NOT EXISTS contractor_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        category VARCHAR(120) NOT NULL,
        description TEXT NULL,
        receipt_path VARCHAR(255) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function engineer_emit_notification(mysqli $db, int $recipientUserId, int $projectId, string $title, string $body): void {
    if ($recipientUserId <= 0) return;
    $stmt = $db->prepare("INSERT INTO engineer_notifications (recipient_user_id, project_id, title, body) VALUES (?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param('iiss', $recipientUserId, $projectId, $title, $body);
    $stmt->execute();
    $stmt->close();
}

function fetch_project_name(mysqli $db, int $projectId): string {
    $stmt = $db->prepare("SELECT name FROM projects WHERE id = ? LIMIT 1");
    if (!$stmt) return '';
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    return (string)($row['name'] ?? '');
}

if ($action === 'load_engineer_dashboard') {
    ensure_engineer_module_tables($db);
    ensure_progress_review_table($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) {
        json_out(['success' => true, 'data' => [
            'summary' => ['assigned_projects' => 0, 'pending_validations' => 0, 'pending_inspections' => 0, 'open_risks' => 0, 'budget_alerts' => 0],
            'status_distribution' => [],
            'monthly_activity' => [],
        ]]);
    }
    $idList = implode(',', array_map('intval', $assigned));
    $summary = ['assigned_projects' => count($assigned), 'pending_validations' => 0, 'pending_inspections' => 0, 'open_risks' => 0, 'budget_alerts' => 0];
    $q1 = $db->query("SELECT COUNT(*) c FROM project_progress_submissions WHERE project_id IN ({$idList}) AND review_status = 'Pending'");
    if ($q1) { $summary['pending_validations'] = (int)($q1->fetch_assoc()['c'] ?? 0); $q1->free(); }
    $q2 = $db->query("SELECT COUNT(*) c FROM engineer_inspection_requests WHERE project_id IN ({$idList}) AND status = 'Pending'");
    if ($q2) { $summary['pending_inspections'] = (int)($q2->fetch_assoc()['c'] ?? 0); $q2->free(); }
    $q3 = $db->query("SELECT COUNT(*) c FROM engineer_issues_risks WHERE project_id IN ({$idList}) AND status IN ('Open','Mitigating')");
    if ($q3) { $summary['open_risks'] = (int)($q3->fetch_assoc()['c'] ?? 0); $q3->free(); }
    $q4 = $db->query("SELECT COUNT(*) c FROM projects WHERE id IN ({$idList}) AND budget > 0 AND COALESCE(expense,0) >= (budget * 0.9)");
    if ($q4) { $summary['budget_alerts'] = (int)($q4->fetch_assoc()['c'] ?? 0); $q4->free(); }

    $distribution = [];
    $resD = $db->query("SELECT COALESCE(status,'Unknown') AS status, COUNT(*) c FROM projects WHERE id IN ({$idList}) GROUP BY COALESCE(status,'Unknown') ORDER BY c DESC");
    while ($resD && ($r = $resD->fetch_assoc())) $distribution[] = $r;
    if ($resD) $resD->free();

    $monthly = [];
    $resM = $db->query("SELECT DATE_FORMAT(submitted_at, '%Y-%m') ym, COUNT(*) c
                        FROM project_progress_submissions
                        WHERE project_id IN ({$idList}) AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
                        ORDER BY ym ASC");
    while ($resM && ($r = $resM->fetch_assoc())) $monthly[] = $r;
    if ($resM) $resM->free();
    json_out(['success' => true, 'data' => ['summary' => $summary, 'status_distribution' => $distribution, 'monthly_activity' => $monthly]]);
}

if ($action === 'load_assigned_projects') {
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => [], 'meta' => ['total' => 0]]);
    $idList = implode(',', array_map('intval', $assigned));
    $status = trim((string)($_GET['status'] ?? ''));
    $priority = trim((string)($_GET['priority'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    $where = ["p.id IN ({$idList})"];
    if ($status !== '') $where[] = "p.status = '" . $db->real_escape_string($status) . "'";
    if ($priority !== '') $where[] = "p.priority = '" . $db->real_escape_string($priority) . "'";
    if ($search !== '') {
        $like = '%' . $db->real_escape_string($search) . '%';
        $where[] = "(p.code LIKE '{$like}' OR p.name LIKE '{$like}' OR p.location LIKE '{$like}')";
    }
    $whereSql = implode(' AND ', $where);
    $countRes = $db->query("SELECT COUNT(*) c FROM projects p WHERE {$whereSql}");
    $total = (int)(($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
    if ($countRes) $countRes->free();
    $rows = [];
    $sql = "SELECT p.id, p.code, p.name, p.location, p.priority, p.start_date, p.end_date, p.status, p.budget,
                   COALESCE(pp.progress_percent,0) AS progress_percent,
                   COALESCE(c.company_name, CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,''))) AS contractor_name
            FROM projects p
            LEFT JOIN (
                SELECT p1.project_id, p1.progress_percent
                FROM project_progress_updates p1
                INNER JOIN (SELECT project_id, MAX(created_at) mx FROM project_progress_updates GROUP BY project_id) p2
                  ON p1.project_id = p2.project_id AND p1.created_at = p2.mx
            ) pp ON pp.project_id = p.id
            LEFT JOIN contractor_project_assignments cpa ON cpa.project_id = p.id
            LEFT JOIN contractors c ON c.id = cpa.contractor_id
            WHERE {$whereSql}
            ORDER BY p.id DESC
            LIMIT {$limit} OFFSET {$offset}";
    $res = $db->query($sql);
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
}

if ($action === 'load_validation_queue') {
    ensure_engineer_module_tables($db);
    ensure_progress_review_table($db);
    engineer_ensure_contractor_submission_tables($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $assigned));
    $rows = [];
    $res1 = $db->query("SELECT id, project_id, 'progress' AS submission_type, submitted_at AS created_at, review_status AS status,
                               submitted_progress_percent AS amount_or_progress, work_details AS details
                        FROM project_progress_submissions WHERE project_id IN ({$idList})");
    while ($res1 && ($r = $res1->fetch_assoc())) $rows[] = $r;
    if ($res1) $res1->free();
    if (engineer_table_exists($db, 'contractor_deliverables')) {
        $res2 = $db->query("SELECT id, project_id, 'deliverable' AS submission_type, created_at, status, 0 AS amount_or_progress,
                                   CONCAT(deliverable_type, ' - ', COALESCE(milestone_reference,'')) AS details
                            FROM contractor_deliverables WHERE project_id IN ({$idList})");
        while ($res2 && ($r = $res2->fetch_assoc())) $rows[] = $r;
        if ($res2) $res2->free();
    }
    if (engineer_table_exists($db, 'contractor_expenses')) {
        $res3 = $db->query("SELECT id, project_id, 'expense' AS submission_type, created_at, status, amount AS amount_or_progress,
                                   CONCAT(category, ' - ', COALESCE(description,'')) AS details
                            FROM contractor_expenses WHERE project_id IN ({$idList})");
        while ($res3 && ($r = $res3->fetch_assoc())) $rows[] = $r;
        if ($res3) $res3->free();
    }
    usort($rows, static function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
    json_out(['success' => true, 'data' => array_slice($rows, 0, 500)]);
}

if ($action === 'decide_validation_item') {
    ensure_engineer_module_tables($db);
    ensure_progress_review_table($db);
    engineer_ensure_contractor_submission_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $type = trim((string)($_POST['submission_type'] ?? ''));
    $itemId = (int)($_POST['item_id'] ?? 0);
    $decision = trim((string)($_POST['decision'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    if (!in_array($decision, ['Approved', 'Rejected', 'Returned'], true)) json_out(['success' => false, 'message' => 'Invalid decision.'], 422);
    if (($decision === 'Rejected' || $decision === 'Returned') && $remarks === '') json_out(['success' => false, 'message' => 'Remarks are required.'], 422);
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    if ($type === 'progress') {
        $status = $decision === 'Returned' ? 'Needs Revision' : $decision;
        $stmt = $db->prepare("UPDATE project_progress_submissions SET review_status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND project_id = ?");
        if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
        $stmt->bind_param('ssiii', $status, $remarks, $userId, $itemId, $projectId);
        $stmt->execute();
        $stmt->close();
    } elseif ($type === 'deliverable') {
        $status = $decision === 'Returned' ? 'Under Review' : $decision;
        $stmt = $db->prepare("UPDATE contractor_deliverables SET status = ? WHERE id = ? AND project_id = ?");
        if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
        $stmt->bind_param('sii', $status, $itemId, $projectId);
        $stmt->execute();
        $stmt->close();
    } elseif ($type === 'expense') {
        $status = $decision === 'Returned' ? 'Pending' : $decision;
        $stmt = $db->prepare("UPDATE contractor_expenses SET status = ? WHERE id = ? AND project_id = ?");
        if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
        $stmt->bind_param('sii', $status, $itemId, $projectId);
        $stmt->execute();
        $stmt->close();
    } else {
        json_out(['success' => false, 'message' => 'Unsupported submission type.'], 422);
    }
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.validation_decision', 'validation_item', $itemId, ['project_id' => $projectId, 'type' => $type, 'decision' => $decision, 'remarks' => $remarks]);
    }
    json_out(['success' => true]);
}

if ($action === 'create_site_report') {
    ensure_engineer_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $progress = (float)($_POST['observed_progress_percent'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $issuesFound = trim((string)($_POST['issues_found'] ?? ''));
    $recommendation = trim((string)($_POST['recommendation'] ?? ''));
    $reportDt = trim((string)($_POST['report_datetime'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    if ($notes === '' || $reportDt === '') json_out(['success' => false, 'message' => 'Notes and date/time are required.'], 422);
    if ($progress < 0 || $progress > 100) json_out(['success' => false, 'message' => 'Progress must be 0-100.'], 422);
    $att = null;
    try { $att = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $path = $att ? $att['path'] : null;
    $engineerId = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("INSERT INTO engineer_site_reports (project_id, engineer_id, observed_progress_percent, notes, issues_found, recommendation, report_datetime, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iidsssss', $projectId, $engineerId, $progress, $notes, $issuesFound, $recommendation, $reportDt, $path);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('engineer.site_report_create', 'engineer_site_report', $id, ['project_id' => $projectId]);
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'load_site_reports') {
    ensure_engineer_module_tables($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $assigned));
    $rows = [];
    $res = $db->query("SELECT sr.id, sr.project_id, p.code, p.name, sr.observed_progress_percent, sr.notes, sr.issues_found, sr.recommendation, sr.report_datetime, sr.attachment_path, sr.created_at
                       FROM engineer_site_reports sr
                       INNER JOIN projects p ON p.id = sr.project_id
                       WHERE sr.project_id IN ({$idList})
                       ORDER BY sr.report_datetime DESC LIMIT 300");
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_inspection_requests_center') {
    ensure_engineer_module_tables($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $assigned));
    if (engineer_table_exists($db, 'contractor_requests')) {
        $src = $db->query("SELECT cr.id, cr.project_id, cr.contractor_id, cr.request_type, cr.details, cr.created_at
                           FROM contractor_requests cr
                           WHERE cr.project_id IN ({$idList})
                             AND LOWER(cr.request_type) IN ('inspection','milestone review','milestone verification','clarification')
                           ORDER BY cr.id DESC LIMIT 500");
        while ($src && ($r = $src->fetch_assoc())) {
            $rid = (int)($r['id'] ?? 0);
            $pid = (int)($r['project_id'] ?? 0);
            if ($rid <= 0 || $pid <= 0) continue;
            $stmtSync = $db->prepare("INSERT IGNORE INTO engineer_inspection_requests (id, project_id, contractor_id, request_type, proposed_datetime, details, status, created_at) VALUES (?, ?, ?, ?, NULL, ?, 'Pending', ?)");
            if ($stmtSync) {
                $contractorId = (int)($r['contractor_id'] ?? 0);
                $rtype = (string)($r['request_type'] ?? 'Inspection');
                $details = (string)($r['details'] ?? '');
                $createdAt = (string)($r['created_at'] ?? date('Y-m-d H:i:s'));
                $stmtSync->bind_param('iiisss', $rid, $pid, $contractorId, $rtype, $details, $createdAt);
                $stmtSync->execute();
                $stmtSync->close();
            }
        }
        if ($src) $src->free();
    }
    $rows = [];
    $res = $db->query("SELECT ir.id, ir.project_id, p.code, p.name, ir.request_type, ir.proposed_datetime, ir.details, ir.status, ir.engineer_decision, ir.engineer_remarks, ir.scheduled_datetime, ir.created_at
                       FROM engineer_inspection_requests ir
                       INNER JOIN projects p ON p.id = ir.project_id
                       WHERE ir.project_id IN ({$idList})
                       ORDER BY ir.created_at DESC LIMIT 300");
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'decide_inspection_request') {
    ensure_engineer_module_tables($db);
    $id = (int)($_POST['request_id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $decision = trim((string)($_POST['decision'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $scheduled = trim((string)($_POST['scheduled_datetime'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    if (!in_array($decision, ['Approved', 'Rejected', 'Rescheduled', 'Completed'], true)) json_out(['success' => false, 'message' => 'Invalid decision.'], 422);
    if ($remarks === '') json_out(['success' => false, 'message' => 'Remarks required.'], 422);
    $status = $decision === 'Rescheduled' ? 'Approved' : $decision;
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("UPDATE engineer_inspection_requests
                          SET status = ?, engineer_decision = ?, engineer_remarks = ?, scheduled_datetime = NULLIF(?, ''), decided_by = ?, decided_at = NOW()
                          WHERE id = ? AND project_id = ?");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('ssssiii', $status, $decision, $remarks, $scheduled, $userId, $id, $projectId);
    $stmt->execute();
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('engineer.inspection_decision', 'engineer_inspection_request', $id, ['project_id' => $projectId, 'decision' => $decision, 'remarks' => $remarks]);
    json_out(['success' => true]);
}

if ($action === 'create_issue_risk') {
    ensure_engineer_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $type = trim((string)($_POST['issue_type'] ?? ''));
    $severity = trim((string)($_POST['severity_level'] ?? 'Low'));
    $status = trim((string)($_POST['status'] ?? 'Open'));
    $description = trim((string)($_POST['description'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    if ($type === '' || $description === '') json_out(['success' => false, 'message' => 'Type and description are required.'], 422);
    if (!in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true)) $severity = 'Low';
    if (!in_array($status, ['Open', 'Mitigating', 'Resolved'], true)) $status = 'Open';
    $att = null;
    try { $att = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $path = $att ? $att['path'] : null;
    $engineerId = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("INSERT INTO engineer_issues_risks (project_id, engineer_id, issue_type, severity_level, status, description, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iisssss', $projectId, $engineerId, $type, $severity, $status, $description, $path);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('engineer.issue_risk_create', 'engineer_issue_risk', $id, ['project_id' => $projectId, 'severity' => $severity]);
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'load_issues_risks') {
    ensure_engineer_module_tables($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $assigned));
    $rows = [];
    $res = $db->query("SELECT ir.id, ir.project_id, p.code, p.name, ir.issue_type, ir.severity_level, ir.status, ir.description, ir.attachment_path, ir.created_at
                       FROM engineer_issues_risks ir
                       INNER JOIN projects p ON p.id = ir.project_id
                       WHERE ir.project_id IN ({$idList})
                       ORDER BY ir.created_at DESC LIMIT 300");
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'upload_project_document') {
    ensure_engineer_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $name = trim((string)($_POST['document_name'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'General'));
    $tags = trim((string)($_POST['tags'] ?? ''));
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    if ($name === '') json_out(['success' => false, 'message' => 'Document name is required.'], 422);
    $att = null;
    try { $att = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    if (!$att) json_out(['success' => false, 'message' => 'File is required.'], 422);
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    $path = (string)$att['path'];
    $stmt = $db->prepare("INSERT INTO project_documents (project_id, uploaded_by, document_name, category, tags, file_path, is_sensitive) VALUES (?, ?, ?, ?, ?, ?, 0)");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iissss', $projectId, $userId, $name, $category, $tags, $path);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('engineer.document_upload', 'project_document', $id, ['project_id' => $projectId, 'category' => $category]);
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'load_project_documents') {
    ensure_engineer_module_tables($db);
    $assigned = engineer_assigned_project_ids($db);
    if (empty($assigned)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $assigned));
    $rows = [];
    $res = $db->query("SELECT d.id, d.project_id, p.code, p.name, d.document_name, d.category, d.tags, d.file_path, d.created_at
                       FROM project_documents d
                       INNER JOIN projects p ON p.id = d.project_id
                       WHERE d.project_id IN ({$idList}) AND COALESCE(d.is_sensitive,0) = 0
                       ORDER BY d.created_at DESC LIMIT 300");
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
    if ($res) $res->free();
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_engineer_notifications_center') {
    ensure_engineer_module_tables($db);
    $userId = (int)($_SESSION['employee_id'] ?? 0);
    $rows = [];
    $stmt = $db->prepare("SELECT id, project_id, title, body, is_read, created_at FROM engineer_notifications WHERE recipient_user_id = ? ORDER BY id DESC LIMIT 300");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
        if ($res) $res->free();
        $stmt->close();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_project_quick_view') {
    ensure_engineer_module_tables($db);
    ensure_progress_review_table($db);
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!engineer_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    $project = null;
    $stmt = $db->prepare("SELECT id, code, name, location, status, priority, start_date, end_date, budget FROM projects WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $contractor = '';
    if (engineer_table_exists($db, 'contractor_project_assignments') && engineer_table_exists($db, 'contractors')) {
        $stmt2 = $db->prepare("SELECT COALESCE(c.company_name, CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,''))) AS contractor_name, c.email, c.phone
                               FROM contractor_project_assignments cpa
                               INNER JOIN contractors c ON c.id = cpa.contractor_id
                               WHERE cpa.project_id = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('i', $projectId);
            $stmt2->execute();
            $row2 = $stmt2->get_result()->fetch_assoc();
            $contractor = trim((string)($row2['contractor_name'] ?? ''));
            if ($contractor !== '' && (string)($row2['email'] ?? '') !== '') $contractor .= ' | ' . (string)$row2['email'];
            $stmt2->close();
        }
    }
    $progress = 0.0;
    $resP = $db->query("SELECT COALESCE(progress_percent,0) AS p FROM project_progress_updates WHERE project_id = {$projectId} ORDER BY created_at DESC LIMIT 1");
    if ($resP) { $progress = (float)($resP->fetch_assoc()['p'] ?? 0); $resP->free(); }
    $pendingValidations = 0;
    $resV = $db->query("SELECT COUNT(*) c FROM project_progress_submissions WHERE project_id = {$projectId} AND review_status = 'Pending'");
    if ($resV) { $pendingValidations = (int)($resV->fetch_assoc()['c'] ?? 0); $resV->free(); }
    $openIssues = 0;
    $resI = $db->query("SELECT COUNT(*) c FROM engineer_issues_risks WHERE project_id = {$projectId} AND status IN ('Open','Mitigating')");
    if ($resI) { $openIssues = (int)($resI->fetch_assoc()['c'] ?? 0); $resI->free(); }
    json_out(['success' => true, 'data' => ['project' => $project, 'contractor' => $contractor, 'progress' => $progress, 'pending_validations' => $pendingValidations, 'open_issues' => $openIssues]]);
}

json_out(['success' => false, 'message' => 'Unknown action.'], 400);
