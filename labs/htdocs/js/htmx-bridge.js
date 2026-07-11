/**
 * htmx-bridge.js — Labs SPA Navigation & Lifecycle Bridge
 *
 * Ensures 100% consistent initialization across both:
 * 1. Full Page Reloads (DOMContentLoaded)
 * 2. HTMX Partial Swaps & SPA Navigation (htmx:afterSettle / htmx:afterSwap / htmx:historyRestore)
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. Unified Page Load Hook (window.onPageLoad)
    // =========================================================================
    window._pageLoadHooks = window._pageLoadHooks || [];
    window._pageLoadInitialized = window._pageLoadInitialized || false;

    window.onPageLoad = function (callback) {
        if (typeof callback !== 'function') return;
        window._pageLoadHooks.push(callback);

        // If DOM is already ready, run immediately
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            try {
                callback();
            } catch (err) {
                console.error('[htmx-bridge] onPageLoad hook error:', err);
            }
        }
    };

    function runAllPageLoadHooks() {
        const hooks = window._pageLoadHooks || [];
        for (let i = 0; i < hooks.length; i++) {
            try {
                hooks[i]();
            } catch (err) {
                console.error('[htmx-bridge] hook execution error:', err);
            }
        }
    }

    // =========================================================================
    // 2. Core UI & Component Initialization (Tooltips, Popovers, Modals)
    // =========================================================================
    function initCoreUIComponents(container) {
        const root = container || document;

        // Clean up any stuck modal backdrops from previous pages
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');

        // Initialize CoreUI Tooltips
        if (typeof coreui !== 'undefined' && coreui.Tooltip) {
            // Dispose stale tooltips in target region
            root.querySelectorAll('[data-coreui-toggle="tooltip"]').forEach((el) => {
                const instance = coreui.Tooltip.getInstance(el);
                if (instance) instance.dispose();
                new coreui.Tooltip(el, { container: 'body' });
            });
        }

        // Initialize CoreUI Popovers
        if (typeof coreui !== 'undefined' && coreui.Popover) {
            root.querySelectorAll('[data-coreui-toggle="popover"]').forEach((el) => {
                const instance = coreui.Popover.getInstance(el);
                if (instance) instance.dispose();
                new coreui.Popover(el);
            });
        }

        // Initialize Syntax Highlighting (Highlight.js)
        if (typeof hljs !== 'undefined' && hljs.highlightAll) {
            try {
                root.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                });
            } catch (e) {}
        }
    }

    // =========================================================================
    // 3. Page & App Modules Re-Initialization (LearnAI, Viewport Locks, etc.)
    // =========================================================================
    function initAppModules(container) {
        const root = container || document;

        // LearnAI module integration
        if (root.querySelector('.learn-app-wrapper') || root.querySelector('#learn-panel-1')) {
            if (window.LearnAI && typeof window.LearnAI.init === 'function') {
                try {
                    window.LearnAI.init();
                } catch (err) {
                    console.error('[htmx-bridge] LearnAI init error:', err);
                }
            }
        }

        // Ensure dynamic app height calculations apply cleanly
        if (typeof window.updateAppHeight === 'function') {
            try { window.updateAppHeight(); } catch (e) {}
        }
    }

    // =========================================================================
    // 4. Sidebar Active Navigation Synchronizer
    // =========================================================================
    function syncSidebarActiveState(targetUrl) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        let path = targetUrl || window.location.pathname;
        // Strip query string or hash
        path = path.split('?')[0].split('#')[0];

        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            const href = link.getAttribute('href');
            if (!href) return;
            const linkPath = href.split('?')[0].split('#')[0];

            if (linkPath === path || (path.startsWith(linkPath) && linkPath !== '/' && linkPath !== '/home')) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    // =========================================================================
    // 5. HTMX Top Loading Bar Indicator Driver
    // =========================================================================
    function setupHtmxProgressIndicator() {
        const progressEl = document.getElementById('htmx-top-progress');
        if (!progressEl) return;

        let progressTimer = null;

        document.addEventListener('htmx:beforeRequest', function () {
            progressEl.style.opacity = '1';
            progressEl.style.width = '15%';
            clearTimeout(progressTimer);
            progressTimer = setTimeout(() => { progressEl.style.width = '65%'; }, 120);
        });

        document.addEventListener('htmx:afterRequest', function () {
            progressEl.style.width = '100%';
            progressTimer = setTimeout(() => {
                progressEl.style.opacity = '0';
                setTimeout(() => { progressEl.style.width = '0%'; }, 250);
            }, 200);
        });

        document.addEventListener('htmx:sendError', function () {
            progressEl.style.opacity = '0';
            progressEl.style.width = '0%';
        });
    }

    // =========================================================================
    // 6. Global Master Initialization Bridge (Fires on DOMContentLoaded + HTMX Swaps)
    // =========================================================================
    function runMasterInitBridge(container, url) {
        initCoreUIComponents(container);
        initAppModules(container);
        runAllPageLoadHooks();
        if (url) {
            syncSidebarActiveState(url);
        } else {
            syncSidebarActiveState(window.location.pathname);
        }
    }

    // DOMContentLoaded handler (Full Page Reload)
    document.addEventListener('DOMContentLoaded', function () {
        setupHtmxProgressIndicator();
        runMasterInitBridge(document, window.location.pathname);
    });

    // HTMX afterSettle handler (SPA Partial Swap)
    document.addEventListener('htmx:afterSettle', function (event) {
        const target = event.detail && event.detail.target ? event.detail.target : document;
        const xhr = event.detail && event.detail.xhr;
        const finalUrl = xhr && xhr.responseURL ? new URL(xhr.responseURL).pathname : window.location.pathname;

        runMasterInitBridge(target, finalUrl);
    });

    // HTMX historyRestore handler (Browser Back / Forward button)
    document.addEventListener('htmx:historyRestore', function (event) {
        runMasterInitBridge(document, window.location.pathname);
    });

    // Expose bridge utility globally
    window.HtmxBridge = {
        init: runMasterInitBridge,
        syncSidebar: syncSidebarActiveState
    };

})();
