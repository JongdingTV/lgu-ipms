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

/* Progress monitoring logic - fetches from database */
const projectsKey = 'projects';

async function getProjects() {
    try {
        const response = await fetch(getApiUrl('admin/progress_monitoring.php?action=load_projects'));
        if (response.ok) {
            const projects = await response.json();
            return projects;
        }
    } catch (error) {
        console.error('Error fetching projects:', error);
    }
    
    // Fallback to localStorage
    if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) {
        return IPMS_DATA.getProjects();
    }
    return JSON.parse(localStorage.getItem(projectsKey) || '[]');
}
function formatCurrency(n) {
    if (!n && n !== 0) return '—';
    return '₱' + Number(n).toLocaleString();
}

async function renderProjects() {
    const container = document.getElementById('projectsList');
    if (!container) return;
    const projects = await getProjects();
    const q = (document.getElementById('pmSearch')?.value || '').trim().toLowerCase();
    const status = document.getElementById('pmStatusFilter')?.value || '';
    const sector = document.getElementById('pmSectorFilter')?.value || '';
    const sort = document.getElementById('pmSort')?.value || 'createdAt_desc';

    // Check if view-only mode (for users) - always true for user view
    const isViewOnly = true;

    let filtered = projects.filter(p => {
        if (status && p.status !== status) return false;
        if (sector && p.sector !== sector) return false;
        if (!q) return true;
        return ((p.code||'') + ' ' + (p.name||'') + ' ' + (p.location||'')).toLowerCase().includes(q);
    });

    if (sort === 'createdAt_desc') filtered.sort((a,b)=> new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
    if (sort === 'createdAt_asc') filtered.sort((a,b)=> new Date(a.createdAt || 0) - new Date(b.createdAt || 0));
    if (sort === 'progress_desc') filtered.sort((a,b)=> (b.progress||0) - (a.progress||0));
    if (sort === 'progress_asc') filtered.sort((a,b)=> (a.progress||0) - (b.progress||0));

    if (!filtered.length) {
        container.innerHTML = '';
        document.getElementById('pmEmpty').style.display = 'block';
        return;
    } else {
        document.getElementById('pmEmpty').style.display = 'none';
    }

    const html = filtered.map((p, idx) => {
        const progress = Number(p.progress || p.percent_complete || 0);
        const pct = Math.min(100, Math.max(0, progress));
        const statusClass = (p.status||'').replace(/\s+/g,'').toLowerCase();
        const statusBadge = `<span class="status-badge ${statusClass}">${p.status||'N/A'}</span>`;
        return `
<div class="project-card" data-idx="${idx}">
  <div class="pc-head">
    <div class="pc-title">
      <strong>${p.code || ''} — ${p.name || 'Unnamed project'}</strong>
      <div class="pc-meta">${p.location || ''} • ${p.province || ''}</div>
    </div>
    <div class="pc-right">
      ${statusBadge}
      <div class="pc-budget">${formatCurrency(p.budget)}</div>
    </div>
  </div>

  <div class="pc-body">
    <div class="pc-info">
      <div><small>Sector</small><div>${p.sector || '—'}</div></div>
      <div><small>Duration</small><div>${p.duration_months || p.durationMonths || p.duration || '—'} months</div></div>
      <div><small>Start</small><div>${p.start_date || p.startDate || '—'}</div></div>
      <div><small>End</small><div>${p.end_date || p.endDate || '—'}</div></div>
    </div>

    <div class="pc-progress">
      <div class="progress-row">
        <div class="progress-label">Progress</div>
        <div class="progress-bar" aria-hidden>
          <div class="progress-fill" style="width:${pct}%;"></div>
        </div>
        <div class="progress-pct">${pct}%</div>
      </div>

      <div class="progress-controls" ${isViewOnly ? 'style="display:none;"' : ''}>
        <input class="progRange" type="range" min="0" max="100" value="${pct}">
        <input class="progNumber" type="number" min="0" max="100" value="${pct}">
        <select class="statusSelect">
          <option ${p.status==='Draft'?'selected':''}>Draft</option>
          <option ${p.status==='For Approval'?'selected':''}>For Approval</option>
          <option ${p.status==='Approved'?'selected':''}>Approved</option>
          <option ${p.status==='On-hold'?'selected':''}>On-hold</option>
          <option ${p.status==='Cancelled'?'selected':''}>Cancelled</option>
        </select>
        <button class="btn small btnSave">Save</button>
        <button class="btn small btnDetails">Details</button>
      </div>
    </div>
  </div>

  <div class="pc-footer">
    <small>Registered:</small> ${(p.created_at || p.createdAt ? new Date(p.created_at || p.createdAt).toLocaleString() : '—')}
  </div>

  <div class="pc-details" hidden>
    <h4>Details & Notes</h4>
    <div class="pc-desc">${(p.description||'No description')}</div>
    <div class="pc-attachments"><strong>Attachments:</strong> ${(p.attachments ? ((p.attachments.sitePhotos||[]).concat(p.attachments.plans||[]).concat(p.attachments.otherDocs||[])).join(', ') : 'None')}</div>
    <div class="pc-notes">
      <label>Notes</label>
      <textarea class="noteText" ${isViewOnly ? 'readonly' : ''}>${p.notes || ''}</textarea>
      <button class="btn small btnNoteSave" ${isViewOnly ? 'style="display:none;"' : ''}>Save Note</button>
    </div>
  </div>
</div>`;
    }).join('');

    container.innerHTML = html;

    // wire up controls per card
    container.querySelectorAll('.project-card').forEach(card => {
        const idx = Number(card.dataset.idx);
        const range = card.querySelector('.progRange');
        const num = card.querySelector('.progNumber');
        const pctDisplay = card.querySelector('.progress-pct');
        const fill = card.querySelector('.progress-fill');
        const statusSel = card.querySelector('.statusSelect');

        range?.addEventListener('input', () => {
            if (num) num.value = range.value;
            if (pctDisplay) pctDisplay.textContent = range.value + '%';
            if (fill) fill.style.width = range.value + '%';
        });

        num?.addEventListener('change', () => {
            let v = Number(num.value);
            if (v < 0) v = 0;
            if (v > 100) v = 100;
            num.value = v;
            if (range) range.value = v;
            if (pctDisplay) pctDisplay.textContent = v + '%';
            if (fill) fill.style.width = v + '%';
        });

        card.querySelector('.btnSave')?.addEventListener('click', () => {
            const projects = getProjects();
            const code = card.querySelector('strong')?.textContent.split('—')[0].trim();
            let foundIdx = projects.findIndex(pp => (pp.code||'') === (code||''));
            if (foundIdx === -1) foundIdx = idx;
            projects[foundIdx] = projects[foundIdx] || {};
            projects[foundIdx].progress = Number(range?.value || 0);
            projects[foundIdx].status = statusSel?.value || projects[foundIdx].status;
            projects[foundIdx].updatedAt = new Date().toISOString();
            saveProjects(projects);
            renderProjects();
        });

        card.querySelector('.btnDetails')?.addEventListener('click', () => {
            const details = card.querySelector('.pc-details');
            if (details) details.hidden = !details.hidden;
        });

        card.querySelector('.btnNoteSave')?.addEventListener('click', (ev) => {
            const projects = getProjects();
            const note = card.querySelector('.noteText')?.value || '';
            const code = card.querySelector('strong')?.textContent.split('—')[0].trim();
            let foundIdx = projects.findIndex(pp => (pp.code||'') === (code||''));
            if (foundIdx === -1) foundIdx = idx;
            projects[foundIdx] = projects[foundIdx] || {};
            projects[foundIdx].notes = note;
            saveProjects(projects);
            const btn = ev.target;
            if (btn) {
                btn.textContent = 'Saved';
                setTimeout(()=> btn.textContent = 'Save Note', 1200);
            }
        });
    });
}

// wire filters/search/sort
document.addEventListener('DOMContentLoaded', () => {
    // Hide export button in view-only mode
    const exportBtn = document.getElementById('exportCsv');
    if (true) { // Always view-only for user side
        exportBtn.style.display = 'none';
    }

    const controls = ['pmSearch','pmStatusFilter','pmSectorFilter','pmSort'];
    controls.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const ev = el.tagName === 'INPUT' ? 'input' : 'change';
        el.addEventListener(ev, renderProjects);
    });

    const exportBtn2 = document.getElementById('exportCsv');
    exportBtn2?.addEventListener('click', () => {
        const projects = getProjects();
        if (!projects.length) { alert('No projects to export'); return; }
        const keys = ['code','name','sector','location','province','budget','durationMonths','progress','status','createdAt'];
        const rows = projects.map(p => keys.map(k => `"${(p[k]||'').toString().replace(/"/g,'""')}"`).join(','));
        const csv = ['"' + keys.join('","') + '"', ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'projects_export.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    // initial render
    renderProjects();
});
