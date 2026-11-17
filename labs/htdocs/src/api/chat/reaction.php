<?php
/**
 * Chat reaction — POST /api/chat/reaction.php
 * Add or remove an emoji reaction on a message.
 * Any authenticated user can react; each user can react once per emoji per message.
 * Broadcasts the reaction event so all connected clients update live.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

$messageId = chat_input('message_id');
$emoji     = chat_input('emoji');

if (!preg_match('/^[a-f0-9]{24}$/i', $messageId)) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}
if ($emoji === '') {
    chat_json(['success' => false, 'error' => 'Emoji required'], 422);
}
// Reactions must be actual emoji, never arbitrary text. Accept the standard
// emoji codepoint repertoire plus its joiners (ZWJ U+200D, variation selector
// U+FE0F, keycap U+20E3, regional indicators, skin tones, emoji tag chars) —
// this rejects ASCII/scripts such as "<img src=x onerror=...>" and plain text.
// Strip control characters first; keep ZWJ/variation selectors intact.
$emoji = preg_replace('/[\x00-\x1F\x7F]/u', '', $emoji) ?? '';
if ($emoji === '') {
    chat_json(['success' => false, 'error' => 'Emoji required'], 422);
}
$EMOJI_RE = '/^['
    . '\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}'             // © ® ‼ ⁉ ™ ℹ
    . '\x{2194}-\x{21AA}\x{231A}-\x{23FA}\x{24C2}\x{25AA}-\x{25FE}' // arrows, watch,circled,badge
    . '\x{2600}-\x{27BF}\x{2934}-\x{2935}\x{2B00}-\x{2B55}'         // misc symbols, dingbats, stars/arrows
    . '\x{3030}\x{303D}\x{3297}\x{3299}\x{FE0F}\x{200D}\x{20E3}'    // wavy, marks, joiners
    . '\x{1F000}-\x{1FAFF}'                                          // main emoji planes
    . '\x{1F1E6}-\x{1F1FF}\x{1F3FB}-\x{1F3FF}'                       // regional indicators, skin tones
    . '\x{E0020}-\x{E007F}\x{0023}\x{002A}\x{0030}-\x{0039}'        // emoji tags, keycap bases
    . ']++$/u';
if (preg_match($EMOJI_RE, $emoji) !== 1) {
    chat_json(['success' => false, 'error' => 'Invalid emoji'], 422);
}
if (mb_strlen($emoji) > 12) {
    chat_json(['success' => false, 'error' => 'Emoji too long'], 422);
}

$userId = (int) $session->getUserId();

try {
    $mongoId = new MongoDB\BSON\ObjectId($messageId);
} catch (\Throwable $e) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

// Verify message exists and is not deleted.
$message = $db->chat_messages->findOne([
    '_id'   => $mongoId,
    'deleted' => ['$ne' => true],
]);
if ($message === null) {
    chat_json(['success' => false, 'error' => 'Message not found'], 404);
}

$reactions = $db->chat_reactions;

// Check if this user already reacted with this emoji on this message.
$existing = $reactions->findOne([
    'message_id' => (string)$messageId,
    'user_id'    => $userId,
    'emoji'      => $emoji,
]);

if ($existing !== null) {
    // Remove reaction (toggle off).
    $reactions->deleteOne(['_id' => $existing['_id']]);
    $added = false;
} else {
    // Add reaction.
    $reactions->insertOne([
        'message_id' => (string)$messageId,
        'user_id'    => $userId,
        'emoji'      => $emoji,
        'created_at' => new MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
    ]);
    $added = true;
}

// Count total users who reacted with this emoji.
$count = (int)$reactions->countDocuments([
    'message_id' => (string)$messageId,
    'emoji'      => $emoji,
]);

// Broadcast reaction event.
chat_publish([
    'type'      => 'reaction',
    'message_id' => (string)$messageId,
    'emoji'      => $emoji,
    'user_id'    => $userId,
    'added'      => $added,
    'count'      => $count,
]);

chat_json([
    'success' => true,
    'added'   => $added,
    'count'   => $count,
    'emoji'   => $emoji,
]);
