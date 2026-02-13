<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();

if (isset($_SESSION['user_id'])) {
    header('Location: /user-dashboard/user-dashboard.php');
    exit;
}

$errors = [];
$form = [
    'firstName' => '',
    'middleName' => '',
    'lastName' => '',
    'suffix' => '',
    'email' => '',
    'mobile' => '',
    'birthdate' => '',
    'gender' => '',
    'civilStatus' => '',
    'address' => '',
    'idType' => '',
    'idNumber' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } elseif (!isset($db) || $db->connect_error) {
        $errors[] = 'Database connection error. Please try again later.';
    } elseif (is_rate_limited('user_register', 6, 600)) {
        $errors[] = 'Too many registration attempts. Please wait a few minutes and try again.';
    } else {
        $form['firstName'] = trim((string) ($_POST['firstName'] ?? ''));
        $form['middleName'] = trim((string) ($_POST['middleName'] ?? ''));
        $form['lastName'] = trim((string) ($_POST['lastName'] ?? ''));
        $form['suffix'] = trim((string) ($_POST['suffix'] ?? ''));
        $form['email'] = sanitize_email((string) ($_POST['email'] ?? ''));
        $form['mobile'] = trim((string) ($_POST['mobile'] ?? ''));
        $form['birthdate'] = trim((string) ($_POST['birthdate'] ?? ''));
        $form['gender'] = trim((string) ($_POST['gender'] ?? ''));
        $form['civilStatus'] = trim((string) ($_POST['civilStatus'] ?? ''));
        $form['address'] = trim((string) ($_POST['address'] ?? ''));
        $form['idType'] = trim((string) ($_POST['idType'] ?? ''));
        $form['idNumber'] = trim((string) ($_POST['idNumber'] ?? ''));

        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

        if ($form['firstName'] === '' || $form['lastName'] === '') {
            $errors[] = 'First name and last name are required.';
        }

        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $form['mobile'])) {
            $errors[] = 'Please enter a valid mobile number.';
        }

        if ($form['gender'] === '' || !in_array($form['gender'], ['male', 'female', 'other', 'prefer_not'], true)) {
            $errors[] = 'Please select a valid gender.';
        }

        if ($form['civilStatus'] === '' || !in_array($form['civilStatus'], ['single', 'married', 'widowed', 'separated'], true)) {
            $errors[] = 'Please select a valid civil status.';
        }

        if ($form['address'] === '') {
            $errors[] = 'Address is required.';
        }

        if ($form['birthdate'] !== '') {
            $birthTs = strtotime($form['birthdate']);
            if ($birthTs === false || $birthTs > time()) {
                $errors[] = 'Please enter a valid birthdate.';
            }
        }

        if (strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        $idUploadPath = '';
        if (!empty($_FILES['idUpload']['name'])) {
            if (!isset($_FILES['idUpload']) || $_FILES['idUpload']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Unable to upload ID file. Please try again.';
            } else {
                $tmpFile = (string) $_FILES['idUpload']['tmp_name'];
                $fileSize = (int) ($_FILES['idUpload']['size'] ?? 0);
                if ($fileSize > (5 * 1024 * 1024)) {
                    $errors[] = 'ID upload must be 5MB or less.';
                } else {
                    $mime = (string) (mime_content_type($tmpFile) ?: '');
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'application/pdf' => 'pdf'
                    ];

                    if (!isset($allowed[$mime])) {
                        $errors[] = 'Only JPG, PNG, or PDF files are allowed for ID upload.';
                    }
                }
            }
        }

        if (empty($errors)) {
            $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            if (!$checkStmt) {
                $errors[] = 'Unable to process your request right now.';
            } else {
                $checkStmt->bind_param('s', $form['email']);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if ($checkRes && $checkRes->num_rows > 0) {
                    $errors[] = 'This email is already registered.';
                }
                $checkStmt->close();
            }
        }

        if (empty($errors) && !empty($_FILES['idUpload']['name'])) {
            $uploadDir = dirname(__DIR__) . '/uploads/user-ids';
            if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $errors[] = 'Unable to create upload directory.';
            } else {
                $tmpFile = (string) $_FILES['idUpload']['tmp_name'];
                $mime = (string) (mime_content_type($tmpFile) ?: '');
                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf'
                ];
                $ext = $extMap[$mime] ?? 'bin';
                $fileName = 'id_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($tmpFile, $targetPath)) {
                    $errors[] = 'Unable to save uploaded ID file.';
                } else {
                    $idUploadPath = '/uploads/user-ids/' . $fileName;
                }
            }
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $db->prepare(
                'INSERT INTO users (first_name, middle_name, last_name, suffix, email, mobile, birthdate, gender, civil_status, address, id_type, id_number, id_upload, password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$insert) {
                $errors[] = 'Unable to create account right now. Please try again later.';
            } else {
                $insert->bind_param(
                    'ssssssssssssss',
                    $form['firstName'],
                    $form['middleName'],
                    $form['lastName'],
                    $form['suffix'],
                    $form['email'],
                    $form['mobile'],
                    $form['birthdate'],
                    $form['gender'],
                    $form['civilStatus'],
                    $form['address'],
                    $form['idType'],
                    $form['idNumber'],
                    $idUploadPath,
                    $hashedPassword
                );

                if ($insert->execute()) {
                    header('Location: /user-dashboard/user-login.php?success=1');
                    exit;
                }

                $errors[] = 'Registration failed. Please try again.';
                $insert->close();
            }
        }

        if (!empty($errors)) {
            record_attempt('user_register');
        }
    }
}

if (isset($db) && $db instanceof mysqli) {
    $db->close();
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Create Account</title>
<link rel="icon" type="image/png" href="/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<?php echo get_app_config_script(); ?>
<script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
<style>
:root {
    --page-navy: #0f2a4a;
    --page-blue: #1d4e89;
    --page-sky: #3f83c9;
    --page-light: #f7fbff;
    --page-text: #0f172a;
    --page-muted: #475569;
    --page-danger: #b91c1c;
    --page-danger-bg: #fee2e2;
    --page-border: rgba(15, 23, 42, 0.12);
}
* { box-sizing: border-box; }
body.user-signup-page {
    min-height: 100vh;
    margin: 0;
    display: flex;
    flex-direction: column;
    padding-top: 88px;
    color: var(--page-text);
    background:
        radial-gradient(circle at 15% 15%, rgba(63, 131, 201, 0.28), transparent 40%),
        radial-gradient(circle at 85% 85%, rgba(29, 78, 137, 0.26), transparent 45%),
        linear-gradient(125deg, rgba(7, 20, 36, 0.72), rgba(15, 42, 74, 0.68)),
        url('/cityhall.jpeg') center/cover fixed no-repeat;
}
body.user-signup-page .nav {
    position: fixed;
    inset: 0 0 auto 0;
    width: 100%;
    height: 78px;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, rgba(255,255,255,0.94), rgba(247,251,255,0.98));
    border-bottom: 1px solid var(--page-border);
    box-shadow: 0 12px 30px rgba(2, 6, 23, 0.12);
    z-index: 30;
}
body.user-signup-page .nav-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--page-navy);
}
body.user-signup-page .nav-logo img {
    width: 44px;
    height: 44px;
    object-fit: contain;
}
body.user-signup-page .home-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 16px;
    border-radius: 10px;
    border: 1px solid rgba(29, 78, 137, 0.22);
    text-decoration: none;
    font-weight: 600;
    color: var(--page-blue);
    background: #ffffff;
}
body.user-signup-page .wrapper {
    width: 100%;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 30px 16px 36px;
}
body.user-signup-page .card {
    width: 100%;
    max-width: 920px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.75);
    border-radius: 20px;
    padding: 30px 26px;
    box-shadow: 0 24px 56px rgba(2, 6, 23, 0.3);
}
body.user-signup-page .card-header {
    text-align: center;
    margin-bottom: 18px;
}
body.user-signup-page .icon-top {
    width: 72px;
    height: 72px;
    object-fit: contain;
    margin: 2px auto 10px;
    display: block;
}
body.user-signup-page .title {
    margin: 0 0 6px;
    font-size: 1.7rem;
    line-height: 1.2;
    color: var(--page-navy);
}
body.user-signup-page .subtitle {
    margin: 0;
    color: var(--page-muted);
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.step-header {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    margin: 10px 0 16px;
}
.step-dot {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.84rem;
    background: #ffffff;
}
.step-dot.active {
    background: linear-gradient(135deg, #1d4e89, #3f83c9);
    color: #ffffff;
    border-color: transparent;
}
.step-dot.done {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
}
.form-step {
    display: block;
    opacity: 0;
    transform: translateY(12px);
    max-height: 0;
    overflow: hidden;
    pointer-events: none;
    transition: opacity 0.28s ease, transform 0.28s ease, max-height 0.32s ease;
}
.form-step.active {
    opacity: 1;
    transform: translateY(0);
    max-height: 900px;
    pointer-events: auto;
}
.step-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
    margin: 0 0 10px;
}
.form-grid .full {
    grid-column: 1 / -1;
}
.input-box {
    text-align: left;
}
.input-box label {
    display: block;
    font-size: 0.86rem;
    color: #1e293b;
    margin-bottom: 6px;
}
.input-box input,
.input-box select,
.input-box textarea {
    width: 100%;
    min-height: 46px;
    border-radius: 11px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: #ffffff;
    padding: 10px 12px;
    font-size: 0.95rem;
    color: #0f172a;
    outline: none;
}
.input-box textarea {
    min-height: 88px;
    resize: vertical;
}
.input-box input:focus,
.input-box select:focus,
.input-box textarea:focus {
    border-color: var(--page-sky);
    box-shadow: 0 0 0 4px rgba(63, 131, 201, 0.15);
}
.actions {
    margin-top: 18px;
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 12px;
}
.actions-left {
    display: flex;
    justify-content: flex-start;
}
.actions-right {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.btn-primary {
    min-width: 170px;
    height: 46px;
    border: 0;
    border-radius: 11px;
    background: linear-gradient(135deg, #1d4e89, #3f83c9);
    color: #ffffff;
    font-size: 0.98rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-secondary {
    min-width: 130px;
    height: 46px;
    border: 1px solid rgba(148, 163, 184, 0.55);
    border-radius: 11px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-link-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 130px;
    height: 42px;
    border: 1px solid rgba(148, 163, 184, 0.55);
    border-radius: 10px;
    text-decoration: none;
    color: #0f172a;
    font-weight: 600;
    font-size: 0.9rem;
}
.step-error {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 0.86rem;
    border: 1px solid rgba(185, 28, 28, 0.2);
    display: none;
}
.password-strength-wrap {
    grid-column: 1 / -1;
    margin-top: 2px;
}
.password-strength-track {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.password-strength-fill {
    height: 100%;
    width: 0%;
    border-radius: 999px;
    background: #ef4444;
    transition: width 0.28s ease, background-color 0.28s ease;
}
.password-strength-label {
    margin-top: 6px;
    font-size: 0.84rem;
    font-weight: 600;
    color: #475569;
}
.meta-links {
    margin-top: 12px;
    text-align: center;
    font-size: 0.9rem;
    color: var(--page-muted);
}
.meta-links a {
    color: var(--page-blue);
    text-decoration: none;
    font-weight: 600;
}
.meta-links a:hover { text-decoration: underline; }
.error-box {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    text-align: left;
    background: var(--page-danger-bg);
    color: var(--page-danger);
    font-size: 0.89rem;
    border: 1px solid rgba(185, 28, 28, 0.2);
}
@media (max-width: 760px) {
    body.user-signup-page .card {
        padding: 24px 18px;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
    .actions {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .actions-left,
    .actions-right {
        width: 100%;
    }
    .actions-right { justify-content: stretch; }
    .actions-right .btn-primary,
    .actions-left .btn-secondary,
    .btn-primary {
        width: 100%;
    }
    .btn-secondary {
        width: 100%;
    }
}
</style>
</head>
<body class="user-signup-page">
<header class="nav">
    <div class="nav-logo"><img src="/logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div>
    <a href="/public/index.php" class="home-btn" aria-label="Go to Home">Home</a>
</header>

<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <img src="/logocityhall.png" class="icon-top" alt="LGU Logo">
            <h2 class="title">Create Account</h2>
            <p class="subtitle">Set up your citizen account to access project updates and submit feedback.</p>
            <div style="margin-top:12px;">
                <a href="/user-dashboard/user-login.php" class="btn-link-back">Back to Login</a>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" autocomplete="on" id="createAccountForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="step-header" aria-label="Registration steps">
                <span class="step-dot active" data-step-dot="1">1</span>
                <span class="step-dot" data-step-dot="2">2</span>
                <span class="step-dot" data-step-dot="3">3</span>
                <span class="step-dot" data-step-dot="4">4</span>
            </div>

            <div class="form-step active" data-step="1">
                <p class="step-title">Step 1: Basic Information</p>
                <div class="form-grid">
                    <div class="input-box">
                        <label for="firstName">First Name *</label>
                        <input id="firstName" name="firstName" type="text" required value="<?php echo htmlspecialchars($form['firstName'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="middleName">Middle Name</label>
                        <input id="middleName" name="middleName" type="text" value="<?php echo htmlspecialchars($form['middleName'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="lastName">Last Name *</label>
                        <input id="lastName" name="lastName" type="text" required value="<?php echo htmlspecialchars($form['lastName'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="suffix">Suffix</label>
                        <select id="suffix" name="suffix">
                            <option value="" <?php echo $form['suffix'] === '' ? 'selected' : ''; ?>>None</option>
                            <option value="jr" <?php echo $form['suffix'] === 'jr' ? 'selected' : ''; ?>>Jr.</option>
                            <option value="sr" <?php echo $form['suffix'] === 'sr' ? 'selected' : ''; ?>>Sr.</option>
                            <option value="ii" <?php echo $form['suffix'] === 'ii' ? 'selected' : ''; ?>>II</option>
                            <option value="iii" <?php echo $form['suffix'] === 'iii' ? 'selected' : ''; ?>>III</option>
                            <option value="iv" <?php echo $form['suffix'] === 'iv' ? 'selected' : ''; ?>>IV</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-step" data-step="2">
                <p class="step-title">Step 2: Contact Details</p>
                <div class="form-grid">
                    <div class="input-box">
                        <label for="email">Email Address *</label>
                        <input id="email" name="email" type="email" required autocomplete="email" value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="mobile">Mobile Number *</label>
                        <input id="mobile" name="mobile" type="text" required placeholder="+63 9XX XXX XXXX" value="<?php echo htmlspecialchars($form['mobile'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="birthdate">Birthdate</label>
                        <input id="birthdate" name="birthdate" type="date" value="<?php echo htmlspecialchars($form['birthdate'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="address">Address *</label>
                        <input id="address" name="address" type="text" required value="<?php echo htmlspecialchars($form['address'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-step" data-step="3">
                <p class="step-title">Step 3: Personal and ID Information</p>
                <div class="form-grid">
                    <div class="input-box">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="" <?php echo $form['gender'] === '' ? 'selected' : ''; ?>>Select</option>
                            <option value="male" <?php echo $form['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $form['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $form['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not" <?php echo $form['gender'] === 'prefer_not' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    <div class="input-box">
                        <label for="civilStatus">Civil Status *</label>
                        <select id="civilStatus" name="civilStatus" required>
                            <option value="" <?php echo $form['civilStatus'] === '' ? 'selected' : ''; ?>>Select</option>
                            <option value="single" <?php echo $form['civilStatus'] === 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo $form['civilStatus'] === 'married' ? 'selected' : ''; ?>>Married</option>
                            <option value="widowed" <?php echo $form['civilStatus'] === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            <option value="separated" <?php echo $form['civilStatus'] === 'separated' ? 'selected' : ''; ?>>Separated</option>
                        </select>
                    </div>
                    <div class="input-box">
                        <label for="idType">ID Type</label>
                        <select id="idType" name="idType">
                            <option value="" <?php echo $form['idType'] === '' ? 'selected' : ''; ?>>Select ID Type</option>
                            <option value="nbi" <?php echo $form['idType'] === 'nbi' ? 'selected' : ''; ?>>NBI Clearance</option>
                            <option value="passport" <?php echo $form['idType'] === 'passport' ? 'selected' : ''; ?>>Passport</option>
                            <option value="drivinglicense" <?php echo $form['idType'] === 'drivinglicense' ? 'selected' : ''; ?>>Driver's License</option>
                            <option value="sss" <?php echo $form['idType'] === 'sss' ? 'selected' : ''; ?>>SSS</option>
                            <option value="tin" <?php echo $form['idType'] === 'tin' ? 'selected' : ''; ?>>TIN</option>
                            <option value="barangayid" <?php echo $form['idType'] === 'barangayid' ? 'selected' : ''; ?>>Barangay ID</option>
                        </select>
                    </div>
                    <div class="input-box">
                        <label for="idNumber">ID Number</label>
                        <input id="idNumber" name="idNumber" type="text" value="<?php echo htmlspecialchars($form['idNumber'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-box full">
                        <label for="idUpload">Upload ID (JPG/PNG/PDF, max 5MB)</label>
                        <input id="idUpload" name="idUpload" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                    </div>
                </div>
            </div>

            <div class="form-step" data-step="4">
                <p class="step-title">Step 4: Account Security</p>
                <div class="form-grid">
                    <div class="input-box">
                        <label for="password">Password *</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password">
                    </div>
                    <div class="input-box">
                        <label for="confirmPassword">Confirm Password *</label>
                        <input id="confirmPassword" name="confirmPassword" type="password" required autocomplete="new-password">
                    </div>
                    <div class="password-strength-wrap" aria-live="polite">
                        <div class="password-strength-track">
                            <div class="password-strength-fill" id="passwordStrengthFill"></div>
                        </div>
                        <div class="password-strength-label" id="passwordStrengthLabel">Password strength: Weak</div>
                    </div>
                </div>
            </div>

            <div class="step-error" id="stepErrorBox"></div>
            <div class="actions">
                <div class="actions-left">
                    <button class="btn-secondary" type="button" id="prevStepBtn" style="visibility:hidden;">Previous</button>
                </div>
                <div class="actions-right">
                    <button class="btn-primary" type="button" id="nextStepBtn">Next</button>
                    <button class="btn-primary" type="submit" name="register_submit" id="submitStepBtn" style="display:none;">Create Account</button>
                </div>
            </div>

            <div class="meta-links">
                Already have an account? <a href="/user-dashboard/user-login.php">Sign in</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<script src="/assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script>
(function () {
    var currentStep = 1;
    var totalSteps = 4;
    var form = document.getElementById('createAccountForm');
    var nextBtn = document.getElementById('nextStepBtn');
    var prevBtn = document.getElementById('prevStepBtn');
    var submitBtn = document.getElementById('submitStepBtn');
    var errorBox = document.getElementById('stepErrorBox');
    var passwordInput = document.getElementById('password');
    var strengthFill = document.getElementById('passwordStrengthFill');
    var strengthLabel = document.getElementById('passwordStrengthLabel');

    if (!form || !nextBtn || !prevBtn || !submitBtn) return;

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.style.display = 'block';
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.style.display = 'none';
    }

    function showStep(step) {
        currentStep = step;
        document.querySelectorAll('.form-step').forEach(function (node) {
            var nodeStep = Number(node.getAttribute('data-step'));
            node.classList.toggle('active', nodeStep === currentStep);
        });
        document.querySelectorAll('[data-step-dot]').forEach(function (dot) {
            var dotStep = Number(dot.getAttribute('data-step-dot'));
            dot.classList.toggle('active', dotStep === currentStep);
            dot.classList.toggle('done', dotStep < currentStep);
        });
        prevBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        nextBtn.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        submitBtn.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
        clearError();
    }

    function computeStrength(value) {
        var score = 0;
        if (value.length >= 8) score += 1;
        if (/[A-Z]/.test(value)) score += 1;
        if (/[a-z]/.test(value)) score += 1;
        if (/[0-9]/.test(value)) score += 1;
        if (/[^A-Za-z0-9]/.test(value)) score += 1;

        var pct = Math.min(100, score * 20);
        var text = 'Weak';
        var color = '#ef4444';

        if (score >= 5) {
            text = 'Strong';
            color = '#16a34a';
        } else if (score >= 4) {
            text = 'Good';
            color = '#22c55e';
        } else if (score >= 3) {
            text = 'Medium';
            color = '#f59e0b';
        }

        return { pct: pct, text: text, color: color };
    }

    function updateStrengthMeter() {
        if (!passwordInput || !strengthFill || !strengthLabel) return;
        var level = computeStrength(passwordInput.value || '');
        strengthFill.style.width = level.pct + '%';
        strengthFill.style.backgroundColor = level.color;
        strengthLabel.textContent = 'Password strength: ' + level.text;
        strengthLabel.style.color = level.color;
    }

    function isStepValid(step) {
        if (step === 1) {
            if (!document.getElementById('firstName').value.trim() || !document.getElementById('lastName').value.trim()) {
                showError('Please enter your first name and last name.');
                return false;
            }
        }
        if (step === 2) {
            var email = document.getElementById('email').value.trim();
            var mobile = document.getElementById('mobile').value.trim();
            var address = document.getElementById('address').value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('Please enter a valid email address.');
                return false;
            }
            if (!mobile || !/^\+?[0-9\s\-]{7,20}$/.test(mobile)) {
                showError('Please enter a valid mobile number.');
                return false;
            }
            if (!address) {
                showError('Please enter your address.');
                return false;
            }
        }
        if (step === 3) {
            if (!document.getElementById('gender').value || !document.getElementById('civilStatus').value) {
                showError('Please select gender and civil status.');
                return false;
            }
        }
        if (step === 4) {
            var pwd = document.getElementById('password').value;
            var cpwd = document.getElementById('confirmPassword').value;
            if (!pwd || !cpwd) {
                showError('Please enter and confirm your password.');
                return false;
            }
            if (pwd !== cpwd) {
                showError('Passwords do not match.');
                return false;
            }
        }
        return true;
    }

    nextBtn.addEventListener('click', function () {
        if (!isStepValid(currentStep)) return;
        if (currentStep < totalSteps) showStep(currentStep + 1);
    });

    prevBtn.addEventListener('click', function () {
        if (currentStep > 1) showStep(currentStep - 1);
    });

    form.addEventListener('submit', function (e) {
        if (!isStepValid(currentStep)) {
            e.preventDefault();
        }
    });

    if (passwordInput) {
        passwordInput.addEventListener('input', updateStrengthMeter);
        updateStrengthMeter();
    }

    showStep(1);
})();
</script>
</body>
</html>
