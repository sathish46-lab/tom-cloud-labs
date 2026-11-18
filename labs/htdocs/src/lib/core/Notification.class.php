<?php

/**
 * Notification data layer — MongoDB `notifications` collection.
 *
 * Every notification is owned by exactly one user (per-user feed). Admin
 * broadcasts create one document per target user so each recipient can mark
 * their own copy read/delete independently.
 *
 * Document shape:
 *   {
 *     _id: ObjectId,
 *     user_id: string,          // owner of this notification
 *     category: string,         // system|mcp|clan|clubs|code_arena|discussions|quiz|score|streak|academy|report
 *     type: string,             // new_device|broadcast|mcp_connect|instance_ready|challenge_done|quiz_passed|...
 *     title: string,
 *     body: string,
 *     data: { url?, ... },      // click target + extra payload
 *     read: bool,
 *     created_at: UTCDateTime,
 *     read_at: UTCDateTime|null,
 *     global: bool,             // admin broadcast flag
 *     from_admin: string|null   // email of admin who sent it (broadcasts)
 *   }
 *
 * Indexes: {user_id:1, read:1, created_at:-1}, {user_id:1, category:1}
 */
class Notification {
    private const COLLECTION = 'notifications';

    /** Categories exposed in the bell dropdown tabs. */
    public const CATEGORIES = [
        'system', 'mcp', 'clan', 'clubs', 'code_arena', 'discussions',
        'quiz', 'score', 'streak', 'academy', 'report',
    ];

    /** Report tab is admin-only. */
    public const ADMIN_ONLY_CATEGORIES = ['report'];

    private static $cache = null;

    private static function collection(): MongoDB\Collection {
        return DatabaseConnection::getDefaultDatabase()->notifications;
    }

    /**
     * Create a notification for a single user.
     * Returns the inserted _id (string) or false on failure.
     */
    public static function create(
        string $userId,
        string $category,
        string $type,
        string $title,
        string $body,
        array $data = [],
        bool $global = false,
        ?string $fromAdmin = null
    ): ?string {
        if (!in_array($category, self::CATEGORIES, true)) {
            error_log("Notification::create invalid category: $category");
            return false;
        }
        try {
            $doc = [
                'user_id'    => (string) $userId,
                'category'   => $category,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => is_array($data) ? $data : [],
                'read'       => false,
                'created_at' => new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                'read_at'    => null,
                'global'     => (bool) $global,
                'from_admin' => $fromAdmin,
            ];
            $result = self::collection()->insertOne($doc);
            return $result ? (string) $result->getInsertedId() : false;
        } catch (\Throwable $e) {
            error_log('Notification::create failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin broadcast: create one notification per target user.
     * Returns the number of docs inserted.
     */
    public static function broadcast(
        string $title,
        string $body,
        string $category,
        array $targetUserIds,
        string $fromAdmin
    ): int {
        if (!in_array($category, self::CATEGORIES, true)) {
            error_log("Notification::broadcast invalid category: $category");
            return 0;
        }
        if ($targetUserIds === []) return 0;
        try {
            $now = new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000));
            $docs = [];
            foreach (array_unique($targetUserIds) as $uid) {
                $docs[] = [
                    'user_id'    => (string) $uid,
                    'category'   => $category,
                    'type'       => 'broadcast',
                    'title'      => $title,
                    'body'       => $body,
                    'data'       => [],
                    'read'       => false,
                    'created_at' => $now,
                    'read_at'    => null,
                    'global'     => true,
                    'from_admin' => $fromAdmin,
                ];
            }
            self::collection()->insertMany($docs);
            return count($docs);
        } catch (\Throwable $e) {
            error_log('Notification::broadcast failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * List notifications for a user.
     * Returns array of public payloads (newest first).
     */
    public static function listForUser(
        string $userId,
        ?string $category = null,
        int $limit = 20,
        int $offset = 0,
        bool $unreadOnly = false
    ): array {
        try {
            $filter = ['user_id' => (string) $userId];
            if ($category !== null) $filter['category'] = $category;
            if ($unreadOnly) $filter['read'] = false;

            $cursor = self::collection()->find(
                $filter,
                [
                    'sort'  => ['created_at' => -1],
                    'limit' => $limit,
                    'skip'  => $offset,
                ]
            );
            $out = [];
            foreach ($cursor as $doc) {
                $out[] = self::publicPayload($doc);
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('Notification::listForUser failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Count unread notifications for a user, optionally per-category. */
    public static function unreadCount(string $userId): array {
        try {
            $total = (int) self::collection()->countDocuments([
                'user_id' => (string) $userId,
                'read'    => false,
            ]);
            $byCategory = [];
            try {
                $agg = self::collection()->aggregate([
                    ['$match' => ['user_id' => (string) $userId, 'read' => false]],
                    ['$group' => ['_id' => '$category', 'count' => ['$sum' => 1]]],
                ]);
                foreach ($agg as $row) {
                    $byCategory[(string) ($row['_id'] ?? 'system')] = (int) ($row['count'] ?? 0);
                }
            } catch (\Throwable $e) {
                // aggregation non-fatal
            }
            return ['total' => $total, 'by_category' => $byCategory];
        } catch (\Throwable $e) {
            error_log('Notification::unreadCount failed: ' . $e->getMessage());
            return ['total' => 0, 'by_category' => []];
        }
    }

    /** Mark one notification read (ownership-checked). */
    public static function markRead(string $notificationId, string $userId): bool {
        try {
            $result = self::collection()->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($notificationId), 'user_id' => (string) $userId, 'read' => false],
                ['$set' => [
                    'read'   => true,
                    'read_at'=> new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                ]]
            );
            return $result ? (bool) $result->getModifiedCount() : false;
        } catch (\Throwable $e) {
            error_log('Notification::markRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Mark all notifications read for a user (optionally one category). */
    public static function markAllRead(string $userId, ?string $category = null): int {
        try {
            $filter = ['user_id' => (string) $userId, 'read' => false];
            if ($category !== null) $filter['category'] = $category;
            $result = self::collection()->updateMany(
                $filter,
                ['$set' => [
                    'read'   => true,
                    'read_at'=> new \MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
                ]]
            );
            return $result ? (int) $result->getModifiedCount() : 0;
        } catch (\Throwable $e) {
            error_log('Notification::markAllRead failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** Delete one notification (ownership-checked). */
    public static function delete(string $notificationId, string $userId): bool {
        try {
            $result = self::collection()->deleteOne([
                '_id'     => new \MongoDB\BSON\ObjectId($notificationId),
                'user_id' => (string) $userId,
            ]);
            return $result ? (bool) $result->getDeletedCount() : false;
        } catch (\Throwable $e) {
            error_log('Notification::delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Build a JSON-safe public payload from a stored doc. */
    private static function publicPayload(MongoDB\Model\BSONDocument|array $doc): array {
        $createdAt = isset($doc['created_at']) ? (int) (string) $doc['created_at'] : 0;
        return [
            'id'         => (string) ($doc['_id'] ?? ''),
            'user_id'    => (string) ($doc['user_id'] ?? ''),
            'category'   => (string) ($doc['category'] ?? 'system'),
            'type'       => (string) ($doc['type'] ?? ''),
            'title'      => (string) ($doc['title'] ?? ''),
            'body'       => (string) ($doc['body'] ?? ''),
            'data'       => is_array($doc['data'] ?? []) ? (array) $doc['data'] : [],
            'read'       => (bool) ($doc['read'] ?? false),
            'created_at' => $createdAt,
            'read_at'    => isset($doc['read_at']) ? (int) (string) $doc['read_at'] : null,
            'global'     => (bool) ($doc['global'] ?? false),
            'from_admin' => (string) ($doc['from_admin'] ?? ''),
            'ago'        => self::timeAgo($createdAt),
        ];
    }

    private static function timeAgo(int $ts): string {
        if ($ts <= 0) return 'just now';
        $diff = time() - $ts;
        if ($diff < 60)   return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
        return floor($diff / 86400) . ' day ago';
    }
}
