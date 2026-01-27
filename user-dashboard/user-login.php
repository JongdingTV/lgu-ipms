<?php
// Include security authentication
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Set no-cache headers on login page
set_no_cache_headers();

// Secret used to sign "remember this device" tokens (10‑day trust)
define('REMEMBER_DEVICE_SECRET', 'change_this_to_a_random_secret_key');



// Use the same mailer as admin side
require_once dirname(__DIR__) . '/config/email.php';

// ...existing code...

                        // ...existing code...
                        // Use the same mailer as admin side
                        $recipientEmail = isset($_SESSION['pending_user']['email']) ? $_SESSION['pending_user']['email'] : (isset($email) ? $email : null);
                        $recipientName = isset($_SESSION['pending_user']['first_name']) ? $_SESSION['pending_user']['first_name'] : '';
                        if ($recipientEmail && $otp) {
                            send_verification_code($recipientEmail, $otp, $recipientName);
                        }
                        $showOtpForm = true;
        // End of elseif (isset($_POST['login_submit']))
        $db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Login</title>
<link rel="icon" type="image/png" href="/assets/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
    /* Isolate login page from sidebar/nav overlap */
    body.login-page .nav {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        background: rgba(255,255,255,0.15) !important;
        border-right: none !important;
        border-bottom: 1px solid rgba(255,255,255,0.25) !important;
        box-shadow: 0 4px 25px rgba(0,0,0,0.10) !important;
        z-index: 10 !important;
        padding: 18px 60px !important;
    }
    body.login-page .nav-links {
        flex-direction: row !important;
        gap: 18px !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    body.login-page {
        min-height: 100vh !important;
        background: url('/cityhall.jpeg') center/cover no-repeat fixed !important;
        background-attachment: fixed !important;
        position: relative !important;
    }
    body.login-page::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.35);
        z-index: 0;
        pointer-events: none;
    }
    body.login-page .wrapper {
        width: 100vw !important;
        min-height: calc(100vh - 80px) !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding-bottom: 0 !important;
        margin: 0 !important;
        background: none !important;
    }
    body.login-page .card {
        width: 340px !important;
        background: rgba(255,255,255,0.92) !important;
        padding: 24px 28px 22px 28px !important;
        border-radius: 18px !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.18) !important;
        text-align: center !important;
        margin: 0 !important;
        border: 1.5px solid #e3e8f0 !important;
    }
    body.login-page .icon-top {
        width: 54px !important;
        margin-bottom: 10px !important;
        border-radius: 10px !important;
        box-shadow: 0 2px 8px rgba(30,58,138,0.08) !important;
    }
    body.login-page .title {
        font-size: 24px !important;
        font-weight: 600 !important;
        margin-bottom: 14px !important;
        color: #1e3a8a !important;
        letter-spacing: -0.5px !important;
    }
    body.login-page .subtitle {
        color: #40598f !important;
        font-size: 1.05em !important;
        margin-bottom: 16px !important;
    }
    body.login-page .input-box {
        margin-bottom: 14px !important;
        text-align: left !important;
    }
    body.login-page .input-box label {
        font-weight: 500 !important;
        color: #1e3a8a !important;
        margin-bottom: 4px !important;
        display: block !important;
        font-size: 0.97em !important;
    }
    body.login-page .input-box input[type="email"],
    body.login-page .input-box input[type="password"],
    body.login-page .input-box input[type="text"] {
        width: 100% !important;
        padding: 10px 12px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        font-size: 1em !important;
        background: #f8f9fa !important;
        margin-top: 2px !important;
        transition: border-color 0.2s;
    }
    body.login-page .input-box input[type="email"]:focus,
    body.login-page .input-box input[type="password"]:focus,
    body.login-page .input-box input[type="text"]:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.08) !important;
        outline: none !important;
    }
    body.login-page .btn-primary, body.login-page .btn-secondary {
        width: 100% !important;
        margin-top: 10px !important;
        padding: 12px 0 !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
        font-size: 1.05em !important;
        box-shadow: 0 8px 20px rgba(37,99,235,0.10) !important;
        border: none !important;
        transition: background 0.15s, color 0.15s;
    }
    body.login-page .btn-primary {
        background: linear-gradient(90deg, #1e3a8a, #2563eb) !important;
        color: #fff !important;
    }
    body.login-page .btn-primary:hover {
        background: linear-gradient(90deg, #2563eb, #1e3a8a) !important;
        color: #fff !important;
    }
    body.login-page .btn-secondary {
        background: #e8f0ff !important;
        color: #2563eb !important;
        border: 1px solid #2563eb !important;
    }
    body.login-page .btn-secondary:hover {
        background: #dbeafe !important;
        color: #1e3a8a !important;
    }
    body.login-page .footer {
        margin-top: 32px !important;
        padding: 18px 0 !important;
        background: none !important;
        color: #999 !important;
        font-size: 0.95em !important;
        text-align: center !important;
        border: none !important;
        box-shadow: none !important;
    }
    body.login-page .small-text, body.login-page .footer-links a {
        color: #64748b !important;
        font-size: 0.97em !important;
    }
    body.login-page .footer-links a {
        margin: 0 8px !important;
        text-decoration: none !important;
        transition: color 0.15s;
    }
    body.login-page .footer-links a:hover {
        color: #2563eb !important;
    }
</style>
<?php echo get_app_config_script(); ?>
<script src="security-no-back.js?v=<?php echo time(); ?>"></script>



<body>

<body class="login-page">
<header class="nav">
    <div class="nav-logo">🏛️ Local Government Unit Portal</div>
    <div class="nav-links">
        <a href="../public/index.php">Home</a>
    </div>
</header>
<div class="wrapper">
    <div class="card">

        <img src="/assets/logocityhall.png" class="icon-top">

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