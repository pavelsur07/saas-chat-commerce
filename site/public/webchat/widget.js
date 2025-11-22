(()=> {
  'use strict';

  const by = (sel, root = document) => root.querySelector(sel);
  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };
  const nowISO = () => new Date().toISOString();
  const fmtTime = (d) => new Date(d).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

  const getScriptEl = () => {
    const cs = document.currentScript;
    if (cs && cs.dataset) return cs;
    const scripts = Array.from(document.scripts);
    for (let i = scripts.length - 1; i >= 0; i--) {
      const s = scripts[i];
      const src = s.getAttribute('src') || '';
      if (src.includes('/webchat/widget.js')) return s;
    }
    return { dataset: {} };
  };

  const scriptTag = getScriptEl();
  const SITE_KEY = scriptTag.dataset.siteKey || scriptTag.getAttribute('data-site-key') || '';
  if (!SITE_KEY) {
    console.warn('[WebChat] data-site-key is missing on <script> tag.');
  }

  const DEFAULT_API_BASE = 'https://chat.2bstock.ru';
  const DEFAULT_SOCKET_BASE = 'https://chat.2bstock.ru';

  const getDataAttr = (name) => {
    if (!scriptTag) return null;
    if (scriptTag.dataset && Object.prototype.hasOwnProperty.call(scriptTag.dataset, name)) {
      return scriptTag.dataset[name];
    }
    if (typeof scriptTag.getAttribute === 'function') {
      return scriptTag.getAttribute(`data-${name.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)}`);
    }
    return null;
  };

  const resolveUrl = (value, purpose) => {
    if (!value) return null;
    try {
      return new URL(value, window.location.href);
    } catch (e) {
      console.warn(`[WebChat] invalid ${purpose || 'URL'}:`, value, e);
      return null;
    }
  };

  const explicitApiBase = getDataAttr('apiBase');
  const explicitSocketBase = getDataAttr('socketBase');

  const apiBaseUrl = resolveUrl(explicitApiBase, 'data-api-base')
    || resolveUrl(DEFAULT_API_BASE, 'default api base')
    || resolveUrl(scriptTag?.getAttribute?.('src') || window.location.href, 'widget src');
  const socketBaseUrl = resolveUrl(explicitSocketBase, 'data-socket-base')
    || resolveUrl(DEFAULT_SOCKET_BASE, 'default socket base')
    || resolveUrl(apiBaseUrl?.origin || window.location.href, 'socket origin');

  const apiBase = apiBaseUrl?.toString() || window.location.origin;
  const socketBase = (() => {
    if (!socketBaseUrl) return window.location.origin;
    const path = (socketBaseUrl.pathname || '').replace(/\/$/, '');
    if (path && path !== '/') {
      return `${socketBaseUrl.origin}${path}`;
    }
    return socketBaseUrl.origin;
  })();

  const buildApiUrl = (path, params = {}) => {
    const url = new URL(path, apiBase);
    Object.entries(params).forEach(([key, value]) => {
      if (value == null) return;
      const stringValue = String(value);
      if (stringValue.trim() === '') return;
      url.searchParams.set(key, stringValue);
    });
    return url.toString();
  };

  const DB_NAME = `webchat_${SITE_KEY || 'default'}`;
  const DB_VERSION = 2;
  let dbPromise = null;

  const openDb = () => {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onerror = () => reject(req.error);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains('messages')) {
          const store = db.createObjectStore('messages', { keyPath: 'id' });
          store.createIndex('thread_created', ['threadId', 'createdAt']);
        }
        if (!db.objectStoreNames.contains('client')) {
          db.createObjectStore('client', { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains('sync')) {
          db.createObjectStore('sync', { keyPath: 'threadId' });
        }
        if (!db.objectStoreNames.contains('outbox')) {
          db.createObjectStore('outbox', { keyPath: 'tmpId' });
        }
      };
      req.onsuccess = () => resolve(req.result);
    });
    return dbPromise;
  };

  const withStore = async (storeName, mode, fn) => {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, mode);
      const store = tx.objectStore(storeName);
      const result = fn(store, tx);
      tx.oncomplete = () => resolve(result);
      tx.onerror = () => reject(tx.error);
    });
  };

  const getClientRecord = async (key) => withStore('client', 'readonly', (store) => store.get(key));
  const putClientRecord = async (record) => withStore('client', 'readwrite', (store) => store.put(record));

  const listMessages = async (threadId, limit = 50) => withStore('messages', 'readonly', (store) => {
    const index = store.index('thread_created');
    const range = IDBKeyRange.bound([threadId, '0000'], [threadId, '\uffff']);
    const req = index.openCursor(range, 'prev');
    const items = [];
    return new Promise((resolve) => {
      req.onsuccess = () => {
        const cursor = req.result;
        if (!cursor || items.length >= limit) {
          resolve(items.reverse());
          return;
        }
        items.push(cursor.value);
        cursor.continue();
      };
      req.onerror = () => resolve(items.reverse());
    });
  });

  const saveMessages = async (messages) => withStore('messages', 'readwrite', (store) => {
    [].concat(messages).forEach((m) => store.put(m));
  });

  const deleteMessagesByThread = async (threadId) => withStore('messages', 'readwrite', (store) => {
    const index = store.index('thread_created');
    const range = IDBKeyRange.bound([threadId, '0000'], [threadId, '\uffff']);
    const req = index.openCursor(range);
    req.onsuccess = () => {
      const cursor = req.result;
      if (!cursor) return;
      store.delete(cursor.primaryKey);
      cursor.continue();
    };
  });

  const getSyncState = async (threadId) => withStore('sync', 'readonly', (store) => store.get(threadId));
  const putSyncState = async (threadId, state) => withStore('sync', 'readwrite', (store) => store.put({ threadId, ...state }));
  const deleteSyncState = async (threadId) => withStore('sync', 'readwrite', (store) => store.delete(threadId));

  const enqueueOutbox = async (entry) => withStore('outbox', 'readwrite', (store) => store.put(entry));
  const removeOutbox = async (tmpId) => withStore('outbox', 'readwrite', (store) => store.delete(tmpId));
  const listOutbox = async () => withStore('outbox', 'readonly', (store) => {
    return new Promise((resolve) => {
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => resolve([]);
    });
  });

  const cookie = {
    set(name, value, { days = 365, sameSite = 'Lax' } = {}) {
      const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
      document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=${sameSite}`;
    },
    get(name) {
      return document.cookie
        .split(';')
        .map((s) => s.trim())
        .find((item) => item.startsWith(`${name}=`))?.split('=')[1] || null;
    },
  };

  const state = {
    visitorId: null,
    sessionId: null,
    threadId: null,
    token: null,
    tokenExpiresAt: 0,
    socket: null,
    socketPath: '/socket.io',
    messages: [],
    outboxProcessing: false,
    isOnline: navigator.onLine,
    hydrateComplete: false,
  };

  const setVisitorId = async (visitorId) => {
    state.visitorId = visitorId;
    cookie.set('wc_vid', visitorId, { days: 365 });
    await putClientRecord({ key: 'visitor', value: visitorId });
  };

  const loadStoredVisitor = async () => {
    const fromCookie = cookie.get('wc_vid');
    if (fromCookie) return fromCookie;
    const record = await getClientRecord('visitor');
    if (record && typeof record.value === 'string') {
      return record.value;
    }
    return null;
  };

  const loadStoredThread = async () => {
    const record = await getClientRecord('thread');
    if (record && typeof record.value === 'string') {
      return record.value;
    }
    return null;
  };

  const saveThread = async (threadId) => {
    if (!threadId) {
      await withStore('client', 'readwrite', (store) => store.delete('thread'));
      return;
    }

    await putClientRecord({ key: 'thread', value: threadId });
  };

  const generateId = () => (window.crypto?.randomUUID?.() || Math.random().toString(16).slice(2));

  const toHeaderObject = (value) => {
    if (!value) return {};
    if (typeof Headers !== 'undefined' && value instanceof Headers) {
      const result = {};
      value.forEach((headerValue, headerName) => {
        result[headerName] = headerValue;
      });
      return result;
    }
    return { ...value };
  };

  const hasHeader = (headers, name) => Object.keys(headers).some((key) => key.toLowerCase() === name.toLowerCase());

  const apiFetch = async (url, options = {}) => {
    const headers = toHeaderObject(options.headers);
    if (state.token && options.includeAuth !== false && !hasHeader(headers, 'Authorization')) {
      headers['Authorization'] = `Bearer ${state.token}`;
    }

    const fetchOptions = {
      method: options.method || 'GET',
      credentials: options.credentials || 'include',
      headers,
    };

    let body = options.body;
    const shouldHandleJson = options.json !== false && body !== undefined && !(body instanceof FormData);
    if (shouldHandleJson) {
      body = JSON.stringify(body);
      if (!hasHeader(headers, 'Content-Type')) {
        headers['Content-Type'] = 'application/json';
      }
    }

    if (body !== undefined) {
      fetchOptions.body = body;
    }

    return fetch(url, fetchOptions);
  };

  const ensureTokenFresh = async () => {
    const now = Date.now();
    if (!state.token || now + 120000 > state.tokenExpiresAt) {
      await handshake();
    }
  };

  const handshake = async () => {
    const payload = {
      site_key: SITE_KEY,
      visitor_id: state.visitorId,
      page_url: window.location.href,
    };
    if (state.sessionId) {
      payload.session_id = state.sessionId;
    }
    const res = await apiFetch(buildApiUrl('/api/webchat/handshake', {
      site_key: payload.site_key,
      page_url: payload.page_url,
    }), {
      method: 'POST',
      body: payload,
      includeAuth: false,
    });
    if (!res.ok) {
      throw new Error(`Handshake failed (${res.status})`);
    }
    const data = await res.json();
    if (data.visitor_id && data.visitor_id !== state.visitorId) {
      await setVisitorId(data.visitor_id);
    }
    state.sessionId = data.session_id || state.sessionId || generateId();
    state.threadId = data.thread_id || null;
    state.token = data.token || null;
    state.tokenExpiresAt = Date.now() + (Number(data.expires_in || 0) * 1000);
    state.socketPath = data.socket_path || state.socketPath;
    await saveThread(state.threadId);
    return data;
  };

  const normalizeDirection = (direction) => {
    const value = typeof direction === 'string' ? direction.toLowerCase() : '';
    if (value === 'in' || value === 'out') return value;
    if (value === 'inbound') return 'in';
    if (value === 'outbound') return 'out';
    return 'in';
  };

  const loadLocalMessages = async () => {
    if (!state.threadId) return [];
    state.messages = await listMessages(state.threadId, 200);
    return state.messages;
  };

  const syncWithServer = async () => {
    if (!state.threadId) return;
    await ensureTokenFresh();

    const syncState = await getSyncState(state.threadId);
    const params = { site_key: SITE_KEY, thread_id: state.threadId };
    if (syncState?.lastSyncedAt) {
      params.since = syncState.lastSyncedAt;
    }
    const res = await apiFetch(buildApiUrl('/api/webchat/messages', params));
    if (!res.ok) return;
    const data = await res.json();
    if (!Array.isArray(data.messages)) return;

    const newMessages = [];
    for (const msg of data.messages) {
      if (!msg || !msg.id) continue;
      const stored = {
        id: msg.id,
        threadId: state.threadId,
        direction: normalizeDirection(msg.direction),
        text: msg.text ?? '',
        payload: msg.payload ?? null,
        createdAt: msg.created_at ?? nowISO(),
        deliveredAt: msg.delivered_at || null,
        readAt: msg.read_at || null,
        tmpId: null,
        status: msg.direction === 'out' && !msg.read_at ? 'delivered' : 'read',
      };
      newMessages.push(stored);
      upsertMessage(stored);
    }

    if (newMessages.length > 0) {
      await saveMessages(newMessages);
      state.messages = await listMessages(state.threadId, 200);
      renderAllMessages();
    }

    const last = data.messages[data.messages.length - 1];
    if (last?.created_at) {
      await putSyncState(state.threadId, { lastSyncedAt: last.created_at });
    }
  };

  const postMessage = async (text, tmpId) => {
    await ensureTokenFresh();
    const payload = {
      site_key: SITE_KEY,
      text,
      tmp_id: tmpId,
    };
    if (state.threadId) {
      payload.thread_id = state.threadId;
    }
    if (state.sessionId) {
      payload.session_id = state.sessionId;
    }
    if (state.visitorId) {
      payload.visitor_id = state.visitorId;
    }
    const res = await apiFetch(
      buildApiUrl('/api/webchat/messages', {
        site_key: SITE_KEY,
        thread_id: state.threadId,
        page_url: window.location.href,
      }),
      { method: 'POST', body: payload }
    );
    if (!res.ok) throw new Error(`Send failed (${res.status})`);
    const data = await res.json();

    if (data.thread_id && data.thread_id !== state.threadId) {
      state.threadId = data.thread_id;
      await saveThread(state.threadId);
    }

    if (data.token) {
      state.token = data.token;
      state.tokenExpiresAt = Date.now() + (Number(data.expires_in || 0) * 1000);
    }

    if (data.client_id) {
      await putClientRecord({ key: 'client_id', value: data.client_id });
    }

    return data;
  };

  const sendAck = async ({ delivered = [], read = [] }) => {
    if (!state.threadId || (!delivered.length && !read.length)) return;
    try {
      await ensureTokenFresh();
      await apiFetch(
        buildApiUrl('/api/webchat/ack', {
          site_key: SITE_KEY,
          thread_id: state.threadId,
          page_url: window.location.href,
        }),
        {
          method: 'POST',
          body: {
            site_key: SITE_KEY,
            thread_id: state.threadId,
            delivered,
            read,
          },
        }
      );
    } catch (err) {
      console.warn('[WebChat] ack failed', err);
    }
  };

  let fabBtn, panel, dot, msgBox, inputArea, ta, sendBtn, emptyHint;

  const injectStyles = () => {
    const css = `
:root {
  --wc-bg: #0b1220;
  --wc-bg-2: #0f172a;
  --wc-accent: #3b82f6;
  --wc-text: #e5e7eb;
  --wc-muted: #94a3b8;
  --wc-danger: #ef4444;
  --wc-shadow: 0 10px 30px rgba(0,0,0,0.3);
  --wc-radius: 14px;
}
.wc-fab {
  position: fixed;
  right: 20px; bottom: 20px;
  width: 56px; height: 56px;
  border-radius: 50%;
  background: var(--wc-accent);
  color: white;
  display: grid; place-items: center;
  font: 600 20px/1 system-ui, -apple-system, Segoe UI, Roboto, 'Inter', sans-serif;
  box-shadow: var(--wc-shadow);
  cursor: pointer;
  z-index: 2147483000;
}
.wc-panel {
  position: fixed;
  right: 20px; bottom: 90px;
  width: min(360px, calc(100vw - 30px));
  height: 560px;
  display: none;
  grid-template-rows: auto 1fr auto;
  background: var(--wc-bg-2);
  color: var(--wc-text);
  border-radius: var(--wc-radius);
  box-shadow: var(--wc-shadow);
  overflow: hidden;
  z-index: 2147483000;
}
.wc-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px;
  background: var(--wc-bg);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.wc-brand {
  display:flex; align-items:center; gap:10px;
  font: 600 14px/1.2 system-ui, -apple-system, Segoe UI, Roboto, 'Inter', sans-serif;
}
.wc-dot {
  width:10px; height:10px; border-radius:50%;
  background: #16a34a;
  box-shadow: 0 0 0 2px rgba(22,163,74,0.2);
}
.wc-dot.off { background:#ef4444; box-shadow: 0 0 0 2px rgba(239,68,68,0.15); }
.wc-close {
  border: 0; background: transparent; color: var(--wc-muted);
  font-size: 16px; cursor: pointer;
}
.wc-messages {
  padding: 12px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 8px;
}
.wc-msg {
  max-width: 80%;
  padding: 10px 12px;
  border-radius: 12px;
  white-space: pre-wrap; word-wrap: break-word;
  position: relative;
}
.wc-msg.me {
  align-self: flex-end;
  background: #1f2937;
  border-top-right-radius: 4px;
}
.wc-msg.them {
  align-self: flex-start;
  background: #111827;
  border-top-left-radius: 4px;
}
.wc-time {
  display:flex; justify-content:flex-end; align-items:center;
  margin-top:4px;
  gap: 6px;
  font-size: 11px; color: var(--wc-muted);
}
.wc-status {
  font-size: 11px;
  color: var(--wc-muted);
}
.wc-status.error { color: var(--wc-danger); }
.wc-input {
  padding: 10px; background: var(--wc-bg);
  border-top: 1px solid rgba(255,255,255,0.06);
  display: grid; grid-template-columns: 1fr auto; gap: 8px;
}
.wc-textarea {
  height: 42px; resize: none;
  background: #0b1020;
  color: var(--wc-text);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 10px;
  font: 14px/1.2 system-ui, -apple-system, Segoe UI, Roboto, 'Inter', sans-serif;
  outline: none;
}
.wc-send {
  height: 42px;
  padding: 0 14px;
  border-radius: 10px;
  border: 0;
  background: var(--wc-accent);
  color: white;
  font-weight: 600;
  cursor: pointer;
}
.wc-send[disabled] { opacity: 0.6; cursor: not-allowed; }
.wc-empty {
  opacity: 0.66; text-align: center; padding: 12px; font-size: 13px;
}
.wc-loader {
  width: 30px; height: 12px; display: grid; grid-auto-flow: column; gap: 4px;
  align-items: end;
}
.wc-loader span { width:6px; height:6px; border-radius:50%; background: var(--wc-muted); animation: wc-b 1s infinite ease-in-out; }
.wc-loader span:nth-child(2){ animation-delay: .1s }
.wc-loader span:nth-child(3){ animation-delay: .2s }
@keyframes wc-b { 0%, 80%, 100% { transform: translateY(0) } 40% { transform: translateY(-5px) } }
`;
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  };

  const buildUI = () => {
    fabBtn = el('button', 'wc-fab', '💬');

    panel = el('div', 'wc-panel');
    const header = el('div', 'wc-header');
    const brand = el('div', 'wc-brand');
    dot = el('span', 'wc-dot');
    const title = el('span', null, 'Чат с поддержкой');
    brand.append(dot, title);

    const closeBtn = el('button', 'wc-close', '✕');
    closeBtn.addEventListener('click', () => {
      panel.style.display = 'none';
    });
    header.append(brand, closeBtn);

    msgBox = el('div', 'wc-messages');
    emptyHint = el('div', 'wc-empty', 'Напишите нам — мы ответим здесь.');
    msgBox.appendChild(emptyHint);

    inputArea = el('div', 'wc-input');
    ta = el('textarea', 'wc-textarea');
    ta.setAttribute('rows', '1');
    ta.setAttribute('placeholder', 'Ваше сообщение…');
    sendBtn = el('button', 'wc-send', 'Отправить');
    sendBtn.disabled = false;
    inputArea.append(ta, sendBtn);

    panel.append(header, msgBox, inputArea);

    fabBtn.addEventListener('click', () => {
      panel.style.display = panel.style.display === 'grid' ? 'none' : 'grid';
      if (panel.style.display === 'grid') {
        ta.focus();
        scrollToBottom();
      }
    });

    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        void doSend();
      }
    });
    sendBtn.addEventListener('click', () => void doSend());

    document.body.append(fabBtn, panel);
  };

  const setOnline = (isOnline) => {
    state.isOnline = isOnline;
    if (!dot) return;
    dot.classList.toggle('off', !isOnline);
  };

  const scrollToBottom = () => {
    if (!msgBox) return;
    msgBox.scrollTop = msgBox.scrollHeight + 999;
  };

  const messageDomId = (id) => `wc-msg-${id}`;

  const renderAllMessages = () => {
    if (!msgBox) return;
    msgBox.innerHTML = '';
    if (!state.messages.length) {
      msgBox.appendChild(emptyHint);
      return;
    }
    for (const message of state.messages) {
      msgBox.appendChild(renderMessageBubble(message));
    }
    scrollToBottom();
  };

  const renderMessageBubble = (message) => {
    const bubble = el('div', `wc-msg ${message.direction === 'in' ? 'them' : 'me'}`);
    bubble.dataset.id = message.id;
    bubble.id = messageDomId(message.id);
    const content = el('div');
    content.textContent = message.text;
    const metaWrap = el('div', 'wc-time');
    const timeLabel = el('span', null, fmtTime(message.createdAt || nowISO()));
    metaWrap.appendChild(timeLabel);
    if (message.direction === 'out') {
      const status = el('span', 'wc-status');
      updateStatusLabel(status, message);
      metaWrap.appendChild(status);
    }
    bubble.append(content, metaWrap);
    return bubble;
  };

  const updateStatusLabel = (node, message) => {
    if (!node) return;
    node.classList.remove('error');
    if (message.status === 'error') {
      node.textContent = 'ошибка';
      node.classList.add('error');
    } else if (message.status === 'sending') {
      node.textContent = 'отправка…';
    } else if (message.status === 'delivered') {
      node.textContent = 'доставлено';
    } else if (message.status === 'read') {
      node.textContent = 'прочитано';
    } else {
      node.textContent = '';
    }
  };

  const upsertMessage = (incoming) => {
    const idx = state.messages.findIndex((m) => m.id === incoming.id);
    if (idx >= 0) {
      state.messages[idx] = { ...state.messages[idx], ...incoming };
    } else {
      state.messages.push(incoming);
      state.messages.sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
    }

    const bubble = by(`#${messageDomId(incoming.id)}`);
    if (bubble) {
      bubble.className = `wc-msg ${incoming.direction === 'in' ? 'them' : 'me'}`;
      bubble.querySelector('div')?.replaceWith(el('div', null, incoming.text));
      const metaWrap = bubble.querySelector('.wc-time');
      if (metaWrap) {
        metaWrap.innerHTML = '';
        metaWrap.appendChild(el('span', null, fmtTime(incoming.createdAt || nowISO())));
        if (incoming.direction === 'out') {
          const status = el('span', 'wc-status');
          updateStatusLabel(status, incoming);
          metaWrap.appendChild(status);
        }
      }
    } else if (msgBox) {
      msgBox.appendChild(renderMessageBubble(incoming));
    }
    scrollToBottom();
  };

  const attachSocket = async () => {
    if (!state.threadId || !state.token) return;
    await ensureSocketLib();
    if (state.socket) {
      state.socket.disconnect();
    }
    state.socket = window.io(socketBase, {
      path: state.socketPath,
      transports: ['websocket', 'polling'],
      withCredentials: true,
      auth: { token: state.token },
    });

    state.socket.on('connect', () => {
      setOnline(true);
      state.socket.emit('join', { room: `thread-${state.threadId}` });
    });
    state.socket.on('disconnect', () => setOnline(false));
    state.socket.on('connect_error', () => setOnline(false));

    state.socket.on('message:new', (payload) => {
      if (!payload || !payload.message) return;
      const msg = payload.message;
      const direction = normalizeDirection(msg.direction);

      // Не дублируем сообщения, которые были отправлены самим webChat-клиентом:
      // они уже добавлены в state.messages и IndexedDB через doSend().
      if (direction === 'out') {
        return;
      }

      const stored = {
        id: msg.id,
        threadId: state.threadId,
        direction,
        text: msg.text || '',
        payload: msg.payload || null,
        createdAt: msg.createdAt || nowISO(),
        deliveredAt: msg.deliveredAt || null,
        readAt: msg.readAt || null,
        tmpId: null,
        status: direction === 'out' && !msg.readAt ? 'delivered' : 'read',
      };
      saveMessages([stored]);
      upsertMessage(stored);

      if (stored.direction === 'out') {
        sendAck({ delivered: [stored.id] });
        if (panel?.style.display === 'grid' && !document.hidden) {
          stored.status = 'read';
          stored.readAt = nowISO();
          sendAck({ read: [stored.id] });
          saveMessages([stored]);
          const bubble = by(`#${messageDomId(stored.id)}`);
          updateStatusLabel(bubble?.querySelector('.wc-status'), stored);
        }
      }
    });

    state.socket.on('message:status', (payload) => {
      if (!payload || !Array.isArray(payload.messages)) return;
      const status = payload.status === 'read' ? 'read' : 'delivered';
      for (const id of payload.messages) {
        const existing = state.messages.find((m) => m.id === id);
        if (!existing) continue;
        existing.status = status;
        if (status === 'delivered') {
          existing.deliveredAt = payload.timestamp || nowISO();
        } else {
          existing.readAt = payload.timestamp || nowISO();
        }
        const bubble = by(`#${messageDomId(id)}`);
        updateStatusLabel(bubble?.querySelector('.wc-status'), existing);
      }
    });
  };

  const ensureSocketLib = () => new Promise((resolve, reject) => {
    if (window.io && typeof window.io === 'function') {
      resolve();
      return;
    }
    const s = document.createElement('script');
    s.src = 'https://cdn.socket.io/4.7.5/socket.io.min.js';
    s.defer = true;
    s.onload = () => resolve();
    s.onerror = (e) => reject(e);
    document.head.appendChild(s);
  });

  const doSend = async () => {
    if (!ta || !sendBtn) return;
    const txt = ta.value.trim();
    if (!txt) return;
    if (!state.threadId) {
      await handshake();
    }

    const tmpId = generateId();
    const message = {
      id: `tmp-${tmpId}`,
      threadId: state.threadId,
      direction: 'out',
      text: txt,
      payload: null,
      createdAt: nowISO(),
      deliveredAt: null,
      readAt: null,
      tmpId,
      status: 'sending',
    };
    state.messages.push(message);
    await saveMessages([message]);
    renderAllMessages();

    ta.value = '';
    sendBtn.disabled = true;

    if (!navigator.onLine) {
      await enqueueOutbox({ tmpId, text: txt, createdAt: message.createdAt });
      message.status = 'error';
      await saveMessages([{ ...message }]);
      renderAllMessages();
      sendBtn.disabled = false;
      return;
    }

    const prevThreadId = state.threadId;

    try {
      await ensureTokenFresh();
      const response = await postMessage(txt, tmpId);
      if (response && response.message_id) {
        const threadChanged = !!response.thread_id && response.thread_id !== prevThreadId;
        const shouldAttachSocket = threadChanged || (!!response.token && (!state.socket || !state.socket.connected));

        const persisted = {
          id: response.message_id,
          threadId: state.threadId,
          direction: 'out',
          text: txt,
          payload: null,
          createdAt: response.created_at || message.createdAt,
          deliveredAt: response.created_at || message.createdAt,
          readAt: null,
          tmpId: null,
          status: 'delivered',
        };
        state.messages = state.messages.filter((m) => m.id !== message.id);
        state.messages.push(persisted);
        state.messages.sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
        await saveMessages([persisted]);
        renderAllMessages();
        await removeOutbox(tmpId);
        await putSyncState(state.threadId, { lastSyncedAt: persisted.createdAt });

        if (shouldAttachSocket && state.threadId && state.token) {
          await attachSocket();
        }
      }
    } catch (err) {
      console.warn('[WebChat] send error:', err);
      message.status = 'error';
      await saveMessages([{ ...message }]);
      await enqueueOutbox({ tmpId, text: txt, createdAt: message.createdAt });
      renderAllMessages();
    } finally {
      sendBtn.disabled = false;
      ta.focus();
    }
  };

  const flushOutbox = async () => {
    if (state.outboxProcessing || !state.threadId) return;
    state.outboxProcessing = true;
    try {
      const pending = await listOutbox();
      for (const item of pending) {
        try {
          await ensureTokenFresh();
          const resp = await postMessage(item.text, item.tmpId);
          if (resp?.message_id) {
            await removeOutbox(item.tmpId);
            const stored = state.messages.find((m) => m.tmpId === item.tmpId || m.id === `tmp-${item.tmpId}`);
            if (stored) {
              stored.id = resp.message_id;
              stored.tmpId = null;
              stored.status = 'delivered';
              stored.createdAt = resp.created_at || stored.createdAt;
              stored.deliveredAt = stored.createdAt;
              await saveMessages([stored]);
              renderAllMessages();
            }
          }
        } catch (err) {
          console.warn('[WebChat] failed to flush outbox item', item.tmpId, err);
        }
      }
    } finally {
      state.outboxProcessing = false;
    }
  };

  const hydrate = async () => {
    let visitor = await loadStoredVisitor();
    if (!visitor) {
      visitor = generateId();
      await setVisitorId(visitor);
    } else {
      state.visitorId = visitor;
    }

    state.sessionId = cookie.get('wc_sid') || generateId();

    state.threadId = await loadStoredThread();

    if (state.threadId) {
      state.messages = await listMessages(state.threadId, 200);
      renderAllMessages();
      const syncState = await getSyncState(state.threadId);
      if (syncState?.lastSyncedAt) {
        await putSyncState(state.threadId, syncState);
      }
    }

    try {
      const data = await handshake();
      if (state.threadId && state.threadId !== data.thread_id) {
        await deleteMessagesByThread(state.threadId);
        await deleteSyncState(state.threadId);
        state.threadId = data.thread_id;
        state.messages = [];
        renderAllMessages();
      }
      state.threadId = data.thread_id;
      await saveThread(state.threadId);
      state.messages = await listMessages(state.threadId, 200);
      renderAllMessages();
      await syncWithServer();
      await attachSocket();
      await flushOutbox();
    } catch (err) {
      console.warn('[WebChat] handshake error', err);
      setOnline(false);
    }

    state.hydrateComplete = true;
  };

  window.addEventListener('online', () => {
    setOnline(true);
    flushOutbox();
    syncWithServer();
  });
  window.addEventListener('offline', () => setOnline(false));

  injectStyles();
  buildUI();
  hydrate();
})();
