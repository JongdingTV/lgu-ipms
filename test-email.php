<?php
/**
 * Email Configuration Test
 * Use this to test email sending functionality
 */

// Load configuration
require_once __DIR__ . '/config/email.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';
require_once __DIR__ . '/vendor/PHPMailer/Exception.php';

echo "<h2>📧 Email Configuration Test</h2>";
echo "<pre>";

// Display configuration
echo "Configuration Settings:\n";
echo "========================\n";
echo "MAIL_HOST: " . MAIL_HOST . "\n";
echo "MAIL_PORT: " . MAIL_PORT . "\n";
echo "MAIL_USERNAME: " . MAIL_USERNAME . "\n";
echo "MAIL_FROM_EMAIL: " . MAIL_FROM_EMAIL . "\n";
echo "MAIL_FROM_NAME: " . MAIL_FROM_NAME . "\n\n";

// Test email sending
echo "Testing Email Send:\n";
echo "===================\n";

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Enable debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    
    // SMTP settings
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
    
    // Email details
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress('caviterawen5@gmail.com', 'Test User'); // Change this to a real email
    $mail->isHTML(true);
    $mail->Subject = 'Email Configuration Test - LGU IPMS';
    $mail->Body = '<h1>Test Email</h1><p>If you received this, email is working correctly!</p>';
    $mail->AltBody = 'Test email from LGU IPMS';
    
    // Try to send
    if ($mail->send()) {
        echo "✅ EMAIL SENT SUCCESSFULLY!\n";
        echo "Message sent to: test@example.com\n";
    } else {
        echo "❌ SEND FAILED\n";
        echo "Error: " . $mail->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION OCCURRED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Check for common issues
echo "<h3>Common Issues Checklist:</h3>";
echo "<ul>";
echo "<li>✓ Using Gmail SMTP: " . (MAIL_HOST === 'smtp.gmail.com' ? '✅ Yes' : '❌ No') . "</li>";
echo "<li>✓ Using App Password (not regular password): " . (strlen(MAIL_PASSWORD) > 10 ? '✅ Yes (looks correct)' : '❌ Probably not') . "</li>";
echo "<li>✓ From email matches Gmail account: " . (MAIL_FROM_EMAIL === MAIL_USERNAME ? '✅ Yes' : '❌ No - GMAIL WILL REJECT THIS') . "</li>";
echo "<li>✓ SSL/TLS enabled: ✅ Yes (ENCRYPTION_STARTTLS)</li>";
echo "<li>✓ Timeout set to 30s: ✅ Yes</li>";
echo "</ul>";

echo "<p><strong>⚠️ IMPORTANT:</strong> Update 'test@example.com' in this script to your actual test email address before running.</p>";
?>
