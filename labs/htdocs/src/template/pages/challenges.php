<?php
/**
 * Challenge Labs Index Page Template
 * Location: /src/template/pages/challenges.php
 */
$user = Session::getUser();
$userId = (int)$user->getUserId();

// Accurate Challenge Data with proper Lab IDs and reliable image paths
$challenges = [
    [
        'lab_id'        => 'zombie-breakout',
        'name'          => 'Zombie Breakout',
        'points'        => 3258,
        'status'        => 'Instance Down',
        'image'         => '/assets/img/challenges/zombie.png',
        'ribbon_class'  => 'event-ended-ribbon',
        'ribbon_text1'  => 'Ended',
        'ribbon_text2'  => 'Yukthi Finale',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'medium',      'class' => 'bg-warning-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ],
    [
        'lab_id'        => 'shadow-partner',
        'name'          => 'The Shadow Partner',
        'points'        => 2232,
        'status'        => 'Instance Down',
        'image'         => '/assets/img/challenges/shadow.png',
        'ribbon_class'  => 'event-ended-ribbon',
        'ribbon_text1'  => 'Ended',
        'ribbon_text2'  => 'Yukthi Finale',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'hard',        'class' => 'bg-danger-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ],
    [
        'lab_id'        => 'backrooms',
        'name'          => 'Backrooms',
        'points'        => 1702,
        'status'        => 'Instance Down',
        'image'         => '/assets/img/challenges/backrooms.png',
        'ribbon_class'  => 'event-retired-ribbon',
        'ribbon_text1'  => 'Retired',
        'ribbon_text2'  => 'Yukthi Prelims',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'free',        'class' => 'bg-info-gradient'],
            ['text' => 'medium',      'class' => 'bg-warning-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ],
    [
        'lab_id'        => 'block-with-buster',
        'name'          => 'Block With Buster',
        'points'        => 3144,
        'status'        => 'Instance Down',
        'image'         => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop',
        'ribbon_class'  => 'event-ended-ribbon',
        'ribbon_text1'  => 'Ended',
        'ribbon_text2'  => 'Yukthi Finale',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'hard',        'class' => 'bg-danger-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ],
    [
        'lab_id'        => 'operation-warehouse',
        'name'          => 'Operation Warehouse',
        'points'        => 1990,
        'status'        => 'Instance Down',
        'image'         => 'https://images.unsplash.com/photo-1542744094-24638eff58bb?q=80&w=2070&auto=format&fit=crop',
        'ribbon_class'  => 'event-retired-ribbon',
        'ribbon_text1'  => 'Retired',
        'ribbon_text2'  => 'Yukthi Prelims',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'free',        'class' => 'bg-info-gradient'],
            ['text' => 'medium',      'class' => 'bg-warning-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ],
    [
        'lab_id'        => 'proxy-pipeline',
        'name'          => 'Proxy Pipeline',
        'points'        => 1961,
        'status'        => 'Instance Down',
        'image'         => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=2070&auto=format&fit=crop',
        'ribbon_class'  => 'event-ended-ribbon',
        'ribbon_text1'  => 'Ended',
        'ribbon_text2'  => 'Yukthi Prelims',
        'tags' => [
            ['text' => 'team',        'class' => 'bg-warning-gradient'],
            ['text' => 'easy',        'class' => 'bg-success-gradient'],
            ['text' => 'beta',        'class' => 'bg-primary-gradient'],
            ['text' => 'not running', 'class' => 'bg-danger-gradient']
        ]
    ]
];
?>

<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-start">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Challenge Labs</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                Challenge Labs offer a realm of cybersecurity and ethical hacking. Engage in real-world hacking scenarios, learning network security and penetration testing. Each lab is a stepping stone in your cybersecurity expertise journey.
            </p>
        </div>
        <div class="col-auto">
            <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-width: 140px;">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <span class="fw-bold theme-text" style="font-size: 2.2rem; line-height: 1;">2</span>
                    <span class="text-secondary opacity-50 ms-2" style="font-size: 1.1rem; font-weight: 500; margin-top: 8px;">/ <?= count($challenges) ?></span>
                </div>
                <?php 
                    $total = count($challenges);
                    $running = 2; // Specific to challenges context
                    $percent = ($running / $total) * 100;
                ?>
                <div class="progress bg-secondary bg-opacity-10 rounded-pill mb-2 w-100" style="height: 6px; max-width: 120px;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="text-secondary opacity-50 text-uppercase fw-bold ls-1" style="font-size: 9px; letter-spacing: 0.8px;">Running Labs</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mb-4">
    <div class="search-container position-relative" style="width: 100%; max-width: 400px;">
        <input type="text" class="form-control rounded-pill px-5 text-center py-2 bg-dark bg-opacity-50 border-white border-opacity-10 text-white" placeholder="Search Challenges...">
        <i class='bx bx-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50'></i>
    </div>
</div>

<div class="container-fluid px-0">
    <div class="row g-3 pb-5">
        <?php foreach ($challenges as $c): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card lab-card blur mb-2 shadow-sm">
                <!-- Cover Image -->
                <div class="position-relative">
                    <img class="card-img labs-index-cover" src="<?= $c['image'] ?>" alt="<?= $c['name'] ?>" onerror="this.src='https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop';">
                    
                    <!-- Overlay with Content -->
                    <div class="card-img-overlay d-flex flex-column justify-content-between p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="card-title text-white fw-bold mb-0 shadow-text"><?= htmlspecialchars($c['name']) ?></h4>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="text-white-50 small fw-bold"><?= $c['status'] ?></span>
                                    <div class="d-flex gap-2 ms-1">
                                        <i class='bx bx-info-circle text-white-50 pointer' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="View Details"></i>
                                        <i class='bx bx-copy text-white-50 pointer' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="Copy Lab ID"></i>
                                        <i class='bx bx-share-alt text-white-50 pointer' style="font-size: 0.9rem;" data-coreui-toggle="tooltip" title="Share Lab"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ribbon -->
                            <span class="<?= $c['ribbon_class'] ?> shadow-lg">
                                <span>
                                    <?= $c['ribbon_text1'] ?><br>
                                    <small class="text-nowrap"><?= $c['ribbon_text2'] ?></small>
                                </span>
                            </span>
                        </div>

                        <div class="d-flex justify-content-between align-items-end">
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($c['tags'] as $tag): ?>
                                    <span class="badge <?= $tag['class'] ?> rounded-pill px-2 py-1" style="font-size: 0.6rem;"><?= $tag['text'] ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="points-display text-end">
                                <div class="d-flex align-items-center gap-1 justify-content-end">
                                    <i class='bx bxs-hot text-white' style="font-size: 1.2rem;"></i>
                                    <h3 class="m-0 text-white fw-bold shadow-text" style="font-size: 1.6rem;"><?= $c['points'] ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons (Fused Pill) -->
                <div class="card-footer p-2 bg-dark bg-opacity-75 border-top border-white border-opacity-10">
                    <div class="btn-group w-100 fused-btn-group rounded-pill overflow-hidden" role="group">
                        <a class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                           href="/challenges/dashboard/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                            <i class='bx bxs-dashboard'></i> Dashboard
                        </a>
                        <a class="btn btn-danger d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                           href="/challenges/challenges/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                            <i class='bx bxs-bug'></i> Challenges
                        </a>
                        <a class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2 fw-bold" 
                           href="/challenges/leaderboard/<?= $c['lab_id'] ?>" style="font-size: 0.75rem; border: none;">
                            <i class='bx bx-line-chart'></i> Leaderboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.shadow-text {
    text-shadow: 0 2px 10px rgba(0,0,0,1);
}
.fused-btn-group {
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.fused-btn-group .btn {
    flex: 1;
    border-radius: 0 !important;
    transition: all 0.2s ease;
}
.fused-btn-group .btn:hover {
    filter: brightness(1.2);
}
.pointer {
    cursor: pointer;
}
</style>
