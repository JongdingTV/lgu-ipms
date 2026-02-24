(function () {
    'use strict';

    var page = document.body ? (document.body.getAttribute('data-page') || '') : '';
    if (!page) return;

    var csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
    var csrfToken = String(window.ADMIN_CSRF_TOKEN || (csrfMeta ? csrfMeta.getAttribute('content') : '') || '');

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function statusChip(status) {
        var raw = String(status || 'pending');
        var key = raw.toLowerCase();
        var cls = 'for-approval';
        if (key === 'approved' || key === 'verified') cls = 'approved';
        else if (key === 'rejected' || key === 'blacklisted') cls = 'cancelled';
        else if (key === 'suspended') cls = 'on-hold';
        return '<span class="status-chip ' + cls + '">' + esc(raw.replace('_', ' ')) + '</span>';
    }

    function fmtDate(v) {
        if (!v) return '-';
        var d = new Date(v);
        if (Number.isNaN(d.getTime())) return String(v);
        return d.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function setFeedback(msg, isErr) {
        var el = document.getElementById('appFeedback');
        if (!el) return;
        el.textContent = msg || '';
        el.className = msg ? (isErr ? 'ac-aabba7cf' : 'ac-0b2b14a3') : 'ac-c8be1ccb';
    }

    function getUrl(type, action, params) {
        var qs = new URLSearchParams(params || {});
        qs.set('type', type);
        qs.set('action', action);
        return '/admin/applications_api.php?' + qs.toString();
    }

    function apiGet(type, action, params) {
        return fetch(getUrl(type, action, params), { credentials: 'same-origin' })
            .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); });
    }

    function apiPost(type, action, payload) {
        var body = new URLSearchParams();
        Object.keys(payload || {}).forEach(function (k) { body.set(k, String(payload[k])); });
        body.set('type', type);
        body.set('csrf_token', csrfToken);

        return fetch('/admin/applications_api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: body.toString()
        }).then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); });
    }

    function getType() {
        if (page === 'applications-engineers') return 'engineer';
        if (page === 'applications-contractors') return 'contractor';
        if (page === 'applications-verified') {
            return String((document.getElementById('appVerifiedType') || {}).value || 'engineer');
        }
        if (page === 'applications-rejected') {
            return String((document.getElementById('appRejectedType') || {}).value || 'engineer');
        }
        return 'engineer';
    }

    function loadSummary(type) {
        return apiGet(type, 'load_summary').then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) return;
            var d = res.json.data || {};
            [['sumPending', 'pending'], ['sumUnderReview', 'under_review'], ['sumVerified', 'verified'], ['sumApproved', 'approved'], ['sumRejected', 'rejected']].forEach(function (pair) {
                var el = document.getElementById(pair[0]);
                if (el) el.textContent = String(d[pair[1]] || 0);
            });
        });
    }

    var currentRows = [];

    function collectFilters() {
        return {
            q: (document.getElementById('appSearch') || {}).value || '',
            status: (document.getElementById('appStatus') || {}).value || '',
            specialization: (document.getElementById('appSpecialization') || {}).value || '',
            area: (document.getElementById('appArea') || {}).value || '',
            date_submitted: (document.getElementById('appDateSubmitted') || {}).value || ''
        };
    }

    function renderApplicationRows(rows) {
        var tbody = document.querySelector('#appTable tbody');
        if (!tbody) return;
        if (!Array.isArray(rows) || rows.length < 1) {
            tbody.innerHTML = '<tr><td colspan="8" class="table-empty">No applications found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (r, idx) {
            var isEng = getType() === 'engineer';
            return [
                '<tr>',
                '<td class="wrap">' + esc(r.display_name || '-') + '</td>',
                '<td>' + (isEng ? 'Engineer' : 'Contractor') + '</td>',
                '<td>' + statusChip(r.status) + '</td>',
                '<td>' + esc(fmtDate(r.created_at)) + '</td>',
                '<td class="wrap">' + esc((r.assigned_area || '-') + ' / ' + (r.specialization || '-')) + '</td>',
                '<td class="wrap">' + esc(r.email || '-') + '</td>',
                '<td class="actions">',
                '<button type="button" class="action-btn action-view" data-action="view" data-index="' + idx + '">View</button>',
                '<button type="button" class="action-btn action-review" data-action="under_review" data-index="' + idx + '">Under Review</button>',
                '<button type="button" class="action-btn action-verify" data-action="verified" data-index="' + idx + '">Verify</button>',
                '<button type="button" class="action-btn action-approve" data-action="approved" data-index="' + idx + '">Approve</button>',
                '<button type="button" class="action-btn action-reject" data-action="rejected" data-index="' + idx + '">Reject</button>',
                '<button type="button" class="action-btn action-suspend" data-action="suspended" data-index="' + idx + '">Suspend</button>',
                '</td>',
                '</tr>'
            ].join('');
        }).join('');

        tbody.querySelectorAll('button[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = this.getAttribute('data-action') || '';
                var idx = Number(this.getAttribute('data-index') || -1);
                if (idx < 0 || !currentRows[idx]) return;
                if (action === 'view') {
                    loadDetails(currentRows[idx].id);
                    return;
                }
                openStatusModal(currentRows[idx], action);
            });
        });
    }

    function loadApplications() {
        var type = getType();
        setFeedback('Loading applications...', false);
        apiGet(type, 'load_applications', collectFilters()).then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load applications.');
            currentRows = Array.isArray(res.json.data) ? res.json.data : [];
            renderApplicationRows(currentRows);
            setFeedback('', false);
            loadSummary(type);
        }).catch(function (err) {
            renderApplicationRows([]);
            setFeedback(err.message || 'Unable to load applications.', true);
        });
    }

    function renderSimpleTable(rows, cols, tbodySelector) {
        var tbody = document.querySelector(tbodySelector);
        if (!tbody) return;
        if (!rows || rows.length < 1) {
            tbody.innerHTML = '<tr><td colspan="' + cols.length + '" class="table-empty">No records found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            return '<tr>' + cols.map(function (c) { return '<td class="wrap">' + esc(c.format ? c.format(r[c.key], r) : (r[c.key] || '-')) + '</td>'; }).join('') + '</tr>';
        }).join('');
    }

    function loadVerified() {
        var type = getType();
        setFeedback('Loading verified users...', false);
        apiGet(type, 'load_verified_users').then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load verified users.');
            renderSimpleTable(res.json.data || [], [
                { key: 'display_name' },
                { key: 'email' },
                { key: 'specialization' },
                { key: 'status', format: function (v) { return statusChip(v); } },
                { key: 'approved_at', format: fmtDate }
            ], '#verifiedTable tbody');
            setFeedback('', false);
        }).catch(function (err) {
            renderSimpleTable([], [{ key: 'a' }], '#verifiedTable tbody');
            setFeedback(err.message || 'Unable to load verified users.', true);
        });
    }

    function loadRejected() {
        var type = getType();
        setFeedback('Loading rejected/suspended users...', false);
        apiGet(type, 'load_rejected_users').then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load records.');
            renderSimpleTable(res.json.data || [], [
                { key: 'display_name' },
                { key: 'email' },
                { key: 'specialization' },
                { key: 'status', format: function (v) { return statusChip(v); } },
                { key: 'rejection_reason' },
                { key: 'created_at', format: fmtDate }
            ], '#rejectedTable tbody');
            setFeedback('', false);
        }).catch(function (err) {
            renderSimpleTable([], [{ key: 'a' }], '#rejectedTable tbody');
            setFeedback(err.message || 'Unable to load records.', true);
        });
    }

    function ensureModal() {
        var modal = document.getElementById('applicationModal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'applicationModal';
        modal.className = 'app-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.innerHTML = [
            '<div class="app-modal-backdrop" data-close="1"></div>',
            '<div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="applicationModalTitle">',
            '<div class="app-modal-head"><h3 id="applicationModalTitle">Application Details</h3><button type="button" class="app-modal-close" data-close="1">&times;</button></div>',
            '<div id="applicationModalBody" class="app-modal-body"></div>',
            '</div>'
        ].join('');
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-close') === '1') closeModal();
        });
        return modal;
    }

    function openModal(html) {
        var modal = ensureModal();
        var body = document.getElementById('applicationModalBody');
        if (body) body.innerHTML = html;
        modal.removeAttribute('hidden');
        document.body.classList.add('app-modal-open');
    }

    function closeModal() {
        var modal = document.getElementById('applicationModal');
        if (!modal) return;
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('app-modal-open');
    }

    function loadDetails(id) {
        var type = getType();
        apiGet(type, 'get_application', { id: id }).then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load details.');
            var app = res.json.data || {};
            var docs = res.json.documents || [];
            var logs = res.json.logs || [];

            var details = Object.keys(app).map(function (k) {
                if (['account_password_hash'].indexOf(k) !== -1) return '';
                var isFull = /reason|remarks|address|area|notes/.test(k);
                return '<div class="app-details-item ' + (isFull ? 'full' : '') + '"><label>' + esc(k.replace(/_/g, ' ')) + '</label><div class="app-details-value">' + (k === 'status' ? statusChip(app[k]) : esc(app[k] == null ? '-' : app[k])) + '</div></div>';
            }).join('');

            var docHtml = docs.length ? docs.map(function (d) {
                var href = '/admin/application_document.php?id=' + encodeURIComponent(d.id);
                return '<div class="app-doc-item"><div><strong>' + esc(d.doc_type || 'Document') + '</strong></div><div><a href="' + href + '" target="_blank">' + esc(d.original_name || d.file_path || 'Open document') + '</a></div><small>' + esc(fmtDate(d.uploaded_at)) + '</small></div>';
            }).join('') : '<div class="app-doc-item">No uploaded documents.</div>';

            var logHtml = logs.length ? logs.map(function (l) {
                return '<div class="app-log-item"><strong>' + esc(l.action || '-') + '</strong><div>' + esc(l.remarks || '-') + '</div><small>' + esc((l.performed_by || 'System') + ' - ' + fmtDate(l.created_at)) + '</small></div>';
            }).join('') : '<div class="app-log-item">No history entries.</div>';

            var html = [
                '<div>',
                '<div class="app-details-grid">', details, '</div>',
                '</div>',
                '<div>',
                '<div class="app-checklist">',
                '<label><input type="checkbox" id="chkPrc"> PRC/License valid</label>',
                '<label><input type="checkbox" id="chkDocs"> Documents complete</label>',
                '<label><input type="checkbox" id="chkSpec"> Matches specialization</label>',
                '<label><input type="checkbox" id="chkContact"> Contact verified</label>',
                '</div>',
                '<div style="height:10px"></div>',
                '<div class="app-doc-list">', docHtml, '</div>',
                '<div style="height:10px"></div>',
                '<div class="app-log-list">', logHtml, '</div>',
                '<div style="height:10px"></div>',
                '<div class="app-status-actions">',
                '<label>Status Action</label>',
                '<select id="appStatusAction"><option value="under_review">Mark Under Review</option><option value="verified">Mark Verified</option><option value="approved">Approve Account</option><option value="rejected">Reject</option><option value="suspended">Suspend</option><option value="pending">Request Revision</option></select>',
                '<label>Admin Remarks</label><textarea id="appAdminRemarks" rows="3" placeholder="Review notes..."></textarea>',
                '<label>Reason (required for reject/suspend)</label><textarea id="appAdminReason" rows="2" placeholder="Reason..."></textarea>',
                '<div class="app-status-btns"><button type="button" class="action-approve" id="appSaveStatusBtn">Save Decision</button><button type="button" class="action-view" id="appCloseModalBtn">Close</button></div>',
                '</div>',
                '</div>'
            ].join('');

            openModal(html);

            var closeBtn = document.getElementById('appCloseModalBtn');
            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            var saveBtn = document.getElementById('appSaveStatusBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function () {
                    var newStatus = String((document.getElementById('appStatusAction') || {}).value || '').toLowerCase();
                    var remarks = String((document.getElementById('appAdminRemarks') || {}).value || '').trim();
                    var reason = String((document.getElementById('appAdminReason') || {}).value || '').trim();
                    var checklist = {
                        prc_valid: !!((document.getElementById('chkPrc') || {}).checked),
                        docs_complete: !!((document.getElementById('chkDocs') || {}).checked),
                        specialization_match: !!((document.getElementById('chkSpec') || {}).checked),
                        contact_verified: !!((document.getElementById('chkContact') || {}).checked)
                    };
                    if ((newStatus === 'rejected' || newStatus === 'suspended' || newStatus === 'blacklisted') && reason === '') {
                        setFeedback('Reason is required for this action.', true);
                        return;
                    }
                    apiPost(type, 'update_status', {
                        id: id,
                        new_status: newStatus,
                        admin_remarks: remarks,
                        reason: reason,
                        checklist_json: JSON.stringify(checklist)
                    }).then(function (resp) {
                        if (!resp.ok || !resp.json || resp.json.success !== true) throw new Error((resp.json && resp.json.message) || 'Unable to update application.');
                        setFeedback(resp.json.message || 'Application updated.', false);
                        closeModal();
                        if (page === 'applications-verified') loadVerified();
                        else if (page === 'applications-rejected') loadRejected();
                        else loadApplications();
                    }).catch(function (err) {
                        setFeedback(err.message || 'Unable to update application.', true);
                    });
                });
            }

        }).catch(function (err) {
            setFeedback(err.message || 'Unable to load details.', true);
        });
    }

    function openStatusModal(row, action) {
        loadDetails(row.id);
        setTimeout(function () {
            var sel = document.getElementById('appStatusAction');
            if (sel) sel.value = action;
        }, 70);
    }

    function initApplicationsPage() {
        var applyBtn = document.getElementById('appApplyFilters');
        var resetBtn = document.getElementById('appResetFilters');
        if (applyBtn) applyBtn.addEventListener('click', loadApplications);
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                ['appSearch', 'appStatus', 'appSpecialization', 'appArea', 'appDateSubmitted'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                    else el.value = '';
                });
                loadApplications();
            });
        }
        loadApplications();
    }

    function initVerifiedPage() {
        var typeSel = document.getElementById('appVerifiedType');
        if (typeSel) typeSel.addEventListener('change', loadVerified);
        loadVerified();
    }

    function initRejectedPage() {
        var typeSel = document.getElementById('appRejectedType');
        if (typeSel) typeSel.addEventListener('change', loadRejected);
        loadRejected();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    if (page === 'applications-engineers' || page === 'applications-contractors') initApplicationsPage();
    if (page === 'applications-verified') initVerifiedPage();
    if (page === 'applications-rejected') initRejectedPage();
})();
