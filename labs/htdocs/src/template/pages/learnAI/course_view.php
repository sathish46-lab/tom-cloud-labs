<?php
$db = DatabaseConnection::getDefaultDatabase();
$lesson_id = $_GET['id'] ?? null;
$lesson = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lesson_id)]);
$chapters = $db->ai_chapters->find(['lesson_id' => new MongoDB\BSON\ObjectId($lesson_id)], ['sort' => ['order' => 1]])->toArray();

// Group chapters by module
$modules = [];
foreach ($chapters as $chapter) {
    $modules[$chapter['module_name']][] = $chapter;
}
?>

<script>
// Anti-Flicker: Apply saved pane widths immediately without blocking JS
(function() {
    ['outlineSidebar', 'courseSidebar', 'paneAI'].forEach(id => {
        const savedWidth = localStorage.getItem('learn-pane-' + id);
        if (savedWidth) {
            document.documentElement.style.setProperty('--' + id + '-saved-width', savedWidth + 'px');
        }
    });
})();
</script>

<div class="learn-app-wrapper stable-app-view d-flex flex-column overflow-hidden bg-transparent" style="height: var(--app-height, 75vh);">
    <div class="flex-grow-1 d-flex flex-row overflow-hidden p-0 gap-0">
        <!-- Sidebar Navigation - Stable -->
        <div id="courseSidebar" class="pane-sidebar d-flex flex-column h-100 transition-all" style="width: var(--courseSidebar-saved-width, 380px); min-width: 300px;">
            <div class="card bg-dark border-secondary border-opacity-10 rounded-4 shadow-sm h-100 d-flex flex-column overflow-hidden">
                <div class="card-body p-4 flex-grow-1 overflow-auto custom-scrollbar">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title fw-bold m-0"><?= $lesson['title'] ?></h5>
                        <button class="btn btn-sm btn-outline-secondary rounded-circle border-opacity-10"><i class="bx bx-heart"></i></button>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-success" style="font-size: 0.75rem;"><i class="bx bxs-check-circle me-1"></i> <?= $lesson['progress'] ?>% Completed</span>
                        </div>
                        <div class="progress bg-secondary bg-opacity-25 rounded-pill" style="height: 5px;">
                            <div class="progress-bar bg-success rounded-pill" style="width: <?= $lesson['progress'] ?>%"></div>
                        </div>
                    </div>

                    <div class="accordion accordion-flush" id="courseOutline">
                        <?php $mod_index = 1; foreach ($modules as $mod_name => $mod_chapters): ?>
                            <div class="accordion-item bg-transparent text-white border-secondary border-opacity-10">
                                <h2 class="accordion-header" id="flush-heading-<?= $mod_index ?>">
                                    <button class="accordion-button collapsed bg-transparent text-white shadow-none px-0 py-3 small" type="button" data-coreui-toggle="collapse" data-coreui-target="#flush-collapse-<?= $mod_index ?>">
                                        <span class="badge bg-secondary rounded-circle me-2 d-inline-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 0.7rem;"><?= $mod_index ?></span>
                                        <span class="fw-bold text-truncate" style="max-width: 200px;"><?= $mod_name ?></span>
                                    </button>
                                </h2>
                                <div id="flush-collapse-<?= $mod_index ?>" class="accordion-collapse collapse" data-coreui-parent="#courseOutline">
                                    <div class="accordion-body px-0 py-2">
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($mod_chapters as $chap): ?>
                                                <div class="list-group-item bg-transparent border-0 px-3 py-2 d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center overflow-hidden">
                                                        <i class="bx <?= $chap['status'] == 'completed' ? 'bxs-check-circle text-success' : 'bx-circle text-secondary' ?> me-2"></i>
                                                        <span class="small text-truncate <?= $chap['status'] == 'completed' ? 'text-white' : 'text-secondary' ?>"><?= $chap['order'] ?>. <?= $chap['title'] ?></span>
                                                    </div>
                                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" class="btn btn-link btn-sm p-0 m-0 text-decoration-none shadow-none ms-2">
                                                        <i class="bx bx-right-arrow-alt"></i>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $mod_index++; endforeach; ?>
                    </div>

                    <div class="mt-4 pt-4 border-top border-secondary border-opacity-10">
                        <h6 class="fw-bold mb-3 small">Required Lab</h6>
                        <div class="card bg-black bg-opacity-25 border-secondary border-opacity-10 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small">Essentials Lab</span>
                                    <i class="bx bxl-ubuntu text-warning"></i>
                                </div>
                                <div class="text-secondary small mb-3">172.30.0.28</div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10" style="font-size: 0.6rem;">beta</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10" style="font-size: 0.6rem;">running</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resizer -->
        <div class="pane-resizer h-100" data-target="courseSidebar"></div>

        <!-- Main Content - Stable Scrollable Card -->
        <div id="courseMain" class="pane-main flex-grow-1 h-100 d-flex flex-column overflow-hidden">
            <div class="card bg-dark border-secondary border-opacity-10 rounded-4 shadow-sm h-100 d-flex flex-column overflow-hidden">
                <div class="card-body p-4 p-lg-5 flex-grow-1 overflow-auto custom-scrollbar">
                    <h1 class="card-title fw-bold mb-2 display-6"><?= $lesson['title'] ?></h1>
                    <div class="text-secondary mb-5 small"><?= $lesson['level'] ?></div>

                    <div class="modules-overview">
                        <?php $mod_index = 1; foreach ($modules as $mod_name => $mod_chapters): ?>
                            <div class="mb-5">
                                <h3 class="fw-bold mb-4 border-bottom border-secondary border-opacity-10 pb-3"><?= $mod_name ?></h3>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($mod_chapters as $chap): ?>
                                        <div class="list-group-item bg-transparent border-secondary border-opacity-5 px-0 py-3 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="text-secondary me-3 fw-bold"><?= $chap['order'] ?>.</span>
                                                <span class="fw-bold"><?= $chap['title'] ?></span>
                                                <?php if ($chap['status'] == 'completed'): ?>
                                                    <i class="bx bxs-check-circle text-success ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" class="btn btn-sm btn-outline-secondary px-4 transition-all">
                                                <?= $chap['status'] == 'completed' ? 'Review' : 'Start' ?> →
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $mod_index++; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.learn-app-wrapper { background: transparent; }
.transition-all { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }

.pane-sidebar, .pane-main { 
    min-width: 0 !important; 
    flex-shrink: 0;
}
.pane-resizer {
    width: 16px;
    margin: 0 -8px;
    background: transparent;
    cursor: col-resize;
    z-index: 1000;
    transition: background 0.2s;
    position: relative;
    flex-shrink: 0;
}
.pane-resizer:hover, .pane-resizer.is-dragging {
    background: rgba(var(--cui-primary-rgb, 50, 31, 219), 0.1);
}
.pane-resizer::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 4px;
    height: 48px;
    background: rgba(255,255,255,0.08);
    border-radius: 10px;
    transition: all 0.2s;
}
.pane-resizer:hover::after, .pane-resizer.is-dragging::after {
    background: var(--cui-primary, #321fdb);
}

/* Custom Scrollbars */
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

/* Responsive Adjustments */
@media (max-width: 991.98px) {
    .learn-app-wrapper { height: auto !important; min-height: 100vh; overflow: auto !important; }
    body { overflow: auto !important; }
    .flex-row { flex-direction: column !important; }
    .pane-sidebar, .pane-main { width: 100% !important; height: auto !important; min-width: 0 !important; }
    .pane-resizer { display: none !important; }
    .pane-main { min-height: 75vh !important; }
}
</style>
