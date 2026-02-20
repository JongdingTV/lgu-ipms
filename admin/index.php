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
                        
                        $stmt = $db->prepare("SELECT id, password, first_name, last_name FROM employees WHERE email = ?");
                        if ($stmt) {
                            $stmt->bind_param('s', $email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $employee = $result->fetch_assoc();
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

$show_gate_wall = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Employee Login</title>
<link rel="icon" type="image/png" href="../logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <link rel="stylesheet" href="../assets/css/shared/admin-auth.css">
    <style>
        :root {
            --page-navy: #0f2a4a;
            --page-blue: #1d4e89;
            --page-sky: #3f83c9;
            --page-light: #f7fbff;
            --page-text: #0f172a;
            --page-muted: #475569;
            --page-danger: #b91c1c;
            --page-danger-bg: #fee2e2;
            --page-success: #166534;
            --page-success-bg: #dcfce7;
            --page-border: rgba(15, 23, 42, 0.12);
        }

        .id-gate-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.78);
            backdrop-filter: blur(2px) grayscale(0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 9999;
        }

        .id-gate-modal {
            width: min(92vw, 460px);
            background: #f8fbff;
            border: 1px solid rgba(66, 95, 136, 0.28);
            border-radius: 16px;
            box-shadow: 0 18px 38px rgba(0, 0, 0, 0.38);
            padding: 18px;
        }

        .id-gate-modal h3 {
            margin: 0 0 8px 0;
            color: #0f2a4a;
            font-size: 1.3rem;
            font-weight: 800;
        }

        .id-gate-warning {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b91c1c;
            font-weight: 700;
            font-size: 0.92rem;
        }

        .id-gate-warning i {
            font-size: 0.98rem;
        }

        .id-gate-modal p {
            margin: 0 0 14px 0;
            color: #334b6f;
            line-height: 1.6;
            font-size: 0.94rem;
        }

        .id-gate-input {
            width: 100%;
            border: 1px solid #c3d4eb;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 1rem;
            color: #0f2a4a;
            margin-bottom: 10px;
        }

        .id-gate-input:focus {
            outline: none;
            border-color: #2d61a8;
            box-shadow: 0 0 0 3px rgba(45, 97, 168, 0.18);
        }

        .id-gate-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 11px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #1b4f92, #2f6db8);
            cursor: pointer;
        }

        .id-gate-actions {
            display: grid;
            gap: 8px;
        }

        .id-gate-home {
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            color: #1b4f92;
            background: #eef4fd;
            border: 1px solid #c7d8ef;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .id-gate-home:hover {
            background: #e6effc;
            border-color: #aac5e8;
        }

        .id-gate-error {
            margin-top: 10px;
            padding: 9px 10px;
            border-radius: 9px;
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            font-size: 0.9rem;
        }

        .gate-ok {
            margin: 12px 0 0;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--page-success-bg);
            border: 1px solid #86efac;
            color: var(--page-success);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .login-blocked {
            filter: grayscale(0.28) blur(1px);
            pointer-events: none;
            user-select: none;
        }

        body.admin-login-page {
            min-height: 100dvh;
            height: 100dvh;
            box-sizing: border-box;
            margin: 0;
            display: flex;
            flex-direction: column;
            padding-top: 88px;
            overflow: hidden;
            color: var(--page-text);
            background:
                radial-gradient(circle at 15% 15%, rgba(63, 131, 201, 0.28), transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(29, 78, 137, 0.26), transparent 45%),
                linear-gradient(125deg, rgba(7, 20, 36, 0.72), rgba(15, 42, 74, 0.68)),
                url("../cityhall.jpeg") center/cover fixed no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        body.admin-login-page::before {
            content: none !important;
            display: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        body.admin-login-page .nav {
            position: fixed;
            inset: 0 0 auto 0;
            width: 100%;
            height: 78px;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.94), rgba(247, 251, 255, 0.98));
            border-bottom: 1px solid var(--page-border);
            box-shadow: 0 12px 30px rgba(2, 6, 23, 0.12);
            z-index: 30;
        }

        body.admin-login-page .nav-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--page-navy);
            letter-spacing: 0.2px;
        }

        body.admin-login-page .nav-logo img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        body.admin-login-page .home-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 16px;
            border-radius: 10px;
            border: 1px solid rgba(29, 78, 137, 0.22);
            text-decoration: none;
            font-weight: 600;
            color: var(--page-blue);
            background: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        body.admin-login-page .home-btn:hover {
            background: #eff6ff;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(29, 78, 137, 0.16);
        }

        body.admin-login-page .wrapper {
            width: 100%;
            flex: 1;
            min-height: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 16px 36px;
        }

        body.admin-login-page .card {
            width: 100%;
            max-width: 430px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 20px;
            padding: 30px 26px;
            text-align: center;
            box-shadow: 0 24px 56px rgba(2, 6, 23, 0.3);
        }

        body.admin-login-page .icon-top {
            width: 72px;
            height: 72px;
            object-fit: contain;
            margin: 2px auto 10px;
        }

        body.admin-login-page .title {
            margin: 0 0 6px;
            font-size: 1.7rem;
            line-height: 1.2;
            color: var(--page-navy);
        }

        body.admin-login-page .subtitle {
            margin: 0 0 20px;
            color: var(--page-muted);
        }

        body.admin-login-page .input-box {
            text-align: left;
            position: relative;
            margin-bottom: 14px;
        }

        body.admin-login-page .input-box label {
            display: block;
            font-size: 0.86rem;
            color: #1e293b;
            margin-bottom: 6px;
        }

        body.admin-login-page .input-box input {
            width: 100%;
            height: 46px;
            border-radius: 11px;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: #ffffff;
            padding: 10px 40px 10px 12px;
            font-size: 0.95rem;
            color: #0f172a;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        body.admin-login-page .input-box input:focus {
            border-color: var(--page-sky);
            box-shadow: 0 0 0 4px rgba(63, 131, 201, 0.15);
        }

        body.admin-login-page .icon {
            position: absolute;
            top: 37px;
            right: 13px;
            color: #64748b;
            font-size: 0.95rem;
            pointer-events: none;
        }

        body.admin-login-page .btn-primary {
            width: 100%;
            height: 46px;
            margin-top: 6px;
            border: 0;
            border-radius: 11px;
            background: linear-gradient(135deg, #1d4e89, #3f83c9);
            color: #ffffff;
            font-size: 0.98rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        body.admin-login-page .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(29, 78, 137, 0.3);
        }

        body.admin-login-page .ac-4d4de932 {
            margin-top: 12px;
            font-size: 0.88rem;
        }

        body.admin-login-page .ac-f72a71bf {
            color: var(--page-blue);
            text-decoration: none;
            font-weight: 600;
        }

        body.admin-login-page .ac-f72a71bf:hover {
            text-decoration: underline;
        }

        body.admin-login-page .ac-aabba7cf {
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 10px;
            text-align: left;
            background: var(--page-danger-bg);
            color: var(--page-danger);
            font-size: 0.89rem;
            border: 1px solid rgba(185, 28, 28, 0.2);
        }

        body.admin-login-page .ac-0b2b14a3 {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--page-success-bg);
            border: 1px solid rgba(22, 101, 52, 0.2);
            color: var(--page-success);
            text-align: left;
            font-size: 0.88rem;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        body.admin-login-page .ac-99b23121 {
            font-weight: 700;
            line-height: 1.2;
            margin-top: 1px;
        }

        body.admin-login-page .footer {
            width: 100%;
            padding: 16px 18px 20px;
            background: rgba(15, 23, 42, 0.52);
            color: #e2e8f0;
            border-top: 1px solid rgba(226, 232, 240, 0.2);
        }

        body.admin-login-page .footer-links {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-bottom: 7px;
        }

        body.admin-login-page .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.86rem;
        }

        body.admin-login-page .footer-links a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        body.admin-login-page .footer-logo {
            text-align: center;
            font-size: 0.82rem;
            color: #cbd5e1;
        }

        @media (max-width: 640px) {
            body.admin-login-page {
                padding-top: 82px;
            }

            body.admin-login-page .nav {
                height: auto;
                min-height: 70px;
                padding: 10px 12px;
            }

            body.admin-login-page .nav-logo {
                font-size: 0.84rem;
                gap: 7px;
            }

            body.admin-login-page .nav-logo img {
                width: 36px;
                height: 36px;
            }

            body.admin-login-page .home-btn {
                padding: 8px 12px;
                font-size: 0.86rem;
            }

            body.admin-login-page .card {
                max-width: 100%;
                padding: 24px 16px;
                border-radius: 16px;
            }

            body.admin-login-page .footer-links {
                flex-wrap: wrap;
                gap: 12px;
            }
        }
    </style>
</head>

<body class="admin-login-page">

<header class="nav">
    <div class="nav-logo"><img src="../logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="../public/index.php" class="home-btn" aria-label="Go to Home">Home</a>
</header>

<div class="wrapper">
    <div class="card">

        <img src="../logocityhall.png" class="icon-top">

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


