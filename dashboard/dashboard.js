document.getElementById('toggleSidebar').addEventListener('click', function(e) {
    e.preventDefault();
    
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});

document.getElementById('toggleSidebarShow').addEventListener('click', function(e) {
    e.preventDefault();
    
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});

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
        progressFill.textContent = budget.utilization + '% Used';
    }
    
    if (utilizationText) {
        utilizationText.textContent = `${IPMS_DATA.formatCurrency(budget.spent)} of ${IPMS_DATA.formatCurrency(budget.total)} allocated`;
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

// Auto-refresh dashboard when returning from other pages
function setupAutoRefresh() {
    // Refresh on page load
    loadDashboardData();

    // Refresh when window gains focus (user returns to dashboard)
    window.addEventListener('focus', loadDashboardData);

    // Refresh every 30 seconds if page is visible
    setInterval(() => {
        if (!document.hidden) {
            loadDashboardData();
        }
    }, 30000);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', setupAutoRefresh);