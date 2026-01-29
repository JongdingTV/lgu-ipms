<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Load email configuration if available
$email_config = null;
if (file_exists(dirname(__DIR__) . '/config/email.php')) {
    require_once dirname(__DIR__) . '/config/email.php';
}

$error = '';
$success = '';
$step = 1;

// Check if we have a reset token in the URL
if (isset($_GET['token'])) {
    $step = 2;
    $reset_token = trim($_GET['token']);
}

// STEP 1: Request password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step1_request'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if email exists in database
        if (!isset($db) || $db->connect_error) {
            $error = 'Database connection error. Please try again later.';
        } else {
            $stmt = $db->prepare("SELECT id, first_name, last_name FROM employees WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    
                    // Generate a reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token in session (for demo purposes)
                    $_SESSION['reset_token'] = $reset_token;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_expires'] = $expires;
                    
                    // In production, you would send an email with the reset link
                    $success = "Password reset instructions have been sent to your email. Check your inbox!";
                    $success .= "<br><strong style='color: #f39c12;'>Demo Mode: <a href='?token=$reset_token' style='color: #f39c12;'>Click here to reset password</a></strong>";
                } else {
                    // For security, don't reveal if email exists or not
                    $success = "If an account exists with that email, you will receive password reset instructions.";
                }
                $stmt->close();
            } else {
                $error = 'Database error. Please try again later.';
            }
        }
    }
}

// STEP 2: Reset password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step2_reset'])) {
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Verify token and email
        if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $reset_token) {
            $error = 'Invalid or expired reset token.';
        } else {
            // Check if token has expired
            if (strtotime($_SESSION['reset_expires']) < time()) {
                $error = 'Reset token has expired. Please request a new one.';
                unset($_SESSION['reset_token']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_expires']);
                $step = 1;
            } else {
                // Update password in database
                if (!isset($db) || $db->connect_error) {
                    $error = 'Database connection error. Please try again later.';
                } else {
                    $email = $_SESSION['reset_email'];
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE employees SET password = ? WHERE email = ?");
                    if ($stmt) {
                        $stmt->bind_param('ss', $hashed_password, $email);
                        if ($stmt->execute()) {
                            $success = 'Password has been reset successfully! Redirecting to login...';
                            // Clear session
                            unset($_SESSION['reset_token']);
                            unset($_SESSION['reset_email']);
                            unset($_SESSION['reset_expires']);
                            // Redirect after 2 seconds
                            echo '<meta http-equiv="refresh" content="2;url=/admin/index.php">';
                        } else {
                            $error = 'Failed to update password. Please try again.';
                        }
                        $stmt->close();
                    } else {
                        $error = 'Database error. Please try again later.';
                    }
                }
            }
        }
    }
}

if (isset($db)) {
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - LGU Employee Portal</title>
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
    background: url("/cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
    padding-top: 80px;
}

body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    backdrop-filter: blur(6px);
    background: rgba(0, 0, 0, 0.35);
    z-index: 0;
}

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

        <h2 class="title">Reset Password</h2>
        <p class="subtitle">Recover your account access</p>

        <?php if (!empty($error)): ?>
        <div style="background-color: #ffe5e5; border: 1px solid #ffcccc; color: #c3423f; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div style="background-color: #e5ffe5; border: 1px solid #ccffcc; color: #27ae60; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- STEP 1: Request Reset -->
        <form method="post">
            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="employee@lgu.gov.ph" required>
                <span class="icon">📧</span>
            </div>

            <button class="btn-primary" type="submit" name="step1_request">Send Reset Link</button>

            <div style="text-align: center; margin-top: 12px;">
                <a href="/admin/index.php" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">Back to Login</a>
            </div>
        </form>

        <?php elseif ($step == 2): ?>
        <!-- STEP 2: Reset Password -->
        <form method="post">
            <div class="input-box">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="••••••••" required>
                <span class="icon">🔒</span>
            </div>

            <div class="input-box">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
                <span class="icon">🔒</span>
            </div>

            <button class="btn-primary" type="submit" name="step2_reset">Reset Password</button>

            <div style="text-align: center; margin-top: 12px;">
                <a href="/admin/index.php" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">Back to Login</a>
            </div>
        </form>

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
