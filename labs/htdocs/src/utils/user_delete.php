<?php
/**
 * Admin user deletion pipeline.
 *
 * Delete = backup first, purge second, keep a restorable record forever:
 *
 *   1. OTP confirmation   — a 6-digit code mailed to the *acting admin*,
 *                           cached for DELETE_OTP_TTL seconds (2 minutes).
 *   2. Snapshot           — every per-user document across both databases is
 *                           copied into `deleted_users`, and the home folder
 *                           {storage_base}/{md5(email)} is moved under
 *                           {storage_base}/.deleted/.
 *   3. Purge              — the live documents are removed (users doc last),
 *                           lab/instance containers are stopped best-effort.
 *   4. Return Back        — `user_delete_restore()` rebuilds the account from
 *                           the snapshot, including the home folder.
 *
 * Requires Cache, DatabaseConnection, storage helpers (loaded via load.php).
 */

/** Confirmation-code lifetime, in seconds. */
const DELETE_OTP_TTL = 120;

/** How many history rows the snapshot viewer will page through. */
const DELETE_HISTORY_LIMIT = 500;

// Home-folder helpers live in storage.php, which is not part of load.php.
if (!function_exists('storage_base_path')) {
    require_once __DIR__ . '/storage.php';
}

/** Normalise a BSON array/document (or plain array) into a PHP array. */
function ud_array($v): array
{
    if ($v instanceof Traversable) {
        return iterator_to_array($v);
    }
    return is_array($v) ? $v : [];
}

/* ---------------------------------------------------------------- OTP cache */

function delete_otp_cache_key(string $adminEmail, string $targetEmail): string
{
    return 'admin_del_otp_' . md5(strtolower(trim($adminEmail)) . '|' . strtolower(trim($targetEmail)));
}

function delete_otp_store(string $adminEmail, string $targetEmail, string $otp): void
{
    Cache::set(delete_otp_cache_key($adminEmail, $targetEmail), [
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'admin'    => $adminEmail,
        'target'   => $targetEmail,
        'created'  => time(),
        'expires'  => time() + DELETE_OTP_TTL,
    ]);
}

function delete_otp_load(string $adminEmail, string $targetEmail): ?array
{
    $entry = Cache::get(delete_otp_cache_key($adminEmail, $targetEmail), null);
    return is_array($entry) ? $entry : null;
}

function delete_otp_clear(string $adminEmail, string $targetEmail): void
{
    // Cache has no delete(); storing null makes get() fall back to the default.
    Cache::set(delete_otp_cache_key($adminEmail, $targetEmail), null);
}

/**
 * Pure check against a cached entry — exists, not expired, OTP matches.
 *
 * @return array{ok:bool,error:?string}
 */
function delete_otp_check(?array $entry, string $otp, ?int $now = null): array
{
    $now = $now ?? time();

    if (!$entry || empty($entry['otp_hash'])) {
        return ['ok' => false, 'error' => 'No confirmation code requested. Send a new code first.'];
    }
    if ((int)($entry['expires'] ?? 0) < $now) {
        return ['ok' => false, 'error' => 'Confirmation code has expired (2 minutes). Request a new one.'];
    }
    if (!password_verify($otp, (string)$entry['otp_hash'])) {
        return ['ok' => false, 'error' => 'Incorrect confirmation code.'];
    }
    return ['ok' => true, 'error' => null];
}

/* ----------------------------------------------------------- collection map */

/**
 * Identity values used to match per-user documents. Collections disagree on
 * whether they key on email, user_email, username or user_id (int or string),
 * so every map entry declares which fields it recognises.
 */
function user_delete_identity_values(string $email, string $username, $userId): array
{
    return [
        'email'           => $email,
        'user_email'      => $email,
        'deploy.email'    => $email,
        'author_email'    => $email,
        'username'        => $username,
        'author'          => $username,
        'deploy.username' => $username,
        'reserved_to'     => $username,
        'allocated_to'    => $username,
        'shared_with'     => $username,
        'shared_by'       => $username,
        'user_id'         => $userId,
    ];
}

function user_delete_query(array $fields, array $values): array
{
    $or = [];
    foreach ($fields as $field) {
        if ($field === 'user_id') {
            $uid = $values['user_id'] ?? null;
            if ($uid !== null && $uid !== '') {
                $or[] = ['user_id' => $uid];
                $or[] = ['user_id' => (string)$uid];
                if (is_numeric($uid)) {
                    $or[] = ['user_id' => (int)$uid];
                }
            }
            continue;
        }
        $v = $values[$field] ?? '';
        if (is_string($v) && $v !== '') {
            $or[] = [$field => $v];
        }
    }
    if (!$or) {
        // Nothing to match on — never select a document.
        return ['__never__' => ['$exists' => false]];
    }
    return ['$or' => $or];
}

/**
 * Every per-user collection we back up and purge, in purge order.
 * `users` is deliberately last so the account stays resolvable mid-purge.
 *
 * db: 'default' = tom_labs_db, 'instances' = tom_labs_instances_db.
 */
function user_delete_collection_map(string $email, string $username, $userId): array
{
    $values = user_delete_identity_values($email, $username, $userId);

    $plan = [
        ['default', 'user_stats',            ['email']],
        ['default', 'transactions',           ['user_email', 'user_id']],
        ['default', 'user_activity',          ['email', 'user_id']],
        ['default', 'machine_labs',           ['email', 'user_id', 'username', 'deploy.email', 'deploy.username']],
        ['default', 'challenge_instances',    ['username', 'email', 'user_id']],
        ['default', 'challenge_submissions',  ['user_email', 'user_id', 'username']],
        ['default', 'challenge_leaderboard',  ['user_email', 'username', 'user_id']],
        ['default', 'quiz_attempts',          ['user_email', 'user_id']],
        ['default', 'quiz_jobs',              ['user_id']],
        ['default', 'user_lessons',           ['user_email', 'user_id']],
        ['default', 'user_achievements',      ['user_email', 'user_id']],
        ['default', 'code_submissions',       ['user_email', 'user_id', 'username']],
        ['default', 'ai_roadmaps',            ['user_id', 'author_email']],
        ['default', 'ai_roadmap_progress',    ['user_id']],
        ['default', 'ai_roadmap_jobs',        ['user_id']],
        ['default', 'ai_roadmap_likes',       ['user_id', 'username']],
        ['default', 'ai_lessons',             ['user_id', 'author_email', 'author']],
        ['default', 'ai_lesson_likes',        ['user_id', 'username']],
        ['default', 'ai_unlocked_lessons',    ['user_id', 'user_email']],
        ['default', 'ai_unlocked_prompts',    ['user_id', 'user_email']],
        ['default', 'ai_chat_history',        ['user_id', 'user_email']],
        ['default', 'ai_highlights',          ['user_id', 'email']],
        ['default', 'ai_lesson_jobs',         ['user_id']],
        ['default', 'domains',                ['user_id', 'email']],
        ['default', 'ssh_keys',               ['user_id', 'username', 'email']],
        ['default', 'devices',                ['user_id', 'email', 'username']],
        ['default', 'ip_registry',            ['email', 'user_id', 'reserved_to', 'allocated_to']],
        ['default', 'storage_usage',          ['user_email', 'user_id', 'username']],
        ['default', 'instance_likes',         ['user_id', 'username']],
        ['default', 'deploy_queue',           ['user_id', 'email']],
        ['default', 'error_events',           ['user_email', 'user_id', 'username']],
        ['default', 'audit_log',              ['user_id']],
        ['default', 'mcp_clients',            ['user_id', 'username', 'email']],
        ['default', 'mcp_tokens',             ['user_id', 'username', 'email']],
        ['default', 'mcp_auth_codes',         ['user_id', 'username', 'email']],
        ['default', 'mcp_grants',             ['user_id', 'username', 'email']],
        ['default', 'mcp_activity',           ['user_id', 'username', 'email']],
        ['default', 'global_settings',        ['user_id']],
        ['default', 'mysql_users',            ['user_id', 'email']],
        ['default', 'mysql_databases',        ['user_id', 'email']],
        ['default', 'mysql_services',         ['user_id', 'email']],
        ['default', 'mariadb_users',          ['user_id', 'email']],
        ['default', 'mariadb_databases',      ['user_id', 'email']],
        ['default', 'postgresql_users',       ['user_id', 'email']],
        ['default', 'postgresql_databases',   ['user_id', 'email']],
        ['default', 'mongodb_users',          ['user_id', 'email']],
        ['default', 'mongodb_databases',      ['user_id', 'email']],
        ['default', 'rabbitmq_users',         ['user_id', 'email']],
        ['default', 'rabbitmq_vhosts',        ['user_id', 'email']],
        ['default', 'redis_users',            ['user_id', 'email']],
        ['default', 'redis_databases',        ['user_id', 'email']],
        ['instances', 'instances',            ['user_id', 'username', 'email']],
        ['instances', 'instance_trash',       ['user_id', 'username', 'email']],
        ['instances', 'instance_shares',      ['shared_with', 'shared_by']],
        ['instances', 'instance_files',       ['username', 'email']],
        ['default', 'users',                  ['email']],
    ];

    $map = [];
    foreach ($plan as [$db, $col, $fields]) {
        $map[] = [
            'db'    => $db,
            'col'   => $col,
            'query' => user_delete_query($fields, $values),
        ];
    }
    return $map;
}

function user_delete_db(string $key)
{
    if ($key === 'instances') {
        return DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    }
    return DatabaseConnection::getDefaultDatabase();
}

/* ------------------------------------------------------------- home folders */

function user_delete_home_path(string $email): string
{
    return storage_base_path() . '/' . md5($email);
}

function user_delete_home_backup_root(): string
{
    return storage_base_path() . '/.deleted';
}

function user_delete_home_stats(string $dir): array
{
    $files = 0;
    $bytes = 0;
    if (is_dir($dir)) {
        $out = @shell_exec('find ' . escapeshellarg($dir) . ' -type f 2>/dev/null | wc -l');
        $du  = @shell_exec('du -sk ' . escapeshellarg($dir) . ' 2>/dev/null');
        if ($out !== null) {
            $files = (int)trim($out);
        }
        if ($du !== null && preg_match('/^\s*(\d+)/', $du, $m)) {
            $bytes = ((int)$m[1]) * 1024;
        }
    }
    return ['files' => $files, 'bytes' => $bytes];
}

/**
 * Move {storage_base}/{md5(email)} into {storage_base}/.deleted/ so the home
 * folder leaves the live tree but survives for Return Back.
 */
function user_delete_backup_home(string $email): array
{
    $src = user_delete_home_path($email);
    $info = [
        'source'      => $src,
        'backup_path' => null,
        'moved'       => false,
        'files'       => 0,
        'bytes'       => 0,
        'error'       => null,
    ];

    if (!is_dir($src)) {
        return $info;
    }

    $stats = user_delete_home_stats($src);
    $info['files'] = $stats['files'];
    $info['bytes'] = $stats['bytes'];

    $root = user_delete_home_backup_root();
    $dst  = $root . '/' . md5($email);
    if (is_dir($dst) || is_file($dst)) {
        $dst .= '.' . substr(md5((string)uniqid('', true)), 0, 8);
    }

    try {
        if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException('Cannot create backup directory ' . $root);
        }
        if (!@rename($src, $dst)) {
            throw new RuntimeException('Cannot move ' . $src . ' to ' . $dst);
        }
        $info['moved'] = true;
        $info['backup_path'] = $dst;
    } catch (Throwable $e) {
        // Non-fatal: the database snapshot still lands; note it for the admin.
        $info['error'] = $e->getMessage();
        error_log('user_delete_backup_home: ' . $e->getMessage());
    }
    return $info;
}

function user_delete_restore_home(array $files, string $email): array
{
    $result = ['moved' => false, 'error' => null];
    $backup = (string)($files['backup_path'] ?? '');
    if ($backup === '' || !is_dir($backup)) {
        $result['error'] = 'Home folder backup not found';
        return $result;
    }
    $dst = user_delete_home_path($email);
    if (is_dir($dst) || is_file($dst)) {
        $result['error'] = 'Live home folder already exists';
        return $result;
    }
    try {
        if (!@rename($backup, $dst)) {
            throw new RuntimeException('Cannot move ' . $backup . ' back to ' . $dst);
        }
        $result['moved'] = true;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        error_log('user_delete_restore_home: ' . $e->getMessage());
    }
    return $result;
}

/* -------------------------------------------------------- array references */

/**
 * Global documents that embed the user inside an array (quiz viewers, likes).
 * Backed up as id lists so the pull can be undone on restore.
 */
function user_delete_array_refs(string $email, string $username): array
{
    $refs = [
        'quizzes_viewers'   => [],
        'ai_lesson_likes'   => [],
        'ai_roadmap_likes'  => [],
    ];
    try {
        $db = DatabaseConnection::getDefaultDatabase();
        foreach ($db->quizzes->find(['viewers' => $email], ['projection' => ['_id' => 1]]) as $d) {
            $refs['quizzes_viewers'][] = (string)$d['_id'];
        }
        if ($username !== '') {
            foreach ($db->ai_lessons->find(['likes' => $username], ['projection' => ['_id' => 1]]) as $d) {
                $refs['ai_lesson_likes'][] = (string)$d['_id'];
            }
            foreach ($db->ai_roadmaps->find(['likes' => $username], ['projection' => ['_id' => 1]]) as $d) {
                $refs['ai_roadmap_likes'][] = (string)$d['_id'];
            }
        }
    } catch (Throwable $e) {
        error_log('user_delete_array_refs: ' . $e->getMessage());
    }
    return $refs;
}

/* ---------------------------------------------------------------- collect */

/**
 * Copy every matching document out of the live collections. The `users` doc
 * is returned separately (snapshot.user) so the map can still purge it.
 *
 * @return array{user:?array,collections:array<string,array>}
 */
function user_delete_collect(array $map): array
{
    $userDoc = null;
    $collections = [];

    foreach ($map as $m) {
        try {
            $coll = user_delete_db($m['db'])->{$m['col']};
            $docs = iterator_to_array($coll->find($m['query']), false);
        } catch (Throwable $e) {
            error_log('user_delete_collect ' . $m['col'] . ': ' . $e->getMessage());
            continue;
        }
        if ($m['col'] === 'users') {
            $userDoc = $docs[0] ?? null;
            continue;
        }
        if ($docs) {
            $collections[$m['col']] = $docs;
        }
    }
    return ['user' => $userDoc, 'collections' => $collections];
}

function user_delete_summary($user, array $collections): array
{
    $count = static fn (string $col): int => count($collections[$col] ?? []);
    $stats = $collections['user_stats'][0] ?? null;

    $zeal = (int)($stats['zeal'] ?? $user['zeal_stats']['zeal'] ?? 0);
    $jolt = (int)($stats['jolt'] ?? $user['zeal_stats']['jolt'] ?? 0);

    $counts = [];
    foreach ($collections as $col => $docs) {
        $counts[$col] = count($docs);
    }

    return [
        'labs'        => $count('machine_labs'),
        'instances'   => $count('instances') + $count('instance_trash'),
        'devices'     => $count('devices'),
        'ips'         => $count('ip_registry'),
        'domains'     => $count('domains'),
        'ssh_keys'    => $count('ssh_keys'),
        'transactions' => $count('transactions'),
        'activity'    => $count('user_activity'),
        'quizzes'     => $count('quiz_attempts'),
        'challenges'  => $count('challenge_submissions'),
        'mcp_clients' => $count('mcp_clients'),
        'zeal'        => $zeal,
        'jolt'        => $jolt,
        'collections' => $counts,
    ];
}

/* ------------------------------------------------------------------ purge */

function user_delete_purge(array $map, string $email, string $username, array $refs): void
{
    foreach ($map as $m) {
        user_delete_db($m['db'])->{$m['col']}->deleteMany($m['query']);
    }

    $db = DatabaseConnection::getDefaultDatabase();
    $objectIds = static function (array $ids) {
        $out = [];
        foreach ($ids as $id) {
            try {
                $out[] = new MongoDB\BSON\ObjectId((string)$id);
            } catch (Throwable $e) {
                // Skip malformed ids collected from an older snapshot.
            }
        }
        return $out;
    };

    if ($refs['quizzes_viewers'] ?? []) {
        $db->quizzes->updateMany(
            ['_id' => ['$in' => $objectIds($refs['quizzes_viewers'])]],
            ['$pull' => ['viewers' => $email]]
        );
    }
    if ($username !== '' && ($refs['ai_lesson_likes'] ?? [])) {
        $db->ai_lessons->updateMany(
            ['_id' => ['$in' => $objectIds($refs['ai_lesson_likes'])]],
            ['$pull' => ['likes' => $username]]
        );
    }
    if ($username !== '' && ($refs['ai_roadmap_likes'] ?? [])) {
        $db->ai_roadmaps->updateMany(
            ['_id' => ['$in' => $objectIds($refs['ai_roadmap_likes'])]],
            ['$pull' => ['likes' => $username]]
        );
    }
}

/**
 * Best-effort teardown of the user's running labs and instances.
 * Never throws — deletion must not fail because a broker is down.
 */
function user_delete_stop_containers(array $collections, string $username): void
{
    foreach (['instances', 'instance_trash'] as $col) {
        foreach ($collections[$col] ?? [] as $doc) {
            $hash = (string)($doc['instance_hash'] ?? '');
            if ($hash === '') {
                continue;
            }
            @shell_exec('docker stop ' . escapeshellarg($hash) . ' 2>/dev/null');
            @shell_exec('docker rm -f ' . escapeshellarg($hash) . ' 2>/dev/null');
        }
    }

    $labs = $collections['machine_labs'] ?? [];
    if (!$labs) {
        return;
    }
    try {
        if (!class_exists('RabbitClient')) {
            require_once __DIR__ . '/../lib/core/RabbitClient.class.php';
        }
        $rabbit = new RabbitClient();
        foreach ($labs as $doc) {
            $rabbit->sendToQueue('labs_jobs', [
                'action' => 'stop',
                'lab'    => (string)($doc['lab_type'] ?? ''),
                'hash'   => (string)($doc['instance_hash'] ?? ''),
                'user'   => $username,
            ]);
        }
    } catch (Throwable $e) {
        error_log('user_delete_stop_containers: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------- delete flow */

/**
 * Snapshot → home-folder backup → purge → record. Returns a json-safe result.
 *
 * @return array{status:string,error?:string,id?:string,summary?:array,files?:array}
 */
function user_delete_run(string $email, string $adminEmail, $adminUserId): array
{
    $db = DatabaseConnection::getDefaultDatabase();

    $user = $db->users->findOne(['email' => $email]);
    if (!$user) {
        return ['status' => 'error', 'error' => 'User not found'];
    }

    $username = (string)($user['username'] ?? '');
    $userId   = $user['user_id'] ?? null;
    $map      = user_delete_collection_map($email, $username, $userId);

    $collected = user_delete_collect($map);
    if (!$collected['user']) {
        return ['status' => 'error', 'error' => 'User not found'];
    }

    $refs   = user_delete_array_refs($email, $username);
    $files  = user_delete_backup_home($email);
    $summary = user_delete_summary($collected['user'], $collected['collections']);

    $record = [
        'email'       => $email,
        'username'    => $username,
        'user_id'     => $userId,
        'status'      => 'deleted',
        'deleted_at'  => time(),
        'deleted_by'  => $adminEmail,
        'deleted_by_id' => (string)($adminUserId ?? ''),
        'snapshot'    => [
            'user'       => $collected['user'],
            'collections' => $collected['collections'],
            'array_refs' => $refs,
            'files'      => $files,
            'summary'    => $summary,
        ],
    ];

    try {
        $inserted = $db->deleted_users->insertOne($record);
        $snapshotId = (string)$inserted->getInsertedId();
    } catch (Throwable $e) {
        error_log('user_delete_run snapshot: ' . $e->getMessage());
        return ['status' => 'error', 'error' => 'Could not store the backup snapshot: ' . $e->getMessage()];
    }

    try {
        user_delete_purge($map, $email, $username, $refs);
    } catch (Throwable $e) {
        error_log('user_delete_run purge: ' . $e->getMessage());
        $db->deleted_users->updateOne(
            ['_id' => $inserted->getInsertedId()],
            ['$set' => ['status' => 'partial', 'error' => $e->getMessage()]]
        );
        return [
            'status' => 'error',
            'error'  => 'Backup stored but the purge failed: ' . $e->getMessage(),
            'id'     => $snapshotId,
        ];
    }

    user_delete_stop_containers($collected['collections'], $username);

    try {
        AuditLog::log('delete_user', 'user', $email, [
            'snapshot_id' => $snapshotId,
            'restorable'  => true,
            'summary'     => $summary,
        ], (string)($adminUserId ?? ''));
    } catch (Throwable $e) {
        error_log('user_delete_run audit: ' . $e->getMessage());
    }

    return ['status' => 'success', 'id' => $snapshotId, 'summary' => $summary, 'files' => $files];
}

/* ------------------------------------------------------------ restore flow */

/**
 * Return Back: rebuild a deleted account from its snapshot.
 *
 * @return array{status:string,error?:string}
 */
function user_delete_restore(string $snapshotId, string $adminEmail, $adminUserId): array
{
    $db = DatabaseConnection::getDefaultDatabase();

    if (!preg_match('/^[a-f0-9]{24}$/', $snapshotId)) {
        return ['status' => 'error', 'error' => 'Invalid snapshot id'];
    }

    $snap = $db->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($snapshotId)]);
    if (!$snap) {
        return ['status' => 'error', 'error' => 'Snapshot not found'];
    }
    if (($snap['status'] ?? '') === 'restored') {
        return ['status' => 'error', 'error' => 'This account has already been restored'];
    }

    $email    = (string)($snap['email'] ?? '');
    $username = (string)($snap['username'] ?? '');
    $userId   = $snap['user_id'] ?? null;
    $userDoc  = $snap['snapshot']['user'] ?? null;

    if ($email === '' || !$userDoc) {
        return ['status' => 'error', 'error' => 'Snapshot is incomplete'];
    }
    if ($db->users->findOne(['email' => $email])) {
        return ['status' => 'error', 'error' => 'A live account already uses ' . $email . ' — restore is blocked'];
    }
    if ($username !== '' && $db->users->findOne(['username' => $username])) {
        return ['status' => 'error', 'error' => 'A live account already uses the username ' . $username];
    }

    $map = user_delete_collection_map($email, $username, $userId);
    $collections = $snap['snapshot']['collections'] ?? [];
    $refs = $snap['snapshot']['array_refs'] ?? [];
    // Snapshot values come back as BSON objects — flatten to plain PHP first.
    $refList = static fn (string $key): array => ud_array($refs[$key] ?? []);

    try {
        foreach ($map as $m) {
            if ($m['col'] === 'users') {
                continue;
            }
            $docs = ud_array($collections[$m['col']] ?? []);
            if ($docs) {
                user_delete_db($m['db'])->{$m['col']}->insertMany($docs);
            }
        }
        $db->users->insertOne($userDoc);
    } catch (Throwable $e) {
        error_log('user_delete_restore: ' . $e->getMessage());
        return ['status' => 'error', 'error' => 'Restore failed while writing documents: ' . $e->getMessage()];
    }

    try {
        $objectIds = static function (array $ids) {
            $out = [];
            foreach ($ids as $id) {
                try {
                    $out[] = new MongoDB\BSON\ObjectId((string)$id);
                } catch (Throwable $e) {
                }
            }
            return $out;
        };
        if ($refList('quizzes_viewers')) {
            $db->quizzes->updateMany(
                ['_id' => ['$in' => $objectIds($refList('quizzes_viewers'))]],
                ['$addToSet' => ['viewers' => $email]]
            );
        }
        $lessonLikes = $refList('ai_lesson_likes');
        if ($username !== '' && $lessonLikes) {
            $db->ai_lessons->updateMany(
                ['_id' => ['$in' => $objectIds($lessonLikes)]],
                ['$addToSet' => ['likes' => $username]]
            );
        }
        $roadmapLikes = $refList('ai_roadmap_likes');
        if ($username !== '' && $roadmapLikes) {
            $db->ai_roadmaps->updateMany(
                ['_id' => ['$in' => $objectIds($roadmapLikes)]],
                ['$addToSet' => ['likes' => $username]]
            );
        }
    } catch (Throwable $e) {
        error_log('user_delete_restore refs: ' . $e->getMessage());
    }

    $home = user_delete_restore_home(ud_array($snap['snapshot']['files'] ?? []), $email);

    try {
        $db->deleted_users->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($snapshotId)],
            ['$set' => [
                'status'      => 'restored',
                'restored_at' => time(),
                'restored_by' => $adminEmail,
                'home'        => $home,
            ]]
        );
        AuditLog::log('restore_user', 'user', $email, [
            'snapshot_id' => $snapshotId,
            'home_restored' => (bool)($home['moved'] ?? false),
        ], (string)($adminUserId ?? ''));
    } catch (Throwable $e) {
        error_log('user_delete_restore finalise: ' . $e->getMessage());
    }

    return ['status' => 'success', 'home' => $home];
}

/* ---------------------------------------------------------- viewer helpers */

/** Render any snapshot value (BSON included) as safe display text. */
function ud_display($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    if ($v instanceof MongoDB\BSON\ObjectId) {
        return (string)$v;
    }
    if ($v instanceof MongoDB\BSON\UTCDateTime) {
        return ud_when($v);
    }
    if (is_bool($v)) {
        return $v ? 'yes' : 'no';
    }
    if ($v instanceof ArrayObject) {
        $v = $v->getArrayCopy();
    }
    if (is_array($v)) {
        if (!$v) {
            return '—';
        }
        return implode(', ', array_map('ud_display', $v));
    }
    if (is_object($v)) {
        return method_exists($v, '__toString') ? (string)$v : json_encode($v);
    }
    return (string)$v;
}

/** Human timestamp for int-seconds or BSON dates. */
function ud_when($v): string
{
    if ($v instanceof MongoDB\BSON\UTCDateTime) {
        return $v->toDateTime()->format('d M Y, H:i');
    }
    if (is_numeric($v) && (int)$v > 1000000000) {
        return date('d M Y, H:i', (int)$v);
    }
    if ($v === null || $v === '') {
        return '—';
    }
    return (string)$v;
}

/** Newest-first sort that tolerates mixed BSON/array rows. */
function ud_sort_desc(array $docs, string $field): array
{
    usort($docs, static function ($a, $b) use ($field) {
        $va = $a[$field] ?? 0;
        $vb = $b[$field] ?? 0;
        if ($va instanceof MongoDB\BSON\UTCDateTime) {
            $va = $va->toDateTime()->getTimestamp();
        }
        if ($vb instanceof MongoDB\BSON\UTCDateTime) {
            $vb = $vb->toDateTime()->getTimestamp();
        }
        return (int)$vb <=> (int)$va;
    });
    return $docs;
}
