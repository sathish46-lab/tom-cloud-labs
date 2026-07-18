<?php
$db = DatabaseConnection::getDefaultDatabase();
$lessonsRaw = $db->ai_lessons->find([], ['sort' => ['_id' => -1]])->toArray();
$user = Session::getUser();
$currentUsername = $user ? $user->getUsername() : '';
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$lessons = [];
$seen = [];
foreach ($lessonsRaw as $l) {
    $title = $l['title'] ?? '';
    if (!isset($seen[$title])) {
        // Determine if current logged-in user is the author
        $isAuthor = false;
        if (!empty($l['author']) && strcasecmp($l['author'], $currentUsername) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['author_email']) && strcasecmp($l['author_email'], $currentEmail) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['user_id']) && (int)$l['user_id'] === $currentUserId && $currentUserId > 0) {
            $isAuthor = true;
        }

        // Check visibility: if Private, only the created author should see it
        $visibility = $l['visibility'] ?? 'Public';
        if (strcasecmp($visibility, 'Private') === 0 && !$isAuthor) {
            continue;
        }

        // Compute likes count and whether current user liked it
        $likesList = $l['likes'] ?? [];
        if (!is_array($likesList)) {
            $likesList = is_object($likesList) && method_exists($likesList, 'getArrayCopy') ? $likesList->getArrayCopy() : (array)$likesList;
        }
        $likesCount = intval($l['likes_count'] ?? count($likesList));
        $likedByCurrent = false;
        if (in_array($currentUsername, $likesList) || ($currentUserId > 0 && in_array((string)$currentUserId, $likesList))) {
            $likedByCurrent = true;
        } else {
            try {
                if (!empty($currentUsername) && $db->ai_lesson_likes->findOne(['lesson_id' => (string)$l['_id'], 'username' => $currentUsername])) {
                    $likedByCurrent = true;
                }
            } catch (Throwable $t) {}
        }
        $l['likes_count'] = $likesCount;
        $l['liked_by_current'] = $likedByCurrent;

        $seen[$title] = true;
        $lessons[] = $l;
    }
}
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
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3 lesson-tabs-container">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 active text-white d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="all"><span class="fs-6">✨</span> For You</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="continue"><span class="fs-6">📚</span> Continue</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="explore"><span class="fs-6">🌏</span> Explore</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="most_liked"><span class="fs-6">❤️‍🔥</span> Most Liked</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="editor_picks"><span class="fs-6">⭐</span> Editor Picks</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="interacted"><span class="fs-6">🔥</span> Most Interacted</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="my_likes"><span class="fs-6">❤️</span> My Likes</button>
                    <button class="btn btn-xs btn-outline-secondary rounded-pill border-opacity-25 px-3 py-1 text-secondary d-inline-flex align-items-center gap-1 lesson-filter-btn" data-filter="my_lessons"><span class="fs-6">👤</span> My Lessons</button>
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
                        <?php
                        $isAuthor = false;
                        if (!empty($lesson['author']) && strcasecmp($lesson['author'], $currentUsername) === 0) {
                            $isAuthor = true;
                        } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $currentEmail) === 0) {
                            $isAuthor = true;
                        } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $currentUserId && $currentUserId > 0) {
                            $isAuthor = true;
                        }
                        $visibility = $lesson['visibility'] ?? 'Public';
                        $isPrivate = strcasecmp($visibility, 'Private') === 0;
                        ?>
                        <div class="col lesson-grid-item" data-is-author="<?= $isAuthor ? '1' : '0' ?>" data-visibility="<?= htmlspecialchars($visibility) ?>" data-liked="<?= !empty($lesson['liked_by_current']) ? 'true' : 'false' ?>" data-likes-count="<?= intval($lesson['likes_count'] ?? 0) ?>">
                            <div class="card liquid-rim lesson-card h-100 hvr-grow" data-lesson-id="<?= $lesson['_id'] ?>">
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
                                            <span class="badge bg-<?= $isPrivate ? 'secondary' : 'info' ?>-gradient d-inline-flex align-items-center gap-1 lesson-visibility-badge">
                                                <i class="bx <?= $isPrivate ? 'bx-lock-alt' : 'bx-globe' ?>"></i> <span class="visibility-text"><?= htmlspecialchars($isPrivate ? 'Private' : 'Public') ?></span>
                                            </span>
                                            <span class="badge bg-primary-gradient d-inline-flex align-items-center gap-1">
                                                <i class="bx bx-search"></i> auto-matched - <?= $matchPct ?>%
                                            </span>
                                        </div>
                                        <div class="dropdown ms-1">
                                            <button class="btn btn-link text-secondary p-0" data-coreui-toggle="dropdown">
                                                <i class="bx bx-cog"></i>
                                                <i class="bx bx-caret-down small"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end blur shadow-sm border-secondary border-opacity-25">
                                                <li><a class="dropdown-item small py-1" href="/learn/lesson/<?= $lesson['_id'] ?>"><i class="bx bx-play me-2"></i>Start Lesson</a></li>
                                                <?php if ($isAuthor): ?>
                                                    <li class="visibility-toggle-item">
                                                        <?php if ($isPrivate): ?>
                                                        <a class="dropdown-item small py-1 visibility-toggle-action" href="#" data-lesson-id="<?= $lesson['_id'] ?>" data-target-visibility="Public"><i class="bx bx-globe me-2 text-info"></i>Make Public</a>
                                                        <?php else: ?>
                                                        <a class="dropdown-item small py-1 visibility-toggle-action" href="#" data-lesson-id="<?= $lesson['_id'] ?>" data-target-visibility="Private"><i class="bx bx-lock-alt me-2 text-warning"></i>Make Private</a>
                                                        <?php endif; ?>
                                                    </li>
                                                    <li><hr class="dropdown-divider border-secondary border-opacity-25 my-1"></li>
                                                    <li><a class="dropdown-item small py-1 text-danger delete-lesson-action" href="#" data-lesson-id="<?= $lesson['_id'] ?>" data-lesson-title="<?= htmlspecialchars($lesson['title'] ?? '', ENT_QUOTES) ?>"><i class="bx bx-trash me-2"></i>Delete Lesson</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="text-decoration-none text-white d-block mb-2">
                                        <h6 class="card-title fw-bold mb-2 text-white"><?= htmlspecialchars($lesson['title'] ?? '') ?></h6>
                                    </a>
                                    <p class="card-text text-secondary mb-3 flex-grow-1 small lesson-desc-clamp"><?= htmlspecialchars($lesson['description'] ?? 'An interactive AI-generated structured curriculum covering architectural foundations, practical exercises, and hands-on laboratory tasks.') ?></p>

                                    <div class="d-flex align-items-center gap-3 text-secondary mb-2 small">
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
                                                <span class="text-secondary small">Progress</span>
                                                <span class="fw-bold text-white small"><?= $lesson['progress'] ?>%</span>
                                            </div>
                                            <div class="progress bg-secondary bg-opacity-10 rounded-pill" style="height: 4px;">
                                                <div class="progress-bar bg-success rounded-pill" style="width: <?= $lesson['progress'] ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-10 mt-auto">
                                        <div class="d-flex align-items-center gap-1 min-w-0 me-1">
                                            <div class="d-flex align-items-center min-w-0">
                                                <img src="<?= Session::getAvatarForUsername($lesson['username'] ?? $lesson['author'] ?? '') ?>" alt="Author" class="rounded-circle me-1 flex-shrink-0 border border-secondary border-opacity-25" width="18" height="18">
                                                <span class="text-secondary text-truncate small" style="max-width: 60px; font-size: 0.75rem;"><?= htmlspecialchars($lesson['author'] ?? 'sathish46') ?></span>
                                            </div>
                                            <button class="btn btn-link text-secondary p-0 d-inline-flex align-items-center gap-1 text-decoration-none toggle-like-btn flex-shrink-0 ms-1" data-lesson-id="<?= $lesson['_id'] ?>" title="Like">
                                                <i class="bx <?= !empty($lesson['liked_by_current']) ? 'bxs-heart text-danger' : 'bx-heart' ?> fs-6"></i>
                                                <span class="lesson-like-count" style="font-size: 0.75rem;"><?= intval($lesson['likes_count'] ?? 0) ?></span>
                                            </button>
                                            <button class="btn btn-link text-secondary p-0 text-decoration-none share-lesson-btn flex-shrink-0 ms-1" data-lesson-id="<?= $lesson['_id'] ?>" data-lesson-title="<?= htmlspecialchars($lesson['title'] ?? '', ENT_QUOTES) ?>" title="Share lesson">
                                                <i class="bx bx-share-alt fs-6"></i>
                                            </button>
                                            <button class="btn btn-link text-secondary p-0 text-decoration-none reveal-hint-btn flex-shrink-0 ms-1" title="Reveal/Hint">
                                                <i class="bx bx-bulb fs-6"></i>
                                            </button>
                                        </div>
                                        <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-sm btn-success-gradient rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1 fw-medium shadow-sm text-nowrap flex-shrink-0" style="font-size: 0.78rem;">
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
