/**
 * news.js — Cybersecurity news aggregation UI logic
 * Loaded via news.php page template.
 *
 * Uses window.onPageLoad pattern for HTMX compatibility (fires on DOMContentLoaded AND htmx:afterSettle).
 * Must be idempotent — safe to call multiple times.
 */
(function() {
    'use strict';

    const NEWS_API_BASE = '/api/news';

    // Exposed for inline script access
    window.escapeHtml = function(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    window.initNewsPage = function() {
        const grid = document.getElementById('news-grid');
        if (!grid) return;

        // Load initial news
        loadNews();

        // Bind filter events (delegated for dynamic handling)
        grid.addEventListener('change', handleFilterChange);
    };

    function loadNews(params = {}) {
        const grid = document.getElementById('news-grid');
        const source = document.getElementById('news-source-filter')?.value || '';
        const q = document.getElementById('news-search')?.value || '';

        if (source) params.source = source;
        if (q) params.q = q;

        const url = NEWS_API_BASE + '/list.php?' + new URLSearchParams(params);

        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') throw new Error(data.error || 'API error');
                renderNews(data.data || []);
            })
            .catch(err => {
                console.error('loadNews failed:', err);
                grid.innerHTML = '<div class="news-error"><i class="bx bx-error-circle me-2"></i>' + escapeHtml(err.message) + '</div>';
            });
    }

    function renderNews(items) {
        const grid = document.getElementById('news-grid');
        if (!items.length) {
            grid.innerHTML = '<div class="news-empty"><i class="bx bx-news me-2"></i>No articles found.</div>';
            return;
        }
        grid.innerHTML = items.map(renderCard).join('');
    }

    function renderCard(item) {
        const published = item.published_at ? new Date(item.published_at.$date || item.published_at) : new Date();
        const publishedStr = published.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

        const severityClass = getSeverityClass(item.severity);

        let cveHtml = '';
        if (item.cve_ids && item.cve_ids.length) {
            cveHtml = item.cve_ids.map(c => `<span class="badge bg-info text-dark rounded-pill me-1">${escapeHtml(c)}</span>`).join('');
        }

        const deleteMenu = window.TomIsAdmin ? `
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bx bx-dots-vertical-rounded"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><button class="dropdown-item text-danger news-delete-btn" data-guid="${escapeHtml(item.guid)}">Delete</button></li>
            </ul>
        ` : '';

        return `
            <div class="card blur border-0 shadow-lg rounded-4 overflow-hidden" data-guid="${escapeHtml(item.guid)}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-${severityClass} text-white rounded-pill fw-bold small">${escapeHtml(item.severity || 'info')}</span>
                            <span class="badge bg-info text-dark rounded-pill fw-bold small">${escapeHtml(item.source || 'unknown')}</span>
                        </div>
                        ${deleteMenu}
                    </div>
                    <h6 class="fw-bold mt-2 mb-1">
                        <a href="${escapeHtml(item.link || '#')}" target="_blank" rel="noopener" class="text-decoration-none stretched-link">${escapeHtml(item.title || 'Untitled')}</a>
                    </h6>
                    <p class="text-muted small mb-2">${escapeHtml(item.summary || '')}</p>
                    ${cveHtml}
                    <small class="text-muted"><i class="bx bx-time-five me-1"></i>${publishedStr}</small>
                </div>
            </div>
        `;
    }

    function getSeverityClass(sev) {
        const map = {
            critical: 'danger',
            high: 'danger',
            medium: 'warning',
            low: 'info',
            success: 'success',
            info: 'secondary'
        };
        return map[sev] || 'secondary';
    }

    function handleFilterChange(e) {
        const target = e.target;
        if (target.id === 'news-source-filter' || target.id === 'news-search') {
            loadNews();
        }
    }

    // Delete button handler (delegated)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.news-delete-btn');
        if (!btn) return;

        e.preventDefault();
        const guid = btn.dataset.guid;
        if (!guid) return;

        if (!confirm('Delete this article from the news feed?')) return;

        fetch(NEWS_API_BASE + '/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': window.csrfToken },
            body: new URLSearchParams({ guid: guid })
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const card = document.querySelector('[data-guid="' + guid + '"]');
                    if (card) card.remove();
                } else {
                    alert('Delete failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('delete failed:', err);
                alert('Delete failed');
            });
    });

    // Fetch now button (delegated)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.news-fetch-btn');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Fetching...';

        fetch(NEWS_API_BASE + '/fetch.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': window.csrfToken }
        })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert('Fetch failed: ' + (data.error || 'Unknown error'));
                    return;
                }
                const inserted = data.data?.total_inserted ?? 0;
                const updated = data.data?.total_updated ?? 0;
                alert('Fetch complete: ' + inserted + ' inserted, ' + updated + ' updated');
                loadNews();
            })
            .catch(err => {
                alert('Fetch failed');
                console.error(err);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-refresh me-1"></i>Fetch Now';
            });
    });

})();