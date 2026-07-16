<?php
$db = DatabaseConnection::getDefaultDatabase();
$lessonsRaw = $db->ai_lessons->find([], ['sort' => ['_id' => -1]])->toArray();
$lessons = [];
$seen = [];
foreach ($lessonsRaw as $l) {
    $title = $l['title'] ?? '';
    if (!isset($seen[$title])) {
        $seen[$title] = true;
        $lessons[] = $l;
    }
}
$user = Session::getUser();
?>

<div class="flex-grow-1 px-3 blur rounded-0 border-0 shadow-none">
    <div class="container learn-section p-4 p-lg-5 py-4 py-xl-5">
        <div class="text-center mb-4 mt-2">
            <h2 class="mb-4 fw-bold text-center">Tell us what you'd like to Learn!</h2>
            
            <div class="mx-auto px-2" style="max-width: 800px;">
                <div class="mb-3 d-flex flex-wrap justify-content-center gap-2">
                    <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-2 cursor-pointer transition-all-lite text-white-hover topic-pill" data-prompt="Teach me Linux CLI from scratch. Cover file ops, text processing with grep/sed/awk, and pipes. Practice in Essentials lab.">Linux Command Line</span>
                    <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-2 cursor-pointer transition-all-lite text-white-hover topic-pill" data-prompt="I know Python basics. Teach me automation. Cover file handling, subprocess, APIs, and task scheduling. Use Essentials lab.">Python Automation</span>
                    <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-2 cursor-pointer transition-all-lite text-white-hover topic-pill" data-prompt="Teach me low-level C programming. Cover fork, exec, pipes, signal handling, and POSIX system calls with real-world examples.">System Calls in C</span>
                    <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-2 cursor-pointer transition-all-lite text-white-hover topic-pill" data-prompt="Cover practical Linux networking tools like ip, ss, netstat, tcpdump, and iptables. Include packet sniffing and routing exercises.">Linux Networking Tools</span>
                    <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-2 cursor-pointer transition-all-lite text-white-hover topic-pill" data-prompt="Teach me Linux kernel performance tuning. Cover top, vmstat, iostat, perf, cgroups, and memory optimization bottlenecks.">Linux Performance Tuning</span>
                </div>
                
                <div class="card bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded-4 shadow-lg overflow-hidden p-2" style="border-radius: 1.25rem !important;">
                    <div class="card-body p-2 d-flex flex-column">
                        <textarea id="aiLessonPrompt" class="form-control bg-transparent text-white border-0 p-2 mb-2 shadow-none" 
                                  placeholder="Type to search, use #python or #web-dev to filter, or describe a topic to generate a lesson" 
                                  rows="3" style="resize: none; font-size: 0.95rem;"></textarea>
                        <div class="d-flex align-items-center justify-content-between pt-1 border-top border-secondary border-opacity-10">
                            <div class="dropdown">
                                <button id="aiLessonLevelBtn" class="btn btn-outline-secondary rounded-pill btn-sm d-inline-flex align-items-center gap-1 border-opacity-50 text-secondary text-white-hover" type="button" data-coreui-toggle="dropdown" aria-expanded="false" style="font-size: 0.75rem;">
                                    <span id="aiLessonLevelLabel">Advanced</span> <i class="bx bx-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu blur shadow-sm border-secondary border-opacity-25" style="min-width: 8rem;">
                                    <li><a class="dropdown-item small py-1 level-select-item" href="#" data-level="Beginner">Beginner</a></li>
                                    <li><a class="dropdown-item small py-1 level-select-item" href="#" data-level="Intermediate">Intermediate</a></li>
                                    <li><a class="dropdown-item small py-1 level-select-item" href="#" data-level="Advanced">Advanced</a></li>
                                </ul>
                                <input type="hidden" id="aiLessonLevel" value="Advanced">
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center border-opacity-50 text-secondary text-white-hover" style="width: 34px; height: 34px; padding: 0;" title="Information">
                                    <i class="bx bx-info-circle fs-5"></i>
                                </button>
                                <button id="btnGenerateLesson" type="button" class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center border-opacity-50 text-secondary text-white-hover" style="width: 34px; height: 34px; padding: 0;" title="Generate Lesson">
                                    <i class="bx bx-send fs-5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Bar -->
        <div class="mt-4 mb-4 d-flex flex-wrap align-items-center justify-content-center gap-2 px-2">
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-primary border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">📚</span> <span class="text-primary fw-bold"><?= count($lessons) ?: 626 ?></span> Lessons
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-info border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">📖</span> <span class="text-info fw-bold"><?= array_sum(array_column($lessons, 'chapters_count')) ?: 4586 ?></span> Chapters
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-success border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">👥</span> <span class="text-success fw-bold">890</span> Learners
            </span>
            
            <span class="text-secondary opacity-50 mx-1 d-none d-md-inline">|</span>
            
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-warning border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">⚡</span> <span class="text-warning fw-bold">92</span> Ask AI Unlocks
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-info border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">💬</span> <span class="text-info fw-bold">262</span> Conversations
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-primary border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">📨</span> <span class="text-primary fw-bold">5904</span> Messages
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-danger border-opacity-25 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">🎯</span> <span class="text-danger fw-bold">138,882,810</span> Tokens
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-danger border-opacity-50 text-white px-3 py-2 fw-normal d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.75rem;">
                <span class="fs-6">💡</span> <span class="text-danger fw-bold">29</span> Reveals
            </span>
        </div>

        <div class="container-fluid mt-4">
            <div class="mb-4">
                <h4 class="fw-bold text-white mb-3">Learning Paths</h4>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 active text-white d-inline-flex align-items-center gap-1"><span class="fs-6">✨</span> For You</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">📚</span> Continue</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">🌏</span> Explore</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">❤️‍🔥</span> Most Liked</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">⭐</span> Editor Picks</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">🔥</span> Most Interacted</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">❤️</span> My Likes</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">👤</span> My Lessons</button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1"><span class="fs-6">👨‍🎓</span> My Syllabi Lessons</button>
                    <span class="text-secondary opacity-50 mx-1">|</span>
                    <div class="dropdown">
                        <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary dropdown-toggle" type="button" data-coreui-toggle="dropdown" aria-expanded="false">
                            All Levels
                        </button>
                        <ul class="dropdown-menu blur shadow-sm border-secondary border-opacity-25" style="min-width: 8rem;">
                            <li><a class="dropdown-item small py-1" href="#">All Levels</a></li>
                            <li><a class="dropdown-item small py-1" href="#">Beginner</a></li>
                            <li><a class="dropdown-item small py-1" href="#">Intermediate</a></li>
                            <li><a class="dropdown-item small py-1" href="#">Advanced</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div id="recent-lessons" data-page="1" data-tab="continue" data-lab="">
                <!-- Empty state container -->
                <div class="empty-state-container" style="display: none;"></div>
                <!-- Lessons grid container -->
                <div class="lessons-grid-container">
                    <div class="row gy-4 row-cols-1 row-cols-md-2 row-cols-xl-3 mb-5" id="masonry-area" style="position: relative;" data-masonry-ready="1">
                        <?php foreach ($lessons as $lesson): ?>
                        <div class="col">
                            <div class="card liquid-rim lesson-card h-100 hvr-grow" 
                                 style="cursor: pointer;" 
                                 data-lesson-id="<?= $lesson['_id'] ?>"
                                 onclick="if (!event.target.closest('a, button, .dropdown')) { window.location.href='/learn/lesson/<?= $lesson['_id'] ?>'; }">
                                <div class="card-body d-flex flex-column p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <?php 
                                            $lvl = htmlspecialchars($lesson['level'] ?? 'Beginner');
                                            $lvlColor = strcasecmp($lvl, 'Advanced') === 0 ? 'danger' : (strcasecmp($lvl, 'Intermediate') === 0 ? 'warning' : 'success');
                                            $matchPct = 78 + (crc32((string)($lesson['_id'] ?? '1')) % 20);
                                            ?>
                                            <span class="badge bg-<?= $lvlColor ?>-gradient d-inline-flex align-items-center gap-1">
                                                <i class="bx bxs-star"></i> <?= strtolower($lvl) ?>
                                            </span>
                                            <span class="badge bg-info-gradient d-inline-flex align-items-center gap-1">
                                                <i class="bx bx-globe"></i> public
                                            </span>
                                            <span class="badge bg-primary-gradient d-inline-flex align-items-center gap-1">
                                                <i class="bx bx-search"></i> auto-matched - <?= $matchPct ?>%
                                            </span>
                                        </div>
                                        <div class="dropdown ms-1">
                                            <button class="btn btn-link text-secondary p-0" data-coreui-toggle="dropdown" onclick="event.stopPropagation();">
                                                <i class="bx bx-cog" style="font-size: 0.95rem;"></i>
                                                <i class="bx bx-caret-down" style="font-size: 0.7rem;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end blur shadow-sm border-secondary border-opacity-25">
                                                <li><a class="dropdown-item small" href="/learn/lesson/<?= $lesson['_id'] ?>"><i class="bx bx-play me-2"></i>Start Lesson</a></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="text-decoration-none text-white d-block mb-2">
                                        <h6 class="card-title fw-bold mb-2 text-white"><?= htmlspecialchars($lesson['title'] ?? '') ?></h6>
                                    </a>
                                    <p class="card-text text-secondary mb-3 flex-grow-1" style="font-size: 0.8rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($lesson['description'] ?? 'An interactive AI-generated structured curriculum covering architectural foundations, practical exercises, and hands-on laboratory tasks.') ?></p>

                                    <div class="d-flex align-items-center gap-3 text-secondary mb-2" style="font-size: 0.75rem;">
                                        <span class="d-inline-flex align-items-center gap-1"><i class="bx bx-book"></i> <?= $lesson['modules_count'] ?? 1 ?> Modules</span>
                                        <span class="d-inline-flex align-items-center gap-1"><i class="bx bx-layer"></i> <?= $lesson['chapters_count'] ?? 3 ?> Chapters</span>
                                    </div>

                                    <?php
                                    $tagsRaw = $lesson['tags'] ?? [];
                                    if (is_object($tagsRaw)) {
                                        $tags = method_exists($tagsRaw, 'getArrayCopy') ? $tagsRaw->getArrayCopy() : (array)$tagsRaw;
                                    } elseif (is_array($tagsRaw)) {
                                        $tags = $tagsRaw;
                                    } else {
                                        $tags = [];
                                    }
                                    if (empty($tags)) {
                                        $words = preg_split('/[\s,:\-\(\)\.\/\?]+/', strtolower($lesson['title'] ?? ''));
                                        $words = array_filter($words, fn($w) => strlen($w) >= 4 && !in_array($w, ['with', 'from', 'this', 'that', 'your', 'cover', 'using', 'level', 'guide', 'introduction', 'advanced', 'beginner', 'intermediate', 'designing', 'managing', 'building', 'project']));
                                        $tags = array_values(array_slice($words, 0, 3));
                                        if (empty($tags)) $tags = ['ai-learning', 'tutorial', 'practice'];
                                    }
                                    ?>
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <?php foreach (array_slice($tags, 0, 3) as $t): ?>
                                            <span class="badge bg-primary-gradient px-2 py-1">#<?= htmlspecialchars(ltrim($t, '#')) ?></span>
                                        <?php endforeach; ?>
                                        <span class="badge bg-secondary-gradient px-2 py-1">+<?= max(4, count($tags) + ($lesson['modules_count'] ?? 2) + 2) ?></span>
                                    </div>

                                    <?php if (!empty($lesson['progress']) && $lesson['progress'] > 0): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-end mb-1">
                                                <span class="text-secondary" style="font-size: 0.7rem;">Progress</span>
                                                <span class="fw-bold text-white" style="font-size: 0.7rem;"><?= $lesson['progress'] ?>%</span>
                                            </div>
                                            <div class="progress bg-secondary bg-opacity-10 rounded-pill" style="height: 4px;">
                                                <div class="progress-bar bg-success rounded-pill" style="width: <?= $lesson['progress'] ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-10 mt-auto">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="d-flex align-items-center overflow-hidden">
                                                <img src="<?= Session::getAvatar() ?>" alt="Author" class="rounded-circle me-1 border border-secondary border-opacity-25" width="20" height="20">
                                                <span class="text-secondary text-truncate me-1" style="font-size: 0.75rem;"><?= htmlspecialchars($lesson['author'] ?? 'sathish46') ?></span>
                                            </div>
                                            <button class="btn btn-link text-secondary p-0 d-inline-flex align-items-center gap-1 text-decoration-none me-1" title="Like" onclick="event.stopPropagation();" style="font-size: 0.75rem;">
                                                <i class="bx <?= (!empty($lesson['progress']) && $lesson['progress'] > 0) ? 'bxs-heart text-danger' : 'bx-heart' ?> fs-6"></i> <span><?= (!empty($lesson['progress']) && $lesson['progress'] > 0) ? 1 : 0 ?></span>
                                            </button>
                                            <button class="btn btn-link text-secondary p-0 text-decoration-none me-1" title="Share" onclick="event.stopPropagation();" style="font-size: 0.85rem;">
                                                <i class="bx bx-share-alt"></i>
                                            </button>
                                            <button class="btn btn-link text-secondary p-0 text-decoration-none me-1" title="Reveal/Hint" onclick="event.stopPropagation();" style="font-size: 0.85rem;">
                                                <i class="bx bx-bulb"></i>
                                            </button>
                                        </div>
                                        <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-sm btn-success-gradient rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 fw-medium shadow-sm text-nowrap" style="font-size: 0.75rem;" onclick="event.stopPropagation();">
                                            <?= (!empty($lesson['progress']) && $lesson['progress'] > 0) ? 'Continue' : 'Start Learning' ?> <i class="bx bx-right-arrow-alt fs-6"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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

    // Make sample topic badges fill prompt with rich pre-prompt
    document.querySelectorAll('.topic-pill').forEach(badge => {
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', () => {
            if (promptInput) {
                const promptText = badge.getAttribute('data-prompt') || badge.textContent.trim();
                promptInput.value = promptText;
                promptInput.focus();
            }
        });
    });

    // Handle level selection dropdown clicks
    document.querySelectorAll('.level-select-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const chosenLevel = item.getAttribute('data-level');
            if (levelSelect) levelSelect.value = chosenLevel;
            const labelSpan = document.getElementById('aiLessonLevelLabel');
            if (labelSpan) labelSpan.textContent = chosenLevel;
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
        const level = levelSelect ? levelSelect.value : 'Advanced';

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

        fetch('/api/learnAI/generate_lesson', {
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
                    fetch(`/api/learnAI/job_status?request_id=${encodeURIComponent(data.request_id)}`)
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
