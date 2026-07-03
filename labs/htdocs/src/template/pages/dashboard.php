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

// 1.5 Fetch Challenge Labs
$username = $user->getUsername();
$challengeLabs = $db->challenge_instances->find(['username' => $username, 'status' => 'running'], ['sort' => ['created_at' => -1]]);

$challengesConfig = [];
$challengesConfigPath = __DIR__ . '/../../config/challenges.json';
if (file_exists($challengesConfigPath)) {
    $challengesConfig = json_decode(file_get_contents($challengesConfigPath), true) ?? [];
}

$challengeLabsList = [];
foreach ($challengeLabs as $clab) {
    $cId = $clab['challenge_id'] ?? '';
    $configKey = str_replace('_', '-', $cId);
    
    $cMeta = null;
    foreach ($challengesConfig as $c) {
        if (($c['lab_id'] ?? '') === $configKey) {
            $cMeta = $c;
            break;
        }
    }

    $cName = $cMeta['name'] ?? ucwords(str_replace('-', ' ', $configKey));
    
    // Find difficulty from tags
    $cDiff = 'Unknown';
    if (!empty($cMeta['tags']) && is_array($cMeta['tags'])) {
        foreach ($cMeta['tags'] as $tag) {
            $tText = strtolower($tag['text'] ?? '');
            if (in_array($tText, ['easy', 'medium', 'hard', 'extreme'])) {
                $cDiff = $tText;
                break;
            }
        }
    }

    // Map difficulty to a specific color
    $diffColor = '#2ed573'; // default green
    switch (strtolower($cDiff)) {
        case 'medium': $diffColor = '#ffa502'; break; // orange
        case 'hard':   $diffColor = '#ff4757'; break; // red
        case 'extreme':$diffColor = '#2f3542'; break; // dark
    }

    $challengeLabsList[] = [
        'name' => $cName,
        'difficulty' => $cDiff,
        'diffColor' => $diffColor,
        'image' => $cMeta['image'] ?? '/assets/Background_Img/challenges/shadow.png',
        'ip' => $clab['internal_ip'] ?? 'Unknown',
        'status' => $clab['status'] ?? 'unknown',
        'hash' => $clab['instance_hash'] ?? '',
        'type' => $cId
    ];
}

// 2. Fetch Domains
$domainCount = $db->domains->countDocuments(['user_id' => ['$in' => [(string)$userId, $userId]]]);
$domainsLimit = 20;
$domains = $db->domains->find(['user_id' => ['$in' => [(string)$userId, $userId]]], ['sort' => ['created_at' => -1]]);

// 3. Fetch User profile and stats dynamically
$userEmail = $user->getEmail();
$username = $user->getUsername();
$avatar = Session::getAvatar();

$userStats = $db->user_stats->findOne(['user_email' => $userEmail]);
$zeal = $userStats['zeal'] ?? 0;
$jolt = $userStats['jolt'] ?? 0;

$finishedQuizzes = $db->quiz_attempts->countDocuments(['user_email' => $userEmail]);

// 4. Dynamic Recent Activity Aggregator
$activitiesList = [];

// A. Fetch Quiz Attempts
$attempts = $db->quiz_attempts->find(
    ['user_email' => $userEmail],
    ['sort' => ['attempted_at' => -1], 'limit' => 5]
);
foreach ($attempts as $a) {
    $time = isset($a['attempted_at']) ? (int)$a['attempted_at'] : time();
    $activitiesList[] = [
        'timestamp' => $time,
        'icon' => "bx bx-award fs-6",
        'color' => "#f1c40f",
        'bg' => "rgba(241, 196, 15, 0.12)",
        'border' => "rgba(241, 196, 15, 0.25)",
        'text' => "Completed Quiz: scored <strong>" . $a['score'] . "/" . $a['total'] . "</strong>"
    ];
}

// B. Fetch Deployed Labs
$labs = $db->deployed_labs->find(
    ['user_id' => $userId],
    ['sort' => ['created_at' => -1], 'limit' => 5]
);
foreach ($labs as $l) {
    $time = isset($l['created_at']) ? (int)$l['created_at'] : time();
    $isStopped = isset($l['status']) && $l['status'] === 'stopped';
    
    $activitiesList[] = [
        'timestamp' => $time,
        'icon' => $isStopped ? "bx bx-stop-circle fs-6" : "bx bx-server fs-6",
        'color' => $isStopped ? "#636e72" : "#10ac84",
        'bg' => $isStopped ? "rgba(99, 110, 114, 0.12)" : "rgba(16, 172, 132, 0.12)",
        'border' => $isStopped ? "rgba(99, 110, 114, 0.25)" : "rgba(16, 172, 132, 0.25)",
        'text' => ($isStopped ? "Stopped" : "Deployed") . " <strong>" . ucfirst($l['lab_type'] ?? 'sandbox') . "</strong> Lab"
    ];
}

// C. Fetch Domains
$doms = $db->domains->find(
    ['user_id' => ['$in' => [(string)$userId, $userId]]],
    ['sort' => ['created_at' => -1], 'limit' => 5]
);
foreach ($doms as $d) {
    $time = isset($d['created_at']) ? (int)$d['created_at'] : time();
    $activitiesList[] = [
        'timestamp' => $time,
        'icon' => "bx bx-globe fs-6",
        'color' => "#2e86de",
        'bg' => "rgba(46, 134, 222, 0.12)",
        'border' => "rgba(46, 134, 222, 0.25)",
        'text' => "Mapped domain: <strong>" . htmlspecialchars($d['domain']) . "</strong>"
    ];
}

// D. Fetch SSH Keys
$keys = $db->ssh_keys->find(
    ['user_id' => ['$in' => [(string)$userId, $userId]]],
    ['sort' => ['created_at' => -1], 'limit' => 3]
);
foreach ($keys as $k) {
    $time = isset($k['created_at']) ? (int)$k['created_at'] : time();
    $activitiesList[] = [
        'timestamp' => $time,
        'icon' => "bx bx-key fs-6",
        'color' => "#e74c3c",
        'bg' => "rgba(231, 76, 60, 0.12)",
        'border' => "rgba(231, 76, 60, 0.25)",
        'text' => "Added SSH Key: <strong>" . htmlspecialchars($k['title']) . "</strong>"
    ];
}

// Sort all activities by timestamp DESC
usort($activitiesList, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Slice top 5
$activitiesList = array_slice($activitiesList, 0, 5);

// Helper function to format activity elapsed time
if (!function_exists('formatActivityTime')) {
    function formatActivityTime($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 0) return 'Just now';
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return round($diff / 60) . 'm ago';
        if ($diff < 86400) return round($diff / 3600) . 'h ago';
        if ($diff < 604800) return round($diff / 86400) . 'd ago';
        return date('M j', $timestamp);
    }
}

// Premium, highly motivational and situation-based dynamic greetings based on hour of the day
$hour = (int)date('H');
$quotes = [];

if ($hour >= 0 && $hour < 5) {
    // Late Night / Early Hours (12 AM - 5 AM)
    $quotes = [
        "The quiet hours suit you, {$username}!",
        "Deep focus mode active. Keep grinding, {$username}!",
        "While the world sleeps, the legends build. Keep it up, {$username}!",
        "Night owl grind is where the magic happens, {$username}!",
        "Quiet night, burning screen. Leveling up your craft, {$username}.",
        "The dark hours reveal the brightest minds. Keep going, {$username}!",
        "Silence around, brilliant code inside. You've got this, {$username}!",
        "Pushing boundaries while others dream. Keep building, {$username}!",
        "Late night commits lead to daylight success. Stay strong, {$username}!",
        "Under the cover of night, masters refine their craft, {$username}!"
    ];
} elseif ($hour >= 5 && $hour < 9) {
    // Early Morning (5 AM - 9 AM)
    $quotes = [
        "Rise and grind, {$username}! A fresh day to conquer.",
        "The early bird gets the code. Let's make today count, {$username}!",
        "Fresh mind, clean code. Good morning, {$username}!",
        "Start your day with intent, {$username}. You've got this!",
        "A new dawn, a new chance to exceed limits, {$username}!",
        "Fuel your ambition early today, {$username}!",
        "Wake up, level up. Today is your day, {$username}!",
        "Clean slate, infinite potential. Good morning, {$username}!",
        "Seize the day before it begins. Rise and shine, {$username}!",
        "Your future is written in the steps you take this morning, {$username}!"
    ];
} elseif ($hour >= 9 && $hour < 12) {
    // Morning (9 AM - 12 PM)
    $quotes = [
        "Peak productivity hour! Crush your goals, {$username}!",
        "Let's build something beautiful today, {$username}!",
        "Code compiled, goals set. Let's conquer the day, {$username}!",
        "Stay focused, stay inspired. You're doing amazing, {$username}!",
        "Hustle mode engaged. Stay sharp, {$username}!",
        "Success isn't given; it is earned line by line, {$username}!",
        "Maintain the energy, master your tasks today, {$username}!",
        "Unlocking peak performance. Let's win, {$username}!",
        "Your dedication is your superpower. Stay on track, {$username}!",
        "Focus on progress, not perfection. Keep moving, {$username}!"
    ];
} elseif ($hour >= 12 && $hour < 17) {
    // Afternoon (12 PM - 5 PM)
    $quotes = [
        "Keep the momentum going, {$username}! Halfway there.",
        "Stay fueled, stay sharp. You are doing great, {$username}!",
        "Afternoon energy check! Ready to crush the rest of the day, {$username}?",
        "One line of code at a time. Keep building, {$username}!",
        "No midday slump can stop a true legend. Power through, {$username}!",
        "Stay persistent. Great things take time and focus, {$username}!",
        "Unleash your drive. The afternoon is yours to own, {$username}!",
        "Success is built on afternoon consistency. Keep pushing, {$username}!",
        "Keep your eyes on the prize. You are closer than you think, {$username}!",
        "Let your passion drive your productivity this afternoon, {$username}!"
    ];
} elseif ($hour >= 17 && $hour < 21) {
    // Evening (5 PM - 9 PM)
    $quotes = [
        "Evening inspiration! Reflect on your wins today, {$username}.",
        "The day is winding down, but your potential is endless, {$username}!",
        "Sunset logic. Time to polish your masterworks, {$username}!",
        "Great work today, {$username}. Keep the flame burning!",
        "Passion projects come alive in the evening. Enjoy the build, {$username}!",
        "Relax the mind, but keep the spark alive, {$username}!",
        "Evening commits are the sweetest. Happy coding, {$username}!",
        "Reflect on how far you've come today. Proud of you, {$username}!",
        "Turn the sunset into your canvas. Keep creating, {$username}!",
        "Consistency in the evening builds character for a lifetime, {$username}!"
    ];
} else {
    // Night (9 PM - 12 AM)
    $quotes = [
        "One more pus, {$username}! You're almost there.",
        "Winding down or leveling up? Either way, you're a legend, {$username}!",
        "Under the stars, code shines the brightest, {$username}!",
        "Refining your craft under the night sky, {$username}. Keep going!",
        "Night mode: ON. Let your focus run free, {$username}.",
        "The night belongs to the creators. Keep designing, {$username}!",
        "End your day on a high note. One last check, {$username}!",
        "Fueling the midnight spark. Keep the dream alive, {$username}!",
        "Excellence is a habit, even under the night sky, {$username}!",
        "Rest well soon, but be proud of the grind tonight, {$username}!"
    ];
}

// Select a completely random quote on every page load
$greetingText = $quotes[array_rand($quotes)];
$greetingText = str_replace($username, '<span class="text-primary">' . htmlspecialchars($username) . '</span>', $greetingText);
?>

<div class="container-fluid px-0 pt-4">

    <!-- Top Row: Profile & Clan Cards -->
    <div class="row g-4 mb-4">
        <!-- Profile Banner -->
        <div class="col-12 col-xl-8">
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
                                <h5 class="fw-semibold mb-0 text-white-90" style="letter-spacing: -0.3px; font-size: 1.05rem;"><?= $greetingText ?></h5>
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
            <div class="card border-0 position-relative overflow-hidden h-100 clan-card" style="background: linear-gradient(180deg, rgba(0, 0, 0, 0.25) 0%, rgba(0, 0, 0, 0.8) 100%), url('/assets/Background_Img/clan_zero_byte.png') no-repeat center; background-size: cover; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                
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

    <!-- Continue Learning Mega Console -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 glass-card continue-learning-card">
                <div class="card-body p-4">
                    <!-- Title & Switch Tabs -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                        <div>
                            <h5 class="fw-bold mb-1 text-white" style="letter-spacing: -0.5px; font-size: 1.15rem;">Continue Learning</h5>
                            <p class="mb-0 text-white text-opacity-40 small" style="font-size: 0.72rem;">Pick up where you left off</p>
                        </div>
                        
                        <!-- Switch Tabs Selector -->
                        <div class="d-flex gap-2 flex-wrap continue-tab-switcher-container">
                            <button class="btn btn-sm rounded-pill px-3 py-1.5 fw-bold continue-tab-btn" 
                                    onclick="switchContinueTab('setup')" data-tab="setup">
                                <i class='bx bx-desktop me-1 align-middle' style="font-size: 0.9rem;"></i> Your Setup
                            </button>
                            <button class="btn btn-sm rounded-pill px-3 py-1.5 fw-bold continue-tab-btn active" 
                                    onclick="switchContinueTab('activity')" data-tab="activity">
                                <i class='bx bx-time-five me-1 align-middle' style="font-size: 0.9rem;"></i> Your Activity
                            </button>
                            <button class="btn btn-sm rounded-pill px-3 py-1.5 fw-bold continue-tab-btn" 
                                    onclick="switchContinueTab('recommended')" data-tab="recommended">
                                <i class='bx bx-star me-1 align-middle' style="font-size: 0.9rem;"></i> Recommended
                            </button>
                        </div>
                    </div>

                    <!-- Tab Contents Panes -->
                    <div id="continue-tab-panes">
                        
                        <!-- Pane 1: Your Activity -->
                        <div class="continue-tab-pane" id="continue-pane-activity">
                            <div class="row g-3">
                                <!-- Card 1 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 213, 115, 0.12); border: 1px solid rgba(46, 213, 115, 0.2);">
                                                <i class='bx bx-book text-success' style="font-size: 1.1rem; color: #2ed573 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" title="Secure Ports and Port Security" style="font-size: 0.78rem;">Secure Ports and Port Security...</h6>
                                                <div class="text-white text-opacity-40 mb-2" style="font-size: 0.65rem;">Beginner · 2/2 chapters</div>
                                                <div class="d-flex flex-wrap gap-1 mb-2.5">
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">ports</span>
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">port security</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.65rem;">
                                                    <span class="fw-bold text-success">20%</span>
                                                    <span class="text-white text-opacity-35">May 5</span>
                                                </div>
                                                <div class="progress" style="height: 3px; background: rgba(255, 255, 255, 0.08);">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(255, 165, 2, 0.12); border: 1px solid rgba(255, 165, 2, 0.2);">
                                                <i class='bx bx-code-alt text-warning' style="font-size: 1.1rem; color: #ffa502 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" title="Designing and Managing AI Learning" style="font-size: 0.78rem;">Designing and Managing AI Le...</h6>
                                                <div class="text-white text-opacity-40 mb-2" style="font-size: 0.65rem;">Intermediate · 7/4 chapters</div>
                                                <div class="d-flex flex-wrap gap-1 mb-2.5">
                                                    <span class="badge rounded bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">ai-assistant</span>
                                                    <span class="badge rounded bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">database-design</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.65rem;">
                                                    <span class="fw-bold text-warning">35%</span>
                                                    <span class="text-white text-opacity-35">Apr 25</span>
                                                </div>
                                                <div class="progress" style="height: 3px; background: rgba(255, 255, 255, 0.08);">
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 35%;" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 213, 115, 0.12); border: 1px solid rgba(46, 213, 115, 0.2);">
                                                <i class='bx bx-book text-success' style="font-size: 1.1rem; color: #2ed573 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" title="Secure Headers: A Beginner's Guide" style="font-size: 0.78rem;">Secure Headers: A Beginner's...</h6>
                                                <div class="text-white text-opacity-40 mb-2" style="font-size: 0.65rem;">Beginner · 5/3 chapters</div>
                                                <div class="d-flex flex-wrap gap-1 mb-2.5">
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">HTTP</span>
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">security headers</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.65rem;">
                                                    <span class="fw-bold text-success">63%</span>
                                                    <span class="text-white text-opacity-35">Apr 21</span>
                                                </div>
                                                <div class="progress" style="height: 3px; background: rgba(255, 255, 255, 0.08);">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: 63%;" aria-valuenow="63" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 213, 115, 0.12); border: 1px solid rgba(46, 213, 115, 0.2);">
                                                <i class='bx bx-book text-success' style="font-size: 1.1rem; color: #2ed573 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" title="Application Security Development" style="font-size: 0.78rem;">Application Security Develop...</h6>
                                                <div class="text-white text-opacity-40 mb-2" style="font-size: 0.65rem;">Beginner · 9/9 chapters</div>
                                                <div class="d-flex flex-wrap gap-1 mb-2.5">
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">appsec</span>
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">secure coding</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.65rem;">
                                                    <span class="fw-bold text-success">20%</span>
                                                    <span class="text-white text-opacity-35">Apr 20</span>
                                                </div>
                                                <div class="progress" style="height: 3px; background: rgba(255, 255, 255, 0.08);">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 5 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(255, 165, 2, 0.12); border: 1px solid rgba(255, 165, 2, 0.2);">
                                                <i class='bx bx-code-alt text-warning' style="font-size: 1.1rem; color: #ffa502 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" title="WebSockets, STOMP, Message Queues" style="font-size: 0.78rem;">WebSockets, STOMP, Message...</h6>
                                                <div class="text-white text-opacity-40 mb-2" style="font-size: 0.65rem;">Intermediate · 3/3 chapters</div>
                                                <div class="d-flex flex-wrap gap-1 mb-2.5">
                                                    <span class="badge rounded bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">WebSocket</span>
                                                    <span class="badge rounded bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2px 5px;">STOMP</span>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.65rem;">
                                                    <span class="fw-bold text-warning">20%</span>
                                                    <span class="text-white text-opacity-35">Apr 20</span>
                                                </div>
                                                <div class="progress" style="height: 3px; background: rgba(255, 255, 255, 0.08);">
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <a href="#" class="text-decoration-none small text-info fw-bold hover-theme-text transition-all" style="font-size: 0.75rem;">View all</a>
                            </div>
                        </div>

                        <!-- Pane 2: Your Setup (DYNAMIC!) -->
                        <div class="continue-tab-pane d-none" id="continue-pane-setup">
                            <div class="row g-4">
                                <!-- Connected Devices Card -->
                                <div class="col-12 col-md-6">
                                    <div class="card h-100 border-0 glass-card device-card">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-4">
                                                <div>
                                                    <h5 class="fw-bold mb-1 text-white d-flex align-items-center" style="letter-spacing: -0.3px; font-size: 1.05rem;">Connected Devices <i class="bx bx-info-circle ms-1.5 align-middle opacity-50" style="font-size: 0.9rem;" title="Active sandbox containers"></i></h5>
                                                    <p class="mb-0 text-white text-opacity-40 fw-medium" style="font-size: 0.85rem;">Sandbox Instances</p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="text-white fw-bold fs-3"><?= sprintf("%02d", $activeLabsCount) ?></span><span class="text-white text-opacity-35 small fw-semibold" style="font-size: 0.75rem;">/<?= $labsLimit ?></span>
                                                </div>
                                            </div>

                                            <div class="device-list pe-1" style="max-height: 155px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;">
                                                <?php if ($activeLabsCount > 0): ?>
                                                    <?php foreach ($labsList as $lab): ?>
                                                    <div class="d-flex align-items-center justify-content-between p-3 rounded active-lab-item-card">
                                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                                            <!-- Circular badge with dynamic server icon -->
                                                            <?php 
                                                                $bgMap = ['essentials' => '#e95420', 'minio' => '#2f3542', 'n8n' => '#ff6b81'];
                                                                $bgColor = $bgMap[$lab['type']] ?? '#2f3542';
                                                                $typeIconMap = ['essentials' => 'bxl-tux', 'minio' => 'bx-cube', 'n8n' => 'bx-git-repo-forked'];
                                                                $iconClass = $typeIconMap[$lab['type']] ?? 'bxl-ubuntu';
                                                            ?>
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: <?= $bgColor ?>; border: 1px solid rgba(255, 255, 255, 0.25); box-shadow: 0 3px 8px rgba(0,0,0,0.15);">
                                                                <i class='bx <?= $iconClass ?> text-white' style="font-size: 1.15rem;"></i>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <h6 class="fw-bold text-white mb-0 text-truncate" style="font-size: 0.85rem; letter-spacing: -0.2px;"><?= htmlspecialchars($lab['name']) ?></h6>
                                                                <span class="text-success fw-bold uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">ONLINE</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end d-flex align-items-center gap-3 flex-shrink-0">
                                                            <div>
                                                                <div class="text-white font-monospace fw-bold" style="font-size: 0.88rem; letter-spacing: 0.5px;"><?= htmlspecialchars($lab['ip']) ?></div>
                                                                <div class="text-white text-opacity-40 uppercase fw-bold" style="font-size: 0.55rem; letter-spacing: 0.5px;">INTERNAL IP</div>
                                                            </div>
                                                            <button class="btn btn-sm btn-link p-0 text-white text-opacity-40 hover-text-white transition-all" onclick="copyText('<?= htmlspecialchars(addslashes($lab['ip'])) ?>', 'IP copied!')">
                                                                <i class="bx bx-copy" style="font-size: 0.9rem;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <!-- Mock matching user screenshot standard setup -->
                                                    <div class="d-flex align-items-center justify-content-between p-3 rounded active-lab-item-card">
                                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                                            <!-- Circular badge with dynamic server icon -->
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: #e95420; border: 1px solid rgba(255, 255, 255, 0.25); box-shadow: 0 3px 8px rgba(0,0,0,0.15);">
                                                                <i class='bx bxl-tux text-white' style="font-size: 1.15rem;"></i>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <h6 class="fw-bold text-white mb-0 text-truncate" style="font-size: 0.85rem; letter-spacing: -0.2px;">Essentials Lab</h6>
                                                                <span class="text-success fw-bold uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">ONLINE</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end d-flex align-items-center gap-3 flex-shrink-0">
                                                            <div>
                                                                <div class="text-white font-monospace fw-bold" style="font-size: 0.88rem; letter-spacing: 0.5px;">172.30.0.28</div>
                                                                <div class="text-white text-opacity-40 uppercase fw-bold" style="font-size: 0.55rem; letter-spacing: 0.5px;">INTERNAL IP</div>
                                                            </div>
                                                            <button class="btn btn-sm btn-link p-0 text-white text-opacity-40 hover-text-white transition-all" onclick="copyText('172.30.0.28', 'IP copied!')">
                                                                <i class="bx bx-copy" style="font-size: 0.9rem;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Linked Domains Card -->
                                <div class="col-12 col-md-6">
                                    <div class="card h-100 border-0 glass-card domain-card">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-4">
                                                <div>
                                                    <h5 class="fw-bold mb-1 text-white" style="letter-spacing: -0.3px; font-size: 1.05rem;">Linked Domains</h5>
                                                    <p class="mb-0 text-white text-opacity-40 fw-medium" style="font-size: 0.85rem;">Active DNS Records</p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="text-success fw-bold fs-3"><?= sprintf("%02d", $domainCount) ?></span><span class="text-white text-opacity-35 small fw-semibold" style="font-size: 0.75rem;">/<?= $domainsLimit ?></span>
                                                </div>
                                            </div>

                                            <div class="domain-list pe-1" style="max-height: 155px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;">
                                                <?php if ($domainCount > 0): ?>
                                                    <?php foreach ($domains as $d): ?>
                                                    <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded active-lab-item-card" style="gap: 8px;">
                                                        <div class="d-flex align-items-center gap-2 min-w-0" style="flex: 1; overflow: hidden;">
                                                            <!-- Circular blue badge with globe icon (reduced) -->
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; background: rgba(46, 134, 222, 0.12); border: 1px solid rgba(46, 134, 222, 0.25);">
                                                                <i class='bx bx-globe' style="font-size: 0.85rem; color: #2e86de !important;"></i>
                                                            </div>
                                                            <div class="min-w-0" style="overflow: hidden;">
                                                                <div style="overflow-x: auto; white-space: nowrap; scrollbar-width: none; -ms-overflow-style: none;" title="<?= htmlspecialchars($d['domain']) ?>">
                                                                    <h6 class="fw-bold text-white mb-0" style="font-size: 0.75rem; letter-spacing: -0.2px; display: inline;"><?= htmlspecialchars($d['domain']) ?></h6>
                                                                </div>
                                                                <span class="fw-bold uppercase" style="color: #2e86de !important; font-size: 0.55rem; letter-spacing: 0.5px;">A RECORD</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end flex-shrink-0">
                                                            <div class="text-white font-monospace fw-bold" style="font-size: 0.78rem; letter-spacing: 0.3px;"><?= htmlspecialchars($d['ip_address'] ?? \TomLabs\Core\Env::get('SERVER_IP')) ?></div>
                                                            <div class="text-white text-opacity-40 uppercase fw-bold" style="font-size: 0.5rem; letter-spacing: 0.5px;">IP TARGET</div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <!-- Mock matching user screenshot standard setup -->
                                                    <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded active-lab-item-card" style="gap: 8px;">
                                                        <div class="d-flex align-items-center gap-2 min-w-0" style="flex: 1; overflow: hidden;">
                                                            <!-- Circular blue badge with globe icon (reduced) -->
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; background: rgba(46, 134, 222, 0.12); border: 1px solid rgba(46, 134, 222, 0.25);">
                                                                <i class='bx bx-globe' style="font-size: 0.85rem; color: #2e86de !important;"></i>
                                                            </div>
                                                            <div class="min-w-0" style="overflow: hidden;">
                                                                <div style="overflow-x: auto; white-space: nowrap; scrollbar-width: none; -ms-overflow-style: none;" title="sathish46.selfmade.fun">
                                                                    <h6 class="fw-bold text-white mb-0" style="font-size: 0.75rem; letter-spacing: -0.2px; display: inline;">sathish46.selfmade.fun</h6>
                                                                </div>
                                                                <span class="fw-bold uppercase" style="color: #2e86de !important; font-size: 0.55rem; letter-spacing: 0.5px;">A RECORD</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end flex-shrink-0">
                                                            <div class="text-white font-monospace fw-bold" style="font-size: 0.78rem; letter-spacing: 0.3px;"><?= htmlspecialchars(\TomLabs\Core\Env::get('SERVER_IP')) ?></div>
                                                            <div class="text-white text-opacity-40 uppercase fw-bold" style="font-size: 0.5rem; letter-spacing: 0.5px;">IP TARGET</div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded active-lab-item-card" style="gap: 8px;">
                                                        <div class="d-flex align-items-center gap-2 min-w-0" style="flex: 1; overflow: hidden;">
                                                            <!-- Circular blue badge with globe icon (reduced) -->
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; background: rgba(46, 134, 222, 0.12); border: 1px solid rgba(46, 134, 222, 0.25);">
                                                                <i class='bx bx-globe' style="font-size: 0.85rem; color: #2e86de !important;"></i>
                                                            </div>
                                                            <div class="min-w-0" style="overflow: hidden;">
                                                                <div style="overflow-x: auto; white-space: nowrap; scrollbar-width: none; -ms-overflow-style: none;" title="photogram.selfmade.monster">
                                                                    <h6 class="fw-bold text-white mb-0" style="font-size: 0.75rem; letter-spacing: -0.2px; display: inline;">photogram.selfmade.monster</h6>
                                                                </div>
                                                                <span class="fw-bold uppercase" style="color: #2e86de !important; font-size: 0.55rem; letter-spacing: 0.5px;">A RECORD</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end flex-shrink-0">
                                                            <div class="text-white font-monospace fw-bold" style="font-size: 0.78rem; letter-spacing: 0.3px;"><?= htmlspecialchars(\TomLabs\Core\Env::get('SERVER_IP')) ?></div>
                                                            <div class="text-white text-opacity-40 uppercase fw-bold" style="font-size: 0.5rem; letter-spacing: 0.5px;">IP TARGET</div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- Close Row 1 (Connected Devices & Linked Domains) -->

                            <div class="row g-3" style="margin-top: 1.5rem !important;"> <!-- Open Row 2 (Machine Labs & Challenge Labs) -->
                                <!-- Machine Labs Card -->
                                <div class="col-12 col-md-7 pe-md-1 mb-4 mb-md-0">
                                    <div class="card h-100 border-0 glass-card machine-labs-card">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold mb-0 d-flex align-items-center text-white" style="font-size: 0.95rem;">
                                                    Machine Labs 
                                                    <span class="badge bg-danger rounded-pill px-2 py-0.5 ms-2 uppercase fw-bold" style="font-size: 0.55rem; letter-spacing: 0.5px;">live</span> 
                                                </h6>
                                                <div class="small text-white text-opacity-40 fw-medium" style="font-size: 0.72rem;">
                                                    Limit: <?= $activeLabsCount ?>/<?= $labsLimit ?>
                                                </div>
                                            </div>

                                            <div id="machine-labs-container" class="d-flex flex-column px-0" style="max-height: 220px; overflow-y: auto;">
                                                <?php if (!empty($labsList)): ?>
                                                     <?php foreach ($labsList as $lab): ?>
                                                     <div class="p-3 mb-3 rounded-4 border transition-all hover-scale flex-shrink-0 active-lab-item-card" 
                                                          style="backdrop-filter: blur(6px);">
                                                         <div class="d-flex flex-column gap-3 w-100">
                                                             <!-- Row 1: Left Info & Right Stats -->
                                                             <div class="d-flex align-items-center justify-content-between w-100">
                                                                 <!-- Left: Logo + Lab Name & Badges -->
                                                                 <div class="d-flex align-items-center gap-2">
                                                                     <!-- Soft OS container -->
                                                                     <?php 
                                                                         $bgMap = ['essentials' => '#e95420', 'minio' => '#2f3542', 'n8n' => '#ff6b81', 'docker_lab' => '#2496ed'];
                                                                         $bgColor = $bgMap[$lab['type']] ?? '#2f3542';
                                                                         $typeIconMap = ['essentials' => 'bxl-tux', 'minio' => 'bx-cube', 'n8n' => 'bx-git-repo-forked', 'docker_lab' => 'bxl-docker'];
                                                                         $iconClass = $typeIconMap[$lab['type']] ?? 'bxl-ubuntu';
                                                                     ?>
                                                                     <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" 
                                                                          style="width: 32px; height: 32px; background: <?= $bgColor ?>; border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 3px 8px rgba(0,0,0,0.15);">
                                                                         <i class="bx <?= $iconClass ?> text-white" style="font-size: 1.1rem;"></i>
                                                                     </div>
                                                                     <!-- Lab details -->
                                                                     <div class="d-flex flex-column gap-0.5">
                                                                         <span class="text-white fw-bold" style="font-size: 0.76rem; letter-spacing: -0.15px; line-height: 1.2;"><?= $lab['name'] ?> Lab</span>
                                                                         <div class="d-flex gap-1 align-items-center">
                                                                             <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.38rem; padding: 1px 4.5px; background-color: #a55eea !important; border: 0; line-height: 1; text-transform: none !important;">beta</span>
                                                                             <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.38rem; padding: 1px 4.5px; background-color: #2ed573 !important; border: 0; color: #000 !important; line-height: 1; text-transform: none !important;"><?= strtolower($lab['status']) ?></span>
                                                                         </div>
                                                                     </div>
                                                                 </div>

                                                                 <!-- Right: Stats Row -->
                                                                 <div class="d-flex align-items-center gap-3 text-center">
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 34px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="cpu-<?= $lab['hash'] ?>" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">0.00%</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">CPU</div>
                                                                     </div>
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 46px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="mem-<?= $lab['hash'] ?>" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">0.00%</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">Memory</div>
                                                                     </div>
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 68px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="load-<?= $lab['hash'] ?>" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">0.00, 0.00, 0.00</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">Load</div>
                                                                     </div>
                                                                 </div>
                                                             </div>

                                                             <!-- Row 2: Right-aligned Buttons -->
                                                             <div class="d-flex justify-content-end w-100">
                                                                 <div class="d-flex gap-2 align-items-center">
                                                                     <a href="/labs/dashboard/<?= $lab['hash'] ?>" class="btn btn-sm btn-success rounded-pill d-flex align-items-center gap-1 transition-all hover-scale" style="padding: 3px 9px; background-color: #2ed573 !important; border: 0; color: #000; font-size: 0.58rem; font-weight: 700; line-height: 1; text-transform: none !important;" title="Dashboard">
                                                                         <i class='bx bx-grid-alt' style="font-size: 0.72rem;"></i> Dashboard
                                                                     </a>
                                                                     <button onclick="openCodeModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= strtolower($lab['status']) ?>')" class="btn btn-sm btn-primary border-0 rounded-pill d-flex align-items-center gap-1 transition-all hover-scale" style="padding: 3px 9px; background-color: #ffa502 !important; color: #000; font-size: 0.58rem; font-weight: 700; line-height: 1; text-transform: none !important;" title="Code">
                                                                         <i class='bx bx-code-alt' style="font-size: 0.72rem;"></i> Code
                                                                     </button>
                                                                     <button onclick="openConnectionModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= strtolower($lab['status']) ?>')" class="btn btn-sm btn-info border-0 rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale" style="width: 18px; height: 18px; padding: 0; background-color: #2e86de !important; color: #fff;" title="Connection Info">
                                                                         <i class='bx bx-info-circle' style="font-size: 0.72rem;"></i>
                                                                     </button>
                                                                 </div>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <?php endforeach; ?>
                                                                 <?php else: ?>
                                                    <!-- Fallback static preview matches screenshot exactly -->
                                                     <div class="p-3 mb-3 rounded-4 border transition-all hover-scale flex-shrink-0 active-lab-item-card" 
                                                          style="backdrop-filter: blur(2px);">
                                                         <div class="d-flex flex-column gap-3 w-100">
                                                             <!-- Row 1: Left Info & Right Stats -->
                                                             <div class="d-flex align-items-center justify-content-between w-100">
                                                                 <!-- Left: Logo + Lab Name & Badges -->
                                                                 <div class="d-flex align-items-center gap-2">
                                                                     <!-- Soft OS container -->
                                                                     <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" 
                                                                          style="width: 32px; height: 32px; background: #e95420; border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 3px 8px rgba(0,0,0,0.15);">
                                                                         <i class="bx bxl-tux text-white" style="font-size: 1.1rem;"></i>
                                                                     </div>
                                                                     <!-- Lab details -->
                                                                     <div class="d-flex flex-column gap-0.5">
                                                                         <span class="text-white fw-bold" style="font-size: 0.76rem; letter-spacing: -0.15px; line-height: 1.2;">Essentials Lab</span>
                                                                         <div class="d-flex gap-1 align-items-center">
                                                                             <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.38rem; padding: 1px 4.5px; background-color: #a55eea !important; border: 0; line-height: 1; text-transform: none !important;">beta</span>
                                                                             <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.38rem; padding: 1px 4.5px; background-color: #2ed573 !important; border: 0; color: #000 !important; line-height: 1; text-transform: none !important;">running</span>
                                                                         </div>
                                                                     </div>
                                                                 </div>

                                                                 <!-- Right: Stats Row -->
                                                                 <div class="d-flex align-items-center gap-3 text-center">
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 34px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="cpu-2dfa0d10c8ee99549594d584e85c92d3" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">0.02%</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">CPU</div>
                                                                     </div>
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 46px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="mem-2dfa0d10c8ee99549594d584e85c92d3" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">3.76%</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">Memory</div>
                                                                     </div>
                                                                     <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 68px;">
                                                                         <div class="fw-bold text-white text-center text-nowrap" id="load-2dfa0d10c8ee99549594d584e85c92d3" style="font-size: 0.65rem; line-height: 1.1; letter-spacing: -0.1px;">0.00, 0.00, 0.00</div>
                                                                         <div class="text-white text-opacity-40 fw-semibold text-center" style="font-size: 0.46rem; letter-spacing: 0.2px; margin-top: 1px;">Load</div>
                                                                     </div>
                                                                 </div>
                                                             </div>

                                                             <!-- Row 2: Right-aligned Buttons -->
                                                             <div class="d-flex justify-content-end w-100">
                                                                 <div class="d-flex gap-2 align-items-center">
                                                                     <a href="/labs/dashboard/2dfa0d10c8ee99549594d584e85c92d3" class="btn btn-sm btn-success rounded-pill d-flex align-items-center gap-1 transition-all hover-scale" style="padding: 3px 9px; background-color: #2ed573 !important; border: 0; color: #000; font-size: 0.58rem; font-weight: 700; line-height: 1; text-transform: none !important;" title="Dashboard">
                                                                         <i class='bx bx-grid-alt' style="font-size: 0.72rem;"></i> Dashboard
                                                                     </a>
                                                                     <button onclick="openCodeModal('2dfa0d10c8ee99549594d584e85c92d3', 'Essentials Lab', 'running')" class="btn btn-sm btn-primary border-0 rounded-pill d-flex align-items-center gap-1 transition-all hover-scale" style="padding: 3px 9px; background-color: #ffa502 !important; color: #000; font-size: 0.58rem; font-weight: 700; line-height: 1; text-transform: none !important;" title="Code">
                                                                         <i class='bx bx-code-alt' style="font-size: 0.72rem;"></i> Code
                                                                     </button>
                                                                     <button onclick="openConnectionModal('2dfa0d10c8ee99549594d584e85c92d3', 'Essentials Lab', 'running')" class="btn btn-sm btn-info border-0 rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale" style="width: 18px; height: 18px; padding: 0; background-color: #2e86de !important; color: #fff;" title="Connection Info">
                                                                         <i class='bx bx-info-circle' style="font-size: 0.72rem;"></i>
                                                                     </button>
                                                                 </div>
                                                             </div>
                                                         </div>
                                                     </div>
                                                 
                                                 <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Challenge Labs Card -->
                                <div class="col-12 col-md-5 ps-md-1">
                                    <div class="card h-100 border-0 glass-card">
                                        <div class="card-body p-2 px-3 d-flex flex-column" style="min-height: 220px;">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold mb-0 d-flex align-items-center text-white" style="font-size: 0.95rem;">
                                                    Challenge Labs 
                                                    <span class="badge bg-danger rounded-pill px-2 py-0.5 ms-2 uppercase fw-bold" style="font-size: 0.55rem; letter-spacing: 0.5px;">live</span> 
                                                </h6>
                                            </div>
                                            <div class="d-flex flex-column gap-2 flex-grow-1" style="max-height: 380px; overflow-y: auto; padding-right: 4px;">
                                                <?php if (!empty($challengeLabsList)): ?>
                                                    <?php foreach ($challengeLabsList as $clab): ?>
                                                        <div class="p-3 py-2 mb-3 border transition-all hover-scale flex-shrink-0 active-lab-item-card d-flex align-items-center justify-content-between" style="backdrop-filter: blur(6px); border-radius: 50px; background: rgba(255, 255, 255, 0.05); padding-right: 8px !important;">
                                                            
                                                            <!-- Left: Image and Info -->
                                                            <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                                                <!-- Avatar/Image -->
                                                                <div class="rounded-circle overflow-hidden flex-shrink-0" style="width: 36px; height: 36px; border: 2px solid rgba(255,255,255,0.1);">
                                                                    <img src="<?= htmlspecialchars($clab['image']) ?>" alt="Challenge" class="w-100 h-100 object-fit-cover" onerror="this.src='/assets/Background_Img/challenges/mystery.png';">
                                                                </div>
                                                                
                                                                <!-- Info -->
                                                                <div class="d-flex flex-column justify-content-center" style="min-width: 0;">
                                                                    <span class="text-white fw-bold" style="font-size: 0.72rem; letter-spacing: -0.15px; line-height: 1.15; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($clab['name']) ?>"><?= htmlspecialchars($clab['name']) ?></span>
                                                                    <div class="d-flex gap-1 align-items-center mt-1 flex-nowrap" style="white-space: nowrap;">
                                                                        <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.45rem; padding: 2px 5px; background-color: <?= $clab['diffColor'] ?> !important; color: #fff !important; line-height: 1; text-transform: lowercase !important; letter-spacing: 0.2px;"><?= htmlspecialchars(strtolower($clab['difficulty'])) ?></span>
                                                                        <span class="badge rounded-pill text-white fw-bold" style="font-size: 0.45rem; padding: 2px 5px; background-color: #2ed573 !important; color: #fff !important; line-height: 1; text-transform: lowercase !important; letter-spacing: 0.2px;"><?= htmlspecialchars(strtolower($clab['status'])) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Right: Buttons -->
                                                            <div class="d-flex gap-1 align-items-center flex-shrink-0 ms-1">
                                                                <!-- Dashboard Button (Green) -->
                                                                <a href="/challenges/dashboard/<?= $clab['hash'] ?>" class="btn rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale border-0" style="width: 30px; height: 30px; background-color: #2ed573 !important; color: #1e272e; padding: 0;" title="Dashboard">
                                                                    <i class='bx bxs-grid-alt' style="font-size: 1.1rem;"></i>
                                                                </a>
                                                                <!-- Challenge Button (Purple) -->
                                                                <a href="/challenges/challenges/<?= $clab['hash'] ?>" class="btn rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale border-0" style="width: 30px; height: 30px; background-color: #a55eea !important; color: #fff; padding: 0;" title="Challenge">
                                                                    <i class='bx bx-target-lock' style="font-size: 1.1rem;"></i>
                                                                </a>
                                                                <!-- Leaderboard Button (Blue) -->
                                                                <a href="/challenges/leaderboard/<?= $clab['hash'] ?>" class="btn rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale border-0" style="width: 30px; height: 30px; background-color: #0abde3 !important; color: #1e272e; padding: 0;" title="Leaderboard">
                                                                    <i class='bx bxs-trophy' style="font-size: 1.1rem;"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-center text-white text-opacity-35 py-4 small flex-grow-1 d-flex justify-content-center align-items-center" style="font-size: 0.78rem;">
                                                        No Challenge Labs Running
                                                    </div>
                                                    <button class="btn btn-sm rounded-pill fw-bold py-1.5 border border-purple border-opacity-30 text-purple hover-bg-purple transition-all align-self-center mt-auto" style="font-size: 0.72rem; color: #a55eea; border-color: rgba(165, 94, 234, 0.3) !important; background: rgba(165, 94, 234, 0.05); width: 100%; letter-spacing: 0.3px;">
                                                        Deploy a Challenge Lab
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pane 3: Recommended -->
                        <div class="continue-tab-pane d-none" id="continue-pane-recommended">
                            <div class="mb-3">
                                <h6 class="text-white text-opacity-40 fw-bold small mb-3 uppercase tracking-widest" style="font-size: 0.72rem;">Recommended For You</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Card 1 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(255, 165, 2, 0.12); border: 1px solid rgba(255, 165, 2, 0.2);">
                                                <i class='bx bx-code-alt text-warning' style="font-size: 1.1rem; color: #ffa502 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge rounded bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2.5px 6px; background-color: rgba(111, 50, 207, 0.1) !important; color: #a55eea !important; border-color: rgba(165, 94, 234, 0.2) !important;">Next Lesson</span>
                                                </div>
                                                <h6 class="fw-bold text-white small mb-1" style="line-height: 1.35; min-height: 38px; font-size: 0.78rem;">Introduction to Cybersecurity for Beginners</h6>
                                                <div class="text-white text-opacity-35" style="font-size: 0.65rem;">Beginner</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 2 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(255, 165, 2, 0.12); border: 1px solid rgba(255, 165, 2, 0.2);">
                                                <i class='bx bx-code-alt text-warning' style="font-size: 1.1rem; color: #ffa502 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge rounded bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2.5px 6px; background-color: rgba(111, 50, 207, 0.1) !important; color: #a55eea !important; border-color: rgba(165, 94, 234, 0.2) !important;">Next Lesson</span>
                                                </div>
                                                <h6 class="fw-bold text-white small mb-1 text-truncate" style="line-height: 1.35; min-height: 38px; font-size: 0.78rem;">Elite Ethical Hacking Roadmap: Beginner to...</h6>
                                                <div class="text-white text-opacity-35" style="font-size: 0.65rem;">Beginner</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 3 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 213, 115, 0.12); border: 1px solid rgba(46, 213, 115, 0.2);">
                                                <i class='bx bx-terminal text-success' style="font-size: 1.1rem; color: #2ed573 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2.5px 6px;">Practice</span>
                                                </div>
                                                <h6 class="fw-bold text-white small mb-1" style="line-height: 1.35; min-height: 38px; font-size: 0.78rem;">Calculate the sum of squares by caching co...</h6>
                                                <div class="text-white text-opacity-35" style="font-size: 0.65rem;">Easy</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 4 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 213, 115, 0.12); border: 1px solid rgba(46, 213, 115, 0.2);">
                                                <i class='bx bx-terminal text-success' style="font-size: 1.1rem; color: #2ed573 !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge rounded bg-success bg-opacity-10 text-success border border-success border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2.5px 6px;">Practice</span>
                                                </div>
                                                <h6 class="fw-bold text-white small mb-1" style="line-height: 1.35; min-height: 38px; font-size: 0.78rem;">Place stones strategically to cross river ...</h6>
                                                <div class="text-white text-opacity-35" style="font-size: 0.65rem;">Easy</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card 5 -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100 border-0 continue-activity-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05) !important; border-radius: 16px;">
                                        <div class="card-body p-3 d-flex gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: rgba(46, 134, 222, 0.12); border: 1px solid rgba(46, 134, 222, 0.2);">
                                                <i class='bx bx-chat text-info' style="font-size: 1.1rem; color: #2e86de !important;"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge rounded bg-info bg-opacity-10 text-info border border-info border-opacity-10 fw-semibold" style="font-size: 0.58rem; padding: 2.5px 6px; background-color: rgba(46, 134, 222, 0.1) !important; color: #2e86de !important; border-color: rgba(46, 134, 222, 0.2) !important;">Join Discussion</span>
                                                </div>
                                                <h6 class="fw-bold text-white small mb-1" style="line-height: 1.35; min-height: 38px; font-size: 0.78rem;">Community Discussions</h6>
                                                <div class="text-white text-opacity-35" style="font-size: 0.65rem;">Ask questions, share knowledge</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                    <?php if (!empty($activitiesList)): ?>
                        <?php foreach ($activitiesList as $act): ?>
                        <div class="d-flex align-items-start gap-3 small">
                            <div class="rounded-circle mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: <?= $act['bg'] ?> !important; border: 1px solid <?= $act['border'] ?> !important; flex-shrink: 0;">
                                <i class='<?= $act['icon'] ?>' style="color: <?= $act['color'] ?> !important; font-size: 0.85rem;"></i>
                            </div>
                            <div>
                                <span class="text-body fw-medium d-block mb-1" style="line-height: 1.35;"><?= $act['text'] ?></span>
                                <span class="text-body-secondary opacity-50" style="font-size: 0.7rem;"><?= formatActivityTime($act['timestamp']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-white text-opacity-30 small">
                            <i class="bx bx-history d-block fs-3 mb-1 opacity-20"></i>
                            No recent activity recorded
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Box 2: Smart Insights (Dynamic) -->
        <div class="card border-0 glass-card position-relative overflow-hidden" id="smart-insights-card">
            <div class="card-body p-3 pt-3 pb-2 position-relative">
                <h6 class="fw-bold mb-1.5 text-body" style="font-size: 0.95rem; letter-spacing: 0.3px;">Smart Insights</h6>
                <p class="mb-1 text-body-secondary" id="insights-subtitle" style="font-size: 0.78rem;">Analyzing your activity...</p>
                <h2 class="fw-bold mb-0 text-body" id="insights-peak-label" style="font-size: 1.5rem; letter-spacing: -0.5px;">
                    <span class="placeholder-glow"><span class="placeholder col-6 rounded"></span></span>
                </h2>
                
                <!-- Bar Chart -->
                <div class="d-flex align-items-end mt-2" style="height: 48px; gap: 1.5px; padding-bottom: 0;" id="insights-bars-container">
                    <?php for ($i = 0; $i < 24; $i++): ?>
                    <div class="insights-bar" data-hour="<?= $i ?>" style="flex: 1; height: 4%; min-width: 0; background: rgba(255,255,255,0.08); border-radius: 2px 2px 0 0; transition: height 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), background 0.3s ease, box-shadow 0.3s ease;"></div>
                    <?php endfor; ?>
                </div>

                <!-- Time Labels -->
                <div class="position-relative mt-1 w-100" style="height: 16px; font-size: 0.62rem; color: rgba(255,255,255,0.35); font-style: italic; font-weight: 500;">
                    <span class="position-absolute text-body-secondary" style="left: 0;">12a</span>
                    <span class="position-absolute text-body-secondary" style="left: 25%; transform: translateX(-50%);">6a</span>
                    <span class="position-absolute text-body-secondary" style="left: 50%; transform: translateX(-50%);">12p</span>
                    <span class="position-absolute text-body-secondary" style="left: 75%; transform: translateX(-50%);">6p</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-1" id="insights-footer" style="display: none !important;">
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
<script>
window.onPageLoad(function() {
    // Initialize premium lab metrics polling
    if (typeof window.initDashboardPolling === 'function') {
        window.initDashboardPolling(<?= json_encode(array_column($labsList, 'hash')) ?>);
    }
    // Initialize Smart Insights activity chart animation
    if (typeof window.initDashboardInsights === 'function') {
        window.initDashboardInsights();
    }
});
</script>