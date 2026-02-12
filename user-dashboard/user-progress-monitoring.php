<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';

// Protect page
set_no_cache_headers();
check_auth();
check_suspicious_activity();

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
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Progress Monitoring - User View</title>
    <link rel="icon" type="image/png" href="/logocityhall.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/user-dashboard/user-dashboard.css">
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <!-- Mobile burger button (top left, only visible on mobile) -->
    <button id="sidebarBurgerBtn" class="sidebar-burger-btn mobile-only" aria-label="Open sidebar" type="button" style="position:fixed;top:18px;left:18px;z-index:1002;display:none;">
        <span class="burger-bar"></span>
        <span class="burger-bar"></span>
        <span class="burger-bar"></span>
    </button>
    <style>
    /* Show burger only on mobile */
    .sidebar-burger-btn.mobile-only {
        display: none;
    }
    @media (max-width: 991px) {
        .sidebar-burger-btn.mobile-only {
            display: block !important;
        }
    }
    </style>

    <!-- Burger button (always visible on mobile, top left) -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <aside class="nav sidebar-animated" id="navbar">
        <div class="nav-logo admin-sidebar-logo">
            <img src="/logocityhall.png" alt="City Hall Logo" class="logo-img" />
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-user">
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
            <div class="user-profile">
                <?php if ($profile_img): ?>
                    <img src="<?php echo $profile_img; ?>" alt="User Icon" class="user-icon" />
                <?php else: ?>
                    <div class="user-icon user-initials" style="background:<?php echo $bgcolor; ?>;">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                <div class="user-name"> <?php echo htmlspecialchars($user_name); ?> </div>
                <div class="user-email"> <?php echo htmlspecialchars($user_email); ?> </div>
            </div>
        </div>
        <hr class="sidebar-divider" />
        <nav class="nav-links">
            <a href="user-dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php" class="active"><img src="../assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </nav>
        <div class="sidebar-logout-container">
            <a href="/logout.php" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
    </aside>
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
            <a href="user-dashboard.php"><img src="/assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon"> Dashboard Overview</a>
            <a href="user-progress-monitoring.php" class="active"><img src="/assets/images/admin/monitoring.png" alt="Progress Monitoring" class="nav-icon"> Progress Monitoring</a>
            <a href="user-feedback.php"><img src="/user-dashboard/feedback.png" alt="Feedback Icon" class="nav-icon"> Feedback</a>
            <a href="user-settings.php"><img src="/user-dashboard/settings.png" alt="Settings Icon" class="nav-icon"> Settings</a>
        </nav>
        <div class="sidebar-logout-container">
            <a href="/logout.php" class="nav-logout logout-btn" id="logoutLink">Logout</a>
        </div>
    </aside>

    <script>
    // Sidebar burger and overlay logic (admin-style, fully hides sidebar)
    (function() {
        const sidebar = document.getElementById('navbar');
        const burger = document.getElementById('sidebarBurgerBtn');
        const overlay = document.getElementById('sidebarOverlay');
        function openSidebar() {
            sidebar.classList.add('sidebar-open');
            overlay.classList.add('sidebar-overlay-active');
            document.body.classList.add('sidebar-opened');
        }
        function closeSidebar() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('sidebar-overlay-active');
            document.body.classList.remove('sidebar-opened');
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
        // Hide sidebar on resize if desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991) {
                closeSidebar();
            }
        });
    })();
    // Logout confirmation (if needed)
    document.addEventListener('DOMContentLoaded', function() {
        window.setupLogoutConfirmation && window.setupLogoutConfirmation();
    });
    </script>


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



    <script src="/assets/js/shared/shared-data.js"></script>
    <script src="/assets/js/shared/shared-toggle.js"></script>
    <script src="user-progress-monitoring.js"></script>
        <script>
        // Sidebar burger and overlay logic (mobile burger only)
        (function() {
            const sidebar = document.getElementById('navbar');
            const burger = document.getElementById('sidebarBurgerBtn');
            const overlay = document.getElementById('sidebarOverlay');
            // Show/hide burger only on mobile
            function updateBurgerVisibility() {
                if (window.innerWidth <= 991) {
                    burger.style.display = 'block';
                } else {
                    burger.style.display = 'none';
                    closeSidebar();
                }
            }
            function openSidebar() {
                sidebar.classList.add('sidebar-open');
                overlay.classList.add('sidebar-overlay-active');
                document.body.classList.add('sidebar-opened');
            }
            function closeSidebar() {
                sidebar.classList.remove('sidebar-open');
                overlay.classList.remove('sidebar-overlay-active');
                document.body.classList.remove('sidebar-opened');
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
            // Also close sidebar if clicking outside sidebar and burger (for extra safety)
            document.addEventListener('click', function(e) {
                if (
                    sidebar.classList.contains('sidebar-open') &&
                    !sidebar.contains(e.target) &&
                    !burger.contains(e.target)
                ) {
                    closeSidebar();
                }
            });
        })();
        // Logout confirmation (if needed)
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    <script>
    (function() {
        // Remove duplicate logout modal if present
        // ...existing code...
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
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
                    sidebar.classList.remove('sidebar-open');
                    sidebar.style.transform = 'translateX(-110%)';
                }
            });
        }
        sidebar && (sidebar.style.transform = 'translateX(0)');
    });
    </script>
</body>
</html>






