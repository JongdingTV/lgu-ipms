// Debounce helper for expensive operations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimize event listeners
const sidebarToggle = () => {
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
};

const toggleBtn1 = document.getElementById('toggleSidebar');
const toggleBtn2 = document.getElementById('toggleSidebarShow');
if (toggleBtn1) toggleBtn1.addEventListener('click', (e) => { e.preventDefault(); sidebarToggle(); });
if (toggleBtn2) toggleBtn2.addEventListener('click', (e) => { e.preventDefault(); sidebarToggle(); });

// Demo fallback data (used when IPMS_DATA is not available)
const demoProjects = [];

/* Dashboard real-time data integration */
function loadDashboardData() {
    // Check if shared data service is available
    if (typeof IPMS_DATA === 'undefined') {
        console.error('IPMS_DATA service not loaded');
        return;
    }

    const metrics = IPMS_DATA.getDashboardMetrics();
    const recentProjects = IPMS_DATA.getRecentProjects(3);

    // Update key metrics
    updateMetricCard(0, metrics.projects.total, 'Active & Completed');
    updateMetricCard(1, metrics.projects.inProgress, 'Currently executing');
    updateMetricCard(2, metrics.projects.completed, 'Successfully finished');
    updateMetricCard(3, IPMS_DATA.formatCurrency(metrics.budget.total), 'Total allocated');

    // Update status distribution
    updateStatusDistribution(metrics.statusDistribution);

    // Update budget utilization
    updateBudgetUtilization(metrics.budget);

    // Update recent projects table
    updateRecentProjectsTable(recentProjects);

    // Update quick stats
    updateQuickStats(metrics.analytics);
}

function updateMetricCard(index, value, status) {
    const cards = document.querySelectorAll('.metric-card');
    if (cards[index]) {
        const valueEl = cards[index].querySelector('.metric-value');
        const statusEl = cards[index].querySelector('.metric-status');
        if (valueEl) valueEl.textContent = value;
        if (statusEl) statusEl.textContent = status;
    }
}

function updateStatusDistribution(distribution) {
    const legendItems = document.querySelectorAll('.legend-item span:last-child');
    if (legendItems[0]) legendItems[0].textContent = `Completed: ${distribution.completed}%`;
    if (legendItems[1]) legendItems[1].textContent = `In Progress: ${distribution.inProgress}%`;
    if (legendItems[2]) legendItems[2].textContent = `Delayed: ${distribution.delayed}%`;
}

function updateBudgetUtilization(budget) {
    const progressFill = document.querySelector('.progress-fill');
    const utilizationText = document.querySelector('.chart-placeholder p');
    
    if (progressFill) {
        progressFill.style.width = budget.utilization + '%';
    }
    
    if (utilizationText) {
        utilizationText.textContent = `Budget utilization: ${budget.utilization}% Used (${IPMS_DATA.formatCurrency(budget.spent)} of ${IPMS_DATA.formatCurrency(budget.total)} allocated)`;
    }
}

function updateRecentProjectsTable(projects) {
    const tbody = document.querySelector('.projects-table tbody');
    if (!tbody) return;

    if (projects.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">No projects registered yet</td></tr>';
        return;
    }

    tbody.innerHTML = projects.map(p => {
        const statusClass = p.status.toLowerCase().replace(/\s+/g, '-');
        return `
            <tr>
                <td>${p.name}</td>
                <td>${p.location}</td>
                <td><span class="status-badge ${statusClass}">${p.status}</span></td>
                <td>
                    <div class="progress-small">
                        <div class="progress-fill-small" style="width: ${p.progress}%;"></div>
                    </div>
                </td>
                <td>${IPMS_DATA.formatCurrency(p.budget)}</td>
            </tr>
        `;
    }).join('');
}

function updateQuickStats(analytics) {
    const statItems = document.querySelectorAll('.stat-item p');
    if (statItems[0]) statItems[0].textContent = analytics.avgDuration + ' months';
    if (statItems[1]) statItems[1].textContent = analytics.onTimeRate + '%';
    if (statItems[2]) statItems[2].textContent = (analytics.budgetVariance >= 0 ? '+' : '') + analytics.budgetVariance + '%';
}

// Debounced refresh function
const debouncedLoadDashboardData = debounce(loadDashboardData, 500);

// Auto-refresh dashboard when returning from other pages
function setupAutoRefresh() {
    // Refresh on page load
    loadDashboardData();

    // Refresh when window gains focus (user returns to dashboard)
    window.addEventListener('focus', debouncedLoadDashboardData);

    // Refresh every 30 seconds if page is visible (use debounce to prevent overlapping calls)
    setInterval(() => {
        if (!document.hidden) {
            debouncedLoadDashboardData();
        }
    }, 30000);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', setupAutoRefresh);

/* Metric card interactions: filter projects and show budget details */
function setupMetricInteractions() {
    const cards = document.querySelectorAll('.metric-card');
    if (!cards || cards.length === 0) return;

    cards.forEach((card, idx) => {
        // add three-dots menu if not present
        if (!card.querySelector('.card-menu')) {
            const btn = document.createElement('button');
            btn.className = 'card-menu';
            btn.type = 'button';
            btn.title = 'More';
            btn.innerText = '⋯';
            card.appendChild(btn);
            // placeholder for menu click
            btn.addEventListener('click', (e) => { e.stopPropagation(); });
        }

        // click behavior on the card itself
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () {
            // animate
            card.classList.add('pulse');
            setTimeout(() => card.classList.remove('pulse'), 380);

            // determine action by index or data attribute
            if (idx === 0) {
                const list = fetchProjectsByFilter('all');
                updateRecentProjectsTable(list);
            } else if (idx === 1) {
                const list = fetchProjectsByFilter('inProgress');
                updateRecentProjectsTable(list);
            } else if (idx === 2) {
                const list = fetchProjectsByFilter('completed');
                updateRecentProjectsTable(list);
            } else if (idx === 3) {
                if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getDashboardMetrics) {
                    const metrics = IPMS_DATA.getDashboardMetrics();
                    if (metrics && metrics.budget) {
                        updateBudgetUtilization(metrics.budget);
                    }
                }
            }
        });
    });
}

function fetchProjectsByFilter(filter) {
    if (typeof IPMS_DATA === 'undefined') {
        const all = demoProjects.slice();
        if (filter === 'all') return all;
        if (filter === 'inProgress') return all.filter(p => p.status && /progress|in progress/i.test(p.status));
        if (filter === 'completed') return all.filter(p => p.status && /complete|completed|finished/i.test(p.status));
        return [];
    }

    try {
        if (filter === 'all') {
            if (IPMS_DATA.getAllProjects) return IPMS_DATA.getAllProjects();
            if (IPMS_DATA.getProjects) return IPMS_DATA.getProjects();
            if (IPMS_DATA.getRecentProjects) return IPMS_DATA.getRecentProjects(1000) || [];
        }

        if (filter === 'inProgress') {
            if (IPMS_DATA.getProjectsByStatus) return IPMS_DATA.getProjectsByStatus('In Progress');
            const all = (IPMS_DATA.getAllProjects ? IPMS_DATA.getAllProjects() : (IPMS_DATA.getRecentProjects ? IPMS_DATA.getRecentProjects(1000) : []));
            return all.filter(p => p.status && /progress|in progress/i.test(p.status));
        }

        if (filter === 'completed') {
            if (IPMS_DATA.getProjectsByStatus) return IPMS_DATA.getProjectsByStatus('Completed');
            const all = (IPMS_DATA.getAllProjects ? IPMS_DATA.getAllProjects() : (IPMS_DATA.getRecentProjects ? IPMS_DATA.getRecentProjects(1000) : []));
            return all.filter(p => p.status && /complete|completed|finished/i.test(p.status));
        }
    } catch (err) {
        console.error('Error fetching projects by filter', err);
    }
    return [];
}

// Run interactions setup after initial render
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(setupMetricInteractions, 120);
});