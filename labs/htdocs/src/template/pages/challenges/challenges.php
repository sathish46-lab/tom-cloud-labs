<?php
/**
 * Challenge Tasks Template
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$status       = Session::get('challenge_status');
$isRunning    = ($status === 'running');
$activeTab    = 'challenges';

// Metadata from Session
$labTitle     = Session::get('challenge_title');
$totalTasks   = Session::get('challenge_total_tasks');

$db            = DatabaseConnection::getDefaultDatabase();

// Fetch tasks from DB
$tasksCursor = $db->challenge_tasks->find(['lab_id' => $labId], ['sort' => ['order' => 1]]);
$tasks       = $tasksCursor ? $tasksCursor->toArray() : [];

// User Progress for progress ring
$userProgress = $db->challenge_instances->findOne(['instance_hash' => $instanceHash]) ?? [];
$challengesCompleted = $userProgress['challenges_completed'] ?? 0;
$missionStarted = $userProgress['mission_started'] ?? false;

// Mock data if empty
if (empty($tasks)) {
    if ($labId === 'sql_injection') {
        $tasks = [
            [
                'task_id'     => 'task_1',
                'title'       => 'SQL Injection Exploitation',
                'description' => 'Bypass the login portal without valid credentials. Analyze the input fields, craft a payload that manipulates the backend database query, and extract the secret flag.',
                'zeal'        => 500,
                'difficulty'  => 'Easy',
                'multiplier'  => 1.0,
                'completed'   => false,
                'tags'        => ['Web', 'SQLi']
            ]
        ];
    } else {
        $tasks = [
            [
                'task_id'     => 'task_1',
                'title'       => 'Initial Infiltration',
                'description' => 'Find the vulnerability in the public-facing web application and gain initial access to the system.',
                'zeal'        => 500,
                'difficulty'  => 'Easy',
                'multiplier'  => 1.0,
                'completed'   => false,
                'tags'        => ['Web', 'SQLi']
            ],
            [
                'task_id'     => 'task_2',
                'title'       => 'Privilege Escalation',
                'description' => 'Locate the sensitive files in the home directory and escalate your privileges to root.',
                'zeal'        => 1200,
                'difficulty'  => 'Medium',
                'multiplier'  => 1.2,
                'completed'   => false,
                'tags'        => ['System', 'PrivEsc']
            ]
        ];
    }
}

include __DIR__ . '/partials/challenge_header.php';
?>

<div class="container-fluid px-0 py-3">
    <div class="row g-4">
        <!-- LEFT: Tasks List -->
        <div class="col-lg-8">
            <div class="d-flex flex-column gap-3">
                <?php foreach ($tasks as $task): ?>
                <div class="card border-0 shadow-sm rounded-4 blur overflow-hidden" style="background:rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05) !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center bg-dark bg-opacity-50" style="width:48px;height:48px; min-width:48px;">
                                    <i class='bx <?= ($task['completed'] ?? false) ? 'bx-check-circle text-success' : 'bx-flag text-white-50' ?>' style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="fw-bold text-white mb-0"><?= htmlspecialchars($task['title']) ?></h5>
                                        <span class="badge rounded-pill bg-opacity-10 <?= strtolower($task['difficulty']) === 'easy' ? 'bg-success text-success' : (strtolower($task['difficulty']) === 'medium' ? 'bg-warning text-warning' : 'bg-danger text-danger') ?>" style="font-size: 0.6rem; border: 1px solid currentColor;">
                                            <?= strtoupper($task['difficulty']) ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary small mb-3" style="max-width: 500px;"><?= htmlspecialchars($task['description']) ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach (($task['tags'] ?? []) as $tag): ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-white-50 rounded-pill px-2" style="font-size: 0.55rem;"><?= $tag ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end ms-auto">
                                <div class="d-flex align-items-center gap-1 justify-content-end mb-2">
                                    <span class="text-warning fw-bold small">ZEAL <?= number_format($task['zeal']) ?></span>
                                    <i class='bx bxs-hot text-warning'></i>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-dark bg-opacity-50 btn-sm rounded-pill px-3 fw-bold text-white-50" style="font-size: 0.7rem;">MISSION BRIEF</button>
                                    <?php if ($missionStarted): ?>
                                        <button class="btn btn-secondary btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.7rem;" disabled>MISSION IN PROGRESS</button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.7rem;" onclick="startMission(this, '<?= htmlspecialchars($task['task_id']) ?>')">START MISSION</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: Hints & Progress -->
        <div class="col-lg-4">
            <div class="card mb-4 border-0 shadow-sm rounded-4 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h6 class="fw-bold mb-0">Your Progress</h6>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="54" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="8"></circle>
                            <circle cx="60" cy="60" r="54" fill="none" stroke="#2eb85c" stroke-width="8" stroke-dasharray="339.29" stroke-dashoffset="<?= 339.29 * (1 - ($challengesCompleted / max(1, $totalTasks))) ?>" stroke-linecap="round" transform="rotate(-90 60 60)"></circle>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <h3 class="fw-bold text-white mb-0"><?= round(($challengesCompleted / max(1, $totalTasks)) * 100) ?>%</h3>
                        </div>
                    </div>
                    <p class="text-secondary small">You have completed <?= $challengesCompleted ?> out of <?= $totalTasks ?> tasks.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Available Hints</h6>
                    <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.6rem;">⚡ COSTS ZEAL</span>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-dark bg-opacity-25 border-white border-opacity-10 text-start text-white-50 small p-3 rounded-3 d-flex justify-content-between align-items-center">
                            <span>Initial Infiltration Hint</span>
                            <i class='bx bx-lock-alt'></i>
                        </button>
                        <button class="btn btn-dark bg-opacity-25 border-white border-opacity-10 text-start text-white-50 small p-3 rounded-3 d-flex justify-content-between align-items-center">
                            <span>Privilege Escalation Hint</span>
                            <i class='bx bx-lock-alt'></i>
                        </button>
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
async function startMission(btn, taskId) {
    if (typeof Dashboard !== 'undefined' && Dashboard.isProcessing) return;
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> STARTING...';
    btn.disabled = true;
    
    try {
        const formData = new URLSearchParams();
        formData.append('challenge_id', '<?= htmlspecialchars($labId) ?>');
        formData.append('hash', '<?= htmlspecialchars($instanceHash) ?>');
        formData.append('task_id', taskId);
        
        const response = await fetch('/api/challenges/start_mission', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            window.location.reload(); // Reload to unmask IPs and update button
        } else {
            alert("Error: " + data.error);
            btn.innerHTML = 'START MISSION';
            btn.disabled = false;
        }
    } catch (e) {
        alert("Error starting mission: " + e.message);
        btn.innerHTML = 'START MISSION';
        btn.disabled = false;
    }
}

</script>