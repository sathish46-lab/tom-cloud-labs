// Instance Dashboard tab switching.
// Same pattern as manage.js — event delegation, htmx re-init, cached tabs.

function syncDashboardCounts(container) {
    const el = container.querySelector('[data-templates-count]');
    if (!el) return;
    const tCount = el.getAttribute('data-templates-count');
    const trCount = el.getAttribute('data-trash-count');
    const tBadge = document.getElementById('templatesCount');
    const trBadge = document.getElementById('trashCount');
    if (tBadge && tCount !== null) tBadge.textContent = tCount;
    if (trBadge && trCount !== null) trBadge.textContent = trCount;
}
window.syncDashboardCounts = syncDashboardCounts;

function initDashboardTabs() {
    const tabs = document.querySelectorAll('.instance-dashboard-tab');
    const contentContainer = document.getElementById('instanceDashboardContent');
    if (tabs.length === 0 || !contentContainer) return;

    if (!window.__dashboardState) {
        window.__dashboardState = { savedHtml: {}, fetching: {} };
    }
    const state = window.__dashboardState;

    window.__loadDashboardTab = async (tabName) => {
        const container = document.getElementById('instanceDashboardContent');
        if (!container) return;

        tabs.forEach(t => t.classList.remove('active'));
        const activeBtn = document.querySelector(`.instance-dashboard-tab[data-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');

        const newUrl = tabName === 'trash' ? '/instances/trash' : '/instances/templates';
        if (window.location.pathname !== newUrl) {
            window.history.pushState(null, '', newUrl);
        }

        if (state.savedHtml[tabName]) {
            container.innerHTML = state.savedHtml[tabName];
            return;
        }

        if (state.fetching[tabName]) return;
        state.fetching[tabName] = true;

        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-secondary" role="status"></div></div>';

        const apiUrl = tabName === 'trash' ? '/api/instances/trash_tab' : '/api/instances/templates_tab';
        try {
            const res = await fetch(apiUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();
            if (res.ok && html.trim()) {
                state.savedHtml[tabName] = html;
                container.innerHTML = html;
                if (typeof htmx !== 'undefined') htmx.process(container);
                syncDashboardCounts(container);
            } else {
                container.innerHTML = '<div class="alert alert-danger">Failed to load.</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="alert alert-danger">Network error.</div>';
        } finally {
            state.fetching[tabName] = false;
        }
    };

    const currentPath = window.location.pathname;
    const initialTab = currentPath.includes('/instances/trash') ? 'trash' : 'templates';
    window.__loadDashboardTab(initialTab);
}

// Delegated click handler — survives htmx swaps
if (!window.__dashboardTabDelegated) {
    window.__dashboardTabDelegated = true;
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.instance-dashboard-tab');
        if (!btn) return;
        e.preventDefault();
        const tabName = btn.dataset.tab;
        if (tabName && window.__loadDashboardTab) {
            window.__loadDashboardTab(tabName);
        }
    });
}

// Init on full load and htmx swaps
if (document.readyState !== 'loading') {
    initDashboardTabs();
}
document.addEventListener('DOMContentLoaded', initDashboardTabs);
document.addEventListener('htmx:afterSettle', initDashboardTabs);
document.addEventListener('htmx:load', initDashboardTabs);

// --- Fork ---
async function submitFork() {
    const form = document.getElementById('forkLabForm');
    const btn = form.querySelector('button[type="button"]');
    const sourceId = form.querySelector('select[name="source_id"]').value;
    if (!sourceId) return alert('Please select a lab to fork.');

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Forking...';

    try {
        const formData = new FormData(form);
        const response = await fetch('/api/instances/fork', { method: 'POST', body: formData });

        if (response.ok) {
            const html = await response.text();
            if (html.trim()) {
                const grid = document.getElementById('templatesGrid');
                if (grid) {
                    const noTemplates = grid.querySelector('.col-12');
                    if (noTemplates) noTemplates.remove();
                    grid.insertAdjacentHTML('beforeend', html);
                }
            }
            // Invalidate cache so next tab load is fresh
            if (window.__dashboardState) {
                window.__dashboardState.savedHtml = {};
                window.__dashboardState.fetching = {};
            }
            const modalEl = document.getElementById('forkLabModal');
            const modal = coreui.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            form.reset();
        } else {
            const errorMsg = await response.text();
            alert('Error: ' + (errorMsg || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
        alert('A network error occurred.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
window.submitFork = submitFork;

// --- Trash ---
function trashInstance(slug) {
    const card = document.getElementById('instance-card-' + slug);
    const deployStatus = card?.getAttribute('data-deploy-status') || 'none';
    const isRunning = ['running', 'deploying', 'starting'].includes(deployStatus);

    const modal = document.getElementById('trashConfirmModal');
    const warningBox = document.getElementById('trashWarningBox');
    const title = document.getElementById('trashModalTitle');
    const desc = document.getElementById('trashModalDesc');
    const confirmBtn = document.getElementById('trashConfirmBtn');

    if (isRunning) {
        title.textContent = 'Stop & Move to Trash?';
        desc.innerHTML = 'This instance is <strong>currently running</strong>. It will be stopped and all active connections (SSH, Code Server, VPN) will be <strong>terminated</strong>.';
        warningBox.classList.remove('d-none');
        confirmBtn.innerHTML = '<i class="bx bx-trash me-1"></i> Stop & Trash';
    } else {
        title.textContent = 'Move to Trash?';
        desc.innerHTML = 'Are you sure you want to move this template to trash?';
        warningBox.classList.add('d-none');
        confirmBtn.innerHTML = '<i class="bx bx-trash me-1"></i> Move to Trash';
    }

    confirmBtn.onclick = async () => {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1" role="status"></span> Processing...';

        try {
            const res = await fetch('/api/instances/trash', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug: slug })
            });
            const data = await res.json();
            if (data.status === 'success') {
                const modalInstance = coreui.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
                if (card) card.remove();
                if (window.__dashboardState) {
                    window.__dashboardState.savedHtml = {};
                    window.__dashboardState.fetching = {};
                }
                window.__loadDashboardTab('templates');
            } else {
                alert('Error: ' + (data.error || 'Failed'));
            }
        } catch (e) {
            alert('Network error.');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bx bx-trash me-1"></i> Move to Trash';
        }
    };

    const bsModal = new coreui.Modal(modal);
    bsModal.show();
}
window.trashInstance = trashInstance;

// --- Restore ---
async function restoreInstance(slug) {
    try {
        const res = await fetch('/api/instances/restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: slug })
        });
        const data = await res.json();
        if (data.status === 'success') {
            const card = document.getElementById('trash-card-' + slug);
            if (card) card.remove();
            if (window.__dashboardState) {
                window.__dashboardState.savedHtml = {};
                window.__dashboardState.fetching = {};
            }
            window.__loadDashboardTab('trash');
        } else {
            alert('Error: ' + (data.error || 'Failed'));
        }
    } catch (e) {
        alert('Network error.');
    }
}
window.restoreInstance = restoreInstance;

// --- Permanent Delete ---
async function permanentDelete(slug) {
    if (!confirm('Permanently delete this template? This cannot be undone.')) return;
    try {
        const res = await fetch('/api/instances/permanent_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: slug })
        });
        const data = await res.json();
        if (data.status === 'success') {
            const card = document.getElementById('trash-card-' + slug);
            if (card) card.remove();
            if (window.__dashboardState) {
                window.__dashboardState.savedHtml = {};
                window.__dashboardState.fetching = {};
            }
            window.__loadDashboardTab('trash');
        } else {
            alert('Error: ' + (data.error || 'Failed'));
        }
    } catch (e) {
        alert('Network error.');
    }
}
window.permanentDelete = permanentDelete;
