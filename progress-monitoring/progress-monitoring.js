console.log('progress-monitoring.js loaded');

// Sidebar toggle - use optional chaining to avoid errors
const sidebarToggle = document.getElementById('toggleSidebar');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
        e.preventDefault();
        
        const navbar = document.getElementById('navbar');
        const body = document.body;
        const toggleBtn = document.getElementById('showSidebarBtn');
        
        navbar.classList.toggle('hidden');
        body.classList.toggle('sidebar-hidden');
        toggleBtn.classList.toggle('show');
    });
}

const sidebarShow = document.getElementById('toggleSidebarShow');
if (sidebarShow) {
    sidebarShow.addEventListener('click', function(e) {
        e.preventDefault();
        
        const navbar = document.getElementById('navbar');
        const body = document.body;
        const toggleBtn = document.getElementById('showSidebarBtn');
        
        navbar.classList.toggle('hidden');
        body.classList.toggle('sidebar-hidden');
        toggleBtn.classList.toggle('show');
    });
}

/* Progress monitoring logic - fetch from database */
let allProjects = [];

function loadProjectsFromDatabase() {
    console.log('Fetching projects from database...');
    fetch('progress_monitoring.php?action=load_projects')
        .then(response => {
            console.log('Response received:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Projects loaded:', data);
            allProjects = data;
            renderProjects();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            allProjects = [];
            renderProjects();
        });
}

function formatCurrency(n) {
    if (!n && n !== 0) return '—';
    return '₱' + Number(n).toLocaleString();
}

function renderProjects() {
    const container = document.getElementById('projectsList');
    if (!container) return;
    const projects = allProjects;
    const q = (document.getElementById('pmSearch')?.value || '').trim().toLowerCase();
    const status = document.getElementById('pmStatusFilter')?.value || '';
    const sector = document.getElementById('pmSectorFilter')?.value || '';
    const sort = document.getElementById('pmSort')?.value || 'createdAt_desc';

    let filtered = projects.filter(p => {
        if (status && p.status !== status) return false;
        if (sector && p.sector !== sector) return false;
        if (!q) return true;
        return ((p.code||'') + ' ' + (p.name||'') + ' ' + (p.location||'')).toLowerCase().includes(q);
    });

    if (sort === 'createdAt_desc') filtered.sort((a,b)=> new Date(b.created_at || 0) - new Date(a.created_at || 0));
    if (sort === 'createdAt_asc') filtered.sort((a,b)=> new Date(a.created_at || 0) - new Date(b.created_at || 0));

    if (!filtered.length) {
        container.innerHTML = '';
        document.getElementById('pmEmpty').style.display = 'block';
        return;
    } else {
        document.getElementById('pmEmpty').style.display = 'none';
    }

    const html = filtered.map((p, idx) => {
        const progress = Number(p.progress || 0);
        const pct = Math.min(100, Math.max(0, progress));
        const statusClass = (p.status||'').replace(/\s+/g,'').toLowerCase();
        const statusBadge = `<span class="badge status-${statusClass}">${p.status||'N/A'}</span>`;
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
      <div><small>Duration</small><div>${p.duration_months || p.duration || '—'} months</div></div>
      <div><small>Start</small><div>${p.start_date || '—'}</div></div>
      <div><small>End</small><div>${p.end_date || '—'}</div></div>
    </div>

    <div class="pc-progress">
      <div class="progress-row">
        <div class="progress-label">Progress</div>
        <div class="progress-bar" aria-hidden>
          <div class="progress-fill" style="width:${pct}%;"></div>
        </div>
        <div class="progress-pct">${pct}%</div>
      </div>
    </div>
  </div>

  <div class="pc-footer">
    <small>Registered:</small> ${(p.created_at ? new Date(p.created_at).toLocaleString() : '—')}
  </div>

  <div class="pc-details">
    <h4>Details</h4>
    <div class="pc-desc">${(p.description||'No description')}</div>
    <div class="pc-manager"><strong>Project Manager:</strong> ${p.project_manager || 'N/A'}</div>
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
    const controls = ['pmSearch','pmStatusFilter','pmSectorFilter','pmSort'];
    controls.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const ev = el.tagName === 'INPUT' ? 'input' : 'change';
        el.addEventListener(ev, renderProjects);
    });

    const exportBtn = document.getElementById('exportCsv');
    exportBtn?.addEventListener('click', () => {
        if (!allProjects.length) { alert('No projects to export'); return; }
        const keys = ['code','name','sector','location','province','budget','duration_months','status','created_at'];
        const rows = allProjects.map(p => keys.map(k => `"${(p[k]||'').toString().replace(/"/g,'""')}"`).join(','));
        const csv = ['"' + keys.join('","') + '"', ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'projects_export.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    // Load projects from database
    loadProjectsFromDatabase();
});