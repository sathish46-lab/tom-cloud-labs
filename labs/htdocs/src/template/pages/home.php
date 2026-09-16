<?php
$user = Session::getUser();
$avatar = Session::getAvatar();
$fullName = $user?->getFullName() ?? '';
$displayName = !empty($fullName) ? $fullName : ($user?->getUsername() ?? 'User');
$username = $user?->getUsername() ?? '';

$hour = date('H');
if ($hour < 12) $greeting = "Good morning,";
elseif ($hour < 17) $greeting = "Good afternoon,";
elseif ($hour < 21) $greeting = "Good evening,";
else $greeting = "Burning the midnight oil,";

$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$timeStr = $now->format('g:i A');
$dateStr = $now->format('D j M');

$labIconMap = [
    'essentials'     => ['glyph' => 'tom-flask',          'color' => '#ff6b1a'],
    'gui_essentials' => ['glyph' => 'tom-desktop',        'color' => '#ff6b1a'],
    'minio'          => ['glyph' => 'tom-database',       'color' => '#c72c41'],
    'n8n'            => ['glyph' => 'tom-workflow',       'color' => '#ff6d5a'],
    'docker_lab'     => ['glyph' => 'tom-package',        'color' => '#0db7ed'],
    'kali'           => ['glyph' => 'tom-terminal',       'color' => '#256bbe'],
];
?>

<div class="lp-top-dock">
    <header class="lp-menubar blur">
        <div class="lp-mb-left">
            <img class="lp-mb-logo" src="/assets/logo/logo1.png" alt="TomWeb Labs">
            <span class="lp-wordmark">TomWeb Labs</span>
            <span class="lp-plan-pill lp-plan-pill-sm">
                <svg class="icon"><use xlink:href="/assets/icons/duotone.svg#tom-check-circle"></use></svg>
                Pro
            </span>
        </div>
        <div class="lp-mb-right">
            <div class="lp-mcp-dock">
                <button type="button" class="lp-mcp-chip" id="lp-mcp-chip" title="MCP — connect your editor to your labs">
                    <svg class="icon lp-mcp-glyph"><use xlink:href="/assets/icons/duotone.svg#tom-wifi-high"></use></svg>
                    <span class="lp-mcp-label">MCP</span>
                </button>
            </div>
            <span class="lp-clock" id="lp-clock" title="Server time (IST)"><?= $timeStr ?> &middot; <?= $dateStr ?></span>

            <div class="dropdown lp-theme-dock">
                <button type="button" class="lp-icon-btn lp-theme-toggle" aria-expanded="false" data-coreui-toggle="dropdown" aria-label="Theme" title="Theme — light, dark, or auto">
                    <svg class="icon theme-icon-active"><use xlink:href="/assets/icons/duotone.svg#tom-moon"></use></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end blur" style="--cui-dropdown-min-width: 8rem; overflow: visible;">
                    <li class="theme-mode-item">
                        <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="light">
                            <svg class="icon icon-lg me-auto my-auto"><use xlink:href="/assets/icons/duotone.svg#tom-sun"></use></svg>
                            <span>Light</span>
                        </button>
                    </li>
                    <li class="theme-mode-item">
                        <button class="dropdown-item d-flex align-items-center active" type="button" data-coreui-theme-value="dark">
                            <svg class="icon icon-lg me-auto my-auto"><use xlink:href="/assets/icons/duotone.svg#tom-moon"></use></svg>
                            <span>Dark</span>
                        </button>
                    </li>
                    <li class="theme-mode-item">
                        <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="auto">
                            <svg class="icon icon-lg me-auto my-auto"><use xlink:href="/assets/icons/duotone.svg#tom-palette"></use></svg>
                            Auto
                        </button>
                    </li>
                </ul>
            </div>

            <div class="dropdown lp-user-dock">
                <button type="button" class="lp-icon-btn lp-user-toggle" id="lp-user-toggle" data-coreui-toggle="dropdown" aria-expanded="false" aria-label="Account" title="Account">
                    <svg class="icon"><use xlink:href="/assets/icons/duotone.svg#tom-gear-six"></use></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end blur" aria-labelledby="lp-user-toggle">
                    <li><a class="dropdown-item" href="/profile"><svg class="icon me-2"><use xlink:href="/assets/icons/duotone.svg#tom-user"></use></svg>My Account</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/?logout"><svg class="icon me-2"><use xlink:href="/assets/icons/duotone.svg#tom-sign-out"></use></svg>Sign out</a></li>
                </ul>
            </div>
        </div>
    </header>
    <button type="button" id="change-bg-button" hidden></button>
</div>

<main class="lp-main">
    <div class="lp-hero">
        <span class="lp-hero-avatar-wrap">
            <img class="lp-hero-avatar-img" src="<?= htmlspecialchars($avatar) ?>" alt="" onerror="this.src='/assets/img/default-avatar.png'">
        </span>
        <div class="lp-hero-text">
            <h1 class="lp-greeting"><?= $greeting ?> <?= htmlspecialchars($displayName) ?></h1>
            <p class="lp-tagline">State of the art laboratories at the hands and homes of every learner!</p>
        </div>
    </div>

    <div class="lp-searchbar">
        <button type="button" class="lp-search-pill blur" id="lp-search-pill" aria-label="Search labs and apps">
            <svg class="icon lp-search-ico"><use xlink:href="/assets/icons/duotone.svg#tom-magnifying-glass"></use></svg>
            <span class="lp-search-ph">Search labs &amp; apps&hellip;</span>
            <kbd class="lp-kbd">&#8984;K</kbd>
        </button>
    </div>

    <section class="lp-section" id="lp-running-section" style="text-align:center;">
        <div class="lp-pillbar blur" id="lp-pillbar" role="tablist" aria-label="Launcher categories">
            <button type="button" class="lp-pill lp-pill-active" data-tab="labs" role="tab" aria-selected="true">
                Labs
                <span class="lp-run-count" id="lp-run-count"><?= $labCount ?></span>
            </button>
            <button type="button" class="lp-pill" data-tab="challenges" role="tab" aria-selected="false">Challenge Labs</button>
        </div>

        <h5 class="lp-heading">Labs</h5>
        <div class="lp-grid" id="labsGrid">
            <div class="lp-grid-loading">
                <svg class="icon bx-spin" style="width:24px;height:24px;color:var(--cui-secondary-color)"><use xlink:href="/assets/icons/duotone.svg#tom-spinner"></use></svg>
            </div>
        </div>

        <div class="lp-removed-row">
            <button type="button" class="lp-removed-toggle" id="lpRemovedToggle" hidden>Removed (<span id="lpRemovedCount">0</span>)</button>
            <span class="lp-removed-list" id="lpRemovedList" hidden></span>
        </div>
    </section>

    <section class="lp-section">
        <h5 class="lp-heading">Platform</h5>
        <div class="lp-grid">
            <a href="/dashboard" class="lp-icon" data-tile="dashboard">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#0db7ed"><use xlink:href="/assets/icons/duotone.svg#tom-chart-pie-slice"></use></svg>
                </span></span>
                <span class="lp-label">Dashboard</span>
            </a>
            <a href="/devices" class="lp-icon" data-tile="devices">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#38bdf8"><use xlink:href="/assets/icons/duotone.svg#tom-desktop"></use></svg>
                </span></span>
                <span class="lp-label">My Devices</span>
            </a>
            <a href="/labs" class="lp-icon" data-tile="labs">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#22c55e"><use xlink:href="/assets/icons/duotone.svg#tom-monitor"></use></svg>
                </span></span>
                <span class="lp-label">Machine Labs</span>
            </a>
            <a href="/challenges" class="lp-icon" data-tile="challenge">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#ef4444"><use xlink:href="/assets/icons/duotone.svg#tom-shield"></use></svg>
                </span></span>
                <span class="lp-label">Challenge Labs</span>
            </a>
            <a href="/quiz" class="lp-icon" data-tile="quiz">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#8b91f9"><use xlink:href="/assets/icons/duotone.svg#tom-check-square"></use></svg>
                </span></span>
                <span class="lp-label">Spot Quiz</span>
            </a>
            <a href="/code" class="lp-icon" data-tile="code">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#fbbf24"><use xlink:href="/assets/icons/duotone.svg#tom-code"></use></svg>
                </span></span>
                <span class="lp-label">Code Arena</span>
            </a>
            <a href="/learn" class="lp-icon" data-tile="learn">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#06b6d4"><use xlink:href="/assets/icons/duotone.svg#tom-book-open"></use></svg>
                </span></span>
                <span class="lp-label">Learn AI</span>
            </a>
            <a href="/roadmaps" class="lp-icon" data-tile="roadmaps">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#10b981"><use xlink:href="/assets/icons/duotone.svg#tom-map-trifold"></use></svg>
                </span></span>
                <span class="lp-label">Roadmaps</span>
            </a>
            <a href="/syllabus" class="lp-icon" data-tile="syllabus">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#f472b6"><use xlink:href="/assets/icons/duotone.svg#tom-note-blank"></use></svg>
                </span></span>
                <span class="lp-label">Syllabus AI</span>
            </a>
            <a href="/discussion/questions/foryou/all" class="lp-icon" data-tile="discussion">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#8b5cf6"><use xlink:href="/assets/icons/duotone.svg#tom-chat-circle"></use></svg>
                </span></span>
                <span class="lp-label">Discussions</span>
            </a>
            <a href="/clubs" class="lp-icon" data-tile="clubs">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#ec4899"><use xlink:href="/assets/icons/duotone.svg#tom-users-three"></use></svg>
                </span></span>
                <span class="lp-label">Clubs</span>
            </a>
            <a href="/clans" class="lp-icon" data-tile="clans">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#ef4444"><use xlink:href="/assets/icons/duotone.svg#tom-flag"></use></svg>
                </span></span>
                <span class="lp-label">Clans</span>
            </a>
            <a href="/lucky" class="lp-icon" data-tile="lucky">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#a855f7"><use xlink:href="/assets/icons/duotone.svg#tom-lightning"></use></svg>
                </span></span>
                <span class="lp-label">Feeling Lucky</span>
            </a>
            <a href="/leaderboard-global" class="lp-icon" data-tile="leaderboard">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#eab308"><use xlink:href="/assets/icons/duotone.svg#tom-chart-bar"></use></svg>
                </span></span>
                <span class="lp-label">Leaderboard</span>
            </a>
            <a href="/mcp" class="lp-icon" data-tile="mcp">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#22d3ee"><use xlink:href="/assets/icons/duotone.svg#tom-share-network"></use></svg>
                </span></span>
                <span class="lp-label">MCP Connections</span>
            </a>
            <a href="/services" class="lp-icon" data-tile="services">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#818cf8"><use xlink:href="/assets/icons/duotone.svg#tom-hard-drives"></use></svg>
                </span></span>
                <span class="lp-label">Services</span>
            </a>
            <a href="/domains" class="lp-icon" data-tile="domains">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#f59e0b"><use xlink:href="/assets/icons/duotone.svg#tom-globe"></use></svg>
                </span></span>
                <span class="lp-label">Domains</span>
            </a>
            <a href="/network" class="lp-icon" data-tile="network">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#34d399"><use xlink:href="/assets/icons/duotone.svg#tom-tree-structure"></use></svg>
                </span></span>
                <span class="lp-label">Network</span>
            </a>
            <a href="/profile/ssh-keys" class="lp-icon" data-tile="sshkeys">
                <span class="lp-tile-wrap"><span class="lp-tile card blur">
                    <svg class="lp-glyph" style="color:#fb7185"><use xlink:href="/assets/icons/duotone.svg#tom-key"></use></svg>
                </span></span>
                <span class="lp-label">Access Keys</span>
            </a>
        </div>
    </section>
</main>

<!-- Spotlight Search Overlay -->
<div class="lp-spot-overlay" id="lpSpotOverlay">
    <div class="lp-spot-card card blur" role="dialog" aria-modal="true" aria-label="Search">
        <div class="lp-spot-inputwrap">
            <svg class="icon"><use xlink:href="/assets/icons/duotone.svg#tom-magnifying-glass"></use></svg>
            <input type="text" class="lp-spot-input" id="lpSpotInput" placeholder="Search labs, apps and pages…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-label="Search">
            <span class="lp-kbd">Esc</span>
        </div>
        <div class="lp-spot-results" id="lpSpotResults" role="listbox">
            <div class="lp-spot-empty">Type to search your labs, apps and pages.</div>
        </div>
    </div>
</div>

<script>
(function() {
    var LAB_ICON_MAP = <?= json_encode($labIconMap) ?>;
    var DEPLOY_TILE = '<a href="/labs" class="lp-icon lp-deploy-tile">' +
        '<span class="lp-tile-wrap"><span class="lp-tile card blur">' +
        '<svg class="lp-glyph"><use xlink:href="/assets/icons/duotone.svg#tom-plus-circle"></use></svg>' +
        '</span></span><span class="lp-label">Deploy a lab</span></a>';

    /* ── Tab switching ── */
    var pills = document.querySelectorAll('.lp-pill');
    var heading = document.querySelector('.lp-heading');
    pills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            pills.forEach(function(p) { p.classList.remove('lp-pill-active'); p.setAttribute('aria-selected', 'false'); });
            pill.classList.add('lp-pill-active');
            pill.setAttribute('aria-selected', 'true');
            if (heading) heading.textContent = pill.dataset.tab === 'labs' ? 'Labs' : 'Challenge Labs';
        });
    });

    /* ── Fetch labs on page load ── */
    function renderLabs(labs) {
        var grid = document.getElementById('labsGrid');
        if (!grid) return;
        var running = 0;
        var html = '';
        labs.forEach(function(lab) {
            var isRunning = lab.status === 'running' || lab.status === 'paused';
            if (isRunning) running++;
            var map = LAB_ICON_MAP[lab.type] || { glyph: 'tom-cube', color: '#8b91f9' };
            var stoppedClass = isRunning ? '' : ' lp-stopped';
            var dotClass = isRunning ? 'lp-dot-healthy' : 'lp-dot-paused';
            var dotTitle = isRunning ? 'Running' : 'Stopped';
            var href = '/labs/dashboard/' + lab.hash;
            html += '<a href="' + href + '" class="lp-icon' + stoppedClass + '" data-category="instance" data-lab="' + lab.type + '" data-hash="' + lab.hash + '">' +
                '<span class="lp-tile-wrap lp-halo-fill">' +
                '<span class="lp-tile card blur">' +
                '<svg class="lp-glyph" style="color:' + map.color + '"><use xlink:href="/assets/icons/duotone.svg#' + map.glyph + '"></use></svg>' +
                '</span>' +
                '<span class="lp-status ' + dotClass + '" role="img" aria-label="' + dotTitle + '" title="' + dotTitle + '"></span>' +
                '<button type="button" class="lp-more" aria-label="More actions for ' + lab.name + '">' +
                '<svg class="icon"><use xlink:href="/assets/icons/duotone.svg#tom-dots-three"></use></svg>' +
                '</button>' +
                '</span>' +
                '<span class="lp-label">' + lab.name + '</span>' +
                '</a>';
        });
        html += DEPLOY_TILE;
        grid.innerHTML = html;
        var countEl = document.getElementById('lp-run-count');
        if (countEl) countEl.textContent = running;
    }

    fetch('/api/labs/home_labs.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.result === 'success' && data.labs) renderLabs(data.labs); })
        .catch(function() {
            var grid = document.getElementById('labsGrid');
            if (grid) grid.innerHTML = DEPLOY_TILE;
        });

    /* ── Spotlight Search ── */
    var overlay = document.getElementById('lpSpotOverlay');
    var input = document.getElementById('lpSpotInput');
    var results = document.getElementById('lpSpotResults');
    var activeIdx = -1;
    var debounceTimer = null;

    function openSpotlight() {
        overlay.classList.add('lp-spot-open');
        input.value = '';
        results.innerHTML = '<div class="lp-spot-empty">Type to search your labs, apps and pages.</div>';
        activeIdx = -1;
        setTimeout(function() { input.focus(); }, 60);
    }

    function closeSpotlight() {
        overlay.classList.remove('lp-spot-open');
        input.value = '';
        results.innerHTML = '';
        activeIdx = -1;
    }

    function groupLabel(key) {
        var map = { running: 'Running Labs', catalog: 'Lab Catalog', apps: 'Apps & Pages', challenges: 'Challenges', quiz: 'Quiz', learn: 'Learn AI', roadmaps: 'Roadmaps', syllabus: 'Syllabus' };
        return map[key] || key;
    }

    function renderResults(groups) {
        var html = '';
        var keys = ['running', 'catalog', 'apps', 'challenges', 'quiz', 'learn', 'roadmaps', 'syllabus'];
        keys.forEach(function(key) {
            var items = groups[key];
            if (!items || !items.length) return;
            html += '<div class="lp-spot-grouphd">' + groupLabel(key) + '</div>';
            items.forEach(function(item, i) {
                var href = item.href || (item.iid ? '/labs/dashboard/' + item.iid : '#');
                var icHtml = '';
                if (item.icon && item.icon.kind === 'glyph') {
                    icHtml = '<svg class="icon" style="color:' + (item.icon.colour || 'var(--cui-primary)') + '"><use xlink:href="/assets/icons/duotone.svg#' + item.icon.glyph + '"></use></svg>';
                } else if (item.glyph) {
                    icHtml = '<svg class="icon" style="color:' + (item.colour || 'var(--cui-primary)') + '"><use xlink:href="/assets/icons/duotone.svg#' + item.glyph + '"></use></svg>';
                } else {
                    icHtml = '<svg class="icon"><use xlink:href="/assets/icons/duotone.svg#tom-arrow-square-out"></use></svg>';
                }
                html += '<a href="' + href + '" class="lp-spot-item" data-idx="' + (activeIdx + 1) + '">' +
                    '<span class="lp-spot-ic">' + icHtml + '</span>' +
                    '<span class="lp-spot-txt"><span class="lp-spot-label">' + (item.label || '') + '</span>' +
                    '<span class="lp-spot-sub">' + (item.sub || '') + '</span></span></a>';
                activeIdx++;
            });
        });
        if (!html) {
            html = '<div class="lp-spot-empty">No results found</div>';
        }
        results.innerHTML = html;
        activeIdx = -1;
    }

    function doSearch(q) {
        if (!q || q.length < 1) { results.innerHTML = ''; return; }
        fetch('/api/search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.result === 'success') renderResults(data.groups || {}); })
            .catch(function() { results.innerHTML = '<div class="lp-spot-empty">Search failed</div>'; });
    }

    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = input.value.trim();
        debounceTimer = setTimeout(function() { doSearch(q); }, 200);
    });

    input.addEventListener('keydown', function(e) {
        var items = results.querySelectorAll('.lp-spot-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            items.forEach(function(it, i) { it.classList.toggle('lp-spot-active', i === activeIdx); });
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            items.forEach(function(it, i) { it.classList.toggle('lp-spot-active', i === activeIdx); });
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].click();
        } else if (e.key === 'Escape') {
            closeSpotlight();
        }
    });

    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeSpotlight(); });

    /* ── Search pill + Cmd+K ── */
    var searchPill = document.getElementById('lp-search-pill');
    if (searchPill) searchPill.addEventListener('click', openSpotlight);
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSpotlight(); }
    });
})();
</script>
