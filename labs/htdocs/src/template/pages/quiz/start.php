<?php
use TomLabs\Labs\Quiz;

$parentTopic = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

$difficultyFilter = $_GET['difficulty'] ?? $_COOKIE['quiz_difficulty_filter'] ?? 'normal';
$difficultyFilter = strtolower($difficultyFilter);

// Reinforce cookie from PHP side
if (isset($_GET['difficulty']) || isset($_COOKIE['quiz_difficulty_filter'])) {
    setcookie('quiz_difficulty_filter', $difficultyFilter, time() + (86400 * 30), "/");
}

// Debugging
if (class_exists('Session')) {
    logit("Difficulty Filter active: " . $difficultyFilter, "quiz_debug");
}

// Fetch real quizzes from MongoDB (High Density Limit)
$quizzes = Quiz::getRecentForSubtopic($subtopic['_id'], 8, 0, $difficultyFilter);

$user = Session::getUser();
$userStats = $user ? Quiz::getUserStats($user->getEmail()) : ['zeal' => 0, 'jolt' => 0];
$availableJolt = $userStats['jolt'] ?? 0;
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
                        <button class="diff-btn btn-easy <?= $difficultyFilter == 'easy' ? 'active' : '' ?>" data-diff="easy">Easy</button>
                        <button class="diff-btn btn-normal <?= $difficultyFilter == 'normal' ? 'active' : '' ?>" data-diff="normal">Normal</button>
                        <button class="diff-btn btn-hard <?= $difficultyFilter == 'hard' ? 'active' : '' ?>" data-diff="hard">Hard</button>
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

<!-- Reusable Empty State Template (Hidden for JS usage) -->
<template id="quiz-empty-state-template">
    <div class="w-100 d-flex justify-content-center py-5 mt-4 animate__animated animate__fadeIn" style="flex: 0 0 100%;">
        <div class="card quiz-glass-card border-0 shadow-lg text-center p-4 p-md-5" style="max-width: 420px; width: 100%; border-radius: 20px; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05) !important;">
            <div class="d-flex justify-content-center mb-4">
                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center border border-success border-opacity-25" style="width: 70px; height: 70px;">
                    <i class="bx bxs-bot fs-1 text-success"></i>
                </div>
            </div>
            <h4 class="fw-bold text-body-emphasis mb-2">No Quizzes Found</h4>
            <p class="text-body-secondary small mb-4 lh-base px-2">
                There are currently no challenges for the <strong class="selected-diff-text"><?= strtoupper($difficultyFilter) ?></strong> level in this topic. Be the first to generate a professional AI-powered quiz and test your skills!
            </p>
            <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm transition-all" onclick="triggerGeneration()" style="font-size: 0.95rem;">
                <i class="bx bxs-zap me-1"></i> Generate with AI
            </button>
        </div>
    </div>
</template>

    <!-- 3. Quizzes Grid -->
    <div id="quiz-list-container" class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 position-relative" style="z-index: 2;">
        <?php if (empty($quizzes)): ?>
            <div class="w-100 d-flex justify-content-center py-5 mt-4" style="flex: 0 0 100%;">
                <div class="card quiz-glass-card border-0 shadow-lg text-center p-4 p-md-5" style="max-width: 420px; width: 100%; border-radius: 20px; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05) !important;">
                    <div class="d-flex justify-content-center mb-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center border border-success border-opacity-25" style="width: 70px; height: 70px;">
                            <i class="bx bxs-bot fs-1 text-success"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-body-emphasis mb-2">No Quizzes Found</h4>
                    <p class="text-body-secondary small mb-4 lh-base px-2">
                        There are currently no challenges for the <strong class="selected-diff-text"><?= strtoupper($difficultyFilter) ?></strong> level in this topic. Be the first to generate a professional AI-powered quiz and test your skills!
                    </p>
                    <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm transition-all" onclick="triggerGeneration()" style="font-size: 0.95rem;">
                        <i class="bx bxs-zap me-1"></i> Generate with AI
                    </button>
                </div>
            </div>
        <?php else: foreach ($quizzes as $q): 
            $qDiff = strtolower($q['difficulty'] ?? 'normal');
            $qJolt = 2;
            if ($qDiff === 'easy') $qJolt = 1;
            elseif ($qDiff === 'hard') $qJolt = 5;
            $user = Session::getUser();
            $isAttempted = $user ? Quiz::hasAttempted($user->getEmail(), $q['hash']) : false;
            include __DIR__ . '/_card.php';
        endforeach; endif; ?>
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
                                <td class="py-2"><?= $availableJolt ?> ⚡️</td>
                                <td class="py-2"><?= max(0, $availableJolt - 1) ?> ⚡️</td>
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

let selectedDiff = '<?= $difficultyFilter ?>';
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
        if (this.classList.contains('active')) return; 

        document.querySelectorAll('.difficulty-modes .diff-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedDiff = this.dataset.diff;
        
        // Persist to cookie for PHP access on reload
        document.cookie = "quiz_difficulty_filter=" + selectedDiff + "; path=/; max-age=" + (86400 * 30) + "; SameSite=Lax";
        
        // Reset and Reload
        const container = document.getElementById('quiz-list-container');
        if (container) container.innerHTML = '<div class="w-100 text-center py-5"><div class="spinner-border theme-text" role="status"></div></div>';
        
        scrollOffset = 0;
        hasMoreQuizzes = true;
        loadMoreQuizzes(true); 
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
            if (data.id) {
                pollStatus(data.id);
                // Update header Jolt balance immediately
                if (typeof data.new_jolt !== 'undefined') {
                    const globalJolt = document.getElementById('header-jolt');
                    if (globalJolt) globalJolt.innerText = data.new_jolt.toLocaleString();
                }
            } else {
                showError(data.error || 'Failed to start generation.');
            }
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
        
        // We rely on PHP-initialized selectedDiff for consistency
        document.querySelectorAll('.difficulty-modes .diff-btn').forEach(b => {
            if (b.dataset.diff === selectedDiff) b.classList.add('active');
            else b.classList.remove('active');
        });

        if (state.offset > 8) {
            const fetchLimit = state.offset;
            isScrollLoading = true;
            fetch(`/api/quiz/list?subtopic_id=${subtopicIdForState}&offset=0&limit=${fetchLimit}&difficulty=${selectedDiff}`)
                .then(res => res.text())
                .then(html => {
                    if (html && html.trim().length > 0) {
                        const container = document.getElementById('quiz-list-container');
                        container.innerHTML = html; 
                        
                        const temp = document.createElement('div');
                        temp.innerHTML = html;
                        const cardCount = temp.querySelectorAll('.quiz-card-item').length;
                        
                        scrollOffset = cardCount;
                        // For restoration, we assume if we got cards, there might be more if we hit the limit
                        hasMoreQuizzes = cardCount >= 8; 
                        setTimeout(() => window.scrollTo({ top: state.scrollTop, behavior: 'instant' }), 100);
                    }
                })
                .finally(() => isScrollLoading = false);
        }
    }



    function loadMoreQuizzes(replace = false) {
        isScrollLoading = true;
        const loader = document.getElementById('scroll-loader');
        if (loader) loader.classList.remove('d-none');
        
        const currentOffset = replace ? 0 : scrollOffset;
        const fetchLimit = 8;
        fetch(`/api/quiz/list?subtopic_id=${subtopicIdForState}&offset=${currentOffset}&limit=${fetchLimit}&difficulty=${selectedDiff}`)
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('quiz-list-container');
                if (!container) return;

                if (replace) container.innerHTML = '';

                if (html && html.trim().length > 0) {
                    if (replace) container.innerHTML = html;
                    else container.insertAdjacentHTML('beforeend', html);

                    // Robust counting
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newCards = temp.querySelectorAll('.quiz-card-item').length;
                    
                    if (replace) scrollOffset = newCards;
                    else scrollOffset += newCards;
                    
                    hasMoreQuizzes = newCards === fetchLimit;
                    saveScrollState();
                } else {
                    console.log("[Quiz Hub] No more quizzes found.");
                    hasMoreQuizzes = false;
                    if (replace) {
                        const template = document.getElementById('quiz-empty-state-template');
                        if (template) {
                            const clone = template.content.cloneNode(true);
                            const diffText = clone.querySelector('.selected-diff-text');
                            if (diffText) diffText.innerText = selectedDiff.toUpperCase();
                            container.appendChild(clone);
                        }
                    }
                }
            })
            .finally(() => {
                isScrollLoading = false;
                if (loader) loader.classList.add('d-none');
                const noMore = document.getElementById('no-more-msg');
                if (!hasMoreQuizzes) {
                    if (noMore) noMore.classList.remove('d-none');
                } else {
                    if (noMore) noMore.classList.add('d-none');
                }
            });
    }
    window.addEventListener('scroll', () => { if (!isScrollLoading) saveScrollState(); });
}
</script>
