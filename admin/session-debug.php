<?php
/**
 * Session Debug Page
 * Check what session variables are set
 */

session_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
<link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
</head>
<body>
    <div class="container">
        <h1>🔍 Session Debug Information</h1>
        <p>Current URL: <?php echo $_SERVER['REQUEST_URI']; ?></p>
        <p>Server: <?php echo $_SERVER['SERVER_NAME']; ?></p>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Session Variables</h3>
            </div>
            <div class="card-body">
                <h5>All Session Data:</h5>
                <pre><?php print_r($_SESSION); ?></pre>

                <hr>

                <h5>Individual Checks:</h5>
                
                <div class="session-var <?php echo isset($_SESSION['employee_id']) ? 'set' : 'not-set'; ?>">
                    <strong>$_SESSION['employee_id']:</strong>
                    <?php echo isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] . ' ✅' : 'NOT SET ❌'; ?>
                </div>

                <div class="session-var <?php echo isset($_SESSION['admin_verified']) ? 'set' : 'not-set'; ?>">
                    <strong>$_SESSION['admin_verified']:</strong>
                    <?php echo isset($_SESSION['admin_verified']) ? $_SESSION['admin_verified'] . ' ✅' : 'NOT SET ❌'; ?>
                </div>

                <div class="session-var <?php echo isset($_SESSION['verified_employee_id']) ? 'set' : 'not-set'; ?>">
                    <strong>$_SESSION['verified_employee_id']:</strong>
                    <?php echo isset($_SESSION['verified_employee_id']) ? $_SESSION['verified_employee_id'] . ' ✅' : 'NOT SET ❌'; ?>
                </div>

                <div class="session-var <?php echo isset($_SESSION['admin_verification_code']) ? 'set' : 'not-set'; ?>">
                    <strong>$_SESSION['admin_verification_code']:</strong>
                    <?php echo isset($_SESSION['admin_verification_code']) ? $_SESSION['admin_verification_code'] . ' ✅' : 'NOT SET ❌'; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h3>Access Control Check</h3>
            </div>
            <div class="card-body">
                <?php
                $can_access = isset($_SESSION['employee_id']) || isset($_SESSION['admin_verified']);
                ?>
                <h5>Can Access Manage Employees?</h5>
                <p>
                    <strong><?php echo $can_access ? '✅ YES' : '❌ NO'; ?></strong>
                </p>
                <p>Reason: <?php 
                    if (isset($_SESSION['employee_id'])) {
                        echo 'Logged in as employee_id: ' . $_SESSION['employee_id'];
                    } elseif (isset($_SESSION['admin_verified'])) {
                        echo 'Verified through 2FA (admin_verified = true)';
                    } else {
                        echo 'Neither logged in nor verified - redirect to admin-verify.php';
                    }
                ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning">
                <h3>Navigation Links</h3>
            </div>
            <div class="card-body">
                <a href="/index.php" class="btn btn-primary">Go to Login</a>
                <a href="/admin/manage-employees.php" class="btn btn-success">Go to Manage Employees</a>
                <a href="/admin/index.php" class="btn btn-info">Go to Admin Dashboard</a>
            </div>
        </div>

        <div class="alert alert-warning">
            <h5>If Not Verified:</h5>
            <ol>
                <li>Click "Go to 2FA Verification" button above</li>
                <li>Complete the 2FA process (Employee ID + Password + Code)</li>
                <li>Come back to this page to see updated session variables</li>
                <li>Then click "Go to Manage Employees"</li>
            </ol>
        </div>
    </div>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
</body>
</html>








