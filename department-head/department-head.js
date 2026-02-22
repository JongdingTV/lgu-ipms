document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var csrfToken = String(window.DEPARTMENT_HEAD_CSRF || '');
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
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

    function bindActionButtons() {
        var tbody = document.querySelector('#projectsTable tbody');
        if (!tbody) return;

        tbody.querySelectorAll('button[data-action="toggle-details"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                var row = tbody.querySelector('tr[data-details-for="' + id + '"]');
                if (!row) return;
                var showing = row.style.display !== 'none';
                row.style.display = showing ? 'none' : 'table-row';
                this.textContent = showing ? 'View Details' : 'Hide Details';
            });
        });

        tbody.querySelectorAll('button[data-action="approve"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
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
                '<td><div class="dept-budget-wrap"><label class="dept-input-label">Project Budget (PHP)</label><input class="dept-budget-input" data-type="budget" data-id="' + esc(row.id) + '" type="number" min="0" step="0.01" placeholder="Enter project budget" value="' + esc(Number(row.budget || 0) > 0 ? row.budget : '') + '"></div><textarea class="dept-note" data-type="note" data-id="' + esc(row.id) + '" placeholder="Decision note for admin and audit trail">' + esc(row.decision_note || '') + '</textarea></td>',
                '<td><div class="dept-action-group"><button type="button" class="dept-btn details" data-action="toggle-details" data-id="' + esc(row.id) + '">View Details</button>' + (pending
                    ? '<button type="button" class="dept-btn approve" data-action="approve" data-id="' + esc(row.id) + '">Approve</button><button type="button" class="dept-btn reject" data-action="reject" data-id="' + esc(row.id) + '">Reject</button>'
                    : '<span class="ac-a004b216 dept-finalized-badge">Finalized</span>') + '</div></td>'
            ].join('');
            tbody.appendChild(tr);

            var detailsTr = document.createElement('tr');
            detailsTr.setAttribute('data-details-for', String(row.id || ''));
            detailsTr.style.display = 'none';
            detailsTr.innerHTML = '<td colspan="6"><div class="dept-project-details">' + buildProjectDetails(row) + '</div></td>';
            tbody.appendChild(detailsTr);
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

    load();
});
