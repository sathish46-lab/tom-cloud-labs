<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/lib/core/Appearance.class.php';

$appearance = Appearance::get();
$modes      = Appearance::modes();
$disabled   = $appearance['disabled_modes'];
$isLocked   = $appearance['force_mode'] !== '';
$publicBgMode = (string)$appearance['public_bg_mode'];
$publicBgLabel = $publicBgMode === ''
    ? 'Site default'
    : ($publicBgMode === Appearance::PUBLIC_OWNER ? "Owner's choice" : Appearance::label($publicBgMode));

$thumbs = [
    'robo'      => '/assets/Background_Img/robo/robo.jpg',
    'ninja'     => '/assets/Background_Img/ninja/ninja.jpg',
    'robotower' => '/assets/Background_Img/RoboTower/robo_tower.jpg',
    'spiderman' => '/assets/Background_Img/spiderman/spiderman.jpg',
    'ironman'   => '/assets/Background_Img/IronMan/0.jpg',
];

$colorChoices = [
    ''      => 'Users choose',
    'dark'  => 'Dark',
    'light' => 'Light',
];

$updatedWhen = ((int)$appearance['updated_at']) > 0
    ? date('d M Y H:i', (int)$appearance['updated_at'])
    : 'never';
?>
<style>
.ap-thumb { width: 54px; height: 34px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,.15); }
.ap-thumb.plain { background: linear-gradient(45deg, #010d12, #0b1e36); }
.ap-mode-row:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.05); }
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
                                <i class='bx bx-palette'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Appearance</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Default background, locked wallpapers and color mode for every user</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a> -->
                    <span class="badge rounded-pill text-bg-<?= $isLocked ? 'warning' : 'success' ?>">
                        Background <?= $isLocked ? 'locked' : 'unlocked' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-4">
    <div class="row g-4">
        <!-- ── Main settings ── -->
        <div class="col-lg-7">
            <div class="card border-0 rounded-4 blur shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                    <span class="fw-bold"><i class='bx bx-image me-2'></i>Background</span>
                </div>
                <div class="card-body p-4">
                    <label class="fw-semibold d-block mb-1" for="apDefaultMode">Default background</label>
                    <div class="small text-body-secondary mb-2">Used by users who have not picked a background yet, and replaces any background you hide below.</div>
                    <select id="apDefaultMode" class="form-select form-select-lg bg-transparent border-secondary border-opacity-25">
                        <?php foreach ($modes as $mode): ?>
                        <option value="<?= htmlspecialchars($mode) ?>" <?= $appearance['default_mode'] === $mode ? 'selected' : '' ?>>
                            <?= htmlspecialchars(Appearance::label($mode)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <hr class="my-4 border-secondary border-opacity-10">

                    <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                        <div>
                            <label class="fw-semibold mb-0" for="apLockBg">Lock the background for everyone</label>
                            <div class="small text-body-secondary">Every user gets the same wallpaper and the picker is disabled.</div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="apLockBg" <?= $isLocked ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <select id="apForceMode" class="form-select bg-transparent border-secondary border-opacity-25 mt-2" <?= $isLocked ? '' : 'disabled' ?>>
                        <?php foreach ($modes as $mode): ?>
                        <option value="<?= htmlspecialchars($mode) ?>" <?= $appearance['force_mode'] === $mode ? 'selected' : '' ?>>
                            <?= htmlspecialchars(Appearance::label($mode)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <hr class="my-4 border-secondary border-opacity-10">

                    <label class="fw-semibold d-block mb-1" for="apColorMode">Color mode</label>
                    <div class="small text-body-secondary mb-2">Force light or dark across the app, or leave the choice to each user.</div>
                    <select id="apColorMode" class="form-select bg-transparent border-secondary border-opacity-25">
                        <?php foreach ($colorChoices as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $appearance['force_color_mode'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ── Public profile background ── -->
            <div class="card border-0 rounded-4 blur shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <span class="fw-bold"><i class='bx bx-globe me-2'></i>Public profile background</span>
                    <span class="badge rounded-pill text-bg-secondary" id="apPublicBgBadge"><?= htmlspecialchars($publicBgLabel) ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="small text-body-secondary mb-3">
                        What signed-out visitors see on public profile pages (example.com/account, /your-handle).
                        Locking the background for everyone still wins over this choice.
                    </div>

                    <label class="fw-semibold d-block mb-1" for="apPublicBg">Wallpaper for public profiles</label>
                    <select id="apPublicBg" class="form-select bg-transparent border-secondary border-opacity-25">
                        <option value="" <?= $publicBgMode === '' ? 'selected' : '' ?>>Same as the site default background</option>
                        <option value="<?= Appearance::PUBLIC_OWNER ?>" <?= $publicBgMode === Appearance::PUBLIC_OWNER ? 'selected' : '' ?>>
                            Profile owner's own background
                        </option>
                        <optgroup label="Always use">
                        <?php foreach ($modes as $mode): ?>
                        <option value="<?= htmlspecialchars($mode) ?>" <?= $publicBgMode === $mode ? 'selected' : '' ?>>
                            <?= htmlspecialchars(Appearance::label($mode)) ?>
                        </option>
                        <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <div class="small text-body-secondary mt-2" id="apPublicBgHint"></div>
                </div>
            </div>

        </div>

        <!-- ── Hidden backgrounds ── -->
        <div class="col-lg-5">
            <div class="card border-0 rounded-4 blur shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                    <span class="fw-bold"><i class='bx bx-hide me-2'></i>Hidden backgrounds</span>
                </div>
                <div class="card-body p-3">
                    <div class="small text-body-secondary mb-3 px-1">
                        Hidden themes disappear from every picker (header dropdown and Change background dialog).
                        The default and the locked background always stay available.
                    </div>
                    <?php foreach ($modes as $mode): ?>
                    <?php
                        $isDefault = $appearance['default_mode'] === $mode;
                        $isForced  = $appearance['force_mode'] === $mode && $appearance['force_mode'] !== '';
                        $lockedRow = $isDefault || $isForced;
                        $isChecked = in_array($mode, $disabled, true) && !$lockedRow;
                    ?>
                    <div class="d-flex align-items-center gap-3 py-2 ap-mode-row rounded-3 px-2">
                        <?php if ($mode === 'plain'): ?>
                            <div class="ap-thumb plain flex-shrink-0"></div>
                        <?php else: ?>
                            <img class="ap-thumb flex-shrink-0" src="<?= htmlspecialchars($thumbs[$mode] ?? '') ?>" alt="">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small mb-0"><?= htmlspecialchars(Appearance::label($mode)) ?></div>
                            <div class="small text-body-secondary" style="font-size:.72rem;">
                                <?= $isDefault ? 'Default background' : ($isForced ? 'Locked background' : 'Available to users') ?>
                            </div>
                        </div>
                        <div class="form-check mb-0 flex-shrink-0">
                            <input class="form-check-input" type="checkbox" name="apDisabled" value="<?= htmlspecialchars($mode) ?>"
                                id="apDis<?= htmlspecialchars($mode) ?>" <?= $isChecked ? 'checked' : '' ?> <?= $lockedRow ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
                        <span id="apUpdated"><?= htmlspecialchars($updatedWhen) ?></span>
                        <?= $appearance['updated_by'] !== '' ? ' by ' . htmlspecialchars((string)$appearance['updated_by']) : '' ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3" id="apResetBtn">
                            <i class='bx bx-reset me-1'></i>Reset to defaults
                        </button>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="apSaveBtn">
                            <i class='bx bx-save me-1'></i>Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function payload() {
        var fd = new URLSearchParams();
        fd.append('default_mode', document.getElementById('apDefaultMode').value);
        var lock = document.getElementById('apLockBg').checked;
        if (lock) {
            fd.append('lock_bg', '1');
            fd.append('force_mode', document.getElementById('apForceMode').value);
        }
        fd.append('force_color_mode', document.getElementById('apColorMode').value);
        fd.append('public_bg_mode', document.getElementById('apPublicBg').value);
        document.querySelectorAll('input[name="apDisabled"]:checked').forEach(function (cb) {
            fd.append('disabled_modes[]', cb.value);
        });
        return fd;
    }

    function updatePublicHint() {
        var sel = document.getElementById('apPublicBg');
        var el = document.getElementById('apPublicBgHint');
        var badge = document.getElementById('apPublicBgBadge');
        if (!sel || !el) return;
        var v = sel.value;
        var label = sel.options[sel.selectedIndex].text.replace(/^\s+|\s+$/g, '');
        var text;
        if (v === 'owner') {
            text = 'Each public profile shows the background its owner saved.';
        } else if (v === '') {
            text = 'Public profiles follow the site default background.';
        } else {
            text = 'Every public profile shows: ' + label + '.';
        }
        el.textContent = text;
        if (badge) {
            badge.textContent = v === '' ? 'Site default' : (v === 'owner' ? "Owner's choice" : label);
        }
    }

    function send(fd, okMsg) {
        fetch('/api/admin/appearance_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    if (window.TomNotify) TomNotify.show(okMsg, 'Success', 'success', 3000);
                    var el = document.getElementById('apUpdated');
                    if (el) el.textContent = new Date().toLocaleString();
                } else if (window.TomNotify) {
                    TomNotify.show(data.error || 'Save failed', 'Error', 'error', 4000);
                }
            })
            .catch(function () {
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
    }

    var lock = document.getElementById('apLockBg');
    lock.addEventListener('change', function () {
        document.getElementById('apForceMode').disabled = !lock.checked;
    });

    var publicBg = document.getElementById('apPublicBg');
    publicBg.addEventListener('change', updatePublicHint);
    updatePublicHint();

    document.getElementById('apSaveBtn').addEventListener('click', function () {
        send(payload(), 'Appearance settings saved');
    });

    document.getElementById('apResetBtn').addEventListener('click', function () {
        var fd = new URLSearchParams();
        fd.append('reset', '1');
        send(fd, 'Appearance settings reset to defaults');
    });
})();
</script>
