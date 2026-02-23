<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Load email configuration
require_once dirname(__DIR__) . '/config/email.php';

$error = '';
$success = '';
$step = 1;
if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
}
$forgotPasswordCsrf = (string)$_SESSION['forgot_password_csrf'];

// Check if we have a reset token in the URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $step = 2;
    $reset_token = trim($_GET['token']);
}

/**
 * Send password reset email
 */
function send_reset_email($email, $employee_name, $reset_token) {
    try {
        error_log('=== SEND_RESET_EMAIL CALLED ===');
        error_log('Email: ' . $email);
        
        // Load PHPMailer
        require_once dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';
        
        error_log('PHPMailer loaded');
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 2;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 30;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        error_log('SMTP configured: ' . MAIL_HOST . ':' . MAIL_PORT);
        
        // Email content
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $employee_name);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - LGU IPMS';
        
        // Build reset link
        $reset_link = 'https://' . $_SERVER['HTTP_HOST'] . '/admin/forgot-password.php?token=' . urlencode($reset_token);
        
        // Email body
        $body = "
        <html>
            <head>
            </head>
            <body>
                <div class=\"container\">
                    <div class=\"header\">
                        <h1>ðŸ” Password Reset Request</h1>
                    </div>
                    
                    <p>Hello " . htmlspecialchars($employee_name) . ",</p>
                    
                    <p>You requested to reset your password for the LGU IPMS system. Click the button below to proceed:</p>
                    
                    <div class=\"button-box\">
                        <a href=\"" . htmlspecialchars($reset_link) . "\" class=\"reset-btn\">Reset Your Password</a>
                    </div>
                    
                    <p>Or copy and paste this link in your browser:</p>
                    <p class=\"link-text\">" . htmlspecialchars($reset_link) . "</p>
                    
                    <div class=\"warning\">
                        <strong>âš ï¸ Security Notice:</strong><br>
                        â€¢ This link expires in 1 hour<br>
                        â€¢ Never share this link with anyone<br>
                        â€¢ If you didn't request this, please ignore this email
                    </div>
                    
                <div class=\"footer\">
                        <p>Â© " . date('Y') . " LGU IPMS System. All rights reserved.</p>
                </div>
                </div>
            </body>
        </html>
        ";
        
        // Send email
        ob_start();
        try {
            if ($mail->send()) {
                ob_end_clean();
                error_log('âœ… Email sent successfully to: ' . $email);
                return true;
            } else {
                $error_info = $mail->ErrorInfo;
                ob_end_clean();
                error_log('âŒ Email send failed: ' . $error_info);
                return false;
            }
        } catch (Exception $e) {
            ob_end_clean();
            error_log('âŒ PHPMailer Exception: ' . $e->getMessage());
            return false;
        }
    } catch (Exception $e) {
        error_log('âŒ Password reset email exception: ' . $e->getMessage());
        return false;
    }
}

// STEP 1: Request password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step1_request'])) {
    if (!hash_equals($forgotPasswordCsrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request token. Please refresh the page and try again.';
    }
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Debug: Log the request
    error_log('Forgot password request for: ' . $email);
    
    // Rate limiting check (max 3 requests per 15 minutes)
    $rate_limit_key = 'reset_' . $email;
    $rate_limit_file = sys_get_temp_dir() . '/' . md5($rate_limit_key) . '.txt';
    
    if (file_exists($rate_limit_file)) {
        $file_time = filemtime($rate_limit_file);
        $count = intval(file_get_contents($rate_limit_file));
        
        if ((time() - $file_time) < 900) { // 15 minutes
            if ($count >= 3) {
                $error = 'Too many reset requests. Please try again in 15 minutes.';
            }
        } else {
            // Reset counter after 15 minutes
            file_put_contents($rate_limit_file, '1');
        }
    } else {
        file_put_contents($rate_limit_file, '1');
    }
    
    if ($error !== '') {
        // keep csrf token error
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($error)) {
        // Check if email exists in database
        if (!isset($db) || $db->connect_error) {
            $error = 'Database connection error. Please try again later.';
            error_log('DB Connection Error: ' . $db->connect_error);
        } else {
            $stmt = $db->prepare("SELECT id, first_name, last_name FROM employees WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    error_log('Employee found: ' . $employee['first_name']);
                    
                    // Generate a secure reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $reset_token);
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Check if password_resets table exists
                    $table_check = $db->query("SHOW TABLES LIKE 'password_resets'");
                    if ($table_check->num_rows == 0) {
                        error_log('ERROR: password_resets table does not exist!');
                        $error = 'System configuration error. Please contact support.';
                    } else {
                        // Store reset token in database
                        $insert_stmt = $db->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = ?");
                        if ($insert_stmt) {
                            $insert_stmt->bind_param('sssss', $email, $token_hash, $expires, $token_hash, $expires);
                            if ($insert_stmt->execute()) {
                                error_log('Token stored for: ' . $email);
                                
                                // DEBUG: Log what we're about to do
                                error_log('About to call send_reset_email with: email=' . $email . ', name=' . $employee['first_name']);
                                
                                // Send email with reset link
                                $email_result = send_reset_email($email, $employee['first_name'] . ' ' . $employee['last_name'], $reset_token);
                                error_log('Email result returned: ' . ($email_result ? 'TRUE' : 'FALSE'));
                                
                                if ($email_result) {
                                    error_log('Email sent successfully to: ' . $email);
                                    $success = "Password reset instructions have been sent to your email. Please check your inbox and follow the link.";
                                    
                                    // Update rate limit counter
                                    file_put_contents($rate_limit_file, (intval(file_get_contents($rate_limit_file)) + 1));
                                } else {
                                    error_log('Failed to send email to: ' . $email);
                                    $error = 'Failed to send email. Please try again later.';
                                }
                            } else {
                                error_log('Failed to store token: ' . $insert_stmt->error);
                                $error = 'Database error. Please try again later.';
                            }
                            $insert_stmt->close();
                        } else {
                            error_log('Failed to prepare insert statement: ' . $db->error);
                            $error = 'Database error. Please try again later.';
                        }
                    }
                } else {
                    error_log('Employee not found: ' . $email);
                    // For security, don't reveal if email exists or not
                    $success = "If an account exists with that email, you will receive password reset instructions.";
                }
                $stmt->close();
            } else {
                error_log('Failed to prepare select statement: ' . $db->error);
                $error = 'Database error. Please try again later.';
            }
        }
    }
}

// STEP 2: Reset password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step2_reset'])) {
    if (!hash_equals($forgotPasswordCsrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request token. Please refresh the page and try again.';
    }
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if ($error !== '') {
        // keep csrf token error
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*etc).';
    } else {
        // Verify token
        if (empty($reset_token)) {
            $error = 'Invalid or missing reset token.';
        } else {
            // Check if token is valid and not expired
            if (!isset($db) || $db->connect_error) {
                $error = 'Database connection error. Please try again later.';
            } else {
                $token_hash = hash('sha256', $reset_token);
                $check_stmt = $db->prepare("SELECT email FROM password_resets WHERE token_hash = ? AND expires_at > NOW()");
                if ($check_stmt) {
                    $check_stmt->bind_param('s', $token_hash);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $reset_record = $result->fetch_assoc();
                        $email = $reset_record['email'];
                        
                        // Update password in database
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $update_stmt = $db->prepare("UPDATE employees SET password = ? WHERE email = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param('ss', $hashed_password, $email);
                            if ($update_stmt->execute()) {
                                // Delete used reset token
                                $delete_stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                                if ($delete_stmt) {
                                    $delete_stmt->bind_param('s', $email);
                                    $delete_stmt->execute();
                                    $delete_stmt->close();
                                }
                                
                                $success = 'Password has been reset successfully! Redirecting to login...';
                                echo '<meta http-equiv="refresh" content="2;url=/admin/index.php">';
                            } else {
                                $error = 'Failed to update password. Please try again.';
                            }
                            $update_stmt->close();
                        } else {
                            $error = 'Database error. Please try again later.';
                        }
                    } else {
                        $error = 'Invalid or expired reset link. Please request a new one.';
                        $step = 1;
                    }
                    $check_stmt->close();
                } else {
                    $error = 'Database error. Please try again later.';
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
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    </head>

<body>

<header class="nav">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="../assets/images/icons/ipms-icon.png" class="icon-top">

        <h2 class="title">Reset Password</h2>
        <p class="subtitle">Recover your account access</p>

        <?php if (!empty($error)): ?>
        <div class="ac-eef834dd">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="ac-eee71138">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- STEP 1: Request Reset -->
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($forgotPasswordCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="employee@lgu.gov.ph" required>
                <span class="icon">ðŸ“§</span>
            </div>

            <button class="btn-primary" type="submit" name="step1_request">Send Reset Link</button>

            <div class="ac-4d4de932">
                <a href="/admin/index.php" class="ac-f72a71bf">Back to Login</a>
            </div>
        </form>

        <?php elseif ($step == 2): ?>
        <!-- STEP 2: Reset Password -->
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($forgotPasswordCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-box">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required>
                <span class="icon">ðŸ”’</span>
            </div>

            <div class="input-box">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required>
                <span class="icon">ðŸ”’</span>
            </div>

            <div class="ac-97825712">
                <strong>Password Requirements:</strong>
                <ul class="ac-cbf6525e">
                    <li>At least 8 characters long</li>
                    <li>At least one uppercase letter (A-Z)</li>
                    <li>At least one number (0-9)</li>
                    <li>At least one special character (!@#$%^&*)</li>
                </ul>
            </div>

            <button class="btn-primary" type="submit" name="step2_reset">Reset Password</button>

            <div class="ac-4d4de932">
                <a href="/admin/index.php" class="ac-f72a71bf">Back to Login</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>


    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>
</html>














