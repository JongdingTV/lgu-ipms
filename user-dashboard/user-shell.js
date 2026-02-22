document.addEventListener('DOMContentLoaded', function () {
    var path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
    var isUserArea = path.indexOf('/user-dashboard/') >= 0;
    var isAuthPage = /\/user-dashboard\/(user-login|create|user-forgot-password)\.php$/.test(path);

    if (!isUserArea || isAuthPage) {
        return;
    }

    var NAV_BREAKPOINT = '(max-width: 992px)';
    var SIDEBAR_PREF_KEY = 'user_sidebar_hidden';
    var THEME_PREF_KEY = 'user_theme_dark';
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
            '  <button type="button" class="admin-utility-btn" id="userNotifBtn" aria-expanded="false" aria-controls="userNotifPanel" title="Notifications">' +
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path><path d="M9 17a3 3 0 0 0 6 0"></path></svg>' +
            '    <span class="admin-utility-label">Alerts</span>' +
            '    <span class="admin-utility-badge" id="userNotifCount">0</span>' +
            '  </button>' +
            '  <button type="button" class="admin-utility-btn" id="userCalendarBtn" aria-expanded="false" aria-controls="userCalendarPanel" title="Calendar">' +
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>' +
            '    <span class="admin-utility-label" id="userCalendarLabel">Calendar</span>' +
            '  </button>' +
            '  <button type="button" class="admin-utility-btn" id="userThemeBtn" title="Toggle dark mode">' +
            '    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>' +
            '    <span class="admin-utility-label" id="userThemeLabel">Dark</span>' +
            '  </button>' +
            '</div>' +
            '<div class="admin-notif-panel" id="userNotifPanel" hidden>' +
            '  <div class="admin-notif-head">' +
            '    <strong>Notifications</strong>' +
            '    <button type="button" id="userNotifMarkRead">Mark all read</button>' +
            '  </div>' +
            '  <ul class="admin-notif-list" id="userNotifList"></ul>' +
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
        var notifBtn = document.getElementById('userNotifBtn');
        var notifPanel = document.getElementById('userNotifPanel');
        var notifList = document.getElementById('userNotifList');
        var notifCount = document.getElementById('userNotifCount');
        var notifMarkRead = document.getElementById('userNotifMarkRead');
        var themeBtn = document.getElementById('userThemeBtn');
        var themeLabel = document.getElementById('userThemeLabel');
        var topMenuBtn = document.getElementById('userTopMenuBtn');
        var isDarkTheme = body.classList.contains('theme-dark');
        var serverClockOffsetMs = 0;
        if (themeLabel) {
            themeLabel.textContent = isDarkTheme ? 'Light' : 'Dark';
        }

        function nowWithServerOffset() {
            return new Date(Date.now() + serverClockOffsetMs);
        }

        var today = nowWithServerOffset();
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
            var now = nowWithServerOffset();
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

        var userNotifications = [];
        var latestUserNotificationId = 0;
        var serverSeenNotifId = 0;

        function getSeenNotifId() {
            return Number(serverSeenNotifId || 0) || 0;
        }

        function setSeenNotifId(id) {
            var nextSeen = Number(id || 0) || 0;
            if (nextSeen <= serverSeenNotifId) return;
            serverSeenNotifId = nextSeen;
            var url = window.getApiUrl ? window.getApiUrl('user-dashboard/user-notifications-api.php') : '/user-dashboard/user-notifications-api.php';
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_seen_id: nextSeen })
            }).catch(function () { /* ignore transient failure; next poll will resync */ });
        }

        function relativeTime(value, tsValue) {
            var ts = null;
            var unixSeconds = Number(tsValue || 0);
            if (unixSeconds > 0) {
                ts = new Date(unixSeconds * 1000);
            } else if (value) {
                var normalized = String(value).trim().replace(' ', 'T');
                if (!/[zZ]|[+\-]\d{2}:\d{2}$/.test(normalized)) normalized += 'Z';
                ts = new Date(normalized);
            }
            if (!ts) return '';
            if (Number.isNaN(ts.getTime())) return '';
            var secs = Math.floor((Date.now() - ts.getTime()) / 1000);
            if (secs < 0) return 'just now';
            if (secs < 60) return 'just now';
            var mins = Math.floor(secs / 60);
            if (mins < 60) return mins + 'm ago';
            var hrs = Math.floor(mins / 60);
            if (hrs < 24) return hrs + 'h ago';
            var days = Math.floor(hrs / 24);
            return days + 'd ago';
        }

        function escHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderUserNotifications() {
            if (!notifList || !notifCount) return;
            var seenId = getSeenNotifId();
            var unreadCount = userNotifications.filter(function (item) { return Number(item.id) > seenId; }).length;

            notifList.innerHTML = userNotifications.map(function (item) {
                var unread = Number(item.id) > seenId;
                var link = item.link || '';
                var category = item.category || 'Update';
                return '' +
                    '<li class="admin-notif-item is-' + escHtml(item.level || 'info') + (unread ? ' unread' : '') + '" data-notif-link="' + escHtml(link) + '">' +
                    '  <span class="dot"></span>' +
                    '  <span>' +
                    '    <strong><span style="display:inline-flex;align-items:center;gap:6px;"><span style="font-size:.68rem;padding:2px 8px;border-radius:999px;border:1px solid #c7d2fe;background:#eef2ff;color:#1e40af;">' + escHtml(category) + '</span>' + escHtml(item.title || 'Update') + '</span></strong>' +
                    '    <small>' + escHtml(item.message || '') + '</small>' +
                    '    <em>' + relativeTime(item.created_at, item.created_at_ts) + '</em>' +
                    '  </span>' +
                    '</li>';
            }).join('');

            notifList.querySelectorAll('.admin-notif-item[data-notif-link]').forEach(function (el) {
                el.addEventListener('click', function () {
                    var target = (el.getAttribute('data-notif-link') || '').trim();
                    if (!target) return;
                    window.location.href = target;
                });
            });

            notifCount.textContent = String(unreadCount);
            notifCount.style.display = unreadCount ? 'inline-flex' : 'none';
        }

        function fetchUserNotifications() {
            var url = window.getApiUrl ? window.getApiUrl('user-dashboard/user-notifications-api.php') : '/user-dashboard/user-notifications-api.php';
            fetch(url + '?_=' + Date.now(), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || data.success !== true) return;
                    var serverNowTs = Number(data.server_now_ts || 0);
                    if (serverNowTs > 0) {
                        serverClockOffsetMs = (serverNowTs * 1000) - Date.now();
                        clockTick();
                    }
                    userNotifications = Array.isArray(data.items) ? data.items : [];
                    latestUserNotificationId = Number(data.latest_id || 0) || 0;
                    serverSeenNotifId = Number(data.seen_id || 0) || serverSeenNotifId;
                    if (!userNotifications.length) {
                        userNotifications = [{ id: 0, level: 'info', title: 'No new updates', message: 'No project updates at the moment.', created_at: null }];
                    }
                    if (notifPanel && !notifPanel.hasAttribute('hidden') && latestUserNotificationId > 0) {
                        setSeenNotifId(latestUserNotificationId);
                    }
                    renderUserNotifications();
                })
                .catch(function () {
                    userNotifications = [{ id: 0, level: 'danger', title: 'Notifications unavailable', message: 'Unable to load updates right now.', created_at: null }];
                    renderUserNotifications();
                });
        }

        fetchUserNotifications();
        window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                fetchUserNotifications();
            }
        }, 10000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                fetchUserNotifications();
                clockTick();
            }
        });
        window.addEventListener('focus', function () {
            fetchUserNotifications();
            clockTick();
        });

        if (calendarBtn && calendarPanel) {
            calendarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var willOpen = calendarPanel.hasAttribute('hidden');
                calendarPanel.toggleAttribute('hidden', !willOpen);
                calendarBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen && notifPanel) {
                    notifPanel.setAttribute('hidden', 'hidden');
                    if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (notifBtn && notifPanel) {
            notifBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var willOpen = notifPanel.hasAttribute('hidden');
                notifPanel.toggleAttribute('hidden', !willOpen);
                notifBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen && calendarPanel) {
                    calendarPanel.setAttribute('hidden', 'hidden');
                    if (calendarBtn) calendarBtn.setAttribute('aria-expanded', 'false');
                }
                if (willOpen) {
                    var highestId = latestUserNotificationId;
                    if (highestId > 0) {
                        setSeenNotifId(highestId);
                        renderUserNotifications();
                    }
                }
            });
        }

        if (notifMarkRead) {
            notifMarkRead.addEventListener('click', function () {
                setSeenNotifId(latestUserNotificationId);
                renderUserNotifications();
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
                var now = nowWithServerOffset();
                calendarCursor = new Date(now.getFullYear(), now.getMonth(), 1);
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
                window.localStorage.setItem(THEME_PREF_KEY, isDark ? '1' : '0');
                if (themeLabel) {
                    themeLabel.textContent = isDark ? 'Light' : 'Dark';
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!util.contains(e.target)) {
                if (calendarPanel && !calendarPanel.hasAttribute('hidden')) {
                    calendarPanel.setAttribute('hidden', 'hidden');
                    if (calendarBtn) calendarBtn.setAttribute('aria-expanded', 'false');
                }
                if (notifPanel && !notifPanel.hasAttribute('hidden')) {
                    notifPanel.setAttribute('hidden', 'hidden');
                    if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    applyResponsiveSidebarMode();
    var savedThemePref = window.localStorage.getItem(THEME_PREF_KEY);
    body.classList.toggle('theme-dark', savedThemePref === '1');
    window.addEventListener('resize', applyResponsiveSidebarMode);
    bindSidebarToggles();
    initLogoutConfirmation();
    initTopUtilities();
});







