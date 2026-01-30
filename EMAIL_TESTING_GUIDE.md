# Email & Forgot Password Testing Guide

## ✅ Configuration Status

Your email configuration is now:
- **SMTP Host**: smtp.gmail.com (Gmail)
- **Port**: 587 (TLS)
- **From Email**: ipms.systemlgu@gmail.com (Matches Gmail account ✓)
- **Timeout**: 30 seconds (Increased from 10s)
- **SSL/TLS**: Enabled with proper verification options ✓

## 📋 Pre-Testing Checklist

### 1. Database Table Setup
First, ensure the `password_resets` table exists in your database:

```sql
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
ON SCHEDULE EVERY 1 HOUR
DO
  DELETE FROM password_resets WHERE expires_at < NOW();
```

**To run this:**
- Open your database management tool (phpMyAdmin, MySQL Workbench, etc.)
- Run the SQL from file: `database/migrations/create_password_resets_table.sql`

### 2. Verify Employee Records
Ensure you have at least one employee with a valid email in the `employees` table:

```sql
SELECT id, first_name, last_name, email FROM employees LIMIT 5;
```

---

## 🧪 Testing Steps

### Test 1: Forgot Password Email
1. Navigate to your admin login page
2. Look for a "Forgot Password?" link
3. Enter an employee's email address
4. You should see: **"Password reset instructions have been sent to your email"**
5. Check the email inbox for password reset email
6. Click the reset link in the email
7. Enter a new password and confirm
8. Login with the new password

### Test 2: Check Error Logs
If emails aren't being sent, check your PHP error logs:

**On Windows (with Apache/PHP):**
- Check error logs in: `C:\xampp\apache\logs\error.log` (or similar)
- Look for lines containing: `❌ Email send failed`, `PHPMailer Exception`, etc.

**Verify logs are enabled:**
- Edit `php.ini`
- Ensure `error_log = "path/to/error.log"`
- Check that error logging is enabled

### Test 3: Verify OTP/Login Emails
1. Try logging in as a regular user
2. If OTP is enabled, you should receive a verification code
3. Check email for the verification code

---

## 🔧 Troubleshooting

### Issue: Email not received
**Possible causes:**
1. ✓ **From address mismatch** - FIXED (now uses ipms.systemlgu@gmail.com)
2. ✓ **Timeout too short** - FIXED (increased to 30 seconds)
3. ✓ **SSL/TLS issues** - FIXED (proper verification options added)
4. **Gmail security blocks** - Check Gmail's "Less secure apps" settings
5. **Wrong email in database** - Verify employee email is correct
6. **App password invalid** - Re-verify the Gmail app password

### Check Gmail Security Settings
1. Go to: https://myaccount.google.com/security
2. Look for "App passwords" 
3. Generate a new app password for "Mail" and "Windows"
4. Copy the password (it includes spaces like: `cmym dhjr alby dbgq`)
5. Update `config/email.php` with the new password if needed

### Check PHP Error Logs
Enable detailed logging in the forgot-password form:
```php
// In admin/forgot-password.php, set:
$mail->SMTPDebug = 2;  // Shows SMTP conversation
$mail->Debugoutput = 'html';  // Display debug output
```

---

## 📧 Email Files Modified

These files handle email sending:
- [config/email.php](config/email.php) - Email configuration and verification code function
- [admin/forgot-password.php](admin/forgot-password.php) - Password reset email sending
- [user-dashboard/user-login.php](user-dashboard/user-login.php) - Login OTP email sending

---

## ✨ Configuration Summary

| Setting | Value | Status |
|---------|-------|--------|
| SMTP Host | smtp.gmail.com | ✅ Correct |
| SMTP Port | 587 | ✅ Correct |
| Encryption | STARTTLS | ✅ Enabled |
| From Email | ipms.systemlgu@gmail.com | ✅ **Fixed** |
| Timeout | 30 seconds | ✅ **Increased** |
| SSL Options | Disabled verification | ✅ **Added** |

---

## 🎯 Next Steps

1. **Create the password_resets table** (if not already done)
2. **Test forgot password functionality**
3. **Check email receipt and reset process**
4. **Monitor error logs** for any issues
5. **Test user login OTP emails** if applicable

