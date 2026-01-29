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
<link rel="stylesheet" href="/admin/style - Copy.css">
<?php echo get_app_config_script(); ?>
<script src="security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body class="user-login-page">
<!-- Blur overlay -->
<style>
body.user-login-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: url('/cityhall.jpeg') center/cover no-repeat fixed;
    position: relative;
    padding-top: 80px;
}
body.user-login-page::before {
    content: "";
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: inherit;
    filter: blur(10px) brightness(0.95);
    z-index: 0;
    pointer-events: none;
}
.nav, .wrapper, .footer { position: relative; z-index: 1; }
.nav { width:100%;position:fixed;top:0;left:0;right:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 32px;height:64px;background:rgba(255,255,255,0.85);backdrop-filter:blur(8px);box-shadow:0 2px 12px rgba(30,58,95,0.04); }
.nav-logo { display:flex;align-items:center;gap:10px; }
.nav-logo img { height:40px;width:auto;object-fit:contain; }
.nav-links { display:flex;align-items:center;gap:24px;margin-left:32px; }
.nav-links a { color:#1e293b;text-decoration:none;font-weight:500;font-size:1.08em;transition:color 0.2s; }
.nav-links a:hover { color:#f39c12; }
.footer {
    position: fixed !important;
    bottom: 0; left: 0; right: 0;
    width: 100%;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(8px);
    color: #1e293b;
    z-index: 100;
    padding: 10px 0 4px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 -2px 12px rgba(30,58,95,0.04);
    font-size: 0.93em;
}
.footer-logo {
    color: #0a4d8c; /* Change font color for copyright */
    font-size: 0.91em;
    opacity: 0.8;
    text-align: center;
    width: 100%;
    max-width: 1200px;
}
.footer-links {
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 18px;
    justify-content: center;
}
.footer-links a {
    color: #1e293b;
    text-decoration: none;
    font-size: 0.93em;
    opacity: 0.9;
}
.footer-links a:hover { color: #f39c12; }
.footer-logo {
    font-size: 0.91em;
    opacity: 0.8;
    text-align: center;
    width: 100%;
    max-width: 1200px;
}
.card .icon-top {
    display: block;
    margin: 0 auto 10px auto;
    height: 56px;
    width: auto;
    object-fit: contain;
}
@media (max-width: 600px) {
    .nav { padding: 0 10px; height: 56px; }
    .nav-logo img { height: 32px; }
    .nav-links a { font-size: 0.98em; }
    .footer { font-size: 0.91em; padding: 8px 0 2px 0; }
    .footer-links { gap: 10px; }
    .footer-logo { font-size: 0.89em; }
    .card .icon-top { height: 40px; }
}
html, body { height: 100%; margin: 0; }
body { min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
.wrapper { flex: 1 0 auto; display: flex; align-items: center; justify-content: center; }
</style>
<header class="nav">
    <div class="nav-logo">
        <img src="/assets/logocityhall.png" alt="LGU Logo">
        <span style="color:#1e293b;font-weight:600;font-size:1.15em;">Local Government Unit Portal</span>
    </div>
    <nav class="nav-links">
        <a href="/public/index.php">Home</a>
    </nav>
</header>
<div class="wrapper">
    <div class="card">
        <img src="/assets/logocityhall.png" class="icon-top" alt="LGU Logo" style="margin-bottom: 10px;">
        <h2 class="title" style="display: flex; align-items: center; gap: 10px; justify-content: center;">
            <img src="/assets/logocityhall.png" alt="LGU Logo" style="height:32px;width:auto;object-fit:contain;vertical-align:middle;"> Citizen Login
        </h2>
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
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
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
    <div class="footer-logo">© 2025 LGU Citizen Portal · All Rights Reserved</div>
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