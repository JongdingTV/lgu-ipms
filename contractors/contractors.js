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

// CRUD for Contractors
let contractors = JSON.parse(localStorage.getItem('contractors')) || [];
let editingId = null;

function saveContractors() {
    localStorage.setItem('contractors', JSON.stringify(contractors));
}

function renderContractors() {
    const list = document.querySelector('.ctr-list');
    list.innerHTML = '';
    
    const search = document.getElementById('ctrSearch').value.toLowerCase();
    const statusFilter = document.getElementById('ctrFilterStatus').value;
    
    const filtered = contractors.filter(c => {
        if (statusFilter && c.status !== statusFilter) return false;
        if (search && !c.company.toLowerCase().includes(search) && !c.license.toLowerCase().includes(search)) return false;
        return true;
    });
    
    if (filtered.length === 0) {
        list.innerHTML = '<div class="ctr-empty">No contractors found.</div>';
        return;
    }
    
    filtered.forEach(c => {
        const item = document.createElement('div');
        item.className = 'ctr-item';
        item.tabIndex = 0;
        item.dataset.id = c.id;
        item.innerHTML = `
            <img class="ctr-avatar" src="../contractors/contractors.png" alt="">
            <div class="ctr-meta">
                <strong>${c.company}</strong>
                <small>License # ${c.license}</small>
            </div>
            <div class="ctr-right">
                <div class="ctr-rating">${'★'.repeat(Math.floor(c.rating || 0))}${'☆'.repeat(5 - Math.floor(c.rating || 0))}</div>
                <div class="ctr-status ${c.status.toLowerCase()}">${c.status}</div>
                <div class="ctr-actions">
                    <button onclick="editContractor('${c.id}')">Edit</button>
                    <button onclick="deleteContractor('${c.id}')">Delete</button>
                </div>
            </div>
        `;
        list.appendChild(item);
    });
    
    // Update stats
    document.getElementById('ctrCount').textContent = contractors.length;
    document.getElementById('ctrActive').textContent = contractors.filter(c => c.status === 'Active').length;
    const avgRating = contractors.reduce((sum, c) => sum + (c.rating || 0), 0) / contractors.length;
    document.getElementById('ctrAvgRating').textContent = avgRating.toFixed(1);
    document.getElementById('ctrCompl').textContent = contractors.filter(c => c.status === 'Suspended' || c.status === 'Blacklisted').length;
}

function editContractor(id) {
    const c = contractors.find(c => c.id === id);
    if (!c) return;
    editingId = id;
    document.getElementById('ctrCompany').value = c.company;
    document.getElementById('ctrLicense').value = c.license;
    document.getElementById('ctrContact').value = c.contact;
    document.getElementById('ctrAddress').value = c.address;
    document.getElementById('ctrStatus').value = c.status;
    document.getElementById('formTitle').textContent = 'Edit Contractor';
    document.getElementById('contractorForm').scrollIntoView({ behavior: 'smooth' });
}

function deleteContractor(id) {
    if (confirm('Are you sure you want to delete this contractor?')) {
        contractors = contractors.filter(c => c.id !== id);
        saveContractors();
        renderContractors();
    }
}

document.getElementById('ctrAdd').addEventListener('click', () => {
    editingId = null;
    document.getElementById('contractorForm').reset();
    document.getElementById('formTitle').textContent = 'Add Contractor';
});

document.getElementById('cancelEdit').addEventListener('click', () => {
    editingId = null;
    document.getElementById('contractorForm').reset();
    document.getElementById('formTitle').textContent = 'Add Contractor';
});

document.getElementById('contractorForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const c = {
        id: editingId || 'c-' + Math.random().toString(36).substr(2, 9),
        company: document.getElementById('ctrCompany').value,
        license: document.getElementById('ctrLicense').value,
        contact: document.getElementById('ctrContact').value,
        address: document.getElementById('ctrAddress').value,
        status: document.getElementById('ctrStatus').value,
        rating: 4 // default
    };
    
    if (editingId) {
        const index = contractors.findIndex(c => c.id === editingId);
        contractors[index] = c;
    } else {
        contractors.push(c);
    }
    
    saveContractors();
    renderContractors();
    document.getElementById('contractorForm').reset();
    editingId = null;
    document.getElementById('formTitle').textContent = 'Add Contractor';
});

document.getElementById('ctrSearch').addEventListener('input', renderContractors);
document.getElementById('ctrFilterStatus').addEventListener('change', renderContractors);

// Initial render
document.addEventListener('DOMContentLoaded', renderContractors);

/* Added features JS: performance chart, checklist, documents, feedback */
const CT_KEY = 'contractors_module_v1';

function loadCTData() {
    try { return JSON.parse(localStorage.getItem(CT_KEY) || '{}'); }
    catch (e) { return {}; }
}
function saveCTData(data) { localStorage.setItem(CT_KEY, JSON.stringify(data)); }

function ensureContractorData(id) {
    const store = loadCTData();
    store[id] = store[id] || { checklist: [], documents: [], feedback: [], ratings: [] };
    saveCTData(store);
    return store[id];
}

function renderForContractor(id) {
    if (!id) return;
    const sel = ensureContractorData(id);
    renderChart(sel.ratings || []);
    renderChecklist(id);
    renderDocuments(id);
    renderFeedback(id);
    // populate header meta placeholders (can be extended)
    document.getElementById('ctrName').textContent = document.querySelector(`.ctr-item[data-id="${id}"] .ctr-meta strong`)?.textContent || 'Contractor';
}

function renderChart(ratings) {
    const canvas = document.getElementById('ctrPerfChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.clientWidth * (window.devicePixelRatio || 1);
    const h = canvas.height = 160 * (window.devicePixelRatio || 1);
    ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
    ctx.clearRect(0,0,canvas.width,canvas.height);
    // prepare 12 months
    const months = 12;
    let values = ratings.slice(-12);
    if (values.length < months) {
        // fill with recent or random sample 3.5-4.5
        while (values.length < months) values.unshift(Math.round((3.5 + Math.random())*10)/10);
    }
    // draw bars
    const barW = Math.floor(canvas.clientWidth / months) - 6;
    const max = 5;
    values.forEach((v,i) => {
        const x = 6 + i*(barW+6);
        const barH = (v / max) * (canvas.clientHeight - 30);
        ctx.fillStyle = '#2563eb';
        ctx.fillRect(x, canvas.clientHeight - barH - 12, barW, barH);
        ctx.fillStyle = '#0f172a';
        ctx.font = '11px Poppins, sans-serif';
        ctx.fillText(v.toFixed(1), x, canvas.clientHeight - barH - 16);
    });
    // axis label
    ctx.fillStyle = '#6b7280';
    ctx.font = '12px Poppins, sans-serif';
    ctx.fillText('Last 12 months performance (1-5)', 8, 12);
}

function renderChecklist(id) {
    const container = document.getElementById('checklistItems');
    container.innerHTML = '';
    const data = loadCTData();
    const ct = data[id] || { checklist: [] };
    const items = ct.checklist || [];
    if (!items.length) container.innerHTML = '<div class="ctr-empty">No checklist items.</div>';
    items.forEach(it => {
        const div = document.createElement('div');
        div.className = 'check-item';
        const expiry = new Date(it.expiry);
        const expired = expiry < new Date();
        div.innerHTML = `
            <div class="meta">
                <div class="check-name">${it.name}</div>
                <div class="check-expiry ${expired ? 'expired' : ''}">Expiry: ${it.expiry}</div>
            </div>
            <div class="check-actions">
                <button data-id="${it.id}" class="btn renew">Renew</button>
                <button data-id="${it.id}" class="btn delete">Delete</button>
            </div>
        `;
        container.appendChild(div);
        div.querySelector('.renew')?.addEventListener('click', ()=> {
            const newDate = prompt('Enter new expiry date (YYYY-MM-DD)', it.expiry);
            if (!newDate) return;
            it.expiry = newDate;
            saveCTData(data);
            renderChecklist(id);
        });
        div.querySelector('.delete')?.addEventListener('click', ()=> {
            data[id].checklist = data[id].checklist.filter(x=> x.id !== it.id);
            saveCTData(data);
            renderChecklist(id);
        });
    });
}

document.getElementById('checklistForm')?.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const name = document.getElementById('checkName').value.trim();
    const expiry = document.getElementById('checkExpiry').value;
    const activeId = document.querySelector('.ctr-item.active')?.dataset.id;
    if (!activeId) { alert('Select a contractor first'); return; }
    const data = loadCTData();
    data[activeId] = data[activeId] || { checklist: [], documents: [], feedback: [], ratings: [] };
    data[activeId].checklist.push({ id: 'ch'+Math.random().toString(36).slice(2,9), name, expiry });
    saveCTData(data);
    ev.target.reset();
    renderChecklist(activeId);
});

function renderDocuments(id) {
    const container = document.getElementById('docList');
    container.innerHTML = '';
    const data = loadCTData();
    const docs = (data[id] && data[id].documents) || [];
    if (!docs.length) container.innerHTML = '<div class="ctr-empty">No documents uploaded.</div>';
    docs.forEach(d => {
        const div = document.createElement('div');
        div.className = 'doc-item';
        div.innerHTML = `<div>
            <a href="${d.url}" target="_blank" rel="noopener">${d.name}</a>
            <div class="doc-meta">${new Date(d.uploadedAt).toLocaleString()}</div>
        </div>
        <div style="margin-left:auto"><button data-id="${d.id}" class="btn deleteDoc">Delete</button></div>`;
        container.appendChild(div);
        div.querySelector('.deleteDoc')?.addEventListener('click', () => {
            const all = loadCTData();
            all[id].documents = all[id].documents.filter(x=> x.id !== d.id);
            saveCTData(all);
            renderDocuments(id);
        });
    });
}

document.getElementById('docUpload')?.addEventListener('change', (ev) => {
    const f = ev.target.files[0];
    const activeId = document.querySelector('.ctr-item.active')?.dataset.id;
    if (!f) return;
    if (!activeId) { alert('Select contractor before uploading'); ev.target.value=''; return; }
    const reader = new FileReader();
    reader.onload = function(e) {
        const url = e.target.result;
        const data = loadCTData();
        data[activeId] = data[activeId] || { checklist: [], documents: [], feedback: [], ratings: [] };
        data[activeId].documents.push({ id: 'd'+Math.random().toString(36).slice(2,9), name: f.name, url, uploadedAt: new Date().toISOString() });
        saveCTData(data);
        renderDocuments(activeId);
    };
    reader.readAsDataURL(f);
    ev.target.value = '';
});

function renderFeedback(id) {
    const container = document.getElementById('feedbackList');
    container.innerHTML = '';
    const data = loadCTData();
    const items = (data[id] && data[id].feedback) || [];
    if (!items.length) container.innerHTML = '<div class="ctr-empty">No feedback notes.</div>';
    items.slice().reverse().forEach(f => {
        const div = document.createElement('div');
        div.className = 'feedback-item';
        div.innerHTML = `<div class="meta">${new Date(f.date).toLocaleString()} • ${f.author||'Inspector'}</div>
            <div class="body">${(f.text||'')}</div>
            ${f.attachment ? `<div class="doc-meta"><a href="${f.attachment.url}" target="_blank">${f.attachment.name}</a></div>` : ''}`;
        container.appendChild(div);
    });
}

document.getElementById('saveFeedback')?.addEventListener('click', ()=> {
    const activeId = document.querySelector('.ctr-item.active')?.dataset.id;
    if (!activeId) { alert('Select contractor to attach feedback'); return; }
    const text = document.getElementById('feedbackText').value.trim();
    const fileInput = document.getElementById('feedbackFile');
    const file = fileInput?.files?.[0];
    const data = loadCTData();
    const ct = data[activeId] = data[activeId] || { checklist: [], documents: [], feedback: [], ratings: [] };
    const note = { id: 'f'+Math.random().toString(36).slice(2,9), text, date: new Date().toISOString(), author: 'Inspector' };
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            note.attachment = { name: file.name, url: e.target.result };
            ct.feedback = ct.feedback || [];
            ct.feedback.push(note);
            saveCTData(data);
            document.getElementById('feedbackText').value = '';
            fileInput.value = '';
            renderFeedback(activeId);
        };
        reader.readAsDataURL(file);
    } else {
        ct.feedback = ct.feedback || [];
        ct.feedback.push(note);
        saveCTData(data);
        document.getElementById('feedbackText').value = '';
        renderFeedback(activeId);
    }
});


// wire selection from list
document.querySelectorAll('.ctr-item').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.ctr-item').forEach(x=> x.classList.remove('active'));
        el.classList.add('active');
        const id = el.dataset.id;
        renderForContractor(id);
    });
});

// initial: select first if exists
document.addEventListener('DOMContentLoaded', () => {
    const first = document.querySelector('.ctr-item');
    if (first) { first.classList.add('active'); renderForContractor(first.dataset.id); }
    // redraw chart on resize
    window.addEventListener('resize', ()=> {
        const id = document.querySelector('.ctr-item.active')?.dataset.id;
        if (id) renderForContractor(id);
    });
});