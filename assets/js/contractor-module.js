'use strict';

(function () {
  const root = document.querySelector('[data-contractor-module]');
  if (!root) return;
  const apiBase = '/contractor/api.php';
  const module = String(root.getAttribute('data-module') || '');
  const csrf = String(root.getAttribute('data-csrf') || '');
  const projectIdFromUrl = Number(new URLSearchParams(window.location.search).get('project_id') || 0);

  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m] || m));
  const badgeClass = (s) => String(s || '').toLowerCase().replace(/\s+/g, '-');

  function get(action, extra) {
    return fetch(apiBase + '?action=' + encodeURIComponent(action) + (extra || ''), { credentials: 'same-origin' })
      .then(r => r.json().catch(() => ({ success: false, message: 'Invalid server response.' })));
  }
  function post(action, payload, withFile) {
    let body;
    let headers = {};
    if (withFile) {
      body = payload;
      body.append('csrf_token', csrf);
    } else {
      body = new URLSearchParams();
      Object.keys(payload || {}).forEach(k => body.set(k, String(payload[k])));
      body.set('csrf_token', csrf);
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    return fetch(apiBase + '?action=' + encodeURIComponent(action), { method: 'POST', credentials: 'same-origin', headers, body: withFile ? body : body.toString() })
      .then(r => r.json().catch(() => ({ success: false, message: 'Invalid server response.' })));
  }

  async function initMyProjects() {
    const tbody = document.getElementById('cmMyProjectsBody');
    if (!tbody) return;
    const j = await get('load_my_projects');
    const rows = Array.isArray((j || {}).data) ? j.data : [];
    tbody.innerHTML = rows.map(r =>
      '<tr><td>' + esc(r.code) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.location || '') + '</td><td>' + Number(r.progress_percent || 0).toFixed(2) + '%</td><td>' + esc(r.priority || '') + '</td><td><span class="cm-badge ' + badgeClass(r.status || 'Pending') + '">' + esc(r.status || 'Pending') + '</span></td><td><a href="/contractor/project_details.php?project_id=' + Number(r.id || 0) + '">View Details</a> | <a href="/contractor/messages.php?project_id=' + Number(r.id || 0) + '">Open Messages</a> | <a href="/contractor/progress_monitoring.php?project_id=' + Number(r.id || 0) + '">Submit Update</a></td></tr>'
    ).join('') || '<tr><td colspan="7">No assigned projects.</td></tr>';
  }

  async function initProjectDetails() {
    const container = document.getElementById('cmProjectDetails');
    if (!container) return;
    if (!projectIdFromUrl) {
      container.innerHTML = '<div class="cm-feedback">No project selected.</div>';
      return;
    }
    const j = await get('load_project_details', '&project_id=' + encodeURIComponent(projectIdFromUrl));
    if (!j || j.success === false) {
      container.innerHTML = '<div class="cm-feedback">Unable to load project details.</div>';
      return;
    }
    const d = j.data || {};
    const p = d.project || {};
    const tasks = Array.isArray(d.tasks) ? d.tasks : [];
    const milestones = Array.isArray(d.milestones) ? d.milestones : [];
    const history = Array.isArray(d.progress_history) ? d.progress_history : [];
    container.innerHTML =
      '<div class="cm-card"><h3>' + esc((p.code || 'PRJ') + ' - ' + (p.name || 'Project')) + '</h3><p>' + esc(p.description || '') + '</p><p><strong>Budget:</strong> PHP ' + Number(p.budget || 0).toLocaleString() + ' | <strong>Timeline:</strong> ' + esc(p.start_date || '-') + ' to ' + esc(p.end_date || '-') + ' | <strong>Status:</strong> ' + esc(p.status || '-') + '</p><p><a href="/contractor/messages.php?project_id=' + Number(p.id || projectIdFromUrl) + '">Open Messages</a></p></div>' +
      '<div class="cm-card"><h4>Milestones</h4><div class="cm-table-wrap"><table class="cm-table"><thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th></tr></thead><tbody>' + (milestones.map(m => '<tr><td>' + esc(m.title) + '</td><td>' + esc(m.status || '') + '</td><td>' + esc(m.planned_date || '') + '</td><td>' + esc(m.actual_date || '') + '</td></tr>').join('') || '<tr><td colspan="4">No milestones.</td></tr>') + '</tbody></table></div></div>' +
      '<div class="cm-card"><h4>Tasks</h4><div class="cm-table-wrap"><table class="cm-table"><thead><tr><th>Title</th><th>Status</th><th>Planned</th><th>Actual</th></tr></thead><tbody>' + (tasks.map(t => '<tr><td>' + esc(t.title) + '</td><td>' + esc(t.status || '') + '</td><td>' + esc((t.planned_start || '') + ' - ' + (t.planned_end || '')) + '</td><td>' + esc((t.actual_start || '') + ' - ' + (t.actual_end || '')) + '</td></tr>').join('') || '<tr><td colspan="4">No tasks.</td></tr>') + '</tbody></table></div></div>' +
      '<div class="cm-card"><h4>Progress History</h4><div class="cm-table-wrap"><table class="cm-table"><thead><tr><th>Submitted</th><th>Progress</th><th>Status</th></tr></thead><tbody>' + (history.map(h => '<tr><td>' + esc(h.submitted_at || '') + '</td><td>' + Number(h.progress_percent || 0).toFixed(2) + '%</td><td>' + esc(h.review_status || '') + '</td></tr>').join('') || '<tr><td colspan="3">No history yet.</td></tr>') + '</tbody></table></div></div>';
  }

  async function bindProjectOptions(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return [];
    const j = await get('load_my_projects');
    const rows = Array.isArray((j || {}).data) ? j.data : [];
    sel.innerHTML = '<option value="">Select project</option>' + rows.map(r => '<option value="' + Number(r.id || 0) + '">' + esc((r.code || 'PRJ') + ' - ' + (r.name || 'Project')) + '</option>').join('');
    return rows;
  }

  async function initDeliverables() {
    await bindProjectOptions('cmProject');
    const tbody = document.getElementById('cmDeliverablesBody');
    const submitBtn = document.getElementById('cmSubmitDeliverable');
    if (!tbody || !submitBtn) return;
    const load = async () => {
      const j = await get('load_deliverables');
      const rows = Array.isArray((j || {}).data) ? j.data : [];
      tbody.innerHTML = rows.map(r => '<tr><td>' + Number(r.project_id || 0) + '</td><td>' + esc(r.deliverable_type || '') + '</td><td>' + esc(r.milestone_reference || '') + '</td><td><span class="cm-badge ' + badgeClass(r.status || 'Submitted') + '">' + esc(r.status || 'Submitted') + '</span></td><td>' + esc(r.created_at || '') + '</td></tr>').join('') || '<tr><td colspan="5">No deliverables submitted.</td></tr>';
    };
    submitBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('cmProject').value);
      fd.append('deliverable_type', document.getElementById('cmDeliverableType').value);
      fd.append('milestone_reference', document.getElementById('cmMilestoneRef').value);
      fd.append('remarks', document.getElementById('cmRemarks').value);
      const f = document.getElementById('cmFile').files[0];
      if (f) fd.append('attachment', f);
      const j = await post('submit_deliverable', fd, true);
      const fb = document.getElementById('cmFeedback');
      if (fb) fb.textContent = (j && j.success) ? 'Deliverable submitted.' : ((j && j.message) || 'Submission failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initExpenses() {
    await bindProjectOptions('cmProject');
    const tbody = document.getElementById('cmExpensesBody');
    const submitBtn = document.getElementById('cmSubmitExpense');
    if (!tbody || !submitBtn) return;
    const load = async () => {
      const j = await get('load_expense_entries');
      const rows = Array.isArray((j || {}).data) ? j.data : [];
      tbody.innerHTML = rows.map(r => '<tr><td>' + Number(r.project_id || 0) + '</td><td>PHP ' + Number(r.amount || 0).toLocaleString() + '</td><td>' + esc(r.category || '') + '</td><td>' + esc(r.status || '') + '</td><td>' + esc(r.created_at || '') + '</td></tr>').join('') || '<tr><td colspan="5">No expense entries.</td></tr>';
    };
    submitBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('cmProject').value);
      fd.append('amount', document.getElementById('cmAmount').value);
      fd.append('category', document.getElementById('cmCategory').value);
      fd.append('description', document.getElementById('cmDescription').value);
      const f = document.getElementById('cmReceipt').files[0];
      if (f) fd.append('receipt', f);
      const j = await post('submit_expense_entry', fd, true);
      const fb = document.getElementById('cmFeedback');
      if (fb) fb.textContent = (j && j.success) ? 'Expense submitted.' : ((j && j.message) || 'Submission failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initRequests() {
    await bindProjectOptions('cmProject');
    const tbody = document.getElementById('cmRequestsBody');
    const submitBtn = document.getElementById('cmSubmitRequest');
    if (!tbody || !submitBtn) return;
    const load = async () => {
      const j = await get('load_requests_center');
      const rows = Array.isArray((j || {}).data) ? j.data : [];
      tbody.innerHTML = rows.map(r => '<tr><td>' + Number(r.project_id || 0) + '</td><td>' + esc(r.request_type || '') + '</td><td>' + esc(r.details || '') + '</td><td>' + esc(r.status || '') + '</td><td>' + esc(r.created_at || '') + '</td></tr>').join('') || '<tr><td colspan="5">No requests.</td></tr>';
    };
    submitBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('cmProject').value);
      fd.append('request_type', document.getElementById('cmRequestType').value);
      fd.append('details', document.getElementById('cmDetails').value);
      const f = document.getElementById('cmAttachment').files[0];
      if (f) fd.append('attachment', f);
      const j = await post('submit_request_center', fd, true);
      const fb = document.getElementById('cmFeedback');
      if (fb) fb.textContent = (j && j.success) ? 'Request submitted.' : ((j && j.message) || 'Submission failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initIssues() {
    await bindProjectOptions('cmProject');
    const tbody = document.getElementById('cmIssuesBody');
    const submitBtn = document.getElementById('cmSubmitIssue');
    if (!tbody || !submitBtn) return;
    const load = async () => {
      const j = await get('load_issues');
      const rows = Array.isArray((j || {}).data) ? j.data : [];
      tbody.innerHTML = rows.map(r => '<tr><td>' + Number(r.project_id || 0) + '</td><td>' + esc(r.issue_type || '') + '</td><td>' + esc(r.severity_level || '') + '</td><td>' + esc(r.status || '') + '</td><td>' + esc(r.created_at || '') + '</td></tr>').join('') || '<tr><td colspan="5">No issues.</td></tr>';
    };
    submitBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('cmProject').value);
      fd.append('issue_type', document.getElementById('cmIssueType').value);
      fd.append('severity_level', document.getElementById('cmSeverity').value);
      fd.append('description', document.getElementById('cmDescription').value);
      const f = document.getElementById('cmAttachment').files[0];
      if (f) fd.append('attachment', f);
      const j = await post('submit_issue', fd, true);
      const fb = document.getElementById('cmFeedback');
      if (fb) fb.textContent = (j && j.success) ? 'Issue submitted.' : ((j && j.message) || 'Submission failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initNotifications() {
    const tbody = document.getElementById('cmNotificationsBody');
    if (!tbody) return;
    const j = await get('load_notifications_center');
    const rows = Array.isArray((j || {}).data) ? j.data : [];
    tbody.innerHTML = rows.map(r => '<tr><td>' + esc(r.title || 'Notification') + '</td><td>' + esc(r.body || '') + '</td><td>' + esc(r.created_at || '') + '</td></tr>').join('') || '<tr><td colspan="3">No notifications yet.</td></tr>';
  }

  if (module === 'my-projects') initMyProjects();
  if (module === 'project-details') initProjectDetails();
  if (module === 'deliverables') initDeliverables();
  if (module === 'expenses') initExpenses();
  if (module === 'requests') initRequests();
  if (module === 'issues') initIssues();
  if (module === 'notifications') initNotifications();
})();
