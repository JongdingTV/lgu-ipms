<?php
/**
 * Email Configuration
 * Settings for sending verification codes via email
 */

// Email service settings
define('MAIL_HOST', 'smtp.gmail.com');           // SMTP server
define('MAIL_PORT', 587);                         // SMTP port (587 for TLS)
define('MAIL_USERNAME', 'ipms.systemlgu@gmail.com'); // Your email address
define('MAIL_PASSWORD', 'cmym dhjr alby dbgq');    // Gmail App Password (NOT your regular password)
define('MAIL_FROM_EMAIL', 'ipms.systemlgu@gmail.com'); // Must match Gmail account
define('MAIL_FROM_NAME', 'LGU IPMS System');

/**
 * Send verification code via email
 * @param string $recipient_email The email address to send to
 * @param string $code The 8-digit verification code
 * @param string $recipient_name The recipient's name
 * @return bool True if email sent successfully, false otherwise
 */
function send_verification_code($recipient_email, $code, $recipient_name = '') {
    try {
        // Load PHPMailer
        require_once dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;                           // Disable debug output
        $mail->isSMTP();                                // Use SMTP
        $mail->Host = MAIL_HOST;                        // SMTP server
        $mail->SMTPAuth = true;                         // Enable SMTP authentication
        $mail->Username = MAIL_USERNAME;                // SMTP username
        $mail->Password = MAIL_PASSWORD;                // SMTP password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
        $mail->Port = MAIL_PORT;                        // SMTP port
        $mail->Timeout = 30;                            // Increased timeout
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Email content
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your LGU IPMS Verification Code';
        
        // Email body
        $body = "
        <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; }
                    .container { max-width: 500px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #1e3a5f; margin: 0; }
                    .code-box { background: #f0f8ff; border: 2px solid #f39c12; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
                    .code { font-size: 32px; font-weight: bold; color: #f39c12; letter-spacing: 2px; font-family: 'Courier New', monospace; }
                    .warning { color: #c3423f; font-size: 14px; margin-top: 15px; }
                    .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; border-top: 1px solid #e0e0e0; padding-top: 15px; }
                </style>
            </head>
            <body>
                <div class=\"container\">
                    <div class=\"header\">
                        <h1>🔐 LGU IPMS Verification</h1>
                    </div>
                    
                    <p>Hello " . htmlspecialchars($recipient_name) . ",</p>
                    
                    <p>You requested a verification code to access the LGU IPMS admin panel. Your code is:</p>
                    
                    <div class=\"code-box\">
                        <div class=\"code\">$code</div>
                    </div>
                    
                    <p><strong>How to use this code:</strong></p>
                    <ol>
                        <li>Go to the admin login page</li>
                        <li>Enter your verification code</li>
                        <li>Click \"Verify Code\"</li>
                    </ol>
                    
                    <div class=\"warning\">
                        <strong>⚠️ Security Notice:</strong><br>
                        • This code expires in 10 minutes<br>
                        • Never share this code with anyone<br>
                        • If you didn't request this, ignore this email
                    </div>
                    
                    <div class=\"footer\">
                        <p>© " . date('Y') . " LGU IPMS System. All rights reserved.</p>
                    </div>
                </div>
            </body>
        </html>
        ";
        
        $mail->Body = $body;
        
        // Send email
        if ($mail->send()) {
            return true;
        } else {
            error_log('Email send failed: ' . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log('Email exception: ' . $e->getMessage());
        return false;
    }
}
?>
