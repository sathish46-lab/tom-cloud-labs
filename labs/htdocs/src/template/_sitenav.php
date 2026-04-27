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
                    <li class="breadcrumb-item">
                        <a href="/" class="text-decoration-none text-secondary small fw-medium">Home</a>
                    </li>
                    <?php
                    $titleParts = explode(' / ', Session::$pageTitle);
                    $count = count($titleParts);
                    
                    foreach ($titleParts as $index => $part) {
                        $isLast = ($index === $count - 1);
                        $url = null;
                        $displayPart = $part;

                        // Logic to map common labels to URLs
                        if (strtolower($part) === 'dashboard') {
                            $url = '/dashboard';
                        } elseif (strtolower($part) === 'lab' || strtolower($part) === 'labs') {
                            $url = '/labs';
                            $displayPart = 'Labs';
                        } elseif (strtolower($part) === 'challenges') {
                            $url = '/challenges';
                            $displayPart = 'Challenges';
                        }
                        
                        if ($isLast) {
                            echo '<li class="breadcrumb-item active"><span class="small fw-bold text-white">' . htmlspecialchars($displayPart) . '</span></li>';
                        } elseif ($url) {
                            echo '<li class="breadcrumb-item"><a href="' . $url . '" class="text-decoration-none text-secondary small fw-medium">' . htmlspecialchars($displayPart) . '</a></li>';
                        } else {
                            echo '<li class="breadcrumb-item"><span class="text-secondary small fw-medium">' . htmlspecialchars($displayPart) . '</span></li>';
                        }
                    }
                    ?>
                </ol>
            </nav>
        </div>

        <ul class="header-nav ms-auto mb-0 align-items-center"></ul>

        <ul class="header-nav mb-0 align-items-center">
            <li class="nav-item dropdown">
                <button class="btn btn-link nav-link py-0 px-2 d-flex align-items-center justify-content-center"
                    type="button" data-coreui-toggle="dropdown" style="height: 40px; width: 40px;">
                    <i class="bx theme-icon-active fs-4" id="currentThemeIcon"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item d-flex align-items-center" onclick="changeTheme('light')"><i
                                class="bx bx-sun me-2"></i> Light</button></li>
                    <li><button class="dropdown-item d-flex align-items-center" onclick="changeTheme('dark')"><i
                                class="bx bx-moon me-2"></i> Dark</button></li>
                    <li><button class="dropdown-item d-flex align-items-center" onclick="changeTheme('auto')"><i
                                class="bx bx-circle-half me-2"></i> Auto</button></li>
                </ul>
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