// filepath: c:\Users\james\OneDrive\Documents\GitHub\lgu-ipms\task-milestone\task-milestone.js
// sidebar toggle (keeps existing functionality)
document.getElementById('toggleSidebar')?.addEventListener('click', function(e) {
    e.preventDefault();
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});
document.getElementById('toggleSidebarShow')?.addEventListener('click', function(e) {
    e.preventDefault();
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});

/* Task & Milestone module (client-only demo using localStorage) */
const TM_KEY = 'lgu_tasks_v1';

function loadTasks() { return JSON.parse(localStorage.getItem(TM_KEY) || '[]'); }
function saveTasks(list) { localStorage.setItem(TM_KEY, JSON.stringify(list)); }
function uid() { return 't' + Math.random().toString(36).slice(2,9); }

function getProjects() {
    if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) {
        return IPMS_DATA.getProjects();
    } else {
        return JSON.parse(localStorage.getItem('projects') || '[]');
    }
}

function renderProjectOptions() {
    const projects = getProjects();
    const sel1 = document.getElementById('taskProject');
    const selFilter = document.getElementById('tmFilterProject');
    [sel1, selFilter].forEach(sel => {
        if (!sel) return;
        const current = sel.value || '';
        sel.innerHTML = '<option value="">Select project</option>';
        projects.forEach(p => {
            const o = document.createElement('option'); o.value = p.code || p.name || ''; o.textContent = (p.code ? p.code + ' — ' : '') + (p.name || 'Unnamed');
            sel.appendChild(o);
        });
        sel.value = current;
    });
}

function isOverdue(d) {
    if (!d) return false;
    const today = new Date(); today.setHours(0,0,0,0);
    const dd = new Date(d); dd.setHours(0,0,0,0);
    return dd < today;
}

function dueWithinWeek(d) {
    if (!d) return false;
    const today = new Date(); today.setHours(0,0,0,0);
    const dd = new Date(d); dd.setHours(0,0,0,0);
    const diff = (dd - today)/(1000*60*60*24);
    return diff >=0 && diff <=7;
}
function renderTasks() {
    const tasks = loadTasks();
    const tbody = document.querySelector('#tasksTable tbody');
    tbody.innerHTML = '';
    // For validation, show deliverable, status, and a validated checkbox
    let validatedCount = 0;
    tasks.forEach(t => {
        const isValidated = t.status === 'Completed' || t.validated;
        if (isValidated) validatedCount++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${t.deliverable || ''}</td>
            <td><span class="badge status-${(t.status||'Not Started').replace(/\s+/g,'\\ ')}">${t.status || 'Not Started'}</span></td>
            <td><input type="checkbox" class="validate-checkbox" data-id="${t.id}" ${isValidated ? 'checked' : ''}></td>
        `;
        tbody.appendChild(tr);
    });

    // Calculate and show validation percentage
    const total = tasks.length;
    const percent = total ? Math.round((validatedCount / total) * 100) : 0;
    document.getElementById('validationPercent').textContent = percent + '%';
    document.getElementById('validationProgress').style.width = percent + '%';

    // Wire validation checkboxes
    document.querySelectorAll('.validate-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const id = cb.dataset.id;
            const list = loadTasks();
            const t = list.find(x => x.id === id);
            if (t) {
                t.validated = cb.checked;
                if (cb.checked) t.status = 'Completed';
                saveTasks(list);
                renderTasks();
            }
        });
    });
}
}
// Remove task creation form logic for validation-only UI
});

document.getElementById('tmExport')?.addEventListener('click', ()=>{
    const tasks = loadTasks();
    if (!tasks.length) { alert('No tasks to export'); return; }
    const keys = ['id','project','deliverable','taskName','assigneeType','assignee','deadline','priority','status','createdAt'];
    const rows = tasks.map(t => keys.map(k => `"${(t[k]||'').toString().replace(/"/g,'""')}"`).join(','));
    const csv = ['"' + keys.join('","') + '"', ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'tasks_export.csv'; a.click(); URL.revokeObjectURL(url);
});

// filters
['tmSearch','tmFilterStatus','tmFilterProject'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', renderTasks);
    document.getElementById(id)?.addEventListener('change', renderTasks);
});

// init
document.addEventListener('DOMContentLoaded', ()=>{
    renderProjectOptions();
    renderTasks();
});