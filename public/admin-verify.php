<?php
/**
 * Enhanced Admin Access Verification Page
 * Three-layer security verification before admin access
 */

// Enable secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// DATABASE CONNECTION
$db = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}
$db->set_charset("utf8mb4");

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

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

$error = '';
$success = '';
$step = 1; // Default to Step 1
$debug_info = [];

// Determine current step based on session state
$debug_info[] = "SESSION temp_employee_id: " . (isset($_SESSION['temp_employee_id']) ? $_SESSION['temp_employee_id'] : 'NOT SET');
$debug_info[] = "SESSION admin_verification_code: " . (isset($_SESSION['admin_verification_code']) ? 'SET' : 'NOT SET');
$debug_info[] = "REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'];
$debug_info[] = "POST verify_credentials: " . (isset($_POST['verify_credentials']) ? 'YES' : 'NO');
$debug_info[] = "POST request_code: " . (isset($_POST['request_code']) ? 'YES' : 'NO');
$debug_info[] = "POST verify_code: " . (isset($_POST['verify_code']) ? 'YES' : 'NO');
$debug_info[] = "ALL SESSION VARS: " . json_encode($_SESSION);

if (isset($_SESSION['temp_employee_id'])) {
    // User has passed Step 1, check if they've requested code
    if (isset($_SESSION['admin_verification_code'])) {
        $step = 3; // Code has been sent, user needs to enter it
    } else {
        $step = 2; // Credentials verified, waiting for code request
    }
} else {
    $step = 1; // Default - not yet verified
}

$debug_info[] = "Determined step: " . $step;

// Handle restart button
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restart'])) {
    unset($_SESSION['temp_employee_id']);
    unset($_SESSION['temp_employee_email']);
    unset($_SESSION['temp_employee_name']);
    unset($_SESSION['admin_verification_code']);
    unset($_SESSION['admin_verification_time']);
    unset($_SESSION['admin_verification_attempts']);
    $step = 1;
    $error = '';
    $success = '';
}

// STEP 1: Verify Employee ID and Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_credentials'])) {
    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $debug_info[] = "STEP 1 FORM HANDLER - ID: $employee_id";
    
    if (empty($employee_id) || empty($password)) {
        $error = 'Please enter both Employee ID and password.';
        $step = 1;
        $debug_info[] = "STEP 1 ERROR: Empty ID or password";
    } else {
        // Query the employees table for this employee
        $stmt = $db->prepare("SELECT id, email, first_name, last_name, password FROM employees WHERE id = ? OR email = ?");
        
        if ($stmt) {
            $stmt->bind_param('ss', $employee_id, $employee_id);
            if (!$stmt->execute()) {
                $error = 'Database query error: ' . $stmt->error;
                $step = 1;
                $debug_info[] = "STEP 1 ERROR: " . $stmt->error;
            } else {
                $result = $stmt->get_result();
                $debug_info[] = "STEP 1: DB returned " . $result->num_rows . " rows";
                
                if ($result->num_rows > 0) {
                    $employee = $result->fetch_assoc();
                    $debug_info[] = "STEP 1: Found employee ID " . $employee['id'];
                    $debug_info[] = "STEP 1: Employee email: " . $employee['email'];
                    $debug_info[] = "STEP 1: DB password hash: " . substr($employee['password'], 0, 20) . "...";
                    $debug_info[] = "STEP 1: Entered password: '$password'";
                    
                    // Verify password
                    $password_match = password_verify($password, $employee['password']);
                    $debug_info[] = "STEP 1: password_verify result: " . ($password_match ? 'TRUE' : 'FALSE');
                    
                    if ($password_match) {
                        // Credentials verified! Store temporarily and move to step 2
                        $_SESSION['temp_employee_id'] = $employee['id'];
                        $_SESSION['temp_employee_email'] = $employee['email'];
                        $_SESSION['temp_employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                        $_SESSION['temp_verification_time'] = time();
                        
                        // FORCE SESSION SAVE
                        session_write_close();
                        session_start();
                        
                        $debug_info[] = "STEP 1: PASSWORD VERIFIED!";
                        $debug_info[] = "STEP 1: Set temp_employee_id = " . $_SESSION['temp_employee_id'];
                        
                        $step = 2;
                        $success = 'Credentials verified. Please request verification code.';
                    } else {
                        $error = 'Invalid password. Please try again.';
                        $step = 1;
                        $debug_info[] = "STEP 1 ERROR: Invalid password";
                    }
                } else {
                    $error = 'Employee not found. Please check your Employee ID.';
                    $step = 1;
                    $debug_info[] = "STEP 1 ERROR: Employee not found";
                }
                $stmt->close();
            }
        } else {
            $error = 'Database error: ' . $db->error;
            $step = 1;
            $debug_info[] = "STEP 1 ERROR: " . $db->error;
        }
    }
}

// STEP 2: Send verification code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_code'])) {
    // Check if credentials were already verified in this session
    if (!isset($_SESSION['temp_employee_email'])) {
        $error = 'Please verify your credentials first.';
        $step = 1;
    } else {
        // Generate 8-digit verification code (stronger than 6 digits)
        $verification_code = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Store in session (valid for 10 minutes)
        $_SESSION['admin_verification_code'] = $verification_code;
        $_SESSION['admin_verification_time'] = time();
        $_SESSION['admin_verification_attempts'] = 0;
        
        // In production, send email with code
        // For demo, show code on screen
        $success = "Verification code sent to " . substr($_SESSION['temp_employee_email'], 0, 3) . "***. (Demo: <strong>" . $verification_code . "</strong>)";
        $step = 3;
    }
}

// STEP 3: Verify code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    $debug_info[] = "STEP 3 FORM HANDLER TRIGGERED";
    
    $entered_code = trim($_POST['code'] ?? '');
    $debug_info[] = "STEP 3: Entered code: '$entered_code'";
    $debug_info[] = "STEP 3: Session code: '" . ($_SESSION['admin_verification_code'] ?? 'NOT SET') . "'";
    
    // Check if all previous steps were completed
    if (!isset($_SESSION['admin_verification_code']) || !isset($_SESSION['temp_employee_id'])) {
        $error = 'Session expired. Please start over.';
        $step = 1;
        $debug_info[] = "STEP 3 ERROR: Session expired";
    } else {
        // Check if code is expired (10 minutes)
        $elapsed = time() - $_SESSION['admin_verification_time'];
        $debug_info[] = "STEP 3: Time elapsed: $elapsed seconds";
        
        if ($elapsed > 600) {
            $error = 'Verification code expired. Please request a new one.';
            unset($_SESSION['admin_verification_code']);
            $step = 2;
            $debug_info[] = "STEP 3 ERROR: Code expired";
        } else {
            // Check attempts (max 5 attempts)
            $_SESSION['admin_verification_attempts'] = ($_SESSION['admin_verification_attempts'] ?? 0) + 1;
            $debug_info[] = "STEP 3: Attempt #" . $_SESSION['admin_verification_attempts'];
            
            if ($_SESSION['admin_verification_attempts'] > 5) {
                $error = 'Too many failed attempts. Please start over.';
                unset($_SESSION['admin_verification_code']);
                unset($_SESSION['temp_employee_id']);
                unset($_SESSION['temp_employee_email']);
                $step = 1;
                $debug_info[] = "STEP 3 ERROR: Too many attempts";
            } elseif ($entered_code === $_SESSION['admin_verification_code']) {
                $debug_info[] = "STEP 3: CODE MATCH! Access granted!";
                // All verifications passed! Grant admin access
                $_SESSION['admin_verified'] = true;
                $_SESSION['admin_verified_time'] = time();
                $_SESSION['verified_employee_id'] = $_SESSION['temp_employee_id'];
                
                // Clean up temporary data
                unset($_SESSION['temp_employee_id']);
                unset($_SESSION['temp_employee_email']);
                unset($_SESSION['temp_employee_name']);
                unset($_SESSION['admin_verification_code']);
                unset($_SESSION['admin_verification_time']);
                
                $debug_info[] = "STEP 3: Redirecting to /admin/manage-employees.php";
                
                // Redirect to manage employees page
                header('Location: /admin/manage-employees.php');
                exit;
            } else {
                $error = 'Invalid verification code. Please try again. (' . (5 - $_SESSION['admin_verification_attempts']) . ' attempts remaining)';
                $step = 3;
                $debug_info[] = "STEP 3 ERROR: Code mismatch";
                $debug_info[] = "STEP 3: Expected: '" . $_SESSION['admin_verification_code'] . "' Got: '$entered_code'";
            }
        }
    }
}

$db->close();
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

        <!-- DEBUG INFO -->
        <div style="background: #ffffcc; border: 1px solid #cccc00; padding: 10px; margin-bottom: 15px; font-size: 12px; font-family: monospace;">
            <strong>DEBUG:</strong><br>
            <?php foreach ($debug_info as $info) { echo $info . "<br>"; } ?>
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
            <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?>"></div>
        </div>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Multi-layer Security:</strong> Identification → Email Verification → Code Confirmation
        </div>

        <?php if ($step == 1): ?>
            <!-- STEP 1: Employee ID + Password -->
            <form method="POST">
                <div class="form-group">
                    <label for="employee_id">
                        <i class="fas fa-id-card"></i> Employee ID
                    </label>
                    <input 
                        type="text" 
                        id="employee_id" 
                        name="employee_id" 
                        placeholder="Enter your employee ID" 
                        required 
                        autocomplete="off"
                    />
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required 
                        autocomplete="current-password"
                    />
                </div>

                <button type="submit" name="verify_credentials" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i> Verify Identity
                </button>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- STEP 2: Request Verification Code -->
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>Identity Verified!</strong>
                <br><small>Employee: <?php echo htmlspecialchars($_SESSION['temp_employee_name'] ?? ''); ?></small>
            </div>

            <form method="POST">
                <div class="info-box" style="background: #fffacd; border-left-color: var(--secondary); margin-bottom: 1.5rem;">
                    <i class="fas fa-envelope"></i>
                    <strong>Next:</strong> A 8-digit code will be sent to your registered email address.
                </div>

                <button type="submit" name="request_code" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>

                <button type="submit" name="restart" class="submit-btn" style="background: #999; margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Start Over
                </button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- STEP 3: Enter Verification Code -->
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>Code Sent!</strong>
                <br><small>Verification code sent to <?php echo substr(htmlspecialchars($_SESSION['temp_employee_email'] ?? ''), 0, 3); ?>***</small>
            </div>

            <form method="POST" id="codeForm">
                <div class="form-group">
                    <label for="code">
                        <i class="fas fa-key"></i> Verification Code (8 digits)
                    </label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        class="code-input" 
                        placeholder="00000000" 
                        maxlength="8" 
                        pattern="[0-9]{8}" 
                        required 
                        autocomplete="off"
                    />
                </div>

                <div class="timer">
                    Code expires in: <strong>10 minutes</strong>
                </div>

                <button type="submit" name="verify_code" value="1" class="submit-btn">
                    <i class="fas fa-check"></i> Verify & Access Admin
                </button>

                <button type="button" class="submit-btn" onclick="location.reload()" style="background: #999; margin-top: 0.5rem;">
                    <i class="fas fa-arrow-left"></i> Request New Code
                </button>
            </form>

            <script>
                // Auto-format and auto-submit code input
                document.getElementById('code')?.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length === 8) {
                        // Auto-submit after 8 digits
                        setTimeout(() => {
                            document.getElementById('codeForm').submit();
                        }, 500);
                    }
                });
            </script>

        <?php endif; ?>

        <div class="back-link">
            <a href="/public/index.php">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
