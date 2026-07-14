<?php
/**
 * Shared partial for rendering AI chat history messages.
 * Used by both initial page load (content.php) and AJAX endpoint (history.php).
 */
try {
    $db = $db ?? DatabaseConnection::getDefaultDatabase();
    $userId = $userId ?? (int)Session::getUser()->getUserId();
    $userAvatar = $userAvatar ?? Session::getAvatar();
    $userAvatarStyle = $userAvatarStyle ?? Session::getAvatarStyle();
    $aiAvatar = $aiAvatar ?? "/assets/logo/logo.png";
    $lessonId = (string)($lessonId ?? '');
    $chapterId = (string)($chapterId ?? '');

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
    
    if ($chatDoc && isset($chatDoc['messages'])) {
        foreach ($chatDoc['messages'] as $m) {
            $msg = (array)$m;
            if (($msg['role'] ?? '') !== 'system_summary') {
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
    
    // Output HTML directly
    $html = '';
    
    foreach ($messages as $msg) {
        $contentHtml = nl2br(htmlspecialchars($msg['content']));
        $rawMd = htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8');
        if ($msg['role'] === 'user') {
            $html .= '
            <div class="message-row user-row ms-auto">
                <div class="msg-bubble">
                    <p class="m-0" data-raw-md="' . $rawMd . '">' . $contentHtml . '</p>
                </div>
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
                $numTools = count($msg['tools']);
                $aggregatedPopover = '';
                
                foreach ($msg['tools'] as $tool) {
                    $toolName = htmlspecialchars($tool['name'] ?? 'Execute');
                    $labName = htmlspecialchars($tool['lab_name'] ?? 'Unknown');
                    $outputRaw = is_string($tool['output']) ? $tool['output'] : json_encode($tool['output'], JSON_PRETTY_PRINT);
                    $outputHtml = htmlspecialchars($outputRaw);
                    
                    $aggregatedPopover .= '<div class="popover-header" style="background:transparent; margin-bottom:0; border-bottom:1px solid #1e293b; padding:0 0 8px 0; margin-top: 8px;"><div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;"><i class=\'bx bxs-check-circle\' style="color:#22c55e;"></i><span style="font-weight:700; color:#fff; font-size:0.95rem;">' . $toolName . '</span></div><div style="display:flex; align-items:center; gap:6px; padding-left:2px;"><i class=\'bx bxl-docker\' style="color:#f97316; font-size:0.9rem;"></i><span style="color:#94a3b8; font-size:0.8rem; font-weight:500;">Run in ' . $labName . '</span></div></div><div class="popover-body" style="padding:12px 0 0 0; background:transparent;"><div class="popover-row"><span class="pop-label" style="color:#818cf8; font-weight:600; display:block; margin-bottom:4px; font-size:0.85rem;">Output:</span><div class="pop-output" style="color:#2dd4bf; white-space:pre-wrap; font-family:monospace; font-size:0.85rem; padding-left:8px; border-left:2px solid #334155;">' . $outputHtml . '</div></div></div>';
                }
                
                $badgeText = $numTools == 1 ? "1 tool" : "{$numTools} tools";
                
                $toolsHtml .= '
                <div class="tool-badge-wrapper mb-1">
                    <div class="agent-activity-btn-wrapper d-flex" style="position:relative;">
                        <button class="agent-activity-btn btn btn-sm" tabindex="0" data-coreui-toggle="popover" data-coreui-placement="bottom" data-coreui-html="true" data-coreui-custom-class="simple-blur" data-coreui-content="' . htmlspecialchars($aggregatedPopover) . '" style="background:transparent; border:1px solid #334155; border-radius:6px; padding:4px 10px; color:#94a3b8; font-size:0.85rem; display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <svg class="icon" style="width:14px; height:14px; fill:currentColor;">
                                <use xlink:href="/assets/icons/free.svg#cil-settings"></use>
                            </svg>
                            ' . $badgeText . '
                        </button>
                    </div>
                </div>';
            }

            $html .= '
            <div class="message-row ai-row"' . $usageAttrs . '>
                <div class="msg-avatar">
                    <img src="' . $aiAvatar . '" style="width: 30px;" alt="AI">
                </div>
                <div class="msg-content-wrapper d-flex flex-column" style="max-width:85%; width:100%;">
                    ' . $toolsHtml . '
                    <div class="msg-bubble w-100 ai-transparent-bubble" style="background:transparent !important; border:none !important; box-shadow:none !important; padding:0 !important;">
                        <p class="m-0" data-raw-md="' . $rawMd . '">' . $contentHtml . '</p>
                    </div>
                </div>
            </div>';
        }
    }
    
    echo $html;

} catch (Exception $e) {
    if (basename($_SERVER['PHP_SELF']) === 'history.php') {
        http_response_code(500);
        echo '<div class="text-center p-3 text-danger small">Failed to load chat history.</div>';
    }
}
