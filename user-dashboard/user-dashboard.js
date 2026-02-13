document.addEventListener('DOMContentLoaded', async function () {
    const completedEl = document.getElementById('completedPercent');
    const inProgressEl = document.getElementById('inProgressPercent');
    const otherEl = document.getElementById('otherPercent');
    const budgetFillEl = document.getElementById('budgetProgressFill');
    const budgetTextEl = document.getElementById('budgetUtilizationText');

    let projects = [];

    try {
        const response = await fetch(getApiUrl('user-dashboard/user-progress-monitoring.php?action=load_projects'));
        if (response.ok) {
            projects = await response.json();
        }
    } catch (error) {
        console.error('Unable to load projects for dashboard metrics:', error);
    }

    if (!Array.isArray(projects) || projects.length === 0) {
        if (completedEl) completedEl.textContent = 'Completed: 0%';
        if (inProgressEl) inProgressEl.textContent = 'In Progress: 0%';
        if (otherEl) otherEl.textContent = 'Other: 0%';
        if (budgetFillEl) budgetFillEl.style.width = '0%';
        if (budgetTextEl) budgetTextEl.textContent = 'Budget utilization: 0% Used';
        return;
    }

    const total = projects.length;
    const completed = projects.filter(p => (p.status || '').toLowerCase() === 'completed').length;
    const inProgress = projects.filter(p => ['approved', 'for approval', 'on-hold'].includes((p.status || '').toLowerCase())).length;
    const other = Math.max(0, total - completed - inProgress);

    const completedPct = Math.round((completed / total) * 100);
    const inProgressPct = Math.round((inProgress / total) * 100);
    const otherPct = Math.max(0, 100 - completedPct - inProgressPct);

    if (completedEl) completedEl.textContent = `Completed: ${completedPct}%`;
    if (inProgressEl) inProgressEl.textContent = `In Progress: ${inProgressPct}%`;
    if (otherEl) otherEl.textContent = `Other: ${otherPct}%`;

    const utilization = Math.round((completed / total) * 100);
    if (budgetFillEl) budgetFillEl.style.width = `${utilization}%`;
    if (budgetTextEl) budgetTextEl.textContent = `Budget utilization: ${utilization}% Used`;
});
