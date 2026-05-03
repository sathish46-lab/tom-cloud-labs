<div class="quiz-container lab-header-section">
    <!-- 1. Hero Section -->
    <div class="d-flex flex-column align-items-center text-center py-2 mb-3 w-100">
        <h1 class="display-4 fw-bold theme-text mb-2 d-flex align-items-center justify-content-center">
            <i class="bx bxs-zap text-warning me-2"></i>Spot Quiz
        </h1>
        <p class="text-body-secondary fs-5 mb-4">Quick 5-question challenges • Earn rewards • Boost your skills</p>
        
        <!-- Mode Pills -->
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-3 max-width-800">
            <span class="badge border border-primary text-primary bg-transparent px-3 py-2 rounded-pill">
                <i class="bx bx-target-lock me-1"></i> Tech Master
            </span>
            <span class="badge border border-warning text-warning bg-transparent px-3 py-2 rounded-pill">
                <i class="bx bx-timer me-1"></i> Time Trial
            </span>
            <span class="badge border border-danger text-danger bg-transparent px-3 py-2 rounded-pill">
                <i class="bx bxs-flame me-1"></i> Rapid Fire
            </span>
            <span class="badge border border-info text-info bg-transparent px-3 py-2 rounded-pill">
                <i class="bx bx-bolt-circle me-1"></i> Blitz
            </span>
            <span class="badge border border-success text-success bg-transparent px-3 py-2 rounded-pill">
                <i class="bx bx-shield-quarter me-1"></i> Endurance
            </span>
            <span class="badge border border-secondary text-body-tertiary bg-transparent px-3 py-2 rounded-pill opacity-75">
                <i class="bx bx-graduation-cap me-1"></i> Self Evaluation
            </span>
        </div>
        <small class="text-body-tertiary opacity-50">AI can make mistakes. Found an issue? <a href="#" class="text-info text-decoration-none">Report it</a></small>
    </div>

    <!-- 2. Tracked Achievements Section -->
    <div class="container-fluid px-lg-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h4 class="fw-bold theme-text m-0">Tracked Achievements</h4>
            <a href="/achievements" class="btn btn-sm btn-outline-info rounded-pill px-3">View All</a>
        </div>

        <div class="row g-3 mb-2">
            <!-- Achievement Card 1 -->
            <div class="col-md-3">
                <div class="card glass-card quiz-glass-card h-100 transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="achievement-icon bg-primary bg-opacity-10 rounded-3 p-2">
                                <i class="bx bxs-trophy text-primary fs-3"></i>
                            </div>
                            <div>
                                <span class="badge bg-secondary-subtle text-body-secondary text-uppercase x-small mb-1">Common</span>
                                <h6 class="fw-bold m-0 card-title-text">Normal Sampler</h6>
                            </div>
                        </div>
                        <p class="text-body-secondary x-small mb-3">Complete 1 normal quiz in 10 different topics. Normal difficulty.</p>
                        <div class="d-flex justify-content-between x-small text-body-tertiary mb-1">
                            <span>9/10 topics (Normal)</span>
                            <span>90%</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px; background: rgba(var(--bs-emphasis-color-rgb), 0.05);">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 90%;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <div class="d-flex gap-2">
                                <span class="text-warning x-small"><i class="bx bxs-hot me-1"></i>30</span>
                                <span class="text-info x-small"><i class="bx bxs-zap me-1"></i>15</span>
                            </div>
                            <button class="btn btn-sm btn-info rounded-pill px-3 py-0 x-small text-white">Track</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievement Card 2 (Rare) -->
            <div class="col-md-3">
                <div class="card glass-card quiz-glass-card h-100 transition-all">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="achievement-icon bg-info bg-opacity-10 rounded-3 p-2">
                                <i class="bx bxs-medal text-info fs-3"></i>
                            </div>
                            <div>
                                <span class="badge bg-primary-subtle text-primary text-uppercase x-small mb-1">Rare</span>
                                <h6 class="fw-bold m-0 card-title-text">Normal Decathlon</h6>
                            </div>
                        </div>
                        <p class="text-body-secondary x-small mb-3">Complete 10 normal quizzes in 10 different topics. Normal diff...</p>
                        <div class="d-flex justify-content-between x-small text-body-tertiary mb-1">
                            <span>9/10 topics (Normal)</span>
                            <span>90%</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px; background: rgba(var(--bs-emphasis-color-rgb), 0.05);">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 90%;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <div class="d-flex gap-2">
                                <span class="text-warning x-small"><i class="bx bxs-hot me-1"></i>150</span>
                                <span class="text-info x-small"><i class="bx bxs-zap me-1"></i>75</span>
                            </div>
                            <button class="btn btn-sm btn-info rounded-pill px-3 py-0 x-small text-white">Track</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overall Stats Card -->
            <div class="col-md-4">
                <div class="card glass-card quiz-glass-card border-info border-opacity-25 h-100 transition-all">
                    <div class="card-body d-flex flex-column justify-content-center text-center p-3">
                        <h2 class="display-6 fw-bold text-info mb-0">62 / 128</h2>
                        <p class="text-body-secondary small mb-3">Collected</p>
                        <div class="px-4 mb-3">
                            <div class="progress" style="height: 4px; background: rgba(var(--bs-emphasis-color-rgb), 0.05);">
                                <div class="progress-bar bg-info" role="progressbar" style="width: 48%;"></div>
                            </div>
                            <small class="text-body-tertiary mt-1 d-block text-uppercase letter-spacing-1">48% Complete</small>
                        </div>
                        <div class="d-flex justify-content-center gap-4 mb-3">
                            <span class="text-warning"><i class="bx bxs-hot me-1"></i>9,541</span>
                            <span class="text-info"><i class="bx bxs-zap me-1"></i>4,764</span>
                        </div>
                        <button class="btn btn-outline-info rounded-pill w-100 py-1 small">View Collected</button>
                    </div>
                </div>
            </div>

            <!-- Ready to Collect Card -->
            <div class="col-md-2">
                <div class="card glass-card quiz-glass-card border-warning border-opacity-25 h-100 transition-all">
                    <div class="card-body d-flex flex-column text-center p-3">
                        <h2 class="display-5 fw-bold text-warning mb-0">4</h2>
                        <p class="text-body-secondary small mb-auto">Ready to Collect</p>
                        <div class="mt-3">
                            <p class="text-body-tertiary x-small mb-2">Rewards waiting!</p>
                            <button class="btn btn-warning rounded-pill w-100 py-1 small fw-bold">Collect Rewards</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="opacity-10 my-2">

        <!-- 3. Dynamic Learning Sections from MongoDB -->
        <?php 
        use TomLabs\Labs\Quiz;
        $sections = Quiz::getAllCategories();
        
        foreach ($sections as $sectionName => $categories): ?>
        <div class="mb-5">
            <h4 class="fw-bold theme-text mb-3"><?= $sectionName ?></h4>
            <div class="row row-cols-1 row-cols-md-3 g-3">
                <?php foreach ($categories as $cat): ?>
                <div class="col">
                    <div class="card glass-card quiz-item-card h-100 transition-all" onclick="startQuiz('<?= $cat['hash'] ?? $cat['_id'] ?>')">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-2 card-title-text"><?= $cat['title'] ?></h5>
                            <p class="text-body-secondary small mb-0 opacity-75"><?= $cat['desc'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function startQuiz(id) {
    console.log("Navigating to Topic ID:", id);
    window.location.href = "/quiz/" + id;
}
</script>

