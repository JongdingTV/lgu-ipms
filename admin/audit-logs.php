<?php
// Start session first
session_start();

// Include configuration and database files first
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? '';

// Session timeout (30 minutes)
$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    session_destroy();
    header('Location: /admin/index.php?session_expired=1');
    exit;
} else {
    $_SESSION['last_activity'] = time();
}

// Fetch login logs
$logs = [];
if (isset($db) && !$db->connect_error) {
    $stmt = $db->prepare("SELECT employee_id, email, ip_address, login_time, status, reason FROM login_logs ORDER BY login_time DESC LIMIT 100");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    }
}

// Count failed attempts in last 24 hours
$failed_attempts = 0;
$locked_count = 0;
if (isset($db) && !$db->connect_error) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM login_logs WHERE status = 'failed' AND login_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $failed_attempts = $row['count'];
        $stmt->close();
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM locked_accounts WHERE locked_until > NOW()");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $locked_count = $row['count'];
        $stmt->close();
    }
}

if (isset($db)) {
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Logs - LGU Admin</title>
<link rel="icon" type="image/png" href="../logocityhall.png">
<link rel="stylesheet" href="../assets/css/admin.css?v=20260212j">
</head>
<body>
<div class="container">
    <div class="btn-back">
        <a href="/admin/dashboard.php" class="btn btn-outline-light">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <h1 class="ac-2bc15e3e">
        <i class="fas fa-shield-alt"></i> Security & Audit Logs
    </h1>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4><i class="fas fa-exclamation-triangle ac-984655ef"></i> Failed Logins (24h)</h4>
            <div class="number"><?php echo $failed_attempts; ?></div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-lock ac-6aefeaa4"></i> Locked Accounts</h4>
            <div class="number"><?php echo $locked_count; ?></div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-user ac-04463d11"></i> Logged In User</h4>
            <div class="number"><?php echo htmlspecialchars($employee_name); ?></div>
        </div>
    </div>

    <!-- Login Activity Log -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Login Activity Log</h3>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox ac-1913582c"></i>
                <p>No login activity recorded yet.</p>
            </div>
            <?php else: ?>
            <div class="ac-42d4450c">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>IP Address</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $log['status']; ?>">
                                    <?php 
                                    echo match($log['status']) {
                                        'success' => '<i class="fas fa-check-circle"></i> Success',
                                        'failed' => '<i class="fas fa-times-circle"></i> Failed',
                                        'locked' => '<i class="fas fa-lock"></i> Locked',
                                        default => $log['status']
                                    };
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['login_time'])); ?></td>
                            <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                            <td><?php echo !empty($log['reason']) ? htmlspecialchars($log['reason']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Security Tips -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lightbulb"></i> Security Best Practices</h3>
        </div>
        <div class="card-body">
            <ul class="ac-648149ce">
                <li>✅ Change your password regularly (at least every 90 days)</li>
                <li>✅ Use strong passwords with mixed characters</li>
                <li>✅ Never share your credentials with anyone</li>
                <li>✅ Log out when finished, especially on shared computers</li>
                <li>✅ Review login activity regularly for suspicious access</li>
                <li>✅ Report any unauthorized access immediately</li>
            </ul>
        </div>
    </div>
</div>
<script src="../assets/js/admin.js?v=20260212j"></script>
</body>
</html>











