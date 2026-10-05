<?php
/**
 * Zeal / Jolt ledger.
 *
 * Balances live in `user_stats` (with a denormalized copy on `users.zeal_stats`).
 * Every movement — earned or spent — is also appended to the `transactions`
 * collection so the admin Transaction Monitor (/admin/transactions) can explain
 * any balance. See https://docs.selfmade.ninja/admin/economy
 *
 * Required after load.php (needs DatabaseConnection + AuditLog).
 */

/* --------------------------------------------------------------- taxonomy */

/** Type taxonomy grouped the way the monitor filters it. */
function currency_groups(): array
{
    return [
        'earning' => [
            'Quiz Completion' => 'Perfect first completion of a quiz',
            'CTF Challenge'   => 'First-time solve of a CTF challenge',
            'Learn AI'        => 'Learn AI module reward',
            'Code Arena'      => 'Code Arena placement reward',
            'Clan'            => 'Clan contribution reward',
            'Streak Daily'    => 'Daily login streak credit',
            'Initial Credit'  => 'Account starter credit',
        ],
        'spending' => [
            'Generated Problem' => 'AI generated a quiz set',
            'AI Assist'         => 'Learn AI assist unlocked',
            'Hint'              => 'CTF hint purchase',
            'Timer Renewal'     => 'Attempt timer renewal',
            'Store'             => 'Store purchase',
        ],
        'system' => [
            'Admin Adjustment' => 'Manual adjustment by an administrator',
            'System Top-up'    => 'System issued top-up',
            'Correction'       => 'Balance correction',
            'Transfer'         => 'Entitlement transfer between accounts',
        ],
    ];
}

/** Flat map of type => group. */
function currency_type_groups(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (currency_groups() as $group => $types) {
            foreach (array_keys($types) as $type) $map[$type] = $group;
        }
    }
    return $map;
}

function currency_group_of(string $type): string
{
    $map = currency_type_groups();
    return $map[$type] ?? 'system';
}

function currency_label(string $currency): string
{
    return strtolower($currency) === 'jolt' ? 'Jolt' : 'Zeal';
}

function currency_emoji(string $currency): string
{
    return strtolower($currency) === 'jolt' ? '⚡' : '🔥';
}

function currency_normalize(string $currency): ?string
{
    $currency = strtolower(trim($currency));
    return in_array($currency, ['zeal', 'jolt'], true) ? $currency : null;
}

function currency_normalize_direction(string $direction): ?string
{
    $direction = strtolower(trim($direction));
    if (in_array($direction, ['earned', 'add', 'credit', 'plus', '+'], true)) return 'earned';
    if (in_array($direction, ['spent', 'subtract', 'debit', 'minus', '-'], true)) return 'spent';
    return null;
}

/* ------------------------------------------------------------------ write */

/** Denormalized identity used on every ledger row. */
function currency_user_context(string $email): array
{
    $out = ['user_id' => '', 'username' => ''];
    try {
        $row = DatabaseConnection::getDefaultDatabase()->users->findOne(
            ['email' => $email],
            ['projection' => ['user_id' => 1, 'username' => 1, 'first_name' => 1, 'last_name' => 1]]
        );
        if ($row) {
            $out['user_id']  = (string)($row['user_id'] ?? '');
            $out['username'] = (string)($row['username'] ?? '');
            if ($out['username'] === '') {
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($name !== '') $out['username'] = $name;
            }
        }
    } catch (Throwable $e) {
        error_log('currency_user_context: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Append one movement to the ledger.
 *
 * @param array $tx user_email, direction(earned|spent), currency(zeal|jolt),
 *                  amount(>0), type, description, reason, source, actor,
 *                  valid, created_at
 * @return string|null inserted id, or null when the write failed
 */
function currency_record(array $tx): ?string
{
    $currency  = currency_normalize((string)($tx['currency'] ?? ''));
    $direction = currency_normalize_direction((string)($tx['direction'] ?? ''));
    $email     = trim((string)($tx['user_email'] ?? ''));
    $amount    = (int)($tx['amount'] ?? 0);

    if ($email === '' || $currency === null || $direction === null || $amount <= 0) {
        error_log('currency_record: invalid transaction payload');
        return null;
    }

    $type   = trim((string)($tx['type'] ?? ''));
    if ($type === '' || !isset(currency_type_groups()[$type])) $type = 'Admin Adjustment';

    $context = currency_user_context($email);

    $doc = [
        'user_email'   => $email,
        'user_id'      => (string)($tx['user_id'] ?? $context['user_id']),
        'username'     => (string)($tx['username'] ?? $context['username']),
        'direction'    => $direction,
        'currency'     => $currency,
        'amount'       => $amount,
        'type'         => $type,
        'group'        => currency_group_of($type),
        'description'  => mb_substr(trim((string)($tx['description'] ?? '')), 0, 300),
        'reason'       => mb_substr(trim((string)($tx['reason'] ?? '')), 0, 300),
        'source'       => (string)($tx['source'] ?? 'system'),
        'actor'        => (string)($tx['actor'] ?? ''),
        'valid'        => (bool)($tx['valid'] ?? true),
        'created_at'   => (int)($tx['created_at'] ?? time()),
    ];

    try {
        $res = DatabaseConnection::getDefaultDatabase()->transactions->insertOne($doc);
        return (string)$res->getInsertedId();
    } catch (Throwable $e) {
        error_log('currency_record: ' . $e->getMessage());
        return null;
    }
}

/**
 * Credit or debit one account, keeping the balance and the ledger in step.
 *
 * Never lets a balance go negative: a debit needs `amount <= balance`.
 *
 * @return array{status:string,error?:string,balance?:int,amount?:int,transaction_id?:string}
 */
function currency_adjust(
    string $email,
    string $currency,
    string $direction,
    $amount,
    string $reason,
    string $actor,
    string $type = 'Admin Adjustment'
): array {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'error' => 'Valid email required'];
    }

    $currency = currency_normalize($currency);
    if ($currency === null) {
        return ['status' => 'error', 'error' => 'Currency must be Zeal or Jolt'];
    }

    $direction = currency_normalize_direction($direction);
    if ($direction === null) {
        return ['status' => 'error', 'error' => 'Direction must be add or subtract'];
    }

    if (!is_numeric($amount)) {
        return ['status' => 'error', 'error' => 'Amount must be a whole number'];
    }
    $amount = (int)$amount;
    if ($amount < 1) {
        return ['status' => 'error', 'error' => 'Amount must be at least 1'];
    }
    if ($amount > 1000000) {
        return ['status' => 'error', 'error' => 'Amount cannot exceed 1,000,000'];
    }

    $reason = trim($reason);
    $len = function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason);
    if ($len < 3) {
        return ['status' => 'error', 'error' => 'A reason is required (3 characters minimum)'];
    }
    if ($len > 300) {
        return ['status' => 'error', 'error' => 'Reason cannot exceed 300 characters'];
    }

    if (!isset(currency_type_groups()[$type])) $type = 'Admin Adjustment';

    try {
        $db = DatabaseConnection::getDefaultDatabase();

        $target = $db->users->findOne(['email' => $email]);
        if (!$target) {
            return ['status' => 'error', 'error' => 'User not found'];
        }

        // Balances always live in `user_stats`, seeded like Quiz::getUserStats().
        if (!$db->user_stats->findOne(['email' => $email], ['projection' => ['_id' => 1]])) {
            $db->user_stats->insertOne(['email' => $email, 'zeal' => 0, 'jolt' => 10]);
        }

        $before = (int)($db->user_stats->findOne(['email' => $email])[$currency] ?? 0);

        if ($direction === 'earned') {
            $db->user_stats->updateOne(['email' => $email], ['$inc' => [$currency => $amount]]);
        } else {
            $res = $db->user_stats->updateOne(
                ['email' => $email, $currency => ['$gte' => $amount]],
                ['$inc' => [$currency => -$amount]]
            );
            if ($res->getMatchedCount() === 0) {
                return ['status' => 'error', 'error' => 'Insufficient ' . currency_label($currency) . ' balance (has ' . number_format($before) . ')'];
            }
        }

        $after = (int)($db->user_stats->findOne(['email' => $email])[$currency] ?? 0);

        // Denormalized copy used by profile/nav before user_stats is read.
        try {
            $db->users->updateOne(
                ['email' => $email],
                ['$set' => ['zeal_stats.' . $currency => $after]]
            );
        } catch (Throwable $e) {
            error_log('currency_adjust users mirror: ' . $e->getMessage());
        }

        $signed = $after - $before;

        $txId = currency_record([
            'user_email'  => $email,
            'direction'   => $direction,
            'currency'    => $currency,
            'amount'      => $amount,
            'type'        => $type,
            'description' => ($direction === 'earned' ? 'Credited ' : 'Debited ') . number_format($amount) . ' ' . currency_label($currency) . ' — ' . $reason,
            'reason'      => $reason,
            'source'      => 'admin',
            'actor'       => $actor,
        ]);

        try {
            AuditLog::log(
                'update',
                'user',
                (string)($target['user_id'] ?? $email),
                [
                    'field'        => 'currency.' . $currency,
                    'action'       => 'adjust_currency',
                    'direction'    => $direction,
                    'amount'       => $amount,
                    'from'         => $before,
                    'to'           => $after,
                    'delta'        => $signed,
                    'reason'       => $reason,
                    'type'         => $type,
                    'target_email' => $email,
                    'transaction'  => $txId,
                    'actor'        => $actor,
                ],
                null // AuditLog resolves the acting session itself
            );
        } catch (Throwable $e) {
            error_log('currency_adjust audit: ' . $e->getMessage());
        }

        return [
            'status'         => 'success',
            'balance'        => $after,
            'previous'       => $before,
            'amount'         => $amount,
            'currency'       => $currency,
            'direction'      => $direction,
            'transaction_id' => (string)$txId,
        ];
    } catch (Throwable $e) {
        error_log('currency_adjust: ' . $e->getMessage());
        return ['status' => 'error', 'error' => 'Failed to adjust balance'];
    }
}

/* ------------------------------------------------------------------ read */

/**
 * Mongo filter for the monitor. `$q` keys: user, type, group, direction,
 * currency, status(all|valid|invalid), from, to (yyyy-mm-dd).
 */
function currency_filter(array $q): array
{
    $f = [];

    $status = strtolower(trim((string)($q['status'] ?? 'valid')));
    if ($status === 'valid')          $f['valid'] = true;
    elseif ($status === 'invalid')    $f['valid'] = false;

    $direction = currency_normalize_direction((string)($q['direction'] ?? ''));
    if (isset($q['direction']) && $direction !== null) $f['direction'] = $direction;

    $currency = currency_normalize((string)($q['currency'] ?? ''));
    if (isset($q['currency']) && $currency !== null) $f['currency'] = $currency;

    $type = trim((string)($q['type'] ?? ''));
    if ($type !== '') $f['type'] = $type;

    $group = strtolower(trim((string)($q['group'] ?? '')));
    if (in_array($group, ['earning', 'spending', 'system'], true)) $f['group'] = $group;

    $user = trim((string)($q['user'] ?? ''));
    if ($user !== '') {
        $rx = ['$regex' => preg_quote($user, '/'), '$options' => 'i'];
        $f['$or'] = [
            ['user_email' => $user],
            ['username'   => $user],
            ['user_email' => $rx],
            ['username'   => $rx],
        ];
    }

    $from = currency_date_boundary($q['from'] ?? null, true);
    $to   = currency_date_boundary($q['to'] ?? null, false);
    if ($from !== null) $f['created_at'] = ($f['created_at'] ?? []) + ['$gte' => $from];
    if ($to   !== null) $f['created_at'] = ($f['created_at'] ?? []) + ['$lte' => $to];

    return $f;
}

/** yyyy-mm-dd -> epoch seconds at start (or end) of day. */
function currency_date_boundary($value, bool $start): ?int
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = DateTime::createFromFormat('Y-m-d', $value);
    if ($ts === false) return null;
    if ($start) return $ts->setTime(0, 0, 0)->getTimestamp();
    return $ts->setTime(23, 59, 59)->getTimestamp();
}

/** Totals per direction+currency for a filter. */
function currency_totals(array $filter): array
{
    $out = [
        'earned' => ['zeal' => 0, 'jolt' => 0, 'n' => 0],
        'spent'  => ['zeal' => 0, 'jolt' => 0, 'n' => 0],
        'total'  => 0,
    ];
    try {
        $rows = DatabaseConnection::getDefaultDatabase()->transactions->aggregate([
            ['$match' => $filter],
            ['$group' => [
                '_id'   => ['direction' => '$direction', 'currency' => '$currency'],
                'total' => ['$sum' => '$amount'],
                'n'     => ['$sum' => 1],
            ]],
        ]);
        foreach ($rows as $row) {
            $dir  = (string)($row['_id']['direction'] ?? '');
            $cur  = (string)($row['_id']['currency'] ?? '');
            $sum  = (int)($row['total'] ?? 0);
            $n    = (int)($row['n'] ?? 0);
            if (!isset($out[$dir])) $out[$dir] = ['zeal' => 0, 'jolt' => 0, 'n' => 0];
            if (!isset($out[$dir][$cur])) $out[$dir][$cur] = 0;
            $out[$dir][$cur] += $sum;
            $out[$dir]['n']  += $n;
            $out['total']    += $n;
        }
    } catch (Throwable $e) {
        error_log('currency_totals: ' . $e->getMessage());
    }
    return $out;
}

/** Earnings/spending breakdown by type for one account (used on the user page). */
function currency_breakdown(string $email, string $direction = 'earned', string $currency = 'zeal'): array
{
    $out = [];
    if ($email === '') return $out;
    try {
        $rows = DatabaseConnection::getDefaultDatabase()->transactions->aggregate([
            ['$match' => [
                'user_email' => $email,
                'direction'  => currency_normalize_direction($direction) ?: 'earned',
                'currency'   => currency_normalize($currency) ?: 'zeal',
                'valid'      => true,
            ]],
            ['$group' => ['_id' => '$type', 'count' => ['$sum' => 1], 'total' => ['$sum' => '$amount']]],
            ['$sort'  => ['total' => -1]],
        ]);
        foreach ($rows as $row) {
            $out[] = ['type' => (string)($row['_id'] ?? 'Other'), 'count' => (int)($row['count'] ?? 0), 'total' => (int)($row['total'] ?? 0)];
        }
    } catch (Throwable $e) {
        error_log('currency_breakdown: ' . $e->getMessage());
    }
    return $out;
}

/** Latest ledger rows for one account. */
function currency_recent(string $email, int $limit = 5): array
{
    $out = [];
    if ($email === '') return $out;
    try {
        $cursor = DatabaseConnection::getDefaultDatabase()->transactions->find(
            ['user_email' => $email],
            ['sort' => ['created_at' => -1], 'limit' => max(1, min(100, $limit))]
        );
        foreach ($cursor as $row) $out[] = $row;
    } catch (Throwable $e) {
        error_log('currency_recent: ' . $e->getMessage());
    }
    return $out;
}

/** One page of ledger rows for the monitor table. */
function currency_rows(array $filter, int $page = 1, int $perPage = 25): array
{
    $page  = max(1, $page);
    $perPage = max(1, min(200, $perPage));
    $skip  = ($page - 1) * $perPage;
    $out   = [];
    try {
        $cursor = DatabaseConnection::getDefaultDatabase()->transactions->find(
            $filter,
            ['sort' => ['created_at' => -1], 'skip' => $skip, 'limit' => $perPage]
        );
        foreach ($cursor as $row) $out[] = $row;
    } catch (Throwable $e) {
        error_log('currency_rows: ' . $e->getMessage());
    }
    return $out;
}

/** Total row count for a filter (monitor pagination). */
function currency_count(array $filter): int
{
    try {
        return (int)DatabaseConnection::getDefaultDatabase()->transactions->countDocuments($filter);
    } catch (Throwable $e) {
        error_log('currency_count: ' . $e->getMessage());
        return 0;
    }
}
