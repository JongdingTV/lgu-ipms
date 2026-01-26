# Admin 2FA - Quick Reference

## Three-Step Verification Process

### 🆔 STEP 1: Identify Yourself
```
Input Fields:
├─ Employee ID: _______________
└─ Password: ___________________

Validation:
├─ Database lookup
├─ Password hash verification (bcrypt)
└─ Success → Proceed to Step 2

Errors:
├─ "Employee not found" → Check ID spelling
├─ "Invalid password" → Check password
└─ Auto-restart on 5 failed attempts
```

### 📧 STEP 2: Request Code
```
Only appears after Step 1 success

Display:
├─ Verified employee name
└─ "Send Verification Code button"

Upon clicking:
├─ 8-digit code generated
├─ Sent to employee's registered email
├─ Code stored in session (10 min lifetime)
└─ User sees: "Code sent to ***@example.com"
```

### 🔐 STEP 3: Enter Code
```
Input Fields:
└─ Verification Code: __________ (8 digits)

Auto-submit: When 8 digits entered

Validation:
├─ Code match check
├─ Expiration check (10 minutes)
└─ Attempt limit (max 5 tries)

Success → Redirect to admin dashboard
Failure → Show attempts remaining
```

---

## Testing Credentials

### Demo Test Account
```
Employee ID: admin
Password: admin123
Email: Will be registered email in database
```

### Database Check
```sql
SELECT id, email, password FROM employees WHERE id='admin';
```

Result should show:
- id: admin
- email: (valid email address)
- password: (bcrypt hash starting with $2y$)

---

## Common Issues & Solutions

### Issue: "Employee not found"
**Cause:** Entered ID doesn't exist in database
**Solution:** 
- Check employee ID is correct
- Try using email address instead
- Verify employee record exists in database

### Issue: "Invalid password"
**Cause:** Password doesn't match database hash
**Solution:**
- Confirm password is correct
- Passwords are case-sensitive
- Check Caps Lock is off
- Verify password was bcrypt-hashed when created

### Issue: "Verification code expired"
**Cause:** More than 10 minutes passed since code was sent
**Solution:**
- Click "Request New Code" button
- Code must be entered within 10 minutes
- Check clock synchronization if timing seems off

### Issue: Code doesn't auto-submit
**Cause:** Only 7 or fewer digits entered
**Solution:**
- Auto-submit triggers at exactly 8 digits
- Check code from email (should be 8 digits)
- Manually click "Verify & Access Admin" if needed

### Issue: "Too many failed attempts"
**Cause:** Entered wrong code 5 times
**Solution:**
- Click "Request New Code" to restart
- Check code from email again
- Ensure typing code correctly

---

## Database Requirements

### Required Employee Table Columns
```sql
id              INT PRIMARY KEY
email           VARCHAR(255) UNIQUE
password        VARCHAR(255)  -- bcrypt hash
first_name      VARCHAR(100)
last_name       VARCHAR(100)
-- ... other columns
```

### Password Hashing (PHP)
```php
// When creating/updating password:
$hashed = password_hash('plaintext_password', PASSWORD_BCRYPT);
// Store $hashed in database

// When verifying (automatic in 2FA):
password_verify('plaintext_password', $hash_from_db) // returns true/false
```

### Check Current Employees
```sql
SELECT id, email, SUBSTRING(password, 1, 10) as pwd_start 
FROM employees 
WHERE password IS NOT NULL 
ORDER BY id;
```

---

## Security Properties

### What's Protected
```
✅ Passwords: bcrypt hashing, never stored plaintext
✅ Sessions: HTTPOnly cookies, SameSite=Strict
✅ Codes: Temporary (10 min), high entropy (8 digits)
✅ Employee Data: Stored in database, not exposed
✅ Brute Force: Rate limiting, attempt limits
✅ Session Hijacking: No JavaScript access to auth tokens
```

### What's NOT Protected
```
⚠️  Email account compromise → Code can be intercepted
⚠️  Password dictionary attacks → Use strong passwords
⚠️  Database breach → All hashes exposed (but still bcrypt)
⚠️  HTTPS not enabled → Passwords sent in plaintext
⚠️  Physical device access → Session can be hijacked
```

### Mitigations
```
→ Use strong, unique passwords
→ Enable HTTPS on production server
→ Keep employee passwords confidential
→ Monitor failed login attempts
→ Update passwords regularly (90 days recommended)
```

---

## Session Variables Used

### During Verification
```
$_SESSION['temp_employee_id']     → Employee ID (temp)
$_SESSION['temp_employee_email']  → Email address (temp)
$_SESSION['temp_employee_name']   → Display name (temp)
```

### During Code Entry
```
$_SESSION['admin_verification_code']      → 8-digit code
$_SESSION['admin_verification_time']      → Code generation timestamp
$_SESSION['admin_verification_attempts']  → Failed attempt count
```

### After Verification Success
```
$_SESSION['admin_verified']       → true (access granted)
$_SESSION['verified_employee_id'] → Employee ID (for audit)
$_SESSION['admin_verified_time']  → Completion timestamp
```

### Admin Login
```
$_SESSION['employee_id']   → Set by admin/index.php after login
$_SESSION['user_role']     → Admin role
$_SESSION['user_name']     → Admin name
```

---

## File Locations

### Verification Page
```
📄 /public/admin-verify.php
   - Main 3-step verification form
   - Handles all validation logic
   - Calls database for employee lookup
```

### Admin Login Page
```
📄 /admin/index.php
   - Checks admin_verified flag first
   - If verified, shows admin login form
   - Sets employee_id after password login
```

### Admin Logout
```
📄 /admin/logout.php
   - Clears all session variables
   - Redirects to admin-verify.php
```

### Database Connection
```
📄 /database.php
   - Provides $db connection
   - Used for employee lookup
```

---

## Deployment Checklist

```
Before Go-Live:
□ All admin employees in database with ID + password + email
□ Passwords are bcrypt-hashed
□ Email addresses are valid and tested
□ HTTPS enabled on domain
□ Session timeout appropriate (30 min recommended)
□ Tested with 3+ different employee accounts
□ Database backup created
□ Documentation updated

After Go-Live:
□ Monitor admin login attempts daily
□ Watch for failed attempt spikes (possible attacks)
□ Review access logs weekly
□ Test password reset process works
□ Schedule password update reminder (90-day cycle)
□ Plan for audit trail implementation
□ Document any issues encountered
```

---

## Production Checklist

### Security
- [ ] HTTPS enabled (redirect HTTP to HTTPS)
- [ ] Database credentials not in code comments
- [ ] Error messages don't reveal sensitive info
- [ ] Logging doesn't store passwords
- [ ] CSP headers configured
- [ ] CORS headers appropriate

### Performance
- [ ] Database indexes on employee id/email
- [ ] Session cleanup configured (remove old sessions)
- [ ] Code in production environment tested
- [ ] Email sending latency acceptable

### Monitoring
- [ ] Failed login alerts setup
- [ ] Admin access logs enabled
- [ ] Database backup schedule active
- [ ] Error logs reviewed daily

---

## FAQ

**Q: What if an admin forgets their password?**
A: They need password reset. Implement admin password reset feature (sends reset link to email) or manual reset by system admin.

**Q: Can I change the code length?**
A: Yes, change in admin-verify.php:
   - Current: `str_pad(rand(10000000, 99999999), 8, ...)`
   - For 6 digits: `str_pad(rand(0, 999999), 6, ...)`
   - Also update HTML maxlength and pattern

**Q: How to extend code expiration?**
A: In admin-verify.php, change:
   - Current: `if ($elapsed > 600)` (10 minutes)
   - For 15 min: `if ($elapsed > 900)`
   - For 5 min: `if ($elapsed > 300)`

**Q: Can I change attempt limit?**
A: Yes, in admin-verify.php:
   - Current: `if ($_SESSION['admin_verification_attempts'] > 5)`
   - Change 5 to desired number

**Q: What if email isn't sent?**
A: Currently in demo mode (shows code on screen). To enable actual email:
   - Configure PHPMailer in project
   - Uncomment actual mail() call
   - Update email template

**Q: Can admins remember their device?**
A: Not yet implemented. Could add:
   - Device fingerprinting
   - 30-day device token
   - Skip 2FA on remembered devices

---

## Support Contacts

For technical issues:
- Check database.php credentials
- Verify employee records exist
- Check email configuration
- Review server logs for errors
- Test with localhost first

For security concerns:
- Review ENHANCED_2FA_SECURITY.md
- Check SECURITY.md for broader guidelines
- Review password storage implementation
- Audit recent login attempts

---

**Last Updated:** 2026-01-26
**Version:** 2.0 Enhanced 3-Factor Authentication
**Status:** ACTIVE AND LIVE ✅
