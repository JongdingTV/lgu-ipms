'use strict';

(function () {
  const path = (window.location.pathname || '').replace(/\\/g, '/');
  const isAdmin = path.includes('/admin/') && !/\/admin\/(index|forgot-password|change-password|logout)\.php$/i.test(path);
  if (!isAdmin) return;

  function $(sel, root = document) { return root.querySelector(sel); }
  function $$(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }
  function isFn(fn) { return typeof fn === 'function'; }

  // Fallback API resolver for legacy scripts
  if (!isFn(window.getApiUrl)) {
    const base = path.replace(/\/admin\/.*$/, '/');
    window.APP_ROOT = base;
    window.getApiUrl = function getApiUrl(endpoint) {
      const cleaned = String(endpoint || '').replace(/^\/+/, '');
      return base + cleaned;
    };
  }

  function initTopSidebarToggle() {
    if ($('.top-sidebar-toggle')) return;
    const btn = document.createElement('button');
    btn.className = 'top-sidebar-toggle';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Toggle sidebar');
    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>';
    document.body.appendChild(btn);

    const saved = localStorage.getItem('ipms_admin_sidebar_hidden');
    if (saved === '1') document.body.classList.add('sidebar-hidden');

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const hidden = document.body.classList.toggle('sidebar-hidden');
      localStorage.setItem('ipms_admin_sidebar_hidden', hidden ? '1' : '0');
      $$('.nav-item-group.open').forEach((g) => g.classList.remove('open'));
    });
  }

  function initUnifiedDropdowns() {
    $$('.nav-item-group').forEach((group) => {
      const trigger = $('.nav-main-item', group);
      if (!trigger || trigger.dataset.enterpriseBound === '1') return;
      trigger.dataset.enterpriseBound = '1';

      trigger.setAttribute('role', 'button');
      trigger.setAttribute('aria-haspopup', 'true');
      trigger.setAttribute('aria-expanded', group.classList.contains('open') ? 'true' : 'false');

      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const willOpen = !group.classList.contains('open');
        $$('.nav-item-group.open').forEach((other) => {
          if (other !== group) other.classList.remove('open');
        });
        group.classList.toggle('open', willOpen);
        trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      }, true);
    });

    document.addEventListener('click', (e) => {
      if (e.target.closest('.nav-item-group')) return;
      $$('.nav-item-group.open').forEach((g) => g.classList.remove('open'));
    });
  }

  function initLogoutModal() {
    const hiddenModalStyle = [
      'position: fixed !important',
      'inset: 0 !important',
      'z-index: 1300 !important',
      'display: none !important',
      'visibility: hidden !important',
      'opacity: 0 !important',
      'pointer-events: none !important',
      'align-items: center !important',
      'justify-content: center !important',
      'background: rgba(7, 18, 33, 0.52) !important',
      'backdrop-filter: blur(4px) !important'
    ].join('; ');
    const shownModalStyle = [
      'position: fixed !important',
      'inset: 0 !important',
      'z-index: 1300 !important',
      'display: flex !important',
      'visibility: visible !important',
      'opacity: 1 !important',
      'pointer-events: auto !important',
      'align-items: center !important',
      'justify-content: center !important',
      'background: rgba(7, 18, 33, 0.52) !important',
      'backdrop-filter: blur(4px) !important'
    ].join('; ');

    let modal = $('#enterpriseLogoutModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'enterpriseLogoutModal';
      modal.className = 'admin-logout-modal';
      modal.style.cssText = hiddenModalStyle;
      modal.innerHTML = `
        <div class="admin-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
          <h3 id="logoutModalTitle">Confirm Logout</h3>
          <p>You are about to end your session. Continue?</p>
          <div class="admin-logout-actions">
            <button type="button" class="btn-cancel">Cancel</button>
            <button type="button" class="btn-logout">Logout</button>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }

    let nextUrl = '/admin/logout.php';
    const close = () => {
      modal.classList.remove('show');
      modal.style.cssText = hiddenModalStyle;
    };

    // Enforce hidden state on init to avoid showing modal content inline.
    close();

    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });
    $('.btn-cancel', modal).addEventListener('click', close);
    $('.btn-logout', modal).addEventListener('click', () => { window.location.href = nextUrl; });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });

    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href*="logout.php"]');
      if (!link) return;
      
      // Check if link is in admin nav or main content areas
      const inAdmin = link.closest('.nav') || link.closest('.sidebar') || link.closest('.main-content');
      if (!inAdmin) return;
      
      e.preventDefault();
      e.stopPropagation();
      
      nextUrl = link.getAttribute('href') || '/admin/logout.php';
      modal.classList.add('show');
      modal.style.cssText = shownModalStyle;
      $('.btn-logout', modal).focus();
    }, true);
  }

  function initDashboardBudgetHoldReveal() {
    if (!/\/admin\/dashboard\.php$/i.test(path)) return;
    const budgetCard = $('#budgetCard');
    const budgetValue = $('#budgetValue');
    const budgetBtn = $('#budgetVisibilityToggle');
    if (!budgetCard || !budgetValue || !budgetBtn) return;

    const masked = '********';
    const actual = '\u20B1' + (budgetCard.getAttribute('data-budget') || '0.00');
    let holding = false;
    budgetValue.textContent = masked;
    budgetBtn.style.touchAction = 'none';

    const show = () => {
      if (holding) return;
      holding = true;
      budgetValue.textContent = actual;
      budgetBtn.style.opacity = '1';
    };

    const hide = () => {
      if (!holding) return;
      holding = false;
      budgetValue.textContent = masked;
      budgetBtn.style.opacity = '0.8';
    };

    if (window.PointerEvent) {
      budgetBtn.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        budgetBtn.setPointerCapture?.(e.pointerId);
        show();
      });
      budgetBtn.addEventListener('pointerup', hide);
      budgetBtn.addEventListener('pointercancel', hide);
      budgetBtn.addEventListener('pointerleave', hide);
    } else {
      budgetBtn.addEventListener('mousedown', (e) => { e.preventDefault(); show(); });
      budgetBtn.addEventListener('touchstart', (e) => { e.preventDefault(); show(); }, { passive: false });
      ['mouseup', 'mouseleave', 'touchend', 'touchcancel', 'blur'].forEach((ev) => {
        budgetBtn.addEventListener(ev, hide);
      });
      document.addEventListener('mouseup', hide);
      document.addEventListener('touchend', hide);
    }

    budgetBtn.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        show();
      }
    });

    budgetBtn.addEventListener('keyup', (e) => {
      if (e.key === ' ' || e.key === 'Enter') hide();
    });

    budgetBtn.addEventListener('blur', hide);
  }

  function statusClass(status) {
    return String(status || 'Draft')
      .toLowerCase()
      .replace(/\s+/g, '-')
      .replace(/[^a-z0-9-]/g, '');
  }

  function formatShortDate(value) {
    if (!value) return '-';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function riskClassFromProject(p, progress) {
    const status = String(p.status || '').toLowerCase();
    if (status === 'cancelled') return 'risk-low';
    if (status === 'on-hold') return 'risk-high';
    if (status === 'completed' || progress >= 100) return 'risk-low';
    if (!p.end_date) return 'risk-medium';
    const end = new Date(p.end_date);
    if (Number.isNaN(end.getTime())) return 'risk-medium';
    const daysLeft = Math.ceil((end.getTime() - Date.now()) / 86400000);
    if (daysLeft < 0 && progress < 100) return 'risk-critical';
    if (daysLeft <= 21 && progress < 60) return 'risk-high';
    return 'risk-medium';
  }

  function renderProgressCards(projects) {
    const list = $('#projectsList');
    const empty = $('#pmEmpty');
    if (!list) return;

    if (!projects.length) {
      list.innerHTML = '';
      if (empty) empty.style.display = 'block';
      return;
    }
    if (empty) empty.style.display = 'none';

    const html = projects.map((p) => {
      const progress = Math.max(0, Math.min(100, Number(p.progress || 0)));
      const contractors = Array.isArray(p.assigned_contractors) ? p.assigned_contractors : [];
      const riskClass = riskClassFromProject(p, progress);
      return `
        <article class="project-card ${riskClass}" data-project-id="${p.id || ''}" tabindex="0" role="button" aria-label="Open project ${String(p.name || 'project')}">
          <div class="project-header">
            <div class="project-title-section">
              <h4>${p.code || 'N/A'} - ${p.name || 'Unnamed Project'}</h4>
              <span class="project-status ${statusClass(p.status)}">${p.status || 'Draft'}</span>
            </div>
          </div>
          <div class="project-meta">
            <div class="project-meta-item"><span class="project-meta-label">Location:</span><span class="project-meta-value">${p.location || '-'}</span></div>
            <div class="project-meta-item"><span class="project-meta-label">Sector:</span><span class="project-meta-value">${p.sector || '-'}</span></div>
            <div class="project-meta-item"><span class="project-meta-label">Budget:</span><span class="project-meta-value">₱${Number(p.budget || 0).toLocaleString()}</span></div>
            <div class="project-meta-item"><span class="project-meta-label">Contractors:</span><span class="project-meta-value">${contractors.length}</span></div>
          </div>
          <div class="progress-container">
            <div class="progress-label"><span>Completion</span><span style="font-weight:700;">${progress}%</span></div>
            <div class="progress-bar"><div class="progress-fill" style="width:${progress}%;"></div></div>
          </div>
          <div class="project-click-hint">Click to view details</div>
        </article>`;
    }).join('');

    list.innerHTML = html;
    $$('.project-card', list).forEach((card) => {
      const open = () => {
        if (isFn(window.showToast)) window.showToast('Project Selected', 'Project details panel can be attached here.', 'info');
      };
      card.addEventListener('click', open);
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
      });
    });
  }

  function applyProgressFilters(all) {
    const q = ($('#pmSearch')?.value || '').trim().toLowerCase();
    const status = ($('#pmStatusFilter')?.value || '').trim();
    const sector = ($('#pmSectorFilter')?.value || '').trim();
    const progressBand = ($('#pmProgressFilter')?.value || '').trim();
    const contractorMode = ($('#pmContractorFilter')?.value || '').trim();
    const sortBy = ($('#pmSort')?.value || 'createdAt_desc').trim();
    const filtered = all.filter((p) => {
      if (status && String(p.status || '').trim() !== status) return false;
      if (sector && String(p.sector || '').trim() !== sector) return false;
      if (progressBand) {
        const [min, max] = progressBand.split('-').map((v) => Number(v));
        const value = Number(p.progress || 0);
        if (!Number.isFinite(min) || !Number.isFinite(max) || value < min || value > max) return false;
      }
      const hasContractors = Array.isArray(p.assigned_contractors) && p.assigned_contractors.length > 0;
      if (contractorMode === 'assigned' && !hasContractors) return false;
      if (contractorMode === 'unassigned' && hasContractors) return false;
      if (!q) return true;
      const text = `${p.code || ''} ${p.name || ''} ${p.location || ''}`.toLowerCase();
      return text.includes(q);
    });
    const timeValue = (p) => {
      const raw = p.created_at || p.createdAt || p.start_date || '';
      const t = new Date(raw).getTime();
      return Number.isNaN(t) ? 0 : t;
    };
    filtered.sort((a, b) => {
      const ap = Number(a.progress || 0);
      const bp = Number(b.progress || 0);
      if (sortBy === 'progress_desc') return bp - ap;
      if (sortBy === 'progress_asc') return ap - bp;
      const at = timeValue(a);
      const bt = timeValue(b);
      if (sortBy === 'createdAt_asc') return at - bt;
      return bt - at;
    });
    return filtered;
  }

  function updateProgressSummary(visible, total) {
    const el = document.getElementById('pmResultSummary');
    if (!el) return;
    el.textContent = `Showing ${visible} of ${total} projects`;
  }

  function updateProgressStats(all) {
    const total = all.length;
    const approved = all.filter((p) => p.status === 'Approved').length;
    const inProgress = all.filter((p) => Number(p.progress || 0) > 0 && Number(p.progress || 0) < 100).length;
    const completed = all.filter((p) => Number(p.progress || 0) >= 100 || p.status === 'Completed').length;
    const contractors = all.reduce((sum, p) => sum + ((p.assigned_contractors || []).length), 0);
    if ($('#statTotal')) $('#statTotal').textContent = String(total);
    if ($('#statApproved')) $('#statApproved').textContent = String(approved);
    if ($('#statInProgress')) $('#statInProgress').textContent = String(inProgress);
    if ($('#statCompleted')) $('#statCompleted').textContent = String(completed);
    if ($('#statContractors')) $('#statContractors').textContent = String(contractors);
  }

  function initProgressMonitoringFix() {
    if (!/\/admin\/progress_monitoring\.php$/i.test(path)) return;
    const list = $('#projectsList');
    if (!list) return;

    let all = [];
    const rerender = () => {
      const visible = applyProgressFilters(all);
      renderProgressCards(visible);
      updateProgressSummary(visible.length, all.length);
      const statusValue = ($('#pmStatusFilter')?.value || '').trim();
      const quick = document.getElementById('pmQuickFilters');
      if (quick) {
        $$('button[data-status]', quick).forEach((btn) => {
          const isActive = statusValue === String(btn.dataset.status || '');
          btn.classList.toggle('active', isActive);
        });
      }
    };
    const load = () => {
      list.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Loading projects...</p></div>';
      const url = `progress_monitoring.php?action=load_projects&_=${Date.now()}`;
      fetch(url, { credentials: 'same-origin' })
        .then((res) => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then((data) => {
          all = Array.isArray(data) ? data : [];
          updateProgressStats(all);
          rerender();
        })
        .catch((err) => {
          list.innerHTML = `<div class="admin-empty-state"><span class="title">Unable to load projects</span><span>${String(err.message || 'Unknown error')}</span></div>`;
        });
    };

    ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmContractorFilter', 'pmSort'].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', rerender);
    });

    const quick = document.getElementById('pmQuickFilters');
    if (quick) {
      quick.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-status]');
        if (!btn) return;
        const statusFilter = document.getElementById('pmStatusFilter');
        if (!statusFilter) return;
        statusFilter.value = btn.dataset.status || '';
        rerender();
      });
    }

    const clearBtn = document.getElementById('pmClearFilters');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmContractorFilter', 'pmSort'].forEach((id) => {
          const el = document.getElementById(id);
          if (!el) return;
          if (el.tagName === 'INPUT') el.value = '';
          if (el.tagName === 'SELECT' && id !== 'pmSort') el.value = '';
        });
        const sortEl = document.getElementById('pmSort');
        if (sortEl) sortEl.value = 'createdAt_desc';
        rerender();
      });
    }

    const exportBtn = document.getElementById('exportCsv');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const rows = applyProgressFilters(all);
        if (!rows.length) {
          if (isFn(window.showToast)) window.showToast('No Data', 'No projects to export with current filters.', 'warning');
          return;
        }
        const escapeCsv = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const header = ['Code', 'Project Name', 'Status', 'Sector', 'Location', 'Budget', 'Progress', 'Contractors', 'Start Date', 'End Date'];
        const csv = [
          header.map(escapeCsv).join(','),
          ...rows.map((p) => ([
            p.code || '',
            p.name || '',
            p.status || '',
            p.sector || '',
            p.location || '',
            Number(p.budget || 0),
            `${Number(p.progress || 0)}%`,
            Array.isArray(p.assigned_contractors) ? p.assigned_contractors.length : 0,
            p.start_date || '',
            p.end_date || ''
          ]).map(escapeCsv).join(','))
        ].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const href = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = href;
        a.download = `progress-monitoring-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(href);
      });
    }

    load();
  }

  document.addEventListener('DOMContentLoaded', () => {
    initTopSidebarToggle();
    initUnifiedDropdowns();
    initLogoutModal();
    initDashboardBudgetHoldReveal();
    initProgressMonitoringFix();
  });
})();
