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

$userObj = Session::getUser();
$userUiPrefs = $userObj ? ($userObj->getUiPreferences() ?? []) : [];
$dbSizesRaw = $userUiPrefs['learnAiThreePanelSizes'] ?? null;
$dbSizesArr = is_string($dbSizesRaw) ? json_decode($dbSizesRaw, true) : $dbSizesRaw;
?>
<?php if (is_array($dbSizesArr) && count($dbSizesArr) === 3): ?>
<style>
:root {
    --outlineSidebar-saved-width: <?= round($dbSizesArr[0], 2) ?>%;
    --courseSidebar-saved-width: <?= round($dbSizesArr[0], 2) ?>%;
    --paneAI-saved-width: <?= round($dbSizesArr[2], 2) ?>%;
}
</style>
<?php endif; ?>

<script>
// Anti-Flicker: Apply saved pane widths and exact viewport height immediately before paint
(function() {
    const prefs = (window.TOM_CONFIG && window.TOM_CONFIG.ui_preferences) || {};

    let threePanelSizes = sessionStorage.getItem('learnAiThreePanelSizes') || prefs['learnAiThreePanelSizes'];
    if (threePanelSizes && typeof threePanelSizes !== 'string') threePanelSizes = JSON.stringify(threePanelSizes);
    if (threePanelSizes) {
        sessionStorage.setItem('learnAiThreePanelSizes', threePanelSizes);
        try {
            const arr = JSON.parse(threePanelSizes);
            if (Array.isArray(arr) && arr.length === 3) {
                document.documentElement.style.setProperty('--courseSidebar-saved-width', arr[0] + '%');
                document.documentElement.style.setProperty('--outlineSidebar-saved-width', arr[0] + '%');
                document.documentElement.style.setProperty('--paneAI-saved-width', arr[2] + '%');

                const isExp = (arr[0] / 100) * window.innerWidth > 175;
                const st = document.createElement('style');
                st.id = 'learn-panel-zero-flicker';
                st.innerHTML = `
                    #learn-panel-1 {
                        width: ${arr[0]}% !important;
                        flex-basis: ${arr[0]}% !important;
                    }
                    #learn-panel-2 {
                        width: calc(100% - ${arr[0]}% - ${arr[2]}% - 8px) !important;
                        flex-basis: calc(100% - ${arr[0]}% - ${arr[2]}% - 8px) !important;
                    }
                    #learn-panel-3 {
                        width: ${arr[2]}% !important;
                        flex-basis: ${arr[2]}% !important;
                    }
                    ${isExp ? `
                    #learn-panel-1 .outline-compact { display: none !important; }
                    #learn-panel-1 .outline-full { display: flex !important; }
                    ` : `
                    #learn-panel-1 .outline-full { display: none !important; }
                    #learn-panel-1 .outline-compact { display: flex !important; }
                    `}
                `;
                document.head.appendChild(st);
            }
        } catch (e) {}
    }

    const headerHeight = 64; // 4rem header height
    const footerHeight = 38; // footer height
    document.documentElement.style.setProperty('--app-height', (window.innerHeight - headerHeight - footerHeight) + 'px');
})();
</script>

<div class="learn-app-wrapper stable-app-view d-flex flex-column overflow-hidden bg-transparent" style="height: var(--app-height, 75vh);">
    <div class="flex-grow-1 d-flex flex-row overflow-hidden p-2 gap-0">
        <!-- Panel 1: Collapsible Sidebar -->
        <div id="learn-panel-1" class="split-panel h-100" style="width: var(--outlineSidebar-saved-width, 68px); min-width: 68px;" data-state="collapsed">
            <script>
            (function(){
                var el = document.getElementById('learn-panel-1');
                if(!el) return;
                var prefs = (window.TOM_CONFIG && window.TOM_CONFIG.ui_preferences) || {};
                var sz = sessionStorage.getItem('learnAiThreePanelSizes') || prefs['learnAiThreePanelSizes'];
                if(sz && typeof sz !== 'string') sz = JSON.stringify(sz);
                var isExp = false;
                if(sz){
                    try{
                        var arr = JSON.parse(sz);
                        if(Array.isArray(arr) && arr.length === 3){
                            var w = (arr[0]/100)*(el.parentElement ? el.parentElement.offsetWidth : window.innerWidth);
                            if(w > 175) isExp = true;
                        }
                    }catch(e){}
                }
                if(isExp){
                    el.setAttribute('data-state', 'expanded');
                    el.classList.remove('auto-compact');
                } else {
                    el.setAttribute('data-state', 'collapsed');
                    el.classList.add('auto-compact');
                }
            })();
            </script>
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-header fs-6 d-flex justify-content-between align-items-center py-2 px-3">
                    <strong class="text-truncate" title="<?= htmlspecialchars($lesson['title']) ?>"><?= htmlspecialchars($lesson['title']) ?></strong>
                    <button class="btn btn-link p-0 text-secondary like-lesson-btn" title="Like this lesson">
                        <i class="bx bx-heart fs-5"></i>
                    </button>
                </div>
                <div class="card-body p-0 overflow-x-auto d-flex flex-column">
                    <!-- Top section: controls and chapters -->
                    <div class="lesson-chapters-section">
                        <div class="d-flex justify-content-center align-items-center p-2 lesson-controls gap-2 border-bottom border-secondary border-opacity-10">
                            <!-- Like button (shown in compact mode via sna.css) -->
                            <button class="btn btn-link p-0 text-secondary like-lesson-btn like-lesson-btn-compact" data-tooltip="Like Lesson">
                                <i class="bx bx-heart fs-5"></i>
                            </button>
                            <!-- Circular progress indicator -->
                            <div class="d-flex align-items-center progress-section gap-2">
                                <div class="circular-progress d-flex align-items-center justify-content-center position-relative" style="width: 36px; height: 36px;">
                                    <svg width="36" height="36" viewBox="0 0 45 45">
                                        <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="3"></circle>
                                        <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="var(--cui-primary)" stroke-width="3" stroke-dasharray="113" stroke-dashoffset="<?= 113 - (113 * ($lesson['progress'] ?? 35) / 100) ?>" stroke-linecap="round" transform="rotate(-90 22.5 22.5)"></circle>
                                    </svg>
                                    <span class="position-absolute small fw-bold" style="font-size: 0.6rem;"><?= $lesson['progress'] ?? 35 ?>%</span>
                                </div>
                                <span class="small fw-bold text-light progress-label">Completed</span>
                            </div>
                            <!-- View switcher buttons -->
                            <div class="view-buttons d-flex align-items-center">
                                <button class="btn btn-sm btn-dark border border-secondary border-opacity-25 rounded-pill d-flex align-items-center learn-switch-btn active" data-tooltip="Outline">
                                    <i class="bx bx-list-ul icon"></i> <span class="btn-label ms-1">Outline</span>
                                </button>
                                <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-sm btn-outline-secondary border-0 rounded-pill d-flex align-items-center learn-switch-btn text-secondary" data-tooltip="Map">
                                    <i class="bx bx-share-alt icon"></i> <span class="btn-label ms-1">Map</span>
                                </a>
                            </div>
                        </div>

                        <div class="accordion accordion-flush" id="accordionFlushExample">
                            <?php $mod_idx = 1; foreach ($modules as $mod_name => $mod_chapters): ?>
                                <div class="accordion-item bg-transparent border-bottom border-secondary border-opacity-10">
                                    <h2 class="accordion-header m-0">
                                        <button class="accordion-button bg-transparent text-white py-2 px-3 d-flex align-items-center shadow-none <?= $mod_idx > 1 ? 'collapsed' : '' ?>" type="button" data-coreui-toggle="collapse" data-coreui-target="#flush-collapse<?= $mod_idx ?>" aria-expanded="<?= $mod_idx === 1 ? 'true' : 'false' ?>" aria-controls="flush-collapse<?= $mod_idx ?>">
                                            <span class="accordion-num badge rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" data-tooltip="<?= htmlspecialchars($mod_name) ?>"><?= $mod_idx ?></span>
                                            <span class="module-title-text"><?= htmlspecialchars($mod_name) ?></span>
                                        </button>
                                    </h2>
                                    <div id="flush-collapse<?= $mod_idx ?>" class="accordion-collapse collapse <?= $mod_idx === 1 ? 'show' : '' ?>" data-coreui-parent="#accordionFlushExample">
                                        <div class="accordion-body p-1">
                                            <?php foreach ($mod_chapters as $chap): ?>
                                                <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" class="btn btn-sm d-flex align-items-center justify-content-between w-100 text-start py-2 px-2 rounded mb-1 learn-accordion-btn text-secondary" data-tooltip="<?= htmlspecialchars($chap['title']) ?>">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="chapter-num-btn badge rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 24px; height: 24px;"><?= $chap['order'] ?></span>
                                                        <h6 class="m-0 small chapter-title-text text-secondary"><?= htmlspecialchars($chap['title']) ?></h6>
                                                    </div>
                                                    <i class="bx bx-check-circle opacity-50 icon"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php $mod_idx++; endforeach; ?>
                        </div>
                    </div>

                    <!-- Bottom Lab Section / Actions matching SNA -->
                    <div class="lesson-lab-section mt-auto d-flex flex-column align-items-center gap-3 pt-3 border-top border-secondary border-opacity-10 w-100">
                        <i class="bx bxl-ubuntu text-warning fs-4"></i>
                        <i class="bx bx-terminal text-secondary fs-4"></i>
                        <i class="bx bx-info-circle text-secondary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resizer 1 (Gutter) -->
        <div class="gutter gutter-horizontal pane-resizer h-100" style="width: 4px;" data-target="learn-panel-1"></div>

        <!-- Panel 2: Center Course Overview Area -->
        <div id="learn-panel-2" class="split-panel flex-grow-1 h-100 overflow-hidden" style="width: calc(100% - 72px);">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-body p-4 p-lg-5 flex-grow-1 overflow-auto custom-scrollbar">
                    <!-- Course Header -->
                    <div class="mb-4">
                        <h2 class="fw-bold text-white mb-1"><?= htmlspecialchars($lesson['title']) ?></h2>
                        <span class="text-secondary small"><?= htmlspecialchars($lesson['level'] ?? 'Intermediate') ?></span>
                    </div>

                    <!-- Modules & Chapters List matching exact SNA design -->
                    <div class="modules-overview">
                        <?php $mod_index = 1; foreach ($modules as $mod_name => $mod_chapters): ?>
                            <div class="mb-4">
                                <h5 class="fw-bold text-white mb-3"><?= $mod_index ?>. <?= htmlspecialchars($mod_name) ?></h5>
                                <div class="card bg-dark bg-opacity-25 border border-secondary border-opacity-10 rounded-4 overflow-hidden">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($mod_chapters as $chap): ?>
                                            <div class="list-group-item bg-transparent border-bottom border-secondary border-opacity-10 py-3 px-4 d-flex align-items-center justify-content-between chapter-row">
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                        <?= $chap['order'] ?>
                                                    </span>
                                                    <h6 class="fw-medium text-white mb-0"><?= htmlspecialchars($chap['title']) ?></h6>
                                                </div>
                                                <div class="d-flex align-items-center gap-3">
                                                    <i class="bx <?= ($chap['status'] ?? '') == 'completed' ? 'bxs-check-circle text-success' : 'bx-check-circle text-secondary' ?> fs-5"></i>
                                                    <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                        <?= ($chap['status'] ?? '') == 'completed' ? 'Review' : 'Continue' ?> <i class="bx bx-right-arrow-alt ms-1"></i>
                                                    </a>
                                                </div>
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
