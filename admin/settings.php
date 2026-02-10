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
        $stmt = $db->prepare("SELECT password FROM employees WHERE id = ?");
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
                        $log_stmt = $db->prepare("INSERT INTO login_logs (employee_id, ip_address, user_agent, status, reason) VALUES (?, ?, ?, 'success', 'Password changed')");
                        if ($log_stmt) {
                            $log_stmt->bind_param('iss', $employee_id, $client_ip, $user_agent);
                            $log_stmt->execute();
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
    <link rel="stylesheet" href="/assets/style.css" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php echo get_app_config_script(); ?>
    <script src="/security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        .nav-item-group { position: relative; display: inline-block; }
        .nav-main-item { display: flex !important; align-items: center; gap: 8px; padding: 10px 16px !important; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: all 0.2s ease; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .nav-main-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px !important; }
        .nav-main-item.active { background: #eff6ff; color: #1e40af; font-weight: 600; }
        .nav-icon { width: 20px; height: 20px; display: inline-block; margin-right: 4px; }
        .dropdown-arrow { display: inline-block; margin-left: 4px; transition: transform 0.3s ease; }
        .nav-item-group.open .dropdown-arrow { transform: rotate(180deg); }
        .nav-submenu { position: absolute; top: 100%; left: 0; background: white; border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); min-width: 220px; margin-top: 8px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; overflow: hidden; }
        .nav-item-group.open .nav-submenu { opacity: 1; visibility: visible; transform: translateY(0); }
        .nav-submenu-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; border-left: 3px solid transparent; white-space: nowrap; }
        .nav-submenu-item:hover { background: #f3f4f6; color: #1f2937; padding-left: 18px; border-left-color: #3b82f6; }
        .nav-submenu-item.active { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; font-weight: 600; }
        .submenu-icon { font-size: 1.1rem; flex-shrink: 0; }
        .nav-submenu-item span:last-child { flex: 1; overflow: hidden; text-overflow: ellipsis; }
        
        .tabs-container { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
        .tab-btn { padding: 12px 20px; border: none; background: none; color: #6b7280; cursor: pointer; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.3s ease; }
        .tab-btn:hover { color: #3b82f6; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
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
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="./dashboard/dashboard.php" data-section="dashboard"><img src="./dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="./project-registration/project_registration.php" class="nav-main-item" id="projectRegToggle" data-section="projects"><img src="./project-registration/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="./project-registration/project_registration.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>New Project</span></a>
                    <a href="./project-registration/registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="./progress-monitoring/progress_monitoring.php" data-section="monitoring"><img src="./progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="./budget-resources/budget_resources.php" data-section="budget"><img src="./budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="./task-milestone/tasks_milestones.php" data-section="tasks"><img src="./task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="./contractors/contractors.php" class="nav-main-item" id="contractorsToggle" data-section="contractors"><img src="./contractors/contractors.png" class="nav-icon">Contractors<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="./contractors/contractors.php" class="nav-submenu-item"><span class="submenu-icon">➕</span><span>Add Contractor</span></a>
                    <a href="./contractors/registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">📋</span><span>View All</span></a>
                </div>
            </div>
            <a href="./project-prioritization/project-prioritization.php" data-section="priorities"><img src="./project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
            <div class="nav-item-group">
                <a href="./settings.php" class="nav-main-item active" id="userMenuToggle" data-section="user"><img src="./dashboard/person.png" class="nav-icon">Settings<span class="dropdown-arrow">▼</span></a>
                <div class="nav-submenu" id="userSubmenu">
                    <a href="./settings.php?tab=password" class="nav-submenu-item"><span class="submenu-icon">🔐</span><span>Change Password</span></a>
                    <a href="./settings.php?tab=security" class="nav-submenu-item"><span class="submenu-icon">🔒</span><span>Security Logs</span></a>
                </div>
            </div>
        </div>
        <div class="nav-divider"></div>
        <div style="padding: 10px 16px; margin-top: auto;">
            <a href="#" id="logoutBtn" style="display: flex; align-items: center; gap: 8px; color: #dc2626; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; padding: 10px 16px; border-radius: 6px;" 
               onmouseover="this.style.background='#fee2e2'; this.style.paddingLeft='18px';" 
               onmouseout="this.style.background='none'; this.style.paddingLeft='16px';"
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

    <section class="main-content">
        <div class="dash-header">
            <h1>Account Settings</h1>
            <p>Manage your account and security preferences</p>
        </div>

        <div class="card" style="margin: 20px; padding: 20px;">
            <!-- Tab Navigation -->
            <div class="tabs-container">
                <button class="tab-btn <?php echo $active_tab === 'password' ? 'active' : ''; ?>" onclick="switchTab('password')">
                    🔐 Change Password
                </button>
                <button class="tab-btn <?php echo $active_tab === 'security' ? 'active' : ''; ?>" onclick="switchTab('security')">
                    🔒 Security Logs
                </button>
            </div>

            <!-- Change Password Tab -->
            <div id="password-tab" class="tab-content <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
                <div style="max-width: 600px;">
                    <h3 style="margin-bottom: 20px;">Change Your Password</h3>
                    
                    <?php if (!empty($error)): ?>
                        <div style="background-color: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                            <strong>Error:</strong> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div style="background-color: #dcfce7; border: 1px solid #86efac; color: #16a34a; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                            <strong>Success:</strong> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter your current password" required 
                                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">New Password</label>
                            <input type="password" name="new_password" placeholder="Enter your new password (min 8 characters)" required 
                                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 500;">Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm your new password" required 
                                   style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        </div>
                        
                        <button type="submit" style="background-color: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                            Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Logs Tab -->
            <div id="security-tab" class="tab-content <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <div>
                    <h3 style="margin-bottom: 20px;">Security Logs</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">View your recent login activities and security events</p>
                    
                    <?php if (count($logs) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                                <thead>
                                    <tr style="background-color: #f3f4f6; border-bottom: 2px solid #d1d5db;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600;">Date & Time</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600;">Status</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600;">IP Address</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600;">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px;"><?php echo date('M d, Y H:i', strtotime($log['login_time'])); ?></td>
                                            <td style="padding: 12px;">
                                                <?php 
                                                    $status_color = $log['status'] === 'success' ? '#10b981' : '#ef4444';
                                                    $status_icon = $log['status'] === 'success' ? '✓' : '✗';
                                                ?>
                                                <span style="background-color: <?php echo $status_color; ?>1a; color: <?php echo $status_color; ?>; padding: 4px 8px; border-radius: 4px; font-weight: 500;">
                                                    <?php echo $status_icon; ?> <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px;"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td style="padding: 12px; color: #6b7280;"><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #6b7280;">
                            <p>No security logs found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">About</a>
            <a href="#">Help</a>
        </div>
        <div class="footer-logo">
            © 2026 LGU Citizen Portal · All Rights Reserved
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ============================================
            // LOGOUT CONFIRMATION
            // ============================================
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = './logout.php';
                        }
                    });
                });
            }

            // ============================================
            // DROPDOWN NAVIGATION
            // ============================================
            const projectRegToggle = document.getElementById('projectRegToggle');
            const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
            const contractorsToggle = document.getElementById('contractorsToggle');
            const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuGroup = userMenuToggle ? userMenuToggle.closest('.nav-item-group') : null;
            
            if (projectRegToggle && projectRegGroup) {
                projectRegToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    projectRegGroup.classList.toggle('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }
            
            if (contractorsToggle && contractorsGroup) {
                contractorsToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    contractorsGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }

            if (userMenuToggle && userMenuGroup) {
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userMenuGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-item-group')) {
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                }
            });
            
            document.querySelectorAll('.nav-submenu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            });
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active to clicked button
            event.target.classList.add('active');
            
            // Update URL
            window.history.pushState({tab: tabName}, '', '?tab=' + tabName);
        }
    </script>

    <script src="/shared-data.js"></script>
    <script src="/shared-toggle.js"></script>
</body>
</html>
