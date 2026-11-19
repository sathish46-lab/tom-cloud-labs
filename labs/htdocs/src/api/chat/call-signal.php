<?php
/**
 * Chat video-call signaling — POST /api/chat/call-signal.php
 * Broadcasts WebRTC signaling events (offer/answer/ice/end) to all chat
 * subscribers so peers can establish a P2P media connection.
 *
 * Permission matrix:
 *   - offer : only start-capable users (admin/superuser). Creates call state.
 *   - answer: any logged-in user who is a participant (caller or first
 *             responder). Marks the callee.
 *   - ice   : any participant of the call.
 *   - end   : any participant of the call.
 *
 * Call state is stored in Mongo (chat_calls collection) so participants can
 * be validated and ICE candidates can be buffered.
 */

require_once __DIR__ . '/_chat_common.php';

$db = chat_db();

$session   = chat_require_user();
AuthMiddleware::requireCsrf();

$myUserId = (int) $session->getUserId();
$myUsername = $session->getUsername();
// Video-call access mode (Admin → Settings → Chat Settings). Enforced
// server-side on every signal type — the client JS is never trusted.
$callMode = chat_call_access();
if ($callMode === ChatSettings::MODE_OFF) {
    // Fully disabled: reject everything, including ICE/end of in-progress
    // calls, so toggling off cuts live calls immediately.
    chat_json(['success' => false, 'error' => 'Video calls are disabled'], 403);
}
$myCanStart = chat_can_start_call_mode();

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

// ---- Load existing call state (if any) ----
$call = null;
try {
    $call = $db->chat_calls->findOne(['call_id' => $callId]);
} catch (\Throwable $e) {
    // Collection may not exist yet; treat as no existing call.
}

// ---- offer: only start-capable users ----
if ($type === 'offer') {
    if (!$myCanStart) {
        chat_json(['success' => false, 'error' => 'Forbidden: only admins/superusers can start calls'], 403);
    }
    // Reject if a call with this ID already exists and has already ended
    // (a new offer needs a fresh call id).
    if ($call && !empty($call['ended_at'])) {
        chat_json(['success' => false, 'error' => 'Call already ended'], 409);
    }

    $now = new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000));
    $ttl = new \MongoDB\BSON\UTCDateTime((int)((microtime(true) + 300) * 1000)); // 5 min TTL

    try {
        $db->chat_calls->updateOne(
            ['call_id' => $callId],
            ['$set' => [
                'call_id'       => $callId,
                'caller_id'     => $myUserId,
                'caller_name'   => $myUsername,
                'callee_id'     => null,
                'callee_name'   => null,
                'status'        => 'offered',
                'created_at'    => $now,
                'updated_at'    => $now,
                'ended_at'      => null,
                'expire_at'     => $ttl,
            ]],
            ['upsert' => true]
        );
    } catch (\Throwable $e) {
        error_log('Call state store failed: ' . $e->getMessage());
        chat_json(['success' => false, 'error' => 'Could not store call state'], 500);
    }
}

// ---- answer / ice / end: must be a participant ----
if (in_array($type, ['answer', 'ice', 'end'], true)) {
    if (!$call) {
        chat_json(['success' => false, 'error' => 'Call not found'], 404);
    }
    $callerId   = (int)($call['caller_id'] ?? 0);
    $rawCalleeId = $call['callee_id'] ?? null;
    // callee_id is stored as null until someone answers; do NOT int-cast null
    // (null would become 0 and falsely look like "answered by another user").
    $calleeId    = ($rawCalleeId === null || (string)$rawCalleeId === '') ? null : (int)$rawCalleeId;
    $isCaller   = ($myUserId === $callerId);
    $isCallee   = ($myUserId === $calleeId);
    $isParticipant = $isCaller || $isCallee;

    // ---- answer: first responder becomes the callee (no prior participation needed) ----
    if ($type === 'answer') {
        if ($call['status'] !== 'offered') {
            chat_json(['success' => false, 'error' => 'Call not in offered state'], 409);
        }
        if ($calleeId !== null && $calleeId !== $myUserId) {
            chat_json(['success' => false, 'error' => 'Call already answered by another user'], 409);
        }
        // Any logged-in user can answer; first one becomes callee.
        try {
            $db->chat_calls->updateOne(
                ['call_id' => $callId],
                ['$set' => [
                    'callee_id'   => $myUserId,
                    'callee_name' => $myUsername,
                    'status'      => 'answered',
                    'updated_at'  => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                ]]
            );
        } catch (\Throwable $e) {
            error_log('Call state update failed: ' . $e->getMessage());
            chat_json(['success' => false, 'error' => 'Could not update call state'], 500);
        }
    }

    // ---- ice / end: must be caller or callee ----
    if (in_array($type, ['ice', 'end'], true)) {
        if (!$isParticipant) {
            chat_json(['success' => false, 'error' => 'Forbidden: not a participant of this call'], 403);
        }

        if ($type === 'end') {
            try {
                $db->chat_calls->updateOne(
                    ['call_id' => $callId],
                    ['$set' => [
                        'status'   => 'ended',
                        'ended_at' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                        'updated_at' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                    ]]
                );
            } catch (\Throwable $e) {
                error_log('Call end state update failed: ' . $e->getMessage());
                chat_json(['success' => false, 'error' => 'Could not update call state'], 500);
            }
        }
    }
}

$event = [
    'type'         => 'call-' . $type,
    'call_id'      => $callId,
    'from_user_id' => $myUserId,
    'from_username'=> $myUsername,
];

if ($sdp !== '')       $event['sdp']      = $sdp;
if ($sdpType !== '')   $event['sdp_type'] = $sdpType;
if ($candidate !== '') $event['candidate']= $candidate;

$published = chat_publish($event);
if (!$published) {
    02);
    exit;
}

chat_json([
    'success' => true,
    'call_id' => $callId,
]);
