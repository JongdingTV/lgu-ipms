/**
 * Back Button Prevention Script
 * Prevents users from using browser back button to access protected pages after logout
 * 
 * Usage: Add this script to the HEAD of all protected pages
 * <script src="../security-no-back.js"></script>
 */

(function() {
    // Prevent back button navigation
    if (window.history && window.history.pushState) {
        // Push current state to history
        window.history.pushState(null, null, window.location.href);
        
        // Listen for popstate (back button pressed)
        window.addEventListener('popstate', function(event) {
            // Push again to prevent going back
            window.history.pushState(null, null, window.location.href);
            
            // Force page reload from server (which will check auth)
            window.location.reload();
        });
    }
    
    // Additional protection: disable keyboard shortcuts for back navigation
    document.addEventListener('keydown', function(e) {
        // Alt+Left Arrow (Firefox, Chrome on Windows)
        if ((e.altKey || e.metaKey) && e.key === 'ArrowLeft') {
            e.preventDefault();
            alert('Navigation back is disabled for security. Please use the menu to navigate.');
            return false;
        }
        
        // Backspace key (only if not in input field)
        if (e.key === 'Backspace' && 
            e.target.tagName !== 'INPUT' && 
            e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            alert('Navigation back is disabled for security. Please use the menu to navigate.');
            return false;
        }
    });
    
    // Prevent page caching in older browsers
    if (window.location && window.location.hash === '') {
        window.location.hash = '#no-back';
    }
    
    // Monitor for page visibility (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // User returned to tab - reload to ensure still authenticated
            // Commented out to avoid excessive reloads
            // location.reload();
        }
    });
})();
