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
  const pageTitle = root.querySelector('.dash-header h1');

  if (!apiBase || !contactSearch || !threadSearch || !contactList || !threadTitle || !feed || !textInput || !sendBtn) return;
  if (fileInput) fileInput.style.display = 'none';

  const state = {
    contacts: [],
    activeContactId: 0,
    messages: [],
    loading: false,
    pollId: null
  };

  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m] || m));

  function formatTime(v) {
    const t = Date.parse(String(v || '').replace(' ', 'T'));
    if (Number.isNaN(t)) return '';
    return new Date(t).toLocaleString();
  }

  function apiGet(action, extra) {
    const join = (extra && String(extra).indexOf('?') === 0) ? '&' : '';
    const bust = '&_ts=' + Date.now();
    return fetch(apiBase + '?action=' + encodeURIComponent(action) + (extra || '') + join + bust, {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(parseApiResponse);
  }

  function apiPost(action, payload) {
    const body = new URLSearchParams();
    Object.keys(payload || {}).forEach((k) => body.set(k, String(payload[k])));
    body.set('csrf_token', csrf);
    return fetch(apiBase + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(parseApiResponse);
  }

  function parseApiResponse(response) {
    return response.text().then((text) => {
      let parsed = null;
      try {
        parsed = JSON.parse(text);
      } catch (_) {
        parsed = null;
      }

      if (parsed && typeof parsed === 'object') {
        if (response.ok) return parsed;
        return Object.assign({ success: false, message: parsed.message || ('HTTP ' + response.status) }, parsed);
      }

      const snippet = String(text || '').replace(/\s+/g, ' ').trim().slice(0, 180);
      const message = snippet || ('HTTP ' + response.status + ' ' + response.statusText);
      return { success: false, message: message };
    }).catch(() => ({ success: false, message: 'Network/response parsing error.' }));
  }

  function ensureThreadButtons() {
    const head = threadTitle.parentElement;
    if (!head) return {};

    let refreshBtn = head.querySelector('.messages-refresh-btn');
    if (!refreshBtn) {
      refreshBtn = document.createElement('button');
      refreshBtn.type = 'button';
      refreshBtn.className = 'messages-btn messages-refresh-btn';
      refreshBtn.textContent = 'Refresh';
      head.appendChild(refreshBtn);
    }

    let deleteBtn = head.querySelector('.messages-delete-btn');
    if (!deleteBtn) {
      deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'messages-btn messages-delete-btn';
      deleteBtn.textContent = 'Delete';
      head.appendChild(deleteBtn);
    }

    return { refreshBtn, deleteBtn };
  }

  function renderGlobalUnread() {
    if (!pageTitle) return;
    let badge = pageTitle.querySelector('.messages-total-unread');
    const total = state.contacts.reduce((sum, c) => sum + Number(c.unread_count || 0), 0);
    if (total <= 0) {
      if (badge) badge.remove();
      return;
    }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'messages-total-unread';
      pageTitle.appendChild(badge);
    }
    badge.textContent = String(total);
  }

  function renderContacts() {
    const q = (contactSearch.value || '').trim().toLowerCase();
    const rows = state.contacts.filter((c) => {
      const hay = [c.display_name, c.email, c.role_label].join(' ').toLowerCase();
      return q === '' || hay.indexOf(q) !== -1;
    });

    if (!rows.length) {
      contactList.innerHTML = '<div class="messages-empty">No contacts found.</div>';
      return;
    }

    contactList.innerHTML = rows.map((c) => {
      const isActive = Number(c.user_id) === Number(state.activeContactId);
      const name = String(c.display_name || 'Contact');
      const initial = esc(name.charAt(0).toUpperCase() || 'C');
      const unread = Number(c.unread_count || 0);
      const unreadHtml = unread > 0 ? '<span class="messages-unread-dot">' + unread + '</span>' : '';
      return '<button type="button" class="messages-project-item' + (isActive ? ' active' : '') + '" data-id="' + Number(c.user_id) + '">' +
        '<div class="messages-contact-avatar">' + initial + '</div>' +
        '<div class="messages-contact-main">' +
          '<div class="messages-contact-title">' + esc(name) + unreadHtml + '</div>' +
          '<div class="messages-meta"><span>' + esc(c.role_label || '') + '</span><span>' + esc(c.email || '') + '</span></div>' +
        '</div>' +
      '</button>';
    }).join('');
    renderGlobalUnread();
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
      const when = formatTime(m.created_at || '');
      const senderInitial = mine ? 'Y' : 'C';
      return '<article class="msg-row ' + (mine ? 'mine' : 'theirs') + '">' +
        (mine ? '' : '<div class="msg-avatar">' + senderInitial + '</div>') +
        '<div class="msg-bubble">' +
        '<div class="msg-head"><strong>' + (mine ? 'You' : 'Contact') + '</strong><span>' + esc(when) + '</span></div>' +
        '<div class="msg-body">' + esc(m.message_text || '') + '</div>' +
        '</div>' +
      '</article>';
    }).join('');

    feed.scrollTop = feed.scrollHeight;
  }

  function updateSendState() {
    sendBtn.disabled = !state.activeContactId || !(textInput.value || '').trim();
  }

  function loadContacts() {
    return apiGet('load_chat_contacts').then((j) => {
      if (!j || j.success === false) {
        const msg = String((j && j.message) || 'Unable to load contacts.');
        contactList.innerHTML = '<div class="messages-empty">' + esc(msg) + '</div>';
        return null;
      }
      state.contacts = Array.isArray((j || {}).data) ? j.data : [];
      if (!state.activeContactId && state.contacts.length) {
        state.activeContactId = Number(state.contacts[0].user_id || 0);
      }
      if (state.activeContactId && !state.contacts.some((c) => Number(c.user_id) === Number(state.activeContactId))) {
        state.activeContactId = state.contacts.length ? Number(state.contacts[0].user_id || 0) : 0;
      }
      renderContacts();
      updateSendState();
      if (state.activeContactId) return loadMessages(true);
      threadTitle.textContent = 'Select a contact';
      feed.innerHTML = '<div class="messages-empty">Pick a contact to open messages.</div>';
      return null;
    });
  }

  function loadMessages(silent) {
    if (!state.activeContactId || state.loading) return Promise.resolve();
    state.loading = true;

    const active = state.contacts.find((c) => Number(c.user_id) === Number(state.activeContactId));
    threadTitle.textContent = active ? 'Chat with ' + String(active.display_name || 'Contact') : 'Messages';
    if (!silent) feed.innerHTML = '<div class="messages-empty">Loading...</div>';

    return apiGet('load_direct_messages', '&contact_user_id=' + encodeURIComponent(state.activeContactId)).then((j) => {
      if (!j || j.success === false) {
        const msg = String((j && j.message) || 'Unable to load messages.');
        feed.innerHTML = '<div class="messages-empty">' + esc(msg) + '</div>';
        return;
      }
      state.messages = Array.isArray((j || {}).data) ? j.data : [];
      renderMessages();
    }).finally(() => {
      state.loading = false;
    });
  }

  function sendMessage() {
    const text = (textInput.value || '').trim();
    if (!state.activeContactId || !text) return;
    sendBtn.disabled = true;

    apiPost('send_direct_message', { contact_user_id: state.activeContactId, message_text: text }).then((j) => {
      if (!j || j.success === false) {
        alert(String((j && j.message) || 'Unable to send message.'));
        return;
      }
      textInput.value = '';
      updateSendState();
      loadMessages(true);
    }).catch(() => {
      alert('Network error while sending message.');
    }).finally(() => {
      updateSendState();
    });
  }

  function deleteConversation() {
    if (!state.activeContactId) return;
    if (!window.confirm('Delete this conversation?')) return;

    apiPost('delete_direct_conversation', { contact_user_id: state.activeContactId }).then((j) => {
      if (!j || j.success === false) {
        alert(String((j && j.message) || 'Unable to delete conversation.'));
        return;
      }
      state.messages = [];
      renderMessages();
    }).catch(() => {
      alert('Network error while deleting conversation.');
    });
  }

  contactList.addEventListener('click', (e) => {
    const btn = e.target.closest('.messages-project-item[data-id]');
    if (!btn) return;
    state.activeContactId = Number(btn.getAttribute('data-id') || 0);
    renderContacts();
    updateSendState();
    loadMessages();
  });

  contactSearch.addEventListener('input', renderContacts);
  threadSearch.addEventListener('input', renderMessages);
  textInput.addEventListener('input', updateSendState);
  sendBtn.addEventListener('click', sendMessage);
  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  const buttons = ensureThreadButtons();
  if (buttons.refreshBtn) buttons.refreshBtn.addEventListener('click', () => loadMessages());
  if (buttons.deleteBtn) buttons.deleteBtn.addEventListener('click', deleteConversation);

  loadContacts();
  updateSendState();

  state.pollId = window.setInterval(() => {
    if (!document.hidden && state.activeContactId) {
      loadMessages(true);
    }
  }, 6000);
})();
