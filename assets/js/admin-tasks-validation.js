(function () {
  'use strict';

  var page = document.querySelector('.validation-workflow-page');
  if (!page) return;

  var canValidate = page.getAttribute('data-can-validate') === '1';
  var csrfToken = page.getAttribute('data-csrf-token') || '';

  var state = {
    page: 1,
    perPage: 20,
    filters: {
      q: '',
      status: '',
      sector: '',
      date_field: 'submitted',
      date_from: '',
      date_to: '',
      sort: 'newest_submitted'
    },
    data: [],
    meta: { page: 1, total: 0, total_pages: 1, has_prev: false, has_next: false },
    summary: { total_deliverables: 0, approved: 0, pending_review: 0, rejected_returned: 0, overall_percent: 0 }
  };

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function statusClass(s) {
    return String(s || 'Pending').toLowerCase().replace(/\s+/g, '-');
  }

  function setFeedback(ok, text) {
    var box = document.getElementById('tvFeedback');
    if (!box) return;
    box.className = ok ? 'ac-0b2b14a3' : 'ac-aabba7cf';
    box.textContent = text || '';
  }

  function apiUrl(action, params) {
    var q = new URLSearchParams(params || {});
    q.set('action', action);
    q.set('_', String(Date.now()));
    return 'tasks_validation_api.php?' + q.toString();
  }

  function fetchDashboard() {
    var params = {
      page: state.page,
      per_page: state.perPage,
      q: state.filters.q,
      status: state.filters.status,
      sector: state.filters.sector,
      date_field: state.filters.date_field,
      date_from: state.filters.date_from,
      date_to: state.filters.date_to,
      sort: state.filters.sort
    };
    return fetch(apiUrl('load_validation_dashboard', params), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); });
  }

  function postDecision(itemId, decision, remarks) {
    var body = new URLSearchParams();
    body.set('action', 'decide_validation_item');
    body.set('csrf_token', csrfToken);
    body.set('item_id', String(itemId));
    body.set('decision', decision);
    body.set('remarks', remarks || '');
    return fetch('tasks_validation_api.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function (res) { return res.json(); });
  }

  function loadItemDetails(itemId) {
    return fetch(apiUrl('load_validation_item_details', { item_id: itemId }), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); });
  }

  function renderSummary() {
    var s = state.summary || {};
    var percent = Number(s.overall_percent || 0);
    var sumTotal = document.getElementById('sumTotal');
    var sumApproved = document.getElementById('sumApproved');
    var sumPending = document.getElementById('sumPending');
    var sumRejected = document.getElementById('sumRejected');
    var sumPercent = document.getElementById('sumPercent');
    var overallBar = document.getElementById('overallValidationBar');

    if (sumTotal) sumTotal.textContent = String(Number(s.total_deliverables || 0));
    if (sumApproved) sumApproved.textContent = String(Number(s.approved || 0));
    if (sumPending) sumPending.textContent = String(Number(s.pending_review || 0));
    if (sumRejected) sumRejected.textContent = String(Number(s.rejected_returned || 0));
    if (sumPercent) sumPercent.textContent = percent.toFixed(2).replace(/\.00$/, '') + '%';
    if (overallBar) overallBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
  }

  function groupByProject(rows) {
    var map = {};
    (rows || []).forEach(function (row) {
      var key = String(row.project_id || '0');
      if (!map[key]) {
        map[key] = {
          project_id: row.project_id,
          code: row.code || '',
          project_name: row.project_name || '',
          location: row.location || '',
          sector: row.sector || '',
          type: row.type || '',
          priority: row.priority || '',
          items: []
        };
      }
      map[key].items.push(row);
    });
    return Object.keys(map).map(function (k) { return map[k]; });
  }

  function renderAccordion() {
    var container = document.getElementById('tvAccordion');
    if (!container) return;
    var groups = groupByProject(state.data);
    if (!groups.length) {
      container.innerHTML = '<div class="admin-empty-state"><span class="title">No deliverables found</span><span>Adjust your filters and try again.</span></div>';
      return;
    }

    var html = groups.map(function (g) {
      var rows = g.items.map(function (item) {
        var status = item.current_status || 'Pending';
        var attachment = item.last_attachment_path ? '<span class="tv-attachment-indicator" title="Has attachment">📎</span>' : '';
        var submittedBy = ((item.submitted_by_name || 'N/A') + (item.submitted_by_role ? (' (' + item.submitted_by_role + ')') : '')).trim();
        var actions = '<button type="button" class="tv-btn tv-btn-view" data-action="view" data-item-id="' + Number(item.id || 0) + '">View</button>';
        if (canValidate) {
          actions += ' <button type="button" class="tv-btn tv-btn-approve" data-action="approve" data-item-id="' + Number(item.id || 0) + '">Approve</button>';
          actions += ' <button type="button" class="tv-btn tv-btn-reject" data-action="reject" data-item-id="' + Number(item.id || 0) + '">Reject</button>';
          actions += ' <button type="button" class="tv-btn tv-btn-return" data-action="return" data-item-id="' + Number(item.id || 0) + '">Return</button>';
        }
        return '<tr>'
          + '<td><strong>' + esc(item.deliverable_name || '') + '</strong><br><small>' + esc(item.deliverable_type || '') + '</small></td>'
          + '<td><span class="status-badge ' + esc(statusClass(status)) + '">' + esc(status) + '</span></td>'
          + '<td>' + esc(submittedBy) + '</td>'
          + '<td>' + esc(item.submitted_at || 'N/A') + '</td>'
          + '<td>' + esc(item.validated_by_name || 'N/A') + '</td>'
          + '<td>' + esc(item.validated_at || 'N/A') + '</td>'
          + '<td>' + esc(item.validator_remarks || '-') + '</td>'
          + '<td>' + esc((Number(item.last_progress_percent || 0)).toFixed(2)) + '% ' + attachment + '</td>'
          + '<td class="tv-actions-cell">' + actions + '</td>'
          + '</tr>';
      }).join('');

      return '<details class="tv-project-group" open>'
        + '<summary><div class="tv-project-title">' + esc(g.code) + ' - ' + esc(g.project_name) + '</div>'
        + '<div class="tv-project-meta">' + esc(g.location) + ' | ' + esc(g.sector) + '</div></summary>'
        + '<div class="table-wrap tv-sticky-head"><table class="table tv-items-table"><thead><tr>'
        + '<th>Deliverable</th><th>Status</th><th>Submitted By</th><th>Submitted Date</th><th>Validated By</th><th>Validated Date</th><th>Remarks</th><th>Last Submission</th><th>Actions</th>'
        + '</tr></thead><tbody>' + rows + '</tbody></table></div>'
        + '</details>';
    }).join('');

    container.innerHTML = html;
  }

  function renderPager() {
    var pager = document.getElementById('tvPager');
    if (!pager) return;
    var meta = state.meta || {};
    pager.innerHTML = ''
      + '<button type="button" class="btn-clear-filters" data-page-dir="prev" ' + (meta.has_prev ? '' : 'disabled') + '>Previous</button>'
      + '<span class="pm-result-summary">Page ' + Number(meta.page || 1) + ' of ' + Number(meta.total_pages || 1) + '</span>'
      + '<button type="button" class="btn-clear-filters" data-page-dir="next" ' + (meta.has_next ? '' : 'disabled') + '>Next</button>';
    var metaText = document.getElementById('tvResultMeta');
    if (metaText) metaText.textContent = 'Showing ' + (state.data || []).length + ' of ' + Number(meta.total || 0) + ' items';
  }

  function refresh() {
    setFeedback(true, '');
    fetchDashboard().then(function (json) {
      if (!json || json.success === false) {
        throw new Error((json && json.message) || 'Failed to load validation data.');
      }
      state.data = Array.isArray(json.data) ? json.data : [];
      state.summary = json.summary || state.summary;
      state.meta = json.meta || state.meta;
      renderSummary();
      renderAccordion();
      renderPager();
    }).catch(function (err) {
      setFeedback(false, String(err.message || err));
    });
  }

  function openModal(contentHtml, titleText) {
    var modal = document.getElementById('validationDetailModal');
    var body = document.getElementById('validationDetailBody');
    var title = document.getElementById('validationDetailTitle');
    if (!modal || !body) return;
    if (title) title.textContent = titleText || 'Validation Details';
    body.innerHTML = contentHtml;
    modal.hidden = false;
    modal.classList.add('show');
  }

  function closeModal() {
    var modal = document.getElementById('validationDetailModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.hidden = true;
  }

  function renderDetails(itemId) {
    loadItemDetails(itemId).then(function (json) {
      if (!json || json.success === false) throw new Error((json && json.message) || 'Failed to load details.');
      var data = json.data || {};
      var item = data.item || {};
      var subs = Array.isArray(data.submissions) ? data.submissions : [];
      var logs = Array.isArray(data.logs) ? data.logs : [];

      var subHtml = subs.length ? subs.map(function (s) {
        var attachment = s.attachment_path ? '<a href="/' + esc(String(s.attachment_path).replace(/^\/+/, '')) + '" target="_blank" rel="noopener">View Attachment</a>' : 'No attachment';
        return '<div class="tv-timeline-item">'
          + '<h5>Version ' + Number(s.version_no || 0) + ' - ' + esc(s.validation_result || 'Submitted') + '</h5>'
          + '<p><strong>Progress:</strong> ' + Number(s.progress_percent || 0).toFixed(2) + '%</p>'
          + '<p><strong>Submitted By:</strong> ' + esc(s.submitted_by_name || 'N/A') + ' (' + esc(s.submitted_role || '') + ')</p>'
          + '<p><strong>Submitted At:</strong> ' + esc(s.submitted_at || 'N/A') + '</p>'
          + '<p><strong>Changes:</strong> ' + esc(s.change_summary || '-')
          + '</p><p><strong>Attachment:</strong> ' + attachment + '</p></div>';
      }).join('') : '<p class="tv-muted">No progress logs yet.</p>';

      var logHtml = logs.length ? logs.map(function (l) {
        return '<div class="tv-timeline-item">'
          + '<h5>' + esc(l.action_type || 'action') + '</h5>'
          + '<p><strong>Status:</strong> ' + esc(l.previous_status || '-') + ' -> ' + esc(l.new_status || '-') + '</p>'
          + '<p><strong>By:</strong> ' + esc(l.actor_name || 'N/A') + ' (' + esc(l.acted_role || '') + ')</p>'
          + '<p><strong>At:</strong> ' + esc(l.acted_at || '') + '</p>'
          + '<p><strong>Remarks:</strong> ' + esc(l.remarks || '-')
          + '</p></div>';
      }).join('') : '<p class="tv-muted">No validation actions yet.</p>';

      var html = '<div class="tv-detail-header">'
        + '<h4>' + esc(item.deliverable_name || '') + '</h4>'
        + '<p>' + esc(item.code || '') + ' - ' + esc(item.project_name || '') + ' | ' + esc(item.location || '') + '</p>'
        + '</div>'
        + '<div class="tv-detail-grid">'
        + '<div><label>Status</label><div>' + esc(item.current_status || '') + '</div></div>'
        + '<div><label>Submitted By</label><div>' + esc(item.submitted_by_name || 'N/A') + '</div></div>'
        + '<div><label>Submitted Date</label><div>' + esc(item.submitted_at || 'N/A') + '</div></div>'
        + '<div><label>Validated By</label><div>' + esc(item.validated_by_name || 'N/A') + '</div></div>'
        + '<div><label>Validated Date</label><div>' + esc(item.validated_at || 'N/A') + '</div></div>'
        + '<div><label>Remarks</label><div>' + esc(item.validator_remarks || '-') + '</div></div>'
        + '</div>'
        + '<h5>Progress Logs</h5><div class="tv-timeline">' + subHtml + '</div>'
        + '<h5>Validation History</h5><div class="tv-timeline">' + logHtml + '</div>';

      openModal(html, 'Deliverable Validation Details');
    }).catch(function (err) {
      setFeedback(false, String(err.message || err));
    });
  }

  function handleDecision(itemId, decision) {
    if (!canValidate) {
      setFeedback(false, 'You are not allowed to validate deliverables.');
      return;
    }
    var promptText = decision === 'approve'
      ? 'Optional remarks for approval:'
      : 'Remarks are required for this action:';
    var remarks = window.prompt(promptText, '') || '';
    if ((decision === 'reject' || decision === 'return') && !remarks.trim()) {
      setFeedback(false, 'Remarks are required for reject/return.');
      return;
    }
    postDecision(itemId, decision, remarks.trim()).then(function (json) {
      if (!json || json.success === false) {
        throw new Error((json && json.message) || 'Failed to save validation action.');
      }
      setFeedback(true, json.message || 'Validation action saved.');
      refresh();
    }).catch(function (err) {
      setFeedback(false, String(err.message || err));
    });
  }

  function bindControls() {
    var applyBtn = document.getElementById('tvApplyFilters');
    var clearBtn = document.getElementById('tvClearFilters');
    var perPage = document.getElementById('tvPerPage');
    var pager = document.getElementById('tvPager');

    function readFilters() {
      state.filters.q = (document.getElementById('tvSearch').value || '').trim();
      state.filters.status = (document.getElementById('tvStatus').value || '').trim();
      state.filters.sector = (document.getElementById('tvSector').value || '').trim();
      state.filters.date_field = (document.getElementById('tvDateField').value || 'submitted').trim();
      state.filters.date_from = (document.getElementById('tvDateFrom').value || '').trim();
      state.filters.date_to = (document.getElementById('tvDateTo').value || '').trim();
      state.filters.sort = (document.getElementById('tvSort').value || 'newest_submitted').trim();
      state.perPage = Number((document.getElementById('tvPerPage').value || '20'));
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        state.page = 1;
        readFilters();
        refresh();
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        ['tvSearch', 'tvStatus', 'tvSector', 'tvDateFrom', 'tvDateTo'].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = '';
        });
        var dateField = document.getElementById('tvDateField');
        if (dateField) dateField.value = 'submitted';
        var sort = document.getElementById('tvSort');
        if (sort) sort.value = 'newest_submitted';
        if (perPage) perPage.value = '20';
        state.page = 1;
        readFilters();
        refresh();
      });
    }
    if (perPage) {
      perPage.addEventListener('change', function () {
        state.page = 1;
        readFilters();
        refresh();
      });
    }
    if (pager) {
      pager.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-page-dir]');
        if (!btn || btn.disabled) return;
        if (btn.getAttribute('data-page-dir') === 'prev' && state.meta.has_prev) {
          state.page = Math.max(1, state.page - 1);
          refresh();
        } else if (btn.getAttribute('data-page-dir') === 'next' && state.meta.has_next) {
          state.page += 1;
          refresh();
        }
      });
    }

    var accordion = document.getElementById('tvAccordion');
    if (accordion) {
      accordion.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-item-id][data-action]');
        if (!btn) return;
        var itemId = Number(btn.getAttribute('data-item-id') || '0');
        var action = String(btn.getAttribute('data-action') || '');
        if (!itemId) return;
        if (action === 'view') renderDetails(itemId);
        if (action === 'approve' || action === 'reject' || action === 'return') handleDecision(itemId, action);
      });
    }

    document.addEventListener('click', function (e) {
      var closeBtn = e.target.closest('[data-close-modal="validationDetailModal"]');
      if (closeBtn || e.target.id === 'validationDetailModal') closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  bindControls();
  refresh();
})();

