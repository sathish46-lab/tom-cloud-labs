<?php
/**
 * Chat presence — POST /api/chat/presence.php
 * Heartbeat called by the chat widget every ~30s while connected.
 *
 * Design:
 *  - One upsert per user in `chat_presence` (TTL index cleans stale rows).
 *  - "Online" = updated within the last 90 seconds.
 *  - Returns the live count and broadcasts {"type":"presence","count":N}
 *    so every connected widget updates its header in real time.
 *
 * Nothing here is trusted from the client except the heartbeat itself —
 * identity always comes from the session.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
$db = chat_db();

rate_limit('chat:presence', 10, 120);

$userId   = (int) $session->getUserId();
$username = (string) $session->getUsername();
$now      = new MongoDB\BSON\UTCDateTime((int) (microtime(true) * 1000));

try {
    // One row per user. TTL index removes rows idle > 3 min (safety net;
    // the count query itself only trusts the last 90s).
    $db->chat_presence->updateOne(
        ['_id' => $userId],
        [
            '$set' => [
                'username'   => $username,
                'updated_at' => $now,
            ],
        ],
        ['upsert' => true]
    );

    // Active window: last 90 seconds.
    $cutoff = new MongoDB\BSON\UTCDateTime((int) ((microtime(true) - 90) * 1000));
    $count  = $db->chat_presence->countDocuments(['updated_at' => ['$gte' => $cutoff]]);

    chat_publish([
        'type'  => 'presence',
        'count' => $count,
    ]);

    chat_json(['success' => true, 'online' => $count]);
} catch (\Throwable $e) {
    error_log('Chat presence failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Presence unavailable'], 500);
}
