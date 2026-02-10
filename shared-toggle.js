/**
 * Shared Toggle Sidebar Functionality
 * Used across all pages for consistent sidebar toggle behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const showSidebarBtn = document.getElementById('showSidebarBtn');
    const navbar = document.getElementById('navbar');

    function toggleSidebarVisibility(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        const isHidden = document.body.classList.toggle('sidebar-hidden');
        
        if (showSidebarBtn) {
            if (isHidden) {
                showSidebarBtn.classList.add('show');
            } else {
                showSidebarBtn.classList.remove('show');
            }
        }
    }

    // Attach event listeners
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', toggleSidebarVisibility);
    }

    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    // Close dropdowns when clicking elsewhere
    document.addEventListener('click', function(e) {
        const navItemGroups = document.querySelectorAll('.nav-item-group');
        navItemGroups.forEach(group => {
            if (!group.contains(e.target)) {
                group.classList.remove('open');
            }
        });
    });
});

