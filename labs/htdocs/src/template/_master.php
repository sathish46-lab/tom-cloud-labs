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
            const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
            const themeToApply = (savedTheme === 'auto') ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-coreui-theme', themeToApply);

            // Instantly apply custom accent (primary) color to prevent flash
            const accentColor = localStorage.getItem('tom-labs-accent-color');
            if (accentColor && accentColor.startsWith('#')) {
                document.documentElement.style.setProperty('--cui-primary', accentColor);
                if (accentColor.length === 7) {
                    const r = parseInt(accentColor.slice(1, 3), 16);
                    const g = parseInt(accentColor.slice(3, 5), 16);
                    const b = parseInt(accentColor.slice(5, 7), 16);
                    document.documentElement.style.setProperty('--cui-primary-rgb', `${r}, ${g}, ${b}`);
                }
            }

            let mode = localStorage.getItem("tom-labs-bg-mode") || "spiderman";
            
            // Forced state for login page
            <?php if (defined('IS_LOGIN_PAGE') && IS_LOGIN_PAGE === true): ?>
            mode = "spiderman";
            document.documentElement.classList.add('glass-mode');
            window.FORCED_BG_MODE = "spiderman";
            <?php endif; ?>

            document.documentElement.classList.toggle("mode-plain", mode === "plain");

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
    $plainColorDark = $serverTheme['plain_color'] ?? 'rgba(7, 24, 41, 0.95)';
    $plainColorLight = $serverTheme['plain_color_light'] ?? 'rgba(227, 234, 239, 0.95)';
    ?>
    <style id="swatch-server-css">
        html.mode-plain[data-coreui-theme="dark"] body { background-color: <?= $plainColorDark ?> !important; transition: none !important; }
        html.mode-plain[data-coreui-theme="light"] body { background-color: <?= $plainColorLight ?> !important; transition: none !important; }
        html.mode-plain #scene, html.mode-plain .scenery-container { display: none !important; }
    </style>

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