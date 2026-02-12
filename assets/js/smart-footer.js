/**
 * Smart Footer Script
 * Hide footer at top of page, show when scrolling down
 * Applied to all admin pages
 */

(function() {
    'use strict';

    const footer = document.querySelector('footer, .footer');
    if (!footer) return;

    // State tracking
    let isFooterVisible = true;
    let lastScrollTop = 0;

    function showFooter() {
        if (!isFooterVisible) {
            footer.style.display = 'flex';
            footer.style.opacity = '1';
            footer.style.visibility = 'visible';
            footer.style.pointerEvents = 'auto';
            isFooterVisible = true;
        }
    }

    function hideFooter() {
        if (isFooterVisible) {
            footer.style.display = 'none';
            footer.style.opacity = '0';
            footer.style.visibility = 'hidden';
            footer.style.pointerEvents = 'none';
            isFooterVisible = false;
        }
    }

    // Check initial scroll position on page load
    function checkInitialScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        console.log('Initial scroll top:', scrollTop);
        
        if (scrollTop > 50) {
            showFooter();
        } else {
            hideFooter();
        }
    }

    // Handle scroll events
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Show footer when scrolling down past 50px
        if (scrollTop > 50 && !isFooterVisible) {
            showFooter();
        } 
        // Hide footer when scrolling back to top
        else if (scrollTop <= 50 && isFooterVisible) {
            hideFooter();
        }
    }, { passive: true });

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkInitialScroll);
    } else {
        checkInitialScroll();
    }

    // Also check after full page load
    window.addEventListener('load', checkInitialScroll);
})();


