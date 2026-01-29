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
<link rel="icon" type="image/png" href="/logocityhall.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
        min-height: 100vh;
        padding: 2rem;
    }
    .container {
        max-width: 1000px;
        margin: 0 auto;
    }
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    .card-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 1.5rem;
        border: none;
    }
    .card-header h3 {
        margin: 0;
        font-size: 1.3rem;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-card h4 {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    .stat-card .number {
        font-size: 2rem;
        font-weight: bold;
        color: #1e3a5f;
    }
    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .status-success {
        background: #e5ffe5;
        color: #27ae60;
    }
    .status-failed {
        background: #ffe5e5;
        color: #c3423f;
    }
    .status-locked {
        background: #fff5e5;
        color: #e67e22;
    }
    table {
        font-size: 0.9rem;
    }
    th {
        background: #f5f5f5;
        color: #1e3a5f;
        font-weight: 600;
    }
    tr:hover {
        background: #f9f9f9;
    }
    .btn-back {
        margin-bottom: 1.5rem;
    }
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #666;
    }
</style>
</head>
<body>
<div class="container">
    <div class="btn-back">
        <a href="/admin/dashboard/dashboard.php" class="btn btn-outline-light">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <h1 style="color: white; margin-bottom: 2rem;">
        <i class="fas fa-shield-alt"></i> Security & Audit Logs
    </h1>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Failed Logins (24h)</h4>
            <div class="number"><?php echo $failed_attempts; ?></div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-lock" style="color: #e67e22;"></i> Locked Accounts</h4>
            <div class="number"><?php echo $locked_count; ?></div>
        </div>
        <div class="stat-card">
            <h4><i class="fas fa-user" style="color: #27ae60;"></i> Logged In User</h4>
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
                <i class="fas fa-inbox" style="font-size: 2rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p>No login activity recorded yet.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
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
            <ul style="margin-bottom: 0;">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
