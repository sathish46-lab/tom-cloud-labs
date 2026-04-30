<?php
/**
 * Tom Labs - Core UI Logic (Dynamic JS Generator)
 */
header('Content-Type: application/javascript');
require_once __DIR__ . '/../src/config/themes.php';
?>
/**
 * Tom Labs - Core UI Logic & Premium Extensions
 */

(function(window) {
    // 1. Theme Configuration (Base64 Encoded to prevent direct exposure)
    const _encodedThemes = "<?= base64_encode(json_encode($tomThemes)) ?>";
    window.TomThemes = JSON.parse(atob(_encodedThemes));

    // 2. Helper Functions (Moved from Master)
    window.hexToRgbValues = function(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        return `${r}, ${g}, ${b}`;
    };

    window.hexToRgba = function(hex, alpha) {
        return `rgba(${hexToRgbValues(hex)}, ${alpha})`;
    };

    window.adjustColor = function(hex, percent) {
        let num = parseInt(hex.replace("#", ""), 16),
            amt = Math.round(2.55 * percent),
            R = (num >> 16) + amt,
            G = (num >> 8 & 0x00FF) + amt,
            B = (num & 0x0000FF) + amt;
        return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 + (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 + (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
    };

    window.ensureDarkness = function(hex, maxBrightness = 0.2) {
        let r = parseInt(hex.slice(1, 3), 16) / 255;
        let g = parseInt(hex.slice(3, 5), 16) / 255;
        let b = parseInt(hex.slice(5, 7), 16) / 255;
        let brightness = (r * 299 + g * 587 + b * 114) / 1000;
        if (brightness > maxBrightness) return adjustColor(hex, -((brightness - maxBrightness) * 100));
        return hex;
    };

    window.ensureLightness = function(hex, minBrightness = 0.8) {
        let r = parseInt(hex.slice(1, 3), 16) / 255;
        let g = parseInt(hex.slice(3, 5), 16) / 255;
        let b = parseInt(hex.slice(5, 7), 16) / 255;
        let brightness = (r * 299 + g * 587 + b * 114) / 1000;
        if (brightness < minBrightness) return adjustColor(hex, (minBrightness - brightness) * 100);
        return hex;
    };

    // 3. Immediate State Recovery Logic
    window.TomState = {
        init: function(isLoginPage = false) {
            const savedTheme = localStorage.getItem('tom-labs-theme') || 'dark';
            const themeToApply = (savedTheme === 'auto') ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-coreui-theme', themeToApply);

            const isNarrow = localStorage.getItem('tom-labs-sidebar-narrow') === 'true';
            if (isNarrow) document.documentElement.classList.add('sidebar-init-narrow');
            
            const isHidden = localStorage.getItem('tom-labs-sidebar-hidden') === 'true';
            if (isHidden) document.documentElement.classList.add('sidebar-init-hidden');

            const savedBlur = localStorage.getItem('tom-labs-visual-blur');
            const blurEnabled = (savedBlur === null) ? true : (savedBlur === 'true');
            const supportsBlur = CSS.supports('backdrop-filter', 'blur(1px)') || 
                                CSS.supports('-webkit-backdrop-filter', 'blur(1px)');

            if (blurEnabled && supportsBlur) {
                document.documentElement.classList.add('enable-blur');
            }

            // Theme Color Logic
            const savedBG = isLoginPage ? 'ninja' : (localStorage.getItem('tom-labs-bg-mode') || 'parallax');
            const savedColor = localStorage.getItem('tom-labs-plain-color') || '#0b1e36';
            const themeColors = { 'robo': '#0b2b1c', 'robotower': '#0b1e36', 'ninja': '#1c0b2b' };
            const isLight = themeToApply === 'light';

            if (savedBG === 'plain' || themeColors[savedBG]) {
                const color = savedBG === 'plain' ? savedColor : themeColors[savedBG];
                const safeColor = isLight ? ensureLightness(color, 0.8) : ensureDarkness(color, 0.15);
                if (savedBG === 'plain') document.documentElement.classList.add('mode-plain');
                
                const primaryColor = adjustColor(color, isLight ? -40 : 40);
                const pRGB = hexToRgbValues(primaryColor);

                document.documentElement.style.setProperty("--glass-bg", isLight ? hexToRgba(safeColor, 0.4) : hexToRgba(safeColor, 0.85));
                document.documentElement.style.setProperty("--glass-bg-solid", isLight ? hexToRgba(ensureLightness(color, 0.92), 0.98) : hexToRgba(safeColor, 0.98));
                document.documentElement.style.setProperty("--cui-card-bg", isLight ? "rgba(0,0,0,0.05)" : hexToRgba(safeColor, 0.2));
                document.documentElement.style.setProperty("--cui-card-bg-solid", isLight ? hexToRgba(ensureLightness(color, 0.96), 0.98) : hexToRgba(safeColor, 0.95));
                document.documentElement.style.setProperty("--cui-body-bg", safeColor);
                document.documentElement.style.setProperty("--cui-primary", primaryColor);
                document.documentElement.style.setProperty("--cui-primary-rgb", pRGB);
                document.documentElement.style.setProperty("--cui-sidebar-bg", hexToRgba(safeColor, 0.95));
                document.documentElement.style.setProperty("--cui-header-bg", hexToRgba(safeColor, 0.85));
                
                document.documentElement.style.setProperty("--c1", isLight ? "#ffffff" : adjustColor(safeColor, -5));
                document.documentElement.style.setProperty("--c2", isLight ? "#f8f9fa" : safeColor);
                document.documentElement.style.setProperty("--c3", isLight ? "#ffffff" : adjustColor(safeColor, 5));
                document.documentElement.style.setProperty("--c4", isLight ? "#f0f2f5" : adjustColor(safeColor, 10));
            }
        }
    };

    // 4. TomNotify - Premium Stackable Notification System
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

            setTimeout(() => {
                const progressBar = toastEl.querySelector('.toast-progress-bar');
                if (progressBar) progressBar.style.width = '0%';
            }, 50);

            toastEl.addEventListener('hidden.coreui.toast', () => {
                toastEl.remove();
            });
        }
    };

    window.showToast = (msg) => TomNotify.show(msg, "System", "info");
    window.copyToClipboard = (text, label = 'Information') => {
        navigator.clipboard.writeText(text).then(() => {
            TomNotify.show(`${label} copied to clipboard!`, "Copied", "success", 3000);
        });
    };

})(window);
