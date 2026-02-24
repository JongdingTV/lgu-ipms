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

  const state = {
    contacts: [],
    filtered: [],
    activeContactId: 0,
    messages: [],
    pollId: null,
    loadingMessages: false
  };

  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m] || m));
  const viewKey = 'dm_seen_' + role + '_' + userId;

  function getSeenMap() {
    try {
      const raw = window.localStorage.getItem(viewKey);
      const parsed = raw ? JSON.parse(raw) : {};
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  }

  function setSeen(contactId, isoTime) {
    const data = getSeenMap();
    data[String(contactId)] = String(isoTime || new Date().toISOString());
    try { window.localStorage.setItem(viewKey, JSON.stringify(data)); } catch (_) {}
  }

  function getSeen(contactId) {
    const data = getSeenMap();
    return String(data[String(contactId)] || '');
  }

  function toDateMs(v) {
    const t = Date.parse(String(v || '').replace(' ', 'T'));
    return Number.isNaN(t) ? 0 : t;
  }

  function formatTime(v) {
    const ms = toDateMs(v);
    if (!ms) return '';
    const d = new Date(ms);
    return d.toLocaleString();
  }

  function createThreadActions() {
    const head = threadTitle.parentElement;
    if (!head) return { refreshBtn: null, deleteBtn: null, statusEl: null };

    let statusEl = head.querySelector('.messages-status');
    if (!statusEl) {
      statusEl = document.createElement('div');
      statusEl.className = 'messages-status';
      statusEl.textContent = 'Ready';
      head.appendChild(statusEl);
    }

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
      deleteBtn.textContent = 'Delete Conversation';
      head.appendChild(deleteBtn);
    }

    return { refreshBtn, deleteBtn, statusEl };
  }

  const threadUi = createThreadActions();

  function setStatus(text, isError) {
    if (!threadUi.statusEl) return;
    threadUi.statusEl.textContent = text || '';
    threadUi.statusEl.classList.toggle('error', !!isError);
  }

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

  function updateSendButtonState() {
    const val = (textInput.value || '').trim();
    sendBtn.disabled = !state.activeContactId || val.length === 0;
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

      const unread = Number(c.unread || 0);
      const unreadHtml = unread > 0 ? '<span class="messages-unread">' + unread + '</span>' : '';
      const lastAt = c.last_at ? formatTime(c.last_at) : '';
      const preview = c.last_text ? esc(c.last_text) : '';

      div.innerHTML =
        '<div class="messages-contact-head"><strong>' + esc(c.display_name || 'Contact') + '</strong>' + unreadHtml + '</div>' +
        '<div class="messages-meta"><span>' + esc(c.role_label || '') + '</span><span>' + esc(c.email || '') + '</span></div>' +
        (preview ? '<div class="messages-preview">' + preview + '</div>' : '') +
        (lastAt ? '<div class="messages-time">' + esc(lastAt) + '</div>' : '');
      contactList.appendChild(div);
    });
  }

  function refreshContactStats() {
    state.contacts.forEach((c) => {
      c.unread = 0;
      c.last_at = '';
      c.last_text = '';
    });

    const activeId = Number(state.activeContactId || 0);
    if (!activeId || !Array.isArray(state.messages)) return;

    const seenTime = getSeen(activeId);
    const seenMs = toDateMs(seenTime);
    const mine = (m) => Number(m.sender_user_id) === Number(userId) && String(m.sender_role || '').toLowerCase() === role;

    let last = null;
    let unread = 0;
    state.messages.forEach((m) => {
      if (!last || toDateMs(m.created_at) > toDateMs(last.created_at)) last = m;
      if (!mine(m) && toDateMs(m.created_at) > seenMs) unread += 1;
    });

    const contact = state.contacts.find((c) => Number(c.user_id) === activeId);
    if (contact && last) {
      contact.last_at = String(last.created_at || '');
      contact.last_text = String(last.message_text || '').slice(0, 72);
      contact.unread = unread;
    }
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
      const sender = mine ? 'You' : 'Contact';
      const at = formatTime(m.created_at || '');
      return '<article class="msg-row ' + (mine ? 'mine' : 'theirs') + '">' +
        '<div class="msg-head"><strong>' + esc(sender) + '</strong><span>' + esc(at) + '</span></div>' +
        '<div class="msg-body">' + esc(m.message_text || '') + '</div>' +
        '<button class="msg-copy" type="button" data-copy="' + esc(m.message_text || '') + '">Copy</button>' +
      '</article>';
    }).join('');

    feed.scrollTop = feed.scrollHeight;
  }

  function loadContacts(keepSelection) {
    return apiGet('load_chat_contacts').then((j) => {
      state.contacts = Array.isArray((j || {}).data) ? j.data : [];
      if ((!state.activeContactId || !keepSelection) && state.contacts.length) {
        state.activeContactId = Number(state.contacts[0].user_id || 0);
      }
      if (state.activeContactId && !state.contacts.some((c) => Number(c.user_id) === Number(state.activeContactId))) {
        state.activeContactId = state.contacts.length ? Number(state.contacts[0].user_id || 0) : 0;
      }
      renderContacts();
      updateSendButtonState();
      if (!state.activeContactId) {
        threadTitle.textContent = 'Select a contact';
        feed.innerHTML = '<div class="messages-empty">Pick a contact to open messages.</div>';
      }
      return state.activeContactId ? loadMessages(true) : null;
    }).catch(() => {
      setStatus('Failed to load contacts.', true);
    });
  }

  function loadMessages(silent) {
    if (!state.activeContactId) return Promise.resolve();
    if (state.loadingMessages) return Promise.resolve();

    state.loadingMessages = true;
    const active = state.contacts.find((c) => Number(c.user_id) === Number(state.activeContactId));
    threadTitle.textContent = active ? ('Chat with ' + String(active.display_name || 'Contact')) : 'Messages';
    if (!silent) setStatus('Loading conversation...');

    return apiGet('load_direct_messages', '&contact_user_id=' + encodeURIComponent(state.activeContactId)).then((j) => {
      state.messages = Array.isArray((j || {}).data) ? j.data : [];
      renderMessages();
      setSeen(state.activeContactId, new Date().toISOString());
      refreshContactStats();
      renderContacts();
      setStatus('Synced at ' + new Date().toLocaleTimeString());
    }).catch(() => {
      setStatus('Failed to load messages.', true);
    }).finally(() => {
      state.loadingMessages = false;
    });
  }

  function sendMessage() {
    const text = (textInput.value || '').trim();
    if (!state.activeContactId || !text) return;
    sendBtn.disabled = true;
    setStatus('Sending...');

    apiPost('send_direct_message', { contact_user_id: state.activeContactId, message_text: text }).then((j) => {
      if (!j || j.success === false) {
        setStatus('Message failed: ' + String((j && j.message) || 'Unable to send'), true);
        sendBtn.disabled = false;
        return;
      }
      textInput.value = '';
      updateSendButtonState();
      loadMessages(true);
    }).catch(() => {
      setStatus('Message failed: Network error', true);
      sendBtn.disabled = false;
    }).finally(() => {
      sendBtn.disabled = false;
    });
  }

  function deleteConversation() {
    if (!state.activeContactId) return;
    const active = state.contacts.find((c) => Number(c.user_id) === Number(state.activeContactId));
    const label = active ? String(active.display_name || 'this contact') : 'this contact';
    if (!window.confirm('Delete entire conversation with ' + label + '?')) return;

    apiPost('delete_direct_conversation', { contact_user_id: state.activeContactId }).then((j) => {
      if (!j || j.success === false) {
        setStatus('Delete failed: ' + String((j && j.message) || 'Unable to delete'), true);
        return;
      }
      state.messages = [];
      renderMessages();
      setStatus('Conversation deleted.');
      loadContacts(true);
    }).catch(() => {
      setStatus('Delete failed: Network error', true);
    });
  }

  function addQuickEmojiBar() {
    const composer = document.querySelector('.messages-composer');
    if (!composer || composer.querySelector('.messages-emoji-bar')) return;
    const bar = document.createElement('div');
    bar.className = 'messages-emoji-bar';
    bar.innerHTML = [
      '<button type="button" data-emoji="??">??</button>',
      '<button type="button" data-emoji="?">?</button>',
      '<button type="button" data-emoji="??">??</button>',
      '<button type="button" data-emoji="??">??</button>',
      '<button type="button" data-emoji="??">??</button>'
    ].join('');
    composer.parentElement.insertBefore(bar, composer);

    bar.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-emoji]');
      if (!btn) return;
      const emoji = String(btn.getAttribute('data-emoji') || '');
      textInput.value = ((textInput.value || '').trim() + ' ' + emoji).trim() + ' ';
      textInput.focus();
      updateSendButtonState();
    });
  }

  contactList.addEventListener('click', (e) => {
    const btn = e.target.closest('.messages-project-item[data-id]');
    if (!btn) return;
    state.activeContactId = Number(btn.dataset.id || 0);
    renderContacts();
    loadMessages();
    updateSendButtonState();
  });

  feed.addEventListener('click', (e) => {
    const copyBtn = e.target.closest('.msg-copy[data-copy]');
    if (!copyBtn) return;
    const text = String(copyBtn.getAttribute('data-copy') || '');
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => setStatus('Message copied.'));
    } else {
      setStatus('Clipboard is unavailable on this browser.', true);
    }
  });

  contactSearch.addEventListener('input', renderContacts);
  threadSearch.addEventListener('input', renderMessages);
  textInput.addEventListener('input', updateSendButtonState);
  sendBtn.addEventListener('click', sendMessage);

  if (threadUi.deleteBtn) threadUi.deleteBtn.addEventListener('click', deleteConversation);
  if (threadUi.refreshBtn) threadUi.refreshBtn.addEventListener('click', () => {
    loadMessages();
    loadContacts(true);
  });

  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  addQuickEmojiBar();
  updateSendButtonState();
  loadContacts(true);

  state.pollId = window.setInterval(() => {
    if (!document.hidden && state.activeContactId) {
      loadMessages(true);
    }
  }, 7000);
})();
