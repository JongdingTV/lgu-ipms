<?php
// Simple test to see if send_reset_email function works
session_start();
require dirname(__FILE__) . '/config/email.php';

function send_reset_email($email, $employee_name, $reset_token) {
    try {
        error_log('=== EMAIL FUNCTION CALLED ===');
        
        require_once dirname(__FILE__) . '/vendor/PHPMailer/PHPMailer.php';
        require_once dirname(__FILE__) . '/vendor/PHPMailer/SMTP.php';
        require_once dirname(__FILE__) . '/vendor/PHPMailer/Exception.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->SMTPDebug = 2;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 10;
        
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $employee_name);
        $mail->isHTML(true);
        $mail->Subject = '[TEST] Password Reset - LGU IPMS';
        $mail->Body = '<h2>Test Email</h2><p>If you see this, the forgot password function is working!</p>';
        
        if ($mail->send()) {
            error_log('✅ TEST EMAIL SENT SUCCESSFULLY');
            return true;
        } else {
            error_log('❌ TEST EMAIL FAILED: ' . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log('❌ TEST EMAIL EXCEPTION: ' . $e->getMessage());
        return false;
    }
}

$test_email = 'admin@lgu.gov.ph';
$result = send_reset_email($test_email, 'Test User', 'test123456');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password Function Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 2rem; }
        .container { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Forgot Password Function Test</h3>
            </div>
            <div class="card-body">
                <p><strong>Function called:</strong> ✅ YES</p>
                <p><strong>Result:</strong> <?php echo $result ? '✅ Email sent (check your inbox!)' : '❌ Email failed (check error logs)'; ?></p>
                
                <div style="margin-top: 2rem; padding: 1rem; background: #f0f0f0; border-radius: 6px;">
                    <p><strong>What to check next:</strong></p>
                    <ul>
                        <li>Check your email inbox and spam folder</li>
                        <li>Check your cPanel error logs for details</li>
                        <li>Make sure password_resets table exists in database</li>
                    </ul>
                </div>

                <a href="/admin/index.php" class="btn btn-primary mt-3">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
