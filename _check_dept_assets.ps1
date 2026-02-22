$files = @(
  '/assets/images/icons/ipms-icon2.png',
  '../assets/images/icons/ipms-icon.png',
  '../assets/images/admin/list.png',
  '../assets/css/shared/admin-auth.css',
  '../assets/css/design-system.css',
  '../assets/css/components.css',
  '../assets/css/admin.css',
  '../assets/css/admin-unified.css',
  '../assets/css/admin-component-overrides.css',
  '../assets/css/admin-enterprise.css',
  '../assets/js/admin.js',
  '../assets/js/admin-enterprise.js',
  '/assets/js/shared/security-no-back.js',
  'department-head.css',
  'department-head.js',
  'login.css',
  'login-security.js'
)
$base = 'C:\xampp\htdocs\lgu-ipms\department-head'
foreach ($f in $files) {
  $path = if ($f.StartsWith('/')) { 'C:\xampp\htdocs\lgu-ipms' + $f.Replace('/','\\') } else { Join-Path $base $f }
  if (Test-Path $path) { "OK  $f" } else { "MISS $f -> $path" }
}
