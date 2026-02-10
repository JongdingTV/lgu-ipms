<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();

// Get user name from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - User View</title>
    <link rel="icon" type="image/png" href="../logocityhall.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="user-dashboard.css">
</head>
<body>
    <?php include 'user-sidebar.php'; ?>

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



    <script src="../shared-data.js"></script>
    <script src="user-progress-monitoring.js"></script>
    <script>
    (function() {
        // Remove duplicate logout modal if present
        // ...existing code...
    })();
    </script>
</body>
</html>
