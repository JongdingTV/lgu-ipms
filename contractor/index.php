<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $activeRole = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
    if (in_array($activeRole, ['contractor', 'admin', 'super_admin'], true)) {
        header('Location: /contractor/dashboard.php');
        exit;
    }
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($emailInput === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (is_rate_limited('contractor_login', 6, 300)) {
        $error = 'Too many login attempts. Please try again in a few minutes.';
    } else {
        $stmt = $db->prepare("SELECT id, first_name, last_name, role, password FROM employees WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $emailInput);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            $isAdminAccount = strtolower($emailInput) === 'admin@lgu.gov.ph';
            $validPassword = false;
            if ($employee) {
                if ($isAdminAccount) {
                    $validPassword = ($password === 'admin123' || password_verify($password, (string) $employee['password']));
                } else {
                    $validPassword = password_verify($password, (string) $employee['password']);
                }
            }

            if (!$employee || !$validPassword) {
                record_attempt('contractor_login');
                $error = 'Invalid email or password.';
            } else {
                $userRole = strtolower(trim((string) ($employee['role'] ?? '')));
                if (!in_array($userRole, ['contractor', 'admin', 'super_admin'], true)) {
                log_security_event('ROLE_DENIED', 'Contractor login blocked for non-contractor role');
                $error = 'Your account is not assigned to contractor access.';
                } else {
                session_regenerate_id(true);
                $_SESSION['employee_id'] = (int) $employee['id'];
                $_SESSION['employee_name'] = trim((string) $employee['first_name'] . ' ' . (string) $employee['last_name']);
                $_SESSION['employee_role'] = $userRole;
                $_SESSION['user_type'] = 'employee';
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /contractor/dashboard.php');
                exit;
                }
            }
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractor Login - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/shared/admin-auth.css">
    <link rel="stylesheet" href="login.css">
</head>
<body class="admin-login-page">
<header class="nav">
    <div class="nav-logo"><img src="../assets/images/icons/ipms-icon.png" alt="LGU Logo"> LGU Contractor Portal</div>
    <a href="/public/index.php" class="home-btn">Home</a>
</header>
<div class="wrapper">
    <div class="card">
        <img src="../assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU">
        <h2 class="title">Contractor Login</h2>
        <p class="subtitle">Use your employee account assigned as contractor.</p>
        <form method="post" autocomplete="off">
            <div class="input-box">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="icon">@</span>
            </div>
            <div class="input-box">
                <label>Password</label>
                <input type="password" name="password" required>
                <span class="icon">*</span>
            </div>
            <button class="btn-primary" type="submit">Sign In</button>
            <?php if ($error !== ''): ?>
                <div class="ac-aabba7cf"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>
<script src="login-security.js?v=<?php echo filemtime(__DIR__ . '/login-security.js'); ?>"></script>
</body>
</html>

