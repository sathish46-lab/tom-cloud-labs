/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


/**
 * ============================================================================
 * TOM LABS — GLOBAL CHAT WIDGET
 * ============================================================================
 * Floating circle button (bottom-right) + panel. One global room for all
 * logged-in users, powered by RabbitMQ Web-STOMP fan-out.
 *
 * Real-time:  TomSocketClient -> /topic/global-chat-messages (subscribe only)
 * Send:       POST /api/chat/send.php  (auth + CSRF + rate limit + sanitize)
 * Delete:     POST /api/chat/delete.php (self-delete, admins delete any)
 * Typing:     POST /api/chat/typing.php (throttled) -> "(name) is typing..."
 * Presence:   POST /api/chat/presence.php heartbeat -> live online count
 * History:    GET  /api/chat/history.php (last 50, gap-fill on reconnect)
 *
 * Security notes:
 *  - Browsers never publish to RabbitMQ; only the PHP endpoints do.
 *  - All message text is rendered with textContent — XSS-inert by design.
 *  - Guests see a sign-in prompt and never open a socket.
 *
 * Dependencies: stomp.js, mq.js (TomSocketClient), app.css (chat styles)
 * Used by: every page (markup self-injected, wired on DOMContentLoaded)
 *
 * @version 1.0.0
 * @author Sathish46 - Tom Labs
 * ============================================================================
 */

const CHAT = {
  el: null,
  panel: null,
  listEl: null,
  inputEl: null,
  sendBtn: null,
  badgeEl: null,
  fabEl: null,       // pulse orb when unread
  countEl: null,
  typingEl: null,

  socket: null,
  connected: false,

  me: null,          // {user_id, username, role} from history response (logged-in only)
  isAdmin: false,

  guestId: null,     // UUID for guest tracking (stored in localStorage)

  open: false,
  unread: 0,

  historyLoaded: false,
  loadSeq: 0,        // guards history fetches against race conditions

  typingIdleTimer: null,   // 3s debounce before auto-stop
  typingClearTimer: null,  // cleanup timeout for expired typing entries
  typingUsers: {},         // userId -> { username, expiresAt }
  lastTypingSent: 0,       // ms timestamp of last typing beacon sent
  isCurrentlyTyping: false,

  presenceTimer: null,     // 30s heartbeat
  lastCount: null,

  deleting: {},      // id -> true, prevents double-delete clicks
};

/* ==========================================================================
 * Guest identity — stable UUID per browser, stored in localStorage.
 * Used to track unread count while not logged in, then merged on login.
 * =========================================================================
 */

function guestId() {
  if (CHAT.guestId) return CHAT.guestId;
  let g = localStorage.getItem("tom-chat-guest-id");
  if (!g) {
    g = "guest_" + crypto.randomUUID?.() || ("g_" + Math.random().toString(36).slice(2));
    localStorage.setItem("tom-chat-guest-id", g);
  }
  CHAT.guestId = g;
  return g;
}

/* ==========================================================================
 * DOM construction (no innerHTML with user data — XSS-safe by design)
 * =========================================================================
 */

function chatEscapeAttr(s) {
  return String(s ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;")
    .replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function el(tag, cls, text) {
  const node = document.createElement(tag);
  if (cls) node.className = cls;
  if (text !== undefined && text !== null) node.textContent = text;
  return node;
}

function avatarNode(avatarUrl, name, cls) {
  const wrap = el("div", cls);
  if (avatarUrl) {
    const img = el("img", "chat-avatar-img");
    img.alt = "";
    img.loading = "lazy";
    img.referrerPolicy = "no-referrer";
    img.addEventListener("error", () => {
      img.remove();
      wrap.classList.add("chat-avatar-fallback");
      wrap.appendChild(el("span", null, (name || "?").charAt(0).toUpperCase()));
    });
    img.src = avatarUrl;
    wrap.appendChild(img);
  } else {
    wrap.classList.add("chat-avatar-fallback");
    wrap.appendChild(el("span", null, (name || "?").charAt(0).toUpperCase()));
  }
  return wrap;
}

// Stable per-sender hue (0-359) so each person gets a consistent avatar/name colour.
// Numeric ids use the golden angle so neighbouring ids land far apart on the
// colour wheel; anything else goes through an FNV-1a hash with an avalanche step.
function chatHue(key) {
  const s = String(key ?? "");
  if (/^\d+$/.test(s)) return Math.round((Number(s) * 137.508) % 360);
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) {
    h ^= s.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  h ^= h >>> 15;
  h = Math.imul(h, 2246822519);
  h ^= h >>> 13;
  return (h >>> 0) % 360;
}

function chatDayKey(ts) {
  const d = new Date(ts);
  return isNaN(d) ? "" : `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

function chatDayLabel(ts) {
  const d = new Date(ts);
  if (isNaN(d)) return "";
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return "Today";
  if (d.toDateString() === yesterday.toDateString()) return "Yesterday";
  const opts = { month: "short", day: "numeric" };
  if (d.getFullYear() !== today.getFullYear()) opts.year = "numeric";
  return d.toLocaleDateString([], opts);
}

function buildWidget() {
  const root = document.getElementById("chat-widget");
  if (!root) return null;

  const panel = document.getElementById("chat-panel");
  const listEl = document.getElementById("chat-messages");
  const input = document.getElementById("chat-input");
  const send = document.getElementById("chat-send");
  const badge = document.getElementById("chat-fab-badge");
  const fab = document.getElementById("chat-fab");
  const countEl = document.getElementById("chat-online-count");
  const typingEl = document.getElementById("chat-typing");
  const scrollBtn = document.getElementById("chat-scroll-bottom");
  const minBtn = panel?.querySelector(".chat-icon-btn[aria-label='Minimize chat']");
  const closeBtn = panel?.querySelector(".chat-icon-btn[aria-label='Close chat']");
  const stormGlow = panel?.querySelector(".chat-storm-glow");
  const stormFlash = panel?.querySelector(".chat-storm-flash");
  const stormBolts = panel?.querySelector(".chat-storm-bolts");
  const fabTyping = fab?.querySelector(".chat-fab-typing");

  return {
    root, panel, listEl, input, send, fab,
    badge: badge, countEl, typingEl, fabTyping,
    minBtn, closeBtn, scrollBtn,
    stormGlow, stormFlash, stormBolts,
  };
}

/* ==========================================================================
 * Rendering
 * =========================================================================
 */

function roleBadge(role) {
  if (role !== "admin") return null;
  const b = el("span", "chat-role-badge", "Admin");
  return b;
}

function renderMessage(msg) {
  const mine = CHAT.me && msg.user_id === CHAT.me.user_id;
  const row = el("div", "chat-msg-row" + (mine ? " mine" : " other"));
  row.dataset.id = msg.id;
  row.dataset.uid = String(msg.user_id ?? "");
  row._ts = msg.ts; // raw value kept as-is (ISO string or epoch) for grouping
  row.style.setProperty("--chat-hue", String(chatHue(msg.user_id ?? msg.username)));

  // Incoming: avatar (real image if the payload has avatar_url, else initial).
  if (!mine) {
    row.appendChild(avatarNode(msg.avatar, msg.username, "chat-avatar"));
  }

  const bubble = el("div", "chat-bubble");

  if (!mine) {
    // Incoming: sender name (+ admin badge) above the text.
    const head = el("div", "chat-msg-head");
    const nameWrap = el("span", "chat-msg-name", msg.username || "unknown");
    // Tag the admin for typing-name resolution ("SIVA is typing...").
    nameWrap.dataset.uid = String(msg.user_id ?? "");
    head.appendChild(nameWrap);
    const badge = roleBadge(msg.role);
    if (badge) head.appendChild(badge);
    bubble.appendChild(head);
  }

  // Text + time share one row for both incoming and outgoing bubbles.
  const meta = el("div", "chat-msg-meta");
  meta.appendChild(el("div", "chat-msg-text", msg.text || ""));
  meta.appendChild(el("span", "chat-msg-time", chatFormatTs(msg.ts)));
  bubble.appendChild(meta);

  // Delete control: own messages for everyone; any message for admins.
  const canDelete = CHAT.me && (mine || CHAT.isAdmin);
  if (canDelete) {
    const del = el("button", "chat-msg-del", "×");
    del.type = "button";
    del.setAttribute("aria-label", "Delete message");
    del.addEventListener("click", () => deleteMessage(msg.id, row));
    bubble.appendChild(del);
  }

  row.appendChild(bubble);
  return row;
}

// Time of day only — the date comes from the day divider above the message.
function chatFormatTs(ts) {
  if (!ts) return "";
  const d = new Date(ts);
  if (isNaN(d)) return "";
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

// Rebuilds day dividers and group classes (.is-first / .is-cont) for the whole
// transcript. A group = consecutive messages from one sender within 5 minutes.
// Cheap (≤ a few hundred rows) and keeps grouping correct after deletes.
function regroup() {
  if (!CHAT.listEl) return;
  CHAT.listEl.querySelectorAll(".chat-day").forEach((n) => n.remove());

  let prev = null;
  let prevDay = "";
  CHAT.listEl.querySelectorAll(".chat-msg-row:not(.chat-msg-removed)").forEach((row) => {
    const day = chatDayKey(row._ts);
    if (day && day !== prevDay) {
      const sep = el("div", "chat-day");
      sep.appendChild(el("span", null, chatDayLabel(row._ts)));
      CHAT.listEl.insertBefore(sep, row);
      prev = null; // a new day always starts a new group
      prevDay = day;
    }
    const cont = !!prev
      && prev.dataset.uid === row.dataset.uid
      && Math.abs(new Date(row._ts) - new Date(prev._ts)) < 5 * 60 * 1000;
    row.classList.toggle("is-cont", cont);
    row.classList.toggle("is-first", !cont);
    prev = row;
  });
}

function appendMessage(msg, { scroll = true } = {}) {
  if (!CHAT.listEl) return;
  // Dedupe (socket + gap-fill history can overlap).
  if (CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(msg.id)}"]`)) return;
  const node = renderMessage(msg);
  CHAT.listEl.appendChild(node);
  regroup();
  if (scroll) scrollList(true);
}

function renderHistory(messages) {
  if (!CHAT.listEl) return;
  CHAT.listEl.textContent = "";
  for (const msg of messages) {
    CHAT.listEl.appendChild(renderMessage(msg));
  }
  regroup();
  scrollList(false);
}

function scrollList(smooth) {
  if (!CHAT.listEl) return;
  CHAT.listEl.scrollTo({
    top: CHAT.listEl.scrollHeight,
    behavior: smooth ? "smooth" : "auto",
  });
}

function showEmptyState() {
  if (!CHAT.listEl || CHAT.listEl.children.length) return;
  const empty = el("div", "chat-empty");
  empty.appendChild(el("i", "bx bx-conversation"));
  empty.appendChild(el("p", null, "No messages yet"));
  empty.appendChild(el("span", null, "Say hi to start the conversation."));
  CHAT.listEl.appendChild(empty);
}

function hideEmptyState() {
  const empty = CHAT.listEl && CHAT.listEl.querySelector(".chat-empty");
  if (empty) empty.remove();
}

/* ==========================================================================
 * Socket (fan-out subscribe only — sending goes through PHP)
 * =========================================================================
 */

function handleEvent(evt) {
  if (!evt || typeof evt !== "object") return;

  if (evt.type === "message") {
    hideEmptyState();
    appendMessage(evt, { scroll: CHAT.open });
    if (!CHAT.open) bumpUnread();
    // A real message clears the sender's typing indicator everywhere.
    if (CHAT.typingUsers[evt.user_id]) {
      delete CHAT.typingUsers[evt.user_id];
      renderTypingStrip();
    }
    return;
  }

  if (evt.type === "delete") {
    removeMessageNode(evt.id);
    return;
  }

  if (evt.type === "typing") {
    showTyping(evt);
    return;
  }

  if (evt.type === "typing_stop") {
    delete CHAT.typingUsers[evt.user_id];
    renderTypingStrip();
    return;
  }

  if (evt.type === "typing_stop") {
    if (!CHAT.typingEl || !CHAT.me) return;
    if (evt.user_id === CHAT.me.user_id) return;
    delete CHAT.typingUsers[evt.user_id];
    console.log("[DBG] typing_stop", evt.user_id);
    renderTypingStrip();
    return;
  }

  if (evt.type === "presence") {
    setOnlineCount(typeof evt.count === "number" ? evt.count : null);
    return;
  }
}

function removeMessageNode(id) {
  if (!CHAT.listEl || !id) return;
  const node = CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(id)}"]`);
  if (node) {
    node.classList.add("chat-msg-removed");
    regroup(); // neighbours re-group immediately while the node fades out
    setTimeout(() => node.remove(), 250);
  }
}

function connectSocket() {
  if (typeof TomSocketClient === "undefined") {
    console.warn("[Chat] TomSocketClient unavailable");
    return;
  }
  if (CHAT.socket && CHAT.connected) return;
  if (!CHAT.socket) CHAT.socket = new TomSocketClient();

  try {
    CHAT.socket.connect(
      "/topic/global-chat-messages",
      (evt) => handleEvent(evt),
      null,
      () => {
        // Subscribed (first time AND after every auto-reconnect):
        // refresh history so nothing sent while disconnected is missed,
        // then start (or restart) the presence heartbeat.
        CHAT.connected = true;
        loadHistory(true).then(startPresence);
      }
    );
  } catch (e) {
    console.error("[Chat] Socket connect failed:", e);
  }
}

function disconnectSocket() {
  stopPresence();
  if (CHAT.socket) {
    try { CHAT.socket.disconnect(); } catch (e) {}
  }
  CHAT.connected = false;
}

/* ==========================================================================
 * History
 * =========================================================================
 */

async function loadHistory(refresh = false) {
  if (!window.CHAT_CONTEXT?.loggedIn && !guestId()) return;
  const seq = ++CHAT.loadSeq;
  try {
    const headers = { "Accept": "application/json" };
    if (!window.CHAT_CONTEXT?.loggedIn) {
      headers["X-Guest-Id"] = guestId();
    }
    const res = await fetch("/api/chat/history.php", {
      method: "GET",
      credentials: "same-origin",
      headers,
    });
    if (seq !== CHAT.loadSeq) return;
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || "History failed");

    CHAT.me = data.me || null;
    CHAT.isAdmin = CHAT.me?.role === "admin";

    // Apply unread count from server (works for both logged-in and guest).
    CHAT.unread = data.unread_count ?? 0;
    if (CHAT.badgeEl) {
      CHAT.badgeEl.textContent = CHAT.unread > 99 ? "99+" : String(CHAT.unread);
      CHAT.badgeEl.hidden = CHAT.unread === 0;
    }
    if (CHAT.fabEl) {
      if (CHAT.unread > 0) CHAT.fabEl.classList.add("chat-fab--has-unread");
      else CHAT.fabEl.classList.remove("chat-fab--has-unread");
    }

    if (refresh) {
      // Gap-fill on reconnect: replace list, keep scroll at bottom.
      const wasAtBottom = isScrolledToBottom();
      renderHistory(data.messages || []);
      if (!wasAtBottom) scrollList(false);
    } else {
      renderHistory(data.messages || []);
    }

    setMembersCount(data.members || 0);
    hideEmptyState();
    showEmptyState();
    CHAT.historyLoaded = true;
  } catch (e) {
    console.warn("[Chat] history failed:", e);
    if (!refresh) showEmptyState();
  }
}

function isScrolledToBottom() {
  if (!CHAT.listEl) return true;
  return CHAT.listEl.scrollHeight - CHAT.listEl.scrollTop - CHAT.listEl.clientHeight < 40;
}

/* ==========================================================================
 * Send / delete
 * =========================================================================
 */

function csrfHeaders(extra = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const headers = {
    "Content-Type": "application/json",
    "X-CSRF-Token": token,
  };
  if (!window.CHAT_CONTEXT?.loggedIn) {
    headers["X-Guest-Id"] = guestId();
  }
  return Object.assign(headers, extra);
}

async function sendMessage() {
  if (!CHAT.inputEl || !CHAT.sendBtn) return;
  const text = CHAT.inputEl.value.trim();
  if (!text) return;

  const btn = CHAT.sendBtn;
  btn.disabled = true;
  try {
    const res = await fetch("/api/chat/send.php", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ text }),
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 429) {
      chatToast("Slow down — too many messages.");
      return;
    }
    if (!data.success) {
      chatToast(data.error || "Could not send message.");
      return;
    }
    CHAT.inputEl.value = "";
    // If the fan-out publish failed, the sender renders its own message
    // from the persisted payload (others see it on next history load).
    if (data.published === false && data.message) {
      hideEmptyState();
      appendMessage(data.message);
    }
    // Stop typing indicator for self — the fan-out message event clears it
    // on every client, including the sender.
    clearTimeout(CHAT.typingIdleTimer);
    CHAT.typingIdleTimer = null;
    CHAT.isCurrentlyTyping = false;
    CHAT.lastTypingSent = 0;
    delete CHAT.typingUsers[CHAT.me?.user_id];
    renderTypingStrip();
  } catch (e) {
    console.warn("[Chat] send failed:", e);
    chatToast("Network error — try again.");
  } finally {
    btn.disabled = false;
    CHAT.inputEl.focus();
  }
}

async function deleteMessage(id, row) {
  if (!id || CHAT.deleting[id]) return;
  CHAT.deleting[id] = true;
  try {
    const res = await fetch("/api/chat/delete.php", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ id }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) {
      chatToast(data.error || "Could not delete message.");
      return;
    }
    removeMessageNode(id);
  } catch (e) {
    console.warn("[Chat] delete failed:", e);
    chatToast("Network error — try again.");
  } finally {
    delete CHAT.deleting[id];
  }
}

function chatToast(msg) {
  if (!CHAT.panel) return;
  let toast = CHAT.panel.querySelector(".chat-toast");
  if (!toast) {
    toast = el("div", "chat-toast");
    CHAT.panel.appendChild(toast);
  }
  toast.textContent = msg;
  toast.classList.add("show");
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove("show"), 3000);
}

/* ==========================================================================
 * Typing indicator (Telegram/WhatsApp style)
 * =========================================================================
 */

function startTypingBeacon() {
  if (!window.CHAT_CONTEXT?.loggedIn) return;
  clearTimeout(CHAT.typingIdleTimer);
  const now = Date.now();
  // Fire immediately on first keypress, then throttle to one beacon per 2.2s.
  if (now - CHAT.lastTypingSent >= 2200 || !CHAT.isCurrentlyTyping) {
    sendTypingBeacon(false);
  }
  // Auto-stop if the user pauses mid-sentence for 3 full seconds.
  CHAT.typingIdleTimer = setTimeout(() => {
    if (CHAT.isCurrentlyTyping) sendTypingBeacon(true);
  }, 3000);
}

function stopTypingBeacon() {
  clearTimeout(CHAT.typingIdleTimer);
  CHAT.typingIdleTimer = null;
  if (CHAT.isCurrentlyTyping) sendTypingBeacon(true);
}

function sendTypingBeacon(stop = false) {
  if (stop) {
    CHAT.isCurrentlyTyping = false;
    CHAT.lastTypingSent = 0;
  } else {
    CHAT.lastTypingSent = Date.now();
    CHAT.isCurrentlyTyping = true;
  }
  fetch("/api/chat/typing.php", {
    method: "POST",
    credentials: "same-origin",
    headers: csrfHeaders(),
    body: JSON.stringify({ stop }),
    keepalive: true,
  }).catch(() => {}); // transient beacons — silent fail
}

function showTyping(evt) {
  if (!CHAT.typingEl || !CHAT.me) return;
  if (evt.user_id === CHAT.me.user_id) return; // never show your own typing
  CHAT.typingUsers[evt.user_id] = {
    username: evt.username || "",
    expiresAt: Date.now() + 3800, // 3.8s absorbs the ~120ms PHP round-trip
  };
  renderTypingStrip();
}

function renderTypingStrip() {
  if (!CHAT.typingEl) return;
  const now = Date.now();
  const active = Object.entries(CHAT.typingUsers)
    .filter(([, v]) => now < v.expiresAt)
    .sort((a, b) => b[1].expiresAt - a[1].expiresAt);

  // Schedule cleanup at the soonest expiry so the strip auto-hides.
  clearTimeout(CHAT.typingClearTimer);
  if (active.length) {
    const soonest = Math.min(...active.map(([, v]) => v.expiresAt)) - now;
    CHAT.typingClearTimer = setTimeout(renderTypingStrip, Math.max(soonest, 50));
  }

  if (!active.length) {
    CHAT.typingEl.hidden = true;
    CHAT.typingEl.textContent = "";
    if (CHAT.fabTyping) { CHAT.fabTyping.hidden = true; CHAT.fabTyping.textContent = ""; }
    return;
  }

  CHAT.typingEl.hidden = false;
  CHAT.typingEl.textContent = "";

  const names = active.map(([, v]) => v.username || "Someone");
  const who = names.length === 1 ? names[0] : `${names.length} people`;
  const dot = el("span", "chat-typing-dots");
  for (let i = 0; i < 3; i++) dot.appendChild(el("span", "d" + i));
  CHAT.typingEl.appendChild(document.createTextNode(`${who} ${names.length === 1 ? "is" : "are"} typing`));
  CHAT.typingEl.appendChild(dot);

  // Also show typing indicator on the floating FAB button.
  if (CHAT.fabTyping) {
    const who = names.length === 1 ? names[0] : `${names.length} people`;
    CHAT.fabTyping.textContent = `${who} ${names.length === 1 ? "is" : "are"} typing `;
    const fabDot = el("span", "chat-typing-dots");
    for (let i = 0; i < 3; i++) fabDot.appendChild(el("span", "d" + i));
    CHAT.fabTyping.appendChild(fabDot);
    CHAT.fabTyping.hidden = false;
  }
}

/* ==========================================================================
 * Presence heartbeat (live online count)
 * =========================================================================
 */

function startPresence() {
  stopPresence();
  sendPresence();
  CHAT.presenceTimer = setInterval(sendPresence, 30000);
}

function stopPresence() {
  clearInterval(CHAT.presenceTimer);
  CHAT.presenceTimer = null;
}

async function sendPresence() {
  if (!window.CHAT_CONTEXT?.loggedIn) return;
  try {
    const res = await fetch("/api/chat/presence.php", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({}),
    });
    const data = await res.json().catch(() => ({}));
    if (data.success && typeof data.online === "number") {
      setOnlineCount(data.online);
    }
  } catch (e) { /* transient — ignore */ }
}

function setOnlineCount(n) {
  if (typeof n !== "number") return;
  CHAT.lastCount = n;
  if (CHAT.countEl) {
    CHAT.countEl.textContent = `${n} online`;
  }
}

function setMembersCount(n) {
  if (!CHAT.countEl) return;
  const online = CHAT.lastCount === null ? "" : ` · ${CHAT.lastCount} online`;
  CHAT.countEl.textContent = `${n} members${online}`;
}

/* ==========================================================================
 * Open / close / unread
 * =========================================================================
 */

function bumpUnread() {
  CHAT.unread += 1;
  if (CHAT.badgeEl) {
    CHAT.badgeEl.textContent = CHAT.unread > 99 ? "99+" : String(CHAT.unread);
    CHAT.badgeEl.hidden = false;
  }
  if (CHAT.fabEl) CHAT.fabEl.classList.add("chat-fab--has-unread");
}

function clearUnread() {
  CHAT.unread = 0;
  if (CHAT.badgeEl) CHAT.badgeEl.hidden = true;
  if (CHAT.fabEl) CHAT.fabEl.classList.remove("chat-fab--has-unread");
}

async function openPanel() {
  if (!CHAT.panel) return;
  CHAT.panel.hidden = false;
  requestAnimationFrame(() => CHAT.panel.classList.add("open"));
  CHAT.open = true;
  clearUnread();

  if (!window.CHAT_CONTEXT?.loggedIn) {
    // Guest: load history to show unread count, but keep input disabled.
    loadHistory(false).then(() => {
      renderGuestState();
      CHAT.inputEl.disabled = true;
      CHAT.sendBtn.disabled = true;
    });
    return;
  }

  if (!CHAT.historyLoaded) {
    await loadHistory(false);
  } else {
    await loadHistory(true);
  }

  // Mark previously-unread messages as read now that the panel is open,
  // so the server stops counting them as unread and the badge clears.
  if (CHAT.unread > 0) {
    await markAllRead();
    clearUnread();
  }

  connectSocket();
  scrollList(true);
  setTimeout(() => CHAT.inputEl && CHAT.inputEl.focus(), 250);
}

function renderGuestState() {
  if (!CHAT.listEl) return;
  CHAT.listEl.textContent = "";
  const empty = el("div", "chat-empty chat-guest");
  empty.appendChild(el("i", "bx bx-lock-alt"));
  empty.appendChild(el("p", null, "Sign in to join the chat"));
  empty.appendChild(el("span", null, "Global chat is available to logged-in members only."));
  const link = el("a", "btn btn-primary chat-signin-btn", "Sign in");
  link.href = "/auth/signin.php";
  empty.appendChild(link);
  CHAT.listEl.appendChild(empty);
  if (CHAT.inputEl) CHAT.inputEl.disabled = true;
  if (CHAT.sendBtn) CHAT.sendBtn.disabled = true;
  if (CHAT.countEl) CHAT.countEl.textContent = "Sign in to chat";
}

function closePanel(minimize) {
  if (!CHAT.panel) return;
  CHAT.panel.classList.remove("open");
  CHAT.open = false;
  const done = () => { CHAT.panel.hidden = true; };
  CHAT.panel.addEventListener("transitionend", done, { once: true });
  setTimeout(done, 300); // fallback if transitionend never fires
  if (minimize) {
    // Keep socket + presence alive so new messages badge the button.
  }
}

/* ==========================================================================
 * Wiring
 * =========================================================================
 */

function wire() {
  const built = buildWidget();
  if (!built) return; // widget not rendered yet

  CHAT.el = built.root;
  CHAT.panel = built.panel;
  CHAT.listEl = built.listEl;
  CHAT.inputEl = built.input;
  CHAT.sendBtn = built.send;
  CHAT.badgeEl = built.badge;
  CHAT.fabEl = built.fab;
  CHAT.countEl = built.countEl;
  CHAT.typingEl = built.typingEl;
  CHAT.fabTyping = built.fabTyping;
  CHAT.storm = {
    glow: built.stormGlow,
    flash: built.stormFlash,
    bolts: built.stormBolts,
    timer: null,
    running: false,
  };

  built.fab.addEventListener("click", openPanel);
  built.minBtn.addEventListener("click", () => closePanel(true));
  built.closeBtn.addEventListener("click", () => closePanel(false));
  built.send.addEventListener("click", sendMessage);

  built.scrollBtn.addEventListener("click", () => {
    scrollList(true);
  });
  built.listEl.addEventListener("scroll", () => {
    built.scrollBtn.hidden = isScrolledToBottom();
  });

  CHAT.inputEl.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  CHAT.inputEl.addEventListener("input", () => {
    if (CHAT.inputEl.value.trim()) startTypingBeacon();
    else stopTypingBeacon();
  });

  CHAT.inputEl.addEventListener("blur", stopTypingBeacon);

  // htmx SPA safety: re-attach the widget if a swap removed it.
  document.body.addEventListener("htmx:afterSwap", () => {
    if (!document.getElementById("chat-widget")) {
      document.body.appendChild(CHAT.el);
    }
  });

  // Logged-in users: connect immediately (panel closed) so unread badges
  // and the live online count work before the panel is ever opened.
  // onSubscribed gap-fills history and starts the presence heartbeat.
  if (window.CHAT_CONTEXT?.loggedIn) {
    // Merge guest unread tracking into the user account if the visitor
    // browsed chat as a guest before logging in.
    const storedGuestId = localStorage.getItem("tom-chat-guest-id");
    if (storedGuestId) {
      fetch("/api/chat/merge_guest.php", {
        method: "POST",
        credentials: "same-origin",
        headers: csrfHeaders(),
        body: JSON.stringify({ guest_id: storedGuestId }),
      }).catch(() => {}); // non-fatal; unread may over-count slightly
      localStorage.removeItem("tom-chat-guest-id");
    }
    connectSocket();
  }

  // Cleanup on unload.
  window.addEventListener("beforeunload", () => {
    stopTypingBeacon();
    stopPresence();
    disconnectSocket();
  });

  console.log("[Chat] widget ready");
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", wire);
} else {
  wire();
}

  })();
} catch (e) {
  console.error("[Chat] init error:", e);
}