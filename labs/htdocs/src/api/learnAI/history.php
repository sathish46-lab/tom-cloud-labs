<?php
/**
 * Learn AI - Chat History API
 * Returns chat history for a given chapter (AJAX endpoint)
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: text/html');

// 1. Validate Session
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo '<div class="text-center p-3 text-danger small">Unauthorized.</div>';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();

// 2. Get parameters from query string
$lessonId = $_GET['lesson_id'] ?? '';
$chapterId = $_GET['chapter_id'] ?? '';

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // First try shared lesson history
    $chatDoc = null;
    if (!empty($lessonId)) {
        $chatDoc = $db->ai_chat_history->findOne([
            'user_id' => $userId,
            'lesson_id' => (string)$lessonId
        ]);
    }
    
    // Fallback to chapter specific history
    if (!$chatDoc && !empty($chapterId)) {
        $chatDoc = $db->ai_chat_history->findOne([
            'user_id' => $userId,
            'chapter_id' => (string)$chapterId
        ]);
    }
    
    $messages = [];
    $summary = null;
    
    if ($chatDoc && isset($chatDoc['messages'])) {
        foreach ($chatDoc['messages'] as $m) {
            $msg = (array)$m;
            if (($msg['role'] ?? '') === 'system_summary') {
                $summary = [
                    'content' => $msg['content'] ?? '',
                    'summarized_count' => (int)($msg['summarized_count'] ?? 0),
                    'timestamp' => (int)($msg['timestamp'] ?? 0)
                ];
            } else {
                $messages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                    'timestamp' => (int)($msg['timestamp'] ?? 0),
                    'usage' => isset($msg['usage']) ? (array)$msg['usage'] : null,
                    'tools' => isset($msg['tools']) ? (array)$msg['tools'] : null
                ];
            }
        }
    }
    
    // Sort messages chronologically
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    $userAvatar = Session::getAvatar();
    $userAvatarStyle = Session::getAvatarStyle();
    $aiAvatar = "/assets/logo/logo.png";
    
    // Output HTML directly
    $html = '';
    
    // User requested not to show the system summary in the UI at all.
    
    foreach ($messages as $msg) {
        $content = nl2br(htmlspecialchars($msg['content']));
        if ($msg['role'] === 'user') {
            $html .= '
            <div class="message-row user-row ms-auto">
                <div class="msg-bubble">
                    <p class="m-0">' . $content . '</p>
                </div>
                <!-- <div class="msg-avatar">
                    <img src="' . $userAvatar . '" style="width: 30px; ' . $userAvatarStyle . '" alt="User">
                </div> -->
            </div>';
        } else {
            $usageAttrs = '';
            if (!empty($msg['usage'])) {
                $usage = $msg['usage'];
                $inp = isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : 0;
                $out = isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : 0;
                $cache = isset($usage['cached_tokens']) ? (int)$usage['cached_tokens'] : 0;
                $total = isset($usage['total_tokens']) ? (int)$usage['total_tokens'] : 0;
                $usageAttrs = sprintf(' data-input-tokens="%d" data-output-tokens="%d" data-cached-tokens="%d" data-total-tokens="%d"', $inp, $out, $cache, $total);
            }
            $toolsHtml = '';
            if (!empty($msg['tools']) && is_array($msg['tools'])) {
                foreach ($msg['tools'] as $tool) {
                    $toolName = htmlspecialchars($tool['name'] ?? 'Execute');
                    $labName = htmlspecialchars($tool['lab_name'] ?? 'Unknown');
                    $outputRaw = is_string($tool['output']) ? $tool['output'] : json_encode($tool['output'], JSON_PRETTY_PRINT);
                    $outputHtml = htmlspecialchars($outputRaw);
                    
                    $popoverContent = '<div class="popover-header" style="background:transparent; margin-bottom:0; border-bottom:1px solid #1e293b; padding:0 0 8px 0;"><div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;"><i class=\'bx bxs-check-circle\' style="color:#22c55e;"></i><span style="font-weight:700; color:#fff; font-size:0.95rem;">' . $toolName . '</span></div><div style="display:flex; align-items:center; gap:6px; padding-left:2px;"><i class=\'bx bxl-docker\' style="color:#f97316; font-size:0.9rem;"></i><span style="color:#94a3b8; font-size:0.8rem; font-weight:500;">Run in ' . $labName . '</span></div></div><div class="popover-body" style="padding:12px 0 0 0; background:transparent;"><div class="popover-row"><span class="pop-label" style="color:#818cf8; font-weight:600; display:block; margin-bottom:4px; font-size:0.85rem;">Output:</span><div class="pop-output" style="color:#2dd4bf; white-space:pre-wrap; font-family:monospace; font-size:0.85rem; padding-left:8px; border-left:2px solid #334155;">' . $outputHtml . '</div></div></div>';
                    
                    $toolsHtml .= '
                    <div class="tool-badge-wrapper mb-1">
                        <div class="agent-activity-btn-wrapper d-flex" style="position:relative;">
                            <button class="agent-activity-btn btn btn-sm" tabindex="0" data-coreui-toggle="popover" data-coreui-placement="bottom" data-coreui-html="true" data-coreui-custom-class="simple-blur" data-coreui-content="' . htmlspecialchars($popoverContent) . '" style="background:transparent; border:1px solid #334155; border-radius:6px; padding:4px 10px; color:#94a3b8; font-size:0.85rem; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <svg class="icon" style="width:14px; height:14px; fill:currentColor;">
                                    <use xlink:href="/assets/icons/free.svg#cil-settings"></use>
                                </svg>
                                1 tool
                            </button>
                        </div>
                    </div>';
                }
            }

            $html .= '
            <div class="message-row ai-row"' . $usageAttrs . '>
                <div class="msg-avatar">
                    <img src="' . $aiAvatar . '" style="width: 30px;" alt="AI">
                </div>
                <div class="msg-content-wrapper d-flex flex-column" style="max-width:85%; width:100%;">
                    ' . $toolsHtml . '
                    <div class="msg-bubble w-100">
                        <p class="m-0">' . $content . '</p>
                    </div>
                </div>
            </div>';
        }
    }
    
    echo $html;

} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="text-center p-3 text-danger small">Failed to load chat history.</div>';
}
