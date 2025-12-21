<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - LGU IPMS</title>
    <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="user-dashboard.php" class="active"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard</a>
            <a href="user-progress-monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="../dashboard/person.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="../dashboard/person.png" class="nav-icon"> Settings</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome, User</span>
            <a href="../login.php" class="nav-logout">Logout</a>
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
        <div class="metrics-container">
            <div class="metric-card">
                <img src="../dashboard/chart.png" alt="Total Projects" class="metric-icon">
                <div class="metric-content">
                    <h3>Projects in Your Area</h3>
                    <p class="metric-value">0</p>
                    <span class="metric-status">Active & Completed</span>
                </div>
            </div>
            <div class="metric-card">
                <img src="../dashboard/sandclock.png" alt="In Progress" class="metric-icon">
                <div class="metric-content">
                    <h3>In Progress</h3>
                    <p class="metric-value">0</p>
                    <span class="metric-status">Currently executing</span>
                </div>
            </div>
            <div class="metric-card">
                <img src="../dashboard/check.png" alt="Completed" class="metric-icon">
                <div class="metric-content">
                    <h3>Completed</h3>
                    <p class="metric-value">0</p>
                    <span class="metric-status">On schedule</span>
                </div>
            </div>
            <div class="metric-card">
                <img src="../dashboard/budget.png" alt="Total Budget" class="metric-icon">
                <div class="metric-content">
                    <h3>Allocated Budget</h3>
                    <p class="metric-value">₱0</p>
                    <span class="metric-status">For your area</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-box">
                <h3>Project Status in Your Area</h3>
                <div class="chart-placeholder">
                    <div class="status-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #10b981;"></span>
                            <span>Completed: 0%</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #f59e0b;"></span>
                            <span>In Progress: 0%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-box">
                <h3>Budget Utilization</h3>
                <div class="chart-placeholder">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.9em; color: #666;">Budget utilization: 0% Used</p>
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
                    <tr>
                        <td>No projects registered</td>
                        <td>-</td>
                        <td><span class="status-badge pending">Pending</span></td>
                        <td>
                            <div class="progress-small">
                                <div class="progress-fill-small" style="width: 0%;"></div>
                            </div>
                        </td>
                        <td>₱0</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-item">
                <h4>Average Project Duration</h4>
                <p>0 months</p>
            </div>
            <div class="stat-item">
                <h4>On-Time Delivery Rate</h4>
                <p>0%</p>
            </div>
            <div class="stat-item">
                <h4>Budget Variance</h4>
                <p>0%</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js"></script>
    <script src="user-dashboard.js"></script>
</body>
</html>
