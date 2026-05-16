<?php
$user = Session::getUser();
$userName = $user ? $user->getUserName() : "User";
$avatar = Session::getAvatar();

// Load dynamic navigation items
$gridItems = include __DIR__ . '/../../config/home_nav.php';

// Greeting logic
$hour = date('H');
if ($hour < 12) $greeting = "Morning, legend";
elseif ($hour < 17) $greeting = "Afternoon, legend";
elseif ($hour < 21) $greeting = "Evening, legend";
else $greeting = "Winding down";
?>

<div class="container-fluid min-vh-100 d-flex flex-column align-items-center justify-content-center py-2 position-relative overflow-hidden">
    <!-- Premium Ambient Background Orbs -->
    <div class="scenery-orb-1"></div>
    <div class="scenery-orb-2"></div>

    <!-- User Header Section -->
    <div class="text-center mb-5 home-fade-in">
        <div class="home-avatar mb-3 position-relative d-inline-block">
            <img src="<?= $avatar ?>" alt="Profile" class="rounded-circle shadow-lg border border-2 border-white border-opacity-10" style="width: 64px; height: 64px; object-fit: cover;">
            <div class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-dark" style="width: 14px; height: 14px;"></div>
        </div>
        <?php 
        $fullName = $user->getFullName();
        $displayTitle = !empty($fullName) ? $fullName : $user->getUsername();
        ?>
        <h1 class="fw-bold mb-1" style="font-size: 1.6rem; letter-spacing: -0.5px; color: var(--cui-body-color);">
            <?= $greeting ?>, <span class="theme-text text-uppercase"><?= htmlspecialchars($displayTitle) ?></span>!
        </h1>
        <p class="small mb-3" style="font-size: 0.85rem; opacity: 0.7; color: var(--cui-body-color-muted);">State of the art laboratories at the hands and homes of every learner!</p>
        <div class="d-flex justify-content-center gap-3 align-items-center">
            <span class="badge rounded-pill home-plan-badge px-3 py-2">
                <i class='bx bxs-check-circle me-1' style="color: #f9ca24;"></i> Pro Plan
            </span>
            <a href="/logout" class="text-white-50 text-decoration-none home-signout-link small opacity-75">
                <i class='bx bx-log-out-circle me-1'></i> Sign out
            </a>
        </div>
    </div>

    <!-- Main Navigation Grid -->
    <div class="row g-4 w-100 justify-content-center home-grid-container" style="max-width: 1100px;">
        <?php foreach ($gridItems as $idx => $item): ?>
            <div class="col-6 col-md-4 col-lg-2-4">
                <a href="<?= $item['url'] ?>" class="card home-nav-card border-0 text-decoration-none h-100" style="--card-accent: <?= $item['color'] ?>; animation-delay: <?= $idx * 0.05 ?>s;">
                    <div class="card-body">
                        <div class="card-icon-ring">
                            <i class='bx <?= $item['icon'] ?>' style="color: <?= $item['color'] ?>;"></i>
                        </div>
                        <h5 class="card-title"><?= $item['title'] ?></h5>
                        <p class="card-text text-center"><?= $item['desc'] ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
