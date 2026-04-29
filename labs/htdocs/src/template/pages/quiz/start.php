<?php
use TomLabs\Labs\Quiz;

$parentTopic = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Fetch real quizzes from MongoDB
$quizzes = Quiz::getRecentForSubtopic($subtopic['_id'], 8);
?>

<div class="fade-in pb-5 lab-header-section">
    <div class="evaluation-bg"></div>
    <div class="container-fluid px-4 pt-2">
        
        <!-- 1. Header Section -->
        <div class="row g-3 mb-4 position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <h1 class="fs-4 fw-bold theme-text mb-0"><?= $parentTopic['title'] ?></h1>
                <p class="text-body-secondary small mb-2 opacity-75"><?= $parentTopic['desc'] ?></p>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-quiz-tag px-2 py-1 fw-bold rounded-pill border border-info border-opacity-10">
                        Subtopic: <?= $subtopic['title'] ?>
                    </span>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end d-flex flex-column justify-content-center align-items-lg-end">
                <div class="d-flex flex-column align-items-end gap-2">
                    <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm transition-all" onclick="triggerGeneration()">
                        <i class="bx bxs-zap me-1"></i>Spot Quiz
                    </button>
                    <div id="difficultySelector" class="difficulty-modes">
                        <button class="diff-btn btn-easy" data-diff="easy">Easy</button>
                        <button class="diff-btn btn-normal active" data-diff="normal">Normal</button>
                        <button class="diff-btn btn-hard" data-diff="hard">Hard</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Navigation Tabs -->
        <div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-2 flex-nowrap quiz-tabs-row position-relative" style="z-index: 2;">
            <button class="btn btn-pill-outline" onclick="window.history.back()">
                <i class="bx bx-left-arrow-alt me-1"></i>Topics
            </button>
            <button class="btn btn-pill-active">
                Recent 📂 in <?= $subtopic['title'] ?>
            </button>
            <button class="btn btn-pill-outline">Trending</button>
            <button class="btn btn-pill-outline">Completed ✅</button>
            <button class="btn btn-pill-outline">Leaderboard <span class="badge-new">New 🆕 ✨</span></button>
        </div>

        <hr class="border-secondary opacity-10 mb-4 position-relative" style="z-index: 2;">

        <!-- 3. Quizzes Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 position-relative" style="z-index: 2;">
            <?php if (empty($quizzes)): ?>
                <div class="col-12 text-center py-5">
                    <div class="card quiz-glass-card p-5 border-dashed">
                        <i class="bx bxs-inbox fs-1 theme-text opacity-25 mb-3"></i>
                        <h5 class="fw-bold theme-text">No Quizzes Available Yet</h5>
                        <p class="text-body-secondary small mb-4">Be the first to generate a professional challenge for this topic!</p>
                        <button class="btn btn-success rounded-pill px-4 fw-bold" onclick="triggerGeneration()">
                            Generate Now with AI
                        </button>
                    </div>
                </div>
            <?php else: foreach ($quizzes as $q): ?>
            <div class="col">
                <div class="card quiz-glass-card h-100 border-0 shadow-sm glass-effect-premium">
                    <div class="card-body d-flex flex-column p-4">
                        <!-- Title -->
                        <h6 class="card-title-text fw-bold mb-2" style="min-height: 2.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= $q['title'] ?>
                        </h6>

                        <!-- Description -->
                        <p class="text-body-secondary x-small mb-3 opacity-75" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8rem;">
                            <?= $q['desc'] ?? "Explore the intricate mechanics of this domain through our AI-curated challenge." ?>
                        </p>
                        
                        <!-- Badges Row -->
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <span class="badge badge-quiz-tag rounded-pill px-2">new ✨</span>
                            <span class="badge badge-quiz-cat rounded-pill px-2"><?= strtoupper($q['difficulty']) ?></span>
                            
                            <?php 
                            $tags = (isset($q['tags']) && is_array($q['tags'])) ? $q['tags'] : [$parentTopic['title'] ?? 'Cybersecurity', 'tech'];
                            foreach (array_slice($tags, 0, 3) as $tag): 
                            ?>
                                <span class="badge badge-tag-dynamic rounded-pill px-2"><?= strtolower($tag) ?></span>
                            <?php endforeach; ?>

                            <div class="ms-auto">
                                <span class="badge badge-quiz-time rounded-pill px-2">
                                    <i class="bx bx-time-five"></i>
                                    <?php try { echo date('M j', (int)$q['created_at']); } catch (\Throwable $t) { echo "recent"; } ?>
                                </span>
                            </div>
                        </div>

                        <!-- Stats Footer -->
                        <div class="mt-auto pt-3 border-top border-white border-opacity-10">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-item">
                                        <span class="fw-bold"><?= $q['points_per_correct'] ?></span>
                                        <i class="bx bxs-hot text-danger"></i>
                                    </div>
                                    <div class="stat-item">
                                        <span class="fw-bold">2</span>
                                        <i class="bx bxs-zap text-warning"></i>
                                    </div>
                                    <div class="stat-item">
                                        <span class="fw-bold">16</span>
                                        <i class="bx bxs-show text-info"></i>
                                    </div>
                                </div>
                                <a href="/quiz/v/<?= $q['hash'] ?>" class="btn btn-success btn-sm rounded-pill fw-bold px-3">
                                    Answer Quiz
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Spot Quiz Modal -->
<div class="modal fade" id="spotQuizModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content quiz-glass-card border-0 shadow-lg overflow-hidden" style="background: rgba(var(--cui-body-bg-rgb), 0.95); backdrop-filter: blur(20px);">
            <div class="modal-body p-5 text-center">
                <div class="ai-loader-container mb-4">
                    <div class="ai-pulse"></div>
                    <i class="bx bxs-brain ai-icon"></i>
                </div>
                
                <h4 class="fw-bold theme-text mb-2">Spot Quiz ⚡️</h4>
                <p class="text-body-secondary small mb-4 px-3" id="modalSubtext">
                    Generating your professional quiz challenge...
                </p>

                <div class="progress rounded-pill bg-white bg-opacity-10 mb-4" style="height: 10px;">
                    <div id="genProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" style="width: 5%"></div>
                </div>

                <div class="d-flex justify-content-between x-small theme-text opacity-75 px-1">
                    <span id="genStatus">Initializing...</span>
                    <span id="genPercent">5%</span>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 d-none" id="modalActions">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 1. View Refinement */
.quiz-start-view.lab-header-section {
    background: transparent !important;
    border-bottom: none !important;
    min-height: 80vh;
}

/* 2. Premium Glass Card Effect */
.glass-effect-premium {
    background: rgba(255, 255, 255, 0.04) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2) !important;
    border-radius: 24px !important;
}

.glass-effect-premium:hover {
    background: rgba(255, 255, 255, 0.07) !important;
    border-color: rgba(var(--cui-info-rgb), 0.4) !important;
    transform: translateY(-8px) !important;
}

/* 3. Dynamic Tag Styling */
.badge-tag-dynamic {
    background: rgba(102, 106, 255, 0.15) !important;
    color: #a5a9ff !important;
    font-size: 0.65rem;
    font-weight: 700;
}

/* AI Animations */
.ai-loader-container { position: relative; width: 80px; height: 80px; margin: 0 auto; display: flex; align-items: center; justify-content: center; }
.ai-icon { font-size: 3rem; color: var(--cui-success); z-index: 2; }
.ai-pulse { position: absolute; width: 100%; height: 100%; background: rgba(var(--cui-success-rgb), 0.2); border-radius: 50%; animation: ai-pulse 2s infinite ease-in-out; }
@keyframes ai-pulse { 0% { transform: scale(0.8); opacity: 0.8; } 50% { transform: scale(1.2); opacity: 0.3; } 100% { transform: scale(0.8); opacity: 0.8; } }
</style>

<script>
let selectedDiff = 'normal';
let generationModal = null;

// Initialize Modal and Handle BFcache
function initModal() {
    const modalEl = document.getElementById('spotQuizModal');
    if (modalEl) {
        generationModal = coreui.Modal.getOrCreateInstance(modalEl);
        generationModal.hide();
    }
}

document.addEventListener('DOMContentLoaded', initModal);
window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        if (!generationModal) initModal();
        if (generationModal) generationModal.hide();
    }
});

document.querySelectorAll('.difficulty-modes .diff-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.difficulty-modes .diff-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedDiff = this.dataset.diff;
    });
});

function triggerGeneration() {
    const topicId = '<?= $parentTopic['_id'] ?>';
    const subtopicId = '<?= $subtopic['_id'] ?>';
    
    if (!generationModal) initModal();

    // Reset Modal UI
    document.getElementById('genProgress').style.width = '5%';
    document.getElementById('genPercent').innerText = '5%';
    document.getElementById('genStatus').innerText = 'Initializing...';
    document.getElementById('modalActions').classList.add('d-none');
    document.getElementById('modalSubtext').classList.remove('text-danger');
    document.getElementById('modalSubtext').innerText = 'Generating your professional quiz challenge...';

    generationModal.show();

    fetch(`/api/quiz/generate?topic=${topicId}&subtopic=${subtopicId}&diff=${selectedDiff}`)
        .then(res => res.json())
        .then(data => {
            if (data.id) pollStatus(data.id);
            else showError(data.error || 'Failed to start.');
        });
}

function pollStatus(jobId) {
    const check = () => {
        fetch(`/api/quiz/job_status?job_id=${jobId}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('genProgress').style.width = data.percentage + '%';
                document.getElementById('genPercent').innerText = data.percentage + '%';
                document.getElementById('genStatus').innerText = data.status_text;

                if (data.generation_success) {
                    if (generationModal) generationModal.hide();
                    setTimeout(() => {
                        window.location.href = `/quiz/v/${data.result_hash}`;
                    }, 300);
                } else if (data.generation_failed) {
                    showError(data.status_text);
                } else {
                    setTimeout(check, 1500);
                }
            });
    };
    check();
}

function showError(msg) {
    document.getElementById('modalSubtext').innerText = msg;
    document.getElementById('modalSubtext').classList.add('text-danger');
    document.getElementById('modalActions').classList.remove('d-none');
}
</script>
