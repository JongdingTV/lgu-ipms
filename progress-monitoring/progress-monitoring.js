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

/* Debounce utility */
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

/* Progress monitoring logic - fetch from database */
let allProjects = [];
let isLoading = false;

function showLoadingState() {
    const container = document.getElementById('projectsList');
    if (container) {
        container.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading projects...</p>
            </div>
        `;
    }
}

function hideLoadingState() {
    isLoading = false;
}

function loadProjectsFromDatabase() {
    if (isLoading) return;
    isLoading = true;
    showLoadingState();
    
    fetch('progress_monitoring.php?action=load_projects')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            allProjects = Array.isArray(data) ? data : [];
            hideLoadingState();
            renderProjects();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            allProjects = [];
            hideLoadingState();
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
        return ((p.code || '') + ' ' + (p.name || '') + ' ' + (p.location || '')).toLowerCase().includes(q);
    });

    if (sort === 'createdAt_desc') filtered.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
    if (sort === 'createdAt_asc') filtered.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
    if (sort === 'name_asc') filtered.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    if (sort === 'budget_high') filtered.sort((a, b) => Number(b.budget || 0) - Number(a.budget || 0));

    if (!filtered.length) {
        container.innerHTML = '';
        const emptyState = document.getElementById('pmEmpty');
        if (emptyState) emptyState.style.display = 'block';
        return;
    } else {
        const emptyState = document.getElementById('pmEmpty');
        if (emptyState) emptyState.style.display = 'none';
    }

    const html = filtered.map((p, idx) => {
        const progress = Number(p.progress || 0);
        const pct = Math.min(100, Math.max(0, progress));
        const statusClass = (p.status || 'draft').replace(/\s+/g, '-').toLowerCase();
        
        return `
<div class="project-card" data-project-id="${p.id || idx}">
  <h4>${p.code || 'N/A'} — ${p.name || 'Unnamed Project'}</h4>
  
  <div class="project-meta">
    <div class="project-meta-item">
      <span class="project-meta-label">Location:</span>
      <span class="project-meta-value">${p.location || '—'}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Sector:</span>
      <span class="project-meta-value">${p.sector || '—'}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Budget:</span>
      <span class="project-meta-value">${formatCurrency(p.budget)}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Duration:</span>
      <span class="project-meta-value">${p.duration_months || p.duration || '—'} months</span>
    </div>
  </div>

  <div class="progress-container">
    <div class="progress-label">
      <span>Progress</span>
      <span>${pct}%</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: ${pct}%;"></div>
    </div>
  </div>

  <span class="project-status ${statusClass}">${p.status || 'Draft'}</span>

  <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 0.85rem; color: #9ca3af;">
    <strong>Description:</strong> ${p.description || 'No description available'}
    ${p.project_manager ? '<br><strong>Manager:</strong> ' + p.project_manager : ''}
    ${p.start_date ? '<br><strong>Start:</strong> ' + p.start_date : ''}
    ${p.end_date ? '<br><strong>End:</strong> ' + p.end_date : ''}
  </div>
</div>`;
    }).join('');

    container.innerHTML = html;
}


// wire filters/search/sort with debouncing
document.addEventListener('DOMContentLoaded', () => {
    const debouncedRender = debounce(renderProjects, 300);
    
    const controls = ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmSort'];
    controls.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const ev = el.tagName === 'INPUT' ? 'input' : 'change';
        el.addEventListener(ev, debouncedRender);
    });

    const exportBtn = document.getElementById('exportCsv');
    exportBtn?.addEventListener('click', () => {
        if (!allProjects.length) { 
            alert('No projects to export'); 
            return; 
        }
        const filtered = allProjects;
        const keys = ['code', 'name', 'sector', 'location', 'budget', 'status', 'progress'];
        const headers = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(',');
        const rows = filtered.map(p => 
            keys.map(k => {
                let val = p[k] || '';
                if (k === 'budget') val = '₱' + Number(val || 0).toLocaleString();
                return `"${String(val).replace(/"/g, '""')}"`;
            }).join(',')
        );
        const csv = [headers, ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `projects_export_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
    });

    // Load projects from database
    loadProjectsFromDatabase();
});