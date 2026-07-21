// Instance Manager tab switching.
// IMPORTANT: The manage page is often loaded via HTMX (body has hx-boost="true"),
// so #main-content is swapped by AJAX without a full page reload. We therefore:
//   1. Use event DELEGATION on document for tab clicks (survives any DOM swap).
//   2. Re-run init on htmx:afterSettle / htmx:load so tab state stays correct.
//   3. Never rely solely on DOMContentLoaded (it only fires once per full load).

function initInstanceTabs() {
    const tabs = document.querySelectorAll('.manage-tab-btn');
    const contentContainer = document.getElementById('instanceTabsContent');
    if (tabs.length === 0 || !contentContainer) return;

    // Determine current slug from URL path (e.g., /instances/my-lab)
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const slug = pathParts.length >= 2 && pathParts[0] === 'instances' ? pathParts[1] : null;
    if (!slug) return;

    const loadTab = async (tabName) => {
        // Update UI state
        tabs.forEach(t => t.classList.remove('active'));
        const activeBtn = document.querySelector(`.manage-tab-btn[data-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');

        // Show loader
        contentContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-secondary" role="status"></div>
            </div>
        `;

        // Update browser URL cleanly (no page reload)
        const newUrl = `/instances/${slug}/${tabName}`;
        window.history.pushState(null, '', newUrl);

        try {
            const response = await fetch(newUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                contentContainer.innerHTML = await response.text();
            } else {
                contentContainer.innerHTML = `
                    <div class="alert alert-danger">
                        Failed to load tab (HTTP ${response.status}).
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error fetching tab:', error);
            contentContainer.innerHTML = `
                <div class="alert alert-danger">
                    A network error occurred while loading this tab.
                </div>
            `;
        }
    };

    // Expose for htmx re-init / popstate without duplicating listeners.
    window.__loadInstanceTab = loadTab;

    // Browser back/forward
    if (!window.__instancePopstateBound) {
        window.__instancePopstateBound = true;
        window.addEventListener('popstate', () => {
            const parts = window.location.pathname.split('/').filter(Boolean);
            const tab = parts.length >= 3 ? parts[2] : 'configuration';
            if (window.__loadInstanceTab) window.__loadInstanceTab(tab);
        });
    }

    // Auto-load the tab from the URL on a fresh page load (deep links like
    // /instances/tech/files must open the Files tab, not just configuration).
    const urlParts = window.location.pathname.split('/').filter(Boolean);
    const urlTab = urlParts.length >= 3 && urlParts[0] === 'instances' ? urlParts[2] : '';
    const validTabs = ['deployments', 'files', 'configuration', 'build', 'sharing', 'versions'];
    if (validTabs.includes(urlTab)) {
        // Sync the nav active state immediately, then load content.
        tabs.forEach(t => t.classList.remove('active'));
        const activeBtn = document.querySelector(`.manage-tab-btn[data-tab="${urlTab}"]`);
        if (activeBtn) activeBtn.classList.add('active');
        loadTab(urlTab);
    } else {
        // Bare /instances/{slug}: default to configuration.
        loadTab('configuration');
    }
}

// Delegated click handler — works for both full loads and HTMX-swapped content.
if (!window.__instanceTabDelegated) {
    window.__instanceTabDelegated = true;
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.manage-tab-btn');
        if (!btn) return;
        e.preventDefault();
        const tabName = btn.dataset.tab;
        if (tabName && window.__loadInstanceTab) {
            window.__loadInstanceTab(tabName);
        }
    });
}

// Initialise now (full load) and whenever HTMX settles a swap.
if (document.readyState !== 'loading') {
    initInstanceTabs();
}
document.addEventListener('DOMContentLoaded', initInstanceTabs);
document.addEventListener('htmx:afterSettle', initInstanceTabs);
document.addEventListener('htmx:load', initInstanceTabs);
