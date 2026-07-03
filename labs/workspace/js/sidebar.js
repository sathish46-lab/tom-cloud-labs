/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


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

    window.onPageLoad( () => {
        let saveTimeoutNarrow;
        let saveTimeoutHidden;
        let prevNarrow = sidebarEl.classList.contains('sidebar-narrow-unfoldable') || sidebarEl.classList.contains('sidebar-narrow');
        let prevHidden = sidebarEl.classList.contains('hide') || sidebarEl.classList.contains('sidebar-hide');

        const observer = new MutationObserver(() => {
            const isNarrow = sidebarEl.classList.contains('sidebar-narrow-unfoldable') ||
                sidebarEl.classList.contains('sidebar-narrow');
                
            if (prevNarrow !== isNarrow) {
                prevNarrow = isNarrow;
                
                clearTimeout(saveTimeoutNarrow);
                saveTimeoutNarrow = setTimeout(() => {
                    var data = new FormData();
                    data.append('preference_id', 'sidebar_unfoldable');
                    data.append('value', isNarrow ? 'true' : 'false');
                    fetch('/api/user/preference_save', {
                        method: 'POST',
                        body: data
                    }).catch(console.error);
                }, 500);
            }

            const isHidden = sidebarEl.classList.contains('hide') || sidebarEl.classList.contains('sidebar-hide');
            
            if (prevHidden !== isHidden) {
                prevHidden = isHidden;
                
                clearTimeout(saveTimeoutHidden);
                saveTimeoutHidden = setTimeout(() => {
                    var data = new FormData();
                    data.append('preference_id', 'sidebar_hidden');
                    data.append('value', isHidden ? 'true' : 'false');
                    fetch('/api/user/preference_save', {
                        method: 'POST',
                        body: data
                    }).catch(console.error);
                }, 500);
            }
        });

        observer.observe(sidebarEl, {
            attributes: true,
            attributeFilter: ['class']
        });
    });
})();


    

    // --- Explicit Window Exports for Inline HTML ---

  })();
} catch (e) {
  console.error("[Fatal Error in sidebar.js]", e);
}
