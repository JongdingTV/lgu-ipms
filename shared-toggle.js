/**
 * Shared Toggle Sidebar Functionality
 * Used across all pages for consistent sidebar toggle behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const showSidebarBtn = document.getElementById('showSidebarBtn');

    function toggleSidebarVisibility(e) {
        e.preventDefault();
        e.stopPropagation();
        
        document.body.classList.toggle('sidebar-hidden');
        
        if (showSidebarBtn) {
            showSidebarBtn.classList.toggle('show');
        }
    }

    // Attach event listeners
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', toggleSidebarVisibility);
    }

    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    // Close dropdown when clicking elsewhere
    document.addEventListener('click', function(e) {
        const navItemGroups = document.querySelectorAll('.nav-item-group');
        navItemGroups.forEach(group => {
            if (!group.contains(e.target)) {
                group.classList.remove('open');
            }
        });
    });
});
