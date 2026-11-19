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
 *     broadcast_id: string,     // shared by all docs from one admin broadcast
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
        'quiz', 'score', 'streak', 'account', 'academy', 'report',
    ];

    /** Report tab is admin-only. */
    public const ADMIN_ONLY_CATEGORIES = ['report'];

    /**
     * Central event catalog. Add a new notification type here once, then call
     * Notification::trigger('event_key', $userId, $context) from any feature —
     * no per-feature notification code needed.
     *
     * Each event defines:
     *   category — one of CATEGORIES (bell tab)
     *   type     — stored on the notification document
     *   title    — plain text, supports {placeholders}
     *   body     — plain text, supports {placeholders}
     *   url      — click target (optional)
     *   gate     — optional per-user switch: 'security' | 'mcp_live'
     */
    public const EVENTS = [
        'new_device' => [
            'category' => 'account',
            'type'     => 'new_device',
            'title'    => 'New device login',
            'body'     => '{username}, you signed in from a new device: {device} • {ip}.',
            'url'      => '/account/settings',
            'gate'     => 'security',
        ],
        'mcp_connect' => [
            'category' => 'mcp',
            'type'     => 'mcp_connect',
            'title'    => 'New MCP connection established',
            'body'     => '{client_name} connected to your account from {ip}. Client ID: {client_id}.',
            'url'      => '/mcp/activity',
            'gate'     => 'mcp_live',
        ],
    ];

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
     * Trigger a catalogued notification event.
     *
     * Respects the site-wide notification mode plus the user's category,
     * security and MCP-live switches. Missing placeholders render as empty.
     * Returns the notification id, or null when suppressed/failed.
     */
    public static function trigger(
        string $event,
        string $userId,
        array $context = [],
        ?string $email = null
    ): ?string {
        $spec = self::EVENTS[$event] ?? null;
        if ($spec === null) {
            error_log("Notification::trigger unknown event: $event");
            return null;
        }

        $category = (string)($spec['category'] ?? 'system');
        if (!in_array($category, self::CATEGORIES, true)) {
            error_log("Notification::trigger invalid category for $event: $category");
            return null;
        }

        if (NotificationSettings::mode() === NotificationSettings::MODE_OFF) {
            return null;
        }

        $userId = (string)$userId;
        if ($userId === '') return null;

        $email = ($email !== null && $email !== '') ? $email : self::lookupEmail($userId);
        if ($email !== '') {
            if (!UserNotificationSettings::categoryEnabled($email, $category)) {
                return null;
            }
            $gate = (string)($spec['gate'] ?? '');
            if ($gate === 'security' && !UserNotificationSettings::securityEnabled($email)) {
                return null;
            }
            if ($gate === 'mcp_live' && !UserNotificationSettings::mcpLiveEnabled($email)) {
                return null;
            }
        }

        $title = self::renderTemplate((string)($spec['title'] ?? ''), $context);
        $body  = self::renderTemplate((string)($spec['body'] ?? ''), $context);

        $data = (isset($context['data']) && is_array($context['data'])) ? $context['data'] : [];
        $url  = (string)($spec['url'] ?? '');
        if ($url !== '') $data['url'] = $url;

        $id = self::create(
            $userId,
            $category,
            (string)($spec['type'] ?? $event),
            $title,
            $body,
            $data
        );

        return (is_string($id) && $id !== '') ? $id : null;
    }

    /** Resolve a user's email from their notification user_id. */
    private static function lookupEmail(string $userId): string {
        try {
            $db  = DatabaseConnection::getDefaultDatabase();
            $ors = [['user_id' => $userId]];
            if (ctype_digit($userId)) $ors[] = ['user_id' => (int)$userId];
            if (preg_match('/^[a-f0-9]{24}$/i', $userId)) {
                $ors[] = ['_id' => new \MongoDB\BSON\ObjectId($userId)];
            }
            $doc = $db->users->findOne(['$or' => $ors], ['projection' => ['email' => 1]]);
            return (string)($doc['email'] ?? '');
        } catch (\Throwable $e) {
            error_log('Notification::lookupEmail failed: ' . $e->getMessage());
            return '';
        }
    }

    /** Replace {placeholder} tokens with context values. */
    private static function renderTemplate(string $template, array $context): string {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function ($m) use ($context) {
            return (string)($context[$m[1]] ?? '');
        }, $template) ?? $template;
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
            $broadcastId = bin2hex(random_bytes(12));
            $docs = [];
            foreach (array_unique($targetUserIds) as $uid) {
                $docs[] = [
                    'user_id'      => (string) $uid,
                    'category'     => $category,
                    'type'         => 'broadcast',
                    'broadcast_id' => $broadcastId,
                    'title'        => $title,
                    'body'         => $body,
                    'data'         => [],
                    'read'         => false,
                    'created_at'   => $now,
                    'read_at'      => null,
                    'global'       => true,
                    'from_admin'   => $fromAdmin,
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
        $createdAt = self::toTimestamp($doc['created_at'] ?? null);
        $data = $doc['data'] ?? [];
        if ($data instanceof MongoDB\Model\BSONDocument || $data instanceof MongoDB\Model\BSONArray) {
            $data = $data->getArrayCopy();
        } elseif (is_object($data)) {
            $data = (array) $data;
        }
        return [
            'id'         => (string) ($doc['_id'] ?? ''),
            'user_id'    => (string) ($doc['user_id'] ?? ''),
            'category'   => (string) ($doc['category'] ?? 'system'),
            'type'       => (string) ($doc['type'] ?? ''),
            'title'      => (string) ($doc['title'] ?? ''),
            'body'       => (string) ($doc['body'] ?? ''),
            'data'       => is_array($data) ? $data : [],
            'read'       => (bool) ($doc['read'] ?? false),
            'created_at' => $createdAt,
            'read_at'    => isset($doc['read_at']) ? self::toTimestamp($doc['read_at']) : null,
            'global'     => (bool) ($doc['global'] ?? false),
            'from_admin' => (string) ($doc['from_admin'] ?? ''),
            'ago'        => self::timeAgo($createdAt),
        ];
    }

    /** Normalise a BSON/DateTime/numeric timestamp to unix seconds. */
    private static function toTimestamp($value): int {
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->getTimestamp();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_numeric($value)) {
            $n = (int) $value;
            return $n > 9999999999 ? (int) floor($n / 1000) : $n;
        }
        return 0;
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
