<?php
/**
 * Challenge inner-pages shared header partial.
 * Uses Session variables populated by _challenge_base.php
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$status       = Session::get('challenge_status');
$labTitle     = Session::get('challenge_title');
$labDesc      = Session::get('challenge_desc');
$labImage     = Session::get('challenge_image');
$tags         = Session::get('challenge_tags');
$eventName    = Session::get('challenge_event_name');
$isEnded      = Session::get('challenge_is_ended');
$isRetired    = Session::get('challenge_is_retired');
$isRunning    = ($status === 'running');

$host      = $_SERVER['HTTP_HOST'] ?? 'labs.selfmade.ninja';
$shareUrl  = "https://{$host}/challenges/challenges/{$labId}"; 
?>
<div class="lab-header-section mb-0">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

            <!-- Avatar + Info -->
            <div class="d-flex align-items-center gap-4">
                <div class="position-relative flex-shrink-0">
                    <div class="avatar" style="height:5.5rem;width:5.5rem;">
                        <div class="avatar-img d-flex align-items-center justify-content-center rounded-circle overflow-hidden border border-white border-opacity-10" style="width:100%;height:100%;">
                            <img src="<?= htmlspecialchars($labImage) ?>" style="width:100%;height:100%;object-fit:cover;" alt="<?= htmlspecialchars($labTitle) ?>" onerror="this.src='/assets/img/challenges/shadow.png';">
                        </div>
                        <span class="avatar-status <?= $isRunning ? 'bg-success' : 'bg-secondary' ?> border-dark ring-2 position-absolute bottom-0 end-0 mb-1 me-1 p-1"></span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-1">
                    <h3 class="fw-bold text-white mb-0"><?= htmlspecialchars($labTitle) ?></h3>

                    <div class="d-flex align-items-center gap-3 small">
                        <div class="d-flex align-items-center text-white-50 opacity-100">
                            <span class="me-2 small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Lab ID:</span>
                            <code class="text-info fw-bold bg-dark bg-opacity-50 px-2 py-1 rounded" style="font-size: 0.85rem; letter-spacing: 0.02em;"><?= htmlspecialchars($labId ?: 'N/A') ?></code>
                            <button class="btn btn-link btn-sm p-0 ms-2 clipboard text-secondary" data-clipboard-text="<?= htmlspecialchars($labId) ?>" data-coreui-toggle="tooltip" title="Copy Lab ID">
                                <i class='bx bx-copy'></i>
                            </button>
                        </div>

                        <div class="d-flex align-items-center text-secondary opacity-75">
                            <button class="btn btn-link btn-sm p-0 clipboard text-decoration-none d-flex align-items-center gap-1" data-clipboard-text="<?= htmlspecialchars($shareUrl) ?>" data-coreui-toggle="tooltip" title="Copy Shareable URL">
                                <i class='bx bx-share-alt'></i>
                                <span style="font-size:10px;">Share</span>
                            </button>
                        </div>
                    </div>

                    <p class="text-secondary mb-2 opacity-75 small" style="max-width:650px;line-height:1.5;">
                        <?= htmlspecialchars($labDesc) ?>
                    </p>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if ($isEnded): ?>
                            <span class="badge bg-danger-gradient border border-white border-opacity-10 rounded-pill px-2 py-1"><?= htmlspecialchars($eventName) ?></span>
                        <?php elseif ($isRetired): ?>
                            <span class="badge bg-warning-gradient border border-white border-opacity-10 rounded-pill px-2 py-1"><?= htmlspecialchars($eventName) ?></span>
                        <?php endif; ?>
                        <?php foreach (($tags ?? []) as $tag): ?>
                            <span class="badge bg-secondary border border-white border-opacity-10 rounded-pill px-2 py-1"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Deploy Button -->
            <div>
                <button class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow d-flex align-items-center gap-2" onclick="">
                    <i class='bx <?= $isRunning ? "bx-refresh" : "bx-cloud-upload" ?>'></i>
                    <?= $isRunning ? 'Redeploy' : 'Deploy' ?>
                    <?php if ($isRunning): ?>
                        <span class="rounded-circle ms-1 pulse" style="background:rgba(255,255,255,0.5);width:8px;height:8px;display:inline-block;"></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Nav Tabs -->
        <div class="row m-0 p-0 mt-3">
            <ul class="nav nav-tabs labs-banner-tabs">
                <li class="nav-item">
                    <a class="nav-link labs-banner-tab <?= ($activeTab ?? '') === 'dashboard' ? 'active' : '' ?>" href="/challenges/dashboard/<?= $instanceHash ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link labs-banner-tab <?= ($activeTab ?? '') === 'challenges' ? 'active' : '' ?>" href="/challenges/challenges/<?= $instanceHash ?>">Challenges</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link labs-banner-tab <?= ($activeTab ?? '') === 'achievements' ? 'active' : '' ?>" href="/challenges/achievements/<?= $instanceHash ?>">Achievements</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link labs-banner-tab <?= ($activeTab ?? '') === 'leaderboard' ? 'active' : '' ?>" href="/challenges/leaderboard/<?= $instanceHash ?>">Leaderboard</a>
                </li>
            </ul>
        </div>
    </div>
</div>
