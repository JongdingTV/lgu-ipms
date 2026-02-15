<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require_once dirname(__DIR__) . '/config/email.php';

set_no_cache_headers();

$error = '';
$success = '';
$step = 1;
$csrfToken = generate_csrf_token();
$resetToken = trim((string) ($_GET['token'] ?? ''));
if ($resetToken !== '') {
    $step = 2;
}

function send_user_reset_email($email, $name, $resetToken)
{
    try {
        require_once dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 30;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LGU Citizen Portal';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = $scheme . '://' . $host . '/user-dashboard/user-forgot-password.php?token=' . urlencode($resetToken);

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
        <html>
            <body style=\"font-family:Arial,sans-serif;color:#0f172a;\">
                <p>Hello {$safeName},</p>
                <p>We received a request to reset your LGU citizen account password.</p>
                <p><a href=\"{$safeLink}\" style=\"display:inline-block;background:#1d4e89;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;\">Reset Your Password</a></p>
                <p>If the button does not work, copy and open this link:</p>
                <p>{$safeLink}</p>
                <p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>
            </body>
        </html>
        ";

        return $mail->send();
    } catch (Exception $e) {
        error_log('User password reset email exception: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1_request'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } elseif (is_rate_limited('user_password_reset_request', 3, 900)) {
        $error = 'Too many reset requests. Please try again after 15 minutes.';
    } else {
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $ins = $db->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)');
                    if ($ins) {
                        $ins->bind_param('sss', $email, $tokenHash, $expiresAt);
                        if ($ins->execute()) {
                            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            $mailSent = send_user_reset_email($email, $fullName === '' ? 'Citizen' : $fullName, $rawToken);
                            if (!$mailSent) {
                                error_log('Failed to send user password reset email to: ' . $email);
                            }
                        }
                        $ins->close();
                    }
                }

                $stmt->close();
                record_attempt('user_password_reset_request');
                $success = 'If an account exists with that email, a reset link has been sent.';
            } else {
                $error = 'Unable to process request right now. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2_reset'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } elseif (is_rate_limited('user_password_reset_submit', 5, 900)) {
        $error = 'Too many reset attempts. Please request a new reset link.';
    } else {
        $resetToken = trim((string) ($_POST['reset_token'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($resetToken === '') {
            $error = 'Invalid or missing reset token.';
            $step = 1;
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 8 ||
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
        } else {
            $tokenHash = hash('sha256', $resetToken);
            $check = $db->prepare('SELECT email FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
            if ($check) {
                $check->bind_param('s', $tokenHash);
                $check->execute();
                $res = $check->get_result();

                if ($res && $res->num_rows === 1) {
                    $row = $res->fetch_assoc();
                    $email = (string) ($row['email'] ?? '');
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $upd = $db->prepare('UPDATE users SET password = ? WHERE email = ? LIMIT 1');
                    if ($upd) {
                        $upd->bind_param('ss', $hash, $email);
                        if ($upd->execute()) {
                            $del = $db->prepare('DELETE FROM password_resets WHERE email = ?');
                            if ($del) {
                                $del->bind_param('s', $email);
                                $del->execute();
                                $del->close();
                            }

                            record_attempt('user_password_reset_submit');
                            $success = 'Password has been reset successfully. Redirecting to login...';
                            echo '<meta http-equiv="refresh" content="2;url=/user-dashboard/user-login.php">';
                        } else {
                            $error = 'Failed to reset password. Please try again.';
                        }
                        $upd->close();
                    } else {
                        $error = 'Unable to process request right now. Please try again.';
                    }
                } else {
                    $error = 'Invalid or expired reset link. Please request a new one.';
                    $step = 1;
                }

                $check->close();
            } else {
                $error = 'Unable to process request right now. Please try again.';
            }
        }
    }
}

if (isset($db) && $db instanceof mysqli) {
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Forgot Password</title>
<link rel="icon" type="image/png" href="/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<?php echo get_app_config_script(); ?>
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
body.user-forgot-page {
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
        url('/cityhall.jpeg') center/cover fixed no-repeat;
}
body.user-forgot-page .nav {
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
body.user-forgot-page .nav-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--page-navy);
}
body.user-forgot-page .nav-logo img {
    width: 44px;
    height: 44px;
    object-fit: contain;
}
body.user-forgot-page .home-btn {
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
body.user-forgot-page .wrapper {
    width: 100%;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 16px 36px;
}
body.user-forgot-page .card {
    width: 100%;
    max-width: 430px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.75);
    border-radius: 20px;
    padding: 30px 26px;
    text-align: center;
    box-shadow: 0 24px 56px rgba(2, 6, 23, 0.3);
}
body.user-forgot-page .icon-top {
    width: 72px;
    height: 72px;
    object-fit: contain;
    margin: 2px auto 10px;
}
body.user-forgot-page .title {
    margin: 0 0 6px;
    font-size: 1.7rem;
    line-height: 1.2;
    color: var(--page-navy);
}
body.user-forgot-page .subtitle {
    margin: 0 0 20px;
    color: var(--page-muted);
}
body.user-forgot-page .input-box {
    text-align: left;
    margin-bottom: 14px;
}
body.user-forgot-page .input-box label {
    display: block;
    font-size: 0.86rem;
    color: #1e293b;
    margin-bottom: 6px;
}
body.user-forgot-page .input-box input {
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
body.user-forgot-page .input-box input:focus {
    border-color: var(--page-sky);
    box-shadow: 0 0 0 4px rgba(63, 131, 201, 0.15);
}
body.user-forgot-page .btn-primary {
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
body.user-forgot-page .meta-links {
    margin-top: 12px;
    font-size: 0.88rem;
}
body.user-forgot-page .meta-links a {
    color: var(--page-blue);
    text-decoration: none;
    font-weight: 600;
}
body.user-forgot-page .error-box {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    text-align: left;
    background: var(--page-danger-bg);
    color: var(--page-danger);
    font-size: 0.89rem;
    border: 1px solid rgba(185, 28, 28, 0.2);
}
body.user-forgot-page .success-box {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    text-align: left;
    background: var(--page-success-bg);
    color: var(--page-success);
    font-size: 0.89rem;
    border: 1px solid rgba(22, 101, 52, 0.2);
}
body.user-forgot-page .requirements {
    margin-top: 10px;
    text-align: left;
    font-size: 0.84rem;
    color: #334155;
}
</style>
</head>
<body class="user-forgot-page">
<header class="nav">
    <div class="nav-logo"><img src="/logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="/public/index.php" class="home-btn" aria-label="Go to Home">Home</a>
</header>

<div class="wrapper">
    <div class="card">
        <img src="/logocityhall.png" class="icon-top" alt="LGU Logo">
        <h2 class="title">Forgot Password</h2>
        <p class="subtitle">Reset your citizen account password.</p>

        <?php if (!empty($error)): ?>
        <div class="error-box"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="success-box"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-box">
                <label for="resetEmail">Email Address</label>
                <input type="email" name="email" id="resetEmail" placeholder="name@lgu.gov.ph" required autocomplete="email">
            </div>
            <button class="btn-primary" type="submit" name="step1_request">Send Reset Link</button>
            <div class="meta-links"><a href="/user-dashboard/user-login.php">Back to Login</a></div>
        </form>

        <?php else: ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-box">
                <label for="newPassword">New Password</label>
                <input type="password" name="new_password" id="newPassword" placeholder="********" required autocomplete="new-password">
            </div>
            <div class="input-box">
                <label for="confirmPassword">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirmPassword" placeholder="********" required autocomplete="new-password">
            </div>
            <div class="requirements">Must include uppercase, lowercase, number, and special character, minimum 8 characters.</div>
            <button class="btn-primary" type="submit" name="step2_reset">Reset Password</button>
            <div class="meta-links"><a href="/user-dashboard/user-login.php">Back to Login</a></div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>
</html>
