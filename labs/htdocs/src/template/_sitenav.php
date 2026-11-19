<header class="header header-sticky blur rounded-0 mb-0" style="border-bottom: none; height: 4rem; min-height: 4rem;">
    <div class="container-fluid px-4 h-100">
        <!-- Left: Home + Back + Page Title -->
        <div class="d-flex align-items-center gap-2 flex-shrink-0" style="z-index: 2;">
            <!-- Mobile Sidebar Toggle -->
            <button class="btn btn-link p-0 text-secondary hover-text-white d-flex d-md-none align-items-center justify-content-center rounded-circle border border-white border-opacity-10 text-decoration-none shadow-none" 
                    type="button" 
                    onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()"
                    style="width: 32px; height: 32px; transition: all 0.2s;">
                <svg class="icon" viewBox="0 0 256 256" width="18" height="18"><use href="/assets/icons/duotone.svg#tom-list"></use></svg>
            </button>

            <!-- Home Button -->
            <a href="/home" hx-boost="false" data-no-boost="true" class="d-flex align-items-center justify-content-center text-secondary text-decoration-none rounded-circle"
                    style="width: 32px; height: 32px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; cursor: pointer;" title="Home">
                <svg class="icon" viewBox="0 0 256 256" width="18" height="18" style="stroke: currentColor; stroke-width: 22px;"><use href="/assets/icons/duotone.svg#tom-house"></use></svg>
            </a>

            <!-- Back Button -->
            <button type="button" onclick="history.back(); return false;" class="btn p-0 d-flex align-items-center justify-content-center text-secondary rounded-circle"
                    style="width: 32px; height: 32px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; cursor: pointer;" title="Go Back">
                <svg class="icon" viewBox="0 0 256 256" width="18" height="18" style="stroke: currentColor; stroke-width: 22px;"><use href="/assets/icons/duotone.svg#tom-arrow-bend-up-left"></use></svg>
            </button>

            <!-- Page Title / Breadcrumb -->
            <nav aria-label="breadcrumb" class="ms-1">
                <ol id="main-breadcrumb" class="breadcrumb my-0 py-0 mb-0">
                    <?php include __DIR__ . '/partials/_breadcrumb.php'; ?>
                </ol>
            </nav>
        </div>

        <!-- Right: Nav Items -->

        <ul class="header-nav ms-auto mb-0 align-items-center list-unstyled d-flex flex-shrink-0">
            <?php 
                $navUser = Session::getUser();
                $navStats = $navUser ? \TomLabs\Labs\Quiz::getUserStats($navUser->getEmail()) : ['zeal' => 0, 'jolt' => 10];
            ?>
            <?php if (!empty($_SESSION['impersonator']['username'])): ?>
            <li class="nav-item me-2">
                <button type="button" id="exit-impersonation"
                        class="btn btn-sm fw-semibold rounded-pill px-3 text-nowrap d-inline-flex align-items-center gap-1"
                        style="border: 1px solid rgba(250,204,21,.55); color: #facc15; background: rgba(250,204,21,.10);"
                        title="Impersonating <?= htmlspecialchars($navUser?->getUsername() ?? '') ?> — still signed in as <?= htmlspecialchars((string)$_SESSION['impersonator']['username']) ?>. Click to exit.">
                    <i class='bx bx-user-circle'></i><span class="d-none d-sm-inline">Exit impersonation</span>
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item px-3">
                <button type="button" id="searchTrigger" onclick="openSearchPalette()"
                        class="btn p-0 d-flex align-items-center justify-content-center text-secondary hover-text-white bg-transparent border-0 shadow-none"
                        title="Search (⌘K)">
                    <svg class="icon" viewBox="0 0 256 256" width="26" height="26" style="stroke: currentColor; stroke-width: 15px;"><use href="/assets/icons/duotone.svg#tom-magnifying-glass"></use></svg>
                </button>
            </li>
            <li class="nav-item d-none d-md-flex align-items-center me-3">
                <div class="d-flex align-items-center gap-3 rounded-pill px-3 py-1 border border-secondary border-opacity-10 shadow-sm" style="background: rgba(var(--cui-emphasis-color-rgb, 128, 128, 128), 0.05);">
                    <div class="d-flex align-items-center gap-1" title="Total Zeal (Experience Points)">
                        <span class="fw-bold text-body-emphasis small" id="header-zeal" style="font-size: 0.85rem;"><?= number_format($navStats['zeal'] ?? 0) ?></span>
                        <i class="bx bxs-hot text-danger" style="font-size: 0.9rem;"></i>
                    </div>
                    <div class="vr bg-secondary opacity-20" style="height: 12px;"></div>
                    <div class="d-flex align-items-center gap-1" title="Available Jolt (Fuel)">
                        <span class="fw-bold text-body-emphasis small" id="header-jolt" style="font-size: 0.85rem;"><?= number_format($navStats['jolt'] ?? 0) ?></span>
                        <i class="bx bxs-zap text-warning" style="font-size: 0.9rem;"></i>
                    </div>
                </div>
            </li>
        
            <!-- Notifications -->
            <?php
                $navUser = Session::getUser();
                $isAdminUser = ($navUser && $navUser->getRole() === 'superuser');
                $notifTabs = class_exists('Notification')
                    ? Notification::CATEGORIES
                    : ['system', 'mcp', 'clan', 'clubs', 'code_arena', 'discussions', 'quiz', 'score', 'streak', 'academy'];
                if (!$isAdminUser) {
                    $adminOnly = class_exists('Notification') ? Notification::ADMIN_ONLY_CATEGORIES : ['report'];
                    $notifTabs = array_values(array_diff($notifTabs, $adminOnly));
                }
                $notifTabMeta = [
                    'system'      => ['System', 'tom-terminal-window'],
                    'mcp'         => ['MCP', 'tom-plug'],
                    'clan'        => ['Clan', 'tom-users-three'],
                    'clubs'       => ['Clubs', 'tom-books'],
                    'code_arena'  => ['Code Arena', 'tom-code'],
                    'discussions' => ['Discussions', 'tom-chat-circle-text'],
                    'quiz'        => ['Quiz', 'tom-question'],
                    'score'       => ['Score', 'tom-chart-line'],
                    'streak'      => ['Streak', 'tom-fire'],
                    'academy'     => ['Academy', 'tom-graduation-cap'],
                    'report'      => ['Report', 'tom-flag'],
                ];
                $firstNotifTab = $notifTabs[0] ?? 'system';
            ?>
            <li class="nav-item dropdown px-2">
                <button class="btn btn-link nav-link py-0 d-flex align-items-center justify-content-center position-relative"
                    type="button" id="notif-bell" aria-expanded="false" style="height: 40px; width: 40px;"
                    onclick="notifToggleDropdown(event)">
                    <i class="bx bx-bell fs-4 text-secondary hover-text-white"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border-0 border-white border-2" 
                        style="font-size: 0.65rem; padding: 2px 6px; min-width: 18px; height: 18px; display: none; line-height: 1;" id="notif-badge">
                        0
                    </span>
                </button>
                <!-- Notification card -->
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg pt-0 simple-blur notifications" id="notif-dropdown"
                    style="position: fixed; top: 60px; right: 7.10rem; left: auto; min-width: 750px; max-height: 650px; max-height: calc(100vh - 80px); border-radius: 16px; overflow: hidden; display: none; flex-direction: column; background: var(--cui-body-bg, #212631); z-index: 1082; padding: 0;">
                    <!-- Header -->
                    <div class="dropdown-header d-flex align-items-center justify-content-between flex-shrink-0 px-3 py-3 border-bottom border-secondary border-opacity-20" style="white-space: normal;">
                        <div class="me-2">
                            <strong>You have <span class="notification-count" id="notif-unread-count">0</span> unread messages</strong>
                        </div>
                        <div class="d-flex align-items-center flex-shrink-0">
                            <button class="btn btn-xs btn-link text-secondary n-mark-all-read p-0 me-2" id="notif-mark-all" onclick="notifMarkAllRead(event)" style="line-height: normal; text-decoration: none; font-size: 0.75rem;">
                                <svg class="icon" viewBox="0 0 256 256" width="14" height="14"><use href="/assets/icons/duotone.svg#tom-check-circle"></use></svg>
                                Mark all as read
                            </button>
                            <button class="btn btn-danger btn-icon btn-sm n-dismiss p-0 ms-2" aria-label="Close" onclick="notifCloseDropdown(event)" style="width: 28px; height: 28px; border-radius: 50%;">
                                <svg class="icon" viewBox="0 0 256 256" width="16" height="16"><use href="/assets/icons/duotone.svg#tom-x-circle"></use></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tab Buttons -->
                    <div class="tab-buttons border-bottom notification-tab-btns p-1 flex-shrink-0" id="notif-tabs" onclick="event.stopPropagation();">
                        <?php foreach ($notifTabs as $tabCat):
                            $tabMeta  = $notifTabMeta[$tabCat] ?? [ucfirst(str_replace('_', ' ', $tabCat)), 'tom-bell'];
                            $tabLabel = $tabMeta[0];
                            $tabIcon  = $tabMeta[1];
                        ?>
                        <button class="btn blur-item tab-button p-1 <?= $tabCat === $firstNotifTab ? 'active-tab' : '' ?>"
                            data-cat="<?= htmlspecialchars($tabCat) ?>"
                            onclick="notifSwitchTab('<?= htmlspecialchars($tabCat) ?>', this, event)">
                            <div>
                                <svg class="icon icon-lg me-auto my-auto" viewBox="0 0 256 256" width="24" height="24">
                                    <use href="/assets/icons/duotone.svg#<?= htmlspecialchars($tabIcon) ?>"></use>
                                </svg>
                            </div>
                            <p class="m-0"><?= htmlspecialchars($tabLabel) ?></p>
                            <span class="position-absolute top-0 end-0 badge rounded-pill bg-danger notification-type-indicator notification-count-<?= htmlspecialchars($tabCat) ?>" style="display: none;">0</span>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tab Contents -->
                    <div class="notif-tab-contents flex-grow-1 overflow-y-auto">
                        <?php foreach ($notifTabs as $tabCat): ?>
                        <div id="notif-pane-<?= htmlspecialchars($tabCat) ?>" class="tab-content tab-content-notification notifications-list notif-pane" data-cat="<?= htmlspecialchars($tabCat) ?>" style="display: <?= $tabCat === $firstNotifTab ? 'block' : 'none' ?>;">
                            <div class="notif-items"></div>
                            <div class="spinner-container notif-spinner justify-content-center py-4">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <div class="notif-empty text-center text-secondary small px-4 py-4" style="display: none;">
                                <svg class="icon icon-xl d-block mx-auto mb-2 opacity-25" viewBox="0 0 256 256" width="40" height="40"><use href="/assets/icons/duotone.svg#tom-bell-slash"></use></svg>
                                <div class="fw-semibold">You are all caught up.</div>
                            </div>
                            <a href="#" class="dropdown-item text-center border-top blur-item p-2 load-button" style="display: none;" onclick="event.preventDefault(); notifLoadMore('<?= htmlspecialchars($tabCat) ?>'); return false;"><strong>Load More...</strong></a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <style>
                #notif-dropdown .tab-buttons {
                    display: flex;
                    gap: 4px;
                    overflow-x: auto;
                    scrollbar-width: none;
                }
                #notif-dropdown .tab-buttons::-webkit-scrollbar { display: none; }
                #notif-dropdown .tab-button {
                    position: relative;
                    flex: 0 0 auto;
                    min-width: 74px;
                    background: transparent;
                    border: 1px solid transparent;
                    border-radius: 10px;
                    color: var(--cui-secondary-color);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 2px;
                }
                #notif-dropdown .tab-button p { font-size: 0.7rem; line-height: 1.1; }
                #notif-dropdown .tab-button:hover { background: rgba(var(--cui-body-color-rgb), 0.06); color: var(--cui-body-color); }
                #notif-dropdown .tab-button.active-tab {
                    background: rgba(var(--cui-primary-rgb, 99, 102, 241), 0.14);
                    border-color: rgba(var(--cui-primary-rgb, 99, 102, 241), 0.45);
                    color: var(--cui-primary, #6366f1);
                }
                #notif-dropdown .tab-button .notification-type-indicator { font-size: 0.6rem; line-height: 1; padding: 3px 5px; }
                #notif-dropdown .notification-item { display: block; padding: 10px 12px; border-radius: 10px; border-bottom: 1px solid rgba(var(--cui-body-color-rgb), 0.06); white-space: normal; }
                #notif-dropdown .notification-item:hover { background: rgba(var(--cui-body-color-rgb), 0.05); }
                #notif-dropdown .notification-item .message { display: flex; align-items: flex-start; gap: 10px; }
                #notif-dropdown .notif-avatar { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
                #notif-dropdown .notification-title { color: var(--cui-body-color); font-size: 0.85rem; line-height: 1.3; }
                #notif-dropdown .notification-message { line-height: 1.35; }
                #notif-dropdown .notification-time { white-space: nowrap; font-size: 0.7rem; }
                #notif-dropdown .notification-subtitle { font-size: 0.7rem; }
                #notif-dropdown .notif-spinner { display: flex; justify-content: center; padding: 1rem 0; }
                #notif-dropdown .notif-item-unread { background: rgba(var(--cui-primary-rgb, 99, 102, 241), 0.06); }
                </style>
            </li>

            <!-- Theme & Background Mega Dropdown -->
            <li class="nav-item dropdown px-2">
                <button class="btn btn-link nav-link py-0 d-flex align-items-center justify-content-center theme-selector-btn"
                    type="button" data-coreui-toggle="dropdown" style="height: 40px; width: 40px;">
                    <i class="bx theme-icon-active fs-4" id="currentThemeIcon"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end theme-mega-container border-0 bg-transparent shadow-none p-0">
                    <div class="d-flex align-items-start gap-3 p-2 flex-row-reverse">
                        <?php
                            require_once __DIR__ . '/../lib/core/Appearance.class.php';
                            $appearance = Appearance::get();
                            $appearanceLocked = (string)$appearance['force_mode'] !== '';
                            $appearanceDisabled = $appearance['disabled_modes'];
                            $colorModeLocked = (string)$appearance['force_color_mode'] !== '';
                        ?>
                        <!-- Mode Selector Card (Rightmost, under the icon) -->
                        <?php if (!$colorModeLocked): ?>
                        <div class="mode-selector-card shadow-lg">
                            <button class="mode-item" onclick="changeTheme('light')" onmouseenter="TomVisuals.switchBGTheme('light')" data-coreui-value="light">
                                <i class='bx bx-sun'></i> <span>Light</span>
                            </button>
                            <button class="mode-item" onclick="changeTheme('dark')" onmouseenter="TomVisuals.switchBGTheme('dark')" data-coreui-value="dark">
                                <i class='bx bx-moon'></i> <span>Dark</span>
                            </button>
                            <button class="mode-item" onclick="changeTheme('auto')" onmouseenter="TomVisuals.switchBGTheme('auto')" data-coreui-value="auto">
                                <i class='bx bx-circle-half'></i> <span>Auto</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Background Selector Card (Reveals to the left) -->
                        <?php if (!$appearanceLocked): ?>
                        <div class="bg-selector-card shadow-lg">
                            <?php require $_SERVER['DOCUMENT_ROOT'] . '/src/config/themes.php'; ?>
                            
                            <!-- Dark Mode Backgrounds -->
                            <div class="theme-bg-grid p-3" id="bg-grid-dark" data-theme="dark">
                                <?php if (!in_array('plain', $appearanceDisabled, true)): ?>
                                <div class="theme-bg-item" onclick="TomBG.setMode('plain')" data-mode="plain">
                                    <div class="theme-bg-thumb-wrapper"><div style="width:100%; height:100%; background: linear-gradient(45deg, #010d12, #0b1e36);"></div></div>
                                    <span class="theme-bg-label">Plain</span>
                                </div>
                                <?php endif; ?>
                                <?php foreach ($tomThemes as $id => $theme): 
                                    if (in_array($id, $appearanceDisabled, true)) continue;
                                    $label = ($id === 'robo') ? 'Lab' : (($id === 'ninja') ? 'War' : (($id === 'robotower') ? 'Tower' : (($id === 'spiderman') ? 'Spidey' : (($id === 'ironman') ? 'Iron Man' : ucfirst($id)))));
                                    $thumb = $theme['assets'][0]; // Use the first layer as thumb
                                    if (strpos($thumb, '.png') !== false && !strpos($thumb, 'robo.jpg') && !strpos($thumb, 'ninja.jpg')) {
                                        // If it's a parallax layer, try to use the jpg thumb if it exists, otherwise just the layer
                                        $jpgThumb = str_replace(['0.png', '1.png', '2.png'], $id.'.jpg', $thumb);
                                        // This is a bit hacky, but for your specific structure:
                                        if ($id === 'robo') $thumb = '/assets/Background_Img/robo/robo.jpg';
                                        if ($id === 'ninja') $thumb = '/assets/Background_Img/ninja/ninja.jpg';
                                        if ($id === 'robotower') $thumb = '/assets/Background_Img/RoboTower/robo_tower.jpg';
                                        if ($id === 'spiderman') $thumb = '/assets/Background_Img/spiderman/spiderman.jpg';
                                    }
                                    if ($id === 'ironman') $thumb = '/assets/Background_Img/IronMan/0.jpg';
                                ?>
                                <div class="theme-bg-item" onclick="TomBG.setMode('<?= $id ?>')" data-mode="<?= $id ?>">
                                    <div class="theme-bg-thumb-wrapper"><img src="<?= $thumb ?>" class="theme-bg-thumb"></div>
                                    <span class="theme-bg-label"><?= $label ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Light Mode Backgrounds (Same list for now as requested) -->
                            <div class="theme-bg-grid p-3 d-none" id="bg-grid-light" data-theme="light">
                                <?php if (!in_array('plain', $appearanceDisabled, true)): ?>
                                <div class="theme-bg-item" onclick="TomBG.setMode('plain')" data-mode="plain">
                                    <div class="theme-bg-thumb-wrapper"><div style="width:100%; height:100%; background: linear-gradient(45deg, #f8f9fa, #e9ecef);"></div></div>
                                    <span class="theme-bg-label">Plain</span>
                                </div>
                                <?php endif; ?>
                                <?php foreach ($tomThemes as $id => $theme): 
                                    if (in_array($id, $appearanceDisabled, true)) continue;
                                    $label = ($id === 'robo') ? 'Lab' : (($id === 'ninja') ? 'War' : (($id === 'robotower') ? 'Tower' : (($id === 'spiderman') ? 'Spidey' : (($id === 'ironman') ? 'Iron Man' : ucfirst($id)))));
                                    $thumb = $theme['assets'][0];
                                    if ($id === 'robo') $thumb = '/assets/Background_Img/robo/robo.jpg';
                                    if ($id === 'ninja') $thumb = '/assets/Background_Img/ninja/ninja.jpg';
                                    if ($id === 'robotower') $thumb = '/assets/Background_Img/RoboTower/robo_tower.jpg';
                                    if ($id === 'spiderman') $thumb = '/assets/Background_Img/spiderman/spiderman.jpg';
                                    if ($id === 'ironman') $thumb = '/assets/Background_Img/IronMan/0.jpg';
                                ?>
                                <div class="theme-bg-item" onclick="TomBG.setMode('<?= $id ?>')" data-mode="<?= $id ?>">
                                    <div class="theme-bg-thumb-wrapper"><img src="<?= $thumb ?>" class="theme-bg-thumb"></div>
                                    <span class="theme-bg-label"><?= $label ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="dropdown-divider border-white border-opacity-10 my-0"></div>
                            <a class="dropdown-item d-flex align-items-center justify-content-center py-2 text-secondary hover-text-white transition-all" 
                               style="font-size: 0.75rem;" data-coreui-toggle="modal" data-coreui-target="#plainColorModal">
                                <i class="bx bx-color-fill me-1"></i> Custom Theme Designer
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </li>

            <li class="nav-item dropdown ms-2">
                <a class="nav-link py-0 pe-0 d-flex align-items-center" data-coreui-toggle="dropdown" href="#"
                    role="button">
                    <div class="avatar avatar-md shadow-sm border border-secondary border-opacity-25 rounded-circle overflow-hidden">
                        <img class="avatar-img" 
                            src="<?= Session::getAvatar() ?>" 
                            style="<?= Session::getAvatarStyle() ?>"
                            alt="User">
                    </div>
                </a>

                <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 p-2"
                    style="min-width: 200px; border-radius: 12px;">
                    <div class="px-2 py-1">
                        <h6 class="fw-bold mb-1 text-lowercase ls-1" style="font-size: 0.85rem;">
                            <?= Session::getUser()?->getUsername() ?? 'Guest' ?></h6>

                        <div class="d-flex align-items-center mb-1">
                            <span class="text-secondary me-1" style="font-size: 0.7rem;">Vpn Status:</span>
                            <span
                                class="badge rounded-pill bg-danger"
                                style="font-size: 0.5rem;">
                                Not Connected
                            </span>
                        </div>
                    </div>

                    <div class="dropdown-divider mx-n2 mb-1"></div>

                    <div class="list-group list-group-flush">
                        <div class="dropdown-item d-flex align-items-center justify-content-between px-2 py-1 rounded" onclick="event.stopPropagation()">
                            <div class="d-flex align-items-center gap-2">
                                <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                                    <input class="form-check-input m-0 pointer" type="checkbox" role="switch" id="visualBlurToggle" style="float: none; margin-left: 0;"
                                        onchange="TomVisuals.toggleBlur(this.checked); TomVisuals.showRecommendation();">
                                </div>
                                <label class="pointer mb-0" for="visualBlurToggle" style="font-size: 0.8rem; font-weight: 500;">
                                    Visual Blur
                                </label>
                            </div>
                            <i class='bx bx-info-circle text-secondary hover-text-white pointer ms-auto' onclick="TomVisuals.showRecommendation()" style="font-size: 0.95rem;" title="Visuals Recommendation"></i>
                        </div>

                        <a class="dropdown-item d-flex align-items-center px-2 py-1 rounded"
                            href="/<?= Session::getUser()?->getUsername() ?>">
                            <i class="bx bx-shield-alt-2 text-secondary me-2" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 500;">My Account</span>
                        </a>

                        <a class="dropdown-item d-flex align-items-center px-2 py-1 rounded pointer" 
                            data-coreui-toggle="modal" data-coreui-target="#bgSelectModal">
                            <i class="bx bx-palette text-secondary me-2" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 500;">Change Background</span>
                        </a>

                        <!-- <a class="dropdown-item d-flex align-items-center px-2 py-1 rounded pointer" 
                            data-coreui-toggle="modal" data-coreui-target="#plainColorModal">
                            <i class="bx bx-color-fill text-secondary me-2" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 500;">Plain Theme Color</span>
                        </a> -->

                        <a class="dropdown-item d-flex align-items-center px-2 py-1 rounded pointer"
                            onclick="openAccountSettings()">
                            <i class="bx bx-cog text-secondary me-2" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 500;">Account Settings</span>
                        </a>

                        <?php if (Session::getUser()?->getRole() === 'superuser'): ?>
                        <div class="dropdown-divider mx-n2 my-1"></div>
                        <a class="dropdown-item d-flex align-items-center px-2 py-1 rounded pointer text-warning" href="/admin/users">
                            <i class="bx bx-crown me-2" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 600;">Admin Panel</span>
                        </a>
                        <?php endif; ?>

                        <div class="dropdown-divider mx-n2 my-1"></div>

                        <form method="post" action="/" class="m-0 p-0" hx-boost="false">
                            <input type="hidden" name="logout" value="1">
                            <button type="submit" class="dropdown-item d-flex align-items-center px-2 py-1 rounded text-danger border-0 bg-transparent w-100 text-start" style="cursor:pointer;">
                                <i class="bx bx-log-out-circle me-2" style="font-size: 1rem;"></i>
                                <span class="small">Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</header>

<!-- Command Palette Overlay (outside header for proper z-index) -->
<div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)closeSearchPalette()">
    <div class="search-palette">
        <div class="search-palette-input-wrap">
            <i class="bx bx-search search-palette-icon"></i>
            <input type="text" class="search-palette-input" id="paletteInput" placeholder="Search labs, apps and pages..." autocomplete="off">
            <span class="search-palette-esc" onclick="closeSearchPalette()">Esc</span>
        </div>
        <div id="paletteResults" class="search-palette-results">
            <div class="search-palette-empty">Type to search your labs, apps and pages.</div>
        </div>
    </div>
</div>

<?php
$labsSearchItems = [];
if (Session::getAuthStatus() === Constants::STATUS_LOGGEDIN) {
    try {
        $navUser = Session::getUser();
        $navLabTemplates = [
            ['id' => 'essentials',     'name' => 'Essentials Lab'],
            ['id' => 'gui_essentials', 'name' => 'GUI Essentials Lab'],
            ['id' => 'minio',          'name' => 'MinIO S3 Storage'],
            ['id' => 'n8n',            'name' => 'n8n Workflow Lab'],
            ['id' => 'docker_lab',     'name' => 'Docker Lab'],
        ];
        foreach ($navLabTemplates as $tmpl) {
            $hash = $navUser->getLabHash($tmpl['id']);

            $dashUrl = '/labs/dashboard/' . $hash;
            $prefUrl = '/labs/preferences/' . $hash;
            $domUrl  = '/labs/domains/' . $hash;

            $name = $tmpl['name'];

            $labsSearchItems[] = ['title' => $name . ' > Dashboard', 'section' => 'Labs', 'url' => $dashUrl, 'icon' => 'bx-layout', 'cat' => 'Navigation'];
            $labsSearchItems[] = ['title' => $name . ' > Preferences', 'section' => 'Labs', 'url' => $prefUrl, 'icon' => 'bx-slider', 'cat' => 'Navigation'];
            $labsSearchItems[] = ['title' => $name . ' > Domains', 'section' => 'Labs', 'url' => $domUrl, 'icon' => 'bx-globe', 'cat' => 'Navigation'];
            $labsSearchItems[] = ['title' => $name . ' > Activity', 'section' => 'Labs', 'url' => '/labs/activity/' . $hash, 'icon' => 'bx-history', 'cat' => 'Navigation'];
        }
    } catch (\Exception $e) {
        $labsSearchItems = [];
    }
}
?>

<script>
function openSearchPalette() {
    var overlay = document.getElementById('searchOverlay');
    var input = document.getElementById('paletteInput');
    if (overlay) {
        overlay.classList.add('active');
        var results = document.getElementById('paletteResults');
        if (results) results.innerHTML = '<div class="search-palette-empty">Type to search your labs, apps and pages.</div>';
        setTimeout(function() { if (input) { input.value = ''; input.focus(); } }, 50);
    }
}
function closeSearchPalette() {
    var overlay = document.getElementById('searchOverlay');
    if (overlay) overlay.classList.remove('active');
}
document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); openSearchPalette(); }
    if (e.key === 'Escape') { closeSearchPalette(); }
});
</script>
<script>
(function() {
    var paletteInput = document.getElementById('paletteInput');
    var paletteResults = document.getElementById('paletteResults');
    if (!paletteInput || !paletteResults) return;

    var activeIndex = -1;
    var currentQuery = '';
    var debounceTimer = null;

    var groupMeta = {
        admin:      { label: 'Admin Features',  icon: 'tom-crown',   colour: '#f43f5e' },
        devices:    { label: 'Your Devices',    icon: 'tom-devices', colour: '#38bdf8' },
        running:    { label: 'Your Labs',        icon: 'bx-hard-hat',  colour: '#22c55e' },
        catalog:    { label: 'Lab Catalog',       icon: 'bx-diamond',   colour: '#E95420' },
        apps:       { label: 'Pages & Apps',      icon: 'bx-grid-alt',  colour: '#6366f1' },
        challenges: { label: 'Challenges',        icon: 'bx-trophy',    colour: '#ef4444' },
        quiz:       { label: 'Quiz Topics',       icon: 'bx-check-circle', colour: '#8b5cf6' },
        learn:      { label: 'Learn AI',          icon: 'bx-book-open',    colour: '#06b6d4' },
        roadmaps:   { label: 'Roadmaps',          icon: 'bx-map-pin',      colour: '#10b981' },
        syllabus:   { label: 'Syllabus',          icon: 'bx-notes',     colour: '#f472b6' },
    };

    function highlightMatch(text, query) {
        if (!query) return text;
        var tokens = query.split(/\s+/).filter(Boolean).map(function(t) {
            return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        });
        if (!tokens.length) return text;
        tokens.sort(function(a, b) { return b.length - a.length; });
        var re = new RegExp('(' + tokens.join('|') + ')', 'gi');
        return text.replace(re, '<strong>$1</strong>');
    }

    function renderIcon(item) {
        if (item.icon && item.icon.kind === 'image' && item.icon.url) {
            return '<img src="' + item.icon.url + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;" alt="">';
        }
        if (item.icon && item.icon.kind === 'glyph') {
            return '<i class="bx ' + item.icon.glyph + ' result-icon" style="color:' + (item.icon.colour || '#999') + ';"></i>';
        }
        if (item.glyph) {
            return '<i class="bx ' + item.glyph + ' result-icon" style="color:' + (item.colour || '#999') + ';"></i>';
        }
        return '<i class="bx bx-hash result-icon text-secondary"></i>';
    }

    function renderPaletteGroup(key, items, query) {
        var meta = groupMeta[key] || { label: key, icon: 'bx-folder', colour: '#999' };
        var html = '<div class="search-palette-section"><div class="px-3 py-1 text-uppercase fw-bold d-flex align-items-center gap-1" style="font-size:0.65rem;letter-spacing:0.08em;color:' + meta.colour + ';opacity:0.85;">'
            + '<i class="bx ' + meta.icon + '" style="font-size:0.75rem;"></i> ' + meta.label + '</div>';

        items.forEach(function(item) {
            var href = item.href || item.url || '#';
            var isExternal = item.codeserver || false;
            var dataAttr = isExternal ? '' : ' data-full-nav';
            html += '<a href="' + href + '" hx-get="' + href + '" hx-target="#main-content" hx-push-url="true"'
                + ' class="search-palette-item' + (globalIdx === activeIndex ? ' active' : '') + '"'
                + ' data-index="' + globalIdx + '"' + dataAttr + '>'
                + renderIcon(item)
                + '<span class="result-title">' + highlightMatch(item.label || item.title || '', query) + '</span>'
                + '<span class="result-sub">' + (item.sub || '') + '</span>'
                + '<span class="result-action">' + (item.codeserver ? 'Open' : (item.sub ? '' : 'Go')) + '</span>'
                + '</a>';
            globalIdx++;
        });
        html += '</div>';
        return html;
    }

    var globalIdx = 0;

    function renderPaletteResults(data) {
        var groups = data.groups || {};
        var query = data.q || '';
        var order = ['admin', 'devices', 'running', 'catalog', 'apps', 'challenges', 'quiz', 'learn', 'roadmaps', 'syllabus'];
        var hasResults = order.some(function(k) { return groups[k] && groups[k].length > 0; });

        if (!hasResults) {
            paletteResults.innerHTML = '<div class="search-palette-empty">No results found for "' + query + '"</div>';
            return;
        }

        var html = '';
        globalIdx = 0;
        order.forEach(function(key) {
            if (groups[key] && groups[key].length > 0) {
                html += renderPaletteGroup(key, groups[key], query);
            }
        });
        paletteResults.innerHTML = html;
        activeIndex = -1;

        paletteResults.querySelectorAll('.search-palette-item').forEach(function(item) {
            item.addEventListener('mouseenter', function() {
                paletteResults.querySelectorAll('.search-palette-item').forEach(function(el) { el.classList.remove('active'); });
                item.classList.add('active');
                activeIndex = parseInt(item.dataset.index);
            });
            item.addEventListener('click', function(e) {
                e.preventDefault();
                var url = item.getAttribute('href');
                var isLabPage = item.hasAttribute('data-full-nav');
                closeSearchPalette();
                if (isLabPage) {
                    window.location.href = url;
                } else {
                    htmx.ajax('GET', url, {target: '#main-content', push: url});
                }
            });
        });
    }

    function fetchPaletteSearch(query) {
        fetch('/api/search?q=' + encodeURIComponent(query))
            .then(function(r) { return r.json(); })
            .then(function(data) { renderPaletteResults(data); })
            .catch(function() {
                paletteResults.innerHTML = '<div class="search-palette-empty">Search error</div>';
            });
    }

    paletteInput.addEventListener('input', function() {
        currentQuery = paletteInput.value.trim();
        activeIndex = -1;
        clearTimeout(debounceTimer);
        if (!currentQuery || currentQuery.length < 1) {
            paletteResults.innerHTML = '<div class="search-palette-empty">Type to search your labs, apps and pages.</div>';
            return;
        }
        debounceTimer = setTimeout(function() { fetchPaletteSearch(currentQuery); }, 200);
    });

    paletteInput.addEventListener('keydown', function(e) {
        var items = paletteResults.querySelectorAll('.search-palette-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            items.forEach(function(el, i) { el.classList.toggle('active', i === activeIndex); });
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            items.forEach(function(el, i) { el.classList.toggle('active', i === activeIndex); });
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            items[activeIndex].click();
        } else if (e.key === 'Escape') {
            closeSearchPalette();
        }
    });
})();
</script>

<script>
document.body.addEventListener('htmx:afterSettle', function(e) {
    if (!e.detail || !e.detail.xhr) return;
    var bc = document.getElementById('main-breadcrumb');
    if (!bc) return;
    try {
        var resp = e.detail.xhr.responseText;
        var match = resp.match(/<script[^>]*id="htmx-page-bootstrap"[^>]*type="application\/json"[^>]*>([\s\S]*?)<\/script>/);
        if (!match) return;
        var data = JSON.parse(match[1]);
        if (!data.breadcrumbs || !data.breadcrumbs.length) return;
        var html = '';
        data.breadcrumbs.forEach(function(crumb, i) {
            var isLast = (i === data.breadcrumbs.length - 1);
            if (isLast) {
                html += '<li class="breadcrumb-item active" aria-current="page"><span class="fw-semibold" style="color: var(--cui-body-color); font-size: 0.85rem;">' + crumb.name + '</span></li>';
            } else {
                html += '<li class="breadcrumb-item"><a href="' + crumb.uri + '" class="text-decoration-none fw-medium" style="color: var(--cui-secondary-color); font-size: 0.85rem;">' + crumb.name + '</a></li>';
            }
        });
        bc.innerHTML = html;
        document.title = data.title || data.breadcrumbs[data.breadcrumbs.length - 1].name;
    } catch(ex) {}
});
</script>

<script>
(function() {
    var dropdown      = document.getElementById('notif-dropdown');
    var badgeEl       = document.getElementById('notif-badge');
    var unreadCountEl = document.getElementById('notif-unread-count');
    var markAllBtn    = document.getElementById('notif-mark-all');
    var bellBtn       = document.getElementById('notif-bell');
    var tabsEl        = document.getElementById('notif-tabs');
    if (!dropdown || !tabsEl) return;

    var PAGE_SIZE = 20;
    var tabButtons = Array.prototype.slice.call(tabsEl.querySelectorAll('.tab-button'));
    var state = {};
    tabButtons.forEach(function(btn) {
        var cat = btn.getAttribute('data-cat');
        if (!cat) return;
        state[cat] = { items: [], loaded: false, loading: false, hasMore: true, error: false };
    });
    var currentTab = tabButtons.length ? tabButtons[0].getAttribute('data-cat') : null;

    var META = {
        system:      { icon: 'tom-terminal-window',  color: '#6366f1' },
        mcp:         { icon: 'tom-plug',             color: '#3b82f6' },
        clan:        { icon: 'tom-users-three',      color: '#22c55e' },
        clubs:       { icon: 'tom-books',            color: '#f59e0b' },
        code_arena:  { icon: 'tom-code',             color: '#a855f7' },
        discussions: { icon: 'tom-chat-circle-text', color: '#ec4899' },
        quiz:        { icon: 'tom-question',         color: '#6366f1' },
        score:       { icon: 'tom-chart-line',       color: '#fb923c' },
        streak:      { icon: 'tom-fire',             color: '#f43f5e' },
        academy:     { icon: 'tom-graduation-cap',   color: '#14b8a6' },
        report:      { icon: 'tom-flag',             color: '#ef4444' }
    };

    function metaFor(cat) { return META[cat] || { icon: 'tom-bell', color: '#6366f1' }; }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function jsArg(s) {
        return String(s == null ? '' : s)
            .replace(/\\/g, '\\\\').replace(/'/g, "\\'")
            .replace(/\r/g, '\\r').replace(/\n/g, '\\n').replace(/</g, '\\x3c');
    }

    function prettyLabel(s) {
        if (!s) return '';
        return String(s).replace(/[_-]+/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function renderTimeAgo(ts) {
        if (!ts) return 'just now';
        var diff = Math.floor((Date.now() - ts * 1000) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 2592000) return Math.floor(diff / 86400) + ' day ago';
        return Math.floor(diff / 2592000) + ' month ago';
    }

    function paneFor(cat) { return document.getElementById('notif-pane-' + cat); }

    function itemHtml(n) {
        var cat = n.category || 'system';
        var m = metaFor(cat);
        var url = (n.data && n.data.url) ? n.data.url : '#';
        var subtitle = (n.data && n.data.subtitle) ? n.data.subtitle : prettyLabel(n.type || cat);
        var title = n.title || 'Notification';
        var body = n.body || '';
        var idArg = jsArg(n.id);
        var urlArg = jsArg(url);

        return '<a class="dropdown-item blur-item notification-item mt-1' + (n.read ? '' : ' notif-item-unread') + '"' +
            ' href="' + esc(url) + '"' +
            ' data-id="' + esc(n.id) + '"' +
            ' data-read="' + (n.read ? 'true' : 'false') + '"' +
            ' onclick="return notifItemClick(event, \'' + idArg + '\', \'' + urlArg + '\');">' +
            '<div class="message">' +
                '<div class="notif-avatar flex-shrink-0" style="background:' + m.color + '1f;color:' + m.color + ';">' +
                    '<svg class="icon" viewBox="0 0 256 256" style="width:22px;height:22px;"><use href="/assets/icons/duotone.svg#' + m.icon + '"></use></svg>' +
                '</div>' +
                '<div class="flex-grow-1 min-w-0">' +
                    '<div class="d-flex align-items-center justify-content-between gap-2">' +
                        '<small class="text-small-emphasis notification-subtitle text-truncate">' + esc(subtitle) + '</small>' +
                        '<small class="text-small-emphasis notification-time">' + esc(renderTimeAgo(n.created_at)) + '</small>' +
                    '</div>' +
                    '<div class="text-truncate font-weight-bold notification-title">' + esc(title) + '</div>' +
                    (body ? '<div class="small text-medium-emphasis text-truncate notification-message">' + esc(body) + '</div>' : '') +
                '</div>' +
            '</div>' +
        '</a>';
    }

    function renderPane(cat) {
        var pane = paneFor(cat);
        if (!pane) return;
        var s = state[cat];
        if (!s) return;

        var itemsEl = pane.querySelector('.notif-items');
        var spinner = pane.querySelector('.notif-spinner');
        var emptyEl = pane.querySelector('.notif-empty');
        var loadBtn = pane.querySelector('.load-button');
        if (!itemsEl) return;

        itemsEl.innerHTML = s.items.map(itemHtml).join('');
        if (spinner) spinner.style.display = s.loading ? 'flex' : 'none';
        if (emptyEl) emptyEl.style.display = (s.loaded && !s.loading && !s.error && s.items.length === 0) ? 'block' : 'none';
        if (loadBtn) loadBtn.style.display = (!s.loading && s.loaded && s.hasMore && s.items.length > 0) ? 'block' : 'none';
    }

    function loadTab(cat, more) {
        var s = state[cat];
        if (!s || s.loading) return;
        if (!more && s.loaded && !s.error) return;

        s.loading = true;
        s.error = false;
        renderPane(cat);

        var offset = more ? s.items.length : 0;
        var url = '/api/notifications/list?limit=' + PAGE_SIZE + '&offset=' + offset + '&category=' + encodeURIComponent(cat);

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success) throw new Error('bad response');
            var incoming = data.items || [];
            s.items = more ? s.items.concat(incoming) : incoming;
            s.hasMore = incoming.length >= PAGE_SIZE;
            s.loaded = true;
            s.loading = false;
            s.error = false;
            renderPane(cat);
            fetchUnreadCount();
        })
        .catch(function() {
            s.loading = false;
            s.loaded = true;
            s.error = true;
            renderPane(cat);
            var pane = paneFor(cat);
            var itemsEl = pane ? pane.querySelector('.notif-items') : null;
            if (itemsEl && s.items.length === 0) {
                itemsEl.innerHTML = '<div class="px-3 py-3 text-center text-secondary small">Unable to load notifications.</div>';
            }
        });
    }

    function setActiveTab(cat) {
        tabButtons.forEach(function(btn) {
            btn.classList.toggle('active-tab', btn.getAttribute('data-cat') === cat);
        });
        Object.keys(state).forEach(function(key) {
            var pane = paneFor(key);
            if (pane) pane.style.display = (key === cat) ? 'block' : 'none';
        });
    }

    window.notifSwitchTab = function(cat, el, ev) {
        if (ev) ev.stopPropagation();
        if (!state[cat]) return;
        currentTab = cat;
        setActiveTab(cat);
        loadTab(cat, false);
    };

    window.notifLoadMore = function(cat) {
        loadTab(cat, true);
    };

    window.notifItemClick = function(ev, id, href) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }

        Object.keys(state).forEach(function(cat) {
            state[cat].items.forEach(function(n) {
                if (n.id === id) n.read = true;
            });
        });
        renderPane(currentTab);

        fetch('/api/notifications/read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ids: [id] })
        }).then(fetchUnreadCount).catch(function() {});

        if (href && href !== '#' && href !== 'javascript:void(0)') {
            window.location.href = href;
        }
        return false;
    };

    window.notifMarkAllRead = function(ev) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }

        fetch('/api/notifications/read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ all: true })
        }).then(function() {
            Object.keys(state).forEach(function(cat) {
                state[cat].items.forEach(function(n) { n.read = true; });
                state[cat].loaded = false;
                state[cat].error = false;
                renderPane(cat);
            });
            loadTab(currentTab, false);
            fetchUnreadCount();
        }).catch(function() {});
    };

    function fetchUnreadCount() {
        fetch('/api/notifications/unread_count', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success) return;
            var total = parseInt(data.total || 0, 10);
            if (badgeEl) {
                badgeEl.style.display = total > 0 ? '' : 'none';
                badgeEl.textContent = total > 99 ? '99+' : String(total);
            }
            if (unreadCountEl) unreadCountEl.textContent = String(total);
            if (markAllBtn) markAllBtn.style.display = total > 0 ? '' : 'none';

            var byCat = data.by_category || {};
            tabButtons.forEach(function(btn) {
                var cat = btn.getAttribute('data-cat');
                var el = btn.querySelector('.notification-type-indicator');
                if (!el) return;
                var c = parseInt(byCat[cat] || 0, 10);
                if (c > 0) {
                    el.style.display = '';
                    el.textContent = c > 9 ? '9+' : String(c);
                } else {
                    el.style.display = 'none';
                }
            });
        })
        .catch(function() {});
    }

    window.notifCloseDropdown = function(ev) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        dropdown.style.display = 'none';
        if (bellBtn) bellBtn.setAttribute('aria-expanded', 'false');
    };

    function openDropdown() {
        dropdown.style.display = 'flex';
        if (bellBtn) bellBtn.setAttribute('aria-expanded', 'true');
        if (currentTab && state[currentTab] && !state[currentTab].loaded) {
            loadTab(currentTab, false);
        }
        fetchUnreadCount();
    }

    dropdown.style.display = 'none';
    if (bellBtn) bellBtn.setAttribute('aria-expanded', 'false');

    window.notifToggleDropdown = function(ev) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        if (dropdown.style.display === 'flex') {
            window.notifCloseDropdown();
        } else {
            openDropdown();
        }
    };

    document.addEventListener('click', function(ev) {
        if (!dropdown || !bellBtn) return;
        if (dropdown.style.display === 'flex' && !dropdown.contains(ev.target) && !bellBtn.contains(ev.target)) {
            dropdown.style.display = 'none';
            bellBtn.setAttribute('aria-expanded', 'false');
        }
    });

    fetchUnreadCount();
})();
</script>

