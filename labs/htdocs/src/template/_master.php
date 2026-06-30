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

    <script type="text/javascript">
    window.addEventListener('load', function() {
        // Wait 2000 milliseconds (2 seconds) AFTER the page loads to inject the tracker.
        // This guarantees the browser stops the loading spinner completely.
        setTimeout(function() {
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "n7q1nqtm06");
        }, 2000); 
    });
    </script>

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
         * Runs before ANY CSS is parsed to eliminate flashes
         */
        (function() {
            // Global Environment Configuration
            window.SERVER_IP = "<?= \TomLabs\Core\Env::get('SERVER_IP') ?>";

            // 0. Background Theme Data will be fetched dynamically
            window.TomBGThemes = {};

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

            // 2. Apply Theme & Layout State immediately to DOM
            let savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
            let themeToApply = (savedTheme === 'auto') ?
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

    <?php
    $plainColorDark = $serverTheme['plain_color'] ?? '#1a2a1a';
    $plainColorLight = $serverTheme['plain_color_light'] ?? '#f0fff4';
    $accentColor = $serverTheme['accent_color'] ?? '#51b355';

    function tomHexToRgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return [0,0,0];
        return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    }
    function tomRgbToHex($r, $g, $b) {
        return sprintf("#%02x%02x%02x", max(0,min(255,round($r))), max(0,min(255,round($g))), max(0,min(255,round($b))));
    }
    function tomAdjustBright($hex, $amt) {
        $rgb = tomHexToRgb($hex);
        return tomRgbToHex($rgb[0]+$amt, $rgb[1]+$amt, $rgb[2]+$amt);
    }
    function tomShiftHue($hex, $degree) {
        $rgb = tomHexToRgb($hex);
        $r = $rgb[0]/255; $g = $rgb[1]/255; $b = $rgb[2]/255;
        $max = max($r, $g, $b); $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($max == $min) { $h = $s = 0; }
        else {
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h /= 6;
        }
        $h = fmod($h + ($degree/360), 1.0);
        if ($h < 0) $h += 1.0;
        
        if ($s == 0) { $r = $g = $b = $l; }
        else {
            $hue2rgb = function($p, $q, $t) {
                if($t < 0) $t += 1; if($t > 1) $t -= 1;
                if($t < 1/6) return $p + ($q - $p) * 6 * $t;
                if($t < 1/2) return $q;
                if($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
                return $p;
            };
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $hue2rgb($p, $q, $h + 1/3);
            $g = $hue2rgb($p, $q, $h);
            $b = $hue2rgb($p, $q, $h - 1/3);
        }
        return tomRgbToHex($r*255, $g*255, $b*255);
    }

    // Color utils have been moved below CSS to ensure specificity.
    ?>

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


    <?php if (!defined('IS_LANDING_PAGE') || IS_LANDING_PAGE === false): ?>
    <link rel="stylesheet" href="/css/app.css?v=<?= time() ?>">
    <?php endif; ?>

    <?php foreach (Session::$customCss as $css): ?>
    <link rel="stylesheet" href="<?= Session::cacheCDN($css) ?>?v=<?= time() ?>">
    <?php endforeach; ?>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@coreui/chartjs/dist/js/coreui-chartjs.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/stomp.js/2.3.3/stomp.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/parallax/3.1.0/parallax.min.js"></script>

    <?php
    $mode = $serverTheme['mode'] ?? 'spiderman';
    if (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE === true) {
        $mode = 'spiderman';
    }

    require_once __DIR__ . '/tom_color_utils.php';
    require_once __DIR__ . '/../config/themes.php';

    $assets = [];

    if ($mode === 'plain') {
        $themeColor = $plainColorDark;
        // Dark Mode logic for Plain
        $safeColorDark = tomEnsureDarkness($themeColor, 0.2);
        $c1Dark = tomAdjustColorLightness($safeColorDark, -10);
        $c2Dark = $safeColorDark;
        $c3Dark = tomAdjustColorLightness($safeColorDark, 8);
        $c4Dark = tomAdjustColorLightness($safeColorDark, 15);
        $c5Dark = tomAdjustColorLightness($safeColorDark, 5);
        $c6Dark = $safeColorDark;
        $c7Dark = tomAdjustColorLightness($safeColorDark, -5);
        
        $glassBgDark = tomHexToRgbaString($safeColorDark, 0.88);
        $cardBgDark = tomHexToRgbaString(tomAdjustColorLightness($safeColorDark, 4), 0.65);
        $sidebarBgDark = tomHexToRgbaString(tomAdjustColorLightness($safeColorDark, 1.5), 0.98);
        $headerBgDark = tomHexToRgbaString(tomAdjustColorLightness($safeColorDark, 1), 0.92);
        $primaryRgb = implode(',', tomHexToRgbArray($accentColor));

        // Light Mode logic for Plain
        $safeColorLight = tomEnsureLightness($themeColor, 0.95);
        $baseLight = tomEnsureLightness($themeColor, 0.98);
        $c1Light = "#ffffff";
        $c2Light = $baseLight;
        $c3Light = tomAdjustColorLightness($baseLight, -2);
        $c4Light = tomAdjustColorLightness($baseLight, -5);
        $c5Light = "#ffffff";
        $c6Light = $baseLight;
        $c7Light = tomAdjustColorLightness($baseLight, -3);
    } else {
        $themeConfig = $tomThemes[$mode] ?? $tomThemes['spiderman'];
        $themeColor = $themeConfig['color'] ?? '#0b1e36';
        $accentColor = $themeConfig['primary'] ?? tomAdjustColorLightness($themeColor, 40);
        $assets = $themeConfig['assets'] ?? [];

        // Dark Mode logic for Image Themes
        $safeColorDark = tomEnsureDarkness($themeColor, 0.15);
        $c1Dark = tomAdjustColorLightness($safeColorDark, -5);
        $c2Dark = $safeColorDark;
        $c3Dark = tomAdjustColorLightness($safeColorDark, 5);
        $c4Dark = tomAdjustColorLightness($safeColorDark, 10);
        $c5Dark = $c4Dark;
        $c6Dark = $c4Dark;
        $c7Dark = $c4Dark;
        
        $glassBgDark = tomHexToRgbaString($safeColorDark, 0.85);
        $cardBgDark = tomHexToRgbaString($safeColorDark, 0.20);
        $sidebarBgDark = tomHexToRgbaString($safeColorDark, 0.95);
        $headerBgDark = tomHexToRgbaString($safeColorDark, 0.85);
        $primaryRgb = implode(',', tomHexToRgbArray($accentColor));

        // Light Mode logic for Image Themes (Fallback approximation)
        $safeColorLight = tomEnsureLightness($themeColor, 0.8);
        $c1Light = "#ffffff";
        $c2Light = "#f8f9fa";
        $c3Light = "#ffffff";
        $c4Light = "#f0f2f5";
        $c5Light = "#ffffff";
        $c6Light = "#ffffff";
        $c7Light = "#ffffff";
    }
    ?>
    <!-- Inject perfectly calculated gradient on the server to prevent ANY flash -->
    <!-- PLACED AFTER APP.CSS TO OVERRIDE DEFAULTS -->
    <style id="swatch-server-css">
        html[data-coreui-theme="dark"] {
            --c1: <?= $c1Dark ?>;
            --c2: <?= $c2Dark ?>;
            --c3: <?= $c3Dark ?>;
            --c4: <?= $c4Dark ?>;
            --c5: <?= $c5Dark ?>;
            --c6: <?= $c6Dark ?>;
            --c7: <?= $c7Dark ?>;
            --glass-bg: <?= $glassBgDark ?>;
            --cui-card-bg: <?= $cardBgDark ?>;
            --cui-sidebar-bg: <?= $sidebarBgDark ?>;
            --cui-header-bg: <?= $headerBgDark ?>;
            --accent-color: <?= $accentColor ?>;
            --cui-primary: <?= $accentColor ?>;
            --cui-primary-rgb: <?= $primaryRgb ?>;
            --cui-body-bg: <?= $safeColorDark ?>;
        }
        html[data-coreui-theme="light"] {
            --c1: <?= $c1Light ?>;
            --c2: <?= $c2Light ?>;
            --c3: <?= $c3Light ?>;
            --c4: <?= $c4Light ?>;
            --c5: <?= $c5Light ?>;
            --c6: <?= $c6Light ?>;
            --c7: <?= $c7Light ?>;
            --glass-bg: #ffffff;
            --cui-card-bg: #ffffff;
            --cui-sidebar-bg: #ffffff;
            --cui-header-bg: #ffffff;
            --accent-color: <?= $accentColor ?>;
            --cui-primary: <?= $accentColor ?>;
            --cui-primary-rgb: <?= $primaryRgb ?>;
            --cui-body-bg: <?= $safeColorLight ?>;
        }
        html[data-coreui-theme="dark"] .btn-primary { color: #ffffff !important; }
        html[data-coreui-theme="dark"] .badge.bg-primary { color: #ffffff !important; }
        html[data-coreui-theme="light"] .btn-primary { color: #ffffff !important; }
        html[data-coreui-theme="light"] .badge.bg-primary { color: #ffffff !important; }

        html.mode-plain body { transition: none !important; }
        html.mode-plain #scene, html.mode-plain .scenery-container { display: none !important; }

        <?php if ($mode !== 'plain'): ?>
        body { background: transparent !important; }
        <?php endif; ?>
    </style>
</head>

<body>
    <?php if (!defined('IS_LANDING_PAGE') || IS_LANDING_PAGE === false): ?>
    <div id="scene" style="<?= $mode !== 'plain' ? 'display: block;' : '' ?>">
        <div class="bg-cover bg-img-1" data-depth="0.8" style="<?= isset($assets[0]) ? "background-image: url('{$assets[0]}'); display: block;" : '' ?>"></div>
        <div class="bg-cover bg-img-2" data-depth="0.5" style="<?= isset($assets[1]) ? "background-image: url('{$assets[1]}'); display: block;" : '' ?>"></div>
        <div class="bg-cover bg-img-3" data-depth="0.3" style="<?= isset($assets[2]) ? "background-image: url('{$assets[2]}'); display: block;" : '' ?>"></div>
        <div class="bg-cover bg-img-4" data-depth="0.1" style="<?= isset($assets[3]) ? "background-image: url('{$assets[3]}'); display: block;" : '' ?>"></div>
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
    <div id="notification-container" class="toast-container position-fixed top-0 end-0 p-3" style="margin-top: 4rem; z-index: 100000 !important; pointer-events: none;">
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
                <!-- Dynamic Content Container -->
                <div id="bgSelectModalContent">
                    <div class="p-5 text-center">
                        <i class="bx bx-loader-alt bx-spin text-primary" style="font-size: 3rem;"></i>
                        <div class="mt-3 text-white opacity-75 fw-semibold tracking-widest uppercase" style="font-size: 0.8rem;">Loading Backgrounds...</div>
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
            <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(var(--cui-body-bg-rgb, 11, 30, 54), 0.92); backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px); border: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.1) !important;">
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold m-0 text-body-emphasis">Edit Custom Theme</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" style="filter: var(--cui-btn-close-white-filter, none);"></button>
                </div>
                <div class="modal-body p-4" id="plainColorModalContent">
                    <div class="p-5 text-center">
                        <i class="bx bx-loader-alt bx-spin text-primary" style="font-size: 3rem;"></i>
                        <div class="mt-3 text-white opacity-75 fw-semibold tracking-widest uppercase" style="font-size: 0.8rem;">Loading Designer...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php endif; ?>

    <script>
    window.TOM_CONFIG = {
        mq_domain: <?= json_encode(get_config('mq_domain') ?: '') ?>
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
                    <div id="${toastId}" class="toast fade border-0 rounded-4 overflow-hidden shadow-lg mb-3" role="alert" aria-live="assertive" aria-atomic="true" data-coreui-autohide="true" data-coreui-delay="${duration}"
                        style="background: var(--glass-bg, rgba(11, 30, 54, 0.88)); min-width: 320px; pointer-events: auto;">
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