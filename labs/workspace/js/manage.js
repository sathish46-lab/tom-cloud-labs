document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.manage-tab-btn');
    const contentContainer = document.getElementById('instanceTabsContent');
    
    if (tabs.length === 0 || !contentContainer) return;

    // Determine current slug from URL path (e.g., /instances/my-lab)
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const slug = pathParts.length >= 2 && pathParts[0] === 'instances' ? pathParts[1] : null;

    if (!slug) return;

    // Find the default active tab from URL path, or default to configuration
    let activeTab = 'configuration';
    const urlTab = pathParts.length >= 3 ? pathParts[2] : null;
    if (urlTab && Array.from(tabs).some(t => t.dataset.tab === urlTab)) {
        activeTab = urlTab;
    }

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
            const response = await fetch(`/instances/${slug}/${tabName}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            
            if (data.status === 'success') {
                contentContainer.innerHTML = data.html;
            } else {
                contentContainer.innerHTML = `
                    <div class="alert alert-danger">
                        Failed to load tab: ${data.error || 'Unknown error'}
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

    // Attach click events
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = tab.dataset.tab;
            if (tabName) {
                loadTab(tabName);
            }
        });
    });

    // Handle browser back/forward
    window.addEventListener('popstate', () => {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const tab = parts.length >= 3 ? parts[2] : 'configuration';
        loadTab(tab);
    });

    // Initial load
    loadTab(activeTab);
});
