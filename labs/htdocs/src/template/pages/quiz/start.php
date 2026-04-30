<?php
use TomLabs\Labs\Quiz;

$parentTopic = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Fetch real quizzes from MongoDB (High Density Limit)
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
        <div id="quiz-list-container" class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 position-relative" style="z-index: 2;">
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

        <!-- Infinite Scroll Sentinel (Strictly Professional) -->
        <div id="infinite-scroll-sentinel" class="text-center py-5 opacity-50" style="min-height: 100px;">
            <div id="scroll-loader" class="spinner-border theme-text d-none" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div id="no-more-msg" class="text-body-secondary small d-none mt-3">
                <i class="bx bx-check-circle me-1"></i> You've explored all challenges for this topic.
            </div>
        </div>
    </div>
</div>

<!-- Spot Quiz Modal (Two-Step: Confirm -> Generate) -->
<div class="modal fade" id="spotQuizModal" tabindex="-1" aria-hidden="true" data-coreui-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            
            <!-- STEP 1: Confirmation View -->
            <div id="modal-view-confirm" class="modal-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-white border-opacity-10 pb-2">
                    <h5 class="fw-bold text-white mb-0">Spot Quiz ⚡️</h5>
                    <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <p class="text-white small mb-3">
                    Are you sure you want to start a spot quiz on this topic with the selected difficulty?<br>
                    <span class="opacity-75">Once the quiz is generated, you will be notified and 1 ⚡️ will be debited.</span>
                </p>

                <!-- Cost Table (Reference Match) -->
                <div class="table-responsive mb-4">
                    <table class="table table-borderless table-sm text-center text-white small mb-0">
                        <thead>
                            <tr class="opacity-50 border-bottom border-white border-opacity-10">
                                <th>Spot Quiz Cost</th>
                                <th>Available Jolt</th>
                                <th>Jolt Remaining after Generating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fw-bold">
                                <td class="py-2">1 ⚡️</td>
                                <td class="py-2">8783 ⚡️</td>
                                <td class="py-2">8782 ⚡️</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 btn-sm fw-bold" data-coreui-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success rounded-pill px-4 btn-sm fw-bold shadow-sm" onclick="startGenerationProcess()">Generate Quiz</button>
                </div>
            </div>

            <!-- STEP 2: Generation View (Hidden initially) -->
            <div id="modal-view-progress" class="modal-body p-5 text-center d-none">
                <h5 class="fw-bold text-white mb-4">Spot Quiz ⚡️</h5>
                
                <p class="text-body-secondary small mb-4" id="modalSubtext">
                    Please wait while we generate your quiz... this may take couple minutes.
                </p>

                <div class="mb-2">
                    <div class="fw-bold small theme-text mb-2 text-start d-flex justify-content-between">
                        <span id="genStatus">Initializing...</span>
                        <span id="genPercent">0%</span>
                    </div>
                    <div class="progress rounded-pill bg-white bg-opacity-10" style="height: 6px;">
                        <div id="genProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-info" 
                             role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <div id="modalActions" class="d-none mt-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-info rounded-pill px-4 fw-bold shadow-sm ms-2" onclick="triggerGeneration()">Try Again</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Assets are now bundled into app.js/app.css
?>

<script>
window.QuizConfig = {
    parentTopicId: '<?= $parentTopic['_id'] ?>',
    subtopicId: '<?= $subtopic['_id'] ?>'
};

/**
 * Quiz Start Module (Restored to Template for Stability)
 * Handles AI generation triggers and Infinite Scroll
 */

let selectedDiff = 'normal';
let generationModal = null;

// Initialize Modal and Handle BFcache
function initModal() {
    const modalEl = document.getElementById('spotQuizModal');
    if (modalEl) {
        // Move modal to body so it's not trapped inside containers
        // with backdrop-filter/transform that break position:fixed
        document.body.appendChild(modalEl);
        generationModal = coreui.Modal.getOrCreateInstance(modalEl);
        generationModal.hide();
    }
}

document.addEventListener('DOMContentLoaded', initModal);
window.addEventListener('pageshow', function (event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        if (!generationModal) initModal();
        if (generationModal) generationModal.hide();
    }
});

// Difficulty Selection
document.querySelectorAll('.difficulty-modes .diff-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.difficulty-modes .diff-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedDiff = this.dataset.diff;
    });
});

/**
 * Global Trigger: AI Generation (Now Two-Step)
 */
window.triggerGeneration = function () {
    console.log("[Quiz Hub] Opening Spot Quiz Confirmation...");
    
    // Ensure views are reset to Step 1 (Confirm)
    const confirmView = document.getElementById('modal-view-confirm');
    const progressView = document.getElementById('modal-view-progress');
    if (confirmView) confirmView.classList.remove('d-none');
    if (progressView) progressView.classList.add('d-none');

    const modalEl = document.getElementById('spotQuizModal');
    if (!modalEl) {
        console.error("[Quiz Hub] spotQuizModal NOT FOUND in DOM!");
        return;
    }
    
    const modalInstance = coreui.Modal.getOrCreateInstance(modalEl);
    if (modalInstance) modalInstance.show();
};

window.startGenerationProcess = function() {
    console.log("[Quiz Hub] Starting Generation Process...");
    
    // Switch to Progress View
    const confirmView = document.getElementById('modal-view-confirm');
    const progressView = document.getElementById('modal-view-progress');
    if (confirmView) confirmView.classList.add('d-none');
    if (progressView) progressView.classList.remove('d-none');

    // Reset Progress UI
    const progress = document.getElementById('genProgress');
    const percent = document.getElementById('genPercent');
    const status = document.getElementById('genStatus');
    const actions = document.getElementById('modalActions');
    const subtext = document.getElementById('modalSubtext');

    if (progress) {
        progress.style.width = '5%';
        progress.classList.remove('bg-danger');
        progress.classList.add('bg-info');
    }
    if (percent) percent.innerText = '5%';
    if (status) status.innerText = 'Initializing...';
    if (actions) actions.classList.add('d-none');
    if (subtext) {
        subtext.classList.remove('text-danger');
        subtext.innerText = 'Please wait while we generate your quiz... this may take couple minutes.';
    }

    // Trigger API
    fetch(`/api/quiz/generate?topic=${window.QuizConfig.parentTopicId}&subtopic=${window.QuizConfig.subtopicId}&diff=${selectedDiff}`)
        .then(res => res.json())
        .then(data => {
            if (data.id) pollStatus(data.id);
            else showError(data.error || 'Failed to start generation.');
        })
        .catch(err => {
            console.error("[Quiz Hub] API Error:", err);
            showError('Connection error. Please check your network.');
        });
};

function pollStatus(jobId) {
    const check = () => {
        fetch(`/api/quiz/job_status?job_id=${jobId}`)
            .then(res => res.json())
            .then(job => {
                if (!job) return;

                const progress = job.percentage || 5;
                const pBar = document.getElementById('genProgress');
                const pText = document.getElementById('genPercent');
                const sText = document.getElementById('genStatus');

                if (pBar) pBar.style.width = progress + '%';
                if (pText) pText.innerText = progress + '%';
                if (sText) sText.innerText = job.status_text || `Attempt: 1 / 3`;

                if (job.generation_success && job.result_hash) {
                    if (sText) sText.innerText = 'Generation Complete!';
                    setTimeout(() => {
                        window.location.href = `/quiz/v/${job.result_hash}`;
                    }, 800);
                } else if (job.generation_failed) {
                    showError(job.error || 'AI Generation failed.');
                } else {
                    setTimeout(check, 1500);
                }
            });
    };
    check();
}

function showError(msg) {
    const subtext = document.getElementById('modalSubtext');
    const actions = document.getElementById('modalActions');
    const progress = document.getElementById('genProgress');

    if (subtext) {
        subtext.innerText = msg;
        subtext.classList.add('text-danger');
    }
    if (actions) actions.classList.remove('d-none');
    if (progress) progress.classList.add('bg-danger');
}

// --- INFINITE SCROLL & STATE PERSISTENCE ---
if (window.QuizConfig && window.QuizConfig.subtopicId) {
    const subtopicIdForState = window.QuizConfig.subtopicId;
    let scrollOffset = 8;
    let isScrollLoading = false;
    let hasMoreQuizzes = true;

    const scrollObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isScrollLoading && hasMoreQuizzes) {
            loadMoreQuizzes();
        }
    }, { threshold: 0.5 });

    const scrollSentinel = document.getElementById('infinite-scroll-sentinel');
    if (scrollSentinel) {
        scrollObserver.observe(scrollSentinel);
        document.addEventListener('DOMContentLoaded', restoreScrollState);
    }

    function saveScrollState() {
        const state = { offset: scrollOffset, scrollTop: window.scrollY, subtopic: subtopicIdForState };
        sessionStorage.setItem('quiz_hub_state_' + subtopicIdForState, JSON.stringify(state));
    }

    function restoreScrollState() {
        const saved = sessionStorage.getItem('quiz_hub_state_' + subtopicIdForState);
        if (!saved) return;
        const state = JSON.parse(saved);
        if (state.subtopic !== subtopicIdForState) return;

        if (state.offset > 8) {
            const fetchLimit = state.offset - 8;
            isScrollLoading = true;
            fetch(`/api/quiz/list?subtopic_id=${subtopicIdForState}&offset=8&limit=${fetchLimit}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        const container = document.getElementById('quiz-list-container');
                        data.data.forEach(q => renderQuizCard(container, q));
                        scrollOffset = state.offset;
                        hasMoreQuizzes = data.has_more;
                        setTimeout(() => window.scrollTo({ top: state.scrollTop, behavior: 'instant' }), 100);
                    }
                })
                .finally(() => isScrollLoading = false);
        }
    }

    function renderQuizCard(container, q) {
        const col = document.createElement('div');
        col.className = 'col animate__animated animate__fadeIn';
        let tagsHtml = '';
        if (q.tags && Array.isArray(q.tags)) {
            q.tags.forEach(tag => {
                tagsHtml += `<span class="badge badge-tag-dynamic rounded-pill px-2 me-1">${tag.toLowerCase()}</span>`;
            });
        }
        col.innerHTML = `
            <div class="card quiz-glass-card h-100 border-0 shadow-sm glass-effect-premium">
                <div class="card-body d-flex flex-column p-4">
                    <h6 class="card-title-text fw-bold mb-2" style="min-height: 2.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        ${q.title}
                    </h6>
                    <p class="text-body-secondary x-small mb-3 opacity-75" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.8rem;">
                        ${q.desc}
                    </p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <span class="badge badge-quiz-tag rounded-pill px-2">new ✨</span>
                        <span class="badge badge-quiz-cat rounded-pill px-2">${q.difficulty}</span>
                        ${tagsHtml}
                        <div class="ms-auto">
                            <span class="badge badge-quiz-time rounded-pill px-2"><i class="bx bx-time-five"></i> ${q.created_at}</span>
                        </div>
                    </div>
                    <div class="mt-auto pt-3 border-top border-white border-opacity-10">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-item"><span class="fw-bold">${q.points_per_correct || 25}</span> <i class="bx bxs-hot text-danger"></i></div>
                                <div class="stat-item"><span class="fw-bold">2</span> <i class="bx bxs-zap text-warning"></i></div>
                                <div class="stat-item"><span class="fw-bold">16</span> <i class="bx bxs-show text-info"></i></div>
                            </div>
                            <a href="/quiz/v/${q.hash}" class="btn btn-success btn-sm rounded-pill fw-bold px-3">Answer Quiz</a>
                        </div>
                    </div>
                </div>
            </div>`;
        container.appendChild(col);
    }

    function loadMoreQuizzes() {
        isScrollLoading = true;
        const loader = document.getElementById('scroll-loader');
        if (loader) loader.classList.remove('d-none');
        setTimeout(() => {
            fetch(`/api/quiz/list?subtopic_id=${subtopicIdForState}&offset=${scrollOffset}&limit=8`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        const container = document.getElementById('quiz-list-container');
                        data.data.forEach(q => renderQuizCard(container, q));
                        scrollOffset += data.data.length;
                        hasMoreQuizzes = data.has_more;
                        saveScrollState();
                    } else {
                        hasMoreQuizzes = false;
                    }
                })
                .finally(() => {
                    isScrollLoading = false;
                    if (loader) loader.classList.add('d-none');
                    if (!hasMoreQuizzes) {
                        const noMore = document.getElementById('no-more-msg');
                        if (noMore) noMore.classList.remove('d-none');
                        scrollObserver.unobserve(scrollSentinel);
                    }
                });
        }, 800);
    }
    window.addEventListener('scroll', () => { if (!isScrollLoading) saveScrollState(); });
}
</script>
