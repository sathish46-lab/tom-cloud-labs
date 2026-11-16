<?php
/**
 * TOM LABS — Global Chat Widget (PHP-rendered DOM)
 * Server-rendered chat widget markup; JS wires up event listeners only.
 * IDs and classes must match chat.js selectors exactly.
 */

?>
<div id="chat-widget" class="chat-widget">
  <!-- Panel (hidden by default) -->
  <div id="chat-panel" class="chat-panel" hidden role="dialog" aria-label="Global chat">
    <!-- Header -->
    <div class="chat-header">
      <div class="chat-header-left">
        <div class="chat-logo"><i class="bx bx-conversation"></i></div>
        <div class="chat-header-title">
          <div class="chat-title">Tom Labs Chat</div>
          <div class="chat-subtitle" id="chat-online-count">…</div>
        </div>
      </div>
      <div class="chat-header-btns">
        <button type="button" class="chat-icon-btn" aria-label="Minimize chat">−</button>
        <button type="button" class="chat-icon-btn" aria-label="Close chat">×</button>
      </div>
    </div>

    <!-- Body -->
    <div class="chat-body">
      <div id="chat-messages" class="chat-messages" aria-live="polite"></div>

      <!-- Typing indicator strip -->
      <div id="chat-typing" class="chat-typing" hidden></div>

      <!-- Scroll-to-bottom button -->
      <button type="button" id="chat-scroll-bottom" class="chat-scroll-bottom" hidden aria-label="Scroll to bottom">⬇</button>

      <!-- Storm back layer (decorative lightning) -->
      <div class="chat-storm" aria-hidden="true">
        <div class="chat-storm-glow"></div>
        <div class="chat-storm-rain"></div>
      </div>
    </div>

    <!-- Composer -->
    <div class="chat-composer">
      <input type="text" id="chat-input" class="chat-input" maxlength="500" placeholder="Type a message…" autocomplete="off">
      <button type="button" id="chat-send" class="chat-send-btn" aria-label="Send message">
        <i class="bx bxs-send"></i>
      </button>
    </div>

    <!-- Storm front layer (decorative lightning) -->
    <div class="chat-storm-fx" aria-hidden="true">
      <div class="chat-storm-rain"></div>
      <div class="chat-storm-flash"></div>
      <svg class="chat-storm-bolts" viewBox="0 0 400 600" preserveAspectRatio="xMidYMid slice"></svg>
    </div>
  </div>

  <!-- Floating action button (bottom-right) -->
  <button type="button" id="chat-fab" class="chat-fab" aria-label="Open global chat">
    <img class="chat-fab-cat" src="/assets/mad-cat.gif" alt="" draggable="false" style="display: block;">
    <span id="chat-fab-badge" class="chat-fab-badge" hidden></span>
    <div class="chat-fab-typing" hidden></div>
  </button>
</div>

<?php
// End of chat widget template
?>
