(function () {
    'use strict';

    var page = (document.body && document.body.getAttribute('data-page')) || '';
    if (!page) return;

    var csrfToken = String(window.DEPARTMENT_HEAD_CSRF || '');

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtDate(v) {
        if (!v) return '-';
        var d = new Date(v);
        if (Number.isNaN(d.getTime())) return String(v);
        return d.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function fmtDateTime(v) {
        if (!v) return '-';
        var d = new Date(v);
        if (Number.isNaN(d.getTime())) return String(v);
        return d.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function fmtPct(v) {
        var n = Number(v || 0);
        if (!Number.isFinite(n)) n = 0;
        n = Math.max(0, Math.min(100, n));
        return n.toFixed(1) + '%';
    }

    function statusChip(raw) {
        var txt = String(raw || 'Pending');
        var k = txt.toLowerCase();
        var cls = 'for-approval';
        if (k === 'approved' || k === 'completed') cls = 'approved';
        else if (k === 'rejected' || k === 'cancelled') cls = 'cancelled';
        else if (k === 'delayed' || k === 'critical') cls = 'on-hold';
        return '<span class="status-chip ' + cls + '">' + esc(txt) + '</span>';
    }

    function priorityChip(raw) {
        var txt = String(raw || 'Medium');
        var k = txt.toLowerCase();
        var cls = 'status-chip';
        if (k === 'critical') cls += ' on-hold';
        else if (k === 'high') cls += ' for-approval';
        else if (k === 'medium') cls += ' default';
        else cls += ' approved';
        return '<span class="' + cls + '">' + esc(txt) + '</span>';
    }

    function setFeedback(message, isError) {
        var box = document.getElementById('dhFeedback');
        if (!box) return;
        box.textContent = message || '';
        box.className = isError ? 'ac-aabba7cf' : 'ac-0b2b14a3';
        if (!message) box.className = 'ac-c8be1ccb';
    }

    function apiGet(action, params) {
        var qs = new URLSearchParams(params || {});
        qs.set('action', action);
        return fetch('/department-head/api.php?' + qs.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); });
    }

    function apiPost(action, payload) {
        var body = new URLSearchParams();
        var obj = payload || {};
        Object.keys(obj).forEach(function (key) { body.set(key, String(obj[key])); });
        body.set('csrf_token', csrfToken);
        return fetch('/department-head/api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: body.toString()
        }).then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); });
    }

    function buildMonitoringDetails(row) {
        return [
            '<div class="dept-project-details">',
            '<div class="dept-details-head">Project Details</div>',
            '<div class="dept-project-grid">',
            '<div class="dept-project-item"><label>Code</label><div class="dept-readonly-field">' + esc(row.code || '-') + '</div></div>',
            '<div class="dept-project-item"><label>Name</label><div class="dept-readonly-field">' + esc(row.name || '-') + '</div></div>',
            '<div class="dept-project-item"><label>Status</label><div class="dept-readonly-field">' + statusChip(row.status || '-') + '</div></div>',
            '<div class="dept-project-item"><label>Priority</label><div class="dept-readonly-field">' + priorityChip(row.priority_level || 'Medium') + '</div></div>',
            '<div class="dept-project-item"><label>Progress</label><div class="dept-readonly-field">' + esc(fmtPct(row.progress_percent)) + '</div></div>',
            '<div class="dept-project-item"><label>Delay</label><div class="dept-readonly-field">' + (Number(row.is_delayed || 0) === 1 ? 'Delayed' : 'On Track') + '</div></div>',
            '<div class="dept-project-item"><label>Assigned Engineer</label><div class="dept-readonly-field">' + esc(row.assigned_engineer || '-') + '</div></div>',
            '<div class="dept-project-item"><label>Assigned Contractor</label><div class="dept-readonly-field">' + esc(row.assigned_contractor || '-') + '</div></div>',
            '<div class="dept-project-item"><label>Start Date</label><div class="dept-readonly-field">' + esc(fmtDate(row.start_date)) + '</div></div>',
            '<div class="dept-project-item"><label>End Date</label><div class="dept-readonly-field">' + esc(fmtDate(row.end_date)) + '</div></div>',
            '<div class="dept-project-item dept-project-item-full"><label>Location</label><div class="dept-readonly-field">' + esc(row.location || '-') + '</div></div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function getDetailsModal() {
        var modal = document.getElementById('deptDetailsModal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'deptDetailsModal';
        modal.className = 'dept-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.innerHTML = [
            '<div class="dept-modal-backdrop" data-role="close"></div>',
            '<div class="dept-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="deptModalTitle">',
            '<div class="dept-modal-head"><h3 id="deptModalTitle">Details</h3><button type="button" class="dept-modal-close" data-role="close" aria-label="Close">&times;</button></div>',
            '<div id="deptModalBody" class="dept-modal-body"></div>',
            '</div>'
        ].join('');
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) {
            if (e.target && e.target.getAttribute('data-role') === 'close') {
                modal.setAttribute('hidden', 'hidden');
                document.body.classList.remove('dept-modal-open');
            }
        });
        return modal;
    }

    function openDetails(html) {
        var modal = getDetailsModal();
        var body = document.getElementById('deptModalBody');
        if (body) body.innerHTML = html;
        modal.removeAttribute('hidden');
        document.body.classList.add('dept-modal-open');
    }

    function initMonitoringPage() {
        var tableBody = document.querySelector('#dhMonitoringTable tbody');
        var applyBtn = document.getElementById('dhMonitoringApply');
        if (!tableBody || !applyBtn) return;

        var rows = [];

        function collectFilters() {
            return {
                search: (document.getElementById('dhSearch') || {}).value || '',
                status: (document.getElementById('dhStatus') || {}).value || '',
                district: (document.getElementById('dhDistrict') || {}).value || '',
                barangay: (document.getElementById('dhBarangay') || {}).value || '',
                engineer: (document.getElementById('dhEngineer') || {}).value || '',
                contractor: (document.getElementById('dhContractor') || {}).value || '',
                priority: (document.getElementById('dhPriority') || {}).value || ''
            };
        }

        function render(list) {
            var total = Array.isArray(list) ? list.length : 0;
            var delayed = 0;
            var completed = 0;
            var active = 0;
            (list || []).forEach(function (r) {
                var statusKey = String(r.status || '').toLowerCase();
                if (Number(r.is_delayed || 0) === 1 || statusKey === 'delayed') delayed += 1;
                if (statusKey === 'completed') completed += 1;
                if (['approved', 'for approval', 'ongoing', 'in progress'].indexOf(statusKey) !== -1) active += 1;
            });
            var statTotal = document.getElementById('dhMonTotal');
            var statActive = document.getElementById('dhMonActive');
            var statDelayed = document.getElementById('dhMonDelayed');
            var statCompleted = document.getElementById('dhMonCompleted');
            if (statTotal) statTotal.textContent = String(total);
            if (statActive) statActive.textContent = String(active);
            if (statDelayed) statDelayed.textContent = String(delayed);
            if (statCompleted) statCompleted.textContent = String(completed);

            if (!Array.isArray(list) || list.length < 1) {
                tableBody.innerHTML = '<tr><td colspan="11" class="table-empty">No projects found for selected filters.</td></tr>';
                return;
            }
            tableBody.innerHTML = list.map(function (r, idx) {
                return [
                    '<tr>',
                    '<td>' + esc(r.code || '-') + '</td>',
                    '<td>' + esc(r.name || '-') + '</td>',
                    '<td>' + statusChip(r.status || '-') + '</td>',
                    '<td>' + esc(fmtPct(r.progress_percent)) + '</td>',
                    '<td>' + priorityChip(r.priority_level || 'Medium') + '</td>',
                    '<td>' + esc(r.assigned_engineer || '-') + '</td>',
                    '<td>' + esc(r.assigned_contractor || '-') + '</td>',
                    '<td>' + esc(fmtDate(r.start_date)) + '</td>',
                    '<td>' + esc(fmtDate(r.end_date)) + '</td>',
                    '<td>' + (Number(r.is_delayed || 0) === 1 ? '<span class="status-chip on-hold">Delayed</span>' : '<span class="status-chip approved">On Track</span>') + '</td>',
                    '<td><button type="button" class="dept-btn details" data-row-index="' + idx + '">View</button></td>',
                    '</tr>'
                ].join('');
            }).join('');

            tableBody.querySelectorAll('button[data-row-index]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var i = Number(this.getAttribute('data-row-index') || -1);
                    if (i < 0 || !rows[i]) return;
                    openDetails(buildMonitoringDetails(rows[i]));
                });
            });
        }

        function load() {
            setFeedback('Loading monitoring data...', false);
            apiGet('load_monitoring', collectFilters()).then(function (res) {
                if (!res.ok || !res.json || res.json.success !== true) {
                    throw new Error((res.json && res.json.message) || 'Unable to load monitoring data.');
                }
                rows = Array.isArray(res.json.data) ? res.json.data : [];
                render(rows);
                setFeedback('', false);
            }).catch(function (err) {
                render([]);
                setFeedback(err.message || 'Unable to load monitoring data.', true);
            });
        }

        applyBtn.addEventListener('click', load);
        load();
    }

    function initPriorityPage() {
        var tableBody = document.querySelector('#dhPriorityTable tbody');
        if (!tableBody) return;

        function render(list) {
            if (!Array.isArray(list) || list.length < 1) {
                tableBody.innerHTML = '<tr><td colspan="7" class="table-empty">No approved projects available for prioritization.</td></tr>';
                return;
            }
            tableBody.innerHTML = list.map(function (r) {
                return [
                    '<tr data-id="' + esc(r.id) + '">',
                    '<td>' + esc(r.code || '-') + '</td>',
                    '<td>' + esc(r.name || '-') + '</td>',
                    '<td>' + statusChip(r.status || '-') + '</td>',
                    '<td>' + esc(r.location || '-') + '</td>',
                    '<td>' + priorityChip(r.priority_level || 'Medium') + '</td>',
                    '<td><select class="dept-priority-select"><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select></td>',
                    '<td><div class="dept-action-group"><button type="button" class="dept-btn details" data-action="set-priority">Save</button><button type="button" class="dept-btn reject" data-action="set-urgent">Set Urgent</button></div></td>',
                    '</tr>'
                ].join('');
            }).join('');

            tableBody.querySelectorAll('tr').forEach(function (row) {
                var current = (row.querySelector('td:nth-child(5) .status-chip') || {}).textContent || 'Medium';
                var select = row.querySelector('.dept-priority-select');
                if (select) select.value = String(current).trim();
            });

            tableBody.querySelectorAll('button[data-action="set-priority"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tr = this.closest('tr');
                    if (!tr) return;
                    var projectId = tr.getAttribute('data-id');
                    var select = tr.querySelector('.dept-priority-select');
                    var priority = select ? select.value : 'Medium';
                    apiPost('set_project_priority', { project_id: projectId, priority_level: priority }).then(function (res) {
                        if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to set priority.');
                        setFeedback('Priority updated successfully.', false);
                        load();
                    }).catch(function (err) { setFeedback(err.message || 'Unable to set priority.', true); });
                });
            });

            tableBody.querySelectorAll('button[data-action="set-urgent"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tr = this.closest('tr');
                    if (!tr) return;
                    var projectId = tr.getAttribute('data-id');
                    apiPost('set_project_priority', { project_id: projectId, set_urgent: 1 }).then(function (res) {
                        if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to set urgent.');
                        setFeedback('Project marked as Critical.', false);
                        load();
                    }).catch(function (err) { setFeedback(err.message || 'Unable to set urgent.', true); });
                });
            });
        }

        function load() {
            setFeedback('Loading priority queue...', false);
            apiGet('load_priority_projects').then(function (res) {
                if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load priority projects.');
                render(Array.isArray(res.json.data) ? res.json.data : []);
                setFeedback('', false);
            }).catch(function (err) {
                render([]);
                setFeedback(err.message || 'Unable to load priority projects.', true);
            });
        }

        load();
    }

    function severityChip(value) {
        var txt = String(value || 'Medium');
        var k = txt.toLowerCase();
        var cls = 'status-chip default';
        if (k === 'critical') cls = 'status-chip on-hold';
        else if (k === 'high') cls = 'status-chip for-approval';
        else if (k === 'low') cls = 'status-chip approved';
        return '<span class="' + cls + '">' + esc(txt) + '</span>';
    }

    function initRiskPage() {
        var tableBody = document.querySelector('#dhRiskTable tbody');
        if (!tableBody) return;
        apiGet('load_risk_alerts').then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load risk alerts.');
            var rows = Array.isArray(res.json.data) ? res.json.data : [];
            if (rows.length < 1) {
                tableBody.innerHTML = '<tr><td colspan="4" class="table-empty">No current risk alerts.</td></tr>';
                return;
            }
            tableBody.innerHTML = rows.map(function (r) {
                return '<tr><td>' + esc(r.project_name || '-') + '</td><td>' + esc(r.issue_type || '-') + '</td><td>' + severityChip(r.severity_level || 'Medium') + '</td><td>' + esc(r.recommended_action || '-') + '</td></tr>';
            }).join('');
        }).catch(function (err) {
            tableBody.innerHTML = '<tr><td colspan="4" class="table-empty">Failed to load risk alerts.</td></tr>';
            setFeedback(err.message || 'Unable to load risk alerts.', true);
        });
    }

    function initDecisionLogsPage() {
        var tableBody = document.querySelector('#dhDecisionTable tbody');
        var applyBtn = document.getElementById('dhDecisionApply');
        if (!tableBody || !applyBtn) return;
        var allRows = [];

        function applyLocalFilters() {
            var q = String((document.getElementById('dhDecisionSearch') || {}).value || '').toLowerCase().trim();
            var t = String((document.getElementById('dhDecisionType') || {}).value || '').toLowerCase().trim();
            var d = String((document.getElementById('dhDecisionDate') || {}).value || '').trim();
            var filtered = allRows.filter(function (r) {
                var matchQ = true;
                if (q) {
                    var hay = [r.code, r.project_name, r.decision_type, r.notes, r.approved_by].join(' ').toLowerCase();
                    matchQ = hay.indexOf(q) !== -1;
                }
                var matchT = !t || String(r.decision_type || '').toLowerCase() === t;
                var matchD = true;
                if (d) {
                    var rowDate = String(r.created_at || '').slice(0, 10);
                    matchD = rowDate === d;
                }
                return matchQ && matchT && matchD;
            });
            if (filtered.length < 1) {
                tableBody.innerHTML = '<tr><td colspan="5" class="table-empty">No decision logs matched the filters.</td></tr>';
                return;
            }
            tableBody.innerHTML = filtered.map(function (r) {
                return '<tr><td>' + esc((r.code || '-') + ' - ' + (r.project_name || '')) + '</td><td>' + statusChip(String(r.decision_type || '').replace('_', ' ')) + '</td><td>' + esc(r.notes || '-') + '</td><td>' + esc(fmtDateTime(r.created_at)) + '</td><td>' + esc(r.approved_by || '-') + '</td></tr>';
            }).join('');
        }

        function load() {
            setFeedback('Loading decision logs...', false);
            apiGet('load_decision_logs').then(function (res) {
                if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load decision logs.');
                allRows = Array.isArray(res.json.data) ? res.json.data : [];
                applyLocalFilters();
                setFeedback('', false);
            }).catch(function (err) {
                tableBody.innerHTML = '<tr><td colspan="5" class="table-empty">Failed to load decision logs.</td></tr>';
                setFeedback(err.message || 'Unable to load decision logs.', true);
            });
        }

        applyBtn.addEventListener('click', applyLocalFilters);
        load();
    }

    function initReportsPage() {
        function setNum(id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = String(value == null ? 0 : value);
        }

        apiGet('load_reports_summary').then(function (res) {
            if (!res.ok || !res.json || res.json.success !== true) throw new Error((res.json && res.json.message) || 'Unable to load report summary.');
            var d = res.json.data || {};
            setNum('dhSummaryTotal', d.total_projects || 0);
            setNum('dhSummaryApproved', d.approved_projects || 0);
            setNum('dhSummaryOngoing', d.ongoing_projects || 0);
            setNum('dhSummaryDelayed', d.delayed_projects || 0);
        }).catch(function (err) {
            setFeedback(err.message || 'Unable to load report summary.', true);
        });

        document.querySelectorAll('.dept-report-card').forEach(function (card) {
            var type = card.getAttribute('data-report-type') || 'monthly';
            card.querySelectorAll('[data-export-format]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var format = this.getAttribute('data-export-format') || 'excel';
                    var url = '/department-head/api.php?action=export_report&report_type=' + encodeURIComponent(type) + '&format=' + encodeURIComponent(format);
                    window.open(url, '_blank');
                });
            });
        });
    }

    if (page === 'project-monitoring') initMonitoringPage();
    if (page === 'priority-control') initPriorityPage();
    if (page === 'risk-alerts') initRiskPage();
    if (page === 'decision-logs') initDecisionLogsPage();
    if (page === 'reports') initReportsPage();
})();
