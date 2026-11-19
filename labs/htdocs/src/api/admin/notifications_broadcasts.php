<?php
/**
 * Admin sent broadcasts — GET/DELETE /api/admin/notifications_broadcasts.php
 *
 * GET    ?category=academy  → grouped list of sent broadcasts (newest first)
 * DELETE { broadcast_id }   → delete every recipient's copy of one broadcast
 */

require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();

function nb_date($value): ?string {
    if ($value instanceof \MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->format('c');
    }
    if ($value instanceof \DateTimeInterface) {
        return $value->format('c');
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $category = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
    $filter = ['type' => 'broadcast', 'global' => true];
    if ($category !== '' && $category !== 'all') {
        if (!in_array($category, Notification::CATEGORIES, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Invalid category']);
            exit;
        }
        $filter['category'] = $category;
    }

    try {
        $pipeline = [
            ['$match' => $filter],
            ['$group' => [
                '_id'         => '$broadcast_id',
                'title'       => ['$first' => '$title'],
                'body'        => ['$first' => '$body'],
                'category'    => ['$first' => '$category'],
                'from_admin'  => ['$first' => '$from_admin'],
                'created_at'  => ['$max' => '$created_at'],
                'recipients'  => ['$sum' => 1],
                'read_count'  => ['$sum' => ['$cond' => [['$eq' => ['$read', true]], 1, 0]]],
            ]],
            ['$sort' => ['created_at' => -1]],
            ['$limit' => 200],
        ];

        $cursor = DatabaseConnection::getDefaultDatabase()->notifications->aggregate($pipeline);
        $rows = [];
        foreach ($cursor as $doc) {
            $id = $doc['_id'];
            if (!is_string($id) || $id === '') continue;
            $rows[] = [
                'id'          => $id,
                'broadcast_id'=> $id,
                'title'       => (string)($doc['title'] ?? ''),
                'body'        => (string)($doc['body'] ?? ''),
                'category'    => (string)($doc['category'] ?? ''),
                'from_admin'  => (string)($doc['from_admin'] ?? ''),
                'recipients'  => (int)($doc['recipients'] ?? 0),
                'read_count'  => (int)($doc['read_count'] ?? 0),
                'created_at'  => nb_date($doc['created_at'] ?? null),
            ];
        }

        echo json_encode([
            'status'     => 'success',
            'broadcasts' => $rows,
            'total'      => count($rows),
        ]);
    } catch (\Throwable $e) {
        error_log('notifications_broadcasts list failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Could not load broadcasts']);
    }
    exit;
}

if ($method === 'DELETE') {
    AuthMiddleware::requireCsrf();

    $raw = file_get_contents('php://input');
    $json = [];
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $json = $decoded;
    }

    $broadcastId = '';
    foreach (['broadcast_id', 'id'] as $key) {
        if (isset($json[$key]) && is_scalar($json[$key])) {
            $broadcastId = (string)$json[$key];
            break;
        }
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $broadcastId = (string)$_POST[$key];
            break;
        }
        if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
            $broadcastId = (string)$_GET[$key];
            break;
        }
    }

    if (!preg_match('/^[a-f0-9]{24}$/i', $broadcastId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Invalid broadcast_id']);
        exit;
    }

    try {
        $collection = DatabaseConnection::getDefaultDatabase()->notifications;
        $filter = [
            'broadcast_id' => $broadcastId,
            'type'         => 'broadcast',
            'global'       => true,
        ];
        $sample = $collection->findOne($filter, ['projection' => ['title' => 1, 'category' => 1]]);
        if (!$sample) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Broadcast not found']);
            exit;
        }

        $result = $collection->deleteMany($filter);
        $deleted = $result->getDeletedCount();

        AuditLog::log('delete', 'notifications', 'broadcast', [
            'action'       => 'delete_broadcast',
            'broadcast_id' => $broadcastId,
            'title'        => (string)($sample['title'] ?? ''),
            'category'     => (string)($sample['category'] ?? ''),
            'deleted'      => $deleted,
        ], (string)($admin->getUserId() ?? 'unknown'));

        echo json_encode([
            'status'  => 'success',
            'deleted' => $deleted,
        ]);
    } catch (\Throwable $e) {
        error_log('notifications_broadcasts delete failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Could not delete broadcast']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'error' => 'Method not allowed']);
