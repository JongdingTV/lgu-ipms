document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = String(window.DEPARTMENT_HEAD_CSRF || (csrfMeta ? csrfMeta.getAttribute('content') : '') || '');
    var permissionFlags = window.DEPARTMENT_HEAD_PERMISSIONS || {};
    var canManage = Boolean(permissionFlags.approvalsManage);
    var canReadNotifications = Boolean(permissionFlags.notificationsRead);
    var state = { rows: [], mode: 'pending' };

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showMessage(type, text) {
        var box = document.getElementById('feedback');
        if (!box) return;
        box.className = type === 'ok' ? 'ac-0b2b14a3' : 'ac-aabba7cf';
        box.textContent = text;
    }

    function statusChip(status) {
        var raw = String(status || 'Pending');
        var key = raw.toLowerCase();
        var cls = 'default';
        if (key === 'approved') cls = 'approved';
        else if (key === 'rejected') cls = 'cancelled';
        else if (key === 'for approval' || key === 'pending' || key === 'draft') cls = 'for-approval';
        return '<span class="status-chip ' + cls + '">' + esc(raw) + '</span>';
    }

    function apiGet(action, params) {
        var query = new URLSearchParams(params || {});
        query.set('action', action);
        return fetch('/department-head/api.php?' + query.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, json: j }; }); });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (k) {
            body.set(k, String(payload[k]));
        });
        body.set('csrf_token', csrfToken);
        return fetch('/department-head/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: body.toString()
        }).then(function (res) { return res.json().then(function (j) { return { ok: res.ok, json: j }; }); });
    }

    function renderStats(rows) {
        var pending = rows.filter(function (r) { return String(r.decision_status || 'Pending').toLowerCase() === 'pending'; }).length;
        var approved = rows.filter(function (r) { return String(r.decision_status || '').toLowerCase() === 'approved'; }).length;
        var rejected = rows.filter(function (r) { return String(r.decision_status || '').toLowerCase() === 'rejected'; }).length;
        var reviewed = approved + rejected;
        var set = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = String(value);
        };
        set('statPending', pending);
        set('statApproved', approved);
        set('statRejected', rejected);
        set('statReviewed', reviewed);
    }

    function initTopUtilities() {
        if (document.querySelector('.admin-top-utilities')) return;

        var bar = document.createElement('div');
        bar.className = 'admin-top-utilities';
        bar.innerHTML = ''
            + '<div class="admin-top-left">'
            + '  <button type="button" class="admin-top-menu" id="deptTopMenuBtn" aria-label="Toggle sidebar" title="Toggle sidebar">'
            + '    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>'
            + '  </button>'
            + '</div>'
            + '<div class="admin-top-right">'
            + '  <div class="admin-time-chip" aria-live="polite">'
            + '    <span class="admin-time" id="deptLiveTime">--:--:--</span>'
            + '    <span class="admin-date" id="deptLiveDate">----</span>'
            + '  </div>'
            + '  <div class="admin-utility-group">'
            + '    <button type="button" class="admin-utility-btn" id="deptCalendarBtn" aria-expanded="false" aria-controls="deptCalendarPanel" title="Calendar">'
            + '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>'
            + '      <span class="admin-utility-label" id="deptCalendarLabel">Calendar</span>'
            + '    </button>'
            + '    <button type="button" class="admin-utility-btn" id="deptNotifBtn" aria-expanded="false" aria-controls="deptNotifPanel" title="Notifications">'
            + '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path><path d="M9 17a3 3 0 0 0 6 0"></path></svg>'
            + '      <span class="admin-utility-label">Alerts</span>'
            + '      <span class="admin-utility-badge" id="deptNotifCount">0</span>'
            + '    </button>'
            + '    <button type="button" class="admin-utility-btn" id="deptThemeBtn" title="Toggle dark mode">'
            + '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>'
            + '      <span class="admin-utility-label" id="deptThemeLabel">Dark</span>'
            + '    </button>'
            + '  </div>'
            + '  <div class="admin-notif-panel" id="deptNotifPanel" hidden>'
            + '    <div class="admin-notif-head"><strong>Notifications</strong><button type="button" id="deptNotifMarkRead">Mark all read</button></div>'
            + '    <ul class="admin-notif-list" id="deptNotifList"></ul>'
            + '  </div>'
            + '  <div class="admin-calendar-panel" id="deptCalendarPanel" hidden>'
            + '    <div class="admin-calendar-head"><button type="button" id="deptCalPrev" aria-label="Previous month">&#8249;</button><strong id="deptCalTitle">Month Year</strong><button type="button" id="deptCalNext" aria-label="Next month">&#8250;</button></div>'
            + '    <div class="admin-calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>'
            + '    <div class="admin-calendar-grid" id="deptCalendarGrid" aria-live="polite"></div>'
            + '    <div class="admin-calendar-foot"><button type="button" id="deptCalToday">Today</button></div>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(bar);

        var themeKey = 'ipms_department_head_theme';
        var seenKey = 'ipms_department_head_notifications_seen_id';
        var today = new Date();
        var calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);
        var latestNotificationId = 0;
        var notifications = [];

        var topMenuBtn = document.getElementById('deptTopMenuBtn');
        var calendarBtn = document.getElementById('deptCalendarBtn');
        var calendarPanel = document.getElementById('deptCalendarPanel');
        var calendarLabel = document.getElementById('deptCalendarLabel');
        var notifBtn = document.getElementById('deptNotifBtn');
        var notifPanel = document.getElementById('deptNotifPanel');
        var notifList = document.getElementById('deptNotifList');
        var notifCount = document.getElementById('deptNotifCount');
        var themeBtn = document.getElementById('deptThemeBtn');
        var themeLabel = document.getElementById('deptThemeLabel');
        var timeEl = document.getElementById('deptLiveTime');
        var dateEl = document.getElementById('deptLiveDate');
        var calTitle = document.getElementById('deptCalTitle');
        var calGrid = document.getElementById('deptCalendarGrid');

        document.body.classList.toggle('theme-dark', localStorage.getItem(themeKey) === 'dark');
        if (themeLabel) themeLabel.textContent = document.body.classList.contains('theme-dark') ? 'Light' : 'Dark';

        function getSeenId() { return Number(localStorage.getItem(seenKey) || 0) || 0; }
        function setSeenId(id) { localStorage.setItem(seenKey, String(Number(id) || 0)); }

        function renderNotifications() {
            if (!notifList || !notifCount) return;
            var seenId = getSeenId();
            var unread = notifications.filter(function (n) { return Number(n.id) > seenId; }).length;
            notifList.innerHTML = notifications.map(function (n) {
                var isUnread = Number(n.id) > seenId;
                return '<li class="admin-notif-item is-' + esc(n.level || 'info') + (isUnread ? ' unread' : '') + '"><span class="dot"></span><span><strong>' + esc(n.title || 'Update') + '</strong><small>' + esc(n.message || '') + '</small><em>' + esc(n.created_at || '') + '</em></span></li>';
            }).join('');
            notifCount.textContent = String(unread);
            notifCount.style.display = unread ? 'inline-flex' : 'none';
        }

        window.departmentHeadRefreshTopNotifications = function () {
            if (!canReadNotifications) {
                notifications = [{ id: 0, level: 'info', title: 'Notifications unavailable', message: 'Your role does not have notification access.', created_at: '' }];
                renderNotifications();
                return;
            }
            fetch('/department-head/api.php?action=load_notifications', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || data.success !== true) return;
                    notifications = Array.isArray(data.items) ? data.items : [];
                    latestNotificationId = Number(data.latest_id || 0) || 0;
                    if (!notifications.length) {
                        notifications = [{ id: 0, level: 'info', title: 'No pending updates', message: 'No project alerts right now.', created_at: '' }];
                    }
                    renderNotifications();
                })
                .catch(function () {
                    notifications = [{ id: 0, level: 'danger', title: 'Notification service unavailable', message: 'Unable to load approval alerts.', created_at: '' }];
                    renderNotifications();
                });
        };

        function renderCalendar() {
            if (!calTitle || !calGrid) return;
            var year = calendarCursor.getFullYear();
            var month = calendarCursor.getMonth();
            var first = new Date(year, month, 1).getDay();
            var days = new Date(year, month + 1, 0).getDate();
            var prev = new Date(year, month, 0).getDate();
            calTitle.textContent = calendarCursor.toLocaleDateString([], { month: 'long', year: 'numeric' });
            var cells = [];
            for (var i = first - 1; i >= 0; i -= 1) cells.push('<span class="is-outside">' + (prev - i) + '</span>');
            for (var d = 1; d <= days; d += 1) cells.push('<span>' + d + '</span>');
            while (cells.length % 7 !== 0) cells.push('<span class="is-outside"></span>');
            calGrid.innerHTML = cells.join('');
        }

        function tick() {
            var now = new Date();
            if (timeEl) timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            if (dateEl) dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            if (calendarLabel) calendarLabel.textContent = now.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
        tick();
        setInterval(tick, 1000);
        renderCalendar();

        if (topMenuBtn) {
            topMenuBtn.addEventListener('click', function () {
                var btn = document.getElementById('toggleSidebar');
                if (btn) btn.click();
            });
        }
        if (calendarBtn && calendarPanel) {
            calendarBtn.addEventListener('click', function () {
                var open = calendarPanel.hasAttribute('hidden');
                calendarPanel.toggleAttribute('hidden', !open);
                calendarBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && notifPanel) {
                    notifPanel.setAttribute('hidden', 'hidden');
                    notifBtn && notifBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }
        if (notifBtn && notifPanel) {
            notifBtn.addEventListener('click', function () {
                var open = notifPanel.hasAttribute('hidden');
                notifPanel.toggleAttribute('hidden', !open);
                notifBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && calendarPanel) {
                    calendarPanel.setAttribute('hidden', 'hidden');
                    calendarBtn && calendarBtn.setAttribute('aria-expanded', 'false');
                    setSeenId(latestNotificationId);
                    renderNotifications();
                }
            });
        }
        var markRead = document.getElementById('deptNotifMarkRead');
        if (markRead) {
            markRead.addEventListener('click', function () {
                setSeenId(latestNotificationId);
                renderNotifications();
            });
        }
        var calPrev = document.getElementById('deptCalPrev');
        var calNext = document.getElementById('deptCalNext');
        var calToday = document.getElementById('deptCalToday');
        if (calPrev) calPrev.addEventListener('click', function () { calendarCursor = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth() - 1, 1); renderCalendar(); });
        if (calNext) calNext.addEventListener('click', function () { calendarCursor = new Date(calendarCursor.getFullYear(), calendarCursor.getMonth() + 1, 1); renderCalendar(); });
        if (calToday) calToday.addEventListener('click', function () { calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1); renderCalendar(); });

        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var isDark = !document.body.classList.contains('theme-dark');
                document.body.classList.toggle('theme-dark', isDark);
                localStorage.setItem(themeKey, isDark ? 'dark' : 'light');
                if (themeLabel) themeLabel.textContent = isDark ? 'Light' : 'Dark';
            });
        }
    }

    function getDetailsModal() {
        var existing = document.getElementById('deptDetailsModal');
        if (existing) {
            return existing;
        }
        var modal = document.createElement('div');
        modal.id = 'deptDetailsModal';
        modal.className = 'dept-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.innerHTML = [
            '<div class="dept-modal-backdrop" data-role="close"></div>',
            '<div class="dept-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="deptDetailsTitle">',
            '<div class="dept-modal-head">',
            '<h3 id="deptDetailsTitle">Project Details</h3>',
            '<button type="button" class="dept-modal-close" aria-label="Close" data-role="close">&times;</button>',
            '</div>',
            '<div id="deptDetailsBody" class="dept-modal-body"></div>',
            '</div>'
        ].join('');
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            var target = e.target;
            if (target && target.getAttribute('data-role') === 'close') {
                closeDetailsModal();
            }
        });

        return modal;
    }

    function openDetailsModal(html) {
        var modal = getDetailsModal();
        var body = document.getElementById('deptDetailsBody');
        if (body) {
            body.innerHTML = html;
        }
        modal.removeAttribute('hidden');
        document.body.classList.add('dept-modal-open');
    }

    function closeDetailsModal() {
        var modal = document.getElementById('deptDetailsModal');
        if (!modal) return;
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('dept-modal-open');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDetailsModal();
        }
    });

    function bindActionButtons() {
        var tbody = document.querySelector('#projectsTable tbody');
        if (!tbody) return;

        tbody.querySelectorAll('button[data-action="toggle-details"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                var row = null;
                for (var i = 0; i < state.rows.length; i += 1) {
                    if (String(state.rows[i].id) === String(id)) {
                        row = state.rows[i];
                        break;
                    }
                }
                if (!row) return;
                openDetailsModal(buildProjectDetails(row));
            });
        });

        tbody.querySelectorAll('button[data-action="approve"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!canManage) {
                    showMessage('err', 'You are not allowed to approve projects.');
                    return;
                }
                var id = this.getAttribute('data-id');
                var noteEl = document.querySelector('textarea[data-type="note"][data-id="' + id + '"]');
                var budgetEl = document.querySelector('input[data-type="budget"][data-id="' + id + '"]');
                var note = noteEl ? noteEl.value : '';
                var budget = budgetEl ? Number(budgetEl.value || 0) : 0;
                if (!(budget > 0)) {
                    showMessage('err', 'Enter a valid budget before approving.');
                    return;
                }
                apiPost('decide_project', {
                    project_id: id,
                    decision_status: 'Approved',
                    decision_note: note,
                    budget_amount: budget
                }).then(function (res) {
                    if (!res.ok || !res.json || res.json.success === false) {
                        throw new Error((res.json && res.json.message) ? res.json.message : 'Failed to approve.');
                    }
                    showMessage('ok', 'Project approved. Admin can now assign contractors and engineers.');
                    load();
                }).catch(function (err) {
                    showMessage('err', err.message || 'Approval failed.');
                });
            });
        });

        tbody.querySelectorAll('button[data-action="reject"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!canManage) {
                    showMessage('err', 'You are not allowed to reject projects.');
                    return;
                }
                var id = this.getAttribute('data-id');
                var noteEl = document.querySelector('textarea[data-type="note"][data-id="' + id + '"]');
                var note = noteEl ? noteEl.value.trim() : '';
                if (note === '') {
                    showMessage('err', 'Please add a note before rejecting.');
                    return;
                }
                apiPost('decide_project', {
                    project_id: id,
                    decision_status: 'Rejected',
                    decision_note: note,
                    budget_amount: 0
                }).then(function (res) {
                    if (!res.ok || !res.json || res.json.success === false) {
                        throw new Error((res.json && res.json.message) ? res.json.message : 'Failed to reject.');
                    }
                    showMessage('ok', 'Project rejected and marked with Department Head decision.');
                    load();
                }).catch(function (err) {
                    showMessage('err', err.message || 'Rejection failed.');
                });
            });
        });
    }

    function renderTable() {
        var tbody = document.querySelector('#projectsTable tbody');
        var search = (document.getElementById('searchInput') && document.getElementById('searchInput').value || '').trim().toLowerCase();
        if (!tbody) return;
        tbody.innerHTML = '';

        var rows = state.rows.filter(function (row) {
            var hay = String((row.code || '') + ' ' + (row.name || '') + ' ' + (row.location || '')).toLowerCase();
            return !search || hay.indexOf(search) >= 0;
        });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="ac-a004b216">No projects found for this queue.</td></tr>';
            return;
        }

        rows.forEach(function (row) {
            var pending = String(row.decision_status || 'Pending').toLowerCase() === 'pending';
            var tr = document.createElement('tr');
            tr.innerHTML = [
                '<td>' + esc(row.code || '-') + '</td>',
                '<td><strong>' + esc(row.name || '-') + '</strong><br><small>' + esc(row.location || '-') + '</small></td>',
                '<td>' + statusChip(row.status || 'For Approval') + '</td>',
                '<td>' + statusChip(row.decision_status || 'Pending') + '<br><small>' + esc(row.decided_by_name || '') + (row.decided_at ? (' | ' + esc(row.decided_at)) : '') + '</small></td>',
                '<td><div class="dept-budget-wrap"><label class="dept-input-label">Project Budget (PHP)</label><input class="dept-budget-input" data-type="budget" data-id="' + esc(row.id) + '" type="number" min="0" step="0.01" placeholder="Enter project budget" value="' + esc(Number(row.budget || 0) > 0 ? row.budget : '') + '"' + (canManage ? '' : ' disabled') + '></div><textarea class="dept-note" data-type="note" data-id="' + esc(row.id) + '" placeholder="Decision note for admin and audit trail"' + (canManage ? '' : ' disabled') + '>' + esc(row.decision_note || '') + '</textarea></td>',
                '<td><div class="dept-action-group"><button type="button" class="dept-btn details" data-action="toggle-details" data-id="' + esc(row.id) + '">View Details</button>' + (pending
                    ? (canManage
                        ? '<button type="button" class="dept-btn approve" data-action="approve" data-id="' + esc(row.id) + '">Approve</button><button type="button" class="dept-btn reject" data-action="reject" data-id="' + esc(row.id) + '">Reject</button>'
                        : '<span class="ac-a004b216 dept-finalized-badge">Read-only</span>')
                    : '<span class="ac-a004b216 dept-finalized-badge">Finalized</span>') + '</div></td>'
            ].join('');
            tbody.appendChild(tr);
        });

        bindActionButtons();
    }

    function buildProjectDetails(row) {
        var reserved = {
            decision_status: true,
            decision_note: true,
            decided_by_name: true,
            decided_at: true
        };
        var preferred = [
            'code', 'name', 'type', 'sector', 'description', 'priority', 'priority_percent',
            'province', 'barangay', 'location', 'start_date', 'end_date', 'duration_months',
            'budget', 'status', 'engineer_license_doc', 'engineer_certification_doc',
            'engineer_credentials_doc', 'created_at', 'updated_at'
        ];
        var html = [];
        preferred.forEach(function (k) {
            if (Object.prototype.hasOwnProperty.call(row, k)) {
                html.push(renderDetailItem(k, row[k]));
            }
        });
        Object.keys(row).forEach(function (k) {
            if (reserved[k]) return;
            if (preferred.indexOf(k) >= 0) return;
            html.push(renderDetailItem(k, row[k]));
        });
        if (!html.length) return '<div class="ac-a004b216">No project details available.</div>';
        return '<div class="dept-details-head">Project Information</div><div class="dept-project-grid">' + html.join('') + '</div>';
    }

    function renderDetailItem(key, value) {
        var label = String(key || '').replace(/_/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); });
        var output = (value == null || value === '') ? '-' : String(value);
        return '<div class="form-group dept-project-item"><label>' + esc(label) + '</label><div class="dept-readonly-field">' + esc(output) + '</div></div>';
    }

    function load() {
        apiGet('load_projects', { mode: state.mode }).then(function (res) {
            if (!res.ok || !res.json || res.json.success === false) {
                throw new Error((res.json && res.json.message) ? res.json.message : 'Unable to load projects.');
            }
            state.rows = Array.isArray(res.json.data) ? res.json.data : [];
            renderStats(state.rows);
            renderTable();
            if (typeof window.departmentHeadRefreshTopNotifications === 'function') {
                window.departmentHeadRefreshTopNotifications();
            }
        }).catch(function (err) {
            showMessage('err', err.message || 'Unable to load projects.');
        });
    }

    var searchInput = document.getElementById('searchInput');
    var statusFilter = document.getElementById('statusFilter');
    if (searchInput) {
        searchInput.addEventListener('input', renderTable);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            state.mode = String(this.value || 'pending').toLowerCase();
            load();
        });
    }

    initTopUtilities();
    load();
});
