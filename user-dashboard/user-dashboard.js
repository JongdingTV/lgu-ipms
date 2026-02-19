document.addEventListener('DOMContentLoaded', async function () {
    const completedEl = document.getElementById('completedPercent');
    const inProgressEl = document.getElementById('inProgressPercent');
    const otherEl = document.getElementById('otherPercent');
    const statusStackBarEl = document.getElementById('statusStackBar');
    const monthlyLineEl = document.getElementById('monthlyActivityLine');
    const monthlyTextEl = document.getElementById('monthlyActivityText');

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
        if (statusStackBarEl) statusStackBarEl.style.width = '0%';
        if (monthlyLineEl) monthlyLineEl.setAttribute('points', '0,110 320,110');
        if (monthlyTextEl) monthlyTextEl.textContent = 'No monthly activity yet.';
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

    if (statusStackBarEl) {
        statusStackBarEl.style.width = '100%';
        statusStackBarEl.style.background = `linear-gradient(90deg, #16a34a 0% ${completedPct}%, #2563eb ${completedPct}% ${completedPct + inProgressPct}%, #f59e0b ${completedPct + inProgressPct}% 100%)`;
    }

    const monthKeys = [];
    const now = new Date();
    for (let i = 5; i >= 0; i -= 1) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        monthKeys.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }

    const monthCounts = Object.fromEntries(monthKeys.map(key => [key, 0]));
    projects.forEach((project) => {
        const createdAt = project.created_at ? new Date(project.created_at) : null;
        if (!createdAt || Number.isNaN(createdAt.getTime())) return;
        const key = `${createdAt.getFullYear()}-${String(createdAt.getMonth() + 1).padStart(2, '0')}`;
        if (Object.prototype.hasOwnProperty.call(monthCounts, key)) {
            monthCounts[key] += 1;
        }
    });

    const values = monthKeys.map(key => monthCounts[key]);
    const maxValue = Math.max(1, ...values);
    const points = values.map((value, index) => {
        const x = Math.round((index / 5) * 320);
        const y = Math.round(110 - ((value / maxValue) * 100));
        return `${x},${y}`;
    }).join(' ');

    if (monthlyLineEl) {
        monthlyLineEl.setAttribute('points', points);
    }

    if (monthlyTextEl) {
        const totalRecent = values.reduce((sum, value) => sum + value, 0);
        monthlyTextEl.textContent = `Projects created in last 6 months: ${totalRecent}`;
    }
});
