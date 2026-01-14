# LGU IPMS Security Implementation Guide

## Overview
This document outlines the comprehensive security measures implemented to protect the LGU IPMS application.

## Security Features Implemented

### 1. **Session Authentication & Authorization**
**File:** `session-auth.php`

#### Features:
- **Session Timeout (30 minutes)**: Automatically logs out users after 30 minutes of inactivity
- **Automatic Redirects**: Unauthorized users are redirected to login page
- **User-Agent Validation**: Detects and prevents session hijacking by monitoring User-Agent consistency
- **Suspicious Activity Detection**: Logs unusual activity patterns

**Usage:**
```php
require 'session-auth.php';
check_auth();  // Verify user is logged in
```

---

### 2. **No-Cache Headers (Prevent Back Button Access)**
When a user logs out, the browser cannot use the back button to access protected pages.

**Mechanism:**
```php
set_no_cache_headers();
```

This function adds HTTP headers that prevent browser caching:
- `Cache-Control: no-store, no-cache, must-revalidate`
- `Pragma: no-cache`
- `Expires: (past date)`

**Result:** After logout, pressing back button forces reload from server, which redirects to login.

---

### 3. **Rate Limiting & Brute Force Protection**
Prevents attackers from attempting unlimited login tries.

**Configuration:**
- **Max Attempts:** 5 failed attempts allowed
- **Time Window:** 300 seconds (5 minutes)
- **Triggers:** Automatic lockout for 5 minutes after 5 failed attempts

**How It Works:**
```php
// Checked in login.php
if (is_rate_limited('login', 5, 300)) {
    die('Too many attempts. Try again in 5 minutes.');
}

// Record attempt on failure
record_attempt('login');
```

---

### 4. **Security Audit Logging**
All security-relevant events are logged to database for auditing.

**Logged Events:**
- User logins/logouts
- Failed login attempts
- Rate limit violations
- Session timeout events
- Suspicious activity detection
- IP addresses and timestamps

**Access Log:**
```sql
SELECT * FROM security_logs WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY);
```

---

### 5. **CSRF Token Protection**
Prevents Cross-Site Request Forgery attacks on forms.

**Usage in Forms:**
```php
// Generate token
$token = generate_csrf_token();
// Output in form
<input type="hidden" name="csrf_token" value="<?php echo $token; ?>">

// Verify on form submission
if (!verify_csrf_token($_POST['csrf_token'])) {
    die('CSRF token invalid');
}
```

---

### 6. **Password Security**
Uses bcrypt hashing (modern standard) for passwords.

**Functions:**
```php
// Hash password during registration
$hashed = hash_password($user_password);

// Verify during login
if (verify_password($user_password, $hashed)) {
    // Password matches
}
```

**Benefits:**
- Resistant to rainbow table attacks
- Adaptive cost factor (automatically hardens over time)
- Salt automatically handled

---

### 7. **Input Validation & Sanitization**
Protects against XSS and SQL injection attacks.

**Functions:**
```php
// Sanitize email
$email = sanitize_email($_POST['email']);

// Sanitize general strings
$name = sanitize_string($_POST['name']);
```

**Protection:**
- HTML entity encoding
- Quote escaping
- Prepared statements for database queries

---

### 8. **Secure Session Configuration**
**Features:**
- **HTTP Only:** JavaScript cannot access session cookies
- **Secure Flag:** Cookies only sent over HTTPS in production
- **SameSite:** Prevents CSRF by limiting cross-site cookie access
- **Strict Mode:** Rejects invalid session IDs

---

### 9. **Protected Pages**
All sensitive pages now require authentication. If a user tries to access without logging in:

**Pages Protected:**
- Dashboard: `/dashboard/dashboard.php`
- Progress Monitoring: `/progress-monitoring/progress_monitoring.php`
- Project Registration: `/project-registration/project_registration.php`
- Contractors: `/contractors/contractors.php`
- Budget & Resources: `/budget-resources/budget_resources.php`
- Task & Milestone: `/task-milestone/tasks_milestones.php`
- Project Prioritization: `/project-prioritization/project-prioritization.php`
- User Dashboard: `/user-dashboard/user-dashboard.php`
- All user-related pages

---

### 10. **Logout Security**
When user logs out:
1. Session completely destroyed
2. Session cookie deleted
3. All user variables cleared
4. User redirected to login page
5. Event logged for audit trail

**Implementation:**
```php
destroy_session();  // In logout.php
```

---

## Configuration & Customization

### Session Timeout
Edit `session-auth.php` line 11:
```php
define('SESSION_TIMEOUT', 30 * 60); // Change 30 to desired minutes
```

### Rate Limiting Attempts
Edit `login.php` - adjust parameters:
```php
is_rate_limited('login', 5, 300)  // 5 attempts in 300 seconds
```

### Password Hashing Cost
Edit `session-auth.php` line 75:
```php
return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]); // Higher = more secure but slower
```

---

## Testing Security Features

### Test 1: Back Button Prevention
1. Login to application
2. Logout
3. Press browser back button
4. **Expected Result:** Redirected to login page (cannot access protected page)

### Test 2: Session Timeout
1. Login
2. Wait 31 minutes without activity
3. Try to access any page
4. **Expected Result:** Redirected to login with session expired message

### Test 3: Rate Limiting
1. Go to login page
2. Enter wrong password 5 times in 5 minutes
3. Try 6th attempt
4. **Expected Result:** "Too many attempts" message

### Test 4: Brute Force Log
```sql
SELECT * FROM security_logs WHERE event_type = 'RATE_LIMIT_EXCEEDED';
```

---

## Security Best Practices Going Forward

### 1. **Enable HTTPS**
- Update `session-auth.php` line 10 to `1` in production:
```php
ini_set('session.cookie_secure', 1);  // Only over HTTPS
```

### 2. **Change Secret Keys**
Edit `login.php` line 5:
```php
define('REMEMBER_DEVICE_SECRET', 'YOUR_RANDOM_SECRET_KEY');
```

### 3. **Regular Security Audits**
Review security logs regularly:
```sql
SELECT * FROM security_logs 
WHERE event_type IN ('FAILED_LOGIN', 'RATE_LIMIT_EXCEEDED')
ORDER BY timestamp DESC LIMIT 100;
```

### 4. **Database Backups**
Include `security_logs` and `rate_limiting` tables in backups.

### 5. **Keep Dependencies Updated**
- PHPMailer is already latest version
- Monitor for PHP security updates
- Keep MySQL/MariaDB updated

---

## Troubleshooting

### Issue: Users keep getting logged out
**Solution:** Increase SESSION_TIMEOUT in session-auth.php

### Issue: "Too many login attempts" appears immediately
**Solution:** Clear `rate_limiting` table:
```sql
DELETE FROM rate_limiting WHERE ip_address = 'USER_IP';
```

### Issue: Can't use back button to go to previously viewed pages
**Solution:** This is intentional security feature. Use navigation menu instead.

---

## Summary of Security Additions

| Feature | Location | Status |
|---------|----------|--------|
| Session Authentication | session-auth.php | ✅ Active |
| Rate Limiting | login.php + session-auth.php | ✅ Active |
| No-Cache Headers | All protected pages | ✅ Active |
| Session Timeout | session-auth.php | ✅ 30 min idle |
| Audit Logging | session-auth.php | ✅ Active |
| Input Sanitization | session-auth.php | ✅ Active |
| Password Hashing | session-auth.php | ✅ Bcrypt |
| CSRF Protection | session-auth.php | ✅ Available |
| User-Agent Validation | session-auth.php | ✅ Active |
| Suspicious Activity | session-auth.php | ✅ Monitoring |

---

## Database Tables Created Automatically

### security_logs
Tracks all security events and authentication attempts.

### rate_limiting
Tracks login attempts for brute force detection.

Both tables are created automatically on first use. No manual setup required.

---

**Last Updated:** January 2026
**System Version:** LGU IPMS v1.0 with Security Features
