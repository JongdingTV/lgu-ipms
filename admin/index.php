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
}

// If already logged in, proceed normally
// If verified but not logged in, show login form (admin_verified persists)

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check database connection before processing
    if (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
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
                    
                    // Log failed attempt
                    $log_stmt = $db->prepare("INSERT INTO login_logs (email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, 'locked', 'Account locked')");
                    if ($log_stmt) {
                        $log_stmt->bind_param('sss', $email, $client_ip, $user_agent);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
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
                                $log_stmt = $db->prepare("INSERT INTO login_logs (employee_id, email, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'success')");
                                if ($log_stmt) {
                                    $log_stmt->bind_param('isss', $employee['id'], $email, $client_ip, $user_agent);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                                
                                // Log session
                                $session_id = session_id();
                                $session_stmt = $db->prepare("INSERT INTO session_logs (employee_id, session_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW())");
                                if ($session_stmt) {
                                    $session_stmt->bind_param('isss', $employee['id'], $session_id, $client_ip, $user_agent);
                                    $session_stmt->execute();
                                    $session_stmt->close();
                                }
                                
                                $_SESSION['employee_id'] = $employee['id'];
                                $_SESSION['employee_name'] = $isAdmin ? 'Admin' : ($employee['first_name'] . ' ' . $employee['last_name']);
                                $_SESSION['user_type'] = 'employee';
                                $_SESSION['last_activity'] = time();
                                $_SESSION['login_time'] = time();
                                
                                header('Location: /admin/dashboard/dashboard.php');
                                exit;
                            } else {
                                // Failed login - track attempt
                                $error = 'Invalid email or password.';
                                
                                // Log failed attempt
                                $log_stmt = $db->prepare("INSERT INTO login_logs (email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, 'failed', 'Invalid credentials')");
                                if ($log_stmt) {
                                    $log_stmt->bind_param('sss', $email, $client_ip, $user_agent);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                                
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
                            $error = 'Invalid email or password.';
                            
                            // Log failed attempt
                            $log_stmt = $db->prepare("INSERT INTO login_logs (email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, 'failed', 'User not found')");
                            if ($log_stmt) {
                                $log_stmt->bind_param('sss', $email, $client_ip, $user_agent);
                                $log_stmt->execute();
                                $log_stmt->close();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Employee Login</title>
<link rel="icon" type="image/png" href="/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style - Copy.css">
<?php echo get_app_config_script(); ?>
<script src="/security-no-back.js?v=<?php echo time(); ?>"></script>
<style>

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;

    /* NEW — background image + blur */
    background: url("/cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
    padding-top: 80px;
}

/* NEW — Blur overlay */
body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    backdrop-filter: blur(6px); /* actual blur */
    background: rgba(0, 0, 0, 0.35); /* dark overlay */
    z-index: 0; /* keeps blur behind content */
}

/* Make content appear ABOVE blur */
.nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 100;
}

.wrapper, .footer {
    position: relative;
    z-index: 1;
}

.footer {
    position: fixed !important;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
}

.footer {
    position: fixed !important;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-logo img {
    height: 45px;
    width: auto;
    object-fit: contain;
}
</style>
</head>

<body>

<header class="nav">
    <div class="nav-logo"><img src="/logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="/logocityhall.png" class="icon-top">

        <h2 class="title">Employee Login</h2>
        <p class="subtitle">Secure access for LGU employees.</p>

        <?php if (isset($_SESSION['admin_verified'])): ?>
        <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2em;">✅</span>
            <span><strong>Verified!</strong> You've passed 2FA verification. Now enter your credentials.</span>
        </div>
        <?php endif; ?>

        <?php if ($show_login_form): ?>
        <form method="post">

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" id="loginEmail" placeholder="employee@lgu.gov.ph" required autocomplete="email">
                <span class="icon">📧</span>
            </div>

            <div class="input-box">
                <label>Password</label>
                <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                <span class="icon">🔒</span>
            </div>

            <button class="btn-primary" type="submit">Sign In</button>

            <div style="text-align: center; margin-top: 12px;">
                <a href="/admin/forgot-password.php" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">Forgot Password?</a>
            </div>

            <!-- Removed 'For citizens, click here' button as requested -->

            <?php if (isset($error)): ?>
            <div style="margin-top:12px;color:#b00;"><?php echo $error; ?></div>
            <?php endif; ?>

        </form>
        <?php else: ?>
            <p>Redirecting to dashboard...</p>
        <?php endif; ?>
    </div>
</div>

<footer class="footer">

    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">About</a>
        <a href="#">Help</a>
    </div>

    <div class="footer-logo">
        © 2026 LGU Citizen Portal · All Rights Reserved
    </div>

</footer>

</body>
</html>
