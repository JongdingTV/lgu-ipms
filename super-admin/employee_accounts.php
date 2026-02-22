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

$allowedRoles = ['super_admin', 'admin', 'employee'];
$allowedStatuses = ['active', 'inactive', 'suspended'];

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

            if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
                $error = 'First name, last name, email and password are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                if (!in_array($role, $allowedRoles, true)) $role = 'employee';
                if (!in_array($status, $allowedStatuses, true)) $status = 'active';

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

                if ($error === '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    if ($hasRole && $hasStatus) {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('ssssss', $firstName, $lastName, $email, $hash, $role, $status);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
                    } elseif ($hasRole) {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('sssss', $firstName, $lastName, $email, $hash, $role);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
                    } else {
                        $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('ssss', $firstName, $lastName, $email, $hash);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account created successfully.' : 'Unable to create employee account.';
                        }
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

            if ($employeeId <= 0 || $firstName === '' || $lastName === '' || $email === '') {
                $error = 'Invalid update payload.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                if (!in_array($role, $allowedRoles, true)) $role = 'employee';
                if (!in_array($status, $allowedStatuses, true)) $status = 'active';
                $selfId = (int)$_SESSION['employee_id'];
                if ($employeeId === $selfId && $status !== 'active') {
                    $error = 'You cannot deactivate your own account.';
                }
                if ($error === '') {
                    if ($hasRole && $hasStatus) {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, role = ?, account_status = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('sssssi', $firstName, $lastName, $email, $role, $status, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
                        }
                    } elseif ($hasRole) {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('ssssi', $firstName, $lastName, $email, $role, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('sssi', $firstName, $lastName, $email, $employeeId);
                            $ok = $stmt->execute();
                            $stmt->close();
                            $message = $ok ? 'Employee account updated.' : 'Update failed.';
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
$result = $db->query("SELECT {$fields} FROM employees ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employeeRows[] = $row;
    }
    $result->free();
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
            <a href="/super-admin/registered_projects.php"><img src="../assets/images/admin/list.png" class="nav-icon">Registered Projects</a>
            <a href="/super-admin/employee_accounts.php" class="active"><img src="../assets/images/admin/person.png" class="nav-icon">Employee Accounts</a>
            <a href="/super-admin/dashboard.php"><img src="../assets/images/admin/check.png" class="nav-icon">Control Center</a>
            <a href="/admin/audit-logs.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Security Audit Logs</a>
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
            <form method="post" class="contractor-form">
                <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div><label>First Name</label><input type="text" name="first_name" required></div>
                    <div><label>Last Name</label><input type="text" name="last_name" required></div>
                    <div><label>Email</label><input type="email" name="email" required></div>
                </div>
                <div class="form-row">
                    <div><label>Password</label><input type="password" name="password" minlength="8" required></div>
                    <?php if ($hasRole): ?>
                    <div>
                        <label>Role</label>
                        <select name="role">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasStatus): ?>
                    <div>
                        <label>Status</label>
                        <select name="account_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn">Create Account</button>
            </form>
        </div>

        <div class="recent-projects" style="margin-top: 16px;">
            <h3>Existing Employee Accounts</h3>
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
                                <td>
                                    <form method="post" style="display:grid; gap:8px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="text" name="first_name" value="<?php echo sa_escape((string)$row['first_name']); ?>" required>
                                        <input type="text" name="last_name" value="<?php echo sa_escape((string)$row['last_name']); ?>" required>
                                        <input type="email" name="email" value="<?php echo sa_escape((string)$row['email']); ?>" required>
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
                                        <button type="submit" class="btn">Save</button>
                                    </form>
                                    <form method="post" style="margin-top:6px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="password" name="new_password" minlength="8" placeholder="New password" required>
                                        <button type="submit" class="view-btn">Reset Password</button>
                                    </form>
                                    <form method="post" style="margin-top:6px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sa_escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="edit-btn" onclick="return confirm('Delete this account?')">Delete</button>
                                    </form>
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
