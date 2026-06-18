/**
 * SIDEBAR PERSISTENCE LOGIC
 * Manages the state of the sidebar (narrow/hidden) across page loads.
 */
(function() {
    const sidebarEl = document.querySelector('#sidebar');
    if (!sidebarEl) return;

    if (document.documentElement.classList.contains('sidebar-init-narrow')) {
        sidebarEl.classList.add('sidebar-narrow-unfoldable');
    }

    if (document.documentElement.classList.contains('sidebar-init-hidden')) {
        sidebarEl.classList.add('hide');
        // Release the CSS lock after applying the state
        document.documentElement.classList.remove('sidebar-init-hidden');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const observer = new MutationObserver(() => {
            const isNarrow = sidebarEl.classList.contains('sidebar-narrow-unfoldable') ||
                sidebarEl.classList.contains('sidebar-narrow');
            localStorage.setItem('tom-labs-sidebar-narrow', isNarrow);

            const isHidden = sidebarEl.classList.contains('hide') || sidebarEl.classList.contains('sidebar-hide');
            localStorage.setItem('tom-labs-sidebar-hidden', isHidden);
        });

        observer.observe(sidebarEl, {
            attributes: true,
            attributeFilter: ['class']
        });
    });
})();
