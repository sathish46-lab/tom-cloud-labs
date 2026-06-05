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
?>

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
                <?php 
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
                    
                    $running = count($runningInstances);
                    $percent = $total > 0 ? ($running / $total) * 100 : 0;
                ?>
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <span class="fw-bold theme-text" style="font-size: 2.2rem; line-height: 1;"><?= $running ?></span>
                    <span class="text-secondary opacity-50 ms-2" style="font-size: 1.1rem; font-weight: 500; margin-top: 8px;">/ <?= $total ?></span>
                </div>
                <div class="progress bg-secondary bg-opacity-10 rounded-pill mb-2 w-100" style="height: 6px; max-width: 120px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="text-secondary opacity-50 text-uppercase fw-bold ls-1" style="font-size: 9px; letter-spacing: 0.8px;">Running Labs</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mb-4">
    <div class="search-container position-relative" style="width: 100%; max-width: 400px;">
        <input type="text" class="form-control rounded-pill px-5 text-center py-2 bg-dark bg-opacity-50 border-white border-opacity-10 text-white" placeholder="Search Challenges...">
        <i class='bx bx-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50'></i>
    </div>
</div>

<div class="container-fluid px-0">
    <div class="row g-3 pb-5">
        <?php foreach ($challenges as $c): ?>
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

            // Compute ribbon styles dynamically
            $ribbonText1 = $c['ribbon_text1'] ?? ($isUnlocked ? 'Active' : (!$isReleased ? 'Upcoming' : 'Not Active'));
            $ribbonClass = $c['ribbon_class'] ?? ($isUnlocked ? 'event-ended-ribbon' : 'event-retired-ribbon');
            $ribbonText2 = $c['ribbon_text2'] ?? 'Yukthi';
            
            // Grayscale layout if locked
            $cardLockedClass = $isUnlocked ? '' : 'opacity-75 grayscale-locked';
            
            $isRunning = in_array($c['lab_id'], $runningInstances);
            $instanceHash = $isRunning ? ($runningHashes[$c['lab_id']] ?? 'N/A') : 'Not Running';
            $statusText = $isUnlocked ? ($isRunning ? 'Running' : 'Instance Down') : (!$isReleased ? 'Coming Soon' : 'Not Active');
            $statusColorClass = $isRunning ? 'text-success' : 'text-white-50';
        ?>
        <div class="col-12 col-md-6 col-xl-4">
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

<style>
.shadow-text {
    text-shadow: 0 2px 10px rgba(0,0,0,1);
}
.fused-btn-group {
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.fused-btn-group .btn {
    flex: 1;
    border-radius: 0 !important;
    transition: all 0.2s ease;
}
.fused-btn-group .btn:hover {
    filter: brightness(1.2);
}
.pointer {
    cursor: pointer;
}
.grayscale-locked img {
    filter: grayscale(1) opacity(0.5) blur(1px);
    transition: all 0.3s ease;
}
.grayscale-locked:hover img {
    filter: grayscale(0.85) opacity(0.6) blur(0.5px);
}
</style>


