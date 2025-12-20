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
    const q = (document.getElementById('tmSearch')?.value || '').toLowerCase();
    const statusFilter = document.getElementById('tmFilterStatus')?.value || '';
    const projectFilter = document.getElementById('tmFilterProject')?.value || '';

    const filtered = tasks.filter(t => {
        if (statusFilter && t.status !== statusFilter) return false;
        if (projectFilter && (t.project || '') !== projectFilter) return false;
        if (!q) return true;
        return ((t.taskName||'') + ' ' + (t.deliverable||'') + ' ' + (t.assignee||'')).toLowerCase().includes(q);
    });

    filtered.forEach(t => {
        const tr = document.createElement('tr');
        if (isOverdue(t.deadline) && t.status !== 'Completed') tr.classList.add('overdue');
        tr.innerHTML = `
            <td>${t.deliverable || ''}</td>
            <td>${t.taskName || ''}</td>
            <td>${t.assignee || ''}</td>
            <td>${t.assigneeType || ''}</td>
            <td class="priority-${t.priority || 'Normal'}">${t.priority || 'Normal'}</td>
            <td>${t.deadline || ''}</td>
            <td><span class="badge status-${(t.status||'Not Started').replace(/\s+/g,'\\ ')}">${t.status || 'Not Started'}</span></td>
            <td>
                <button class="action-btn btn-edit" data-id="${t.id}">Edit</button>
                <button class="action-btn delete btn-delete" data-id="${t.id}">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });

    // stats
    const total = tasks.length;
    const overdue = tasks.filter(t => isOverdue(t.deadline) && t.status !== 'Completed').length;
    const week = tasks.filter(t => dueWithinWeek(t.deadline)).length;
    document.getElementById('tmTotal').textContent = total;
    document.getElementById('tmOverdue').textContent = overdue;
    document.getElementById('tmDueWeek').textContent = week;

    // wire buttons
    document.querySelectorAll('.btn-delete').forEach(b => b.addEventListener('click', () => {
        const id = b.dataset.id;
        const list = loadTasks().filter(x=> x.id !== id);
        saveTasks(list); renderTasks();
    }));
    document.querySelectorAll('.btn-edit').forEach(b => b.addEventListener('click', () => {
        const id = b.dataset.id;
        const list = loadTasks();
        const t = list.find(x=> x.id===id);
        if (!t) return;
        // populate form for quick edit (overwrite if user submits)
        document.getElementById('taskProject').value = t.project || '';
        document.getElementById('deliverable').value = t.deliverable || '';
        document.getElementById('taskName').value = t.taskName || '';
        document.getElementById('assigneeType').value = t.assigneeType || 'Contractor';
        document.getElementById('assignee').value = t.assignee || '';
        document.getElementById('deadline').value = t.deadline || '';
        document.getElementById('priority').value = t.priority || 'Normal';
        // change submit to update
        const form = document.getElementById('taskForm');
        form.dataset.editId = id;
        form.scrollIntoView({behavior:'smooth', block:'center'});
    }));
}

document.getElementById('taskForm')?.addEventListener('submit', function(ev){
    ev.preventDefault();
    const form = ev.target;
    const project = document.getElementById('taskProject').value;
    const deliverable = document.getElementById('deliverable').value.trim();
    const taskName = document.getElementById('taskName').value.trim();
    const assigneeType = document.getElementById('assigneeType').value;
    const assignee = document.getElementById('assignee').value.trim();
    const deadline = document.getElementById('deadline').value;
    const priority = document.getElementById('priority').value || 'Normal';
    if (!taskName || !assignee) return;

    const list = loadTasks();
    const editId = form.dataset.editId;
    if (editId) {
        const idx = list.findIndex(x=> x.id === editId);
        if (idx !== -1) {
            list[idx] = { ...list[idx], project, deliverable, taskName, assigneeType, assignee, deadline, priority };
            saveTasks(list);
            delete form.dataset.editId;
        }
    } else {
        const t = { id: uid(), project, deliverable, taskName, assigneeType, assignee, deadline, priority, status: 'Not Started', createdAt: new Date().toISOString() };
        list.push(t); saveTasks(list);
    }
    form.reset();
    renderTasks();
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