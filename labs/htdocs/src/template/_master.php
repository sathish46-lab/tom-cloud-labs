<?php
// Start the timer at the earliest possible moment
define('PAGE_START_TIME', microtime(true));
?>
<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= Session::cdn3('logo/favicon.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= Session::cdn3('logo/favicon.png') ?>">

    <!-- Professional SEO Meta Tags -->
    <?php
    $seoTitle = Session::$pageTitle ?? 'Tom Labs - Advanced Development Environment';
    $seoDesc = Session::get('seo_description', 'Experience the ultimate cloud development environment. Tom Labs provides a highly secure, fast, and feature-rich development workspace, VPS, and VPN tailored for professionals.');
    $seoKeywords = Session::get('seo_keywords', 'Advanced Development Environment, Cloud IDE, VPS Hosting, Secure VPN, Docker Labs, Tom Labs, Coding Workspace');
    $seoAuthor = Session::get('seo_author', 'Sathish');
    $seoUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'labs.tomweb.in') . ($_SERVER['REQUEST_URI'] ?? '/');
    $seoImage = Session::get('seo_image', "https://" . ($_SERVER['HTTP_HOST'] ?? 'labs.tomweb.in') . "/assets/images/og-image.jpg");
    ?>
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoKeywords) ?>">
    <meta name="author" content="<?= htmlspecialchars($seoAuthor) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seoUrl) ?>">

    <!-- JSON-LD Structured Data for Google -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "Tom Labs",
      "operatingSystem": "Web, Windows, macOS, Linux",
      "applicationCategory": "DeveloperApplication",
      "creator": {
        "@type": "Person",
        "name": "Sathish"
      },
      "description": "<?= htmlspecialchars($seoDesc) ?>",
      "url": "<?= htmlspecialchars($seoUrl) ?>"
    }
    </script>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($seoUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoImage) ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($seoUrl) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars($seoImage) ?>">

    <!-- Professional Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.2/dist/css/coreui.min.css" rel="stylesheet">

    <style id="tom-theme-vars">
        /* Server-side default theme (SNA Deep Blue) */
        :root, [data-coreui-theme="dark"] {
            --cui-body-bg: #0b1e36;
            --glass-bg: rgba(11, 30, 54, 0.88);
            --glass-bg-solid: rgba(11, 30, 54, 0.96);
            --cui-card-bg: rgba(11, 30, 54, 0.45);
            --cui-card-bg-solid: rgba(11, 30, 54, 0.95);
            --cui-primary: #5856d6;
            --cui-sidebar-bg: rgba(11, 30, 54, 0.97);
            --cui-header-bg: rgba(11, 30, 54, 0.92);
        }

        [data-coreui-theme="light"] {
            --cui-body-bg: #f4f7f9;
            --glass-bg: rgba(255, 255, 255, 0.4); /* Premium, transparent frosted glass */
            --glass-bg-solid: rgba(255, 255, 255, 0.98);
            --cui-card-bg: rgba(255, 255, 255, 0.35);
            --cui-card-bg-solid: rgba(255, 255, 255, 0.95);
            --cui-primary: #5856d6;
            --cui-sidebar-bg: rgba(255, 255, 255, 0.98);
            --cui-header-bg: rgba(255, 255, 255, 0.95);
            --cui-body-color: #2f353a;
        }
    </style>

    <?php
    $serverTheme = [];
    if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN) {
        $user = Session::getUser();
        if ($user) {
            $serverTheme = $user->getThemePreferences() ?? [];
        }
    }
    ?>
    <script>
        /**
         * Fast-track state recovery to prevent UI Flicker
         * Matches SNA's 'simple' architectural pattern
         */
        (function() {
            // 0. Load Background Theme Data
            <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/src/config/themes.php'; ?>
            window.TomBGThemes = <?= json_encode($tomThemes) ?>;

            // 1. Sync Server Preferences to LocalStorage (Server is Source of Truth)
            const serverTheme = <?= json_encode($serverTheme) ?>;
            if (serverTheme && serverTheme.mode) localStorage.setItem('tom-labs-bg-mode', serverTheme.mode);
            if (serverTheme && serverTheme.plain_color) localStorage.setItem('tom-labs-plain-color', serverTheme.plain_color);
            if (serverTheme && serverTheme.custom_slots && Array.isArray(serverTheme.custom_slots)) {
                serverTheme.custom_slots.forEach((color, i) => {
                    if (color) localStorage.setItem('tom-labs-custom-color-' + i, color);
                });
            }
            if (serverTheme && serverTheme.accent_color) localStorage.setItem('tom-labs-accent-color', serverTheme.accent_color);
            if (serverTheme && serverTheme.custom_themes && Array.isArray(serverTheme.custom_themes)) {
                serverTheme.custom_themes.forEach((theme, i) => {
                    if (theme) localStorage.setItem('tom-labs-custom-theme-' + i, theme);
                });
            }

            // 2. Apply Theme & Layout State
            const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
            const themeToApply = (savedTheme === 'auto') ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-coreui-theme', themeToApply);

            let mode = localStorage.getItem("tom-labs-bg-mode") || "spiderman";
            
            // Forced state for login page
            <?php if (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE === true): ?>
            mode = "spiderman";
            document.documentElement.classList.add('glass-mode');
            window.FORCED_BG_MODE = "spiderman";
            <?php endif; ?>

            document.documentElement.classList.toggle("mode-plain", mode === "plain");

            const isNarrow = localStorage.getItem('tom-labs-sidebar-narrow') === 'true';
            if (isNarrow) document.documentElement.classList.add('sidebar-init-narrow');
            
            const isHidden = localStorage.getItem('tom-labs-sidebar-hidden') === 'true';
            if (isHidden) document.documentElement.classList.add('sidebar-init-hidden');
            
            const savedBlur = localStorage.getItem('tom-labs-visual-blur');
            if (savedBlur !== 'false') document.documentElement.classList.add('glass-mode');
        })();
    </script>


    <?php if (!defined('IS_LANDING_PAGE') || IS_LANDING_PAGE === false): ?>
    <link rel="stylesheet" href="/css/app.css?v=<?= time() ?>">
    <?php endif; ?>

    <?php foreach (Session::$customCss as $css): ?>
    <link rel="stylesheet" href="<?= Session::cacheCDN($css) ?>?v=<?= time() ?>">
    <?php endforeach; ?>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@coreui/chartjs/dist/js/coreui-chartjs.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/stomp.js/2.3.3/stomp.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/parallax/3.1.0/parallax.min.js"></script>

</head>

<body>
    <?php if (!defined('IS_LANDING_PAGE') || IS_LANDING_PAGE === false): ?>
    <div id="scene">
        <div class="bg-cover bg-img-1" data-depth="0.8"></div>
        <div class="bg-cover bg-img-2" data-depth="0.5"></div>
        <div class="bg-cover bg-img-3" data-depth="0.3"></div>
        <div class="bg-cover bg-img-4" data-depth="0.1"></div>
    </div>
    <?php endif; ?>

    <?php if (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE === true): ?>
    <div class="container">
        <?php echo Session::get('page_content'); ?>
    </div>

    <?php elseif (defined('IS_LANDING_PAGE') && IS_LANDING_PAGE === true): ?>
    <div class="landing-wrapper">
        <?php echo Session::get('page_content'); ?>
    </div>
    <?php if (!Session::get('footer', false)) { echo Session::generateFooter(); } ?>

    <?php else: ?>
    <?php if (!defined('IS_HOME_PAGE')): Session::getNav(); endif; ?>

    <div class="wrapper d-flex flex-column min-vh-100 bg-transparent" style="<?= defined('IS_HOME_PAGE') ? '--cui-sidebar-occupy-start: 0px;' : '' ?>"> 
    <?php if (!defined('IS_HOME_PAGE')): Session::getSiteNav(); endif; ?>

    <div class="body flex-grow-1 bg-transparent"> 
        <div class="container-fluid <?= (Session::get('is_learn_ai') || defined('IS_HOME_PAGE')) ? 'p-0' : 'px-4' ?> bg-transparent">
                <?php
                    if (!Session::get('brokenPage', false)) {
                        echo Session::generatePageBody();
                    } else {
                        echo Session::loadTemplate('_error');
                    }
                    ?>
            </div>
        </div>

        <?php if (!Session::get('footer', false) && !defined('IS_HOME_PAGE')) { echo Session::generateFooter(); } ?>
    </div>
    <!-- Premium Stackable Notification Container -->
    <div id="notification-container" class="toast-container position-fixed top-0 end-0 p-3" style="margin-top: 4rem; z-index: 100000 !important;">
        <!-- Toasts will be injected here dynamically -->
    </div>
    <!-- This card section is for the background selection modal -->
    <div class="modal fade" id="bgSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(var(--cui-body-bg-rgb, 11, 30, 54), 0.92); backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px); border: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.1) !important;">
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold m-0 text-body-emphasis">Change Background</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" style="filter: var(--cui-btn-close-white-filter, none);"></button>
                </div>
                <!-- Tab Navigation -->
                <div class="px-4 pt-3">
                    <ul class="nav nav-pills gap-2" id="bgModalTabs" role="tablist" style="border: none;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active px-3 py-2 rounded-pill fw-semibold" id="swatches-tab" data-coreui-toggle="tab" data-coreui-target="#swatches-pane" type="button" role="tab" aria-selected="true"
                                style="font-size: 0.82rem; background: rgba(var(--cui-primary-rgb), 0.15); color: var(--cui-primary); border: 1px solid rgba(var(--cui-primary-rgb), 0.25);">
                                <i class='bx bxs-palette me-1'></i>Swatches <span class="badge bg-primary bg-opacity-25 text-primary ms-1" style="font-size: 0.65rem;">14</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-2 rounded-pill fw-semibold" id="themes-tab" data-coreui-toggle="tab" data-coreui-target="#themes-pane" type="button" role="tab" aria-selected="false"
                                style="font-size: 0.82rem; color: var(--cui-body-color); opacity: 0.65;">
                                <i class='bx bxs-image me-1'></i>Themes <span class="badge bg-body-secondary text-body-secondary ms-1" style="font-size: 0.65rem;">5</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="modal-body px-4 pb-4 pt-3">
                    <div class="tab-content" id="bgModalTabContent">
                        <!-- ============ SWATCHES TAB ============ -->
                        <div class="tab-pane fade show active" id="swatches-pane" role="tabpanel">
                            <div class="d-flex flex-wrap justify-content-center gap-4">
                                <?php
                                $swatches = [
                                    ['name' => 'Default',   'bg' => '#010d12',  'accent' => '#8b91f9', 'gradient' => 'radial-gradient(circle at 35% 30%, #1a3a4a, #010d12 70%)'],
                                    ['name' => 'Charcoal',  'bg' => '#0B1E36',  'accent' => '#5dade2', 'gradient' => 'radial-gradient(circle at 35% 30%, #1a3d5c, #0B1E36 70%)'],
                                    ['name' => 'Sunset',    'bg' => '#FF6251',  'accent' => '#1a1a2e', 'gradient' => 'radial-gradient(circle at 35% 30%, #ff9a8b, #FF6251 70%)'],
                                    ['name' => 'Ocean',     'bg' => '#00BBD6',  'accent' => '#0a2540', 'gradient' => 'radial-gradient(circle at 35% 30%, #5ce1f0, #00BBD6 70%)'],
                                    ['name' => 'Gold',      'bg' => '#FFE373',  'accent' => '#3d2e00', 'gradient' => 'radial-gradient(circle at 35% 30%, #fff4b8, #FFE373 70%)'],
                                    ['name' => 'Midnight',  'bg' => '#000000',  'accent' => '#a78bfa', 'gradient' => 'radial-gradient(circle at 35% 30%, #3a3a3a, #000000 70%)'],
                                    ['name' => 'Arctic',    'bg' => '#ffffff',  'accent' => '#2563eb', 'gradient' => 'radial-gradient(circle at 35% 30%, #ffffff, #d4d4d4 70%)'],
                                    ['name' => 'Forest',    'bg' => '#0d5e3a',  'accent' => '#a7f3d0', 'gradient' => 'radial-gradient(circle at 35% 30%, #28a06a, #0d5e3a 70%)'],
                                    ['name' => 'Amethyst',  'bg' => '#6B3FA0',  'accent' => '#fbbf24', 'gradient' => 'radial-gradient(circle at 35% 30%, #a06de0, #6B3FA0 70%)'],
                                    ['name' => 'Slate',     'bg' => '#3D4F5F',  'accent' => '#67e8f9', 'gradient' => 'radial-gradient(circle at 35% 30%, #6b8da0, #3D4F5F 70%)'],
                                    ['name' => 'Teal',      'bg' => '#00796B',  'accent' => '#fde68a', 'gradient' => 'radial-gradient(circle at 35% 30%, #2baf9e, #00796B 70%)'],
                                    ['name' => 'Rose',      'bg' => '#C2185B',  'accent' => '#fce7f3', 'gradient' => 'radial-gradient(circle at 35% 30%, #f06292, #C2185B 70%)'],
                                    ['name' => 'Deep Sea',  'bg' => '#0D2137',  'accent' => '#38bdf8', 'gradient' => 'radial-gradient(circle at 35% 30%, #1d4a6e, #0D2137 70%)'],
                                    ['name' => 'Coral',     'bg' => '#FF7043',  'accent' => '#1e293b', 'gradient' => 'radial-gradient(circle at 35% 30%, #ffab91, #FF7043 70%)'],
                                ];
                                foreach ($swatches as $swatch): ?>
                                    <div class="text-center pointer swatch-sphere-wrap swatch-item" 
                                         onclick="TomBG.applySwatchPreset('<?= $swatch['bg'] ?>', '<?= $swatch['accent'] ?>')"
                                         data-bg="<?= $swatch['bg'] ?>" data-accent="<?= $swatch['accent'] ?>"
                                         style="width: 72px;">
                                        <div class="rounded-circle mx-auto mb-2 swatch-sphere dual-sphere d-flex align-items-center justify-content-center position-relative" 
                                             style="width: 52px; height: 52px; border: 2px solid <?= $swatch['bg'] ?>; padding: 3px; background: transparent; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                                            <div class="w-100 h-100 rounded-circle position-relative" style="background: linear-gradient(to bottom, #1e293b 50%, #ffffff 50%); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                                                <span class="dual-sphere-dot" style="background: <?= $swatch['accent'] ?>; border: 2px solid rgba(255,255,255,0.8); width: 14px; height: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border-radius: 50%;"></span>
                                            </div>
                                            <div class="active-badge position-absolute shadow-sm" style="top: -4px; right: -4px; width: 16px; height: 16px; background: #22c55e; border-radius: 50%; color: white; display: none; align-items: center; justify-content: center; font-size: 11px; border: 2px solid var(--cui-body-bg); z-index: 2;">
                                                <i class='bx bx-check fw-bold'></i>
                                            </div>
                                        </div>
                                        <span class="d-block text-body-emphasis" style="font-size: 0.7rem; font-weight: 500; opacity: 0.85; line-height: 1.2;"><?= $swatch['name'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Your Custom Colors Section -->
                            <div class="mt-4 pt-3" style="border-top: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.08);">
                                <h6 class="fw-semibold text-body-emphasis mb-3" style="font-size: 0.82rem; opacity: 0.7;">
                                    <i class='bx bxs-brush me-1'></i>Your Custom Colors
                                </h6>
                                <div id="dynamic-custom-slots" class="d-flex justify-content-center gap-4 align-items-start flex-wrap">
                                    <!-- Dynamic custom slots will be injected here by JS -->
                                    <!-- Add new custom theme -->
                                    <div class="text-center pointer swatch-sphere-wrap create-new-slot" 
                                         onclick="TomBG.setMode('plain'); var m = coreui.Modal.getInstance(document.getElementById('bgSelectModal')); if(m)m.hide(); var dm = new coreui.Modal(document.getElementById('plainColorModal')); dm.show();"
                                         style="width: 72px;">
                                        <div class="rounded-circle mx-auto mb-2 swatch-sphere d-flex align-items-center justify-content-center"
                                             style="width: 52px; height: 52px; background: radial-gradient(circle at 35% 30%, rgba(255,255,255,0.1), rgba(255,255,255,0.02) 70%); box-shadow: 0 6px 16px rgba(0,0,0,0.15); border: 2px dashed rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.2); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                                            <i class='bx bx-plus text-body-emphasis' style="font-size: 1.3rem; opacity: 0.4;"></i>
                                        </div>
                                        <span class="d-block text-body-secondary" style="font-size: 0.65rem; font-weight: 500; line-height: 1.2;">Create New</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============ THEMES TAB ============ -->
                        <div class="tab-pane fade" id="themes-pane" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                        onclick="TomBG.setMode('robo')" 
                                        style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/robo/robo.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Robot Mode</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                        onclick="TomBG.setMode('ninja')" 
                                        style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/ninja/ninja.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Ninja Mode</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                        onclick="TomBG.setMode('robotower')" 
                                        style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('/assets/Background_Img/RoboTower/robo_tower.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Robo Tower</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                        onclick="TomBG.setMode('spiderman')" 
                                        style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/assets/Background_Img/spiderman/spiderman.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Spiderman Mode</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                        onclick="TomBG.setMode('ironman')" 
                                        style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/assets/Background_Img/IronMan/0.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Iron Man Mode</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Ok Button -->
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-coreui-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Plain Theme Color Picker Modal — Edit Custom Theme -->
    <div class="modal fade" id="plainColorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-5 shadow-lg bg-transparent">
                <div class="apple-card">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0 text-white">Edit Custom Theme</h5>
                        <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
                    </div>

                    <div class="row g-4">
                        <!-- ===== LEFT: Color Pickers ===== -->
                        <div class="col-lg-7">
                            <!-- Background / Accent Toggle -->
                            <div class="picker-target-toggle d-flex gap-2 mb-3">
                                <button type="button" id="picker-target-bg" class="picker-target-btn active" onclick="TomBG.switchPickerTarget('background')">
                                    <i class='bx bxs-color-fill me-1'></i>Background
                                </button>
                                <button type="button" id="picker-target-accent" class="picker-target-btn" onclick="TomBG.switchPickerTarget('accent')">
                                    <i class='bx bxs-star me-1'></i>Accent
                                </button>
                            </div>

                            <!-- Tabbed Color Designer -->
                            <div class="color-designer-tabs d-flex justify-content-center gap-2 mb-3 p-1 rounded-4">
                                <button type="button" class="designer-tab active" onclick="TomBG.switchPickerTab('spectrum')" title="Spectrum">
                                    <i class="bx bxs-grid-alt"></i>
                                </button>
                                <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('wheel')" title="Wheel">
                                    <i class="bx bx-loader-circle"></i>
                                </button>
                                <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('sliders')" title="Sliders">
                                    <i class="bx bx-slider-alt"></i>
                                </button>
                                <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('palettes')" title="Palettes">
                                    <i class="bx bxs-palette"></i>
                                </button>
                                <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('pencils')" title="Pencils">
                                    <i class="bx bx-pencil"></i>
                                </button>
                            </div>

                            <div id="picker-content" class="picker-content">
                                <!-- Spectrum Mode -->
                                <div id="picker-spectrum" class="picker-mode active">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <label class="small fw-bold text-white m-0 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Spectrum Designer</label>
                                        <span class="text-secondary fw-mono" style="font-size: 10px;" id="hex-preview">#0B1E36</span>
                                    </div>
                                    <div id="spectrum-map" class="spectrum-map rounded-4 position-relative pointer shadow-inner mb-4">
                                        <div class="spectrum-cursor" id="spectrum-cursor"></div>
                                    </div>
                                </div>

                                <!-- Wheel Mode -->
                                <div id="picker-wheel" class="picker-mode d-none text-center">
                                    <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase text-start" style="font-size: 9px;">Color Wheel</label>
                                    <div class="d-flex justify-content-center mb-3">
                                        <div id="color-wheel" class="color-wheel">
                                            <div class="wheel-cursor" id="wheel-cursor"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Global Brightness (For Wheel & Spectrum) -->
                                <div class="brightness-container mb-2 px-2 d-none" id="global-brightness-cont">
                                    <div class="d-flex justify-content-between small mb-2 opacity-50"><span>Brightness</span><span id="val-bright">100%</span></div>
                                    <input type="range" id="brightness-slider" min="0" max="100" value="100" class="brightness-slider w-100" oninput="TomBG.updateBrightness(this.value)">
                                </div>

                                <!-- Sliders Mode -->
                                <div id="picker-sliders" class="picker-mode d-none">
                                    <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">RGB Sliders</label>
                                    <div class="slider-group mb-3">
                                        <div class="d-flex justify-content-between small mb-1"><span>Red</span><span id="val-r">0</span></div>
                                        <input type="range" class="rgb-slider r" min="0" max="255" value="11" oninput="TomBG.updateFromSliders()">
                                    </div>
                                    <div class="slider-group mb-3">
                                        <div class="d-flex justify-content-between small mb-1"><span>Green</span><span id="val-g">0</span></div>
                                        <input type="range" class="rgb-slider g" min="0" max="255" value="30" oninput="TomBG.updateFromSliders()">
                                    </div>
                                    <div class="slider-group mb-3">
                                        <div class="d-flex justify-content-between small mb-1"><span>Blue</span><span id="val-b">0</span></div>
                                        <input type="range" class="rgb-slider b" min="0" max="255" value="54" oninput="TomBG.updateFromSliders()">
                                    </div>
                                </div>

                                <!-- Palettes Mode -->
                                <div id="picker-palettes" class="picker-mode d-none">
                                    <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Web Palettes</label>
                                    <div class="palette-grid">
                                        <?php 
                                        $webColors = ['#F44336','#E91E63','#9C27B0','#673AB7','#3F51B5','#2196F3','#03A9F4','#00BCD4','#009688','#4CAF50','#8BC34A','#CDDC39','#FFEB3B','#FFC107','#FF9800','#FF5722','#795548','#9E9E9E','#607D8B','#000000','#FFFFFF'];
                                        foreach($webColors as $c): ?>
                                            <div class="palette-item" style="background: <?= $c ?>" onclick="TomBG.setPlainColor('<?= $c ?>')"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Pencils Mode -->
                                <div id="picker-pencils" class="picker-mode d-none">
                                    <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Professional Pencils</label>
                                    <div class="pencils-container">
                                        <?php 
                                        $pencils = [
                                            '#010d12', '#1e293b', '#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1', '#f8fafc',
                                            '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#06b6d4',
                                            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e', '#71717a'
                                        ];
                                        foreach($pencils as $p): ?>
                                            <div class="pencil-item" data-color="<?= $p ?>" style="--pencil-color: <?= $p ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== RIGHT: Contrast + Preview ===== -->
                        <div class="col-lg-5">
                            <!-- Contrast Accessibility Scores -->
                            <div class="contrast-panel rounded-4 p-3 mb-3" style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.06);">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="fw-bold text-white" style="font-size: 0.9rem;" id="contrast-overall-score">—</span>
                                    <div>
                                        <span class="fw-semibold text-warning" style="font-size: 0.8rem;" id="contrast-overall-label">Checking...</span>
                                        <div class="d-flex gap-1 mt-1" id="contrast-stars">
                                            <i class='bx bxs-star' style="font-size: 0.7rem; color: #fbbf24;"></i>
                                            <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                                            <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                                            <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                                            <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-column gap-2" id="contrast-rows-container">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-white opacity-70" style="font-size: 0.72rem; min-width: 95px;" id="contrast-text-label">Text on bg</span>
                                        <div class="contrast-bar flex-grow-1 mx-2"><div id="contrast-text-bar" class="contrast-bar-fill" style="width: 50%;"></div></div>
                                        <span class="text-white fw-bold" style="font-size: 0.72rem; min-width: 28px; text-align: right;" id="contrast-text-val">—</span>
                                        <span class="contrast-badge badge-aaa ms-2" id="contrast-text-badge">—</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-white opacity-70" style="font-size: 0.72rem; min-width: 95px;" id="contrast-accent-label">Accent on bg</span>
                                        <div class="contrast-bar flex-grow-1 mx-2"><div id="contrast-accent-bar" class="contrast-bar-fill" style="width: 50%;"></div></div>
                                        <span class="text-white fw-bold" style="font-size: 0.72rem; min-width: 28px; text-align: right;" id="contrast-accent-val">—</span>
                                        <span class="contrast-badge badge-fail ms-2" id="contrast-accent-badge">—</span>
                                    </div>
                                </div>
                                
                                <!-- Semantic Colors Preview -->
                                <div class="d-flex gap-3 mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.06);">
                                    <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #22c55e;"></span><span class="text-white opacity-60">Success</span></span>
                                    <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #ef4444;"></span><span class="text-white opacity-60">Danger</span></span>
                                    <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #f59e0b;"></span><span class="text-white opacity-60">Warning</span></span>
                                    <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #3b82f6;"></span><span class="text-white opacity-60">Info</span></span>
                                </div>
                            </div>

                            <!-- Preview Spheres: Background / Preview / Accent -->
                            <div class="d-flex justify-content-center gap-4 mb-3">
                                <div class="text-center">
                                    <div class="rounded-circle mx-auto mb-2 designer-preview-sphere"
                                         id="designer-sphere-bg"
                                         style="width: 58px; height: 58px; box-shadow: 0 6px 16px rgba(0,0,0,0.4), inset 0 -4px 8px rgba(0,0,0,0.3), inset 0 2px 4px rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.1);"></div>
                                    <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Background</span>
                                </div>
                                <div class="text-center">
                                    <div class="rounded-circle mx-auto mb-2 designer-preview-sphere position-relative"
                                         id="designer-sphere-preview"
                                         style="width: 68px; height: 68px; box-shadow: 0 8px 24px rgba(0,0,0,0.5), inset 0 -6px 12px rgba(0,0,0,0.35), inset 0 3px 6px rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.15);">
                                        <span class="rounded-circle position-absolute" id="designer-sphere-preview-dot"
                                              style="width: 22px; height: 22px; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></span>
                                    </div>
                                    <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Preview</span>
                                </div>
                                <div class="text-center">
                                    <div class="rounded-circle mx-auto mb-2 designer-preview-sphere"
                                         id="designer-sphere-accent"
                                         style="width: 58px; height: 58px; box-shadow: 0 6px 16px rgba(0,0,0,0.4), inset 0 -4px 8px rgba(0,0,0,0.3), inset 0 2px 4px rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.1);"></div>
                                    <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Accent</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-end mt-3">
                                <button type="button" class="enhance-btn" onclick="TomBG.enhanceAccent()">
                                    <i class='bx bxs-magic-wand me-1'></i>Enhance
                                </button>
                                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" style="font-size: 0.82rem;"
                                        onclick="var slot = TomBG.currentEditingSlot !== null ? TomBG.currentEditingSlot : 0; TomBG.saveCustomTheme(slot); var m = coreui.Modal.getInstance(document.getElementById('plainColorModal')); if(m)m.hide();">
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php endif; ?>

    <script>
    <?php
    $domains = get_config('domains') ?: [];
    $mq_domain = $domains['mqs'] ?? '';
    ?>
    window.TOM_CONFIG = {
        mq_domain: <?= json_encode($mq_domain) ?>
    };
    </script>
    <script src="<?= Session::cacheCDN("/js/app.js") ?>"></script>

    <script>
    // Initialize all tooltips globally with body container to fix positioning issues
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-coreui-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new coreui.Tooltip(tooltipTriggerEl, {
                container: 'body',
                trigger: 'hover'
            });
        });

        // Silent Activity Tracker — fire-and-forget for Smart Insights
        <?php if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN): ?>
        try {
            fetch('/api/dashboard/track_activity', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'page=' + encodeURIComponent(window.location.pathname)
            }).catch(function(){});
        } catch(e) {}
        <?php endif; ?>
    });
    </script>
    <?php if (!defined('IS_LOGIN_PAGE') || IS_LOGIN_PAGE === false): ?>
    <script>
        /**
         * TomNotify - Premium Stackable Notification System
         */
        window.TomNotify = {
            show: function(message, title = 'Notification', type = 'success', duration = 5000) {
                const container = document.getElementById('notification-container');
                if (!container) return;

                const iconMap = {
                    success: 'bxs-check-circle text-success',
                    error: 'bxs-error-circle text-danger',
                    warning: 'bxs-warning text-warning',
                    info: 'bxs-info-circle text-info'
                };

                const toastId = 'toast-' + Date.now() + Math.floor(Math.random() * 1000);
                const icon = iconMap[type] || iconMap.success;
                const progressColor = type === 'error' ? '#e74c3c' : (type === 'warning' ? '#f1c40f' : 'var(--cui-primary, #8b91f9)');

                const html = `
                    <div id="${toastId}" class="toast border-0 rounded-4 overflow-hidden shadow-lg mb-3" role="alert" aria-live="assertive" aria-atomic="true" data-coreui-autohide="true" data-coreui-delay="${duration}"
                        style="background: var(--glass-bg, rgba(11, 30, 54, 0.88)); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.1) !important; min-width: 320px;">
                        <div class="toast-header border-0 bg-transparent pt-3 px-3 d-flex align-items-center">
                            <strong class="me-auto d-flex align-items-center gap-2 fs-6 text-body-emphasis">
                                <i class="bx ${icon}"></i> 
                                <span class="ls-tight">${title}</span>
                            </strong>
                            <small class="text-body-secondary fw-light" style="font-size: 10px;">now</small>
                            <button type="button" class="btn-close ms-3 mb-1" style="font-size: 9px; filter: var(--cui-btn-close-white-filter, none);" data-coreui-dismiss="toast"></button>
                        </div>
                        <div class="toast-body text-body px-3 pb-3 pt-1">
                            <span class="small" style="line-height: 1.5; opacity: 0.85;">${message}</span>
                        </div>
                        <div class="toast-progress-container" style="height: 3px; background: rgba(var(--cui-emphasis-color-rgb, 255,255,255), 0.05); width: 100%;">
                            <div class="toast-progress-bar" style="height: 100%; width: 100%; background: ${progressColor}; transition: width ${duration}ms linear;"></div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('afterbegin', html);
                const toastEl = document.getElementById(toastId);
                const toast = new coreui.Toast(toastEl, { autohide: true, delay: duration });
                toast.show();

                // Start progress bar animation
                setTimeout(() => {
                    const progressBar = toastEl.querySelector('.toast-progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                }, 50);

                // Clean up DOM after hidden
                toastEl.addEventListener('hidden.coreui.toast', () => {
                    toastEl.remove();
                });
                
                // Fallback cleanup if event fails
                setTimeout(() => {
                    if (document.getElementById(toastId)) {
                        toast.hide();
                        setTimeout(() => {
                            if (document.getElementById(toastId)) document.getElementById(toastId).remove();
                        }, 500);
                    }
                }, duration + 1000);
            }
        };

        // Legacy compatibility
        window.showToast = (msg) => TomNotify.show(msg, "System", "info");
        window.copyToClipboard = (text, label = 'Information') => {
            navigator.clipboard.writeText(text).then(() => {
                TomNotify.show(`${label} copied to clipboard!`, "Copied", "success", 3000);
            });
        };

        // Auto-trigger from PHP Sessions
        document.addEventListener('DOMContentLoaded', () => {
            <?php 
            $flashTypes = ['success' => 'Success', 'error' => 'Failed', 'info' => 'Notice', 'warning' => 'Warning'];
            foreach ($flashTypes as $key => $title):
                if ($msg = Session::get("toast_$key")): 
            ?>
                TomNotify.show("<?= htmlspecialchars($msg) ?>", "<?= $title ?>", "<?= $key ?>");
            <?php endif; endforeach; ?>
        });

        // Sync UI on load
        document.addEventListener('DOMContentLoaded', function() {
            if (window.TomVisuals) window.TomVisuals.syncUI();
        });
    </script>
    <?php endif; ?>

    <?php 
    // This translates your indented Session::$ConsoleLogs into JS
    Console::flush(); 
    ?>
    <?php include __DIR__ . '/_session_expired_popup.php'; ?>
    
    <!-- Masonry Layout Library -->
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
    <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>

    <!-- SNA Liquid Refraction Filter -->
    <svg style="display:none;" aria-hidden="true">
        <filter id="liquid-refraction">
            <feTurbulence type="fractalNoise" baseFrequency="0.012" numOctaves="3" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="12" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </svg>
</body>

</html>