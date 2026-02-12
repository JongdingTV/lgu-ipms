/**
 * Smart Footer Script
 * Hide footer at top of page, show when scrolling down
 * Applied to all admin pages
 */

(function() {
    'use strict';

    const footer = document.querySelector('footer, .footer');
    if (!footer) return;

    let lastScrollTop = 0;
    let isFooterVisible = false;

    // Initially hide footer on page load if at top
    function checkInitialScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop === 0) {
            hideFooter();
        } else {
            showFooter();
        }
    }

    function showFooter() {
        footer.style.display = 'flex';
        footer.style.opacity = '1';
        footer.style.visibility = 'visible';
        footer.style.pointerEvents = 'auto';
        isFooterVisible = true;
    }

    function hideFooter() {
        footer.style.display = 'none';
        footer.style.opacity = '0';
        footer.style.visibility = 'hidden';
        footer.style.pointerEvents = 'none';
        isFooterVisible = false;
    }

    // Handle scroll events with debouncing
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            // Show footer when scrolling down (more than 50px from top)
            if (scrollTop > 50) {
                if (!isFooterVisible) {
                    showFooter();
                }
            } 
            // Hide footer when back at top
            else if (scrollTop <= 50) {
                if (isFooterVisible) {
                    hideFooter();
                }
            }
            
            lastScrollTop = scrollTop;
        }, 50);
    }, false);

    // Check on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkInitialScroll);
    } else {
        checkInitialScroll();
    }
    
    // Also check after window load
    window.addEventListener('load', checkInitialScroll);
})();

