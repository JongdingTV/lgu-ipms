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

/* ===== Budget & Resources logic ===== */

const BUDGET_KEY = 'lgu_budget_module_v1';

function loadData() {
    return JSON.parse(localStorage.getItem(BUDGET_KEY) || '{"globalBudget":0,"milestones":[],"expenses":[]}');
}
function saveData(data) {
    localStorage.setItem(BUDGET_KEY, JSON.stringify(data));
}
function currency(n) {
    if (n === undefined || n === null || isNaN(n)) return '₱0';
    return '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
}
function uid() { return 'm' + Math.random().toString(36).slice(2,9); }

function renderAll() {
    const data = loadData();
    document.getElementById('globalBudget').value = data.globalBudget || '';
    renderMilestones(data);
    renderExpenses(data);
    renderSummary(data);
    drawChart(data);
    populateExpenseSelect(data);
}

function renderMilestones(data) {
    const tbody = document.querySelector('#milestonesTable tbody');
    tbody.innerHTML = '';
    data.milestones.forEach(ms => {
        const spent = (data.expenses || []).filter(e=> e.milestoneId === ms.id).reduce((s,e)=> s + Number(e.amount||0), 0);
        const rem = Math.max(0, (ms.allocated||0) - spent);
        const pct = ms.allocated ? Math.round((spent / ms.allocated) * 100) : 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${ms.name}</td>
            <td><input data-id="${ms.id}" class="allocInput" type="number" min="0" step="0.01" value="${ms.allocated||0}"></td>
            <td>${currency(spent)}</td>
            <td>${currency(rem)}</td>
            <td>${pct}%</td>
            <td>
                <div class="btn-row">
                    <button data-id="${ms.id}" class="btn-small btnDelete">Delete</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // wire alloc input and delete buttons
    document.querySelectorAll('.allocInput').forEach(inp=>{
        inp.addEventListener('change', (e)=>{
            const id = inp.dataset.id;
            const val = Number(inp.value || 0);
            const data = loadData();
            const m = data.milestones.find(x=> x.id === id);
            if (m) { m.allocated = val; saveData(data); renderAll(); }
        });
    });
    document.querySelectorAll('.btnDelete').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const id = btn.dataset.id;
            let data = loadData();
            data.milestones = data.milestones.filter(m=> m.id !== id);
            data.expenses = data.expenses.filter(e=> e.milestoneId !== id);
            saveData(data);
            renderAll();
        });
    });
}

function renderExpenses(data) {
    const tbody = document.querySelector('#expensesTable tbody');
    tbody.innerHTML = '';
    (data.expenses || []).slice().reverse().forEach(exp=>{
        const ms = data.milestones.find(m=> m.id === exp.milestoneId);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(exp.date).toLocaleString()}</td>
            <td>${ms ? ms.name : '(milestone removed)'}</td>
            <td>${exp.description || ''}</td>
            <td>${currency(exp.amount)}</td>
            <td><button data-id="${exp.id}" class="btn-small btn-danger btnExpDel">Delete</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.querySelectorAll('.btnExpDel').forEach(b=>{
        b.addEventListener('click', ()=>{
            const id = b.dataset.id;
            let data = loadData();
            data.expenses = data.expenses.filter(e=> e.id !== id);
            saveData(data);
            renderAll();
        });
    });
}

function populateExpenseSelect(data) {
    const sel = document.getElementById('expenseMilestone');
    sel.innerHTML = '<option value="">Select milestone</option>';
    data.milestones.forEach(m=>{
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name;
        sel.appendChild(opt);
    });
}

function renderSummary(data) {
    const allocated = data.milestones.reduce((s,m)=> s + Number(m.allocated||0), 0);
    const spent = (data.expenses || []).reduce((s,e)=> s + Number(e.amount||0), 0);
    const remaining = Math.max(0, (Number(data.globalBudget||0) - spent));
    const consumption = allocated ? Math.round((spent / allocated) * 100) : (data.globalBudget ? Math.round((spent / data.globalBudget) * 100) : 0);

    document.getElementById('summaryAllocated').textContent = currency(allocated);
    document.getElementById('summarySpent').textContent = currency(spent);
    document.getElementById('summaryRemaining').textContent = currency(remaining);
    document.getElementById('summaryConsumption').textContent = (consumption||0) + '%';
}

function drawChart(data) {
    const canvas = document.getElementById('consumptionChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    // clear
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const padding = 40;
    const w = canvas.width - padding*2;
    const h = canvas.height - padding*2;
    const ms = data.milestones || [];
    if (!ms.length) {
        ctx.fillStyle = '#6b7280';
        ctx.font = '14px Poppins, sans-serif';
        ctx.fillText('No milestones to display', padding, padding + 20);
        return;
    }

    // compute max base
    const maxVal = Math.max(...ms.map(m => Number(m.allocated||0)), 1);
    const barH = Math.max(18, Math.floor(h / ms.length) - 8);
    ms.forEach((m, i) => {
        const y = padding + i*(barH + 12);
        // allocated bar (bg)
        const allocW = (Number(m.allocated||0) / maxVal) * w;
        ctx.fillStyle = '#eef2ff';
        ctx.fillRect(padding, y, w, barH);
        // allocated fill
        ctx.fillStyle = '#2563eb';
        ctx.fillRect(padding, y, allocW, barH);
        // spent overlay (green) proportional to allocated
        const spent = (data.expenses || []).filter(e=> e.milestoneId === m.id).reduce((s,e)=> s + Number(e.amount||0), 0);
        const spentW = m.allocated ? (spent / m.allocated) * allocW : 0;
        ctx.fillStyle = '#16a34a';
        ctx.fillRect(padding, y, Math.min(spentW, allocW), barH);
        // labels
        ctx.fillStyle = '#0f172a';
        ctx.font = '12px Poppins, sans-serif';
        ctx.fillText(m.name, padding + w + 12, y + barH/2 + 4);
        ctx.fillStyle = '#0f172a';
        ctx.fillText(currency(m.allocated), padding + 6, y + barH/2 + 4);
        ctx.fillStyle = '#065f46';
        ctx.fillText(currency(spent), padding + Math.min(allocW, w) - 50, y + barH/2 + 4);
    });
}

/* wire UI */
document.addEventListener('DOMContentLoaded', ()=>{
    // initial render
    renderAll();

    document.getElementById('milestoneForm').addEventListener('submit', (ev)=>{
        ev.preventDefault();
        const name = document.getElementById('milestoneName').value.trim();
        const alloc = Number(document.getElementById('milestoneAlloc').value || 0);
        if (!name) return;
        const data = loadData();
        const m = { id: uid(), name, allocated: alloc };
        data.milestones.push(m);
        saveData(data);
        document.getElementById('milestoneForm').reset();
        renderAll();
    });

    document.getElementById('expenseForm').addEventListener('submit', (ev)=>{
        ev.preventDefault();
        const milId = document.getElementById('expenseMilestone').value;
        const amount = Number(document.getElementById('expenseAmount').value || 0);
        const desc = document.getElementById('expenseDesc').value.trim();
        if (!milId || !amount) return;
        const data = loadData();
        const e = { id: 'e' + Math.random().toString(36).slice(2,9), milestoneId: milId, amount, description: desc, date: new Date().toISOString() };
        data.expenses = data.expenses || [];
        data.expenses.push(e);
        saveData(data);
        document.getElementById('expenseForm').reset();
        renderAll();
    });

    document.getElementById('globalBudget').addEventListener('change', (ev)=>{
        const val = Number(ev.target.value || 0);
        const data = loadData();
        data.globalBudget = val;
        saveData(data);
        renderAll();
    });

    document.getElementById('btnExportBudget').addEventListener('click', ()=>{
        const data = loadData();
        const rows = [];
        rows.push(['type','milestoneId','milestoneName','allocated','expenseId','expenseAmount','expenseDesc','date'].join(','));
        (data.milestones || []).forEach(m=>{
            const expenses = (data.expenses||[]).filter(e=> e.milestoneId === m.id);
            if (!expenses.length) {
                rows.push(['milestone',m.id, `"${m.name.replace(/"/g,'""')}"`, m.allocated,'','','',''].join(','));
            } else {
                expenses.forEach(ex=>{
                    rows.push(['expense',m.id, `"${m.name.replace(/"/g,'""')}"`, m.allocated, ex.id, ex.amount, `"${(ex.description||'').replace(/"/g,'""')}"`, ex.date].join(','));
                });
            }
        });
        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'budget_export.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    document.getElementById('btnImport').addEventListener('click', ()=>{
        const projects = JSON.parse(localStorage.getItem('projects') || '[]');
        if (!projects.length) { alert('No projects available to import.'); return; }
        const proj = projects[0];
        const data = loadData();
        if (proj.budget) data.globalBudget = proj.budget;
        saveData(data);
        renderAll();
        alert('Imported budget from first saved project (demo).');
    });

    window.addEventListener('resize', ()=> drawChart(loadData()));
});