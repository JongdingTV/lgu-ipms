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
    'last_name' => '',
    'email' => '',
    'contact_number' => '',
    'position_title' => '',
    'specialization' => ''
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

function ec_check_duplicates(mysqli $db, string $email): array
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
    return $errors;
}

function ec_validate(array $input): array
{
    $errors = [];
    $data = [];
    foreach (['first_name','last_name','email','contact_number','position_title','specialization'] as $k) {
        $data[$k] = trim((string)($input[$k] ?? ''));
    }

    $data['email'] = strtolower($data['email']);

    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');

    if ($data['first_name'] === '' || $data['last_name'] === '') $errors[] = 'First and last name are required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^(09\d{9}|\+639\d{9})$/', str_replace([' ', '-'], '', $data['contact_number']))) $errors[] = 'Mobile format must be 09XXXXXXXXX or +639XXXXXXXXX.';
    if ($data['specialization'] === '') $errors[] = 'Specialization is required.';

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

        $columns = ['first_name', 'last_name', 'full_name', 'email', 'specialization'];
        $types = 'sssss';
        $values = [
            $data['first_name'],
            $data['last_name'],
            trim($data['first_name'] . ' ' . $data['last_name']),
            $data['email'],
            $data['specialization']
        ];

        if (ec_table_has_column($db, 'engineers', 'contact_number')) { $columns[] = 'contact_number'; $types .= 's'; $values[] = $data['contact_number']; }
        if (ec_table_has_column($db, 'engineers', 'position_title')) { $columns[] = 'position_title'; $types .= 's'; $values[] = $data['position_title']; }
        if (ec_table_has_column($db, 'engineers', 'availability_status')) { $columns[] = 'availability_status'; $types .= 's'; $values[] = 'Available'; }
        if (ec_table_has_column($db, 'engineers', 'employee_id')) { $columns[] = 'employee_id'; $types .= 'i'; $values[] = $employeeId; }

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
        if (!$errors) $errors = array_merge($errors, ec_check_duplicates($db, $data['email']));
        if (!$errors) {
            try {
                ec_create_account($db, $data);
                $success = 'Engineer account created successfully. Sign in to continue.';
                $_SESSION['engineer_create_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['engineer_create_token'];
                foreach ($form as $k => $v) $form[$k] = '';
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
body.user-signup-page .card{width:100%;max-width:780px;background:rgba(255,255,255,.95);border:1px solid rgba(255,255,255,.75);border-radius:20px;padding:30px 26px;box-shadow:0 24px 56px rgba(2,6,23,.3)}
body.user-signup-page .card-header{text-align:center;margin-bottom:18px}
body.user-signup-page .icon-top{width:72px;height:72px;object-fit:contain;margin:2px auto 10px;display:block}
body.user-signup-page .title{margin:0 0 6px;font-size:1.7rem;line-height:1.2;color:var(--page-navy)}
body.user-signup-page .subtitle{margin:0;color:var(--page-muted)}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.form-grid .full{grid-column:1 / -1}
.input-box label{display:block;font-size:.86rem;color:#1e293b;margin-bottom:6px}
.input-box input,.input-box select{width:100%;min-height:46px;border-radius:11px;border:1px solid rgba(148,163,184,.45);background:#fff;padding:10px 12px;font-size:.95rem;color:#0f172a;outline:none}
.input-box input:focus,.input-box select:focus{border-color:var(--page-sky);box-shadow:0 0 0 4px rgba(63,131,201,.15)}
.actions{margin-top:18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.btn-primary{min-width:170px;height:46px;border:0;border-radius:11px;background:linear-gradient(135deg,#1d4e89,#3f83c9);color:#fff;font-size:.98rem;font-weight:600;cursor:pointer}
.btn-secondary{min-width:130px;height:46px;border:1px solid rgba(148,163,184,.55);border-radius:11px;background:#fff;color:#0f172a;font-size:.95rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.btn-primary:hover,.btn-secondary:hover{filter:brightness(1.02)}
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
    .form-grid{grid-template-columns:1fr;gap:10px}
    .form-grid .full{grid-column:auto}
    .actions{margin-top:14px;gap:8px;justify-content:stretch}
    .btn-primary,.btn-secondary{width:100%;min-width:0;min-height:44px}
}
</style>
</head>
<body class="user-signup-page">
<header class="nav"><div class="nav-logo"><img src="/assets/images/icons/ipms-icon.png" alt="LGU Logo"> Local Government Unit Portal</div><a href="/engineer/index.php" class="home-btn">Back to Login</a></header>
<div class="wrapper"><div class="card">
<div class="card-header"><img src="/assets/images/icons/ipms-icon.png" class="icon-top" alt="LGU Logo"><h2 class="title">Create Engineer Account</h2><p class="subtitle">Core account information for system access.</p></div>
<?php if (!empty($errors)): ?><div class="error-box"><?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="ok-box"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="form-grid">
<div class="input-box"><label>First Name</label><input type="text" name="first_name" required value="<?php echo htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Last Name</label><input type="text" name="last_name" required value="<?php echo htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Official Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Mobile Number</label><input type="text" name="contact_number" required placeholder="09XXXXXXXXX or +639XXXXXXXXX" value="<?php echo htmlspecialchars($form['contact_number'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Position/Title</label><input type="text" name="position_title" value="<?php echo htmlspecialchars($form['position_title'], ENT_QUOTES, 'UTF-8'); ?>"></div>
<div class="input-box"><label>Specialization</label><select name="specialization" required><option value="">-- Select --</option><?php foreach (['Civil Engineering', 'Electrical Engineering', 'Mechanical Engineering', 'Structural Engineering', 'Geotechnical Engineering'] as $spec): ?><option value="<?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $form['specialization'] === $spec ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
<div class="input-box"></div>
<div class="input-box"><label>Password</label><input type="password" name="password" required></div>
<div class="input-box"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
</div>
<div class="actions"><button type="submit" class="btn-primary">Create Account</button><a href="/engineer/index.php" class="btn-secondary">Back to Login</a></div>
</form>
</div></div>
</body>
</html>
