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

function updateStatistics() {
    const total = allProjects.length;
    const approved = allProjects.filter(p => p.status === 'Approved').length;
    const inProgress = allProjects.filter(p => {
        const prog = Number(p.progress || 0);
        return prog > 0 && prog < 100;
    }).length;
    const completed = allProjects.filter(p => {
        const prog = Number(p.progress || 0);
        return prog === 100 || p.status === 'Completed';
    }).length;
    
    let totalContractors = 0;
    allProjects.forEach(p => {
        totalContractors += (p.assigned_contractors || []).length;
    });
    
    const statTotal = document.getElementById('statTotal');
    const statApproved = document.getElementById('statApproved');
    const statInProgress = document.getElementById('statInProgress');
    const statCompleted = document.getElementById('statCompleted');
    const statContractors = document.getElementById('statContractors');
    
    if (statTotal) statTotal.textContent = total;
    if (statApproved) statApproved.textContent = approved;
    if (statInProgress) statInProgress.textContent = inProgress;
    if (statCompleted) statCompleted.textContent = completed;
    if (statContractors) statContractors.textContent = totalContractors;
}

function loadProjectsFromDatabase() {
    if (isLoading) return;
    isLoading = true;
    showLoadingState();
    
    console.log('=== LOADING PROJECTS ===');
    console.log('Current URL:', window.location.href);
    console.log('Fetching from: progress_monitoring.php?action=load_projects');
    
    // Use getApiUrl to ensure it works from any location
    const fetchUrl = getApiUrl('progress-monitoring/progress_monitoring.php?action=load_projects');
    console.log('Full fetch URL:', fetchUrl);
    
    fetch(fetchUrl)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            console.log('Response headers:', {
                'content-type': response.headers.get('content-type')
            });
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response text length:', text.length);
            console.log('First 500 chars of response:', text.substring(0, 500));
            try {
                const data = JSON.parse(text);
                console.log('✅ Parsed JSON data successfully');
                console.log('Data is array?', Array.isArray(data));
                console.log('Data length:', Array.isArray(data) ? data.length : 'N/A');
                console.log('First item:', Array.isArray(data) && data.length > 0 ? data[0] : 'N/A');
                
                if (data.error) {
                    console.error('API returned error:', data.error);
                    allProjects = [];
                } else {
                    allProjects = Array.isArray(data) ? data : [];
                }
                
                console.log('Total projects loaded:', allProjects.length);
                hideLoadingState();
                updateStatistics();
                renderProjects();
            } catch (parseError) {
                console.error('❌ JSON parse error:', parseError);
                console.error('Text that failed to parse:', text);
                allProjects = [];
                hideLoadingState();
                renderProjects();
                const container = document.getElementById('projectsList');
                if (container) {
                    container.innerHTML = `<div style="color: red; padding: 20px; background: #ffe0e0; border-radius: 8px; border: 1px solid red;">JSON Parse Error: ${parseError.message}<br>Response: ${text.substring(0, 200)}</div>`;
                }
            }
        })
        .catch(error => {
            console.error('❌ FETCH ERROR:', error);
            allProjects = [];
            hideLoadingState();
            renderProjects();
            const container = document.getElementById('projectsList');
            if (container) {
                container.innerHTML = `<div style="color: red; padding: 20px; background: #ffe0e0; border-radius: 8px; border: 1px solid red;">Fetch Error: ${error.message}</div>`;
            }
        });
}

function formatCurrency(n) {
    if (!n && n !== 0) return '—';
    return '₱' + Number(n).toLocaleString();
}

function renderContractorsBadges(contractors) {
    if (!contractors || contractors.length === 0) {
        return '<div class="contractors-badge empty">No Contractors</div>';
    }
    
    const badges = contractors.slice(0, 3).map(c => `
        <div class="contractor-badge" title="${c.company}">
            <span class="contractor-name">${c.company}</span>
            ${c.rating ? '<span class="contractor-rating">⭐ ' + c.rating + '</span>' : ''}
        </div>
    `).join('');
    
    const extra = contractors.length > 3 ? `<div class="contractor-badge extra">+${contractors.length - 3} more</div>` : '';
    
    return badges + extra;
}

function getRiskLevel(progress, status, endDate) {
    // Calculate risk level based on progress and dates
    if (status === 'Cancelled' || status === 'On-hold') return 'low';
    
    const today = new Date();
    const end = endDate ? new Date(endDate) : null;
    
    // If no end date, consider medium risk
    if (!end) return 'medium';
    
    const daysRemaining = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    const prog = Number(progress || 0);
    
    // High risk: behind schedule
    if (daysRemaining <= 30 && prog < 80) return 'high';
    if (daysRemaining <= 0) return 'critical';
    
    // Medium risk
    if (daysRemaining <= 60 && prog < 50) return 'medium';
    
    return 'low';
}

function getProgressColor(progress) {
    const p = Number(progress || 0);
    if (p >= 80) return '#10b981';      // Green
    if (p >= 50) return '#f59e0b';      // Orange
    if (p >= 25) return '#f97316';      // Orange-red
    return '#ef4444';                    // Red
}

function getTimelineStatus(startDate, endDate) {
    if (!startDate || !endDate) return { status: 'unknown', label: '?' };
    
    const today = new Date();
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (today < start) return { status: 'upcoming', label: '⏳ Upcoming' };
    if (today > end) return { status: 'overdue', label: '⚠️ Overdue' };
    return { status: 'active', label: '▶️ Active' };
}

function renderProjects() {
    const container = document.getElementById('projectsList');
    if (!container) return;
    
    const projects = allProjects || [];
    const q = (document.getElementById('pmSearch')?.value || '').trim().toLowerCase();
    const status = document.getElementById('pmStatusFilter')?.value || '';
    const sector = document.getElementById('pmSectorFilter')?.value || '';
    const progressFilter = document.getElementById('pmProgressFilter')?.value || '';
    const contractorFilter = document.getElementById('pmContractorFilter')?.value || '';
    const sort = document.getElementById('pmSort')?.value || 'createdAt_desc';

    let filtered = projects.filter(p => {
        if (status && (p.status || '').trim() !== status.trim()) return false;
        if (sector && (p.sector || '').trim() !== sector.trim()) return false;
        
        // Progress filter
        if (progressFilter && p.progress !== undefined) {
            const progress = Number(p.progress || 0);
            if (progressFilter === '0-25' && !(progress >= 0 && progress <= 25)) return false;
            if (progressFilter === '25-50' && !(progress > 25 && progress <= 50)) return false;
            if (progressFilter === '50-75' && !(progress > 50 && progress <= 75)) return false;
            if (progressFilter === '75-100' && !(progress > 75 && progress <= 100)) return false;
        }
        
        // Contractor filter
        if (contractorFilter === 'assigned' && (!p.assigned_contractors || p.assigned_contractors.length === 0)) return false;
        if (contractorFilter === 'unassigned' && (p.assigned_contractors && p.assigned_contractors.length > 0)) return false;
        
        if (!q) return true;
        const searchText = ((p.code || '') + ' ' + (p.name || '') + ' ' + (p.location || '')).toLowerCase();
        return searchText.includes(q);
    });

    if (sort === 'createdAt_desc') filtered.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
    if (sort === 'createdAt_asc') filtered.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
    if (sort === 'progress_desc') filtered.sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0));
    if (sort === 'progress_asc') filtered.sort((a, b) => Number(a.progress || 0) - Number(b.progress || 0));

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
        const contractorCount = (p.assigned_contractors || []).length;
        const riskLevel = getRiskLevel(progress, p.status, p.end_date);
        const progressColor = getProgressColor(progress);
        const timeline = getTimelineStatus(p.start_date, p.end_date);
        
        return `
<div class="project-card risk-${riskLevel}" data-project-id="${p.id || idx}">
  <div class="project-header">
    <div class="project-title-section">
      <h4>${p.code || 'N/A'} — ${p.name || 'Unnamed Project'}</h4>
      <span class="timeline-badge timeline-${timeline.status}">${timeline.label}</span>
    </div>
    <div class="project-header-right">
      <span class="risk-badge risk-${riskLevel}">
        ${riskLevel === 'critical' ? '🚨 CRITICAL' : riskLevel === 'high' ? '⚠️ HIGH' : riskLevel === 'medium' ? '⚡ MEDIUM' : '✅ LOW'}
      </span>
      <span class="project-status ${statusClass}">${p.status || 'Draft'}</span>
    </div>
  </div>
  
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
      <span>Completion</span>
      <span style="font-weight: 700; color: ${progressColor};">${pct}%</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: ${pct}%; background-color: ${progressColor};"></div>
    </div>
  </div>

  <div class="contractors-section">
    <div class="contractors-title">
      <span>👷 Assigned Contractors</span>
      <span class="contractor-count">${contractorCount}</span>
    </div>
    ${renderContractorsBadges(p.assigned_contractors)}
  </div>

  <div class="project-timeline-info">
    <div class="timeline-item">
      <span class="timeline-label">Start:</span>
      <span class="timeline-value">${p.start_date ? new Date(p.start_date).toLocaleDateString() : '—'}</span>
    </div>
    <div class="timeline-item">
      <span class="timeline-label">End:</span>
      <span class="timeline-value">${p.end_date ? new Date(p.end_date).toLocaleDateString() : '—'}</span>
    </div>
  </div>

  <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 0.85rem; color: #9ca3af;">
    <strong>Description:</strong> ${p.description || 'No description available'}
    ${p.project_manager ? '<br><strong>Manager:</strong> ' + p.project_manager : ''}
  </div>
</div>`;
    }).join('');

    container.innerHTML = html;
}


// wire filters/search/sort with debouncing
document.addEventListener('DOMContentLoaded', () => {
    const debouncedRender = debounce(renderProjects, 300);
    
    const controls = ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmContractorFilter', 'pmSort'];
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