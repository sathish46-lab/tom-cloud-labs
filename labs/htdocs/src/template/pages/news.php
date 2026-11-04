<?php
/**
 * News page UI — Cybersecurity news aggregation dashboard.
 * Included by _master.php via generatePageBody() → app/news.php route.
 * Public read — anyone can browse. Admin-only "Fetch now" button for superusers.
 * Supports /news?id=<guid> for detail view.
 */

$user = Session::getUser();
$isAdmin = \AuthMiddleware::isAdmin();
$isViewerLoggedIn = Session::getAuthStatus() === Constants::STATUS_LOGGEDIN;

// CSRF token for mutating requests
$csrfToken = \CsrfProtection::token();

/**
 * Helper function to get severity badge class
 */
function getSeverityClass(string $severity): string {
    switch ($severity) {
        case 'critical': return 'text-bg-soft-danger';
        case 'high': return 'text-bg-soft-danger';
        case 'medium': return 'text-bg-soft-warning';
        case 'low': return 'text-bg-soft-info';
        case 'success': return 'text-bg-soft-success';
        default: return 'text-bg-soft-secondary';
    }
}

// Check if we're showing a detail view
$id = $_GET['id'] ?? null;

if ($id) {
    // ── Detail view ──
    $db = DatabaseConnection::getDefaultDatabase();
    $col = $db->selectCollection('news_articles');

    $doc = $col->findOne(
        ['guid' => $id, 'deleted_at' => null],
        ['projection' => [
            'guid' => 1, 'title' => 1, 'link' => 1, 'source' => 1,
            'summary' => 1, 'description' => 1, 'published_at' => 1,
            'fetched_at' => 1, 'category' => 1, 'severity' => 1,
            'cve_ids' => 1, 'raw_json' => 1
        ]]
    );

    if (!$doc) {
        http_response_code(404);
        Session::$pageTitle = 'Article Not Found';
        ?>
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card blur border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-body p-5 text-center">
                            <i class="bx bx-error-circle fs-1 text-danger mb-3"></i>
                            <h1 class="card-title fw-bold mb-3">Article Not Found</h1>
                            <p class="text-muted">The requested news article does not exist or has been removed.</p>
                            <a href="/news" class="btn btn-primary"><i class="bx bx-arrow-back me-1"></i> Back to News</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    $article = $doc;
    ?>
    <!-- Article Detail View -->
    <div class="container py-4" id="news-detail-page">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                <!-- Back button -->
                <div class="mb-3">
                    <button class="btn btn-outline-secondary" onclick="history.back()">
                        <i class="bx bx-arrow-back me-1"></i> Back to News
                    </button>
                </div>

                <!-- Article Header -->
                <div class="card blur border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge <?= getSeverityClass($article['severity'] ?? 'info') ?> rounded-pill fw-bold">
                                <?= htmlspecialchars($article['severity'] ?? 'info') ?>
                            </span>
                            <span class="badge text-bg-soft-primary rounded-pill fw-bold">
                                <?= htmlspecialchars($article['source'] ?? 'unknown') ?>
                            </span>
                            <span class="badge text-bg-soft-info rounded-pill fw-bold">
                                <?= htmlspecialchars($article['category'] ?? 'news') ?>
                            </span>
                        </div>

                        <h1 class="card-title fw-bold mb-3"><?= htmlspecialchars($article['title'] ?? '') ?></h1>

                        <?php if (!empty($article['link'])): ?>
                            <p class="mb-2">
                                <a href="<?= htmlspecialchars($article['link']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                    <i class="bx bx-link-alt me-1"></i> View Source Article
                                </a>
                            </p>
                        <?php endif; ?>

                        <div class="text-muted small mb-3">
                            <i class="bx bx-time-five me-1"></i>
                            Published: <?= $article['published_at'] ? TimeHelper::format($article['published_at']) : 'Unknown' ?>
                            | Fetched: <?= $article['fetched_at'] ? TimeHelper::format($article['fetched_at']) : 'Unknown' ?>
                        </div>

                        <?php if (!empty($article['cve_ids'])): ?>
                            <div class="mb-3">
                                <strong>CVE References: </strong>
                                <?php foreach ($article['cve_ids'] as $cve): ?>
                                    <span class="badge bg-info text-dark rounded-pill"><?= htmlspecialchars($cve) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Article Content -->
                <div class="card blur border-0 shadow-lg rounded-4 overflow-hidden mt-4">
                    <div class="card-body p-4">
                        <?php if (!empty($article['summary'])): ?>
                            <h5 class="fw-bold mb-3">Summary</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($article['summary'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($article['description']) && $article['description'] !== $article['summary']): ?>
                            <h5 class="fw-bold mt-4 mb-3">Description</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($article['description'])) ?></p>
                        <?php endif; ?>

                        <!-- Raw JSON for debugging (admin only) -->
                        <?php if ($isAdmin && !empty($article['raw_json'])): ?>
                            <details class="mt-4">
                                <summary class="fw-bold text-muted" style="cursor: pointer;">View Raw Data (JSON)</summary>
                                <pre class="bg-dark bg-opacity-25 p-3 rounded mt-2" style="font-size: 0.8rem; overflow-x: auto;"><?= htmlspecialchars(json_encode($article['raw_json'], JSON_PRETTY_PRINT)) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Delete Button -->
                <?php if ($isAdmin): ?>
                    <div class="mt-4">
                        <button class="btn btn-outline-danger news-delete-btn" data-guid="<?= htmlspecialchars($article['guid']) ?>">
                            <i class="bx bx-trash me-1"></i> Delete Article
                        </button>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script id="csrf-token">
        window.csrfToken = '<?= $csrfToken ?>';
        window.TomIsAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="/assets/js/app.js"></script>

    <script>
    window.onPageLoad(() => {
        if (typeof initNewsPage === 'function') {
            initNewsPage();
        }
    });
    </script>
    <?php
    return;
}

// ── List view ──
// Category and severity options for filter dropdowns
$categoryOptions = [
    'news' => 'News',
    'cve' => 'CVE',
    'malware' => 'Malware',
    'breach' => 'Breach',
    'exploit' => 'Exploit',
    'research' => 'Research',
    'policy' => 'Policy',
];

$severityOptions = [
    'info' => 'Info',
    'success' => 'Success/Fixed',
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical',
];

// Get initial filters from query string
$initialSource = $_GET['source'] ?? '';
$initialCategory = $_GET['category'] ?? '';
$initialSeverity = $_GET['severity'] ?? '';
$initialSearch = $_GET['q'] ?? '';
// Default news tab — per-user preference stored in users.ui_preferences.news_default_tab
// (chosen over localStorage: it applies server-side on full reload with no JS
// flash and follows the account across devices). Query string still overrides.
// Preference value format: "source|date_filter" (e.g. "github-advisories|today" or "|today")
$newsTab = 'today';
$newsPrefSource = '';
$newsPrefDateFilter = '';
try {
    $sessionUser = $_SESSION['username'] ?? null;
    if ($sessionUser) {
        $userDoc = \DatabaseConnection::getDefaultDatabase()->users->findOne(
            ['$or' => [['username' => $sessionUser], ['email' => $sessionUser]]],
            ['ui_preferences' => 1]
        );
        // BSONDocument (Mongo driver object) is not is_array() — normalise first
        $prefs = $userDoc && isset($userDoc['ui_preferences'])
            ? json_decode(json_encode($userDoc['ui_preferences']), true)
            : null;
        if (is_array($prefs) && isset($prefs['news_default_tab'])) {
            $prefVal = (string) $prefs['news_default_tab'];
            $prefParts = explode('|', $prefVal, 2);
            $newsPrefSource = $prefParts[0] ?? '';
            $newsPrefDateFilter = $prefParts[1] ?? '';
            // Validate date part
            if (!in_array($newsPrefDateFilter, ['today', 'yesterday', ''], true)) {
                $newsPrefDateFilter = '';
            }
            // Set newsTab for backward-compat (main tabs use it)
            if ($newsPrefDateFilter === 'today' || $newsPrefDateFilter === 'yesterday') {
                $newsTab = $newsPrefDateFilter;
            } elseif ($newsPrefDateFilter === '' && empty($newsPrefSource)) {
                $newsTab = 'today';
            }
        }
    }
} catch (\Throwable $e) { /* preference is optional — fall back to today */ }

// Query string overrides preference
$initialSource = $_GET['source'] ?? $newsPrefSource;
$requestedTab = $_GET['date_filter'] ?? '';

// Resolve final date_filter
if ($initialSource === 'github-advisories') {
    // GitHub tab: Today/Yesterday/All sub-tabs
    // Default to 'today' when no specific date_filter set
    $initialDateFilter = in_array($requestedTab, ['today', 'yesterday'], true) ? $requestedTab : ($newsPrefDateFilter ?: 'today');
} else {
    // Main tabs: Today/Yesterday only
    $initialDateFilter = in_array($requestedTab, ['today', 'yesterday'], true) ? $requestedTab : ($newsPrefDateFilter ?: $newsTab);
}
?>
<style>
/* Filter block: centered — date tabs on top, dropdowns+search row below */
.news-filters { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; }
.news-filter-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: center; }
.news-filter-row select, .news-filter-row input { flex: 0 0 180px; max-width: 180px; }
.news-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.5rem; padding: 1rem 1.5rem; margin-bottom: 1rem; }
.news-empty { text-align: center; padding: 3rem; color: var(--cui-secondary-color); }
.news-date-section { border-bottom: 1px solid var(--cui-border-color); padding-bottom: 0.5rem; margin-bottom: 1rem; }
.news-date-section h6 {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700 !important;
    color: #0d6efb !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
/* news-grid layout lives in _news.scss — JS masonry; no page override */
.date-filter-tabs {
    display: flex;
    gap: 0.4rem;
    background: rgba(var(--cui-body-bg-rgb, 33, 37, 41), 0.4);
    border-radius: 0.75rem;
    padding: 0.5rem;
    border: 1px solid rgba(var(--cui-border-color-rgb, 200, 200, 200), 0.2);
}
.date-tab {
    padding: 0.5rem 1rem;
    border-radius: 0.6rem;
    border: none;
    background: transparent;
    color: rgba(var(--cui-body-color-rgb, 255, 255, 255), 0.6);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.date-tab.active {
    background: var(--cui-primary, #0d6efb);
    color: #fff;
    box-shadow: 0 2px 8px rgba(13, 110, 251, 0.3);
}
.date-tab:hover:not(.active) {
    background: rgba(var(--cui-body-color-rgb, 255, 255, 255), 0.05);
    color: #fff;
}
.github-date-tabs {
    display: flex;
    gap: 0.4rem;
    background: rgba(var(--cui-body-bg-rgb, 33, 37, 41), 0.2);
    border-radius: 0.6rem;
    padding: 0.4rem;
    margin-top: 0.5rem;
    border: 1px solid rgba(var(--cui-border-color-rgb, 200, 200, 200), 0.15);
}
.github-date-tabs .date-tab.btn-sm {
    padding: 0.35rem 0.8rem;
    font-size: 0.75rem;
}
.github-date-tabs .date-tab.active {
    background: var(--cui-primary, #0d6efb);
    color: #fff;
    box-shadow: 0 2px 6px rgba(13, 110, 251, 0.25);
}
.news-card .card-title,
.news-card .card-title a,
.news-card .card-title .stretched-link {
  color: #ffffff !important;
}
.news-card .card-title a {
  text-decoration: none !important;
}
</style>

<div class="container-fluid blur px-4">
    <div class="row align-items-center py-3">
        <div class="col">
            <h1 class="fw-bold theme-text m-0 mb-2 network-header-title">Cybersecurity News</h1>
            <p class="text-body mb-2 mb-0 network-header-desc">
                🚨 Your daily cyber radar! Fresh CVEs 🔥, zero-days ⚡, malware waves 🦠, data breaches 💀 & live exploit alerts — aggregated hourly from 10+ trusted sources like The Hacker News, BleepingComputer, Krebs on Security, NVD & CISA KEV. Zero fluff, pure threat intel! 🛡️✨
            </p>
        </div>
        <?php if ($isAdmin): ?>
        <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm news-fetch-btn">
                <i class="bx bx-refresh me-1"></i>Fetch Now
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="flex-grow-1 px-3 blur rounded-0 border-0 shadow-none">
    <div class="container learn-section p-3 py-4 py-xl-3">
 <div class="news-filters px-2">
            <!-- Date Filter Tabs - centered on top -->
            <div class="date-filter-tabs">
                <button type="button" class="date-tab<?= $initialDateFilter === 'today' && empty($initialSource) ? ' active' : '' ?>" id="news-date-filter-today" data-filter="today">
                    Today News
                </button>
                <button type="button" class="date-tab<?= $initialDateFilter === 'yesterday' && empty($initialSource) ? ' active' : '' ?>" id="news-date-filter-yesterday" data-filter="yesterday">
                    Yesterday News
                </button>
                <button type="button" class="date-tab<?= $initialSource === 'github-advisories' ? ' active' : '' ?>" id="news-date-filter-github" data-filter="github-advisories" data-source="github-advisories">
                    GitHub Advisories
                </button>
            </div>

            <!-- GitHub Advisories date sub-tabs — shown only when GitHub tab is active -->
            <div class="github-date-tabs<?= ($initialSource !== 'github-advisories') ? ' d-none' : '' ?>">
                <button type="button" class="date-tab btn-sm<?= ($initialDateFilter === 'today' && $initialSource === 'github-advisories') ? ' active' : '' ?>" id="news-github-today" data-filter="today" data-source="github-advisories">
                    Today
                </button>
                <button type="button" class="date-tab btn-sm<?= ($initialDateFilter === 'yesterday' && $initialSource === 'github-advisories') ? ' active' : '' ?>" id="news-github-yesterday" data-filter="yesterday" data-source="github-advisories">
                    Yesterday
                </button>
                <button type="button" class="date-tab btn-sm<?= ($initialSource === 'github-advisories' && empty($initialDateFilter)) ? ' active' : '' ?>" id="news-github-all" data-filter="all" data-source="github-advisories">
                    All
                </button>
            </div>

            <div class="news-filter-row">
                <select class="form-select form-select-sm" id="news-source-filter" name="source">
                <option value="">All Sources</option>
                <option value="thehackernews" <?= $initialSource === 'thehackernews' ? 'selected' : '' ?>>The Hacker News</option>
                <option value="bleepingcomputer" <?= $initialSource === 'bleepingcomputer' ? 'selected' : '' ?>>BleepingComputer</option>
                <option value="krebsonsecurity" <?= $initialSource === 'krebsonsecurity' ? 'selected' : '' ?>>Krebs on Security</option>
                <option value="securityweek" <?= $initialSource === 'securityweek' ? 'selected' : '' ?>>SecurityWeek</option>
                <option value="cybersecuritynews" <?= $initialSource === 'cybersecuritynews' ? 'selected' : '' ?>>The Cyber Security News</option>
                <option value="securityaffairs" <?= $initialSource === 'securityaffairs' ? 'selected' : '' ?>>Security Affairs</option>
                <option value="cybersecuritydive" <?= $initialSource === 'cybersecuritydive' ? 'selected' : '' ?>>Cybersecurity Dive</option>
                <option value="theverge-security" <?= $initialSource === 'theverge-security' ? 'selected' : '' ?>>The Verge Security</option>
                <option value="nvd-cve" <?= $initialSource === 'nvd-cve' ? 'selected' : '' ?>>NVD CVE</option>
                <option value="cisa-kev" <?= $initialSource === 'cisa-kev' ? 'selected' : '' ?>>CISA KEV</option>
            </select>

            <select class="form-select form-select-sm" id="news-category-filter" name="category">
                <option value="">All Categories</option>
                <?php foreach ($categoryOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $initialCategory === $val ? 'selected' : '' ?>>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-select form-select-sm" id="news-severity-filter" name="severity">
                <option value="">All Severities</option>
                <?php foreach ($severityOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $initialSeverity === $val ? 'selected' : '' ?>>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" class="form-control form-control-sm" id="news-search" name="q" placeholder="Search titles..." value="<?= htmlspecialchars($initialSearch) ?>">
            </div>
        </div>

        <div id="news-list">
            <?php
            $_GET['limit'] = 10;
            $_GET['group_by_date'] = 1;
            if ($initialSource === 'github-advisories') {
                $_GET['source'] = 'github-advisories';
                $_GET['date_filter'] = $initialDateFilter; // may be null (All) or today/yesterday
            } else {
                $_GET['source'] = $initialSource;
                $_GET['date_filter'] = $initialDateFilter;
            }
            include __DIR__ . '/../partials/_news_list.php';
            ?>
        </div>

        <div id="news-more"></div>
    </div>
</div>

<script id="csrf-token">
    window.csrfToken = '<?= $csrfToken ?>';
    window.TomIsAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
</script>

<!-- app.js NOT included here: _master.php's footer already loads it — a second copy throws
     "SyntaxError: currentDeleteIp has already been declared" and kills the duplicate script. -->

<script>
window.onPageLoad(function() {
    initNews();
});

let isLoading = false;
let loadedGuids = new Set();
let hasMore = true;
let observer = null;

function initNews() {
    const container = document.getElementById('news-list-container');
    // window.onPageLoad fires initNews on both DOMContentLoaded AND
    // htmx:afterSettle on the same page load — run once per DOM. After an
    // HTMX navigation onto /news the container is a fresh element, so this
    // guard naturally resets.
    if (container && container.dataset.newsInit === '1') return;
    if (container) container.dataset.newsInit = '1';

    // Only fetch on an explicit user scroll — never auto-fetch on page load
    // (browser scroll restoration on reload must not start a fetch).
    // The scroll handler is the real trigger: when the user scrolls the
    // sentinel into view and there are more articles, loadMore() runs.
    var loadScrollY = window.scrollY;
    window.addEventListener('scroll', function() {
        if (window.scrollY !== loadScrollY) window._newsUserScrolled = true;
        if (!window._newsUserScrolled || isLoading || !hasMore) return;
        var sentinel = document.getElementById('news-sentinel');
        if (!sentinel) return;
        var rect = sentinel.getBoundingClientRect();
        var viewHeight = window.innerHeight || document.documentElement.clientHeight;
        if (rect.top < viewHeight && rect.bottom >= 0) {
            loadMore();
        }
    }, { passive: true });

    const sourceFilter = document.getElementById('news-source-filter');
    const categoryFilter = document.getElementById('news-category-filter');
    const severityFilter = document.getElementById('news-severity-filter');
    const searchInput = document.getElementById('news-search');

    // Track already-loaded GUIDs to prevent duplicates on infinite scroll
    // (only if container exists yet — HTMX will re-run initNews after swap)
    if (container) {
        document.querySelectorAll('#news-list-container .news-card').forEach(card => {
            const guid = card.getAttribute('data-guid');
            if (guid) loadedGuids.add(guid);
        });
    }

    var hasMoreEl = document.getElementById('news-has-more');
    hasMore = hasMoreEl !== null && hasMoreEl.value === '1';

    function getBaseParams() {
        const params = new URLSearchParams();
        params.set('source', sourceFilter ? sourceFilter.value : '');
        params.set('category', categoryFilter ? categoryFilter.value : '');
        params.set('severity', severityFilter ? severityFilter.value : '');
        params.set('q', searchInput ? searchInput.value : '');
        params.set('limit', 10);
        params.set('group_by_date', 1);
        var todayTab = document.getElementById('news-date-filter-today');
        var yesterdayTab = document.getElementById('news-date-filter-yesterday');
        var githubTab = document.getElementById('news-date-filter-github');
        var githubTodayTab = document.getElementById('news-github-today');
        var githubYesterdayTab = document.getElementById('news-github-yesterday');
        var githubAllTab = document.getElementById('news-github-all');

        // GitHub Advisories tab: filter by source, with optional Today/Yesterday/All sub-tabs
        var githubAnyActive = (githubTab && githubTab.classList.contains('active')) ||
                              (githubTodayTab && githubTodayTab.classList.contains('active')) ||
                              (githubYesterdayTab && githubYesterdayTab.classList.contains('active')) ||
                              (githubAllTab && githubAllTab.classList.contains('active'));
        if (githubAnyActive) {
            params.set('source', 'github-advisories');
            if (githubTodayTab && githubTodayTab.classList.contains('active')) {
                params.set('date_filter', 'today');
            } else if (githubYesterdayTab && githubYesterdayTab.classList.contains('active')) {
                params.set('date_filter', 'yesterday');
            } else {
                params.set('date_filter', ''); // All GitHub advisories
            }
        } else {
            params.set('date_filter', (todayTab && todayTab.classList.contains('active')) ? 'today' : (yesterdayTab && yesterdayTab.classList.contains('active')) ? 'yesterday' : 'today');
        }
        return params;
    }

    // Bind element handlers once per element — window.onPageLoad fires
    // initNews on both DOMContentLoaded AND htmx:afterSettle (and re-runs it
    // after every HTMX navigation onto this page), so unguarded listeners
    // would fire twice per interaction.
    if (sourceFilter && !sourceFilter.dataset.newsBound) {
        sourceFilter.dataset.newsBound = '1';
        sourceFilter.addEventListener('change', function() {
            htmxLoadList(getBaseParams(), true);
        });
    }

    if (categoryFilter && !categoryFilter.dataset.newsBound) {
        categoryFilter.dataset.newsBound = '1';
        categoryFilter.addEventListener('change', function() {
            htmxLoadList(getBaseParams(), true);
        });
    }

    if (severityFilter && !severityFilter.dataset.newsBound) {
        severityFilter.dataset.newsBound = '1';
        severityFilter.addEventListener('change', function() {
            htmxLoadList(getBaseParams(), true);
        });
    }

    if (searchInput && !searchInput.dataset.newsBound) {
        searchInput.dataset.newsBound = '1';
        let searchTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                htmxLoadList(getBaseParams(), true);
            }, 300);
        });
    }

    // Date tab click handlers
    document.querySelectorAll('.date-tab').forEach(tab => {
        if (!tab.dataset.newsBound) {
            tab.dataset.newsBound = '1';
            tab.addEventListener('click', function(e) {
                // Remove active class from all tabs
                document.querySelectorAll('.date-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                // When clicking a GitHub sub-tab (Today/Yesterday/All),
                // keep the parent "GitHub Advisories" tab active too
                var githubTab = document.getElementById('news-date-filter-github');
                if (this.id && this.id.startsWith('news-github-')) {
                    if (githubTab) githubTab.classList.add('active');
                }

                // Show/hide GitHub date sub-tabs
                var githubSubTabs = document.querySelector('.github-date-tabs');
                if (githubSubTabs) {
                    if (this.id === 'news-date-filter-github') {
                        githubSubTabs.classList.remove('d-none');
                    } else if (this.id === 'news-date-filter-today' || this.id === 'news-date-filter-yesterday') {
                        githubSubTabs.classList.add('d-none');
                    }
                    // GitHub sub-tabs (news-github-today/yesterday/all): leave visible
                }

                // Persist chosen tab as the user's default (server-side pref —
                // applied by PHP on full reload, saved per account not per browser)
                // Save as "source|date_filter" so GitHub Today/Yesterday/All context survives reload
                var tabValue = this.getAttribute('data-filter') || 'today';
                var srcVal = this.getAttribute('data-source') || '';
                // Parent GitHub tab has data-filter="github-advisories" → default to Today sub-tab
                if (tabValue === 'github-advisories') {
                    tabValue = 'today';
                    // Also click the Today sub-tab element to activate it
                    var githubTodayTab = document.getElementById('news-github-today');
                    if (githubTodayTab && !githubTodayTab.classList.contains('active')) {
                        // Remove active from all sub-tabs first
                        document.querySelectorAll('.github-date-tabs .date-tab').forEach(t => t.classList.remove('active'));
                        githubTodayTab.classList.add('active');
                    }
                }
                saveNewsTabPref(srcVal, tabValue);

                htmxLoadList(getBaseParams(), true);
            });
        }
    });

    // Delete + Fetch now: document-level delegated listeners, bound ONCE
    // globally. document listeners persist across HTMX swaps, so binding them
    // per initNews run would double-fire each click (confirm/alert twice).
    if (!window._newsDocBound) {
        window._newsDocBound = true;
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.news-delete-btn');
            if (btn) handleNewsDelete(btn);
            const fetchBtn = e.target.closest('.news-fetch-btn');
            if (fetchBtn) handleNewsFetch(fetchBtn);
        });
    }

    // Infinite scroll observer

    initObserver();

    // Masonry layout (roadmap dashboard style) + re-run once fonts settle
    newsMasonry();
    setTimeout(newsMasonry, 60);
}

// CSRF token lives in the <meta name="csrf-token"> tag (no window.csrfToken global)
function getNewsCsrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// Save the news default-tab preference (users.ui_preferences.news_default_tab via
// /api/user/preference_save) — value stored as "source|date_filter"
// (e.g. "github-advisories|today" or "|today" or "github-advisories|" for All)
// date_filter can be: today | yesterday | all | '' (empty = all GitHub advisories)
function saveNewsTabPref(src, tab) {
    const token = getNewsCsrf();
    if (!token || (tab !== 'today' && tab !== 'yesterday' && tab !== 'all' && tab !== '')) return;
    var value = (src || '') + '|' + tab;
    try {
        fetch('/api/user/preference_save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': token },
            body: new URLSearchParams({ preference_id: 'news_default_tab', value: value })
        }).catch(function() {});
    } catch (err) { /* preference save must never break tab switching */ }
}

// Delete one article — invoked from the single document-level delegated listener
function handleNewsDelete(btn) {
    const guid = btn.dataset.guid;
    if (!guid) return;

    if (!confirm('Delete this article from the news feed?')) return;

    fetch('/api/news/delete.php', {
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
                loadedGuids.delete(guid);
            } else {
                alert('Delete failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('delete:', err);
            alert('Delete failed');
        });
}

// Manual "Fetch Now" — invoked from the single document-level delegated listener
function handleNewsFetch(btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Fetching...';

    fetch('/api/news/fetch.php', {
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
            const d = (typeof data.data === 'object' && data.data !== null) ? data.data : {};
            const inserted = d.total_inserted === undefined ? 0 : d.total_inserted;
            const updated = d.total_updated === undefined ? 0 : d.total_updated;
            alert('Fetch complete: ' + inserted + ' inserted, ' + updated + ' updated');
            location.reload();
        })
        .catch(err => {
            alert('Fetch failed');
            console.error(err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-refresh me-1"></i>Fetch Now';
        });
}

// Masonry layout — same packed-column engine as the roadmap dashboard (rmMasonry)
function newsMasonry() {
    var container = document.getElementById('news-list-container');
    if (!container) return;

    // Collect direct .news-card children (no :scope — old WebKit quirks)
    var cards = [];
    for (var ci = 0; ci < container.children.length; ci++) {
        var child = container.children[ci];
        if (child.classList && child.classList.contains('news-card')) {
            cards.push(child);
        }
    }

    try {
        var containerWidth = container.offsetWidth;
        var gap = 20;
        var colCount = containerWidth >= 900 ? 3 : containerWidth >= 600 ? 2 : 1;
        var colWidth = (containerWidth - (colCount - 1) * gap) / colCount;
        var colHeights = new Array(colCount).fill(0);

        cards.forEach(function(card) {
            card.style.width = colWidth + 'px';
            card.style.position = 'absolute';

            // Find shortest column
            var minIdx = 0;
            for (var i = 1; i < colHeights.length; i++) {
                if (colHeights[i] < colHeights[minIdx]) minIdx = i;
            }

            var top = colHeights[minIdx];
            card.style.left = (minIdx * (colWidth + gap)) + 'px';
            card.style.top = top + 'px';

            colHeights[minIdx] = top + card.offsetHeight + gap;
        });

        // Sentinel row: full width, always below the tallest column
        var sentinelWrapper = document.getElementById('news-sentinel-wrapper');
        var extra = 0;
        if (sentinelWrapper && container.contains(sentinelWrapper)) {
            sentinelWrapper.style.position = 'absolute';
            sentinelWrapper.style.left = '0px';
            sentinelWrapper.style.width = containerWidth + 'px';
            sentinelWrapper.style.top = Math.max.apply(null, colHeights) + 'px';
            extra = sentinelWrapper.offsetHeight;
        }

        container.style.height = Math.max.apply(null, colHeights) + extra + 'px';
    } catch (err) {
        // Never leave cards invisible on any layout failure
        console.error('newsMasonry:', err);
    }

    // Reveal regardless of success so a JS failure degrades to plain grid, never a blank page
    container.style.visibility = 'visible';
    container.setAttribute('data-masonry-ready', '1');
    window._newsMasonryLastW = container.offsetWidth;
}

// Re-run masonry on container width change — covers window resize AND sidebar
// expand/collapse (CoreUI toggling the sidebar does NOT fire window resize).
function scheduleNewsMasonry() {
    clearTimeout(window._newsMasonryTimer);
    window._newsMasonryTimer = setTimeout(newsMasonry, 120);
}

if (!window._newsMasonryListenInit) {
    window._newsMasonryListenInit = true;

    if (window.newsMasonryRO && typeof window.newsMasonryRO.disconnect === 'function') {
        try { window.newsMasonryRO.disconnect(); } catch (e) {}
    }

    // Primary: observe the container itself (fires during/after sidebar animation)
    if (typeof ResizeObserver === 'function') {
        var ro = new ResizeObserver(function(entries) {
            var w = entries && entries.length ? entries[entries.length - 1].contentRect.width : 0;
            // Ignore height-only notifications caused by our own height setting (loop guard)
            if (w && typeof window._newsMasonryLastW === 'number' && Math.abs(w - window._newsMasonryLastW) < 2) return;
            scheduleNewsMasonry();
        });
        var newsContainer = document.getElementById('news-list-container');
        if (newsContainer) {
            try { ro.observe(newsContainer); } catch (e) {}
        }
        window.newsMasonryRO = ro;
    }

    // Fallback for engines without ResizeObserver
    window.addEventListener('resize', scheduleNewsMasonry);

    // Late-loading webfonts change card heights — re-run once fonts settle
    if (typeof document.fonts === 'object' && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
        document.fonts.ready.then(function() {
            setTimeout(newsMasonry, 100);
        });
    }
}

function initObserver() {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
    const sentinel = document.getElementById('news-sentinel');
    if (sentinel && hasMore) {
        // Observer is a backup for the append case (new cards push the
        // sentinel into view). The real trigger is the scroll handler above,
        // which only fetches on an explicit user scroll — never on page load.
        observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && window._newsUserScrolled && !isLoading && hasMore) {
                loadMore();
            }
        }, { threshold: 0.1 });
        observer.observe(sentinel);
    }
}

async function loadMore() {
    if (isLoading || !hasMore || !window._newsUserScrolled) return;
    isLoading = true;
    // Element value helper (avoids optional chaining — breaks older Safari at parse time)
    function newsVal(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    const skip = parseInt(newsVal('news-next-skip') || 0, 10);
    const limit = parseInt(newsVal('news-next-limit') || 10, 10);
    const source = newsVal('news-next-source');
    const category = newsVal('news-next-category');
    const severity = newsVal('news-next-severity');
    const q = newsVal('news-next-q');
    const dateFilterLine = newsVal('news-next-date-filter');
    const groupByDate = newsVal('news-next-group-by-date') === '1';

    const params = new URLSearchParams();
    params.set('skip', skip);
    params.set('limit', limit);
    if (source) params.set('source', source);
    if (category) params.set('category', category);
    if (severity) params.set('severity', severity);
    if (q) params.set('q', q);
    if (dateFilterLine) params.set('date_filter', dateFilterLine);
    params.set('group_by_date', groupByDate ? '1' : '0');

    try {
        const response = await fetch('/news/_list.php?' + params.toString(), {
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('Network error');
        const html = await response.text();

        const temp = document.createElement('div');
        temp.innerHTML = html;

        const newCards = temp.querySelectorAll('.news-card');
        const container = document.getElementById('news-list-container');
        const sentinelWrapper = document.getElementById('news-sentinel-wrapper');

        if (container && newCards.length > 0) {
            // Remove sentinel from container, insert cards before where it was, then re-append sentinel
            if (sentinelWrapper && container.contains(sentinelWrapper)) {
                container.removeChild(sentinelWrapper);
            }

            newCards.forEach(card => {
                const guid = card.getAttribute('data-guid');
                if (guid && loadedGuids.has(guid)) return; // skip duplicate
                loadedGuids.add(guid);
                container.appendChild(card);
            });

            // Re-append sentinel at the end
            if (sentinelWrapper) {
                container.appendChild(sentinelWrapper);
            } else {
                // Sentinel was lost, create a new one
                const newSentinel = document.createElement('div');
                newSentinel.id = 'news-sentinel-wrapper';
                newSentinel.className = 'col-12 text-center mt-4 mb-4';
                newSentinel.innerHTML = '<div class="news-sentinel" id="news-sentinel"><i class="bx bx-loader-circle bx-spin fs-4 text-primary"></i></div>';
                container.appendChild(newSentinel);
            }

            // Update skip value for next load
            const nextSkip = skip + newCards.length;
            const nextSkipEl = document.getElementById('news-next-skip');
            if (nextSkipEl) nextSkipEl.value = nextSkip;

            // Check if there's more
            const newHasMoreEl = temp.querySelector('#news-has-more');
            if (newHasMoreEl) {
                hasMore = newHasMoreEl.value === '1';
                const hasMoreCurrent = document.getElementById('news-has-more');
                if (hasMoreCurrent) hasMoreCurrent.value = newHasMoreEl.value;
            }

            // Re-init observer for the sentinel
            initObserver();
            newsMasonry();

            if (!hasMore) {
                // Replace sentinel with "all loaded" message
                const sentinelEl = document.getElementById('news-sentinel');
                if (sentinelEl) {
                    sentinelEl.parentElement.innerHTML = '<div class="text-center mt-4 mb-4"><div class="news-sentinel" style="opacity:0.5;"><i class="bx bx-check-circle text-success"></i> All news loaded</div></div>';
                }
                newsMasonry();
            }
        }
    } catch (err) {
        console.error('loadMore:', err);
    } finally {
        isLoading = false;
    }
}

function htmxLoadList(params, replace = false) {
    // Each tab switch starts fresh: no auto-fetch until the user scrolls the
    // new tab's list to the bottom.
    if (replace) window._newsUserScrolled = false;
    const url = '/news/_list.php?' + params.toString();
    fetch(url, { credentials: 'same-origin' })
        .then(r => {
            console.log('Fetch response:', r.status, r.ok);
            if (!r.ok) throw new Error('Network error');
            return r.text();
        })
        .then(html => {
            const temp = document.createElement('div');
            temp.innerHTML = html;

            const newCards = temp.querySelectorAll('.news-card');
            let container = document.getElementById('news-list-container');
            // Create container if missing (e.g. initial render had no articles)
            if (!container) {
                const newsListDiv = document.getElementById('news-list');
                if (newsListDiv) {
                    container = document.createElement('div');
                    container.id = 'news-list-container';
                    container.className = 'news-grid';
                    newsListDiv.appendChild(container);
                } else {
                    console.error('htmxLoadList: #news-list not found');
                    return;
                }
            }
            const sentinelWrapper = document.getElementById('news-sentinel-wrapper');

            if (replace) {
                // Full replace
                loadedGuids.clear();
                container.innerHTML = '';
                newCards.forEach(card => {
                    const guid = card.getAttribute('data-guid');
                    if (guid) loadedGuids.add(guid);
                    container.appendChild(card);
                });
                const newsMoreEl = document.getElementById('news-more');
                if (newsMoreEl) newsMoreEl.innerHTML = '';

                const totalDiv = temp.querySelector('#news-total-count');
                if (totalDiv) {
                    const existing = document.getElementById('news-total-count');
                    if (existing) existing.outerHTML = totalDiv.outerHTML;
                }

                // Re-add sentinel wrapper at end
                const newSentinelWrapper = temp.querySelector('#news-sentinel-wrapper');
                if (newSentinelWrapper) {
                    container.appendChild(newSentinelWrapper.cloneNode(true));
                }

                // Refresh hidden state inputs that live outside the container
                // (they survive container.innerHTML=''), so the next tab switch
                // / scroll reads fresh skip / has-more values instead of stale ones.
                ['news-next-skip','news-next-limit','news-next-source','news-next-category','news-next-severity','news-next-q','news-next-date-filter','news-has-more'].forEach(function(id) {
                    var el = temp.querySelector('#' + id);
                    if (el) {
                        var cur = document.getElementById(id);
                        if (cur) cur.outerHTML = el.outerHTML;
                        else container.parentNode.appendChild(el.cloneNode(true));
                    }
                });
                initObserver();
                newsMasonry();
            } else {
                // Append before sentinel
                newCards.forEach(card => {
                    const guid = card.getAttribute('data-guid');
                    if (guid && loadedGuids.has(guid)) return;
                    loadedGuids.add(guid);
                    container.insertBefore(card, sentinelWrapper);
                });
                newsMasonry();

                const newHasMoreEl = temp.querySelector('#news-has-more');
                if (newHasMoreEl) {
                    hasMore = newHasMoreEl.value === '1';
                    const hasMoreCurrent = document.getElementById('news-has-more');
                    if (hasMoreCurrent) hasMoreCurrent.value = newHasMoreEl.value;
                }

                const nextSkipEl = temp.querySelector('#news-next-skip');
                if (nextSkipEl) {
                    const nextSkipCurrent = document.getElementById('news-next-skip');
                    if (nextSkipCurrent) nextSkipCurrent.value = nextSkipEl.value;
                }
            }

            // Update hasMore
            const newHasMoreEl2 = temp.querySelector('#news-has-more');
            if (newHasMoreEl2) {
                hasMore = newHasMoreEl2.value === '1';
            }
        })
        .catch(err => {
            if (err && err.name === 'AbortError') return; // superseded by a newer request — silent
            console.error('htmxLoadList:', err);
            const container = document.getElementById('news-list-container');
            if (container) {
                container.innerHTML = '<div class="news-error"><i class="bx bx-error-circle me-2"></i>' + err.message + '</div>';
            }
        });
}
</script>
