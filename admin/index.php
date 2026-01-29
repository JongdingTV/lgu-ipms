<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/config-path.php';

// Add no-cache headers to prevent cached login page from being shown after logout
set_no_cache_headers();

// Check if user is accessing admin page without verification or login
if (!isset($_SESSION['employee_id'])) {
    // User not logged in - will show login form below
    $show_login_form = true;
} else {
    $show_login_form = false;
}

// If already logged in, proceed normally
// If verified but not logged in, show login form (admin_verified persists)

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check database connection before processing
    if (!isset($db) || $db->connect_error) {
        $error = 'Database connection error. Please try again later.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if (!empty($email) && !empty($password)) {
            $stmt = $db->prepare("SELECT id, password, first_name, last_name FROM employees WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    $isAdmin = ($email === 'admin@lgu.gov.ph');
                    $valid = false;
                    if ($isAdmin) {
                        // Allow plain password for admin test account
                        $valid = ($password === 'admin123' || password_verify($password, $employee['password']));
                    } else {
                        $valid = password_verify($password, $employee['password']);
                    }
                    if ($valid) {
                        $_SESSION['employee_id'] = $employee['id'];
                        $_SESSION['employee_name'] = $isAdmin ? 'Admin' : ($employee['first_name'] . ' ' . $employee['last_name']);
                        $_SESSION['user_type'] = 'employee';
                        header('Location: /admin/dashboard/dashboard.php');
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                $stmt->close();
            } else {
                $error = 'Database error. Please try again later.';
            }
        } else {
            $error = 'Please enter both email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Employee Login</title>
<link rel="icon" type="image/png" href="/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style - Copy.css">
<?php echo get_app_config_script(); ?>
<script src="/security-no-back.js?v=<?php echo time(); ?>"></script>
<style>

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;

    /* NEW — background image + blur */
    background: url("/cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
    padding-top: 80px;
}

/* NEW — Blur overlay */
body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    backdrop-filter: blur(6px); /* actual blur */
    background: rgba(0, 0, 0, 0.35); /* dark overlay */
    z-index: 0; /* keeps blur behind content */
}

/* Make content appear ABOVE blur */
.nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    z-index: 100;
}

.wrapper, .footer {
    position: relative;
    z-index: 1;
}

.footer {
    position: fixed !important;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
}

.footer {
    position: fixed !important;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-logo img {
    height: 45px;
    width: auto;
    object-fit: contain;
}
</style>
</head>

<body>

<header class="nav">
    <div class="nav-logo"><img src="/logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="/logocityhall.png" class="icon-top">

        <h2 class="title">Employee Login</h2>
        <p class="subtitle">Secure access for LGU employees.</p>

        <?php if (isset($_SESSION['admin_verified'])): ?>
        <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.2em;">✅</span>
            <span><strong>Verified!</strong> You've passed 2FA verification. Now enter your credentials.</span>
        </div>
        <?php endif; ?>

        <?php if ($show_login_form): ?>
        <form method="post">

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" id="loginEmail" placeholder="employee@lgu.gov.ph" required autocomplete="email">
                <span class="icon">📧</span>
            </div>

            <div class="input-box">
                <label>Password</label>
                <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                <span class="icon">🔒</span>
            </div>

            <button class="btn-primary" type="submit">Sign In</button>

            <div style="text-align: center; margin-top: 12px;">
                <a href="/admin/forgot-password.php" style="color: #3498db; text-decoration: none; font-size: 0.9rem;">Forgot Password?</a>
            </div>

            <!-- Removed 'For citizens, click here' button as requested -->

            <?php if (isset($error)): ?>
            <div style="margin-top:12px;color:#b00;"><?php echo $error; ?></div>
            <?php endif; ?>

        </form>
        <?php else: ?>
            <p>Redirecting to dashboard...</p>
        <?php endif; ?>
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

</body>
</html>
