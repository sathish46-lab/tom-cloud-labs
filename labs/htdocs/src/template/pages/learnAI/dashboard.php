<?php
$db = DatabaseConnection::getDefaultDatabase();
$lessons = $db->ai_lessons->find()->toArray();
$user = Session::getUser();
?>

<div class="learn-app-wrapper pb-5">
    <!-- Main Dashboard Area - Natural Scroll -->
    <div class="p-0">
        <div class="text-center mb-5 mt-4">
            <h1 class="display-6 fw-bold mb-3">Tell us what you'd like to Learn!</h1>
            
            <div class="mx-auto px-2" style="max-width: 800px;">
                <div class="mb-3 d-flex flex-wrap justify-content-center gap-2">
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Node.js Backend</span>
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Web Engineering Fundamentals</span>
                    <span class="badge rounded-pill bg-secondary text-white px-3 py-2">Docker Containerization</span>
                </div>
                
                <div class="card bg-dark border-secondary border-opacity-10 rounded-4 shadow-lg overflow-hidden">
                    <div class="card-body p-0">
                        <textarea id="aiLessonPrompt" class="form-control bg-transparent text-white border-0 p-3" 
                                  placeholder="Search lessons or describe a topic..." 
                                  rows="2" style="resize: none; font-size: 1rem;"></textarea>
                        <div class="d-flex align-items-center justify-content-end p-2 bg-dark bg-opacity-25 border-top border-secondary border-opacity-10">
                            <select id="aiLessonLevel" class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-25 rounded-pill me-2" style="width: auto; font-size: 0.75rem;">
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                            <button id="btnGenerateLesson" type="button" class="btn btn-primary btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;">
                                 <i class="bx bx-send"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid mt-5">
            <div class="d-flex flex-column flex-md-row align-items-md-center mb-4 gap-3">
                <div class="d-flex gap-2 align-items-center overflow-auto no-scrollbar pb-1">
                    <span class="badge bg-dark border border-secondary border-opacity-10 text-white px-3 py-2 rounded-pill whitespace-nowrap" style="font-size: 0.7rem;">
                        <i class="bx bxs-book-open text-primary me-1"></i> <?= count($lessons) ?> Lessons
                    </span>
                </div>
                <div class="ms-md-auto d-flex gap-2">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill active border-opacity-25 px-3">Continue</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-10 px-3">Explore</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-10 px-3">My Lessons</button>
                </div>
            </div>

            <h4 class="fw-bold mb-4 px-1">Learning Paths</h4>

            <div class="row g-3 mb-5">
                <?php foreach ($lessons as $lesson): ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 bg-dark bg-opacity-50 border-secondary border-opacity-10 rounded-4 shadow-sm overflow-hidden transition-all-lite">
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill" style="font-size: 0.6rem;">
                                    <i class="bx bxs-star me-1"></i><?= $lesson['level'] ?>
                                </span>
                                <div class="dropdown">
                                    <button class="btn btn-link text-secondary p-0" data-coreui-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded" style="font-size: 0.9rem;"></i>
                                    </button>
                                </div>
                            </div>

                            <h6 class="card-title fw-bold mb-2 text-white"><?= $lesson['title'] ?></h6>
                            <p class="card-text text-secondary mb-3 flex-grow-1" style="font-size: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= $lesson['description'] ?></p>

                            <div class="d-flex gap-2 text-secondary mb-3" style="font-size: 0.65rem;">
                                <span><i class="bx bx-book me-1"></i><?= $lesson['modules_count'] ?> Mod</span>
                                <span><i class="bx bx-layer me-1"></i><?= $lesson['chapters_count'] ?> Chap</span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <span class="text-secondary" style="font-size: 0.65rem;">Progress</span>
                                    <span class="fw-bold text-white" style="font-size: 0.65rem;"><?= $lesson['progress'] ?>%</span>
                                </div>
                                <div class="progress bg-secondary bg-opacity-10 rounded-pill" style="height: 3px;">
                                    <div class="progress-bar bg-success rounded-pill" style="width: <?= $lesson['progress'] ?>%"></div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center pt-2 border-top border-secondary border-opacity-10 mt-1">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <img src="<?= Session::getAvatar() ?>" alt="Author" class="rounded-circle me-2 border border-secondary border-opacity-25" width="18" height="18">
                                    <span class="text-secondary text-truncate" style="font-size: 0.65rem;"><?= $lesson['author'] ?></span>
                                </div>
                                <div class="ms-auto">
                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-xs btn-primary rounded-pill px-3 shadow-sm" style="font-size: 0.65rem;">Continue</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- AI Lesson Generation Progress Modal Card -->
<div class="modal fade" id="lessonGenModal" tabindex="-1" aria-hidden="true" data-coreui-backdrop="static" data-coreui-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary border-opacity-25 shadow-lg rounded-4 overflow-hidden bg-dark text-white">
            <div class="modal-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1">
                        <i class="bx bx-bot me-1"></i> Learn AI Generator
                    </span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="ai-icon-wrapper my-3 position-relative d-inline-flex align-items-center justify-content-center">
                    <div class="ai-ring"></div>
                    <i class="bx bx-chip text-primary fs-1 position-relative z-1"></i>
                </div>

                <h5 class="fw-bold mb-1 text-white" id="lessonGenTopicDisplay">Curating AI Learning Path...</h5>
                <p class="text-secondary small mb-4" id="lessonGenLevelDisplay">Professional Interactive Course</p>

                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <span class="text-secondary small" id="lessonGenStatus">Initializing AI engine...</span>
                    <span class="fw-bold text-primary small" id="lessonGenPercent">5%</span>
                </div>
                <div class="progress bg-secondary bg-opacity-25 rounded-pill mb-4" style="height: 6px;">
                    <div id="lessonGenProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary rounded-pill" role="progressbar" style="width: 5%"></div>
                </div>

                <!-- Live Job Status Response Card -->
                <div class="card bg-black bg-opacity-50 border-secondary border-opacity-25 rounded-3 text-start p-3 font-monospace small">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                        <span class="text-secondary" style="font-size: 0.7rem;">JOB STATUS DETAILS</span>
                        <span id="lessonGenStatusBadge" class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">RUNNING</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-secondary">request_id:</span>
                        <span id="lessonGenRequestId" class="text-info text-truncate ms-2" style="max-width: 220px;">--</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-secondary">status:</span>
                        <span id="lessonGenStatusValue" class="text-white">running</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-secondary">message:</span>
                        <span id="lessonGenMessageValue" class="text-white text-truncate ms-2">running</span>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                        <span class="text-secondary">completed:</span>
                        <span id="lessonGenCompletedValue" class="text-white">false</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 justify-content-center">
                <button id="btnCancelLessonGen" type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* App Layout Overrides */
.learn-app-wrapper { background: transparent; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.whitespace-nowrap { white-space: nowrap; }
.transition-all-lite { transition: all 0.2s ease; }

/* Extra small button */
.btn-xs { 
    padding: 0.2rem 0.6rem;
    font-size: 0.65rem;
    line-height: 1.2;
}

/* AI Ring Pulse */
.ai-icon-wrapper {
    width: 72px;
    height: 72px;
}
.ai-ring {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.15);
    border: 1px solid rgba(13, 110, 253, 0.4);
    animation: ai-ring-pulse 2s infinite ease-in-out;
}
@keyframes ai-ring-pulse {
    0% { transform: scale(0.9); opacity: 0.8; }
    50% { transform: scale(1.18); opacity: 0.35; }
    100% { transform: scale(0.9); opacity: 0.8; }
}

@media (max-width: 768px) {
    .display-6 { font-size: 1.5rem !important; }
}
</style>

<script>
window.onPageLoad(function() {
    const promptInput = document.getElementById('aiLessonPrompt');
    const levelSelect = document.getElementById('aiLessonLevel');
    const btnSend = document.getElementById('btnGenerateLesson');

    // Make sample topic badges clickable
    document.querySelectorAll('.badge.bg-secondary.text-white').forEach(badge => {
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', () => {
            if (promptInput) {
                promptInput.value = badge.textContent.trim();
                promptInput.focus();
            }
        });
    });

    let pollInterval = null;

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function startLessonGeneration() {
        const topic = promptInput ? promptInput.value.trim() : '';
        const level = levelSelect ? levelSelect.value : 'Beginner';

        if (!topic) {
            if (promptInput) promptInput.focus();
            return;
        }

        stopPolling();

        const modalEl = document.getElementById('lessonGenModal');
        const modal = coreui.Modal.getInstance(modalEl) || new coreui.Modal(modalEl);

        // Reset UI state without displaying raw prompt
        document.getElementById('lessonGenTopicDisplay').textContent = "Curating AI Learning Path...";
        document.getElementById('lessonGenLevelDisplay').textContent = level + ' Level Professional Course';
        document.getElementById('lessonGenProgress').style.width = '10%';
        document.getElementById('lessonGenProgress').className = 'progress-bar progress-bar-striped progress-bar-animated bg-primary rounded-pill';
        document.getElementById('lessonGenPercent').textContent = '10%';
        document.getElementById('lessonGenStatus').textContent = 'Initiating AI request...';
        document.getElementById('lessonGenRequestId').textContent = 'Generating...';
        document.getElementById('lessonGenStatusValue').textContent = 'running';
        document.getElementById('lessonGenMessageValue').textContent = 'running';
        document.getElementById('lessonGenCompletedValue').textContent = 'false';

        const statusBadge = document.getElementById('lessonGenStatusBadge');
        statusBadge.className = 'badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25';
        statusBadge.textContent = 'RUNNING';

        modal.show();

        fetch('/src/api/learnAI/generate_lesson.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ topic: topic, level: level })
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.request_id) {
                document.getElementById('lessonGenRequestId').textContent = data.request_id;
                document.getElementById('lessonGenStatusValue').textContent = data.status || 'running';
                document.getElementById('lessonGenMessageValue').textContent = data.message || 'running';

                // Poll job status
                pollInterval = setInterval(() => {
                    fetch(`/src/api/learnAI/job_status.php?request_id=${encodeURIComponent(data.request_id)}`)
                        .then(r => r.json())
                        .then(job => {
                            if (!job) return;

                            const pct = job.percentage || 40;
                            document.getElementById('lessonGenProgress').style.width = pct + '%';
                            document.getElementById('lessonGenPercent').textContent = pct + '%';
                            document.getElementById('lessonGenStatus').textContent = job.message || 'Processing...';
                            document.getElementById('lessonGenStatusValue').textContent = job.status || 'running';
                            document.getElementById('lessonGenMessageValue').textContent = job.message || 'running';
                            document.getElementById('lessonGenCompletedValue').textContent = job.completed ? 'true' : 'false';

                            if (job.completed && job.lesson_id) {
                                stopPolling();
                                document.getElementById('lessonGenProgress').style.width = '100%';
                                document.getElementById('lessonGenProgress').className = 'progress-bar bg-success rounded-pill';
                                document.getElementById('lessonGenPercent').textContent = '100%';
                                document.getElementById('lessonGenStatus').textContent = 'Complete! Redirecting to lesson...';
                                statusBadge.className = 'badge bg-success bg-opacity-25 text-success border border-success border-opacity-25';
                                statusBadge.textContent = 'COMPLETED';

                                if (promptInput) promptInput.value = '';

                                setTimeout(() => {
                                    window.location.href = `/learn/lesson/${job.lesson_id}`;
                                }, 1200);
                            } else if (job.failed) {
                                stopPolling();
                                document.getElementById('lessonGenProgress').className = 'progress-bar bg-danger rounded-pill';
                                document.getElementById('lessonGenStatus').textContent = job.error_message || 'Generation failed.';
                                statusBadge.className = 'badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25';
                                statusBadge.textContent = 'FAILED';
                            }
                        })
                        .catch(() => {});
                }, 1500);
            } else {
                document.getElementById('lessonGenStatus').textContent = data.message || 'Failed to start generation.';
                document.getElementById('lessonGenProgress').className = 'progress-bar bg-danger rounded-pill';
            }
        })
        .catch(() => {
            document.getElementById('lessonGenStatus').textContent = 'Network error starting generator.';
            document.getElementById('lessonGenProgress').className = 'progress-bar bg-danger rounded-pill';
        });
    }

    if (btnSend) {
        btnSend.addEventListener('click', startLessonGeneration);
    }
    if (promptInput) {
        promptInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                startLessonGeneration();
            }
        });
    }
});
</script>
