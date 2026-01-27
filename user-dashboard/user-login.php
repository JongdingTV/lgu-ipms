<?php
// Include security authentication
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Set no-cache headers on login page
set_no_cache_headers();

// Secret used to sign "remember this device" tokens (10‑day trust)
define('REMEMBER_DEVICE_SECRET', 'change_this_to_a_random_secret_key');

// Use PHPMailer (copied from the external LGU portal into this app's own vendor folder)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// IMPORTANT:
// Make sure you have a local copy of PHPMailer in: /vendor/PHPMailer
require dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
require dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
require dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';

// By default, show the normal email/password form
$showOtpForm = isset($_SESSION['pending_user'], $_SESSION['otp'], $_SESSION['otp_time']);

// Helper: check if this device is remembered for this user
function isRememberedDeviceForUser($userId) {
    if (empty($_COOKIE['remember_device'])) {
        return false;
    }
    $parts = explode('|', $_COOKIE['remember_device']);
    if (count($parts) !== 3) {
        return false;
    }
    list($cookieUserId, $expiresAt, $signature) = $parts;
    if ((int)$cookieUserId !== (int)$userId) {
        return false;
    }
    if ($expiresAt < time()) {
        return false;
    }
    $payload = $cookieUserId . '|' . $expiresAt;
    $expectedSig = hash_hmac('sha256', $payload, REMEMBER_DEVICE_SECRET);
    return hash_equals($expectedSig, $signature);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
    if ($db->connect_error) {
        die('Database connection failed: ' . $db->connect_error);
    }

    // STEP 2: Verify OTP
    if (isset($_POST['otp_submit'])) {
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time']) || !isset($_SESSION['pending_user'])) {
            $error = 'OTP expired or not generated. Please log in again.';
            // Clear any stale OTP-related data
            unset($_SESSION['otp'], $_SESSION['otp_time'], $_SESSION['pending_user']);
            $showOtpForm = false;
        } elseif (time() - $_SESSION['otp_time'] >= 600) { // 10 minutes
            $error = 'OTP expired. Please log in again.';
            unset($_SESSION['otp'], $_SESSION['otp_time'], $_SESSION['pending_user']);
            $showOtpForm = false;
        } elseif ($enteredOtp === $_SESSION['otp']) {
            // Finalize citizen login
            $user = $_SESSION['pending_user'];

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Remember this device for 10 days if requested
            if (!empty($_POST['remember_device'])) {
                $expiresAt = time() + (10 * 24 * 60 * 60);
                $_SESSION['remember_device_until'] = $expiresAt;
                $payload = $user['id'] . '|' . $expiresAt;
                $signature = hash_hmac('sha256', $payload, REMEMBER_DEVICE_SECRET);
                $token = $payload . '|' . $signature;
                setcookie('remember_device', $token, $expiresAt, '/', '', false, true);
            }

            // Clear OTP data
            unset($_SESSION['otp'], $_SESSION['otp_time'], $_SESSION['pending_user']);

            header('Location: user-dashboard/user-dashboard.php');
            exit;
        } else {
            $error = 'Invalid OTP. Please try again.';
            $showOtpForm = true;
        }
    }
    // RESEND OTP after timer (10 minutes) – optional
    elseif (isset($_POST['resend_otp'])) {
        if (isset($_SESSION['pending_user'], $_SESSION['otp_time'])) {
            $elapsed = time() - $_SESSION['otp_time'];
            if ($elapsed >= 600) {
                $user = $_SESSION['pending_user'];
                $email = $user['email'] ?? null;

                if ($email) {
                    $otp = (string)rand(100000, 999999);
                    $_SESSION['otp'] = $otp;
                    $_SESSION['otp_time'] = time();

                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'lguportalph@gmail.com';
                        $mail->Password   = 'zsozvbpsggclkcno';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('lguportalph@gmail.com', 'LGU Portal');
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your Identity: LGU Citizen Portal OTP Code';
                        $mail->Body = '
                            <p>Dear citizen,</p>
                            <p>Your new one-time verification code for the LGU Citizen Portal is:</p>
                            <p style="font-size:24px;font-weight:bold;letter-spacing:4px;">' . $otp . '</p>
                            <p>This code is valid for <strong>10 minutes</strong> and can only be used once.</p>
                            <p>If you did not request this, you can safely ignore this email.</p>
                        ';

                        $mail->send();
                        $showOtpForm = true;
                    } catch (Exception $e) {
                        $error = 'Failed to resend OTP. Please try again later.';
                        $showOtpForm = true;
                    }
                } else {
                    $error = 'Unable to retrieve email address. Please log in again.';
                    unset($_SESSION['pending_user'], $_SESSION['otp'], $_SESSION['otp_time']);
                    $showOtpForm = false;
                }
            } else {
                $error = 'Please wait until the timer ends before requesting a new code.';
                $showOtpForm = true;
            }
        } else {
            $error = 'OTP session expired. Please log in again.';
            $showOtpForm = false;
        }
    }
    // STEP 1: Email + Password + initial OTP send
    elseif (isset($_POST['login_submit'])) {
        // Check rate limiting (brute force protection)
        if (is_rate_limited('login', 5, 300)) {
            $error = 'Too many login attempts. Please try again in 5 minutes.';
            log_security_event('RATE_LIMIT_EXCEEDED', 'Too many failed login attempts');
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $email = sanitize_email($email);

            $stmt = $db->prepare("SELECT id, password, first_name, last_name, email FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {

                    // If this device is remembered for this user, skip OTP entirely
                    if (isRememberedDeviceForUser($user['id'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        header('Location: user-dashboard/user-dashboard.php');
                        exit;
                    }

                    // Store user temporarily until OTP is verified
                    $_SESSION['pending_user'] = $user;

                    // Generate OTP (same style as LGU portal)
                    $otp = (string)rand(100000, 999999);
                    $_SESSION['otp'] = $otp;
                    $_SESSION['otp_time'] = time();

                    // Send OTP email using LGU portal Gmail account
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'lguportalph@gmail.com';
                        $mail->Password   = 'zsozvbpsggclkcno';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('lguportalph@gmail.com', 'LGU Portal');
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your Identity: LGU Citizen Portal OTP Code';

                        // Simple email body; you can copy the fancy HTML from the LGU portal if desired
                        $mail->Body = '
                            <p>Dear citizen,</p>
                            <p>Your one-time verification code for the LGU Citizen Portal is:</p>
                            <p style="font-size:24px;font-weight:bold;letter-spacing:4px;">' . $otp . '</p>
                            <p>This code is valid for <strong>10 minutes</strong> and can only be used once.</p>
                            <p>If you did not request this, you can safely ignore this email.</p>
                        ';

                        $mail->send();
                        $showOtpForm = true;
                    } catch (Exception $e) {
                        $error = 'Failed to send OTP. Please try again later.';
                        // Clean up pending login if email fails
                        unset($_SESSION['pending_user'], $_SESSION['otp'], $_SESSION['otp_time']);
                        $showOtpForm = false;
                    }
                } else {
                    // Failed login attempt - record it
                    $error = 'Invalid email or password.';
                    record_attempt('login');
                    log_security_event('FAILED_LOGIN', 'Failed login attempt with email: ' . $email);
                    $showOtpForm = false;
                }
            } else {
                // User not found - record attempt
                $error = 'Invalid email or password.';
                record_attempt('login');
                log_security_event('FAILED_LOGIN', 'Login attempt with non-existent email: ' . $email);
                $showOtpForm = false;
            }
        }
    }
    // End of elseif (isset($_POST['login_submit']))

    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Login</title>
<link rel="icon" type="image/png" href="logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<?php echo get_app_config_script(); ?>
<script src="security-no-back.js?v=<?php echo time(); ?>"></script>
</head>

<body>

<header class="nav">
    <div class="nav-logo">🏛️ Local Government Unit Portal</div>
    <div class="nav-links">
        <a href="">Home</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="logocityhall.png" class="icon-top">

        <h2 class="title">LGU Login</h2>

        <?php if ($showOtpForm && isset($_SESSION['pending_user'])): ?>
            <p class="subtitle">We sent a one-time verification code to your email. Enter it below to continue.</p>

            <form method="post">
                <div class="input-box">
                    <label>Verification Code</label>
                    <input type="text" name="otp" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required autocomplete="one-time-code">
                </div>

                <div class="input-box" style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <input type="checkbox" id="rememberDevice" name="remember_device" style="width:auto;">
                    <label for="rememberDevice" style="margin:0;">Remember this device for 10 days</label>
                </div>

                <button class="btn-primary" type="submit" name="otp_submit">Verify Code</button>
            </form>

            <form method="post" style="margin-top:10px;text-align:center;">
                <button class="btn-secondary" type="submit" name="resend_otp" id="resendBtn" disabled>Resend Code</button>
                <p class="small-text" id="resendInfo" style="margin-top:6px;">You can request another code in <span id="resendTimer">10:00</span>.</p>
            </form>
        <?php else: ?>
            <p class="subtitle">Secure access to community maintenance services.</p>

            <form method="post">

                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" id="loginEmail" placeholder="name@lgu.gov.ph" required autocomplete="email">
                    <span class="icon">📧</span>
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                    <span class="icon">🔒</span>
                </div>

                <button class="btn-primary" type="submit" name="login_submit">Sign In</button>

                <p class="small-text">Don’t have an account?
                    <a href="create.php" class="link">Create one</a>
                </p>
            </form>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div style="margin-top:12px;color:#b00;"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
        <div style="margin-top:12px;color:#0b0;">Account created successfully. Please log in.</div>
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
        © 2025 LGU Citizen Portal · All Rights Reserved
    </div>

</footer>

<?php if ($showOtpForm && isset($_SESSION['pending_user'], $_SESSION['otp_time'])): ?>
<script>
(function() {
    var resendBtn = document.getElementById('resendBtn');
    var timerEl = document.getElementById('resendTimer');
    if (!resendBtn || !timerEl) return;

    var total = <?php echo max(0, 600 - (time() - (int)$_SESSION['otp_time'])); ?>;

    function updateTimer() {
        if (total <= 0) {
            timerEl.textContent = '00:00';
            resendBtn.disabled = false;
            return;
        }
        var m = Math.floor(total / 60);
        var s = total % 60;
        timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        total--;
        if (total >= 0) {
            setTimeout(updateTimer, 1000);
        } else {
            resendBtn.disabled = false;
        }
    }

    resendBtn.disabled = true;
    updateTimer();
})();
</script>
<?php endif; ?>

</body>
</html>