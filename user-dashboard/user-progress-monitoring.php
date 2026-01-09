<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - User View</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            <a href="user-dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard</a>
            <a href="user-progress-monitoring.php" class="active"><img src="../progress-monitoring/monitoring.png" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="settings.png" class="nav-icon"> Settings</a>
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

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Progress Monitoring</h1>
            <p>View project progress in your area</p>
        </div>

        <div class="recent-projects">
            <div class="pm-controls">
                <div class="pm-left">
                    <input id="pmSearch" type="search" placeholder="Search by project code, name or location">
                </div>
                <div class="pm-right">
                    <select id="pmStatusFilter" title="Filter by status">
                        <option value="">All Status</option>
                        <option>Draft</option>
                        <option>For Approval</option>
                        <option>Approved</option>
                        <option>On-hold</option>
                        <option>Cancelled</option>
                    </select>

                    <select id="pmSectorFilter" title="Filter by sector">
                        <option value="">All Sectors</option>
                        <option>Road</option>
                        <option>Drainage</option>
                        <option>Building</option>
                        <option>Water</option>
                        <option>Sanitation</option>
                        <option>Other</option>
                    </select>

                    <select id="pmSort" title="Sort">
                        <option value="createdAt_desc">Newest</option>
                        <option value="createdAt_asc">Oldest</option>
                        <option value="progress_desc">Progress (high → low)</option>
                        <option value="progress_asc">Progress (low → high)</option>
                    </select>

                    <button id="exportCsv" type="button">Export CSV</button>
                </div>
            </div>

            <h3>Tracked Projects</h3>
            <div id="projectsList" class="projects-list">Loading projects...</div>

            <div id="pmEmpty" class="pm-empty" style="display:none;">No projects match your filters.</div>
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
    <script src="user-progress-monitoring.js"></script>
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
