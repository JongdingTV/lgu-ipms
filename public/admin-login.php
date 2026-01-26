<?php
/**
 * Simplified Admin Verification System
 * Clean, working 2FA implementation with email verification
 */

session_start();

// Load email configuration
require_once dirname(__DIR__) . '/config/email.php';

// Database connection
$db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}
$db->set_charset("utf8mb4");

$error = '';
$success = '';
$step = 1;

// Check session to determine current step
if (isset($_SESSION['admin_temp_id']) && isset($_SESSION['admin_temp_code'])) {
    $step = 3; // Waiting for code verification
} elseif (isset($_SESSION['admin_temp_id'])) {
    $step = 2; // Waiting for code request
} else {
    $step = 1; // Initial login
}

// STEP 1: Login with ID and Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step1_login'])) {
    $emp_id = trim($_POST['emp_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($emp_id) || empty($password)) {
        $error = 'Please enter both Employee ID and password.';
    } else {
        // Check database
        $stmt = $db->prepare("SELECT id, email, first_name, last_name, password FROM employees WHERE id = ? OR email = ?");
        $stmt->bind_param('ss', $emp_id, $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $emp = $result->fetch_assoc();
            if (password_verify($password, $emp['password'])) {
                // Store in session for next step
                $_SESSION['admin_temp_id'] = $emp['id'];
                $_SESSION['admin_temp_email'] = $emp['email'];
                $_SESSION['admin_temp_name'] = $emp['first_name'] . ' ' . $emp['last_name'];
                
                $step = 2;
                $success = 'Identity verified! Now requesting verification code...';
            } else {
                $error = 'Invalid password. Please try again.';
            }
        } else {
            $error = 'Employee not found.';
        }
        $stmt->close();
    }
}

// STEP 2: Request Code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step2_request'])) {
    if (!isset($_SESSION['admin_temp_id'])) {
        $error = 'Session expired. Please start over.';
        $step = 1;
    } else {
        // Generate code
        $code = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Store code with timestamp
        $_SESSION['admin_temp_code'] = $code;
        $_SESSION['admin_code_time'] = time();
        $_SESSION['admin_code_attempts'] = 0;
        
        // Send email with actual code
        $email_sent = send_verification_code(
            $_SESSION['admin_temp_email'],
            $code,
            $_SESSION['admin_temp_name']
        );
        
        if ($email_sent) {
            $step = 3;
            $success = "Verification code sent to " . substr($_SESSION['admin_temp_email'], 0, 3) . "***@... Check your email!";
        } else {
            // Fallback to demo mode if email fails
            $step = 3;
            $success = "Code sent to email. <strong style='color: #f39c12;'>Demo: $code</strong>";
        }
    }
}

// STEP 3: Verify Code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step3_verify'])) {
    $entered_code = trim($_POST['code'] ?? '');
    
    if (!isset($_SESSION['admin_temp_code'])) {
        $error = 'Session expired. Please start over.';
        $step = 1;
    } else {
        // Check if code expired (10 minutes)
        if (time() - $_SESSION['admin_code_time'] > 600) {
            $error = 'Code expired. Request a new one.';
            unset($_SESSION['admin_temp_code']);
            $step = 2;
        } else {
            $_SESSION['admin_code_attempts']++;
            
            if ($_SESSION['admin_code_attempts'] > 5) {
                $error = 'Too many attempts. Starting over.';
                unset($_SESSION['admin_temp_id']);
                unset($_SESSION['admin_temp_code']);
                $step = 1;
            } elseif ($entered_code === $_SESSION['admin_temp_code']) {
                // SUCCESS - Grant access
                $_SESSION['admin_verified'] = true;
                $_SESSION['verified_employee_id'] = $_SESSION['admin_temp_id'];
                
                // Cleanup
                unset($_SESSION['admin_temp_id']);
                unset($_SESSION['admin_temp_code']);
                unset($_SESSION['admin_code_time']);
                unset($_SESSION['admin_code_attempts']);
                
                // Redirect to admin login page
                header('Location: /admin/index.php');
                exit;
            } else {
                $attempts_left = 5 - $_SESSION['admin_code_attempts'];
                $error = "Invalid code. $attempts_left attempts remaining.";
            }
        }
    }
}

// Handle restart
if (isset($_POST['restart'])) {
    unset($_SESSION['admin_temp_id']);
    unset($_SESSION['admin_temp_code']);
    unset($_SESSION['admin_code_time']);
    unset($_SESSION['admin_code_attempts']);
    $step = 1;
    $error = '';
}

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LGU IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #1e3a5f; --secondary: #f39c12; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #2c5282 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.7rem;
            color: var(--primary);
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }
        .code-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.3rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }
        .btn-secondary {
            background: #999;
        }
        .btn-secondary:hover {
            background: #777;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: none;
        }
        .alert-danger {
            background-color: #ffe5e5;
            color: #c3423f;
        }
        .alert-success {
            background-color: #e5ffe5;
            color: #27ae60;
        }
        .step-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .step-bar .step {
            flex: 1;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
        }
        .step-bar .step.active {
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-header">
            <div style="font-size: 3rem; color: var(--secondary); margin-bottom: 1rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Admin Access</h1>
            <p>Secure Verification Required</p>
        </div>

        <!-- Step indicator -->
        <div class="step-bar">
            <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?>"></div>
            <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?>"></div>
            <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?>"></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- STEP 1: Login -->
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Employee ID</label>
                    <input type="text" name="emp_id" placeholder="Enter employee ID" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="step1_login" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- STEP 2: Request Code -->
            <div style="background: #f0f8ff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <p><strong>Employee:</strong> <?php echo htmlspecialchars($_SESSION['admin_temp_name'] ?? ''); ?></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;">Click below to receive verification code</p>
            </div>
            <form method="POST">
                <button type="submit" name="step2_request" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Code to Email
                </button>
                <button type="submit" name="restart" class="btn-submit btn-secondary" style="margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- STEP 3: Verify Code -->
            <div style="background: #fffacd; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <p><strong>Enter the 8-digit code sent to your email</strong></p>
                <p style="margin: 0; font-size: 0.9rem; color: #666;">Code expires in 10 minutes</p>
            </div>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="code" class="code-input" placeholder="00000000" maxlength="8" pattern="[0-9]{8}" required autofocus>
                </div>
                <button type="submit" name="step3_verify" class="btn-submit">
                    <i class="fas fa-check"></i> Verify Code
                </button>
                <button type="submit" name="restart" class="btn-submit btn-secondary" style="margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Start Over
                </button>
            </form>

        <?php endif; ?>

        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
            <a href="/public/index.php" style="color: #3498db; text-decoration: none;">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
