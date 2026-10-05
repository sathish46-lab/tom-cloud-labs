<?php
// Returns full relative paths like 'dashboard' or 'labs/dashboard'
$current = Session::getCurrentFile(); 
?>
<div class="sidebar sidebar-fixed border-end-0" id="sidebar">
    <!-- ANTI-FLICKER SCRIPT: Instantly apply narrow state before browser paints -->
    <script>
        (function() {
            const sb = document.getElementById('sidebar');
            // Unconditionally block transitions during initial render
            sb.classList.add('no-transition-on-load');
            
            if (document.documentElement.classList.contains('sidebar-init-narrow')) {
                sb.classList.add('sidebar-narrow-unfoldable');
            }
            if (document.documentElement.classList.contains('sidebar-init-hidden')) {
                sb.classList.add('hide');
            }
            
            // Remove the transition lock after the page has fully loaded and painted
            window.addEventListener('load', () => {
                setTimeout(() => sb.classList.remove('no-transition-on-load'), 50);
            });
        })();
    </script>
    <div class="sidebar-inner-layer d-flex flex-column">
    <div class="sidebar-header border-opacity-10 py-3">
        <div class="sidebar-brand text-decoration-none">
            <div class="sidebar-brand-full">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <img src="/assets/logo/logo.png" width="44" height="44" alt="Tom Labs Icon" style="border-radius: 8px;">
                    <span class="fs-4 fw-bold mb-0" style="color: var(--cui-sidebar-brand-color, inherit); letter-spacing: 0.5px; line-height: 1;">Tom Labs</span>
                </div>
            </div>
            <img class="sidebar-brand-narrow" src="/assets/logo/logo.png" width="44" height="44" alt="TL" style="border-radius: 8px;">
        </div>
    </div>

    <?php $isSuperuser = Session::getUser()?->getRole() === 'superuser'; ?>
    <?php if ($isSuperuser): ?>
    <!-- ============ ADMIN SIDEBAR (superuser only) ============
         Rendered on EVERY page: HTMX navigations do not swap the sidebar, so
         both nav lists must exist in the DOM. JS toggles which one is visible
         on htmx:afterSettle; PHP sets the initial state for full reloads.

         Structure follows the Admin guide's six groups. Every group except
         Platform belongs to a module — switching that module off hides it.
         Tier A ships Platform; the other groups are appended as they land. -->
    <ul class="sidebar-nav admin-sidebar-nav <?= $current === 'admin' ? '' : 'd-none' ?>"
        data-coreui="navigation" data-simplebar>
        <?php
        $adminPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $adminGroups = [
            [
                'title'  => 'Platform',
                'module' => null, // Platform is never hidden by a module
                'items'  => [
                    ['label' => 'Dashboard',      'url' => '/admin',         'icon' => 'bx-tachometer',     'match' => '', 'exact' => true],
                    ['label' => 'Users',          'url' => '/admin/users',   'icon' => 'bx-group',          'match' => '/admin/user/'],
                    ['label' => 'Deleted Users',  'url' => '/admin/deleted-users', 'icon' => 'bx-archive',  'match' => '/admin/deleted-users'],
                    ['label' => 'Access Control', 'url' => '/admin/acl',     'icon' => 'bx-shield-quarter', 'match' => ''],
                    ['label' => 'MCP Tools',      'url' => '/admin/mcp',     'icon' => 'bx-bot',            'match' => ''],
                    ['label' => 'Storage Quotas', 'url' => '/admin/storage', 'icon' => 'bx-hdd',            'match' => ''],
                    ['label' => 'Transactions',   'url' => '/admin/transactions', 'icon' => 'bx-transfer', 'match' => ''],
                    ['label' => 'Errors',         'url' => '/admin/errors', 'icon' => 'bx-error-circle', 'match' => ''],
                ],
                'groups' => [
                    [
                        'label' => 'Settings',
                        'icon'  => 'bx-cog',
                        'items' => [
                            ['label' => 'Services',  'url' => '/admin/services',  'icon' => 'bx-desktop',     'match' => ''],
                            ['label' => 'Modules',   'url' => '/admin/modules',   'icon' => 'bx-toggle-right', 'match' => ''],
                            ['label' => 'Appearance', 'url' => '/admin/appearance', 'icon' => 'bx-palette',    'match' => ''],
                        ],
                    ],
                ],
            ],

            [
                'title'  => 'Labs',
                'module' => null,
                'items'  => [
                    ['label' => 'Instances', 'url' => '/admin/instances', 'icon' => 'bx-server', 'match' => ''],
                ],
            ],
        ];

        $adminItem = function (array $it) use ($adminPath) {
            $isActive = strpos($adminPath, $it['url']) === 0
                     || (($it['match'] ?? '') !== '' && strpos($adminPath, $it['match']) === 0);
            // /admin itself must not light up on every child route
            if (!empty($it['exact'])) {
                $isActive = ($adminPath === $it['url']);
            }
            echo '<li class="nav-item">';
            echo '  <a class="nav-link' . ($isActive ? ' active' : '') . '" href="' . htmlspecialchars($it['url']) . '"';
            echo ' data-admin-link="1"';
            if (!empty($it['exact'])) echo ' data-exact="1"';
            if (($it['match'] ?? '') !== '') echo ' data-match="' . htmlspecialchars($it['match']) . '"';
            echo '><i class="nav-icon bx ' . htmlspecialchars($it['icon']) . '"></i> ' . htmlspecialchars($it['label']) . '</a>';
            echo '</li>';
        };
        ?>

        <?php
        $adminModules = [];
        $adminModulesLoaded = false;
        ?>
        <?php foreach ($adminGroups as $g): ?>
            <?php
            // Lazily load module flags — only if a group actually declares one.
            if (($g['module'] ?? null) !== null) {
                if (!$adminModulesLoaded) {
                    $adminModulesLoaded = true;
                    try {
                        $gdb = DatabaseConnection::getDefaultDatabase();
                        foreach (['master_switches', 'lab_features'] as $sid) {
                            foreach ((array)$gdb->global_settings->findOne(['_id' => $sid]) as $k => $v) {
                                if ($k !== '_id' && is_bool($v)) $adminModules[$k] = $v;
                            }
                        }
                    } catch (Throwable $e) {
                        $adminModules = [];
                    }
                }
                // A group hides only when its flag is explicitly false.
                if (array_key_exists($g['module'], $adminModules) && $adminModules[$g['module']] === false) {
                    continue;
                }
            }
            ?>
            <li class="nav-title"><?= htmlspecialchars($g['title']) ?></li>

            <?php foreach (($g['items'] ?? []) as $it) $adminItem($it); ?>

            <?php foreach (($g['groups'] ?? []) as $sub): ?>
                <?php
                $hasActive = false;
                foreach ($sub['items'] as $it) {
                    if (strpos($adminPath, $it['url']) === 0
                        || (($it['match'] ?? '') !== '' && strpos($adminPath, $it['match']) === 0)) {
                        $hasActive = true;
                        break;
                    }
                }
                ?>
                <li class="nav-group <?= $hasActive ? 'show' : '' ?>">
                    <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                        <i class="nav-icon bx <?= htmlspecialchars($sub['icon']) ?>"></i> <?= htmlspecialchars($sub['label']) ?>
                    </a>
                    <ul class="nav-group-items">
                        <?php foreach ($sub['items'] as $it) $adminItem($it); ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <li class="nav-item mt-auto pt-2 border-top border-light border-opacity-10">
            <a class="nav-link" href="/home" hx-boost="false" data-admin-link="1" data-admin-exit="1">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-desktop-tower"></use></svg> Back to Labs
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <ul class="sidebar-nav main-sidebar-nav <?= ($isSuperuser && $current === 'admin') ? 'd-none' : '' ?>"
        data-coreui="navigation" data-simplebar>
        <li class="nav-item">
            <a class="nav-link <?= $current == 'dashboard' ? 'active' : '' ?>" href="<?= Session::url('dashboard') ?>">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-speedometer"></use>
                </svg>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= (str_contains($current, 'events')) ? 'active' : '' ?>" href="/events">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-calendar-event"></use></svg> Events
            </a>
        </li>
        <li class="nav-group <?= (str_contains($current, 'learn') || str_contains($current, 'roadmap')) ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-book-bookmark"></use></svg> Learn
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= (str_contains($current, 'learn')) ? 'active' : '' ?>" href="/learn">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-book-bookmark"></use></svg> Learn AI
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (str_contains($current, 'roadmap')) ? 'active' : '' ?>" href="/roadmaps">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-map-trifold"></use></svg> Roadmaps
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-group <?= $current == 'quiz' ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-notepad"></use></svg> Evaluate
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'quiz' ? 'active' : '' ?>" href="/quiz">
                        <i class="nav-icon bx bxs-zap text-warning"></i> Spot Quiz  ⚡
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);">
                        <i class="nav-icon bx bx-code-alt"></i> Code Arena 👨🏽‍💻
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-group <?= (str_contains($current, 'discuss') || str_contains($current, 'clubs')) ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-chat-dots"></use></svg> Discuss
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= (str_contains($current, 'recent')) ? 'active' : '' ?>" href="/recent">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-clock-history"></use></svg> Recent
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (str_contains($current, 'clubs')) ? 'active' : '' ?>" href="/clubs">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-users-group"></use></svg> Clubs 🌱
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= str_contains($current, 'lucky') ? 'active' : '' ?>" href="/lucky">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-sparkles"></use></svg> Feeling Lucky ✨
            </a>
        </li>
        <li class="nav-group <?= (str_contains($current, 'leaderboard') || str_contains($current, 'leagues')) ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <i class="nav-icon bx bx-trophy"></i> Mastery Hall
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($current, 'leaderboard') ? 'active' : '' ?>" href="/leaderboard-global">
                        <i class="nav-icon bx bx-trophy"></i> Global Leaderboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($current, 'leagues') ? 'active' : '' ?>" href="/leagues">
                        <i class="nav-icon bx bx-medal"></i> League of Ronin
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-title">Tom Labs</li>
        <li class="nav-group <?= (str_contains($current, 'devices') || str_contains($current, 'network') || str_contains($current, 'domains')) ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-cell-tower"></use></svg> Connectivity
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'devices' ? 'active' : '' ?>" href="/devices">
                        <svg class="nav-icon">
                            <use xlink:href="/assets/icons/sprites/free.svg#cil-devices"></use>
                        </svg> My Device
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'network' ? 'active' : '' ?>" href="/network">
                        <svg class="nav-icon">
                            <use xlink:href="/assets/icons/sprites/free.svg#cil-sitemap"></use>
                        </svg> My Network
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'domains' ? 'active' : '' ?>" href="/domains">
                        <i class="nav-icon bx bx-globe"></i> My Domain
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-group <?= (str_contains($current, 'labs') || str_contains($current, 'challenges')) ? 'show' : '' ?>">
            <a class="nav-link nav-group-toggle" href="javascript:void(0);">
                <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-desktop-tower"></use></svg> My Labs
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= (str_contains($current, 'labs')) ? 'active' : '' ?>" href="/labs">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-desktop"></use></svg> Machine Labs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'challenges' ? 'active' : '' ?>" href="<?= Session::url('challenges') ?>">
                        <svg class="nav-icon" viewBox="0 0 256 256"><use href="/assets/icons/duotone.svg#tom-shield-checkered"></use></svg> Challenge Labs
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= (str_contains($current, 'instances')) ? 'active' : '' ?>" href="/instances">
                <i class="nav-icon bx bx-cube-alt"></i> Instances
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= (str_contains($current, 'services')) ? 'active' : '' ?>" href="/services">
                <i class="nav-icon bx bxs-data"></i> Services
            </a>
        </li>
        <?php if (\TomLabs\Labs\LabFeatures::canAccessMcp()): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current == 'mcp' ? 'active' : '' ?>" href="/mcp">
                <i class="nav-icon bx bx-terminal"></i> MCP Inspector
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?= $current == 'test' ? 'active' : '' ?>" href="/test">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-bowling"></use>
                </svg> test
            </a>
        </li>
    </ul>

    <div id="sidebar-stats-container" class="sidebar-stats p-3 border-top border-light border-opacity-10">
        <div class="stat-group mb-2">
            <div class="small mb-1 cpuinfotext">
                <span class="stat-label fw-bold">CPU USAGE: <span id="sidebar-cpu-val">0.00%</span></span>
            </div>
            <div class="progress rounded-0 stats-progress-bg cpuinfo" style="height: 6px; background: rgba(0,0,0,0.3);">
                <?php
                $style = ['danger', 'warning', 'primary', 'success', 'info', 'secondary'];
                $ncpu = Session::getProcessorCount();
                for ($i = 0; $i < $ncpu; $i++) {
                    $colorClass = $style[$i % count($style)];
                    // Use transition: width for smooth animation without jQuery
                    echo '<div class="progress-bar bg-' . $colorClass . '-gradient cpu-' . $i . '" 
                                role="progressbar" style="width: 0%; transition: width 0.4s ease;" 
                                aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>';
                }
                ?>
            </div>
            <div id="sidebar-load-val" class="stat-subtext mt-1">Loading...</div>
        </div>

        <div class="stat-group mb-2">
            <div class="small mb-1"><span class="stat-label fw-bold">MEMORY USAGE</span></div>
            <div class="progress rounded-0 stats-progress-bg" style="height: 6px; background: rgba(0,0,0,0.3);">
                <div id="sidebar-mem-bar" class="progress-bar bg-warning"
                    style="width: 0%; transition: width 0.4s ease;"></div>
            </div>
            <div id="sidebar-mem-details" class="stat-subtext mt-1">Loading...</div>
        </div>

        <div class="stat-group mb-2">
            <div class="small mb-1"><span class="stat-label fw-bold">SWAP USAGE</span></div>
            <div class="progress rounded-0 stats-progress-bg" style="height: 6px; background: rgba(0,0,0,0.3);">
                <div id="sidebar-swap-bar" class="progress-bar bg-danger"
                    style="width: 0%; transition: width 0.4s ease;"></div>
            </div>
            <div id="sidebar-swap-details" class="stat-subtext mt-1">Loading...</div>
        </div>
    </div>
    </div>

    <div class="sidebar-footer border-top border-light border-opacity-10 d-flex align-items-center" style="height: 46px; padding: 0 8px;">
        <button class="header-toggler border-0 d-flex align-items-center justify-content-center" type="button"
            onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()"
            style="width: 46px; height: 46px; background: transparent;">
            <i class="bx bx-menu fs-4 text-secondary"></i>
        </button>
        <button class="sidebar-toggler ms-auto me-2" type="button" data-coreui-toggle="unfoldable"
            data-coreui-target="#sidebar"></button>
    </div>
</div>

<!-- Fast Sidebar Logo Toggler script to prevent JS overhead -->
<script>
(() => {
  const sidebar   = document.getElementById('sidebar');
  if (!sidebar) return;

  const fullImgs  = sidebar.querySelectorAll('.sidebar-brand-full');
  const narrowImgs= sidebar.querySelectorAll('.sidebar-brand-narrow');

  const showSet = (showFull) => {
    fullImgs.forEach(img   => img.style.display   = showFull ? 'block' : 'none');
    narrowImgs.forEach(img => img.style.display   = showFull ? 'none'  : 'block');

    if (showFull) {
      fullImgs.forEach(img => {
        if (!img.dataset.src || img.dataset.loaded) return;
        img.src = img.dataset.src;
        img.dataset.loaded = '1';
      });
    }
  };

  const isCollapsed = () => sidebar.classList.contains('sidebar-narrow') || sidebar.classList.contains('sidebar-narrow-unfoldable');

  showSet(!isCollapsed());

  sidebar.addEventListener('transitionend', (e) => {
    if (e.propertyName !== 'width') return;
    showSet(!isCollapsed());
  });
})();

// Sidebar Active State Sync & Cleanup
// Runs on DOMContentLoaded (full page reload) AND htmx:afterSettle (HTMX nav).
// The sidebar lives OUTSIDE #main-content, so HTMX never swaps it — this
// function is the single source of truth for admin visibility + active state.
function syncSidebarActiveState(targetUrl) {
    let path = (targetUrl || window.location.pathname).split('?')[0].split('#')[0];
    const isAdminPath = path === '/admin' || path.startsWith('/admin/');

    const adminNav = document.querySelector('.admin-sidebar-nav');
    const mainNav  = document.querySelector('.main-sidebar-nav');

    // Show EXACTLY ONE nav list. The admin list only exists for superusers.
    const showAdmin = isAdminPath && !!adminNav;
    if (adminNav) adminNav.classList.toggle('d-none', !showAdmin);
    if (mainNav)  mainNav.classList.toggle('d-none', showAdmin);

    const nav = showAdmin ? adminNav : mainNav;
    if (!nav) return;

    // --- Active link ---
    nav.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');

        // Back to Labs points at /home — never mark it active
        if (link.dataset.adminExit) return;

        if (href && !href.startsWith('javascript:') && href !== '#' && href !== '/') {
            // Optional data-match supplies extra prefixes (e.g. /admin/user/xxx -> /admin/users)
            const extra = link.dataset.match || null;
            // data-exact = prefix matching off (/admin must not match /admin/users)
            const hit = link.dataset.exact
                ? path === href
                : (path === href
                    || path.startsWith(href + '/')
                    || (extra && path.startsWith(extra)));
            if (hit) {
                link.classList.add('active');
                const navGroup = link.closest('.nav-group');
                if (navGroup) navGroup.classList.add('show');
            }
        } else if (href === '/' && path === '/') {
            link.classList.add('active');
        }
    });

    // --- Collapse groups that have no active child ---
    nav.querySelectorAll('.nav-group').forEach(group => {
        if (group.querySelector('.nav-group-items .nav-link.active')) return;
        const toggle = group.querySelector('.nav-group-toggle');
        if (toggle) toggle.classList.remove('active');
        if (window.location.hash === '#' || path.includes('/dashboard')) {
            group.classList.remove('show');
        }
    });
}
document.addEventListener('DOMContentLoaded', syncSidebarActiveState);
document.addEventListener('htmx:afterSettle', syncSidebarActiveState);
</script>