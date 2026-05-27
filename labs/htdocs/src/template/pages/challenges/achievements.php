<?php
/**
 * Challenge Achievements Template
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$activeTab    = 'achievements';

include __DIR__ . '/partials/challenge_header.php';

// Mock Achievements Data
$achievements = [
    [
        'id'    => 'first_blood',
        'title' => 'First Blood',
        'desc'  => 'Be the first to capture the initial flag in this lab.',
        'icon'  => 'bx-target-lock',
        'color' => 'danger',
        'unlocked' => true,
        'date'  => '2024-04-20'
    ],
    [
        'id'    => 'root_king',
        'title' => 'Root King',
        'desc'  => 'Escalate privileges to root on the target system.',
        'icon'  => 'bx-crown',
        'color' => 'warning',
        'unlocked' => true,
        'date'  => '2024-04-21'
    ],
    [
        'id'    => 'ghost',
        'title' => 'Ghost Protocol',
        'desc'  => 'Complete all tasks without triggering any security alerts.',
        'icon'  => 'bx-ghost',
        'color' => 'info',
        'unlocked' => false,
        'date'  => null
    ],
    [
        'id'    => 'speed_demon',
        'title' => 'Speed Demon',
        'desc'  => 'Complete the entire lab in under 30 minutes.',
        'icon'  => 'bx-bolt-circle',
        'color' => 'success',
        'unlocked' => false,
        'date'  => null
    ]
];
?>

<div class="container-fluid px-0 py-3">
    <div class="row g-4">
        <?php foreach ($achievements as $a): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 blur transition-hover <?= $a['unlocked'] ? '' : 'opacity-50 grayscale' ?>" style="background:rgba(255,255,255,0.03);">
                <div class="card-body p-4 text-center">
                    <div class="achievement-icon-wrap mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle bg-dark bg-opacity-50 border border-white border-opacity-10" style="width:80px;height:80px;">
                        <i class='bx <?= $a['icon'] ?> text-<?= $a['color'] ?>' style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2"><?= htmlspecialchars($a['title']) ?></h5>
                    <p class="text-secondary small mb-3" style="font-size: 0.75rem; line-height: 1.4;">
                        <?= htmlspecialchars($a['desc']) ?>
                    </p>
                    
                    <?php if ($a['unlocked']): ?>
                        <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 0.6rem; border: 1px solid rgba(46, 184, 92, 0.3);">
                            UNLOCKED ON <?= date('M d, Y', strtotime($a['date'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="badge bg-dark bg-opacity-50 text-secondary rounded-pill px-3 py-2 fw-bold" style="font-size: 0.6rem; border: 1px solid rgba(255,255,255,0.1);">
                            LOCKED
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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

<style>
.transition-hover {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.transition-hover:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.06) !important;
}
.grayscale {
    filter: grayscale(1);
}
</style>
