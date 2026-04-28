<?php
$parentTopic = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Mock data for generated quizzes - matching the reference
$quizzes = [
    [
        "id" => "1a79243e-4f74-44fb-a63e-11fea9aada7c",
        "title" => "Guarding the Digital Drawbridge on Your First Shift",
        "badges" => ["new", "easy", "firewalls", "network-basics"],
        "category" => "cybersecurity",
        "time" => "4 months ago",
        "zeal" => 15,
        "zap" => 2,
        "views" => 31
    ],
    [
        "id" => "2b80354f-5g85-55gc-b74f-22gfb9bbdb8d",
        "title" => "Trickling Through the Digital Downpour: A Stroll Through Firewalls and VPNs",
        "badges" => ["new", "easy", "firewalls", "network-traffic"],
        "category" => "cybersecurity",
        "time" => "1 year ago",
        "zeal" => 15,
        "zap" => 2,
        "views" => 6
    ],
    [
        "id" => "3c91465g-6h96-66hd-c85g-33hgc0ccdc9e",
        "title" => "Mastering the Cybersecurity Essentials: The Firewalls and VPNs Chronicle",
        "badges" => ["new", "easy", "firewall", "network-security"],
        "category" => "cybersecurity",
        "time" => "1 year ago",
        "zeal" => 15,
        "zap" => 2,
        "views" => 6
    ],
    [
        "id" => "4d02576h-7i07-77ie-d96h-44ihd1dde0af",
        "title" => "Setting Sail on the Cybersecurity Ocean: The Dynamic Duo of Firewalls and VPNs Revealed",
        "badges" => ["new", "easy", "firewall", "network-security"],
        "category" => "cybersecurity",
        "time" => "1 year ago",
        "zeal" => 10,
        "zap" => 2,
        "views" => 6
    ],
    [
        "id" => "5e13687i-8j18-88jf-e07i-55jie2eef1bg",
        "title" => "Ciphered Bridge and Digital Watchtowers: Embarking on the Journey of Firewalls and VPNs",
        "badges" => ["new", "easy", "firewall", "cybersecurity", "basics"],
        "category" => "cybersecurity",
        "time" => "2 years ago",
        "zeal" => 15,
        "zap" => 2,
        "views" => 9
    ],
    [
        "id" => "6f24798j-9k29-99kg-f18j-66kjf3ffg2ch",
        "title" => "Navigating the Cybersecurity Pathways: An Exploration of Firewalls and VPNs",
        "badges" => ["new", "easy", "network-security", "firewalls"],
        "category" => "vpn",
        "time" => "2 years ago",
        "zeal" => 15,
        "zap" => 2,
        "views" => 28
    ]
];
?>

<div class="quiz-start-view fade-in pb-5 lab-header-section">
    <div class="container-fluid px-4 pt-2">
        
        <!-- 1. Header Section -->
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <h1 class="fs-4 fw-bold theme-text mb-0"><?= $parentTopic['title'] ?></h1>
                <p class="text-body-secondary small mb-2 opacity-75"><?= $parentTopic['desc'] ?></p>
                <p class="x-small text-body-tertiary mb-0">
                    Quiz based on <span class="fw-bold text-emphasis"><?= $subtopic['title'] ?></span>, <?= $subtopic['desc'] ?>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end d-flex flex-column justify-content-center align-items-lg-end">
                <div class="d-flex flex-column align-items-end gap-2">
                    <button class="btn btn-spot-quiz rounded-pill px-4 py-2 fw-bold shadow-sm mb-1">
                        <i class="bx bxs-zap me-1"></i>Spot Quiz
                    </button>
                    <div class="difficulty-modes d-flex rounded-pill overflow-hidden shadow-sm border border-white border-opacity-10">
                        <button class="diff-btn btn-easy active">Easy</button>
                        <button class="diff-btn btn-normal">Normal</button>
                        <button class="diff-btn btn-hard">Hard</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Navigation Tabs -->
        <div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-2 flex-nowrap quiz-tabs-row">
            <button class="btn btn-pill-outline" onclick="window.history.back()">Topics</button>
            <div class="dropdown">
                <button class="btn btn-pill-active dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Recent 📂 in <?= $subtopic['title'] ?>
                </button>
            </div>
            <button class="btn btn-pill-outline">Trending</button>
            <div class="dropdown">
                <button class="btn btn-pill-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Completed ✅ in <?= $subtopic['title'] ?>
                </button>
            </div>
            <button class="btn btn-pill-outline">Leaderboard <span class="badge-new">New 🆕 ✨</span></button>
        </div>

        <hr class="border-secondary opacity-10 mb-4">

        <!-- 3. Quizzes Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
            <?php foreach ($quizzes as $q): ?>
            <div class="col">
                <div class="card quiz-challenge-card h-100">
                    <div class="card-body p-3 d-flex flex-column">
                        <h6 class="fw-bold mb-3 quiz-title-text lh-base"><?= $q['title'] ?></h6>
                        
                        <!-- Badges Row -->
                        <div class="d-flex flex-wrap gap-1 mb-4">
                            <?php foreach ($q['badges'] as $b): ?>
                                <span class="badge badge-quiz-tag"><?= $b ?></span>
                            <?php endforeach; ?>
                            <span class="badge badge-quiz-cat"><?= $q['category'] ?></span>
                            <span class="badge badge-quiz-time"><i class="bx bx-time-five me-1"></i><?= $q['time'] ?></span>
                        </div>

                        <!-- Stats Row -->
                        <div class="mt-auto d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-item">
                                    <span class="fw-bold"><?= $q['zeal'] ?></span> <i class="bx bxs-hot text-warning"></i>
                                </div>
                                <div class="stat-item">
                                    <span class="fw-bold"><?= $q['zap'] ?></span> <i class="bx bxs-zap text-info"></i>
                                </div>
                                <div class="stat-item">
                                    <span class="fw-bold"><?= $q['views'] ?></span> <i class="bx bxs-show text-white-50"></i>
                                </div>
                                <button class="btn btn-link p-0 text-white-50"><i class="bx bx-share-alt fs-5"></i></button>
                            </div>
                            <a href="/quiz/<?= $parentTopic['id'] ?>/recent/<?= $q['id'] ?>" class="btn btn-answer-quiz rounded-pill py-1 px-3 fw-bold">Answer Quiz</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Header Styling */
.theme-text { color: var(--bs-heading-color); }
.text-emphasis { color: var(--bs-emphasis-color); }

.btn-spot-quiz {
    background: #2eb857 !important;
    border: none !important;
    color: #fff !important;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}
.btn-spot-quiz:hover {
    background: #33cc66 !important;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 184, 87, 0.3) !important;
}

.difficulty-modes {
    background: rgba(var(--bs-emphasis-color-rgb), 0.05);
}
.diff-btn {
    border: none;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--bs-body-secondary);
    background: transparent;
    transition: all 0.2s ease;
}
.diff-btn:hover { color: var(--bs-emphasis-color); }
.diff-btn.active { background: rgba(var(--bs-emphasis-color-rgb), 0.1); color: var(--bs-emphasis-color); }

.btn-easy.active { border-bottom: 2px solid #2eb857; color: #2eb857; }
.btn-normal.active { border-bottom: 2px solid #f9b115; color: #f9b115; }
.btn-hard.active { border-bottom: 2px solid #e55353; color: #e55353; }

/* Tabs Styling */
.quiz-tabs-row .btn-pill-active {
    background: var(--bs-emphasis-color) !important;
    color: var(--bs-body-bg) !important;
    border-radius: 50px;
    padding: 6px 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
}
.quiz-tabs-row .btn-pill-outline {
    background: rgba(var(--bs-emphasis-color-rgb), 0.03);
    color: var(--bs-body-secondary);
    border: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.15);
    border-radius: 50px;
    padding: 6px 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Challenge Cards */
.quiz-challenge-card {
    background: linear-gradient(145deg, rgba(var(--bs-emphasis-color-rgb), 0.06) 0%, rgba(var(--bs-emphasis-color-rgb), 0.01) 100%) !important;
    border: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.1) !important;
    border-top: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.15) !important;
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-radius: 20px !important;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.quiz-challenge-card:hover {
    background: rgba(var(--bs-emphasis-color-rgb), 0.08) !important;
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.quiz-title-text {
    font-size: 0.95rem;
    color: var(--bs-heading-color);
}

.badge-quiz-tag {
    background: rgba(51, 153, 255, 0.1) !important;
    color: #3399ff !important;
    font-weight: 500;
    font-size: 0.65rem;
}
.badge-quiz-cat {
    background: rgba(102, 16, 242, 0.1) !important;
    color: #6610f2 !important;
    font-weight: 500;
    font-size: 0.65rem;
}
.badge-quiz-time {
    background: rgba(var(--bs-emphasis-color-rgb), 0.05) !important;
    color: var(--bs-body-tertiary) !important;
    font-weight: 400;
    font-size: 0.65rem;
}

.stat-item {
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.btn-answer-quiz {
    background: #2eb857 !important;
    border: none !important;
    color: #fff !important;
    font-size: 0.75rem;
    transition: all 0.3s ease;
}
.btn-answer-quiz:hover {
    background: #33cc66 !important;
    transform: scale(1.05);
}

.badge-new { color: #00d2ff; font-size: 0.75rem; margin-left: 4px; }
</style>
