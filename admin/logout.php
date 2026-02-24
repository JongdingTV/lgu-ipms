<?php
// Admin logout - redirects to admin login page
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

// Log the logout event
log_security_event('ADMIN_LOGOUT', 'Admin user successfully logged out');

// Destroy session
destroy_session();

// Add strict no-cache headers
set_no_cache_headers();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true);
header('Pragma: no-cache', true);
header('Expires: 0', true);
header('Clear-Site-Data: "cache"', false);

// Use replace redirect so this logout page is not kept in history stack.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0, private">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Signing out...</title>
</head>
<body>
<script>
  try {
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '/admin/index.php?logout=1');
    }
  } catch (e) {}
  window.location.replace('/admin/index.php?logout=1');
</script>
<noscript>
  <meta http-equiv="refresh" content="0;url=/admin/index.php?logout=1">
</noscript>
</body>
</html>
<?php
exit;
?>







