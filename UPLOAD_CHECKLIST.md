<?php
/**
 * Upload Checklist
 * Files that MUST be uploaded to CyberPanel
 */
?>
# FILES TO UPLOAD TO CYBERPANEL

## CRITICAL - Must Upload Now
- [ ] /admin/index.php
- [ ] /public/admin-login.php
- [ ] /admin/manage-employees.php
- [ ] /config/email.php
- [ ] /.htaccess (root)
- [ ] /admin/.htaccess

## ALREADY UPLOADED (Verify)
- [ ] /public/index.php
- [ ] /database.php
- [ ] /session-auth.php

## DOCUMENTATION (Upload for reference)
- [ ] /ADMIN_GUIDE.md
- [ ] /EMAIL_SETUP.md
- [ ] /ADMIN_FLOW.md
- [ ] /ADMIN_SYSTEM_COMPLETE.md

## Upload Instructions

1. Connect to CyberPanel File Manager
2. Navigate to /home/username/public_html/ipms.infragovservices.com/
3. Upload each file from the list above
4. For .htaccess files, you may need to:
   - Show hidden files in File Manager
   - Or use terminal to upload

## Verify After Upload

Test these URLs:
- https://ipms.infragovservices.com/public/admin-login.php (should work)
- https://ipms.infragovservices.com/admin/index.php (should work)
- https://ipms.infragovservices.com/test-access.php (should show all ✅)
