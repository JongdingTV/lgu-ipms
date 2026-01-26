# Admin Access Flow - Complete System

## 🔐 Authentication Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN ACCESS FLOW                         │
└─────────────────────────────────────────────────────────────┘

START
  │
  ├─→ User clicks "Admin Login" button
  │   URL: https://ipms.infragovservices.com/public/admin-login.php
  │
  ├─→ STEP 1: 2FA Verification - ID + Password
  │   File: /public/admin-login.php (Step 1)
  │   ├─ Check database for employee
  │   ├─ Verify password
  │   └─ Store temp session: admin_temp_id, admin_temp_email, admin_temp_name
  │
  ├─→ STEP 2: Request Verification Code
  │   File: /public/admin-login.php (Step 2)
  │   ├─ Generate 8-digit code
  │   ├─ Send email with code (via PHPMailer + Gmail)
  │   ├─ Store in session: admin_temp_code (expires 10 min)
  │   └─ Display code entry form
  │
  ├─→ STEP 3: Enter Verification Code
  │   File: /public/admin-login.php (Step 3)
  │   ├─ Verify code matches
  │   ├─ Check if code expired
  │   ├─ Limit attempts (max 5)
  │   └─ On success:
  │       ├─ Set $_SESSION['admin_verified'] = true
  │       ├─ Set $_SESSION['verified_employee_id']
  │       ├─ Clean up temp variables
  │       └─ Redirect to /admin/index.php
  │
  ├─→ STEP 4: Full Employee Login
  │   File: /admin/index.php
  │   ├─ Check: Is admin_verified set?
  │   ├─ Yes → Show employee login form
  │   ├─ Form: Email + Password
  │   ├─ Verify credentials against employees table
  │   └─ On success:
  │       ├─ Set $_SESSION['employee_id']
  │       ├─ Set $_SESSION['employee_name']
  │       ├─ Set $_SESSION['user_type'] = 'employee'
  │       └─ Redirect to dashboard
  │
  └─→ ACCESS GRANTED ✅
      User can now:
      ├─ View admin dashboard
      ├─ Manage employees (/admin/manage-employees.php)
      └─ Access other admin features
```

---

## 📍 File Locations

| Step | File | Purpose |
|------|------|---------|
| 1-3 | `/public/admin-login.php` | 2FA Verification (ID + Password + Email Code) |
| 4 | `/admin/index.php` | Full Employee Login (Email + Password) |
| N/A | `/admin/manage-employees.php` | Employee Management Interface (after full login) |
| N/A | `/admin/dashboard/dashboard.php` | Admin Dashboard (after full login) |

---

## 🔧 Configuration Files

| File | Purpose |
|------|---------|
| `/config/email.php` | Email settings & sending function |
| `/database.php` | Database connection |
| `/session-auth.php` | Session management |

---

## 🎯 Session Variables Used

### During 2FA (temporary)
```php
$_SESSION['admin_temp_id']        // Employee ID being verified
$_SESSION['admin_temp_email']     // Employee email
$_SESSION['admin_temp_name']      // Employee name
$_SESSION['admin_temp_code']      // 8-digit verification code
$_SESSION['admin_code_time']      // Code generation timestamp
$_SESSION['admin_code_attempts']  // Failed code entry attempts
```

### After 2FA (persistent until full login)
```php
$_SESSION['admin_verified']       // true = passed 2FA
$_SESSION['verified_employee_id'] // Employee ID who passed 2FA
```

### After Full Login (persistent)
```php
$_SESSION['employee_id']          // Logged-in employee ID
$_SESSION['employee_name']        // Logged-in employee name
$_SESSION['user_type']            // 'employee' or 'admin'
```

---

## ✅ Success Scenarios

### Scenario 1: New Admin Access
```
1. Click "Admin Login"
2. Enter ID: 1, Password: admin123
3. Click "Login"
4. Receive email with code
5. Enter code
6. See employee login form
7. Enter email & password
8. Access admin panel
```

### Scenario 2: New Employee Added Access
```
Employee ID: 2
Employee Email: john@lgu.gov.ph
Employee Password: (set during account creation)

1. Go to admin-login.php
2. Enter ID: 2, Password: (as set)
3. Complete 2FA
4. Enter email: john@lgu.gov.ph
5. Enter password
6. Access employee dashboard
```

---

## ⚠️ Error Handling

| Error | Cause | Solution |
|-------|-------|----------|
| "Employee not found" | ID doesn't exist | Check employee ID in database |
| "Invalid password" | Wrong password in 2FA | Verify password or reset account |
| "Code expired" | Didn't enter code within 10 min | Request new code |
| "Invalid code" | Wrong code entered | Check email for correct code |
| "Too many attempts" | 5+ failed code attempts | Click "Start Over" |
| 404 on redirect | File path incorrect | Check /admin/index.php exists |

---

## 🔐 Security Features

✅ **2FA Verification**
- Employee ID + Password required
- Email code verification
- Code expires after 10 minutes
- Maximum 5 code entry attempts

✅ **Session Management**
- HTTPOnly cookies
- Session regeneration
- Timeout protection
- SameSite=Strict

✅ **Password Security**
- Bcrypt password hashing
- Minimum 6 characters
- Salted and hashed storage

✅ **Email Verification**
- Actual email codes (via Gmail)
- Demo fallback for testing
- Secure SMTP connection (TLS)

---

## 📧 Email Configuration

The system uses **Gmail App Passwords** for sending codes:

1. Requires 2FA on Gmail account
2. Generate 16-character app password
3. Store in `/config/email.php`
4. Sends HTML-formatted emails
5. Demo code shown if email fails

**See:** EMAIL_SETUP.md for detailed configuration

---

## 🚀 Deployment Checklist

- [ ] Update `/config/email.php` with Gmail credentials
- [ ] Upload `/public/admin-login.php`
- [ ] Verify `/admin/index.php` redirects correctly
- [ ] Test 2FA flow end-to-end
- [ ] Confirm employee login works
- [ ] Delete old `/public/admin-verify.php` (if not needed)
- [ ] Test with new employee account
- [ ] Document credentials for admins

---

## 📞 Troubleshooting

### Step 1: Cannot login to 2FA
- Check employee ID in database
- Verify password is correct
- Confirm employee record exists

### Step 2: Email not received
- Check `/config/email.php` settings
- Verify Gmail app password is correct
- Check email spam folder
- Look at server error logs

### Step 3: Code doesn't verify
- Enter exact code from email
- Check code hasn't expired (10 min)
- Verify you're using correct code
- Try requesting new code if expired

### Step 4: Cannot login with full credentials
- Use email address (not ID)
- Check password is correct
- Confirm account has access permission
- Clear browser cache/cookies

---

**Last Updated:** January 26, 2026
**System Version:** 2.0 (Simplified 2FA)
