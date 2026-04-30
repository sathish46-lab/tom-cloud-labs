<?php
// Start the timer at the earliest possible moment
define('PAGE_START_TIME', microtime(true));

// Professional Background Theme Configuration
$tomThemes = [
    'robo' => [
        'assets' => [
            '/assets/Background_Img/robo/0.png',
            '/assets/Background_Img/robo/1.png',
            '/assets/Background_Img/robo/2.png'
        ]
    ],
    'ninja' => [
        'assets' => [
            '/assets/Background_Img/ninja/0.png',
            '/assets/Background_Img/ninja/1.png',
            '/assets/Background_Img/ninja/2.png'
        ]
    ],
    'robotower' => [
        'assets' => [
            '/assets/Background_Img/RoboTower/0.png',
            '/assets/Background_Img/RoboTower/1.png',
            '/assets/Background_Img/RoboTower/2.png',
            '/assets/Background_Img/RoboTower/3.png'
        ]
    ],
    'parallax' => [
        'assets' => [
            '/assets/Background_Img/parallax/0.png',
            '/assets/Background_Img/parallax/1.png',
            '/assets/Background_Img/parallax/2.png',
            '/assets/Background_Img/parallax/3.png'
        ]
    ]
];
?>
<script>
    // Pass PHP themes to JavaScript
    window.TomThemes = <?php echo json_encode($tomThemes); ?>;
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= Session::$pageTitle ?></title>

    <!-- Professional Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">

    <script>
    /**
     * IMMEDIATE STATE RECOVERY (Prevents UI Flicker)
     * Runs before <body> is parsed to set instant UI state
     */
    (function() {
        // 1. Theme recovery
        const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
        const themeToApply = (savedTheme === 'auto') ?
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
            savedTheme;
        document.documentElement.setAttribute('data-coreui-theme', themeToApply);

        const isNarrow = localStorage.getItem('tom-labs-sidebar-narrow') === 'true';
        if (isNarrow) {
            document.documentElement.classList.add('sidebar-init-narrow');
        }
        const isHidden = localStorage.getItem('tom-labs-sidebar-hidden') === 'true';
        if (isHidden) {
            document.documentElement.classList.add('sidebar-init-hidden');
        }
        // 3. Visual Blur recovery (Immediate)
        const savedBlur = localStorage.getItem('tom-labs-visual-blur');
        // Default to true if never set
        const blurEnabled = (savedBlur === null) ? true : (savedBlur === 'true');
        
        // Check for Browser Support instantly
        const supportsBlur = CSS.supports('backdrop-filter', 'blur(1px)') || 
                            CSS.supports('-webkit-backdrop-filter', 'blur(1px)');

        if (blurEnabled && supportsBlur) {
            document.documentElement.classList.add('enable-blur');
        }

        /**
         * 4. Background & Color Force Logic
         * Removes bgColors object and applies specific styles directly
         */
        const isLoginPage = <?= (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE) ? 'true' : 'false' ?>;
        const savedBG = isLoginPage ? 'ninja' : (localStorage.getItem('tom-labs-bg-mode') || 'parallax');
        const savedColor = localStorage.getItem('tom-labs-plain-color') || '#0b1e36';

        const themeColors = {
            'robo': '#0b2b1c',
            'robotower': '#0b1e36',
            'ninja': '#1c0b2b'
        };

        const isLight = themeToApply === 'light';

        if (savedBG === 'plain' || themeColors[savedBG]) {
            const color = savedBG === 'plain' ? savedColor : themeColors[savedBG];
            const safeColor = isLight ? ensureLightness(color, 0.8) : ensureDarkness(color, 0.15);

            if (savedBG === 'plain') {
                document.documentElement.classList.add('mode-plain');
            }
            
            // Apply theme variables instantly to prevent flicker in sidebar/cards
            const primaryColor = adjustColor(color, isLight ? -40 : 40);
            const pRGB = hexToRgbValues(primaryColor);

            document.documentElement.style.setProperty("--glass-bg", isLight ? hexToRgba(safeColor, 0.4) : hexToRgba(safeColor, 0.85));
            document.documentElement.style.setProperty("--glass-bg-solid", isLight ? hexToRgba(ensureLightness(color, 0.92), 0.98) : hexToRgba(safeColor, 0.98));
            document.documentElement.style.setProperty("--cui-card-bg", isLight ? "rgba(0,0,0,0.05)" : hexToRgba(safeColor, 0.2));
            document.documentElement.style.setProperty("--cui-card-bg-solid", isLight ? hexToRgba(ensureLightness(color, 0.96), 0.98) : hexToRgba(safeColor, 0.95));
            document.documentElement.style.setProperty("--cui-body-bg", safeColor);
            document.documentElement.style.setProperty("--cui-primary", primaryColor);
            document.documentElement.style.setProperty("--cui-primary-rgb", pRGB);
            document.documentElement.style.setProperty("--cui-sidebar-bg", isLight ? hexToRgba(safeColor, 0.95) : hexToRgba(safeColor, 0.95));
            document.documentElement.style.setProperty("--cui-header-bg", isLight ? hexToRgba(safeColor, 0.85) : hexToRgba(safeColor, 0.85));

            // Sync subtle background variants
            document.documentElement.style.setProperty("--c1", isLight ? "#ffffff" : adjustColor(safeColor, -5));
            document.documentElement.style.setProperty("--c2", isLight ? "#f8f9fa" : safeColor);
            document.documentElement.style.setProperty("--c3", isLight ? "#ffffff" : adjustColor(safeColor, 5));
            document.documentElement.style.setProperty("--c4", isLight ? "#f0f2f5" : adjustColor(safeColor, 10));
        } else {
            // Parallax / image themes: set neutral defaults to prevent color flash
            if (isLight) {
                document.documentElement.style.setProperty("--c1", "#ffffff");
                document.documentElement.style.setProperty("--c2", "#f8f9fa");
                document.documentElement.style.setProperty("--c3", "#ffffff");
                document.documentElement.style.setProperty("--c4", "#f0f2f5");
                document.documentElement.style.setProperty("--cui-body-bg", "#f8f9fa");
            } else {
                document.documentElement.style.setProperty("--c1", "#0b1e36");
                document.documentElement.style.setProperty("--c2", "#112e4a");
                document.documentElement.style.setProperty("--c3", "#16375e");
                document.documentElement.style.setProperty("--c4", "#1a2c48");
                document.documentElement.style.setProperty("--cui-body-bg", "rgba(3, 17, 36, 0.94)");
                document.documentElement.style.setProperty("--glass-bg", "rgba(0, 10, 24, 0.823)");
                document.documentElement.style.setProperty("--glass-bg-solid", "rgba(10, 20, 35, 0.94)");
                document.documentElement.style.setProperty("--cui-sidebar-bg", "rgba(11, 30, 54, 0.95)");
            }
        }

        function adjustColor(hex, percent) {
            var num = parseInt(hex.replace("#",""),16),
            amt = Math.round(2.55 * percent),
            R = (num >> 16) + amt,
            G = (num >> 8 & 0x00FF) + amt,
            B = (num & 0x0000FF) + amt;
            return "#" + (0x1000000 + (R<255?R<1?0:R:255)*0x10000 + (G<255?G<1?0:G:255)*0x100 + (B<255?B<1?0:B:255)).toString(16).slice(1);
        }

        function hexToRgba(hex, opacity) {
            return `rgba(${hexToRgbValues(hex)}, ${opacity})`;
        }

        function hexToRgbValues(hex) {
            var num = parseInt(hex.replace("#", ""), 16),
            R = (num >> 16) & 0xff,
            G = (num >> 8) & 0xff,
            B = num & 0xff;
            return `${R}, ${G}, ${B}`;
        }

        function ensureDarkness(hex, maxLuminance) {
            const rgbStr = hexToRgbValues(hex).split(",");
            const r = parseInt(rgbStr[0]), g = parseInt(rgbStr[1]), b = parseInt(rgbStr[2]);
            const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
            
            if (luminance > maxLuminance) {
                const factor = maxLuminance / luminance;
                const nr = Math.round(r * factor);
                const ng = Math.round(g * factor);
                const nb = Math.round(b * factor);
                return "#" + (0x1000000 + (nr << 16) + (ng << 8) + nb).toString(16).slice(1);
            }
            return hex;
        }

        function ensureLightness(hex, minLuminance) {
            const rgbStr = hexToRgbValues(hex).split(",");
            const r = parseInt(rgbStr[0]), g = parseInt(rgbStr[1]), b = parseInt(rgbStr[2]);
            const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
            
            if (luminance < minLuminance) {
                const factor = (1 - minLuminance) / (1 - luminance);
                const nr = Math.round(255 - (255 - r) * factor);
                const ng = Math.round(255 - (255 - g) * factor);
                const nb = Math.round(255 - (255 - b) * factor);
                return "#" + (0x1000000 + (nr << 16) + (ng << 8) + nb).toString(16).slice(1);
            }
            return hex;
        }

        // Keep the global variable so app.js knows which images to load
        window.FORCED_BG_MODE = isLoginPage ? 'ninja' : null;
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
                                onclick="TomBG.setMode('plain')" style="background: #0b1e36; min-height: 140px; display: flex; align-items: center; justify-content: center;">
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
                                style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('/assets/Background_Img/RoboTower/3.png'); background-size: contain; background-repeat: no-repeat; background-position: center bottom; background-color: #0b1e36; min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                <h6 class="fw-bold m-0 text-white">Robo Tower</h6>
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
                                'Default' => '#0b1e36',
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
                                    '#0b1e36', '#1e293b', '#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1', '#f8fafc',
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

    <!-- This card section is for the visual recommendation modal -->
    <div class="modal fade" id="visualsRecommendationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content  border-0 rounded-4 shadow-lg p-3">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Visuals Recommendation</h5>
                    <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small opacity-75 mb-4">
                        This website uses Blur effects for background. This effect is only available if your browser supports WebGL and your GPU is a high-performance one. You can check your GPU info below.
                    </p>
                    
                    <div class="table-responsive rounded-3 border border-white border-opacity-10">
                        <table class="table table-dark table-borderless mb-0 small">
                            <tbody>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <td class="text-secondary py-2 ps-3">WebGL Support</td>
                                    <td class="fw-bold text-success py-2 pe-3 text-end" id="gpu-webgl">Detecting...</td>
                                </tr>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <td class="text-secondary py-2 ps-3">High Performance GPU</td>
                                    <td class="fw-bold text-success py-2 pe-3 text-end" id="gpu-performance">Detecting...</td>
                                </tr>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <td class="text-secondary py-2 ps-3">GPU Vendor</td>
                                    <td class="py-2 pe-3 text-end" id="gpu-vendor">Detecting...</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary py-2 ps-3">Renderer</td>
                                    <td class="py-2 pe-3 text-end" id="gpu-renderer">Detecting...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-4 mb-0 small text-secondary italic">
                        <span class="fw-bold text-white">Note:</span> This info is only for recommendation purpose. You can still use this website without WebGL support or High Performance GPU. However, some visual effects may not work properly.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" data-coreui-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <script src="<?= Session::cacheCDN("/js/app.js") ?>"></script>

    <script>
    /**
     * Theme & Icon Controller
     */
    function updateThemeIcon(theme) {
        const iconMap = {
            'light': 'bx-sun',
            'dark': 'bx-moon',
            'auto': 'bx-circle-half'
        };
        const iconElement = document.getElementById('currentThemeIcon');
        if (iconElement) {
            iconElement.classList.remove('bx-sun', 'bx-moon', 'bx-circle-half', 'bx-circle');
            iconElement.classList.add(iconMap[theme] || 'bx-circle');
        }
    }

    // Sync theme icon on load
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
        updateThemeIcon(savedTheme);
    });

    function changeTheme(themeName) {
        let themeToApply = themeName;
        if (themeName === 'auto') {
            themeToApply = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        
        // Apply theme attribute for SCSS selectors
        document.documentElement.setAttribute('data-coreui-theme', themeToApply);
        localStorage.setItem('tom-labs-theme', themeName);
        updateThemeIcon(themeName);

        // OPTIONAL: Dispatch event for parallax.js or GSAP backgrounds to re-calculate
        window.dispatchEvent(new Event('themeChanged'));
    }

    // Initialize all tooltips globally with body container to fix positioning issues
    document.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-coreui-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new coreui.Tooltip(tooltipTriggerEl, {
                container: 'body',
                trigger: 'hover'
            });
        });
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
                        style="background: rgba(10, 20, 35, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08) !important; min-width: 320px;">
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
    </script>
    <?php endif; ?>

    <?php 
    // This translates your indented Session::$ConsoleLogs into JS
    Console::flush(); 
    ?>
</body>

</html>