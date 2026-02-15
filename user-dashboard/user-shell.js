document.addEventListener('DOMContentLoaded', function () {
    var path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
    var isUserArea = path.indexOf('/user-dashboard/') >= 0;
    var isAuthPage = /\/user-dashboard\/(user-login|create|user-forgot-password)\.php$/.test(path);

    if (!isUserArea || isAuthPage) {
        return;
    }

    var NAV_BREAKPOINT = '(max-width: 992px)';
    var SIDEBAR_PREF_KEY = 'user_sidebar_hidden';
    var mq = window.matchMedia(NAV_BREAKPOINT);
    var body = document.body;
    var nav = document.getElementById('navbar') || document.querySelector('.nav');
    var savedSidebarPref = window.localStorage.getItem(SIDEBAR_PREF_KEY);
    if (savedSidebarPref === null) {
        window.localStorage.setItem(SIDEBAR_PREF_KEY, '1');
        savedSidebarPref = '1';
    }
    var desktopSidebarHidden = savedSidebarPref === '1';
    var mobileCloseBtn = null;

    function q(sel, root) { return (root || document).querySelector(sel); }

    function closeMobileNav() {
        body.classList.remove('mobile-nav-open');
        body.classList.add('sidebar-hidden');
    }

    var mobileCloseBtn = null;
    function applyResponsiveSidebarMode() {
        var isMobile = mq.matches;
        body.classList.toggle('mobile-sidebar-mode', isMobile);
        if (mobileCloseBtn) {
            mobileCloseBtn.hidden = !isMobile;
        }

        if (isMobile) {
            body.classList.add('sidebar-hidden');
            body.classList.remove('mobile-nav-open');
        } else {
            body.classList.toggle('sidebar-hidden', desktopSidebarHidden);
            body.classList.remove('mobile-nav-open');
        }
    }

    function toggleSidebar() {
        if (mq.matches) {
            body.classList.toggle('mobile-nav-open');
            body.classList.remove('sidebar-hidden');
            return;
        }

        desktopSidebarHidden = body.classList.toggle('sidebar-hidden');
        window.localStorage.setItem(SIDEBAR_PREF_KEY, desktopSidebarHidden ? '1' : '0');
    }

    function bindSidebarToggles() {
        var toggleIds = ['toggleSidebar', 'toggleSidebarShow', 'navbarMenuIcon'];
        toggleIds.forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        });

        var topToggle = document.querySelector('.top-sidebar-toggle');
        if (!topToggle) {
            topToggle = document.createElement('button');
            topToggle.type = 'button';
            topToggle.className = 'top-sidebar-toggle';
            topToggle.setAttribute('aria-label', 'Toggle sidebar');
            topToggle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>';
            document.body.appendChild(topToggle);
        }


        var mobileClose = document.getElementById('mobileSidebarClose');
        if (!mobileClose && nav) {
            mobileClose = document.createElement('button');
            mobileClose.type = 'button';
            mobileClose.id = 'mobileSidebarClose';
            mobileClose.className = 'mobile-sidebar-close';
            mobileClose.setAttribute('aria-label', 'Close sidebar');
            mobileClose.innerHTML = '&times;';
            nav.appendChild(mobileClose);
        }

        if (mobileClose) {
            mobileCloseBtn = mobileClose;
            mobileClose.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeMobileNav();
            });
        }

        topToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });

        document.addEventListener('click', function (e) {
            if (!body.classList.contains('mobile-sidebar-mode') || !body.classList.contains('mobile-nav-open')) {
                return;
            }
            if (nav && nav.contains(e.target)) {
                return;
            }
            if (e.target.closest('.top-sidebar-toggle')) {
                return;
            }
            closeMobileNav();
        }, true);

        document.querySelectorAll('#navbar .nav-links a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (mq.matches) {
                    closeMobileNav();
                }
            });
        });
    }

    function initLogoutConfirmation() {
        var modal = document.getElementById('userLogoutConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'userLogoutConfirmModal';
            modal.className = 'admin-logout-modal';
            modal.setAttribute('hidden', 'hidden');
            modal.innerHTML = '' +
                '<div class="admin-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="userLogoutTitle">' +
                '  <div class="admin-logout-head">' +
                '    <span class="admin-logout-icon" aria-hidden="true">&#8618;</span>' +
                '    <div>' +
                '      <h3 id="userLogoutTitle">Logout Confirmation</h3>' +
                '      <p>You are about to end your current user session.</p>' +
                '    </div>' +
                '  </div>' +
                '  <p class="admin-logout-note">Any unsaved changes on this page may be lost.</p>' +
                '  <div class="admin-logout-actions">' +
                '    <button type="button" class="btn-cancel">Cancel</button>' +
                '    <button type="button" class="btn-logout">Logout</button>' +
                '  </div>' +
                '</div>';
            document.body.appendChild(modal);
        }

        var nextUrl = '/logout.php';
        var cancelBtn = q('.btn-cancel', modal);
        var logoutBtn = q('.btn-logout', modal);

        function hideModal() {
            modal.classList.remove('show');
            modal.setAttribute('hidden', 'hidden');
        }

        function showModal(url) {
            nextUrl = url || '/logout.php';
            modal.classList.add('show');
            modal.removeAttribute('hidden');
            if (logoutBtn) logoutBtn.focus();
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', hideModal);
        }
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
                window.location.href = nextUrl;
            });
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                hideModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideModal();
            }
        });

        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href*="logout.php"]');
            if (!link) return;
            var inUserShell = link.closest('.nav') || link.closest('.main-content');
            if (!inUserShell) return;

            e.preventDefault();
            e.stopPropagation();
            showModal(link.getAttribute('href') || '/logout.php');
        }, true);
    }

    function initTopUtilities() {
        if (document.querySelector('.admin-top-utilities')) {
            return;
        }

        var util = document.createElement('div');
        util.className = 'admin-top-utilities';
        util.innerHTML = '' +
            '<div class="admin-time-chip" aria-live="polite">' +
            '  <span class="admin-time" id="userLiveTime">--:--:--</span>' +
            '  <span class="admin-date" id="userLiveDate">----</span>' +
            '</div>' +
            '<div class="admin-utility-group">' +
            '  <button type="button" class="admin-utility-btn" id="userCalendarBtn" aria-expanded="false" aria-controls="userCalendarPanel" title="Calendar">' +
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>' +
            '    <span class="admin-utility-label" id="userCalendarLabel">Calendar</span>' +
            '  </button>' +
            '  <button type="button" class="admin-utility-btn" id="userThemeBtn" title="Toggle dark mode">' +
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>' +
            '    <span class="admin-utility-label" id="userThemeLabel">Dark</span>' +
            '  </button>' +
            '</div>' +
            '<div class="admin-calendar-panel" id="userCalendarPanel" hidden>' +
            '  <div class="admin-calendar-head">' +
            '    <button type="button" id="userCalPrev" aria-label="Previous month">&#8249;</button>' +
            '    <strong id="userCalTitle">Month Year</strong>' +
            '    <button type="button" id="userCalNext" aria-label="Next month">&#8250;</button>' +
            '  </div>' +
            '  <div class="admin-calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>' +
            '  <div class="admin-calendar-grid" id="userCalendarGrid" aria-live="polite"></div>' +
            '  <div class="admin-calendar-foot"><button type="button" id="userCalToday">Today</button></div>' +
            '</div>';

        document.body.appendChild(util);

        // Mirror admin top bar: include a menu button on the left side.
        var topLeft = document.createElement('div');
        topLeft.className = 'admin-top-left';
        topLeft.innerHTML = '' +
            '<button type="button" class="admin-top-menu" id="userTopMenuBtn" aria-label="Toggle sidebar">' +
            '  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '    <line x1="3" y1="12" x2="21" y2="12"></line>' +
            '    <line x1="3" y1="6" x2="21" y2="6"></line>' +
            '    <line x1="3" y1="18" x2="21" y2="18"></line>' +
            '  </svg>' +
            '</button>';
        util.insertBefore(topLeft, util.firstChild);

        var timeEl = document.getElementById('userLiveTime');
        var dateEl = document.getElementById('userLiveDate');
        var calendarBtn = document.getElementById('userCalendarBtn');
        var calendarLabel = document.getElementById('userCalendarLabel');
        var calendarPanel = document.getElementById('userCalendarPanel');
        var calTitle = document.getElementById('userCalTitle');
        var calGrid = document.getElementById('userCalendarGrid');
        var calPrev = document.getElementById('userCalPrev');
        var calNext = document.getElementById('userCalNext');
        var calToday = document.getElementById('userCalToday');
        var themeBtn = document.getElementById('userThemeBtn');
        var themeLabel = document.getElementById('userThemeLabel');
        var topMenuBtn = document.getElementById('userTopMenuBtn');

        var isDarkTheme = body.classList.contains('theme-dark');
        if (themeLabel) {
            themeLabel.textContent = isDarkTheme ? 'Light' : 'Dark';
        }

        var today = new Date();
        var calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);

        function renderCalendar() {
            if (!calTitle || !calGrid) return;

            var year = calendarCursor.getFullYear();
            var month = calendarCursor.getMonth();
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var prevMonthDays = new Date(year, month, 0).getDate();
            var isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;

            calTitle.textContent = calendarCursor.toLocaleDateString([], { month: 'long', year: 'numeric' });

            var cells = [];
            for (var i = firstDay - 1; i >= 0; i -= 1) {
                cells.push('<span class="is-outside">' + (prevMonthDays - i) + '</span>');
            }
            for (var day = 1; day <= daysInMonth; day += 1) {
                var cls = isCurrentMonth && day === today.getDate() ? ' class="is-today"' : '';
                cells.push('<span' + cls + '>' + day + '</span>');
            }
            var nextMonthDay = 1;
            while (cells.length % 7 !== 0) {
                cells.push('<span class="is-outside">' + nextMonthDay + '</span>');
                nextMonthDay += 1;
            }

            calGrid.innerHTML = cells.join('');
        }

        function clockTick() {
            var now = new Date();
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            }
            if (calendarLabel) {
                calendarLabel.textContent = now.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        }

        clockTick();
        setInterval(clockTick, 1000);
        renderCalendar();

        if (calendarBtn && calendarPanel) {
            calendarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var willOpen = calendarPanel.hasAttribute('hidden');
                calendarPanel.toggleAttribute('hidden', !willOpen);
                calendarBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        }

        if (calPrev) {
            calPrev.addEventListener('click', function () {
                calendarCursor = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth() - 1, 1);
                renderCalendar();
            });
        }

        if (calNext) {
            calNext.addEventListener('click', function () {
                calendarCursor = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth() + 1, 1);
                renderCalendar();
            });
        }

        if (calToday) {
            calToday.addEventListener('click', function () {
                calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);
                renderCalendar();
            });
        }

        if (topMenuBtn) {
            topMenuBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var isDark = !body.classList.contains('theme-dark');
                body.classList.toggle('theme-dark', isDark);
                if (themeLabel) {
                    themeLabel.textContent = isDark ? 'Light' : 'Dark';
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!util.contains(e.target) && calendarPanel && !calendarPanel.hasAttribute('hidden')) {
                calendarPanel.setAttribute('hidden', 'hidden');
                if (calendarBtn) calendarBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    applyResponsiveSidebarMode();
    window.addEventListener('resize', applyResponsiveSidebarMode);
    bindSidebarToggles();
    initLogoutConfirmation();
    initTopUtilities();
});







