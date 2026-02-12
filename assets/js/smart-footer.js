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
            isFooterVisible = false;
        } else {
            showFooter();
            isFooterVisible = true;
        }
    }

    function showFooter() {
        if (!isFooterVisible) {
            footer.style.display = 'flex';
            footer.style.opacity = '1';
            footer.style.visibility = 'visible';
            footer.style.transition = 'all 0.3s ease-in-out';
            isFooterVisible = true;
        }
    }

    function hideFooter() {
        if (isFooterVisible) {
            footer.style.display = 'none';
            footer.style.opacity = '0';
            footer.style.visibility = 'hidden';
            footer.style.transition = 'all 0.3s ease-in-out';
            isFooterVisible = false;
        }
    }

    // Handle scroll events
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Show footer when scrolling down from top
        if (scrollTop > 50) {
            if (!isFooterVisible) {
                showFooter();
            }
        } 
        // Hide footer when back at top
        else if (scrollTop === 0 || scrollTop < 50) {
            if (isFooterVisible) {
                hideFooter();
            }
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    }, false);

    // Check on page load
    window.addEventListener('load', checkInitialScroll);
    
    // Also check immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkInitialScroll);
    } else {
        checkInitialScroll();
    }
})();
