# Security Policy & Implementation Guide

## Overview
This document outlines all security measures implemented in the LGU Infrastructure Project Management System to protect citizen data and maintain system integrity.

## 1. Authentication & Access Control

### Citizen-Only Access
- Only registered citizens can access the citizen portal
- Admin/employee login links are hidden from public pages
- No employee access buttons visible on homepage
- Citizen login is the primary call-to-action

### Admin Portal Protection
- Admin access moved to hidden URL: `/admin-portal.php`
- Requires valid employee authentication
- All access attempts logged for security auditing
- Unauthorized access attempts redirected to homepage

## 2. Search Engine Visibility

### Robots.txt Configuration
- `robots.txt` file blocks all search engine bots from indexing
- Specific bot exclusions: Google, Bing, DuckDuckBot, Yahoo
- Prevents domain discovery through search engines
- Crawl delay set to 10 seconds

### Meta Tags
- `noindex, nofollow` meta tags on all sensitive pages
- Prevents accidental indexing by search engines
- Applied to admin, user dashboard, and API pages

## 3. HTTP Security Headers

### Implemented Headers
- **X-Content-Type-Options: nosniff** - Prevents MIME type sniffing attacks
- **X-Frame-Options: SAMEORIGIN** - Prevents clickjacking attacks
- **X-XSS-Protection: 1; mode=block** - Enables XSS protection
- **Strict-Transport-Security** - Forces HTTPS for 1 year
- **Permissions-Policy** - Disables camera, microphone, geolocation
- **Content-Security-Policy** - Whitelist for scripts and resources
- **Referrer-Policy: strict-origin-when-cross-origin** - Controls referrer info

### Header Removal
- Removes Server header to hide Apache/PHP version
- Removes X-Powered-By header
- Prevents version disclosure attacks

## 4. File & Directory Security

### .htaccess Rules
- Blocks direct access to sensitive files (.env, .git, config files)
- Prevents directory listing with `Options -Indexes`
- Restricts access to `/admin`, `/api`, `/app` directories
- Hides file extensions to obscure system structure

### Protected Directories
- `/admin` - Requires authentication
- `/api` - API endpoints protected
- `/app` - Application code protected
- `/includes` - Core functionality hidden
- `/config` - Configuration files hidden

## 5. Session Security

### Session Configuration
- HTTPOnly cookies prevent JavaScript access
- Secure flag forces HTTPS transmission
- SameSite=Strict prevents CSRF attacks
- Strict session mode prevents fixation attacks

### Session Timeout
- Configurable timeout in config files
- Automatic logout after inactivity
- User data cleared on logout

## 6. Input Validation & Sanitization

### Data Validation
- All user inputs validated before processing
- Email validation for email fields
- Integer validation for numeric fields
- URL sanitization for links
- XSS prevention through HTML escaping

### Functions Available
- `sanitize_input()` - Sanitizes various input types
- `check_rate_limit()` - Prevents brute force attacks
- Input type-specific validation

## 7. Rate Limiting

### Implementation
- 100 requests per hour per IP (configurable)
- Prevents brute force login attempts
- Protects against DDoS attacks
- Graceful handling of limit exceed

## 8. Security Logging

### Logged Events
- Admin portal access attempts
- Failed authentication attempts
- Unauthorized access attempts
- Rate limit violations
- Sensitive data access

### Log Location
- Logs stored in `/storage/logs/security.log`
- JSON formatted for easy parsing
- Includes timestamp, IP, user agent, event details

## 9. SQL Injection Prevention

### Prepared Statements
- All database queries use parameterized statements
- Prevents SQL injection attacks
- Connection using mysqli with proper escaping

### Database Credentials
- Credentials stored in secure config files
- Not exposed in version control
- Changed regularly for security

## 10. HTTPS/TLS

### Implementation
- HSTS header forces HTTPS for one year
- Secure cookie flag enables on HTTPS
- All sensitive operations require HTTPS

### Certificate Management
- Valid SSL/TLS certificate required
- Certificate renewal before expiration
- Mixed content blocking

## 11. Password Security

### Requirements
- Strong password requirements for all users
- Password hashing using PHP's `password_hash()`
- Salted passwords (automatic with password_hash)
- Password change forced after login

### Two-Factor Authentication
- OTP (One-Time Password) via email
- 10-minute OTP validity
- Automatic OTP expiration
- Device remembering for 10 days

## 12. Admin Access Protection

### Hidden Access Point
- Admin portal URL: `/admin-portal.php`
- Not linked from public pages
- Not indexed by search engines
- Requires valid employee login

### Access Controls
- Employee role verification required
- Unauthorized access logged and blocked
- Redirected to homepage on failure

## 13. Regular Security Updates

### Maintenance Tasks
- Monitor for PHP/library security updates
- Regular password changes for admin accounts
- Security header review and updates
- Log file rotation and archival

## 14. Data Protection

### Privacy Measures
- No sensitive data in URLs
- Encrypted connections for data transmission
- Secure logout clearing all session data
- Data retention policies enforced

### Compliance
- GDPR compliance for user data
- Data protection impact assessments
- Privacy policy enforcement
- User consent collection

## 15. Monitoring & Alerting

### Security Monitoring
- Failed login attempt tracking
- Suspicious activity alerts
- Rate limit breach notifications
- Unusual access pattern detection

## 16. Deployment Security

### Production Checklist
- Debug mode disabled
- Error display disabled (logged instead)
- Strong session secrets configured
- Database credentials secured
- HTTPS certificate installed
- Firewall rules configured

## 17. Incident Response

### Breach Protocol
1. Immediately disable affected accounts
2. Log all activities for forensics
3. Notify affected users
4. Reset passwords and secrets
5. Review and patch vulnerabilities
6. Monitor for further incidents

## 18. User Responsibilities

### Citizens Should
- Keep password confidential
- Use strong passwords
- Logout after using public computers
- Report suspicious activities
- Update browser regularly
- Don't share OTP codes

## File Locations

- **Security Functions**: `/includes/security.php`
- **Authentication**: `/includes/auth.php`
- **Configuration**: `/config/app.php`
- **Security Logs**: `/storage/logs/security.log`
- **Hidden Admin Access**: `/admin-portal.php`
- **Robots Config**: `/robots.txt`
- **HTTP Rules**: `/.htaccess`

## Testing Security

### Regular Tests
- Penetration testing
- SQL injection tests
- XSS vulnerability scans
- CSRF token validation
- Session hijacking prevention
- Password strength validation

## Support & Questions

For security concerns or to report vulnerabilities:
- Contact the LGU IT Department
- Use secure communication channels
- Do not post security issues publicly
- Allow reasonable time for fixes

---
Last Updated: January 2026
Version: 1.0.0
