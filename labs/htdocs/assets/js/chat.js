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
 * Edit:       POST /api/chat/edit
 * Pin:        POST /api/chat/pin (admin only)
 * Pinned:     GET  /api/chat/pinned (panel list)
 * Reaction:   POST /api/chat/reaction
 * Search:     GET  /api/chat/search?q=
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
 * @version 1.1.0
 * @author Sathish46 - Tom Labs
 * ============================================================================
 */

const QUICK_REACTIONS = ["👍", "❤️", "😂", "🔥", "🎉"];

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
  fabTyping: null,

  socket: null,
  connected: false,

  me: null,          // {user_id, username, role} from history response (logged-in only)
  isAdmin: false,

  guestId: null,     // UUID for guest tracking (stored in localStorage)

  open: false,
  unread: 0,

  teaser: {
    lastAt: 0,         // ms timestamp of last teaser shown (cooldown)
    el: null,          // active teaser bubble
    hideTimer: null,
  },

  call: {
    active: false,
    callId: null,
    pc: null,
    localStream: null,
    remoteStream: null,
    incoming: false,
    offerFrom: null,
    offerSdp: null,
    offerType: null,
  },

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
  msgCache: {},      // id -> {username,text,user_id} so reply quotes can name the original sender

  // --- Feature state ---
  editingId: null,          // message id currently being edited
  editInput: null,          // edit input element
  editOriginal: null,       // text before editing (for cancel)
  replyToId: null,          // message id being replied to
  replyEl: null,            // reply indicator element
  searchQuery: '',          // current search text
  searchResults: [],        // search result messages
  searchActive: false,      // whether search results are showing
  matchIndex: 0,            // currently highlighted search match
  _searchDebounce: null,
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
  let g = null;
  try { g = localStorage.getItem("tom-chat-guest-id"); } catch (e) {}
  if (!g) {
    g = crypto.randomUUID
      ? "guest_" + crypto.randomUUID()
      : "g_" + Math.random().toString(36).slice(2);
    try { localStorage.setItem("tom-chat-guest-id", g); } catch (e) {}
  }
  CHAT.guestId = g;
  return g;
}

/* ==========================================================================
 * DOM helpers (no innerHTML with user data — XSS-safe by design)
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

function rowById(id) {
  if (!CHAT.listEl || id === undefined || id === null) return null;
  return CHAT.listEl.querySelector(`[data-id="${chatEscapeAttr(id)}"]`);
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

// Time of day only — the date comes from the day divider above the message.
function chatFormatTs(ts) {
  if (!ts) return "";
  const d = new Date(ts);
  if (isNaN(d)) return "";
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
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
    badge, countEl, typingEl, fabTyping,
    minBtn, closeBtn, scrollBtn,
    stormGlow, stormFlash, stormBolts,
  };
}

/* ==========================================================================
 * Rendering — single message
 * =========================================================================
 */

function roleBadge(role) {
  if (role !== "admin") return null;
  return el("span", "chat-role-badge", "Admin");
}

function pinTagNode() {
  const t = el("div", "chat-pin-tag");
  t.appendChild(el("i", "bx bxs-pin"));
  t.appendChild(document.createTextNode("Pinned"));
  return t;
}

function cacheMsg(m) {
  if (m && m.id !== undefined) CHAT.msgCache[m.id] = { username: m.username, text: m.text, user_id: m.user_id };
}

// WhatsApp-style quote: coloured bar, bold sender name ("You" for your own
// messages), quoted text underneath. Name/text come from the server if it
// sends them, otherwise from the original message we already have loaded.
function replyQuoteNode(msg) {
  const src = CHAT.msgCache[msg.reply_to] || {};
  const origUid = msg.reply_to_user_id ?? src.user_id;
  const isMe = CHAT.me && origUid !== undefined && origUid !== null && String(origUid) === String(CHAT.me.user_id);
  const name = isMe ? "You" : (msg.reply_to_username || src.username || "unknown");
  const text = msg.reply_to_text || src.text || "Original message isn’t loaded";
  const rq = el("div", "chat-reply-quote");
  rq.style.setProperty("--chat-hue", String(chatHue(origUid ?? name)));
  rq.setAttribute("role", "button");
  rq.tabIndex = 0;
  rq.title = "Jump to original message";
  rq.appendChild(el("div", "chat-reply-quote-name", name));
  rq.appendChild(el("div", "chat-reply-quote-text", text));
  const go = () => jumpToMessage(msg.reply_to);
  rq.addEventListener("click", (e) => { e.stopPropagation(); go(); });
  rq.addEventListener("keydown", (e) => { if (e.key === "Enter") go(); });
  return rq;
}

function renderMessage(msg) {
  cacheMsg(msg);
  const mine = !!(CHAT.me && msg.user_id === CHAT.me.user_id);
  const row = el("div", "chat-msg-row" + (mine ? " mine" : " other"));
  row.dataset.id = msg.id;
  row.dataset.uid = String(msg.user_id ?? "");
  row._ts = msg.ts;
  row.style.setProperty("--chat-hue", String(chatHue(msg.user_id ?? msg.username)));

  // Incoming: avatar (real image if the payload has avatar_url, else initial).
  if (!mine) row.appendChild(avatarNode(msg.avatar, msg.username, "chat-avatar"));

  const bubble = el("div", "chat-bubble");

  // Pinned tag (visible to everyone)
  if (msg.pinned) bubble.appendChild(pinTagNode());

  // Reply quote lives INSIDE the bubble
  if (msg.reply_to) bubble.appendChild(replyQuoteNode(msg));

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

  // Text + (edited) + time share one row.
  const meta = el("div", "chat-msg-meta");
  meta.appendChild(el("div", "chat-msg-text", msg.text || ""));
  if (msg.edited) meta.appendChild(el("span", "chat-msg-edited", "edited"));
  meta.appendChild(el("span", "chat-msg-time", chatFormatTs(msg.ts)));
  bubble.appendChild(meta);

  // Reactions under the text
  if (msg.reactions && msg.reactions.length) {
    bubble.appendChild(reactionsWrapNode(msg.reactions));
  }

  row.appendChild(bubble);

  // Three-dot menu is a SIBLING of the bubble (CSS places it beside it).
  if (CHAT.me) row.appendChild(moreOptionsNode(msg.id, mine, !!msg.pinned));

  return row;
}

/* ==========================================================================
 * Three-dot menu
 * ========================================================================= */

let activeMenu = null;   // { menu, wrap }

function menuItem(icon, label, cls, onClick) {
  const b = el("button", "chat-msg-menu-item" + (cls ? " " + cls : ""));
  b.type = "button";
  b.setAttribute("role", "menuitem");
  b.appendChild(el("i", "bx " + icon));
  b.appendChild(el("span", null, label));
  b.addEventListener("click", (e) => {
    e.stopPropagation();
    closeAllMenus();
    onClick();
  });
  return b;
}

// The menu is ONE floating popover appended to the panel (not inside the
// scrolling transcript), so later messages can never paint over it and the
// transcript can never clip it.
function buildMenu(messageId, mine, pinned) {
  const menu = el("div", "chat-msg-menu");
  menu.setAttribute("role", "menu");

  const emojis = el("div", "chat-msg-menu-emojis");
  for (const em of QUICK_REACTIONS) {
    const b = el("button", "chat-msg-menu-emoji", em);
    b.type = "button";
    b.setAttribute("aria-label", "React with " + em);
    b.addEventListener("click", (e) => {
      e.stopPropagation();
      closeAllMenus();
      toggleReaction(em, messageId);
    });
    emojis.appendChild(b);
  }

  // "+" opens the full emoji picker (extended reactions)
  const more = el("button", "chat-msg-menu-emoji chat-msg-menu-emoji--more");
  more.type = "button";
  more.setAttribute("aria-label", "Choose another emoji");
  more.innerHTML = '<i class="bx bx-plus"></i>';
  more.addEventListener("click", (e) => {
    e.stopPropagation();
    const r = more.getBoundingClientRect();
    closeAllMenus();
    openEmojiPicker(messageId, r);
  });
  emojis.appendChild(more);
  menu.appendChild(emojis);

  menu.appendChild(menuItem("bx-reply", "Reply", "", () => {
    const row = rowById(messageId);
    if (!row) return;
    const name = row.querySelector(".chat-msg-name")?.textContent || (mine ? "You" : "unknown");
    const text = row.querySelector(".chat-msg-text")?.textContent || "";
    setReplyTo(messageId, name, text);
    CHAT.inputEl?.focus();
  }));
  if (mine) menu.appendChild(menuItem("bx-edit", "Edit", "", () => startEditMode(messageId)));
  if (CHAT.isAdmin) {
    menu.appendChild(menuItem(pinned ? "bxs-pin" : "bx-pin", pinned ? "Unpin" : "Pin", "",
      () => pinMessage(messageId, !pinned)));
  }
  if (mine || CHAT.isAdmin) {
    menu.appendChild(menuItem("bx-trash", "Delete", "chat-msg-menu-item--danger",
      () => deleteMessage(messageId, null)));
  }
  menu.addEventListener("click", (e) => e.stopPropagation());
  return menu;
}

function openMenu(wrap, btn, messageId, mine, pinned) {
  closeAllMenus();
  const menu = buildMenu(messageId, mine, pinned);
  CHAT.panel.appendChild(menu);
  wrap.classList.add("is-open");
  activeMenu = { menu, wrap };

  const pr = CHAT.panel.getBoundingClientRect();
  const br = btn.getBoundingClientRect();
  const mw = menu.offsetWidth, mh = menu.offsetHeight, pad = 8;

  // horizontal: my messages open toward the left, others toward the right
  let left = mine ? br.right - pr.left - mw : br.left - pr.left;
  left = Math.max(pad, Math.min(left, pr.width - mw - pad));

  // vertical: below the button, flip above if it would hit the composer area
  const limit = pr.height - 70;
  let top = br.bottom - pr.top + 4;
  if (top + mh > limit) top = br.top - pr.top - mh - 4;
  top = Math.max(pad + 50, top);

  menu.style.left = left + "px";
  menu.style.top = top + "px";
}

function moreOptionsNode(messageId, mine, pinned) {
  const wrap = el("div", "chat-msg-more-wrap");
  const btn = el("button", "chat-msg-more", "⋮");
  btn.type = "button";
  btn.setAttribute("aria-label", "More options");
  btn.setAttribute("aria-haspopup", "menu");
  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    if (activeMenu && activeMenu.wrap === wrap) { closeAllMenus(); return; }
    openMenu(wrap, btn, messageId, mine, pinned);
  });
  wrap.appendChild(btn);
  return wrap;
}

function closeAllMenus() {
  if (!activeMenu) return;
  activeMenu.menu.remove();
  activeMenu.wrap.classList.remove("is-open");
  activeMenu = null;
}

/* --------------------------------------------------------------------------
 * Extended-reaction emoji picker (lazy-loaded web component)
 * -------------------------------------------------------------------------- */

let activeEmojiPicker = null;
const EMOJI_POP_W = 340, EMOJI_POP_H = 400;

function openEmojiPicker(messageId, anchorRect) {
  if (activeEmojiPicker) { closeEmojiPicker(); return; }

  const pop = el("div", "chat-emoji-popover chat-emoji-popover--loading");
  pop.setAttribute("role", "dialog");
  pop.setAttribute("aria-label", "Pick a reaction emoji");
  pop.addEventListener("click", (e) => e.stopPropagation());
  CHAT.panel.appendChild(pop);
  activeEmojiPicker = pop;

  // position relative to the "⋮" button rect captured before the menu closed
  const pr = CHAT.panel.getBoundingClientRect();
  const pw = pop.offsetWidth || EMOJI_POP_W, ph = pop.offsetHeight || EMOJI_POP_H, pad = 8;
  let left = anchorRect.left - pr.left;
  left = Math.max(pad, Math.min(left, pr.width - pw - pad));
  let top = anchorRect.bottom - pr.top + 4;
  const limit = pr.height - 70;
  if (top + ph > limit) top = anchorRect.top - pr.top - ph - 4;
  top = Math.max(pad + 50, top);
  pop.style.left = left + "px";
  pop.style.top = top + "px";

  import("/assets/js/emoji-picker.js")
    .then(() => {
      const picker = document.createElement("emoji-picker");
      picker.setAttribute("data-source", "/assets/js/emoji-data.json");
      picker.addEventListener("emoji-click", (ev) => {
        ev.stopPropagation();
        const uni = ev.detail && (ev.detail.unicode || (ev.detail.emoji && ev.detail.emoji.unicode));
        if (!uni) return;
        closeEmojiPicker();
        toggleReaction(uni, messageId);
      });
      pop.classList.remove("chat-emoji-popover--loading");
      pop.appendChild(picker);
    })
    .catch((e) => {
      console.warn("[Chat] emoji picker failed to load:", e);
      chatToast("Could not load emoji picker.");
      closeEmojiPicker();
    });
}

function closeEmojiPicker() {
  if (!activeEmojiPicker) return;
  activeEmojiPicker.remove();
  activeEmojiPicker = null;
}

document.addEventListener("click", closeAllMenus);
document.addEventListener("click", closeEmojiPicker);
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") { closeAllMenus(); closeEmojiPicker(); }
});

/* ==========================================================================
 * Reactions
 * ========================================================================= */

function reactionNode(emoji, count, userReacted) {
  const wrap = el("span", "chat-reaction");
  wrap.dataset.emoji = emoji;
  wrap.title = count + " reacted with " + emoji;
  wrap.appendChild(el("span", "chat-reaction-emoji", emoji));
  wrap.appendChild(el("span", "chat-reaction-count", String(count)));
  if (userReacted) wrap.classList.add("chat-reaction--self");

  wrap.addEventListener("click", (e) => {
    e.stopPropagation();
    const row = wrap.closest(".chat-msg-row");
    if (row) toggleReaction(emoji, row.dataset.id);
  });
  return wrap;
}

function reactionsWrapNode(reactions) {
  const wrap = el("div", "chat-reactions");
  for (const r of reactions) {
    wrap.appendChild(reactionNode(r.emoji, r.count, r.user_reacted));
  }
  return wrap;
}

async function toggleReaction(emoji, messageId) {
  if (!CHAT.me || !messageId) return;
  try {
    const res = await fetch("/api/chat/reaction", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ message_id: messageId, emoji }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) chatToast(data.error || "Could not react.");
    // UI updates via the socket fan-out event; no optimistic update here.
  } catch (e) {
    console.warn("[Chat] reaction failed:", e);
  }
}

// Applies a "reaction" socket event to the matching row.
// If the server sends the full `reactions` array we re-render the whole chip
// list (preferred). Otherwise we patch just the one chip that changed.
function applyReactionEvent(evt) {
  const row = rowById(evt.message_id);
  if (!row) return;
  const bubble = row.querySelector(".chat-bubble");
  if (!bubble) return;
  let wrap = bubble.querySelector(".chat-reactions");

  if (Array.isArray(evt.reactions)) {
    wrap?.remove();
    if (evt.reactions.length) bubble.appendChild(reactionsWrapNode(evt.reactions));
    return;
  }

  if (!evt.emoji) return;
  if (!wrap) {
    wrap = el("div", "chat-reactions");
    bubble.appendChild(wrap);
  }
  const chip = wrap.querySelector(`[data-emoji="${chatEscapeAttr(evt.emoji)}"]`);

  if (!evt.count) {                       // last reaction removed
    chip?.remove();
    if (!wrap.children.length) wrap.remove();
    return;
  }

  const actedByMe = evt.user_id === CHAT.me?.user_id;
  const wasSelf = chip ? chip.classList.contains("chat-reaction--self") : false;
  const isSelf = actedByMe ? evt.added !== false : wasSelf;
  const fresh = reactionNode(evt.emoji, evt.count, isSelf);
  if (chip) chip.replaceWith(fresh);
  else wrap.appendChild(fresh);
}

/* ==========================================================================
 * Edit message
 * ========================================================================= */

function startEditMode(messageId) {
  if (!CHAT.me) return;
  const row = rowById(messageId);
  if (!row) return;
  if (CHAT.editingId) cancelEditMode();

  const textEl = row.querySelector(".chat-msg-text");
  if (!textEl) return;
  const currentText = textEl.textContent;

  CHAT.editingId = messageId;
  CHAT.editOriginal = currentText;
  CHAT.editInput = el("input", "chat-edit-input");
  CHAT.editInput.value = currentText;
  CHAT.editInput.maxLength = 500;

  const saveBtn = el("button", "chat-edit-save", "✓");
  saveBtn.type = "button";
  saveBtn.setAttribute("aria-label", "Save edit");
  saveBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    finishEditMode(true);
  });
  const cancelBtn = el("button", "chat-edit-cancel", "✕");
  cancelBtn.type = "button";
  cancelBtn.setAttribute("aria-label", "Cancel edit");
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
      e.stopPropagation();
      cancelEditMode();
    }
  });
}

function cancelEditMode() {
  if (!CHAT.editInput) return;
  const textEl = CHAT.editInput.parentElement;
  if (textEl) textEl.textContent = CHAT.editOriginal ?? "";
  CHAT.editInput = null;
  CHAT.editingId = null;
  CHAT.editOriginal = null;
  document.querySelector(".chat-msg-row--editing")?.classList.remove("chat-msg-row--editing");
}

async function finishEditMode(save) {
  if (!CHAT.editInput || !CHAT.editingId) { cancelEditMode(); return; }
  const id = CHAT.editingId;                 // capture BEFORE cancel clears it
  const newText = CHAT.editInput.value.trim();
  const changed = newText !== CHAT.editOriginal;
  cancelEditMode();
  if (!save || !newText || !changed) return;
  try {
    const res = await fetch("/api/chat/edit", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
      body: JSON.stringify({ message_id: id, text: newText }),
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) chatToast(data.error || "Could not edit message.");
    // Row updates via socket fan-out edit event.
  } catch (e) {
    console.warn("[Chat] edit failed:", e);
    chatToast("Network error — try again.");
  }
}

/* ==========================================================================
 * Reply / quote  (preview bar sits ABOVE the composer pill)
 * ========================================================================= */

function replyNode(messageId, username, text) {
  const wrap = el("div", "chat-reply");
  wrap.dataset.replyTo = messageId;

  const body = el("div", "chat-reply-body");
  body.appendChild(el("div", "chat-reply-name", username || "unknown"));
  body.appendChild(el("div", "chat-reply-text", text || ""));
  wrap.appendChild(body);

  const remove = el("button", "chat-reply-remove", "✕");
  remove.type = "button";
  remove.setAttribute("aria-label", "Cancel reply");
  remove.addEventListener("click", (e) => {
    e.stopPropagation();
    cancelReply();
  });
  wrap.appendChild(remove);
  return wrap;
}

function setReplyTo(messageId, username, text) {
  cancelReply();
  CHAT.replyToId = messageId;
  const composer = CHAT.inputEl?.closest(".chat-composer");
  if (!composer) return;
  const rep = replyNode(messageId, username, text);
  composer.parentNode.insertBefore(rep, composer);
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
    // Row + pinned list update via socket fan-out pin event.
  } catch (e) {
    console.warn("[Chat] pin failed:", e);
    chatToast("Network error — try again.");
  }
}

/* ==========================================================================
 * Pinned panel
 * ========================================================================= */

async function loadPinned() {
  if (!window.CHAT_CONTEXT?.loggedIn) return;
  try {
    // Dedicated endpoint: search.php returns an empty list for an empty
    // query, so the panel cannot reuse it.
    const res = await fetch("/api/chat/pinned", {
      credentials: "same-origin",
      headers: { "Accept": "application/json" },
    });
    const data = await res.json().catch(() => ({}));
    if (!data.success) return;
    CHAT.pinnedList = (data.messages || []).filter((m) => m.pinned);
    renderPinned(CHAT.pinnedList);
  } catch (e) {
    console.warn("[Chat] load pinned failed:", e);
  }
}

function renderPinned(messages) {
  const list = document.getElementById("chat-pinned-list");
  if (!list) return;
  list.textContent = "";

  const badge = document.getElementById("chat-pinned-count");
  if (badge) {
    badge.textContent = String(messages.length);
    badge.hidden = messages.length === 0;
  }

  if (!messages.length) {
    const empty = el("div", "chat-pinned-empty");
    empty.appendChild(el("i", "bx bx-pin"));
    empty.appendChild(el("div", null, "No pinned messages yet."));
    list.appendChild(empty);
    return;
  }
  for (const msg of messages) list.appendChild(pinnedItemNode(msg));
}

function pinnedItemNode(msg) {
  const item = el("div", "chat-pinned-item");
  item.dataset.id = msg.id;
  item.style.setProperty("--chat-hue", String(chatHue(msg.user_id ?? msg.username)));
  item.appendChild(avatarNode(msg.avatar, msg.username, "chat-avatar"));

  const body = el("div", "chat-pinned-body");
  const head = el("div", "chat-pinned-head");
  head.appendChild(el("span", "chat-msg-name", msg.username || "unknown"));
  head.appendChild(el("span", "chat-msg-time", chatDayLabel(msg.ts) + " · " + chatFormatTs(msg.ts)));
  body.appendChild(head);
  body.appendChild(el("div", "chat-pinned-text", msg.text || ""));
  item.appendChild(body);

  if (CHAT.isAdmin) {
    const un = el("button", "chat-pinned-unpin", "✕");
    un.type = "button";
    un.title = "Unpin";
    un.setAttribute("aria-label", "Unpin message");
    un.addEventListener("click", (e) => {
      e.stopPropagation();
      pinMessage(msg.id, false);
    });
    item.appendChild(un);
  }

  item.addEventListener("click", () => jumpToMessage(msg.id));
  return item;
}

function closePinned() {
  const p = document.getElementById("chat-pinned");
  if (p) p.hidden = true;
  CHAT.pinnedView = false;
}

function jumpToMessage(id) {
  closePinned();
  const row = rowById(id);
  if (!row) {
    chatToast("That message is older than the loaded history.");
    return;
  }
  row.scrollIntoView({ behavior: "smooth", block: "center" });
  row.classList.add("chat-msg-flash");
  setTimeout(() => row.classList.remove("chat-msg-flash"), 1600);
}

/* ==========================================================================
 * Search
 * ========================================================================= */

async function searchMessages(query) {
  if (!query) {
    CHAT.searchActive = false;
    CHAT.searchQuery = "";
    CHAT.searchResults = [];
    CHAT.matchIndex = 0;
    updateMatchNav();
    if (CHAT.historyLoaded) loadHistory(true);   // restore the normal transcript
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
    CHAT.matchIndex = 0;
    renderSearchResults(CHAT.searchResults);
    updateMatchNav();
  } catch (e) {
    console.warn("[Chat] search failed:", e);
  }
}

/* Match navigation: ↑/↓ buttons + "n of M matches" counter. Every match is
 * rendered while search is active, so the count is always complete; if a
 * match row disappeared live (deleted), re-render before jumping. */
function updateMatchNav() {
  const nav = document.getElementById("chat-match-nav");
  if (!nav) return;
  const total = CHAT.searchActive ? CHAT.searchResults.length : 0;
  nav.hidden = total === 0;
  if (!total) return;
  if (CHAT.matchIndex < 0) CHAT.matchIndex = 0;
  if (CHAT.matchIndex >= total) CHAT.matchIndex = total - 1;
  const count = document.getElementById("chat-match-count");
  if (count) count.textContent = (CHAT.matchIndex + 1) + " of " + total + " matches";
}

function flashMatch(row) {
  if (!row) return;
  row.scrollIntoView({ behavior: "smooth", block: "center" });
  row.classList.add("chat-msg-flash");
  setTimeout(() => row.classList.remove("chat-msg-flash"), 1600);
}

function gotoMatch(delta) {
  const total = CHAT.searchActive ? CHAT.searchResults.length : 0;
  if (!total) return;
  CHAT.matchIndex = (CHAT.matchIndex + delta + total) % total;
  updateMatchNav();
  const msg = CHAT.searchResults[CHAT.matchIndex];
  let row = msg && rowById(msg.id);
  if (!row) {
    // A result vanished from the DOM (live delete) — rebuild, then jump.
    renderSearchResults(CHAT.searchResults);
    updateMatchNav();
    row = msg && rowById(msg.id);
  }
  flashMatch(row);
}

function renderSearchResults(messages) {
  if (!CHAT.listEl) return;
  closeAllMenus();
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
  scrollList(false);
}

/* ==========================================================================
 * Transcript helpers
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
  if (rowById(msg.id)) return;
  const node = renderMessage(msg);
  CHAT.listEl.appendChild(node);
  regroup();
  if (scroll) scrollList(true);
}

function renderHistory(messages) {
  if (!CHAT.listEl) return;
  closeAllMenus();
  messages.forEach(cacheMsg);
  // If a reload happened while search results were showing (reconnect
  // gap-fill, etc.), re-run the search against the fresh cache/server so
  // the transcript, the match count and the arrows never disagree, and
  // matches that arrived in the gap are counted too.
  if (CHAT.searchActive && CHAT.searchQuery) {
    searchMessages(CHAT.searchQuery);
    return;
  }
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
    // While search results are on screen, don't mix live messages into them;
    // closing search reloads the history anyway.
    if (!CHAT.searchActive) {
      hideEmptyState();
      appendMessage(evt, { scroll: CHAT.open });
    }
    if (!CHAT.open) bumpUnread();
    if (!CHAT.open) teaserForMessage(evt);
    // A real message clears the sender's typing indicator everywhere.
    if (CHAT.typingUsers[evt.user_id]) {
      delete CHAT.typingUsers[evt.user_id];
      renderTypingStrip();
    }
    return;
  }

  if (evt.type === "delete") {
    removeMessageNode(evt.id);
    if (CHAT.pinnedList.some((m) => m.id === evt.id)) loadPinned();
    return;
  }

  if (evt.type === "reaction") {
    applyReactionEvent(evt);
    return;
  }

  if (evt.type === "edit") {
    const row = rowById(evt.id);
    if (row) {
      const textEl = row.querySelector(".chat-msg-text");
      // don't clobber the text while the user is editing this very message
      if (textEl && CHAT.editingId !== evt.id) textEl.textContent = evt.text;
      if (CHAT.msgCache[evt.id]) CHAT.msgCache[evt.id].text = evt.text;
      const meta = row.querySelector(".chat-msg-meta");
      meta?.querySelector(".chat-msg-edited")?.remove();
      const edited = el("span", "chat-msg-edited", "edited");
      const time = meta?.querySelector(".chat-msg-time");
      if (meta) meta.insertBefore(edited, time || null);
    }
    if (CHAT.pinnedList.some((m) => m.id === evt.id)) loadPinned();
    return;
  }

  if (evt.type === "pin") {
    const row = rowById(evt.id);
    if (row) {
      const bubble = row.querySelector(".chat-bubble");
      bubble?.querySelector(".chat-pin-tag")?.remove();
      if (evt.pinned && bubble) bubble.insertBefore(pinTagNode(), bubble.firstChild);
      // rebuild the menu so it says Pin / Unpin correctly
      row.querySelector(".chat-msg-more-wrap")?.replaceWith(
        moreOptionsNode(evt.id, row.classList.contains("mine"), !!evt.pinned)
      );
    }
    loadPinned();
    return;
  }

  if (evt.type && evt.type.startsWith("call-")) {
    handleCallEvent(evt);
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

  if (evt.type === "presence") {
    setOnlineCount(typeof evt.count === "number" ? evt.count : null);
    return;
  }
}

function removeMessageNode(id) {
  const node = rowById(id);
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
        loadHistory(true).then(() => {
          startPresence();
          loadPinned();
        });
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
    CHAT.isAdmin = ["admin", "superuser"].includes(CHAT.me?.role);

    // Apply unread count from server (works for both logged-in and guest).
    // While the panel is open nothing is unread, so keep the badge clear.
    CHAT.unread = CHAT.open ? 0 : (data.unread_count ?? 0);
    if (CHAT.badgeEl) {
      CHAT.badgeEl.textContent = CHAT.unread > 99 ? "99+" : String(CHAT.unread);
      CHAT.badgeEl.hidden = CHAT.unread === 0;
    }
    if (CHAT.fabEl) {
      CHAT.fabEl.classList.toggle("chat-fab--has-unread", CHAT.unread > 0);
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
    maybeWelcomeTeaser();
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
    if (CHAT.pinnedList.some((m) => m.id === id)) loadPinned();
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

/* ==========================================================================
 * Teaser bubbles — gentle nudges that invite the user to open the chat
 * (message previews while the panel is closed + one-time welcome note).
 * Anti-annoyance: 3-min cooldown between teasers, an ✕ dismiss snoozes all
 * teasers for 24 h, and nothing is ever shown while the panel is open.
 * ========================================================================= */

const TEASER_COOLDOWN_MS = 3 * 60 * 1000; // min gap between teasers
const TEASER_SHOW_MS     = 6000;          // auto-hide delay
const TEASER_DISMISS_KEY = "tom-chat-teaser-dismissed"; // 24 h snooze after ✕
const TEASER_WELCOME_KEY = "tom-chat-teaser-welcomed";  // one-time welcome flag

function teaserDismissed() {
  try {
    const until = parseInt(localStorage.getItem(TEASER_DISMISS_KEY), 10);
    return until && Date.now() < until;
  } catch (e) { return false; }
}

function hideTeaser() {
  clearTimeout(CHAT.teaser.hideTimer);
  if (CHAT.teaser.el) {
    const node = CHAT.teaser.el;
    node.classList.remove("chat-fab-teaser--show");
    setTimeout(() => node.remove(), 250);
    CHAT.teaser.el = null;
  }
}

function showTeaser(body) {
  if (CHAT.open || teaserDismissed()) return;
  if (Date.now() - CHAT.teaser.lastAt < TEASER_COOLDOWN_MS) return;
  const fab = CHAT.fabEl;
  if (!fab || !fab.parentElement) return;

  hideTeaser();
  CHAT.teaser.lastAt = Date.now();

  const bubble = el("div", "chat-fab-teaser");
  bubble.setAttribute("role", "status");
  bubble.appendChild(body);

  const x = el("button", "chat-fab-teaser-x", "✕");
  x.type = "button";
  x.setAttribute("aria-label", "Hide chat teasers for 24 hours");
  x.addEventListener("click", (e) => {
    e.stopPropagation();
    try {
      localStorage.setItem(TEASER_DISMISS_KEY, String(Date.now() + 24 * 3600 * 1000));
    } catch (err) {}
    hideTeaser();
  });
  bubble.appendChild(x);

  bubble.addEventListener("click", (e) => {
    e.stopPropagation();
    hideTeaser();
    openPanel();
  });

  fab.parentElement.appendChild(bubble);
  CHAT.teaser.el = bubble;
  requestAnimationFrame(() => bubble.classList.add("chat-fab-teaser--show"));
  CHAT.teaser.hideTimer = setTimeout(hideTeaser, TEASER_SHOW_MS);
}

// Live message teaser: "<name>: <preview>" while the panel is closed.
function teaserForMessage(evt) {
  if (CHAT.me && evt.user_id == CHAT.me.user_id) return; // never tease own messages
  if (teaserDismissed()) return;
  if (Date.now() - CHAT.teaser.lastAt < TEASER_COOLDOWN_MS) return;

  const body = el("div", "chat-fab-teaser-body");
  body.appendChild(el("span", "chat-fab-teaser-name", (evt.username || "Someone") + ":"));
  let text = String(evt.text || "").replace(/\s+/g, " ").trim();
  if (!text) text = "sent a message";
  if (text.length > 60) text = text.slice(0, 60) + "…";
  body.appendChild(el("span", "chat-fab-teaser-text", text));
  showTeaser(body);
}

// One-time welcome nudge for logged-in users with nothing unread.
function maybeWelcomeTeaser() {
  if (!window.CHAT_CONTEXT?.loggedIn) return;
  if (CHAT.open || CHAT.unread > 0) return;
  try {
    if (localStorage.getItem(TEASER_WELCOME_KEY) === "1") return;
    localStorage.setItem(TEASER_WELCOME_KEY, "1");
  } catch (e) { return; }

  const body = el("div", "chat-fab-teaser-body");
  body.appendChild(el("span", "chat-fab-teaser-wave", "👋"));
  body.appendChild(el("span", "chat-fab-teaser-text", "People are chatting right now — join in!"));
  showTeaser(body);
}

/* ==========================================================================
 * Video call (admin-initiated, P2P WebRTC)
 * Signaling rides the existing socket topic via /api/chat/call-signal.php
 * ========================================================================= */

const CALL_STUN = [{ urls: "stun:stun.l.google.com:19302" }];
const CALL_SIGNAL_URL = "/api/chat/call-signal.php";

function callEls() {
  return {
    modal: document.getElementById("chat-call-modal"),
    incoming: document.getElementById("chat-call-incoming"),
    localVideo: document.getElementById("chat-call-local-video"),
    remoteVideo: document.getElementById("chat-call-remote-video"),
    localLabel: document.getElementById("chat-call-local-label"),
    remoteLabel: document.getElementById("chat-call-remote-label"),
    status: document.getElementById("chat-call-status"),
    endBtn: document.getElementById("chat-call-end"),
    acceptBtn: document.getElementById("chat-call-accept"),
    declineBtn: document.getElementById("chat-call-decline"),
    fromLabel: document.getElementById("chat-call-incoming-from"),
    error: document.getElementById("chat-call-error"),
    errorTitle: document.getElementById("chat-call-error-title"),
    errorDesc: document.getElementById("chat-call-error-desc"),
    settingsBtn: document.getElementById("chat-call-open-settings"),
    retryBtn: document.getElementById("chat-call-retry-perm"),
  };
}

async function publishSignal(type, payload) {
  try {
    await fetch(CALL_SIGNAL_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: { ...csrfHeaders(), "content-type": "application/json" },
      body: JSON.stringify({ type, ...payload }),
    });
    console.log("[Chat] signal published:", type, "callId=", payload.call_id);
  } catch (e) {
    console.warn("[Chat] call signal failed:", e);
  }
}

function showCallModal() {
  const { modal } = callEls();
  if (!modal) return;
  CHAT.call.active = true;
  modal.hidden = false;
  requestAnimationFrame(() => { modal.hidden = false; });
  callEls().endBtn?.addEventListener("click", endCall);
  console.log("[Chat] call modal opened");
}

function hideCallModal() {
  const { modal, localVideo, remoteVideo } = callEls();
  if (modal) modal.hidden = true;
  CHAT.call.active = false;
  if (localVideo) { localVideo.srcObject = null; }
  if (remoteVideo) { remoteVideo.srcObject = null; }
  hideCallError();
  callEls().endBtn?.removeEventListener("click", endCall);
  console.log("[Chat] call modal closed");
}

function stopLocalStream() {
  if (CHAT.call.localStream) {
    CHAT.call.localStream.getTracks().forEach((t) => t.stop());
    CHAT.call.localStream = null;
  }
}

function openSiteSettings() {
  try {
    const host = window.location.hostname || "";
    const ua = navigator.userAgent || "";
    // Try opening the per-origin site settings panel first (Chrome/Edge).
    if (/Chrome\//.test(ua) || /Edg\//.test(ua)) {
      location.href = `chrome://settings/content/siteDetails?site=${host}`;
      return;
    }
    if (/Firefox\//.test(ua)) {
      location.href = `about:permissions#section-camera`;
      return;
    }
    location.href = "chrome://settings/content/microphone";
  } catch (e) {
    console.warn("[Chat] could not open site settings:", e);
  }
}

function showCallError(title, desc) {
  const { modal, error, errorTitle, errorDesc, videos, status } = callElsWithVideos();
  if (!modal || !error) return;
  CHAT.call.active = true;
  modal.hidden = false;
  requestAnimationFrame(() => { modal.hidden = false; });
  if (videos) videos.hidden = true;
  if (status) status.hidden = true;
  if (errorTitle) errorTitle.textContent = title || "Camera/microphone access required";
  if (errorDesc) errorDesc.textContent = desc || "Allow camera and microphone in site settings to start the video call.";
  error.hidden = false;
}

function hideCallError() {
  const { error, videos, status } = callElsWithVideos();
  if (error) error.hidden = true;
  if (videos) videos.hidden = false;
  if (status) status.hidden = false;
}

function callElsWithVideos() {
  const base = callEls();
  base.videos = document.querySelector(".chat-call-videos");
  return base;
}

function retryCall() {
  if (CHAT.call._retry === "answer") answerCall();
  else startCall();
}

function closePeerConnection() {
  if (CHAT.call.pc) {
    try { CHAT.call.pc.close(); } catch (e) {}
    CHAT.call.pc = null;
  }
}

async function endCall() {
  console.log("[Chat] endCall() callId=", CHAT.call.callId);
  closePeerConnection();
  stopLocalStream();
  hideCallModal();
  hideIncomingCall();
  if (CHAT.call.callId) {
    await publishSignal("end", { call_id: CHAT.call.callId });
    CHAT.call.callId = null;
  }
  CHAT.call.incoming = false;
  CHAT.call.offerFrom = null;
  CHAT.call.offerSdp = null;
  CHAT.call.offerType = null;
}

function showIncomingCall(fromUserId, fromUsername, callId, offerSdp, offerType) {
  console.log("[Chat] showIncomingCall() from=", fromUsername, "callId=", callId);
  const { incoming, fromLabel, acceptBtn, declineBtn } = callEls();
  if (!incoming) return;
  CHAT.call.incoming = true;
  CHAT.call.offerFrom = fromUserId;
  CHAT.call.callId = callId;
  CHAT.call.offerSdp = offerSdp;
  CHAT.call.offerType = offerType;
  if (fromLabel) fromLabel.textContent = fromUsername || "Someone";
  incoming.hidden = false;
  acceptBtn?.addEventListener("click", answerCall);
  declineBtn?.addEventListener("click", declineCall);
}

function hideIncomingCall() {
  console.log("[Chat] hideIncomingCall()");
  const { incoming, acceptBtn, declineBtn } = callEls();
  if (incoming) incoming.hidden = true;
  CHAT.call.incoming = false;
  acceptBtn?.removeEventListener("click", answerCall);
  declineBtn?.removeEventListener("click", declineCall);
}

async function startCall() {
  console.log("[Chat] startCall() triggered, isAdmin=", CHAT.isAdmin, "active=", CHAT.call.active);
  if (!CHAT.isAdmin) return;
  if (CHAT.call.active) return;

  try {
    CHAT.call.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  } catch (e) {
    console.warn("[Chat] camera/mic access denied:", e);
    CHAT.call._retry = "start";
    showCallError(
      "Camera/microphone access required",
      "Allow camera/microphone for dev.tomweb.in in site settings, then click Retry."
    );
    return;
  }
  console.log("[Chat] getUserMedia success, tracks=", CHAT.call.localStream.getTracks().length);

  const pc = new RTCPeerConnection({ iceServers: CALL_STUN });
  CHAT.call.pc = pc;

  CHAT.call.localStream.getTracks().forEach((track) => pc.addTrack(track, CHAT.call.localStream));

  pc.ontrack = (e) => {
    const { remoteVideo, remoteLabel } = callEls();
    if (remoteVideo && e.streams[0]) {
      remoteVideo.srcObject = e.streams[0];
      CHAT.call.remoteStream = e.streams[0];
      if (remoteLabel) remoteLabel.textContent = "Peer";
    }
  };

  pc.onicecandidate = (e) => {
    if (e.candidate) {
      console.log("[Chat] ICE candidate generated");
      publishSignal("ice", {
        call_id: CHAT.call.callId,
        candidate: JSON.stringify(e.candidate),
      });
    }
  };

  pc.onconnectionstatechange = () => {
    const { status } = callEls();
    if (status) {
      status.textContent = pc.connectionState === "connected" ? "Connected" : pc.connectionState || "Connecting…";
    }
    console.log("[Chat] connection state:", pc.connectionState);
    if (pc.connectionState === "disconnected" || pc.connectionState === "failed") {
      endCall();
    }
  };

  CHAT.call.callId = crypto.randomUUID?.() || Math.random().toString(36).slice(2);
  console.log("[Chat] callId=", CHAT.call.callId);

  try {
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    console.log("[Chat] offer created:", offer.type);
    await publishSignal("offer", {
      call_id: CHAT.call.callId,
      sdp: offer.sdp,
      sdp_type: offer.type,
    });
    console.log("[Chat] offer signal published");
    const { status, localVideo } = callEls();
    if (status) status.textContent = "Waiting for someone to join…";
    if (localVideo) localVideo.srcObject = CHAT.call.localStream;
    hideCallError();
    showCallModal();
  } catch (e) {
    console.warn("[Chat] call offer failed:", e);
    endCall();
  }
}

async function answerCall() {
  console.log("[Chat] answerCall() for callId=", CHAT.call.callId);
  if (!CHAT.call.callId || !CHAT.call.offerSdp) return;

  try {
    CHAT.call.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  } catch (e) {
    console.warn("[Chat] camera/mic access denied:", e);
    CHAT.call._retry = "answer";
    declineCall();
    showCallError(
      "Camera/microphone access required",
      "Allow camera and microphone in site settings to answer the video call."
    );
    return;
  }

  const pc = new RTCPeerConnection({ iceServers: CALL_STUN });
  CHAT.call.pc = pc;

  CHAT.call.localStream.getTracks().forEach((track) => pc.addTrack(track, CHAT.call.localStream));

  pc.ontrack = (e) => {
    const { remoteVideo, remoteLabel } = callEls();
    if (remoteVideo && e.streams[0]) {
      remoteVideo.srcObject = e.streams[0];
      CHAT.call.remoteStream = e.streams[0];
      if (remoteLabel) remoteLabel.textContent = "Peer";
    }
  };

  pc.onicecandidate = (e) => {
    if (e.candidate) {
      publishSignal("ice", {
        call_id: CHAT.call.callId,
        candidate: JSON.stringify(e.candidate),
      });
    }
  };

  pc.onconnectionstatechange = () => {
    const { status } = callEls();
    if (status) {
      status.textContent = pc.connectionState === "connected" ? "Connected" : pc.connectionState || "Connecting…";
    }
    if (pc.connectionState === "disconnected" || pc.connectionState === "failed") {
      endCall();
    }
  };

  hideIncomingCall();

  try {
    const offer = new RTCSessionDescription({ sdp: CHAT.call.offerSdp, type: CHAT.call.offerType });
    await pc.setRemoteDescription(offer);
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    await publishSignal("answer", {
      call_id: CHAT.call.callId,
      sdp: answer.sdp,
      sdp_type: answer.type,
    });
    const { status, localVideo } = callEls();
    if (status) status.textContent = "Connected";
    if (localVideo) localVideo.srcObject = CHAT.call.localStream;
    hideCallError();
    showCallModal();
    console.log("[Chat] answer published and modal opened");
  } catch (e) {
    console.warn("[Chat] call answer failed:", e);
    endCall();
  }
}

function declineCall() {
  console.log("[Chat] declineCall() callId=", CHAT.call.callId);
  hideIncomingCall();
  if (CHAT.call.callId) {
    publishSignal("end", { call_id: CHAT.call.callId });
    CHAT.call.callId = null;
  }
  CHAT.call.incoming = false;
  CHAT.call.offerFrom = null;
  CHAT.call.offerSdp = null;
  CHAT.call.offerType = null;
}

/* ==========================================================================
 * Socket event handling — call signaling
 * ========================================================================= */

function handleCallEvent(evt) {
  const callId = evt.call_id;
  const fromId = evt.from_user_id;
  const fromName = evt.from_username || "Someone";

  // Ignore our own signals.
  if (CHAT.me && fromId == CHAT.me.user_id) return;

  console.log("[Chat] handleCallEvent:", evt.type, "callId=", callId, "from=", fromName);

  if (evt.type === "call-offer") {
    if (CHAT.call.active) { console.log("[Chat] call-offer ignored (already in call)"); return; }
    showIncomingCall(fromId, fromName, callId, evt.sdp, evt.sdp_type);
    return;
  }

  if (evt.type === "call-answer") {
    if (!CHAT.call.active || CHAT.call.callId !== callId) { console.log("[Chat] call-answer ignored"); return; }
    const { status } = callEls();
    if (status) status.textContent = "Connecting…";
    try {
      const answer = new RTCSessionDescription({ sdp: evt.sdp, type: evt.sdp_type });
      CHAT.call.pc?.setRemoteDescription(answer).catch((e) => console.warn("[Chat] set remote answer failed:", e));
    } catch (e) {
      console.warn("[Chat] invalid answer:", e);
    }
    return;
  }

  if (evt.type === "call-ice") {
    if (!CHAT.call.active || CHAT.call.callId !== callId) { console.log("[Chat] call-ice ignored"); return; }
    try {
      const cand = JSON.parse(evt.candidate);
      CHAT.call.pc?.addIceCandidate(cand).catch((e) => console.warn("[Chat] add ice candidate failed:", e));
    } catch (e) {
      console.warn("[Chat] invalid ice candidate:", e);
    }
    return;
  }

  if (evt.type === "call-end") {
    console.log("[Chat] call-end received for callId=", callId);
    if (!callId || CHAT.call.callId === callId) endCall();
    return;
  }
}

async function markAllRead() {
  try {
    const res = await fetch("/api/chat/read", {
      method: "POST",
      credentials: "same-origin",
      headers: csrfHeaders(),
    });
    if (!res.ok) return;
    await res.json().catch(() => ({}));
  } catch (e) {
    // Non-fatal: badge stays until the next open.
  }
}

async function openPanel() {
  if (!CHAT.panel) return;
  CHAT.panel.hidden = false;
  requestAnimationFrame(() => CHAT.panel.classList.add("open"));
  CHAT.open = true;
  hideTeaser();
  const hadUnread = CHAT.unread > 0;
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
  if (hadUnread) {
    await markAllRead();
    clearUnread();
  }

  loadPinned();            // everyone sees pinned messages; no need to await
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
  closeAllMenus();
  CHAT.panel.classList.remove("open");
  CHAT.open = false;
  const done = () => { if (!CHAT.open) CHAT.panel.hidden = true; };
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
    closeAllMenus();
  });

  // --- Search + Pinned toggles ---
  const searchToggle = document.getElementById("chat-search-toggle");
  const searchEl = document.getElementById("chat-search");
  const searchInput = document.getElementById("chat-search-input");
  const searchClose = document.getElementById("chat-search-close");
  const pinnedToggle = document.getElementById("chat-pinned-toggle");
  const pinnedEl = document.getElementById("chat-pinned");
  const pinnedClose = document.getElementById("chat-pinned-close");

  function closeSearch() {
    if (!searchEl) return;
    const wasActive = CHAT.searchActive;
    searchEl.hidden = true;
    searchInput.value = "";
    CHAT.searchActive = false;
    CHAT.searchQuery = "";
    CHAT.matchIndex = 0;
    updateMatchNav();
    if (wasActive && CHAT.historyLoaded) loadHistory(true);   // restore normal transcript
  }

  if (searchToggle && searchEl && searchInput && searchClose) {
    searchToggle.addEventListener("click", () => {
      if (!searchEl.hidden) { closeSearch(); return; }
      closePinned();
      searchEl.hidden = false;
      searchInput.focus();
    });
    searchClose.addEventListener("click", closeSearch);
    searchInput.addEventListener("input", (e) => {
      clearTimeout(CHAT._searchDebounce);
      CHAT._searchDebounce = setTimeout(() => searchMessages(e.target.value.trim()), 300);
    });
    searchInput.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeSearch();
      if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); gotoMatch(CHAT.matchIndex === 0 ? 0 : 1); }
      if (e.key === "Enter" && e.shiftKey) { e.preventDefault(); gotoMatch(-1); }
    });
    const matchPrev = document.getElementById("chat-match-prev");
    const matchNext = document.getElementById("chat-match-next");
    if (matchPrev) matchPrev.addEventListener("click", () => gotoMatch(-1));
    if (matchNext) matchNext.addEventListener("click", () => gotoMatch(1));
  }

  if (pinnedToggle && pinnedEl && pinnedClose) {
    pinnedToggle.addEventListener("click", () => {
      if (!pinnedEl.hidden) { closePinned(); return; }
      if (searchEl && !searchEl.hidden) closeSearch();
      pinnedEl.hidden = false;
      CHAT.pinnedView = true;
      loadPinned();
    });
    pinnedClose.addEventListener("click", closePinned);
  }

  // Video call toggle — rendered server-side for admins only.
  const callToggle = document.getElementById("chat-call-toggle");
  if (callToggle) {
    callToggle.addEventListener("click", startCall);
    console.log("[Chat] video call toggle listener attached, admin=", CHAT.isAdmin);
  } else {
    console.log("[Chat] video call toggle not rendered (non-admin or template missing)");
  }

  // Permission-error actions inside the call modal.
  const permSettings = document.getElementById("chat-call-open-settings");
  const permRetry = document.getElementById("chat-call-retry-perm");
  if (permSettings) permSettings.addEventListener("click", openSiteSettings);
  if (permRetry) permRetry.addEventListener("click", retryCall);

  CHAT.inputEl.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    } else if (e.key === "Escape" && CHAT.replyToId) {
      cancelReply();
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
    let storedGuestId = null;
    try { storedGuestId = localStorage.getItem("tom-chat-guest-id"); } catch (e) {}
    if (storedGuestId) {
      fetch("/api/chat/merge_guest", {
        method: "POST",
        credentials: "same-origin",
        headers: csrfHeaders(),
        body: JSON.stringify({ guest_id: storedGuestId }),
      }).catch(() => {}); // non-fatal; unread may over-count slightly
      try { localStorage.removeItem("tom-chat-guest-id"); } catch (e) {}
    }
    connectSocket();
  }

  // Cleanup on unload.
  window.addEventListener("beforeunload", () => {
    stopTypingBeacon();
    stopPresence();
    disconnectSocket();
    // Drop any active call so peers are notified.
    if (CHAT.call.pc) { try { CHAT.call.pc.close(); } catch (e) {} CHAT.call.pc = null; }
    if (CHAT.call.localStream) { CHAT.call.localStream.getTracks().forEach((t) => t.stop()); CHAT.call.localStream = null; }
    if (CHAT.call.callId) {
      publishSignal("end", { call_id: CHAT.call.callId }).catch(() => {});
    }
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