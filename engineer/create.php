<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();

if (isset($_SESSION['employee_id'])) {
    $activeRole = strtolower(trim((string) ($_SESSION['employee_role'] ?? '')));
    if (in_array($activeRole, ['engineer', 'admin', 'super_admin'], true)) {
        header('Location: /engineer/dashboard_overview.php');
        exit;
    }
}

$errors = [];
$success = '';

if (empty($_SESSION['engineer_create_token'])) {
    $_SESSION['engineer_create_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['engineer_create_token'];

$form = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'dob' => '',
    'gender' => '',
    'civil_status' => '',
    'email' => '',
    'contact_number' => '',
    'address' => '',
    'prc_license_number' => '',
    'license_expiry_date' => '',
    'specialization' => '',
    'years_experience' => '0',
    'position_title' => '',
    'availability_status' => 'Available',
    'highest_education' => '',
    'school_university' => '',
    'certifications_trainings' => '',
    'past_projects_count' => '0',
    'notes' => '',
    'emergency_contact_name' => '',
    'emergency_contact_number' => '',
    'emergency_contact_relationship' => ''
];

function ec_table_has_column(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function ec_engineers_columns(mysqli $db): array
{
    $rows = [];
    $stmt = $db->prepare("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, COLUMN_TYPE, EXTRA
                          FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'engineers'
                          ORDER BY ORDINAL_POSITION");
    if (!$stmt) return $rows;
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    if ($res) $res->free();
    $stmt->close();
    return $rows;
}

function ec_bind_type_for_data_type(string $dataType): string
{
    $t = strtolower($dataType);
    if (in_array($t, ['tinyint','smallint','mediumint','int','integer','bigint'], true)) return 'i';
    if (in_array($t, ['decimal','numeric','float','double','real'], true)) return 'd';
    return 's';
}

function ec_first_enum_value(string $columnType): string
{
    if (preg_match("/^enum\\('([^']+)'/i", $columnType, $m)) {
        return (string)$m[1];
    }
    return '';
}

function ec_truncate_to_varchar(string $value, string $columnType): string
{
    if (preg_match('/^varchar\\((\\d+)\\)$/i', trim($columnType), $m)) {
        $max = (int)$m[1];
        if ($max > 0 && strlen($value) > $max) {
            return substr($value, 0, $max);
        }
    }
    return $value;
}

function ec_check_duplicates(mysqli $db, string $email, string $license): array
{
    $errors = [];
    $stmt = $db->prepare('SELECT id FROM employees WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        if ($res) $res->free();
        $stmt->close();
        if ($exists) $errors[] = 'Email already exists.';
    }

    if ($license !== '' && ec_table_has_column($db, 'engineers', 'prc_license_number')) {
        $stmt = $db->prepare('SELECT id FROM engineers WHERE prc_license_number = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $license);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            if ($res) $res->free();
            $stmt->close();
            if ($exists) $errors[] = 'PRC license number already exists.';
        }
    }
    return $errors;
}

function ec_validate(array $input): array
{
    $errors = [];
    $data = [];
    foreach ([
        'first_name','middle_name','last_name','suffix','dob','gender','civil_status',
        'email','contact_number','address','prc_license_number','license_expiry_date','specialization',
        'position_title','availability_status','highest_education','school_university',
        'certifications_trainings','notes','emergency_contact_name','emergency_contact_number','emergency_contact_relationship'
    ] as $k) {
        $data[$k] = trim((string)($input[$k] ?? ''));
    }

    $skills = $input['skills'] ?? [];
    if (!is_array($skills)) $skills = [];
    $skills = array_values(array_filter(array_map('trim', $skills), static function ($v) { return $v !== ''; }));
    $data['skills'] = $skills;

    $data['email'] = strtolower($data['email']);
    $data['years_experience'] = max(0, (int)($input['years_experience'] ?? 0));
    $data['past_projects_count'] = max(0, (int)($input['past_projects_count'] ?? 0));

    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');

    if ($data['first_name'] === '' || $data['last_name'] === '') $errors[] = 'First and last name are required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', str_replace([' ', '-'], '', $data['contact_number']))) $errors[] = 'Mobile format must be 09XXXXXXXXX or +639XXXXXXXXX.';
    if ($data['specialization'] === '') $errors[] = 'Specialization is required.';
    if ($data['prc_license_number'] === '') $errors[] = 'PRC license number is required.';
    if ($data['license_expiry_date'] === '' || strtotime($data['license_expiry_date']) === false || strtotime($data['license_expiry_date']) <= strtotime(date('Y-m-d'))) {
        $errors[] = 'License expiry date must be in the future.';
    }
    if ($data['highest_education'] === '') $errors[] = 'Highest educational attainment is required.';
    if ($data['availability_status'] === '' || !in_array($data['availability_status'], ['Available', 'Assigned', 'On Leave'], true)) {
        $data['availability_status'] = 'Available';
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 chars and include uppercase, lowercase, number, and symbol.';
    }
    if ($password !== $confirm) $errors[] = 'Password and confirmation do not match.';

    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    return ['errors' => $errors, 'data' => $data];
}

function ec_create_account(mysqli $db, array $data): void
{
    $db->begin_transaction();
    try {
        $emp = $db->prepare("INSERT INTO employees (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'engineer')");
        if (!$emp) throw new RuntimeException('Failed to prepare employee insert.');
        $emp->bind_param('ssss', $data['first_name'], $data['last_name'], $data['email'], $data['password_hash']);
        if (!$emp->execute()) throw new RuntimeException('Failed to create login account: ' . $emp->error);
        $employeeId = (int)$db->insert_id;
        $emp->close();

        $fullName = trim($data['first_name'] . ' ' . $data['middle_name'] . ' ' . $data['last_name'] . ' ' . $data['suffix']);
        if ($fullName === '') $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        $skillsJson = json_encode($data['skills'], JSON_UNESCAPED_SLASHES);

        $columns = ['first_name', 'last_name', 'full_name', 'email', 'specialization'];
        $types = 'sssss';
        $values = [$data['first_name'], $data['last_name'], $fullName, $data['email'], $data['specialization']];

        if (ec_table_has_column($db, 'engineers', 'engineer_code')) {
            $engineerCode = 'ENG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $columns[] = 'engineer_code';
            $types .= 's';
            $values[] = $engineerCode;
        }

        if (ec_table_has_column($db, 'engineers', 'middle_name')) { $columns[] = 'middle_name'; $types .= 's'; $values[] = $data['middle_name']; }
        if (ec_table_has_column($db, 'engineers', 'suffix')) { $columns[] = 'suffix'; $types .= 's'; $values[] = $data['suffix']; }
        if (ec_table_has_column($db, 'engineers', 'date_of_birth')) { $columns[] = 'date_of_birth'; $types .= 's'; $values[] = $data['dob']; }
        if (ec_table_has_column($db, 'engineers', 'gender')) { $columns[] = 'gender'; $types .= 's'; $values[] = $data['gender']; }
        if (ec_table_has_column($db, 'engineers', 'civil_status')) { $columns[] = 'civil_status'; $types .= 's'; $values[] = $data['civil_status']; }
        if (ec_table_has_column($db, 'engineers', 'contact_number')) { $columns[] = 'contact_number'; $types .= 's'; $values[] = $data['contact_number']; }
        if (ec_table_has_column($db, 'engineers', 'address')) { $columns[] = 'address'; $types .= 's'; $values[] = $data['address']; }
        if (ec_table_has_column($db, 'engineers', 'prc_license_number')) { $columns[] = 'prc_license_number'; $types .= 's'; $values[] = $data['prc_license_number']; }
        if (ec_table_has_column($db, 'engineers', 'license_expiry_date')) { $columns[] = 'license_expiry_date'; $types .= 's'; $values[] = $data['license_expiry_date']; }
        if (ec_table_has_column($db, 'engineers', 'years_experience')) { $columns[] = 'years_experience'; $types .= 'i'; $values[] = $data['years_experience']; }
        if (ec_table_has_column($db, 'engineers', 'position_title')) { $columns[] = 'position_title'; $types .= 's'; $values[] = $data['position_title']; }
        if (ec_table_has_column($db, 'engineers', 'skills_json')) { $columns[] = 'skills_json'; $types .= 's'; $values[] = $skillsJson; }
        if (ec_table_has_column($db, 'engineers', 'availability_status')) { $columns[] = 'availability_status'; $types .= 's'; $values[] = $data['availability_status']; }
        if (ec_table_has_column($db, 'engineers', 'highest_education')) { $columns[] = 'highest_education'; $types .= 's'; $values[] = $data['highest_education']; }
        if (ec_table_has_column($db, 'engineers', 'school_university')) { $columns[] = 'school_university'; $types .= 's'; $values[] = $data['school_university']; }
        if (ec_table_has_column($db, 'engineers', 'certifications_trainings')) { $columns[] = 'certifications_trainings'; $types .= 's'; $values[] = $data['certifications_trainings']; }
        if (ec_table_has_column($db, 'engineers', 'past_projects_count')) { $columns[] = 'past_projects_count'; $types .= 'i'; $values[] = $data['past_projects_count']; }
        if (ec_table_has_column($db, 'engineers', 'notes')) { $columns[] = 'notes'; $types .= 's'; $values[] = $data['notes']; }
        if (ec_table_has_column($db, 'engineers', 'emergency_contact_name')) { $columns[] = 'emergency_contact_name'; $types .= 's'; $values[] = $data['emergency_contact_name']; }
        if (ec_table_has_column($db, 'engineers', 'emergency_contact_number')) { $columns[] = 'emergency_contact_number'; $types .= 's'; $values[] = $data['emergency_contact_number']; }
        if (ec_table_has_column($db, 'engineers', 'emergency_contact_relationship')) { $columns[] = 'emergency_contact_relationship'; $types .= 's'; $values[] = $data['emergency_contact_relationship']; }
        if (ec_table_has_column($db, 'engineers', 'username')) {
            $emailUser = strstr($data['email'], '@', true);
            if ($emailUser === false || trim($emailUser) === '') $emailUser = 'eng' . date('His');
            $autoUsername = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$emailUser)) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $columns[] = 'username'; $types .= 's'; $values[] = $autoUsername;
        }
        if (ec_table_has_column($db, 'engineers', 'password_hash')) { $columns[] = 'password_hash'; $types .= 's'; $values[] = $data['password_hash']; }
        if (ec_table_has_column($db, 'engineers', 'role')) { $columns[] = 'role'; $types .= 's'; $values[] = 'Engineer'; }
        if (ec_table_has_column($db, 'engineers', 'account_status')) { $columns[] = 'account_status'; $types .= 's'; $values[] = 'active'; }
        if (ec_table_has_column($db, 'engineers', 'employee_id')) { $columns[] = 'employee_id'; $types .= 'i'; $values[] = $employeeId; }

        $existing = array_fill_keys($columns, true);
        $allCols = ec_engineers_columns($db);
        foreach ($allCols as $col) {
            $name = (string)($col['COLUMN_NAME'] ?? '');
            if ($name === '' || isset($existing[$name])) continue;
            $nullable = strtoupper((string)($col['IS_NULLABLE'] ?? 'YES')) === 'YES';
            $default = $col['COLUMN_DEFAULT'] ?? null;
            $extra = strtolower((string)($col['EXTRA'] ?? ''));
            if ($nullable || $default !== null || strpos($extra, 'auto_increment') !== false) continue;

            $dataType = strtolower((string)($col['DATA_TYPE'] ?? 'varchar'));
            $columnType = (string)($col['COLUMN_TYPE'] ?? '');
            $val = '';

            switch ($name) {
                case 'engineer_code': $val = 'ENG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); break;
                case 'first_name': $val = $data['first_name']; break;
                case 'last_name': $val = $data['last_name']; break;
                case 'full_name': $val = $fullName; break;
                case 'email': $val = $data['email']; break;
                case 'contact_number': $val = $data['contact_number']; break;
                case 'position_title': $val = $data['position_title'] !== '' ? $data['position_title'] : 'Engineer'; break;
                case 'specialization': $val = $data['specialization']; break;
                case 'availability_status': $val = $data['availability_status']; break;
                case 'prc_license_number': $val = $data['prc_license_number']; break;
                case 'license_expiry_date': $val = $data['license_expiry_date']; break;
                case 'highest_education': $val = $data['highest_education']; break;
                case 'username':
                    $emailUser = strstr($data['email'], '@', true);
                    if ($emailUser === false || trim($emailUser) === '') $emailUser = 'eng' . date('His');
                    $val = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$emailUser)) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
                    break;
                case 'password_hash': $val = $data['password_hash']; break;
                case 'role': $val = 'Engineer'; break;
                case 'account_status': $val = 'active'; break;
                case 'employee_id': $val = $employeeId; break;
                case 'years_experience': $val = $data['years_experience']; break;
                case 'past_projects_count': $val = $data['past_projects_count']; break;
                default:
                    if ($dataType === 'enum') {
                        $enumVal = ec_first_enum_value($columnType);
                        $val = $enumVal !== '' ? $enumVal : 'N/A';
                    } elseif (in_array($dataType, ['tinyint','smallint','mediumint','int','integer','bigint','decimal','numeric','float','double','real'], true)) {
                        $val = 0;
                    } elseif ($dataType === 'date') {
                        $val = date('Y-m-d');
                    } elseif (in_array($dataType, ['datetime','timestamp'], true)) {
                        $val = date('Y-m-d H:i:s');
                    } else {
                        $val = 'AUTO_' . strtoupper($name) . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
                    }
                    break;
            }

            if (is_string($val)) {
                $val = ec_truncate_to_varchar($val, $columnType);
            }

            $columns[] = $name;
            $types .= ec_bind_type_for_data_type($dataType);
            $values[] = $val;
            $existing[$name] = true;
        }

        $sql = 'INSERT INTO engineers (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $ins = $db->prepare($sql);
        if (!$ins) throw new RuntimeException('Failed to prepare engineer insert.');
        $ins->bind_param($types, ...$values);
        if (!$ins->execute()) throw new RuntimeException('Failed to save engineer profile: ' . $ins->error);
        $ins->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } elseif (is_rate_limited('engineer_create', 5, 600)) {
        $errors[] = 'Too many registration attempts. Try again later.';
    } else {
        $validated = ec_validate($_POST);
        $errors = array_merge($errors, $validated['errors']);
        $data = $validated['data'];
        foreach ($form as $k => $v) if (isset($data[$k])) $form[$k] = (string)$data[$k];
        if (!$errors) $errors = array_merge($errors, ec_check_duplicates($db, $data['email'], $data['prc_license_number']));
        if (!$errors) {
            try {
                ec_create_account($db, $data);
                $success = 'Engineer account created successfully. Sign in to continue.';
                $_SESSION['engineer_create_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['engineer_create_token'];
                foreach ($form as $k => $v) {
                    if ($k === 'years_experience' || $k === 'past_projects_count') $form[$k] = '0';
                    elseif ($k === 'availability_status') $form[$k] = 'Available';
                    else $form[$k] = '';
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Engineer Account Create</title>
<link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<style>
:root{--page-navy:#0f2a4a;--page-blue:#1d4e89;--page-sky:#3f83c9;--page-muted:#475569;--page-danger:#b91c1c;--page-danger-bg:#fee2e2;--page-border:rgba(15,23,42,.12)}
*{box-sizing:border-box}
body.user-signup-page{min-height:100vh;margin:0;display:flex;flex-direction:column;padding-top:88px;color:#0f172a;background:radial-gradient(circle at 15% 15%,rgba(63,131,201,.28),transparent 40%),radial-gradient(circle at 85% 85%,rgba(29,78,137,.26),transparent 45%),linear-gradient(125deg,rgba(7,20,36,.72),rgba(15,42,74,.68)),url('/cityhall.jpeg') center/cover fixed no-repeat}
body.user-signup-page .nav{position:fixed;inset:0 0 auto 0;width:100%;height:78px;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(90deg,rgba(255,255,255,.94),rgba(247,251,255,.98));border-bottom:1px solid var(--page-border);box-shadow:0 12px 30px rgba(2,6,23,.12);z-index:30}
body.user-signup-page .nav-logo{display:inline-flex;align-items:center;gap:10px;font-size:.98rem;font-weight:700;color:var(--page-navy)}
body.user-signup-page .nav-logo img{width:44px;height:44px;object-fit:contain}
body.user-signup-page .home-btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 16px;border-radius:10px;border:1px solid rgba(29,78,137,.22);text-decoration:none;font-weight:600;color:var(--page-blue);background:#fff}
body.user-signup-page .wrapper{width:100%;flex:1;display:flex;justify-content:center;align-items:flex-start;padding:30px 16px 36px}
body.user-signup-page .card{width:100%;max-width:920px;background:rgba(255,255,255,.95);border:1px solid rgba(255,255,255,.75);border-radius:20px;padding:30px 26px;box-shadow:0 24px 56px rgba(2,6,23,.3)}
body.user-signup-page .card-header{text-align:center;margin-bottom:18px}
body.user-signup-page .icon-top{width:72px;height:72px;object-fit:contain;margin:2px auto 10px;display:block}
body.user-signup-page .title{margin:0 0 6px;font-size:1.7rem;line-height:1.2;color:var(--page-navy)}
body.user-signup-page .subtitle{margin:0;color:var(--page-muted)}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-grid .full{grid-column:1 / -1}.input-box{text-align:left}
.form-section{margin-top:16px;padding:14px;border:1px solid rgba(148,163,184,.3);border-radius:12px;background:rgba(248,251,255,.7)}
.form-section h3{margin:0 0 10px;color:#0f2a4a;font-size:1rem}
.input-box label{display:block;font-size:.86rem;color:#1e293b;margin-bottom:6px}
.input-box input,.input-box select,.input-box textarea{width:100%;min-height:46px;border-radius:11px;border:1px solid rgba(148,163,184,.45);background:#fff;padding:10px 12px;font-size:.95rem;color:#0f172a;outline:none}
.input-box textarea{min-height:88px;resize:vertical}
.input-box input:focus,.input-box select:focus,.input-box textarea:focus{border-color:var(--page-sky);box-shadow:0 0 0 4px rgba(63,131,201,.15)}
.actions{margin-top:18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.btn-primary{min-width:170px;height:46px;border:0;border-radius:11px;background:linear-gradient(135deg,#1d4e89,#3f83c9);color:#fff;font-size:.98rem;font-weight:600;cursor:pointer}
.btn-secondary{min-width:130px;height:46px;border:1px solid rgba(148,163,184,.55);border-radius:11px;background:#fff;color:#0f172a;font-size:.95rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.btn-primary:hover,.btn-secondary:hover{filter:brightness(1.02)}
.btn-primary:active,.btn-secondary:active{transform:translateY(1px)}
.error-box{margin-top:14px;padding:10px 12px;border-radius:10px;text-align:left;background:var(--page-danger-bg);color:var(--page-danger);font-size:.89rem;border:1px solid rgba(185,28,28,.2)}
.ok-box{margin-top:12px;padding:10px 12px;border-radius:10px;background:#dcfce7;color:#166534;font-size:.89rem;border:1px solid #bbf7d0}
@media (max-width:860px){
    body.user-signup-page{padding-top:78px}
    body.user-signup-page .nav{height:70px;padding:10px 14px}
    body.user-signup-page .nav-logo{font-size:.9rem}
    body.user-signup-page .nav-logo img{width:38px;height:38px}
    body.user-signup-page .home-btn{padding:8px 12px;min-height:40px}
    body.user-signup-page .wrapper{padding:16px 10px 20px}
    body.user-signup-page .card{padding:18px 14px;border-radius:14px}
    body.user-signup-page .icon-top{width:58px;height:58px}
    body.user-signup-page .title{font-size:1.34rem}
    body.user-signup-page .subtitle{font-size:.9rem}
    .form-section{margin-top:12px;padding:11px}
    .form-grid{grid-template-columns:1fr;gap:10px}
    .form-grid .full{grid-column:auto}
    .input-box label{font-size:.83rem}
    .input-box input,.input-box select,.input-box textarea{min-height:44px;font-size:.94rem;padding:9px 11px}
    .input-box select[multiple]{min-height:120px}
    .actions{margin-top:14px;gap:8px;justify-content:stretch}
    .btn-primary,.btn-secondary{width:100%;min-width:0;min-height:44px}
}
</style>
</head>
<body class="user-signup-page">
<header class="nav"><div class="nav-logo"><img src="/assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div><a href="/engineer/index.php" class="home-btn">Back to Login</a></header>
<div class="wrapper"><div class="card">
<div class="card-header"><img src="/assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU Logo"><h2 class="title">Create Engineer Account</h2><p class="subtitle">Register your engineer profile and secure your login.</p></div>
<?php if (!empty($errors)): ?><div class="error-box"><?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="ok-box"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="form-section"><h3>Personal Information</h3><div class="form-grid">
<div class="input-box"><label>First Name</label><input type="text" name="first_name" required value="<?php echo htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Middle Name</label><input type="text" name="middle_name" value="<?php echo htmlspecialchars($form['middle_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Last Name</label><input type="text" name="last_name" required value="<?php echo htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Suffix</label><input type="text" name="suffix" placeholder="Jr., Sr., III" value="<?php echo htmlspecialchars($form['suffix'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Date of Birth</label><input type="date" name="dob" value="<?php echo htmlspecialchars($form['dob'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Gender</label><select name="gender"><option value="">Select</option><option value="male" <?php echo $form['gender'] === 'male' ? 'selected' : ''; ?>>Male</option><option value="female" <?php echo $form['gender'] === 'female' ? 'selected' : ''; ?>>Female</option><option value="other" <?php echo $form['gender'] === 'other' ? 'selected' : ''; ?>>Other</option></select></div>
<div class="input-box"><label>Civil Status</label><select name="civil_status"><option value="">Select</option><option value="single" <?php echo $form['civil_status'] === 'single' ? 'selected' : ''; ?>>Single</option><option value="married" <?php echo $form['civil_status'] === 'married' ? 'selected' : ''; ?>>Married</option><option value="widowed" <?php echo $form['civil_status'] === 'widowed' ? 'selected' : ''; ?>>Widowed</option><option value="separated" <?php echo $form['civil_status'] === 'separated' ? 'selected' : ''; ?>>Separated</option></select></div>
<div class="input-box"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Mobile Number</label><input type="text" name="contact_number" required placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="<?php echo htmlspecialchars($form['contact_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box full"><label>Address</label><textarea name="address" rows="3" required><?php echo htmlspecialchars($form['address'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
</div></div>
<div class="form-section"><h3>Professional Information</h3><div class="form-grid">
<div class="input-box"><label>PRC License Number</label><input type="text" name="prc_license_number" required value="<?php echo htmlspecialchars($form['prc_license_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>License Expiry Date</label><input type="date" name="license_expiry_date" required value="<?php echo htmlspecialchars($form['license_expiry_date'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Specialization</label><select name="specialization" required><option value="">-- Select --</option><?php foreach (['Civil Engineering', 'Electrical Engineering', 'Mechanical Engineering', 'Structural Engineering', 'Geotechnical Engineering'] as $spec): ?><option value="<?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['specialization'] === $spec ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
<div class="input-box"><label>Years of Experience</label><input type="number" name="years_experience" min="0" value="<?php echo htmlspecialchars($form['years_experience'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Current Position/Title</label><input type="text" name="position_title" value="<?php echo htmlspecialchars($form['position_title'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Availability Status</label><select name="availability_status"><option value="Available" <?php echo $form['availability_status'] === 'Available' ? 'selected' : ''; ?>>Available</option><option value="Assigned" <?php echo $form['availability_status'] === 'Assigned' ? 'selected' : ''; ?>>Assigned</option><option value="On Leave" <?php echo $form['availability_status'] === 'On Leave' ? 'selected' : ''; ?>>On Leave</option></select></div>
</div></div>
<div class="form-section"><h3>Credentials and Emergency Contact</h3><div class="form-grid">
<div class="input-box"><label>Highest Educational Attainment</label><input type="text" name="highest_education" required value="<?php echo htmlspecialchars($form['highest_education'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>School/University</label><input type="text" name="school_university" value="<?php echo htmlspecialchars($form['school_university'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Past Projects Count</label><input type="number" name="past_projects_count" min="0" value="<?php echo htmlspecialchars($form['past_projects_count'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box full"><label>Skills (hold Ctrl/Cmd for multiple)</label><select name="skills[]" multiple size="5"><?php foreach (['AutoCAD', 'Project Management', 'Site Supervision', 'Cost Estimation', 'Structural Analysis', 'Safety Management'] as $skill): ?><option value="<?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($skill, (array)($_POST['skills'] ?? []), true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
<div class="input-box full"><label>Certifications/Trainings</label><textarea name="certifications_trainings" rows="3"><?php echo htmlspecialchars($form['certifications_trainings'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
<div class="input-box full"><label>Notes</label><textarea name="notes" rows="2"><?php echo htmlspecialchars($form['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
<div class="input-box"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($form['emergency_contact_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Emergency Contact Number</label><input type="text" name="emergency_contact_number" value="<?php echo htmlspecialchars($form['emergency_contact_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Emergency Contact Relationship</label><input type="text" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($form['emergency_contact_relationship'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Password</label><input type="password" name="password" required></div>
<div class="input-box"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
</div></div>
<div class="actions"><button type="submit" class="btn-primary">Create Account</button><a href="/engineer/index.php" class="btn-secondary">Back to Login</a></div>
</form>
</div></div>
</body>
</html>
