(function () {
        const table = document.getElementById('projectsTable');
        const tbody = table ? table.querySelector('tbody') : null;
        const msg = document.getElementById('formMessage');
        const searchInput = document.getElementById('searchProjects');
        const statusFilter = document.getElementById('filterStatus');
        const exportCsvBtn = document.getElementById('exportCsv');
        const tableWrap = document.querySelector('.table-wrap');

        const editModal = document.getElementById('editProjectModal');
        const editForm = document.getElementById('editProjectForm');
        const editSaveBtn = document.querySelector('.btn-save');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const deleteConfirmProjectName = document.getElementById('deleteConfirmProjectName');
        const deleteConfirmCancel = document.getElementById('deleteConfirmCancel');
        const deleteConfirmProceed = document.getElementById('deleteConfirmProceed');
        const timelineModal = document.getElementById('projectTimelineModal');
        const timelineList = document.getElementById('timelineList');
        const timelineProjectName = document.getElementById('timelineProjectName');
        const closeTimelineModalBtn = document.getElementById('closeTimelineModalBtn');
        const timelineCloseFooterBtn = document.getElementById('timelineCloseFooterBtn');
        const timelineRange = document.getElementById('timelineRange');
        const timelineExportCsvBtn = document.getElementById('timelineExportCsvBtn');
        const timelineSearch = document.getElementById('timelineSearch');
        const timelineCount = document.getElementById('timelineCount');
        const timelineLoadMoreBtn = document.getElementById('timelineLoadMoreBtn');
        const timelineShowDuplicates = document.getElementById('timelineShowDuplicates');
        const timelineLatestStatus = document.getElementById('timelineLatestStatus');
        const timelineTotalChanges = document.getElementById('timelineTotalChanges');
        const timelineMostFrequent = document.getElementById('timelineMostFrequent');
        const timelineReviewers = document.getElementById('timelineReviewers');

        if (!table || !tbody) return;

        let allProjects = [];
        let currentPage = 1;
        let currentPerPage = 20;
        let currentTotal = 0;
        let currentTotalPages = 1;
        let searchDebounce = null;
        let pendingDeleteId = null;
        let currentTimelineEntries = [];
        let currentTimelineProjectId = null;
        let currentTimelineProjectName = '';
        let currentFilteredTimelineEntries = [];
        let timelineVisibleCount = 30;
        const timelinePageSize = 30;

        function showMsg(text, ok) {
            if (!msg) return;
            msg.textContent = text || '';
            msg.style.color = ok ? '#16a34a' : '#dc2626';
            msg.style.display = 'block';
            setTimeout(() => { msg.style.display = 'none'; }, 3000);
        }

        function apiUrls(suffix) {
            const urls = [];
            if (typeof window.getApiUrl === 'function') {
                urls.push(getApiUrl('admin/registered_projects.php' + suffix));
            }
            urls.push('registered_projects.php' + suffix);
            urls.push('/admin/registered_projects.php' + suffix);
            return urls;
        }

        function fetchJsonWithFallback(urls, options) {
            const tryFetch = (idx) => {
                if (idx >= urls.length) throw new Error('All API endpoints failed');
                return fetch(urls[idx], options)
                    .then((res) => {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .catch(() => tryFetch(idx + 1));
            };
            return tryFetch(0);
        }

        function esc(v) {
            return String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function toKey(v) {
            return String(v || 'draft').toLowerCase().replace(/\s+/g, '');
        }

        function renderProjects(projects) {
            tbody.innerHTML = '';
            if (!projects.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#6b7280;">No projects found.</td></tr>';
                return;
            }

            projects.forEach((p) => {
                const row = document.createElement('tr');
                const createdDate = p.created_at ? new Date(p.created_at).toLocaleDateString() : 'N/A';
                const priority = p.priority || 'Medium';
                const priorityMap = { crucial: 100, high: 75, medium: 50, low: 25 };
                const priorityPct = priorityMap[String(priority).toLowerCase()] || 50;
                const status = p.status || 'Draft';
                row.innerHTML = `
                    <td>${esc(p.code)}</td>
                    <td>${esc(p.name)}</td>
                    <td>${esc(p.type)}</td>
                    <td>${esc(p.sector)}</td>
                    <td><span class="priority-badge ${toKey(priority)}">${esc(priority)} ${priorityPct}%</span></td>
                    <td><span class="status-badge ${toKey(status)}">${esc(status)}</span></td>
                    <td>${esc(createdDate)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-timeline" data-id="${esc(p.id)}" type="button">Timeline</button>
                            <button class="btn-edit" data-id="${esc(p.id)}" type="button">Edit</button>
                            <button class="btn-delete" data-id="${esc(p.id)}" type="button">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function ensurePaginationContainer() {
            if (!tableWrap) return null;
            let pager = document.getElementById('projectsPager');
            if (pager) return pager;
            pager = document.createElement('div');
            pager.id = 'projectsPager';
            pager.style.display = 'flex';
            pager.style.justifyContent = 'space-between';
            pager.style.alignItems = 'center';
            pager.style.gap = '10px';
            pager.style.padding = '12px 6px 0';
            pager.innerHTML = `
                <div id="projectsPagerInfo" style="font-size:13px;color:#475569;">Showing 0 of 0</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button id="projectsPrevBtn" type="button" class="btn btn-secondary">Prev</button>
                    <span id="projectsPageLabel" style="font-size:13px;color:#334155;min-width:84px;text-align:center;">Page 1/1</span>
                    <button id="projectsNextBtn" type="button" class="btn btn-secondary">Next</button>
                </div>
            `;
            tableWrap.insertAdjacentElement('afterend', pager);
            return pager;
        }

        function renderPagination(meta) {
            ensurePaginationContainer();
            const info = document.getElementById('projectsPagerInfo');
            const label = document.getElementById('projectsPageLabel');
            const prevBtn = document.getElementById('projectsPrevBtn');
            const nextBtn = document.getElementById('projectsNextBtn');
            if (!info || !label || !prevBtn || !nextBtn) return;

            const page = Number(meta.page || 1);
            const total = Number(meta.total || 0);
            const perPage = Number(meta.per_page || currentPerPage || 20);
            const totalPages = Math.max(1, Number(meta.total_pages || 1));
            const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const to = total === 0 ? 0 : Math.min(total, page * perPage);

            info.textContent = `Showing ${from}-${to} of ${total}`;
            label.textContent = `Page ${page}/${totalPages}`;
            prevBtn.disabled = page <= 1;
            nextBtn.disabled = page >= totalPages;
        }

        function renderTimelineEntries(entries) {
            if (!timelineList) return;
            if (!entries.length) {
                timelineList.innerHTML = '<div class="timeline-empty">No status history yet.</div>';
                if (timelineCount) timelineCount.textContent = '0 entries';
                if (timelineLoadMoreBtn) timelineLoadMoreBtn.style.display = 'none';
                updateTimelineSummary(entries);
                return;
            }

            const visible = entries.slice(0, timelineVisibleCount);
            timelineList.innerHTML = visible.map((entry, index) => {
                const changedAt = entry.changed_at ? new Date(entry.changed_at).toLocaleString() : 'N/A';
                const badgeClass = toKey(entry.status || 'draft');
                const previousEntry = entries[index + 1] || null;
                const currentStatus = String(entry.status || 'Unknown');
                const previousStatus = String(previousEntry ? (previousEntry.status || 'Unknown') : 'Initial');
                const hasTransition = previousEntry && previousStatus !== currentStatus;
                const transitionText = hasTransition
                    ? `${previousStatus} -> ${currentStatus}`
                    : `Set as ${currentStatus}`;
                return `
                    <div class="timeline-item">
                        <div class="timeline-item-head">
                            <span class="status-badge ${esc(badgeClass)}">${esc(entry.status || 'Unknown')}</span>
                            <span class="timeline-item-date">${esc(changedAt)}</span>
                        </div>
                        <div class="timeline-item-transition">${esc(transitionText)}</div>
                        <div class="timeline-item-meta">By: ${esc(entry.changed_by || 'System')}</div>
                        <div class="timeline-item-notes">${esc(entry.notes || 'No notes provided.')}</div>
                    </div>
                `;
            }).join('');

            if (timelineCount) {
                timelineCount.textContent = `${visible.length} of ${entries.length} entries`;
            }

            if (timelineLoadMoreBtn) {
                timelineLoadMoreBtn.style.display = visible.length < entries.length ? 'inline-flex' : 'none';
            }
            updateTimelineSummary(entries);
        }

        function updateTimelineSummary(entries) {
            const list = Array.isArray(entries) ? entries : [];
            if (timelineTotalChanges) timelineTotalChanges.textContent = String(list.length);

            if (timelineLatestStatus) {
                timelineLatestStatus.textContent = list.length ? String(list[0].status || 'Unknown') : '-';
            }

            if (timelineReviewers) {
                const reviewers = new Set(
                    list
                        .map((x) => String(x.changed_by || '').trim())
                        .filter((x) => x.length > 0)
                );
                timelineReviewers.textContent = String(reviewers.size);
            }

            if (timelineMostFrequent) {
                if (!list.length) {
                    timelineMostFrequent.textContent = '-';
                } else {
                    const freq = {};
                    list.forEach((x) => {
                        const key = String(x.status || 'Unknown').trim() || 'Unknown';
                        freq[key] = (freq[key] || 0) + 1;
                    });
                    let topStatus = '';
                    let topCount = -1;
                    Object.keys(freq).forEach((k) => {
                        if (freq[k] > topCount) {
                            topStatus = k;
                            topCount = freq[k];
                        }
                    });
                    timelineMostFrequent.textContent = topStatus ? `${topStatus} (${topCount})` : '-';
                }
            }
        }

        function filterTimelineEntries(entries, rangeValue) {
            if (!Array.isArray(entries)) return [];
            if (!rangeValue || rangeValue === 'all') return entries.slice();

            const days = Number(rangeValue);
            if (!Number.isFinite(days) || days <= 0) return entries.slice();

            const now = Date.now();
            const cutoff = now - (days * 24 * 60 * 60 * 1000);
            return entries.filter((entry) => {
                const dt = Date.parse(entry.changed_at || '');
                return Number.isFinite(dt) && dt >= cutoff;
            });
        }

        function removeConsecutiveDuplicateStatuses(entries) {
            if (!Array.isArray(entries) || entries.length < 2) return Array.isArray(entries) ? entries.slice() : [];
            const out = [];
            let lastStatus = null;
            entries.forEach((entry) => {
                const statusKey = String(entry.status || '').trim().toLowerCase();
                if (statusKey !== lastStatus) {
                    out.push(entry);
                    lastStatus = statusKey;
                }
            });
            return out;
        }

        function applyTimelineFilter() {
            const rangeValue = timelineRange ? timelineRange.value : 'all';
            const searchTerm = (timelineSearch ? timelineSearch.value : '').trim().toLowerCase();
            const ranged = filterTimelineEntries(currentTimelineEntries, rangeValue);
            const searched = !searchTerm ? ranged : ranged.filter((entry) => {
                const haystack = `${entry.status || ''} ${entry.notes || ''} ${entry.changed_by || ''}`.toLowerCase();
                return haystack.includes(searchTerm);
            });
            const showDuplicates = timelineShowDuplicates ? !!timelineShowDuplicates.checked : false;
            currentFilteredTimelineEntries = showDuplicates ? searched : removeConsecutiveDuplicateStatuses(searched);
            renderTimelineEntries(currentFilteredTimelineEntries);
        }

        function closeTimelineModal() {
            if (!timelineModal) return;
            timelineModal.classList.remove('show');
            timelineModal.setAttribute('aria-hidden', 'true');
        }

        function openTimelineModal(projectId, projectName) {
            if (!timelineModal || !timelineList) return;

            currentTimelineProjectId = Number(projectId) || null;
            currentTimelineProjectName = projectName || '';
            currentTimelineEntries = [];
            currentFilteredTimelineEntries = [];
            timelineVisibleCount = timelinePageSize;
            if (timelineRange) timelineRange.value = 'all';
            if (timelineSearch) timelineSearch.value = '';
            if (timelineShowDuplicates) timelineShowDuplicates.checked = false;
            timelineProjectName.textContent = projectName ? ('Project: ' + projectName) : 'Project timeline';
            timelineList.innerHTML = '<div class="timeline-empty">Loading timeline...</div>';
            timelineModal.classList.add('show');
            timelineModal.setAttribute('aria-hidden', 'false');

            const nonce = Date.now();
            const url = '?action=load_project_timeline&project_id=' + encodeURIComponent(projectId) + '&_=' + nonce;
            fetchJsonWithFallback(apiUrls(url), { credentials: 'same-origin' })
                .then((data) => {
                    if (!data || data.success === false) {
                        throw new Error((data && data.message) ? data.message : 'Failed timeline request');
                    }
                    currentTimelineEntries = Array.isArray(data.history) ? data.history : [];
                    applyTimelineFilter();
                })
                .catch(() => {
                    timelineList.innerHTML = '<div class="timeline-empty">Unable to load status timeline.</div>';
                });
        }

        function exportCurrentTimelineCsv() {
            if (!currentTimelineEntries.length) {
                showMsg('No timeline data to export.', false);
                return;
            }
            const rowsData = currentFilteredTimelineEntries.length ? currentFilteredTimelineEntries : [];
            if (!rowsData.length) {
                showMsg('No timeline entries for selected range.', false);
                return;
            }

            const headers = ['Project ID', 'Project Name', 'Status', 'Changed At', 'Changed By', 'Notes'];
            const rows = rowsData.map((item) => ([
                currentTimelineProjectId || '',
                currentTimelineProjectName || '',
                item.status || '',
                item.changed_at || '',
                item.changed_by || '',
                item.notes || ''
            ].map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')));

            const csv = [headers.join(','), ...rows].join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const dateTag = new Date().toISOString().slice(0, 10);
            link.href = URL.createObjectURL(blob);
            link.download = `project_timeline_${currentTimelineProjectId || 'unknown'}_${dateTag}.csv`;
            link.click();
        }

        function fillEditModal(project) {
            if (!editModal || !editForm) return;
            const setVal = (selector, value) => {
                const el = document.querySelector(selector);
                if (el) el.value = value || '';
            };
            setVal('#projectId', project.id);
            setVal('#projectCode', project.code);
            setVal('#projectName', project.name);
            setVal('#projectType', project.type);
            setVal('#projectSector', project.sector);
            setVal('#projectPriority', project.priority || 'Medium');
            setVal('#projectStatus', project.status || 'Draft');
            setVal('#projectDescription', project.description);
            editModal.classList.add('show');
        }

        function openEditProjectModal(id) {
            const project = allProjects.find((x) => Number(x.id) === Number(id));
            if (project) {
                fillEditModal(project);
                return;
            }

            const nonce = Date.now();
            const urls = apiUrls('?action=get_project&id=' + encodeURIComponent(id) + '&_=' + nonce);
            fetchJsonWithFallback(urls, { credentials: 'same-origin' })
                .then((data) => {
                    if (!data || data.success === false) throw new Error('Project not found');
                    fillEditModal(data);
                })
                .catch(() => showMsg('Project data not found. Please refresh.', false));
        }

        function closeEditProjectModal() {
            if (editModal) editModal.classList.remove('show');
        }

        function saveEditedProject() {
            if (!editForm) return;
            const formData = new FormData(editForm);
            formData.append('action', 'update_project');

            if (editSaveBtn) {
                editSaveBtn.disabled = true;
                editSaveBtn.textContent = 'Saving...';
            }

            fetchJsonWithFallback(apiUrls(''), { method: 'POST', body: formData })
                .then((data) => {
                    showMsg(data.message || (data.success ? 'Project updated.' : 'Update failed.'), !!data.success);
                    if (data.success) {
                        closeEditProjectModal();
                        loadProjects();
                    }
                })
                .catch(() => showMsg('Error saving project.', false))
                .finally(() => {
                    if (editSaveBtn) {
                        editSaveBtn.disabled = false;
                        editSaveBtn.textContent = 'Save Changes';
                    }
                });
        }

        function performDelete(id) {
            fetchJsonWithFallback(apiUrls(''), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_project&id=' + encodeURIComponent(id)
            })
            .then((data) => {
                showMsg(data.message || (data.success ? 'Project deleted.' : 'Delete failed.'), !!data.success);
                if (data.success) loadProjects();
            })
            .catch(() => showMsg('Error deleting project.', false));
        }

        function confirmDeleteProject(id, projectName) {
            const safeName = projectName || 'this project';

            if (!deleteConfirmModal || !deleteConfirmProjectName) {
                if (window.confirm(`Delete "${safeName}" permanently? This cannot be undone.`)) {
                    performDelete(id);
                }
                return;
            }

            pendingDeleteId = id;
            deleteConfirmProjectName.textContent = safeName;
            deleteConfirmModal.classList.add('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'false');
            if (deleteConfirmProceed) deleteConfirmProceed.focus();
        }

        function closeDeleteConfirmModal() {
            pendingDeleteId = null;
            if (!deleteConfirmModal) return;
            deleteConfirmModal.classList.remove('show');
            deleteConfirmModal.setAttribute('aria-hidden', 'true');
        }

        function buildLoadQuery(pageOverride) {
            const page = Math.max(1, Number(pageOverride || currentPage || 1));
            const q = (searchInput ? searchInput.value : '').trim();
            const status = (statusFilter ? statusFilter.value : '').trim();
            const sort = 'createdAt_desc';
            const params = new URLSearchParams();
            params.set('action', 'load_projects');
            params.set('v2', '1');
            params.set('page', String(page));
            params.set('per_page', String(currentPerPage));
            params.set('q', q);
            params.set('status', status);
            params.set('sort', sort);
            params.set('_', String(Date.now()));
            return params.toString();
        }

        function loadProjects(pageOverride) {
            const nonce = Date.now();
            const query = buildLoadQuery(pageOverride) + '&nonce=' + nonce;
            fetchJsonWithFallback(apiUrls('?' + query), { credentials: 'same-origin' })
                .then((payload) => {
                    if (Array.isArray(payload)) {
                        allProjects = payload;
                        currentTotal = payload.length;
                        currentTotalPages = 1;
                        currentPage = 1;
                        renderProjects(allProjects);
                        renderPagination({
                            page: 1,
                            per_page: currentPerPage,
                            total: payload.length,
                            total_pages: 1
                        });
                        return;
                    }
                    if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                        throw new Error('Invalid payload');
                    }
                    const meta = payload.meta || {};
                    allProjects = payload.data;
                    currentPage = Number(meta.page || 1);
                    currentPerPage = Number(meta.per_page || currentPerPage || 20);
                    currentTotal = Number(meta.total || allProjects.length);
                    currentTotalPages = Math.max(1, Number(meta.total_pages || 1));
                    renderProjects(allProjects);
                    renderPagination({
                        page: currentPage,
                        per_page: currentPerPage,
                        total: currentTotal,
                        total_pages: currentTotalPages
                    });
                })
                .catch(() => {
                    if (!tbody.querySelector('tr')) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error loading projects. Check console.</td></tr>';
                    }
                    renderPagination({ page: 1, per_page: currentPerPage, total: 0, total_pages: 1 });
                });
        }

        table.addEventListener('click', function (event) {
            const timelineBtn = event.target.closest('.btn-timeline');
            if (timelineBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const row = timelineBtn.closest('tr');
                const projectName = row ? (row.querySelector('td:nth-child(2)')?.textContent || '').trim() : '';
                openTimelineModal(timelineBtn.dataset.id, projectName);
                return;
            }

            const editBtn = event.target.closest('.btn-edit');
            if (editBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openEditProjectModal(editBtn.dataset.id);
                return;
            }

            const deleteBtn = event.target.closest('.btn-delete');
            if (deleteBtn) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const row = deleteBtn.closest('tr');
                const projectName = row ? (row.querySelector('td:nth-child(2)')?.textContent || '').trim() : '';
                confirmDeleteProject(deleteBtn.dataset.id, projectName);
            }
        }, true);

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (searchDebounce) window.clearTimeout(searchDebounce);
                searchDebounce = window.setTimeout(function () {
                    currentPage = 1;
                    loadProjects(1);
                }, 250);
            });
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                currentPage = 1;
                loadProjects(1);
            });
        }

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', function () {
                const q = (searchInput ? searchInput.value : '').trim();
                const status = (statusFilter ? statusFilter.value : '').trim();
                const sort = 'createdAt_desc';
                const params = new URLSearchParams();
                params.set('action', 'export_projects_csv');
                params.set('q', q);
                params.set('status', status);
                params.set('sort', sort);
                params.set('_', String(Date.now()));
                window.location.href = 'registered_projects.php?' + params.toString();
            });
        }

        function bindPagerEvents() {
            const prevBtn = document.getElementById('projectsPrevBtn');
            const nextBtn = document.getElementById('projectsNextBtn');
            if (prevBtn && !prevBtn.dataset.bound) {
                prevBtn.dataset.bound = '1';
                prevBtn.addEventListener('click', function () {
                    if (currentPage > 1) loadProjects(currentPage - 1);
                });
            }
            if (nextBtn && !nextBtn.dataset.bound) {
                nextBtn.dataset.bound = '1';
                nextBtn.addEventListener('click', function () {
                    if (currentPage < currentTotalPages) loadProjects(currentPage + 1);
                });
            }
        }

        window.openEditModal = openEditProjectModal;
        window.closeEditModal = closeEditProjectModal;
        window.saveProject = saveEditedProject;
        window.confirmDeleteProject = confirmDeleteProject;

        if (editModal) {
            window.addEventListener('click', function (event) {
                if (event.target === editModal) closeEditProjectModal();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && editModal && editModal.classList.contains('show')) {
                closeEditProjectModal();
            }
            if (event.key === 'Escape' && deleteConfirmModal && deleteConfirmModal.classList.contains('show')) {
                closeDeleteConfirmModal();
            }
            if (event.key === 'Escape' && timelineModal && timelineModal.classList.contains('show')) {
                closeTimelineModal();
            }
        });

        if (deleteConfirmCancel) {
            deleteConfirmCancel.addEventListener('click', closeDeleteConfirmModal);
        }

        if (deleteConfirmProceed) {
            deleteConfirmProceed.addEventListener('click', function () {
                if (!pendingDeleteId) return;
                const idToDelete = pendingDeleteId;
                closeDeleteConfirmModal();
                performDelete(idToDelete);
            });
        }

        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('click', function (event) {
                if (event.target === deleteConfirmModal) {
                    closeDeleteConfirmModal();
                }
            });
        }

        if (closeTimelineModalBtn) {
            closeTimelineModalBtn.addEventListener('click', closeTimelineModal);
        }
        if (timelineCloseFooterBtn) {
            timelineCloseFooterBtn.addEventListener('click', closeTimelineModal);
        }
        if (timelineRange) {
            timelineRange.addEventListener('change', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineSearch) {
            timelineSearch.addEventListener('input', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineShowDuplicates) {
            timelineShowDuplicates.addEventListener('change', function () {
                timelineVisibleCount = timelinePageSize;
                applyTimelineFilter();
            });
        }
        if (timelineExportCsvBtn) {
            timelineExportCsvBtn.addEventListener('click', exportCurrentTimelineCsv);
        }
        if (timelineLoadMoreBtn) {
            timelineLoadMoreBtn.addEventListener('click', function () {
                timelineVisibleCount += timelinePageSize;
                renderTimelineEntries(currentFilteredTimelineEntries);
            });
        }
        if (timelineModal) {
            timelineModal.addEventListener('click', function (event) {
                if (event.target === timelineModal) {
                    closeTimelineModal();
                }
            });
        }

        ensurePaginationContainer();
        bindPagerEvents();
        loadProjects(1);
    })();
