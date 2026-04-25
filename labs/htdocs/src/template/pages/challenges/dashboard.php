<?php
/**
 * Challenge Dashboard Template
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$status       = Session::get('challenge_status');
$isRunning    = ($status === 'running');
$activeTab    = 'dashboard';

// Metadata from Session
$maxZeal      = Session::get('challenge_max_zeal');
$totalTasks   = Session::get('challenge_total_tasks');

$db           = DatabaseConnection::getDefaultDatabase();
$userProgress = $db->challenge_instances->findOne(['instance_hash' => $instanceHash]) ?? [];
$challengesCompleted = $userProgress['challenges_completed'] ?? 0;
$zealAcquired        = $userProgress['zeal'] ?? 0;
$timeSpent           = $userProgress['time_spent_seconds'] ?? 0;
$lbRank              = $userProgress['leaderboard_rank'] ?? '--';

$creds     = $userProgress['credentials'] ?? null;
$hasConn   = $isRunning && $creds;

include __DIR__ . '/partials/challenge_header.php';
?>

<div class="container-fluid px-0 py-3">
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small text-muted fw-bold text-uppercase" style="font-size:0.6rem;letter-spacing:0.05em;">CHALLENGES COMPLETED</span>
                        <i class="bx bx-flag text-secondary" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white" style="font-size:1.8rem;line-height:1;"><?= "{$challengesCompleted}/{$totalTasks}" ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small text-muted fw-bold text-uppercase" style="font-size:0.6rem;letter-spacing:0.05em;">ZEAL ACQUIRED</span>
                        <i class="bx bxs-hot text-warning" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white" style="font-size:1.8rem;line-height:1;"><?= number_format($zealAcquired) ?> <span class="fs-6 opacity-50">/ <?= number_format($maxZeal) ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small text-muted fw-bold text-uppercase" style="font-size:0.6rem;letter-spacing:0.05em;">TOTAL TIME SPENT</span>
                        <i class="bx bx-time text-info" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white" style="font-size:1.8rem;line-height:1;"><?= gmdate('H:i:s', $timeSpent) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small text-muted fw-bold text-uppercase" style="font-size:0.6rem;letter-spacing:0.05em;">LEADERBOARD RANK</span>
                        <i class="bx bx-user text-white" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white" style="font-size:1.8rem;line-height:1;"><?= $lbRank ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100" style="background: #080c16;">
                <div class="card-header bg-dark bg-opacity-50 border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex gap-1 me-2">
                            <span class="rounded-circle bg-danger" style="width:10px;height:10px;"></span>
                            <span class="rounded-circle bg-warning" style="width:10px;height:10px;"></span>
                            <span class="rounded-circle bg-success" style="width:10px;height:10px;"></span>
                        </div>
                        <h6 class="fw-bold mb-0 text-white-50 small">SERVER LOGS</h6>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-link btn-sm p-0 text-secondary" onclick="clearTerminal()"><i class='bx bx-trash'></i></button>
                        <span class="badge bg-success rounded-pill px-2" style="font-size: 0.6rem;">CONNECTED</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="terminal-container" class="p-3 font-monospace small" style="height: 400px; overflow-y: auto; background: #080c16; color: #a9b7c6; line-height: 1.4;">
                        <div class="text-success mb-1">[SYSTEM] Connected to challenge instance: <?= $instanceHash ?></div>
                        <div class="text-secondary mb-1">[INFO] Initializing security protocols...</div>
                        <div class="text-secondary mb-1">[INFO] Waiting for incoming logs...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4 border-0 shadow-sm rounded-4 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">Connection Information</h6>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if ($hasConn): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($creds as $key => $val): ?>
                            <div class="row align-items-center">
                                <div class="col-4 text-secondary small fw-bold text-uppercase"><?= str_replace('_', ' ', $key) ?></div>
                                <div class="col-8">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control rounded-pill border-white border-opacity-10 bg-dark bg-opacity-50 text-white px-3 font-monospace" value="<?= htmlspecialchars($val) ?>" readonly>
                                        <button class="btn btn-outline-secondary ms-2 rounded-circle p-0 d-flex align-items-center justify-content-center clipboard" data-clipboard-text="<?= htmlspecialchars($val) ?>" style="width:28px;height:28px;">
                                            <i class='bx bx-copy' style="font-size:0.8rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class='bx bx-lock-alt text-secondary' style="font-size: 2rem; opacity: 0.3;"></i>
                            <p class="text-secondary small mt-2">Deploy the lab to see credentials</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">Container Health</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-white-50 text-uppercase" style="font-size: 0.6rem;">CPU Usage</span>
                            <span class="small fw-bold text-white" id="ch-cpu-pct">0.00%</span>
                        </div>
                        <div class="progress" style="height:4px;background:rgba(255,255,255,0.05);">
                            <div class="progress-bar bg-info" id="ch-cpu-bar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold text-white-50 text-uppercase" style="font-size: 0.6rem;">Memory Usage</span>
                            <span class="small fw-bold text-white" id="ch-mem-pct">0%</span>
                        </div>
                        <div class="progress" style="height:4px;background:rgba(255,255,255,0.05);">
                            <div class="progress-bar bg-warning" id="ch-mem-bar" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function clearTerminal() {
    document.getElementById('terminal-container').innerHTML = '<div class="text-secondary small">[INFO] Terminal cleared.</div>';
}

(function(){
    const HASH = <?= json_encode($instanceHash) ?>;
    async function fetchStats() {
        try {
            const r = await fetch(`/api/instance/stats?hash=${HASH}`);
            const d = await r.json();
            if (d.status === 'offline') return;
            document.getElementById('ch-cpu-pct').textContent = (d.cpu_percent || 0).toFixed(2) + '%';
            document.getElementById('ch-cpu-bar').style.width = Math.min(d.cpu_percent || 0, 100) + '%';
            document.getElementById('ch-mem-pct').textContent = (d.mem_percent || 0).toFixed(0) + '%';
            document.getElementById('ch-mem-bar').style.width = Math.min(d.mem_percent || 0, 100) + '%';
        } catch(e) {}
    }
    setInterval(fetchStats, 5000);
    fetchStats();
})();
</script>
