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
$isRunning    = ($status === 'running' || $status === 'completed');

$host      = $_SERVER['HTTP_HOST'] ?? 'labs.selfmade.ninja';
$shareUrl  = "https://{$host}/challenges/challenges/{$labId}"; 

$dbInstance = DatabaseConnection::getDefaultDatabase();
$userProgress = $dbInstance->challenge_instances->findOne(['instance_hash' => $instanceHash]) ?? [];
$createdAt = $userProgress['created_at'] ?? time();
$durationMinutes = Session::get('challenge_duration') ?? 15;

if ($isRunning) {
    // Use DB's stored expires_at as single source of truth when running
    $expiresAt = $userProgress['expires_at'] ?? ($createdAt + ($durationMinutes * 60));
    $timeLeft = max(0, $expiresAt - time());
} else {
    // If not running, show the full available duration
    $timeLeft = $durationMinutes * 60;
}

$isExpired = ($isRunning && $timeLeft <= 0);

$initM = str_pad(floor($timeLeft / 60), 2, "0", STR_PAD_LEFT);
$initS = str_pad($timeLeft % 60, 2, "0", STR_PAD_LEFT);
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

                        <div class="d-flex align-items-center opacity-75">
                            <button class="btn btn-sm p-0 clipboard text-primary text-decoration-none d-flex align-items-center gap-1" data-clipboard-text="<?= htmlspecialchars($shareUrl) ?>" data-coreui-toggle="tooltip" title="Copy Shareable URL">
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
                        <?php foreach (($tags ?? []) as $tag): 
                            $tagColors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning text-dark', 'bg-danger', 'bg-indigo', 'bg-teal'];
                            $colorClass = $tagColors[abs(crc32($tag)) % count($tagColors)];
                        ?>
                            <span class="badge <?= $colorClass ?> bg-opacity-75 border border-white border-opacity-10 rounded-pill px-2 py-1 shadow-sm"><?= htmlspecialchars(strtoupper($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Deploy Button & Timer -->
            <div class="d-flex align-items-center gap-3">
                <?php if ($isRunning): ?>
                    <div class="timer-container text-center bg-dark bg-opacity-50 rounded-pill px-3 py-2 border border-white border-opacity-10 shadow-sm" id="challenge-timer-wrapper">
                        <i class='bx bx-time-five text-warning mb-0'></i>
                        <span id="challenge-countdown" class="fw-bold ms-1" style="font-family: monospace; font-size: 1.1rem; color: #ffc107;" data-expires="<?= $expiresAt ?>"><?= $initM ?>:<?= $initS ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="btn-group shadow-sm rounded-pill overflow-hidden me-4" role="group">
                    <button class="btn btn-sm btn-success btn-redeploy-lab px-3 py-1 fw-bold hover-scale border-0 d-flex align-items-center gap-2" 
                            style="background: #34d399; border: none; color: #000;"
                            data-coreui-toggle="modal" data-coreui-target="#confirmDeployModal"
                            data-tooltip="<?= $isRunning ? 'Redeploy for a fresh instance' : 'Deploy this challenge' ?>"
                            data-coreui-spinner-type="grow">
                        <i class='bx <?= $isRunning ? 'bx-refresh' : 'bx-cloud-upload' ?> fs-6 text-dark'></i>
                        <span class="text-dark"><?= $isRunning ? 'Redeploy' : 'Deploy' ?></span>
                    </button>

                    <?php if ($isRunning): ?>
                        <button id="btn-stop-action" class="btn btn-sm px-3 py-1 fw-bold hover-scale border-0 d-flex align-items-center gap-2" 
                                style="background: #ef4444; color: #fff;"
                                onclick="stopChallenge(this, '<?= htmlspecialchars($labId) ?>')"
                                data-tooltip="Stop Challenge Immediately"
                                data-coreui-toggle="loading-button" data-coreui-spinner-type="grow">
                            <i class='bx bx-stop-circle fs-6' style="filter: brightness(0) invert(1);"></i>
                            <span>Stop</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        window.SESSION_HASH = <?= json_encode($instanceHash) ?>;
        
        async function handleChallengeDeploy(btn, challengeId) {
            if(typeof Dashboard !== 'undefined' && Dashboard.isProcessing) return;
            
            if(typeof Dashboard !== 'undefined') {
                Dashboard.toggleLoading(btn, true);
                Dashboard.isProcessing = true;
                Dashboard.resetTerminal();
                Dashboard.appendCommand(`labsctl challenge deploy --user=${window.LAB_USER || 'tom'} --hash=${window.SESSION_HASH} --challenge=${challengeId}`);
            }

            try {
                const formData = new URLSearchParams();
                formData.append('challenge_id', challengeId);
                formData.append('hash', window.SESSION_HASH);
                
                const response = await fetch('/api/challenges/deploy', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if(data.status === 'success') {
                    if(typeof Dashboard !== 'undefined') Dashboard.appendLog("[*] Deployment task queued. Waiting for worker...");
                } else {
                    if(typeof Dashboard !== 'undefined') Dashboard.appendLog(`[!] Error: ${data.error || 'Deploy request failed'}`);
                    if(typeof Dashboard !== 'undefined') Dashboard.isProcessing = false;
                    if(typeof Dashboard !== 'undefined') Dashboard.toggleLoading(btn, false);
                }
            } catch(e) {
                console.error("Deploy Error:", e);
                if(typeof Dashboard !== 'undefined') {
                    Dashboard.appendLog(`[!] Critical Error: ${e.message}`);
                    Dashboard.isProcessing = false;
                    Dashboard.toggleLoading(btn, false);
                }
            }
        }
        
        async function stopChallenge(btn, challengeId) {
            if(typeof Dashboard !== 'undefined' && Dashboard.isProcessing) return;
            
            if(!confirm("Are you sure you want to stop this challenge? The container will be shut down and your progress will be paused.")) return;
            
            if(typeof Dashboard !== 'undefined') {
                Dashboard.toggleLoading(btn, true);
                Dashboard.isProcessing = true;
                Dashboard.appendLog("[*] Stop task queued. Waiting for worker to shut down...");
                Dashboard.resetTerminal();
                Dashboard.appendCommand(`labsctl challenge stop --user=${window.LAB_USER || 'tom'} --hash=${window.SESSION_HASH} --challenge=${challengeId}`);
            } else {
                btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Stopping...';
                btn.disabled = true;
            }
            
            try {
                const formData = new URLSearchParams();
                formData.append('challenge_id', challengeId);
                formData.append('hash', window.SESSION_HASH);
                
                const response = await fetch('/api/challenges/stop_mission', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    // UI will listen to RMQ via _challenge_base or reload
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    alert("Error: " + data.error);
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    if(typeof Dashboard !== 'undefined') Dashboard.isProcessing = false;
                }
            } catch (e) {
                alert("Error stopping challenge: " + e.message);
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                if(typeof Dashboard !== 'undefined') Dashboard.isProcessing = false;
            }
        }
        
        <?php if ($isRunning && !$isExpired): ?>
        // Countdown Logic
        (function(){
            const timerEl = document.getElementById('challenge-countdown');
            if(!timerEl) return;
            const expires = parseInt(timerEl.getAttribute('data-expires')) * 1000;
            
            const interval = setInterval(() => {
                const now = new Date().getTime();
                const distance = expires - now;
                
                if (distance <= 0) {
                    clearInterval(interval);
                    timerEl.innerHTML = '<span class="text-danger">EXPIRED</span>';
                    timerEl.parentElement.classList.add('border-danger');
                    
                    // Actively stop the container via API (don't rely on background reaper)
                    (async function() {
                        try {
                            const formData = new URLSearchParams();
                            formData.append('challenge_id', <?= json_encode($labId) ?>);
                            formData.append('hash', <?= json_encode($instanceHash) ?>);
                            
                            await fetch('/api/challenges/stop_mission', {
                                method: 'POST',
                                body: formData
                            });
                        } catch(e) {
                            console.error('Auto-stop on expiry failed:', e);
                        }
                        // Reload after a short delay to let the stop job queue
                        setTimeout(() => window.location.reload(), 2000);
                    })();
                    return;
                }
                
                const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((distance % (1000 * 60)) / 1000);
                timerEl.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                
                if (distance < 60000) { // last minute
                    timerEl.style.color = '#dc3545';
                    timerEl.parentElement.classList.add('pulse-danger');
                }
            }, 1000);
        })();
        <?php endif; ?>
        </script>

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

<!-- Confirm Deploy Modal -->
<div class="modal fade" id="confirmDeployModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08) !important;">
            <div class="modal-header border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0 text-white" style="font-size: 1.35rem; letter-spacing: -0.3px;">Confirm Deploy?</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" style="font-size: 0.8rem; filter: var(--cui-btn-close-white-filter, none);"></button>
            </div>
            <div class="modal-body px-4 pb-3 pt-3">
                <div class="mb-3">
                    <h5 class="fw-bold text-danger mb-2" style="font-size: 1.15rem;">You are deploying a Challenge Lab</h5>
                    <p class="text-white-50 small" style="font-size: 0.88rem; line-height: 1.5;">
                        If you stop/redeploy or the lab expires while on a mission, all your ongoing missions will be considered as failed.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                <button type="button" class="btn rounded-pill px-4 py-2 fw-bold text-white" onclick="triggerActualDeploy(this)" style="background: #2ecc71; border: none; font-size: 0.82rem; transition: all 0.2s;">Confirm Deploy</button>
                <button type="button" class="btn rounded-pill px-4 py-2 fw-bold text-white" data-coreui-dismiss="modal" style="background: #3c4b64; border: none; font-size: 0.82rem;">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function triggerActualDeploy(btn) {
    const modalEl = document.getElementById('confirmDeployModal');
    if (modalEl) {
        const modalInstance = coreui.Modal.getInstance(modalEl) || new coreui.Modal(modalEl);
        modalInstance.hide();
    }
    const deployBtn = document.querySelector('[data-coreui-target="#confirmDeployModal"]');
    handleChallengeDeploy(deployBtn, <?= json_encode($labId) ?>);
}
</script>

