<?php
/**
 * Admin → Settings → Chat Settings
 * Global chat feature switches. Stored as JSON in src/config/chat.json
 * (not Mongo) via the ChatSettings class.
 *
 * UI: ONE card with a single select (same pattern as Appearance → Public URL
 * background) + a readme strip under it that follows the selected mode + an
 * (i) info button that opens a full-documentation popup card.
 */
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/lib/core/ChatSettings.class.php';

$settings = ChatSettings::get();
$mode     = (string)$settings['video_call_mode'];

$updatedWhen = ((int)$settings['updated_at']) > 0
    ? date('d M Y H:i', (int)$settings['updated_at'])
    : 'never';

$modeDescriptions = [
    ChatSettings::MODE_OFF    => 'Video calls fully disabled — the call button, incoming-call UI and call code are removed from every browser, and the signaling API rejects all requests. In-progress calls are cut immediately.',
    ChatSettings::MODE_ADMIN  => 'Default. Only admins and superusers see the call button and can start calls; any logged-in user can still answer. Same behavior as before this setting existed.',
    ChatSettings::MODE_PUBLIC => 'Every logged-in user can start and answer video calls. Guests are never allowed — all chat APIs require a session.',
];

$modeLabels = [
    ChatSettings::MODE_OFF    => 'Off',
    ChatSettings::MODE_ADMIN  => 'Admins only',
    ChatSettings::MODE_PUBLIC => 'All logged-in users',
];

$modeIcons = [
    ChatSettings::MODE_OFF    => 'bx-video-off',
    ChatSettings::MODE_ADMIN  => 'bx-shield-quarter',
    ChatSettings::MODE_PUBLIC => 'bx-group',
];
?>
<style>
    /* ── Selected-mode readme strip ── */
    .ch-readme { border-left: 3px solid rgba(46, 204, 113, .55); }
    [data-coreui-theme="light"] .ch-readme { border-left-color: rgba(16, 163, 74, .55); }

    /* ── (i) info button (banner) ── */
    .ch-info-btn {
        width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: .5rem;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .75);
        font-size: 1.15rem;
        transition: background .15s ease, color .15s ease;
    }
    .ch-info-btn:hover { background: rgba(255, 255, 255, .16); color: #fff; }
    [data-coreui-theme="light"] .ch-info-btn {
        background: rgba(16, 24, 40, .06);
        border-color: rgba(16, 24, 40, .12);
        color: rgba(16, 24, 40, .65);
    }
    [data-coreui-theme="light"] .ch-info-btn:hover { background: rgba(16, 24, 40, .1); color: rgba(16, 24, 40, .9); }

    /* ── Full-readme popup card ── */
    .ch-doc-overlay {
        position: fixed; inset: 0; z-index: 1200;
        display: flex; align-items: center; justify-content: center;
        background: rgba(0, 0, 0, .6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0; pointer-events: none;
        transition: opacity .18s ease;
        padding: 1rem;
    }
    .ch-doc-overlay.is-open { opacity: 1; pointer-events: auto; }
    .ch-doc-card {
        width: min(680px, 94vw);
        max-height: min(80vh, 720px);
        overflow-y: auto;
        border-radius: 1.1rem;
        background: color-mix(in srgb, var(--cui-body-bg, #212631) 92%, transparent);
        border: 1px solid var(--cui-border-color, rgba(255,255,255,.12));
        box-shadow: 0 24px 60px rgba(0, 0, 0, .55);
    }
    .ch-doc-section {
        border: 1px solid var(--cui-border-color, rgba(255,255,255,.1));
        border-radius: .9rem;
        padding: .9rem 1.1rem;
        background: rgba(255, 255, 255, .03);
    }
    [data-coreui-theme="light"] .ch-doc-section { background: rgba(16, 24, 40, .03); }
    .ch-doc-section h6 { margin-bottom: .35rem; }
    .ch-doc-section p, .ch-doc-section li { color: var(--cui-secondary-color); font-size: .86rem; line-height: 1.55; }
    .ch-doc-close {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: .5rem;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .75);
        font-size: 1.05rem;
    }
    .ch-doc-close:hover { background: rgba(229, 83, 83, .18); color: #e55353; }
    [data-coreui-theme="light"] .ch-doc-close {
        background: rgba(16, 24, 40, .06); border-color: rgba(16, 24, 40, .12); color: rgba(16, 24, 40, .65);
    }
</style>

<!-- ============================== Banner ============================== -->
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-conversation'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Chat Settings</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Video call access for the global chat widget</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-success" id="chHeaderMode"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span>
                    <button type="button" class="ch-info-btn" id="chChatInfoBtn" aria-label="How chat settings work" title="How chat settings work">
                        <i class='bx bx-info-circle'></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-4">
    <div class="row g-4 mb-3">
        <div class="col-12">
            <!-- ── Single card: one select, same pattern as Appearance → Public URL background ── -->
            <div class="card border-0 rounded-4 blur shadow-sm">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i class='bx bxs-video me-2'></i>Video calls</span>
                    <span class="badge rounded-pill text-bg-secondary" id="chModeBadge"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="small text-body-secondary mb-3">
                        Who can start and answer video calls in the global chat widget.
                        The choice is enforced server-side and applies site-wide immediately.
                    </div>

                    <label class="fw-semibold d-block mb-1" for="chCallMode">Video call access</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <select id="chCallMode" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25" style="max-width: 260px;">
                            <option value="<?= ChatSettings::MODE_OFF ?>"    <?= $mode === ChatSettings::MODE_OFF    ? 'selected' : '' ?>>Off</option>
                            <option value="<?= ChatSettings::MODE_ADMIN ?>"  <?= $mode === ChatSettings::MODE_ADMIN  ? 'selected' : '' ?>>Admins only</option>
                            <option value="<?= ChatSettings::MODE_PUBLIC ?>" <?= $mode === ChatSettings::MODE_PUBLIC ? 'selected' : '' ?>>All logged-in users</option>
                        </select>
                        <div class="small text-body-secondary d-flex align-items-center gap-2" id="chModeIcon" title="Selected mode">
                            <i class='bx <?= htmlspecialchars($modeIcons[$mode] ?? 'bx-question-mark') ?> fs-5'></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Readme strip: follows the selected mode -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 rounded-4 blur shadow-sm ch-readme">
                <div class="card-body px-4 py-3 d-flex align-items-start gap-3">
                    <i class='bx bxs-info-square text-success mt-1'></i>
                    <div>
                        <div class="fw-semibold small mb-1">
                            How <span id="chReadmeMode"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span> works
                            <span class="text-body-secondary">(saved instantly on change)</span>
                        </div>
                        <div class="small text-body-secondary" id="chReadmeText"><?= htmlspecialchars($modeDescriptions[$mode] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Page action bar ── -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 rounded-4 blur shadow-sm mb-4">
                <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap px-4 py-3">
                    <div class="small text-body-secondary mb-0">
                        <i class='bx bx-time-five me-1'></i>Last saved:
                        <span id="chUpdated"><?= htmlspecialchars($updatedWhen) ?></span>
                        <?= $settings['updated_by'] !== '' ? ' by ' . htmlspecialchars((string)$settings['updated_by']) : '' ?>
                    </div>
                    <div class="small text-body-secondary mb-0">
                        Stored in <code>src/config/chat.json</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── "How chat settings work" popup card ── -->
<div class="ch-doc-overlay" id="chChatInfoModal" role="dialog" aria-modal="true" aria-label="How chat settings work">
    <div class="ch-doc-card">
        <div class="d-flex align-items-center justify-content-between gap-3 px-4 py-3 border-bottom border-secondary border-opacity-10">
            <h5 class="fw-bold mb-0"><i class='bx bx-info-circle me-2'></i>How chat settings work</h5>
            <button type="button" class="ch-doc-close" id="chChatInfoClose" aria-label="Close">✕</button>
        </div>
        <div class="p-4 d-flex flex-column gap-3">
            <div class="ch-doc-section">
                <h6 class="fw-bold">🎯 What this page controls</h6>
                <p>
                    One switch: <strong>who can use video calls</strong> in the global chat widget.
                    It applies to the whole site and takes effect immediately — no reload or deploy needed.
                </p>
            </div>

            <div class="ch-doc-section">
                <h6 class="fw-bold">🛡️ The three modes</h6>
                <ul class="mb-0 ps-3">
                    <li><strong>Off</strong> — calls are completely disabled. The call button, incoming-call popup and the entire call JavaScript are removed from every browser, and the signaling API rejects everything. In-progress calls are cut immediately.</li>
                    <li><strong>Admins only</strong> — <em>default</em>. Only admins and superusers see the call button and can start calls. Any logged-in user can still answer an incoming call.</li>
                    <li><strong>All logged-in users</strong> — every signed-in user can start and answer calls. Guests can never call.</li>
                </ul>
            </div>

            <div class="ch-doc-section">
                <h6 class="fw-bold">🔒 How it is enforced (security)</h6>
                <p class="mb-1">
                    The browser is <strong>never trusted</strong>. Every call signal
                    (<code>offer / answer / ice / end</code>) goes through
                    <code>/api/chat/call-signal.php</code>, which re-checks the current mode, your
                    session identity and your CSRF token on every request. The JavaScript is only UI.
                </p>
                <p class="mb-0">
                    Changing the select saves the setting server-side with an audited admin API
                    (<code>requireAdmin</code> + CSRF), so only admins can change it.
                </p>
            </div>

            <div class="ch-doc-section">
                <h6 class="fw-bold">📦 Where the setting is stored</h6>
                <p class="mb-0">
                    A plain JSON file: <code>src/config/chat.json</code> — not the database. It is
                    written atomically (temp file + rename), is human-readable, survives DB issues,
                    and falls back safely to <strong>Admins only</strong> if the file is missing or corrupt.
                </p>
            </div>

            <div class="ch-doc-section">
                <h6 class="fw-bold">🧩 How the code reaches browsers</h6>
                <p class="mb-0">
                    The call logic lives in a separate bundle (<code>chat-call.js</code>) that is
                    sent to the browser <strong>only</strong> when calls are enabled for the viewer.
                    In <strong>Off</strong> mode nothing call-related is shipped at all — view-source
                    shows no call UI, no call button, no call code.
                </p>
            </div>

            <div class="ch-doc-section">
                <h6 class="fw-bold">✨ Good to know</h6>
                <p class="mb-0">
                    Calls are peer-to-peer (WebRTC) with a STUN server; signaling (who-calls-whom
                    metadata) is relayed through the chat backend. While a call is open, admins see a
                    live counter in the call bar, e.g. <code>Connected · 2/10 · 10 online</code> —
                    participants vs users online.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var current = <?= json_encode($mode) ?>;

    var modeLabels = <?= json_encode($modeLabels) ?>;
    var modeDescriptions = <?= json_encode($modeDescriptions) ?>;
    var modeIcons = <?= json_encode($modeIcons) ?>;

    var select = document.getElementById('chCallMode');

    function setMode(mode) {
        if (mode === current) return;
        var fd = new URLSearchParams();
        fd.append('video_call_mode', mode);
        fetch('/api/admin/chat_settings_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    current = mode;
                    if (window.TomNotify) TomNotify.show('Chat settings saved', 'Success', 'success', 3000);
                    refreshView();
                } else if (window.TomNotify) {
                    TomNotify.show(data.error || 'Save failed', 'Error', 'error', 4000);
                    select.value = current; // revert on failure
                }
            })
            .catch(function () {
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
                select.value = current; // revert on failure
            });
    }

    function refreshView() {
        var label = modeLabels[current] || current;
        var text = modeDescriptions[current] || '';
        var icon = modeIcons[current] || 'bx-question-mark';

        document.getElementById('chHeaderMode').textContent = label;
        document.getElementById('chModeBadge').textContent = label;
        document.getElementById('chModeIcon').innerHTML = '<i class="bx ' + icon + ' fs-5"></i>';
        document.getElementById('chReadmeMode').textContent = label;
        document.getElementById('chReadmeText').textContent = text;
    }

    if (select) {
        select.addEventListener('change', function () { setMode(select.value); });
    }

    // (i) info popup
    var infoBtn = document.getElementById('chChatInfoBtn');
    var infoModal = document.getElementById('chChatInfoModal');
    var infoClose = document.getElementById('chChatInfoClose');

    function openInfo() {
        if (!infoModal) return;
        infoModal.hidden = false;
        infoModal.classList.add('is-open');
    }
    function closeInfo() {
        if (!infoModal) return;
        infoModal.classList.remove('is-open');
        setTimeout(function () { infoModal.hidden = true; }, 200);
    }

    if (infoBtn) infoBtn.addEventListener('click', openInfo);
    if (infoClose) infoClose.addEventListener('click', closeInfo);
    if (infoModal) {
        infoModal.hidden = true;
        infoModal.addEventListener('click', function (e) {
            if (e.target === infoModal) closeInfo();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && infoModal.classList.contains('is-open')) closeInfo();
        });
    }
})();
</script>
