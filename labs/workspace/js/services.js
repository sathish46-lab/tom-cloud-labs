function initServicesGridMasonry() {
    const grid = document.querySelector('#services-masonry-grid');
    if (!grid) return;

    // Destroy existing instance before re-init (important for HTMX swaps)
    if (grid.msnry) {
        grid.msnry.destroy();
        grid.msnry = null;
    }

    const msnry = new Masonry(grid, {
        itemSelector: '.services-card-item',
        columnWidth: '.services-grid-sizer',
        percentPosition: true,
        transitionDuration: '0.4s'
    });

    if (typeof imagesLoaded !== 'undefined') {
        imagesLoaded(grid).on('progress', () => {
            msnry.layout();
        });
    }

    grid.msnry = msnry;

    // Force layout after a short delay to ensure all elements are rendered
    setTimeout(() => { if (grid.msnry) grid.msnry.layout(); }, 100);
}

// Use onPageLoad so it works on both full loads AND HTMX swaps
window.onPageLoad(() => {
    initServicesGridMasonry();

    const container = document.getElementById('services-masonry-grid');
    if (container) {
        const observer = new MutationObserver(() => {
            if (container.msnry) container.msnry.layout();
        });
        observer.observe(container, { childList: true, subtree: true });

        const visibilityObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                if (container.msnry) container.msnry.layout();
            }
        }, { threshold: 0.1 });
        visibilityObserver.observe(container);
    }

    const themeObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'data-coreui-theme') {
                setTimeout(() => {
                    const grid = document.querySelector('#services-masonry-grid');
                    if (grid && grid.msnry) grid.msnry.layout();
                }, 150);
            }
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true });
});
