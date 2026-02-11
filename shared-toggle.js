/**
 * Shared Toggle Sidebar Functionality
 * Used across all pages for consistent sidebar toggle behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const navbarMenuIcon = document.getElementById('navbarMenuIcon');
    const showSidebarBtn = document.getElementById('showSidebarBtn');
    const navbar = document.getElementById('navbar');

    function toggleSidebarVisibility(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Toggle the sidebar-hidden class on body
        const isSidebarHidden = document.body.classList.toggle('sidebar-hidden');
        
        // Also toggle .show class on floating button and navbar icon as backup
        if (showSidebarBtn) {
            showSidebarBtn.classList.toggle('show', isSidebarHidden);
        }
        if (navbarMenuIcon) {
            navbarMenuIcon.classList.toggle('show', isSidebarHidden);
        }
    }

    // Attach event listeners for all toggle buttons
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', toggleSidebarVisibility);
    }

    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    if (navbarMenuIcon) {
        navbarMenuIcon.addEventListener('click', toggleSidebarVisibility);
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

