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
$zealAcquired        = $userProgress['zeal_earned'] ?? 0;

// Total time spent is only calculated from mission start to completion
$missionStartTime = $userProgress['mission_start_time'] ?? 0;
$completedAt = $userProgress['completed_at'] ?? 0;
$timeSpent = 0;
if ($missionStartTime > 0) {
    if ($completedAt > 0) {
        $timeSpent = $completedAt - $missionStartTime;
    } else {
        $timeSpent = time() - $missionStartTime;
    }
}
$timeSpent = max(0, $timeSpent);

// Attempts logic
$failedAttempts = $userProgress['failed_attempts'] ?? 0;
$completedOnAttempt = $userProgress['completed_on_attempt'] ?? null;
$attemptsShow = ($challengesCompleted > 0 && $completedOnAttempt !== null) ? $completedOnAttempt : $failedAttempts;

$creds     = $userProgress['credentials'] ?? null;
$hasConn   = $isRunning && $creds;
$missionStarted = $userProgress['mission_started'] ?? false;

// Format values for professional display (no 0 defaults)
$timeDisplay = $timeSpent > 0 ? gmdate('H:i:s', $timeSpent) : '00:00:00';
$zealDisplay = $zealAcquired > 0 ? number_format($zealAcquired) : '0000';
$attemptsDisplay = $attemptsShow > 0 ? $attemptsShow : '00';

// Dynamic Lab Information Readme loading
$readmes = require __DIR__ . '/../../../config/challenge_readmes.php';
$labReadme = $readmes[$labId] ?? 'Engage in real-world hacking scenarios and penetration testing.';

include __DIR__ . '/partials/challenge_header.php';
?>

<div class="container-fluid px-0 py-3">
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="position-relative d-flex justify-content-center mb-3">
                        <span class="small text-muted fw-bold text-uppercase text-center" style="font-size:0.6rem;letter-spacing:0.05em;">CHALLENGES COMPLETED</span>
                        <i class="bx bx-flag text-secondary position-absolute end-0" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white text-center" style="font-size:1.8rem;line-height:1;"><?= "{$challengesCompleted}/{$totalTasks}" ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="position-relative d-flex justify-content-center mb-3">
                        <span class="small text-muted fw-bold text-uppercase text-center" style="font-size:0.6rem;letter-spacing:0.05em;">ZEAL ACQUIRED</span>
                        <i class="bx bxs-hot text-warning position-absolute end-0" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white text-center" style="font-size:1.8rem;line-height:1;"><?= $zealDisplay ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="position-relative d-flex justify-content-center mb-3">
                        <span class="small text-muted fw-bold text-uppercase text-center" style="font-size:0.6rem;letter-spacing:0.05em;">TOTAL TIME SPENT</span>
                        <i class="bx bx-time text-info position-absolute end-0" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white text-center" style="font-size:1.8rem;line-height:1;"><?= $timeDisplay ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4">
                    <div class="position-relative d-flex justify-content-center mb-3">
                        <span class="small text-muted fw-bold text-uppercase text-center" style="font-size:0.6rem;letter-spacing:0.05em;">ATTEMPTS</span>
                        <i class="bx bx-target-lock text-white position-absolute end-0" style="font-size:1.4rem;opacity:0.6;"></i>
                    </div>
                    <div class="fw-bold text-white text-center" style="font-size:1.8rem;line-height:1;"><?= $attemptsDisplay ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05) !important;">
                <div class="card-header bg-dark bg-opacity-50 border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="fw-bold mb-0 text-white small">Lab Information <span class="text-secondary ms-1 fw-normal" style="font-size: 0.75rem;">Readme</span></h6>
                    </div>
                </div>
                <div class="card-body p-4" style="line-height: 1.7; font-size: 0.85rem;">
                    <?= nl2br(htmlspecialchars($labReadme)) ?>
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
                            <?php 
                            foreach ($creds as $key => $val): 
                                $displayVal = $val;
                                $isMasked = false;
                                if (!$missionStarted && (strpos(strtolower($key), 'ip') !== false || strpos(strtolower($key), 'url') !== false)) {
                                    $displayVal = '***.***.***.*** (Start Mission to view)';
                                    $isMasked = true;
                                }
                            ?>
                            <div class="row align-items-center">
                                <div class="col-4 text-secondary small fw-bold text-uppercase"><?= str_replace('_', ' ', $key) ?></div>
                                <div class="col-8">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control rounded-pill border-white border-opacity-10 bg-dark bg-opacity-50 text-white px-3 font-monospace <?= $isMasked ? 'text-muted' : '' ?>" value="<?= htmlspecialchars($displayVal) ?>" readonly>
                                        <?php if (!$isMasked): ?>
                                        <button class="btn btn-outline-secondary ms-2 rounded-circle p-0 d-flex align-items-center justify-content-center clipboard" data-clipboard-text="<?= htmlspecialchars($val) ?>" style="width:28px;height:28px;">
                                            <i class='bx bx-copy' style="font-size:0.8rem;"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-outline-secondary ms-2 rounded-circle p-0 d-flex align-items-center justify-content-center" disabled style="width:28px;height:28px; opacity:0.3;">
                                            <i class='bx bx-lock' style="font-size:0.8rem;"></i>
                                        </button>
                                        <?php endif; ?>
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

            <!-- Container Load (Live) Card -->
            <div class="card mb-4 border-0 shadow-sm blur rounded-4" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">
                        Container Load
                        <?php if ($isRunning): ?>
                            <span class="badge bg-success rounded-pill ms-2 pulse" style="font-size: 0.6rem;">Live</span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill ms-2" style="font-size: 0.6rem;">Offline</span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 text-start stat-card-inner">
                                <div class="mb-1">
                                    <span class="small fw-bold text-white text-start">
                                        <span id="stat-cpu-usage"></span> <small class="text-muted text-uppercase ms-1">CPU Load</small>
                                    </span>
                                </div>
                                <div class="progress" style="height: 4px; background: rgba(255,255,255,0.1);">
                                    <div class="progress-bar bg-info" id="stat-cpu-bar" style="width: 0%"></div>
                                </div>
                                <div class="small text-muted mt-2 text-start" id="stat-pid-container" style="display: <?= $isRunning ? 'block' : 'none' ?>;">PID Count: <span id="stat-pid-count">0</span></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 text-start stat-card-inner">
                                <div class="mb-1">
                                    <span class="small fw-bold text-white text-start">
                                        <span id="stat-mem-perc"></span> <small class="text-muted text-uppercase ms-1">Memory Usage</small>
                                    </span>
                                </div>
                                <div class="progress" style="height: 4px; background: rgba(255,255,255,0.1);">
                                    <div class="progress-bar bg-warning" id="stat-mem-bar" style="width: 0%"></div>
                                </div>
                                <div class="small text-muted mt-2 text-start" id="stat-mem-info"> </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">1 Min Avg</div>
                                <div class="fw-bold text-white small" id="stat-load-1">0</div>
                                <div style="height:35px;">
                                    <canvas id="chart-avg-1"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">5 Min Avg</div>
                                <div class="fw-bold text-white small" id="stat-load-5">0</div>
                                <div style="height:35px;">
                                    <canvas id="chart-avg-5"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">15 Min Avg</div>
                                <div class="fw-bold text-white small" id="stat-load-15">0</div>
                                <div style="height:35px;">
                                    <canvas id="chart-avg-15"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Load History Card -->
            <div class="card border-0 shadow-sm blur rounded-4" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">Load History <span class="text-secondary ms-1 fw-normal" style="font-size: 0.75rem;">One Hour</span></h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">CPU Peak</div>
                                <div class="fw-bold text-white" id="stat-peak-cpu"></div>
                                <div class="mt-2" style="height:40px;">
                                    <canvas id="chart-peak-cpu"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">PID Max</div>
                                <div class="fw-bold text-white" id="stat-max-pid"></div>
                                <div class="mt-2" style="height:40px;">
                                    <canvas id="chart-max-pid"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">Memory High</div>
                                <div class="fw-bold text-white" id="stat-high-mem"></div>
                                <div class="mt-2" style="height:40px;">
                                    <canvas id="chart-high-mem"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IO Stats Card -->
            <div class="card mt-4 border-0 shadow-sm blur rounded-4" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">IO Stats <span class="text-secondary ms-1 fw-normal" style="font-size: 0.75rem;">Net and Block</span></h6>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    <div class="row g-2">
                        <div class="col-6 text-center">
                            <div class="p-2 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">NET IO</div>
                                <div class="fw-bold text-white mb-0 d-flex justify-content-center gap-3" style="font-size: 0.85rem;">
                                    <span id="stat-net-io">0B / 0B</span>
                                </div>
                                <div class="mt-1" style="height:30px;">
                                    <canvas id="chart-net-io"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="p-2 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">BLOCK IO</div>
                                <div class="fw-bold text-white mb-0 d-flex justify-content-center gap-3" style="font-size: 0.85rem;">
                                    <span id="stat-block-io">0B / 0B</span>
                                </div>
                                <div class="mt-1" style="height:30px;">
                                    <canvas id="chart-block-io"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="server-logs-panel shadow-lg">
    <div class="logs-header">
        <div class="logs-title d-flex align-items-center gap-2">
            <i class='bx bx-terminal fs-5'></i>
            <i class="bx bxs-circle" id="mq-status-dot" style="font-size: 8px;"></i>
            <span class="small fw-bold ls-1 opacity-75">Server Logs</span>
            
            <div class="terminal-info-wrapper ms-1">
                <i class='bx bx-info-circle opacity-50' style="font-size: 14px;"></i>
                <div class="terminal-tooltip">
                    You cannot type anything here, this is a terminal to watch server logs
                </div>
            </div>
        </div>
    </div>
    <div class="logs-body" id="terminal-viewport" style="overflow-y: auto;">
        <div id="live-logs-container" class="small"></div>
    </div>
</div>

<script>
function clearTerminal() {
    const container = document.getElementById('live-logs-container');
    if (container) container.innerHTML = '<div class="text-secondary small">[INFO] Terminal cleared.</div>';
}
</script>
