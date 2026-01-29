<?php
/**
 * Email Configuration Test
 * Test if email sending is working properly
 */

// Correct paths for public_html structure
$base_path = __DIR__;
$config_path = $base_path . '/config/email.php';

if (!file_exists($config_path)) {
    die('Error: Could not find email.php configuration file at: ' . htmlspecialchars($config_path));
}

require_once $config_path;

// Try to load database
$db_path = $base_path . '/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
}

$test_email = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($test_email)) {
    if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Test email sending
        try {
            require_once dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Enable debug output for testing
            $mail->SMTPDebug = 4;  // More detailed output
            $debug_log = [];
            
            // Create a custom debug callback
            $mail->Debugoutput = function($str, $level) use (&$debug_log) {
                $debug_log[] = htmlspecialchars($str);
            };
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            $mail->Timeout = 10;
            
            // Email content
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($test_email);
            $mail->isHTML(true);
            $mail->Subject = '[TEST] LGU IPMS - Email Configuration Test';
            $mail->Body = '<html><body><h2>Email Test Successful!</h2><p>This is a test email to verify your SMTP configuration is working correctly.</p></body></html>';
            
            // Send email
            if ($mail->send()) {
                $result = '✅ Email sent successfully to ' . htmlspecialchars($test_email) . '!';
            } else {
                $error = '❌ Failed to send email: ' . $mail->ErrorInfo;
            }
            
            $debug_output = implode('<br>', $debug_log);
            
        } catch (Exception $e) {
            $error = '❌ Email Error: ' . $e->getMessage();
            $debug_output = ob_get_clean();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .config-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .label-title {
            font-weight: 600;
            color: #1e3a5f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;"><i class="fas fa-envelope"></i> Email Configuration Test</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($result)): ?>
                    <div class="alert alert-success"><?php echo $result; ?></div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <h5>Current Configuration:</h5>
                <div class="config-box">
                    <div><span class="label-title">SMTP Host:</span> <?php echo MAIL_HOST; ?></div>
                    <div><span class="label-title">SMTP Port:</span> <?php echo MAIL_PORT; ?></div>
                    <div><span class="label-title">Username:</span> <?php echo MAIL_USERNAME; ?></div>
                    <div><span class="label-title">From Email:</span> <?php echo MAIL_FROM_EMAIL; ?></div>
                    <div><span class="label-title">From Name:</span> <?php echo MAIL_FROM_NAME; ?></div>
                </div>

                <h5 style="margin-top: 2rem;">Send Test Email:</h5>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Recipient Email Address</label>
                        <input type="email" class="form-control" name="test_email" placeholder="your@email.com" required>
                        <small class="text-muted">We'll send a test email to this address</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Test Email</button>
                </form>

                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd;">
                    <h5>Troubleshooting:</h5>
                    <ul style="font-size: 0.95rem;">
                        <li><strong>Gmail App Password:</strong> Make sure you're using a 16-character app password from Google Account settings, not your regular password</li>
                        <li><strong>Gmail 2FA:</strong> You must have 2-Factor Authentication enabled on the Gmail account</li>
                        <li><strong>Less Secure Apps:</strong> Gmail blocks less secure app access. Use App Passwords instead.</li>
                        <li><strong>Firewall/ISP:</strong> Some ISPs block port 587. Try port 465 with SSL instead</li>
                        <li><strong>Check spam folder:</strong> Test emails might go to spam</li>
                    </ul>
                </div>

                <?php if (!empty($debug_output)): ?>
                    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd;">
                        <h5>Debug Output:</h5>
                        <pre style="background: #f8f9fa; padding: 1rem; border-radius: 6px; max-height: 300px; overflow-y: auto; font-size: 0.85rem;"><?php echo htmlspecialchars($debug_output); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="/admin/index.php" class="btn btn-outline-light">Back to Admin Login</a>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
