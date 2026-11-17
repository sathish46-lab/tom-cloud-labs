<?php
/**
 * Chat video-call signaling — POST /api/chat/call-signal.php
 * Broadcasts WebRTC signaling events (offer/answer/ice/end) to all chat
 * subscribers so peers can establish a P2P media connection.
 * Admin-only: only admins can originate/modify call signaling.
 */

require_once __DIR__ . '/_chat_common.php';

$session   = chat_require_user();
AuthMiddleware::requireCsrf();
AuthMiddleware::requireAdmin(); // admins only

$type     = chat_input('type');     // offer | answer | ice | end
$callId   = chat_input('call_id');  // required
$sdp      = chat_input('sdp');      // optional
$sdpType  = chat_input('sdp_type'); // optional
$candidate = chat_input('candidate'); // optional (JSON string)

if (!in_array($type, ['offer', 'answer', 'ice', 'end'], true)) {
    chat_json(['success' => false, 'error' => 'Invalid signal type'], 422);
}
if ($callId === '') {
    chat_json(['success' => false, 'error' => 'Call id required'], 422);
}

$event = [
    'type'         => 'call-' . $type,
    'call_id'      => $callId,
    'from_user_id' => (int) $session->getUserId(),
    'from_username'=> $session->getUsername(),
];

if ($sdp !== '')       $event['sdp']      = $sdp;
if ($sdpType !== '')   $event['sdp_type'] = $sdpType;
if ($candidate !== '') $event['candidate']= $candidate;

chat_publish($event);

chat_json([
    'success' => true,
    'call_id' => $callId,
]);
