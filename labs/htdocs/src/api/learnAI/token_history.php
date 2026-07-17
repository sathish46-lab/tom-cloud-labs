<?php
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo '<div class="text-center p-3 text-danger small">Unauthorized.</div>';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$lessonId = $_GET['lesson_id'] ?? '';
$chapterId = $_GET['chapter_id'] ?? '';

// Connect to MongoDB
$db = DatabaseConnection::getDefaultDatabase();

$filter = ['user_id' => (int)$userId];
if (!empty($lessonId)) {
    $filter['lesson_id'] = $lessonId;
} else if (!empty($chapterId)) {
    $filter['chapter_id'] = $chapterId;
} else {
    echo '<div class="p-4 text-center text-danger">Invalid request.</div>';
    exit;
}

$chatHistory = $db->ai_chat_history->findOne($filter);
$messages = [];

if ($chatHistory && isset($chatHistory['messages'])) {
    $messages = (array)$chatHistory['messages'];
}

// Ensure array keys are sorted by timestamp
usort($messages, function($a, $b) {
    return $a['timestamp'] - $b['timestamp'];
});

$interactions = [];
$lastUserQuery = "Unknown Query";

$totalInput = 0;
$totalOutput = 0;
$totalCached = 0;
$totalTools = 0;
$totalAll = 0;

$count = 1;

foreach ($messages as $msg) {
    if ($msg['role'] === 'user') {
        $lastUserQuery = $msg['content'] ?? '';
    } else if ($msg['role'] === 'model') {
        $usage = $msg['usage'] ?? [];
        $tools = $msg['tools'] ?? [];
        
        $inputTokens = (int)($usage['input_tokens'] ?? 0);
        $outputTokens = (int)($usage['output_tokens'] ?? 0);
        $cachedTokens = (int)($usage['cached_tokens'] ?? 0);
        $totalTokens = (int)($usage['total_tokens'] ?? ($inputTokens + $outputTokens));
        $cacheHitPct = (float)($usage['cache_hit_percent'] ?? 0);
        $numTools = is_array($tools) ? count($tools) : 0;
        
        // Accumulate totals
        $totalInput += $inputTokens;
        $totalOutput += $outputTokens;
        $totalCached += $cachedTokens;
        $totalTools += $numTools;
        $totalAll += $totalTokens;
        
        $interactions[] = [
            'num' => $count++,
            'query' => $lastUserQuery,
            'input' => $inputTokens,
            'output' => $outputTokens,
            'cached' => $cachedTokens,
            'total' => $totalTokens,
            'cache_pct' => $cacheHitPct,
            'num_tools' => $numTools
        ];
    }
}

$totalCachePctOverall = $totalInput > 0 ? round(($totalCached / $totalInput) * 100, 1) : 0;

$html = '
<div class="modal-header border-bottom py-3 px-4">
    <h5 class="modal-title fw-bold" id="tokenHistoryModalLabel">Token Usage History</h5>
    <button type="button" class="btn-close" data-coreui-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body hide-scrollbar p-0">
    <div class="p-4 border-bottom">
        <h6 class="fw-bold mb-2 d-flex align-items-center gap-2"><i class="bx bx-line-chart text-primary fs-5"></i> Token Usage History</h6>
        <div class="fw-bold mb-1">Total Tokens Used: <span class="fw-normal">' . number_format($totalAll) . ' tokens</span></div>
        <div class="text-secondary small">Showing all ' . count($interactions) . ' interactions in this conversation</div>
    </div>
    
    <div class="table-responsive hide-scrollbar m-0 border-bottom" style="max-height: 340px; overflow-y: auto;">
        <table class="table table-borderless table-striped table-hover mb-0" style="font-size: 0.85rem;">
            <thead class="border-bottom" style="position: sticky; top: 0; z-index: 5; background: var(--cui-card-bg, var(--cui-body-bg, #11131a));">
                <tr>
                    <th class="ps-4 py-2">#</th>
                    <th class="py-2" style="width: 38%;">Query</th>
                    <th class="py-2 text-end">Input</th>
                    <th class="py-2 text-end">Output</th>
                    <th class="py-2 text-end">Cached</th>
                    <th class="py-2 text-center">Tools</th>
                    <th class="py-2 text-end">Total</th>
                    <th class="pe-4 py-2 text-end">Cache %</th>
                </tr>
            </thead>
            <tbody>';

if (empty($interactions)) {
    $html .= '<tr><td colspan="8" class="text-center py-4 text-secondary">No AI interactions found.</td></tr>';
} else {
    foreach ($interactions as $row) {
        $queryTruncated = strlen($row['query']) > 50 ? substr($row['query'], 0, 50) . '...' : $row['query'];
        $queryHtml = htmlspecialchars($queryTruncated);
        
        $cachedHtml = $row['cached'] > 0 ? '<span class="text-success fw-bold font-monospace">' . number_format($row['cached']) . '</span>' : '<span class="text-secondary opacity-50 font-monospace">0</span>';
        $toolsHtml = $row['num_tools'] > 0 ? '<span class="badge bg-info text-dark rounded-pill font-monospace">' . $row['num_tools'] . '</span>' : '<span class="text-secondary opacity-50">-</span>';
        $cachePctHtml = $row['cache_pct'] > 0 ? '<span class="badge bg-success rounded-pill font-monospace">' . $row['cache_pct'] . '%</span>' : '<span class="text-secondary opacity-50">-</span>';
        
        $html .= '
                <tr class="border-bottom border-opacity-10">
                    <td class="ps-4 py-2 align-middle text-secondary font-monospace">' . $row['num'] . '</td>
                    <td class="py-2 align-middle text-truncate" style="max-width: 250px;" title="' . htmlspecialchars($row['query']) . '">' . $queryHtml . '</td>
                    <td class="py-2 text-end align-middle font-monospace">' . number_format($row['input']) . '</td>
                    <td class="py-2 text-end align-middle font-monospace">' . number_format($row['output']) . '</td>
                    <td class="py-2 text-end align-middle font-monospace">' . $cachedHtml . '</td>
                    <td class="py-2 text-center align-middle">' . $toolsHtml . '</td>
                    <td class="py-2 text-end align-middle font-monospace fw-medium">' . number_format($row['total']) . '</td>
                    <td class="pe-4 py-2 text-end align-middle">' . $cachePctHtml . '</td>
                </tr>';
    }
}

$totalCachePctHtml = $totalCachePctOverall > 0 ? '<span class="badge bg-success rounded-pill font-monospace">' . $totalCachePctOverall . '%</span>' : '-';

$html .= '
                <!-- Total Row -->
                <tr class="border-top fw-bold" style="position: sticky; bottom: 0; z-index: 5; background: var(--cui-card-bg, var(--cui-body-bg, #11131a));">
                    <td colspan="2" class="ps-4 py-3">Total</td>
                    <td class="text-end py-3 font-monospace">' . number_format($totalInput) . '</td>
                    <td class="text-end py-3 font-monospace">' . number_format($totalOutput) . '</td>
                    <td class="text-end py-3 font-monospace text-success">' . number_format($totalCached) . '</td>
                    <td class="text-center py-3"><span class="badge bg-info text-dark rounded-pill font-monospace">' . number_format($totalTools) . '</span></td>
                    <td class="text-end py-3 font-monospace text-warning fs-6">' . number_format($totalAll) . '</td>
                    <td class="pe-4 py-3 text-end">' . $totalCachePctHtml . '</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="p-4" style="background-color: rgba(255, 193, 7, 0.05) !important;">
        <p class="mb-2 fw-bold text-warning d-flex align-items-center gap-2"><i class="bx bx-bulb fs-5"></i> Understanding Metrics:</p>
        <ul class="small text-secondary mb-2" style="list-style-type: disc; padding-left: 20px;">
            <li><strong>Input:</strong> Tokens sent to AI (includes conversation history)</li>
            <li><strong>Output:</strong> Tokens received from AI</li>
            <li><strong>Cached:</strong> Tokens retrieved from cache (50-90% cost savings!)</li>
            <li><strong>Tools:</strong> Number of tool calls (lab commands, file operations, etc.)</li>
            <li><strong>Cache %:</strong> Percentage of input that was cached</li>
        </ul>
        <p class="mb-0 small text-warning fw-bold">Note: Input tokens may decrease between interactions when the SDK removes tool call details from history to save tokens.</p>
    </div>
</div>
<div class="modal-footer border-top py-2 px-4">
    <button type="button" class="btn btn-warning btn-sm px-4 fw-bold rounded-pill" data-coreui-dismiss="modal" data-bs-dismiss="modal">Okay</button>
</div>';

echo $html;
