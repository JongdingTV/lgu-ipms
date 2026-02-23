<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/config/email.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $activeRole = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
    if (in_array($activeRole, ['contractor', 'admin', 'super_admin'], true)) {
        header('Location: /contractor/dashboard_overview.php');
        exit;
    }
}

$error = '';
$emailInput = '';
$otpMessage = '';
$otpPending = isset(
    $_SESSION['contractor_login_otp_employee_id'],
    $_SESSION['contractor_login_otp_code'],
    $_SESSION['contractor_login_otp_expires']
);
$otpEmail = (string)($_SESSION['contractor_login_otp_email'] ?? '');

function clear_contractor_login_otp_session(): void
{
    unset(
        $_SESSION['contractor_login_otp_employee_id'],
        $_SESSION['contractor_login_otp_name'],
        $_SESSION['contractor_login_otp_email'],
        $_SESSION['contractor_login_otp_role'],
        $_SESSION['contractor_login_otp_code'],
        $_SESSION['contractor_login_otp_expires'],
        $_SESSION['contractor_login_otp_attempts'],
        $_SESSION['contractor_login_otp_sent_at']
    );
}

function issue_contractor_login_otp(int $employeeId, string $name, string $email, string $role): bool
{
    $code = (string) random_int(100000, 999999);
    if (!send_verification_code($email, $code, $name === '' ? 'Contractor' : $name)) {
        return false;
    }
    $_SESSION['contractor_login_otp_employee_id'] = $employeeId;
    $_SESSION['contractor_login_otp_name'] = $name;
    $_SESSION['contractor_login_otp_email'] = $email;
    $_SESSION['contractor_login_otp_role'] = $role;
    $_SESSION['contractor_login_otp_code'] = $code;
    $_SESSION['contractor_login_otp_expires'] = time() + (10 * 60);
    $_SESSION['contractor_login_otp_attempts'] = 0;
    $_SESSION['contractor_login_otp_sent_at'] = time();
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp_submit'])) {
        if (!$otpPending) {
            $error = 'No OTP session found. Please sign in again.';
        } elseif (is_rate_limited('contractor_login_otp', 10, 600)) {
            $error = 'Too many OTP attempts. Please wait before trying again.';
        } else {
            $otpInput = trim((string)($_POST['otp_code'] ?? ''));
            $storedCode = (string)($_SESSION['contractor_login_otp_code'] ?? '');
            $expiresAt = (int)($_SESSION['contractor_login_otp_expires'] ?? 0);
            $attempts = (int)($_SESSION['contractor_login_otp_attempts'] ?? 0);

            if (time() > $expiresAt) {
                clear_contractor_login_otp_session();
                $otpPending = false;
                $error = 'OTP expired. Please sign in again.';
            } elseif (!preg_match('/^\d{6}$/', $otpInput)) {
                $error = 'Please enter the 6-digit OTP.';
            } elseif (!hash_equals($storedCode, $otpInput)) {
                $_SESSION['contractor_login_otp_attempts'] = $attempts + 1;
                if ($_SESSION['contractor_login_otp_attempts'] >= 5) {
                    clear_contractor_login_otp_session();
                    $otpPending = false;
                    $error = 'Too many incorrect OTP attempts. Please sign in again.';
                } else {
                    $error = 'Invalid OTP code.';
                }
            } else {
                $employeeId = (int)($_SESSION['contractor_login_otp_employee_id'] ?? 0);
                $employeeName = (string)($_SESSION['contractor_login_otp_name'] ?? '');
                $role = strtolower(trim((string)($_SESSION['contractor_login_otp_role'] ?? 'contractor')));
                clear_contractor_login_otp_session();
                session_regenerate_id(true);
                $_SESSION['employee_id'] = $employeeId;
                $_SESSION['employee_name'] = $employeeName;
                $_SESSION['employee_role'] = $role;
                $_SESSION['user_type'] = 'employee';
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /contractor/dashboard_overview.php');
                exit;
            }
        }
    } elseif (isset($_POST['resend_otp_submit'])) {
        if (!$otpPending) {
            $error = 'No OTP session found. Please sign in again.';
        } elseif (is_rate_limited('contractor_login_otp_resend', 4, 600)) {
            $error = 'Too many OTP resend attempts. Please wait.';
        } else {
            $employeeId = (int)($_SESSION['contractor_login_otp_employee_id'] ?? 0);
            $employeeName = (string)($_SESSION['contractor_login_otp_name'] ?? '');
            $role = strtolower(trim((string)($_SESSION['contractor_login_otp_role'] ?? 'contractor')));
            $email = (string)($_SESSION['contractor_login_otp_email'] ?? '');
            $lastSent = (int)($_SESSION['contractor_login_otp_sent_at'] ?? 0);
            if ($employeeId <= 0 || $email === '') {
                clear_contractor_login_otp_session();
                $otpPending = false;
                $error = 'OTP session invalid. Please sign in again.';
            } elseif ($lastSent > 0 && (time() - $lastSent) < 45) {
                $error = 'Please wait before requesting another OTP.';
            } elseif (issue_contractor_login_otp($employeeId, $employeeName, $email, $role)) {
                $otpPending = true;
                $otpEmail = $email;
                $otpMessage = 'A new OTP was sent to your email.';
            } else {
                $error = 'Unable to resend OTP right now.';
            }
        }
    } else {
        $emailInput = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($emailInput === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } elseif (is_rate_limited('contractor_login', 6, 300)) {
            $error = 'Too many login attempts. Please try again in a few minutes.';
        } else {
            $stmt = $db->prepare("SELECT id, first_name, last_name, role, password FROM employees WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $emailInput);
                $stmt->execute();
                $result = $stmt->get_result();
                $employee = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                $validPassword = false;
                if ($employee) {
                    $validPassword = password_verify($password, (string) $employee['password']);
                }

                if (!$employee || !$validPassword) {
                    record_attempt('contractor_login');
                    $error = 'Invalid email or password.';
                } else {
                    $userRole = strtolower(trim((string) ($employee['role'] ?? '')));
                    if (!in_array($userRole, ['contractor', 'admin', 'super_admin'], true)) {
                        log_security_event('ROLE_DENIED', 'Contractor login blocked for non-contractor role');
                        $error = 'Your account is not assigned to contractor access.';
                    } else {
                        clear_contractor_login_otp_session();
                        $fullName = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
                        if (issue_contractor_login_otp((int)$employee['id'], $fullName, $emailInput, $userRole)) {
                            $otpPending = true;
                            $otpEmail = $emailInput;
                            $otpMessage = 'A verification code was sent to your email. Enter it below to continue.';
                        } else {
                            $error = 'Login validated, but OTP email could not be sent. Please try again.';
                        }
                    }
                }
            } else {
                $error = 'Database error. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Login - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/admin-auth.css">
    <link rel="stylesheet" href="login.css">
</head>
<body class="admin-login-page">
<header class="nav">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="LGU Logo"> LGU Contractor Portal</div>
    <a href="/public/index.php" class="home-btn">Home</a>
</header>
<div class="wrapper">
    <div class="card">
        <img src="../assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU">
        <h2 class="title"><?php echo $otpPending ? 'Verify OTP' : 'Contractor Login'; ?></h2>
        <p class="subtitle"><?php echo $otpPending ? 'Enter the code sent to your email to continue.' : 'Use your employee account assigned as contractor.'; ?></p>
        <?php if ($otpPending): ?>
            <form method="post" autocomplete="off">
                <div class="meta-links" style="margin-bottom:10px;font-size:.88rem;">
                    Code sent to: <strong><?php echo htmlspecialchars($otpEmail, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="input-box">
                    <label>One-Time Password (6 digits)</label>
                    <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required placeholder="123456">
                    <span class="icon">#</span>
                </div>
                <button class="btn-primary" type="submit" name="verify_otp_submit">Verify & Sign In</button>
                <button class="btn-primary" type="submit" name="resend_otp_submit" style="margin-top:10px;background:linear-gradient(135deg,#475569,#64748b);">Resend OTP</button>
                <?php if ($otpMessage !== ''): ?>
                    <div class="meta-links" style="color:#166534;"><?php echo htmlspecialchars($otpMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="ac-aabba7cf"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <form method="post" autocomplete="off">
                <div class="input-box">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="icon">@</span>
                </div>
                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <span class="icon">*</span>
                </div>
                <button class="btn-primary" type="submit">Sign In</button>
                <?php if ($error !== ''): ?>
                    <div class="ac-aabba7cf"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div class="meta-links" style="margin-top:12px;font-size:.88rem;">
                    No account yet? <a href="/contractor/create.php" style="color:#1d4e89;font-weight:600;text-decoration:none;">Create contractor account</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<script src="login-security.js?v=<?php echo filemtime(__DIR__ . '/login-security.js'); ?>"></script>
</body>
</html>

