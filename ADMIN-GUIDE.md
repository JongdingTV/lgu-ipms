# Administrator's Guide - LGU IPMS

## ⚠️ CONFIDENTIAL - FOR AUTHORIZED PERSONNEL ONLY

This document is strictly confidential and intended only for authorized administrators and employees of the LGU. Unauthorized access, sharing, or distribution of this document is prohibited.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Admin Access](#admin-access)
3. [User Management](#user-management)
4. [Security Monitoring](#security-monitoring)
5. [Troubleshooting](#troubleshooting)
6. [Emergency Procedures](#emergency-procedures)
7. [Contact Information](#contact-information)

## System Overview

### System Components
- **Public Frontend**: `/public/index.php` - Citizen homepage
- **Citizen Portal**: `/user-dashboard/` - Citizen access
- **Admin Dashboard**: `/admin/` - Employee/Admin access
- **API Layer**: `/api/` - Backend services
- **Database**: `ipms_lgu` - MySQL database

### Architecture
```
Citizens → Public Portal → Citizen Dashboard
Employees → Hidden Admin Portal → Admin Dashboard → API → Database
```

### Key Technologies
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, HTML5, CSS3, JavaScript
- **Security**: HTTPS/TLS, Session-based auth, OTP

## Admin Access

### Accessing Admin Panel

#### Method 1: Hidden Portal (Recommended)
```
URL: /admin-portal.php
Method: GET or POST
Access: Employee login required
Redirect: /admin/index.php if authenticated
```

#### Method 2: Direct Access
```
URL: /admin/index.php
Method: Direct access if authenticated
Protection: .htaccess blocks unauthenticated access
```

### Admin Credentials

**Default Admin Account**
- Email: admin@lgu.local
- Role: Super Admin
- Access Level: Full system access

⚠️ **IMPORTANT**: Change default password immediately upon first login!

### First Login Steps

1. Access `/admin-portal.php`
2. Login with default credentials
3. Navigate to Settings → Change Password
4. Create strong password (12+ chars, mixed case, numbers, symbols)
5. Logout and login with new password
6. Review security logs

### Sessions & Security

- **Session Timeout**: 30 minutes of inactivity
- **Concurrent Sessions**: 1 per user (logout previous)
- **Password Reset**: Available in Settings
- **2FA**: OTP via email (if enabled)

## User Management

### User Types

#### 1. Citizens
- **Access**: Public portal + citizen dashboard
- **Permissions**: View projects, leave feedback, report issues
- **Data Access**: Only publicly available data
- **Features**: Progress tracking, feedback, community engagement

#### 2. Employees
- **Access**: Hidden admin portal
- **Permissions**: Manage projects, view reports, moderate content
- **Data Access**: Employee-level data
- **Features**: Project management, budget tracking, user management

#### 3. Super Admins
- **Access**: Full admin portal
- **Permissions**: All system access
- **Data Access**: Complete system database
- **Features**: User management, system settings, security audit

### Managing Users

#### Adding New Employee
1. Login to admin panel
2. Go to Users → Employees
3. Click "Add New Employee"
4. Fill in details:
   - Name
   - Email
   - Department
   - Role (Employee/Manager/Admin)
5. Click "Send Invite"
6. Employee receives email with login link

#### Managing Permissions
1. Go to Users → Roles
2. Select role to edit
3. Check/uncheck permissions
4. Click "Save"
5. Changes apply immediately

#### Resetting User Password
1. Go to Users → List
2. Find user
3. Click "Reset Password"
4. Send temporary link to email
5. User can login and set new password

## Security Monitoring

### Security Logs

**Location**: `/storage/logs/security.log`

**Log Contents**:
```json
{
  "timestamp": "2026-01-26 14:30:45",
  "event_type": "failed_login",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "details": {"user_id": 5, "reason": "wrong_password"}
}
```

### Reviewing Logs

#### View Real-Time Logs
```bash
tail -f /storage/logs/security.log
```

#### Search for Specific Events
```bash
grep "failed_login" /storage/logs/security.log
grep "admin_portal_access" /storage/logs/security.log
grep "unauthorized" /storage/logs/security.log
```

#### View Today's Events
```bash
grep "$(date +%Y-%m-%d)" /storage/logs/security.log
```

### Security Alerts

#### High Priority Alerts
1. Repeated failed login attempts (> 5 in 15 min)
2. Unauthorized access attempts
3. Admin area access from unusual IP
4. Rapid rate-limit violations
5. Database connection errors

#### Medium Priority Alerts
1. Failed OTP attempts (> 3)
2. Admin password change
3. User role changes
4. API rate limiting engaged

#### Low Priority Alerts
1. Successful logins
2. Logout events
3. Feature access
4. Page views

### Monitoring Dashboard

Access monitoring via:
1. Admin Panel → Security → Logs
2. View by date range
3. Filter by event type
4. Export logs as CSV

## Troubleshooting

### Common Issues

#### Issue: User Can't Login
**Symptoms**: Login page shows error after entering credentials

**Solution**:
1. Verify email address is correct
2. Check user account is active (not disabled)
3. Reset password via admin panel
4. Clear browser cookies/cache
5. Try different browser
6. Check security logs for error details

#### Issue: 403 Forbidden on Admin Pages
**Symptoms**: Getting 403 error when accessing `/admin/`

**Solution**:
1. Verify you're logged in (check session cookie)
2. Check user role is "employee" or higher
3. Verify `.htaccess` is correctly configured
4. Check Apache `mod_rewrite` is enabled
5. Review security logs for blocked requests

#### Issue: OTP Not Received
**Symptoms**: Login email doesn't arrive

**Solution**:
1. Check email configuration in settings
2. Verify SMTP credentials are correct
3. Check email spam folder
4. Verify user email address is correct
5. Check server logs: `tail -f /var/log/mail.log`
6. Resend OTP and wait 30 seconds

#### Issue: Session Timeout Too Frequent
**Symptoms**: Getting logged out suddenly

**Solution**:
1. Check session timeout setting
2. Verify server time is correct
3. Check for clock skew issues
4. Increase timeout in `/config/app.php`:
   ```php
   define('SESSION_TIMEOUT', 3600); // 1 hour
   ```
5. Restart PHP/Apache

#### Issue: Slow Performance
**Symptoms**: Admin pages loading slowly

**Solution**:
1. Check database connection speed
2. Review slow query logs
3. Optimize database indexes
4. Check server CPU/memory usage
5. Clear cache: `php artisan cache:clear`
6. Check for large log files
7. Archive old logs

### Debug Mode

#### Enable Debug Logging
In `/config/app.php`:
```php
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
```

#### View Debug Output
```bash
tail -f /storage/logs/debug.log
```

#### Disable Before Production
```php
define('DEBUG_MODE', false);
define('LOG_LEVEL', 'ERROR');
```

## Emergency Procedures

### Database Connection Lost
1. Check database server status
2. Verify connection credentials
3. Check network connectivity
4. Review database error logs
5. Restart MySQL service:
   ```bash
   service mysql restart
   ```

### Malicious Activity Detected
1. **IMMEDIATE**: Block attacker IP via firewall
2. Review last 1 hour of security logs
3. Check for data exfiltration
4. Review admin access logs
5. Change all admin passwords
6. Notify security team
7. File incident report

### System Compromise Suspected
1. **IMMEDIATE**: Take system offline
2. Preserve all logs (don't clear)
3. Take filesystem snapshot
4. Preserve memory dump
5. Contact security team
6. Do NOT restore from backup until verified clean
7. Investigate with external security firm

### Password Compromise
1. **IMMEDIATE**: Revoke old password
2. Change login credentials
3. Force all users to reset passwords
4. Review access logs for unauthorized use
5. Monitor for suspicious activity
6. Enable 2FA if not already enabled

### Ransomware/Malware Suspected
1. **IMMEDIATE**: Disconnect from network
2. Preserve logs and evidence
3. Contact security team
4. Contact law enforcement
5. Assess backups for infection
6. Plan recovery strategy
7. Document everything

## Contact Information

### Support Hierarchy

#### Tier 1 - Department Lead
- Name: _________________________
- Email: _________________________
- Phone: _________________________
- Available: Business hours

#### Tier 2 - IT Manager
- Name: _________________________
- Email: _________________________
- Phone: _________________________
- Available: Business hours + emergency

#### Tier 3 - Security Officer
- Name: _________________________
- Email: _________________________
- Phone: _________________________
- Available: 24/7 emergency hotline

#### Tier 4 - External Security Firm
- Company: _________________________
- Contact: _________________________
- Phone: _________________________
- Email: _________________________
- Contract: _________________________

### Escalation Procedures

**Level 1**: Try to resolve with available resources
**Level 2**: Contact Tier 1 support
**Level 3**: Escalate to Tier 2 (if unresolved in 1 hour)
**Level 4**: Escalate to Tier 3 (if unresolved in 4 hours)
**Level 5**: Engage external security firm

### Incident Reporting

**Report To**: [IT Manager]
**Method**: Email + phone call
**Information**: 
- What happened
- When it happened
- Impact assessment
- Actions taken
- System status

**Response Time SLA**:
- Acknowledgment: 15 minutes
- Triage: 1 hour
- Resolution plan: 4 hours

## Additional Resources

### Documentation Files
- `/SECURITY-IMPLEMENTATION.md` - Complete security guide
- `/SETUP_GUIDE.md` - System setup instructions
- `/SECURITY-CHECKLIST.md` - Security verification checklist
- `/BEST_PRACTICES.md` - Development best practices

### Log Locations
```
/storage/logs/security.log     - Security events
/storage/logs/debug.log        - Debug information
/storage/logs/errors.log       - System errors
/var/log/apache2/error.log     - Web server errors
/var/log/php.log               - PHP errors
/var/log/mysql/error.log       - Database errors
```

### Configuration Files
```
/config/app.php               - Application settings
/config/database.php          - Database connection
/.htaccess                    - Web server rules
/admin/.htaccess              - Admin protection rules
```

### Important Files
```
/robots.txt                   - Search engine rules
/admin-portal.php            - Hidden admin access
/public/index.php            - Public homepage
/includes/security.php       - Security functions
```

## Security Best Practices

### For Administrators
1. **Never share admin credentials** - Use individual accounts
2. **Change passwords regularly** - At least every 90 days
3. **Monitor logs daily** - Catch suspicious activity early
4. **Keep software updated** - Apply patches immediately
5. **Use strong passwords** - 12+ characters, mixed case, symbols
6. **Enable 2FA** - Additional layer of security
7. **Logout when done** - Don't leave admin panel open
8. **Use HTTPS always** - Never admin panel over HTTP
9. **Backup regularly** - Daily automated backups
10. **Document everything** - Keep audit trail of changes

### For System Security
1. **Keep PHP updated** - Use latest stable version
2. **Keep MySQL updated** - Security patches critical
3. **Keep Apache updated** - Latest security fixes
4. **Regular penetration testing** - External security audits
5. **Vulnerability scanning** - Use OWASP ZAP or Burp
6. **Code reviews** - Review all code changes
7. **Dependency updates** - Keep libraries current
8. **WAF rules** - Implement Web Application Firewall
9. **DDoS protection** - Monitor for attacks
10. **Rate limiting** - Protect against brute force

## Acknowledgment

I acknowledge that I have read and understood this administrator's guide and agree to:
- Maintain confidentiality of system information
- Follow all security procedures
- Report any suspicious activity immediately
- Keep passwords secure and confidential
- Use admin access only for authorized purposes
- Log all significant system changes

**Name**: _________________________
**Title**: _________________________
**Date**: _________________________
**Signature**: _________________________

---

**Document Version**: 1.0.0  
**Classification**: CONFIDENTIAL  
**Last Updated**: January 2026  
**Review Date**: [Next review date]

⚠️ This document contains sensitive information. Keep secure and do not share.
