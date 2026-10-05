<?php
/**
 * Global leaderboard — data assembly + row rendering.
 *
 * Shared by src/template/pages/leaderboard-global.php (initial render of the
 * first 15 ranks) and src/api/leaderboard_rows.php (the "Load more" fetch,
 * which returns raw <tr> HTML for the next 10 ranks).
 */
require_once __DIR__ . '/leagues.php';
require_once __DIR__ . '/achievements.php';

/** Podium rows rendered on the initial page load (ranks 1–3). */
const LB_WINNER_COUNT = 3;

/** Table rows rendered under the podium on the initial page load (ranks 4–15). */
const LB_INITIAL_ROWS = 12;

/** Ranks rendered by the initial page load (3 winners + 12 rows). */
const LB_INITIAL_RANKS = LB_WINNER_COUNT + LB_INITIAL_ROWS;

/** Rows returned by one "Load more" request. */
const LB_LOAD_MORE_ROWS = 10;

/** Hard cap per request so a hand-crafted URL cannot dump the whole table. */
const LB_MAX_ROWS = 50;

/**
 * Ranking pipeline — runs on the `users` collection, so every account in the
 * users DB appears on the board. `user_stats` is left-joined on email; users
 * with no stats row simply rank with 0 zeal / 0 jolt. Nothing is hardcoded:
 * names come from `users.username`.
 *
 * @return array<int, array<string, mixed>>
 */
function leaderboard_ranked_pipeline(int $skip = 0, int $limit = 0): array
{
    $pipe = [
        ['$lookup' => [
            'from'    => 'user_stats',
            'let'     => ['e' => '$email'],
            'pipeline' => [
                ['$match' => ['$expr' => ['$eq' => ['$email', '$$e']]]],
                ['$sort'  => ['zeal' => -1]],
                ['$limit' => 1],
            ],
            'as' => '_s',
        ]],
        ['$addFields' => [
            'zeal'       => ['$ifNull' => [['$arrayElemAt' => ['$_s.zeal', 0]], 0]],
            'jolt'       => ['$ifNull' => [['$arrayElemAt' => ['$_s.jolt', 0]], 0]],
            'email' => '$email',
            '_user'      => '$$ROOT',
        ]],
        ['$sort' => ['zeal' => -1, '_id' => 1]],
    ];
    if ($skip > 0) {
        $pipe[] = ['$skip' => $skip];
    }
    if ($limit > 0) {
        $pipe[] = ['$limit' => $limit];
    }
    return $pipe;
}

function leaderboard_total(): int
{
    $pipe = leaderboard_ranked_pipeline();
    $pipe[] = ['$count' => 'n'];
    $rows = DatabaseConnection::getDefaultDatabase()->users->aggregate($pipe)->toArray();
    return (int)($rows[0]['n'] ?? 0);
}

/**
 * Ranked entries for a window of the leaderboard.
 *
 * @param int      $skip     ranks to skip (0 = rank 1)
 * @param int      $limit    how many ranks to return
 * @param int|null $ctfTotal platform-wide distinct challenge count (out)
 * @return array<int, array<string, mixed>> each entry carries 'rank' => 1-based
 */
function leaderboard_entries(int $skip, int $limit, ?int &$ctfTotal = null): array
{
    $db    = DatabaseConnection::getDefaultDatabase();
    $skip  = max(0, $skip);
    $limit = min(LB_MAX_ROWS, max(1, $limit));

    $raw = DatabaseConnection::getDefaultDatabase()
        ->users
        ->aggregate(leaderboard_ranked_pipeline($skip, $limit))
        ->toArray();

    $emails = [];
    foreach ($raw as $row) {
        if (!empty($row['user_email'])) {
            $emails[] = (string)$row['user_email'];
        }
    }

    // ── Bulk per-domain metrics. Every query is guarded: quiz_attempts,
    //    challenge_submissions and transactions may be empty or absent. ──────
    $quiz = [];
    if ($emails) {
        try {
            foreach ($db->quiz_attempts->aggregate([
                ['$match' => ['user_email' => ['$in' => $emails]]],
                ['$group' => [
                    '_id'      => '$user_email',
                    'attempts' => ['$sum' => 1],
                    'done'     => ['$sum' => ['$cond' => [['$eq' => ['$status', 'completed']], 1, 0]]],
                    'correct'  => ['$sum' => '$score'],
                    'asked'    => ['$sum' => '$total'],
                ]],
            ]) as $r) {
                $quiz[(string)$r['_id']] = $r;
            }
        } catch (Throwable $e) {
        }
    }

    $ctf = [];
    $ctfTotal = 0;
    if ($emails) {
        try {
            foreach ($db->challenge_submissions->aggregate([
                ['$match' => ['user_email' => ['$in' => $emails], 'status' => 'solved']],
                ['$group' => ['_id' => '$user_email', 'solved' => ['$sum' => 1]]],
            ]) as $r) {
                $ctf[(string)$r['_id']] = (int)$r['solved'];
            }
            $ctfTotal = count($db->challenge_submissions->distinct('challenge_id', ['status' => 'solved']));
        } catch (Throwable $e) {
        }
    }

    $txn = [];
    if ($emails) {
        try {
            foreach ($db->transactions->aggregate([
                ['$match' => ['user_email' => ['$in' => $emails], 'valid' => ['$ne' => false]]],
                ['$group' => [
                    '_id' => ['e' => '$user_email', 't' => '$type', 'c' => '$currency'],
                    'amt' => ['$sum' => '$amount'],
                    'n'   => ['$sum' => 1],
                ]],
            ]) as $r) {
                $id = $r['_id'];
                $txn[(string)$id['e']][(string)$id['t']][(string)$id['c']] = [
                    'amt' => (int)$r['amt'],
                    'n'   => (int)$r['n'],
                ];
            }
        } catch (Throwable $e) {
        }
    }

    $meName = null;
    if ($me = Session::getUser()) {
        $meName = $me->getUsername();
    }

    $entries = [];
    $rank    = $skip;
    foreach ($raw as $row) {
        $rank++;
        $email = (string)($row['email'] ?? '');
        $user  = $row['_user'] ?? null;
        if (!$user || $email === '') {
            continue;
        }
        $name  = (string)($user['username'] ?? explode('@', $email)[0]);
        $zeal  = (int)($row['zeal'] ?? 0);
        $jolt  = (int)($row['jolt'] ?? 0);
        $avatar = !empty($user['avatar_url'])
            ? (string)$user['avatar_url']
            : Session::getAvatarForUsername($name);
        $cur = league_for_zeal($zeal);

        $q = $quiz[$email] ?? null;
        $quizCount = $q ? (int)(($q['done'] ?? 0) ?: ($q['attempts'] ?? 0)) : 0;
        $quizRight = $q ? (int)($q['correct'] ?? 0) : 0;
        $quizAsked = $q ? (int)($q['asked'] ?? 0) : 0;

        $ach = null;
        $achGroups = [];
        if ($user) {
            try {
                $metrics = achievements_metrics($user, $db);
                $groups  = achievement_groups($metrics);
                $ach     = achievement_summary($groups);
                foreach ($groups as $g) {
                    $unlocked = array_values(array_filter($g['items'], static function ($item) {
                        return !empty($item['unlocked']);
                    }));
                    if (!$unlocked) {
                        continue;
                    }
                    $achGroups[] = [
                        'label' => $g['label'],
                        'items' => array_slice($unlocked, 0, 4),
                        'extra' => max(0, count($unlocked) - 4),
                    ];
                }
            } catch (Throwable $e) {
                $ach = null;
            }
        }

        $entries[] = [
            'rank'       => $rank,
            'username'   => $name,
            'avatar'     => $avatar,
            'zeal'       => $zeal,
            'jolt'       => $jolt,
            'level'      => $cur['level'],
            'label'      => league_level_label(max(1, $cur['next_level'])),
            'title'      => league_level_title(max(1, $cur['next_level'])),
            'badge'      => league_avatar_url(max(1, $cur['next_level'])),
            'quiz_count' => $quizCount,
            'quiz_zeal'  => ledger_sum($txn, $email, 'Quiz Completion', 'zeal'),
            'quiz_jolt'  => ledger_sum($txn, $email, 'Quiz Completion', 'jolt'),
            'quiz_right' => $quizRight,
            'quiz_wrong' => $quizAsked > $quizRight ? $quizAsked - $quizRight : 0,
            'ctf_solved' => (int)($ctf[$email] ?? 0),
            'ctf_zeal'   => ledger_sum($txn, $email, 'CTF Challenge', 'zeal'),
            'ctf_jolt'   => ledger_sum($txn, $email, 'CTF Challenge', 'jolt'),
            'code_count' => code_rows($txn, $email),
            'code_zeal'  => ledger_sum($txn, $email, 'Code Arena', 'zeal'),
            'code_jolt'  => ledger_sum($txn, $email, 'Code Arena', 'jolt'),
            'ach'        => $ach,
            'ach_groups' => $achGroups,
            'is_me'      => $meName !== null && $name === $meName,
        ];
    }

    return $entries;
}

/** Zeal/jolt earned for one transaction type. */
function ledger_sum(array $txn, string $email, string $type, string $currency): int
{
    return (int)($txn[$email][$type][$currency]['amt'] ?? 0);
}

/** Rows logged for one transaction type (stands in for Code Arena problems). */
function code_rows(array $txn, string $email): int
{
    return (int)($txn[$email]['Code Arena']['zeal']['n'] ?? 0)
        + (int)($txn[$email]['Code Arena']['jolt']['n'] ?? 0);
}

/** Zeal + jolt chips (reference: badge bg-secondary bg-opacity-10). */
function leaderboard_pills(int $zeal, int $jolt): string
{
    return '<div class="d-flex gap-1 justify-content-center flex-wrap">'
        . '<div class="bg-secondary bg-opacity-10 rounded px-2 py-1" data-coreui-toggle="tooltip" data-coreui-placement="top" data-coreui-original-title="Zeal earned">'
        . number_format($zeal) . ' 🔥</div>'
        . '<div class="bg-secondary bg-opacity-10 rounded px-2 py-1" data-coreui-toggle="tooltip" data-coreui-placement="top" data-coreui-original-title="Jolt earned">'
        . number_format($jolt) . ' ⚡</div>'
        . '</div>';
}

/** Achievement chips: one pill per group with its badge icons. */
function leaderboard_ach_chips(array $groups): string
{
    if (!$groups) {
        return '<span class="small text-medium-emphasis">—</span>';
    }
    $html = '<div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">';
    foreach ($groups as $g) {
        $html .= '<div class="d-flex align-items-center gap-1" style="padding: 2px 6px; background: rgba(255,255,255,0.05); border-radius: 6px;">'
            . '<span class="text-muted" style="font-size: 0.7rem;">' . htmlspecialchars($g['label']) . '</span>'
            . '<div class="avatars-stack">';
        foreach ($g['items'] as $item) {
            $html .= '<div class="avatar" data-coreui-toggle="tooltip"'
                . ' aria-label="' . htmlspecialchars($item['name'], ENT_QUOTES) . '"'
                . ' data-coreui-original-title="' . htmlspecialchars($item['name'], ENT_QUOTES) . '"'
                . ' title="' . htmlspecialchars($item['name'], ENT_QUOTES) . '"'
                . ' style="width: 1.25rem; height: 1.25rem; min-width: 1.25rem; background: ' . $item['color'] . ';">'
                . '<i class="bx ' . htmlspecialchars($item['icon']) . '" style="font-size: 0.75rem; color: #fff; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"></i>'
                . '</div>';
        }
        if ($g['extra'] > 0) {
            $html .= '<span class="badge bg-secondary" style="font-size: 0.65rem; padding: 2px 4px;">+' . (int)$g['extra'] . '</span>';
        }
        $html .= '</div></div>';
    }
    return $html . '</div>';
}

/** Raw <tr> HTML for one rank — the exact payload /api/leaderboard_rows sends. */
function leaderboard_row_html(array $row, int $ctfTotal): string
{
    ob_start();
    include __DIR__ . '/../template/partials/leaderboard_row.php';
    return (string)ob_get_clean();
}
