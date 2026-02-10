<?php
// admin-login.php
// Employee/Admin login page

require_once __DIR__ . '/../includes/auth.php';

session_start();

if (is_authenticated() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee') {
    header('Location: /admin/index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result = authenticate_employee($email, $password);
    if ($result['success']) {
        $_SESSION['employee_id'] = $result['user_id'];
        $_SESSION['employee_name'] = $result['user_name'] ?? '';
        $_SESSION['user_type'] = 'employee';
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        header('Location: /admin/dashboard/dashboard.php');
        exit();
    } else {
        $error = $result['error'] ?? 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Login</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="login-container">
        <h2>Employee Login</h2>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
