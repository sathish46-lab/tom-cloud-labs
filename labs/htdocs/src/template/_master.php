<?php
// Start the timer at the earliest possible moment
define('PAGE_START_TIME', microtime(true));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= Session::$pageTitle ?> | Tom Labs</title>

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

        // 2. Sidebar recovery: APPLY CLASSES INSTANTLY
        const isNarrow = localStorage.getItem('tom-labs-sidebar-narrow') === 'true';
        if (isNarrow) {
            // We add the class to <html> so CSS handles layout immediately
            document.documentElement.classList.add('sidebar-init-narrow');
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
        // const savedBG = isLoginPage ? 'ninja' : (localStorage.getItem('tom-labs-bg-mode') || 'parallax');

        // if (savedBG === 'ninja') {
        //     // Apply Ninja specific glass color
        //     document.documentElement.style.setProperty("--glass-bg", "rgba(5, 9, 30, 0.85)");
        // } else if (savedBG === 'parallax') {
        //     // Apply Parallax specific glass color
        //     document.documentElement.style.setProperty("--glass-bg", "rgba(0, 10, 24, 0.823)");
        // } else if (savedBG === 'plain') {
        //     // Standard plain mode
        //     document.documentElement.classList.add('mode-plain');
        // }

        // Keep the global variable so app.js knows which images to load
        window.FORCED_BG_MODE = isLoginPage ? 'ninja' : null;
    })();
    </script>

    <?php if (!defined('IS_LANDING_PAGE') || IS_LANDING_PAGE === false): ?>
    <link rel="stylesheet" href="<?= Session::cacheCDN("/css/app.css") ?>">
    <?php endif; ?>

    <?php foreach (Session::$customCss as $css): ?>
    <link rel="stylesheet" href="<?= Session::cacheCDN($css) ?>">
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
        <div class="bg-cover bg-img-1" data-depth="0.1"></div>
        <div class="bg-cover bg-img-2" data-depth="0.2"></div>
        <div class="bg-cover bg-img-3" data-depth="0.4"></div>
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
    <?php Session::getNav(); ?>

    <div class="wrapper d-flex flex-column min-vh-100 bg-transparent"> 
    <?php Session::getSiteNav(); ?>

    <div class="body flex-grow-1 bg-transparent"> 
        <div class="container-fluid px-4 bg-transparent">
                <?php
                    if (!Session::get('brokenPage', false)) {
                        echo Session::generatePageBody();
                    } else {
                        echo Session::loadTemplate('_error');
                    }
                    ?>
            </div>
        </div>

        <?php if (!Session::get('footer', false)) { echo Session::generateFooter(); } ?>
    </div>
    <!-- Toast Container (Message Toast) -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="margin-top: 4rem; z-index: 2000;">
        <div id="copyToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i id="toast-icon" class="bx bx-check-circle fs-5"></i> 
                    <span id="toast-message">Action successful!</span>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-coreui-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <!-- This card section is for the background selection modal -->
    <div class="modal fade" id="bgSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content  border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="fw-bold m-0">Change Background</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer" 
                                onclick="TomBG.setMode('plain')" style="background: #0b1e36;">
                                <h6 class="fw-bold m-0 text-white">Plain Theme</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer" 
                                onclick="TomBG.setMode('robo')" 
                                style="background: url('/assets/img/robo/robo.jpg'); background-size: cover;">
                                <h6 class="fw-bold m-0 text-white">Robot Mode</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-preview rounded-3 p-5 text-center pointer" 
                                onclick="TomBG.setMode('ninja')" 
                                style="background: url('/assets/img/ninja/ninja.jpg'); background-size: cover;">
                                <h6 class="fw-bold m-0 text-white">Ninja Mode</h6>
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
    
    </script>
    <?php 
    // This translates your indented Session::$ConsoleLogs into JS
    Console::flush(); 
    ?>
</body>

</html>