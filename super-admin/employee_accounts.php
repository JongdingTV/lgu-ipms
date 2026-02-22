<?php
require_once __DIR__ . '/auth.php';

function sa_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['super_admin_csrf'])) {
    $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['super_admin_csrf'];

$message = '';
$error = '';

$hasRole = super_admin_has_column($db, 'role');
$hasStatus = super_admin_has_column($db, 'account_status');

$allowedRoles = ['super_admin', 'admin', 'employee', 'engineer'];
$allowedStatuses = ['active', 'inactive', 'suspended'];

$searchQuery = trim((string)($_GET['q'] ?? ''));

function sa_table_exists(mysqli $db, string $table): bool
{
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
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function sa_table_has_column(mysqli $db, string $table, string $column): bool
{
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
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

$hasEngineersTable = isset($db) && !$db->connect_error && sa_table_exists($db, 'engineers');
$hasEngineerLink = $hasEngineersTable && sa_table_has_column($db, 'engineers', 'employee_id');
$engineerOptions = [];
$engineerByEmployee = [];
if ($hasEngineersTable) {
    $sql = "SELECT id, full_name, first_name, last_name, prc_license_number, employee_id FROM engineers ORDER BY id DESC";
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $engineerOptions[] = $row;
            if (!empty($row['employee_id'])) {
                $engineerByEmployee[(int)$row['employee_id']] = (int)$row['id'];
            }
        }
        $res->free();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $csrf)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = strtolower(trim((string)($_POST['role'] ?? 'employee')));
            $status = strtolower(trim((string)($_POST['account_status'] ?? 'active')));
            $linkEngineerId = (int)($_POST['engineer_id'] ?? 0);

            if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
                $error = 'First name, last name, email and password are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                if (!in_array($role, $allowedRoles, true)) $role = 'employee';
                if (!in_array($status, $allowedStatuses, true)) $status = 'active';
                if (!$hasEngineerLink || $role !== 'engineer') {
                    $linkEngineerId = 0;
                }

                $check = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
                if ($check) {
                    $check->bind_param('s', $email);
                    $check->execute();
                    $existsRes = $check->get_result();
                    $exists = $existsRes && $existsRes->num_rows > 0;
                    $check->close();
                    if ($exists) {
                        $error = 'Email is already registered to another employee.';
                    }
                }

                if ($error === '' && $linkEngineerId > 0) {
                    $checkEng = $db->prepare("SELECT employee_id FROM engineers WHERE id = ? LIMIT 1");
                    if ($checkEng) {
                        $checkEng->bind_param('i', $linkEngineerId);
                        $checkEng->execute();
                        $engRes = $checkEng->get_result();
                        $engRow = $engRes ? $engRes->fetch_assoc() : null;
                        $checkEng->close();
                        $linkedEmployee = (int)($engRow['employee_id'] ?? 0);
                        if (!$engRow) {
                            $error = 'Selected engineer record not found.';
                        } elseif ($linkedEmployee > 0) {
                            $error = 'Selected engineer is already linked to another account.';
                        }
                    }
                }

                if ($error === '') {
                    $needsLink = $linkEngineerId > 0;
                    if ($needsLink) {
                        $db->begin_transaction();
                    }
                    $createdOk = false;
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    if ($hasRole && $hasStatus) {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('ssssss', $firstName, $lastName, $email, $hash, $role, $status);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $createdOk = (bool)$ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.create', 'employee', (int) $db->insert_id, [
                                    'email' => $email,
                                    'role' => $role,
                                    'status' => $status
                                ]);
                            }
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
                    } elseif ($hasRole) {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('sssss', $firstName, $lastName, $email, $hash, $role);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $createdOk = (bool)$ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.create', 'employee', (int) $db->insert_id, [
                                    'email' => $email,
                                    'role' => $role,
                                    'status' => $status
                                ]);
                            }
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
                    } else {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('ssss', $firstName, $lastName, $email, $hash);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $createdOk = (bool)$ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.create', 'employee', (int) $db->insert_id, [
                                    'email' => $email,
                                    'role' => $role,
                                    'status' => $status
                                ]);
                            }
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
                    }
                    if ($needsLink && $createdOk && $error === '') {
                        $newEmployeeId = (int)$db->insert_id;
                        $linkStmt = $db->prepare("UPDATE engineers SET employee_id = ? WHERE id = ? AND (employee_id IS NULL OR employee_id = 0)");
                        if ($linkStmt) {
                            $linkStmt->bind_param('ii', $newEmployeeId, $linkEngineerId);
                            $okLink = $linkStmt->execute();
                            $linkStmt->close();
                            if (!$okLink || $db->affected_rows === 0) {
                                $db->rollback();
                                $error = 'Unable to link engineer to the new account.';
                                $message = '';
                            } else {
                                $db->commit();
                            }
                        } else {
                            $db->rollback();
                            $error = 'Unable to link engineer to the new account.';
                            $message = '';
                        }
                    } elseif ($needsLink && !$createdOk) {
                        $db->rollback();
                    }
                }
            }
        } elseif ($action === 'update') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = strtolower(trim((string)($_POST['role'] ?? 'employee')));
            $status = strtolower(trim((string)($_POST['account_status'] ?? 'active')));
            $linkEngineerId = (int)($_POST['engineer_id'] ?? 0);

            if ($employeeId <= 0 || $firstName === '' || $lastName === '' || $email === '') {
                $error = 'Invalid update payload.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                if (!in_array($role, $allowedRoles, true)) $role = 'employee';
                if (!in_array($status, $allowedStatuses, true)) $status = 'active';
                if (!$hasEngineerLink || $role !== 'engineer') {
                    $linkEngineerId = 0;
                }
                $selfId = (int)$_SESSION['employee_id'];
                if ($employeeId === $selfId && $status !== 'active') {
                    $error = 'You cannot deactivate your own account.';
                }
                if ($error === '') {
                    $employeeUpdated = false;
                    if ($hasRole && $hasStatus) {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, role = ?, account_status = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('sssssi', $firstName, $lastName, $email, $role, $status, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $employeeUpdated = $ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.update', 'employee', $employeeId, [
                                    'email' => $email,
                                    'role' => $role,
                                    'status' => $status
                                ]);
                            }
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
                        }
                    } elseif ($hasRole) {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('ssssi', $firstName, $lastName, $email, $role, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $employeeUpdated = $ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.update', 'employee', $employeeId, [
                                    'email' => $email,
                                    'role' => $role,
                                    'status' => $status
                                ]);
                            }
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('sssi', $firstName, $lastName, $email, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $employeeUpdated = $ok;
                            if ($ok && function_exists('rbac_audit')) {
                                rbac_audit('employee.update', 'employee', $employeeId, [
                                    'email' => $email
                                ]);
                            }
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
                        }
                    }
                    if ($employeeUpdated && $hasEngineerLink) {
                        if ($role !== 'engineer') {
                            $unlinkStmt = $db->prepare("UPDATE engineers SET employee_id = NULL WHERE employee_id = ?");
                            if ($unlinkStmt) {
                                $unlinkStmt->bind_param('i', $employeeId);
                                $unlinkStmt->execute();
                                $unlinkStmt->close();
                            }
                        } elseif ($linkEngineerId > 0) {
                            $checkEng = $db->prepare("SELECT employee_id FROM engineers WHERE id = ? LIMIT 1");
                            if ($checkEng) {
                                $checkEng->bind_param('i', $linkEngineerId);
                                $checkEng->execute();
                                $engRes = $checkEng->get_result();
                                $engRow = $engRes ? $engRes->fetch_assoc() : null;
                                $checkEng->close();
                                $linkedEmployee = (int)($engRow['employee_id'] ?? 0);
                                if (!$engRow) {
                                    $error = 'Selected engineer record not found.';
                                    $message = '';
                                } elseif ($linkedEmployee > 0 && $linkedEmployee !== $employeeId) {
                                    $error = 'Selected engineer is already linked to another account.';
                                    $message = '';
                                }
                            }
                            if ($error === '') {
                                $db->begin_transaction();
                                $unlinkStmt = $db->prepare("UPDATE engineers SET employee_id = NULL WHERE employee_id = ? AND id <> ?");
                                if ($unlinkStmt) {
                                    $unlinkStmt->bind_param('ii', $employeeId, $linkEngineerId);
                                    $unlinkStmt->execute();
                                    $unlinkStmt->close();
                                }
                                $linkStmt = $db->prepare("UPDATE engineers SET employee_id = ? WHERE id = ?");
                                if ($linkStmt) {
                                    $linkStmt->bind_param('ii', $employeeId, $linkEngineerId);
                                    $okLink = $linkStmt->execute();
                                    $linkStmt->close();
                                    if (!$okLink) {
                                        $db->rollback();
                                        $error = 'Unable to link engineer to this account.';
                                        $message = '';
                                    } else {
                                        $db->commit();
                                    }
                                } else {
                                    $db->rollback();
                                    $error = 'Unable to link engineer to this account.';
                                    $message = '';
                                }
                            }
                        } else {
                            $unlinkStmt = $db->prepare("UPDATE engineers SET employee_id = NULL WHERE employee_id = ?");
                            if ($unlinkStmt) {
                                $unlinkStmt->bind_param('i', $employeeId);
                                $unlinkStmt->execute();
                                $unlinkStmt->close();
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'reset_password') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $newPassword = (string)($_POST['new_password'] ?? '');
            if ($employeeId <= 0 || strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $hash, $employeeId);
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        // End active sessions for this employee if table exists
                        $sessionStmt = $db->prepare("UPDATE session_logs SET status = 'expired' WHERE employee_id = ? AND status = 'active'");
                        if ($sessionStmt) {
                            $sessionStmt->bind_param('i', $employeeId);
                            $sessionStmt->execute();
                            $sessionStmt->close();
                        }
                        if (function_exists('rbac_audit')) {
                            rbac_audit('employee.password_reset', 'employee', $employeeId);
                        }
                        $message = 'Password reset successfully.';
                    } else {
                        $error = 'Unable to reset password.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $selfId = (int)$_SESSION['employee_id'];
            if ($employeeId <= 0) {
                $error = 'Invalid account selected.';
            } elseif ($employeeId === $selfId) {
                $error = 'You cannot delete your own account.';
            } else {
                $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $employeeId);
                    $ok = $stmt->execute();
                    $stmt->close();
                if ($ok && function_exists('rbac_audit')) {
                    rbac_audit('employee.delete', 'employee', $employeeId);
                }
                $message = $ok ? 'Employee account deleted.' : 'Unable to delete account.';
                }
            }
        }
    }
}

$employeeRows = [];
$fields = "id, first_name, last_name, email, created_at";
if ($hasRole) $fields .= ", role";
if ($hasStatus) $fields .= ", account_status";
if ($searchQuery !== '' && $db) {
    $sql = "SELECT {$fields} FROM employees WHERE id = ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR first_name LIKE ? OR last_name LIKE ? ORDER BY id DESC";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $idQuery = ctype_digit($searchQuery) ? (int)$searchQuery : 0;
        $likeQuery = '%' . $searchQuery . '%';
        $stmt->bind_param('isss', $idQuery, $likeQuery, $likeQuery, $likeQuery);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $employeeRows[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $result = $db->query("SELECT {$fields} FROM employees ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employeeRows[] = $row;
        }
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Accounts - Super Admin</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/super-admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/super-admin.css'); ?>">
</head>
<body class="super-admin-theme">
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../assets/images/icons/ipms-icon.png" alt="IPMS Logo" class="logo-img">
            <span class="logo-text">Super Admin</span>
        </div>
        <div class="nav-links">
            <a href="/super-admin/dashboard_overview.php"><img src="../assets/images/admin/dashboard.png" class="nav-icon">Dashboard Overview</a>
            <a href="/super-admin/progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="/super-admin/employee_accounts.php" class="active"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/super-admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Employee Account Management</h1>
            <p>Super Admin controls for employee security, role governance, and account lifecycle.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo sa_escape($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo sa_escape($error); ?></div>
        <?php endif; ?>

        <div class="recent-projects">
            <h3>Create New Employee</h3>
            <form method="post" class="contractor-form sa-create-form">
                <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                <div class="sa-form-grid">
                    <div class="sa-field"><label>First Name</label><input type="text" name="first_name" required></div>
                    <div class="sa-field"><label>Last Name</label><input type="text" name="last_name" required></div>
                    <div class="sa-field"><label>Email</label><input type="email" name="email" required></div>
                    <div class="sa-field"><label>Password</label><input type="password" name="password" minlength="8" required></div>
                    <?php if ($hasRole): ?>
                    <div class="sa-field">
                        <label>Role</label>
                        <select name="role">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasStatus): ?>
                    <div class="sa-field">
                        <label>Status</label>
                        <select name="account_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasEngineerLink): ?>
                    <div class="sa-field">
                        <label>Linked Engineer</label>
                        <select name="engineer_id">
                            <option value="">No link</option>
                            <?php foreach ($engineerOptions as $eng): ?>
                                <?php
                                    $engId = (int)$eng['id'];
                                    $engName = trim((string)($eng['full_name'] ?? ''));
                                    if ($engName === '') {
                                        $engName = trim((string)($eng['first_name'] ?? '') . ' ' . (string)($eng['last_name'] ?? ''));
                                    }
                                    $engLabel = $engName !== '' ? $engName : ('Engineer #' . $engId);
                                    $license = trim((string)($eng['prc_license_number'] ?? ''));
                                    $linkedEmployeeId = (int)($eng['employee_id'] ?? 0);
                                    $disabled = $linkedEmployeeId > 0 ? 'disabled' : '';
                                    $suffix = $linkedEmployeeId > 0 ? ' (linked)' : '';
                                ?>
                                <option value="<?php echo $engId; ?>" <?php echo $disabled; ?>>
                                    <?php echo sa_escape($engLabel . ($license !== '' ? " | PRC {$license}" : '') . $suffix); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Linking works only when role is Engineer.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="sa-form-actions">
                    <button type="submit" class="view-btn">Create Account</button>
                </div>
            </form>
        </div>

        <div class="recent-projects sa-section-spaced">
            <div class="sa-section-header">
                <div>
                    <h3>Existing Employee Accounts</h3>
                    <p class="sa-section-subtext">Search by ID or name to filter results quickly.</p>
                </div>
                <form method="get" class="sa-search-form">
                    <input type="text" name="q" placeholder="Search ID or name..." value="<?php echo sa_escape($searchQuery); ?>">
                    <button type="submit" class="view-btn sa-mini-btn">Search</button>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="/super-admin/employee_accounts.php" class="edit-btn sa-mini-btn sa-clear-btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="table-wrap">
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($employeeRows)): ?>
                        <tr><td colspan="7">No employees found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($employeeRows as $row): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sa_escape(trim((string)$row['first_name'] . ' ' . (string)$row['last_name'])); ?></td>
                                <td><?php echo sa_escape((string)$row['email']); ?></td>
                                <td><?php echo sa_escape((string)($row['role'] ?? 'employee')); ?></td>
                                <td><?php echo sa_escape((string)($row['account_status'] ?? 'active')); ?></td>
                                <td><?php echo sa_escape((string)($row['created_at'] ?? '-')); ?></td>
                                <td class="sa-actions-cell">
                                    <div class="sa-action-stack">
                                        <form method="post" class="sa-inline-form sa-action-card">
                                            <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                            <p class="sa-action-title">Update Account</p>
                                            <div class="sa-action-grid">
                                                <input type="text" name="first_name" value="<?php echo sa_escape((string)$row['first_name']); ?>" required>
                                                <input type="text" name="last_name" value="<?php echo sa_escape((string)$row['last_name']); ?>" required>
                                        <input type="email" name="email" value="<?php echo sa_escape((string)$row['email']); ?>" required class="sa-span-2">
                                        <?php if ($hasRole): ?>
                                        <select name="role">
                                            <?php foreach ($allowedRoles as $roleName): ?>
                                                <option value="<?php echo sa_escape($roleName); ?>" <?php echo strtolower((string)($row['role'] ?? '')) === $roleName ? 'selected' : ''; ?>>
                                                    <?php echo sa_escape($roleName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <?php if ($hasStatus): ?>
                                        <select name="account_status">
                                            <?php foreach ($allowedStatuses as $statusName): ?>
                                                <option value="<?php echo sa_escape($statusName); ?>" <?php echo strtolower((string)($row['account_status'] ?? 'active')) === $statusName ? 'selected' : ''; ?>>
                                                    <?php echo sa_escape($statusName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <?php if ($hasEngineerLink): ?>
                                            <?php $currentEngineerId = (int)($engineerByEmployee[(int)$row['id']] ?? 0); ?>
                                            <select name="engineer_id" class="sa-span-2" aria-label="Linked Engineer">
                                                <option value="">No link</option>
                                                <?php foreach ($engineerOptions as $eng): ?>
                                                    <?php
                                                        $engId = (int)$eng['id'];
                                                        $engName = trim((string)($eng['full_name'] ?? ''));
                                                        if ($engName === '') {
                                                            $engName = trim((string)($eng['first_name'] ?? '') . ' ' . (string)($eng['last_name'] ?? ''));
                                                        }
                                                        $engLabel = $engName !== '' ? $engName : ('Engineer #' . $engId);
                                                        $license = trim((string)($eng['prc_license_number'] ?? ''));
                                                        $linkedEmployeeId = (int)($eng['employee_id'] ?? 0);
                                                        $isLinkedToOther = $linkedEmployeeId > 0 && $linkedEmployeeId !== (int)$row['id'];
                                                        $disabled = $isLinkedToOther ? 'disabled' : '';
                                                        $suffix = $isLinkedToOther ? ' (linked)' : '';
                                                        $selected = $currentEngineerId === $engId ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo $engId; ?>" <?php echo $selected . ' ' . $disabled; ?>>
                                                        <?php echo sa_escape($engLabel . ($license !== '' ? " | PRC {$license}" : '') . $suffix); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                            <button type="submit" class="view-btn sa-mini-btn">Save Changes</button>
                                        </form>
                                        <form method="post" class="sa-inline-form sa-action-card sa-action-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="password" name="new_password" minlength="8" placeholder="New password" required>
                                            <button type="submit" class="view-btn sa-mini-btn">Reset Password</button>
                                        </form>
                                        <form method="post" class="sa-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="edit-btn sa-mini-btn sa-delete-btn" onclick="return confirm('Delete this account?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>
