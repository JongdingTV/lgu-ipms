<?php
/**
 * Employee Management Page
 * Add, edit, and manage employee accounts for admin access
 */

session_start();

// DATABASE CONNECTION
require_once dirname(__DIR__) . '/database.php';

// Check if user is logged in or at least verified through 2FA
if (!isset($_SESSION['employee_id']) && !isset($_SESSION['admin_verified'])) {
    header('Location: /public/admin-verify.php');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1e3a5f;
            --primary-light: #2c5282;
            --secondary: #f39c12;
            --success: #27ae60;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #ecf0f1;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            padding: 2rem 0;
        }

        .container {
            max-width: 1200px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: 10px 10px 0 0;
            padding: 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.7rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        table {
            margin-bottom: 0;
        }

        thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        th {
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border: none;
        }

        td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #e0e0e0;
        }

        tbody tr:hover {
            background-color: #f9f9f9;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #cfe2ff;
            color: #084298;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }

        .info-box {
            background: #f0f8ff;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .info-box i {
            color: var(--info);
            margin-right: 0.5rem;
        }
    </style>
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
            <button class="tab-btn active" onclick="switchTab('add')">
                <i class="fas fa-plus-circle"></i> Add Employee
            </button>
            <button class="tab-btn" onclick="switchTab('list')">
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
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>">
                                                    <button type="submit" name="delete_employee" class="btn btn-danger" 
                                                            onclick="return confirm('Delete <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>?')">
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
                            <p>No employees found. <a href="#" onclick="switchTab('add')">Add one now</a></p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
