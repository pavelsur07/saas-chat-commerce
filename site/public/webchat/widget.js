(()=>{
  "use strict";

  // ---------- Helpers ----------
  const by = (sel, root = document) => root.querySelector(sel);
  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };
  const nowISO = () => new Date().toISOString();
  const fmtTime = (d) =>
    new Date(d).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });

  const getScriptEl = () => {
    // robust: find the <script> that loaded this file (async-safe)
    // Prefer currentScript; fallback to last <script src*="/webchat/widget.js">
    const cs = document.currentScript;
    if (cs && cs.dataset) return cs;
    const scripts = Array.from(document.scripts);
    for (let i = scripts.length - 1; i >= 0; i--) {
      const s = scripts[i];
      const src = s.getAttribute("src") || "";
      if (src.includes("/webchat/widget.js")) return s;
    }
    // As a last resort, return an empty element-like object.
    return { dataset: {} };
  };

  // ---------- State ----------
  const scriptTag = getScriptEl();
  const SITE_KEY = scriptTag.dataset.siteKey || scriptTag.getAttribute("data-site-key") || "";
  if (!SITE_KEY) {
    console.warn("[WebChat] data-site-key is missing on <script> tag.");
  }

  let sessionId = null;       // from /api/embed/init
  let clientId = null;        // from /api/embed/message (first send)
  let room = null;            // "client-{id}"
  let socket = null;          // window.io(...) client
  let socketPath = "/socket.io";
  let connected = false;
  let firstMessageSent = false;

  // queue outbound messages until socket joins the room (after first send)
  const outboxQueue = [];

  // ---------- DOM: inject styles ----------
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
  font: 600 20px/1 system-ui, -apple-system, Segoe UI, Roboto, "Inter", sans-serif;
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
  font: 600 14px/1.2 system-ui, -apple-system, Segoe UI, Roboto, "Inter", sans-serif;
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
  display:block;
  margin-top:4px;
  font-size: 11px; color: var(--wc-muted);
  text-align: right;
}
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
  font: 14px/1.2 system-ui, -apple-system, Segoe UI, Roboto, "Inter", sans-serif;
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
    const style = document.createElement("style");
    style.textContent = css;
    document.head.appendChild(style);
  };

  // ---------- DOM: build UI ----------
  let fabBtn, panel, dot, msgBox, inputArea, ta, sendBtn, emptyHint;

  const buildUI = () => {
    fabBtn = el("button", "wc-fab", "💬");

    panel = el("div", "wc-panel");
    const header = el("div", "wc-header");
    const brand = el("div", "wc-brand");
    dot = el("span", "wc-dot");
    const title = el("span", null, "Чат с поддержкой");
    brand.append(dot, title);

    const closeBtn = el("button", "wc-close", "✕");
    closeBtn.addEventListener("click", () => {
      panel.style.display = "none";
    });
    header.append(brand, closeBtn);

    msgBox = el("div", "wc-messages");
    emptyHint = el("div", "wc-empty", "Напишите нам — мы ответим здесь.");
    msgBox.appendChild(emptyHint);

    inputArea = el("div", "wc-input");
    ta = el("textarea", "wc-textarea");
    ta.setAttribute("rows", "1");
    ta.setAttribute("placeholder", "Ваше сообщение…");
    sendBtn = el("button", "wc-send", "Отправить");
    sendBtn.disabled = false;
    inputArea.append(ta, sendBtn);

    panel.append(header, msgBox, inputArea);

    fabBtn.addEventListener("click", () => {
      panel.style.display = panel.style.display === "grid" ? "none" : "grid";
      if (panel.style.display === "grid") {
        ta.focus();
        scrollToBottom();
      }
    });

    ta.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        doSend();
      }
    });
    sendBtn.addEventListener("click", doSend);

    document.body.append(fabBtn, panel);
  };

  const setOnline = (isOnline) => {
    if (!dot) return;
    dot.classList.toggle("off", !isOnline);
  };

  const scrollToBottom = () => {
    if (!msgBox) return;
    msgBox.scrollTop = msgBox.scrollHeight + 999;
  };

  const renderMessage = (text, direction, tsISO) => {
    if (emptyHint && emptyHint.parentNode) emptyHint.remove();
    const bubble = el("div", `wc-msg ${direction === "me" ? "me" : "them"}`);
    const content = el("div");
    content.textContent = text; // XSS-safe
    const time = el("span", "wc-time", fmtTime(tsISO || nowISO()));
    bubble.append(content, time);
    msgBox.appendChild(bubble);
    scrollToBottom();
  };

  const renderTyping = () => {
    const wrap = el("div", "wc-msg them");
    const loader = el("div", "wc-loader");
    loader.append(el("span"), el("span"), el("span"));
    wrap.append(loader);
    msgBox.appendChild(wrap);
    scrollToBottom();
    // remove after a short delay unless replaced by a real message
    setTimeout(() => wrap.remove(), 1500);
  };

  // ---------- API ----------
  const API = {
    init: async () => {
      try {
        const res = await fetch("/api/embed/init", {
          method: "POST",
          credentials: "include",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            site_key: SITE_KEY,
            page_url: location.href,
          }),
        });
        if (!res.ok) throw new Error("init failed " + res.status);
        const data = await res.json();
        sessionId = data.session_id || null;
        // socket_path may be provided; store as default before /message
        if (data.socket_path) socketPath = data.socket_path;
        setOnline(true);
      } catch (e) {
        console.warn("[WebChat] init error:", e);
        setOnline(false);
      }
    },

    sendMessage: async (text) => {
      if (!text || !text.trim()) return;
      const payload = {
        site_key: SITE_KEY,
        session_id: sessionId,
        text: text.trim(),
        page_url: location.href,
        referrer: document.referrer || "",
      };
      const res = await fetch("/api/embed/message", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error("message failed " + res.status);
      return res.json(); // expects { clientId, room, socket_path }
    },
  };

  // ---------- Socket ----------
  const ensureSocketLib = () =>
    new Promise((resolve, reject) => {
      if (window.io && typeof window.io === "function") return resolve();
      const s = document.createElement("script");
      s.src = "https://cdn.socket.io/4.7.5/socket.io.min.js";
      s.defer = true;
      s.onload = () => resolve();
      s.onerror = (e) => reject(e);
      document.head.appendChild(s);
    });

  const connectSocket = async (path, joinRoom) => {
    await ensureSocketLib();
    try {
      socket = window.io("", {
        path: path || "/socket.io",
        transports: ["websocket", "polling"],
        withCredentials: true,
      });
      socket.on("connect", () => {
        connected = true;
        setOnline(true);
        // Ask server to join the specific client room
        socket.emit("join", { room: joinRoom });
        // Flush any queued outbound echoes (we render immediately anyway)
        while (outboxQueue.length) outboxQueue.shift();
      });
      socket.on("disconnect", () => {
        connected = false;
        setOnline(false);
      });
      socket.on("connect_error", () => {
        connected = false;
        setOnline(false);
      });
      socket.on("new_message", (payload) => {
        // Expected: { clientId, text, direction: 'out', createdAt }
        try {
          const { text, direction, createdAt } = payload || {};
          if (!text) return;
          // Direction 'out' => message from operator; render as 'them'
          const who = direction === "out" ? "them" : "me";
          renderMessage(String(text), who, createdAt || nowISO());
        } catch (e) {
          console.warn("[WebChat] invalid payload:", payload);
        }
      });
    } catch (e) {
      console.warn("[WebChat] socket error:", e);
      setOnline(false);
    }
  };

  // ---------- Actions ----------
  const doSend = async () => {
    if (!ta || !sendBtn) return;
    const txt = ta.value;
    if (!txt.trim()) return;

    // UI optimistic render
    renderMessage(txt.trim(), "me", nowISO());
    renderTyping();
    ta.value = "";
    sendBtn.disabled = true;

    try {
      // First message boots the session & returns socket info
      const resp = await API.sendMessage(txt);
      if (!firstMessageSent) {
        firstMessageSent = true;
        clientId = resp.clientId || resp.client_id || null;
        room = resp.room;
        socketPath = resp.socket_path || socketPath;
        // Connect socket and join the room
        await connectSocket(socketPath, room);
      }
    } catch (e) {
      console.warn("[WebChat] send error:", e);
      // show a small error bubble
      renderMessage("Не удалось отправить. Повторите попытку.", "them", nowISO());
    } finally {
      sendBtn.disabled = false;
      ta.focus();
    }
  };

  // ---------- Boot ----------
  const boot = async () => {
    injectStyles();
    buildUI();
    await API.init();
  };

  // DOM ready-ish: run immediately or on DOMContentLoaded
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
