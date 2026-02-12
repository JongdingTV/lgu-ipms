<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';

// Protect page
set_no_cache_headers();

require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}

// Get user info from database
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ($user['first_name'] . ' ' . $user['last_name']);

// Get project statistics
$totalProjects = $db->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
$inProgressProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('Approved', 'For Approval')")->fetch_assoc()['count'];
$completedProjects = $db->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count'];
$totalBudget = $db->query("SELECT COALESCE(SUM(budget), 0) as total FROM projects")->fetch_assoc()['total'];

// Get recent projects
$recentProjects = $db->query("SELECT id, name, location, status, budget FROM projects ORDER BY created_at DESC LIMIT 5");
// Get user feedback from database
$feedbacks = $db->query("SELECT id, subject, category, status, date_submitted FROM feedback ORDER BY date_submitted DESC LIMIT 20");
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/user-dashboard/user-dashboard.css">
    <?php echo get_app_config_script(); ?>
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>


    <!-- Sidebar with overlay for mobile -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <aside class="nav sidebar-animated" id="navbar">
        <!-- Logo and IPMS side by side at top -->
        <div class="nav-logo admin-sidebar-logo" style="display:flex;flex-direction:row;align-items:center;justify-content:center;padding:18px 0 8px 0;gap:10px;">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" style="width:48px;height:48px;" />
            <span class="logo-text" style="font-size:1.5em;font-weight:700;letter-spacing:1px;">IPMS</span>
        </div>

            <!-- Mobile burger button (top left, only visible on mobile) -->
            <button id="sidebarBurgerBtn" class="sidebar-burger-btn mobile-only" aria-label="Open sidebar" type="button">
                <span class="burger-bar"></span>
                <span class="burger-bar"></span>
                <span class="burger-bar"></span>
            </button>
            <style>
            .sidebar-burger-btn.mobile-only {
                display: none;
                position: fixed;
                top: 18px;
                left: 18px;
                z-index: 1002;
                width: 48px;
                height: 48px;
                background: #fff;
                border: none;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(30,58,138,0.10);
                transition: box-shadow 0.2s;
                cursor: pointer;
                outline: none;
                justify-content: center;
                align-items: center;
                padding: 0;
            }
            .sidebar-burger-btn.mobile-only:active,
            .sidebar-burger-btn.mobile-only:focus {
                box-shadow: 0 4px 16px rgba(30,58,138,0.18);
            }
            .sidebar-burger-btn.mobile-only .burger-bar {
                display: block;
                width: 28px;
                height: 4px;
                margin: 5px auto;
                background: #1e3a8a;
                border-radius: 2px;
                transition: all 0.3s cubic-bezier(.4,2,.6,1);
            }
            .sidebar-burger-btn.mobile-only.open .burger-bar:nth-child(1) {
                transform: translateY(9px) rotate(45deg);
            }
            .sidebar-burger-btn.mobile-only.open .burger-bar:nth-child(2) {
                opacity: 0;
            }
            .sidebar-burger-btn.mobile-only.open .burger-bar:nth-child(3) {
                transform: translateY(-9px) rotate(-45deg);
            }
            @media (max-width: 991px) {
                .sidebar-burger-btn.mobile-only {
                    display: flex !important;
                }
                #navbar {
                    transform: translateX(-110%);
                    transition: transform 0.3s cubic-bezier(.4,2,.6,1);
                    position: fixed;
                    left: 0;
                    top: 0;
                    height: 100vh;
                    z-index: 1003;
                }
                #navbar.sidebar-open {
                    transform: translateX(0);
                }
            }
            @media (min-width: 992px) {
                #navbar {
                    transform: none !important;
                    position: static !important;
                    height: auto !important;
                    z-index: 1001;
                }
            }
            </style>
        <!-- Profile section, centered -->
        <div class="nav-user" style="display:flex;flex-direction:column;align-items:center;gap:6px;margin-bottom:8px;">
            <?php
            $profile_img = '';
            $user_email = isset($user['email']) ? $user['email'] : '';
            $user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : 'User');
            $initials = '';
            if ($user_name) {
                $parts = explode(' ', $user_name);
                foreach ($parts as $p) {
                    if ($p) $initials .= strtoupper($p[0]);
                }
            }
            if (!function_exists('stringToColor')) {
                function stringToColor($str) {
                    $colors = [
                        '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3',
                        '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39',
                        '#FFEB3B', '#FFC107', '#FF9800', '#FF5722', '#795548', '#607D8B'
                    ];
                    $hash = 0;
                    for ($i = 0; $i < strlen($str); $i++) {
                        $hash = ord($str[$i]) + (($hash << 5) - $hash);
                    }
                    $index = abs($hash) % count($colors);
                    return $colors[$index];
                }
            }
            $bgcolor = stringToColor($user_name);
            ?>
            <?php if ($profile_img): ?>
                <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon" style="width:48px;height:48px;" />
            <?php else: ?>
                <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;color:#fff;font-weight:600;font-size:1.1em;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
            <div class="user-name" style="font-weight:700;font-size:1.08em;line-height:1.2;margin-top:2px;text-align:center;"> <?php echo htmlspecialchars($user_name); ?> </div>
            <div class="user-email" style="font-size:0.97em;color:#64748b;line-height:1.1;text-align:center;"> <?php echo htmlspecialchars($user_email); ?> </div>
        </div>
        <hr class="sidebar-divider" />
        <nav class="nav-links">
            <a href="user-dashboard.php" class="active"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/user-dashboard/feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/user-dashboard/settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </nav>
        <div class="sidebar-logout-container">
            <a href="/logout.php" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
    </aside>

    <section class="main-content">
        <div class="dash-header">
            <h1>User Dashboard</h1>
            <p>Infrastructure Project Management System</p>
        </div>

        <!-- Key Metrics Section -->
        <div class="metrics-container modern-metrics">
            <div class="metric-card accent-blue">
                <div class="metric-icon-bg">
                    <img src="../assets/images/admin/chart.png" alt="Total Projects" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>Projects in Your Area</h3>
                    <p class="metric-value"><?php echo $totalProjects; ?></p>
                    <span class="metric-status">Active & Completed</span>
                </div>
            </div>
            <div class="metric-card accent-yellow">
                <div class="metric-icon-bg">
                    <img src="../assets/images/admin/sandclock.png" alt="In Progress" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value"><?php echo $inProgressProjects; ?></p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card accent-green">
                <div class="metric-icon-bg">
                    <img src="../assets/images/admin/check.png" alt="Completed" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value"><?php echo $completedProjects; ?></p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card accent-purple">
                <div class="metric-icon-bg">
                    <img src="../assets/images/admin/budget.png" alt="Total Budget" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>Allocated Budget</h3>
                    <p class="metric-value">₱<?php echo number_format($totalBudget, 2); ?></p>
                    <span class="metric-status">For your area</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-box">
                <h3>Project Status Distribution</h3>
                <div class="chart-placeholder">
                    <div class="status-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #10b981;"></span>
                            <span id="completedPercent">Completed: 0%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #f59e0b;"></span>
                            <span id="inProgressPercent">In Progress: 0%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #ef4444;"></span>
                            <span id="otherPercent">Other: 0%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-box">
                <h3>Budget Utilization</h3>
                <div class="chart-placeholder">
                    <div class="progress-bar">
                        <div class="progress-fill" id="budgetProgressFill" style="width: 0%;"></div>
                    </div>
                    <p id="budgetUtilizationText" style="margin-top: 10px; font-size: 0.9em; color: #666;">Budget utilization: 0% Used</p>
                </div>
            </div>
        </div>

        <!-- Recent Projects Section -->
        <div class="recent-projects">
            <h3>Projects in Your Area</h3>
            <table class="projects-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Budget</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($recentProjects && $recentProjects->num_rows > 0) {
                        while ($project = $recentProjects->fetch_assoc()) {
                            $statusColor = 'pending';
                            if ($project['status'] === 'Completed') $statusColor = 'completed';
                            elseif ($project['status'] === 'Approved') $statusColor = 'approved';
                            elseif ($project['status'] === 'For Approval') $statusColor = 'pending';
                            elseif ($project['status'] === 'On-hold') $statusColor = 'onhold';
                            elseif ($project['status'] === 'Cancelled') $statusColor = 'cancelled';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                <td><span class="status-badge <?php echo $statusColor; ?>"><?php echo $project['status']; ?></span></td>
                                <td>
                                    <div class="progress-small">
                                        <div class="progress-fill-small" style="width: 0%;"></div>
                                    </div>
                                </td>
                                <td>₱<?php echo number_format($project['budget'], 2); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: #999;">No projects registered yet</td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Stats: Feedback Review Table Only (no feedback form) -->
        <div class="feedback-review recent-projects">
            <h3>Your Feedback Review</h3>
            <table class="projects-table">
                <thead style="background:#f1f5f9;">
                    <tr>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Control No.</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Date</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Subject</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Category</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($feedbacks && $feedbacks->num_rows > 0) {
                    $count = 1;
                    while ($fb = $feedbacks->fetch_assoc()) {
                        $controlNo = 'CTL-' . str_pad($count, 3, '0', STR_PAD_LEFT);
                        echo '<tr>';
                        echo '<td style="padding:12px 8px;"><strong>' . $controlNo . '</strong></td>';
                        echo '<td style="padding:12px 8px;">' . date('M d, Y', strtotime($fb['date_submitted'])) . '</td>';
                        echo '<td style="padding:12px 8px;">' . htmlspecialchars($fb['subject']) . '</td>';
                        echo '<td style="padding:12px 8px;">' . htmlspecialchars($fb['category']) . '</td>';
                        echo '<td style="padding:12px 8px;"><span class="status-badge">' . htmlspecialchars($fb['status']) . '</span></td>';
                        echo '</tr>';
                        $count++;
                    }
                } else {
                    echo '<tr><td colspan="5" style="text-align:center;padding:20px;color:#999;">No feedback submitted yet</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <!-- Remove duplicate logout modal -->


    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="user-dashboard.js"></script>
    <script>
    // Sidebar burger and overlay logic (mobile burger only, with animation)
    (function() {
        const sidebar = document.getElementById('navbar');
        const burger = document.getElementById('sidebarBurgerBtn');
        const overlay = document.getElementById('sidebarOverlay');
        function updateBurgerVisibility() {
            if (window.innerWidth <= 991) {
                burger.style.display = 'flex';
            } else {
                burger.style.display = 'none';
                closeSidebar();
            }
        }
        function openSidebar() {
            sidebar.classList.add('sidebar-open');
            overlay.classList.add('sidebar-overlay-active');
            document.body.classList.add('sidebar-opened');
            burger.classList.add('open');
        }
        function closeSidebar() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('sidebar-overlay-active');
            document.body.classList.remove('sidebar-opened');
            burger.classList.remove('open');
        }
        burger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', updateBurgerVisibility);
        document.addEventListener('DOMContentLoaded', updateBurgerVisibility);
    })();
    // Logout confirmation (if needed)
    document.addEventListener('DOMContentLoaded', function() {
        window.setupLogoutConfirmation && window.setupLogoutConfirmation();
    });
    </script>
</body>
</html>






