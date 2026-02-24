<?php
if (function_exists('ob_start')) {
    ob_start();
}
@ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/includes/rbac.php';

set_no_cache_headers();
check_auth();
rbac_require_from_matrix('admin.applications.view', ['admin', 'department_admin', 'super_admin']);
check_suspicious_activity();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e): void {
    error_log('applications_api uncaught: ' . $e->getMessage());
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    exit;
});

function app_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function app_table_exists(mysqli $db, string $table): bool
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

function app_col_exists(mysqli $db, string $table, string $col): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function app_safe_query(mysqli $db, string $sql): bool
{
    try {
        return (bool)$db->query($sql);
    } catch (Throwable $e) {
        error_log('applications_api schema query failed: ' . $e->getMessage());
        return false;
    }
}

function app_ensure_column(mysqli $db, string $table, string $column, string $definition): void
{
    if (!app_col_exists($db, $table, $column)) {
        app_safe_query($db, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function app_ensure_schema(mysqli $db): void
{
    app_safe_query($db, "CREATE TABLE IF NOT EXISTS engineer_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        department VARCHAR(120) NULL,
        position VARCHAR(120) NULL,
        specialization VARCHAR(120) NOT NULL,
        assigned_area VARCHAR(190) NULL,
        prc_license_no VARCHAR(120) NULL,
        prc_expiry DATE NULL,
        years_experience INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        account_password_hash VARCHAR(255) NULL,
        admin_remarks TEXT NULL,
        rejection_reason TEXT NULL,
        verified_by INT NULL,
        verified_at DATETIME NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    app_safe_query($db, "CREATE TABLE IF NOT EXISTS contractor_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        company_name VARCHAR(190) NOT NULL,
        contact_person VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(60) NOT NULL,
        address VARCHAR(255) NULL,
        assigned_area VARCHAR(190) NULL,
        specialization VARCHAR(120) NOT NULL,
        years_in_business INT NOT NULL DEFAULT 0,
        license_no VARCHAR(120) NULL,
        license_expiry DATE NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        account_password_hash VARCHAR(255) NULL,
        admin_remarks TEXT NULL,
        rejection_reason TEXT NULL,
        blacklist_reason TEXT NULL,
        verified_by INT NULL,
        verified_at DATETIME NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    app_safe_query($db, "CREATE TABLE IF NOT EXISTS application_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_type VARCHAR(20) NOT NULL,
        application_id INT NOT NULL,
        doc_type VARCHAR(60) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NULL,
        mime_type VARCHAR(120) NULL,
        file_size INT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    app_safe_query($db, "CREATE TABLE IF NOT EXISTS application_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_type VARCHAR(20) NOT NULL,
        application_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        performed_by_user_id INT NULL,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Backfill missing columns in existing deployments
    app_ensure_column($db, 'contractor_applications', 'assigned_area', "VARCHAR(190) NULL AFTER address");
    app_ensure_column($db, 'contractor_applications', 'account_password_hash', "VARCHAR(255) NULL AFTER status");
    app_ensure_column($db, 'contractor_applications', 'verified_by', "INT NULL AFTER rejection_reason");
    app_ensure_column($db, 'contractor_applications', 'verified_at', "DATETIME NULL AFTER verified_by");
    app_ensure_column($db, 'contractor_applications', 'approved_by', "INT NULL AFTER verified_at");
    app_ensure_column($db, 'contractor_applications', 'approved_at', "DATETIME NULL AFTER approved_by");

    app_ensure_column($db, 'engineer_applications', 'account_password_hash', "VARCHAR(255) NULL AFTER status");
    app_ensure_column($db, 'engineer_applications', 'verified_by', "INT NULL AFTER rejection_reason");
    app_ensure_column($db, 'engineer_applications', 'verified_at', "DATETIME NULL AFTER verified_by");
    app_ensure_column($db, 'engineer_applications', 'approved_by', "INT NULL AFTER verified_at");
    app_ensure_column($db, 'engineer_applications', 'approved_at', "DATETIME NULL AFTER approved_by");
}

function app_pick_col(mysqli $db, string $table, array $candidates): ?string
{
    foreach ($candidates as $c) {
        if (app_col_exists($db, $table, $c)) return $c;
    }
    return null;
}

function app_bind(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || !$params) return true;
    $args = [$types];
    foreach ($params as $k => $v) $args[] = &$params[$k];
    return call_user_func_array([$stmt, 'bind_param'], $args);
}

function app_require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!verify_csrf_token($token)) {
        app_json(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
    }
}

function app_log(mysqli $db, string $type, int $id, string $action, string $remarks = ''): void
{
    $uid = (int)($_SESSION['employee_id'] ?? 0);
    $stmt = $db->prepare("INSERT INTO application_logs (application_type, application_id, action, performed_by_user_id, remarks, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) return;
    $stmt->bind_param('siiss', $type, $id, $action, $uid, $remarks);
    $stmt->execute();
    $stmt->close();
}

function app_split_name(string $fullName): array
{
    $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
    if ($fullName === '') return ['', ''];
    $parts = explode(' ', $fullName);
    if (count($parts) === 1) return [$parts[0], $parts[0]];
    $last = array_pop($parts);
    return [implode(' ', $parts), $last];
}

function app_create_or_activate_employee(mysqli $db, string $role, string $email, string $nameOrCompany, ?string $passwordHash, ?int $currentUserId = null): int
{
    $role = strtolower(trim($role));
    $statusCol = app_col_exists($db, 'employees', 'account_status');

    $sel = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
    if (!$sel) throw new RuntimeException('Unable to check employee account.');
    $sel->bind_param('s', $email);
    $sel->execute();
    $res = $sel->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $sel->close();

    if ($row) {
        $employeeId = (int)$row['id'];
        if ($statusCol) {
            $up = $db->prepare("UPDATE employees SET role = ?, account_status = 'active' WHERE id = ?");
            if ($up) {
                $up->bind_param('si', $role, $employeeId);
                $up->execute();
                $up->close();
            }
        } else {
            $up = $db->prepare("UPDATE employees SET role = ? WHERE id = ?");
            if ($up) {
                $up->bind_param('si', $role, $employeeId);
                $up->execute();
                $up->close();
            }
        }
        return $employeeId;
    }

    if (!$passwordHash || $passwordHash === '') {
        throw new RuntimeException('Missing applicant password hash. Re-apply is required for approval.');
    }

    [$firstName, $lastName] = app_split_name($nameOrCompany);
    if ($role === 'contractor' && trim($firstName) === trim($lastName)) {
        $firstName = $nameOrCompany;
        $lastName = 'Contractor';
    }

    if ($statusCol) {
        $ins = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, 'active')");
        if (!$ins) throw new RuntimeException('Unable to create employee account.');
        $ins->bind_param('sssss', $firstName, $lastName, $email, $passwordHash, $role);
    } else {
        $ins = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        if (!$ins) throw new RuntimeException('Unable to create employee account.');
        $ins->bind_param('sssss', $firstName, $lastName, $email, $passwordHash, $role);
    }

    if (!$ins->execute()) {
        $msg = $ins->error;
        $ins->close();
        throw new RuntimeException('Unable to create employee account: ' . $msg);
    }
    $employeeId = (int)$db->insert_id;
    $ins->close();
    return $employeeId;
}

function app_sync_engineer_profile(mysqli $db, array $app, int $employeeId): void
{
    if (!app_table_exists($db, 'engineers')) return;

    $email = (string)($app['email'] ?? '');
    $existing = null;
    $sel = $db->prepare("SELECT id FROM engineers WHERE email = ? LIMIT 1");
    if ($sel) {
        $sel->bind_param('s', $email);
        $sel->execute();
        $r = $sel->get_result();
        $existing = $r ? $r->fetch_assoc() : null;
        if ($r) $r->free();
        $sel->close();
    }

    $fullName = (string)($app['full_name'] ?? '');
    [$firstName, $lastName] = app_split_name($fullName);

    if ($existing) {
        $id = (int)$existing['id'];
        $upFields = [];
        $types = '';
        $vals = [];

        foreach ([
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => (string)($app['email'] ?? ''),
            'contact_number' => (string)($app['phone'] ?? ''),
            'specialization' => (string)($app['specialization'] ?? ''),
            'position_title' => (string)($app['position'] ?? ''),
            'address' => (string)($app['assigned_area'] ?? ''),
            'prc_license_number' => (string)($app['prc_license_no'] ?? ''),
            'license_expiry_date' => (string)($app['prc_expiry'] ?? ''),
            'years_experience' => (int)($app['years_experience'] ?? 0),
            'employee_id' => $employeeId,
            'account_status' => 'active',
        ] as $col => $val) {
            if (!app_col_exists($db, 'engineers', $col)) continue;
            $upFields[] = "{$col} = ?";
            if (is_int($val)) {
                $types .= 'i';
                $vals[] = $val;
            } else {
                $types .= 's';
                $vals[] = (string)$val;
            }
        }

        if ($upFields) {
            $types .= 'i';
            $vals[] = $id;
            $sql = "UPDATE engineers SET " . implode(', ', $upFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                app_bind($stmt, $types, $vals);
                $stmt->execute();
                $stmt->close();
            }
        }
        return;
    }

    $columns = [];
    $placeholders = [];
    $types = '';
    $vals = [];

    $insertMap = [
        'full_name' => $fullName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => (string)($app['email'] ?? ''),
        'contact_number' => (string)($app['phone'] ?? ''),
        'specialization' => (string)($app['specialization'] ?? ''),
        'position_title' => (string)($app['position'] ?? ''),
        'address' => (string)($app['assigned_area'] ?? ''),
        'prc_license_number' => (string)($app['prc_license_no'] ?? ''),
        'license_expiry_date' => (string)($app['prc_expiry'] ?? ''),
        'years_experience' => (int)($app['years_experience'] ?? 0),
        'employee_id' => $employeeId,
        'account_status' => 'active',
    ];

    foreach ($insertMap as $col => $val) {
        if (!app_col_exists($db, 'engineers', $col)) continue;
        $columns[] = $col;
        $placeholders[] = '?';
        if (is_int($val)) {
            $types .= 'i';
            $vals[] = $val;
        } else {
            $types .= 's';
            $vals[] = (string)$val;
        }
    }

    if ($columns) {
        $sql = "INSERT INTO engineers (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            app_bind($stmt, $types, $vals);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function app_sync_contractor_profile(mysqli $db, array $app, int $employeeId): void
{
    if (!app_table_exists($db, 'contractors')) return;

    $email = (string)($app['email'] ?? '');
    $existing = null;
    $sel = $db->prepare("SELECT id FROM contractors WHERE email = ? LIMIT 1");
    if ($sel) {
        $sel->bind_param('s', $email);
        $sel->execute();
        $r = $sel->get_result();
        $existing = $r ? $r->fetch_assoc() : null;
        if ($r) $r->free();
        $sel->close();
    }

    $company = (string)($app['company_name'] ?? '');
    $contact = (string)($app['contact_person'] ?? '');

    $map = [
        'company' => $company,
        'company_name' => $company,
        'owner' => $contact,
        'email' => (string)($app['email'] ?? ''),
        'phone' => (string)($app['phone'] ?? ''),
        'address' => (string)($app['address'] ?? ''),
        'specialization' => (string)($app['specialization'] ?? ''),
        'experience' => (int)($app['years_in_business'] ?? 0),
        'status' => 'Active',
        'license' => (string)($app['license_no'] ?? ''),
        'license_number' => (string)($app['license_no'] ?? ''),
        'license_expiration_date' => (string)($app['license_expiry'] ?? ''),
        'account_employee_id' => $employeeId,
    ];

    if ($existing) {
        $id = (int)$existing['id'];
        $set = [];
        $types = '';
        $vals = [];
        foreach ($map as $col => $val) {
            if (!app_col_exists($db, 'contractors', $col)) continue;
            $set[] = "{$col} = ?";
            if (is_int($val)) { $types .= 'i'; $vals[] = $val; }
            else { $types .= 's'; $vals[] = (string)$val; }
        }
        if ($set) {
            $types .= 'i';
            $vals[] = $id;
            $stmt = $db->prepare("UPDATE contractors SET " . implode(', ', $set) . " WHERE id = ?");
            if ($stmt) {
                app_bind($stmt, $types, $vals);
                $stmt->execute();
                $stmt->close();
            }
        }
        return;
    }

    $columns = [];
    $placeholders = [];
    $types = '';
    $vals = [];
    foreach ($map as $col => $val) {
        if (!app_col_exists($db, 'contractors', $col)) continue;
        $columns[] = $col;
        $placeholders[] = '?';
        if (is_int($val)) { $types .= 'i'; $vals[] = $val; }
        else { $types .= 's'; $vals[] = (string)$val; }
    }
    if ($columns) {
        $stmt = $db->prepare("INSERT INTO contractors (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
        if ($stmt) {
            app_bind($stmt, $types, $vals);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function app_legacy_rows(mysqli $db, string $type): array
{
    $table = $type === 'engineer' ? 'engineers' : 'contractors';
    if (!app_table_exists($db, $table)) {
        return [];
    }

    if ($type === 'engineer') {
        $nameExpr = app_col_exists($db, 'engineers', 'full_name')
            ? "COALESCE(full_name,'')"
            : (app_col_exists($db, 'engineers', 'first_name') && app_col_exists($db, 'engineers', 'last_name')
                ? "TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))"
                : "''");
        $emailExpr = app_col_exists($db, 'engineers', 'email') ? 'COALESCE(email,"")' : '""';
        $specExpr = app_col_exists($db, 'engineers', 'specialization') ? 'COALESCE(specialization,"")' : '""';
        $phoneExpr = app_col_exists($db, 'engineers', 'contact_number') ? 'COALESCE(contact_number,"")' : '""';
        $areaExpr = app_col_exists($db, 'engineers', 'address') ? 'COALESCE(address,"")' : '""';
        $statusExpr = app_col_exists($db, 'engineers', 'account_status')
            ? "COALESCE(account_status,'approved')"
            : (app_col_exists($db, 'engineers', 'status') ? "COALESCE(status,'approved')" : "'approved'");
        $createdExpr = app_col_exists($db, 'engineers', 'created_at') ? 'created_at' : 'NOW()';
        $sql = "SELECT id, {$nameExpr} AS display_name, {$emailExpr} AS email, {$phoneExpr} AS phone, {$specExpr} AS specialization, {$areaExpr} AS assigned_area, {$statusExpr} AS status, {$createdExpr} AS created_at FROM engineers ORDER BY id DESC LIMIT 500";
    } else {
        $nameExpr = app_col_exists($db, 'contractors', 'company_name')
            ? "COALESCE(company_name,'')"
            : (app_col_exists($db, 'contractors', 'company') ? "COALESCE(company,'')" : "''");
        $emailExpr = app_col_exists($db, 'contractors', 'email') ? 'COALESCE(email,"")' : '""';
        $specExpr = app_col_exists($db, 'contractors', 'specialization') ? 'COALESCE(specialization,"")' : '""';
        $phoneExpr = app_col_exists($db, 'contractors', 'phone') ? 'COALESCE(phone,"")' : '""';
        $areaExpr = app_col_exists($db, 'contractors', 'address') ? 'COALESCE(address,"")' : '""';
        $statusExpr = app_col_exists($db, 'contractors', 'status') ? "COALESCE(status,'approved')" : "'approved'";
        $createdExpr = app_col_exists($db, 'contractors', 'created_at') ? 'created_at' : 'NOW()';
        $sql = "SELECT id, {$nameExpr} AS display_name, {$emailExpr} AS email, {$phoneExpr} AS phone, {$specExpr} AS specialization, {$areaExpr} AS assigned_area, {$statusExpr} AS status, {$createdExpr} AS created_at FROM contractors ORDER BY id DESC LIMIT 500";
    }

    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    return $rows;
}

function app_legacy_detail(mysqli $db, string $type, int $id): ?array
{
    if ($id <= 0) return null;
    if ($type === 'engineer') {
        if (!app_table_exists($db, 'engineers')) return null;
        $sql = "SELECT * FROM engineers WHERE id = ? LIMIT 1";
    } else {
        if (!app_table_exists($db, 'contractors')) return null;
        $sql = "SELECT * FROM contractors WHERE id = ? LIMIT 1";
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    if (!$row) return null;

    if ($type === 'engineer') {
        $fullName = trim((string)($row['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        }
        $row['display_name'] = $fullName !== '' ? $fullName : ('Engineer #' . $id);
        if (!isset($row['assigned_area'])) $row['assigned_area'] = (string)($row['address'] ?? '');
        if (!isset($row['phone'])) $row['phone'] = (string)($row['contact_number'] ?? '');
        if (!isset($row['created_at'])) $row['created_at'] = date('Y-m-d H:i:s');
    } else {
        $company = trim((string)($row['company_name'] ?? ''));
        if ($company === '') $company = trim((string)($row['company'] ?? ''));
        $row['display_name'] = $company !== '' ? $company : ('Contractor #' . $id);
        if (!isset($row['assigned_area'])) $row['assigned_area'] = (string)($row['address'] ?? '');
        if (!isset($row['created_at'])) $row['created_at'] = date('Y-m-d H:i:s');
    }
    return $row;
}

function app_apply_legacy_status_update(mysqli $db, string $type, int $id, string $newStatus, string $remarks, string $reason): bool
{
    $statusMap = [
        'pending' => 'Pending',
        'under_review' => 'Under Review',
        'verified' => 'Verified',
        'approved' => 'Active',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
        'blacklisted' => 'Blacklisted',
    ];
    $legacyStatus = $statusMap[$newStatus] ?? ucfirst($newStatus);

    if ($type === 'engineer') {
        if (!app_table_exists($db, 'engineers')) return false;
        $set = [];
        $types = '';
        $vals = [];
        if (app_col_exists($db, 'engineers', 'status')) {
            $set[] = 'status = ?';
            $types .= 's';
            $vals[] = $legacyStatus;
        }
        if (app_col_exists($db, 'engineers', 'account_status')) {
            $acc = 'pending';
            if ($newStatus === 'approved') $acc = 'active';
            if ($newStatus === 'rejected') $acc = 'inactive';
            if ($newStatus === 'suspended') $acc = 'suspended';
            $set[] = 'account_status = ?';
            $types .= 's';
            $vals[] = $acc;
        }
        if (app_col_exists($db, 'engineers', 'notes')) {
            $set[] = 'notes = ?';
            $types .= 's';
            $vals[] = trim(($remarks !== '' ? $remarks : 'Status updated by admin') . ($reason !== '' ? (' | Reason: ' . $reason) : ''));
        }
        if (empty($set)) return false;
        $types .= 'i';
        $vals[] = $id;
        $sql = "UPDATE engineers SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;
        if (!app_bind($stmt, $types, $vals)) {
            $stmt->close();
            return false;
        }
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    if (!app_table_exists($db, 'contractors')) return false;
    $set = [];
    $types = '';
    $vals = [];
    if (app_col_exists($db, 'contractors', 'status')) {
        $set[] = 'status = ?';
        $types .= 's';
        $vals[] = $legacyStatus;
    }
    if (app_col_exists($db, 'contractors', 'notes')) {
        $set[] = 'notes = ?';
        $types .= 's';
        $vals[] = trim(($remarks !== '' ? $remarks : 'Status updated by admin') . ($reason !== '' ? (' | Reason: ' . $reason) : ''));
    }
    if (empty($set)) return false;
    $types .= 'i';
    $vals[] = $id;
    $sql = "UPDATE contractors SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    if (!app_bind($stmt, $types, $vals)) {
        $stmt->close();
        return false;
    }
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));
rbac_require_action_matrix($action !== '' ? $action : 'load_applications', [
    'load_summary' => 'admin.applications.view',
    'load_applications' => 'admin.applications.view',
    'get_application' => 'admin.applications.view',
    'load_logs' => 'admin.applications.view',
    'load_verified_users' => 'admin.applications.view',
    'load_rejected_users' => 'admin.applications.view',
    'update_status' => 'admin.applications.manage',
], 'admin.applications.view');

if (!in_array($action, ['load_summary', 'load_applications', 'get_application', 'load_logs', 'load_verified_users', 'load_rejected_users'], true)) {
    app_require_post_csrf();
}

$type = strtolower(trim((string)($_GET['type'] ?? $_POST['type'] ?? 'engineer')));
if (!in_array($type, ['engineer', 'contractor'], true)) $type = 'engineer';
$readOnlyActions = ['load_summary', 'load_applications', 'get_application', 'load_logs', 'load_verified_users', 'load_rejected_users'];
$action = $action === '' ? 'load_applications' : $action;
$fallbackLegacy = false;
$appTable = $type === 'engineer' ? 'engineer_applications' : 'contractor_applications';
app_ensure_schema($db);
if (!app_table_exists($db, $appTable)) {
    if (in_array($action, $readOnlyActions, true)) {
        $fallbackLegacy = true;
    } else {
        app_json(['success' => false, 'message' => 'Application tables are missing.'], 500);
    }
}

if ($action === 'load_summary') {
    if ($fallbackLegacy) {
        $rows = app_legacy_rows($db, $type);
        $out = ['pending' => 0, 'under_review' => 0, 'verified' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0];
        foreach ($rows as $row) {
            $status = strtolower(trim((string)($row['status'] ?? 'approved')));
            if ($status === 'active' || $status === 'approved') $status = 'approved';
            if ($status === 'inactive' || $status === 'blacklisted') $status = 'rejected';
            if (isset($out[$status])) $out[$status]++;
        }
        app_json(['success' => true, 'data' => $out, 'legacy' => true]);
    }
    $out = [
        'pending' => 0,
        'under_review' => 0,
        'verified' => 0,
        'approved' => 0,
        'rejected' => 0,
        'suspended' => 0,
    ];
    $res = $db->query("SELECT LOWER(status) status_key, COUNT(*) total FROM {$appTable} GROUP BY LOWER(status)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $k = (string)($row['status_key'] ?? '');
            if (array_key_exists($k, $out)) $out[$k] = (int)($row['total'] ?? 0);
        }
        $res->free();
    }
    $hasRows = array_sum($out) > 0;
    if (!$hasRows) {
        $legacyRows = app_legacy_rows($db, $type);
        if (!empty($legacyRows)) {
            $legacy = ['pending' => 0, 'under_review' => 0, 'verified' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0];
            foreach ($legacyRows as $row) {
                $status = strtolower(trim((string)($row['status'] ?? 'approved')));
                if ($status === 'active' || $status === 'approved') $status = 'approved';
                if ($status === 'inactive' || $status === 'blacklisted') $status = 'rejected';
                if (isset($legacy[$status])) $legacy[$status]++;
            }
            app_json(['success' => true, 'data' => $legacy, 'legacy' => true]);
        }
    }
    app_json(['success' => true, 'data' => $out]);
}

if ($action === 'load_applications') {
    if ($fallbackLegacy) {
        $q = strtolower(trim((string)($_GET['q'] ?? '')));
        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $specialization = strtolower(trim((string)($_GET['specialization'] ?? '')));
        $area = strtolower(trim((string)($_GET['area'] ?? '')));
        $rows = app_legacy_rows($db, $type);
        $rows = array_values(array_filter($rows, static function ($row) use ($q, $status, $specialization, $area) {
            $rowStatus = strtolower(trim((string)($row['status'] ?? 'approved')));
            $normalizedStatus = $rowStatus;
            if ($normalizedStatus === 'active') $normalizedStatus = 'approved';
            if ($normalizedStatus === 'inactive' || $normalizedStatus === 'blacklisted') $normalizedStatus = 'rejected';
            if ($q !== '') {
                $hay = strtolower(implode(' ', [
                    (string)($row['display_name'] ?? ''),
                    (string)($row['email'] ?? ''),
                    (string)($row['specialization'] ?? '')
                ]));
                if (strpos($hay, $q) === false) return false;
            }
            if ($status !== '' && $normalizedStatus !== $status) return false;
            if ($specialization !== '' && strpos(strtolower((string)($row['specialization'] ?? '')), $specialization) === false) return false;
            if ($area !== '' && strpos(strtolower((string)($row['assigned_area'] ?? '')), $area) === false) return false;
            return true;
        }));
        app_json(['success' => true, 'data' => $rows, 'legacy' => true]);
    }
    $q = strtolower(trim((string)($_GET['q'] ?? '')));
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    $specialization = strtolower(trim((string)($_GET['specialization'] ?? '')));
    $area = strtolower(trim((string)($_GET['area'] ?? '')));
    $dateSubmitted = trim((string)($_GET['date_submitted'] ?? ''));

    $assignedAreaField = app_col_exists($db, $appTable, 'assigned_area') ? 'assigned_area' : "'' AS assigned_area";
    $fields = $type === 'engineer'
        ? "id, full_name AS display_name, email, phone, department, position, specialization, {$assignedAreaField}, status, created_at"
        : "id, company_name AS display_name, email, phone, NULL AS department, NULL AS position, specialization, {$assignedAreaField}, status, created_at";

    $sql = "SELECT {$fields} FROM {$appTable} WHERE 1=1";
    $types = '';
    $params = [];

    if ($q !== '') {
        $sql .= " AND (LOWER(COALESCE(email,'')) LIKE ? OR LOWER(COALESCE(specialization,'')) LIKE ? OR LOWER(COALESCE(" . ($type === 'engineer' ? 'full_name' : 'company_name') . ",'')) LIKE ? )";
        $like = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '') {
        $sql .= " AND LOWER(COALESCE(status,'')) = ?";
        $types .= 's';
        $params[] = $status;
    }
    if ($specialization !== '') {
        $sql .= " AND LOWER(COALESCE(specialization,'')) LIKE ?";
        $types .= 's';
        $params[] = '%' . $specialization . '%';
    }
    if ($area !== '' && app_col_exists($db, $appTable, 'assigned_area')) {
        $sql .= " AND LOWER(COALESCE(assigned_area,'')) LIKE ?";
        $types .= 's';
        $params[] = '%' . $area . '%';
    }
    if ($dateSubmitted !== '') {
        $sql .= " AND DATE(created_at) = ?";
        $types .= 's';
        $params[] = $dateSubmitted;
    }

    $sql .= " ORDER BY created_at DESC LIMIT 500";
    $stmt = $db->prepare($sql);
    if (!$stmt) app_json(['success' => false, 'message' => 'Unable to load applications.'], 500);
    if (!app_bind($stmt, $types, $params)) {
        $stmt->close();
        app_json(['success' => false, 'message' => 'Unable to bind filters.'], 500);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    if ($res) $res->free();
    $stmt->close();

    if (empty($rows)) {
        $legacyRows = app_legacy_rows($db, $type);
        if (!empty($legacyRows)) {
            app_json(['success' => true, 'data' => $legacyRows, 'legacy' => true]);
        }
    }
    app_json(['success' => true, 'data' => $rows]);
}

if ($action === 'get_application') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) app_json(['success' => false, 'message' => 'Invalid application id.'], 422);

    $legacyLookup = static function () use ($db, $type, $id): void {
        $row = app_legacy_detail($db, $type, $id);
        if ($row) {
            app_json(['success' => true, 'data' => $row, 'documents' => [], 'logs' => [], 'legacy' => true]);
        }
        app_json(['success' => false, 'message' => 'Record not found.'], 404);
    };

    if ($fallbackLegacy) {
        $legacyLookup();
    }

    $stmt = $db->prepare("SELECT * FROM {$appTable} WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $legacyLookup();
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $app = $res ? $res->fetch_assoc() : null;
    if ($res) $res->free();
    $stmt->close();
    if (!$app) {
        // Applications table may exist but UI could be in legacy data mode.
        $legacyLookup();
    }

    $docs = [];
    if (app_table_exists($db, 'application_documents')) {
        $d = $db->prepare("SELECT id, doc_type, file_path, original_name, mime_type, file_size, uploaded_at FROM application_documents WHERE application_type = ? AND application_id = ? ORDER BY uploaded_at DESC");
        if ($d) {
            $d->bind_param('si', $type, $id);
            $d->execute();
            $dr = $d->get_result();
            while ($dr && ($row = $dr->fetch_assoc())) {
                $docs[] = $row;
            }
            if ($dr) $dr->free();
            $d->close();
        }
    }

    $logs = [];
    if (app_table_exists($db, 'application_logs')) {
        $l = $db->prepare("SELECT l.id, l.action, l.remarks, l.created_at, TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) performed_by FROM application_logs l LEFT JOIN employees e ON e.id = l.performed_by_user_id WHERE l.application_type = ? AND l.application_id = ? ORDER BY l.created_at DESC, l.id DESC");
        if ($l) {
            $l->bind_param('si', $type, $id);
            $l->execute();
            $lr = $l->get_result();
            while ($lr && ($row = $lr->fetch_assoc())) {
                $logs[] = $row;
            }
            if ($lr) $lr->free();
            $l->close();
        }
    }

    app_json(['success' => true, 'data' => $app, 'documents' => $docs, 'logs' => $logs]);
}

if ($action === 'load_logs') {
    if ($fallbackLegacy) {
        app_json(['success' => true, 'data' => [], 'legacy' => true]);
    }
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) app_json(['success' => false, 'message' => 'Invalid application id.'], 422);
    $rows = [];
    $l = $db->prepare("SELECT l.id, l.action, l.remarks, l.created_at, TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) performed_by FROM application_logs l LEFT JOIN employees e ON e.id = l.performed_by_user_id WHERE l.application_type = ? AND l.application_id = ? ORDER BY l.created_at DESC, l.id DESC");
    if ($l) {
        $l->bind_param('si', $type, $id);
        $l->execute();
        $lr = $l->get_result();
        while ($lr && ($row = $lr->fetch_assoc())) $rows[] = $row;
        if ($lr) $lr->free();
        $l->close();
    }
    app_json(['success' => true, 'data' => $rows]);
}

if ($action === 'load_verified_users') {
    if ($fallbackLegacy) {
        $rows = array_values(array_filter(app_legacy_rows($db, $type), static function ($row) {
            $status = strtolower(trim((string)($row['status'] ?? 'approved')));
            return in_array($status, ['approved', 'active', 'verified'], true);
        }));
        app_json(['success' => true, 'data' => $rows, 'legacy' => true]);
    }
    $nameCol = $type === 'engineer' ? (app_col_exists($db, $appTable, 'full_name') ? 'full_name' : 'email') : (app_col_exists($db, $appTable, 'company_name') ? 'company_name' : 'email');
    $approvedCol = app_col_exists($db, $appTable, 'approved_at') ? 'approved_at' : (app_col_exists($db, $appTable, 'updated_at') ? 'updated_at' : 'created_at');
    $createdCol = app_col_exists($db, $appTable, 'created_at') ? 'created_at' : 'NOW()';
    $specCol = app_col_exists($db, $appTable, 'specialization') ? 'specialization' : "''";
    $sql = "SELECT id, {$nameCol} AS display_name, email, {$specCol} AS specialization, status, {$approvedCol} AS approved_at, {$createdCol} AS created_at FROM {$appTable} WHERE LOWER(COALESCE(status,'')) = 'approved' ORDER BY {$approvedCol} DESC, {$createdCol} DESC LIMIT 500";
    $res = $db->query($sql);
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $res->free();
    } else {
        $rows = array_values(array_filter(app_legacy_rows($db, $type), static function ($row) {
            $status = strtolower(trim((string)($row['status'] ?? 'approved')));
            return in_array($status, ['approved', 'active', 'verified'], true);
        }));
        app_json(['success' => true, 'data' => $rows, 'legacy' => true]);
    }
    app_json(['success' => true, 'data' => $rows]);
}

if ($action === 'load_rejected_users') {
    if ($fallbackLegacy) {
        $rows = array_values(array_filter(app_legacy_rows($db, $type), static function ($row) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            return in_array($status, ['rejected', 'suspended', 'inactive', 'blacklisted'], true);
        }));
        app_json(['success' => true, 'data' => $rows, 'legacy' => true]);
    }
    $nameCol = $type === 'engineer' ? (app_col_exists($db, $appTable, 'full_name') ? 'full_name' : 'email') : (app_col_exists($db, $appTable, 'company_name') ? 'company_name' : 'email');
    $createdCol = app_col_exists($db, $appTable, 'created_at') ? 'created_at' : 'NOW()';
    $updatedCol = app_col_exists($db, $appTable, 'updated_at') ? 'updated_at' : $createdCol;
    $specCol = app_col_exists($db, $appTable, 'specialization') ? 'specialization' : "''";
    $reasonCol = app_col_exists($db, $appTable, 'rejection_reason') ? 'rejection_reason' : "''";
    $sql = "SELECT id, {$nameCol} AS display_name, email, {$specCol} AS specialization, status, {$reasonCol} AS rejection_reason, {$createdCol} AS created_at FROM {$appTable} WHERE LOWER(COALESCE(status,'')) IN ('rejected','suspended','blacklisted') ORDER BY {$updatedCol} DESC, {$createdCol} DESC LIMIT 500";
    $res = $db->query($sql);
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $res->free();
    } else {
        $rows = array_values(array_filter(app_legacy_rows($db, $type), static function ($row) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            return in_array($status, ['rejected', 'suspended', 'inactive', 'blacklisted'], true);
        }));
        app_json(['success' => true, 'data' => $rows, 'legacy' => true]);
    }
    app_json(['success' => true, 'data' => $rows]);
}

if ($action === 'update_status') {
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = strtolower(trim((string)($_POST['new_status'] ?? '')));
    $remarks = trim((string)($_POST['admin_remarks'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $checklist = trim((string)($_POST['checklist_json'] ?? ''));

    $allowed = $type === 'engineer'
        ? ['pending','under_review','verified','approved','rejected','suspended']
        : ['pending','under_review','verified','approved','rejected','blacklisted','suspended'];
    if ($id <= 0 || !in_array($newStatus, $allowed, true)) {
        app_json(['success' => false, 'message' => 'Invalid status payload.'], 422);
    }
    if (in_array($newStatus, ['rejected', 'suspended', 'blacklisted'], true) && $reason === '') {
        app_json(['success' => false, 'message' => 'Reason is required for this action.'], 422);
    }

    $stmt = $db->prepare("SELECT * FROM {$appTable} WHERE id = ? LIMIT 1");
    $app = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $app = $res ? $res->fetch_assoc() : null;
        if ($res) $res->free();
        $stmt->close();
    }

    if (!$app) {
        // Legacy path: allow admin actions directly on engineers/contractors tables.
        $ok = app_apply_legacy_status_update($db, $type, $id, $newStatus, $remarks, $reason);
        if (!$ok) {
            app_json(['success' => false, 'message' => 'Application not found.'], 404);
        }
        app_json(['success' => true, 'message' => 'Status updated successfully (legacy profile).', 'legacy' => true]);
    }

    $oldStatus = strtolower((string)($app['status'] ?? 'pending'));
    $actorId = (int)($_SESSION['employee_id'] ?? 0);

    $db->begin_transaction();
    try {
        if ($newStatus === 'approved') {
            $email = (string)($app['email'] ?? '');
            $nameOrCompany = $type === 'engineer' ? (string)($app['full_name'] ?? '') : (string)($app['company_name'] ?? '');
            $passwordHash = (string)($app['account_password_hash'] ?? '');
            $employeeId = app_create_or_activate_employee($db, $type, $email, $nameOrCompany, $passwordHash, $actorId);

            if ($type === 'engineer') app_sync_engineer_profile($db, $app, $employeeId);
            else app_sync_contractor_profile($db, $app, $employeeId);

            $up = $db->prepare("UPDATE {$appTable} SET status='approved', user_id=?, admin_remarks=?, rejection_reason=NULL, approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?");
            if (!$up) throw new RuntimeException('Unable to approve application.');
            $up->bind_param('isii', $employeeId, $remarks, $actorId, $id);
            $up->execute();
            $up->close();

            app_log($db, $type, $id, 'approved', trim('Status: ' . $oldStatus . ' -> approved. ' . $remarks));
            rbac_audit('admin.application.approved', 'application', $id, ['type' => $type, 'old_status' => $oldStatus, 'new_status' => 'approved']);
        } else {
            $setParts = ["status = ?", "admin_remarks = ?", "updated_at = NOW()"];
            $types = 'ss';
            $vals = [$newStatus, $remarks];

            if ($newStatus === 'verified') {
                $setParts[] = "verified_by = ?";
                $setParts[] = "verified_at = NOW()";
                $types .= 'i';
                $vals[] = $actorId;
            }

            if (in_array($newStatus, ['rejected', 'suspended'], true)) {
                if (app_col_exists($db, $appTable, 'rejection_reason')) {
                    $setParts[] = "rejection_reason = ?";
                    $types .= 's';
                    $vals[] = $reason;
                }
            }
            if ($newStatus === 'blacklisted' && app_col_exists($db, $appTable, 'blacklist_reason')) {
                $setParts[] = "blacklist_reason = ?";
                $types .= 's';
                $vals[] = $reason;
            }

            if (in_array($newStatus, ['rejected', 'suspended', 'blacklisted'], true) && (int)($app['user_id'] ?? 0) > 0 && app_col_exists($db, 'employees', 'account_status')) {
                $empStatus = $newStatus === 'rejected' ? 'inactive' : 'suspended';
                $esu = $db->prepare("UPDATE employees SET account_status = ? WHERE id = ?");
                if ($esu) {
                    $uid = (int)$app['user_id'];
                    $esu->bind_param('si', $empStatus, $uid);
                    $esu->execute();
                    $esu->close();
                }
            }

            $types .= 'i';
            $vals[] = $id;
            $sql = "UPDATE {$appTable} SET " . implode(', ', $setParts) . " WHERE id = ?";
            $up = $db->prepare($sql);
            if (!$up) throw new RuntimeException('Unable to update application status.');
            if (!app_bind($up, $types, $vals)) {
                $up->close();
                throw new RuntimeException('Unable to bind application update payload.');
            }
            $up->execute();
            $up->close();

            $combinedRemarks = trim('Status: ' . $oldStatus . ' -> ' . $newStatus . '. ' . $remarks . ' ' . $reason);
            if ($checklist !== '') $combinedRemarks .= ' Checklist: ' . $checklist;
            app_log($db, $type, $id, $newStatus, $combinedRemarks);
            rbac_audit('admin.application.status_update', 'application', $id, ['type' => $type, 'old_status' => $oldStatus, 'new_status' => $newStatus]);
        }

        $db->commit();
        app_json(['success' => true, 'message' => 'Application updated successfully.']);
    } catch (Throwable $e) {
        $db->rollback();
        app_json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

app_json(['success' => false, 'message' => 'Unknown action.'], 400);
