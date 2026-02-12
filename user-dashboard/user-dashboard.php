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
    <div id="sidebarBurgerBtn" style="position:fixed;top:24px;right:32px;z-index:200;display:flex;align-items:center;cursor:pointer;">
        <button class="navbar-menu-icon" title="Show/hide sidebar" style="background:none;border:none;padding:0;outline:none;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
    <?php include __DIR__ . '/user-sidebar.php'; ?>

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
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('navbar');
    const burgerBtn = document.getElementById('sidebarBurgerBtn');
    if (sidebar && burgerBtn) {
        burgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            if (sidebar.classList.contains('sidebar-open')) {
                sidebar.style.transform = 'translateX(0)';
            } else {
                sidebar.style.transform = 'translateX(-110%)';
            }
        });
        // Optional: close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
                sidebar.classList.remove('sidebar-open');
                sidebar.style.transform = 'translateX(-110%)';
            }
        });
    }
    // Initialize sidebar state
    sidebar && (sidebar.style.transform = 'translateX(0)');
});
</script>
</body>
</html>






