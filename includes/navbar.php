<?php
/**
 * Navigation Navbar Component
 * 
 * Shared navigation bar used across admin pages
 * Displays user info, navigation links, and logout button
 * 
 * @package LGU-IPMS
 * @subpackage Components
 * @version 1.0.0
 */
?>

<header class="navbar" id="navbar">
    <div class="nav-logo">
        <img src="<?php echo image('ipms-icon.png', 'icons'); ?>" alt="City Hall Logo" class="logo-img">
        <span class="logo-text">IPMS</span>
    </div>
    
    <div class="nav-links" id="navLinks">
        <a href="/app/admin/dashboard.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : ''; ?>">
            <img src="<?php echo image('dashboard.png', 'icons'); ?>" alt="Dashboard" class="nav-icon">
            <span>Dashboard</span>
        </a>
        
        <a href="/app/admin/projects/" class="nav-item nav-dropdown-toggle <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('projects.png', 'icons'); ?>" alt="Projects" class="nav-icon">
            <span>Projects</span>
            <span class="dropdown-arrow">▼</span>
        </a>
        <div class="nav-dropdown">
            <a href="/app/admin/projects/" class="nav-sub-item">View All Projects</a>
            <a href="/app/admin/projects/?action=new" class="nav-sub-item">Create New Project</a>
            <a href="/app/admin/projects/?status=approved" class="nav-sub-item">Approved Projects</a>
        </div>
        
        <a href="/app/admin/progress/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/progress') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('progress.png', 'icons'); ?>" alt="Progress" class="nav-icon">
            <span>Progress Monitoring</span>
        </a>
        
        <a href="/app/admin/budget/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/budget') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('budget.png', 'icons'); ?>" alt="Budget" class="nav-icon">
            <span>Budget & Resources</span>
        </a>
        
        <a href="/app/admin/tasks/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/tasks') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('tasks.png', 'icons'); ?>" alt="Tasks" class="nav-icon">
            <span>Tasks & Milestones</span>
        </a>
        
        <a href="/app/admin/contractors/" class="nav-item nav-dropdown-toggle <?php echo (strpos($_SERVER['REQUEST_URI'], '/contractors') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('contractors.png', 'icons'); ?>" alt="Contractors" class="nav-icon">
            <span>Contractors</span>
            <span class="dropdown-arrow">▼</span>
        </a>
        <div class="nav-dropdown">
            <a href="/app/admin/contractors/" class="nav-sub-item">View All Contractors</a>
            <a href="/app/admin/contractors/?action=new" class="nav-sub-item">Register Contractor</a>
        </div>
        
        <a href="/app/admin/priorities/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/priorities') !== false) ? 'active' : ''; ?>">
            <img src="<?php echo image('priorities.png', 'icons'); ?>" alt="Priorities" class="nav-icon">
            <span>Project Prioritization</span>
        </a>
    </div>
    
    <div class="nav-user">
        <img src="<?php echo image('user.png', 'icons'); ?>" alt="User" class="user-icon">
        <span class="nav-username">Welcome <?php echo escape(get_user_name()); ?></span>
        <a href="/app/auth/logout.php" class="nav-logout">
            <img src="<?php echo image('logout.png', 'icons'); ?>" alt="Logout" class="logout-icon">
            Logout
        </a>
    </div>
    
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
    </button>
</header>

<script>
// Mobile navigation toggle
document.getElementById('navToggle').addEventListener('click', function() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
});

// Dropdown menus
document.querySelectorAll('.nav-dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        // If on desktop, don't prevent default
        if (window.innerWidth > 768) {
            return;
        }
        
        e.preventDefault();
        const dropdown = this.nextElementSibling;
        if (dropdown && dropdown.classList.contains('nav-dropdown')) {
            dropdown.classList.toggle('active');
        }
    });
});
</script>
