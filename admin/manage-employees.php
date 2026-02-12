<?php
/**
 * Employee Management Page
 * Add, edit, and manage employee accounts for admin access
 */

session_start();

// DATABASE CONNECTION
require_once dirname(__DIR__) . '/database.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: /index.php');
    exit;
}

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_employee'])) {
        $emp_id = !empty($_POST['emp_id']) ? (int)trim($_POST['emp_id']) : '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'Employee');
        
        // Validation
        if (empty($emp_id) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = 'All fields are required. Please fill in every field.';
        } elseif ($emp_id <= 0) {
            $error = 'Employee ID must be a positive number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format. Example: john@lgu.gov.ph';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if database connection exists
            if (!isset($db) || $db->connect_error) {
                $error = 'Database connection failed. Please try again later.';
            } else {
                // Check if employee ID already exists
                $stmt = $db->prepare("SELECT id FROM employees WHERE id = ?");
                if (!$stmt) {
                    $error = 'Database error: ' . $db->error;
                } else {
                    $stmt->bind_param('i', $emp_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = 'Employee ID ' . $emp_id . ' already exists. Please use a different ID.';
                    } else {
                        // Hash password with bcrypt
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        
                        // Insert employee
                        $stmt = $db->prepare("INSERT INTO employees (id, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            $error = 'Database error: ' . $db->error;
                        } else {
                            $stmt->bind_param('isssss', $emp_id, $first_name, $last_name, $email, $hashed_password, $role);
                            
                            if ($stmt->execute()) {
                                $message = "✅ Employee '$first_name $last_name' (ID: $emp_id) added successfully!";
                                // Clear form
                                $_POST = [];
                            } else {
                                $error = 'Error adding employee: ' . $stmt->error;
                            }
                        }
                    }
                    $stmt->close();
                }
            }
        }
    } elseif (isset($_POST['delete_employee'])) {
        $emp_id = (int)$_POST['emp_id'];
        
        $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param('i', $emp_id);
        
        if ($stmt->execute()) {
            $message = "✅ Employee deleted successfully!";
        } else {
            $error = 'Error deleting employee: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// GET ALL EMPLOYEES
$employees = [];
$result = $db->query("SELECT id, first_name, last_name, email, role, created_at FROM employees ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Note: Don't close $db here as it's reused from database.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - Admin Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="../assets/css/admin.css?v=20260212g">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Employee Management</h1>
            <p>Add, edit, and manage employee accounts for admin access</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" data-onclick="switchTab('add')">
                <i class="fas fa-plus-circle"></i> Add Employee
            </button>
            <button class="tab-btn" data-onclick="switchTab('list')">
                <i class="fas fa-list"></i> Employee List (<?php echo count($employees); ?>)
            </button>
        </div>

        <!-- TAB 1: Add Employee -->
        <div id="add" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-plus"></i> Add New Employee
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Required Fields:</strong> Employee ID, First Name, Last Name, Email, Password
                    </div>

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emp_id">
                                    <i class="fas fa-id-card"></i> Employee ID
                                </label>
                                <input 
                                    type="number" 
                                    id="emp_id" 
                                    name="emp_id" 
                                    placeholder="e.g., 1001"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['emp_id'] ?? ''); ?>"
                                />
                                <small class="text-muted">Must be unique and numeric</small>
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    placeholder="employee@lgu.gov.ph"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">
                                    <i class="fas fa-user"></i> First Name
                                </label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    placeholder="John"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                />
                            </div>

                            <div class="form-group">
                                <label for="last_name">
                                    <i class="fas fa-user"></i> Last Name
                                </label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    placeholder="Doe"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="At least 6 characters"
                                    required
                                />
                                <small class="text-muted">Minimum 6 characters, will be bcrypt-hashed</small>
                            </div>

                            <div class="form-group">
                                <label for="role">
                                    <i class="fas fa-briefcase"></i> Role
                                </label>
                                <select id="role" name="role">
                                    <option value="Employee">Employee</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Supervisor">Supervisor</option>
                                </select>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" name="add_employee" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Clear Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: Employee List -->
        <div id="list" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Employee List (<?php echo count($employees); ?> employees)
                </div>
                <div class="card-body">
                    <?php if (count($employees) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Added</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($emp['id']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($emp['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo htmlspecialchars($emp['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($emp['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <form method="POST" class="ac-434fc32e">
                                                    <input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>">
                                                    <button type="submit" name="delete_employee" class="btn btn-danger" 
                                                            data-onclick="return confirm('Delete <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No employees found. <a href="#" data-onclick="switchTab('add')">Add one now</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Reference -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-question-circle"></i> Quick Reference
            </div>
            <div class="card-body">
                <h5>Employee Requirements for 2FA:</h5>
                <ul>
                    <li><strong>Employee ID:</strong> Numeric, unique identifier (e.g., 1001, 1002)</li>
                    <li><strong>Email:</strong> Must be valid and unique</li>
                    <li><strong>Password:</strong> Minimum 6 characters, will be bcrypt-hashed automatically</li>
                    <li><strong>Name:</strong> First and last name for display purposes</li>
                </ul>

                <hr>

                <h5>2FA Login Process:</h5>
                <ol>
                    <li>Employee enters their <strong>Employee ID</strong></li>
                    <li>Employee enters their <strong>Password</strong></li>
                    <li>System sends <strong>8-digit code</strong> to their email</li>
                    <li>Employee enters the code</li>
                    <li><strong>Admin access granted</strong> ✅</li>
                </ol>

                <hr>

                <h5>Testing Credentials:</h5>
                <p>Default admin account (from database):</p>
                <div class="info-box">
                    <strong>Employee ID:</strong> 1<br>
                    <strong>Email:</strong> admin@lgu.gov.ph<br>
                    <strong>Password:</strong> admin123
                </div>
            </div>
        </div>
    </div>
<script src="../assets/js/admin.js"></script>
</body>
</html>









