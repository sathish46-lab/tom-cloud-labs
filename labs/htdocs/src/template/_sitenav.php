<header class="header header-sticky mb-0" style="border-bottom: none; height: 4rem; min-height: 4rem;">
    <div class="container-fluid px-4 d-flex align-items-center h-100">

        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link p-0 text-secondary hover-text-white d-flex align-items-center justify-content-center rounded-circle border border-white border-opacity-10 text-decoration-none shadow-none" 
                    onclick="history.back()" 
                    style="width: 32px; height: 32px; transition: all 0.2s;">
                <i class="bx bx-left-arrow-alt fs-4"></i>
            </button>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb my-0">
                    <?php
                    $titleParts = explode(' / ', Session::$pageTitle);
                    $labHash = Session::get('full_instance_hash');
                    $challengeHash = Session::get('challenge_instance_hash');
                    
                    // Always prepend Home if it's not there
                    if (strtolower(trim($titleParts[0])) !== 'home') {
                        array_unshift($titleParts, 'Home');
                    }

                    $count = count($titleParts);
                    $hasLabsContext = false;
                    $hasChallengesContext = false;
                    
                    foreach ($titleParts as $index => $part) {
                        $isLast = ($index === $count - 1);
                        $displayPart = trim($part);
                        $url = null;
                        $lowerPart = strtolower($displayPart);

                        // Check context
                        if (stripos($lowerPart, 'lab') !== false) {
                            $hasLabsContext = true;
                        }
                        if (stripos($lowerPart, 'challenge') !== false) {
                            $hasChallengesContext = true;
                        }

                        // 1. Quiz Hub Context Mapping (High Priority Context)
                        $quizParent = Session::get('parent_topic');
                        $quizSubtopic = Session::get('current_subtopic');
                        $quizCategory = Session::get('current_topic');

                        if (strcasecmp($lowerPart, 'quiz') === 0) {
                            $url = '/quiz';
                        } elseif (strcasecmp($lowerPart, 'spot quiz') === 0) {
                            $quiz = Session::get('current_quiz');
                            $url = $quiz ? "/quiz/v/" . $quiz['hash'] : '/quiz';
                        } elseif (($quizParent && strcasecmp(trim($displayPart), trim($quizParent['title'])) === 0) || 
                                  ($quizCategory && strcasecmp(trim($displayPart), trim($quizCategory['title'])) === 0)) {
                            $cat = $quizParent ?? $quizCategory;
                            $url = "/quiz/" . ($cat['id'] ?? $cat['_id']);
                        } elseif ($quizSubtopic && strcasecmp(trim($displayPart), trim($quizSubtopic['title'])) === 0) {
                            $catId = $quizParent ? ($quizParent['id'] ?? $quizParent['_id']) : ($quizCategory ? ($quizCategory['id'] ?? $quizCategory['_id']) : 'all');
                            $url = "/quiz/$catId/Recent/" . ($quizSubtopic['id'] ?? $quizSubtopic['_id']);
                        
                        // 2. Standard Global Mappings (Lower Priority Keywords)
                        } elseif (stripos($lowerPart, 'home') !== false) {
                            $url = '/';
                        } elseif (stripos($lowerPart, 'dashboard') !== false) {
                            if ($hasLabsContext && $labHash) {
                                $url = "/labs/dashboard/$labHash";
                            } elseif ($hasChallengesContext && $challengeHash) {
                                $url = "/challenges/dashboard/$challengeHash";
                            } else {
                                $url = '/dashboard';
                            }
                        } elseif ($lowerPart === 'lab' || $lowerPart === 'labs') {
                            $url = '/labs';
                            $displayPart = 'Labs';
                        } elseif ($lowerPart === 'challenge' || $lowerPart === 'challenges') {
                            $url = '/challenges';
                            $displayPart = 'Challenges';
                        } elseif (stripos($lowerPart, 'device') !== false) {
                            $url = '/devices';
                        } elseif (stripos($lowerPart, 'network') !== false) {
                            $url = '/network';
                        } elseif (stripos($lowerPart, 'domain') !== false) {
                            if ($hasLabsContext && $labHash) {
                                $url = "/labs/domains/$labHash";
                            } else {
                                $url = '/domains';
                            }
                        } elseif (stripos($lowerPart, 'pref') !== false && $hasLabsContext && $labHash) {
                            $url = "/labs/preferences/$labHash";
                        } elseif (stripos($lowerPart, 'account') !== false) {
                            $url = '/account';
                        } elseif (stripos($lowerPart, 'achieve') !== false && $challengeHash) {
                            $url = "/challenges/achievements/$challengeHash";
                        } elseif (stripos($lowerPart, 'leader') !== false && $challengeHash) {
                            $url = "/challenges/leaderboard/$challengeHash";
                        }

                        $href = $url ? $url : '#';
                        
                        if ($isLast) {
                            echo '<li class="breadcrumb-item active"><a href="' . $href . '" class="text-decoration-none small fw-bold theme-text transition-all">' . htmlspecialchars($displayPart) . '</a></li>';
                        } else {
                            echo '<li class="breadcrumb-item"><a href="' . $href . '" class="text-decoration-none hover-theme-text small fw-medium transition-all" style="color: var(--glass-text-muted);">' . htmlspecialchars($displayPart) . '</a></li>';
                        }
                    }
                    ?>
                </ol>
            </nav>
        </div>

        <ul class="header-nav ms-auto mb-0 align-items-center list-unstyled d-flex">
            <?php 
                $navUser = Session::getUser();
                $navStats = $navUser ? \TomLabs\Labs\Quiz::getUserStats($navUser->getEmail()) : ['zeal' => 0, 'jolt' => 10];
            ?>
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
        
            <!-- Theme & Background Mega Dropdown -->
            <li class="nav-item dropdown px-2">
                <button class="btn btn-link nav-link py-0 d-flex align-items-center justify-content-center theme-selector-btn"
                    type="button" data-coreui-toggle="dropdown" style="height: 40px; width: 40px;">
                    <i class="bx theme-icon-active fs-4" id="currentThemeIcon"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end theme-mega-container border-0 bg-transparent shadow-none p-0">
                    <div class="d-flex align-items-start gap-3 p-2 flex-row-reverse">
                        <!-- Mode Selector Card (Rightmost, under the icon) -->
                        <div class="mode-selector-card shadow-lg">
                            <button class="mode-item" onclick="changeTheme('light')" data-coreui-value="light">
                                <i class='bx bx-sun'></i> <span>Light</span>
                            </button>
                            <button class="mode-item" onclick="changeTheme('dark')" data-coreui-value="dark">
                                <i class='bx bx-moon'></i> <span>Dark</span>
                            </button>
                            <button class="mode-item" onclick="changeTheme('auto')" data-coreui-value="auto">
                                <i class='bx bx-circle-half'></i> <span>Auto</span>
                            </button>
                        </div>

                        <!-- Background Selector Card (Reveals to the left) -->
                        <div class="bg-selector-card shadow-lg">
                            <div class="theme-bg-grid p-3">
                                <div class="theme-bg-item" onclick="TomBG.setMode('plain')" data-mode="plain">
                                    <div class="theme-bg-thumb-wrapper">
                                        <div style="width:100%; height:100%; background: linear-gradient(45deg, #010d12, #0b1e36);"></div>
                                    </div>
                                    <span class="theme-bg-label">Plain</span>
                                </div>
                                <div class="theme-bg-item" onclick="TomBG.setMode('ninja')" data-mode="ninja">
                                    <div class="theme-bg-thumb-wrapper">
                                        <img src="/assets/Background_Img/ninja/ninja.jpg" class="theme-bg-thumb">
                                    </div>
                                    <span class="theme-bg-label">Ninja</span>
                                </div>
                                <div class="theme-bg-item" onclick="TomBG.setMode('robo')" data-mode="robo">
                                    <div class="theme-bg-thumb-wrapper">
                                        <img src="/assets/Background_Img/robo/robo.jpg" class="theme-bg-thumb">
                                    </div>
                                    <span class="theme-bg-label">Robot</span>
                                </div>
                                <div class="theme-bg-item" onclick="TomBG.setMode('robotower')" data-mode="robotower">
                                    <div class="theme-bg-thumb-wrapper">
                                        <img src="/assets/Background_Img/RoboTower/robo_tower.jpg" class="theme-bg-thumb">
                                    </div>
                                    <span class="theme-bg-label">Tower</span>
                                </div>
                                <div class="theme-bg-item" onclick="TomBG.setMode('parallax')" data-mode="parallax">
                                    <div class="theme-bg-thumb-wrapper">
                                        <img src="/assets/Background_Img/ninja/0.png" class="theme-bg-thumb">
                                    </div>
                                    <span class="theme-bg-label">Parallax</span>
                                </div>
                            </div>
                            <div class="dropdown-divider border-white border-opacity-10 my-0"></div>
                            <a class="dropdown-item d-flex align-items-center justify-content-center py-2 text-secondary hover-text-white transition-all" 
                               style="font-size: 0.75rem;" data-coreui-toggle="modal" data-coreui-target="#plainColorModal">
                                <i class="bx bx-color-fill me-1"></i> Custom Theme Designer
                            </a>
                        </div>
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

                <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 p-3"
                    style="min-width: 250px; border-radius: 15px;">
                    <div class="px-2 py-1">
                        <h6 class="fw-bold mb-1 text-lowercase ls-1">
                            <?= Session::getUser()?->getUsername() ?? 'Guest' ?></h6>

                        <div class="d-flex align-items-center mb-2">
                            <span class="small text-secondary me-2">Vpn Status:</span>
                            <span
                                class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"
                                style="font-size: 0.5rem;">
                                Not Connected
                            </span>
                        </div>
                    </div>

                    <div class="dropdown-divider mx-n3 mb-2"></div>

                    <div class="list-group list-group-flush">
                        <div class="dropdown-item d-flex align-items-center px-2 py-2 rounded" onclick="event.stopPropagation()">
                            <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="visualBlurToggle" 
                                    onchange="TomVisuals.toggleBlur(this.checked)">
                                <label class="form-check-label small fw-semibold d-flex align-items-center gap-1" for="visualBlurToggle">
                                    Visual Blur 
                                    <i class='bx bx-info-circle opacity-50 pointer' onclick="TomVisuals.showRecommendation()" style="font-size: 0.8rem;"></i>
                                </label>
                            </div>
                        </div>

                        <a class="dropdown-item d-flex align-items-center px-2 py-2 rounded"
                            href="/<?= Session::getUser()?->getUsername() ?>">
                            <i class="bx bx-shield-alt-2 fs-5 me-2 text-secondary"></i>
                            <span class="small fw-semibold">My Account</span>
                        </a>

                        <a class="dropdown-item d-flex align-items-center px-2 py-2 rounded pointer" 
                            data-coreui-toggle="modal" data-coreui-target="#bgSelectModal">
                            <i class="bx bx-palette fs-5 me-2 text-secondary"></i>
                            <span class="small fw-semibold">Change Background</span>
                        </a>

                        <a class="dropdown-item d-flex align-items-center px-2 py-2 rounded pointer" 
                            data-coreui-toggle="modal" data-coreui-target="#plainColorModal">
                            <i class="bx bx-color-fill fs-5 me-2 text-secondary"></i>
                            <span class="small fw-semibold">Plain Theme Color</span>
                        </a>

                        <div class="dropdown-divider mx-n3 my-2"></div>

                        <a class="dropdown-item d-flex align-items-center px-2 py-2 rounded text-danger fw-bold"
                            href="?logout=1">
                            <i class="bx bx-log-out-circle fs-5 me-2"></i>
                            <span class="small">Logout</span>
                        </a>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</header>