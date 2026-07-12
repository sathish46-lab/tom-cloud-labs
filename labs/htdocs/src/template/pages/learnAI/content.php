<?php
$db = DatabaseConnection::getDefaultDatabase();
$chapter_id = $_GET['id'] ?? '';
$chapter = null;
if ($chapter_id) {
    try {
        $chapter = $db->ai_chapters->findOne(['_id' => new MongoDB\BSON\ObjectId($chapter_id)]);
    } catch (Exception $e) {}
}

$lesson = $chapter ? $db->ai_lessons->findOne(['_id' => $chapter['lesson_id']]) : null;
$chapters = $lesson ? $db->ai_chapters->find(['lesson_id' => $chapter['lesson_id']], ['sort' => ['order' => 1]])->toArray() : [];
$modules = [];
foreach ($chapters as $chapItem) {
    $modName = !empty($chapItem['module_name']) ? $chapItem['module_name'] : 'General Content';
    $modules[$modName][] = $chapItem;
}

$userId = (int)Session::getUser()->getUserId();
$userId = (int)Session::getUser()->getUserId();

// Note: Chat history is now loaded asynchronously via fetch in learnAI.js

$userAvatar = Session::getAvatar();
$userAvatarStyle = Session::getAvatarStyle();
$aiAvatar = "/assets/logo/logo.png";

$userObj = Session::getUser();
$userUiPrefs = $userObj ? ($userObj->getUiPreferences() ?? []) : [];
$dbSizesRaw = $userUiPrefs['learnAiThreePanelSizes'] ?? null;
$dbSizesArr = is_string($dbSizesRaw) ? json_decode($dbSizesRaw, true) : $dbSizesRaw;
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<?php if (is_array($dbSizesArr) && count($dbSizesArr) === 3): ?>
<style>
:root {
    --outlineSidebar-saved-width: <?= round($dbSizesArr[0], 2) ?>%;
    --courseSidebar-saved-width: <?= round($dbSizesArr[0], 2) ?>%;
    --paneAI-saved-width: <?= max(25, round($dbSizesArr[2], 2)) ?>%;
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
                document.documentElement.style.setProperty('--outlineSidebar-saved-width', arr[0] + '%');
                document.documentElement.style.setProperty('--courseSidebar-saved-width', arr[0] + '%');
                const p3Pct = (arr[2] && arr[2] > 12) ? arr[2] : 25;
                document.documentElement.style.setProperty('--paneAI-saved-width', p3Pct + '%');

                const isExp = (arr[0] / 100) * window.innerWidth > 175;
                const st = document.createElement('style');
                st.id = 'learn-panel-zero-flicker';
                st.innerHTML = `
                    #learn-panel-1 {
                        width: ${arr[0]}% !important;
                        flex-basis: ${arr[0]}% !important;
                    }
                    #learn-panel-2 {
                        width: calc(100% - ${arr[0]}% - ${p3Pct}% - 8px) !important;
                        flex-basis: calc(100% - ${arr[0]}% - ${p3Pct}% - 8px) !important;
                    }
                    #learn-panel-3 {
                        width: ${p3Pct}% !important;
                        flex-basis: ${p3Pct}% !important;
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

    function updateAppHeight() {
        const header = document.querySelector('header.header');
        const footer = document.querySelector('footer.footer');
        const hHeight = header ? header.offsetHeight : 64;
        const fHeight = footer ? footer.offsetHeight : 38;
        document.documentElement.style.setProperty('--app-height', Math.max(400, window.innerHeight - hHeight - fHeight) + 'px');
    }
    updateAppHeight();
    window.addEventListener('resize', updateAppHeight);
})();
</script>

<div class="learn-app-wrapper stable-app-view d-flex flex-column overflow-hidden bg-transparent" style="height: var(--app-height, 75vh);">
    <!-- Main App Body -->
    <div class="flex-grow-1 d-flex flex-row overflow-hidden p-2 gap-0">
        
<?php
$isPanel1Expanded = false;
if (is_array($dbSizesArr) && count($dbSizesArr) === 3 && $dbSizesArr[0] > 10) {
    $isPanel1Expanded = true;
}
$panel1State = $isPanel1Expanded ? 'expanded' : 'collapsed';
$panel1Class = $isPanel1Expanded ? '' : 'auto-compact';
?>
        <!-- Panel 1: Collapsible Sidebar -->
        <div id="learn-panel-1" class="split-panel h-100 <?= $panel1Class ?>" style="width: var(--outlineSidebar-saved-width, 68px); min-width: 68px;" data-state="<?= $panel1State ?>">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-header fs-6 d-flex justify-content-between align-items-center py-2 px-3">
                    <strong class="text-truncate" title="<?= htmlspecialchars($lesson['title']) ?>"><?= htmlspecialchars($lesson['title']) ?></strong>
                    <button class="btn btn-link p-0 text-secondary like-lesson-btn" title="Like this lesson">
                        <i class="bx bx-heart fs-5"></i>
                    </button>
                </div>
                <div class="card-body p-0 overflow-hidden d-flex flex-column">
                    <!-- Top section: controls and chapters (scrollable independently) -->
                    <div class="lesson-chapters-section flex-grow-1 overflow-y-auto custom-scrollbar">
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
                                            <?php $clean_mod_name = preg_replace('/^\d+[\.\)]\s*/', '', $mod_name); ?>
                                            <span class="accordion-num badge rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" data-tooltip="<?= htmlspecialchars($clean_mod_name) ?>"><?= $mod_idx ?></span>
                                            <span class="module-title-text"><?= htmlspecialchars($clean_mod_name) ?></span>
                                        </button>
                                    </h2>
                                    <div id="flush-collapse<?= $mod_idx ?>" class="accordion-collapse collapse <?= $mod_idx === 1 ? 'show' : '' ?>" data-coreui-parent="#accordionFlushExample">
                                        <div class="accordion-body p-1">
                                            <?php $chap_local_idx = 1; foreach ($mod_chapters as $chap): ?>
                                                <?php $clean_chap_title = preg_replace('/^\d+[\.\)]\s*/', '', $chap['title']); ?>
                                                <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" class="btn btn-sm d-flex align-items-center justify-content-between w-100 text-start py-2 px-2 rounded mb-1 learn-accordion-btn <?= ($chap['_id'] ?? '') == ($chapter['_id'] ?? '') ? 'active bg-primary bg-opacity-25 text-white fw-bold' : 'text-secondary' ?>" data-tooltip="<?= htmlspecialchars($clean_chap_title) ?>">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="chapter-num-btn badge rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 24px; height: 24px;"><?= $chap_local_idx++ ?></span>
                                                        <h6 class="m-0 small chapter-title-text <?= ($chap['_id'] ?? '') == ($chapter['_id'] ?? '') ? 'text-white' : 'text-secondary' ?>"><?= htmlspecialchars($clean_chap_title) ?></h6>
                                                    </div>
                                                    <i class="bx <?= ($chap['_id'] ?? '') == ($chapter['_id'] ?? '') ? 'bxs-check-circle text-primary' : 'bx-check-circle opacity-50' ?> icon"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php $mod_idx++; endforeach; ?>
                        </div>

                        <?php
                        $labsList = Session::get('labs_list', []);
                        $currentLab = null;
                        if (!empty($labsList)) {
                            foreach ($labsList as $lItem) {
                                if (($lItem['status'] ?? '') === 'running' && (stripos($lItem['name'] ?? '', 'Essential') !== false || ($lItem['id'] ?? '') === 'essentials')) {
                                    $currentLab = $lItem;
                                    break;
                                }
                            }
                            if (!$currentLab) {
                                foreach ($labsList as $lItem) {
                                    if (($lItem['status'] ?? '') === 'running') {
                                        $currentLab = $lItem;
                                        break;
                                    }
                                }
                            }
                            if (!$currentLab) $currentLab = $labsList[0];
                        }
                        if (!$currentLab) {
                            $currentLab = [
                                'name' => 'Essentials',
                                'ip' => '172.30.0.28',
                                'status' => 'running',
                                'is_public' => 'public',
                                'hash' => 'essentials-lab-id',
                                'icon' => 'tux',
                                'badges' => ['beta']
                            ];
                        }
                        $labName = $currentLab['name'] ?? 'Essentials';
                        $labIp = $currentLab['ip'] ?? '172.30.0.28';
                        $labStatus = $currentLab['status'] ?? 'running';
                        $labPublic = $currentLab['is_public'] ?? 'public';
                        $labHash = $currentLab['hash'] ?? 'essentials-lab-id';
                        $labBadges = $currentLab['badges'] ?? ['beta'];

                        $iconMap = [
                            'tux'    => 'bxl-tux',
                            'docker' => 'bxl-docker',
                            'git-repo-forked' => 'bx-git-repo-forked'
                        ];
                        $bxClass = $iconMap[$currentLab['icon'] ?? ''] ?? 'bxl-tux';
                        ?>

                        <!-- Bottom Lab Section scrolling naturally with chapters -->
                        <div class="lesson-lab-section mt-auto pt-3 border-top border-secondary border-opacity-10 w-100 px-2 pb-3">
                            <!-- EXPANDED FULL LAB CARD (Screenshot 1) -->
                            <div class="outline-full flex-column w-100">
                                <h6 class="small fw-bold text-white mb-2 px-1">Required Lab</h6>
                                <div class="lab-card-box rounded-4 overflow-hidden w-100 position-relative shadow-sm" style="background: rgba(45, 25, 30, 0.45); border: 1px solid rgba(255, 255, 255, 0.1);">
                                    <i class="bx <?= $bxClass ?> position-absolute end-0 top-50 translate-middle-y text-white opacity-10" style="font-size: 5.5rem; pointer-events: none;"></i>
                                    <div class="p-3 position-relative">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="fw-bold text-white fs-6"><?= htmlspecialchars($labName) ?> Lab</span>
                                            <i class="bx bx-info-circle small text-secondary ms-1"></i>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="font-monospace text-white fs-6"><?= htmlspecialchars($labIp) ?></span>
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-link p-1 text-secondary hover-text-white" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($labIp) ?>')" title="Copy IP"><i class="bx bx-copy fs-5"></i></button>
                                                <a href="/labs/dashboard/<?= htmlspecialchars($labHash) ?>" class="btn btn-link p-1 text-secondary hover-text-white" title="Open Lab"><i class="bx bx-share fs-5"></i></a>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <?php foreach ($labBadges as $badge): ?>
                                                <span class="badge rounded-pill px-2 py-1 small fw-bold" style="background-color: #6366f1 !important; color: #fff;"><?= htmlspecialchars($badge) ?></span>
                                            <?php endforeach; ?>
                                            <span class="badge rounded-pill bg-warning text-dark px-2 py-1 small fw-bold"><?= htmlspecialchars($labPublic) ?></span>
                                            <span class="badge rounded-pill bg-success text-white px-2 py-1 small fw-bold"><?= htmlspecialchars($labStatus) ?></span>
                                        </div>
                                    </div>
                                    <!-- Action Buttons Bar matching Screenshot 1 -->
                                    <div class="d-flex border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.85);">
                                        <button type="button" class="btn btn-link flex-fill py-2 text-white border-end border-secondary border-opacity-25 d-flex align-items-center justify-content-center" style="background-color: #6366f1;" onclick="openCodeModal('<?= $labHash ?>', '<?= addslashes($labName) ?> Lab', '<?= $labStatus ?>')" title="Terminal"><i class="bx bx-terminal fs-5"></i></button>
                                        <a href="/labs/dashboard/<?= htmlspecialchars($labHash) ?>" class="btn btn-link flex-fill py-2 text-white border-end border-secondary border-opacity-25 d-flex align-items-center justify-content-center" style="background-color: #10b981;" title="Ports & Services"><i class="bx bx-grid-alt fs-5"></i></a>
                                        <button type="button" class="btn btn-link flex-fill py-2 text-white d-flex align-items-center justify-content-center" style="background-color: #06b6d4;" onclick="openConnectionModal('<?= $labHash ?>', '<?= addslashes($labName) ?> Lab', '<?= $labStatus ?>')" title="Lab Info"><i class="bx bx-info-circle fs-5"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- COMPACT LAB ICONS ONLY (Screenshot 2) -->
                            <div class="outline-compact flex-column align-items-center gap-2 w-100">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; background: rgba(234, 88, 12, 0.2); border: 2px solid #ea580c;" data-tooltip="<?= htmlspecialchars($labName) ?> Lab (<?= htmlspecialchars($labIp) ?>)">
                                    <i class="bx <?= $bxClass ?> fs-4 text-white"></i>
                                </div>
                                <div class="d-flex flex-column rounded-pill p-1 gap-1 border border-secondary border-opacity-25 shadow-sm" style="background: rgba(15, 23, 42, 0.85);">
                                    <button type="button" class="btn btn-sm rounded-pill d-flex align-items-center justify-content-center p-0 text-white" style="background-color: #6366f1; width: 34px; height: 26px;" onclick="openCodeModal('<?= $labHash ?>', '<?= addslashes($labName) ?> Lab', '<?= $labStatus ?>')" data-tooltip="Terminal"><i class="bx bx-terminal fs-6"></i></button>
                                    <a href="/labs/dashboard/<?= htmlspecialchars($labHash) ?>" class="btn btn-sm rounded-pill d-flex align-items-center justify-content-center p-0 text-white" style="background-color: #10b981; width: 34px; height: 26px;" data-tooltip="Ports & Services"><i class="bx bx-grid-alt fs-6"></i></a>
                                    <button type="button" class="btn btn-sm rounded-pill d-flex align-items-center justify-content-center p-0 text-white" style="background-color: #06b6d4; width: 34px; height: 26px;" onclick="openConnectionModal('<?= $labHash ?>', '<?= addslashes($labName) ?> Lab', '<?= $labStatus ?>')" data-tooltip="Lab Info"><i class="bx bx-info-circle fs-6"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resizer 1 (Gutter) -->
        <div class="gutter gutter-horizontal pane-resizer h-100" style="width: 4px;" data-target="learn-panel-1"></div>

        <!-- Panel 2: Center Content Area -->
        <div id="learn-panel-2" class="split-panel flex-grow-1 h-100 overflow-hidden" style="width: calc(64% - 2px);">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-body p-0 overflow-x-auto chapter-card d-flex flex-column h-100">
                    <div class="p-3 pb-0 d-flex justify-content-between align-items-center border-bottom border-secondary border-opacity-10 flex-shrink-0">
                        <div class="py-2 d-flex align-items-center gap-3">
                            <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-circle p-2 d-flex align-items-center justify-content-center" title="Back to Course Overview" style="width: 34px; height: 34px;">
                                <i class="bx bx-left-arrow-alt fs-5"></i>
                            </a>
                            <div>
                                <span class="text-secondary small d-block mb-1" style="font-size: 0.75rem;"><?= htmlspecialchars($chapter['module_name']) ?></span>
                                <h4 class="fw-bold m-0 text-white"><?= htmlspecialchars($chapter['title']) ?></h4>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button id="btnGenerateContent" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-flex align-items-center gap-1 border-opacity-25" data-chapter-id="<?= $chapter_id ?>" title="Generate human-like tutorial content">
                                <i class="bx bx-magic-wand"></i> <span>Generate Content</span>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary rounded-circle border-opacity-10 p-2"><i class="bx bx-share-alt"></i></button>
                            <button class="btn btn-sm btn-outline-success rounded-circle border-opacity-10 p-2"><i class="bx bx-check"></i></button>
                        </div>
                    </div>

                    <div id="map-container" style="display:none"></div>

                    <div class="p-3 flex-grow-1 overflow-auto custom-scrollbar position-relative">
                        <!-- Generation Loading Skeleton -->
                        <div id="contentGeneratingStatus" class="d-none alert alert-dark border-secondary border-opacity-25 rounded-3 mb-4 d-flex align-items-center gap-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="small text-secondary">Senior Mentor is crafting practical tutorial material...</span>
                        </div>

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

        <!-- Resizer 2 (Gutter) -->
        <div class="gutter gutter-horizontal pane-resizer h-100" style="width: 4px;" data-target="learn-panel-3" data-direction="right"></div>

        <!-- Panel 3: Right AI Assistant -->
        <div id="learn-panel-3" class="split-panel pane-ai d-flex flex-column h-100" style="width: var(--paneAI-saved-width, 350px); min-width: 300px;">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-header bg-dark bg-opacity-25 border-secondary border-opacity-10 py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bx-bot text-primary"></i>
                        <h6 class="fw-bold m-0 small ls-1 text-uppercase">AI Assist</h6>
                        <span class="badge bg-warning text-dark" style="font-size: 0.55rem; padding: 0.2em 0.5em;">PRO</span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                         <select id="aiModelSelect" class="form-select form-select-sm bg-transparent text-secondary border-0 p-0 me-1" style="font-size: 0.7rem; width: auto; cursor: pointer;">
                             <option value="gemini">Gemini</option>
                             <option value="lm_studio">LM Studio</option>
                         </select>
                         <i class="bx bx-refresh text-secondary cursor-pointer fs-6 opacity-50 hover-opacity-100" title="Clear Chat" onclick="if(confirm('Clear?')) document.getElementById('aiChatHistory').innerHTML=''"></i>
                    </div>
                </div>
                <div class="card-body p-0 d-flex flex-column flex-grow-1 overflow-hidden position-relative">
                    <input type="hidden" id="currentLessonId" value="<?= $lesson['_id'] ?? '' ?>">
                    <input type="hidden" id="currentChapterId" value="<?= $chapter['_id'] ?? '' ?>">
                    <input type="hidden" id="userAvatarUrl" value="<?= $userAvatar ?>">
                    <input type="hidden" id="userAvatarStyle" value="<?= $userAvatarStyle ?>">
                    <input type="hidden" id="aiAvatarUrl" value="<?= $aiAvatar ?>">

                    <div id="aiChatHistory" class="chat-history flex-grow-1 overflow-auto custom-scrollbar p-2 d-flex flex-column gap-2">
                        <!-- Chat history will be loaded here via JS fetch -->
                    </div>

                    <!-- Bottom Input Bar (from sna.css) -->
                    <!-- Bottom Input Bar (from sna.css) -->
                    <div class="input-container">
                        <div class="unified-input-box simple-blur">
                            <textarea class="user-text-input" type="text" id="aiChatInput" placeholder="Ask AI ✨" rows="1" style="height: auto; resize: none;"></textarea>
                            
                            <div class="token-ribbon">
                                
                                <!-- Token Metrics -->
                                <div class="token-metrics" id="token-metrics">
                                    <div class="context-progress" data-coreui-toggle="tooltip" data-coreui-original-title="Context window">
                                        <svg class="progress-ring" width="24" height="24">
                                            <circle class="progress-ring-bg" cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"></circle>
                                            <circle id="aiContextProgressRing" class="progress-ring-circle" cx="12" cy="12" r="10" fill="none" stroke="#0d6efd" stroke-width="2" stroke-dasharray="63" stroke-dashoffset="63" transform="rotate(-90 12 12)" style="transition: stroke-dashoffset 0.3s;"></circle>
                                        </svg>
                                        <span id="aiCachePercBadge" class="progress-percentage">0%</span>
                                    </div>
                                    <span id="aiTokensDisplay" class="context-value" data-coreui-toggle="tooltip" data-coreui-original-title="Context: 0 / 1M tokens">0/1M</span>
                                    <span class="token-separator">|</span>
                                    <span class="token-metric" data-coreui-toggle="tooltip" data-coreui-original-title="Output tokens">
                                        ↑ <span id="aiOutputTokens" class="output-tokens">0</span>
                                    </span>
                                    <span id="aiCachedTokensWrap" class="token-metric cached-metric text-success" data-coreui-toggle="tooltip" data-coreui-original-title="Cached tokens">
                                        💾 <span id="aiCachedTokens" class="cached-tokens">0</span>
                                    </span>
                                    
                                    <button class="token-history-btn" id="token-history-btn" data-coreui-toggle="tooltip" aria-label="Token history" data-coreui-original-title="Token history">
                                        <svg class="icon"><use xlink:href="/assets/icons/sprites/free.svg#cil-chart-line"></use></svg>
                                    </button>
                                </div>

                                <!-- Send Button -->
                                <button id="aiChatSend" class="send-button">
                                    <svg class="nav-icon"><use xlink:href="/assets/icons/sprites/free.svg#cil-paper-plane"></use></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Auto-scroll chat to bottom on page load -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatHistory = document.getElementById('aiChatHistory');
    if (chatHistory) {
        // Slight delay to allow rendering
        setTimeout(() => { chatHistory.scrollTop = chatHistory.scrollHeight; }, 150);
    }
});
</script>

<!-- Code + Connection modals (API-driven, shared partial) -->
<?php include __DIR__ . '/../labs/partials/lab_action_modals.php'; ?>

<style>
/* App Layout Overrides */
.learn-app-wrapper { background: transparent; }
.transition-all { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.ls-1 { letter-spacing: 1px; }

/* Chat UI Styles */
#aiChatHistory { background: transparent; }

.message-row { 
    display: flex; 
    gap: 8px; 
    max-width: 90%;
    align-items: flex-start;
}

.user-row { flex-direction: row; text-align: right; }
.ai-row { flex-direction: row; text-align: left; }

.msg-avatar {
    width: 32px;
    height: 32px;
    min-width: 32px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    margin-top: 4px;
}

.msg-avatar img { width: 100%; height: 100%; object-fit: cover; }

.msg-bubble {
    padding: 8px 12px;
    border-radius: 14px;
    font-size: 0.85rem;
    line-height: 1.4;
    position: relative;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.ai-row .msg-bubble {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top-left-radius: 4px;
    color: #e5e7eb;
}

.user-row .msg-bubble {
    background: #1f2937;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-top-right-radius: 4px;
    color: #ffffff;
    text-align: left;
}

/* Summary Bubble */
.summary-bubble {
    background: rgba(56, 189, 248, 0.05) !important;
    border: 1px dashed rgba(56, 189, 248, 0.2) !important;
    border-radius: 12px !important;
}

/* Chat Input Overhaul */
.chat-input-pill {
    background: #111827;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.chat-input-pill:focus-within {
    border-color: var(--cui-primary, #321fdb);
    box-shadow: 0 0 0 4px rgba(var(--cui-primary-rgb), 0.15);
}

.chat-input-pill input::placeholder {
    color: #6b7280;
    font-style: italic;
}

/* Typing Dots */
.typing-dots { display: inline-flex; align-items: center; gap: 4px; padding: 4px 0; }
.typing-dots span {
    width: 6px;
    height: 6px;
    background: currentColor;
    border-radius: 50%;
    animation: blink 1.4s infinite both;
    opacity: 0.4;
}
.typing-dots span:nth-child(2) { animation-delay: .2s; }
.typing-dots span:nth-child(3) { animation-delay: .4s; }

@keyframes blink {
    0% { transform: scale(1); opacity: 0.4; }
    20% { transform: scale(1.3); opacity: 1; }
    100% { transform: scale(1); opacity: 0.4; }
}

.pane-outline, .pane-sidebar, .pane-ai { 
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

/* Scrollbars */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.1); }

/* Typography */
.chapter-text-content { color: #d1d5db; }
.chapter-text-content h2, .chapter-text-content h3 { 
    color: #fff; 
    margin-top: 2rem; 
    margin-bottom: 1.25rem; 
    font-weight: 700; 
}
.chapter-text-content p { margin-bottom: 1.5rem; }

/* Responsive */
@media (max-width: 991.98px) {
    .learn-app-wrapper { height: auto !important; min-height: 100vh; overflow: auto !important; }
    body { overflow: auto !important; }
    .flex-row { flex-direction: column !important; }
    .pane-outline, .pane-ai, .pane-content { width: 100% !important; height: auto !important; min-width: 0 !important; }
    .pane-resizer { display: none !important; }
    .pane-outline { order: 2; height: auto !important; padding-bottom: 2rem; }
    .pane-content { order: 1; min-height: 70vh; margin-bottom: 1rem; }
    .pane-ai { order: 3; min-height: 400px; }
}
</style>

<!-- Token History Modal Container -->
<div class="modal fade" id="tokenHistoryModal" tabindex="-1" aria-labelledby="tokenHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light" style="border: 1px solid #334155; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);" id="tokenHistoryModalContent">
            <!-- Content will be injected here via AJAX -->
        </div>
    </div>
</div>
