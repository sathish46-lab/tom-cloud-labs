<?php
/**
 * Error reporting — turn an internal failure into something a learner can read
 * and an admin can audit.
 *
 * Flow (mirrors the request-review pattern):
 *
 *   raw failure ──► errors_report() ──► `error_events` (admin queue + history)
 *                 └► errors_public() ──► generic message + stable reference
 *
 * The raw text never leaves the server: `errors_sanitize()` maps known upstream
 * failures to plain English, and `errors_is_technical()` catches anything that
 * still looks like a stack trace, path, SQL or an API key.
 *
 * Required after load.php (needs DatabaseConnection).
 */

/** Statuses an error event can move through. */
function error_statuses(): array
{
    return ['open' => 'Open', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved'];
}

/* ------------------------------------------------------- classification */

/**
 * True when a message leaks implementation detail (paths, SQL, API keys,
 * stack traces, upstream payload dumps).
 */
function errors_is_technical(string $message): bool
{
    static $patterns = null;
    if ($patterns === null) {
        $patterns = [
            '/\bAPI[_ ]?key\b/i',
            '/AIza[0-9A-Za-z_-]{8,}/',
            '/\bBearer\s+[A-Za-z0-9._~+\/-]{16,}/',
            '/\b(\d{3})\s+(API|HTTP)\b/i',
            '/[\'"]?(reason|domain|metadata|locale|status_code|grpc-status)[\'"]?\s*[:{]/i',
            '/\/(var|usr|home|opt|etc|tmp|private)\/[A-Za-z0-9_.\-\/]+/',
            '/\b(SQLSTATE|MongoDB\\\\Driver|mysqli?_|PDO::|Undefined (variable|function|class)|TypeError|Division by zero|Call to (a )?null)\b/',
            '/\bstack trace\b|\b#0\s+\d+/i',
            '/\b(ECONNREFUSED|ETIMEDOUT|EAI_AGAIN|cURL error|getaddrinfo|Connection refused)\b/i',
            '/\b(throw in|thrown in|at line \d+|on line \d+)\b/i',
        ];
    }
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message)) return true;
    }
    return false;
}

/**
 * Plain-English stand-in for a known upstream failure.
 * Returns '' when nothing matches, so the caller can fall back to the generic text.
 */
function errors_sanitize(string $raw): string
{
    $m = strtolower($raw);

    if (preg_match('/api[_ ]?key|api_key_invalid|unauthenticated|permission denied|\b401\b|\b403\b/', $m)) {
        return 'The AI service rejected our credentials.';
    }
    if (preg_match('/rate.?limit|quota|too many requests|\b429\b|resource.?exhausted/', $m)) {
        return 'The AI service is busy right now. Please try again in a moment.';
    }
    if (preg_match('/timed? ?out|timeout|econnrefused|unavailable|connection (refused|reset)|\b50[234]\b/', $m)) {
        return 'The AI service is temporarily unavailable. Please try again in a moment.';
    }
    if (preg_match('/\b400\b|invalid (request|argument|parameter)|malformed|could not (process|parse)/', $m)) {
        return 'The AI service could not process this request. Try a different topic or difficulty.';
    }
    if (preg_match('/sqlstate|duplicate key|storage|write error|no space left/', $m)) {
        return 'A storage error occurred. Please try again.';
    }
    if (preg_match('/forbidden|not allowed|access denied/', $m)) {
        return 'You do not have access to that. Please check your permissions.';
    }
    if (preg_match('/insufficient|not enough|quota exceeded/', $m)) {
        return 'You do not have enough balance for that.';
    }
    return '';
}

/* ------------------------------------------------------------------ write */

function format_backtrace(array $frames): string
{
    $out = [];
    foreach ($frames as $f) {
        $file = (string)($f['file'] ?? '?');
        $line = (int)($f['line'] ?? 0);
        $fn   = (isset($f['class']) ? $f['class'] . ($f['type'] ?? '::') : '') . ($f['function'] ?? '?');
        $out[] = $file . ':' . $line . ' → ' . $fn . '()';
    }
    return implode("\n", $out);
}

function errors_excerpt(string $raw, int $max = 300): string
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    return function_exists('mb_substr') ? mb_substr($raw, 0, $max) : substr($raw, 0, $max);
}

/**
 * Stable public reference for a failure — same context, message and account
 * always hash to the same code, so repeated polls collapse into one event.
 */
function errors_ref(array $ctx): string
{
    $seed = ($ctx['context'] ?? '') . '|' . ($ctx['message'] ?? '') . '|' . ($ctx['user_email'] ?? '');
    return 'ERR-' . strtoupper(substr(hash('sha256', $seed), 0, 6));
}

/**
 * Record a failure and return the reference to show the user.
 *
 * @param array $ctx context, message (raw), severity, user_email, user_id,
 *                   url, extra (array, kept as the admin-only detail)
 * @return string reference code, or '' when the write itself failed
 */
function errors_report(array $ctx): string
{
    $message = trim((string)($ctx['message'] ?? ''));
    if ($message === '') return '';

    $context = trim((string)($ctx['context'] ?? ''));
    if ($context === '') $context = 'app';

    $severity = in_array($ctx['severity'] ?? '', ['error', 'warning', 'info'], true) ? $ctx['severity'] : 'error';
    $email    = trim((string)($ctx['user_email'] ?? ''));
    $ref      = errors_ref([
        'context'    => $context,
        'message'    => $message,
        'user_email' => $email,
    ]);

    try {
        $db   = DatabaseConnection::getDefaultDatabase();
        $now  = time();
        $friendly = errors_sanitize($message);
        $detail   = function_exists('mb_substr') ? mb_substr($message, 0, 4000) : substr($message, 0, 4000);

        // Repeated occurrences of the same failure inside a minute just bump the
        // counter — a poll loop must not flood the admin queue.
        $existing = $db->error_events->findOne([
            'ref' => $ref,
            'status' => 'open',
            'updated_at' => ['$gte' => $now - 60],
        ]);
        if ($existing) {
            $db->error_events->updateOne(
                ['_id' => $existing['_id']],
                ['$inc' => ['count' => 1], '$set' => ['updated_at' => $now]]
            );
            return $ref;
        }

        $identity = [];
        if ($email !== '') {
            try {
                $u = $db->users->findOne(['email' => $email], ['projection' => ['user_id' => 1, 'username' => 1, 'role' => 1]]);
                if ($u) {
                    $identity = [
                        'user_id'   => (string)($u['user_id'] ?? ''),
                        'username'  => (string)($u['username'] ?? ''),
                        'user_role' => (string)($u['role'] ?? ''),
                    ];
                }
            } catch (Throwable $e) { /* identity is best-effort */ }
        }

        $extra = $ctx['extra'] ?? [];
        if (!is_array($extra)) $extra = ['info' => (string)$extra];

        // Every event must answer "which line did this come from?".
        if (empty($extra['file'])) {
            $extra = errors_caller_location() + $extra;
        }
        if (!empty($extra['file'])) {
            $extra['where'] = $extra['file'] . ':' . (int)($extra['line'] ?? 0);
        }
        if (empty($extra['trace'])) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
            $bt = array_values(array_filter($bt, function ($f) {
                return ($f['file'] ?? '') !== '' && $f['file'] !== __FILE__;
            }));
            if ($bt) $extra['trace'] = errors_excerpt(format_backtrace($bt), 1500);
        }

        $db->error_events->insertOne([
            'ref'        => $ref,
            'context'    => $context,
            'severity'   => $severity,
            'message'    => errors_excerpt($friendly !== '' ? $friendly : $message),
            'detail'     => $detail,
            'extra'      => $extra,
            'user_email' => $email,
            'user_id'    => (string)($ctx['user_id'] ?? ($identity['user_id'] ?? '')),
            'username'   => (string)($identity['username'] ?? ''),
            'user_role'  => (string)($identity['user_role'] ?? ''),
            'url'        => errors_excerpt((string)($ctx['url'] ?? ($_SERVER['REQUEST_URI'] ?? '')), 300),
            'ip'         => (string)($ctx['ip'] ?? (function_exists('get_client_ip') ? get_client_ip() : '')),
            'status'     => 'open',
            'count'      => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'handled_at' => null,
            'handled_by' => '',
        ]);

        error_log('[error ' . $ref . '] ' . $context . ': ' . $message);
    } catch (Throwable $e) {
        // Reporting must never be the reason a request fails.
        error_log('errors_report: ' . $e->getMessage());
    }

    return $ref;
}

/* ------------------------------------------------------------ public text */

/** The only message a learner ever sees, plus their reference code. */
function errors_public(?string $ref, string $fallback = ''): string
{
    $base = trim($fallback);
    if ($base === '') $base = 'Something went wrong on our side.';
    if (errors_is_technical($base)) $base = 'Something went wrong on our side.';

    $base .= ' Please try again.';
    if ($ref) {
        $base .= ' If it keeps happening, contact an admin and quote reference ' . $ref . '.';
    }
    return $base;
}

/**
 * Sanitize a message that is about to be sent to a browser: technical text is
 * replaced, anything else is passed through untouched.
 */
function errors_to_user(string $raw, ?string &$ref = null): string
{
    $ref = null;
    if ($raw === '') return 'Something went wrong on our side. Please try again.';
    if (!errors_is_technical($raw)) return $raw;

    $friendly = errors_sanitize($raw);
    $ref = errors_report(['context' => 'user_visible', 'message' => $raw]);
    return errors_public($ref, $friendly);
}

/**
 * Turn an exception into the payload an API endpoint should echo back.
 *
 * The raw message is always audited; only technical text is rewritten to
 * plain English (plus the reference code). Everything else is passed through,
 * so ordinary messages such as "Email already registered" stay intact.
 *
 * @return array payload with an `error` key, plus `ref` when one was minted
 */
function errors_from_exception(Throwable $e, string $context): array
{
    $raw = (string)$e->getMessage();
    $ref = errors_report([
        'context' => $context !== '' ? $context : 'api',
        'message' => $raw,
        'extra'   => [
            'class' => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => errors_excerpt($e->getTraceAsString(), 2000),
        ],
    ]);

    if ($raw !== '' && !errors_is_technical($raw)) {
        // Nothing to hide — but the caller still gets the audit reference.
        return ['error' => $raw, 'ref' => $ref];
    }
    return ['error' => errors_public($ref, errors_sanitize($raw)), 'ref' => $ref];
}

/**
 * Where in our own code this report came from — used when the caller did not
 * supply an exception (job polls, manual reports, worker callbacks).
 *
 * Walks past this file so the answer is the line that actually raised it.
 */
function errors_caller_location(): array
{
    $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
    foreach ($frames as $frame) {
        $file = (string)($frame['file'] ?? '');
        if ($file === '' || $file === __FILE__) continue;
        if (substr($file, -10) === 'errors.php') continue;
        return ['file' => $file, 'line' => (int)($frame['line'] ?? 0)];
    }
    return [];
}

/* ------------------------------------------------------------------ read */

function errors_filter(array $q): array
{
    $f = [];

    $status = strtolower(trim((string)($q['status'] ?? 'open')));
    if ($status !== 'all' && isset(error_statuses()[$status])) $f['status'] = $status;

    $context = trim((string)($q['context'] ?? ''));
    if ($context !== '') $f['context'] = $context;

    $severity = strtolower(trim((string)($q['severity'] ?? '')));
    if (in_array($severity, ['error', 'warning', 'info'], true)) $f['severity'] = $severity;

    $user = trim((string)($q['user'] ?? ''));
    if ($user !== '') {
        $rx = ['$regex' => preg_quote($user, '/'), '$options' => 'i'];
        $f['$or'] = [['user_email' => $user], ['user_email' => $rx], ['username' => $rx], ['ref' => strtoupper($user)]];
    }

    $search = trim((string)($q['q'] ?? ''));
    if ($search !== '') {
        $rx = ['$regex' => preg_quote($search, '/'), '$options' => 'i'];
        $f['$or'] = array_merge($f['$or'] ?? [], [['ref' => $rx], ['message' => $rx], ['context' => $rx]]);
    }

    $from = errors_date_boundary($q['from'] ?? null, true);
    $to   = errors_date_boundary($q['to'] ?? null, false);
    if ($from !== null) $f['created_at'] = ($f['created_at'] ?? []) + ['$gte' => $from];
    if ($to   !== null) $f['created_at'] = ($f['created_at'] ?? []) + ['$lte' => $to];

    return $f;
}

function errors_date_boundary($value, bool $start): ?int
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = DateTime::createFromFormat('Y-m-d', $value);
    if ($ts === false) return null;
    return $start ? $ts->setTime(0, 0, 0)->getTimestamp() : $ts->setTime(23, 59, 59)->getTimestamp();
}

function errors_rows(array $filter, int $page = 1, int $perPage = 25): array
{
    $page = max(1, $page);
    $perPage = max(1, min(200, $perPage));
    $out = [];
    try {
        $cursor = DatabaseConnection::getDefaultDatabase()->error_events->find($filter, [
            'sort' => ['updated_at' => -1],
            'skip' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);
        foreach ($cursor as $row) $out[] = $row;
    } catch (Throwable $e) {
        error_log('errors_rows: ' . $e->getMessage());
    }
    return $out;
}

function errors_count(array $filter): int
{
    try {
        return (int)DatabaseConnection::getDefaultDatabase()->error_events->countDocuments($filter);
    } catch (Throwable $e) {
        error_log('errors_count: ' . $e->getMessage());
        return 0;
    }
}

function errors_find(string $ref): ?array
{
    try {
        $row = DatabaseConnection::getDefaultDatabase()->error_events->findOne(['ref' => strtoupper(trim($ref))]);
        return $row ? (array)$row : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Distinct contexts seen at least once — the monitor's Type filter. */
function error_contexts(): array
{
    $out = [];
    try {
        foreach (DatabaseConnection::getDefaultDatabase()->error_events->distinct('context') as $c) {
            $c = (string)$c;
            if ($c !== '') $out[] = $c;
        }
        sort($out);
    } catch (Throwable $e) { /* non-fatal */ }
    return $out;
}

function errors_counts(): array
{
    $out = ['open' => 0, 'acknowledged' => 0, 'resolved' => 0, 'total' => 0, 'today' => 0, 'events' => 0];
    try {
        $db = DatabaseConnection::getDefaultDatabase();
        foreach ($db->error_events->aggregate([
            ['$group' => ['_id' => '$status', 'n' => ['$sum' => 1], 'events' => ['$sum' => '$count']]],
        ]) as $row) {
            $s = (string)($row['_id'] ?? '');
            if (isset($out[$s])) {
                $out[$s] = (int)($row['n'] ?? 0);
                $out['events'] += (int)($row['events'] ?? 0);
            }
        }
        $out['total'] = $out['open'] + $out['acknowledged'] + $out['resolved'];
        $out['today'] = (int)$db->error_events->countDocuments(['created_at' => ['$gte' => errors_today_start()]]);
    } catch (Throwable $e) {
        error_log('errors_counts: ' . $e->getMessage());
    }
    return $out;
}

function errors_today_start(): int
{
    $ts = new DateTimeImmutable('today');
    return $ts->getTimestamp();
}

/** Move an event through the review flow. Admin-only; callers enforce auth. */
function errors_set_status(string $ref, string $status, string $actor = ''): bool
{
    $ref = strtoupper(trim($ref));
    if ($ref === '' || !isset(error_statuses()[$status])) return false;
    try {
        $res = DatabaseConnection::getDefaultDatabase()->error_events->updateOne(
            ['ref' => $ref],
            ['$set' => [
                'status'     => $status,
                'updated_at' => time(),
                'handled_at' => $status === 'open' ? null : time(),
                'handled_by' => $status === 'open' ? '' : $actor,
            ]]
        );
        return $res->getMatchedCount() > 0;
    } catch (Throwable $e) {
        error_log('errors_set_status: ' . $e->getMessage());
        return false;
    }
}
