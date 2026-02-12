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
            return;
        }

        function applyFooterLayout() {
            const isAdminPage = !document.body.classList.contains('admin-login-page') &&
                !document.body.classList.contains('login-page') &&
                !document.body.classList.contains('signup-page');
            const isMobile = window.innerWidth <= 992;
            const isSidebarHidden = document.body.classList.contains('sidebar-hidden');

            footer.style.setProperty('position', 'fixed', 'important');
            footer.style.setProperty('bottom', '0', 'important');
            footer.style.setProperty('z-index', '1200', 'important');
            footer.style.setProperty('height', isMobile ? '36px' : '40px', 'important');
            footer.style.setProperty('min-height', isMobile ? '36px' : '40px', 'important');
            footer.style.setProperty('padding', isMobile ? '5px 10px' : '6px 12px', 'important');
            footer.style.setProperty('font-size', isMobile ? '10px' : '11px', 'important');
            footer.style.setProperty('line-height', '1.2', 'important');
            footer.style.setProperty('display', 'flex', 'important');
            footer.style.setProperty('align-items', 'center', 'important');
            footer.style.setProperty('justify-content', 'center', 'important');
            footer.style.setProperty('text-align', 'center', 'important');
            footer.style.setProperty('white-space', 'nowrap', 'important');

            if (!isAdminPage || isMobile) {
                footer.style.setProperty('left', '0', 'important');
                footer.style.setProperty('right', '0', 'important');
                footer.style.setProperty('width', '100%', 'important');
            } else if (isSidebarHidden) {
                footer.style.setProperty('left', '120px', 'important');
                footer.style.removeProperty('right');
                footer.style.setProperty('width', 'calc(100% - 120px)', 'important');
            } else {
                footer.style.setProperty('left', '270px', 'important');
                footer.style.removeProperty('right');
                footer.style.setProperty('width', 'calc(100% - 270px)', 'important');
            }
        }

        // Ensure footer is hidden initially
        applyFooterLayout();
        footer.style.setProperty('display', 'none', 'important');
        footer.style.setProperty('opacity', '0', 'important');
        footer.style.setProperty('visibility', 'hidden', 'important');
        footer.style.setProperty('transform', 'translateY(100%)', 'important');

        let isFooterVisible = false;
        let scrollTimeout = null;

        function showFooter() {
            if (isFooterVisible) return;
            
            applyFooterLayout();
            footer.style.setProperty('transition', 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)', 'important');
            footer.style.setProperty('display', 'flex', 'important');
            
            setTimeout(() => {
                applyFooterLayout();
                footer.style.setProperty('opacity', '1', 'important');
                footer.style.setProperty('visibility', 'visible', 'important');
                footer.style.setProperty('transform', 'translateY(0)', 'important');
            }, 10);
            
            isFooterVisible = true;
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

        window.addEventListener('resize', () => {
            applyFooterLayout();
        });

        const bodyObserver = new MutationObserver(() => {
            applyFooterLayout();
        });
        bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    }, 500); // Wait 500ms for DOM to fully load

})();


