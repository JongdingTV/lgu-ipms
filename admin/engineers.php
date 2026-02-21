<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

$errors = [];
$success = '';

if (empty($_SESSION['engineer_form_token'])) {
    $_SESSION['engineer_form_token'] = bin2hex(random_bytes(32));
}
$formToken = $_SESSION['engineer_form_token'];

function eng_old(string $key, string $default = ''): string
{
    return htmlspecialchars((string)($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function eng_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function eng_store_uploaded_file(array $file, string $docType): array
{
    $allowedMimeToExt = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $maxSizeBytes = 5 * 1024 * 1024;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $docType . '.');
    }
    if ((int)($file['size'] ?? 0) > $maxSizeBytes) {
        throw new RuntimeException($docType . ' exceeds 5MB limit.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (string)(mime_content_type($tmp) ?: '');
    if (!isset($allowedMimeToExt[$mime])) {
        throw new RuntimeException($docType . ' has invalid file type. Allowed: PDF/JPG/PNG.');
    }

    $rootPath = realpath(dirname(__DIR__));
    if (!$rootPath) {
        throw new RuntimeException('Unable to resolve application path.');
    }
    $relativeDir = 'uploads/engineers/' . date('Y') . '/' . date('m');
    $targetDir = $rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Failed to create upload directory.');
    }

    $ext = $allowedMimeToExt[$mime];
    $filename = sprintf('eng_%s_%s_%s.%s', strtolower($docType), date('YmdHis'), bin2hex(random_bytes(6)), $ext);
    $targetFile = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $targetFile)) {
        throw new RuntimeException('Could not save uploaded file for ' . $docType . '.');
    }

    return [
        'document_type' => $docType,
        'file_path' => $relativeDir . '/' . $filename,
        'original_name' => (string)($file['name'] ?? ''),
        'mime_type' => $mime,
        'file_size' => (int)($file['size'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($formToken, $postedToken)) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
    }

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $middleName = trim((string)($_POST['middle_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $suffix = trim((string)($_POST['suffix'] ?? ''));
    $dob = trim((string)($_POST['dob'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? ''));
    $civilStatus = trim((string)($_POST['civil_status'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    $prcLicense = trim((string)($_POST['prc_license_number'] ?? ''));
    $licenseExpiry = trim((string)($_POST['license_expiry_date'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $yearsExperience = (int)($_POST['years_experience'] ?? 0);
    $positionTitle = trim((string)($_POST['position_title'] ?? ''));
    $skills = $_POST['skills'] ?? [];
    if (!is_array($skills)) $skills = [];
    $availabilityStatus = trim((string)($_POST['availability_status'] ?? 'Available'));

    $education = trim((string)($_POST['highest_education'] ?? ''));
    $school = trim((string)($_POST['school_university'] ?? ''));
    $certifications = trim((string)($_POST['certifications_trainings'] ?? ''));
    $pastProjectsCount = (int)($_POST['past_projects_count'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'Engineer'));

    if ($firstName === '') $errors[] = 'First Name is required.';
    if ($lastName === '') $errors[] = 'Last Name is required.';
    if ($contactNumber === '') $errors[] = 'Contact Number is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid Email is required.';

    if ($prcLicense === '') $errors[] = 'PRC License Number is required.';
    if ($licenseExpiry === '') {
        $errors[] = 'License Expiry Date is required.';
    } elseif (strtotime($licenseExpiry) === false || strtotime($licenseExpiry) <= strtotime(date('Y-m-d'))) {
        $errors[] = 'License Expiry Date must be in the future.';
    }
    if ($specialization === '') $errors[] = 'Field/Specialization is required.';
    if ($education === '') $errors[] = 'Highest Educational Attainment is required.';

    if ($username === '') $errors[] = 'Username is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Password and Confirm Password do not match.';

    if (!isset($_FILES['prc_license_file']) || ($_FILES['prc_license_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'PRC ID / License File is required.';
    }
    if (!isset($_FILES['resume_file']) || ($_FILES['resume_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Resume/CV is required.';
    }

    if (!$errors && (!eng_table_exists($db, 'engineers') || !eng_table_exists($db, 'engineer_documents'))) {
        $errors[] = 'Engineers schema is missing. Run migration: database/migrations/2026_02_21_engineers_registration.sql';
    }

    if (!$errors) {
        try {
            $documents = [];
            $documents[] = eng_store_uploaded_file($_FILES['prc_license_file'], 'prc_license');
            $documents[] = eng_store_uploaded_file($_FILES['resume_file'], 'resume_cv');

            if (isset($_FILES['government_id_file']) && ($_FILES['government_id_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $documents[] = eng_store_uploaded_file($_FILES['government_id_file'], 'government_id');
            }

            if (isset($_FILES['certificates_files']) && is_array($_FILES['certificates_files']['name'] ?? null)) {
                $count = count($_FILES['certificates_files']['name']);
                for ($i = 0; $i < $count; $i++) {
                    $err = (int)($_FILES['certificates_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    $file = [
                        'name' => $_FILES['certificates_files']['name'][$i] ?? '',
                        'type' => $_FILES['certificates_files']['type'][$i] ?? '',
                        'tmp_name' => $_FILES['certificates_files']['tmp_name'][$i] ?? '',
                        'error' => $err,
                        'size' => $_FILES['certificates_files']['size'][$i] ?? 0,
                    ];
                    $documents[] = eng_store_uploaded_file($file, 'certificate');
                }
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $skillsJson = json_encode(array_values($skills), JSON_UNESCAPED_SLASHES);
            $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);

            $db->begin_transaction();

            $insert = $db->prepare(
                "INSERT INTO engineers (
                    first_name, middle_name, last_name, suffix, full_name,
                    date_of_birth, gender, civil_status, address, contact_number, email,
                    prc_license_number, license_expiry_date, specialization, years_experience,
                    position_title, skills_json, availability_status,
                    highest_education, school_university, certifications_trainings, past_projects_count, notes,
                    username, password_hash, role
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$insert) {
                throw new RuntimeException('Unable to prepare engineer insert.');
            }

            $insert->bind_param(
                'ssssssssssssssisssssssssss',
                $firstName, $middleName, $lastName, $suffix, $fullName,
                $dob, $gender, $civilStatus, $address, $contactNumber, $email,
                $prcLicense, $licenseExpiry, $specialization, $yearsExperience,
                $positionTitle, $skillsJson, $availabilityStatus,
                $education, $school, $certifications, $pastProjectsCount, $notes,
                $username, $passwordHash, $role
            );
            if (!$insert->execute()) {
                throw new RuntimeException('Unable to save engineer: ' . $insert->error);
            }
            $engineerId = (int)$db->insert_id;
            $insert->close();

            $docStmt = $db->prepare(
                "INSERT INTO engineer_documents (
                    engineer_id, document_type, file_path, original_name, mime_type, file_size
                ) VALUES (?, ?, ?, ?, ?, ?)"
            );
            if (!$docStmt) {
                throw new RuntimeException('Unable to prepare document insert.');
            }

            foreach ($documents as $doc) {
                $docStmt->bind_param(
                    'issssi',
                    $engineerId,
                    $doc['document_type'],
                    $doc['file_path'],
                    $doc['original_name'],
                    $doc['mime_type'],
                    $doc['file_size']
                );
                if (!$docStmt->execute()) {
                    throw new RuntimeException('Unable to save uploaded document.');
                }
            }
            $docStmt->close();

            $db->commit();
            $success = 'Engineer registered successfully.';
            $_POST = [];
            $_SESSION['engineer_form_token'] = bin2hex(random_bytes(32));
            $formToken = $_SESSION['engineer_form_token'];
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Engineer Registration - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-engineers-add.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-engineers-add.css'); ?>">
</head>
<body class="engineer-registration-page">
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle">
                    <img src="../assets/images/admin/list.png" class="nav-icon">Project Registration
                    <span class="dropdown-arrow"></span>
                </a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item">
                        <span class="submenu-icon">+</span>
                        <span>New Project</span>
                    </a>
                    <a href="registered_projects.php" class="nav-submenu-item">
                        <span class="submenu-icon">&#128203;</span>
                        <span>Registered Projects</span>
                    </a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>

            <div class="nav-item-group">
                <a href="engineers.php" class="nav-main-item" id="contractorsToggle">
                    <img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers
                    <span class="dropdown-arrow"></span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="engineers.php" class="nav-submenu-item active">
                        <span class="submenu-icon">+</span>
                        <span>Add Engineer</span>
                    </a>
                    <a href="registered_engineers.php" class="nav-submenu-item">
                        <span class="submenu-icon">&#128100;</span>
                        <span>Registered Engineers</span>
                    </a>
                </div>
            </div>

            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/admin/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="engineer-form-page">
        <header class="engineer-form-header">
            <div>
                <h1>Engineer Registration Form</h1>
                <p>LGU Infrastructure Project Management System - Admin Side</p>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="engineer-alert error" role="alert">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="engineer-alert success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form id="engineerRegistrationForm" class="engineer-form" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">

            <section class="engineer-section">
                <h2>Personal Information</h2>
                <div class="engineer-grid cols-4">
                    <label>First Name *<input type="text" name="first_name" required value="<?php echo eng_old('first_name'); ?>"></label>
                    <label>Middle Name<input type="text" name="middle_name" value="<?php echo eng_old('middle_name'); ?>"></label>
                    <label>Last Name *<input type="text" name="last_name" required value="<?php echo eng_old('last_name'); ?>"></label>
                    <label>Suffix<input type="text" name="suffix" value="<?php echo eng_old('suffix'); ?>"></label>
                </div>
                <div class="engineer-grid cols-4">
                    <label>Date of Birth<input type="date" name="dob" value="<?php echo eng_old('dob'); ?>"></label>
                    <label>Gender
                        <select name="gender">
                            <option value="">Select</option>
                            <option value="male" <?php echo eng_old('gender') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo eng_old('gender') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo eng_old('gender') === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not" <?php echo eng_old('gender') === 'prefer_not' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </label>
                    <label>Civil Status
                        <select name="civil_status">
                            <option value="">Select</option>
                            <option value="single" <?php echo eng_old('civil_status') === 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo eng_old('civil_status') === 'married' ? 'selected' : ''; ?>>Married</option>
                            <option value="widowed" <?php echo eng_old('civil_status') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            <option value="separated" <?php echo eng_old('civil_status') === 'separated' ? 'selected' : ''; ?>>Separated</option>
                        </select>
                    </label>
                    <label>Contact Number *<input type="text" name="contact_number" required value="<?php echo eng_old('contact_number'); ?>"></label>
                </div>
                <div class="engineer-grid cols-2">
                    <label>Email *<input type="email" name="email" required value="<?php echo eng_old('email'); ?>"></label>
                    <label>Address<textarea name="address" rows="2"><?php echo eng_old('address'); ?></textarea></label>
                </div>
            </section>

            <section class="engineer-section">
                <h2>Professional Details</h2>
                <div class="engineer-grid cols-4">
                    <label>PRC License Number *<input type="text" name="prc_license_number" required value="<?php echo eng_old('prc_license_number'); ?>"></label>
                    <label>License Expiry Date *<input type="date" name="license_expiry_date" required value="<?php echo eng_old('license_expiry_date'); ?>"></label>
                    <label>Field/Specialization *
                        <select name="specialization" required>
                            <option value="">Select</option>
                            <option value="Civil Engineering" <?php echo eng_old('specialization') === 'Civil Engineering' ? 'selected' : ''; ?>>Civil Engineering</option>
                            <option value="Electrical Engineering" <?php echo eng_old('specialization') === 'Electrical Engineering' ? 'selected' : ''; ?>>Electrical Engineering</option>
                            <option value="Mechanical Engineering" <?php echo eng_old('specialization') === 'Mechanical Engineering' ? 'selected' : ''; ?>>Mechanical Engineering</option>
                            <option value="Structural Engineering" <?php echo eng_old('specialization') === 'Structural Engineering' ? 'selected' : ''; ?>>Structural Engineering</option>
                            <option value="Geotechnical Engineering" <?php echo eng_old('specialization') === 'Geotechnical Engineering' ? 'selected' : ''; ?>>Geotechnical Engineering</option>
                        </select>
                    </label>
                    <label>Years of Experience<input type="number" name="years_experience" min="0" value="<?php echo eng_old('years_experience', '0'); ?>"></label>
                </div>
                <div class="engineer-grid cols-3">
                    <label>Current Position/Title<input type="text" name="position_title" value="<?php echo eng_old('position_title'); ?>"></label>
                    <label>Availability Status
                        <select name="availability_status">
                            <option value="Available" <?php echo eng_old('availability_status', 'Available') === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Assigned" <?php echo eng_old('availability_status') === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="On Leave" <?php echo eng_old('availability_status') === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </label>
                    <label>Skills
                        <select name="skills[]" multiple size="5">
                            <?php $oldSkills = $_POST['skills'] ?? []; if (!is_array($oldSkills)) $oldSkills = []; ?>
                            <?php foreach (['AutoCAD', 'Project Management', 'Site Supervision', 'Cost Estimation', 'Structural Analysis', 'Safety Management'] as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($skill, $oldSkills, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

            <section class="engineer-section">
                <h2>Credentials &amp; Background</h2>
                <div class="engineer-grid cols-4">
                    <label>Highest Educational Attainment *<input type="text" name="highest_education" required value="<?php echo eng_old('highest_education'); ?>"></label>
                    <label>School/University<input type="text" name="school_university" value="<?php echo eng_old('school_university'); ?>"></label>
                    <label>Past Projects Count<input type="number" name="past_projects_count" min="0" value="<?php echo eng_old('past_projects_count', '0'); ?>"></label>
                    <label>Notes<textarea name="notes" rows="2"><?php echo eng_old('notes'); ?></textarea></label>
                </div>
                <div class="engineer-grid cols-1">
                    <label>Certifications/Trainings<textarea name="certifications_trainings" rows="3"><?php echo eng_old('certifications_trainings'); ?></textarea></label>
                </div>
            </section>

            <section class="engineer-section">
                <h2>Documents Upload</h2>
                <p class="engineer-help-text">Accepted files: PDF/JPG/PNG, max 5MB each.</p>
                <div class="engineer-grid cols-2">
                    <label>PRC ID / License File *<input type="file" name="prc_license_file" accept=".pdf,.jpg,.jpeg,.png" required></label>
                    <label>Resume/CV *<input type="file" name="resume_file" accept=".pdf,.jpg,.jpeg,.png" required></label>
                    <label>Government ID<input type="file" name="government_id_file" accept=".pdf,.jpg,.jpeg,.png"></label>
                    <label>Certificates (multiple)<input type="file" name="certificates_files[]" accept=".pdf,.jpg,.jpeg,.png" multiple></label>
                </div>
            </section>

            <section class="engineer-section">
                <h2>Emergency/Other</h2>
                <div class="engineer-grid cols-3">
                    <label>Emergency Contact Name<input type="text" name="emergency_contact_name" value="<?php echo eng_old('emergency_contact_name'); ?>"></label>
                    <label>Emergency Contact Number<input type="text" name="emergency_contact_number" value="<?php echo eng_old('emergency_contact_number'); ?>"></label>
                    <label>Emergency Contact Relationship<input type="text" name="emergency_contact_relationship" value="<?php echo eng_old('emergency_contact_relationship'); ?>"></label>
                </div>
            </section>

            <section class="engineer-section">
                <h2>Account Access</h2>
                <div class="engineer-grid cols-4">
                    <label>Username *<input type="text" name="username" required value="<?php echo eng_old('username'); ?>"></label>
                    <label>Password *<input type="password" name="password" required></label>
                    <label>Confirm Password *<input type="password" name="confirm_password" required></label>
                    <label>Role
                        <select name="role">
                            <option value="Engineer" <?php echo eng_old('role', 'Engineer') === 'Engineer' ? 'selected' : ''; ?>>Engineer</option>
                            <option value="Admin" <?php echo eng_old('role') === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </label>
                </div>
            </section>

            <div class="engineer-form-actions">
                <button type="submit" class="btn-submit-engineer">Register Engineer</button>
                <button type="reset" class="btn-reset-engineer">Reset</button>
            </div>
        </form>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-engineers-add.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-engineers-add.js'); ?>"></script>
</body>
</html>
