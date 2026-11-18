<?php
/**
 * Admin → Settings → Notification Settings
 * Global notification mode + broadcast control.
 * Stored as JSON in src/config/notification.json via NotificationSettings class.
 *
 * UI: ONE card with a mode select + broadcast toggle (same pattern as
 * Admin → Settings → Chat Settings) + a readme strip under it that follows
 * the selected mode + an (i) info button that opens a full-documentation
 * popup card.
 */
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/lib/core/NotificationSettings.class.php';

$settings = NotificationSettings::get();
$mode     = (string)$settings['notification_mode'];
$broadcast= (bool)($settings['broadcast_enabled'] ?? true);

$updatedWhen = ((int)$settings['updated_at']) > 0
    ? date('d M Y H:i', (int)$settings['updated_at'])
    : 'never';

$modeDescriptions = [
    NotificationSettings::MODE_OFF   => 'Notifications fully suppressed — no new-device security alerts, no admin broadcasts, no MCP connect notifications. The bell still renders but nothing new is ever created. Use this to turn the whole feature off site-wide.',
    NotificationSettings::MODE_ADMIN => 'Default. Admins can broadcast to all users (or a role / specific emails). System, security and MCP notifications are created normally per user. Each user controls their own email + category toggles in Account Settings.',
];

$modeLabels = [
    NotificationSettings::MODE_OFF   => 'Off',
    NotificationSettings::MODE_ADMIN => 'Admin controlled',
];
?>
<style>
    /* ── Selected-mode readme strip ── */
    .nt-readme { border-left: 3px solid rgba(99, 102, 241, .55); }
    [data-coreui-theme="light"] .nt-readme { border-left-color: rgba(79, 70, 229, .55); }

    /* ── (i) info button (banner) ── */
    .nt-info-btn {
        width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: .5rem;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .75);
        font-size: 1.15rem;
        transition: background .15s ease, color .15s ease;
    }
    .nt-info-btn:hover { background: rgba(255, 255, 255, .16); color: #fff; }
    [data-coreui-theme="light"] .nt-info-btn {
        background: rgba(16, 24, 40, .06);
        border-color: rgba(16, 24, 40, .12);
        color: rgba(16, 24, 40, .65);
    }
    [data-coreui-theme="light"] .nt-info-btn:hover { background: rgba(16, 24, 40, .1); color: rgba(16, 24, 40, .9); }

    /* ── Full-readme popup card ── */
    .nt-doc-overlay {
        position: fixed; inset: 0; z-index: 1200;
        display: flex; align-items: center; justify-content: center;
        background: rgba(0, 0, 0, .6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0; pointer-events: none;
        transition: opacity .18s ease;
        padding: 1rem;
    }
    .nt-doc-overlay.is-open { opacity: 1; pointer-events: auto; }
    .nt-doc-card {
        width: min(680px, 94vw);
        max-height: min(80vh, 720px);
        overflow-y: auto;
        border-radius: 1.1rem;
        background: color-mix(in srgb, var(--cui-body-bg, #212631) 92%, transparent);
        border: 1px solid var(--cui-border-color, rgba(255,255,255,.12));
        box-shadow: 0 24px 60px rgba(0, 0, 0, .55);
    }
    .nt-doc-section {
        border: 1px solid var(--cui-border-color, rgba(255,255,255,.1));
        border-radius: .9rem;
        padding: .9rem 1.1rem;
        background: rgba(255, 255, 255, .03);
    }
    [data-coreui-theme="light"] .nt-doc-section { background: rgba(16, 24, 40, .03); }
    .nt-doc-section h6 { margin-bottom: .35rem; }
    .nt-doc-section p, .nt-doc-section li { color: var(--cui-secondary-color); font-size: .86rem; line-height: 1.55; }
    .nt-doc-close {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: .5rem;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .75);
        font-size: 1.05rem;
    }
    .nt-doc-close:hover { background: rgba(229, 83, 83, .18); color: #e55353; }
    [data-coreui-theme="light"] .nt-doc-close {
        background: rgba(16, 24, 40, .06); border-color: rgba(16, 24, 40, .12); color: rgba(16, 24, 40, .65);
    }

    /* ── Broadcast toggle row ── */
    .nt-toggle { cursor: pointer; user-select: none; }
    .nt-toggle input { opacity: 0; position: absolute; }
    .nt-toggle .track {
        width: 44px; height: 24px; background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18); border-radius: 12px;
        display: inline-block; vertical-align: middle; transition: background .2s ease, border-color .2s ease;
    }
    .nt-toggle .knob {
        width: 20px; height: 20px; background: #fff; border-radius: 50%;
        display: inline-block; position: relative; top: 1px; left: 2px;
        transition: transform .2s ease, background .2s ease;
    }
    .nt-toggle input:checked + .track { background: var(--cui-primary, #6366f1); border-color: var(--cui-primary, #6366f1); }
    .nt-toggle input:checked + .track .knob { transform: translateX(20px); }
    .nt-toggle-label { display: inline-block; vertical-align: middle; margin-left: .75rem; font-size: .9rem; color: var(--cui-body-color); }
    [data-coreui-theme="light"] .nt-toggle-label { color: var(--cui-body-color, #0f172a); }
</style>
<?php
// ---- Broadcast POST (admin toggle broadcast_enabled) ----
$broadcastMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthMiddleware::requireCsrf();
    $newBroadcast = isset($_POST['broadcast_enabled']) ? filter_var($_POST['broadcast_enabled'], FILTER_VALIDATE_BOOLEAN) : null;
    if ($newBroadcast !== null) {
        if (NotificationSettings::save($mode, $newBroadcast, (string)(Session::getUser()?->getEmail() ?? ''))) {
            $broadcast = $newBroadcast;
            $settings = NotificationSettings::get();
            $broadcastMsg = 'Broadcast setting saved.';
        } else {
            $broadcastMsg = 'Failed to save broadcast setting.';
        }
    }
}
?>
<!-- ============================== Banner ============================== -->
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-bell'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Notification Settings</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Global notification mode + admin broadcast control</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-<?= $mode === NotificationSettings::MODE_OFF ? 'danger' : 'success' ?>" id="ntHeaderMode"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span>
                    <button type="button" class="nt-info-btn" id="ntInfoBtn" aria-label="How notification settings work" title="How notification settings work">
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
            <!-- ── Single card: mode select + broadcast toggle ── -->
            <div class="card border-0 rounded-4 blur shadow-sm">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i class='bx bx-bell me-2'></i>Notifications</span>
                    <span class="badge rounded-pill text-bg-secondary" id="ntModeBadge"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="small text-body-secondary mb-3">
                        Controls whether users receive notifications (new-device security alerts, admin
                        broadcasts, MCP live data) site-wide. Per-user email + category toggles live in
                        Account Settings and are respected when this mode is <strong>Admin controlled</strong>.
                    </div>

                    <label class="fw-semibold d-block mb-1" for="ntMode">Notification mode</label>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <select id="ntMode" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25" style="max-width: 260px;">
                            <option value="<?= NotificationSettings::MODE_OFF ?>"    <?= $mode === NotificationSettings::MODE_OFF   ? 'selected' : '' ?>>Off</option>
                            <option value="<?= NotificationSettings::MODE_ADMIN ?>" <?= $mode === NotificationSettings::MODE_ADMIN ? 'selected' : '' ?>>Admin controlled</option>
                        </select>
                        <div class="small text-body-secondary d-flex align-items-center gap-2" id="ntModeIcon" title="Selected mode">
                            <i class='bx <?= $mode === NotificationSettings::MODE_OFF ? 'bx-ban' : 'bx-shield-check' ?> fs-5'></i>
                        </div>
                    </div>

                    <hr class="my-3 border-secondary border-opacity-10">

                    <!-- Broadcast toggle -->
                    <label class="fw-semibold d-block mb-1" for="ntBroadcast">Admin broadcast to all users</label>
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <label class="nt-toggle mb-0 d-inline-flex align-items-center" for="ntBroadcast">
                            <input type="checkbox" id="ntBroadcast" <?= $broadcast ? 'checked' : '' ?>>
                            <span class="track"><span class="knob"></span></span>
                        </label>
                        <span class="nt-toggle-label"><?= $broadcast ? 'Enabled' : 'Disabled' ?></span>
                    </div>
                    <div class="small text-body-secondary mt-1">
                        When enabled, admins can send one notification that lands in every user’s feed
                        (or a specific role / email list). When disabled, the broadcast API is rejected.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Readme strip: follows the selected mode -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 rounded-4 blur shadow-sm nt-readme">
                <div class="card-body px-4 py-3 d-flex align-items-start gap-3">
                    <i class='bx bxs-info-square text-primary mt-1'></i>
                    <div>
                        <div class="fw-semibold small mb-1">
                            How <span id="ntReadmeMode"><?= htmlspecialchars($modeLabels[$mode] ?? $mode) ?></span> works
                            <span class="text-body-secondary">(saved instantly on change)</span>
                        </div>
                        <div class="small text-body-secondary" id="ntReadmeText"><?= htmlspecialchars($modeDescriptions[$mode] ?? '') ?></div>
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
                        <span id="ntUpdated"><?= htmlspecialchars($updatedWhen) ?></span>
                        <?= $settings['updated_by'] !== '' ? ' by ' . htmlspecialchars((string)$settings['updated_by']) : '' ?>
                    </div>
                    <div class="small text-body-secondary mb-0">
                        Stored in <code>src/config/notification.json</code>
                    </div>
                    <?php if ($broadcastMsg !== ''): ?>
                    <div class="small text-<?= strpos($broadcastMsg, 'Failed') === false ? 'success' : 'danger' ?> mb-0">
                        <?= htmlspecialchars($broadcastMsg) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── "How notification settings work" popup card ── -->
<div class="nt-doc-overlay" id="ntInfoModal" role="dialog" aria-modal="true" aria-label="How notification settings work">
    <div class="nt-doc-card">
        <div class="d-flex align-items-center justify-content-between gap-3 px-4 py-3 border-bottom border-secondary border-opacity-10">
            <h5 class="fw-bold mb-0"><i class='bx bx-info-circle me-2'></i>How notification settings work</h5>
            <button type="button" class="nt-doc-close" id="ntInfoClose" aria-label="Close">✕</button>
        </div>
        <div class="p-4 d-flex flex-column gap-3">
            <div class="nt-doc-section">
                <h6 class="fw-bold">🎯 What this page controls</h6>
                <p>
                    Two global switches that gate every notification in the system:
                    <strong>notification mode</strong> and <strong>admin broadcast</strong>.
                    Changes save instantly via an audited admin API (<code>requireAdmin</code> + CSRF).
                </p>
            </div>

            <div class="nt-doc-section">
                <h6 class="fw-bold">🛡️ The two modes</h6>
                <ul class="mb-0 ps-3">
                    <li><strong>Off</strong> — notifications fully suppressed. No new-device security alerts,
                        no admin broadcasts, no MCP connect notifications. The bell still renders but nothing
                        new is ever created.</li>
                    <li><strong>Admin controlled</strong> — <em>default</em>. Admins can broadcast to all users
                        (or a role / specific emails). System, security and MCP notifications are created normally
                        per user.</li>
                </ul>
            </div>

            <div class="nt-doc-section">
                <h6 class="fw-bold">📬 Per-user controls (Account Settings)</h6>
                <p>
                    Each user controls their own <strong>email</strong> master switch,
                    <strong>security alerts</strong> (new-device sign-in — on by default),
                    <strong>MCP live data</strong>, and per-category toggles in Account Settings.
                    These are respected only when the global mode is <em>Admin controlled</em>.
                </p>
            </div>

            <div class="nt-doc-section">
                <h6 class="fw-bold">📢 Admin broadcast</h6>
                <p class="mb-1">
                    When broadcast is enabled, an admin can send one notification that is delivered
                    to <strong>every user</strong> (or a target role / email list). Each recipient gets
                    their own copy and can mark it read or delete it independently.
                </p>
                <p class="mb-0">
                    Broadcast goes through <code>/api/admin/notifications_broadcast</code> and is audited.
                </p>
            </div>

            <div class="nt-doc-section">
                <h6 class="fw-bold">📦 Where the setting is stored</h6>
                <p class="mb-0">
                    A plain JSON file: <code>src/config/notification.json</code> — not the database.
                    It is written atomically (temp file + rename), is human-readable, survives DB issues,
                    and falls back safely to <strong>Admin controlled</strong> if the file is missing or corrupt.
                </p>
            </div>

            <div class="nt-doc-section">
                <h6 class="fw-bold">✨ Triggers</h6>
                <p class="mb-0">
                    Notifications are created for: <strong>new device login</strong> (security alert,
                    emailed if the user has security + email on), <strong>admin broadcast</strong>, and
                    <strong>MCP user connect</strong> (live data). Labs features can also emit
                    notifications via the <code>Notification::create()</code> helper.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var current = <?= json_encode($mode) ?>;
    var currentBroadcast = <?= json_encode($broadcast) ?>;

    var modeLabels   = <?= json_encode($modeLabels) ?>;
    var modeDescriptions = <?= json_encode($modeDescriptions) ?>;

    var select   = document.getElementById('ntMode');
    var toggle   = document.getElementById('ntBroadcast');
    var toggleLabel = document.querySelector('.nt-toggle-label');

    function setMode(mode) {
        if (mode === current) return;
        var fd = new URLSearchParams();
        fd.append('notification_mode', mode);
        fd.append('broadcast_enabled', currentBroadcast ? 'true' : 'false');
        fetch('/api/admin/notifications_mode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    current = mode;
                    if (window.TomNotify) TomNotify.show('Notification settings saved', 'Success', 'success', 3000);
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

    function setBroadcast(val) {
        var fd = new URLSearchParams();
        fd.append('notification_mode', current);
        fd.append('broadcast_enabled', val ? 'true' : 'false');
        fetch('/api/admin/notifications_mode', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    currentBroadcast = val;
                    if (window.TomNotify) TomNotify.show('Broadcast setting saved', 'Success', 'success', 3000);
                    refreshView();
                } else if (window.TomNotify) {
                    TomNotify.show(data.error || 'Save failed', 'Error', 'error', 4000);
                    toggle.checked = currentBroadcast; // revert on failure
                    refreshToggleLabel();
                }
            })
            .catch(function () {
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
                toggle.checked = currentBroadcast; // revert on failure
                refreshToggleLabel();
            });
    }

    function refreshView() {
        var label = modeLabels[current] || current;
        var text = modeDescriptions[current] || '';
        var icon = current === 'off' ? 'bx-ban' : 'bx-shield-check';

        document.getElementById('ntHeaderMode').textContent = label;
        document.getElementById('ntHeaderMode').className = 'badge rounded-pill text-bg-' + (current === 'off' ? 'danger' : 'success');
        document.getElementById('ntModeBadge').textContent = label;
        document.getElementById('ntModeIcon').innerHTML = '<i class="bx ' + icon + ' fs-5"></i>';
        document.getElementById('ntReadmeMode').textContent = label;
        document.getElementById('ntReadmeText').textContent = text;
    }

    function refreshToggleLabel() {
        if (toggleLabel) toggleLabel.textContent = currentBroadcast ? 'Enabled' : 'Disabled';
    }

    if (select) select.addEventListener('change', function () { setMode(select.value); });
    if (toggle) toggle.addEventListener('change', function () { setBroadcast(toggle.checked); });

    // (i) info popup
    var infoBtn   = document.getElementById('ntInfoBtn');
    var infoModal = document.getElementById('ntInfoModal');
    var infoClose = document.getElementById('ntInfoClose');

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
