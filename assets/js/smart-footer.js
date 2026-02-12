/**
 * Smart Footer Script
 * Hide footer at top of page, show when scrolling down with smooth animation
 * Applied to all admin pages
 */

(function() {
    'use strict';

    const footer = document.querySelector('footer, .footer');
    if (!footer) return;

    // State tracking
    let isFooterVisible = false;
    let lastScrollTop = 0;
    let scrollTimeout = null;

    function showFooter() {
        if (!isFooterVisible) {
            footer.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important';
            footer.style.setProperty('display', 'flex', 'important');
            // Small delay for smooth animation sequence
            setTimeout(() => {
                footer.style.setProperty('opacity', '1', 'important');
                footer.style.setProperty('visibility', 'visible', 'important');
                footer.style.setProperty('pointer-events', 'auto', 'important');
                footer.style.setProperty('transform', 'translateY(0)', 'important');
            }, 10);
            isFooterVisible = true;
        }
    }

    function hideFooter() {
        if (isFooterVisible) {
            footer.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important';
            footer.style.setProperty('opacity', '0', 'important');
            footer.style.setProperty('visibility', 'hidden', 'important');
            footer.style.setProperty('pointer-events', 'none', 'important');
            footer.style.setProperty('transform', 'translateY(100%)', 'important');
            
            // Hide display after animation completes
            setTimeout(() => {
                if (footer.style.opacity === '0') {
                    footer.style.setProperty('display', 'none', 'important');
                }
            }, 400);
            isFooterVisible = false;
        }
    }

    // Check initial scroll position on page load
    function checkInitialScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        console.log('Initial scroll top:', scrollTop);
        
        if (scrollTop > 50) {
            // Show immediately without animation on page load
            footer.style.setProperty('display', 'flex', 'important');
            footer.style.setProperty('opacity', '1', 'important');
            footer.style.setProperty('visibility', 'visible', 'important');
            footer.style.setProperty('pointer-events', 'auto', 'important');
            footer.style.setProperty('transform', 'translateY(0)', 'important');
            isFooterVisible = true;
        } else {
            footer.style.setProperty('display', 'none', 'important');
            footer.style.setProperty('transform', 'translateY(100%)', 'important');
            isFooterVisible = false;
        }
    }

    // Handle scroll events with throttling for performance
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Throttle scroll events to ~60fps
        if (scrollTimeout) return;
        
        scrollTimeout = setTimeout(() => {
            // Show footer when scrolling down past 50px
            if (scrollTop > 50 && !isFooterVisible) {
                showFooter();
            } 
            // Hide footer when scrolling back to top
            else if (scrollTop <= 50 && isFooterVisible) {
                hideFooter();
            }
            scrollTimeout = null;
        }, 16);
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


