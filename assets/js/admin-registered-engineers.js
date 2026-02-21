(function () {
        if (!location.pathname.endsWith('/registered_contractors.php')) return;

        const contractorsTbody = document.querySelector('#contractorsTable tbody');
        const projectsTbody = document.querySelector('#projectsTable tbody');
        const searchInput = document.getElementById('searchContractors');
        const statusFilter = document.getElementById('filterStatus');
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
        const assignContractorId = document.getElementById('assignContractorId');
        const saveAssignmentsBtn = document.getElementById('saveAssignments');
        const assignCancelBtn = document.getElementById('assignCancelBtn');
        const projectsCloseBtn = document.getElementById('projectsCloseBtn');

        const contractorDeleteModal = document.getElementById('contractorDeleteModal');
        const contractorDeleteName = document.getElementById('contractorDeleteName');
        const contractorDeleteCancel = document.getElementById('contractorDeleteCancel');
        const contractorDeleteConfirm = document.getElementById('contractorDeleteConfirm');

        let contractorsCache = [];
        let projectsCache = [];
        let visibleContractors = [];
        let currentAssignedIds = [];

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
            const list = ['registered_contractors.php?' + query, '/admin/registered_contractors.php?' + query];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_contractors.php?' + query));
            }
            return Array.from(new Set(list));
        }

        function postApiCandidates() {
            const list = ['registered_contractors.php', '/admin/registered_contractors.php'];
            if (typeof window.getApiUrl === 'function') {
                list.unshift(window.getApiUrl('admin/registered_contractors.php'));
            }
            return Array.from(new Set(list));
        }

        async function fetchJsonWithFallback(query) {
            const urls = apiCandidates(query);
            for (const url of urls) {
                try {
                    const res = await fetch(url, { credentials: 'same-origin' });
                    if (!res.ok) continue;
                    const text = await res.text();
                    return JSON.parse(text);
                } catch (_) {}
            }
            throw new Error('Unable to load data from API');
        }

        async function postJsonWithFallback(formBody) {
            const urls = postApiCandidates();
            for (const url of urls) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formBody,
                        credentials: 'same-origin'
                    });
                    if (!res.ok) continue;
                    return await res.json();
                } catch (_) {}
            }
            throw new Error('Unable to save data to API');
        }

        function renderContractors(rows) {
            if (!contractorsTbody) return;
            contractorsTbody.innerHTML = '';
            const list = Array.isArray(rows) ? rows : [];
            visibleContractors = list;

            if (countEl) countEl.textContent = `${list.length} Engineer${list.length === 1 ? '' : 's'}`;

            if (!list.length) {
                contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#6b7280;">No Engineers found.</td></tr>';
                return;
            }

            for (const c of list) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${esc(c.display_name || c.company || 'N/A')}</strong></td>
                    <td>${esc(c.license || 'N/A')}</td>
                    <td>${esc(c.email || c.phone || 'N/A')}</td>
                    <td><span class="status-badge ${esc(String(c.status || '').toLowerCase().replace(/\s+/g, '-'))}">${esc(c.status || 'N/A')}</span></td>
                    <td>${Number(c.performance_score || c.rating || 0).toFixed(1)} | ${esc(c.risk_level || 'Medium')}</td>
                    <td><button class="btn-view-projects" data-id="${esc(c.id)}">View Projects</button></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-assign" data-id="${esc(c.id)}">Assign Projects</button>
                            <button class="btn-delete" data-id="${esc(c.id)}">Delete</button>
                        </div>
                    </td>
                `;
                contractorsTbody.appendChild(tr);
            }
        }

        function updateStats(rows) {
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

            if (statTotalEl) statTotalEl.textContent = String(list.length);
            if (statActiveEl) statActiveEl.textContent = String(active);
            if (statSuspendedEl) statSuspendedEl.textContent = String(suspended);
            if (statBlacklistedEl) statBlacklistedEl.textContent = String(blacklisted);
            if (statAvgRatingEl) statAvgRatingEl.textContent = ratingCount ? (ratingSum / ratingCount).toFixed(1) : '0.0';
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

        function applyFilters() {
            const q = (searchInput?.value || '').trim().toLowerCase();
            const s = (statusFilter?.value || '').trim();
            const filtered = contractorsCache.filter((c) => {
                const hitSearch = !q || `${c.company || ''} ${c.license || ''} ${c.email || ''} ${c.phone || ''}`.toLowerCase().includes(q);
                const hitStatus = !s || String(c.status || '') === s;
                return hitSearch && hitStatus;
            });
            renderContractors(filtered);
        }

        let booted = false;
        async function loadAllData() {
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing...';
            }
            try {
                const [Engineers, projects] = await Promise.all([
                    fetchJsonWithFallback('action=load_contractors&_=' + Date.now()),
                    fetchJsonWithFallback('action=load_projects&_=' + Date.now())
                ]);
                contractorsCache = Array.isArray(Engineers) ? Engineers : [];
                projectsCache = Array.isArray(projects) ? projects : [];
                updateStats(contractorsCache);
                updateLastSync();
                renderContractors(contractorsCache);
                renderProjects(projectsCache);
                await loadEvaluationOverview();
            } catch (err) {
                if (contractorsTbody) contractorsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:18px; color:#c00;">Failed to load Engineers data.</td></tr>';
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
        contractorsTbody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const contractorId = btn.getAttribute('data-id');
            const row = btn.closest('tr');
            const contractorName = row ? row.querySelector('td:first-child')?.textContent.trim() : 'Engineer';
            if (!contractorId) return;

            if (btn.classList.contains('btn-view-projects')) {
                openProjectsModal(contractorId, contractorName || 'Engineer');
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
                    contractorsCache = contractorsCache.filter((c) => String(c.id) !== String(contractorId));
                    updateStats(contractorsCache);
                    applyFilters();
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
        assignCancelBtn?.addEventListener('click', closeAssignModal);
        projectsCloseBtn?.addEventListener('click', closeProjectsModal);
        assignmentModal?.addEventListener('click', (e) => { if (e.target === assignmentModal) closeAssignModal(); });
        projectsViewModal?.addEventListener('click', (e) => { if (e.target === projectsViewModal) closeProjectsModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            closeAssignModal();
            closeProjectsModal();
            closeDeleteModal();
        });

        window.closeAssignModal = closeAssignModal;
        window.closeProjectsModal = closeProjectsModal;
        window.saveAssignmentsHandler = saveAssignmentsHandler;

        document.addEventListener('DOMContentLoaded', boot);
        if (document.readyState !== 'loading') boot();
    })();



