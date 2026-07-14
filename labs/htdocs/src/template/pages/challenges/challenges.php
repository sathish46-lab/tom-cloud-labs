<?php
/**
 * Challenge Tasks Template
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$status       = Session::get('challenge_status');
$isRunning    = ($status === 'running' || $status === 'completed');
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
$failedAttempts = (int)($userProgress['failed_attempts'] ?? 0);
$creds     = $userProgress['credentials'] ?? null;
$hasConn   = $isRunning && $creds;

// Mock data if empty
if (empty($tasks)) {
    $tasksJsonPath = __DIR__ . '/../../../config/challenge_tasks.json';
    if (file_exists($tasksJsonPath)) {
        $tasksData = json_decode(file_get_contents($tasksJsonPath), true) ?? [];
        $normalizedId = str_replace('_', '-', $labId);
        $tasks = $tasksData[$labId] ?? $tasksData[$normalizedId] ?? [];
    } else {
        $tasks = [];
    }
}

// Mark tasks completed if progress exists and calculate dynamic attempts/multiplier
foreach ($tasks as &$t) {
    if ($challengesCompleted > 0) {
        $t['completed'] = true;
    }
    // Calculate attempts and multiplier dynamically based on user's progress
    $multiplier = 1.5 * pow(0.75, $failedAttempts);
    $t['multiplier'] = round($multiplier, 3);
}
unset($t);

include __DIR__ . '/partials/challenge_header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="row g-4">
        <!-- LEFT: Tasks List -->
        <div class="col-lg-8">
            <div class="d-flex flex-column gap-3">
                <?php foreach ($tasks as $task): ?>
                <div class="card border-0 shadow rounded-4 overflow-hidden mb-3" style="background: rgba(18, 18, 18, 0.45); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08) !important;">
                    <!-- Top Content -->
                    <div class="p-3 d-flex align-items-start justify-content-between gap-3">
                        <div class="d-flex gap-3 align-items-start flex-grow-1">
                            <!-- Image -->
                            <img src="<?= $labDetails['image'] ?? 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=150&h=150&fit=crop' ?>" alt="Task Image" class="rounded-3 shadow-sm flex-shrink-0" style="width: 72px; height: 72px; object-fit: cover; border: 1px solid rgba(255,255,255,0.12);">
                            
                            <!-- Info -->
                            <div class="d-flex flex-column gap-1">
                                <h4 class="fw-bold text-white mb-1" style="font-size: 1.25rem; letter-spacing: -0.3px; text-shadow: 0 2px 4px rgba(0,0,0,0.4);"><?= htmlspecialchars($task['title']) ?></h4>
                                <div class="d-flex flex-wrap gap-1.5 align-items-center mb-1">
                                    <span class="badge rounded-pill text-white px-2 py-0.5" style="font-size: 0.65rem; font-weight: 700; background: #2ecc71; text-transform: lowercase;"><?= strtolower($task['difficulty']) ?></span>
                                    <?php foreach (($task['tags'] ?? []) as $tag): ?>
                                        <span class="badge rounded-pill text-white px-2 py-0.5" style="font-size: 0.65rem; font-weight: 700; background: #3498db; text-transform: lowercase;"><?= strtolower($tag) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($task['completed'] ?? false): ?>
                                        <span class="badge rounded-pill text-white px-2 py-0.5" style="font-size: 0.65rem; font-weight: 700; background: #9b59b6; text-transform: lowercase;">completed 🏆</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-white-50 mb-0 mt-0.5" style="font-size: 0.82rem; line-height: 1.35; max-width: 680px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);"><?= htmlspecialchars($task['description']) ?></p>
                            </div>
                        </div>

                        <!-- Zeal & Jolt -->
                        <div class="text-end flex-shrink-0 pt-0.5 d-flex align-items-center" style="gap: 12px;">
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="text-white-50" style="font-size: 0.95rem; font-weight: 500;">Zeal</span>
                                <?php
                                $displayZeal = ($task['completed'] ?? false) ? 0 : (int)round($task['zeal'] * $task['multiplier']);
                                ?>
                                <span class="text-white fw-bold" style="font-size: 1.45rem; line-height: 1;"><?= number_format($displayZeal) ?></span>
                                <span style="font-size: 1.2rem; line-height: 1;">🔥</span>
                            </div>
                            <div class="d-flex align-items-center gap-1.5">
                                <span class="text-white-50" style="font-size: 0.95rem; font-weight: 500;">Jolt</span>
                                <?php
                                $displayJolt = ($task['completed'] ?? false) ? 0 : ($task['jolt'] ?? 10);
                                ?>
                                <span class="text-white fw-bold" style="font-size: 1.45rem; line-height: 1;"><?= number_format($displayJolt) ?></span>
                                <span style="font-size: 1.2rem; line-height: 1;">⚡</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: rgba(0,0,0,0.35); border-top: 1px solid rgba(255,255,255,0.06);">
                        <div class="text-white-50 d-flex align-items-center" style="font-size: 0.8rem;">
                            Failed Attempts: <?= $failedAttempts ?> &middot; Score Multiplier: <?= (floor($task['multiplier'] * 10) == $task['multiplier'] * 10 ? number_format($task['multiplier'], 1) : number_format($task['multiplier'], 3)) ?>x
                            <i class='bx bx-info-square ms-1.5 text-white-50' style="cursor: pointer; font-size: 0.95rem;"></i>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn rounded-pill px-3 py-1 fw-bold text-white shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.78rem; background: #17a2b8; border: none;">
                                <i class='bx bx-news' style="font-size: 0.9rem;"></i> Mission Brief
                            </button>
                            <?php if ($isRunning && $missionStarted): ?>
                                <button class="btn rounded-pill px-3 py-1 fw-bold text-dark shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.78rem; background: #fbbf24; border: none;" data-coreui-toggle="modal" data-coreui-target="#submitFlagModal-<?= htmlspecialchars($task['task_id']) ?>">
                                    <i class='bx bx-flag' style="font-size: 0.9rem;"></i> Submit Flag
                                </button>
                            <?php elseif ($task['completed'] ?? false): ?>
                                <button class="btn rounded-pill px-3 py-1 fw-bold text-white shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.78rem; background: #2ecc71; border: none;" data-coreui-toggle="modal" data-coreui-target="#startMissionConfirmModal-<?= htmlspecialchars($task['task_id']) ?>">
                                    <i class='bx bx-refresh' style="font-size: 0.9rem;"></i> Restart Mission
                                </button>
                            <?php else: ?>
                                <button class="btn rounded-pill px-3 py-1 fw-bold text-white shadow-sm d-flex align-items-center gap-1.5" style="font-size: 0.78rem; background: #2ecc71; border: none;" data-coreui-toggle="modal" data-coreui-target="#startMissionConfirmModal-<?= htmlspecialchars($task['task_id']) ?>">
                                    <i class='bx bx-flag' style="font-size: 0.9rem;"></i> Start Mission
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Start/Restart Mission Confirmation Modal -->
                <div class="modal fade" id="startMissionConfirmModal-<?= htmlspecialchars($task['task_id']) ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08) !important;">
                            <div class="modal-header border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold m-0 text-white" style="font-size: 1.35rem; letter-spacing: -0.3px;"><?= $task['completed'] ? 'Restart Mission' : 'Start Mission' ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" style="font-size: 0.8rem; filter: var(--cui-btn-close-white-filter, none);"></button>
                            </div>
                            <div class="modal-body px-4 pb-3 pt-3">
                                <div class="mb-3">
                                    <div class="text-white-50 small mb-1">Challenge: <strong class="text-white" style="font-size: 1.05rem;"><?= htmlspecialchars($task['title']) ?></strong></div>
                                    <p class="text-white-50 small mb-3"><?= htmlspecialchars($task['description']) ?></p>
                                    <div class="text-white small d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.05);">
                                        <span>📩</span>
                                        <span>Read the mission brief for more information about how to approach for the mission.</span>
                                    </div>
                                </div>
                                
                                <hr style="border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;">

                                <div class="mb-2">
                                    <h6 class="fw-bold text-white mb-3" style="font-size: 1.0rem;">Zeal Rewards on Successful Completion in this attempt: <?= $failedAttempts + 1 ?></h6>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-dark table-borderless align-middle mb-0" style="background: transparent;">
                                            <thead>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">#</th>
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">Description</th>
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">Rewards</th>
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">Zeal Multiplier</th>
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">Multiplied Rewards</th>
                                                    <th style="font-size: 0.75rem; color: rgba(255,255,255,0.6); padding-bottom: 8px;">Claims</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                                                    <td class="text-white-50">1</td>
                                                    <td class="text-white fw-bold">Capture the Flag</td>
                                                    <td class="text-white-50">
                                                        <?php if ($failedAttempts === 0): ?>
                                                            <?= $task['zeal'] ?> 🔥
                                                        <?php else: ?>
                                                            <?= $task['zeal'] ?> x (0.75<sup><?= $failedAttempts ?></sup>) = <?= (int)round($task['zeal'] * pow(0.75, $failedAttempts)) ?> 🔥
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-white-50">1.5 x</td>
                                                    <td class="text-success fw-bold">
                                                        <?php if ($task['completed']): ?>
                                                            0
                                                        <?php else: ?>
                                                            <?= (int)round($task['zeal'] * $task['multiplier']) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($task['completed']): ?>
                                                            <span class="badge rounded-pill bg-success px-2 py-1"><span class="d-inline-block rounded-circle bg-white me-1" style="width:6px;height:6px;"></span> Claimed</span>
                                                        <?php else: ?>
                                                            <span class="badge rounded-pill bg-secondary px-2 py-1"><span class="d-inline-block rounded-circle bg-light me-1" style="width:6px;height:6px;"></span> Not Claimed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr style="font-weight: bold;">
                                                    <td></td>
                                                    <td class="text-white">Total</td>
                                                    <td class="text-white-50"><?= $task['zeal'] ?> 🔥</td>
                                                    <td class="text-white-50">1.5 x</td>
                                                    <td class="text-success fw-bold">
                                                        <?php if ($task['completed']): ?>
                                                            0
                                                        <?php else: ?>
                                                            <?= (int)round($task['zeal'] * $task['multiplier']) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-white fw-bold">
                                                        <?php if ($task['completed']): ?>
                                                            <?= number_format($userProgress['zeal_earned'] ?? 0) ?>
                                                        <?php else: ?>
                                                            0
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                                <button type="button" class="btn rounded-pill px-4 py-1.5 fw-bold text-white" data-coreui-dismiss="modal" style="background: rgba(255, 255, 255, 0.1); border: none; font-size: 0.82rem;">Dismiss</button>
                                <button type="button" class="btn rounded-pill px-4 py-1.5 fw-bold text-white d-flex align-items-center gap-1.5" onclick="triggerConfirmStartMission(this, '<?= htmlspecialchars($task['task_id']) ?>', 'startMissionConfirmModal-<?= htmlspecialchars($task['task_id']) ?>')" style="background: #2ecc71; border: none; font-size: 0.82rem;">
                                    <i class="bx bx-flag" style="font-size: 0.95rem;"></i> <?= $task['completed'] ? 'Restart Mission' : 'Start Mission' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Flag Modal -->
                <div class="modal fade" id="submitFlagModal-<?= htmlspecialchars($task['task_id']) ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08) !important;">
                            <div class="modal-header border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold m-0 text-white" style="font-size: 1.35rem; letter-spacing: -0.3px;">Submit Flag</h5>
                                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" style="font-size: 0.8rem; filter: var(--cui-btn-close-white-filter, none);"></button>
                            </div>
                            <div class="modal-body px-4 pb-3 pt-3">
                                <!-- Info Section -->
                                <div class="mb-3">
                                    <div class="text-white-50 small mb-1">Challenge: <strong class="text-white" style="font-size: 1.05rem;"><?= htmlspecialchars($task['title']) ?></strong></div>
                                    <p class="text-white-50 small mb-3"><?= htmlspecialchars($task['description']) ?></p>
                                    <div class="text-white small d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.05);">
                                        <span>🚩</span>
                                        <span>You are about to Submit a flag that you have captured by completing the challenge.</span>
                                    </div>
                                </div>
                                
                                <hr style="border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;">

                                <!-- Form Section -->
                                <form id="submitFlagForm-<?= htmlspecialchars($task['task_id']) ?>" onsubmit="submitFlag(event, '<?= htmlspecialchars($task['task_id']) ?>', 'submitFlagModal-<?= htmlspecialchars($task['task_id']) ?>')">
                                    <div class="d-flex align-items-center gap-3 mb-4">
                                        <label class="text-white fw-bold mb-0 flex-shrink-0" style="font-size: 0.9rem;">Submit Captured Flag</label>
                                        <input type="text" id="flagInput-<?= htmlspecialchars($task['task_id']) ?>" class="form-control rounded-pill text-white border-0 px-4 py-2" placeholder="32 bit md5.ninja" required style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08) !important; font-size: 0.9rem;">
                                    </div>
                                </form>

                                <!-- Warnings Section -->
                                <div class="mt-2">
                                    <h6 class="fw-bold text-white mb-2" style="font-size: 0.92rem; opacity: 0.9;">Read Carefully before you submit the flag</h6>
                                    <ul class="text-white-50 small ps-3 d-flex flex-column gap-2" style="font-size: 0.82rem; line-height: 1.4;">
                                        <li>Submitting a wrong flag will result in a penalty of 25% of the Zeal Score Multiplier and mission is considered as a failure attempt.</li>
                                        <li>Submitting a correct flag will result in a reward of <strong><?= number_format($task['zeal']) ?> zeal</strong>.</li>
                                        <li>You can claim the bonus Zeal after submitting the correct flag by clicking on the <strong>Submit Mission Report</strong> button.</li>
                                        <li>The bonus is awarded only once per challenge on submission of the mission report. Mission report has to be submitted within 48 hours of the successful completion of the mission.</li>
                                        <li>If you are redoing this mission after a successful completion, you will not get any rewards.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                                <button type="button" class="btn rounded-pill px-4 py-1.5 fw-bold text-white" data-coreui-dismiss="modal" style="background: rgba(255, 255, 255, 0.1); border: none; font-size: 0.82rem;">Dismiss</button>
                                <button type="button" id="submitBtn-<?= htmlspecialchars($task['task_id']) ?>" class="btn rounded-pill px-4 py-1.5 fw-bold text-dark d-flex align-items-center gap-1.5" onclick="document.getElementById('submitFlagForm-<?= htmlspecialchars($task['task_id']) ?>').requestSubmit()" style="background: #fbbf24; border: none; font-size: 0.82rem;">
                                    <i class="bx bx-flag" style="font-size: 0.95rem;"></i> Submit Flag
                                </button>
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

            <div class="card mb-4 border-0 shadow-sm rounded-4 blur" style="background:rgba(255,255,255,0.03);">
                <div class="card-header bg-transparent border-0 pt-3 px-3">
                    <h6 class="fw-bold mb-0" style="font-size: 0.9rem;">Your Progress</h6>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="position-relative d-inline-block mb-2">
                        <svg width="80" height="80" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="6"></circle>
                            <circle cx="40" cy="40" r="34" fill="none" stroke="#2eb85c" stroke-width="6" stroke-dasharray="213.62" stroke-dashoffset="<?= 213.62 * (1 - ($challengesCompleted / max(1, $totalTasks))) ?>" stroke-linecap="round" transform="rotate(-90 40 40)"></circle>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <h5 class="fw-bold text-white mb-0"><?= round(($challengesCompleted / max(1, $totalTasks)) * 100) ?>%</h5>
                        </div>
                    </div>
                    <p class="text-secondary mb-0" style="font-size: 0.75rem;">Completed <?= $challengesCompleted ?> of <?= $totalTasks ?> tasks.</p>
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

<?php include __DIR__ . '/../labs/partials/server_logs.php'; ?>

<script>
function triggerConfirmStartMission(btn, taskId, modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
        const modalInstance = coreui.Modal.getInstance(modalEl) || new coreui.Modal(modalEl);
        modalInstance.hide();
    }
    const targetBtn = document.querySelector(`[data-coreui-target="#${modalId}"]`);
    startMission(targetBtn || btn, taskId);
}

async function startMission(btn, taskId) {
    if (typeof Dashboard !== 'undefined' && Dashboard.isProcessing) return;
    
    // Dynamically detect if the lab is running from the DOM (immune to stale page loads)
    const isRunning = !!document.getElementById('challenge-countdown');
    
    if (!isRunning) {
        if (typeof TomNotify !== 'undefined') {
            TomNotify.show("Challenge is not currently running. Deploy it first.", "Error", "error");
        } else {
            alert("Error: Challenge is not currently running. Deploy it first.");
        }
        return;
    }
    
    const originalHTML = btn.innerHTML;
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
            window.location.reload();
        } else {
            if (typeof TomNotify !== 'undefined') {
                TomNotify.show(data.error || "Challenge is not currently running. Deploy it first.", "Error", "error");
            } else {
                alert("Error: " + (data.error || "Challenge is not currently running. Deploy it first."));
            }
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    } catch (e) {
        if (typeof TomNotify !== 'undefined') {
            TomNotify.show("Error starting mission: " + e.message, "Error", "error");
        } else {
            alert("Error starting mission: " + e.message);
        }
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

async function submitFlag(event, taskId, modalId) {
    event.preventDefault();
    
    const flagInput = document.getElementById('flagInput-' + taskId);
    const submitBtn = document.getElementById('submitBtn-' + taskId);
    
    if (!flagInput || !submitBtn) return;
    
    const originalBtnHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> VALIDATING...';
    submitBtn.disabled = true;
    
    try {
        const formData = new URLSearchParams();
        formData.append('challenge_id', '<?= htmlspecialchars($labId) ?>');
        formData.append('hash', '<?= htmlspecialchars($instanceHash) ?>');
        formData.append('flag', flagInput.value.trim());
        
        const response = await fetch('/api/challenges/submit_flag', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.status === 'success') {
            // Success! Hide Modal
            const modalEl = document.getElementById(modalId);
            if (modalEl) {
                const modalInstance = coreui.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
            }
            
            if (typeof TomNotify !== 'undefined') {
                TomNotify.show("Congratulations! Correct flag submitted.", "Mission Cleared", "success");
            } else {
                alert("Success: " + data.message);
            }
            
            // Reload page to reflect completion status
            setTimeout(() => {
                window.location.reload();
            }, 1800);
            
        } else {
            // Hide Modal
            const modalEl = document.getElementById(modalId);
            if (modalEl) {
                const modalInstance = coreui.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
            }

            if (typeof TomNotify !== 'undefined') {
                TomNotify.show(data.error || "Incorrect flag. Please try again!", "Failed", "error");
            } else {
                alert("Error: " + data.error);
            }
            
            // Reload page to update attempts counter and reset back to Restart Mission
            setTimeout(() => {
                window.location.reload();
            }, 1800);
        }
    } catch (e) {
        if (typeof TomNotify !== 'undefined') {
            TomNotify.show("Error submitting flag: " + e.message, "Error", "error");
        } else {
            alert("Error submitting flag: " + e.message);
        }
        submitBtn.innerHTML = originalBtnHTML;
        submitBtn.disabled = false;
    }
}

</script>