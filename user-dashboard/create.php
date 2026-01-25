<?php
// Include security functions
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Add no-cache headers
set_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($db->connect_error) {
        die('Database connection failed: ' . $db->connect_error);
    }

    // Get form data
    $first_name = trim($_POST['firstName']);
    $middle_name = trim($_POST['middleName']);
    $last_name = trim($_POST['lastName']);
    $suffix = $_POST['suffix'];
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $civil_status = $_POST['civilStatus'];
    $address = trim($_POST['address']);
    $id_type = $_POST['idType'];
    $id_number = trim($_POST['idNumber']);
    $password = trim($_POST['password']);
    $confirm_password = $_POST['confirmPassword'];

    // Validate
    $errors = [];
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $errors[] = 'Required fields missing.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password too short.';
    }
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Email already registered.';
    }
    $stmt->close();

    if (empty($errors)) {
        // Handle file upload
        $id_upload = '';
        if (isset($_FILES['idUpload']) && $_FILES['idUpload']['error'] == 0) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $file_name = uniqid() . '_' . basename($_FILES['idUpload']['name']);
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['idUpload']['tmp_name'], $file_path)) {
                $id_upload = $file_path;
            }
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert
        $stmt = $db->prepare("INSERT INTO users (first_name, middle_name, last_name, suffix, email, mobile, birthdate, gender, civil_status, address, id_type, id_number, id_upload, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssssssssss', $first_name, $middle_name, $last_name, $suffix, $email, $mobile, $birthdate, $gender, $civil_status, $address, $id_type, $id_number, $id_upload, $hashed_password);
        if ($stmt->execute()) {
            header('Location: login.php?success=1');
            exit;
        } else {
            $errors[] = 'Registration failed.';
        }
        $stmt->close();
    }
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Create Account</title>
<link rel="icon" type="image/png" href="logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<?php echo get_app_config_script(); ?>
<script src="security-no-back.js?v=<?php echo time(); ?>"></script>
<style>
body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding-top: 80px;
    padding-bottom: 80px;

    /* background image + blur */
    background: url("cityhall.jpeg") center/cover no-repeat fixed;
    position: relative;
}

/* Blur overlay */
body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    backdrop-filter: blur(6px);
    background: rgba(0, 0, 0, 0.35);
    z-index: 0;
}

/* Ensure content is above the blur */
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

/* Fixed wrapper - no scroll */
.wrapper {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px;
    overflow: visible;
}

/* Card layout (auto height; page scroll instead of inner scroll) */
.wrapper .card {
    width: 620px;
    max-width: 95%;
    padding: 28px 36px 28px 36px;
    display: flex;
    flex-direction: column;
    margin: 0 auto;
}

/* Card header (logo, title, subtitle) */
.card-header {
    flex-shrink: 0;
    text-align: center;
    margin-bottom: 12px;
}

.card-header .icon-top {
    width: 42px;
    margin-bottom: 4px;
}

.card-header .title {
    font-size: 20px;
    margin-bottom: 2px;
}

.card-header .subtitle {
    font-size: 12px;
    margin-bottom: 0;
}

/* Progress indicator */
.progress-container {
    flex-shrink: 0;
    margin-bottom: 12px;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.progress-step {
    flex: 1;
    text-align: center;
    position: relative;
}

.progress-step-circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(40, 100, 239, 0.2);
    border: 2px solid rgba(40, 100, 239, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 5px;
    font-weight: 600;
    font-size: 13px;
    color: #666;
    transition: all 0.3s ease;
}

.progress-step.active .progress-step-circle {
    background: linear-gradient(135deg, #6384d2, #285ccd);
    border-color: #285ccd;
    color: #fff;
    box-shadow: 0 4px 12px rgba(40, 100, 239, 0.3);
}

.progress-step.completed .progress-step-circle {
    background: #0a7e3d;
    border-color: #0a7e3d;
    color: #fff;
}

.progress-step-label {
    font-size: 10px;
    color: #666;
    font-weight: 500;
}

.progress-step.active .progress-step-label {
    color: #2864ef;
    font-weight: 600;
}

.progress-step.completed .progress-step-label {
    color: #0a7e3d;
}

/* Progress bar */
.progress-bar-container {
    width: 100%;
    height: 4px;
    background: rgba(40, 100, 239, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6384d2, #285ccd);
    transition: width 0.4s ease;
    border-radius: 2px;
}

/* Form content area - no inner scrollbar, let page scroll */
.form-content {
    flex: 1;
    margin-bottom: 16px;
}

/* Custom scrollbar */
.form-content::-webkit-scrollbar {
    width: 6px;
}

.form-content::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.form-content::-webkit-scrollbar-thumb {
    background: rgba(40, 100, 239, 0.3);
    border-radius: 3px;
}

.form-content::-webkit-scrollbar-thumb:hover {
    background: rgba(40, 100, 239, 0.5);
}

/* Step container */
.form-step {
    display: none;
    animation: fadeIn 0.3s ease;
}

.form-step.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form navigation buttons */
.form-navigation {
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding-top: 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    padding: 10px 20px;
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: rgba(0, 0, 0, 0.1);
}

.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    padding: 10px 20px;
    background: linear-gradient(135deg, #6384d2, #285ccd);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0;
    width: auto;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4d76d6, #1651d0);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(43, 91, 222, 0.45);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Form inputs styling */
.wrapper .card form input, 
.wrapper .card form select { 
    width: 100%; 
    padding: 9px; 
    margin: 4px 0 10px 0; 
    font-size: 13px;
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.15);
    background: rgba(255, 255, 255, 0.9);
}

.wrapper .card form label { 
    font-size: 13px; 
    font-weight: 500;
    display: block;
    margin-bottom: 3px;
}

/* Enhanced file upload styling */
.file-upload-wrapper {
    position: relative;
    width: 100%;
    margin: 4px 0 10px 0;
}

.file-upload-input {
    display: none;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px;
    border: 2px dashed #2864ef;
    border-radius: 6px;
    background: linear-gradient(135deg, rgba(40, 100, 239, 0.05) 0%, rgba(40, 100, 239, 0.02) 100%);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 13px;
    color: #2864ef;
}

.file-upload-label:hover {
    border-color: #1e4fbf;
    background: linear-gradient(135deg, rgba(40, 100, 239, 0.1) 0%, rgba(40, 100, 239, 0.05) 100%);
}

.file-upload-label.active {
    border-color: #0a7e3d;
    background: linear-gradient(135deg, rgba(10, 126, 61, 0.1) 0%, rgba(10, 126, 61, 0.05) 100%);
    color: #0a7e3d;
}

.file-upload-label svg {
    width: 20px;
    height: 20px;
    stroke-width: 2;
}

.file-name-display {
    font-size: 12px;
    color: #666;
    margin-top: 8px;
    padding: 8px;
    background: #f5f5f5;
    border-radius: 4px;
    display: none;
}

.file-name-display.show {
    display: block;
}

.file-name-display .check-icon {
    color: #0a7e3d;
    margin-right: 6px;
}

.invalid {
    border: 2px solid #d93025 !important;
    box-shadow: 0 0 0 3px rgba(217,48,37,0.06);
    transition: box-shadow 0.18s ease, border-color 0.18s ease;
}

.error-tip {
    font-size: 12px; 
    color: #b00; 
    margin-top: 6px;
}

/* password strength bar */
.pwd-bar {
    width: 100%;
    height: 8px;
    background: #eee;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 6px;
}

.pwd-fill {
    width: 0%;
    height: 100%;
    transition: width 380ms cubic-bezier(.2,.8,.2,1), background 300ms ease;
    background: linear-gradient(90deg,#ff4d4f,#ffb86b);
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}

.shake {
    animation: shake 420ms ease;
}

.small-text { 
    margin-top: 12px; 
    font-size: 12px;
    text-align: center;
    flex-shrink: 0;
    padding-bottom: 4px;
}

/* Two-column form grid for some steps */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 14px;
    align-items: start;
}

.form-grid .input-box { 
    margin-bottom: 0;
}

.input-box {
    margin-bottom: 10px;
}

.input-box.full-width {
    grid-column: 1 / -1;
}

@media (max-width: 700px) {
    body {
        padding-top: 55px;
        padding-bottom: 55px;
    }
    
    .nav {
        height: 55px;
        padding: 10px 20px;
    }
    
    .footer {
        height: 55px;
        padding: 10px 20px;
    }
    
    .form-grid { 
        grid-template-columns: 1fr; 
        gap: 12px; 
    }
    
    .wrapper {
        padding: 20px 10px;
        align-items: stretch;
    }
    
    .wrapper .card { 
        padding: 20px 18px 22px 18px;
        width: 100%;
    }
    
    .progress-step-label {
        font-size: 9px;
    }
    
    .progress-step-circle {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
    
    .card-header .icon-top {
        width: 40px;
        margin-bottom: 6px;
    }
    
    .card-header .title {
        font-size: 20px;
    }
}

/* Step titles */
.step-title {
    font-size: 16px;
    font-weight: 600;
    color: #000;
    margin-bottom: 4px;
}

.step-subtitle {
    font-size: 11px;
    color: #666;
    margin-bottom: 12px;
}
</style>
</head>
<body>
<header class="nav">
    <div class="nav-logo">🏛️ Local Government Unit Portal</div>
    <div class="nav-links">
        <a href="dashboard/dashboard.php">Home</a>
    </div>
</header>

<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <img src="logocityhall.png" class="icon-top">
            <h2 class="title">Create Account</h2>
            <p class="subtitle">Register to access the LGU Portal</p>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-step active" data-step="1">
                    <div class="progress-step-circle">1</div>
                    <div class="progress-step-label">Basic Info</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="progress-step-circle">2</div>
                    <div class="progress-step-label">Contact</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="progress-step-circle">3</div>
                    <div class="progress-step-label">Personal</div>
                </div>
                <div class="progress-step" data-step="4">
                    <div class="progress-step-circle">4</div>
                    <div class="progress-step-label">ID</div>
                </div>
                <div class="progress-step" data-step="5">
                    <div class="progress-step-circle">5</div>
                    <div class="progress-step-label">Security</div>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progressBar" style="width: 20%"></div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="registerForm" novalidate>
            <div class="form-content">
                <!-- Step 1: Basic Information -->
                <div class="form-step active" data-step="1">
                    <h3 class="step-title">Basic Information</h3>
                    <p class="step-subtitle">Let's start with your name</p>
                    
                    <div class="form-grid">
                        <div class="input-box">
                            <label for="firstName">First Name *</label>
                            <input id="firstName" name="firstName" type="text" required aria-required="true" placeholder="First name" />
                        </div>

                        <div class="input-box">
                            <label for="middleName">Middle Name</label>
                            <input id="middleName" name="middleName" type="text" placeholder="Middle name" />
                        </div>

                        <div class="input-box">
                            <label for="lastName">Last Name *</label>
                            <input id="lastName" name="lastName" type="text" required aria-required="true" placeholder="Last name" />
                        </div>

                        <div class="input-box">
                            <label for="suffix">Suffix <small style="font-weight:normal;">(optional)</small></label>
                            <select id="suffix" name="suffix" aria-label="Name suffix">
                                <option value="">None</option>
                                <option value="jr">Jr.</option>
                                <option value="sr">Sr.</option>
                                <option value="ii">II</option>
                                <option value="iii">III</option>
                                <option value="iv">IV</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Contact Information -->
                <div class="form-step" data-step="2">
                    <h3 class="step-title">Contact Information</h3>
                    <p class="step-subtitle">How can we reach you?</p>
                    
                    <div class="input-box">
                        <label for="email">Email Address *</label>
                        <input id="email" name="email" type="email" required aria-required="true" placeholder="you@example.com" />
                    </div>

                    <div class="input-box">
                        <label for="mobile">Mobile Number *</label>
                        <input id="mobile" name="mobile" type="tel" inputmode="tel" pattern="\+?[0-9\s-]{7,15}" required aria-required="true" placeholder="+63 9XX XXX XXXX" />
                    </div>
                </div>

                <!-- Step 3: Personal Details -->
                <div class="form-step" data-step="3">
                    <h3 class="step-title">Personal Details</h3>
                    <p class="step-subtitle">Tell us more about yourself</p>
                    
                    <div class="form-grid">
                        <div class="input-box">
                            <label for="birthdate">Birthdate</label>
                            <input id="birthdate" name="birthdate" type="date" />
                        </div>

                        <div class="input-box">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" required aria-required="true" aria-label="Gender">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                                <option value="prefer_not">Prefer not to say</option>
                            </select>
                        </div>

                        <div class="input-box full-width">
                            <label for="civilStatus">Civil Status *</label>
                            <select id="civilStatus" name="civilStatus" required aria-required="true">
                                <option value="">Select</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="widowed">Widowed</option>
                                <option value="separated">Separated</option>
                            </select>
                        </div>

                        <div class="input-box full-width">
                            <label for="address">Address / Barangay *</label>
                            <input id="address" name="address" type="text" required aria-required="true" placeholder="Street, Barangay, City" />
                        </div>
                    </div>
                </div>

                <!-- Step 4: Identification -->
                <div class="form-step" data-step="4">
                    <h3 class="step-title">Identification</h3>
                    <p class="step-subtitle">Verify your identity (optional)</p>
                    
                    <div class="form-grid">
                        <div class="input-box">
                            <label for="idType">ID Type <small style="font-weight:normal;">(optional)</small></label>
                            <select id="idType" name="idType" aria-label="ID Type">
                                <option value="">Select ID Type</option>
                                <option value="nbi">NBI Clearance</option>
                                <option value="passport">Passport</option>
                                <option value="drivinglicense">Driver's License</option>
                                <option value="sss">SSS</option>
                                <option value="tin">TIN</option>
                                <option value="barangayid">Barangay ID</option>
                                <option value="seniorid">Senior Citizen ID</option>
                                <option value="pwdid">PWD ID</option>
                            </select>
                        </div>

                        <div class="input-box">
                            <label for="idNumber">ID Number <small style="font-weight:normal;">(optional)</small></label>
                            <input id="idNumber" name="idNumber" type="text" placeholder="e.g., 12345678" />
                        </div>

                        <div class="input-box full-width">
                            <label style="display: block; margin-bottom: 8px;">Upload ID Copy <small style="font-weight:normal;">(optional, JPG/PNG, max 5MB)</small></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="idUpload" name="idUpload" class="file-upload-input" accept=".jpg,.jpeg,.png" />
                                <label for="idUpload" class="file-upload-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span id="uploadText">Click to upload or drag & drop</span>
                                </label>
                                <div class="file-name-display" id="fileDisplay">
                                    <span class="check-icon">✓</span><span id="fileName"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Account Security -->
                <div class="form-step" data-step="5">
                    <h3 class="step-title">Account Security</h3>
                    <p class="step-subtitle">Create a strong password</p>
                    
                    <div class="input-box">
                        <label for="password">Password *</label>
                        <input id="password" name="password" type="password" required aria-required="true" aria-describedby="pwdHelp pwdStrength" placeholder="Create a strong password" autocomplete="new-password" />
                        <div id="pwdHelp" style="font-size:12px;color:#666;margin-top:8px;">Requirements: 8–12 characters, uppercase, lowercase, number, special character</div>
                        <meter id="pwdStrength" min="0" max="4" low="2" high="3" optimum="4" value="0" style="width:100%;margin-top:8px; display:none;"></meter>
                        <div class="pwd-bar" aria-hidden="true"><div class="pwd-fill" id="pwdFill"></div></div>
                    </div>

                    <div class="input-box">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input id="confirmPassword" name="confirmPassword" type="password" required aria-required="true" placeholder="Re-enter your password" autocomplete="new-password" />
                    </div>

                    <div style="margin-top: 20px; text-align: center;">
                        <label style="font-size:13px; display: block;">
                            <input id="privacyAgree" type="checkbox" required aria-required="true" style="margin-right:6px;" />
                            <span>I agree to the <a href="#" style="color:#2864ef;">Privacy Notice</a> and data usage policy</span>
                        </label>
                        <div style="font-size:12px; color:#666; margin-top:4px;">(Data Privacy Act of 2012)</div>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="form-navigation">
                <button type="button" class="btn-secondary" id="prevBtn" disabled>Previous</button>
                <div id="regMessage" role="status" aria-live="polite" style="flex: 1; padding: 0 12px; font-size: 13px; display: none;"></div>
                <button type="button" class="btn-primary" id="nextBtn">Next</button>
            </div>
        </form>

        <?php if (!empty($errors)): ?>
        <div style="color:#b00; margin-top:12px;">
            <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="small-text">Already have an account? <a href="login.php">Sign in here</a></p>
    </div>
</div>

<footer class="footer">
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">About</a>
        <a href="#">Help</a>
    </div>
    <div class="footer-logo">© 2025 LGU Citizen Portal · All Rights Reserved</div>
</footer>

<script>
// Multi-step form state
let currentStep = 1;
const totalSteps = 5;

// Password Policy Validator
function validatePassword(p){
    const errors = [];
    if(p.length < 8) errors.push('Password must be at least 8 characters.');
    if(!/[a-z]/.test(p)) errors.push('Include a lowercase letter.');
    if(!/[A-Z]/.test(p)) errors.push('Include an uppercase letter.');
    if(!/[0-9]/.test(p)) errors.push('Include a number.');
    if(!/[!@#\$%\^&\*\(\)_\+\-=\[\]\{\};:\"\\|,.<>\/?]/.test(p)) errors.push('Include a special character.');
    return { ok: errors.length===0, errors };
}

// Live Password Strength Meter + animated bar
const pwdInput = document.getElementById('password');
const meter = document.getElementById('pwdStrength');
const pwdFill = document.getElementById('pwdFill');

function setPwdFill(score){
    const pct = Math.round((score / 4) * 100);
    if(pwdFill){
        pwdFill.style.width = pct + '%';
        if(score <= 1) pwdFill.style.background = 'linear-gradient(90deg,#ff4d4f,#ff7a59)';
        else if(score === 2) pwdFill.style.background = 'linear-gradient(90deg,#ffb86b,#ffd54a)';
        else if(score === 3) pwdFill.style.background = 'linear-gradient(90deg,#cddc39,#8bc34a)';
        else pwdFill.style.background = 'linear-gradient(90deg,#7be495,#4caf50)';
    }
}

if(pwdInput){
    pwdInput.addEventListener('input', function(){
        const val = pwdInput.value || '';
        let score = 0;
        if(val.length >= 8) score++;
        if(/[A-Z]/.test(val)) score++;
        if(/[0-9]/.test(val)) score++;
        if(/[^A-Za-z0-9]/.test(val)) score++;
        if(meter) meter.value = score;
        setPwdFill(score);
    });
}

// Helper functions
function markInvalid(el){
    if(!el) return;
    try{ el.classList.add('invalid'); }catch(e){}
    try{ el.focus({preventScroll:true}); }catch(e){}
    try{ el.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
}

function markValid(el){ 
    if(!el) return; 
    try{ el.classList.remove('invalid'); }catch(e){} 
}

// Clear invalid marker when user types
const emailInputField = document.getElementById('email');
if(emailInputField){
    emailInputField.addEventListener('input', () => { 
        markValid(emailInputField); 
        const m = document.getElementById('regMessage'); 
        if(m) m.style.display = 'none'; 
    });
}

// File Upload Handling
const idUploadInput = document.getElementById('idUpload');
const uploadLabel = document.querySelector('.file-upload-label');
const fileDisplay = document.getElementById('fileDisplay');
const fileName = document.getElementById('fileName');
const uploadText = document.getElementById('uploadText');

if(idUploadInput && uploadLabel){
    idUploadInput.addEventListener('change', handleFileSelect);
    
    uploadLabel.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadLabel.classList.add('active');
    });
    
    uploadLabel.addEventListener('dragleave', () => {
        uploadLabel.classList.remove('active');
    });
    
    uploadLabel.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadLabel.classList.remove('active');
        const files = e.dataTransfer.files;
        if(files.length > 0) idUploadInput.files = files;
        handleFileSelect();
    });
}

function handleFileSelect(){
    const file = idUploadInput.files[0];
    if(file){
        fileName.textContent = file.name;
        fileDisplay.classList.add('show');
        uploadLabel.classList.add('active');
        uploadText.textContent = '✓ File selected';
    } else {
        fileDisplay.classList.remove('show');
        uploadLabel.classList.remove('active');
        uploadText.textContent = 'Click to upload or drag & drop';
    }
}

// Step Navigation
function updateProgress() {
    const progressBar = document.getElementById('progressBar');
    const progressSteps = document.querySelectorAll('.progress-step');
    const percentage = (currentStep / totalSteps) * 100;
    
    progressBar.style.width = percentage + '%';
    
    progressSteps.forEach((step, index) => {
        const stepNum = index + 1;
        if(stepNum < currentStep) {
            step.classList.add('completed');
            step.classList.remove('active');
        } else if(stepNum === currentStep) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            step.classList.remove('active', 'completed');
        }
    });
}

function showStep(step) {
    const formSteps = document.querySelectorAll('.form-step');
    formSteps.forEach((formStep, index) => {
        if(index + 1 === step) {
            formStep.classList.add('active');
        } else {
            formStep.classList.remove('active');
        }
    });
    
    // Update buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    prevBtn.disabled = step === 1;
    
    if(step === totalSteps) {
        nextBtn.textContent = 'Create Account';
    } else {
        nextBtn.textContent = 'Next';
    }
    
    updateProgress();
}

// Validate current step
function validateStep(step) {
    const msgEl = document.getElementById('regMessage');
    msgEl.style.display = 'none';
    
    if(step === 1) {
        // Basic Information
        const firstName = (document.getElementById('firstName').value || '').trim();
        const lastName = (document.getElementById('lastName').value || '').trim();
        
        if(!firstName) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> First name is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('firstName'));
            return false;
        }
        
        if(!lastName) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Last name is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('lastName'));
            return false;
        }
    }
    
    if(step === 2) {
        // Contact Information
        let email = (document.getElementById('email').value || '').trim().toLowerCase();
        email = email.replace(/[\u200B-\u200D\uFEFF]/g, '');
        const mobile = (document.getElementById('mobile').value || '').trim();
        
        if(!email) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Email is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('email'));
            return false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(!emailRegex.test(email)){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Enter a valid email address.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('email'));
            return false;
        }
        
        if(!mobile) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Mobile number is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('mobile'));
            return false;
        }
        
        const mobileRegex = /^\+?[0-9\s-]{7,15}$/;
        if(!mobileRegex.test(mobile)){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Enter a valid mobile number.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('mobile'));
            return false;
        }
    }
    
    if(step === 3) {
        // Personal Details
        const address = (document.getElementById('address').value || '').trim();
        const gender = document.getElementById('gender').value;
        const civilStatus = document.getElementById('civilStatus').value;
        
        if(!address) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Address is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('address'));
            return false;
        }
        
        if(!gender) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Gender is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('gender'));
            return false;
        }
        
        if(!civilStatus) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Civil status is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('civilStatus'));
            return false;
        }
    }
    
    if(step === 4) {
        // File validation
        const idUploadFile = document.getElementById('idUpload').files[0] || null;
        
        if(idUploadFile && idUploadFile.size > 5 * 1024 * 1024){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> ID file must be less than 5MB.';
            msgEl.style.display = 'block';
            markInvalid(uploadLabel);
            return false;
        }
        
        if(idUploadFile && !['image/jpeg', 'image/png'].includes(idUploadFile.type)){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> ID file must be JPG or PNG.';
            msgEl.style.display = 'block';
            markInvalid(uploadLabel);
            return false;
        }
    }
    
    if(step === 5) {
        // Password validation
        const rawPassword = document.getElementById('password').value || '';
        const rawConfirm = document.getElementById('confirmPassword').value || '';
        const password = rawPassword.replace(/[\u200B-\u200D\uFEFF]/g, '');
        const confirmPwd = rawConfirm.replace(/[\u200B-\u200D\uFEFF]/g, '');
        const privacyAgree = document.getElementById('privacyAgree').checked;
        
        if(!password) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Password is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('password'));
            return false;
        }
        
        if(!confirmPwd) {
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Confirm password is required.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('confirmPassword'));
            return false;
        }
        
        if(password !== confirmPwd){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> Passwords do not match.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('password'));
            markInvalid(document.getElementById('confirmPassword'));
            const bar = document.querySelector('.pwd-bar'); 
            if(bar){ 
                bar.classList.add('shake'); 
                setTimeout(()=>bar.classList.remove('shake'),420); 
            }
            return false;
        }
        
        const passCheck = validatePassword(password);
        if(!passCheck.ok){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️ Password Issues:</strong><br>' + passCheck.errors.join('<br>');
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('password'));
            const bar2 = document.querySelector('.pwd-bar'); 
            if(bar2){ 
                bar2.classList.add('shake'); 
                setTimeout(()=>bar2.classList.remove('shake'),420); 
            }
            return false;
        }
        
        if(!privacyAgree){
            msgEl.style.backgroundColor = '#fee'; 
            msgEl.style.color = '#c00';
            msgEl.innerHTML = '<strong>⚠️</strong> You must agree to the Privacy Notice.';
            msgEl.style.display = 'block';
            markInvalid(document.getElementById('privacyAgree'));
            return false;
        }
    }
    
    return true;
}

// Next button handler
document.getElementById('nextBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    if(currentStep === totalSteps) {
        // This is the submit button on last step
        handleSubmit(e);
    } else {
        // Validate current step
        if(validateStep(currentStep)) {
            currentStep++;
            showStep(currentStep);
        }
    }
});

// Previous button handler
document.getElementById('prevBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    if(currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
});

// Form submission
function handleSubmit(e) {
    e.preventDefault();
    
    // Validate final step
    if(!validateStep(currentStep)) {
        return;
    }
    
    // Submit the form
    document.getElementById('registerForm').submit();
}

// Initialize
showStep(currentStep);
</script>
</body>
</html>
