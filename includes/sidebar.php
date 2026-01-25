<?php
/**
 * Admin Sidebar Component
 * 
 * Left sidebar with navigation for admin pages
 * 
 * @package LGU-IPMS
 * @subpackage Components
 * @version 1.0.0
 */
?>

<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <h3 class="sidebar-title">Main Menu</h3>
            <ul class="sidebar-menu">
                <li>
                    <a href="/app/admin/dashboard.php" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>">
                        <img src="<?php echo image('dashboard.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/projects/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('projects.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Projects</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/budget/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/budget') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('budget.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Budget</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/contractors/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/contractors') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('contractors.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Contractors</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/progress/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/progress') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('progress.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Progress</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/tasks/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/tasks') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('tasks.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Tasks</span>
                    </a>
                </li>
                <li>
                    <a href="/app/admin/priorities/" class="sidebar-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/priorities') !== false) ? 'active' : ''; ?>">
                        <img src="<?php echo image('priorities.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Priorities</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-section">
            <h3 class="sidebar-title">Settings</h3>
            <ul class="sidebar-menu">
                <li>
                    <a href="/app/admin/reports/" class="sidebar-link">
                        <img src="<?php echo image('reports.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="/app/user/settings.php" class="sidebar-link">
                        <img src="<?php echo image('settings.png', 'icons'); ?>" alt="" class="sidebar-icon">
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <img src="<?php echo image('menu.png', 'icons'); ?>" alt="Menu">
</button>

<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
});

// Close sidebar when clicking a link on mobile
if (window.innerWidth < 768) {
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
        });
    });
}
</script>
