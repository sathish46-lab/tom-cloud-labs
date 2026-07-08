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

$userId = (int)Session::getUser()->getUserId();
$messages = [];
$hasSummary = false;
$summaryText = '';

try {
    // Query with consistent integer user_id and exact chapter_id string match
    $chatDoc = $db->ai_chat_history->findOne([
        'user_id' => $userId,
        'chapter_id' => (string)$chapter_id
    ]);

    if ($chatDoc && isset($chatDoc['messages']) && is_array($chatDoc['messages'])) {
        foreach ($chatDoc['messages'] as $m) {
            $msg = (array)$m;
            if (($msg['role'] ?? '') === 'system_summary') {
                $hasSummary = true;
                $summaryText = $msg['content'] ?? '';
            } else {
                $messages[] = $msg;
            }
        }
    }

    // Chronological Sort
    usort($messages, function($a, $b) {
        $ta = (int)($a['timestamp'] ?? 0);
        $tb = (int)($b['timestamp'] ?? 0);
        return $ta - $tb;
    });

} catch (Exception $e) {
    // Silence error to keep page alive
}

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
                document.documentElement.style.setProperty('--outlineSidebar-saved-width', arr[0] + '%');
                document.documentElement.style.setProperty('--courseSidebar-saved-width', arr[0] + '%');
                document.documentElement.style.setProperty('--paneAI-saved-width', arr[2] + '%');
            }
        } catch (e) {}
    }

    const headerHeight = 64; // 4rem header height
    const footerHeight = 38; // footer height
    document.documentElement.style.setProperty('--app-height', (window.innerHeight - headerHeight - footerHeight) + 'px');
})();
</script>

<div class="learn-app-wrapper stable-app-view d-flex flex-column overflow-hidden bg-transparent" style="height: var(--app-height, 75vh);">
    <!-- Main App Body -->
    <div class="flex-grow-1 d-flex flex-row overflow-hidden p-0 gap-0">
        
        <!-- Pane 1: Collapsible Sidebar (Outline) -->
        <div id="outlineSidebar" class="pane-outline d-flex flex-column h-100 transition-all" style="width: var(--outlineSidebar-saved-width, 70px); min-width: 70px;" data-state="collapsed">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-body p-2 d-flex flex-column align-items-center py-3 overflow-hidden">
                    <!-- Compact View (Shown when collapsed) -->
                    <div class="outline-compact d-flex flex-column gap-3 align-items-center w-100 overflow-auto no-scrollbar flex-grow-1">
                        <button class="btn btn-sm btn-outline-secondary rounded-circle border-opacity-10 p-2 d-flex align-items-center justify-content-center" title="Favorite">
                            <i class="bx bx-heart fs-5"></i>
                        </button>
                        
                        <div class="sidebar-progress-circle d-flex align-items-center justify-content-center position-relative" style="width: 42px; height: 42px;">
                            <svg width="42" height="42" viewBox="0 0 45 45">
                                <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="3" />
                                <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="var(--cui-primary)" stroke-width="3" stroke-dasharray="113" stroke-dashoffset="<?= 113 - (113 * $lesson['progress'] / 100) ?>" stroke-linecap="round" transform="rotate(-90 22.5 22.5)" />
                            </svg>
                            <span class="position-absolute small fw-bold" style="font-size: 0.65rem;"><?= $lesson['progress'] ?>%</span>
                        </div>

                        <button class="btn btn-link text-secondary p-0" onclick="toggleOutline()" title="Quick Outline">
                            <i class="bx bx-list-ul fs-4"></i>
                        </button>
                        <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-link text-secondary p-0" title="Map View">
                            <i class="bx bx-share-alt fs-4"></i>
                        </a>

                        <div class="border-top border-secondary border-opacity-10 w-50 my-1"></div>

                        <?php foreach ($chapters as $chap): ?>
                            <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" 
                               class="btn btn-sm <?= $chap['_id'] == $chapter['_id'] ? 'btn-primary shadow-sm' : 'btn-outline-secondary border-0 opacity-50' ?> rounded-circle p-0 d-flex align-items-center justify-content-center" 
                               style="width: 32px; height: 32px; font-size: 0.75rem;" 
                               title="<?= $chap['title'] ?>">
                                <?= $chap['order'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Full View (Shown when expanded) -->
                    <div class="outline-full d-none w-100 h-100 flex-column overflow-hidden text-start p-3">
                        <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                            <h6 class="fw-bold m-0 small text-light lh-base"><?= $lesson['title'] ?></h6>
                            <button class="btn btn-sm btn-outline-secondary rounded-circle border-opacity-10 flex-shrink-0"><i class="bx bx-heart"></i></button>
                        </div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom border-secondary border-opacity-10">
                            <div class="d-flex align-items-center gap-2">
                                <div class="sidebar-progress-circle d-flex align-items-center justify-content-center position-relative" style="width: 38px; height: 38px;">
                                    <svg width="38" height="38" viewBox="0 0 45 45">
                                        <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="3" />
                                        <circle cx="22.5" cy="22.5" r="18" fill="none" stroke="var(--cui-primary)" stroke-width="3" stroke-dasharray="113" stroke-dashoffset="<?= 113 - (113 * $lesson['progress'] / 100) ?>" stroke-linecap="round" transform="rotate(-90 22.5 22.5)" />
                                    </svg>
                                    <span class="position-absolute small fw-bold" style="font-size: 0.6rem;"><?= $lesson['progress'] ?>%</span>
                                </div>
                                <span class="small fw-bold text-light">Completed</span>
                            </div>

                            <div class="d-flex gap-2 align-items-center">
                                <button class="btn btn-sm btn-dark border border-secondary border-opacity-25 rounded-pill px-3 py-1 d-flex align-items-center gap-2 active">
                                    <i class="bx bx-list-ul"></i> Outline
                                </button>
                                <a href="/learn/lesson/<?= $lesson['_id'] ?>" class="btn btn-sm btn-outline-secondary border-0 rounded-pill px-3 py-1 d-flex align-items-center gap-2 text-secondary">
                                    <i class="bx bx-share-alt"></i> Map
                                </a>
                            </div>
                        </div>
                        <div class="list-group list-group-flush overflow-auto custom-scrollbar flex-grow-1">
                            <?php foreach ($chapters as $chap): ?>
                                <a href="/learn/lesson/<?= $lesson['_id'] ?>/chapter/<?= $chap['_id'] ?>" 
                                   class="list-group-item list-group-item-action bg-transparent border-0 px-2 py-2 rounded mb-1 d-flex align-items-center gap-2 <?= $chap['_id'] == $chapter['_id'] ? 'active bg-primary bg-opacity-10 text-primary fw-bold' : 'text-secondary' ?>">
                                    <span class="badge rounded-circle bg-secondary bg-opacity-10 p-1 <?= $chap['_id'] == $chapter['_id'] ? 'text-primary' : 'text-secondary' ?>" style="width: 22px; height: 22px; font-size: 0.65rem;"><?= $chap['order'] ?></span>
                                    <span class="small text-truncate"><?= $chap['title'] ?></span>
                                    <?php if ($chap['status'] == 'completed'): ?>
                                        <i class="bx bxs-check-circle text-success ms-auto small"></i>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bottom Actions -->
                    <div class="mt-auto d-flex flex-column align-items-center gap-3 pt-3 border-top border-secondary border-opacity-10 w-100">
                         <i class="bx bxl-ubuntu text-warning fs-4"></i>
                         <i class="bx bx-terminal text-secondary fs-4"></i>
                         <i class="bx bx-info-circle text-secondary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resizer 1 -->
        <div class="pane-resizer h-100" data-target="outlineSidebar"></div>

        <!-- Pane 2: Center Content Area -->
        <div class="pane-content flex-grow-1 h-100 d-flex flex-column overflow-hidden">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-header bg-transparent border-secondary border-opacity-10 p-3 p-lg-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small d-block mb-1" style="font-size: 0.7rem;"><?= $chapter['module_name'] ?></span>
                        <h4 class="card-title fw-bold m-0 text-truncate" style="max-width: 400px;"><?= $chapter['title'] ?></h4>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button id="btnGenerateContent" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-flex align-items-center gap-1 border-opacity-25" data-chapter-id="<?= $chapter_id ?>" title="Generate human-like tutorial content">
                            <i class="bx bx-magic-wand"></i> <span>Generate Content</span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary rounded-circle border-opacity-10 p-2"><i class="bx bx-share-alt"></i></button>
                        <button class="btn btn-sm btn-outline-success rounded-circle border-opacity-10 p-2"><i class="bx bx-check"></i></button>
                    </div>
                </div>
                <div class="card-body p-3 p-lg-5 flex-grow-1 overflow-auto custom-scrollbar position-relative">
                    <!-- Generation Loading Skeleton -->
                    <div id="contentGeneratingStatus" class="d-none alert alert-dark border-secondary border-opacity-25 rounded-3 mb-4 d-flex align-items-center gap-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="small text-secondary">Senior Mentor is crafting practical tutorial material...</span>
                    </div>

                    <div id="chapterContentContainer" class="chapter-text-content lh-lg" style="font-size: 1.05rem;" data-raw-md="<?= htmlspecialchars($chapter['content'] ?? '') ?>">
                        <?php if (!empty($chapter['content_html']) && $chapter['content_html'] !== '...'): ?>
                            <?= $chapter['content_html'] ?>
                        <?php elseif (!empty($chapter['content']) && $chapter['content'] !== '...'): ?>
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

        <!-- Resizer 2 -->
        <div class="pane-resizer h-100" data-target="paneAI" data-direction="right"></div>

        <!-- Pane 3: Right AI Assistant -->
        <div id="paneAI" class="pane-ai d-flex flex-column h-100" style="width: var(--paneAI-saved-width, 350px); min-width: 300px;">
            <div class="card h-100 border-secondary border-opacity-10 rounded-4 shadow-sm d-flex flex-column overflow-hidden">
                <div class="card-header bg-dark bg-opacity-25 border-secondary border-opacity-10 py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bx-bot text-primary"></i>
                        <h6 class="fw-bold m-0 small ls-1 text-uppercase">AI Assist</h6>
                        <span class="badge bg-warning text-dark" style="font-size: 0.55rem; padding: 0.2em 0.5em;">PRO</span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                         <select id="aiModelSelect" class="form-select form-select-sm bg-transparent text-secondary border-0 p-0 me-1" style="font-size: 0.7rem; width: auto; cursor: pointer;">
                             <option value="lm_studio">LM Studio</option>
                             <option value="gemini">Gemini</option>
                         </select>
                         <i class="bx bx-refresh text-secondary cursor-pointer fs-6 opacity-50 hover-opacity-100" title="Clear Chat" onclick="if(confirm('Clear?')) document.getElementById('aiChatHistory').innerHTML=''"></i>
                    </div>
                </div>
                <div class="card-body p-0 d-flex flex-column flex-grow-1 overflow-hidden position-relative">
                    <input type="hidden" id="currentChapterId" value="<?= $chapter['_id'] ?? '' ?>">
                    <input type="hidden" id="userAvatarUrl" value="<?= $userAvatar ?>">
                    <input type="hidden" id="userAvatarStyle" value="<?= $userAvatarStyle ?>">
                    <input type="hidden" id="aiAvatarUrl" value="<?= $aiAvatar ?>">

                    <div id="aiChatHistory" class="chat-history flex-grow-1 overflow-auto custom-scrollbar p-2 d-flex flex-column gap-2">
                        <!-- AI Introduction -->
                        <div class="message-row ai-row">
                            <div class="msg-avatar">
                                <img src="<?= $aiAvatar ?>" alt="AI">
                            </div>
                            <div class="msg-bubble">
                                <p class="m-0">Hello! I'm your AI learning assistant. How can I help you understand "<?= htmlspecialchars($chapter['title']) ?>" better today? ✨</p>
                            </div>
                        </div>
                        
                        <?php if ($hasSummary && $summaryText): ?>
                            <!-- RAG Summary of Previous Conversations -->
                            <div class="message-row ai-row">
                                <div class="msg-avatar">
                                    <img src="<?= $aiAvatar ?>" alt="AI">
                                </div>
                                <div class="msg-bubble summary-bubble">
                                    <div class="d-flex align-items-center gap-2 mb-1 cursor-pointer" onclick="this.closest('.summary-bubble').querySelector('.summary-content').classList.toggle('d-none'); this.querySelector('.bx').classList.toggle('bx-chevron-down'); this.querySelector('.bx').classList.toggle('bx-chevron-right');">
                                        <i class="bx bx-chevron-right text-info" style="font-size: 0.9rem;"></i>
                                        <span class="badge bg-info bg-opacity-15 text-info" style="font-size: 0.6rem;">📋 Previous Context Summary</span>
                                    </div>
                                    <div class="summary-content d-none">
                                        <p class="m-0 mt-1 text-secondary" style="font-size: 0.8rem; line-height: 1.5;"><?= nl2br(htmlspecialchars($summaryText)) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['role'] === 'user'): ?>
                                <div class="message-row user-row ms-auto">
                                    <div class="msg-bubble">
                                        <p class="m-0"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                                    </div>
                                    <div class="msg-avatar shadow-sm border border-secondary border-opacity-25">
                                        <img src="<?= $userAvatar ?>" style="<?= $userAvatarStyle ?>" alt="User">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="message-row ai-row">
                                    <div class="msg-avatar">
                                        <img src="<?= $aiAvatar ?>" alt="AI">
                                    </div>
                                    <div class="msg-bubble">
                                        <p class="m-0"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bottom Input Bar -->
                    <div class="chat-input-wrapper p-3 border-top border-secondary border-opacity-10 bg-black bg-opacity-10">
                        <div class="chat-input-pill d-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-lg">
                            <div class="input-actions-left d-flex gap-2">
                                <i class="bx bx-plus-circle text-secondary fs-5 opacity-50 cursor-pointer"></i>
                            </div>
                            <input type="text" id="aiChatInput" class="form-control bg-transparent border-0 text-white shadow-none ps-1" placeholder="Ask AI ✨" style="font-size: 0.95rem;">
                            <button id="aiChatSend" class="btn btn-primary rounded-circle p-0 d-flex align-items-center justify-content-center shadow-lg" style="width: 38px; height: 38px; min-width: 38px;">
                                <i class="bx bx-up-arrow-alt fs-4"></i>
                            </button>
                        </div>
                        <div class="input-stats-row d-flex align-items-center justify-content-between mt-2 px-3 opacity-50" style="font-size: 0.65rem;">
                             <div class="d-flex gap-3">
                                 <span><i class="bx bx-chart me-1"></i> qwen-3-4b</span>
                                 <span><i class="bx bx-bolt-circle me-1"></i> online</span>
                             </div>
                             <div class="d-flex gap-2 align-items-center">
                                 <i class="bx bx-refresh cursor-pointer fs-6" title="Clear Chat" onclick="if(confirm('Clear history?')) document.getElementById('aiChatHistory').innerHTML=''"></i>
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
