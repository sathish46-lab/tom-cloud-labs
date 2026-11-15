<?php
/**
 * Global Chat Feature — Database Migration
 * Run once: php src/api/chat/migrate.php
 *
 * Creates:
 *  - chat_messages indexes (history sort + cleanup filters)
 *  - chat_presence TTL index (stale presence rows are removed by Mongo
 *    automatically; the "online" query additionally only trusts the
 *    last 90 seconds, so a lagging TTL sweep can never inflate counts)
 */

require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

// CRITICAL: This is a migration script — admin only
$user = AuthMiddleware::requireAdmin();

try {
    $db = DatabaseConnection::getDefaultDatabase();

    echo "=== Global Chat Migration ===\n\n";

    // ── 1. chat_messages ──
    echo "[1/2] Ensuring chat_messages indexes...\n";

    $db->chat_messages->createIndex(['created_at' => -1], ['name' => 'chat_history_sort']);
    $db->chat_messages->createIndex(
        ['deleted' => 1, 'created_at' => -1],
        ['name' => 'chat_history_active_sort']
    );

    echo "  ✓ chat_messages indexes ready\n\n";

    // ── 2. chat_presence (TTL) ──
    echo "[2/2] Ensuring chat_presence TTL index...\n";

    // Presence rows older than 180s are swept by MongoDB. The count query
    // only trusts rows updated within the last 90s, so sweeping lag has
    // no effect on the displayed online count.
    $db->chat_presence->createIndex(
        ['updated_at' => 1],
        [
            'name'               => 'chat_presence_ttl',
            'expireAfterSeconds' => 180,
        ]
    );

    echo "  ✓ chat_presence TTL index ready\n\n";

    echo json_encode(['success' => true]) . "\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Migration failed: ' . $e->getMessage() . "\n";
    error_log('Chat migration failed: ' . $e->getMessage());
}
