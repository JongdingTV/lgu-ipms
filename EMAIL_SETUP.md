# Email Configuration Setup Guide

## How to Set Up Email Verification Codes

The LGU IPMS system now sends verification codes via email. Follow these steps to configure it:

---

## Option 1: Using Gmail (Recommended for Quick Setup)

### Step 1: Create a Gmail Account
Create a dedicated Gmail account for the system (e.g., `ipms-system@gmail.com`)

### Step 2: Enable 2-Step Verification on Gmail
1. Go to: https://myaccount.google.com
2. Click **Security** (left menu)
3. Enable **2-Step Verification**

### Step 3: Create an App Password
1. Go back to **Security** settings
2. Find **App passwords** (appears only after 2FA is enabled)
3. Select: **Mail** and **Windows Computer**
4. Google will generate a 16-character password
5. **Copy this password** (you'll need it in the next step)

### Step 4: Configure the Email File
Edit: **`config/email.php`**

Replace these lines with your information:
```php
define('MAIL_USERNAME', 'ipms-system@gmail.com');  // Your Gmail address
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // 16-char app password
define('MAIL_FROM_EMAIL', 'noreply@lgu.gov.ph');
define('MAIL_FROM_NAME', 'LGU IPMS Admin');
```

**Example:**
```php
define('MAIL_USERNAME', 'ipms-system@gmail.com');
define('MAIL_PASSWORD', 'abcd efgh ijkl mnop');  // App password from Google
```

### Step 5: Upload to CyberPanel
Upload the updated `config/email.php` file to CyberPanel

### Step 6: Test
1. Go to: https://ipms.infragovservices.com/public/admin-login.php
2. Login with ID: `1`, Password: `admin123`
3. Click "Send Code to Email"
4. Check the email inbox for the verification code
5. Enter the code and complete login

---

## Option 2: Using Your Own SMTP Server

If you have your own mail server, edit `config/email.php`:

```php
define('MAIL_HOST', 'your-smtp-server.com');
define('MAIL_PORT', 587);                         // or 465 for SSL
define('MAIL_USERNAME', 'your-email@domain.com');
define('MAIL_PASSWORD', 'your-password');
define('MAIL_FROM_EMAIL', 'noreply@lgu.gov.ph');
define('MAIL_FROM_NAME', 'LGU IPMS Admin');
```

---

## Option 3: Using SendGrid, Mailgun, etc.

Contact your email service provider for SMTP details and update `config/email.php` accordingly.

---

## Troubleshooting

### "Code sent to email. Demo: 12345678"
This message means the email failed to send, but the code is shown for testing. Check:
1. **Email credentials are correct** in `config/email.php`
2. **The email account has 2FA enabled** (for Gmail)
3. **An App Password was created** (not the regular Gmail password)
4. **Check server error logs** for email errors

### Test Email Sending
Create a test file to verify email configuration works:

**File: `test-email.php`**
```php
<?php
require_once 'config/email.php';

$result = send_verification_code(
    'your-test-email@gmail.com',
    '12345678',
    'Test User'
);

if ($result) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Email failed to send. Check server error logs.";
}
?>
```

Visit: `https://ipms.infragovservices.com/test-email.php`

---

## Security Notes

⚠️ **Important:**
- **Never commit `config/email.php` to version control** if it contains real credentials
- **Use App Passwords, not your regular password** (for Gmail)
- **Keep SMTP credentials secure** on the server
- **Delete test files** like `test-email.php` after testing

---

## Email Template Customization

To customize the verification email appearance, edit `config/email.php` and modify the `$body` variable in the `send_verification_code()` function.

Change:
- Logo/branding
- Email subject line
- Message text
- Colors and styling
- Any other HTML content

---

## Support

If you need help:
1. Check the error logs on CyberPanel
2. Verify email credentials are correct
3. Test with a simple Gmail account first
4. Contact your email service provider for SMTP details
