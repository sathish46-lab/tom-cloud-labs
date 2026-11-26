<?php
/**
 * Submit an issue report — POST /api/reports/submit
 *
 * Accepts JSON or form-encoded input:
 *   type       required, short machine slug (e.g. "quiz", "roadmap")
 *   entity_id  optional, reference id of the reported item
 *   message    required, 5–1000 characters
 *   page       optional, client page path (fallback context)
 *
 * Delivers the report to every superuser as a `report`-category bell
 * notification. Clicking it opens a read-only popup (data.popup = "report")
 * instead of navigating anywhere.
 */

require_once __DIR__ . '/../../load.php';

$session = AuthMiddleware::requireAuth();
AuthMiddleware::requireCsrf();
rate_limit('report:submit', 5, 300);

/**
 * Read a trimmed scalar from a JSON body first, then form POST.
 */
function report_field(string $key, array $json): string {
    if (isset($json[$key]) && is_scalar($json[$key])) {
        return trim((string) $json[$key]);
    }
    return isset($_POST[$key]) && is_scalar($_POST[$key])
        ? trim((string) $_POST[$key])
        : '';
}

function report_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

$raw  = file_get_contents('php://input');
$json = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $json = $decoded;
    }
}

$type     = report_field('type', $json);
$entityId = report_field('entity_id', $json);
$message  = report_field('message', $json);
$page     = report_field('page', $json);

// Type is a machine slug, never free text.
if ($type === '' || !preg_match('/^[a-z0-9_\-]{1,40}$/', $type)) {
    api_json(['success' => false, 'error' => 'A valid report type is required'], 400);
}

// Strip control characters from reference/page so stored data stays clean.
$entityId = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $entityId);
$page     = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $page);

if (report_length($entityId) > 128) {
    api_json(['success' => false, 'error' => 'Entity id is too long'], 400);
}
if (report_length($page) > 300) {
    api_json(['success' => false, 'error' => 'Page path is too long'], 400);
}

$messageLen = report_length($message);
if ($messageLen < 5) {
    api_json(['success' => false, 'error' => 'Please describe the issue (at least 5 characters)'], 400);
}
if ($messageLen > 1000) {
    api_json(['success' => false, 'error' => 'Message is too long (max 1000 characters)'], 400);
}

// Reporter identity always comes from the authenticated session.
$reporterEmail = (string) ($session->getEmail() ?? '');
$reporterName  = (string) ($session->getUsername() ?? '');
$reporterId    = (string) ($session->getUserId() ?? '');
$reporterLabel = $reporterEmail !== '' ? $reporterEmail : $reporterName;

$db       = DatabaseConnection::getDefaultDatabase();
$adminIds = [];
$cursor   = $db->users->find(
    ['role' => 'superuser'],
    ['projection' => ['_id' => 1, 'user_id' => 1]]
);
foreach ($cursor as $admin) {
    $uid = isset($admin['user_id']) && (string) $admin['user_id'] !== ''
        ? (string) $admin['user_id']
        : (string) $admin['_id'];
    if ($uid !== '') {
        $adminIds[$uid] = true;
    }
}
$adminIds = array_keys($adminIds);

$title = 'New ' . $type . ' report';
$body  = ($reporterLabel !== '' ? $reporterLabel : 'A user') . ' reported an issue.';
$data  = [
    'url'      => '#',
    'popup'    => 'report',
    'subtitle' => $reporterLabel,
    'report'   => [
        'type'           => $type,
        'entity_id'      => $entityId,
        'message'        => $message,
        'page'           => $page,
        'reported_by'    => $reporterLabel,
        'reported_by_id' => $reporterId,
        'at'             => gmdate('c'),
    ],
];

$delivered = 0;
foreach ($adminIds as $uid) {
    $id = Notification::create($uid, 'report', 'report_issue', $title, $body, $data);
    if (is_string($id) && $id !== '') {
        $delivered++;
    }
}

api_json([
    'success'   => true,
    'delivered' => $delivered,
    'message'   => 'Report submitted. Thanks!',
]);
