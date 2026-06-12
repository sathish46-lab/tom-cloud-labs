<?php
// Returns full relative paths like 'dashboard' or 'labs/dashboard'
$current = Session::getCurrentFile(); 
?>
<div class="sidebar sidebar-fixed border-end-0" id="sidebar">
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

    <ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
        <li class="nav-item">
            <a class="nav-link <?= $current == 'dashboard' ? 'active' : '' ?>" href="<?= Session::url('dashboard') ?>">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-speedometer"></use>
                </svg>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= (str_contains($current, 'learn')) ? 'active' : '' ?>" href="/learn">
                <i class="nav-icon bx bxs-graduation"></i>
                Learn AI 
            </a>
        </li>
        <li class="nav-group">
            <a class="nav-link nav-group-toggle" href="#">
                <i class="nav-icon bx bx-spreadsheet"></i> Evaluate
            </a>
            <ul class="nav-group-items">
                <li class="nav-item">
                    <a class="nav-link <?= $current == 'quiz' ? 'active' : '' ?>" href="/quiz">
                        <i class="nav-icon bx bxs-zap text-warning"></i> Spot Quiz  ⚡
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="nav-icon bx bx-code-alt"></i> Code Arena 👨🏽‍💻
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-title">Tom Labs</li>
        <li class="nav-item">
            <a class="nav-link <?= $current == 'devices' ? 'active' : '' ?>" href="/devices">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-devices"></use>
                </svg> Devices
            </a>
            <a class="nav-link <?= $current == 'network' ? 'active' : '' ?>" href="/network">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-sitemap"></use>
                </svg> Network
            </a>
            <a class="nav-link <?= $current == 'domains' ? 'active' : '' ?>" href="/domains">
                <i class="nav-icon bx bx-globe"></i> Domains
            </a>
            <a class="nav-link <?= (str_contains($current, 'labs')) ? 'active' : '' ?>" href="/labs">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-memory"></use>
                </svg> Labs
            </a>
            <a class="nav-link <?= $current == 'challenges' ? 'active' : '' ?>" href="<?= Session::url('challenges') ?>">
                <i class="nav-icon bx bxs-flag-alt"></i> Challenges
            </a>
            <a class="nav-link <?= $current == 'test' ? 'active' : '' ?>" href="/test">
                <svg class="nav-icon">
                    <use xlink:href="/assets/icons/sprites/free.svg#cil-bowling"></use>
                </svg> test
            </a>
        </li>
    </ul>

    <div id="sidebar-stats-container" class="sidebar-stats p-3 mt-auto border-top border-light border-opacity-10">
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

    <div class="sidebar-footer border-top border-light border-opacity-10 d-flex align-items-center" style="min-height: 46px; height: 46px; padding: 0;">
        <button class="header-toggler border-0 d-flex align-items-center justify-content-center" type="button"
            onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()"
            style="width: 46px; height: 46px; background: transparent;">
            <i class="bx bx-menu fs-4 text-secondary"></i>
        </button>
        <button class="sidebar-toggler ms-auto me-2" type="button" data-coreui-toggle="unfoldable"
            data-coreui-target="#sidebar"></button>
    </div>

</div>

<style>
/* Sidebar Layout Fix: Ensure Footer and Stats stay at bottom */
.sidebar {
    display: flex !important;
    flex-direction: column !important;
}

.sidebar-nav {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
}

#sidebar-stats-container, .sidebar-footer {
    flex: 0 0 auto !important;
}

/* 0. IMMEDIATE HIDE FOR RECOVERY (Prevents UI Flicker) */
.sidebar-init-hidden #sidebar {
    display: none !important;
}

/* 1. HIDE STATS ON COLLAPSE */
.sidebar-narrow #sidebar-stats-container,
.sidebar-narrow-unfoldable:not(:hover) #sidebar-stats-container {
    display: none !important;
}

/* 2. PROGRESS BAR STYLING */
.stats-progress-bg {
    height: 6px;
    background: rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.stat-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--glass-text, #fff);
}

.stat-subtext {
    font-size: 0.7rem;
    color: var(--glass-text-muted, rgba(255, 255, 255, 0.7));
}

/* 3. PER-CORE STYLING */
.cpuinfo.progress {
    display: flex;
    background: rgba(0, 0, 0, 0.2);
}
</style>

<script>
// SIDEBAR PERSISTENCE LOGIC
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
</script>