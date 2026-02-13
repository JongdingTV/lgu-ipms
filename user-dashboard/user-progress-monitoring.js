(function () {
    let allProjects = [];
    let activeQuickStatus = '';

    function normalizeStatus(status) {
        return (status || '').toLowerCase().trim();
    }

    function toNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function formatCurrency(value) {
        return 'PHP ' + toNumber(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getProgress(project) {
        const direct = toNumber(project.progress);
        if (direct >= 0 && direct <= 100) return direct;

        const status = normalizeStatus(project.status);
        if (status === 'completed') return 100;
        if (status === 'approved') return 70;
        if (status === 'for approval') return 40;
        if (status === 'on-hold') return 20;
        return 0;
    }

    async function loadProjects() {
        try {
            const response = await fetch(getApiUrl('user-dashboard/user-progress-monitoring.php?action=load_projects'));
            if (!response.ok) throw new Error('Failed to load projects');
            const data = await response.json();
            allProjects = Array.isArray(data) ? data : [];
        } catch (error) {
            console.error(error);
            allProjects = [];
        }
        render();
    }

    function applyFilters(projects) {
        const query = (document.getElementById('pmSearch')?.value || '').trim().toLowerCase();
        const status = document.getElementById('pmStatusFilter')?.value || '';
        const sector = document.getElementById('pmSectorFilter')?.value || '';
        const progressRange = document.getElementById('pmProgressFilter')?.value || '';
        const sort = document.getElementById('pmSort')?.value || 'createdAt_desc';

        let filtered = projects.filter(project => {
            if (status && project.status !== status) return false;
            if (activeQuickStatus && project.status !== activeQuickStatus) return false;
            if (sector && project.sector !== sector) return false;

            if (progressRange) {
                const progress = getProgress(project);
                const [min, max] = progressRange.split('-').map(v => Number(v));
                if (!(progress >= min && progress <= max)) return false;
            }

            if (query) {
                const haystack = `${project.code || ''} ${project.name || ''} ${project.location || ''}`.toLowerCase();
                if (!haystack.includes(query)) return false;
            }

            return true;
        });

        filtered.sort((a, b) => {
            if (sort === 'progress_desc') return getProgress(b) - getProgress(a);
            if (sort === 'progress_asc') return getProgress(a) - getProgress(b);

            const aDate = new Date(a.created_at || a.createdAt || 0).getTime();
            const bDate = new Date(b.created_at || b.createdAt || 0).getTime();
            if (sort === 'createdAt_asc') return aDate - bDate;
            return bDate - aDate;
        });

        return filtered;
    }

    function updateStats(projects) {
        const total = projects.length;
        const approved = projects.filter(p => normalizeStatus(p.status) === 'approved').length;
        const inProgress = projects.filter(p => ['approved', 'for approval', 'on-hold'].includes(normalizeStatus(p.status))).length;
        const completed = projects.filter(p => normalizeStatus(p.status) === 'completed').length;

        const totalEl = document.getElementById('statTotal');
        const approvedEl = document.getElementById('statApproved');
        const inProgressEl = document.getElementById('statInProgress');
        const completedEl = document.getElementById('statCompleted');

        if (totalEl) totalEl.textContent = String(total);
        if (approvedEl) approvedEl.textContent = String(approved);
        if (inProgressEl) inProgressEl.textContent = String(inProgress);
        if (completedEl) completedEl.textContent = String(completed);
    }

    function renderProjects(projects) {
        const list = document.getElementById('projectsList');
        const empty = document.getElementById('pmEmpty');
        const summary = document.getElementById('pmResultSummary');

        if (!list || !empty) return;

        if (!projects.length) {
            list.innerHTML = '';
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            list.innerHTML = projects.map(project => {
                const progress = getProgress(project);
                const statusClass = normalizeStatus(project.status).replace(/\s+/g, '-');

                return `
                    <article class="project-card risk-low">
                        <div class="project-header">
                            <div class="project-title-section">
                                <span class="project-code-badge">${project.code || 'N/A'}</span>
                                <h4>${project.name || 'Unnamed Project'}</h4>
                            </div>
                            <span class="project-status ${statusClass}">${project.status || 'Draft'}</span>
                        </div>

                        <p class="project-description">${project.description || 'No description provided.'}</p>

                        <div class="project-meta">
                            <div class="project-meta-item"><span class="project-meta-label">Location</span><span class="project-meta-value">${project.location || '-'}</span></div>
                            <div class="project-meta-item"><span class="project-meta-label">Sector</span><span class="project-meta-value">${project.sector || '-'}</span></div>
                            <div class="project-meta-item"><span class="project-meta-label">Budget</span><span class="project-meta-value">${formatCurrency(project.budget)}</span></div>
                            <div class="project-meta-item"><span class="project-meta-label">Duration</span><span class="project-meta-value">${project.duration_months || '-'} months</span></div>
                        </div>

                        <div class="progress-container">
                            <div class="progress-label"><span>Progress</span><strong>${progress}%</strong></div>
                            <div class="progress-bar"><div class="progress-fill" style="width:${progress}%;height:100%;"></div></div>
                        </div>

                        <div class="project-click-hint">View-only mode: data is managed by admin.</div>
                    </article>
                `;
            }).join('');
        }

        if (summary) {
            summary.textContent = `Showing ${projects.length} of ${allProjects.length} projects`;
        }
    }

    function render() {
        const filtered = applyFilters(allProjects);
        updateStats(allProjects);
        renderProjects(filtered);
    }

    function setQuickFilterButtons() {
        const quickWrap = document.getElementById('pmQuickFilters');
        if (!quickWrap) return;

        quickWrap.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function () {
                activeQuickStatus = btn.dataset.status || '';
                quickWrap.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                render();
            });
        });
    }

    function setupControls() {
        ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmSort'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            const eventName = el.tagName.toLowerCase() === 'input' ? 'input' : 'change';
            el.addEventListener(eventName, render);
        });

        const clearBtn = document.getElementById('pmClearFilters');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                const ids = ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmSort'];
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (id === 'pmSort') el.value = 'createdAt_desc';
                    else el.value = '';
                });

                const quickWrap = document.getElementById('pmQuickFilters');
                if (quickWrap) {
                    quickWrap.querySelectorAll('button').forEach((btn, index) => {
                        btn.classList.toggle('active', index === 0);
                    });
                }

                activeQuickStatus = '';
                render();
            });
        }

    }

    document.addEventListener('DOMContentLoaded', function () {
        setQuickFilterButtons();
        setupControls();
        loadProjects();
    });
})();
