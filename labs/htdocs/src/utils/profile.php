<?php
/**
 * Profile helpers — user resolution plus the aggregates the profile page reads.
 *
 * Everything here is read-only and scoped to one user. Charts are built from
 * `user_activity` (page views with a `date` + `hour` + `timestamp`), which is
 * the only long-lived per-user stream we store.
 */

/** Route words that mean "me", never a real username. */
function profile_self_aliases(): array
{
    return ['', 'account', 'profile', 'me'];
}

/**
 * Resolve the profile target from the `?username=` route param.
 * Falls back to the signed-in user when the param is an alias, unknown, or empty.
 */
function profile_resolve($requested): ?array
{
    $db = null;
    try {
        $db = DatabaseConnection::getDefaultDatabase();
    } catch (Throwable $e) {
        return null;
    }

    $requested = trim((string)$requested);
    $requested = str_replace(['"', "'", '<', '>', '&'], '', $requested);

    if (!in_array(strtolower($requested), profile_self_aliases(), true) && $requested !== '') {
        $doc = $db->users->findOne(['username' => $requested]);
        if (!$doc) {
            $doc = $db->users->findOne(['email' => $requested]);
        }
        if ($doc) {
            return $doc->getArrayCopy();
        }
    }

    $viewer = Session::getUser();
    if (!$viewer) {
        return null;
    }
    $doc = $db->users->findOne(['email' => $viewer->getEmail()]);
    return $doc ? $doc->getArrayCopy() : null;
}

/** Human "3 days ago" style label. */
function profile_time_ago(int $ts): string
{
    if ($ts <= 0) {
        return 'never';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400 * 30) {
        $d = (int)floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400 * 365) {
        $mo = (int)floor($diff / (86400 * 30));
        return $mo . ' month' . ($mo === 1 ? '' : 's') . ' ago';
    }
    $y = (int)floor($diff / (86400 * 365));
    return $y . ' year' . ($y === 1 ? '' : 's') . ' ago';
}

/** 1-based global position by zeal; null when the user has no stats row. */
function profile_global_rank(array $user, $db): ?int
{
    $email = (string)($user['email'] ?? '');
    if ($email === '') {
        return null;
    }
    try {
        $stats = $db->user_stats->findOne(['user_email' => $email], ['projection' => ['zeal' => 1]]);
        if (!$stats) {
            return null;
        }
        $above = $db->user_stats->countDocuments(['zeal' => ['$gt' => (int)$stats['zeal']]]);
        return $above + 1;
    } catch (Throwable $e) {
        return null;
    }
}

/** zeal + jolt for the target user, from user_stats with users.zeal_stats fallback. */
function profile_scores(array $user, $db): array
{
    $email = (string)($user['email'] ?? '');
    $zeal = (int)($user['zeal_stats']['zeal'] ?? 0);
    $jolt = (int)($user['zeal_stats']['jolt'] ?? 0);
    try {
        if ($email !== '') {
            $stats = $db->user_stats->findOne(['user_email' => $email]);
            if ($stats) {
                $zeal = (int)($stats['zeal'] ?? $zeal);
                $jolt = (int)($stats['jolt'] ?? $jolt);
            }
        }
    } catch (Throwable $e) {
    }
    return ['zeal' => $zeal, 'jolt' => $jolt];
}

/** Bucket a page view into a human area name. */
function profile_area_of(string $page): string
{
    $page = '/' . ltrim($page, '/');
    $first = explode('/', trim($page, '/'))[0] ?? '';
    $map = [
        'labs'      => 'Labs',
        'roadmaps'  => 'Roadmaps',
        'learn'     => 'Learn',
        'quiz'      => 'Quiz',
        'instances' => 'Instances',
        'mcp'       => 'MCP',
        'devices'   => 'Devices',
        'domains'   => 'Domains',
        'network'   => 'Network',
        'ssl'       => 'SSL',
        'services'  => 'Services',
        'admin'     => 'Admin',
        'home'      => 'Home',
        'dashboard' => 'Dashboard',
        'challenges'=> 'Challenges',
        'signin'    => 'Account',
        'account'   => 'Account',
        'profile'   => 'Account',
    ];
    if ($first === '' || $first === null) {
        return 'Other';
    }
    if (isset($map[$first])) {
        return $map[$first];
    }
    return 'Other';
}

/** @return array<int,array{date:string,count:int}> ascending by date, full history */
function profile_activity_series(array $user, $db): array
{
    $uid = $user['user_id'] ?? null;
    if ($uid === null) {
        return [];
    }
    $out = [];
    try {
        $cursor = $db->user_activity->aggregate([
            ['$match' => ['user_id' => ['$in' => [$uid, (string)$uid]]]],
            ['$group' => ['_id' => '$date', 'count' => ['$sum' => 1]]],
            ['$sort' => ['_id' => 1]],
        ]);
        foreach ($cursor as $row) {
            $out[] = ['date' => (string)$row['_id'], 'count' => (int)$row['count']];
        }
    } catch (Throwable $e) {
    }
    return $out;
}

/** @return int[] 24 slots, index = hour of day */
function profile_activity_hours(array $user, $db): array
{
    $uid = $user['user_id'] ?? null;
    $hours = array_fill(0, 24, 0);
    if ($uid === null) {
        return $hours;
    }
    try {
        $cursor = $db->user_activity->aggregate([
            ['$match' => ['user_id' => ['$in' => [$uid, (string)$uid]]]],
            ['$group' => ['_id' => '$hour', 'count' => ['$sum' => 1]]],
        ]);
        foreach ($cursor as $row) {
            $h = (int)$row['_id'];
            if ($h >= 0 && $h < 24) {
                $hours[$h] = (int)$row['count'];
            }
        }
    } catch (Throwable $e) {
    }
    return $hours;
}

/** @return array<int,array{label:string,count:int}> descending */
function profile_area_counts(array $user, $db, int $limit = 8): array
{
    $uid = $user['user_id'] ?? null;
    if ($uid === null) {
        return [];
    }
    $totals = [];
    try {
        $cursor = $db->user_activity->aggregate([
            ['$match' => ['user_id' => ['$in' => [$uid, (string)$uid]]]],
            ['$group' => ['_id' => '$page', 'count' => ['$sum' => 1]]],
        ]);
        foreach ($cursor as $row) {
            $area = profile_area_of((string)$row['_id']);
            $totals[$area] = ($totals[$area] ?? 0) + (int)$row['count'];
        }
    } catch (Throwable $e) {
    }
    arsort($totals);
    $out = [];
    foreach (array_slice($totals, 0, $limit, true) as $label => $count) {
        $out[] = ['label' => $label, 'count' => $count];
    }
    return $out;
}

/**
 * Skills = technology tags on the roadmaps this user has opened.
 * Roadmaps are found from saved progress first, then from visited /roadmaps/<slug> URLs.
 *
 * @return array<int,array{label:string,count:int}>
 */
function profile_skills(array $user, $db, int $limit = 8): array
{
    $uid = $user['user_id'] ?? null;
    if ($uid === null) {
        return [];
    }

    $ids = [];
    try {
        foreach ($db->ai_roadmap_progress->find(['user_id' => ['$in' => [$uid, (string)$uid]]], ['projection' => ['roadmap_id' => 1]]) as $row) {
            if (isset($row['roadmap_id'])) {
                $ids[(string)$row['roadmap_id']] = $row['roadmap_id'];
            }
        }
        if (!$ids) {
            foreach ($db->user_activity->find(
                ['user_id' => ['$in' => [$uid, (string)$uid]], 'page' => ['$regex' => '^/roadmaps/']],
                ['projection' => ['page' => 1], 'limit' => 60]
            ) as $row) {
                $slug = trim(explode('/', trim((string)$row['page'], '/'))[1] ?? '', " \t");
                if ($slug === '') {
                    continue;
                }
                $roadmap = $db->ai_roadmaps->findOne(['slug' => $slug], ['projection' => ['_id' => 1]]);
                if ($roadmap) {
                    $ids[(string)$roadmap['_id']] = $roadmap['_id'];
                }
            }
        }
    } catch (Throwable $e) {
    }

    if (!$ids) {
        return [];
    }

    $weights = [];
    $labels  = [];
    try {
        foreach ($db->ai_roadmaps->find(['_id' => ['$in' => array_values($ids)]], ['projection' => ['tags' => 1]]) as $roadmap) {
            if (!isset($roadmap['tags'])) {
                continue;
            }
            foreach ((array)$roadmap['tags'] as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') {
                    continue;
                }
                $key = strtolower($tag);
                $weights[$key] = ($weights[$key] ?? 0) + 1;
                if (!isset($labels[$key]) || strlen($tag) < strlen($labels[$key])) {
                    $labels[$key] = $tag;
                }
            }
        }
    } catch (Throwable $e) {
    }

    arsort($weights);
    $out = [];
    foreach (array_slice($weights, 0, $limit, true) as $key => $weight) {
        $out[] = ['label' => $labels[$key] ?? ucfirst($key), 'count' => (int)$weight];
    }
    return $out;
}

/** Summary counters shown on the Profile tab. */
function profile_facts(array $user, $db): array
{
    $uid = $user['user_id'] ?? null;
    $email = (string)($user['email'] ?? '');
    $idIn = $uid === null ? [] : [$uid, (string)$uid];

    $count = static function ($name, array $filter) use ($db): int {
        try {
            return (int)$db->selectCollection($name)->countDocuments($filter);
        } catch (Throwable $e) {
            return 0;
        }
    };

    $uidFilter = ['user_id' => ['$in' => $idIn]];
    $activeDays = 0;
    try {
        foreach ($db->user_activity->aggregate([
            ['$match' => $uidFilter],
            ['$group' => ['_id' => '$date']],
            ['$count' => 'n'],
        ]) as $row) {
            $activeDays = (int)($row['n'] ?? 0);
        }
    } catch (Throwable $e) {
    }

    return [
        'labs'        => $count('machine_labs', $uidFilter),
        'domains'     => $count('domains', $uidFilter),
        'ssh_keys'    => $count('ssh_keys', $uidFilter),
        'mcp_clients' => $count('mcp_clients', $uidFilter),
        'mcp_tokens'  => $count('mcp_tokens', $uidFilter),
        'roadmaps'    => $count('ai_roadmap_progress', $uidFilter),
        'events'      => $count('user_activity', $uidFilter),
        'audit'       => $count('audit_log', $uidFilter),
        'events_week' => $count('user_activity', $uidFilter + ['timestamp' => ['$gte' => time() - 7 * 86400]]),
        'active_days' => $activeDays,
        'email' => $email,
    ];
}

/**
 * Normalize the timestamp shapes we actually store (epoch seconds, epoch
 * milliseconds, BSON datetimes, ISO-8601 strings) into unix seconds.
 */
function profile_doc_ts(array $row): int
{
    foreach (['attempted_at', 'submitted_at', 'completed_at', 'created_at', 'deployed_at', 'timestamp', 'updated_at', 'liked_at'] as $key) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        $value = $row[$key];
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            try {
                $ts = (int)$value->toDateTime()->getTimestamp();
                if ($ts > 0) {
                    return $ts;
                }
            } catch (Throwable $e) {
            }
            continue;
        }
        if (is_numeric($value)) {
            $n = (float)$value;
            if ($n > 100000000000) { // epoch milliseconds
                $n /= 1000;
            }
            $n = (int)$n;
            if ($n > 0) {
                return $n;
            }
            continue;
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false && $ts > 0) {
                return $ts;
            }
        }
    }
    return 0;
}

/**
 * Per-month event counts for a collection keyed on `user_email`.
 *
 * Buckets are built in PHP rather than with $dateToString: our writers store
 * `attempted_at` as epoch seconds, which Mongo would read as milliseconds.
 *
 * @return array<int,array{ym:string,label:string,count:int}> ascending, $months buckets
 */
function profile_monthly_series(array $user, $db, string $collection, int $months = 12, array $extraMatch = []): array
{
    $email = (string)($user['email'] ?? '');
    $out = [];
    $base = new DateTimeImmutable('now');
    for ($i = max(1, $months) - 1; $i >= 0; $i--) {
        $d = $base->modify('-' . $i . ' months');
        $ym = $d->format('Y-m');
        $out[$ym] = ['ym' => $ym, 'label' => $d->format('M'), 'count' => 0];
    }
    if ($email === '') {
        return array_values($out);
    }

    try {
        $cursor = $db->selectCollection($collection)->find(
            array_merge(['user_email' => $email], $extraMatch),
            ['projection' => ['attempted_at' => 1, 'created_at' => 1, 'submitted_at' => 1, 'completed_at' => 1, 'timestamp' => 1]]
        );
        foreach ($cursor as $row) {
            $ts = profile_doc_ts($row->getArrayCopy());
            if ($ts <= 0) {
                continue;
            }
            $ym = date('Y-m', $ts);
            if (isset($out[$ym])) {
                $out[$ym]['count']++;
            }
        }
    } catch (Throwable $e) {
    }

    return array_values($out);
}

/**
 * Monthly page-view totals, oldest first — the honest stand-in for a zeal
 * history, since zeal itself is only stored as a running total.
 *
 * @return array<int,array{ym:string,label:string,count:int}>
 */
function profile_activity_months(array $user, $db, int $months = 6): array
{
    $uid = $user['user_id'] ?? null;
    $out = [];
    $base = new DateTimeImmutable('now');
    for ($i = max(1, $months) - 1; $i >= 0; $i--) {
        $d = $base->modify('-' . $i . ' months');
        $ym = $d->format('Y-m');
        $out[$ym] = ['ym' => $ym, 'label' => $d->format('M Y'), 'count' => 0];
    }
    if ($uid === null) {
        return array_values($out);
    }
    $oldestYm = (string)array_key_first($out);

    try {
        $cursor = $db->user_activity->aggregate([
            ['$match' => ['user_id' => ['$in' => [$uid, (string)$uid]], 'date' => ['$gte' => $oldestYm]]],
            ['$group' => ['_id' => ['$substrCP' => ['$date', 0, 7]], 'count' => ['$sum' => 1]]],
        ]);
        foreach ($cursor as $row) {
            $ym = (string)($row['_id'] ?? '');
            if (isset($out[$ym])) {
                $out[$ym]['count'] = (int)$row['count'];
            }
        }
    } catch (Throwable $e) {
    }

    return array_values($out);
}

/** @return array<int,array{label:string,count:int}> descending */
function profile_ctf_categories(array $user, $db, int $limit = 8): array
{
    $email = (string)($user['email'] ?? '');
    if ($email === '') {
        return [];
    }
    $totals = [];
    try {
        foreach ($db->selectCollection('challenge_submissions')->find(['user_email' => $email]) as $row) {
            $tags = $row['tags'] ?? $row['category'] ?? $row['topic'] ?? null;
            $tags = is_array($tags) ? $tags : [$tags];
            $seen = [];
            foreach ($tags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '' || isset($seen[strtolower($tag)])) {
                    continue;
                }
                $seen[strtolower($tag)] = true;
                $totals[$tag] = ($totals[$tag] ?? 0) + 1;
            }
        }
    } catch (Throwable $e) {
    }
    arsort($totals);
    $out = [];
    foreach (array_slice($totals, 0, $limit, true) as $label => $count) {
        $out[] = ['label' => (string)$label, 'count' => (int)$count];
    }
    return $out;
}

/**
 * Reverse-chronological activity feed for the Activity tab.
 *
 * Sources are accomplishments and lab events — never raw page views, so the
 * feed reads the same for signed-in and signed-out visitors. Domains and SSH
 * key titles are infrastructure detail, so they only surface to the profile
 * owner (or an admin).
 *
 * @return array<int,array{type:string,icon:string,text:string,ts:int,earned:?int}>
 */
function profile_feed(array $user, $db, int $limit = 40, bool $private = false): array
{
    $uid = $user['user_id'] ?? null;
    $idIn = $uid === null ? [] : [$uid, (string)$uid];
    $email = (string)($user['email'] ?? '');
    $username = (string)($user['username'] ?? '');
    $items = [];

    $esc = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $push = static function (string $type, string $icon, string $text, int $ts, ?int $earned = null) use (&$items): void {
        if ($ts <= 0) {
            return;
        }
        $items[] = ['type' => $type, 'icon' => $icon, 'text' => $text, 'ts' => $ts, 'earned' => $earned];
    };
    $collect = static function (string $collection, array $filter, array $options) use ($db): array {
        try {
            $out = [];
            foreach ($db->selectCollection($collection)->find($filter, $options) as $row) {
                $out[] = $row->getArrayCopy();
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    };

    if ($email !== '') {
        foreach ($collect('quiz_attempts', ['user_email' => $email], ['sort' => ['attempted_at' => -1], 'limit' => $limit]) as $r) {
            $push('quiz', 'bx bx-award', 'Completed quiz — scored <b>' . $esc((int)($r['score'] ?? 0) . '/' . (int)($r['total'] ?? 0)) . '</b>', profile_doc_ts($r));
        }

        foreach ($collect('challenge_submissions', ['user_email' => $email], ['sort' => ['created_at' => -1], 'limit' => $limit]) as $r) {
            $solved = (string)($r['status'] ?? '') !== 'failed';
            if (!$solved) {
                continue;
            }
            $ref = (string)($r['challenge_id'] ?? $r['title'] ?? $r['challenge_title'] ?? '');
            $ref = $ref !== '' ? ' <b>' . $esc($ref) . '</b>' : '';
            $earned = (int)($r['zeal_earned'] ?? $r['points'] ?? 0);
            $push('ctf', 'bx bx-shield-quarter', 'Solved a challenge' . $ref, profile_doc_ts($r), $earned > 0 ? $earned : null);
        }
    }

    if ($idIn) {
        $labLabel = 'lab';
        foreach ($collect('machine_labs', ['user_id' => ['$in' => $idIn]], ['projection' => ['lab_type' => 1, 'created_at' => 1, 'username' => 1, 'activity_log' => 1], 'sort' => ['created_at' => -1], 'limit' => 4]) as $r) {
            $labLabel = ucfirst((string)($r['lab_type'] ?? 'sandbox'));
            $push('lab', 'bx bx-rocket', 'Launched <b>' . $esc($labLabel) . '</b> lab', profile_doc_ts($r));

            $icons = [
                'Redeployed' => 'bx bx-refresh',
                'Paused'     => 'bx bx-pause',
                'Resumed'    => 'bx bx-play',
                'Stopped'    => 'bx bx-stop',
                'Started'    => 'bx bx-play',
                'Fast Apply' => 'bx bx-check-circle',
            ];
            foreach (array_slice((array)($r['activity_log'] ?? []), 0, 20) as $a) {
                $a = (array)$a;
                if (isset($a['user']) && $a['user'] !== '' && $username !== '' && (string)$a['user'] !== $username) {
                    continue;
                }
                $action = trim((string)($a['action'] ?? ''));
                $ts = (int)($a['timestamp'] ?? 0);
                if ($action === '' || $ts <= 0) {
                    continue;
                }
                if ((string)($a['type'] ?? 'lab') === 'preference') {
                    $push('lab', 'bx bx-slider-alt', 'Updated preferences for <b>' . $esc($labLabel) . '</b> lab', $ts);
                    continue;
                }
                $push('lab', $icons[$action] ?? 'bx bx-server', $esc($action) . ' <b>' . $esc($labLabel) . '</b> lab', $ts);
            }
        }

        $progress = [];
        $seenRoadmaps = [];
        foreach ($collect('ai_roadmap_progress', ['user_id' => ['$in' => $idIn]], ['sort' => ['created_at' => -1], 'limit' => 40]) as $p) {
            $key = (string)($p['roadmap_id'] ?? $p['_id'] ?? '');
            if (isset($seenRoadmaps[$key])) {
                continue;
            }
            $seenRoadmaps[$key] = true;
            $progress[] = $p;
        }
        $roadmapIds = [];
        foreach ($progress as $p) {
            if (isset($p['roadmap_id'])) {
                $roadmapIds[(string)$p['roadmap_id']] = $p['roadmap_id'];
            }
        }
        $roadmapTitles = [];
        if ($roadmapIds) {
            try {
                foreach ($db->ai_roadmaps->find(['_id' => ['$in' => array_values($roadmapIds)]], ['projection' => ['title' => 1, 'name' => 1, 'slug' => 1]]) as $rm) {
                    $roadmapTitles[(string)$rm['_id']] = (string)($rm['title'] ?? $rm['name'] ?? $rm['slug'] ?? '');
                }
            } catch (Throwable $e) {
            }
        }
        foreach ($progress as $p) {
            $key = (string)($p['roadmap_id'] ?? '');
            $title = $roadmapTitles[$key] ?? '';
            $push('learn', 'bx bx-map', 'Started roadmap' . ($title !== '' ? ' <b>' . $esc($title) . '</b>' : ''), profile_doc_ts($p));
        }

        foreach ($collect('ai_roadmap_likes', ['user_id' => ['$in' => $idIn]], ['sort' => ['liked_at' => -1], 'limit' => 10]) as $r) {
            $push('learn', 'bx bx-heart', 'Liked a roadmap', profile_doc_ts($r));
        }
        foreach ($collect('ai_lesson_likes', ['user_id' => ['$in' => $idIn]], ['sort' => ['liked_at' => -1], 'limit' => 10]) as $r) {
            $push('learn', 'bx bx-heart', 'Liked a lesson', profile_doc_ts($r));
        }

        if ($private) {
            foreach ($collect('domains', ['user_id' => ['$in' => $idIn]], ['sort' => ['created_at' => -1], 'limit' => 6]) as $r) {
                $push('domain', 'bx bx-globe', 'Mapped domain <b>' . $esc((string)($r['domain'] ?? '')) . '</b>', profile_doc_ts($r));
            }
            foreach ($collect('ssh_keys', ['user_id' => ['$in' => $idIn]], ['sort' => ['created_at' => -1], 'limit' => 6]) as $r) {
                $push('key', 'bx bx-key', 'Added SSH key <b>' . $esc((string)($r['title'] ?? $r['name'] ?? '')) . '</b>', profile_doc_ts($r));
            }
        }
    }

    usort($items, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
    return array_slice($items, 0, max(1, $limit));
}
