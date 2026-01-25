# LGU IPMS - Best Practices & Standards

## Table of Contents
1. [Folder Structure Standards](#folder-structure-standards)
2. [File Naming Conventions](#file-naming-conventions)
3. [PHP Code Standards](#php-code-standards)
4. [CSS Code Standards](#css-code-standards)
5. [JavaScript Code Standards](#javascript-code-standards)
6. [HTML Standards](#html-standards)
7. [Database Standards](#database-standards)
8. [Security Standards](#security-standards)
9. [Performance Standards](#performance-standards)
10. [Documentation Standards](#documentation-standards)

---

## Folder Structure Standards

### Principle: Logical Organization by Functionality
Each folder should have a clear, single purpose. Related files should be grouped together.

### Example Structure
```
lgu-ipms/
├── public/              # Public entry points
├── app/                 # Application pages
│   ├── auth/           # Authentication pages
│   ├── admin/          # Admin pages
│   └── user/           # User pages
├── api/                 # API endpoints
├── includes/            # Shared includes
├── assets/              # Static assets
│   ├── css/            # Stylesheets
│   ├── js/             # JavaScript files
│   └── images/         # Images
├── config/              # Configuration files
├── storage/             # Uploads and cache
├── database/            # Database files
└── docs/                # Documentation
```

### Rules
- ✅ One responsibility per folder
- ✅ Avoid nested more than 3 levels deep
- ✅ Group related functionality together
- ✅ Use descriptive folder names
- ❌ Don't mix different types (CSS, JS, PHP, images)
- ❌ Don't create arbitrary nested structures

---

## File Naming Conventions

### PHP Files
```
admin-dashboard.php         ✅ Multi-word: use hyphens
adminDashboard.php          ❌ Don't use camelCase
admin_dashboard.php         ⚠️  Snake_case acceptable for data-heavy files
index.php                   ✅ Always use index.php for folder entry points
api-response.php            ✅ Action-verb names for API files
```

### CSS Files
```
main.css                    ✅ Core styles
admin.css                   ✅ Feature-specific
responsive.css              ✅ Feature-specific
style.css                   ⚠️  Acceptable but "main.css" preferred
styles.css                  ⚠️  Acceptable but "main.css" preferred
STYLE.CSS                   ❌ Never use all caps
```

### JavaScript Files
```
admin.js                    ✅ Feature-specific
validation.js               ✅ Functionality-specific
utils.js                    ✅ Utility functions
adminDashboard.js           ❌ Don't mix naming styles
admin-dashboard.js          ⚠️  Acceptable but "admin.js" preferred
Admin.js                    ❌ Never use PascalCase for files
```

### Image Files
```
logo.png                    ✅ Descriptive
dashboard-icon.png          ✅ Use hyphens for multi-word
logo-dark.png               ✅ Include variant suffix
avatar-user-01.png          ✅ Include identifier when needed
road.jpg                    ✅ Descriptive
```

### Configuration Files
```
config/app.php              ✅ Feature-specific
config/database.php         ✅ Component-specific
config/security.php         ✅ Component-specific
.env                        ✅ Environment variables
.htaccess                   ✅ URL rewriting
```

### Rules
- ✅ Use lowercase
- ✅ Use hyphens to separate words in PHP/CSS/JS files
- ✅ Be descriptive and clear
- ✅ Use underscore only for database-related PHP files
- ❌ No spaces in filenames
- ❌ No special characters except hyphens and underscores
- ❌ No version numbers in filenames (use query params instead)

---

## PHP Code Standards

### File Header
```php
<?php
/**
 * Project Registration Management
 * 
 * Handles creation, editing, and deletion of infrastructure projects
 * 
 * @package LGU-IPMS
 * @subpackage Admin
 * @version 1.0.0
 * @since 2024-01-15
 */

define('ROOT', dirname(__DIR__, 2) . '/');
require_once ROOT . 'includes/config.php';
require_once ROOT . 'includes/auth.php';
require_once ROOT . 'includes/database.php';

// Your code here
```

### Function Documentation
```php
/**
 * Retrieve all projects with optional filtering
 * 
 * @param array $filters Optional filters: [
 *     'status' => string,
 *     'location' => string,
 *     'limit' => int,
 *     'offset' => int
 * ]
 * @return array Array of projects or empty array if none found
 * @throws PDOException if database error occurs
 * 
 * @example
 * $projects = get_projects(['status' => 'Approved', 'limit' => 10]);
 */
function get_projects($filters = []) {
    global $db;
    // Implementation
}
```

### Database Queries
```php
// ✅ Good: Prepared statement
$stmt = $db->prepare("SELECT * FROM projects WHERE status = ? AND location = ?");
$stmt->bind_param('ss', $status, $location);
$stmt->execute();
$result = $stmt->get_result();

// ❌ Bad: SQL injection vulnerability
$result = $db->query("SELECT * FROM projects WHERE status = '$status'");
```

### Error Handling
```php
// ✅ Good: Try-catch with specific handling
try {
    $stmt = $db->prepare("INSERT INTO projects (name, budget) VALUES (?, ?)");
    $stmt->bind_param('sd', $name, $budget);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create project: ' . $stmt->error);
    }
    
    return ['success' => true, 'id' => $db->insert_id];
} catch (Exception $e) {
    error_log($e->getMessage());
    return ['success' => false, 'error' => 'Database error occurred'];
}
```

### Indentation & Formatting
```php
// ✅ Good: 4 spaces, consistent formatting
function process_project($id) {
    if ($id <= 0) {
        return false;
    }
    
    $query = "SELECT * FROM projects WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ❌ Bad: Inconsistent spacing
function process_project($id){
if($id<=0){return false;}
$query="SELECT * FROM projects WHERE id=?";
}
```

### Constants
```php
// ✅ Good: Uppercase with underscores
define('PROJECT_STATUS_APPROVED', 'Approved');
define('PROJECT_STATUS_PENDING', 'Pending');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// ❌ Bad: Inconsistent naming
define('project_status_approved', 'Approved');
define('ProjectStatusApproved', 'Approved');
```

### Ternary and Short Syntax
```php
// ✅ Good: Readable multi-line
$status = isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
    ? 'Admin'
    : 'User';

// ✅ Good: Null coalescing
$name = $_POST['name'] ?? 'Guest';

// ❌ Bad: Confusing nested ternary
$status = isset($_SESSION['role']) ? ($_SESSION['role'] === 'admin' ? 'Admin' : 'Staff') : 'Guest';
```

---

## CSS Code Standards

### File Organization
```css
/* ✅ Good: Organized by sections */

/* ========== VARIABLES ========== */
:root {
    --primary-color: #2980b9;
    --secondary-color: #6dd5fa;
    --spacing-unit: 8px;
}

/* ========== BASE STYLES ========== */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
}

/* ========== LAYOUT COMPONENTS ========== */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--spacing-unit);
}

/* ========== BUTTONS ========== */
.btn {
    padding: calc(var(--spacing-unit) * 2) calc(var(--spacing-unit) * 4);
    border-radius: 6px;
    cursor: pointer;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .container {
        padding: 0 calc(var(--spacing-unit) / 2);
    }
}
```

### Naming Conventions
```css
/* ✅ Good: BEM-like naming */
.dashboard { }
.dashboard__header { }
.dashboard__header--active { }
.dashboard__card { }

/* ✅ Good: Descriptive class names */
.button-primary { }
.navigation-sidebar { }
.project-card { }

/* ❌ Bad: Non-descriptive names */
.box { }
.item { }
.div1 { }

/* ❌ Bad: Using ID selectors (too specific) */
#dashboard-header { }
```

### Spacing & Indentation
```css
/* ✅ Good: Consistent formatting */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f5f5f5;
}

/* ❌ Bad: Inconsistent spacing */
.container{max-width:1200px;margin:0 auto;padding:20px;}
.element { margin:10px;padding: 5px; }
```

### Colors & Units
```css
/* ✅ Good: Use variables for repeated values */
:root {
    --color-primary: #2980b9;
    --color-danger: #e74c3c;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
}

.button {
    padding: var(--spacing-md);
    background-color: var(--color-primary);
}

/* ❌ Bad: Magic numbers and repeated values */
.button {
    padding: 16px;
    background-color: #2980b9;
    margin: 16px;
}

.card {
    padding: 16px;
    background-color: #ffffff;
    margin: 16px;
}
```

---

## JavaScript Code Standards

### File Structure
```javascript
// ✅ Good: Well-organized structure
(function() {
    'use strict';
    
    // Private variables
    const API_BASE = '/api';
    const CACHE = {};
    
    // Private functions
    function validateForm(data) {
        // Validation logic
    }
    
    // Public API
    window.ProjectManager = {
        init: function() {
            this.bindEvents();
            this.loadProjects();
        },
        
        bindEvents: function() {
            document.addEventListener('submit', this.handleSubmit.bind(this));
        },
        
        loadProjects: function() {
            fetch(API_BASE + '/projects/list.php')
                .then(response => response.json())
                .then(data => this.render(data));
        },
        
        render: function(data) {
            // Rendering logic
        }
    };
    
    // Initialize on document ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.ProjectManager.init());
    } else {
        window.ProjectManager.init();
    }
})();
```

### Variable Naming
```javascript
// ✅ Good: Clear, descriptive names
let userProfileData = {};
const MAX_RETRIES = 3;
const isAdminUser = true;
function validateEmailAddress(email) {}

// ❌ Bad: Unclear or single-letter variables
let data = {};
let x = 0;
let f = function(x) {};
```

### Comments & Documentation
```javascript
// ✅ Good: Clear, purpose-driven comments
// Fetch user profile and cache for 5 minutes
function loadUserProfile(userId) {
    const cacheKey = `user_${userId}`;
    
    // Check if already cached
    if (CACHE[cacheKey] && Date.now() - CACHE[cacheKey].timestamp < 300000) {
        return Promise.resolve(CACHE[cacheKey].data);
    }
    
    // Fetch fresh data
    return fetch(`/api/users/${userId}`)
        .then(response => response.json())
        .then(data => {
            // Store in cache with timestamp
            CACHE[cacheKey] = {
                data: data,
                timestamp: Date.now()
            };
            return data;
        });
}

// ❌ Bad: Obvious or vague comments
// Get user data
function getUser(id) {
    // This fetches user data
    return fetch('/api/user/' + id).then(r => r.json());
}
```

### Error Handling
```javascript
// ✅ Good: Proper error handling
fetch('/api/projects/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
})
.then(response => {
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
})
.then(data => {
    if (data.success) {
        showSuccessMessage('Project created successfully');
        loadProjects(); // Refresh list
    } else {
        showErrorMessage(data.error || 'Unknown error occurred');
    }
})
.catch(error => {
    console.error('Error creating project:', error);
    showErrorMessage('Network error. Please try again.');
});

// ❌ Bad: Silent failures
fetch('/api/projects/create.php', {
    method: 'POST',
    body: JSON.stringify(data)
}).then(r => r.json()).then(d => loadProjects());
```

---

## HTML Standards

### Structure
```html
<!-- ✅ Good: Semantic HTML with proper structure -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LGU Infrastructure Project Management System">
    <title>LGU IPMS - Dashboard</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <header class="navbar">
        <nav role="navigation" aria-label="Main navigation">
            <a href="/" class="navbar__logo">IPMS</a>
            <ul class="navbar__menu">
                <li><a href="/app/admin/dashboard.php" class="navbar__link">Dashboard</a></li>
                <li><a href="/app/admin/projects/" class="navbar__link">Projects</a></li>
            </ul>
        </nav>
    </header>
    
    <main class="container" role="main">
        <h1>Dashboard</h1>
        <section class="projects">
            <article class="project-card">
                <h2>Project Name</h2>
                <p>Description</p>
            </article>
        </section>
    </main>
    
    <footer class="footer">
        <p>&copy; 2024 LGU. All rights reserved.</p>
    </footer>
    
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
```

### Accessibility
```html
<!-- ✅ Good: Accessible form -->
<form id="projectForm" aria-label="Create new project">
    <div class="form-group">
        <label for="projectName">Project Name</label>
        <input 
            id="projectName" 
            name="name" 
            type="text" 
            required 
            aria-required="true"
            aria-describedby="nameHelp"
        />
        <small id="nameHelp">Enter a descriptive project name</small>
    </div>
    
    <button type="submit" aria-label="Submit project form">Create Project</button>
</form>

<!-- ❌ Bad: Inaccessible form -->
<form>
    <input type="text" placeholder="Name">
    <button>Submit</button>
</form>
```

---

## Database Standards

### Table Naming
```sql
-- ✅ Good: Plural, lowercase, hyphens or underscores
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT
);

CREATE TABLE project_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT
);

-- ❌ Bad: Singular or inconsistent
CREATE TABLE project ( );
CREATE TABLE ProjectTasks ( );
CREATE TABLE Project-Tasks ( );
```

### Column Naming
```sql
-- ✅ Good: Descriptive, consistent
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    budget DECIMAL(12, 2),
    status ENUM('Pending', 'Approved', 'In Progress', 'Completed') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(id)
);

-- ❌ Bad: Unclear or inconsistent naming
CREATE TABLE p (
    i INT,
    n VARCHAR(255),
    d TEXT,
    l VARCHAR(255),
    b DECIMAL,
    s VARCHAR(50),
    CreatedBy INT,
    CreatedDate DATETIME
);
```

### Relationships
```sql
-- ✅ Good: Clear foreign key relationships
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100)
);

CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    assigned_to INT NOT NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE CASCADE
);

-- ✅ Good: Many-to-many relationships
CREATE TABLE project_contractors (
    project_id INT NOT NULL,
    contractor_id INT NOT NULL,
    role VARCHAR(100),
    PRIMARY KEY (project_id, contractor_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
);
```

---

## Security Standards

### Input Validation
```php
// ✅ Good: Validate and sanitize all inputs
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    die('Invalid email address');
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$id) {
    die('Invalid ID');
}

$name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
```

### SQL Injection Prevention
```php
// ✅ Good: Use prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
$stmt->bind_param('ss', $email, $role);
$stmt->execute();

// ❌ Bad: SQL injection vulnerability
$result = $db->query("SELECT * FROM users WHERE email = '$email'");
```

### Output Escaping
```php
// ✅ Good: Always escape output
<?php echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8'); ?>

// ❌ Bad: Unescaped output
<?php echo $user_input; ?>
```

### Session Security
```php
// ✅ Good: Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// ✅ Good: Session validation
function is_valid_session() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_agent']) &&
           $_SESSION['user_agent'] === hash('sha256', $_SERVER['HTTP_USER_AGENT']);
}
```

---

## Performance Standards

### Database Optimization
```php
// ✅ Good: Select only needed columns
$result = $db->query("SELECT id, name, status FROM projects LIMIT 100");

// ✅ Good: Use indexes
// ALTER TABLE projects ADD INDEX idx_status (status);

// ❌ Bad: Select everything
$result = $db->query("SELECT * FROM projects");

// ❌ Bad: N+1 queries
foreach ($projects as $project) {
    $tasks = $db->query("SELECT * FROM tasks WHERE project_id = {$project['id']}");
}
```

### Asset Optimization
```html
<!-- ✅ Good: Minified and versioned -->
<link rel="stylesheet" href="/assets/css/main.min.css?v=1.2.3">
<script src="/assets/js/main.min.js?v=1.2.3" defer></script>

<!-- ✅ Good: Lazy loading -->
<img src="placeholder.jpg" loading="lazy" data-src="/assets/images/large.jpg">

<!-- ❌ Bad: Unoptimized -->
<link rel="stylesheet" href="/assets/css/main.css">
<script src="/assets/js/main.js"></script>
```

### Caching
```php
// ✅ Good: Cache frequently accessed data
$cache_key = 'projects_list_' . md5(json_encode($filters));
if (isset($GLOBALS['cache'][$cache_key])) {
    return $GLOBALS['cache'][$cache_key];
}

$projects = fetch_projects_from_db($filters);
$GLOBALS['cache'][$cache_key] = $projects;

// Set cache headers
header('Cache-Control: public, max-age=3600');
header('ETag: ' . md5(json_encode($projects)));
```

---

## Documentation Standards

### README
```markdown
# LGU Infrastructure Project Management System

## Description
Brief description of the system

## Features
- Feature 1
- Feature 2

## Setup Instructions
1. Step 1
2. Step 2

## Usage
```

### API Documentation
```markdown
# Projects API

## GET /api/projects/list.php
Retrieve all projects

### Parameters
- `status` (optional): Filter by status
- `limit` (optional): Number of results to return

### Response
```json
{
    "success": true,
    "data": [
        { "id": 1, "name": "Project 1" }
    ]
}
```
```

### Code Comments
```php
// ✅ Good: Comment WHY, not WHAT
// Retrieve only approved projects because pending projects may have incomplete data
$projects = $db->query("SELECT * FROM projects WHERE status = 'Approved'");

// ❌ Bad: Comment the obvious
// Get projects
$projects = $db->query("SELECT * FROM projects WHERE status = 'Approved'");
```

---

## Summary Checklist

### Before committing code:
- [ ] Follows file naming conventions
- [ ] Uses proper folder organization
- [ ] PHP code is documented with PHPDoc
- [ ] All input is validated
- [ ] SQL queries use prepared statements
- [ ] Output is properly escaped
- [ ] CSS uses variables and organized sections
- [ ] JavaScript is modular and error-handled
- [ ] HTML is semantic and accessible
- [ ] No sensitive data in code
- [ ] Performance optimizations applied
- [ ] Mobile responsive design checked

