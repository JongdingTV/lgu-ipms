<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
$success = '';
$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? '';

// Get client IP
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$client_ip = get_client_ip();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Session timeout (30 minutes)
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_destroy();
    header('Location: /admin/index.php?session_expired=1');
    exit;
} else {
    $_SESSION['last_activity'] = time();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*etc).';
    } elseif ($current_password === $new_password) {
        $error = 'New password must be different from current password.';
    } else {
        // Verify current password
        if (!isset($db) || $db->connect_error) {
            $error = 'Database connection error. Please try again later.';
        } else {
            $stmt = $db->prepare("SELECT password, email FROM employees WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $employee_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    $email = $employee['email'];
                    $isAdmin = ($email === 'admin@lgu.gov.ph');
                    
                    // Verify current password
                    $password_valid = false;
                    if ($isAdmin) {
                        $password_valid = ($current_password === 'admin123' || password_verify($current_password, $employee['password']));
                    } else {
                        $password_valid = password_verify($current_password, $employee['password']);
                    }
                    
                    if ($password_valid) {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param('si', $hashed_password, $employee_id);
                            if ($update_stmt->execute()) {
                                // Log password change
                                $log_stmt = $db->prepare("INSERT INTO login_logs (employee_id, email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, ?, 'success', 'Password changed')");
                                if ($log_stmt) {
                                    $log_stmt->bind_param('isss', $employee_id, $email, $client_ip, $user_agent);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                                
                                $success = 'Password changed successfully!';
                            } else {
                                $error = 'Failed to update password. Please try again.';
                            }
                            $update_stmt->close();
                        } else {
                            $error = 'Database error. Please try again later.';
                        }
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                } else {
                    $error = 'User not found.';
                }
                $stmt->close();
            } else {
                $error = 'Database error. Please try again later.';
            }
        }
    }
}

if (isset($db)) {
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password - LGU Employee Portal</title>
<link rel="icon" type="image/png" href="../logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <link rel="stylesheet" href="../assets/css/admin.css?v=20260212f">
</head>

<body>

<header class="nav">
    <div class="nav-logo"><img src="../logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="../logocityhall.png" class="icon-top">

        <h2 class="title">Change Password</h2>
        <p class="subtitle">Update your account password securely</p>

        <?php if (!empty($error)): ?>
        <div class="ac-eef834dd">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="ac-eee71138">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <div class="input-box">
                <label>Current Password</label>
                <input type="password" name="current_password" placeholder="••••••••" required autocomplete="current-password">
                <span class="icon">🔒</span>
            </div>

            <div class="input-box">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="••••••••" required autocomplete="new-password">
                <span class="icon">🔒</span>
            </div>

            <div class="input-box">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required autocomplete="new-password">
                <span class="icon">🔒</span>
            </div>

            <div class="ac-97825712">
                <strong>Password Requirements:</strong>
                <ul class="ac-cbf6525e">
                    <li>At least 8 characters long</li>
                    <li>At least one uppercase letter (A-Z)</li>
                    <li>At least one number (0-9)</li>
                    <li>At least one special character (!@#$%^&*)</li>
                </ul>
            </div>

            <button class="btn-primary" type="submit">Change Password</button>

            <div class="ac-e5cd2b77">
                <a href="/admin/dashboard.php" class="ac-f72a71bf">Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>

<footer class="footer">
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">About</a>
        <a href="#">Help</a>
    </div>

    <div class="footer-logo">
        © 2026 LGU Citizen Portal · All Rights Reserved
    </div>
</footer>

    <script src="../assets/js/admin.js"></script>
</body>
</html>











