<?php
// Import security functions - go up 2 levels to root
require dirname(__DIR__, 1) . '/session-auth.php';
// Database connection
require dirname(__DIR__, 1) . '/database.php';
require dirname(__DIR__, 1) . '/config-path.php';

// Set no-cache headers to prevent back button access to protected pages
set_no_cache_headers();

// Check authentication - redirect to login if not authenticated
check_auth();

// Check for suspicious activity (user-agent changes, etc.)
check_suspicious_activity();

if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? '';
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'password';

// Get client IP
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$client_ip = get_client_ip();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Get current password from database
        $stmt = $db->prepare("SELECT password, email FROM employees WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            $stmt->close();
            
            if ($employee && password_verify($current_password, $employee['password'])) {
                // Hash and update password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                $update_stmt = $db->prepare("UPDATE employees SET password = ? WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param('si', $hashed_password, $employee_id);
                    if ($update_stmt->execute()) {
                        $success = 'Password changed successfully!';
                        // Log the change
                        $employee_email = (string)($employee['email'] ?? ($_SESSION['email'] ?? ''));
                        $log_stmt = $db->prepare("INSERT INTO login_logs (employee_id, email, ip_address, user_agent, status, reason) VALUES (?, ?, ?, ?, 'success', 'Password changed')");
                        if ($log_stmt) {
                            $log_stmt->bind_param('isss', $employee_id, $employee_email, $client_ip, $user_agent);
                            try {
                                $log_stmt->execute();
                            } catch (Throwable $e) {
                                // Do not fail password updates if audit log insert fails.
                            }
                            $log_stmt->close();
                        }
                    } else {
                        $error = 'Error updating password. Please try again.';
                    }
                    $update_stmt->close();
                } else {
                    $error = 'Database error. Please try again later.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}
// Fetch security logs
$logs = [];
if (isset($db) && !$db->connect_error) {
    $stmt = $db->prepare("SELECT employee_id, email, ip_address, login_time, status, reason FROM login_logs WHERE employee_id = ? ORDER BY login_time DESC LIMIT 50");
    if ($stmt) {
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LGU IPMS</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/form-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
    </head>
<body>
    <!-- Sidebar Toggle Button (Floating) -->
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <!-- Navbar menu icon - shows when sidebar is hidden -->
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
            <a href="dashboard.php" data-section="dashboard"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle" data-section="projects"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php" data-section="monitoring"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php" data-section="budget"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php" data-section="tasks"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="contractors.php" class="nav-main-item" id="contractorsToggle" data-section="contractors"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Engineer</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="project-prioritization.php" data-section="priorities"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="settings.php" class="nav-main-item active" id="userMenuToggle" data-section="user"><img src="../assets/images/admin/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                    <a href="citizen-verification.php" class="nav-submenu-item"><span class="submenu-icon">ID</span><span>Citizen Verification</span></a>
                </div>
            </div>
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
        </div>
    </header>

    <section class="main-content settings-page">
        <div class="dash-header">
            <h1>Account Settings</h1>
            <p>Manage your account and security preferences</p>
        </div>

        <div class="settings-layout">
        <div class="card ac-e9b6d4ca settings-card">
            <div class="settings-tabs settings-switcher">
                <a href="settings.php?tab=password" class="tab-btn <?php echo $active_tab === 'password' ? 'active' : ''; ?>" aria-current="<?php echo $active_tab === 'password' ? 'page' : 'false'; ?>">
                    Change Password
                </a>
                <a href="settings.php?tab=security" class="tab-btn <?php echo $active_tab === 'security' ? 'active' : ''; ?>" aria-current="<?php echo $active_tab === 'security' ? 'page' : 'false'; ?>">
                    Security Logs
                </a>
            </div>

            <?php if ($active_tab === 'password'): ?>
            <div class="settings-view">
                <div class="ac-dc271cfe settings-panel settings-password-panel">
                    <h3 class="ac-b75fad00">Change Your Password</h3>
                    <p class="settings-subtitle">Use a strong password with at least 8 characters.</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="ac-565a021d settings-alert settings-alert-error">
                            <strong>Error:</strong> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="ac-6c5498e2 settings-alert settings-alert-success">
                            <strong>Success:</strong> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="settings-form">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="ac-4a3180e2">
                            <label class="ac-37c29296">Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter your current password" required 
                                   class="ac-6f762f4a">
                        </div>
                        
                        <div class="ac-4a3180e2">
                            <label class="ac-37c29296">New Password</label>
                            <input type="password" name="new_password" placeholder="Enter your new password (min 8 characters)" required 
                                   class="ac-6f762f4a">
                        </div>
                        
                        <div class="ac-b75fad00">
                            <label class="ac-37c29296">Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm your new password" required 
                                   class="ac-6f762f4a">
                        </div>
                        
                        <button type="submit" class="ac-f84d9680">
                            Update Password
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="settings-view">
                <div class="settings-panel">
                    <h3 class="ac-b75fad00">Security Logs</h3>
                    <p class="ac-bcaa02df">View your recent login activities and security events</p>
                    
                    <?php if (count($logs) > 0): ?>
                        <div class="ac-42d4450c table-wrap settings-logs-wrap">
                            <table class="ac-297d90f5">
                                <thead>
                                    <tr class="ac-7707967d">
                                        <th class="ac-34829204">Date & Time</th>
                                        <th class="ac-34829204">Status</th>
                                        <th class="ac-34829204">IP Address</th>
                                        <th class="ac-34829204">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="ac-5817c522">
                                            <td class="ac-e850a73c"><?php echo date('M d, Y H:i', strtotime($log['login_time'])); ?></td>
                                            <td class="ac-e850a73c">
                                                <?php 
                                                    $status_class = $log['status'] === 'success' ? 'status-chip-success' : 'status-chip-failed';
                                                    $status_icon = $log['status'] === 'success' ? '&#10003;' : '&#10007;';
                                                ?>
                                                <span class="status-chip <?php echo $status_class; ?>">
                                                    <?php echo $status_icon; ?> <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td class="ac-e850a73c"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td class="ac-dd211560"><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="ac-a6302130 settings-empty">
                            <p>No security logs found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        </div>
    </section>

    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
</body>
</html>
















