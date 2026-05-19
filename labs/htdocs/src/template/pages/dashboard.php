<?php
/**
 * Dashboard Template - Pre-rendered for Performance
 */
$user = Session::getUser();
$userId = (int)$user->getUserId();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

// 1. Fetch Labs
$activeLabsCount = $db->deployed_labs->countDocuments(['user_id' => $userId, 'status' => 'running']);
$labsLimit = 5;
$deployedLabs = $db->deployed_labs->find(['user_id' => $userId, 'status' => 'running'], ['sort' => ['created_at' => -1]]);

$labsList = [];
foreach ($deployedLabs as $lab) {
    $labsList[] = [
        'name' => ucfirst($lab['lab_type'] ?? 'Lab'),
        'ip' => $lab['internal_ip'] ?? 'Unknown',
        'status' => $lab['status'] ?? 'unknown',
        'hash' => $lab['instance_hash'] ?? '',
        'type' => $lab['lab_type'] ?? 'unknown'
    ];
}

// 2. Fetch Domains
$domainCount = $db->domains->countDocuments(['user_id' => ['$in' => [(string)$userId, $userId]]]);
$domainsLimit = 10;
$domains = $db->domains->find(['user_id' => ['$in' => [(string)$userId, $userId]]], ['sort' => ['created_at' => -1]]);

// 3. Fetch User profile and stats dynamically
$userEmail = $user->getEmail();
$username = $user->getUsername();
$avatar = Session::getAvatar();

$userStats = $db->user_stats->findOne(['user_email' => $userEmail]);
$zeal = $userStats['zeal'] ?? 0;
$jolt = $userStats['jolt'] ?? 0;

$finishedQuizzes = $db->quiz_attempts->countDocuments(['user_email' => $userEmail]);

// Dynamic greeting based on current local hour
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Morning";
} elseif ($hour < 17) {
    $greeting = "Afternoon";
} else {
    $greeting = "Evening";
}
$greetingText = "{$greeting}, legend, {$username}!";
?>

<div class="container-fluid px-0 pt-4">
    <style>
        @keyframes greenPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 172, 132, 0.85);
                transform: scale(0.9);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(16, 172, 132, 0);
                transform: scale(1.25);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 172, 132, 0);
                transform: scale(0.9);
            }
        }
        @keyframes bluePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(46, 134, 222, 0.85);
                transform: scale(0.9);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(46, 134, 222, 0);
                transform: scale(1.25);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(46, 134, 222, 0);
                transform: scale(0.9);
            }
        }
        .active-indicator-green {
            width: 7px;
            height: 7px;
            display: inline-block;
            border-radius: 50%;
            background-color: #10ac84 !important;
            box-shadow: 0 0 6px rgba(16, 172, 132, 0.6);
            animation: greenPulse 1.6s infinite ease-in-out;
        }
        .active-indicator-blue {
            width: 7px;
            height: 7px;
            display: inline-block;
            border-radius: 50%;
            background-color: #2e86de !important;
            box-shadow: 0 0 6px rgba(46, 134, 222, 0.6);
            animation: bluePulse 1.6s infinite ease-in-out;
        }
    </style>
    <!-- Top Row: Profile & Clan Cards -->
    <div class="row g-4 mb-4">
        <!-- Profile Banner -->
        <div class="col-12 col-xl-8">
            <style>
                @keyframes waveFlow {
                    0% {
                        transform: translate3d(0, 0, 0);
                    }
                    100% {
                        transform: translate3d(-50%, 0, 0);
                    }
                }
                .wave-wrapper {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 200%;
                    height: 100%;
                    pointer-events: none;
                    z-index: 1;
                }
                .wave-svg {
                    width: 100%;
                    height: 100%;
                    display: block;
                }
                .wave-front {
                    animation: waveFlow 18s linear infinite;
                }
                .wave-back {
                    animation: waveFlow 12s linear infinite reverse;
                }
            </style>
            <div class="card border-0 glass-card position-relative overflow-hidden h-100">
                <!-- Glowing Layered Liquid Waves (Background Visual Elements) -->
                <div class="wave-wrapper">
                    <svg class="wave-svg" viewBox="0 0 1600 220" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="waveStrokeGradient" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#ff4757" />
                                <stop offset="50%" stop-color="#ffa502" />
                                <stop offset="100%" stop-color="#ff7f50" />
                            </linearGradient>
                            <linearGradient id="waveStrokeGradientBack" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#70a1ff" />
                                <stop offset="50%" stop-color="#a55eea" />
                                <stop offset="100%" stop-color="#ff6b81" />
                            </linearGradient>
                        </defs>
                        <!-- Layer 2 (Back Wave) -->
                        <path class="wave-back" d="M 0,180 Q 200,150 400,180 T 800,180 T 1200,180 T 1600,180 L 1600,230 L 0,230 Z" fill="rgba(165, 94, 234, 0.06)"></path>
                        <path class="wave-back" d="M 0,180 Q 200,150 400,180 T 800,180 T 1200,180 T 1600,180" fill="none" stroke="url(#waveStrokeGradientBack)" stroke-width="1.5" opacity="0.45"></path>

                        <!-- Layer 1 (Front Wave) -->
                        <path class="wave-front" d="M 0,175 Q 200,145 400,175 T 800,175 T 1200,175 T 1600,175 L 1600,230 L 0,230 Z" fill="rgba(255, 110, 50, 0.15)"></path>
                        <path class="wave-front" d="M 0,175 Q 200,145 400,175 T 800,175 T 1200,175 T 1600,175" fill="none" stroke="url(#waveStrokeGradient)" stroke-width="2.5" filter="drop-shadow(0px 2px 8px rgba(255, 100, 50, 0.45))"></path>
                    </svg>
                </div>
                
                <div class="card-body p-4 d-flex flex-column position-relative" style="z-index: 2;">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="position-relative">
                                <img src="<?= $avatar ?>" alt="Profile" class="rounded-circle border border-2 border-white border-opacity-10 shadow" style="width: 58px; height: 58px; object-fit: cover;">
                                <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-dark rounded-circle" style="width: 12px; height: 12px; transform: translate(1px, 1px);"></span>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0 text-white" style="letter-spacing: -0.5px; font-size: 1.35rem;"><?= $greetingText ?></h4>
                                <p class="mb-0 text-white text-opacity-50 small mt-0.5" style="font-size: 0.78rem;">5 lessons in progress — keep going!</p>
                            </div>
                        </div>
                        <div>
                            <a href="/profile" class="btn btn-sm bg-white bg-opacity-10 text-white rounded-pill px-3 py-1.5 fw-semibold border border-white border-opacity-10 transition-all hover-lift" style="font-size: 0.75rem; backdrop-filter: blur(10px);">
                                <i class='bx bx-user me-1 align-middle'></i> Profile
                            </a>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <div class="d-flex align-items-center gap-1.5 me-3">
                            <i class='bx bxs-hot animate-pulse align-middle' style="font-size: 1.25rem; color: #ff763b !important;"></i>
                            <span class="fw-bold text-white fs-5 align-middle" style="letter-spacing: -0.5px; font-family: sans-serif;"><?= number_format($zeal) ?></span>
                            <span class="text-white text-opacity-45 align-middle ms-1" style="font-size: 0.76rem;">zeal</span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5 me-3">
                            <i class='bx bxs-zap align-middle' style="font-size: 1.25rem; color: #b53bf6 !important;"></i>
                            <span class="fw-bold text-white fs-5 align-middle" style="letter-spacing: -0.5px; font-family: sans-serif;"><?= number_format($jolt) ?></span>
                            <span class="text-white text-opacity-45 align-middle ms-1" style="font-size: 0.76rem;">jolt</span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <i class='bx bxs-medal align-middle' style="font-size: 1.25rem; color: #3b82f6 !important;"></i>
                            <span class="fw-bold text-white fs-5 align-middle" style="letter-spacing: -0.5px; font-family: sans-serif;">#3</span>
                            <span class="text-white text-opacity-45 align-middle ms-1" style="font-size: 0.76rem;">Rank</span>
                        </div>
                    </div>

                    <!-- Dynamic Pills Row -->
                    <div class="d-flex flex-wrap gap-2.5 mb-4">
                        <span class="badge rounded-pill px-3 py-1.5 d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(46, 213, 115, 0.35) !important; color: #2ed573 !important; background-color: rgba(46, 213, 115, 0.08) !important;">
                            <i class='bx bx-check-circle fs-6 me-0.5 align-middle'></i> <?= $finishedQuizzes ?> Quizzes
                        </span>
                        <span class="badge rounded-pill px-3 py-1.5 d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(255, 71, 87, 0.35) !important; color: #ff4757 !important; background-color: rgba(255, 71, 87, 0.08) !important;">
                            <i class='bx bx-swords fs-6 me-0.5 align-middle'></i> 0 Challenges
                        </span>
                        <span class="badge rounded-pill px-3 py-1.5 d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(165, 94, 234, 0.35) !important; color: #a55eea !important; background-color: rgba(165, 94, 234, 0.08) !important;">
                            <i class='bx bx-terminal fs-6 me-0.5 align-middle'></i> 0 Code Solved
                        </span>
                        <span class="badge rounded-pill px-3 py-1.5 d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(30, 144, 255, 0.35) !important; color: #1e90ff !important; background-color: rgba(30, 144, 255, 0.08) !important;">
                            <i class='bx bx-book-open fs-6 me-0.5 align-middle'></i> 0 Lessons
                        </span>
                        <span class="badge rounded-pill px-3 py-1.5 d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600; border: 1px solid rgba(255, 165, 2, 0.35) !important; color: #ffa502 !important; background-color: rgba(255, 165, 2, 0.08) !important;">
                            <i class='bx bx-trophy fs-6 me-0.5 align-middle'></i> 0 Achievements
                        </span>
                    </div>

                    <!-- Navigation Action Buttons -->
                    <div class="d-flex flex-wrap gap-2 pt-2" style="z-index: 3;">
                        <a href="/learn" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: #6f32cf !important; color: #ffffff !important; border: none !important;">
                            <i class='bx bxs-brain me-1 align-middle'></i> AI Learning
                        </a>
                        <a href="/labs" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: #2ed573 !important; color: #ffffff !important; border: none !important;">
                            <i class='bx bx-desktop me-1 align-middle'></i> Labs
                        </a>
                        <a href="#" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: #1e90ff !important; color: #ffffff !important; border: none !important;">
                            <i class='bx bx-code-alt me-1 align-middle'></i> Code Arena
                        </a>
                        <a href="#" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: #ffa502 !important; color: #ffffff !important; border: none !important;">
                            <i class='bx bx-map-alt me-1 align-middle'></i> Roadmaps
                        </a>
                        <a href="/quiz" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: #ff4757 !important; color: #ffffff !important; border: none !important;">
                            <i class='bx bx-check-square me-1 align-middle'></i> Quizzes
                        </a>
                        <a href="#" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold hover-lift transition-all" style="font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.12) !important; color: #ffffff !important; border: none !important; backdrop-filter: blur(5px);">
                            <i class='bx bx-chat me-1 align-middle'></i> Discuss
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clan Card -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 position-relative overflow-hidden h-100" style="background: linear-gradient(180deg, rgba(0, 0, 0, 0.25) 0%, rgba(0, 0, 0, 0.8) 100%), url('/assets/Background_Img/clan_zero_byte.png') no-repeat center; background-size: cover; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                
                <div class="card-body p-4 d-flex flex-column justify-content-between position-relative" style="z-index: 2;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="position-relative">
                            <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center bg-dark bg-opacity-70 border shadow" style="width: 52px; height: 52px; border-color: #00d2d3 !important; border-width: 2px !important; box-shadow: 0 0 15px rgba(0, 210, 211, 0.6) !important;">
                                <span class="fw-bold text-white" style="font-size: 1.15rem; letter-spacing: -1px; font-family: sans-serif; text-shadow: 0 0 8px rgba(0,210,211,0.5);">ZB</span>
                            </div>
                        </div>
                        <div>
                            <h5 class="fw-bold text-white mb-0" style="letter-spacing: -0.5px; font-size: 1.1rem;">Zero Byte</h5>
                            <p class="mb-0 text-white text-opacity-40 small" style="font-size: 0.75rem;">@<?= $username ?></p>
                        </div>
                    </div>

                    <!-- Frosted Glass Stats Grid -->
                    <div class="rounded-4 my-2 clan-stats-grid" style="padding: 12px 16px;">
                        <!-- Row 1: 3 Columns -->
                        <div class="d-flex justify-content-between align-items-center text-center">
                            <div style="flex: 1;">
                                <div class="fw-bold text-white d-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem; font-family: sans-serif;">
                                    <i class="bx bxs-hot" style="color: #ff9f43 !important; font-size: 0.85rem;"></i> 15,941
                                </div>
                                <div class="text-white text-opacity-45 mt-0.5" style="font-size: 0.58rem; font-weight: 500;">Zeal</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="fw-bold text-white d-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem; font-family: sans-serif;">
                                    <i class="bx bxs-user-detail text-white text-opacity-75" style="font-size: 0.85rem;"></i> 2
                                </div>
                                <div class="text-white text-opacity-45 mt-0.5" style="font-size: 0.58rem; font-weight: 500;">Members</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="fw-bold text-white d-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem; font-family: sans-serif;">
                                    <i class="bx bxs-award text-white text-opacity-75" style="font-size: 0.85rem;"></i> 98
                                </div>
                                <div class="text-white text-opacity-45 mt-0.5" style="font-size: 0.58rem; font-weight: 500;">Badges</div>
                            </div>
                        </div>
                        
                        <!-- Row 2: 2 Columns -->
                        <div class="d-flex justify-content-around align-items-center text-center mt-2.5 px-3">
                            <div style="flex: 1;">
                                <div class="fw-bold text-white d-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem; font-family: sans-serif;">
                                    <i class="bx bx-check-square text-white text-opacity-75" style="font-size: 0.85rem;"></i> 35
                                </div>
                                <div class="text-white text-opacity-45 mt-0.5" style="font-size: 0.58rem; font-weight: 500;">Missions</div>
                            </div>
                            <div style="flex: 1;">
                                <div class="fw-bold text-white d-flex align-items-center justify-content-center gap-1" style="font-size: 0.8rem; font-family: sans-serif;">
                                    <i class="bx bx-desktop text-white text-opacity-75" style="font-size: 0.85rem;"></i> 17/56
                                </div>
                                <div class="text-white text-opacity-45 mt-0.5" style="font-size: 0.58rem; font-weight: 500;">Labs Done</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <a href="#" class="btn btn-sm rounded-pill fw-bold py-2 border-0 d-flex align-items-center justify-content-center gap-1.5 mt-2 transition-all hover-lift" style="background-color: #6f32cf !important; color: #ffffff !important; font-size: 0.78rem;">
                        <i class='bx bx-group align-middle'></i> View Clan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Row: Connected Devices & Domains -->
    <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 glass-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-white" style="letter-spacing: -0.5px; font-size: 1.05rem;">Connected Devices</h5>
                        <p class="mb-0 text-white text-opacity-40 small" style="font-size: 0.7rem;">Active sandbox containers</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 fw-semibold d-inline-flex align-items-center gap-2" style="font-size: 0.72rem; padding: 4px 10px;">
                            <span class="rounded-circle active-indicator-green" style="width: 6px; height: 6px;"></span>
                            <?= $activeLabsCount ?> / <?= $labsLimit ?> Active
                        </span>
                    </div>
                </div>

                <!-- Device List -->
                <div class="device-list pe-1" style="max-height: 125px; overflow-y: auto;">
                    <?php if (!empty($labsList)): ?>
                        <?php foreach ($labsList as $lab): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(16, 172, 132, 0.12); border: 1px solid rgba(16, 172, 132, 0.2);">
                                    <i class='bx bx-cube-alt text-success' style="font-size: 0.85rem; color: #10ac84 !important;"></i>
                                </div>
                                <div>
                                    <span class="text-white fw-bold small d-block" style="line-height: 1.2; font-size: 0.78rem;"><?= $lab['name'] ?> Lab</span>
                                    <span class="text-success fw-bold text-uppercase" style="font-size: 0.55rem; letter-spacing: 0.3px; color: #10ac84 !important;">Online</span>
                                </div>
                            </div>
                            <div class="text-end d-flex align-items-center gap-2">
                                <div>
                                    <div class="text-white font-monospace fw-semibold" style="font-size: 0.7rem;"><?= $lab['ip'] ?></div>
                                    <div class="text-white text-opacity-35 small fw-bold text-uppercase" style="font-size: 0.5rem; letter-spacing: 0.3px;">Internal IP</div>
                                </div>
                                <button class="btn btn-sm btn-link p-1 text-white text-opacity-40 hover-text-white transition-all" onclick="navigator.clipboard.writeText('<?= $lab['ip'] ?>'); showToast('IP copied!')">
                                    <i class="bx bx-copy" style="font-size: 0.8rem;"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-white text-opacity-30 py-4 small">
                            <i class="bx bx-server opacity-20 d-block fs-3 mb-1"></i>
                            No active nodes currently connected
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 glass-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-white" style="letter-spacing: -0.5px; font-size: 1.05rem;">Linked Domains</h5>
                        <p class="mb-0 text-white text-opacity-40 small" style="font-size: 0.7rem;">Domain mapping & routing</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-20 fw-semibold d-inline-flex align-items-center gap-2" style="font-size: 0.72rem; padding: 4px 10px;">
                            <span class="rounded-circle active-indicator-blue" style="width: 6px; height: 6px;"></span>
                            <?= $domainCount ?> / <?= $domainsLimit ?> Mapped
                        </span>
                    </div>
                </div>

                <!-- Domain List -->
                <div class="domain-list pe-1" style="max-height: 125px; overflow-y: auto;">
                    <?php if ($domainCount > 0): ?>
                        <?php foreach ($domains as $d): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(46, 134, 222, 0.12); border: 1px solid rgba(46, 134, 222, 0.2);">
                                    <i class='bx bx-globe text-info' style="font-size: 0.85rem; color: #2e86de !important;"></i>
                                </div>
                                <div>
                                    <span class="text-white fw-bold small d-block" style="line-height: 1.2; font-size: 0.78rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= $d['domain'] ?></span>
                                    <span class="text-info fw-bold text-uppercase" style="font-size: 0.55rem; letter-spacing: 0.3px; color: #2e86de !important;">A Record</span>
                                </div>
                            </div>
                            <div class="text-end d-flex align-items-center gap-2">
                                <div>
                                    <div class="text-white font-monospace fw-semibold" style="font-size: 0.7rem;"><?= $d['ip_address'] ?? 'Pending' ?></div>
                                    <div class="text-white text-opacity-35 small fw-bold text-uppercase" style="font-size: 0.5rem; letter-spacing: 0.3px;">Pointer IP</div>
                                </div>
                                <button class="btn btn-sm btn-link p-1 text-white text-opacity-40 hover-text-white transition-all" onclick="navigator.clipboard.writeText('<?= $d['domain'] ?>'); showToast('Domain copied!')">
                                    <i class="bx bx-copy" style="font-size: 0.8rem;"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-white text-opacity-30 py-4 small">
                            <i class="bx bx-globe opacity-20 d-block fs-3 mb-1"></i>
                            No custom domains linked yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 glass-card">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 d-flex align-items-center text-body" style="font-size: 0.95rem;">
                    Machine Labs 
                    <span class="badge bg-danger rounded-circle p-1 ms-2 animate-pulse" style="width:8px; height:8px;"></span> 
                    <span class="text-danger small fw-bold ms-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">LIVE</span>
                </h6>
                <div class="small text-body-secondary fw-medium" style="font-size: 0.7rem;">
                    Limit: <?= $activeLabsCount ?>/<?= $labsLimit ?> <i class="bx bx-info-circle ms-1 align-middle opacity-50"></i>
                </div>
            </div>
            <div class="card-body p-4 pt-2">
                <div id="machine-labs-container" class="d-flex flex-column gap-3">
                    <?php if (!empty($labsList)): ?>
                        <?php foreach ($labsList as $lab): ?>
                        <div class="lab-row-premium d-flex flex-column flex-lg-row align-items-center p-3 rounded-4 gap-3 border border-white border-opacity-10" style="background: rgba(255,255,255,0.03);">
                            <div class="d-flex align-items-center gap-3 flex-grow-1 w-100">
                                    <div class="d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <?php 
                                            $typeIconMap = ['essentials' => 'bxl-tux', 'minio' => 'bxl-docker', 'n8n' => 'bx-git-repo-forked'];
                                            $iconClass = $typeIconMap[$lab['type']] ?? 'bxl-ubuntu';
                                        ?>
                                        <i class="bx <?= $iconClass ?> theme-text opacity-75" style="font-size: 2.2rem;"></i>
                                    </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-body"><?= $lab['name'] ?> Lab</h6>
                                    <div class="d-flex gap-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10" style="font-size: 0.6rem;">beta</span>
                                        <?php 
                                            $statusColor = 'bg-danger';
                                            if ($lab['status'] === 'running') $statusColor = 'bg-success';
                                            else if ($lab['status'] === 'deploying') $statusColor = 'bg-warning';
                                            else if ($lab['status'] === 'stopping') $statusColor = 'bg-orange';
                                        ?>
                                        <span class="badge <?= $statusColor ?> bg-opacity-10 <?= str_replace('bg-', 'text-', $statusColor) ?> border border-opacity-10" style="font-size: 0.6rem;"><?= strtoupper($lab['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-wrap justify-content-between justify-content-lg-end align-items-center gap-4 w-100">
                                <div class="text-center" style="min-width: 70px;">
                                    <div class="fw-bold text-body small" id="cpu-<?= $lab['hash'] ?>">0.00%</div>
                                    <div class="text-body-secondary" style="font-size: 0.6rem; font-weight: 600; letter-spacing: 0.3px;">CPU LOAD</div>
                                </div>
                                <div class="text-center" style="min-width: 80px;">
                                    <div class="fw-bold text-body small" id="mem-<?= $lab['hash'] ?>">0.00%</div>
                                    <div class="text-body-secondary" style="font-size: 0.6rem; font-weight: 600; letter-spacing: 0.3px;">MEMORY</div>
                                </div>
                                <div class="text-center" style="min-width: 120px;">
                                    <div class="fw-bold text-body small" id="load-<?= $lab['hash'] ?>">0.00, 0.00, 0.00</div>
                                    <div class="text-body-secondary" style="font-size: 0.6rem; font-weight: 600; letter-spacing: 0.3px;">LOAD AVG</div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="/labs/dashboard/<?= $lab['hash'] ?>" class="btn btn-sm btn-success rounded-3 px-3 fw-bold d-flex align-items-center" style="height: 34px; font-size: 0.75rem;">
                                        <i class='bx bx-grid-alt me-1'></i> Dashboard
                                    </a>
                                    <button onclick="openCodeModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')" class="btn btn-sm btn-primary bg-opacity-75 border-0 rounded-3 px-3 fw-bold d-flex align-items-center" style="height: 34px; font-size: 0.75rem;">
                                        <i class='bx bx-code-alt me-1'></i> Code
                                    </button>
                                    <button onclick="openConnectionModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')" class="btn btn-sm btn-info bg-opacity-75 border-0 rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class='bx bx-info-circle text-white' style="font-size: 1rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-body-secondary small">No machine labs deployed yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar (Three Boxes) -->
    <div class="col-12 col-xl-4 d-flex flex-column gap-4">
        <!-- Box 1: Recent Activity -->
        <div class="card border-0 glass-card">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-4" style="font-size: 0.9rem; letter-spacing: 0.5px; text-transform: uppercase;">Recent Activity</h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-start gap-3 small">
                        <div class="bg-success bg-opacity-10 p-2 rounded-circle mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                            <i class='bx bx-check-circle text-success fs-6'></i>
                        </div>
                        <div>
                            <span class="text-body fw-medium d-block mb-1">Milestone reached! +53🔥 & +35⚡</span>
                            <span class="text-body-secondary opacity-50" style="font-size: 0.7rem;">Just now</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 small">
                        <div class="bg-warning bg-opacity-10 p-2 rounded-circle mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                            <i class='bx bx-zap text-warning fs-6'></i>
                        </div>
                        <div>
                            <span class="text-body fw-medium d-block mb-1">Created theme: Ben 10</span>
                            <span class="text-body-secondary opacity-50" style="font-size: 0.7rem;">1w</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 small">
                        <div class="bg-warning bg-opacity-10 p-2 rounded-circle mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                            <i class='bx bx-zap text-warning fs-6'></i>
                        </div>
                        <div>
                            <span class="text-body fw-medium d-block mb-1">Submitted theme for review: Spiderman</span>
                            <span class="text-body-secondary opacity-50" style="font-size: 0.7rem;">1w</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 small">
                        <div class="bg-warning bg-opacity-10 p-2 rounded-circle mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                            <i class='bx bx-zap text-warning fs-6'></i>
                        </div>
                        <div>
                            <span class="text-body fw-medium d-block mb-1">Created theme: Spiderman</span>
                            <span class="text-body-secondary opacity-50" style="font-size: 0.7rem;">1w</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 2: Smart Insights (Dynamic) -->
        <div class="card border-0 glass-card position-relative overflow-hidden" id="smart-insights-card">
            <div class="card-body px-4 pt-4 pb-3 position-relative">
                <h6 class="fw-bold mb-3" style="color: #fff; font-size: 1.05rem; letter-spacing: 0.3px;">Smart Insights</h6>
                <p class="mb-1" id="insights-subtitle" style="color: rgba(255,255,255,0.55); font-size: 0.82rem;">Analyzing your activity...</p>
                <h2 class="fw-bold mb-0" id="insights-peak-label" style="color: #fff; font-size: 1.7rem; letter-spacing: -0.5px;">
                    <span class="placeholder-glow"><span class="placeholder col-6 rounded"></span></span>
                </h2>
                
                <!-- Bar Chart -->
                <div class="d-flex align-items-end gap-1 mt-4" style="height: 90px; padding-bottom: 0;" id="insights-bars-container">
                    <?php for ($i = 0; $i < 24; $i++): ?>
                    <div class="insights-bar" data-hour="<?= $i ?>" style="flex: 1; height: 4%; min-width: 0; background: rgba(255,255,255,0.18); border-radius: 3px 3px 0 0; transition: height 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), background 0.3s ease, box-shadow 0.3s ease;"></div>
                    <?php endfor; ?>
                </div>

                <!-- Time Labels -->
                <div class="d-flex justify-content-between mt-2 px-0" style="font-size: 0.65rem; color: rgba(255,255,255,0.3); font-style: italic; font-weight: 500;">
                    <span>12a</span>
                    <span style="margin-left: 20%;">6a</span>
                    <span>12p</span>
                    <span style="margin-right: 2%;">6p</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2" id="insights-footer" style="display: none !important;">
                    <span class="small" id="insights-active-days" style="font-size: 0.68rem; color: rgba(255,255,255,0.35);"></span>
                    <span class="small" id="insights-last-seen" style="font-size: 0.68rem; color: rgba(255,255,255,0.35);"></span>
                </div>
            </div>
        </div>

        <!-- Box 3: Upcoming Events -->
        <div class="card border-0 glass-card">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body d-flex align-items-center gap-2 mb-3" style="font-size: 0.9rem; letter-spacing: 0.5px; text-transform: uppercase;">
                    <i class='bx bx-calendar-event fs-5 opacity-75'></i> Upcoming Events
                </h6>
                <div class="py-2">
                    <p class="text-body-secondary small mb-2">No upcoming events</p>
                    <a href="#" class="text-decoration-none small text-info fw-medium hover-theme-text transition-all" style="font-size: 0.8rem;">
                        View all events <i class='bx bx-right-arrow-alt align-middle'></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Code Info Modal (Simplified IDE Launch) -->
<div class="modal fade" id="codeInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-body-tertiary" style="backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-body mb-0">Code Server Access</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2" id="codeModalLabName">Lab Name</span>
                </div>
                <div id="codeModalLoading" class="text-center py-5">
                    <div class="spinner-grow text-primary" role="status"></div>
                </div>
                <div id="codeModalOffline" class="text-center py-5" style="display: none;">
                    <i class='bx bx-power-off text-danger fs-1 mb-3'></i>
                    <h6 class="text-body fw-bold">Instance is Offline</h6>
                </div>
                <div id="codeModalContent" style="display: none;">
                    <div id="codeFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill" data-coreui-dismiss="modal">Dismiss</button>
                <div id="codeModalActionBtn"></div>
            </div>
        </div>
    </div>
</div>

<!-- Technical Connection Info Modal -->
<div class="modal fade" id="connectionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-body-tertiary" style="backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-body mb-0">Technical Connection Info</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2" id="modalLabName">Lab Name</span>
                </div>
                <div id="modalLoading" class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>
                <div id="modalOffline" class="text-center py-5" style="display: none;">
                    <i class='bx bx-server text-muted fs-1 mb-3'></i>
                    <h6 class="text-body fw-bold">Offline</h6>
                </div>
                <div id="modalContent" style="display: none;">
                    <div id="connectionFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill w-100" data-coreui-dismiss="modal">Close Details</button>
            </div>
        </div>
    </div>
</div>

<script src="workspace/js/connection_info.js"></script>
<script src="workspace/js/lab_code.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function startMetricsPolling(hash) {
        function fetchMetrics() {
            fetch(`/api/instance/stats?hash=${hash}`)
                .then(res => res.json())
                .then(stats => {
                    if (stats.CPUPerc) document.getElementById(`cpu-${hash}`).textContent = stats.CPUPerc;
                    if (stats.MemUsage) {
                        const usage = stats.MemUsage.split(' / ')[0];
                        document.getElementById(`mem-${hash}`).textContent = usage;
                    }
                    if (stats.Load1 !== undefined) {
                        const loadAvg = `${stats.Load1.toFixed(4)}, ${stats.Load5.toFixed(4)}, ${stats.Load15.toFixed(4)}`;
                        document.getElementById(`load-${hash}`).textContent = loadAvg;
                    }
                })
                .catch(() => {});
        }
        fetchMetrics();
        setInterval(fetchMetrics, 5000); 
    }

    // Initialize polling for all pre-rendered labs
    <?php foreach ($labsList as $lab): ?>
    startMetricsPolling('<?= $lab['hash'] ?>');
    <?php endforeach; ?>

    // ── Smart Insights: Fetch real activity data ──
    fetch('/api/dashboard/insights')
        .then(res => res.json())
        .then(data => {
            const subtitle = document.getElementById('insights-subtitle');
            const peakLabel = document.getElementById('insights-peak-label');
            const footer = document.getElementById('insights-footer');
            const activeDays = document.getElementById('insights-active-days');
            const lastSeen = document.getElementById('insights-last-seen');

            if (data.has_data) {
                subtitle.textContent = "You're most productive between";
                peakLabel.textContent = data.peak_label;

                // Animate bars with SNA-style colors
                const bars = document.querySelectorAll('.insights-bar');
                const barValues = data.bars || [];
                const maxVal = Math.max(...barValues);
                bars.forEach((bar, i) => {
                    const val = barValues[i] || 0;
                    const minHeight = val > 0 ? Math.max(val, 6) : 3;
                    setTimeout(() => {
                        bar.style.height = minHeight + '%';
                        if (val >= 70) {
                            // Peak hours — orange
                            bar.style.background = '#ffa502';
                            bar.style.boxShadow = '0 0 8px rgba(255, 165, 2, 0.35)';
                        } else if (val > 0) {
                            // Active hours — dark gray
                            bar.style.background = 'rgba(255,255,255,0.22)';
                            bar.style.boxShadow = 'none';
                        } else {
                            // Inactive — very subtle
                            bar.style.background = 'rgba(255,255,255,0.08)';
                        }
                    }, i * 25);
                });

                // Show footer stats
                if (data.active_days > 0) {
                    activeDays.textContent = data.active_days + ' active days';
                    footer.style.cssText = '';
                    footer.classList.add('d-flex');
                }
                if (data.last_seen) {
                    lastSeen.textContent = 'Last: ' + data.last_seen;
                }
            } else {
                subtitle.textContent = "Start exploring to see your insights";
                peakLabel.textContent = "No data yet";
                peakLabel.style.fontSize = '1.2rem';
                peakLabel.style.opacity = '0.4';
            }
        })
        .catch(() => {
            document.getElementById('insights-subtitle').textContent = "Start exploring to see your insights";
            document.getElementById('insights-peak-label').textContent = "No data yet";
        });
});
</script>