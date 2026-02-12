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
    <!-- Sidebar with logo and IPMS at the top -->
    <aside class="nav" id="navbar">
        <div class="nav-logo" style="display:flex;flex-direction:row;align-items:center;justify-content:center;padding:18px 0 8px 0;gap:10px;">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" style="width:48px;height:48px;" />
            <span class="logo-text" style="font-size:1.5em;font-weight:700;letter-spacing:1px;">IPMS</span>
        </div>
        <!-- No burger button or header, sidebar only -->
        <!-- Profile section -->
        <div class="nav-user" style="border-top:none;padding-top:0;margin-bottom:8px;">
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
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                <?php if ($profile_img): ?>
                    <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon" style="width:48px;height:48px;" />
                <?php else: ?>
                    <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;color:#fff;font-weight:600;font-size:1.1em;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                <div style="font-weight:700;font-size:1.08em;line-height:1.2;margin-top:2px;text-align:center;"> <?php echo htmlspecialchars($user_name); ?> </div>
                <div style="font-size:0.97em;color:#64748b;line-height:1.1;text-align:center;"> <?php echo htmlspecialchars($user_email); ?> </div>
            </div>
        </div>
        <!-- Divider -->
        <hr style="width:80%;margin:10px auto 16px auto;border:0;border-top:1.5px solid #e5e7eb;" />
        <!-- Navigation menu -->
        <nav class="nav-links">
            <a href="user-dashboard.php" class="active"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/user-dashboard/feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/user-dashboard/settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </nav>
        <!-- Logout button at bottom -->
        <div style="margin-top:auto;padding:18px 0 0 0;display:flex;justify-content:center;">
            <a href="/logout.php" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    </aside>

    <!-- Toggle button for mobile (if needed) can be added here if required -->

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

        <!-- Quick Stats -->
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
    (function() {
        const logoutLink = document.getElementById('logoutLink');
        const modal = document.getElementById('logoutModal');
        const cancelBtn = document.getElementById('cancelLogout');
        const confirmBtn = document.getElementById('confirmLogout');
        if (!logoutLink || !modal || !cancelBtn || !confirmBtn) return;

        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
        });

        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        confirmBtn.addEventListener('click', function() {
            window.location.href = '../logout.php';
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Remove duplicate logout modal if present
        const modals = document.querySelectorAll('#logoutModal');
        if (modals.length > 1) {
            for (let i = 1; i < modals.length; i++) {
                modals[i].remove();
            }
        }
    })();
    </script>
</body>
</html>






