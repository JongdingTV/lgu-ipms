<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('contractor.workspace.view', ['contractor','admin','super_admin']);
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

function ensure_progress_submission_table(mysqli $db): void {
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

function ensure_validation_workflow_tables(mysqli $db): void {
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
        CONSTRAINT fk_validation_log_item FOREIGN KEY (item_id) REFERENCES project_validation_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function contractor_store_progress_proof(array $file): string {
    $tmp = (string)($file['tmp_name'] ?? '');
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = (int)($file['size'] ?? 0);
    if ($err !== UPLOAD_ERR_OK || $tmp === '') {
        throw new RuntimeException('Proof photo is required.');
    }
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Proof photo must be 5MB or less.');
    }

    $mime = (string)(mime_content_type($tmp) ?: '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Proof photo must be JPG, PNG, or WEBP.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/progress-proofs';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to prepare proof upload folder.');
    }

    $filename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Unable to save proof photo.');
    }
    return 'uploads/progress-proofs/' . $filename;
}

function contractor_latest_official_progress(mysqli $db, int $projectId): float {
    $stmt = $db->prepare("SELECT COALESCE(progress_percent, 0) AS progress_percent
                          FROM project_progress_updates
                          WHERE project_id = ?
                          ORDER BY created_at DESC
                          LIMIT 1");
    if (!$stmt) return 0.0;
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    return (float)($row['progress_percent'] ?? 0);
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

function contractor_column_exists(mysqli $db, string $table, string $column): bool {
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

function contractor_normalize_role(string $role): string {
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

function contractor_identity(mysqli $db): array {
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $email = '';
    if ($employeeId > 0) {
        $stmt = $db->prepare("SELECT email FROM employees WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            if ($res) $res->free();
            $stmt->close();
            $email = strtolower(trim((string)($row['email'] ?? '')));
        }
    }
    $ids = [];
    if (!contractor_table_exists($db, 'contractors') && $employeeId > 0) {
        // Legacy fallback only when contractors table is unavailable.
        $ids[$employeeId] = true;
    }
    if (contractor_table_exists($db, 'contractors')) {
        $stmtByEmp = $db->prepare("SELECT id FROM contractors WHERE account_employee_id = ?");
        if ($stmtByEmp && $employeeId > 0) {
            $stmtByEmp->bind_param('i', $employeeId);
            $stmtByEmp->execute();
            $resEmp = $stmtByEmp->get_result();
            while ($resEmp && ($r = $resEmp->fetch_assoc())) {
                $cid = (int)($r['id'] ?? 0);
                if ($cid > 0) $ids[$cid] = true;
            }
            if ($resEmp) $resEmp->free();
            $stmtByEmp->close();
        }
    }
    if ($email !== '' && contractor_table_exists($db, 'contractors')) {
        $stmt2 = $db->prepare("SELECT id FROM contractors WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
        if ($stmt2) {
            $stmt2->bind_param('s', $email);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($res2 && ($r = $res2->fetch_assoc())) {
                $cid = (int)($r['id'] ?? 0);
                if ($cid > 0) $ids[$cid] = true;
            }
            $stmt2->close();
        }
    }
    return ['employee_id' => $employeeId, 'contractor_ids' => array_map('intval', array_keys($ids))];
}

function contractor_chat_identity_ids(mysqli $db): array {
    $identity = contractor_identity($db);
    $ids = [];
    $emp = (int)($identity['employee_id'] ?? 0);
    if ($emp > 0) $ids[$emp] = true;
    foreach ((array)($identity['contractor_ids'] ?? []) as $id) {
        $id = (int)$id;
        if ($id > 0) $ids[$id] = true;
    }
    return array_map('intval', array_keys($ids));
}

function contractor_assigned_project_ids(mysqli $db): array {
    $identity = contractor_identity($db);
    $ids = $identity['contractor_ids'];
    if (empty($ids) || !contractor_table_exists($db, 'contractor_project_assignments')) return [];
    $list = implode(',', array_map('intval', $ids));
    $rows = [];
    $res = $db->query("SELECT DISTINCT project_id FROM contractor_project_assignments WHERE contractor_id IN ({$list})");
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = (int)($r['project_id'] ?? 0);
        $res->free();
    }
    return array_values(array_filter(array_unique($rows)));
}

function contractor_has_project_access(mysqli $db, int $projectId): bool {
    return $projectId > 0 && in_array($projectId, contractor_assigned_project_ids($db), true);
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

function contractor_ensure_module_tables(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS contractor_deliverables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        deliverable_type VARCHAR(120) NOT NULL,
        milestone_reference VARCHAR(180) NULL,
        remarks TEXT NULL,
        file_path VARCHAR(255) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Submitted',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query("CREATE TABLE IF NOT EXISTS contractor_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        category VARCHAR(120) NOT NULL,
        description TEXT NULL,
        receipt_path VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query("CREATE TABLE IF NOT EXISTS contractor_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        request_type VARCHAR(120) NOT NULL,
        details TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query("CREATE TABLE IF NOT EXISTS contractor_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_user_id INT NOT NULL,
        project_id INT NULL,
        title VARCHAR(160) NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
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
rbac_require_action_matrix(
    $action !== '' ? $action : 'load_projects',
    [
        'load_projects' => 'contractor.workspace.view',
        'load_notifications' => 'contractor.notifications.read',
        'load_message_projects' => 'contractor.workspace.view',
        'load_project_messages' => 'contractor.workspace.view',
        'send_project_message' => 'contractor.workspace.manage',
        'delete_project_message' => 'contractor.workspace.manage',
        'mark_project_messages_read' => 'contractor.workspace.view',
        'load_chat_contacts' => 'contractor.workspace.view',
        'load_direct_messages' => 'contractor.workspace.view',
        'send_direct_message' => 'contractor.workspace.manage',
        'delete_direct_conversation' => 'contractor.workspace.manage',
        'load_budget_state' => 'contractor.budget.read',
        'submit_status_request' => 'contractor.status.request',
        'load_status_requests' => 'contractor.status.request',
        'update_budget' => 'contractor.budget.manage',
        'update_expense' => 'contractor.workspace.manage',
        'update_progress' => 'contractor.progress.submit',
        'load_progress_history' => 'contractor.workspace.view',
        'load_validation_items' => 'contractor.workspace.view',
        'submit_validation_item' => 'contractor.progress.submit',
        'load_task_milestone' => 'contractor.workspace.manage',
        'add_task' => 'contractor.workspace.manage',
        'update_task_status' => 'contractor.workspace.manage',
        'add_milestone' => 'contractor.workspace.manage',
        'update_milestone_status' => 'contractor.workspace.manage',
        'load_my_projects' => 'contractor.workspace.view',
        'load_project_details' => 'contractor.workspace.view',
        'load_deliverables' => 'contractor.workspace.view',
        'submit_deliverable' => 'contractor.workspace.manage',
        'load_expense_entries' => 'contractor.workspace.view',
        'submit_expense_entry' => 'contractor.workspace.manage',
        'load_requests_center' => 'contractor.workspace.view',
        'submit_request_center' => 'contractor.workspace.manage',
        'load_issues' => 'contractor.workspace.view',
        'submit_issue' => 'contractor.workspace.manage',
        'load_notifications_center' => 'contractor.notifications.read',
    ],
    'contractor.workspace.view'
);

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

if ($action === 'load_chat_contacts') {
    direct_messages_ensure_table($db);
    $rows = [];
    $seen = [];
    if (contractor_table_exists($db, 'employees')) {
        $sql = "SELECT id, COALESCE(first_name,'') AS first_name, COALESCE(last_name,'') AS last_name, COALESCE(email,'') AS email, COALESCE(role,'') AS role";
        $sql .= " FROM employees ORDER BY id DESC LIMIT 600";
        $res = $db->query($sql);
        while ($res && ($e = $res->fetch_assoc())) {
            if (contractor_normalize_role((string)($e['role'] ?? '')) !== 'engineer') continue;
            $userId = (int)($e['id'] ?? 0);
            if ($userId <= 0 || isset($seen[$userId])) continue;
            $seen[$userId] = true;
            $displayName = trim((string)($e['first_name'] ?? '') . ' ' . (string)($e['last_name'] ?? ''));
            $email = trim((string)($e['email'] ?? ''));

            if ($displayName === '' && contractor_table_exists($db, 'engineers')) {
                $stmtProfile = $db->prepare("SELECT COALESCE(NULLIF(full_name,''), '') AS full_name, COALESCE(email,'') AS profile_email FROM engineers WHERE employee_id = ? LIMIT 1");
                if ($stmtProfile) {
                    $stmtProfile->bind_param('i', $userId);
                    $stmtProfile->execute();
                    $profile = $stmtProfile->get_result()->fetch_assoc();
                    $stmtProfile->close();
                    if ($profile) {
                        $profileName = trim((string)($profile['full_name'] ?? ''));
                        if ($profileName !== '') $displayName = $profileName;
                        if ($email === '') $email = trim((string)($profile['profile_email'] ?? ''));
                    }
                }
            }

            if ($displayName === '') $displayName = 'Engineer #' . $userId;
            $rows[] = [
                'user_id' => $userId,
                'display_name' => $displayName,
                'email' => $email,
                'role_label' => 'Engineer'
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
    $selfIds = contractor_chat_identity_ids($db);
    if (empty($selfIds)) json_out(['success' => true, 'data' => []]);
    $idList = implode(',', array_map('intval', $selfIds));
    $sql = "SELECT id, sender_user_id, sender_role, receiver_user_id, receiver_role, message_text, created_at
            FROM direct_messages
            WHERE is_deleted = 0
              AND (
                (sender_role = 'engineer' AND sender_user_id = {$contactId} AND receiver_role = 'contractor' AND receiver_user_id IN ({$idList}))
                OR
                (sender_role = 'contractor' AND sender_user_id IN ({$idList}) AND receiver_role = 'engineer' AND receiver_user_id = {$contactId})
              )
            ORDER BY created_at ASC
            LIMIT 600";
    $res = $db->query($sql);
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
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
    $stmt = $db->prepare("INSERT INTO direct_messages (sender_user_id, sender_role, receiver_user_id, receiver_role, message_text) VALUES (?, 'contractor', ?, 'engineer', ?)");
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
    $selfIds = contractor_chat_identity_ids($db);
    if (empty($selfIds)) json_out(['success' => true, 'affected' => 0]);
    $idList = implode(',', array_map('intval', $selfIds));
    $sql = "UPDATE direct_messages
            SET is_deleted = 1
            WHERE is_deleted = 0
              AND (
                (sender_role = 'engineer' AND sender_user_id = {$contactId} AND receiver_role = 'contractor' AND receiver_user_id IN ({$idList}))
                OR
                (sender_role = 'contractor' AND sender_user_id IN ({$idList}) AND receiver_role = 'engineer' AND receiver_user_id = {$contactId})
              )";
    $ok = $db->query($sql);
    json_out(['success' => (bool)$ok, 'affected' => (int)$db->affected_rows]);
}

if ($action === 'load_projects') {
    ensure_progress_table($db);
    ensure_progress_submission_table($db);
    $allowed = contractor_assigned_project_ids($db);
    if (empty($allowed)) {
        json_out(['success' => true, 'data' => []]);
    }
    $idList = implode(',', array_map('intval', $allowed));
    $rows = [];
    $res = $db->query("SELECT
            p.id,
            p.code,
            p.name,
            p.location,
            COALESCE(p.budget, 0) AS budget,
            p.status,
            COALESCE(pp.progress_percent, 0) AS progress_percent,
            (
                SELECT COUNT(*)
                FROM project_progress_submissions s
                WHERE s.project_id = p.id AND s.review_status = 'Pending'
            ) AS pending_submissions,
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
    $requestId = (int) $db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('contractor.status_request_submit', 'project_status_request', $requestId, [
            'project_id' => $projectId,
            'requested_status' => $status,
            'note' => $note,
        ]);
    }
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
    if (function_exists('rbac_audit')) {
        rbac_audit('project.budget_update', 'project', $projectId, [
            'budget' => $budget,
        ]);
    }
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
    $expenseId = (int) $db->insert_id;
    $stmt->close();

    $db->query("UPDATE milestones m
                LEFT JOIN (
                    SELECT milestoneId, COALESCE(SUM(amount),0) AS total_spent
                    FROM expenses
                    GROUP BY milestoneId
                ) e ON e.milestoneId = m.id
                SET m.spent = COALESCE(e.total_spent,0)");
    if (function_exists('rbac_audit')) {
        rbac_audit('contractor.expense_update', 'expense', $expenseId, [
            'milestone_id' => $milestoneId,
            'amount' => $amount,
            'description' => $description,
        ]);
    }
    json_out(['success' => true]);
}

if ($action === 'update_progress') {
    ensure_progress_table($db);
    ensure_progress_submission_table($db);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $progress = (float) ($_POST['progress'] ?? -1);
    $workDetails = trim((string)($_POST['work_details'] ?? ''));
    $validationNotes = trim((string)($_POST['validation_notes'] ?? ''));
    if ($projectId <= 0 || $progress < 0 || $progress > 100) {
        json_out(['success' => false, 'message' => 'Progress must be between 0 and 100.'], 422);
    }
    if ($workDetails === '' || strlen($workDetails) < 10) {
        json_out(['success' => false, 'message' => 'Please provide work details (at least 10 characters).'], 422);
    }
    if ($validationNotes === '' || strlen($validationNotes) < 10) {
        json_out(['success' => false, 'message' => 'Please provide validation information (at least 10 characters).'], 422);
    }
    if (!isset($_FILES['proof_image'])) {
        json_out(['success' => false, 'message' => 'Please attach a proof photo.'], 422);
    }

    $employeeId = (int) $_SESSION['employee_id'];
    $proofPath = '';
    try {
        $proofPath = contractor_store_progress_proof($_FILES['proof_image']);
    } catch (Throwable $e) {
        json_out(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $official = contractor_latest_official_progress($db, $projectId);
    $delta = $progress - $official;
    $discrepancyFlag = 0;
    $discrepancyNote = null;
    if ($delta < 0) {
        $discrepancyFlag = 1;
        $discrepancyNote = 'Submitted progress is lower than official progress.';
    } elseif ($delta > 20) {
        $discrepancyFlag = 1;
        $discrepancyNote = 'Submitted progress jump is greater than 20%.';
    }

    $stmt = $db->prepare("INSERT INTO project_progress_submissions
        (project_id, submitted_progress_percent, work_details, validation_notes, proof_image_path, discrepancy_flag, discrepancy_note, review_status, submitted_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param('idsssisi', $projectId, $progress, $workDetails, $validationNotes, $proofPath, $discrepancyFlag, $discrepancyNote, $employeeId);
    $stmt->execute();
    $submissionId = (int) $db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) {
        rbac_audit('contractor.progress_submit', 'project_progress_submission', $submissionId, [
            'project_id' => $projectId,
            'progress_percent' => $progress,
            'discrepancy_flag' => $discrepancyFlag,
        ]);
    }
    json_out(['success' => true, 'message' => 'Progress submission sent to engineer for review.']);
}

if ($action === 'load_progress_history') {
    ensure_progress_table($db);
    ensure_progress_submission_table($db);
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid project.'], 422);
    }
    $rows = [];
    $stmt = $db->prepare("SELECT
                            pps.id,
                            pps.submitted_progress_percent AS progress_percent,
                            DATE_FORMAT(pps.submitted_at, '%b %d, %Y %h:%i %p') AS created_at,
                            CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,'')) AS updated_by,
                            pps.review_status,
                            pps.discrepancy_flag,
                            pps.discrepancy_note,
                            pps.work_details,
                            pps.validation_notes,
                            pps.proof_image_path,
                            pps.review_note
                          FROM project_progress_submissions pps
                          LEFT JOIN employees e ON e.id = pps.submitted_by
                          WHERE pps.project_id = ?
                          ORDER BY pps.submitted_at DESC
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

if ($action === 'load_validation_items') {
    ensure_validation_workflow_tables($db);
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid project.'], 422);
    }
    $rows = [];
    $stmt = $db->prepare("SELECT
                            vi.id,
                            vi.project_id,
                            vi.deliverable_type,
                            vi.deliverable_name,
                            vi.current_status,
                            vi.submitted_at,
                            vi.validated_at,
                            vi.validator_remarks,
                            COALESCE(vs.version_no, 0) AS version_no,
                            COALESCE(vs.progress_percent, 0) AS progress_percent
                          FROM project_validation_items vi
                          LEFT JOIN project_validation_submissions vs ON vs.id = vi.last_submission_id
                          WHERE vi.project_id = ?
                          ORDER BY vi.id DESC");
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

if ($action === 'submit_validation_item') {
    ensure_validation_workflow_tables($db);
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $progress = (float) ($_POST['progress_percent'] ?? 0);
    $summary = trim((string)($_POST['change_summary'] ?? ''));
    if ($itemId <= 0 || $progress < 0 || $progress > 100) {
        json_out(['success' => false, 'message' => 'Invalid validation submission payload.'], 422);
    }
    if ($summary === '' || strlen($summary) < 5) {
        json_out(['success' => false, 'message' => 'Please provide a clear change summary.'], 422);
    }

    $attachmentPath = null;
    if (isset($_FILES['proof_image']) && (int)($_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $attachmentPath = contractor_store_progress_proof($_FILES['proof_image']);
        } catch (Throwable $e) {
            json_out(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    $pick = $db->prepare("SELECT project_id, current_status FROM project_validation_items WHERE id = ? LIMIT 1");
    if (!$pick) {
        json_out(['success' => false, 'message' => 'Database error.'], 500);
    }
    $pick->bind_param('i', $itemId);
    $pick->execute();
    $resPick = $pick->get_result();
    $item = $resPick ? $resPick->fetch_assoc() : null;
    if ($resPick) $resPick->free();
    $pick->close();
    if (!$item) {
        json_out(['success' => false, 'message' => 'Validation item not found.'], 404);
    }

    $previousStatus = trim((string)($item['current_status'] ?? 'Pending'));
    $projectId = (int)($item['project_id'] ?? 0);
    $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
    if ($employeeId <= 0 || $projectId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid session.'], 403);
    }

    $nextVersion = 1;
    $vStmt = $db->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 AS next_version FROM project_validation_submissions WHERE item_id = ?");
    if ($vStmt) {
        $vStmt->bind_param('i', $itemId);
        $vStmt->execute();
        $vRes = $vStmt->get_result();
        $vRow = $vRes ? $vRes->fetch_assoc() : null;
        $nextVersion = (int)($vRow['next_version'] ?? 1);
        if ($vRes) $vRes->free();
        $vStmt->close();
    }

    $db->begin_transaction();
    try {
        $ins = $db->prepare("INSERT INTO project_validation_submissions
            (item_id, version_no, progress_percent, change_summary, attachment_path, submitted_by, submitted_role)
            VALUES (?, ?, ?, ?, ?, ?, 'contractor')");
        if (!$ins) {
            throw new RuntimeException('Unable to create validation submission.');
        }
        $ins->bind_param('iidssi', $itemId, $nextVersion, $progress, $summary, $attachmentPath, $employeeId);
        $ins->execute();
        $submissionId = (int) $db->insert_id;
        $ins->close();

        $newStatus = 'Submitted';
        $up = $db->prepare("UPDATE project_validation_items
                            SET current_status = ?, last_submission_id = ?, submitted_by = ?, submitted_at = NOW(),
                                validated_by = NULL, validated_at = NULL, validator_remarks = NULL
                            WHERE id = ?");
        if (!$up) {
            throw new RuntimeException('Unable to update validation item.');
        }
        $up->bind_param('siii', $newStatus, $submissionId, $employeeId, $itemId);
        $up->execute();
        $up->close();

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $log = $db->prepare("INSERT INTO project_validation_logs
            (item_id, submission_id, action_type, previous_status, new_status, remarks, acted_by, acted_role, ip_address)
            VALUES (?, ?, 'submit', ?, ?, ?, ?, 'contractor', ?)");
        if ($log) {
            $log->bind_param('iisssis', $itemId, $submissionId, $previousStatus, $newStatus, $summary, $employeeId, $ip);
            $log->execute();
            $log->close();
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        json_out(['success' => false, 'message' => $e->getMessage()], 500);
    }

    if (function_exists('rbac_audit')) {
        rbac_audit('contractor.validation_submit', 'project_validation_item', $itemId, [
            'project_id' => $projectId,
            'previous_status' => $previousStatus,
            'new_status' => 'Submitted',
            'version_no' => $nextVersion,
            'progress_percent' => $progress,
        ]);
    }

    json_out(['success' => true, 'message' => 'Deliverable submitted for validation.']);
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

if ($action === 'load_message_projects') {
    messaging_ensure_tables($db);
    $projectIds = contractor_assigned_project_ids($db);
    if (empty($projectIds)) {
        json_out(['success' => true, 'data' => []]);
    }
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
    if (!contractor_has_project_access($db, $projectId)) {
        json_out(['success' => false, 'message' => 'Access denied.'], 403);
    }
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
    if (!contractor_has_project_access($db, $projectId)) {
        json_out(['success' => false, 'message' => 'Access denied.'], 403);
    }
    $attachment = null;
    try {
        $attachment = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null;
    } catch (Throwable $e) {
        json_out(['success' => false, 'message' => $e->getMessage()], 422);
    }
    if ($text === '' && !$attachment) {
        json_out(['success' => false, 'message' => 'Message is empty.'], 422);
    }
    $text = strip_tags($text);
    if (strlen($text) > 4000) $text = substr($text, 0, 4000);
    $convId = messaging_ensure_conversation($db, $projectId);
    $senderId = (int)($_SESSION['employee_id'] ?? 0);
    $type = $attachment ? 'file' : 'text';
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO project_messages (conversation_id, project_id, sender_user_id, sender_role, message_text, message_type) VALUES (?, ?, ?, 'contractor', ?, ?)");
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
    if ((int)($row['sender_user_id'] ?? 0) !== $userId || !contractor_has_project_access($db, $projectId)) {
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
    if (!contractor_has_project_access($db, $projectId)) {
        json_out(['success' => false, 'message' => 'Access denied.'], 403);
    }
    $db->query("INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
                SELECT pm.id, {$userId}, NOW()
                FROM project_messages pm
                WHERE pm.project_id = {$projectId} AND pm.sender_user_id <> {$userId} AND pm.is_deleted = 0");
    json_out(['success' => true]);
}

if ($action === 'load_my_projects') {
    $rows = [];
    $res = $db->query("SELECT p.id, p.code, p.name, p.location, p.status, COALESCE(p.priority,'') AS priority, COALESCE(p.budget,0) AS budget,
                              COALESCE(pp.progress_percent,0) AS progress_percent
                       FROM projects p
                       LEFT JOIN (
                         SELECT p1.project_id, p1.progress_percent
                         FROM project_progress_updates p1
                         INNER JOIN (SELECT project_id, MAX(created_at) mx FROM project_progress_updates GROUP BY project_id) p2
                           ON p1.project_id = p2.project_id AND p1.created_at = p2.mx
                       ) pp ON pp.project_id = p.id
                       ORDER BY p.id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_project_details') {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!contractor_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    ensure_task_milestone_tables($db);
    ensure_progress_submission_table($db);
    $project = null;
    $s = $db->prepare("SELECT id, code, name, location, status, priority, budget, start_date, end_date, description FROM projects WHERE id = ? LIMIT 1");
    if ($s) {
        $s->bind_param('i', $projectId);
        $s->execute();
        $project = $s->get_result()->fetch_assoc();
        $s->close();
    }
    $tasks = [];
    $t = $db->prepare("SELECT id, title, status, planned_start, planned_end, actual_start, actual_end, notes FROM project_tasks WHERE project_id = ? ORDER BY id DESC");
    if ($t) {
        $t->bind_param('i', $projectId);
        $t->execute();
        $res = $t->get_result();
        while ($res && ($r = $res->fetch_assoc())) $tasks[] = $r;
        if ($res) $res->free();
        $t->close();
    }
    $milestones = [];
    $m = $db->prepare("SELECT id, title, status, planned_date, actual_date, notes FROM project_milestones WHERE project_id = ? ORDER BY id DESC");
    if ($m) {
        $m->bind_param('i', $projectId);
        $m->execute();
        $res = $m->get_result();
        while ($res && ($r = $res->fetch_assoc())) $milestones[] = $r;
        if ($res) $res->free();
        $m->close();
    }
    $progress = [];
    $p = $db->prepare("SELECT submitted_progress_percent AS progress_percent, review_status, submitted_at FROM project_progress_submissions WHERE project_id = ? ORDER BY id DESC LIMIT 40");
    if ($p) {
        $p->bind_param('i', $projectId);
        $p->execute();
        $res = $p->get_result();
        while ($res && ($r = $res->fetch_assoc())) $progress[] = $r;
        if ($res) $res->free();
        $p->close();
    }
    json_out(['success' => true, 'data' => ['project' => $project, 'tasks' => $tasks, 'milestones' => $milestones, 'progress_history' => $progress]]);
}

if ($action === 'load_deliverables') {
    contractor_ensure_module_tables($db);
    $allowedProjects = contractor_assigned_project_ids($db);
    $rows = [];
    $res = $db->query("SELECT id, contractor_id, project_id, deliverable_type, milestone_reference, remarks, file_path, status, created_at FROM contractor_deliverables ORDER BY id DESC LIMIT 250");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (in_array((int)($r['project_id'] ?? 0), $allowedProjects, true)) $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'submit_deliverable') {
    contractor_ensure_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!contractor_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    $type = trim((string)($_POST['deliverable_type'] ?? ''));
    $milestoneRef = trim((string)($_POST['milestone_reference'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    if ($type === '') json_out(['success' => false, 'message' => 'Deliverable type is required.'], 422);
    $att = null;
    try { $att = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $path = $att ? $att['path'] : null;
    $identity = contractor_identity($db);
    $contractorId = (int)($identity['contractor_ids'][0] ?? ($identity['employee_id'] ?? 0));
    $stmt = $db->prepare("INSERT INTO contractor_deliverables (contractor_id, project_id, deliverable_type, milestone_reference, remarks, file_path, status) VALUES (?, ?, ?, ?, ?, ?, 'Submitted')");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iissss', $contractorId, $projectId, $type, $milestoneRef, $remarks, $path);
    $stmt->execute();
    $deliverableId = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('contractor.deliverable_submit', 'contractor_deliverable', $deliverableId, ['project_id' => $projectId]);
    json_out(['success' => true, 'id' => $deliverableId]);
}

if ($action === 'load_expense_entries') {
    contractor_ensure_module_tables($db);
    $allowedProjects = contractor_assigned_project_ids($db);
    $rows = [];
    $res = $db->query("SELECT id, contractor_id, project_id, amount, category, description, receipt_path, status, created_at FROM contractor_expenses ORDER BY id DESC LIMIT 250");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (in_array((int)($r['project_id'] ?? 0), $allowedProjects, true)) $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'submit_expense_entry') {
    contractor_ensure_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!contractor_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    $amount = (float)($_POST['amount'] ?? 0);
    $category = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($amount <= 0 || $category === '') json_out(['success' => false, 'message' => 'Amount and category are required.'], 422);
    $receipt = null;
    try { $receipt = isset($_FILES['receipt']) ? messaging_store_attachment($_FILES['receipt']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $path = $receipt ? $receipt['path'] : null;
    $identity = contractor_identity($db);
    $contractorId = (int)($identity['contractor_ids'][0] ?? ($identity['employee_id'] ?? 0));
    $stmt = $db->prepare("INSERT INTO contractor_expenses (contractor_id, project_id, amount, category, description, receipt_path, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iidsss', $contractorId, $projectId, $amount, $category, $description, $path);
    $stmt->execute();
    $expenseId = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('contractor.expense_submit', 'contractor_expense', $expenseId, ['project_id' => $projectId, 'amount' => $amount]);
    json_out(['success' => true, 'id' => $expenseId]);
}

if ($action === 'load_requests_center') {
    contractor_ensure_module_tables($db);
    $allowedProjects = contractor_assigned_project_ids($db);
    $rows = [];
    $res = $db->query("SELECT id, contractor_id, project_id, request_type, details, attachment_path, status, created_at FROM contractor_requests ORDER BY id DESC LIMIT 250");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (in_array((int)($r['project_id'] ?? 0), $allowedProjects, true)) $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'submit_request_center') {
    contractor_ensure_module_tables($db);
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!contractor_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    $requestType = trim((string)($_POST['request_type'] ?? ''));
    $details = trim((string)($_POST['details'] ?? ''));
    if ($requestType === '' || $details === '') json_out(['success' => false, 'message' => 'Request type and details are required.'], 422);
    $attachment = null;
    try { $attachment = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $path = $attachment ? $attachment['path'] : null;
    $identity = contractor_identity($db);
    $contractorId = (int)($identity['contractor_ids'][0] ?? ($identity['employee_id'] ?? 0));
    $stmt = $db->prepare("INSERT INTO contractor_requests (contractor_id, project_id, request_type, details, attachment_path, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iisss', $contractorId, $projectId, $requestType, $details, $path);
    $stmt->execute();
    $requestId = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('contractor.request_submit', 'contractor_request', $requestId, ['project_id' => $projectId, 'request_type' => $requestType]);
    json_out(['success' => true, 'id' => $requestId]);
}

if ($action === 'submit_issue' || $action === 'load_issues') {
    $db->query("CREATE TABLE IF NOT EXISTS contractor_issues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contractor_id INT NOT NULL,
        project_id INT NOT NULL,
        issue_type VARCHAR(120) NOT NULL,
        severity_level VARCHAR(20) NOT NULL DEFAULT 'Low',
        description TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

if ($action === 'submit_issue') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!contractor_has_project_access($db, $projectId)) json_out(['success' => false, 'message' => 'Project access denied.'], 403);
    $issueType = trim((string)($_POST['issue_type'] ?? ''));
    $severity = trim((string)($_POST['severity_level'] ?? 'Low'));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($issueType === '' || $description === '') json_out(['success' => false, 'message' => 'Issue type and description are required.'], 422);
    if (!in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true)) $severity = 'Low';
    $att = null;
    try { $att = isset($_FILES['attachment']) ? messaging_store_attachment($_FILES['attachment']) : null; } catch (Throwable $e) { json_out(['success' => false, 'message' => $e->getMessage()], 422); }
    $identity = contractor_identity($db);
    $contractorId = (int)($identity['contractor_ids'][0] ?? ($identity['employee_id'] ?? 0));
    $path = $att ? $att['path'] : null;
    $stmt = $db->prepare("INSERT INTO contractor_issues (contractor_id, project_id, issue_type, severity_level, description, attachment_path, status) VALUES (?, ?, ?, ?, ?, ?, 'Open')");
    if (!$stmt) json_out(['success' => false, 'message' => 'Database error.'], 500);
    $stmt->bind_param('iissss', $contractorId, $projectId, $issueType, $severity, $description, $path);
    $stmt->execute();
    $issueId = (int)$db->insert_id;
    $stmt->close();
    if (function_exists('rbac_audit')) rbac_audit('contractor.issue_submit', 'contractor_issue', $issueId, ['project_id' => $projectId, 'severity' => $severity]);
    json_out(['success' => true, 'id' => $issueId]);
}

if ($action === 'load_issues') {
    $allowed = contractor_assigned_project_ids($db);
    $rows = [];
    $res = $db->query("SELECT * FROM contractor_issues ORDER BY id DESC LIMIT 200");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (in_array((int)($r['project_id'] ?? 0), $allowed, true)) $rows[] = $r;
        }
        $res->free();
    }
    json_out(['success' => true, 'data' => $rows]);
}

if ($action === 'load_notifications_center') {
    contractor_ensure_module_tables($db);
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $rows = [];
    if ($employeeId > 0 && contractor_table_exists($db, 'contractor_notifications')) {
        $stmt = $db->prepare("SELECT id, project_id, title, body, is_read, created_at FROM contractor_notifications WHERE recipient_user_id = ? ORDER BY id DESC LIMIT 200");
        if ($stmt) {
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
            if ($res) $res->free();
            $stmt->close();
        }
    }
    json_out(['success' => true, 'data' => $rows]);
}

json_out(['success' => false, 'message' => 'Unknown action.'], 400);
