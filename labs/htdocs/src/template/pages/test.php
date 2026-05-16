<?php
/**
 * Test Card Template — Small Card Grid
 * Matches the premium glassmorphic dashboard card style
 */
$user = Session::getUser();
$userName = $user ? $user->getUserName() : "User";
$avatar = Session::getAvatar();

$cardItems = [
    ['icon' => 'bx-tachometer',       'title' => 'Dashboard',      'desc' => 'Your control center',   'url' => '/dashboard',   'color' => '#00d2ff'],
    ['icon' => 'bx-desktop',          'title' => 'Machine Labs',   'desc' => 'Deploy & practice',     'url' => '/labs',        'color' => '#27ae60'],
    ['icon' => 'bx-shield-quarter',   'title' => 'Challenge Labs', 'desc' => 'CTF & security',        'url' => '/challenges',  'color' => '#e74c3c'],
    ['icon' => 'bx-check-square',     'title' => 'Spot Quiz',      'desc' => 'Test your knowledge',   'url' => '/quiz',        'color' => '#f39c12'],
    ['icon' => 'bx-code-alt',         'title' => 'Code Arena',     'desc' => 'Solve & compete',       'url' => '#',            'color' => '#f1c40f'],
    ['icon' => 'bx-book-open',        'title' => 'Learn AI',       'desc' => 'AI-powered lessons',    'url' => '/learn',       'color' => '#3498db'],
    ['icon' => 'bx-map-alt',          'title' => 'Roadmaps',       'desc' => 'Learning paths',        'url' => '#',            'color' => '#16a085'],
    ['icon' => 'bx-chat',             'title' => 'Discussions',    'desc' => 'Ask & answer',          'url' => '#',            'color' => '#9b59b6'],
    ['icon' => 'bx-group',            'title' => 'Clubs',          'desc' => 'Find your people',      'url' => '#',            'color' => '#ff4757'],
    ['icon' => 'bx-calendar-event',   'title' => 'Events',         'desc' => 'Hackathons & more',     'url' => '#',            'color' => '#ffa502'],
    ['icon' => 'bx-list-ul',          'title' => 'Syllabus AI',    'desc' => 'Exam prep & study',     'url' => '#',            'color' => '#ff6b6b'],
    ['icon' => 'bx-devices',          'title' => 'My Devices',     'desc' => 'Manage connections',    'url' => '/devices',     'color' => '#1e90ff'],
    ['icon' => 'bx-bolt-circle',      'title' => 'Feeling Lucky',  'desc' => 'Personalized picks',    'url' => '#',            'color' => '#a55eea'],
    ['icon' => 'bx-bar-chart-alt-2',  'title' => 'Leaderboard',    'desc' => 'Rankings & stats',      'url' => '#',            'color' => '#f9ca24'],
    ['icon' => 'bx-flag',             'title' => 'Clans',          'desc' => 'Team up for CTF',       'url' => '#',            'color' => '#ff4757'],
];
?>

<div class="container-fluid min-vh-100 d-flex flex-column align-items-center justify-content-center py-5">
    <!-- User Header Section -->
    <div class="text-center mb-4 tc-fade-in">
        <div class="tc-avatar-wrapper mb-2 position-relative d-inline-block">
            <img src="<?= $avatar ?>" alt="Profile" class="rounded-circle shadow-lg border border-2 border-white border-opacity-10" style="width: 56px; height: 56px; object-fit: cover;">
            <div class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-dark" style="width: 12px; height: 12px;"></div>
        </div>
        <?php
        $fullName = $user->getFullName();
        $displayTitle = !empty($fullName) ? $fullName : $user->getUsername();
        ?>
        <h2 class="fw-bold text-white mb-0" style="font-size: 1.4rem; letter-spacing: -0.2px;">
            Still here? Respect, <span class="theme-text text-uppercase"><?= htmlspecialchars($displayTitle) ?></span>!
        </h2>
        <p class="text-white-50 small mb-1" style="font-size: 0.75rem; opacity: 0.8;">State of the art laboratories at the hands and homes of every learner!</p>
        <div class="d-flex justify-content-center gap-3 align-items-center mt-1">
            <span class="badge rounded-pill tc-plan-badge">
                <i class='bx bxs-star me-1' style="color: #f9ca24;"></i> Pro Plan
            </span>
            <a href="/logout" class="text-white-50 text-decoration-none tc-signout-link" style="font-size: 0.72rem;">
                <i class='bx bx-log-out-circle me-1'></i> Sign out
            </a>
        </div>
    </div>

    <!-- Card Grid -->
    <div class="row g-3 g-md-3 w-100 justify-content-center tc-grid-container" style="max-width: 1050px;">
        <?php foreach ($cardItems as $idx => $item): ?>
            <div class="col-6 col-md-4 col-lg-2-4">
                <a href="<?= $item['url'] ?>" class="card tc-card h-100 text-decoration-none" style="--tc-accent: <?= $item['color'] ?>; animation-delay: <?= $idx * 0.04 ?>s;">
                    <div class="card-body tc-card-body d-flex flex-column align-items-center text-center justify-content-center">
                        <div class="tc-icon-ring mb-2">
                            <i class='bx <?= $item['icon'] ?>' style="color: <?= $item['color'] ?>; font-size: 1.6rem;"></i>
                        </div>
                        <h5 class="tc-card-title"><?= $item['title'] ?></h5>
                        <p class="tc-card-desc"><?= $item['desc'] ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<link rel="stylesheet" href="/css/test-cards.css?v=<?= time() ?>">
