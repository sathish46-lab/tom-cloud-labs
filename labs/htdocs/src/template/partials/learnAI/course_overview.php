                <div class="card-body p-0 flex-grow-1 overflow-auto hide-scrollbar">
                    <!-- Course Header -->
                    <div class="p-3 pb-0">
                        <h2 class="fw-bold text-white mb-1"><?= htmlspecialchars($lesson['title']) ?></h2>
                        <span class="text-secondary small"><?= htmlspecialchars($lesson['level'] ?? 'Intermediate') ?></span>
                    </div>

                    <!-- Modules & Chapters List matching exact SNA design -->
                    <div class="modules-overview p-3 pt-3">
                        <?php $mod_index = 1; foreach ($modules as $mod_name => $mod_chapters): ?>
                            <div class="mb-4">
                                <?php $clean_mod_name = preg_replace('/^\d+[\.\)]\s*/', '', $mod_name); ?>
                                <h5 class="fw-bold text-white mb-3"><?= $mod_index ?>. <?= htmlspecialchars($clean_mod_name) ?></h5>
                                <div class="card bg-dark bg-opacity-25 border border-secondary border-opacity-10 rounded-4 overflow-hidden">
                                    <div class="list-group list-group-flush">
                                        <?php $chap_index = 1; foreach ($mod_chapters as $chap): ?>
                                            <?php $clean_chap_title = preg_replace('/^\d+[\.\)]\s*/', '', $chap['title']); ?>
                                            <div class="list-group-item bg-transparent border-bottom border-secondary border-opacity-10 py-3 px-4 d-flex align-items-center justify-content-between chapter-row">
                                                <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                    <h6 class="fw-medium text-white mb-0 fs-6"><?= $chap_index++ ?>. <?= htmlspecialchars($clean_chap_title) ?></h6>
                                                </div>
                                                <div class="d-flex align-items-center gap-3">
                                                    <i class="bx <?= ($chap['status'] ?? '') == 'completed' ? 'bxs-check-circle text-success' : 'bx-check-circle text-secondary' ?> fs-5"></i>
                                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" hx-boost="false" class="btn btn-sm btn-outline-primary rounded-pill px-3 ajax-chapter-load">
                                                        <?= ($chap['status'] ?? '') == 'completed' ? 'Review &rarr;' : 'Continue &rarr;' ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php $mod_index++; endforeach; ?>
                    </div>
                </div>
