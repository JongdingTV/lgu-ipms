<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.engineers.manage', ['admin', 'department_admin', 'super_admin']);
check_suspicious_activity();

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

function rc_table_has_column(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $exists;
}

$columns = [
    'company',
    'owner',
    'license',
    'email',
    'phone',
    'address',
    'specialization',
    'experience',
    'rating',
    'status'
];
if (rc_table_has_column($db, 'contractors', 'contractor_type')) $columns[] = 'contractor_type';
if (rc_table_has_column($db, 'contractors', 'contact_person_first_name')) $columns[] = 'contact_person_first_name';
if (rc_table_has_column($db, 'contractors', 'contact_person_last_name')) $columns[] = 'contact_person_last_name';
if (rc_table_has_column($db, 'contractors', 'contact_person_role')) $columns[] = 'contact_person_role';
if (rc_table_has_column($db, 'contractors', 'license_expiration_date')) $columns[] = 'license_expiration_date';
if (rc_table_has_column($db, 'contractors', 'tin')) $columns[] = 'tin';
if (rc_table_has_column($db, 'contractors', 'created_at')) $columns[] = 'created_at';
if (rc_table_has_column($db, 'contractors', 'account_employee_id')) $columns[] = 'account_employee_id';

$selectCols = 'c.id, ' . implode(', ', array_map(static fn($c) => 'c.' . $c, $columns));
$joinEmployee = rc_table_has_column($db, 'contractors', 'account_employee_id') && rc_table_has_column($db, 'employees', 'id');
if ($joinEmployee) {
    $selectCols .= ", e.role AS account_role, e.account_status AS account_status";
}

$sql = "SELECT {$selectCols}
        FROM contractors c";
if ($joinEmployee) {
    $sql .= " LEFT JOIN employees e ON e.id = c.account_employee_id";
}
$sql .= " ORDER BY c.id DESC";

$rows = [];
$res = $db->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
}
$db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Contractors - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    <style>
        .rc-toolbar { display: flex; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .rc-search {
            width: min(460px, 100%);
            min-height: 42px;
            border: 1px solid #c8d8ea;
            border-radius: 10px;
            padding: 10px 12px;
            font: inherit;
        }
        .rc-count { font-size: .9rem; color: #475569; font-weight: 600; }
        .rc-tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: .74rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .rc-tag.active { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
        .rc-tag.inactive { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
        .rc-tag.pending { color: #92400e; background: #fef3c7; border-color: #fde68a; }
    </style>
</head>
<body>
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
        <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS</span>
    </div>
    <div class="nav-links">
        <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
        <div class="nav-item-group">
            <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu" id="projectRegSubmenu">
                <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
            </div>
        </div>
        <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
        <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
        <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
        <div class="nav-item-group">
            <a href="engineers.php" class="nav-main-item active" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
            <div class="nav-submenu show" id="contractorsSubmenu">
                <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                <a href="contractors.php" class="nav-submenu-item active"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
            </div>
        </div>
        <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
        <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
    </div>
    <div class="nav-divider"></div>
    <div class="nav-action-footer">
        <a href="/admin/logout.php" class="btn-logout nav-logout"><span>Logout</span></a>
    </div>
</header>

<section class="main-content">
    <div class="dash-header">
        <h1>Registered Contractors</h1>
        <p>Showing real contractor accounts registered in the system database.</p>
    </div>

    <div class="pm-section card">
        <div class="rc-toolbar">
            <input id="rcSearch" class="rc-search" type="search" placeholder="Search by company, contact, email, specialization, license...">
            <div class="rc-count">Total: <span id="rcTotal"><?php echo count($rows); ?></span></div>
        </div>
        <div class="table-wrap">
            <table class="table" id="rcTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company / Contractor</th>
                        <th>Contact Person</th>
                        <th>Email / Phone</th>
                        <th>License / TIN</th>
                        <th>Specialization</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Account</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $company = trim((string)($r['company'] ?? ''));
                        $owner = trim((string)($r['owner'] ?? ''));
                        $cfirst = trim((string)($r['contact_person_first_name'] ?? ''));
                        $clast = trim((string)($r['contact_person_last_name'] ?? ''));
                        $contactRole = trim((string)($r['contact_person_role'] ?? ''));
                        $contactName = trim($cfirst . ' ' . $clast);
                        if ($contactName === '') $contactName = $owner !== '' ? $owner : 'N/A';
                        $statusRaw = strtolower(trim((string)($r['status'] ?? 'pending')));
                        $statusClass = in_array($statusRaw, ['active', 'approved', 'verified'], true) ? 'active' : (in_array($statusRaw, ['inactive', 'rejected', 'suspended', 'blacklisted'], true) ? 'inactive' : 'pending');
                        $accountStatus = strtolower(trim((string)($r['account_status'] ?? 'active')));
                    ?>
                    <tr>
                        <td><?php echo (int)($r['id'] ?? 0); ?></td>
                        <td><strong><?php echo htmlspecialchars($company !== '' ? $company : ($owner !== '' ? $owner : 'N/A'), ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?php echo htmlspecialchars((string)($r['contractor_type'] ?? 'Contractor'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($contactRole !== '' ? $contactRole : '-', ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><?php echo htmlspecialchars((string)($r['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars((string)($r['phone'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><?php echo htmlspecialchars((string)($r['license'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars((string)($r['tin'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><?php echo htmlspecialchars((string)($r['specialization'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)($r['experience'] ?? 0); ?> yrs</td>
                        <td><span class="rc-tag <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)($r['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars((string)($r['account_role'] ?? 'contractor'), ENT_QUOTES, 'UTF-8'); ?><br><small><?php echo htmlspecialchars($accountStatus !== '' ? $accountStatus : '-', ENT_QUOTES, 'UTF-8'); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
(function () {
    const search = document.getElementById('rcSearch');
    const table = document.getElementById('rcTable');
    const total = document.getElementById('rcTotal');
    if (!search || !table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    function filterRows() {
        const q = (search.value || '').trim().toLowerCase();
        let count = 0;
        rows.forEach(function (row) {
            const txt = (row.textContent || '').toLowerCase();
            const ok = q === '' || txt.indexOf(q) !== -1;
            row.style.display = ok ? '' : 'none';
            if (ok) count += 1;
        });
        total.textContent = String(count);
    }

    search.addEventListener('input', filterRows);
})();
</script>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>
</html>

