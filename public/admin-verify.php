<?php
/**
 * Admin Access Verification Page
 * Provides secure verification before admin login
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/helpers.php';

// Enable secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$error = '';
$success = '';
$step = 1; // Step 1: Request verification code, Step 2: Enter verification code

// Handle verification code request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_code'])) {
    $email = sanitize_input($_POST['email'] ?? '', 'email');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Generate 6-digit verification code
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store in session (valid for 10 minutes)
        $_SESSION['admin_verification_code'] = $verification_code;
        $_SESSION['admin_verification_email'] = $email;
        $_SESSION['admin_verification_time'] = time();
        $_SESSION['admin_verification_attempts'] = 0;
        
        // In production, send email with code
        // For demo, show code on screen
        $success = "Verification code sent! (Demo: <strong>" . $verification_code . "</strong>)";
        $step = 2;
    }
}

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    $entered_code = sanitize_input($_POST['code'] ?? '');
    
    // Check if session has valid verification data
    if (!isset($_SESSION['admin_verification_code'])) {
        $error = 'Please request a verification code first.';
        $step = 1;
    } else {
        // Check if code is expired (10 minutes)
        $elapsed = time() - $_SESSION['admin_verification_time'];
        if ($elapsed > 600) {
            $error = 'Verification code expired. Please request a new one.';
            unset($_SESSION['admin_verification_code']);
            $step = 1;
        } else {
            // Check attempts (max 5 attempts)
            $_SESSION['admin_verification_attempts'] = ($_SESSION['admin_verification_attempts'] ?? 0) + 1;
            
            if ($_SESSION['admin_verification_attempts'] > 5) {
                $error = 'Too many failed attempts. Please request a new code.';
                unset($_SESSION['admin_verification_code']);
                $step = 1;
            } elseif ($entered_code === $_SESSION['admin_verification_code']) {
                // Code verified! Proceed to admin login
                $_SESSION['admin_verified'] = true;
                $_SESSION['admin_verified_time'] = time();
                
                // Redirect to admin login
                header('Location: /admin/index.php');
                exit;
            } else {
                $error = 'Invalid verification code. Please try again. (' . (5 - $_SESSION['admin_verification_attempts']) . ' attempts remaining)';
                $step = 2;
            }
        }
    }
}

// Helper function to sanitize input
function sanitize_input($input, $type = 'text') {
    if (is_array($input)) {
        return array_map(function($value) use ($type) {
            return sanitize_input($value, $type);
        }, $input);
    }
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'text':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Verification - LGU IPMS</title>
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
            --danger: #e74c3c;
            --success: #27ae60;
            --info: #3498db;
            --light: #ecf0f1;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .verification-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verification-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .verification-header h1 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .verification-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .lock-icon {
            font-size: 3rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.7rem;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.95rem;
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

        .submit-btn {
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
            letter-spacing: 0.5px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            border: none;
        }

        .alert-danger {
            background-color: #ffe5e5;
            color: var(--danger);
        }

        .alert-success {
            background-color: #e5ffe5;
            color: var(--success);
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }

        .back-link a {
            color: var(--info);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: var(--primary);
        }

        .timer {
            text-align: center;
            color: #999;
            font-size: 0.85rem;
            margin-top: 1rem;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .step {
            flex: 1;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .step.active {
            background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
        }

        .info-box {
            background: #f0f8ff;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #333;
        }

        .info-box i {
            color: var(--info);
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <div class="lock-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Admin Access</h1>
            <p>Secure Verification Required</p>
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

        <div class="step-indicator">
            <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?>"></div>
            <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?>"></div>
        </div>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>For security:</strong> A verification code will be sent to your email. This protects your admin account from unauthorized access.
        </div>

        <?php if ($step == 1): ?>
            <!-- Step 1: Request Verification Code -->
            <form method="POST">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your admin email" 
                        required 
                        autocomplete="email"
                    />
                </div>

                <button type="submit" name="request_code" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>

        <?php else: ?>
            <!-- Step 2: Enter Verification Code -->
            <form method="POST">
                <div class="form-group">
                    <label for="code">
                        <i class="fas fa-key"></i> Verification Code
                    </label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        class="code-input" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="[0-9]{6}" 
                        required 
                        autocomplete="off"
                    />
                </div>

                <div class="timer">
                    Code expires in: <strong>10 minutes</strong>
                </div>

                <button type="submit" name="verify_code" class="submit-btn">
                    <i class="fas fa-check"></i> Verify & Access Admin
                </button>

                <button type="button" class="submit-btn" onclick="goBackToEmail()" style="background: #999; margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </form>

            <script>
                function goBackToEmail() {
                    location.reload();
                }
            </script>

        <?php endif; ?>

        <div class="back-link">
            <a href="/public/index.php">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format code input
        document.getElementById('code')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
