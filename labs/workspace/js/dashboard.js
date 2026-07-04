/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


// ========================================================================
// Dashboard — Workspace Polling & Insights Animations
// ========================================================================

(function () {
    let dashboardPollingInterval = null;

    window.initDashboardPolling = function () {
        if (dashboardPollingInterval) return; // Prevent duplicate intervals

        function fetchDashboardMetrics() {
            fetch(`/api/dashboard/stats`)
                .then(res => res.json())
                .then(data => {
                    if (!data.labs || !data.labs.all_labs) return;
                    
                    data.labs.all_labs.forEach(lab => {
                        const hash = lab.hash;
                        const stats = lab.metrics;
                        if (!hash || !stats) return;

                        const cpuEl = document.getElementById(`cpu-${hash}`);
                        const memEl = document.getElementById(`mem-${hash}`);
                        const loadEl = document.getElementById(`load-${hash}`);

                        if (stats.CPUPerc && cpuEl) cpuEl.textContent = stats.CPUPerc;
                        if (stats.MemUsage && memEl) {
                            const usage = stats.MemUsage.split(' / ')[0];
                            memEl.textContent = usage;
                        }
                        if (stats.Load1 !== undefined && loadEl) {
                            const loadAvg = `${stats.Load1.toFixed(2)}, ${stats.Load5.toFixed(2)}, ${stats.Load15.toFixed(2)}`;
                            loadEl.textContent = loadAvg;
                        }
                    });
                })
                .catch(() => { });
        }
        
        fetchDashboardMetrics();
        dashboardPollingInterval = setInterval(fetchDashboardMetrics, 5000);
    };

    // To allow stopping it on HTMX page transitions if needed
    window.stopDashboardPolling = function() {
        if (dashboardPollingInterval) {
            clearInterval(dashboardPollingInterval);
            dashboardPollingInterval = null;
        }
    };

    // Initialize Smart Insights activity graph
    window.initDashboardInsights = function () {
        const subtitle = document.getElementById('insights-subtitle');
        const peakLabel = document.getElementById('insights-peak-label');
        const footer = document.getElementById('insights-footer');
        const activeDays = document.getElementById('insights-active-days');
        const lastSeen = document.getElementById('insights-last-seen');

        if (!subtitle || !peakLabel) return;

        fetch('/api/dashboard/insights')
            .then(res => res.json())
            .then(data => {
                if (data.has_data) {
                    subtitle.textContent = "You're most productive between";
                    peakLabel.textContent = data.peak_label;

                    // Animate bars with theme-aware colors
                    const isLight = document.documentElement.getAttribute('data-coreui-theme') === 'light';
                    const bars = document.querySelectorAll('.insights-bar');
                    const barValues = data.bars || [];
                    bars.forEach((bar, i) => {
                        const val = barValues[i] || 0;
                        const minHeight = val > 0 ? Math.max(val, 6) : 3;
                        setTimeout(() => {
                            bar.style.height = minHeight + '%';
                            if (val >= 70) {
                                // Peak hours — orange
                                bar.style.background = '#ffa502';
                                bar.style.boxShadow = isLight ? '0 0 6px rgba(255, 165, 2, 0.45)' : '0 0 8px rgba(255, 165, 2, 0.35)';
                            } else if (val > 0) {
                                // Active hours
                                bar.style.background = isLight ? 'rgba(0, 0, 0, 0.16)' : 'rgba(255, 255, 255, 0.22)';
                                bar.style.boxShadow = 'none';
                            } else {
                                // Inactive — very subtle
                                bar.style.background = isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.08)';
                                bar.style.boxShadow = 'none';
                            }
                        }, i * 25);
                    });

                    // Show footer stats
                    if (data.active_days > 0 && activeDays && footer) {
                        activeDays.textContent = data.active_days + ' active days';
                        footer.style.cssText = '';
                        footer.classList.remove('d-none');
                        footer.classList.add('d-flex');
                    }
                    if (data.last_seen && lastSeen) {
                        lastSeen.textContent = 'Last: ' + data.last_seen;
                    }
                } else {
                    subtitle.textContent = "Start exploring to see your insights";
                    peakLabel.textContent = "No data yet";
                    peakLabel.style.fontSize = '1.2rem';
                    peakLabel.style.opacity = '0.4';
                }
            })
            .catch(() => {
                subtitle.textContent = "Start exploring to see your insights";
                peakLabel.textContent = "No data yet";
            });
    };

    // Tab Switcher and selection persistence
    window.switchContinueTab = function (tabId) {
        // 1. Update buttons by toggling active class cleanly
        const buttons = document.querySelectorAll('.continue-tab-btn');
        buttons.forEach(btn => {
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 2. Toggle panes
        const panes = document.querySelectorAll('.continue-tab-pane');
        panes.forEach(pane => {
            if (pane.id === 'continue-pane-' + tabId) {
                pane.classList.remove('d-none');
            } else {
                pane.classList.add('d-none');
            }
        });

        // 3. Persist the active tab in localStorage
        try {
            localStorage.setItem('active_continue_tab', tabId);
        } catch (e) {
            console.error('Failed to persist active tab:', e);
        }
    };

    // Restore previously active tab on page load
    window.onPageLoad( function () {
        try {
            const savedTab = localStorage.getItem('active_continue_tab');
            if (savedTab) {
                const pane = document.getElementById('continue-pane-' + savedTab);
                if (pane) {
                    window.switchContinueTab(savedTab);
                }
            }
        } catch (e) {
            console.error('Failed to restore active tab:', e);
        }
    });
})();



    

    // --- Explicit Window Exports for Inline HTML ---

  })();
} catch (e) {
  console.error("[Fatal Error in dashboard.js]", e);
}
