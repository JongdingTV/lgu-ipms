<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = new mysqli('localhost:3307', 'root', '', 'lgu_ipms');
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $stmt = $conn->prepare("SELECT id, password, first_name, last_name FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            header('Location: user-dashboard/user-dashboard.php');
            exit;
        }
    }
    $error = 'Invalid email or password.';
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;

    /* NEW — background image + blur */
    background: url("cityhall.jpeg") center/cover no-repeat fixed;
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
</style>
</head>

<body>

<header class="nav">
    <div class="nav-logo">🏛️ Local Government Unit Portal</div>
    <div class="nav-links">
        <a href="">Home</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">

        <img src="logocityhall.png" class="icon-top">

        <h2 class="title">LGU Login</h2>
        <p class="subtitle">Secure access to community maintenance services.</p>

        <form method="post">

            <div class="input-box">
                <label>Email Address</label>
                <input type="email" name="email" id="loginEmail" placeholder="name@lgu.gov.ph" required autocomplete="email">
                <span class="icon">📧</span>
            </div>

            <div class="input-box">
                <label>Password</label>
                <input type="password" name="password" id="loginPassword" placeholder="••••••••" required autocomplete="current-password">
                <span class="icon">🔒</span>
            </div>

            <button class="btn-primary" type="submit">Sign In</button>

            <p class="small-text">Don’t have an account?
                <a href="create.php" class="link">Create one</a>
            </p>

            <?php if (isset($error)): ?>
            <div style="margin-top:12px;color:#b00;"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
            <div style="margin-top:12px;color:#0b0;">Account created successfully. Please log in.</div>
            <?php endif; ?>

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
        © 2025 LGU Citizen Portal · All Rights Reserved
    </div>

</footer>

</body>
</html>

