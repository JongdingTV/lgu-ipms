/**
 * Smart Footer Script
 * Hide footer at top of page, show when scrolling down with smooth animation
 * Applied to all admin pages
 */

(function() {
    'use strict';

    // Wait a bit for DOM to be ready
    setTimeout(function() {
        const footer = document.querySelector('footer') || document.querySelector('.footer');
        if (!footer) {
            console.log('Footer element not found');
            return;
        }

        // Ensure footer is hidden initially
        footer.style.setProperty('display', 'none', 'important');
        footer.style.setProperty('opacity', '0', 'important');
        footer.style.setProperty('visibility', 'hidden', 'important');
        footer.style.setProperty('transform', 'translateY(100%)', 'important');

        let isFooterVisible = false;
        let scrollTimeout = null;

        function showFooter() {
            if (isFooterVisible) return;
            
            footer.style.setProperty('transition', 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)', 'important');
            footer.style.setProperty('display', 'flex', 'important');
            
            setTimeout(() => {
                footer.style.setProperty('opacity', '1', 'important');
                footer.style.setProperty('visibility', 'visible', 'important');
                footer.style.setProperty('transform', 'translateY(0)', 'important');
            }, 10);
            
            isFooterVisible = true;
            console.log('Footer shown');
        }

        function hideFooter() {
            if (!isFooterVisible) return;
            
            footer.style.setProperty('transition', 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)', 'important');
            footer.style.setProperty('opacity', '0', 'important');
            footer.style.setProperty('visibility', 'hidden', 'important');
            footer.style.setProperty('transform', 'translateY(100%)', 'important');
            
            setTimeout(() => {
                footer.style.setProperty('display', 'none', 'important');
            }, 400);
            
            isFooterVisible = false;
            console.log('Footer hidden');
        }

        function checkScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > 50 && !isFooterVisible) {
                showFooter();
            } else if (scrollTop <= 50 && isFooterVisible) {
                hideFooter();
            }
        }

        // Check on page load
        setTimeout(checkScroll, 100);

        // Listen for scroll events
        window.addEventListener('scroll', function() {
            if (scrollTimeout) return;
            
            scrollTimeout = setTimeout(() => {
                checkScroll();
                scrollTimeout = null;
            }, 16);
        }, { passive: true });

    }, 500); // Wait 500ms for DOM to fully load

})();


