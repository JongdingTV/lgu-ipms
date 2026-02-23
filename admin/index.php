<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/config-path.php';

// Add no-cache headers to prevent cached login page from being shown after logout
set_no_cache_headers();

// Get client IP
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$client_ip = get_client_ip();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

function get_employee_id_by_email($db, $email) {
    if (!isset($db) || $db->connect_error || $email === '') {
        return null;
    }
    $stmt = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

function employees_has_column(mysqli $db, string $column): bool {
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'employees'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $exists;
}

function safe_log_login($db, $email, $ip, $agent, $status, $reason = null, $employeeId = null) {
    if (!isset($db) || $db->connect_error || $email === '') {
        return;
    }
    if ($employeeId === null) {
        $employeeId = get_employee_id_by_email($db, $email);
    }
    if ($employeeId === null) {
        return;
    }

    try {
        if ($reason !== null && $reason !== '') {
            $stmt = $db->prepare("INSERT INTO login_logs (employee_id, email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isssss', $employeeId, $email, $ip, $agent, $status, $reason);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $db->prepare("INSERT INTO login_logs (employee_id, email, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('issss', $employeeId, $email, $ip, $agent, $status);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        error_log('safe_log_login failed: ' . $e->getMessage());
    }
}

// Session timeout (30 minutes)
$session_timeout = 1800;
if (isset($_SESSION['employee_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
        // Log timeout
        if (isset($db) && !$db->connect_error) {
            $emp_id = $_SESSION['employee_id'];
            $session_id = session_id();
            $stmt = $db->prepare("UPDATE session_logs SET status = 'expired' WHERE employee_id = ? AND session_id = ? AND status = 'active'");
            if ($stmt) {
                $stmt->bind_param('is', $emp_id, $session_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        session_destroy();
        session_start();
        $show_login_form = true;
        $error = 'Your session has expired. Please log in again.';
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Check if user is accessing admin page without verification or login
if (!isset($_SESSION['employee_id'])) {
    // User not logged in - will show login form below
    $show_login_form = true;
} else {
    $show_login_form = false;
    // Redirect authenticated users before any HTML output
    header('Location: /admin/dashboard.php');
    exit;
}

// If already logged in, proceed normally
// If verified but not logged in, show login form (admin_verified persists)

$error = '';
$email_input = '';
$gate_error = '';
$employee_id_input = '';
$gate_ok_flash = '';
$gate_passed = true;
unset($_SESSION['admin_gate_passed'], $_SESSION['admin_gate_verified_id'], $_SESSION['admin_gate_notice']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } else {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            $email_input = $email;
            
            if (!empty($email) && !empty($password)) {
                // Check if account is locked
                $lock_stmt = $db->prepare("SELECT locked_until FROM locked_accounts WHERE email = ? AND locked_until > NOW()");
                if ($lock_stmt) {
                    $lock_stmt->bind_param('s', $email);
                    $lock_stmt->execute();
                    $lock_result = $lock_stmt->get_result();
                    
                    if ($lock_result->num_rows > 0) {
                        $lock_data = $lock_result->fetch_assoc();
                        $time_remaining = strtotime($lock_data['locked_until']) - time();
                        $minutes = ceil($time_remaining / 60);
                        
                        $error = "Account locked due to too many failed login attempts. Try again in $minutes minute" . ($minutes > 1 ? 's' : '') . ".";
                        
                        safe_log_login($db, $email, $client_ip, $user_agent, 'locked', 'Account locked');
                        
                        $lock_stmt->close();
                    } else {
                        // Account not locked, proceed with login
                        $lock_stmt->close();
                        
                        $hasRoleColumn = employees_has_column($db, 'role');
                        $hasStatusColumn = employees_has_column($db, 'account_status');
                        $selectFields = "id, password, first_name, last_name";
                        if ($hasRoleColumn) {
                            $selectFields .= ", role";
                        }
                        if ($hasStatusColumn) {
                            $selectFields .= ", account_status";
                        }
                        $stmt = $db->prepare("SELECT {$selectFields} FROM employees WHERE email = ?");
                        if ($stmt) {
                            $stmt->bind_param('s', $email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $employee = $result->fetch_assoc();
                                $accountStatus = strtolower((string)($employee['account_status'] ?? 'active'));
                                if ($hasStatusColumn && $accountStatus !== 'active') {
                                    $error = 'Your account is currently ' . htmlspecialchars($accountStatus) . '. Please contact the Super Admin.';
                                    safe_log_login($db, $email, $client_ip, $user_agent, 'failed', 'Account not active', (int) $employee['id']);
                                    $stmt->close();
                                    goto login_done;
                                }
                                $isAdmin = ($email === 'admin@lgu.gov.ph');
                                $valid = false;
                                
                                if ($isAdmin) {
                                    // Allow plain password for admin test account
                                    $valid = ($password === 'admin123' || password_verify($password, $employee['password']));
                                } else {
                                    $valid = password_verify($password, $employee['password']);
                                }
                                
                                if ($valid) {
                                    // Reset failed login attempts
                                    $reset_stmt = $db->prepare("DELETE FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                                    if ($reset_stmt) {
                                        $reset_stmt->bind_param('s', $email);
                                        $reset_stmt->execute();
                                        $reset_stmt->close();
                                    }
                                    
                                    // Log successful login
                                    safe_log_login($db, $email, $client_ip, $user_agent, 'success', null, (int) $employee['id']);
                                    
                                    // Log session
                                    $session_id = session_id();
                                    $session_stmt = $db->prepare("INSERT INTO session_logs (employee_id, session_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW())");
                                    if ($session_stmt) {
                                        $session_stmt->bind_param('isss', $employee['id'], $session_id, $client_ip, $user_agent);
                                        $session_stmt->execute();
                                        $session_stmt->close();
                                    }
                                    
                                    unset($_SESSION['admin_gate_passed'], $_SESSION['admin_gate_verified_id']);
                                    $_SESSION['employee_id'] = $employee['id'];
                                    $_SESSION['employee_name'] = $isAdmin ? 'Admin' : ($employee['first_name'] . ' ' . $employee['last_name']);
                                    $role = strtolower(trim((string)($employee['role'] ?? '')));
                                    if ($role === '') {
                                        $role = $isAdmin ? 'super_admin' : 'employee';
                                    }
                                    $_SESSION['employee_role'] = $role;
                                    $_SESSION['is_super_admin'] = ($role === 'super_admin');
                                    $_SESSION['user_type'] = 'employee';
                                    $_SESSION['last_activity'] = time();
                                    $_SESSION['login_time'] = time();
                                    
                                    header('Location: /admin/dashboard.php');
                                    exit;
                                } else {
                                    // Failed login - track attempt
                                    $error = 'Invalid email or password.';
                                    
                                    safe_log_login($db, $email, $client_ip, $user_agent, 'failed', 'Invalid credentials', (int) $employee['id']);
                                    
                                    // Track failed attempt
                                    $attempt_stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, FALSE)");
                                    if ($attempt_stmt) {
                                        $attempt_stmt->bind_param('ss', $email, $client_ip);
                                        $attempt_stmt->execute();
                                        $attempt_stmt->close();
                                    }
                                    
                                    // Check failed attempts in last 30 minutes
                                    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE email = ? AND success = FALSE AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
                                    if ($check_stmt) {
                                        $check_stmt->bind_param('s', $email);
                                        $check_stmt->execute();
                                        $check_result = $check_stmt->get_result();
                                        $count_data = $check_result->fetch_assoc();
                                        
                                        // Lock account after 5 failed attempts
                                        if ($count_data['count'] >= 5) {
                                            $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                                            $lock_insert = $db->prepare("INSERT INTO locked_accounts (email, locked_until, reason) VALUES (?, ?, 'Too many failed login attempts') ON DUPLICATE KEY UPDATE locked_until = ?");
                                            if ($lock_insert) {
                                                $lock_insert->bind_param('sss', $email, $locked_until, $locked_until);
                                                $lock_insert->execute();
                                                $lock_insert->close();
                                            }
                                            $error = "Account has been locked for 30 minutes due to too many failed attempts. Please try again later.";
                                        }
                                        
                                        $check_stmt->close();
                                    }
                                }
                            } else {
                                $error = 'This email is not registered for employee/admin access.';
                                safe_log_login($db, $email, $client_ip, $user_agent, 'failed', 'User not found');
                                $attempt_stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, FALSE)");
                                if ($attempt_stmt) {
                                    $attempt_stmt->bind_param('ss', $email, $client_ip);
                                    $attempt_stmt->execute();
                                    $attempt_stmt->close();
                                }
                            }
                            $stmt->close();
                        } else {
                            $error = 'Database error. Please try again later.';
                        }
                    }
                } else {
                    $error = 'Database error. Please try again later.';
                }
            } else {
                $error = 'Please enter both email and password.';
            }
    }
}

login_done:

$show_gate_wall = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Employee Login</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <link rel="stylesheet" href="../assets/css/shared/admin-auth.css">
    <link rel="stylesheet" href="../assets/css/admin-login.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-login.css'); ?>">
</head>

<body class="admin-login-page">

<header class="nav">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="../public/index.php" class="home-btn" aria-label="Go to Home">Home</a>
</header>

<div class="wrapper">
    <div class="card">

        <img src="../assets/images/icons/ipms-icon.png" class="icon-top">

        <h2 class="title">Employee Login</h2>
        <p class="subtitle">Secure access for LGU employees.</p>
        <?php if (isset($_SESSION['admin_verified'])): ?>
        <div class="ac-0b2b14a3">
            <span class="ac-99b23121">&#10003;</span>
            <span><strong>Verified!</strong> You've passed 2FA verification. Now enter your credentials.</span>
        </div>
        <?php endif; ?>

        <?php if ($show_login_form): ?>
        <form method="post">

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" id="loginEmail" placeholder="employee@lgu.gov.ph" required autocomplete="email" value="<?php echo htmlspecialchars($email_input, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="icon">@</span>
            </div>

            <div class="input-box">
                <label>Password</label>
                <input type="password" name="password" id="loginPassword" placeholder="********" required autocomplete="current-password">
                <span class="icon">*</span>
            </div>

            <button class="btn-primary" type="submit">Sign In</button>

            <!-- Removed 'For citizens, click here' button as requested -->

            <?php if (!empty($error)): ?>
            <div class="ac-aabba7cf"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

        </form>
        <?php endif; ?>
    </div>
</div>


    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>




