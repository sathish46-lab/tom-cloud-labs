                <div class="card-body p-0 chapter-card d-flex flex-column h-100" style="min-height: 0;">
                    <div class="flex-grow-1 overflow-x-hidden overflow-y-auto hide-scrollbar position-relative" style="min-height: 0;">
                        <div class="p-3 pb-0">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h3 id="currentLessonTitle" class="mb-1 fw-bold text-white"><?= htmlspecialchars($lesson['title']) ?></h3>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                        <span id="currentLessonLevel" class="opacity-75 text-secondary small"><?= htmlspecialchars($lesson['level'] ?? 'Intermediate') ?></span>
                                    </div>
                                </div>
                                <button class="btn btn-link p-0 toggle-chapter-complete ms-3 text-secondary hover-text-success" style="text-decoration: none;" title="Mark Complete">
                                    <i class="bx bx-check-circle fs-3"></i>
                                </button>
                                <button id="btnGenerateContent" class="btn btn-link p-0 regenerate-chapter-btn ms-2" style="text-decoration: none;" data-chapter-id="<?= $chapter_id ?>" title="Generate Content">
                                    <svg class="icon text-warning" style="height: 24px; width: 24px;">
                                        <use xlink:href="/assets/icons/sprites/free.svg#cil-loop-circular"></use>
                                    </svg>
                                </button>
                            </div>
                            <hr class="mb-0 border-secondary border-opacity-10">
                        </div>

                        <div id="map-container" style="display:none"></div>

                        <!-- Chapter container wrapper - holds multiple chapter divs -->
                        <div class="chapter-container-wrapper">
                            <div class="chapter-container p-3 position-relative" data-chapter-id="<?= $chapter_id ?>">
                                <!-- Generation Loading Skeleton -->
                                <div id="contentGeneratingStatus" class="d-none alert alert-dark border-secondary border-opacity-25 rounded-3 mb-4 d-flex align-items-center gap-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="small text-secondary">Senior Mentor is crafting practical tutorial material...</span>
                                </div>

                                <h1 class="fw-bold mb-4 text-white fs-3"><?= htmlspecialchars($chapter['title']) ?></h1>

                        <?php
                        $isDummyFallback = !empty($chapter['content']) && strpos($chapter['content'], 'Welcome to this chapter on') !== false;
                        $hasRealHtml = !empty($chapter['content_html']) && $chapter['content_html'] !== '...' && strpos($chapter['content_html'], 'Welcome to this chapter on') === false;
                        $hasRealMd = !empty($chapter['content']) && $chapter['content'] !== '...' && !$isDummyFallback;
                        ?>
                        <div id="chapterContentContainer" class="chapter-text-content lh-lg" style="font-size: 1.05rem;" data-raw-md="<?= htmlspecialchars((!$hasRealHtml && $hasRealMd) ? $chapter['content'] : '') ?>">
                            <?php if ($hasRealHtml): ?>
                                <?= $chapter['content_html'] ?>
                            <?php elseif ($hasRealMd): ?>
                                <div class="raw-markdown-fallback"><?= htmlspecialchars($chapter['content']) ?></div>
                            <?php else: ?>
                                <div id="emptyContentPrompt" class="text-center py-5 my-4">
                                    <div class="mb-3">
                                        <i class="bx bx-book-open text-secondary opacity-50" style="font-size: 3.5rem;"></i>
                                    </div>
                                    <h5 class="fw-bold text-white mb-2">Ready to Learn?</h5>
                                    <p class="text-secondary small mb-4">Click below to generate practical, human-like tutorial content with live code blocks.</p>
                                    <button class="btn btn-primary rounded-pill px-4 btn-trigger-generate">
                                        <i class="bx bx-magic-wand me-1"></i> Generate Chapter Material
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
