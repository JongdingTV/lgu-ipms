# LGU IPMS - Implementation Guide & Best Practices

## Complete File Path Reference

### Public Entry Points
```
/public/index.php                  → Homepage/portal entry point
```

### Application Pages
```
/app/auth/login.php                → Admin/User login (unified)
/app/auth/register.php             → User registration
/app/auth/logout.php               → Logout handler

/app/admin/dashboard.php           → Admin dashboard home
/app/admin/projects/index.php      → Project management
/app/admin/budget/index.php        → Budget and resources
/app/admin/contractors/index.php   → Contractor management
/app/admin/progress/index.php      → Progress monitoring
/app/admin/tasks/index.php         → Tasks and milestones
/app/admin/priorities/index.php    → Project prioritization
/app/admin/reports/index.php       → Reports

/app/user/dashboard.php            → User dashboard
/app/user/feedback.php             → Feedback submission
/app/user/progress-monitoring.php  → User progress view
/app/user/settings.php             → User settings
/app/user/create-account.php       → Account creation
```

### API Endpoints
```
/api/projects/list.php             → GET projects
/api/projects/create.php           → POST new project
/api/projects/update.php           → PUT update project
/api/projects/delete.php           → DELETE project

/api/contractors/list.php          → GET contractors
/api/contractors/create.php        → POST new contractor
/api/contractors/delete.php        → DELETE contractor

/api/feedback/list.php             → GET feedback
/api/feedback/create.php           → POST new feedback
/api/feedback/update.php           → PUT update feedback status

/api/tasks/list.php                → GET tasks
/api/tasks/create.php              → POST new task
/api/tasks/update.php              → PUT update task

/api/common/response.php           → Response formatter
/api/common/validator.php          → Input validator
```

### Shared Includes
```
/includes/config.php               → App configuration
/includes/database.php             → Database connection
/includes/auth.php                 → Authentication functions
/includes/security.php             → Security functions
/includes/session-manager.php      → Session handling
/includes/header.php               → Header component
/includes/footer.php               → Footer component
/includes/sidebar.php              → Sidebar navigation
/includes/navbar.php               → Top navigation
/includes/helpers.php              → Utility functions
```

### Assets
```
/assets/css/main.css               → Core styles
/assets/css/admin.css              → Admin panel styles
/assets/css/user.css               → User pages styles
/assets/css/responsive.css         → Mobile responsive
/assets/css/themes.css             → Theme variations

/assets/js/main.js                 → Core functionality
/assets/js/admin.js                → Admin-specific JS
/assets/js/user.js                 → User-specific JS
/assets/js/security.js             → Security features
/assets/js/validation.js           → Form validation
/assets/js/utils.js                → Utility functions

/assets/images/logo.png            → Main logo
/assets/images/icons/dashboard.png  → Dashboard icon
/assets/images/icons/projects.png   → Projects icon
/assets/images/icons/contractors.png → Contractors icon
/assets/images/icons/budget.png    → Budget icon
/assets/images/icons/progress.png  → Progress icon
/assets/images/icons/tasks.png     → Tasks icon
/assets/images/icons/priorities.png → Priorities icon
/assets/images/icons/user.png      → User icon
/assets/images/icons/settings.png  → Settings icon
/assets/images/icons/logout.png    → Logout icon

/assets/images/gallery/road.jpg    → Gallery image
/assets/images/gallery/construction.jpg → Gallery image
/assets/images/gallery/drainage.jpg → Gallery image
/assets/images/gallery/bridge.jpg  → Gallery image
```

---

## How to Use Includes - Examples

### Example 1: Basic Page with Header/Footer
```php
<?php
// Set up root path
define('ROOT', dirname(__DIR__, 2) . '/');
require_once ROOT . 'includes/config.php';
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';

// Check authentication
check_auth();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Page</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <?php include ROOT . 'includes/navbar.php'; ?>
    <div class="container">
        <?php include ROOT . 'includes/sidebar.php'; ?>
        <main class="content">
            <!-- Page content here -->
        </main>
    </div>
    <?php include ROOT . 'includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
```

### Example 2: API Endpoint
```php
<?php
define('ROOT', dirname(__DIR__, 3) . '/');
require_once ROOT . 'includes/config.php';
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'api/common/response.php';
require_once ROOT . 'api/common/validator.php';

// Check authentication
check_auth();

// Validate input
$data = json_decode(file_get_contents('php://input'), true);
if (!validate_required_fields($data, ['name', 'email'])) {
    send_error('Missing required fields', 400);
}

// Process request
try {
    // Your logic here
    send_success(['message' => 'Success']);
} catch (Exception $e) {
    send_error($e->getMessage(), 500);
}
?>
```

### Example 3: Shared Function in helpers.php
```php
<?php
// In /includes/helpers.php
function get_project_stats() {
    global $db;
    return [
        'total' => $db->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'],
        'approved' => $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Approved'")->fetch_assoc()['count'],
        'completed' => $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count']
    ];
}

// Usage in any page:
require ROOT . 'includes/helpers.php';
$stats = get_project_stats();
echo "Total: " . $stats['total'];
?>
```

---

## CSS Path Examples

### Root-Relative Paths (Recommended)
```html
<!-- From any page, these work the same -->
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="icon" type="image/png" href="/assets/images/logo.png">
```

### In CSS (for background images)
```css
/* In /assets/css/main.css */
.hero {
    background-image: url('/assets/images/gallery/road.jpg');
}

.icon-dashboard {
    background-image: url('/assets/images/icons/dashboard.png');
}
```

---

## JavaScript Include Examples

### Script Tags
```html
<!-- From any page -->
<script src="/assets/js/main.js"></script>
<script src="/assets/js/validation.js"></script>
<script src="/assets/js/security.js"></script>
```

### Using APP_ROOT Variable (if needed)
```javascript
// In /assets/js/main.js
const API_BASE = '/api';

function fetchProjects() {
    return fetch(API_BASE + '/projects/list.php')
        .then(response => response.json());
}
```

---

## Form Submission Examples

### Form Action
```html
<!-- From any page, these work -->
<form action="/api/projects/create.php" method="POST">
    <input type="text" name="name" required>
    <button type="submit">Create</button>
</form>

<!-- Or using JavaScript -->
<form id="projectForm">
    <input type="text" name="name" required>
    <button type="submit">Create</button>
</form>

<script>
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch('/api/projects/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.fromEntries(new FormData(this)))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Project created!');
        } else {
            alert('Error: ' + data.message);
        }
    });
});
</script>
```

---

## Environment-Specific Configuration

### /config/app.php Example
```php
<?php
// Determine environment
$env = getenv('APP_ENV') ?? 'development';

define('APP_ENV', $env);
define('APP_URL', getenv('APP_URL') ?? 'http://localhost:8000');
define('DEBUG_MODE', APP_ENV === 'development');

// Database
define('DB_HOST', getenv('DB_HOST') ?? 'localhost');
define('DB_USER', getenv('DB_USER') ?? 'ipms_root');
define('DB_PASS', getenv('DB_PASS') ?? 'G3P+JANpr2GK6fax');
define('DB_NAME', getenv('DB_NAME') ?? 'ipms_lgu');

// Session
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTP_ONLY', true);

// Paths (auto-calculated)
define('ROOT_PATH', dirname(__DIR__) . '/');
define('APP_PATH', ROOT_PATH . 'app/');
define('API_PATH', ROOT_PATH . 'api/');
define('ASSETS_PATH', '/assets');
define('STORAGE_PATH', ROOT_PATH . 'storage/');
?>
```

### .env Example
```
APP_ENV=development
APP_URL=http://localhost:8000
DB_HOST=localhost
DB_USER=ipms_root
DB_PASS=G3P+JANpr2GK6fax
DB_NAME=ipms_lgu
```

### Load .env in config.php
```php
<?php
// Load environment variables from .env
if (file_exists(dirname(__DIR__) . '/.env')) {
    $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}
?>
```

---

## Database Function Examples

### /includes/database.php
```php
<?php
define('ROOT', dirname(__DIR__) . '/');
require_once ROOT . 'config/app.php';

// Create connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    if (DEBUG_MODE) {
        die('Database connection failed: ' . $db->connect_error);
    } else {
        die('Database connection failed. Please try again later.');
    }
}

$db->set_charset("utf8mb4");
?>
```

---

## Common PHP Patterns

### Pattern 1: Protect Page with Auth
```php
<?php
define('ROOT', dirname(__DIR__) . '/');
require_once ROOT . 'includes/config.php';
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';

check_auth(); // Redirect to login if not authenticated
?>
```

### Pattern 2: Check User Role
```php
<?php
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function require_role($role) {
    if ($_SESSION['role'] !== $role) {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied');
    }
}

// Usage
require_role('admin'); // Page only for admins
?>
```

### Pattern 3: API Response Handler
```php
<?php
// /api/common/response.php
function send_success($data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Usage
if ($condition) {
    send_error('Invalid input', 400);
} else {
    send_success(['id' => $id, 'message' => 'Created successfully'], 201);
}
?>
```

### Pattern 4: Form Validation
```php
<?php
// /api/common/validator.php
function validate_required_fields($data, $fields) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return false;
        }
    }
    return true;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    return preg_match('/^\+?[0-9\s-]{7,15}$/', $phone);
}

// Usage
if (!validate_email($email)) {
    send_error('Invalid email format', 400);
}
?>
```

---

## Navigation Links

### In HTML (Root-Relative)
```html
<!-- These work from any page -->
<a href="/public/index.php">Home</a>
<a href="/app/admin/dashboard.php">Admin Dashboard</a>
<a href="/app/user/dashboard.php">User Dashboard</a>
<a href="/app/auth/logout.php">Logout</a>
```

### In PHP (Using constants)
```php
<?php
define('SITE_URL', 'http://localhost:8000');

echo '<a href="' . SITE_URL . '/app/admin/dashboard.php">Admin Dashboard</a>';
?>
```

---

## Asset Versioning for Cache-Busting

### Static Version
```html
<link rel="stylesheet" href="/assets/css/main.css?v=1.0.0">
<script src="/assets/js/main.js?v=1.0.0"></script>
```

### Dynamic Version (using file modification time)
```php
<?php
function version_asset($path) {
    $file = dirname(__DIR__) . '/public' . $path;
    if (file_exists($file)) {
        return $path . '?v=' . filemtime($file);
    }
    return $path;
}
?>

<!-- Usage -->
<link rel="stylesheet" href="<?php echo version_asset('/assets/css/main.css'); ?>">
```

---

## Directory Permissions (Linux/Mac)

```bash
# Make upload directories writable
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/cache/

# Protect config and database files (readable only)
chmod 644 config/database.php
chmod 644 .env
```

---

## Security Best Practices

1. **Never include sensitive data in commits**
   ```bash
   echo ".env" >> .gitignore
   echo "storage/uploads/" >> .gitignore
   ```

2. **Validate all inputs**
   ```php
   $input = filter_var($_GET['id'], FILTER_VALIDATE_INT);
   if (!$input) die('Invalid input');
   ```

3. **Use prepared statements**
   ```php
   $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
   $stmt->bind_param('s', $email);
   ```

4. **Sanitize output**
   ```php
   echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
   ```

5. **Set secure headers**
   ```php
   header('X-Content-Type-Options: nosniff');
   header('X-Frame-Options: DENY');
   header('X-XSS-Protection: 1; mode=block');
   ```

---

## Testing Checklist After Restructuring

- [ ] Homepage loads (public/index.php)
- [ ] Admin login works
- [ ] User registration works
- [ ] Admin dashboard loads with correct stats
- [ ] All admin modules load (projects, budget, contractors, etc.)
- [ ] User dashboard loads
- [ ] Navigation menus work across all pages
- [ ] All CSS styles apply correctly
- [ ] All images load correctly
- [ ] JavaScript functionality works (forms, validation, etc.)
- [ ] API endpoints respond correctly
- [ ] Database queries work
- [ ] Session management works (login/logout)
- [ ] Mobile responsive design works
- [ ] No 404 errors in console

---

## Troubleshooting Common Issues

### Issue: "File not found" or 404 errors
**Solution**: Check relative paths. Use `/assets/...` instead of `assets/...`

### Issue: CSS/Images not loading
**Solution**: Verify `.htaccess` allows access to assets folder

### Issue: PHP includes not found
**Solution**: Make sure `define('ROOT', ...)` is correct for that file's location

### Issue: Database connection errors
**Solution**: Verify credentials in `config/database.php` match your setup

### Issue: Session not working
**Solution**: Ensure `includes/session-manager.php` is included before accessing `$_SESSION`

---

## Deployment Checklist

1. Upload all files to server
2. Set up database (run `database/backups/lgu_ipms.sql`)
3. Configure `.env` with production values
4. Set proper permissions on `storage/` and `config/` directories
5. Enable HTTPS and update `SESSION_SECURE` to true
6. Run tests to verify all functionality
7. Set up error logging
8. Remove debug files
9. Implement monitoring and backups

