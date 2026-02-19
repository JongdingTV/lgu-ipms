<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

if (!isset($db) || $db->connect_error) {
    die('Database connection failed: ' . ($db->connect_error ?? 'Unknown error'));
}

function cv_users_has_verification_status(mysqli $db): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'verification_status'
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

$message = '';
$error = '';
$hasVerificationStatus = cv_users_has_verification_status($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasVerificationStatus) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    if ($userId <= 0 || !in_array($status, ['verified', 'rejected', 'pending'], true)) {
        $error = 'Invalid verification request.';
    } else {
        $stmt = $db->prepare('UPDATE users SET verification_status = ? WHERE id = ?');
        if (!$stmt) {
            $error = 'Unable to prepare update query.';
        } else {
            $stmt->bind_param('si', $status, $userId);
            if ($stmt->execute()) {
                $message = 'Verification status updated.';
            } else {
                $error = 'Failed to update verification status.';
            }
            $stmt->close();
        }
    }
}

$users = [];
$sql = $hasVerificationStatus
    ? "SELECT id, first_name, middle_name, last_name, email, address, id_type, id_number, id_upload, verification_status, created_at FROM users ORDER BY created_at DESC"
    : "SELECT id, first_name, middle_name, last_name, email, address, id_type, id_number, id_upload, created_at FROM users ORDER BY created_at DESC";
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

$db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Citizen Verification - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
<section class="main-content" style="margin-left:0;width:100%;">
    <div class="dash-header">
        <h1>Citizen Verification</h1>
        <p>Review uploaded IDs and approve or reject citizen accounts.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!$hasVerificationStatus): ?>
        <div class="alert alert-danger">Missing column: <code>verification_status</code>. Run database update first.</div>
    <?php endif; ?>

    <div class="recent-projects card">
        <h3>Registered Citizens</h3>
        <div class="table-wrap dashboard-table-wrap">
            <table class="projects-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>ID Type</th>
                    <th>ID Number</th>
                    <th>ID File</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $fullName = trim((string) (($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? '')));
                        $status = strtolower((string) ($u['verification_status'] ?? 'pending'));
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['id_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (!empty($u['id_upload'])): ?>
                                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars((string) $u['id_upload'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View</a>
                                <?php else: ?>
                                    No file
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge pending"><?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo !empty($u['created_at']) ? htmlspecialchars(date('M d, Y', strtotime((string) $u['created_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                            <td>
                                <?php if ($hasVerificationStatus): ?>
                                    <form method="post" style="display:flex;gap:6px;">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                        <button class="btn btn-primary" type="submit" name="status" value="verified">Approve</button>
                                        <button class="btn btn-danger" type="submit" name="status" value="rejected">Reject</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="ac-a004b216">No citizen accounts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
<script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>
