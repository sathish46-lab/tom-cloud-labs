<?php
/**
 * Challenge Labs Index Page Template
 * Location: /src/template/pages/challenges.php
 */
$user = Session::getUser();
$userId = (int)$user->getUserId();

// Load dynamic challenges configuration from JSON
$jsonPath = __DIR__ . '/../../config/challenges.json';
$challenges = [];
if (file_exists($jsonPath)) {
    $challenges = json_decode(file_get_contents($jsonPath), true) ?? [];
}
$total = count($challenges);

// Fetch actual running challenge labs from database
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$username = $user->getUsername();

// Fetch running challenges to correctly label "Running" vs "Instance Down"
$runningInstancesCursor = $db->challenge_instances->find(['username' => $username, 'status' => 'running']);
$runningInstances = [];
$runningHashes = [];
foreach ($runningInstancesCursor as $ri) {
    // The database saves "flask_server_side_injection", so we normalize it to match "flask-server-side-injection" from the config
    $key = str_replace('_', '-', $ri['challenge_id']);
    $runningInstances[] = $key;
    $runningHashes[$key] = $ri['instance_hash'] ?? 'N/A';
}

// Fetch ALL challenge instances for this user to get max completed tasks
$allInstancesCursor = $db->challenge_instances->find(['username' => $username]);
$challengeProgress = [];
foreach ($allInstancesCursor as $instance) {
    $key = str_replace('_', '-', $instance['challenge_id'] ?? '');
    if (!$key) continue;
    $completed = $instance['challenges_completed'] ?? 0;
    if (!isset($challengeProgress[$key]) || $completed > $challengeProgress[$key]) {
        $challengeProgress[$key] = $completed;
    }
}

// Pre-load tasks data for progress calculation
$tasksData = [];
$tasksJsonPath = __DIR__ . '/../../config/challenge_tasks.json';
if (file_exists($tasksJsonPath)) {
    $tasksData = json_decode(file_get_contents($tasksJsonPath), true) ?? [];
}

$running = count($runningInstances);
$percent = $total > 0 ? ($running / $total) * 100 : 0;
?>

<?php if (isset($_GET['ajax'])) {
    while (ob_get_level()) ob_end_clean();
} ?>
<script>window.savedChallengeFilters = <?= json_encode($_SESSION['challenge_filters'] ?? []) ?>;</script>

<?php if (!isset($_GET['ajax'])): ?>
<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-start">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Challenge Labs</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                Challenge Labs offer a realm of cybersecurity and ethical hacking. Engage in real-world hacking scenarios, learning network security and penetration testing. Each lab is a stepping stone in your cybersecurity expertise journey.
            </p>
        </div>
        <div class="col-auto">
            <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-width: 140px;">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <span class="fw-bold theme-text" style="font-size: 2.2rem; line-height: 1;"><?= $running ?></span>
                    <span class="text-secondary opacity-50 ms-2" style="font-size: 1.1rem; font-weight: 500; margin-top: 8px;">/ <?= $total ?></span>
                </div>
                <div class="progress bg-secondary bg-opacity-10 rounded-pill mb-2 w-100" style="height: 6px; max-width: 120px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="text-secondary opacity-50 text-uppercase fw-bold ls-1 mb-2" style="font-size: 9px; letter-spacing: 0.8px;">Running Labs</div>
                <button class="btn w-100 rounded-3 d-flex align-items-center justify-content-center gap-2 transition-all mt-1" 
                        data-coreui-toggle="modal" 
                        data-coreui-target="#badgeExplanationModal"
                        style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); padding: 6px 10px; color: rgba(255, 255, 255, 0.8);"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.color='#fff';"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.05)'; this.style.color='rgba(255, 255, 255, 0.8)';">
                    <i class='bx bx-info-circle text-info' style="font-size: 1.05rem;"></i>
                    <span class="fw-bold" style="font-size: 0.75rem; letter-spacing: 0.2px;">Badge Guide</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mb-4 position-relative z-3">
    <!-- The expandable search wrapper -->
    <div id="expandableSearchContainer" class="dropdown position-relative">
        
        <!-- Floating Clear / Close Badge (Hidden initially) -->
        <button id="searchCloseBtn" type="button" class="btn p-0 d-none align-items-center justify-content-center rounded-circle border-0 text-white shadow-sm position-absolute">
            <i class='bx bx-x'></i>
        </button>

        <!-- The visual search bar -->
        <div id="searchBarUI" class="d-flex align-items-center rounded-pill w-100">
            <div class="ps-3 pe-2 text-white-50 d-flex align-items-center justify-content-center">
                <i class='bx bx-search fs-5 text-white'></i>
            </div>
            
            <input type="text" id="challengeSearchInput" class="form-control bg-transparent border-0 text-white shadow-none p-0 h-100" placeholder="Search" style="display: none;">
            <span id="searchLabel" class="text-white fw-bold w-100">Search</span>
            
            <!-- Filter Button (Hidden initially) -->
            <button id="filterToggleBtn" class="btn btn-link text-decoration-none border-0 p-0 pe-3 ps-2 d-none align-items-center justify-content-center h-100 shadow-none ms-auto" type="button" data-coreui-toggle="dropdown" aria-expanded="false" data-coreui-auto-close="outside">
                <i class='bx bx-list-ul' style="color: #ff9f43; font-size: 1.5rem;"></i>
            </button>
            
            <!-- Filter Dropdown Menu -->
            <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 custom-search-dropdown p-0 mt-2" style="background-color: #1e1e1e; width: 220px;">
                <div class="p-4" style="max-height: 400px; overflow-y: auto;">
                    
                    <h6>Plan</h6>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="filterPremium">
                        <label class="form-check-label text-white small" for="filterPremium">Premium</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-3">
                        <input class="form-check-input" type="checkbox" id="filterFree">
                        <label class="form-check-label text-white small" for="filterFree">Free</label>
                    </div>

                    <h6>Filter</h6>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="filterTeam">
                        <label class="form-check-label text-white small" for="filterTeam">Team</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="filterEvent">
                        <label class="form-check-label text-white small" for="filterEvent">Event</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="filterSolo">
                        <label class="form-check-label text-white small" for="filterSolo">Solo</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-3">
                        <input class="form-check-input" type="checkbox" id="filterRetired">
                        <label class="form-check-label text-white small" for="filterRetired">Retired</label>
                    </div>

                    <h6>Sort</h6>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="sortNew">
                        <label class="form-check-label text-white small" for="sortNew">New</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="sortPartial">
                        <label class="form-check-label text-white small" for="sortPartial">Partial</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-3">
                        <input class="form-check-input" type="checkbox" id="sortCompleted">
                        <label class="form-check-label text-white small" for="sortCompleted">Completed</label>
                    </div>

                    <h6>Level</h6>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="levelEasy">
                        <label class="form-check-label text-white small" for="levelEasy">Easy</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="levelMedium">
                        <label class="form-check-label text-white small" for="levelMedium">Medium</label>
                    </div>
                    <div class="form-check form-switch custom-switch-dark mb-2">
                        <input class="form-check-input" type="checkbox" id="levelHard">
                        <label class="form-check-label text-white small" for="levelHard">Hard</label>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>


<div class="container-fluid px-0">
    <div id="challengesGrid" class="row g-3 pb-5 position-relative">
        <?php $cardIndex = 0; foreach ($challenges as $c): ?>
        <?php
            // STEP 1: Visibility - If 'card' parameter is false, hide the card completely
            $isVisible = isset($c['card']) ? (bool)$c['card'] : true;
            if (!$isVisible) {
                continue;
            }

            // STEP 2: Activation - Check combined release date/time and the 'challenge' enable flag
            $releaseDateStr = isset($c['release_date']) ? $c['release_date'] : '';
            $releaseTimeStr = isset($c['release_time']) ? $c['release_time'] : '12:00 AM';
            $releaseTime = strtotime($releaseDateStr . ' ' . $releaseTimeStr);
            if ($releaseTime === false) {
                $releaseTime = 0;
            }
            
            $isReleased = ($releaseTime <= time());
            $isChallengeActive = isset($c['challenge']) ? (bool)$c['challenge'] : true;
            
            // Fully unlocked / deployable only if the release time has passed AND challenge flag is enabled
            $isUnlocked = ($isReleased && $isChallengeActive);

            $labId = $c['lab_id'];
            $normalizedId = str_replace('_', '-', $labId);
            $completedTasks = $challengeProgress[$normalizedId] ?? 0;
            
            // Get total tasks for this challenge from DB or JSON
            $tasksCursor = $db->challenge_tasks->find(['lab_id' => $labId]);
            $totalTasksForLab = count($tasksCursor ? $tasksCursor->toArray() : []);
            if ($totalTasksForLab === 0) {
                $totalTasksForLab = count($tasksData[$labId] ?? $tasksData[$normalizedId] ?? []);
            }
            if ($totalTasksForLab === 0) {
                $totalTasksForLab = 1; // Default to 1 to avoid division by zero
            }

            $isCompleted = ($completedTasks >= $totalTasksForLab && $totalTasksForLab > 0);
            $isPartial = ($completedTasks > 0 && $completedTasks < $totalTasksForLab);

            // Compute ribbon styles dynamically
            if ($isUnlocked) {
                if ($isCompleted) {
                    $ribbonText1 = $c['ribbon_text1'] ?? 'FINISHED';
                    $ribbonClass = 'live-ribbon'; // Green
                } else if ($isPartial) {
                    $ribbonText1 = $c['ribbon_text1'] ?? 'PARTIAL';
                    $ribbonClass = 'event-retired-ribbon'; // Orange
                } else {
                    $ribbonText1 = $c['ribbon_text1'] ?? 'ACTIVE';
                    $ribbonClass = $c['ribbon_class'] ?? 'event-ended-ribbon'; // Red
                }
            } else {
                $ribbonText1 = $c['ribbon_text1'] ?? (!$isReleased ? 'Upcoming' : 'Not Active');
                $ribbonClass = $c['ribbon_class'] ?? 'event-retired-ribbon';
            }
            
            $ribbonText2 = $c['ribbon_text2'] ?? 'Yukthi';
            
            // Grayscale layout if locked
            $cardLockedClass = $isUnlocked ? '' : 'opacity-75 grayscale-locked';
            
            $isRunning = in_array($c['lab_id'], $runningInstances);
            $instanceHash = $isRunning ? ($runningHashes[$c['lab_id']] ?? 'N/A') : 'Not Running';
            $statusText = $isUnlocked ? ($isRunning ? 'Running' : 'Instance Down') : (!$isReleased ? 'Coming Soon' : 'Not Active');
            $statusColorClass = $isRunning ? 'text-success' : 'text-white-50';

            $cardTitle = strtolower(htmlspecialchars($c['name'] ?? ''));
            $cardTagsArray = array_map(function($t){ return strtolower($t['text']); }, $c['tags'] ?? []);
            $cardTags = htmlspecialchars(implode(' ', $cardTagsArray));
            $cardStatus = $isCompleted ? 'completed' : ($isPartial ? 'partial' : 'new');
            $cardPlan = (isset($c['points']) && $c['points'] > 0) ? 'premium' : 'free';
            $cardIsRetired = $isUnlocked ? 'false' : 'true';

            // Server-Side Filtering Logic
            $sessFilters = $_SESSION['challenge_filters'] ?? [];
            $qSearch = strtolower(trim($sessFilters['q'] ?? ''));
            $fPlans = !empty($sessFilters['plan']) ? explode(',', $sessFilters['plan']) : [];
            $fFilters = !empty($sessFilters['filter']) ? explode(',', $sessFilters['filter']) : [];
            $fSorts = !empty($sessFilters['sort']) ? explode(',', $sessFilters['sort']) : [];
            $fLevels = !empty($sessFilters['level']) ? explode(',', $sessFilters['level']) : [];


            $showCard = true;
            
            if ($qSearch !== '') {
                if (strpos($cardTitle, $qSearch) === false && strpos($cardTags, $qSearch) === false) {
                    $showCard = false;
                }
            }
            if ($showCard && !empty($fPlans) && !in_array($cardPlan, $fPlans)) {
                $showCard = false;
            }
            if ($showCard && !empty($fFilters)) {
                $filterMatch = false;
                foreach ($fFilters as $ff) {
                    if ($ff === 'retired' && $cardIsRetired === 'true') $filterMatch = true;
                    if (strpos($cardTags, $ff) !== false) $filterMatch = true;
                }
                if (!$filterMatch) $showCard = false;
            }
            if ($showCard && !empty($fSorts) && !in_array($cardStatus, $fSorts)) {
                $showCard = false;
            }
            if ($showCard && !empty($fLevels)) {
                $levelMatch = false;
                foreach ($fLevels as $fl) {
                    if (strpos($cardTags, $fl) !== false) $levelMatch = true;
                }
                if (!$levelMatch) $showCard = false;
            }

            if (!$showCard) continue;
            
            $delay = $cardIndex * 0.1; // 100ms stagger between cards
            $cardIndex++;
        ?>
        <div class="col-12 col-md-6 col-xl-4 challenge-card-wrapper card-assemble"
             style="animation-delay: <?= $delay ?>s;"
             data-title="<?= $cardTitle ?>"
             data-tags="<?= $cardTags ?>"
             data-status="<?= $cardStatus ?>"
             data-plan="<?= $cardPlan ?>"
             data-is-retired="<?= $cardIsRetired ?>">
            <div class="card lab-card blur mb-2 shadow-sm <?= $cardLockedClass ?>">
                <!-- Ribbon (3D Folded Layout) -->
                <span class="<?= $ribbonClass ?> shadow-lg">
                    <span>
                        <?= htmlspecialchars($ribbonText1) ?><br>
                        <small class="text-nowrap"><?= htmlspecialchars($ribbonText2) ?></small>
                    </span>
                </span>

                <!-- Cover Image & Content (Dynamic Height Layout to prevent badges hiding under card footer) -->
                <div class="position-relative overflow-hidden" style="border-radius: 12px 12px 0 0;">
                    <!-- Cover Image Background (Absolute behind content) -->
                    <img class="card-img labs-index-cover position-absolute top-0 start-0 w-100 h-100" src="<?= $c['image'] ?>" alt="<?= $c['name'] ?>" onerror="this.src='https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop';">
                    
                    <!-- Content Overlay (Normal Relative Flow to dynamically expand height) -->
                    <div class="position-relative d-flex flex-column justify-content-between p-3 w-100" style="background: linear-gradient(to bottom, rgba(0, 0, 0, 0.1) 0%, rgba(0, 0, 0, 0.8) 100%); z-index: 2;">
                        <div class="d-flex flex-column gap-1 w-100">
                            <!-- Row 1: Title -->
                            <h4 class="card-title text-white fw-bold mb-0 shadow-text"><?= htmlspecialchars($c['name']) ?></h4>
                            
                            <!-- Row 2: Status (Left) + Points (Right) aligned horizontally -->
                            <div class="d-flex justify-content-between align-items-center w-100 mt-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="<?= $statusColorClass ?> small fw-bold" style="font-size: 0.8rem;"><?= $statusText ?></span>
                                    <div class="d-flex gap-2 ms-1">
                                        <i class='bx bx-info-circle text-white-50 pointer transition-all hover-text-white' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="<?= $isRunning ? 'Hash: ' . htmlspecialchars($instanceHash) : 'Instance Not Running' ?>" onclick="<?= $isRunning ? "copyText('".htmlspecialchars(addslashes($instanceHash))."', 'Instance Hash Copied!');" : "TomNotify.show('Start the lab to view its hash.', 'Notice', 'warning');" ?>"></i>
                                        <i class='bx bx-copy text-white-50 pointer transition-all hover-text-white' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="Copy Lab ID" onclick="copyText('<?= htmlspecialchars(addslashes($c['lab_id'])) ?>', 'Lab ID Copied!');"></i>
                                        <i class='bx bx-share-alt text-white-50 pointer transition-all hover-text-white' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="Share Lab" onclick="copyText(window.location.origin + '/challenges/dashboard/<?= htmlspecialchars(addslashes($c['lab_id'])) ?>', 'Share Link Copied!');"></i>
                                    </div>
                                </div>
                                
                                <div class="points-display text-end">
                                    <div class="d-flex align-items-center gap-1 justify-content-end">
                                        <i class='bx bxs-hot text-white' style="font-size: 1.25rem;"></i>
                                        <h3 class="m-0 text-white fw-bold shadow-text" style="font-size: 1.6rem; line-height: 1;"><?= $c['points'] ?></h3>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 3: Badges -->
                            <div class="d-flex gap-1 overflow-hidden mt-1" style="white-space: nowrap;">
                                <?php foreach ($c['tags'] as $tag): ?>
                                    <span class="badge <?= $tag['class'] ?> rounded-pill"><?= htmlspecialchars($tag['text']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (!$isUnlocked): ?>
                            <!-- Premium lock icon in center of cover -->
                            <div class="position-absolute top-50 start-50 translate-middle bg-dark bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center shadow-lg" style="width: 48px; height: 48px; border: 1px solid rgba(255,255,255,0.15); z-index: 10;">
                                <i class='bx bxs-lock-alt text-white fs-4'></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Buttons (Fused Pill) -->
                <div class="card-footer p-2 bg-dark bg-opacity-75 border-top border-white border-opacity-10">
                    <?php if ($isUnlocked): ?>
                        <div class="btn-group w-100 fused-btn-group rounded-pill overflow-hidden" role="group">
                            <a class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                               href="/challenges/dashboard/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                                <i class='bx bxs-dashboard'></i> Dashboard
                            </a>
                            <a class="btn btn-danger d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                               href="/challenges/challenges/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                                <i class='bx bxs-bug'></i> Challenges
                            </a>
                            <a class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                               href="/challenges/leaderboard/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                                <i class='bx bx-line-chart'></i> Leaderboard
                            </a>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 rounded-pill py-2 fw-bold disabled d-flex align-items-center justify-content-center gap-2" 
                                style="font-size: 0.75rem; border: none; background: rgba(255, 255, 255, 0.05); color: rgba(255, 255, 255, 0.45);">
                            <i class='bx bxs-lock-alt'></i> 
                            <?php if (!$isReleased): ?>
                                <?php
                                    // Formats date/time in 12-hour AM/PM based Indian Standard Time
                                    $dateObj = new DateTime("@" . $releaseTime);
                                    $dateObj->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                    $formattedReleaseTime = $dateObj->format('h:i A, M d, Y') . ' IST';
                                ?>
                                Releasing: <?= htmlspecialchars($formattedReleaseTime) ?>
                            <?php else: ?>
                                Challenge Locked
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (isset($_GET['ajax'])) exit; ?>

<!-- Badge Explanation Modal -->
<div class="modal fade" id="badgeExplanationModal" tabindex="-1" aria-labelledby="badgeExplanationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08) !important;">
            <div class="modal-header border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0 text-white" id="badgeExplanationModalLabel">Challenge Progress Badges</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close" style="filter: var(--cui-btn-close-white-filter, none);"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <p class="text-white-50 small mb-4">
                    As you progress through your journey, the colored badges on each Challenge Lab card will automatically update to reflect your current completion status.
                </p>
                
                <div class="d-flex flex-column gap-3">
                    <!-- Active Badge -->
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 80px;">
                            <span class="badge rounded-pill text-white shadow-sm" style="background: #ff5252; padding: 6px 12px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px;">ACTIVE</span>
                        </div>
                        <div>
                            <h6 class="text-white fw-bold mb-1" style="font-size: 0.95rem;">Active / Unstarted</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.8rem; line-height: 1.4;">The challenge is unlocked and ready for you to dive in, but you haven't completed any of its tasks yet.</p>
                        </div>
                    </div>
                    
                    <!-- Partial Badge -->
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 80px;">
                            <span class="badge rounded-pill text-white shadow-sm" style="background: #ff9f43; padding: 6px 12px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px;">PARTIAL</span>
                        </div>
                        <div>
                            <h6 class="text-white fw-bold mb-1" style="font-size: 0.95rem;">Partially Completed</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.8rem; line-height: 1.4;">You have successfully conquered some of the tasks inside this challenge, but there are still more objectives remaining.</p>
                        </div>
                    </div>
                    
                    <!-- Finished Badge -->
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 80px;">
                            <span class="badge rounded-pill text-white shadow-sm" style="background: #00d084; padding: 6px 12px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px;">FINISHED</span>
                        </div>
                        <div>
                            <h6 class="text-white fw-bold mb-1" style="font-size: 0.95rem;">Fully Mastered</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.8rem; line-height: 1.4;">Incredible work! You have found all the flags and completed every task within this challenge.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn rounded-pill px-4 py-2 fw-bold text-white w-100" data-coreui-dismiss="modal" style="background: rgba(255, 255, 255, 0.1); border: none; font-size: 0.85rem; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">Got it, thanks!</button>
            </div>
        </div>
    </div>
</div>


