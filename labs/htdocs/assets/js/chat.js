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
 * Send:       POST /api/chat/send  (auth + CSRF + rate limit + sanitize)
 * Delete:     POST /api/chat/delete (self-delete, admins delete any)
 * Typing:     POST /api/chat/typing (throttled) -> "(name) is typing..."
 * Presence:   POST /api/chat/presence heartbeat -> live online count
 * History:    GET  /api/chat/history (last 50, gap-fill on reconnect)
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

  // --- New features state ---
  editingId: null,          // message id currently being edited
  editInput: null,          // edit input element
  replyToId: null,          // message id being replied to
  replyEl: null,            // reply indicator element
  searchQuery: '',          // current search text
  searchResults: [],        // search result message ids
  searchActive: false,      // whether search results are showing
  pinnedList: [],           // pinned message objects
  pinnedView: false,        // whether pinned panel is open
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
  row._ts = msg.ts;
  row.style.setProperty("--chat-hue", String(chatHue(msg.user_id ?? msg.username)));

  // --- Reply quote indicator (shown above the bubble) ---
  if (msg.reply_to) {
    const rq = el("div", "chat-reply-quote");
    const rqName = el("div", "chat-reply-quote-name", msg.reply_to_username || "Someone");
    const rqText = el("div", "chat-reply-quote-text", msg.reply_to_text || "");
    rq.appendChild(rqName);
    rq.appendChild(rqText);
    row.appendChild(rq);
  }

  // --- Pinned badge ---
  if (msg.pinned && CHAT.isAdmin) {
    const pinBadge = el("span", "chat-msg-pin-badge", "Pinned");
    row.appendChild(pinBadge);
  }

  // Incoming: avatar (real image if the payload has avatar_url, else initial).
  if (!mine) {
    row.appendChild(avatarNode(msg.avatar, msg.username, "chat-avatar"));
  }

  const bubble = el("div", "chat-bubble");

  if (!mine) {
    // Incoming: sender name (+ admin badge) above the text.
    const head = el("div", "chat-msg-head");
    const nameWrap = el("span", "chat-msg-name", msg.username || "unknown");
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

  // --- Reactions (below text, above time) ---
  if (msg.reactions && msg.reactions.length) {
    const reactionsWrap = el("div", "chat-reactions");
    for (const r of msg.reactions) {
      reactionsWrap.appendChild(reactionNode(r.emoji, r.count, r.user_reacted));
    }
    meta.insertBefore(reactionsWrap, meta.firstChild);
  }

  // --- Edited indicator ---
  if (msg.edited) {
    const edited = el("span", "chat-msg-edited", "edited");
    meta.appendChild(edited);
  }

  // --- Controls (edit, delete, pin) via three-dot dropdown ---
  const canEdit = CHAT.me && mine && CHAT.editingId !== msg.id;
  const canDelete = CHAT.me && (mine || CHAT.isAdmin);
  const canPin = CHAT.isAdmin;

  if (canEdit || canDelete || canPin) {
    bubble.appendChild(moreOptionsNode(msg.id, mine, !!msg.pinned));
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

/* ==========================================================================
 * More options dropdown (three-dot menu)
 * ========================================================================= */

function moreOptionsNode(messageId, mine, pinned) {
  const wrap = el("div", "chat-msg-more-wrap");

  const btn = el("button", "chat-msg-more", "⋮");
  btn.type = "button";
  btn.setAttribute("aria-label", "More options");

  const menu = el("div", "chat-msg-menu");
  menu.hidden = true;

  // Edit option
  if (CHAT.me && mine && CHAT.editingId !== messageId) {
    const editItem = el("button", "chat-msg-menu-item", "Edit");
    editItem.type = "button";
    editItem.addEventListener("click", (e) => {
      e.stopPropagation();
      closeMenu(menu);
      startEditMode(messageId);
    });
    menu.appendChild(editItem);
  }

  // Pin / unpin option
  if (CHAT.isAdmin) {
    const pinItem = el("button", "chat-msg-menu-item", pinned ? "Unpin" : "Pin");
    pinItem.type = "button";
    pinItem.addEventListener("click", (e) => {
      e.stopPropagation();
      closeMenu(menu);
      pinMessage(messageId, !pinned);
    });
    menu.appendChild(pinItem);
  }

  // Delete option
  if (CHAT.me && (mine || CHAT.isAdmin)) {
    const delItem = el("button", "chat-msg-menu-item chat-msg-menu-item--danger", "Delete");
    delItem.type = "button";
    delItem.addEventListener("click", (e) => {
      e.stopPropagation();
      closeMenu(menu);
      deleteMessage(messageId, null);
    });
    menu.appendChild(delItem);
  }

  // Click outside to close
  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    closeAllMenus();
    menu.hidden = !menu.hidden;
  });

  wrap.appendChild(btn);
  wrap.appendChild(menu);
  return wrap;
}

function closeMenu(menu) {
  if (menu) menu.hidden = true;
}

function closeAllMenus() {
  document.querySelectorAll(".chat-msg-menu").forEach((m) => (m.hidden = true));
}

// Close menus when clicking anywhere
document.addEventListener("click", () => {
  closeAllMenus();
});

/* ==========================================================================
 * Reactions
 * ========================================================================= */

function reactionNode(emoji, count, userReacted) {
  const wrap = el("span", "chat-reaction");
  wrap.dataset.emoji = emoji;
  wrap.title = count + " reacted with " + emoji;

  const emojiSpan = el("span", "chat-reaction-emoji", emoji);
  const countSpan = el("span", "chat-reaction-count", String(count));
  wrap.appendChild(emojiSpan);
  wrap.appendChild(countSpan);

  if (userReacted) wrap.classList.add("chat-reaction--self");

  wrap.addEventListener("click", (e) => {
    e.stopPropagation();
    toggleReaction(emoji, wrap);
  });
  return wrap;
}

async function toggleReaction(emoji, node) {
  if (!CHAT.me) return;
  const row = node.closest(".chat-msg-row");
  if (!row) return;
  const messageId = row.dataset.id;
  try {
    const res = await fetch("/api/chat/reaction", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ message_id: messageId, emoji }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) return;
    // node will be updated via socket fan-out event; no optimistic UI here
  } catch (e) {
    console.warn("[Chat] reaction failed:", e);
  }
}

/* ==========================================================================
 * Edit message
 * ========================================================================= */

function startEditMode(messageId) {
  if (!CHAT.me) return;
  const row = CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(messageId)}"]`);
  if (!row) return;
  if (CHAT.editingId) cancelEditMode();

  const textEl = row.querySelector(".chat-msg-text");
  if (!textEl) return;
  const currentText = textEl.textContent;

  CHAT.editingId = messageId;
  CHAT.editInput = el("input", "chat-edit-input");
  CHAT.editInput.value = currentText;
  CHAT.editInput.maxLength = 500;

  const saveBtn = el("button", "chat-edit-save", "✓");
  saveBtn.type = "button";
  saveBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    finishEditMode(true);
  });
  const cancelBtn = el("button", "chat-edit-cancel", "✕");
  cancelBtn.type = "button";
  cancelBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    cancelEditMode();
  });

  textEl.textContent = "";
  textEl.appendChild(CHAT.editInput);
  textEl.appendChild(saveBtn);
  textEl.appendChild(cancelBtn);
  row.classList.add("chat-msg-row--editing");
  CHAT.editInput.focus();

  CHAT.editInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      finishEditMode(true);
    } else if (e.key === "Escape") {
      cancelEditMode();
    }
  });
}

function cancelEditMode() {
  if (!CHAT.editInput) return;
  const textEl = CHAT.editInput.parentElement;
  if (textEl) {
    textEl.textContent = CHAT.editInput.value || "";
  }
  CHAT.editInput = null;
  CHAT.editingId = null;
  const row = document.querySelector(".chat-msg-row--editing");
  if (row) row.classList.remove("chat-msg-row--editing");
}

async function finishEditMode(save) {
  if (!CHAT.editInput || !CHAT.editingId) {
    cancelEditMode();
    return;
  }
  const newText = CHAT.editInput.value.trim();
  cancelEditMode();
  if (!save || newText === "") return;
  try {
    const res = await fetch("/api/chat/edit", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ message_id: CHAT.editingId, text: newText }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) {
      chatToast(data.error || "Could not edit message.");
      return;
    }
    // Row will be updated via socket fan-out edit event.
  } catch (e) {
    console.warn("[Chat] edit failed:", e);
    chatToast("Network error — try again.");
  }
}

/* ==========================================================================
 * Reply / quote
 * ========================================================================= */

function replyNode(messageId, username, text) {
  const wrap = el("div", "chat-reply");
  wrap.dataset.replyTo = messageId;

  const name = el("div", "chat-reply-name", username || "unknown");
  const msg  = el("div", "chat-reply-text", text || "");
  wrap.appendChild(name);
  wrap.appendChild(msg);

  const remove = el("button", "chat-reply-remove", "✕");
  remove.type = "button";
  remove.setAttribute("aria-label", "Remove reply");
  remove.addEventListener("click", (e) => {
    e.stopPropagation();
    cancelReply();
  });
  wrap.appendChild(remove);

  return wrap;
}

function setReplyTo(messageId, username, text) {
  CHAT.replyToId = messageId;
  const composer = CHAT.inputEl.closest(".chat-composer");
  if (!composer) return;
  // Remove existing reply indicator if any
  const old = composer.querySelector(".chat-reply");
  if (old) old.remove();
  const rep = replyNode(messageId, username, text);
  composer.insertBefore(rep, CHAT.inputEl);
  CHAT.replyEl = rep;
  CHAT.inputEl.placeholder = "Replying…";
}

function cancelReply() {
  CHAT.replyToId = null;
  if (CHAT.replyEl) {
    CHAT.replyEl.remove();
    CHAT.replyEl = null;
  }
  if (CHAT.inputEl) CHAT.inputEl.placeholder = "Type a message…";
}

/* ==========================================================================
 * Pin / unpin
 * ========================================================================= */

async function pinMessage(messageId, pinned) {
  if (!CHAT.isAdmin) return;
  try {
    const res = await fetch("/api/chat/pin", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ message_id: messageId, pinned }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) {
      chatToast(data.error || "Could not pin message.");
      return;
    }
    // Row will be updated via socket fan-out pin event.
  } catch (e) {
    console.warn("[Chat] pin failed:", e);
  }
}

/* ==========================================================================
 * Search
 * ========================================================================= */

async function searchMessages(query) {
  if (!query) {
    CHAT.searchActive = false;
    CHAT.searchResults = [];
    renderSearchResults([]);
    return;
  }
  try {
    const res = await fetch("/api/chat/search?q=" + encodeURIComponent(query), {
      method: "GET",
      credentials: "same-origin",
      headers: { "Accept": "application/json" },
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) return;
    CHAT.searchQuery = query;
    CHAT.searchResults = data.messages || [];
    CHAT.searchActive = true;
    renderSearchResults(CHAT.searchResults);
  } catch (e) {
    console.warn("[Chat] search failed:", e);
  }
}

function renderSearchResults(messages) {
  if (!CHAT.listEl) return;
  CHAT.listEl.textContent = "";
  if (messages.length === 0) {
    const empty = el("div", "chat-empty");
    empty.appendChild(el("i", "bx bx-search"));
    empty.appendChild(el("p", null, "No results for “" + CHAT.searchQuery + "”"));
    CHAT.listEl.appendChild(empty);
    return;
  }
  for (const msg of messages) {
    CHAT.listEl.appendChild(renderMessage(msg));
  }
  regroup();
}

/* ==========================================================================
 * Pinned messages
 * ========================================================================= */

function renderPinned(messages) {
  const list = document.getElementById("chat-pinned-list");
  if (!list) return;
  list.textContent = "";
  if (messages.length === 0) {
    list.appendChild(el("p", null, "No pinned messages."));
    return;
  }
  for (const msg of messages) {
    list.appendChild(renderMessage(msg));
  }
}

/* ==========================================================================
 * Rendering — updated renderMessage with reactions, edit, reply, pin
 * ========================================================================= */


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

  if (evt.type === "reaction") {
    // Update reaction counts on the matching message row.
    const row = CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(evt.message_id)}"]`);
    if (row) {
      // Re-render the row with updated reactions by fetching fresh history
      // is too heavy; instead patch the reactions in place.
      const meta = row.querySelector(".chat-msg-meta");
      const existing = row.querySelector(".chat-reactions");
      if (existing) existing.remove();
      if (evt.added !== undefined && evt.count !== undefined) {
        // Find or create reactions container
        const wrap = el("div", "chat-reactions");
        wrap.appendChild(reactionNode(evt.emoji, evt.count, evt.user_id === CHAT.me?.user_id));
        meta.insertBefore(wrap, meta.firstChild);
      }
    }
    return;
  }

  if (evt.type === "edit") {
    const row = CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(evt.id)}"]`);
    if (row) {
      const textEl = row.querySelector(".chat-msg-text");
      if (textEl) textEl.textContent = evt.text;
      row.querySelector(".chat-msg-edited")?.remove();
      const edited = el("span", "chat-msg-edited", "edited");
      row.querySelector(".chat-msg-meta")?.appendChild(edited);
      row.classList.add("chat-msg-row--edited");
    }
    return;
  }

  if (evt.type === "pin") {
    const row = CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(evt.id)}"]`);
    if (row) {
      const existing = row.querySelector(".chat-msg-pin-badge");
      if (evt.pinned && !existing) {
        const badge = el("span", "chat-msg-pin-badge", "Pinned");
        row.insertBefore(badge, row.firstChild);
      } else if (!evt.pinned && existing) {
        existing.remove();
      }
      const btn = row.querySelector(".chat-msg-pin");
      if (btn) {
        btn.textContent = evt.pinned ? "📌" : "📍";
        btn.classList.toggle("chat-msg-pin--pinned", evt.pinned);
        btn.setAttribute("aria-label", evt.pinned ? "Unpin message" : "Pin message");
      }
    }
    // Refresh pinned panel if open
    if (CHAT.pinnedView) {
      loadHistory(true);
    }
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
    const res = await fetch("/api/chat/history", {
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
    const body = { text };
    if (CHAT.replyToId) body.reply_to = CHAT.replyToId;
    const res = await fetch("/api/chat/send", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify(body),
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
    cancelReply();
    // If the fan-out publish failed, the sender's message will appear on the
    // next history load (gap-fill on reconnect).
    if (data.published === false) {
      chatToast("Message sent — will sync on reconnect.");
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
    const res = await fetch("/api/chat/delete", {
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
  fetch("/api/chat/typing", {
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

  const names = active.map(([, v]) => v.username || "Someone");
  const who = names.length === 1 ? names[0] : `${names.length} people`;

  if (CHAT.open) {
    // Panel is open: show typing inside the panel only, hide FAB bubble.
    CHAT.typingEl.hidden = false;
    CHAT.typingEl.textContent = "";
    const dot = el("span", "chat-typing-dots");
    for (let i = 0; i < 3; i++) dot.appendChild(el("span", "d" + i));
    CHAT.typingEl.appendChild(document.createTextNode(`${who} ${names.length === 1 ? "is" : "are"} typing`));
    CHAT.typingEl.appendChild(dot);
    if (CHAT.fabTyping) { CHAT.fabTyping.hidden = true; CHAT.fabTyping.textContent = ""; }
  } else {
    // Panel is closed: show typing on the floating FAB button only.
    if (CHAT.fabTyping) {
      CHAT.fabTyping.textContent = `${who} ${names.length === 1 ? "is" : "are"} typing `;
      const fabDot = el("span", "chat-typing-dots");
      for (let i = 0; i < 3; i++) fabDot.appendChild(el("span", "d" + i));
      CHAT.fabTyping.appendChild(fabDot);
      CHAT.fabTyping.hidden = false;
    }
    CHAT.typingEl.hidden = true;
    CHAT.typingEl.textContent = "";
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
    const res = await fetch("/api/chat/presence", {
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

async function markAllRead() {
  try {
    const res = await fetch("/api/chat/read", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
    });
    if (!res.ok) return;
    const data = await res.json();
    if (!data.success) return;
  } catch (e) {
    // Non-fatal: badge stays until the next open.
  }
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

  // Load pinned messages if admin.
  if (CHAT.isAdmin) {
    await loadPinned();
  }

  connectSocket();
  scrollList(true);
  setTimeout(() => CHAT.inputEl && CHAT.inputEl.focus(), 250);
}

async function loadPinned() {
  if (!CHAT.isAdmin) return;
  try {
    const res = await fetch("/api/chat/search?q=", {
      method: "GET",
      credentials: "same-origin",
      headers: { "Accept": "application/json" },
    });
    // search with empty q returns all messages; filter pinned on client
    const data = await res.json().catch(() => ({}));
    if (!data.success) return;
    CHAT.pinnedList = (data.messages || []).filter((m) => m.pinned);
    renderPinned(CHAT.pinnedList);
    const badge = document.getElementById("chat-pinned-count");
    if (badge) {
      badge.textContent = CHAT.pinnedList.length;
      badge.hidden = CHAT.pinnedList.length === 0;
    }
  } catch (e) {
    console.warn("[Chat] load pinned failed:", e);
  }
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

  // --- Search ---
  const searchToggle = document.getElementById("chat-search-toggle");
  const searchEl = document.getElementById("chat-search");
  const searchInput = document.getElementById("chat-search-input");
  const searchClose = document.getElementById("chat-search-close");
  const pinnedToggle = document.getElementById("chat-pinned-toggle");
  const pinnedEl = document.getElementById("chat-pinned");
  const pinnedClose = document.getElementById("chat-pinned-close");

  if (searchToggle && searchEl && searchInput && searchClose) {
    searchToggle.addEventListener("click", () => {
      const hidden = searchEl.hidden;
      searchEl.hidden = !hidden;
      if (!hidden) {
        pinnedEl.hidden = true;
        CHAT.pinnedView = false;
        searchInput.focus();
      }
    });
    searchClose.addEventListener("click", () => {
      searchEl.hidden = true;
      CHAT.searchActive = false;
      CHAT.searchQuery = "";
      searchInput.value = "";
      renderHistory(CHAT.listEl ? [] : []); // will be replaced by history load
      if (CHAT.historyLoaded) loadHistory(true);
    });
    searchInput.addEventListener("input", (e) => {
      clearTimeout(CHAT._searchDebounce);
      CHAT._searchDebounce = setTimeout(() => {
        searchMessages(e.target.value);
      }, 300);
    });
    searchInput.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        searchEl.hidden = true;
        CHAT.searchActive = false;
        searchInput.value = "";
        if (CHAT.historyLoaded) loadHistory(true);
      }
    });
  }

  // --- Pinned ---
  if (pinnedToggle && pinnedEl && pinnedClose) {
    pinnedToggle.addEventListener("click", () => {
      const hidden = pinnedEl.hidden;
      pinnedEl.hidden = !hidden;
      if (!hidden) {
        searchEl.hidden = true;
        CHAT.pinnedView = true;
        if (CHAT.isAdmin) loadPinned();
      } else {
        CHAT.pinnedView = false;
      }
    });
    pinnedClose.addEventListener("click", () => {
      pinnedEl.hidden = true;
      CHAT.pinnedView = false;
    });
  }

  // Cancel reply/edit when clicking elsewhere in composer area
  CHAT.inputEl.addEventListener("focus", () => {
    // keep reply/edit active while typing
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
      fetch("/api/chat/merge_guest", {
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