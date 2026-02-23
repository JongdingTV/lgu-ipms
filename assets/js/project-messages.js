'use strict';

(function () {
  const root = document.querySelector('[data-messages-root]');
  if (!root) return;

  const apiBase = String(root.getAttribute('data-api-base') || '');
  const role = String(root.getAttribute('data-role') || '');
  const currentUserId = Number(root.getAttribute('data-user-id') || 0);
  const csrf = String(root.getAttribute('data-csrf') || '');

  const projectSearch = document.getElementById('messageProjectSearch');
  const threadSearch = document.getElementById('messageThreadSearch');
  const projectList = document.getElementById('messageProjectList');
  const threadTitle = document.getElementById('messageThreadTitle');
  const feed = document.getElementById('messageFeed');
  const textInput = document.getElementById('messageText');
  const fileInput = document.getElementById('messageFile');
  const sendBtn = document.getElementById('messageSendBtn');

  const state = { projects: [], filteredProjects: [], activeProjectId: 0, messages: [] };
  const preselectedProjectId = Number(new URLSearchParams(window.location.search).get('project_id') || 0);

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function apiGet(action, extra) {
    const q = apiBase + '?action=' + encodeURIComponent(action) + (extra || '');
    return fetch(q, { credentials: 'same-origin' }).then(r => r.json());
  }

  function apiPost(action, payload, withFile) {
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
    return fetch(apiBase + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: withFile ? body : body.toString()
    }).then(r => r.json());
  }

  function renderProjects() {
    const q = (projectSearch.value || '').trim().toLowerCase();
    state.filteredProjects = state.projects.filter(p => {
      const hay = [p.code, p.name, p.last_message_text].join(' ').toLowerCase();
      return q === '' || hay.indexOf(q) !== -1;
    });
    projectList.innerHTML = '';
    if (!state.filteredProjects.length) {
      projectList.innerHTML = '<div class="messages-empty">No assigned projects found.</div>';
      return;
    }
    state.filteredProjects.forEach(p => {
      const div = document.createElement('div');
      div.className = 'messages-project-item' + (Number(p.id) === Number(state.activeProjectId) ? ' active' : '');
      div.setAttribute('data-project-id', String(p.id));
      div.innerHTML = '<div><strong>' + esc((p.code || 'PRJ') + ' - ' + (p.name || 'Project')) + '</strong></div>' +
        '<div class="messages-meta"><span>' + esc((p.last_message_text || '').slice(0, 42) || 'No messages yet') + '</span>' +
        '<span>' + (Number(p.unread_count || 0) > 0 ? ('<span class="messages-unread">' + Number(p.unread_count || 0) + '</span>') : '') + '</span></div>' +
        '<div class="messages-meta"><span>' + esc(p.last_message_at || '') + '</span></div>';
      projectList.appendChild(div);
    });
  }

  function renderMessages() {
    const q = (threadSearch.value || '').trim().toLowerCase();
    const rows = state.messages.filter(m => q === '' || String(m.message_text || '').toLowerCase().indexOf(q) !== -1);
    feed.innerHTML = '';
    if (!rows.length) {
      feed.innerHTML = '<div class="messages-empty">No messages yet for this project.</div>';
      return;
    }
    rows.forEach(m => {
      const mine = Number(m.sender_user_id || 0) === currentUserId;
      const row = document.createElement('div');
      row.className = 'msg-row' + (mine ? ' mine' : '');
      const attachment = m.file_path ? ('<a class="msg-attachment" href="/' + esc(String(m.file_path).replace(/^\/+/, '')) + '" target="_blank" rel="noopener">Attachment: ' + esc(m.file_name || 'file') + '</a>') : '';
      const deleteBtn = mine ? (' <button class="messages-btn msg-delete-btn" data-message-id="' + Number(m.id || 0) + '">Delete</button>') : '';
      row.innerHTML = '<div class="msg-head"><span>' + esc((m.sender_name || 'Unknown') + ' (' + (m.sender_role || role) + ')') + '</span><span>' + esc(m.created_at || '') + deleteBtn + '</span></div>' +
        '<div class="msg-body">' + esc(m.message_text || '') + '</div>' + attachment;
      feed.appendChild(row);
    });
    feed.scrollTop = feed.scrollHeight;
  }

  function markRead() {
    if (!state.activeProjectId) return Promise.resolve();
    return apiPost('mark_project_messages_read', { project_id: state.activeProjectId }).then(() => {});
  }

  function loadProjects() {
    return apiGet('load_message_projects').then(j => {
      state.projects = Array.isArray((j || {}).data) ? j.data : [];
      if (!state.activeProjectId && preselectedProjectId > 0 && state.projects.some(p => Number(p.id) === preselectedProjectId)) {
        state.activeProjectId = preselectedProjectId;
      }
      if (!state.activeProjectId && state.projects.length) {
        state.activeProjectId = Number(state.projects[0].id || 0);
      }
      renderProjects();
      if (state.activeProjectId) return loadMessages();
      threadTitle.textContent = 'Select a project';
      feed.innerHTML = '<div class="messages-empty">Pick a project to open its thread.</div>';
    });
  }

  function loadMessages() {
    if (!state.activeProjectId) return;
    const project = state.projects.find(p => Number(p.id) === Number(state.activeProjectId));
    threadTitle.textContent = project ? ((project.code || 'PRJ') + ' - ' + (project.name || 'Project')) : 'Conversation';
    return apiGet('load_project_messages', '&project_id=' + encodeURIComponent(state.activeProjectId)).then(j => {
      state.messages = Array.isArray((j || {}).data) ? j.data : [];
      renderMessages();
      markRead().then(loadProjects);
    });
  }

  function sendMessage() {
    if (!state.activeProjectId) return;
    const text = (textInput.value || '').trim();
    const file = fileInput.files && fileInput.files[0];
    if (!text && !file) return;
    const fd = new FormData();
    fd.append('project_id', String(state.activeProjectId));
    fd.append('message_text', text);
    if (file) fd.append('attachment', file);
    sendBtn.disabled = true;
    apiPost('send_project_message', fd, true).then(j => {
      sendBtn.disabled = false;
      if (!j || j.success === false) return;
      textInput.value = '';
      fileInput.value = '';
      loadMessages();
    }).catch(() => { sendBtn.disabled = false; });
  }

  document.addEventListener('click', e => {
    const projectEl = e.target.closest('[data-project-id]');
    if (projectEl) {
      state.activeProjectId = Number(projectEl.getAttribute('data-project-id') || 0);
      renderProjects();
      loadMessages();
      return;
    }
    const del = e.target.closest('.msg-delete-btn[data-message-id]');
    if (del) {
      const id = Number(del.getAttribute('data-message-id') || 0);
      if (!id) return;
      apiPost('delete_project_message', { message_id: id }).then(loadMessages);
    }
  });
  projectSearch.addEventListener('input', renderProjects);
  threadSearch.addEventListener('input', renderMessages);
  sendBtn.addEventListener('click', sendMessage);
  textInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  loadProjects();
})();
