<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/lib/core/Appearance.class.php';
require_once __DIR__ . '/../../../../src/lib/core/SiteFont.class.php';
require_once __DIR__ . '/../../../../src/lib/core/SiteBrand.class.php';
require_once __DIR__ . '/../../../../src/lib/core/SiteSeo.class.php';

$seoStored = SiteSeo::stored();
$seoEffective = SiteSeo::defaults();

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

$faviconFile = __DIR__ . '/../../../../assets/logo/favicon.png';
$faviconV    = file_exists($faviconFile) ? filemtime($faviconFile) : '1';
$faviconBak  = __DIR__ . '/../../../../assets/logo/favicon.png.bak';
$faviconBakV = file_exists($faviconBak) ? filemtime($faviconBak) : '0';
$logoFile    = __DIR__ . '/../../../../assets/logo/logo.png';
$logoV       = file_exists($logoFile) ? filemtime($logoFile) : '1';
$logoBak     = __DIR__ . '/../../../../assets/logo/logo.png.bak';
$logoBakV    = file_exists($logoBak) ? filemtime($logoBak) : '0';

function brand_tile(string $badge, bool $isCur, int $v, string $urlPath, string $type): string {
    // Same glass card look as the page's other cards (card border-0 rounded-4 blur shadow-sm)
    $border = $isCur
        ? 'border:1px solid rgba(46,204,113,.35);'
        : 'border:1px solid rgba(255,255,255,.1);';
    $badgeStyle = $isCur
        ? 'background:rgba(46,204,113,.15);color:#2ecc71;'
        : 'background:rgba(255,255,255,.08);color:rgba(255,255,255,.55);';

    $badgeHtml = '<span style="display:inline-block;border-radius:50rem;' . $badgeStyle
        . 'font-size:.58rem;letter-spacing:.4px;padding:.2rem .55rem;margin-bottom:6px;">' . $badge . '</span>';

    // BAK tiles with a real file are click-to-select
    $selectable = !$isCur && $v > 0;
    $pickAttr = $selectable ? ' data-pick="' . $type . '"' : '';
    $cursor   = $selectable ? 'cursor:pointer;' : '';
    $cardCls  = 'card border-0 rounded-4 blur shadow-sm p-2 text-center h-100 brand-tile';

    if ($v <= 0) {
        return '
    <div class="col-6">
        <div class="' . $cardCls . '" style="' . $border . $cursor . '"' . $pickAttr . '>' . $badgeHtml . '
            <div style="height:44px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:rgba(255,255,255,.04);">
                <i class="bx bx-image-alt" style="font-size:1.2rem;opacity:.35;"></i>
            </div>
            <div style="font-size:.62rem;color:rgba(255,255,255,.45);margin-top:6px;">not present</div>
        </div>
    </div>';
    }

    return '
    <div class="col-6">
        <div class="' . $cardCls . '" style="' . $border . $cursor . '"' . $pickAttr . '>' . $badgeHtml . '
            <img src="' . htmlspecialchars($urlPath . '?v=' . $v) . '" alt="' . $badge . '" data-brand-tile="' . htmlspecialchars($urlPath) . '"
                 style="width:44px;height:44px;object-fit:contain;border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.2);margin:0 auto;">
            <div style="font-size:.62rem;color:rgba(255,255,255,.45);margin-top:6px;" data-brand-date="' . htmlspecialchars($urlPath) . '">' . htmlspecialchars(date('d M Y H:i', $v)) . '</div>
        </div>
    </div>';
}
?>
<style>
.ap-thumb { width: 54px; height: 34px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,.15); }
.ap-thumb.plain { background: linear-gradient(45deg, #010d12, #0b1e36); }
.ap-mode-row:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.05); }
.brand-tile { transition: border-color .15s, box-shadow .15s; }
.brand-tile[data-pick]:hover { border-color: rgba(255,255,255,.3) !important; }
.brand-tile.selected {
    border-color: #8b5cf6 !important;
    box-shadow: 0 0 0 2px rgba(139,92,246,.4) !important;
}

/* ── Reference stepped cards (test.php): wide head → shelf → concave step → body ── */
.ap-curve { position: relative; }
.ap-curve-edge {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 4;
    overflow: visible;
}
.ap-curve-edge path {
    fill: none;
    stroke: rgba(255,255,255,.16);
    stroke-width: 2;
    stroke-linejoin: round;
}
[data-coreui-theme=light] .ap-curve-edge path { stroke: rgba(0,0,0,.22); }
/* ── Interlocking blocks (ap-interlock layout): notch column fills the partner's step ── */
:root { --ap-g: 24px; --ap-b: 72px; }
.block { display: grid; gap: var(--ap-g); margin-bottom: var(--ap-g); }
@media (min-width: 992px) {
    .b1 { grid-template-columns: minmax(0,1.5fr) var(--ap-b) minmax(0,1fr); }
    .b1 .c-wide  { grid-column: 1 / 3; grid-row: 1; }
    .b1 .c-tall  { grid-column: 2 / 4; grid-row: 1 / 3; }
    .b1 .c-plain { grid-column: 1; grid-row: 2; }

    .b2 { grid-template-columns: minmax(0,1fr) var(--ap-b) minmax(0,1.5fr); }
    .b2 .c-wide  { grid-column: 2 / 4; grid-row: 1; }
    .b2 .c-tall  { grid-column: 1 / 3; grid-row: 1 / 3; }
    .b2 .c-plain { grid-column: 3; grid-row: 2; }

    /* keep content clear of the notch */
    .n-br > .card-header, .n-br > .card-body { padding-right: calc(1.5rem + var(--ap-b) + var(--ap-g)) !important; }
    .n-bl > .card-header, .n-bl > .card-body { padding-left:  calc(1.5rem + var(--ap-b) + var(--ap-g)) !important; }
    .n-tl > .card-header { padding-left:  calc(1.5rem + var(--ap-b) + var(--ap-g)) !important; display: flex; align-items: center; }
    .n-tr > .card-header { padding-right: calc(1.5rem + var(--ap-b) + var(--ap-g)) !important; display: flex; align-items: center; }
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
    <div class="block b1">
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-wide n-br" id="cBg" data-notch="br">
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

                    <hr class="my-4 border-secondary border-opacity-10">

                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <label class="fw-semibold mb-0" for="apVisualBlurDefault">Visual blur</label>
                            <div class="small text-body-secondary">Default for users with no saved preference (first login). Users can still change it in their settings.</div>
                        </div>
                        <div class="form-check form-switch mb-0 flex-shrink-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="apVisualBlurDefault" <?= $appearance['visual_blur_default'] !== '0' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-tall n-tl" data-notch="tl" data-pair="cBg">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
                    <span class="fw-bold"><i class='bx bx-icon me-2'></i>Brand images</span>
                    <button type="button" class="btn btn-sm rounded-pill fw-semibold px-3" id="apBrandListBtn"
                            style="border:1px solid rgba(255,255,255,.2);color:#cbd5e1;">
                        <i class='bx bx-list-ul me-1'></i>List
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img id="apFaviconPreview" src="/assets/logo/favicon.png?v=<?= htmlspecialchars($faviconV) ?>"
                             alt="Current favicon" width="48" height="48"
                             style="border-radius:10px;border:1px solid rgba(255,255,255,.15);background:#0b1e36;object-fit:contain;">
                        <div class="small text-body-secondary">
                            <b>Favicon</b> — browser tab icon, site-wide.
                            Replaces <code>assets/logo/favicon.png</code> directly (no database).
                        </div>
                    </div>
                    <input type="file" class="form-control bg-transparent border-secondary border-opacity-25 mb-2"
                           id="apFaviconInput" accept="image/png">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="apFaviconBtn">
                        <i class='bx bx-upload me-1'></i>Upload favicon
                    </button>

                    <div class="d-none mt-3 mb-4" id="favTileRow">
                        <div class="small text-body-secondary mb-2" style="font-size:.68rem;">
                            <i class='bx bx-pointer me-1'></i>Select an image, then Save
                        </div>
                        <div class="row g-2">
                            <?= brand_tile('CUR', true, (int)$faviconV, '/assets/logo/favicon.png', 'favicon') ?>
                            <?= brand_tile('BAK', false, (int)$faviconBakV, '/assets/logo/favicon.png.bak', 'favicon') ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold mt-2 d-none apBrandSave"
                                data-type="favicon">
                            <i class='bx bx-save me-1'></i>Save
                        </button>
                    </div>

                    <hr class="my-4 border-secondary border-opacity-10">

                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img id="apLogoPreview" src="/assets/logo/logo.png?v=<?= htmlspecialchars($logoV) ?>"
                             alt="Current logo" width="48" height="48"
                             style="border-radius:10px;border:1px solid rgba(255,255,255,.15);background:#0b1e36;object-fit:contain;">
                        <div class="small text-body-secondary">
                            <b>Site logo</b> — sidebar header, sign-in pages, AI avatars.
                            Replaces <code>assets/logo/logo.png</code> directly (no database).
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <input type="file" class="form-control bg-transparent border-secondary border-opacity-25 flex-grow-1"
                               id="apLogoInput" accept="image/png">
                        <button type="button" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                id="apLogoInlineEdit" title="Quick edit — pick a PNG to replace the sidebar logo"
                                style="width:34px;height:34px;">
                            <i class='bx bx-pencil'></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="apLogoBtn">
                        <i class='bx bx-upload me-1'></i>Upload logo
                    </button>

                    <div class="d-none mt-3" id="logoTileRow">
                        <div class="small text-body-secondary mb-2" style="font-size:.68rem;">
                            <i class='bx bx-pointer me-1'></i>Select an image, then Save
                        </div>
                        <div class="row g-2">
                            <?= brand_tile('CUR', true, (int)$logoV, '/assets/logo/logo.png', 'logo') ?>
                            <?= brand_tile('BAK', false, (int)$logoBakV, '/assets/logo/logo.png.bak', 'logo') ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold mt-2 d-none apBrandSave"
                                data-type="logo">
                            <i class='bx bx-save me-1'></i>Save
                        </button>
                    </div>

                    <hr class="my-4 border-secondary border-opacity-10">

                    <label class="fw-semibold d-block mb-1" for="apSiteName">Site name</label>
                    <div class="small text-body-secondary mb-2">
                        Shown beside the logo in the sidebar header. Saved to a config file (no database).
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="apSiteName" maxlength="30" value="<?= htmlspecialchars(Session::siteName()) ?>"
                               class="form-control bg-transparent border-secondary border-opacity-25">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold flex-shrink-0" id="apSiteNameSave">
                            <i class='bx bx-save me-1'></i>Save
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Public profile background ── -->
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-plain" data-notch="none">
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

    <div class="block b2">
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-wide n-bl" id="cFont" data-notch="bl">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
                    <span class="fw-bold"><i class='bx bx-font-family me-2'></i>Site font</span>
                    <span class="badge rounded-pill text-bg-secondary" id="apFontBadge"><?= htmlspecialchars(SiteFont::family()) ?></span>
                </div>
                <div class="card-body p-4">
                    <label class="fw-semibold d-block mb-1" for="apFontFamily">Body font for every page</label>
                    <div class="small text-body-secondary mb-2">Change any time — applies on the next page load. Saved to a config file (no database).</div>
                    <select id="apFontFamily" class="form-select bg-transparent border-secondary border-opacity-25">
                        <?php foreach (SiteFont::CURATED_GROUPS as $groupName => $groupFonts): ?>
                        <optgroup label="<?= htmlspecialchars($groupName) ?>">
                            <?php foreach ($groupFonts as $fontChoice): ?>
                            <option value="<?= htmlspecialchars($fontChoice) ?>" <?= SiteFont::family() === $fontChoice ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fontChoice) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                        <?php $isCustomFont = !in_array(SiteFont::family(), SiteFont::curated(), true); ?>
                        <optgroup label="Other">
                            <option value="__custom" <?= $isCustomFont ? 'selected' : '' ?>>Custom… (any Google font)</option>
                        </optgroup>
                    </select>
                    <input type="text" id="apFontCustom" maxlength="40"
                           class="form-control bg-transparent border-secondary border-opacity-25 mt-2 <?= $isCustomFont ? '' : 'd-none' ?>"
                           placeholder="Any Google Fonts family name" value="<?= $isCustomFont ? htmlspecialchars(SiteFont::family()) : '' ?>">
                    <div id="apFontPreview" class="rounded-3 border border-secondary border-opacity-10 p-3 mt-3 fw-semibold fs-5">
                        The quick brown fox jumps over the lazy dog — <?= htmlspecialchars(Session::siteName()) ?> 0123456789
                    </div>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 mt-3" id="apFontSave">
                        <i class='bx bx-save me-1'></i>Save font
                    </button>
                </div>
            </div>
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-tall n-tr" data-notch="tr" data-pair="cFont">
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
<div class="card border-0 rounded-4 blur shadow-sm ap-curve c-plain" data-notch="none">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
                    <span class="fw-bold"><i class='bx bx-search-alt me-2'></i>SEO</span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm rounded-pill fw-semibold px-3" id="apSeoReset"
                                style="border:1px solid rgba(255,255,255,.2);color:#cbd5e1;">
                            <i class='bx bx-reset me-1'></i>Reset
                        </button>
                        <span class="badge rounded-pill text-bg-secondary" id="apSeoBadge"><?= ($seoStored['title'] !== '' || $seoStored['description'] !== '' || $seoStored['keywords'] !== '' || $seoStored['author'] !== '') ? 'custom' : 'defaults' ?></span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="small text-body-secondary mb-3">
                        Site-wide meta tags for Google and social shares (title, description, keywords).
                        Saved to a config file (no database). Pages can still set their own title or description.
                        Use <code>{name}</code> in any field to insert the current site name.
                    </div>

                    <label class="fw-semibold d-block mb-1" for="apSeoTitle">Default page title</label>
                    <div class="small text-body-secondary mb-2">Used when a page does not set its own title.</div>
                    <input type="text" id="apSeoTitle" maxlength="<?= SiteSeo::MAX_TITLE ?>"
                           class="form-control bg-transparent border-secondary border-opacity-25 mb-3"
                           placeholder="<?= htmlspecialchars(SiteSeo::DEFAULT_TITLE) ?>"
                           value="<?= htmlspecialchars($seoStored['title']) ?>">

                    <label class="fw-semibold d-block mb-1" for="apSeoDesc">Meta description</label>
                    <div class="small text-body-secondary mb-2">Shown under the title in search results.</div>
                    <textarea id="apSeoDesc" rows="3" maxlength="<?= SiteSeo::MAX_DESC ?>"
                              class="form-control bg-transparent border-secondary border-opacity-25 mb-3"
                              placeholder="<?= htmlspecialchars(SiteSeo::DEFAULT_DESC) ?>"><?= htmlspecialchars($seoStored['description']) ?></textarea>

                    <label class="fw-semibold d-block mb-1" for="apSeoKeys">Meta keywords</label>
                    <div class="small text-body-secondary mb-2">Comma-separated list.</div>
                    <input type="text" id="apSeoKeys" maxlength="<?= SiteSeo::MAX_KEYWORDS ?>"
                           class="form-control bg-transparent border-secondary border-opacity-25 mb-3"
                           placeholder="<?= htmlspecialchars(SiteSeo::DEFAULT_KEYWORDS) ?>"
                           value="<?= htmlspecialchars($seoStored['keywords']) ?>">

                    <label class="fw-semibold d-block mb-1" for="apSeoAuthor">Author</label>
                    <div class="small text-body-secondary mb-2">Shown in the meta author tag and JSON-LD structured data.</div>
                    <input type="text" id="apSeoAuthor" maxlength="<?= SiteSeo::MAX_AUTHOR ?>"
                           class="form-control bg-transparent border-secondary border-opacity-25 mb-3"
                           placeholder="<?= htmlspecialchars(SiteSeo::DEFAULT_AUTHOR) ?>"
                           value="<?= htmlspecialchars($seoStored['author']) ?>">

                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold" id="apSeoSave">
                        <i class='bx bx-save me-1'></i>Save SEO
                    </button>
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
        fd.append('visual_blur_default', document.getElementById('apVisualBlurDefault').checked ? '1' : '0');
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

    // ── Brand image uploads (file overwrite, no DB) ──
    function wireBrandUpload(btnId, inputId, previewId, field) {
        var btn = document.getElementById(btnId);
        var input = document.getElementById(inputId);
        var preview = document.getElementById(previewId);
        btn.addEventListener('click', function () {
            var f = input.files && input.files[0];
            if (!f) {
                if (window.TomNotify) TomNotify.show('Choose a PNG file first', 'Error', 'error', 3000);
                return;
            }
            var fd = new FormData();
            fd.append(field, f);
            btn.disabled = true;
            fetch('/api/admin/favicon_upload', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    if (data.status === 'success') {
                        var tilePath = data.url.split('?')[0];
                        preview.src = data.url + '&t=' + Date.now();
                        // Keep the inline CUR tile in sync without a reload
                        document.querySelectorAll('img[data-brand-tile="' + tilePath + '"]')
                            .forEach(function (img) { img.src = data.url + '&t=' + Date.now(); });
                        if (data.cur_date) {
                            document.querySelectorAll('[data-brand-date="' + tilePath + '"]')
                                .forEach(function (el) { el.textContent = data.cur_date; });
                        }
                        input.value = '';
                        if (window.TomNotify) TomNotify.show('Updated site-wide', 'Success', 'success', 3000);
                    } else if (window.TomNotify) {
                        TomNotify.show(data.error || 'Upload failed', 'Error', 'error', 4000);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
                });
        });
    }
    wireBrandUpload('apFaviconBtn', 'apFaviconInput', 'apFaviconPreview', 'favicon');
    wireBrandUpload('apLogoBtn', 'apLogoInput', 'apLogoPreview', 'logo');

    // ── Site logo quick-edit: pencil right of Choose file → pick → auto upload ──
    (function () {
        var pencil = document.getElementById('apLogoInlineEdit');
        var input = document.getElementById('apLogoInput');
        if (!pencil || !input) return;
        var inlinePick = false;

        // A direct click on "Choose file" keeps the normal two-step flow
        input.addEventListener('click', function (e) {
            if (e.isTrusted) inlinePick = false;
        });

        pencil.addEventListener('click', function () {
            inlinePick = true;
            input.click();
        });

        input.addEventListener('change', function () {
            var auto = inlinePick;
            inlinePick = false;
            var f = input.files && input.files[0];
            if (!auto || !f) return;
            var fd = new FormData();
            fd.append('logo', f);
            pencil.disabled = true;
            fetch('/api/admin/favicon_upload', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    pencil.disabled = false;
                    if (data.status !== 'success') {
                        if (window.TomNotify) TomNotify.show(data.error || 'Upload failed', 'Error', 'error', 4000);
                        return;
                    }
                    // Refresh every logo.png shown on this page (sidebar, preview, tiles) — not .bak
                    var u = data.url + '&t=' + Date.now();
                    document.querySelectorAll('img[src*="/assets/logo/logo.png"]').forEach(function (img) {
                        if (img.src.indexOf('logo.png.bak') !== -1) return;
                        img.src = u;
                    });
                    document.querySelectorAll('[data-brand-date="/assets/logo/logo.png"]').forEach(function (el) {
                        if (data.cur_date) el.textContent = data.cur_date;
                    });
                    input.value = '';
                    if (window.TomNotify) TomNotify.show('Sidebar logo updated', 'Success', 'success', 3000);
                })
                .catch(function () {
                    pencil.disabled = false;
                    if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
                });
        });
    })();

    // ── Inline brand picker: List toggles tile rows, pick BAK, Save swaps ──
    var listBtn = document.getElementById('apBrandListBtn');
    var tileRows = [document.getElementById('favTileRow'), document.getElementById('logoTileRow')].filter(Boolean);

    function clearRow(row) {
        row.querySelectorAll('.brand-tile.selected').forEach(function (t) { t.classList.remove('selected'); });
        var save = row.querySelector('.apBrandSave');
        if (save) save.classList.add('d-none');
    }

    if (listBtn) {
        listBtn.addEventListener('click', function () {
            tileRows.forEach(function (row) {
                row.classList.toggle('d-none');
                if (row.classList.contains('d-none')) clearRow(row);
            });
        });
    }

    // Click a tile: BAK (data-pick) selects it and reveals Save; CUR or re-click clears
    document.addEventListener('click', function (e) {
        var tile = e.target.closest('.brand-tile');
        if (!tile) return;
        var row = tile.closest('#favTileRow, #logoTileRow');
        if (!row) return;
        var save = row.querySelector('.apBrandSave');
        var isPickable = tile.hasAttribute('data-pick');
        var wasSelected = tile.classList.contains('selected');
        row.querySelectorAll('.brand-tile.selected').forEach(function (t) { t.classList.remove('selected'); });
        if (isPickable && !wasSelected) {
            tile.classList.add('selected');
            if (save) save.classList.remove('d-none');
        } else if (save) {
            save.classList.add('d-none');
        }
    });

    // Save selected backup → current (file swap via restore=1, no new endpoint)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.apBrandSave');
        if (!btn) return;
        var type = btn.getAttribute('data-type');
        var row = btn.closest('#favTileRow, #logoTileRow');
        var file = type === 'logo' ? 'logo.png' : 'favicon.png';
        var fd = new FormData();
        fd.append('restore', '1');
        fd.append('type', type);
        btn.disabled = true;
        fetch('/api/admin/favicon_upload', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
            body: fd
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.status !== 'success') {
                    if (window.TomNotify) TomNotify.show(data.error || 'Restore failed', 'Error', 'error', 4000);
                    return;
                }
                var t = Date.now();
                var curPath = '/assets/logo/' + file;
                var bakPath = curPath + '.bak';
                var curImg = document.querySelector('img[data-brand-tile="' + curPath + '"]');
                var bakImg = document.querySelector('img[data-brand-tile="' + bakPath + '"]');
                if (curImg) curImg.src = data.url + '&t=' + t;
                if (bakImg) bakImg.src = data.bak_url + '&t=' + t;
                var curDate = document.querySelector('[data-brand-date="' + curPath + '"]');
                var bakDate = document.querySelector('[data-brand-date="' + bakPath + '"]');
                if (curDate && data.cur_date) curDate.textContent = data.cur_date;
                if (bakDate && data.bak_date) bakDate.textContent = data.bak_date;
                var prev = document.getElementById(type === 'logo' ? 'apLogoPreview' : 'apFaviconPreview');
                if (prev) prev.src = data.url + '&t=' + t;
                if (row) clearRow(row);
                if (window.TomNotify) TomNotify.show('Backup restored as current', 'Success', 'success', 3000);
            })
            .catch(function () {
                btn.disabled = false;
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
    });
})();

// ── Site name (file-based, no DB): /api/admin/site_name_save ──
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var input = document.getElementById('apSiteName');
    var btn = document.getElementById('apSiteNameSave');
    if (!input || !btn) return;

    btn.addEventListener('click', function () {
        var name = (input.value || '').trim();
        if (!name) {
            if (window.TomNotify) TomNotify.show('Enter a site name', 'Error', 'error', 3000);
            return;
        }
        btn.disabled = true;
        var fd = new URLSearchParams();
        fd.append('name', name);
        fetch('/api/admin/site_name_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.status !== 'success') {
                    if (window.TomNotify) TomNotify.show(data.error || 'Save failed', 'Error', 'error', 4000);
                    return;
                }
                // Live-update every site-name spot in the sidebar without a reload
                document.querySelectorAll('[data-site-name]').forEach(function (el) { el.textContent = data.name; });
                input.value = data.name;
                if (window.TomNotify) TomNotify.show('Site name updated', 'Success', 'success', 3000);
            })
            .catch(function () {
                btn.disabled = false;
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
    });
})();

// ── SEO defaults (file-based, no DB): /api/admin/site_seo_save ──
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var btn = document.getElementById('apSeoSave');
    var resetBtn = document.getElementById('apSeoReset');
    if (!btn || !resetBtn) return;

    function postSeo(fields, onOk) {
        btn.disabled = true;
        resetBtn.disabled = true;
        var fd = new URLSearchParams();
        fd.append('title', fields.title || '');
        fd.append('description', fields.description || '');
        fd.append('keywords', fields.keywords || '');
        fd.append('author', fields.author || '');
        fetch('/api/admin/site_seo_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                resetBtn.disabled = false;
                if (data.status !== 'success') {
                    if (window.TomNotify) TomNotify.show(data.error || 'Save failed', 'Error', 'error', 4000);
                    return;
                }
                var badge = document.getElementById('apSeoBadge');
                if (badge) badge.textContent = data.default ? 'defaults' : 'custom';
                onOk(data);
            })
            .catch(function () {
                btn.disabled = false;
                resetBtn.disabled = false;
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
    }

    btn.addEventListener('click', function () {
        postSeo({
            title:      (document.getElementById('apSeoTitle').value || '').trim(),
            description:(document.getElementById('apSeoDesc').value || '').trim(),
            keywords:   (document.getElementById('apSeoKeys').value || '').trim(),
            author:     (document.getElementById('apSeoAuthor').value || '').trim()
        }, function () {
            if (window.TomNotify) TomNotify.show('SEO defaults saved', 'Success', 'success', 3000);
        });
    });

    // Reset: clear the config file → built-in defaults, empty the form, badge → defaults
    resetBtn.addEventListener('click', function () {
        postSeo({ title: '', description: '', keywords: '', author: '' }, function (data) {
            document.getElementById('apSeoTitle').value = '';
            document.getElementById('apSeoDesc').value = '';
            document.getElementById('apSeoKeys').value = '';
            document.getElementById('apSeoAuthor').value = '';
            if (window.TomNotify) TomNotify.show('SEO reset to default content', 'Success', 'success', 3000);
        });
    });
})();

// ── Site font (file-based, no DB): /api/admin/font_save ──
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var sel = document.getElementById('apFontFamily');
    if (!sel) return;
    var custom = document.getElementById('apFontCustom');
    var preview = document.getElementById('apFontPreview');
    var badge = document.getElementById('apFontBadge');
    var btn = document.getElementById('apFontSave');

    function currentFamily() {
        return sel.value === '__custom' ? (custom.value || '').trim() : sel.value;
    }

    function ensurePreviewLink(family) {
        if (!family) return;
        var el = document.getElementById('apFontPreviewLink');
        if (!el) {
            el = document.createElement('link');
            el.id = 'apFontPreviewLink';
            el.rel = 'stylesheet';
            document.head.appendChild(el);
        }
        el.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family) + ':wght@400&display=swap';
    }

    function applyPreview() {
        var isCustom = sel.value === '__custom';
        custom.classList.toggle('d-none', !isCustom);
        var family = currentFamily();
        if (family) {
            ensurePreviewLink(family);
            preview.style.fontFamily = '"' + family + '", sans-serif';
        }
    }

    sel.addEventListener('change', applyPreview);
    custom.addEventListener('input', applyPreview);
    applyPreview();

    btn.addEventListener('click', function () {
        var family = currentFamily();
        if (!family) return;
        btn.disabled = true;
        var fd = new URLSearchParams();
        fd.append('family', family);
        fetch('/api/admin/font_save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: fd.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.status === 'success') {
                    badge.textContent = data.family || 'Rubik';
                    if (window.TomNotify) TomNotify.show('Site font saved — new pages open with "' + data.family + '"', 'Success', 'success', 3000);
                } else if (window.TomNotify) {
                    TomNotify.show(data.error || 'Could not save font', 'Error', 'error', 4000);
                }
            })
            .catch(function () {
                btn.disabled = false;
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
    });
})();

</script>

<script>
(function () {
    // Same geometry as ap-interlock: notch depth D = B + G, concave radius, shared step lines
    var D = 96, R = 20, CR = 44, G = 24;
    var desktop = window.matchMedia('(min-width: 992px)');

    function stepY(h) {
        var sy = Math.max(110, Math.round(h * 0.35));
        var maxSy = h - R - CR - 40;
        if (sy > maxSy) sy = Math.max(R * 2 + 8, maxSy);
        return sy;
    }

    // Base shape = notch bottom-right; flip X/Y for bl / tl / tr
    function shape(w, h, sy, fx, fy) {
        var bw = w - D, shelf = bw + CR;
        var P = [
            ['M', R, 0],
            ['L', w - R, 0], ['Q', w, 0, w, R],
            ['L', w, sy - R], ['Q', w, sy, w - R, sy],
            ['L', shelf, sy], ['Q', bw, sy, bw, sy + CR],
            ['L', bw, h - R], ['Q', bw, h, bw - R, h],
            ['L', R, h], ['Q', 0, h, 0, h - R],
            ['L', 0, R], ['Q', 0, 0, R, 0]
        ];
        var out = '';
        P.forEach(function (seg) {
            out += seg[0];
            for (var i = 1; i < seg.length; i += 2) {
                var x = fx ? w - seg[i] : seg[i];
                var y = fy ? h - seg[i + 1] : seg[i + 1];
                out += x + ',' + y + ' ';
            }
        });
        return out + 'Z';
    }

    var FLIP = { br: [0, 0], bl: [1, 0], tl: [1, 1], tr: [0, 1] };

    function reset(card) {
        card.style.clipPath = '';
        var svg = card.querySelector(':scope > .ap-curve-edge');
        if (svg) svg.remove();
        var hd = card.querySelector(':scope > .card-header');
        if (hd) hd.style.minHeight = '';
    }

    function paint(card, d) {
        card.style.clipPath = 'path("' + d + '")';
        var svg = card.querySelector(':scope > .ap-curve-edge');
        if (!svg) {
            svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'ap-curve-edge');
            svg.appendChild(document.createElementNS('http://www.w3.org/2000/svg', 'path'));
            card.appendChild(svg);
        }
        svg.querySelector('path').setAttribute('d', d);
    }

    function layout() {
        var cards = document.querySelectorAll('.ap-curve');
        if (!desktop.matches) { cards.forEach(reset); return; }

        // 1) wide-head cards decide their own step line
        cards.forEach(function (c) {
            var n = c.dataset.notch;
            if (n !== 'br' && n !== 'bl') return;
            var w = c.clientWidth, h = c.clientHeight;
            if (!w || !h) return;
            var f = FLIP[n];
            paint(c, shape(w, h, stepY(h), f[0], f[1]));
        });

        // 2) tall cards fill the partner's notch: head = partner step line + gap
        cards.forEach(function (c) {
            var n = c.dataset.notch;
            if (n !== 'tl' && n !== 'tr') return;
            var partner = document.getElementById(c.dataset.pair);
            var w = c.clientWidth;
            if (!partner || !w) return;
            var hh = stepY(partner.clientHeight) + G;
            var hd = c.querySelector(':scope > .card-header');
            if (hd) hd.style.minHeight = hh + 'px';
            var h = c.clientHeight;
            var f = FLIP[n];
            paint(c, shape(w, h, h - hh, f[0], f[1]));
        });
    }

    var ro = new ResizeObserver(layout);
    document.querySelectorAll('.ap-curve').forEach(function (c) { ro.observe(c); });
    desktop.addEventListener('change', layout);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(layout);
    layout();
})();
</script>
