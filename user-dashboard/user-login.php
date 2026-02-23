<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require dirname(__DIR__) . '/config/email.php';

set_no_cache_headers();

if (!isset($_SESSION['user_id'])) {
    try_auto_login_from_remember_cookie();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /user-dashboard/user-dashboard.php');
    exit;
}

$error = '';
$email = '';
$otpMessage = '';
$otpPending = isset($_SESSION['user_login_otp_user_id'], $_SESSION['user_login_otp_code'], $_SESSION['user_login_otp_expires']);
$otpEmail = (string) ($_SESSION['user_login_otp_email'] ?? '');

function clear_user_login_otp_session(): void
{
    unset(
        $_SESSION['user_login_otp_user_id'],
        $_SESSION['user_login_otp_name'],
        $_SESSION['user_login_otp_email'],
        $_SESSION['user_login_otp_code'],
        $_SESSION['user_login_otp_expires'],
        $_SESSION['user_login_otp_attempts'],
        $_SESSION['user_login_otp_remember']
    );
}

function issue_user_login_otp(int $userId, string $name, string $email): bool
{
    $code = (string) random_int(100000, 999999);
    $ok = send_verification_code($email, $code, $name === '' ? 'Citizen' : $name);
    if (!$ok) {
        return false;
    }

    $_SESSION['user_login_otp_user_id'] = $userId;
    $_SESSION['user_login_otp_name'] = $name;
    $_SESSION['user_login_otp_email'] = $email;
    $_SESSION['user_login_otp_code'] = $code;
    $_SESSION['user_login_otp_expires'] = time() + (10 * 60);
    $_SESSION['user_login_otp_attempts'] = 0;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } elseif (is_rate_limited('user_login', 5, 300)) {
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $stmt = $db->prepare('SELECT id, password, first_name, last_name FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        $userId = (int) ($user['id'] ?? 0);
                        if (is_user_rate_limited('user_login', 8, 900, (int) $user['id'])) {
                            $error = 'Too many login attempts for this account. Please wait a few minutes and try again.';
                            $stmt->close();
                            goto user_login_after_query;
                        }
                        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        if (is_valid_remember_device_for_user($userId)) {
                            clear_user_login_otp_session();
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['user_name'] = $fullName;
                            $_SESSION['user_type'] = 'citizen';
                            $_SESSION['last_activity'] = time();
                            $_SESSION['login_time'] = time();
                            $_SESSION['remember_until'] = time() + (REMEMBER_DEVICE_DAYS * 86400);
                            log_security_event('USER_LOGIN_SUCCESS', 'Citizen account login via remembered device');
                            header('Location: /user-dashboard/user-dashboard.php');
                            exit;
                        } else {
                            clear_user_login_otp_session();
                            if (issue_user_login_otp($userId, $fullName, $email)) {
                                $_SESSION['user_login_otp_remember'] = !empty($_POST['remember_device']) ? 1 : 0;
                                $otpPending = true;
                                $otpEmail = $email;
                                $otpMessage = 'A verification code was sent to your email. Enter it below to finish login.';
                                record_user_attempt('user_login', $userId);
                            } else {
                                $error = 'Login verified, but OTP email could not be sent. Please try again.';
                            }
                        }
                    } else {
                        record_attempt('user_login');
                        $error = 'Invalid email or password.';
                    }
                } else {
                    record_attempt('user_login');
                    $error = 'Invalid email or password.';
                }

                $stmt->close();
            } else {
                $error = 'Unable to process request right now. Please try again.';
            }
            user_login_after_query:
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp_submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!$otpPending) {
        $error = 'No OTP session found. Please sign in again.';
    } else {
        $otpInput = trim((string) ($_POST['otp_code'] ?? ''));
        $storedCode = (string) ($_SESSION['user_login_otp_code'] ?? '');
        $expiresAt = (int) ($_SESSION['user_login_otp_expires'] ?? 0);
        $attempts = (int) ($_SESSION['user_login_otp_attempts'] ?? 0);

        if (time() > $expiresAt) {
            clear_user_login_otp_session();
            $otpPending = false;
            $error = 'OTP expired. Please sign in again.';
        } elseif (!preg_match('/^\d{6}$/', $otpInput)) {
            $error = 'Please enter the 6-digit OTP code.';
        } elseif (!hash_equals($storedCode, $otpInput)) {
            $_SESSION['user_login_otp_attempts'] = $attempts + 1;
            if ($_SESSION['user_login_otp_attempts'] >= 5) {
                clear_user_login_otp_session();
                $otpPending = false;
                $error = 'Too many incorrect OTP attempts. Please sign in again.';
            } else {
                $error = 'Invalid OTP code.';
            }
        } else {
            $userId = (int) ($_SESSION['user_login_otp_user_id'] ?? 0);
            $userName = (string) ($_SESSION['user_login_otp_name'] ?? '');
            $rememberRequested = !empty($_SESSION['user_login_otp_remember']);
            clear_user_login_otp_session();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $userName;
            $_SESSION['user_type'] = 'citizen';
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            if ($rememberRequested) {
                remember_user_device($userId, REMEMBER_DEVICE_DAYS);
            }
            log_security_event('USER_LOGIN_SUCCESS', 'Citizen account login via OTP success');

            header('Location: /user-dashboard/user-dashboard.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp_submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!$otpPending) {
        $error = 'No OTP session found. Please sign in again.';
    } else {
        $userId = (int) ($_SESSION['user_login_otp_user_id'] ?? 0);
        $userName = (string) ($_SESSION['user_login_otp_name'] ?? '');
        $userEmail = (string) ($_SESSION['user_login_otp_email'] ?? '');
        if ($userId <= 0 || $userEmail === '') {
            clear_user_login_otp_session();
            $otpPending = false;
            $error = 'OTP session invalid. Please sign in again.';
        } elseif (issue_user_login_otp($userId, $userName, $userEmail)) {
            $otpPending = true;
            $otpEmail = $userEmail;
            $otpMessage = 'A new verification code was sent to your email.';
        } else {
            $error = 'Unable to resend OTP right now. Please try again.';
        }
    }
}

if (isset($db) && $db instanceof mysqli) {
    $db->close();
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Citizen Login</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<?php echo get_app_config_script(); ?>
<script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
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
    --page-border: rgba(15, 23, 42, 0.12);
}
body.user-login-page {
    min-height: 100vh;
    margin: 0;
    display: flex;
    flex-direction: column;
    padding-top: 88px;
    color: var(--page-text);
    background:
        radial-gradient(circle at 15% 15%, rgba(63, 131, 201, 0.28), transparent 40%),
        radial-gradient(circle at 85% 85%, rgba(29, 78, 137, 0.26), transparent 45%),
        linear-gradient(125deg, rgba(7, 20, 36, 0.72), rgba(15, 42, 74, 0.68)),
        url("/cityhall.jpeg") center/cover fixed no-repeat;
    background-attachment: fixed;
}
body.user-login-page .nav {
    position: fixed;
    inset: 0 0 auto 0;
    width: 100%;
    height: 78px;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, rgba(255,255,255,0.94), rgba(247,251,255,0.98));
    border-bottom: 1px solid var(--page-border);
    box-shadow: 0 12px 30px rgba(2, 6, 23, 0.12);
    z-index: 30;
}
body.user-login-page .nav-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--page-navy);
}
body.user-login-page .nav-logo img {
    width: 44px;
    height: 44px;
    object-fit: contain;
}
body.user-login-page .home-btn {
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
}
body.user-login-page .wrapper {
    width: 100%;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 16px 36px;
}
body.user-login-page .card {
    width: 100%;
    max-width: 430px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.75);
    border-radius: 20px;
    padding: 30px 26px;
    text-align: center;
    box-shadow: 0 24px 56px rgba(2, 6, 23, 0.3);
}
body.user-login-page .icon-top {
    width: 72px;
    height: 72px;
    object-fit: contain;
    margin: 2px auto 10px;
}
body.user-login-page .title {
    margin: 0 0 6px;
    font-size: 1.7rem;
    line-height: 1.2;
    color: var(--page-navy);
}
body.user-login-page .subtitle {
    margin: 0 0 20px;
    color: var(--page-muted);
}
body.user-login-page .input-box {
    text-align: left;
    margin-bottom: 14px;
}
body.user-login-page .input-box label {
    display: block;
    font-size: 0.86rem;
    color: #1e293b;
    margin-bottom: 6px;
}
body.user-login-page .input-box input {
    width: 100%;
    height: 46px;
    border-radius: 11px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: #ffffff;
    padding: 10px 12px;
    font-size: 0.95rem;
    color: #0f172a;
    outline: none;
}
body.user-login-page .input-box input:focus {
    border-color: var(--page-sky);
    box-shadow: 0 0 0 4px rgba(63, 131, 201, 0.15);
}
body.user-login-page .btn-primary {
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
}
body.user-login-page .meta-links {
    margin-top: 12px;
    font-size: 0.88rem;
}
body.user-login-page .meta-links a {
    color: var(--page-blue);
    text-decoration: none;
    font-weight: 600;
}
body.user-login-page .meta-links a:hover {
    text-decoration: underline;
}
body.user-login-page .error-box {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    text-align: left;
    background: var(--page-danger-bg);
    color: var(--page-danger);
    font-size: 0.89rem;
    border: 1px solid rgba(185, 28, 28, 0.2);
}
</style>
</head>
<body class="user-login-page">
<header class="nav">
    <div class="nav-logo"><img src="/assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="/public/index.php" class="home-btn" aria-label="Go to Home">Home</a>
</header>

<div class="wrapper">
    <div class="card">
        <img src="/assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU Logo">
        <h2 class="title"><?php echo $otpPending ? 'Verify OTP' : 'Citizen Login'; ?></h2>
        <p class="subtitle"><?php echo $otpPending ? 'Enter the code sent to your email to continue.' : 'Secure access to your user dashboard.'; ?></p>

        <?php if ($otpPending): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="meta-links" style="margin-bottom:10px;">
                    Code sent to: <strong><?php echo htmlspecialchars($otpEmail, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="input-box">
                    <label for="otpCode">One-Time Password (6 digits)</label>
                    <input type="text" name="otp_code" id="otpCode" maxlength="6" pattern="\d{6}" placeholder="123456" required>
                </div>
                <button class="btn-primary" type="submit" name="verify_otp_submit">Verify & Sign In</button>
                <button class="btn-primary" type="submit" name="resend_otp_submit" style="margin-top:10px;background:linear-gradient(135deg,#475569,#64748b);">Resend OTP</button>

                <?php if (!empty($otpMessage)): ?>
                <div class="meta-links" style="color:#166534;"><?php echo htmlspecialchars($otpMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <div class="error-box"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <form method="post" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="input-box">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" name="email" id="loginEmail" placeholder="name@lgu.gov.ph" required autocomplete="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="input-box">
                    <label for="loginPassword">Password</label>
                    <input type="password" name="password" id="loginPassword" placeholder="********" required autocomplete="current-password">
                </div>
                <div class="input-box" style="margin-top:-4px;margin-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="remember_device" value="1" checked style="width:16px;height:16px;">
                        <span style="font-size:0.86rem;color:#334155;">Remember this device for 7 days</span>
                    </label>
                </div>

                <button class="btn-primary" type="submit" name="login_submit">Sign In</button>

                <div class="meta-links">
                    <a href="/user-dashboard/user-forgot-password.php">Forgot Password?</a>
                </div>
                <div class="meta-links">
                    Don&apos;t have an account? <a href="/user-dashboard/create.php">Create one</a>
                </div>

                <?php if (!empty($error)): ?>
                <div class="error-box"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['success'])): ?>
                <div class="meta-links" style="color:#166534;">Account created successfully. Please log in.</div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>
</html>

