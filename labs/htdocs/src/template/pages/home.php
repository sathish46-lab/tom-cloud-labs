<?php
$user = Session::getUser();
$userName = $user ? $user->getUserName() : "User";
$avatar = Session::getAvatar();

$gridItems = [
    ['icon' => 'bx-tachometer', 'title' => 'Dashboard', 'desc' => 'Your control center', 'url' => '/dashboard', 'color' => '#00d2ff'],
    ['icon' => 'bx-monitor', 'title' => 'Machine Labs', 'desc' => 'Deploy & practice', 'url' => '/labs', 'color' => '#27ae60'],
    ['icon' => 'bx-shield-quarter', 'title' => 'Challenge Labs', 'desc' => 'CTF & security', 'url' => '/challenges', 'color' => '#e74c3c'],
    ['icon' => 'bx-check-square', 'title' => 'Spot Quiz', 'desc' => 'Test your knowledge', 'url' => '#', 'color' => '#8e44ad'],
    ['icon' => 'bx-code-alt', 'title' => 'Code Arena', 'desc' => 'Solve & compete', 'url' => '#', 'color' => '#f1c40f'],
    ['icon' => 'bx-book-open', 'title' => 'Learn AI', 'desc' => 'AI-powered lessons', 'url' => '/learn', 'color' => '#3498db'],
    ['icon' => 'bx-map-alt', 'title' => 'Roadmaps', 'desc' => 'Learning paths', 'url' => '#', 'color' => '#16a085'],
    ['icon' => 'bx-chat', 'title' => 'Discussions', 'desc' => 'Ask & answer', 'url' => '#', 'color' => '#9b59b6'],
    ['icon' => 'bx-group', 'title' => 'Clubs', 'desc' => 'Find your people', 'url' => '#', 'color' => '#ff4757'],
    ['icon' => 'bx-calendar-event', 'title' => 'Events', 'desc' => 'Hackathons & more', 'url' => '#', 'color' => '#ffa502'],
    ['icon' => 'bx-list-ul', 'title' => 'Syllabus AI', 'desc' => 'Exam prep & study', 'url' => '#', 'color' => '#ff6b6b'],
    ['icon' => 'bx-devices', 'title' => 'My Devices', 'desc' => 'Manage connections', 'url' => '/devices', 'color' => '#1e90ff'],
    ['icon' => 'bx-bolt-circle', 'title' => 'Feeling Lucky', 'desc' => 'Personalized picks', 'url' => '#', 'color' => '#a55eea'],
    ['icon' => 'bx-bar-chart-alt-2', 'title' => 'Leaderboard', 'desc' => 'Rankings & stats', 'url' => '#', 'color' => '#f9ca24'],
    ['icon' => 'bx-flag', 'title' => 'Clans', 'desc' => 'Team up for CTF', 'url' => '#', 'color' => '#ff4757'],
];
?>

<div class="container-fluid min-vh-100 d-flex flex-column align-items-center justify-content-center py-5">
    <!-- User Header Section -->
    <div class="text-center mb-4 fade-in">
        <div class="avatar-wrapper mb-2 position-relative d-inline-block">
            <img src="<?= $avatar ?>" alt="Profile" class="rounded-circle shadow-lg border border-2 border-white border-opacity-10" style="width: 56px; height: 56px; object-fit: cover;">
            <div class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-dark" style="width: 12px; height: 12px;"></div>
        </div>
        <?php 
        $fullName = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
        $displayTitle = !empty($fullName) ? $fullName : $userName;
        ?>
        <h2 class="fw-bold text-white mb-0" style="font-size: 1.4rem; letter-spacing: -0.2px;">Welcome back, <span class="theme-text text-uppercase"><?= htmlspecialchars($displayTitle) ?></span></h2>
        <p class="text-white-50 small mb-2" style="font-size: 0.75rem; opacity: 0.8;">State of the art laboratories at the hands and homes of every learner!</p>
        <a href="/logout" class="btn btn-link text-white-50 text-decoration-none p-0" style="font-size: 0.7rem;">
            <i class='bx bx-log-out-circle me-1'></i> Sign out
        </a>
    </div>

    <!-- Main Navigation Grid -->
    <div class="row g-3 g-md-3 w-100 justify-content-center home-grid-container" style="max-width: 1050px;">
        <?php foreach ($gridItems as $item): ?>
            <div class="col-6 col-md-4 col-lg-2-4">
                <a href="<?= $item['url'] ?>" class="card h-100 border-0 glass-card text-decoration-none transition-all home-nav-card">
                    <div class="card-body p-3 d-flex flex-column align-items-center text-center justify-content-center">
                        <div class="icon-wrapper mb-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class='bx <?= $item['icon'] ?> fs-3' style="color: <?= $item['color'] ?>;"></i>
                        </div>
                        <h5 class="fw-bold text-white mb-1" style="font-size: 0.9rem; letter-spacing: -0.1px;"><?= $item['title'] ?></h5>
                        <p class="text-white-50 m-0" style="font-size: 0.65rem; line-height: 1.2; opacity: 0.6;"><?= $item['desc'] ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* 5-column layout for Large screens */
@media (min-width: 992px) {
    .col-lg-2-4 {
        flex: 0 0 auto;
        width: 20%;
    }
}

.home-nav-card {
    min-height: 145px;
    background: rgba(15, 25, 45, 0.5) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 18px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.home-nav-card:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    border-color: rgba(100, 200, 255, 0.4) !important;
    transform: translateY(-6px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5) !important;
}

.home-nav-card .icon-wrapper {
    transition: transform 0.3s ease;
}

.home-nav-card:hover .icon-wrapper {
    transform: scale(1.1);
}

.fade-in {
    animation: fadeInUp 0.6s ease-out forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
