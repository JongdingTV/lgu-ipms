# 🚀 Quick Start Guide - LGU IPMS Restructured

## Getting Started in 5 Minutes

### Step 1: Understand the Structure
The project is now organized as:
- **`public/`** - Public web entry
- **`app/`** - Application pages (admin & user)
- **`api/`** - API endpoints
- **`includes/`** - Reusable PHP components
- **`config/`** - Configuration files
- **`assets/`** - CSS, JS, images
- **`storage/`** - Uploads & cache
- **`database/`** - SQL files

### Step 2: Setup Configuration
1. **Copy your existing database.php to `config/database.php`**
   ```bash
   # Your credentials should be:
   # DB_HOST: localhost
   # DB_USER: ipms_root
   # DB_PASS: G3P+JANpr2GK6fax
   # DB_NAME: ipms_lgu
   ```

2. **Create `.env` file in root**
   ```
   APP_ENV=development
   APP_URL=http://localhost
   DB_HOST=localhost
   DB_USER=ipms_root
   DB_PASS=G3P+JANpr2GK6fax
   DB_NAME=ipms_lgu
   ```

### Step 3: Create Your First Page
Create `/app/admin/projects/index.php`:

```php
<?php
define('ROOT', dirname(__DIR__, 3) . '/');
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'includes/helpers.php';

check_auth();
require_role('admin');

// Get projects
$result = $db->query("SELECT * FROM projects LIMIT 10");
?>
<!DOCTYPE html>
<html>
<head>
    <?php include ROOT . 'includes/header.php'; ?>
    <title>Projects</title>
</head>
<body>
    <?php include ROOT . 'includes/navbar.php'; ?>
    <div style="display: flex;">
        <?php include ROOT . 'includes/sidebar.php'; ?>
        <main style="flex: 1; padding: 20px;">
            <h1>Projects</h1>
            <!-- Your content here -->
        </main>
    </div>
    <?php include ROOT . 'includes/footer.php'; ?>
</body>
</html>
```

### Step 4: Use Helper Functions
```php
<?php
// Paths
echo asset('css/main.css');          // /assets/css/main.css?v=123
echo image('logo.png');              // /assets/images/logo.png
echo image('dashboard.png', 'icons'); // /assets/images/icons/dashboard.png

// Formatting
echo format_currency(5000);    // ₱5,000.00
echo format_date('now');       // 2024-01-25 14:30:45
echo time_ago('2024-01-25');  // just now

// URLs
echo url('/app/admin/dashboard.php'); // http://localhost/app/admin/dashboard.php
?>
```

### Step 5: Create an API Endpoint
Create `/api/projects/list.php`:

```php
<?php
define('ROOT', dirname(__DIR__, 2) . '/');
require_once ROOT . 'config/app.php';
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'api/common/response.php';

check_method('GET');
check_auth();

$limit = (int)($_GET['limit'] ?? 10);
$projects = [];

$result = $db->query("SELECT id, name, location, status, budget FROM projects LIMIT $limit");
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

send_success($projects);
?>
```

Then fetch from JavaScript:
```javascript
fetch('/api/projects/list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(data.data); // Array of projects
        }
    });
```

---

## 📁 File Location Cheat Sheet

### CSS
```
/assets/css/main.css           # Core styles
/assets/css/responsive.css     # Mobile responsive
/assets/css/admin.css          # Admin-specific (create if needed)
/assets/css/user.css           # User-specific (create if needed)
```

### JavaScript
```
/assets/js/main.js             # Core functionality
/assets/js/admin.js            # Admin scripts (create if needed)
/assets/js/user.js             # User scripts (create if needed)
/assets/js/validation.js       # Form validation (create if needed)
```

### Images
```
/assets/images/logo.png
/assets/images/icons/dashboard.png
/assets/images/icons/projects.png
/assets/images/gallery/road.jpg
```

### PHP Includes
```
/includes/auth.php             # Authentication functions
/includes/database.php         # Database connection
/includes/helpers.php          # Utility functions
/includes/header.php           # Meta tags
/includes/navbar.php           # Top navigation
/includes/sidebar.php          # Left sidebar
/includes/footer.php           # Footer
```

### Configuration
```
/config/app.php                # Main config
/config/database.php           # DB credentials
/.env                          # Environment variables
```

### Pages
```
/app/admin/dashboard.php       # Admin dashboard
/app/admin/projects/           # Projects module
/app/admin/budget/             # Budget module
/app/user/dashboard.php        # User dashboard
/app/auth/login.php            # Login page
```

### API
```
/api/projects/list.php         # GET projects
/api/projects/create.php       # POST new project
/api/common/response.php       # API response handler
/api/common/validator.php      # Input validator
```

---

## 🔑 Key Functions Reference

### Authentication
```php
check_auth();              // Ensure user logged in
require_role('admin');     // Ensure admin role
is_authenticated();        // Check if logged in
get_user_id();            // Get current user ID
get_user_name();          // Get current user name
logout();                 // Logout user
```

### Database
```php
$db->query("SELECT...");   // Execute query
$result->fetch_assoc();    // Get single row
$result->fetch_all();      // Get all rows
execute_query($query, 'ss', $param1, $param2); // Prepared statement
```

### Helpers
```php
asset('css/main.css');                    // Get asset URL with version
image('logo.png');                        // Get image URL
image('dashboard.png', 'icons');          // Get icon URL
url('/app/admin/dashboard.php');          // Get absolute URL
escape($user_input);                      // Escape HTML
redirect('/app/admin/dashboard.php');     // Redirect
format_currency(5000);                    // Format as currency
format_date('now');                       // Format date
time_ago(time() - 3600);                 // Time ago string
```

### API Response
```php
send_success($data);            // Success with HTTP 200
send_created($data);            // Created with HTTP 201
send_error('message', 400);     // Error with HTTP 400
send_unauthorized();            // Unauthorized (401)
send_forbidden();               // Forbidden (403)
send_not_found();               // Not found (404)
```

### Validation
```php
validate_email($email);                      // Check email format
validate_phone($phone);                      // Check phone format
validate_password($password);                // Check password strength
validate_int($value);                        // Check if integer
validate_positive($value);                   // Check if positive
validate_required_fields($data, ['name', 'email']); // Check required fields
validate_data($data, $rules);               // Validate against rules
```

---

## 🎨 Common HTML Patterns

### Admin Page with Navbar + Sidebar
```html
<!DOCTYPE html>
<html>
<head>
    <?php include ROOT . 'includes/header.php'; ?>
    <title>Page Title</title>
</head>
<body>
    <?php include ROOT . 'includes/navbar.php'; ?>
    <div style="display: flex;">
        <?php include ROOT . 'includes/sidebar.php'; ?>
        <main style="flex: 1; padding: 20px;">
            <!-- Page content -->
        </main>
    </div>
    <?php include ROOT . 'includes/footer.php'; ?>
</body>
</html>
```

### User Page
```html
<!DOCTYPE html>
<html>
<head>
    <?php include ROOT . 'includes/header.php'; ?>
    <title>Page Title</title>
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/app/user/dashboard.php">Dashboard</a>
        <a href="/app/auth/logout.php">Logout</a>
    </nav>
    
    <main class="container">
        <!-- Page content -->
    </main>
    
    <?php include ROOT . 'includes/footer.php'; ?>
</body>
</html>
```

### Form with Validation
```html
<form id="projectForm" method="POST" action="/api/projects/create.php">
    <div class="form-group">
        <label for="name">Project Name</label>
        <input type="text" id="name" name="name" required>
    </div>
    
    <div class="form-group">
        <label for="budget">Budget</label>
        <input type="number" id="budget" name="budget" step="0.01" required>
    </div>
    
    <button type="submit" class="btn btn-primary">Create Project</button>
</form>

<script>
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = new FormData(this);
    const obj = Object.fromEntries(data);
    
    fetch('/api/projects/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(obj)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Project created!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
});
</script>
```

---

## 🔗 Path Rules

### **ALWAYS** use root-relative paths for assets:
```html
✅ <link rel="stylesheet" href="/assets/css/main.css">
✅ <script src="/assets/js/main.js"></script>
✅ <img src="/assets/images/logo.png">

❌ <link rel="stylesheet" href="assets/css/main.css">
❌ <script src="assets/js/main.js"></script>
❌ <img src="images/logo.png">
```

### **ALWAYS** use ROOT constant for PHP includes:
```php
✅ require_once ROOT . 'includes/auth.php';
✅ require_once ROOT . 'config/app.php';
✅ define('ROOT', dirname(__DIR__, 2) . '/');

❌ require 'includes/auth.php';
❌ require '../../../includes/auth.php';
❌ require '/xampp/htdocs/lgu-ipms/includes/auth.php';
```

---

## 📋 Checklist for New Pages

When creating a new page:
- [ ] Set ROOT constant correctly
- [ ] Include config/auth/database/helpers
- [ ] Call check_auth() if protected
- [ ] Call require_role() if restricted
- [ ] Include header.php in <head>
- [ ] Include navbar.php in <body>
- [ ] Include sidebar.php if admin page
- [ ] Include footer.php at bottom
- [ ] Use `/assets/...` for CSS/JS/images
- [ ] Use escape() for user input
- [ ] Use asset() and image() helpers
- [ ] Test page loads without errors

---

## 🐛 Troubleshooting Tips

### "Call to undefined function"
→ Check you included the right file in includes/

### "File not found" or 404
→ Check path uses `/assets/...` not `assets/...`

### CSS/Images not loading
→ Use root-relative paths `/assets/...`

### Database errors
→ Check config/app.php has correct credentials

### Session lost
→ Verify check_auth() is called on protected pages

### Function ROOT not defined
→ Add `define('ROOT', dirname(__DIR__, X) . '/');` at top

---

## 📚 Full Documentation

For detailed information, read:
- **RESTRUCTURING_PLAN.md** - Why the new structure
- **IMPLEMENTATION_GUIDE.md** - Code examples & patterns
- **BEST_PRACTICES.md** - Development standards

---

## ✨ You're Ready!

You now have:
- ✅ Professional folder structure
- ✅ Reusable components
- ✅ Helper functions
- ✅ Security functions
- ✅ API infrastructure
- ✅ Documentation

**Start building your admin pages and API endpoints!**

Happy coding! 🎉
