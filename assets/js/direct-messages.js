'use strict';

(function () {
  const root = document.querySelector('[data-messages-root]');
  if (!root) return;

  const apiBase = root.getAttribute('data-api-base') || '';
  const role = String(root.getAttribute('data-role') || '').toLowerCase();
  const csrf = String(root.getAttribute('data-csrf') || '');
  const userId = Number(root.getAttribute('data-user-id') || 0);

  const contactSearch = document.getElementById('messageProjectSearch');
  const threadSearch = document.getElementById('messageThreadSearch');
  const contactList = document.getElementById('messageProjectList');
  const threadTitle = document.getElementById('messageThreadTitle');
  const feed = document.getElementById('messageFeed');
  const textInput = document.getElementById('messageText');
  const sendBtn = document.getElementById('messageSendBtn');
  const fileInput = document.getElementById('messageFile');
  if (!apiBase || !contactSearch || !threadSearch || !contactList || !threadTitle || !feed || !textInput || !sendBtn) return;
  if (fileInput) fileInput.style.display = 'none';

  const state = { contacts: [], filtered: [], activeContactId: 0, messages: [] };
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m] || m));

  function apiGet(action, extra) {
    return fetch(apiBase + '?action=' + encodeURIComponent(action) + (extra || ''), { credentials: 'same-origin' })
      .then((r) => r.json().catch(() => ({ success: false, message: 'Invalid server response.' })));
  }

  function apiPost(action, payload) {
    const body = new URLSearchParams();
    Object.keys(payload || {}).forEach((k) => body.set(k, String(payload[k])));
    body.set('csrf_token', csrf);
    return fetch(apiBase + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then((r) => r.json().catch(() => ({ success: false, message: 'Invalid server response.' })));
  }

  function renderContacts() {
    const q = (contactSearch.value || '').trim().toLowerCase();
    state.filtered = state.contacts.filter((c) => {
      const hay = [c.display_name, c.email].join(' ').toLowerCase();
      return q === '' || hay.indexOf(q) !== -1;
    });
    if (!state.filtered.length) {
      contactList.innerHTML = '<div class="messages-empty">No chat contacts found.</div>';
      return;
    }
    contactList.innerHTML = '';
    state.filtered.forEach((c) => {
      const div = document.createElement('button');
      div.type = 'button';
      div.className = 'messages-project-item' + (Number(c.user_id) === Number(state.activeContactId) ? ' active' : '');
      div.dataset.id = String(c.user_id);
      div.innerHTML =
        '<div><strong>' + esc(c.display_name || 'Contact') + '</strong></div>' +
        '<div class="messages-meta"><span>' + esc(c.role_label || '') + '</span><span>' + esc(c.email || '') + '</span></div>';
      contactList.appendChild(div);
    });
  }

  function renderMessages() {
    const q = (threadSearch.value || '').trim().toLowerCase();
    const rows = state.messages.filter((m) => q === '' || String(m.message_text || '').toLowerCase().indexOf(q) !== -1);
    if (!rows.length) {
      feed.innerHTML = '<div class="messages-empty">No messages yet.</div>';
      return;
    }
    feed.innerHTML = rows.map((m) => {
      const mine = Number(m.sender_user_id) === Number(userId) && String(m.sender_role || '').toLowerCase() === role;
      return '<article class="msg-row ' + (mine ? 'mine' : 'theirs') + '">' +
        '<div class="msg-meta"><strong>' + esc(m.sender_name || (mine ? 'You' : 'Contact')) + '</strong><span>' + esc(m.created_at || '') + '</span></div>' +
        '<div class="msg-body">' + esc(m.message_text || '') + '</div>' +
      '</article>';
    }).join('');
    feed.scrollTop = feed.scrollHeight;
  }

  function loadContacts() {
    return apiGet('load_chat_contacts').then((j) => {
      state.contacts = Array.isArray((j || {}).data) ? j.data : [];
      if (!state.activeContactId && state.contacts.length) {
        state.activeContactId = Number(state.contacts[0].user_id || 0);
      }
      renderContacts();
      if (state.activeContactId) return loadMessages();
      threadTitle.textContent = 'Select a contact';
      feed.innerHTML = '<div class="messages-empty">Pick a contact to open messages.</div>';
    });
  }

  function loadMessages() {
    if (!state.activeContactId) return Promise.resolve();
    const active = state.contacts.find((c) => Number(c.user_id) === Number(state.activeContactId));
    threadTitle.textContent = active ? ('Chat with ' + String(active.display_name || 'Contact')) : 'Messages';
    return apiGet('load_direct_messages', '&contact_user_id=' + encodeURIComponent(state.activeContactId)).then((j) => {
      state.messages = Array.isArray((j || {}).data) ? j.data : [];
      renderMessages();
    });
  }

  function sendMessage() {
    const text = (textInput.value || '').trim();
    if (!state.activeContactId || !text) return;
    sendBtn.disabled = true;
    apiPost('send_direct_message', { contact_user_id: state.activeContactId, message_text: text }).then((j) => {
      sendBtn.disabled = false;
      if (!j || j.success === false) return;
      textInput.value = '';
      loadMessages();
    }).catch(() => { sendBtn.disabled = false; });
  }

  contactList.addEventListener('click', (e) => {
    const btn = e.target.closest('.messages-project-item[data-id]');
    if (!btn) return;
    state.activeContactId = Number(btn.dataset.id || 0);
    renderContacts();
    loadMessages();
  });
  contactSearch.addEventListener('input', renderContacts);
  threadSearch.addEventListener('input', renderMessages);
  sendBtn.addEventListener('click', sendMessage);
  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  loadContacts();
})();

