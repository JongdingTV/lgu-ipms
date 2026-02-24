(function () {
        if (!location.pathname.endsWith('/registered_contractors.php') && !location.pathname.endsWith('/registered_engineers.php')) return;

        const contractorsTbody = document.querySelector('#contractorsTable tbody');
        const projectsTbody = document.querySelector('#projectsTable tbody');
        const searchInput = document.getElementById('searchContractors');
        const statusFilter = document.getElementById('filterStatus');
        const approvalFilter = document.getElementById('filterApproval');
        const countEl = document.getElementById('contractorsCount');
        const formMessage = document.getElementById('formMessage');
        const lastSyncEl = document.getElementById('contractorLastSync');
        const refreshBtn = document.getElementById('refreshContractorsBtn');
        const exportCsvBtn = document.getElementById('exportContractorsCsvBtn');
        const statTotalEl = document.getElementById('contractorStatTotal');
        const statActiveEl = document.getElementById('contractorStatActive');
        const statSuspendedEl = document.getElementById('contractorStatSuspended');
        const statBlacklistedEl = document.getElementById('contractorStatBlacklisted');
        const statAvgRatingEl = document.getElementById('contractorStatAvgRating');
        const approvalQueue = document.getElementById('approvalQueue');
        const approvalQueueAll = document.getElementById('approvalQueueAll');
        const approvalQueuePending = document.getElementById('approvalQueuePending');
        const approvalQueueVerified = document.getElementById('approvalQueueVerified');
        const approvalQueueApproved = document.getElementById('approvalQueueApproved');
        const approvalQueueRejected = document.getElementById('approvalQueueRejected');
        const approvalQueueSuspended = document.getElementById('approvalQueueSuspended');
        const topPerformingList = document.getElementById('topPerformingList');
        const highRiskList = document.getElementById('highRiskList');
        const mostDelayedList = document.getElementById('mostDelayedList');
        const recommendProjectSelect = document.getElementById('recommendProjectSelect');
        const recommendEngineerBtn = document.getElementById('recommendEngineerBtn');
        const recommendedEngineersList = document.getElementById('recommendedEngineersList');

        const assignmentModal = document.getElementById('assignmentModal');
        const projectsViewModal = document.getElementById('projectsViewModal');
        const assignmentTitle = document.getElementById('assignmentTitle');
        const projectsListEl = document.getElementById('projectsList');
        const projectsViewTitle = document.getElementById('projectsViewTitle');
        const projectsViewList = document.getElementById('projectsViewList');
        const contractorDocsModal = document.getElementById('contractorDocsModal');
        const contractorDocsTitle = document.getElementById('contractorDocsTitle');
        const contractorDocsList = document.getElementById('contractorDocsList');
        const contractorDocsCloseBtn = document.getElementById('contractorDocsCloseBtn');
        const contractorStatusModal = document.getElementById('contractorStatusModal');
        const contractorStatusTitle = document.getElementById('contractorStatusTitle');
        const statusContractorId = document.getElementById('statusContractorId');
        const statusSelect = document.getElementById('statusSelect');
        const statusNote = document.getElementById('statusNote');
        const statusCancelBtn = document.getElementById('statusCancelBtn');
        const statusSaveBtn = document.getElementById('statusSaveBtn');
        const approvalHistoryModal = document.getElementById('approvalHistoryModal');
        const approvalHistoryTitle = document.getElementById('approvalHistoryTitle');
        const approvalHistoryFilters = document.getElementById('approvalHistoryFilters');
        const approvalHistoryList = document.getElementById('approvalHistoryList');
        const approvalHistoryCloseBtn = document.getElementById('approvalHistoryCloseBtn');
        const approvalHistoryExportBtn = document.getElementById('approvalHistoryExportBtn');
        const assignContractorId = document.getElementById('assignContractorId');
        const saveAssignmentsBtn = document.getElementById('saveAssignments');
        const assignCancelBtn = document.getElementById('assignCancelBtn');
        const projectsCloseBtn = document.getElementById('projectsCloseBtn');

        const contractorDeleteModal = document.getElementById('contractorDeleteModal');
        const contractorDeleteName = document.getElementById('contractorDeleteName');
        const contractorDeleteCancel = document.getElementById('contractorDeleteCancel');
        const contractorDeleteConfirm = document.getElementById('contractorDeleteConfirm');
        const contractorsSection = document.querySelector('.contractors-section');

        let contractorsCache = [];
        let projectsCache = [];
        let visibleContractors = [];
        let currentAssignedIds = [];
        let currentDocsContractorId = null;
        let currentDocsContractorName = 'Engineer';
        let approvalHistoryCache = [];
        let approvalHistoryCurrentWindow = 'all';
        let contractorsMeta = { page: 1, per_page: 25, total: 0, total_pages: 1, has_next: false, has_prev: false };
        let contractorsStats = null;
        let contractorsQueryTimer = null;
        const contractorsPerPage = 25;
        let contractorsPaginationEl = null;

        function getCsrfToken() {
            const tokenInput = document.getElementById('registeredEngineersCsrfToken');
            return tokenInput ? String(tokenInput.value || '').trim() : '';
        }

        function esc(v) {
            return String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function projectPriorityClass(level) {
            const key = String(level || 'medium').toLowerCase();
            if (key === 'crucial') return 'critical';
            if (key === 'high') return 'high';
            if (key === 'low') return 'low';
            return 'medium';
        }

        function verificationBadgeClass(status) {
            const key = String(status || '').toLowerCase();
            if (key.includes('incomplete')) return 'is-incomplete';
            if (key.includes('complete')) return 'is-complete';
            if (key.includes('expired')) return 'is-expired';
            return 'is-incomplete';
        }

        function approvalBadgeClass(status) {
            const key = String(status || '').toLowerCase();
            if (key === 'approved') return 'is-complete';
            if (key === 'verified') return 'is-warning';
            if (key === 'rejected') return 'is-expired';
            if (key === 'suspended' || key === 'blacklisted' || key === 'inactive') return 'is-incomplete';
            return 'is-incomplete';
        }

        function approvalLabel(status) {
            const key = String(status || 'pending').toLowerCase();
            return key.charAt(0).toUpperCase() + key.slice(1);
        }

        function projectDetailCardMarkup(p, withCheckbox, assignedSet) {
            const pid = String(p.id || '');
            const checked = assignedSet && assignedSet.has(pid) ? 'checked' : '';
            const docs = Array.isArray(p.documents) ? p.documents : [];
            const locationText = p.location_exact || p.location || 'N/A';
            const fullAddress = p.full_address || [p.province || '', p.barangay || '', locationText].filter(Boolean).join(' / ');
            const priorityLevel = p.priority || 'Medium';
            const priorityPercent = Number(p.priority_percent || 0);
            const taskSummary = p.task_summary || { total: 0, completed: 0 };
            const milestoneSummary = p.milestone_summary || { total: 0, completed: 0 };
            const mapQuery = encodeURIComponent(locationText);
            const docList = docs.length
                ? docs.map((d) => '<a class="engineer-project-doc" href="/storage/' + esc(d) + '" target="_blank" rel="noopener">Document</a>').join(' ')
                : '<span class="engineer-project-muted">No attached documents</span>';

            return '<div class="engineer-project-card">'
                + (withCheckbox
                    ? '<label class="engineer-project-select"><input type="checkbox" class="project-checkbox" value="' + esc(pid) + '" ' + checked + '> <span>Select project</span></label>'
                    : '')
                + '<div class="engineer-project-head"><strong>' + esc((p.code || '') + ' - ' + (p.name || '')) + '</strong>'
                + '<span class="engineer-project-priority ' + projectPriorityClass(priorityLevel) + '">' + esc(priorityLevel) + (priorityPercent ? (' ' + esc(priorityPercent.toFixed(0)) + '%') : '') + '</span></div>'
                + '<div class="engineer-project-grid">'
                + '<div><span class="engineer-project-label">Type / Sector</span><span class="engineer-project-value">' + esc((p.type || 'N/A') + ' / ' + (p.sector || 'N/A')) + '</span></div>'
                + '<div><span class="engineer-project-label">Status</span><span class="engineer-project-value">' + esc(p.status || 'Draft') + '</span></div>'
                + '<div><span class="engineer-project-label">Location</span><span class="engineer-project-value">' + esc(locationText) + '</span><a class="engineer-project-link" href="https://maps.google.com/?q=' + mapQuery + '" target="_blank" rel="noopener">View Full Address/Map</a></div>'
                + '<div><span class="engineer-project-label">Dates</span><span class="engineer-project-value">' + esc((p.start_date || '-') + ' to ' + (p.end_date || '-')) + '</span></div>'
                + '<div><span class="engineer-project-label">Duration</span><span class="engineer-project-value">' + esc(String(p.duration_months || '-')) + ' month(s)</span></div>'
                + '<div><span class="engineer-project-label">Budget (Allocated / Spent)</span><span class="engineer-project-value">PHP ' + Number(p.allocated_budget || 0).toLocaleString() + ' / PHP ' + Number(p.spent_budget || 0).toLocaleString() + '</span></div>'
                + '<div><span class="engineer-project-label">Milestones</span><span class="engineer-project-value">' + Number(milestoneSummary.completed || 0) + ' / ' + Number(milestoneSummary.total || 0) + ' completed</span></div>'
                + '<div><span class="engineer-project-label">Tasks</span><span class="engineer-project-value">' + Number(taskSummary.completed || 0) + ' / ' + Number(taskSummary.total || 0) + ' completed</span></div>'
                + '<div class="engineer-project-full"><span class="engineer-project-label">Full Address</span><span class="engineer-project-value">' + esc(fullAddress) + '</span></div>'
                + '<div class="engineer-project-full"><span class="engineer-project-label">Attached Documents</span>' + docList + '</div>'
                + '</div></div>';
        }

        function apiCandidates(query) {
            const list = [
                'registered_engineers.php?' + query,
                '/admin/registered_engineers.php?' + query
            ];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_engineers.php?' + query));
            }
            return Array.from(new Set(list));
        }

        function postApiCandidates() {
            const list = ['registered_engineers.php', '/admin/registered_engineers.php'];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_engineers.php'));
            }
            return Array.from(new Set(list));
        }

        function sanitizeSnippet(text) {
            return String(text || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        }

        async function fetchJsonWithFallback(query) {
            const urls = apiCandidates(query);
            let lastErr = null;
            for (const url of urls) {
                try {
                    const res = await fetch(url, { credentials: 'same-origin' });
                    if (!res.ok) continue;
                    const text = await res.text();
                    const trimmed = String(text || '').trim();
                    if (!trimmed) {
                        lastErr = new Error('Empty API response from ' + url);
                        continue;
                    }
                    if (trimmed.startsWith('<')) {
                        lastErr = new Error('Non-JSON response from API (' + url + '): ' + sanitizeSnippet(trimmed));
                        continue;
                    }
                    const payload = JSON.parse(trimmed);
                    if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
                        if (payload.success === false) {
                            lastErr = new Error(payload.message || 'API request failed.');
                            continue;
                        }
                        if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
                            return payload.data;
                        }
                    }
                    return payload;
                } catch (err) {
                    lastErr = err;
                }
            }
            throw (lastErr || new Error('Unable to load data from API'));
        }

        async function postJsonWithFallback(formBody) {
            const urls = postApiCandidates();
            const bodyParams = new URLSearchParams(formBody || '');
            const csrfToken = getCsrfToken();
            if (csrfToken && !bodyParams.has('csrf_token')) {
                bodyParams.set('csrf_token', csrfToken);
            }
            const encodedBody = bodyParams.toString();
            let lastErr = null;
            for (const url of urls) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: encodedBody,
                        credentials: 'same-origin'
                    });
                    if (!res.ok) continue;
                    const text = await res.text();
                    const trimmed = String(text || '').trim();
                    if (!trimmed) {
                        lastErr = new Error('Empty API response from ' + url);
                        continue;
                    }
                    if (trimmed.startsWith('<')) {
                        lastErr = new Error('Non-JSON response from API (' + url + '): ' + sanitizeSnippet(trimmed));
                        continue;
                    }
                    const payload = JSON.parse(trimmed);
                    if (payload && typeof payload === 'object' && payload.success === false) {
                        lastErr = new Error(payload.message || 'API request failed.');
                        continue;
                    }
                    return payload;
                } catch (err) {
                    lastErr = err;
                }
            }
            throw (lastErr || new Error('Unable to save data to API'));
        }

        function ensureContractorsPagination() {
            if (contractorsPaginationEl || !contractorsSection) return;
            contractorsPaginationEl = document.createElement('div');
            contractorsPaginationEl.id = 'contractorsPagination';
            contractorsPaginationEl.className = 'engineers-pagination';
            const tableWrap = contractorsSection.querySelector('.table-wrap');
            if (tableWrap && tableWrap.parentNode) {
                tableWrap.parentNode.insertBefore(contractorsPaginationEl, tableWrap.nextSibling);
            } else {
                contractorsSection.appendChild(contractorsPaginationEl);
            }
        }

        function renderContractorsPagination(meta) {
            ensureContractorsPagination();
            if (!contractorsPaginationEl) return;
            const page = Number(meta?.page || 1);
            const totalPages = Number(meta?.total_pages || 1);
            const total = Number(meta?.total || 0);
            const hasPrev = !!meta?.has_prev;
            const hasNext = !!meta?.has_next;
            const start = total === 0 ? 0 : (((page - 1) * contractorsPerPage) + 1);
            const end = Math.min(total, page * contractorsPerPage);

            contractorsPaginationEl.innerHTML = `
                <div class="engineers-pagination-meta">Showing ${start}-${end} of ${total} Engineers</div>
                <div class="engineers-pagination-actions">
                    <button type="button" class="btn-contractor-secondary" data-page-nav="prev" ${hasPrev ? '' : 'disabled'}>Prev</button>
                    <span class="engineers-pagination-page">Page ${page} / ${Math.max(1, totalPages)}</span>
                    <button type="button" class="btn-contractor-secondary" data-page-nav="next" ${hasNext ? '' : 'disabled'}>Next</button>
                </div>
            `;
        }

        function renderContractors(rows) {
            if (!contractorsTbody) return;
            contractorsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];
            visibleContractors = list;

            const totalCount = Number(contractorsMeta?.total ?? list.length);
            if (countEl) countEl.textContent = `${totalCount} Engineer${totalCount === 1 ? '' : 's'}`;

            if (!list.length) {
                contractorsTbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:18px; color:#6b7280;">No Engineers found.</td></tr>';
                return;
            }

            for (const c of list) {
                const approvalStatus = approvalLabel(c.approval_status || 'pending');
                const approvalClass = approvalBadgeClass(c.approval_status || 'pending');
                const approvalKey = String(c.approval_status || '').toLowerCase();
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${esc(c.display_name || c.company || 'N/A')}</strong></td>
                    <td>${esc(c.license || 'N/A')}</td>
                    <td>${esc(c.email || c.phone || 'N/A')}</td>
                    <td><span class="status-badge ${esc(String(c.status || '').toLowerCase().replace(/\s+/g, '-'))}">${esc(c.status || 'N/A')}</span></td>
                    <td><span class="verification-badge ${approvalClass}">${esc(approvalStatus)}</span></td>
                    <td><span class="verification-badge ${verificationBadgeClass(c.verification_status)}">${esc(c.verification_status || 'Incomplete')}</span></td>
                    <td>${Number(c.performance_score || c.rating || 0).toFixed(1)} | ${esc(c.risk_level || 'Medium')}</td>
                    <td><button class="btn-view-projects" data-id="${esc(c.id)}">View Projects</button></td>
                    <td><button class="btn-contractor-secondary btn-docs" data-id="${esc(c.id)}">Documents</button></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-contractor-secondary btn-status-edit" data-id="${esc(c.id)}" data-status="${esc(approvalKey || 'pending')}" data-name="${esc(c.display_name || c.company || 'Engineer')}">Edit Status</button>
                            <button class="btn-contractor-secondary btn-history" data-id="${esc(c.id)}" data-name="${esc(c.display_name || c.company || 'Engineer')}">History</button>
                            <button class="btn-assign" data-id="${esc(c.id)}">Assign Projects</button>
                            <button class="btn-delete" data-id="${esc(c.id)}">Delete</button>
                        </div>
                    </td>
                `;
                contractorsTbody.appendChild(tr);
            }
        }

        function updateStats(rows, statsPayload) {
            const stats = statsPayload && typeof statsPayload === 'object' ? statsPayload : null;
            const list = Array.isArray(rows) ? rows : [];
            let active = 0;
            let suspended = 0;
            let blacklisted = 0;
            let ratingSum = 0;
            let ratingCount = 0;

            for (const c of list) {
                const status = String(c.status || '').toLowerCase();
                if (status === 'active') active += 1;
                if (status === 'suspended') suspended += 1;
                if (status === 'blacklisted') blacklisted += 1;
                const r = Number(c.rating);
                if (Number.isFinite(r) && r > 0) {
                    ratingSum += r;
                    ratingCount += 1;
                }
            }

            const totalCount = Number(stats?.total ?? list.length);
            const activeCount = Number(stats?.active ?? active);
            const suspendedCount = Number(stats?.suspended ?? suspended);
            const blacklistedCount = Number(stats?.blacklisted ?? blacklisted);
            const avgRating = stats?.avg_rating !== undefined
                ? Number(stats.avg_rating || 0).toFixed(1)
                : (ratingCount ? (ratingSum / ratingCount).toFixed(1) : '0.0');

            if (statTotalEl) statTotalEl.textContent = String(totalCount);
            if (statActiveEl) statActiveEl.textContent = String(activeCount);
            if (statSuspendedEl) statSuspendedEl.textContent = String(suspendedCount);
            if (statBlacklistedEl) statBlacklistedEl.textContent = String(blacklistedCount);
            if (statAvgRatingEl) statAvgRatingEl.textContent = avgRating;

            const approvalCounts = {
                all: totalCount,
                pending: 0,
                verified: 0,
                approved: 0,
                rejected: 0,
                suspended: 0
            };
            if (stats?.approval_counts) {
                approvalCounts.pending = Number(stats.approval_counts.pending || 0);
                approvalCounts.verified = Number(stats.approval_counts.verified || 0);
                approvalCounts.approved = Number(stats.approval_counts.approved || 0);
                approvalCounts.rejected = Number(stats.approval_counts.rejected || 0);
                approvalCounts.suspended = Number(stats.approval_counts.suspended || 0);
            } else {
                for (const c of list) {
                    const key = String(c.approval_status || 'pending').toLowerCase();
                    if (Object.prototype.hasOwnProperty.call(approvalCounts, key)) {
                        approvalCounts[key] += 1;
                    }
                }
            }
            if (approvalQueueAll) approvalQueueAll.textContent = String(approvalCounts.all);
            if (approvalQueuePending) approvalQueuePending.textContent = String(approvalCounts.pending);
            if (approvalQueueVerified) approvalQueueVerified.textContent = String(approvalCounts.verified);
            if (approvalQueueApproved) approvalQueueApproved.textContent = String(approvalCounts.approved);
            if (approvalQueueRejected) approvalQueueRejected.textContent = String(approvalCounts.rejected);
            if (approvalQueueSuspended) approvalQueueSuspended.textContent = String(approvalCounts.suspended);
        }

        function updateLastSync() {
            if (!lastSyncEl) return;
            const now = new Date();
            const stamp = now.toLocaleString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
            lastSyncEl.textContent = `Last synced: ${stamp}`;
        }

        function exportVisibleContractorsCsv() {
            const list = Array.isArray(visibleContractors) ? visibleContractors : [];
            if (!list.length) {
                setMessage('No Engineers to export.', true);
                return;
            }
            const escCsv = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
            const header = ['Company', 'License', 'Email', 'Phone', 'Status', 'Rating'];
            const rows = list.map((c) => [
                c.company || '',
                c.license || '',
                c.email || '',
                c.phone || '',
                c.status || '',
                c.rating || ''
            ]);
            const csv = [header, ...rows].map((r) => r.map(escCsv).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `Engineers-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
        }

        function renderProjects(rows) {
            if (!projectsTbody) return;
            projectsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];
            if (!list.length) {
                projectsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px; color:#6b7280;">No projects available.</td></tr>';
                return;
            }

            for (const p of list) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${esc(p.code || '')}</td>
                    <td>${esc(p.name || '')}</td>
                    <td>${esc(p.type || '')}</td>
                    <td>${esc(p.sector || '')}</td>
                    <td><span class="status-badge ${esc(String(p.status || '').toLowerCase().replace(/\s+/g, '-'))}">${esc(p.status || 'N/A')}</span></td>
                `;
                projectsTbody.appendChild(tr);
            }

            if (recommendProjectSelect) {
                const options = ['<option value=\"\">Select a project</option>'].concat(
                    list.map((p) => `<option value=\"${esc(p.id)}\">${esc((p.code || 'N/A') + ' - ' + (p.name || 'Untitled'))}</option>`)
                );
                recommendProjectSelect.innerHTML = options.join('');
            }
        }

        function fillEvalList(el, rows, mapper) {
            if (!el) return;
            const list = Array.isArray(rows) ? rows : [];
            if (!list.length) {
                el.innerHTML = '<li>No data yet.</li>';
                return;
            }
            el.innerHTML = list.map(mapper).join('');
        }

        async function loadEvaluationOverview() {
            try {
                const overview = await fetchJsonWithFallback('action=load_evaluation_overview&_=' + Date.now());
                fillEvalList(topPerformingList, overview.top_performing, (r) => `<li><strong>${esc(r.display_name || 'Engineer')}</strong> - Score ${Number(r.performance_rating || 0).toFixed(1)}</li>`);
                fillEvalList(highRiskList, overview.high_risk, (r) => `<li><strong>${esc(r.display_name || 'Engineer')}</strong> - ${esc(r.risk_level || 'High')} (${Number(r.risk_score || 0).toFixed(1)})</li>`);
                fillEvalList(mostDelayedList, overview.most_delayed, (r) => `<li><strong>${esc(r.display_name || 'Engineer')}</strong> - Delayed ${Number(r.delayed_project_count || 0)} / ${Number(r.past_project_count || 0)} projects</li>`);
            } catch (_) {
                fillEvalList(topPerformingList, [], () => '');
                fillEvalList(highRiskList, [], () => '');
                fillEvalList(mostDelayedList, [], () => '');
            }
        }

        async function loadRecommendedEngineers() {
            if (!recommendedEngineersList) return;
            const projectId = recommendProjectSelect?.value || '';
            if (!projectId) {
                recommendedEngineersList.innerHTML = '<li>Select a project to load recommendations.</li>';
                return;
            }

            recommendedEngineersList.innerHTML = '<li>Loading recommendations...</li>';
            try {
                const rows = await fetchJsonWithFallback(`action=recommended_engineers&project_id=${encodeURIComponent(projectId)}&_=${Date.now()}`);
                fillEvalList(recommendedEngineersList, rows, (r) => `<li><strong>${esc(r.display_name || 'Engineer')}</strong> - ${esc(r.specialization || 'General')} | Perf ${Number(r.performance_rating || 0).toFixed(1)} | Risk ${esc(r.risk_level || 'Medium')}</li>`);
            } catch (_) {
                recommendedEngineersList.innerHTML = '<li>Unable to load recommendations.</li>';
            }
        }

        function setMessage(text, isError) {
            if (!formMessage) return;
            formMessage.style.display = 'block';
            formMessage.style.color = isError ? '#c00' : '#166534';
            formMessage.textContent = text;
            setTimeout(() => { formMessage.style.display = 'none'; }, 3000);
        }

        function openAssignModal(contractorId, contractorName) {
            if (!assignmentModal) return;
            assignContractorId.value = contractorId;
            assignmentTitle.textContent = `Assign "${contractorName}" to Projects`;
            projectsListEl.innerHTML = '<p class="engineer-modal-message">Loading projects...</p>';
            assignmentModal.style.display = 'flex';
            loadProjectsForAssignment(contractorId);
        }

        function closeAssignModal() {
            if (assignmentModal) assignmentModal.style.display = 'none';
        }

        function openProjectsModal(contractorId, contractorName) {
            if (!projectsViewModal) return;
            projectsViewTitle.textContent = `Projects Assigned to ${contractorName}`;
            projectsViewList.innerHTML = '<p class="engineer-modal-message">Loading projects...</p>';
            projectsViewModal.style.display = 'flex';

            fetchJsonWithFallback(`action=get_assigned_projects&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`)
                .then((rows) => {
                    const list = Array.isArray(rows) ? rows : [];
                    if (!list.length) {
                        projectsViewList.innerHTML = '<p class="engineer-modal-message">No projects assigned.</p>';
                        return;
                    }
                    projectsViewList.innerHTML = list.map((p) => projectDetailCardMarkup(p, false)).join('');
                })
                .catch(() => {
                    projectsViewList.innerHTML = '<p class="engineer-modal-message error">Failed to load assigned projects.</p>';
                });
        }

        function closeProjectsModal() {
            if (projectsViewModal) projectsViewModal.style.display = 'none';
        }

        function closeDocsModal() {
            if (contractorDocsModal) contractorDocsModal.style.display = 'none';
            currentDocsContractorId = null;
        }

        function openStatusModal(contractorId, contractorName, currentStatus) {
            if (!contractorStatusModal || !statusContractorId || !statusSelect) return;
            statusContractorId.value = String(contractorId || '');
            statusSelect.value = String(currentStatus || 'pending').toLowerCase();
            if (statusNote) statusNote.value = '';
            if (contractorStatusTitle) {
                contractorStatusTitle.textContent = `Update Status - ${contractorName || 'Engineer'}`;
            }
            contractorStatusModal.style.display = 'flex';
        }

        function closeStatusModal() {
            if (contractorStatusModal) contractorStatusModal.style.display = 'none';
        }

        function historyStatusClass(status) {
            const key = String(status || '').toLowerCase();
            if (key === 'approved') return 'is-complete';
            if (key === 'verified') return 'is-warning';
            if (key === 'rejected') return 'is-expired';
            if (key === 'suspended') return 'is-incomplete';
            return 'is-incomplete';
        }

        async function openApprovalHistoryModal(contractorId, contractorName) {
            if (!approvalHistoryModal || !approvalHistoryList) return;
            if (approvalHistoryTitle) {
                approvalHistoryTitle.textContent = `Approval Timeline - ${contractorName || 'Engineer'}`;
            }
            approvalHistoryCache = [];
            approvalHistoryCurrentWindow = 'all';
            approvalHistoryFilters?.querySelectorAll('.approval-history-filter').forEach((btn) => {
                btn.classList.toggle('active', btn.getAttribute('data-history-window') === 'all');
            });
            approvalHistoryList.innerHTML = '<p class="engineer-modal-message">Loading timeline...</p>';
            approvalHistoryModal.style.display = 'flex';
            try {
                const rows = await fetchJsonWithFallback(`action=load_approval_history&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`);
                approvalHistoryCache = Array.isArray(rows) ? rows : [];
                renderApprovalHistory('all');
            } catch (_) {
                approvalHistoryList.innerHTML = '<p class="engineer-modal-message error">Unable to load approval history.</p>';
            }
        }

        function getFilteredApprovalHistory(windowKey) {
            const source = Array.isArray(approvalHistoryCache) ? approvalHistoryCache : [];
            const days = Number(windowKey);
            if (!(Number.isFinite(days) && days > 0)) {
                return source;
            }
            const cutoff = Date.now() - (days * 24 * 60 * 60 * 1000);
            return source.filter((r) => {
                const ts = Date.parse(String(r.changed_at || ''));
                return Number.isFinite(ts) && ts >= cutoff;
            });
        }

        function renderApprovalHistory(windowKey) {
            if (!approvalHistoryList) return;
            approvalHistoryCurrentWindow = windowKey || 'all';
            const list = getFilteredApprovalHistory(approvalHistoryCurrentWindow);
            if (!list.length) {
                approvalHistoryList.innerHTML = '<p class="engineer-modal-message">No approval history for selected range.</p>';
                return;
            }
            approvalHistoryList.innerHTML = list.map((r) => `
                <article class="approval-history-item">
                    <div class="approval-history-head">
                        <span class="verification-badge ${historyStatusClass(r.status)}">${esc(approvalLabel(r.status || 'pending'))}</span>
                        <time>${esc(r.changed_at || '-')}</time>
                    </div>
                    <p class="approval-history-meta">By: <strong>${esc(r.reviewer_name || 'System')}</strong> (${esc(r.reviewer_role || 'n/a')})</p>
                    <p class="approval-history-note">${esc(r.notes || 'No note provided.')}</p>
                </article>
            `).join('');
        }

        function closeApprovalHistoryModal() {
            if (approvalHistoryModal) approvalHistoryModal.style.display = 'none';
        }

        function exportApprovalHistoryCsv() {
            const list = getFilteredApprovalHistory(approvalHistoryCurrentWindow);
            if (!list.length) {
                setMessage('No approval history to export.', true);
                return;
            }
            const escCsv = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
            const rows = [
                ['Status', 'Reviewer', 'Role', 'Changed At', 'Note'],
                ...list.map((r) => [
                    approvalLabel(r.status || 'pending'),
                    r.reviewer_name || 'System',
                    r.reviewer_role || 'n/a',
                    r.changed_at || '',
                    r.notes || ''
                ])
            ];
            const csv = rows.map((r) => r.map(escCsv).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const rangeLabel = approvalHistoryCurrentWindow === 'all' ? 'all' : `${approvalHistoryCurrentWindow}d`;
            a.href = url;
            a.download = `approval-timeline-${rangeLabel}-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        async function openDocsModal(contractorId, contractorName) {
            if (!contractorDocsModal || !contractorDocsList || !contractorDocsTitle) return;
            currentDocsContractorId = String(contractorId);
            currentDocsContractorName = contractorName || currentDocsContractorName || 'Engineer';
            contractorDocsTitle.textContent = `Engineer Documents - ${currentDocsContractorName}`;
            contractorDocsList.innerHTML = '<p class="engineer-modal-message">Loading documents...</p>';
            contractorDocsModal.style.display = 'flex';

            try {
                const docs = await fetchJsonWithFallback(`action=load_contractor_documents&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`);
                if (!Array.isArray(docs) || docs.length === 0) {
                    contractorDocsList.innerHTML = '<p class="engineer-modal-message">No uploaded documents found.</p>';
                    return;
                }
                contractorDocsList.innerHTML = docs.map((d) => {
                    const verified = Number(d.is_verified || 0) === 1;
                    return `<div class="engineer-project-card">
                        <div class="engineer-project-head">
                            <strong>${esc(String(d.document_type || 'document').toUpperCase())}</strong>
                            <span class="engineer-project-priority ${verified ? 'low' : 'high'}">${verified ? 'Verified' : 'Pending Verification'}</span>
                        </div>
                        <div class="engineer-project-grid">
                            <div><span class="engineer-project-label">Original Name</span><span class="engineer-project-value">${esc(d.original_name || '-')}</span></div>
                            <div><span class="engineer-project-label">Uploaded</span><span class="engineer-project-value">${esc(d.uploaded_at || '-')}</span></div>
                            <div><span class="engineer-project-label">Expires On</span><span class="engineer-project-value">${esc(d.expires_on || '-')}</span></div>
                            <div><span class="engineer-project-label">Size</span><span class="engineer-project-value">${Number(d.file_size || 0).toLocaleString()} bytes</span></div>
                            <div class="engineer-project-full">
                                <a class="engineer-project-doc" href="${esc(d.viewer_url || '#')}" target="_blank" rel="noopener">View Document</a>
                                <button type="button" class="btn-contractor-secondary btn-verify-doc" data-doc-id="${esc(d.id)}" data-verify="${verified ? '0' : '1'}">${verified ? 'Mark Unverified' : 'Verify Document'}</button>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            } catch (_) {
                contractorDocsList.innerHTML = '<p class="engineer-modal-message error">Failed to load documents.</p>';
            }
        }

        function closeDeleteModal() {
            if (contractorDeleteModal) contractorDeleteModal.style.display = 'none';
        }

        function confirmDeleteContractor(contractorName) {
            return new Promise((resolve) => {
                if (!contractorDeleteModal) {
                    resolve(window.confirm(`Delete ${contractorName}? This cannot be undone.`));
                    return;
                }
                contractorDeleteName.textContent = contractorName || 'Selected Engineer';
                contractorDeleteModal.style.display = 'flex';

                const cancel = () => {
                    closeDeleteModal();
                    contractorDeleteCancel?.removeEventListener('click', onCancel);
                    contractorDeleteConfirm?.removeEventListener('click', onConfirm);
                    contractorDeleteModal?.removeEventListener('click', onBackdrop);
                    resolve(false);
                };
                const confirm = () => {
                    closeDeleteModal();
                    contractorDeleteCancel?.removeEventListener('click', onCancel);
                    contractorDeleteConfirm?.removeEventListener('click', onConfirm);
                    contractorDeleteModal?.removeEventListener('click', onBackdrop);
                    resolve(true);
                };
                const onCancel = () => cancel();
                const onConfirm = () => confirm();
                const onBackdrop = (e) => { if (e.target === contractorDeleteModal) cancel(); };

                contractorDeleteCancel?.addEventListener('click', onCancel);
                contractorDeleteConfirm?.addEventListener('click', onConfirm);
                contractorDeleteModal?.addEventListener('click', onBackdrop);
            });
        }

        async function loadProjectsForAssignment(contractorId) {
            try {
                const [assigned, allProjects] = await Promise.all([
                    fetchJsonWithFallback(`action=get_assigned_projects&contractor_id=${encodeURIComponent(contractorId)}&_=${Date.now()}`),
                    fetchJsonWithFallback(`action=load_projects&_=${Date.now()}`)
                ]);
                const assignedSet = new Set((Array.isArray(assigned) ? assigned : []).map((p) => String(p.id)));
                currentAssignedIds = Array.from(assignedSet);
                projectsCache = Array.isArray(allProjects) ? allProjects : [];

                if (!projectsCache.length) {
                    projectsListEl.innerHTML = '<p class="engineer-modal-message">No projects available.</p>';
                    return;
                }

                projectsListEl.innerHTML = projectsCache.map((p) => projectDetailCardMarkup(p, true, assignedSet)).join('');
            } catch (_) {
                projectsListEl.innerHTML = '<p class="engineer-modal-message error">Failed to load projects for assignment.</p>';
            }
        }

        async function saveAssignmentsHandler() {
            const contractorId = assignContractorId?.value;
            if (!contractorId) return;
            if (!saveAssignmentsBtn) return;

            saveAssignmentsBtn.disabled = true;
            saveAssignmentsBtn.textContent = 'Saving...';

            try {
                const checkedNow = Array.from(document.querySelectorAll('.project-checkbox:checked')).map((el) => String(el.value));
                const prevSet = new Set(currentAssignedIds);
                const nextSet = new Set(checkedNow);
                const toAssign = checkedNow.filter((id) => !prevSet.has(id));
                const toUnassign = currentAssignedIds.filter((id) => !nextSet.has(id));

                for (const id of toAssign) {
                    await postJsonWithFallback(`action=assign_contractor&contractor_id=${encodeURIComponent(contractorId)}&project_id=${encodeURIComponent(id)}`);
                }
                for (const id of toUnassign) {
                    await postJsonWithFallback(`action=unassign_contractor&contractor_id=${encodeURIComponent(contractorId)}&project_id=${encodeURIComponent(id)}`);
                }

                closeAssignModal();
                setMessage('Assignments updated successfully.', false);
            } catch (e) {
                setMessage(e.message || 'Failed to update assignments.', true);
            } finally {
                saveAssignmentsBtn.disabled = false;
                saveAssignmentsBtn.textContent = 'Save Assignments';
            }
        }

        async function loadContractorsData(page = 1) {
            const q = (searchInput?.value || '').trim();
            const s = (statusFilter?.value || '').trim();
            const a = (approvalFilter?.value || '').trim().toLowerCase();
            if (approvalQueue) {
                approvalQueue.querySelectorAll('.approval-queue-item').forEach((item) => {
                    const itemFilter = String(item.getAttribute('data-approval-filter') || '').toLowerCase();
                    item.classList.toggle('active', itemFilter === a);
                });
            }
            const query = `action=load_contractors&page=${encodeURIComponent(page)}&per_page=${contractorsPerPage}&q=${encodeURIComponent(q)}&status=${encodeURIComponent(s)}&approval=${encodeURIComponent(a)}&_=${Date.now()}`;
            const payload = await fetchJsonWithFallback(query);
            if (payload && !Array.isArray(payload) && payload.success === false) {
                throw new Error(payload.message || 'Failed to load Engineers data.');
            }
            const rows = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
            contractorsCache = rows;
            contractorsStats = payload?.stats || null;
            contractorsMeta = payload?.meta || { page: 1, per_page: contractorsPerPage, total: rows.length, total_pages: 1, has_next: false, has_prev: false };
            renderContractors(contractorsCache);
            updateStats(contractorsCache, contractorsStats);
            renderContractorsPagination(contractorsMeta);
        }

        function applyFilters() {
            if (contractorsQueryTimer) {
                clearTimeout(contractorsQueryTimer);
            }
            contractorsQueryTimer = setTimeout(() => {
                loadContractorsData(1).catch((err) => {
                    setMessage(err.message || 'Failed to load Engineers data.', true);
                });
            }, 220);
        }

        let booted = false;
        async function loadAllData() {
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing...';
            }
            try {
                const [projects] = await Promise.all([
                    fetchJsonWithFallback('action=load_projects&limit=200&_=' + Date.now())
                ]);
                await loadContractorsData(1);
                projectsCache = Array.isArray(projects) ? projects : [];
                updateLastSync();
                renderProjects(projectsCache);
                await loadEvaluationOverview();
            } catch (err) {
                if (contractorsTbody) contractorsTbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:18px; color:#c00;">Failed to load Engineers data.</td></tr>';
                if (projectsTbody) projectsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:18px; color:#c00;">Failed to load projects data.</td></tr>';
                if (formMessage) {
                    formMessage.style.display = 'block';
                    formMessage.style.color = '#c00';
                    formMessage.textContent = err.message || 'Failed to load data.';
                }
            } finally {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = 'Refresh';
                }
            }
        }

        async function boot() {
            if (booted) return;
            booted = true;
            await loadAllData();
        }

        searchInput?.addEventListener('input', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);
        approvalFilter?.addEventListener('change', applyFilters);
        approvalQueue?.addEventListener('click', (e) => {
            const button = e.target.closest('[data-approval-filter]');
            if (!button || !approvalFilter) return;
            const nextFilter = button.getAttribute('data-approval-filter') || '';
            approvalFilter.value = nextFilter;
            approvalQueue.querySelectorAll('.approval-queue-item').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            applyFilters();
        });
        contractorsTbody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const contractorId = btn.getAttribute('data-id');
            const row = btn.closest('tr');
            const contractorName = row ? row.querySelector('td:first-child')?.textContent.trim() : 'Engineer';
            if (!contractorId) return;

            if (btn.classList.contains('btn-status-edit')) {
                const currentStatus = btn.getAttribute('data-status') || 'pending';
                const buttonName = btn.getAttribute('data-name') || contractorName || 'Engineer';
                openStatusModal(contractorId, buttonName, currentStatus);
                return;
            }
            if (btn.classList.contains('btn-history')) {
                const buttonName = btn.getAttribute('data-name') || contractorName || 'Engineer';
                openApprovalHistoryModal(contractorId, buttonName);
                return;
            }

            if (btn.classList.contains('btn-view-projects')) {
                openProjectsModal(contractorId, contractorName || 'Engineer');
                return;
            }
            if (btn.classList.contains('btn-docs')) {
                openDocsModal(contractorId, contractorName || 'Engineer');
                return;
            }
            if (btn.classList.contains('btn-assign')) {
                openAssignModal(contractorId, contractorName || 'Engineer');
                return;
            }
            if (btn.classList.contains('btn-delete')) {
                const proceed = await confirmDeleteContractor(contractorName);
                if (!proceed) return;

                try {
                    const result = await postJsonWithFallback(`action=delete_contractor&id=${encodeURIComponent(contractorId)}`);
                    if (!result || result.success === false) throw new Error((result && result.message) || 'Delete failed');
                    await loadContractorsData(1);
                    setMessage('Engineer deleted successfully.', false);
                } catch (err) {
                    setMessage(err.message || 'Failed to delete Engineer.', true);
                }
            }
        });

        saveAssignmentsBtn?.addEventListener('click', saveAssignmentsHandler);
        refreshBtn?.addEventListener('click', loadAllData);
        exportCsvBtn?.addEventListener('click', exportVisibleContractorsCsv);
        recommendEngineerBtn?.addEventListener('click', loadRecommendedEngineers);
        contractorDocsCloseBtn?.addEventListener('click', closeDocsModal);
        assignCancelBtn?.addEventListener('click', closeAssignModal);
        projectsCloseBtn?.addEventListener('click', closeProjectsModal);
        assignmentModal?.addEventListener('click', (e) => { if (e.target === assignmentModal) closeAssignModal(); });
        projectsViewModal?.addEventListener('click', (e) => { if (e.target === projectsViewModal) closeProjectsModal(); });
        contractorDocsModal?.addEventListener('click', (e) => { if (e.target === contractorDocsModal) closeDocsModal(); });
        contractorDocsList?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-verify-doc');
            if (!btn || !currentDocsContractorId) return;
            const docId = btn.getAttribute('data-doc-id');
            const verifyValue = btn.getAttribute('data-verify') || '1';
            if (!docId) return;
            try {
                const resp = await postJsonWithFallback(`action=verify_contractor_document&document_id=${encodeURIComponent(docId)}&is_verified=${encodeURIComponent(verifyValue)}`);
                if (!resp || resp.success === false) throw new Error((resp && resp.message) || 'Unable to update document status.');
                await openDocsModal(currentDocsContractorId, currentDocsContractorName);
            } catch (err) {
                setMessage(err.message || 'Failed to update document verification.', true);
            }
        });

        statusCancelBtn?.addEventListener('click', closeStatusModal);
        contractorStatusModal?.addEventListener('click', (e) => {
            if (e.target === contractorStatusModal) closeStatusModal();
        });
        approvalHistoryCloseBtn?.addEventListener('click', closeApprovalHistoryModal);
        approvalHistoryModal?.addEventListener('click', (e) => {
            if (e.target === approvalHistoryModal) closeApprovalHistoryModal();
        });
        approvalHistoryFilters?.addEventListener('click', (e) => {
            const btn = e.target.closest('.approval-history-filter');
            if (!btn) return;
            const windowKey = btn.getAttribute('data-history-window') || 'all';
            approvalHistoryFilters.querySelectorAll('.approval-history-filter').forEach((item) => item.classList.remove('active'));
            btn.classList.add('active');
            renderApprovalHistory(windowKey);
        });
        approvalHistoryExportBtn?.addEventListener('click', exportApprovalHistoryCsv);
        statusSaveBtn?.addEventListener('click', async () => {
            const contractorId = statusContractorId?.value || '';
            const nextStatus = statusSelect?.value || '';
            const note = statusNote?.value?.trim() || '';
            if (!contractorId || !nextStatus) return;
            statusSaveBtn.disabled = true;
            const oldText = statusSaveBtn.textContent;
            statusSaveBtn.textContent = 'Saving...';
            try {
                const result = await postJsonWithFallback(`action=update_contractor_approval&contractor_id=${encodeURIComponent(contractorId)}&status=${encodeURIComponent(nextStatus)}&note=${encodeURIComponent(note)}`);
                if (!result || result.success === false) throw new Error((result && result.message) || 'Approval update failed');
                await loadContractorsData(Number(contractorsMeta.page || 1));
                closeStatusModal();
                setMessage(`Engineer status updated to ${nextStatus}.`, false);
            } catch (err) {
                setMessage(err.message || 'Failed to update approval status.', true);
            } finally {
                statusSaveBtn.disabled = false;
                statusSaveBtn.textContent = oldText;
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            closeAssignModal();
            closeProjectsModal();
            closeDocsModal();
            closeDeleteModal();
        });

        window.closeAssignModal = closeAssignModal;
        window.closeProjectsModal = closeProjectsModal;
        window.saveAssignmentsHandler = saveAssignmentsHandler;
        contractorsSection?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-page-nav]');
            if (!btn) return;
            const nav = btn.getAttribute('data-page-nav');
            const currentPage = Number(contractorsMeta.page || 1);
            if (nav === 'prev' && contractorsMeta.has_prev) {
                loadContractorsData(currentPage - 1).catch((err) => setMessage(err.message || 'Failed to load page.', true));
            } else if (nav === 'next' && contractorsMeta.has_next) {
                loadContractorsData(currentPage + 1).catch((err) => setMessage(err.message || 'Failed to load page.', true));
            }
        });

        document.addEventListener('DOMContentLoaded', boot);
        if (document.readyState !== 'loading') boot();
    })();

