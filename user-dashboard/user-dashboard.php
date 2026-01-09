<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Database connection
$conn = new mysqli('localhost:3307', 'root', '', 'lgu_ipms');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

// Get project statistics
$totalProjects = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
$inProgressProjects = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status IN ('Approved', 'For Approval')")->fetch_assoc()['count'];
$completedProjects = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'")->fetch_assoc()['count'];
$totalBudget = $conn->query("SELECT COALESCE(SUM(budget), 0) as total FROM projects")->fetch_assoc()['total'];

// Get recent projects
$recentProjects = $conn->query("SELECT id, name, location, status, budget FROM projects ORDER BY created_at DESC LIMIT 5");
// Get user feedback from database
$feedbacks = $conn->query("SELECT id, subject, category, status, date_submitted FROM feedback ORDER BY date_submitted DESC LIMIT 20");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - LGU IPMS</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="user-dashboard.php" class="active"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="../dashboard/person.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="../dashboard/person.png" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="#" class="nav-logout" id="logoutLink">Logout</a>
        </div>
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
    </header>

    <!-- Toggle button to show sidebar -->
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>User Dashboard</h1>
            <p>Infrastructure Project Management System</p>
        </div>

        <!-- Key Metrics Section -->
        <div class="metrics-container modern-metrics">
            <div class="metric-card accent-blue">
                <div class="metric-icon-bg">
                    <img src="../dashboard/chart.png" alt="Total Projects" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>Projects in Your Area</h3>
                    <p class="metric-value"><?php echo $totalProjects; ?></p>
                    <span class="metric-status">Active & Completed</span>
                </div>
            </div>
            <div class="metric-card accent-yellow">
                <div class="metric-icon-bg">
                    <img src="../dashboard/sandclock.png" alt="In Progress" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value"><?php echo $inProgressProjects; ?></p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card accent-green">
                <div class="metric-icon-bg">
                    <img src="../dashboard/check.png" alt="Completed" class="metric-icon">
                </div>
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value"><?php echo $completedProjects; ?></p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card accent-purple">
                <div class="metric-icon-bg">
                    <img src="../dashboard/budget.png" alt="Total Budget" class="metric-icon">
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
        <div class="feedback-review" style="margin:40px auto 0;max-width:900px;">
            <h3 style="font-size:1.2rem;font-weight:600;color:#2563eb;margin-bottom:18px;">Your Feedback Review</h3>
            <table class="projects-table" style="width:100%;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.07);overflow:hidden;">
                <thead style="background:#f1f5f9;">
                    <tr>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Date</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Subject</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Category</th>
                        <th style="padding:12px 8px;font-weight:600;color:#1e3a8a;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($feedbacks && $feedbacks->num_rows > 0) {
                    while ($fb = $feedbacks->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . date('M d, Y', strtotime($fb['date_submitted'])) . '</td>';
                        echo '<td>' . htmlspecialchars($fb['subject']) . '</td>';
                        echo '<td>' . htmlspecialchars($fb['category']) . '</td>';
                        echo '<td><span class="status-badge">' . htmlspecialchars($fb['status']) . '</span></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999;">No feedback submitted yet</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;padding:24px 28px;max-width:360px;width:90%;box-shadow:0 10px 30px rgba(15,23,42,0.35);text-align:center;">
            <h2 style="margin-bottom:10px;font-size:1.1rem;color:#111827;">Confirm Logout</h2>
            <p style="font-size:0.9rem;color:#4b5563;margin-bottom:18px;">Are you sure you want to log out of your citizen account?</p>
            <div style="display:flex;justify-content:center;gap:12px;">
                <button id="cancelLogout" style="padding:8px 16px;border-radius:999px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:0.9rem;cursor:pointer;">Cancel</button>
                <button id="confirmLogout" style="padding:8px 16px;border-radius:999px;border:none;background:#ef4444;color:#fff;font-size:0.9rem;cursor:pointer;">Logout</button>
            </div>
        </div>
    </div>

    <script src="../shared-data.js"></script>
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
    })();
    </script>
</body>
</html>
