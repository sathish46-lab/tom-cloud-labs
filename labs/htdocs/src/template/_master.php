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

            // 2. Apply Theme & Layout State
            const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
            const themeToApply = (savedTheme === 'auto') ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-coreui-theme', themeToApply);

            let mode = localStorage.getItem("tom-labs-bg-mode") || "ninja";
            
            // Forced state for login page
            <?php if (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE === true): ?>
            mode = "ninja";
            document.documentElement.classList.add('glass-mode');
            window.FORCED_BG_MODE = "ninja";
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
    <div id="notification-container" class="toast-container position-fixed top-0 end-0 p-3" style="margin-top: 4rem; z-index: 2000;">
        <!-- Toasts will be injected here dynamically -->
    </div>
    <!-- This card section is for the background selection modal -->
    <div class="modal fade" id="bgSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(var(--cui-body-bg-rgb, 11, 30, 54), 0.75); backdrop-filter: blur(24px); border: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.1) !important;">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="fw-bold m-0 text-body-emphasis">Change Background</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" style="filter: var(--cui-btn-close-white-filter, none);"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                onclick="TomBG.setMode('plain')" style="background: #010d12; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Plain Theme</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                onclick="TomBG.setMode('robo')" 
                                style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/robo/robo.jpg'); background-size: cover; background-position: center; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Robot Mode</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                onclick="TomBG.setMode('ninja')" 
                                style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/ninja/ninja.jpg'); background-size: cover; background-position: center; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Ninja Mode</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                onclick="TomBG.setMode('robotower')" 
                                style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('/assets/Background_Img/RoboTower/robo_tower.jpg'); background-size: cover; background-position: center; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Robo Tower</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                                onclick="TomBG.setMode('spiderman')" 
                                style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/assets/Background_Img/spiderman/spiderman.jpg'); background-size: cover; background-position: center; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Spiderman Mode</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Plain Theme Color Picker Modal -->
    <div class="modal fade" id="plainColorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 shadow-lg bg-transparent">
                <div class="apple-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0 text-white">Theme Designer</h5>
                        <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
                    </div>

                    <!-- Preset Themes -->
                    <div class="mb-3">
                        <label class="text-secondary fw-bold d-block mb-2 small uppercase tracking-widest">PRESET PALETTES</label>
                        <div class="d-flex justify-content-between align-items-center px-1">
                            <?php
                            $presets = [
                                'Default' => '#010d12',
                                'Charcoal' => '#0B1E36',
                                'Midnight' => '#FF6251',
                                'Emerald' => '#00BBD6',
                                'Crimson' => '#FFE373',
                                'Slate' => '#000000',
                                'Green' => '#ffffffff',
                                
                            ];
                            foreach ($presets as $name => $hex): ?>
                                <div class="color-preset-wrapper text-center">
                                    <div class="color-preset rounded-circle pointer border border-white border-opacity-10" 
                                        onclick="TomBG.setPlainColor('<?= $hex ?>')"
                                        style="background: <?= $hex ?>; width: 38px; height: 38px;"
                                        title="<?= $name ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- My Themes (Slots) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="text-secondary fw-bold small uppercase tracking-widest m-0">SAVED THEMES</label>
                            <span class="smaller text-secondary opacity-50" style="font-size: 9px;">Select individual colors for each slot</span>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <?php for($i=0; $i<4; $i++): ?>
                                <div class="position-relative flex-grow-1">
                                    <div id="custom-slot-<?= $i ?>" class="custom-slot w-100" onclick="TomBG.applySlot(<?= $i ?>)">
                                        <i class="bx bx-plus opacity-25"></i>
                                        <?php if($i === 0): ?>
                                            <div class="edit-icon shadow-sm" onclick="event.stopPropagation(); TomBG.openDesignerForSlot(0)">
                                                <i class="bx bxs-magic-wand"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="edit-icon shadow-sm" onclick="event.stopPropagation(); document.getElementById('slot-picker-<?= $i ?>').click()">
                                                <i class="bx bx-pencil"></i>
                                            </div>
                                            <input type="color" id="slot-picker-<?= $i ?>" class="position-absolute opacity-0 pointer-none" 
                                                style="top:0; left:0; width:1px; height:1px;" 
                                                onchange="TomBG.saveCustomColor(<?= $i ?>, this.value)">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
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
            </div>
        </div>
    </div>


    <?php endif; ?>

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
                    <div id="${toastId}" class="toast border-0 rounded-4 overflow-hidden shadow-lg mb-3" role="alert" aria-live="assertive" aria-atomic="true" 
                        style="background: rgba(1, 13, 18, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08) !important; min-width: 320px;">
                        <div class="toast-header border-0 bg-transparent text-white pt-3 px-3 d-flex align-items-center">
                            <strong class="me-auto d-flex align-items-center gap-2 fs-6">
                                <i class="bx ${icon}"></i> 
                                <span class="ls-tight">${title}</span>
                            </strong>
                            <small class="opacity-50 fw-light" style="font-size: 10px;">now</small>
                            <button type="button" class="btn-close btn-close-white ms-3 mb-1" style="font-size: 9px;" data-coreui-dismiss="toast"></button>
                        </div>
                        <div class="toast-body text-white opacity-80 px-3 pb-3 pt-1">
                            <span class="small" style="line-height: 1.5;">${message}</span>
                        </div>
                        <div class="toast-progress-container" style="height: 3px; background: rgba(255,255,255,0.05); width: 100%;">
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