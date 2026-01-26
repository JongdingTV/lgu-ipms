# 👥 How to Add Employee Accounts

## 3 Ways to Add Employees

---

## **Method 1: Web Interface (Easiest)** ✅ RECOMMENDED

### Access the Employee Management Page
```
URL: http://localhost/admin/manage-employees.php
```

### Steps:
1. Open the link above
2. Click **"Add Employee"** tab
3. Fill in the form:
   - **Employee ID:** Numeric ID (e.g., 1001, 1002)
   - **First Name:** Employee's first name
   - **Last Name:** Employee's last name
   - **Email:** Must be valid email (e.g., john@lgu.gov.ph)
   - **Password:** Minimum 6 characters
   - **Role:** Select appropriate role (Employee, Manager, Admin, Supervisor)
4. Click **"Add Employee"** button
5. Success message appears

### Features:
- ✅ Password automatically bcrypt-hashed
- ✅ Automatic email validation
- ✅ Duplicate ID prevention
- ✅ View all employees in list
- ✅ Delete employees if needed
- ✅ Beautiful, user-friendly interface

---

## **Method 2: Direct SQL (Database)**

### Using phpMyAdmin or MySQL Client:

### Add a Single Employee:
```sql
INSERT INTO employees (id, first_name, last_name, email, password, role) 
VALUES (
  1001,                                                    -- Employee ID
  'John',                                                   -- First Name
  'Doe',                                                    -- Last Name
  'john@lgu.gov.ph',                                       -- Email
  '$2y$10$C9QtuPJwMZI8wRcLPqWdVOhNMhBlCWV5ZxrREK5b2kHDDQqPBpTUa',  -- Password hash
  'Employee'                                                -- Role
);
```

### Generate Bcrypt Password Hash:

#### Using PHP (from command line):
```bash
php -r "echo password_hash('password123', PASSWORD_BCRYPT);"
```

#### Using Online Generator:
- Go to: https://www.browserling.com/tools/bcrypt
- Enter your desired password
- Copy the generated hash

### Example with Password "SecurePass123":
```sql
INSERT INTO employees (id, first_name, last_name, email, password, role) 
VALUES (
  1002,
  'Jane',
  'Smith',
  'jane@lgu.gov.ph',
  '$2y$10$somebcrypthashhere1234567890abcdefghijklmnopqrstuvwxyz',
  'Manager'
);
```

### Add Multiple Employees at Once:
```sql
INSERT INTO employees (id, first_name, last_name, email, password, role) VALUES
(1001, 'John', 'Doe', 'john@lgu.gov.ph', '$2y$10$...hash1...', 'Employee'),
(1002, 'Jane', 'Smith', 'jane@lgu.gov.ph', '$2y$10$...hash2...', 'Manager'),
(1003, 'Bob', 'Johnson', 'bob@lgu.gov.ph', '$2y$10$...hash3...', 'Supervisor');
```

---

## **Method 3: PHP Script**

### Quick Add Script (create temporarily):

Save this as `add-employee-quick.php` in your root directory:

```php
<?php
// Quick Employee Add Script
$db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');

if ($_POST['add']) {
    $id = (int)$_POST['id'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    
    $stmt = $db->prepare("INSERT INTO employees (id, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $id, $fname, $lname, $email, $password, $role);
    
    if ($stmt->execute()) {
        echo "✅ Employee added!";
    } else {
        echo "❌ Error: " . $stmt->error;
    }
}
?>
```

---

## 📋 Employee Database Structure

### Table: `employees`

```sql
CREATE TABLE `employees` (
  `id` int(11) NOT NULL,              -- Unique Employee ID
  `first_name` varchar(50) NOT NULL,  -- First name
  `last_name` varchar(50) NOT NULL,   -- Last name
  `email` varchar(100) NOT NULL,      -- Email (unique)
  `password` varchar(255) NOT NULL,   -- Bcrypt hashed password
  `role` varchar(50) DEFAULT 'Employee',  -- Role/Position
  `created_at` timestamp DEFAULT current_timestamp()  -- Creation date
);
```

### Column Requirements:
- **id**: Must be unique, numeric, no duplicates
- **first_name**: Required, text
- **last_name**: Required, text
- **email**: Required, must be valid email format
- **password**: Required, must be bcrypt-hashed (NOT plaintext!)
- **role**: Optional, defaults to 'Employee'

---

## 🔐 Password Security

### ❌ DO NOT Store Plaintext Passwords:
```php
// WRONG - Don't do this!
INSERT INTO employees ... VALUES (..., 'mypassword', ...);
```

### ✅ ALWAYS Use Bcrypt:
```php
// CORRECT - Always do this!
$hashed = password_hash('mypassword', PASSWORD_BCRYPT);
INSERT INTO employees ... VALUES (..., '$hashed_value', ...);
```

### How to Generate Bcrypt Hash:

#### Option 1: PHP CLI
```bash
php -r "echo password_hash('YourPassword123', PASSWORD_BCRYPT);"
```

#### Option 2: Online Tool
- https://www.browserling.com/tools/bcrypt
- https://www.tools4noobs.com/online_tools/bcrypt/

#### Option 3: PHP Script (One-time use)
Create `generate-hash.php`:
```php
<?php
$password = $_GET['password'] ?? 'password123';
echo password_hash($password, PASSWORD_BCRYPT);
?>
```

Then visit: `http://localhost/generate-hash.php?password=MyPassword123`

---

## ✅ Verification Checklist

After adding an employee, verify they can access the admin area:

### 1. Check Database:
```sql
SELECT id, email, password 
FROM employees 
WHERE id = 1001;
```

Should show:
- ✅ id: 1001
- ✅ email: john@lgu.gov.ph
- ✅ password: (starts with $2y$10$...)

### 2. Test 2FA Login:
1. Go to: http://localhost/public/admin-verify.php
2. **STEP 1:**
   - Employee ID: 1001
   - Password: (password you set)
   - Click "Verify Identity"
3. **STEP 2:**
   - Click "Send Verification Code"
   - Code appears on screen (demo mode)
4. **STEP 3:**
   - Enter the 8-digit code
   - Should redirect to admin dashboard ✅

### 3. Common Issues:

**"Employee not found"**
- Check Employee ID is correct in database
- Run: `SELECT * FROM employees WHERE id = 1001;`

**"Invalid password"**
- Verify password is bcrypt-hashed (starts with $2y$10$)
- Test hash with: `php -r "echo password_verify('password123', '\$2y\$10\$...');"` (should print `1`)

**"Invalid email"**
- Check email format (must contain @)
- Ensure no spaces or special characters

---

## 📊 Example Employee Data

### Add These Sample Employees:

```sql
-- Admin User
INSERT INTO employees (id, first_name, last_name, email, password, role) 
VALUES (1, 'System', 'Administrator', 'admin@lgu.gov.ph', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');

-- Manager
INSERT INTO employees (id, first_name, last_name, email, password, role) 
VALUES (101, 'Maria', 'Garcia', 'maria.garcia@lgu.gov.ph', 
'$2y$10$C9QtuPJwMZI8wRcLPqWdVOhNMhBlCWV5ZxrREK5b2kHDDQqPBpTUa', 'Manager');

-- Staff Member
INSERT INTO employees (id, first_name, last_name, email, password, role) 
VALUES (102, 'Juan', 'Cruz', 'juan.cruz@lgu.gov.ph', 
'$2y$10$lQV3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5YmMxSUIulySm', 'Employee');
```

**All passwords are: `password`** (for testing)

---

## 🔒 Best Practices

### When Adding Employees:
1. ✅ Use strong passwords (8+ characters, mixed case, numbers, symbols)
2. ✅ Always bcrypt-hash passwords
3. ✅ Use company email addresses
4. ✅ Double-check for duplicate IDs
5. ✅ Test login immediately after adding
6. ✅ Document employee IDs in a secure location

### Security Tips:
1. ❌ Never share passwords via email or chat
2. ❌ Never store plaintext passwords
3. ❌ Don't reuse passwords across systems
4. ✅ Update passwords every 90 days
5. ✅ Disable old accounts when employees leave
6. ✅ Use strong, unique employee IDs

---

## 🆘 Troubleshooting

### Problem: "Duplicate ID Error"
**Solution:** Use `SELECT MAX(id) FROM employees;` to find the highest ID, then use ID+1

### Problem: "Invalid email error"
**Solution:** Ensure email has @ symbol and proper format (name@domain.com)

### Problem: "Password too short error"
**Solution:** Use at least 6 characters. Longer passwords are more secure (12+ recommended)

### Problem: "Employee found but wrong password"
**Solution:** 
1. Verify password hash in database (must start with $2y$10$)
2. Regenerate hash: `php -r "echo password_hash('password', PASSWORD_BCRYPT);"`
3. Update database with new hash

### Problem: "Can't access manage-employees.php"
**Solution:** 
1. Must be logged in first
2. Go to http://localhost/public/admin-verify.php
3. Login with existing admin account
4. Then access http://localhost/admin/manage-employees.php

---

## 📞 Quick Reference

### Default Admin Account (If Created):
```
Employee ID: 1
Email: admin@lgu.gov.ph
Password: admin123
```

### Test ID Pattern:
```
Managers:     100-199 (e.g., 101, 102, 103)
Supervisors:  200-299 (e.g., 201, 202, 203)
Staff:        300-999 (e.g., 301, 302, 303)
Admins:       1-99    (e.g., 1, 2, 3)
```

### Minimum Required Fields:
- id (numeric, unique)
- first_name (text)
- last_name (text)
- email (valid email)
- password (bcrypt hash, NOT plaintext)

---

**Now you have three ways to add employees! The web interface is easiest for most users. 👥**
