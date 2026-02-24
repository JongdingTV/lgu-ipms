'use strict';
(function(){
  const root = document.querySelector('[data-engineer-module]');
  if (!root) return;
  const module = String(root.getAttribute('data-module') || '');
  const csrf = String(root.getAttribute('data-csrf') || '');
  const apiBase = '/engineer/api.php';
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]||m));
  const badge = (s) => String(s||'').toLowerCase().replace(/\s+/g,'-');

  function apiGet(action, q){
    return fetch(apiBase + '?action=' + encodeURIComponent(action) + (q || ''), { credentials: 'same-origin' })
      .then(r => r.json().catch(() => ({ success:false, message:'Invalid server response.' })));
  }
  function apiPost(action, payload, withFile){
    let body; const headers = {};
    if (withFile) { body = payload; body.append('csrf_token', csrf); }
    else {
      body = new URLSearchParams();
      Object.keys(payload || {}).forEach(k => body.set(k, String(payload[k])));
      body.set('csrf_token', csrf);
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    return fetch(apiBase + '?action=' + encodeURIComponent(action), {
      method:'POST', credentials:'same-origin', headers, body: withFile ? body : body.toString()
    }).then(r => r.json().catch(() => ({ success:false, message:'Invalid server response.' })));
  }
  async function bindProjectOptions(selId){
    const sel = document.getElementById(selId); if (!sel) return [];
    const j = await apiGet('load_assigned_projects', '&limit=100&page=1');
    const rows = Array.isArray((j||{}).data) ? j.data : [];
    sel.innerHTML = '<option value="">Select project</option>' + rows.map(r => '<option value="'+Number(r.id||0)+'">'+esc((r.code||'PRJ')+' - '+(r.name||'Project'))+'</option>').join('');
    return rows;
  }

  async function initDashboard(){
    const sum = document.getElementById('emSummary'); if (!sum) return;
    const j = await apiGet('load_engineer_dashboard');
    const d = (j||{}).data || {};
    const s = d.summary || {};
    sum.innerHTML = [
      ['My Assigned Projects', s.assigned_projects || 0],
      ['Pending Validations', s.pending_validations || 0],
      ['Pending Inspection Requests', s.pending_inspections || 0],
      ['Open Issues/Risks', s.open_risks || 0],
      ['Budget Alerts', s.budget_alerts || 0]
    ].map(x => '<div class="em-card em-stat"><h4>'+esc(x[0])+'</h4><strong>'+Number(x[1])+'</strong></div>').join('');

    const dist = document.getElementById('emStatusDist');
    if (dist) dist.innerHTML = (Array.isArray(d.status_distribution)?d.status_distribution:[]).map(r => '<tr><td>'+esc(r.status)+'</td><td>'+Number(r.c||0)+'</td></tr>').join('') || '<tr><td colspan="2">No data.</td></tr>';
    const mon = document.getElementById('emMonthlyActivity');
    if (mon) mon.innerHTML = (Array.isArray(d.monthly_activity)?d.monthly_activity:[]).map(r => '<tr><td>'+esc(r.ym)+'</td><td>'+Number(r.c||0)+'</td></tr>').join('') || '<tr><td colspan="2">No data.</td></tr>';
  }

  async function initAssignedProjects(){
    const tbody = document.getElementById('emAssignedProjectsBody'); if (!tbody) return;
    const search = document.getElementById('emSearch');
    const status = document.getElementById('emStatus');
    const priority = document.getElementById('emPriority');
    let page = 1;
    async function load(){
      const q = '&page='+page+'&limit=12&search='+encodeURIComponent((search&&search.value)||'')+'&status='+encodeURIComponent((status&&status.value)||'')+'&priority='+encodeURIComponent((priority&&priority.value)||'');
      const j = await apiGet('load_assigned_projects', q);
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr>'+
        '<td>'+esc(r.code)+'</td><td>'+esc(r.name)+'</td><td>'+esc(r.location||'')+'</td><td>'+esc(r.priority||'')+'</td><td>'+esc(r.start_date||'')+' - '+esc(r.end_date||'')+'</td>'+
        '<td>'+Number(r.progress_percent||0).toFixed(2)+'%</td><td><span class="em-badge '+badge(r.status||'Pending')+'">'+esc(r.status||'Pending')+'</span></td>'+
        '<td>'+esc(r.contractor_name||'')+'</td>'+
        '<td><a href="/engineer/monitoring.php?project_id='+Number(r.id||0)+'">View</a> | <a href="/engineer/task_milestone.php?project_id='+Number(r.id||0)+'">Tasks</a> | <a href="/engineer/messages.php?project_id='+Number(r.id||0)+'">Messages</a> | <a href="/engineer/site_reports.php?project_id='+Number(r.id||0)+'">Site Report</a> | <button class="em-btn secondary em-qv" data-id="'+Number(r.id||0)+'" type="button">Quick View</button></td>'+
      '</tr>').join('') || '<tr><td colspan="9">No assigned projects.</td></tr>';
      const meta = (j||{}).meta || {}; const total = Number(meta.total||0); const lastPage = Math.max(1, Math.ceil(total / Number(meta.limit||12)));
      const pg = document.getElementById('emPagination'); if (pg) pg.textContent = 'Page '+page+' of '+lastPage;
      const prev = document.getElementById('emPrev'); const next = document.getElementById('emNext');
      if (prev) prev.disabled = page <= 1;
      if (next) next.disabled = page >= lastPage;
    }
    if (search) search.addEventListener('input', () => { page = 1; load(); });
    if (status) status.addEventListener('change', () => { page = 1; load(); });
    if (priority) priority.addEventListener('change', () => { page = 1; load(); });
    const prev = document.getElementById('emPrev'); if (prev) prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    const next = document.getElementById('emNext'); if (next) next.addEventListener('click', () => { page++; load(); });
    load();

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.em-qv[data-id]'); if (!btn) return;
      const id = Number(btn.getAttribute('data-id') || 0); if (!id) return;
      const j = await apiGet('load_project_quick_view', '&project_id='+id);
      if (!j || j.success === false) return;
      const d = j.data || {}; const p = d.project || {};
      const modal = document.getElementById('emQuickViewModal'); const body = document.getElementById('emQuickViewBody');
      if (!modal || !body) return;
      body.innerHTML = '<h3>'+esc((p.code||'PRJ')+' - '+(p.name||'Project'))+'</h3><p><strong>Location:</strong> '+esc(p.location||'')+'</p><p><strong>Contractor:</strong> '+esc(d.contractor||'N/A')+'</p><p><strong>Progress:</strong> '+Number(d.progress||0).toFixed(2)+'%</p><p><strong>Pending Validations:</strong> '+Number(d.pending_validations||0)+'</p><p><strong>Open Issues:</strong> '+Number(d.open_issues||0)+'</p>';
      modal.classList.add('open');
    });
    const close = document.getElementById('emQuickViewClose'); const modal = document.getElementById('emQuickViewModal');
    if (close && modal) close.addEventListener('click', () => modal.classList.remove('open'));
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('open'); });
  }

  async function initValidationQueue(){
    const tbody = document.getElementById('emValidationBody'); if (!tbody) return;
    const feedback = document.getElementById('emFeedback');
    const load = async () => {
      const j = await apiGet('load_validation_queue');
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.created_at||'')+'</td><td>'+esc(r.submission_type||'')+'</td><td>'+Number(r.project_id||0)+'</td><td>'+esc(r.details||'')+'</td><td>'+(r.submission_type==='progress'?Number(r.amount_or_progress||0).toFixed(2)+'%':'PHP '+Number(r.amount_or_progress||0).toLocaleString())+'</td><td><span class="em-badge '+badge(r.status||'Pending')+'">'+esc(r.status||'Pending')+'</span></td><td><button class="em-btn" data-v="approve" data-type="'+esc(r.submission_type)+'" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Approve</button> <button class="em-btn danger" data-v="reject" data-type="'+esc(r.submission_type)+'" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Reject</button> <button class="em-btn warn" data-v="return" data-type="'+esc(r.submission_type)+'" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Return</button></td></tr>').join('') || '<tr><td colspan="7">No submissions.</td></tr>';
    };
    document.addEventListener('click', async (e) => {
      const b = e.target.closest('button[data-v][data-id][data-type][data-project]'); if (!b) return;
      const decision = b.getAttribute('data-v') === 'approve' ? 'Approved' : (b.getAttribute('data-v') === 'reject' ? 'Rejected' : 'Returned');
      let remarks = '';
      if (decision !== 'Approved') remarks = prompt('Remarks (required):', '') || '';
      const j = await apiPost('decide_validation_item', { project_id: b.getAttribute('data-project'), submission_type: b.getAttribute('data-type'), item_id: b.getAttribute('data-id'), decision, remarks });
      if (feedback) feedback.textContent = (j && j.success) ? 'Validation updated.' : ((j && j.message) || 'Failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initSiteReports(){
    const tbody = document.getElementById('emSiteReportsBody'); const btn = document.getElementById('emSubmitSiteReport');
    if (!tbody || !btn) return;
    await bindProjectOptions('emProject');
    const feedback = document.getElementById('emFeedback');
    const load = async () => {
      const j = await apiGet('load_site_reports');
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.report_datetime||'')+'</td><td>'+esc((r.code||'')+' - '+(r.name||''))+'</td><td>'+Number(r.observed_progress_percent||0).toFixed(2)+'%</td><td>'+esc(r.notes||'')+'</td><td>'+esc(r.recommendation||'')+'</td><td>'+(r.attachment_path?'<a href="/'+esc(String(r.attachment_path).replace(/^\/+/,''))+'" target="_blank" rel="noopener">File</a>':'-')+'</td></tr>').join('') || '<tr><td colspan="6">No reports.</td></tr>';
    };
    btn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('emProject').value);
      fd.append('report_datetime', document.getElementById('emDatetime').value);
      fd.append('observed_progress_percent', document.getElementById('emObservedProgress').value);
      fd.append('notes', document.getElementById('emNotes').value);
      fd.append('issues_found', document.getElementById('emIssuesFound').value);
      fd.append('recommendation', document.getElementById('emRecommendation').value);
      const f = document.getElementById('emAttachment').files[0]; if (f) fd.append('attachment', f);
      const j = await apiPost('create_site_report', fd, true);
      if (feedback) feedback.textContent = (j && j.success) ? 'Site report submitted.' : ((j && j.message) || 'Submission failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initInspectionRequests(){
    const tbody = document.getElementById('emInspectionBody'); if (!tbody) return;
    const feedback = document.getElementById('emFeedback');
    const load = async () => {
      const j = await apiGet('load_inspection_requests_center');
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.created_at||'')+'</td><td>'+esc((r.code||'')+' - '+(r.name||''))+'</td><td>'+esc(r.request_type||'')+'</td><td>'+esc(r.proposed_datetime||'')+'</td><td><span class="em-badge '+badge(r.status||'Pending')+'">'+esc(r.status||'Pending')+'</span></td><td>'+esc(r.engineer_remarks||'')+'</td><td><button class="em-btn" data-ir="Approved" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Approve</button> <button class="em-btn danger" data-ir="Rejected" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Reject</button> <button class="em-btn warn" data-ir="Rescheduled" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Reschedule</button> <button class="em-btn secondary" data-ir="Completed" data-id="'+Number(r.id||0)+'" data-project="'+Number(r.project_id||0)+'">Complete</button></td></tr>').join('') || '<tr><td colspan="7">No requests.</td></tr>';
    };
    document.addEventListener('click', async (e) => {
      const b = e.target.closest('button[data-ir][data-id][data-project]'); if (!b) return;
      const decision = b.getAttribute('data-ir');
      const remarks = prompt('Remarks:', '') || '';
      const scheduled = decision === 'Rescheduled' || decision === 'Approved' ? (prompt('Scheduled datetime (YYYY-MM-DD HH:MM), optional:', '') || '') : '';
      const j = await apiPost('decide_inspection_request', { request_id: b.getAttribute('data-id'), project_id: b.getAttribute('data-project'), decision, remarks, scheduled_datetime: scheduled });
      if (feedback) feedback.textContent = (j && j.success) ? 'Inspection request updated.' : ((j && j.message) || 'Failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initIssuesRisks(){
    const tbody = document.getElementById('emIssuesBody'); const btn = document.getElementById('emSubmitIssueRisk');
    if (!tbody || !btn) return;
    await bindProjectOptions('emProject');
    const feedback = document.getElementById('emFeedback');
    const load = async () => {
      const j = await apiGet('load_issues_risks');
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.created_at||'')+'</td><td>'+esc((r.code||'')+' - '+(r.name||''))+'</td><td>'+esc(r.issue_type||'')+'</td><td><span class="em-badge '+badge(r.severity_level||'Low')+'">'+esc(r.severity_level||'Low')+'</span></td><td><span class="em-badge '+badge(r.status||'Open')+'">'+esc(r.status||'Open')+'</span></td><td>'+esc(r.description||'')+'</td><td>'+(r.attachment_path?'<a href="/'+esc(String(r.attachment_path).replace(/^\/+/,''))+'" target="_blank" rel="noopener">File</a>':'-')+'</td></tr>').join('') || '<tr><td colspan="7">No issues/risks.</td></tr>';
    };
    btn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('emProject').value);
      fd.append('issue_type', document.getElementById('emIssueType').value);
      fd.append('severity_level', document.getElementById('emSeverity').value);
      fd.append('status', document.getElementById('emStatus').value);
      fd.append('description', document.getElementById('emDescription').value);
      const f = document.getElementById('emAttachment').files[0]; if (f) fd.append('attachment', f);
      const j = await apiPost('create_issue_risk', fd, true);
      if (feedback) feedback.textContent = (j && j.success) ? 'Issue/Risk logged.' : ((j && j.message) || 'Failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initDocuments(){
    const tbody = document.getElementById('emDocumentsBody'); const btn = document.getElementById('emSubmitDocument');
    if (!tbody || !btn) return;
    await bindProjectOptions('emProject');
    const feedback = document.getElementById('emFeedback');
    const load = async () => {
      const j = await apiGet('load_project_documents');
      const rows = Array.isArray((j||{}).data)?j.data:[];
      tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.created_at||'')+'</td><td>'+esc((r.code||'')+' - '+(r.name||''))+'</td><td>'+esc(r.document_name||'')+'</td><td>'+esc(r.category||'')+'</td><td>'+esc(r.tags||'')+'</td><td><a href="/'+esc(String(r.file_path||'').replace(/^\/+/,''))+'" target="_blank" rel="noopener">Open</a></td></tr>').join('') || '<tr><td colspan="6">No documents.</td></tr>';
    };
    btn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('project_id', document.getElementById('emProject').value);
      fd.append('document_name', document.getElementById('emDocumentName').value);
      fd.append('category', document.getElementById('emCategory').value);
      fd.append('tags', document.getElementById('emTags').value);
      const f = document.getElementById('emAttachment').files[0]; if (f) fd.append('attachment', f);
      const j = await apiPost('upload_project_document', fd, true);
      if (feedback) feedback.textContent = (j && j.success) ? 'Document uploaded.' : ((j && j.message) || 'Upload failed.');
      if (j && j.success) load();
    });
    load();
  }

  async function initNotifications(){
    const tbody = document.getElementById('emNotificationsBody'); if (!tbody) return;
    const j = await apiGet('load_engineer_notifications_center');
    const rows = Array.isArray((j||{}).data)?j.data:[];
    tbody.innerHTML = rows.map(r => '<tr><td>'+esc(r.created_at||'')+'</td><td>'+esc(r.title||'')+'</td><td>'+esc(r.body||'')+'</td></tr>').join('') || '<tr><td colspan="3">No notifications.</td></tr>';
  }

  if (module === 'dashboard') initDashboard();
  if (module === 'assigned-projects') initAssignedProjects();
  if (module === 'validations') initValidationQueue();
  if (module === 'site-reports') initSiteReports();
  if (module === 'inspection-requests') initInspectionRequests();
  if (module === 'issues-risks') initIssuesRisks();
  if (module === 'documents') initDocuments();
  if (module === 'notifications') initNotifications();
})();
