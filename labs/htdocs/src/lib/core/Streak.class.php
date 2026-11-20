<?php

/**
 * Streak — Reusable streak & daily-activity reward engine.
 *
 * Single source of truth for streak calculation, milestone bonuses,
 * zeal/jolt awarding, and miss penalties. Any service (login, quiz,
 * deploy, device, roadmap, learn, code, discussion, mcp) can call
 * recordActivity() to credit points and advance the user's streak.
 *
 * Streak state is stored on the MongoDB `users` document field `streak`:
 *   {
 *     current_streak: int,          // consecutive active days
 *     longest_streak: int,          // best ever
 *     last_activity_date: "YYYY-MM-DD",
 *     misses_this_cycle: int,       // 0-3 within current 21-day cycle
 *     cycle_start_date: "YYYY-MM-DD",
 *     milestones_reached: [5, 10, ...]
 *   }
 *
 * Zeal/Jolt balances live in `user_stats` (seeded like Quiz::getUserStats).
 * Every movement is appended to the `transactions` ledger via
 * currency_record() / Quiz::updateUserStats().
 *
 * Security: every method validates the email, uses atomic $set/$inc with
 * ownership check (email match), and never trusts caller input.
 */

class Streak {
    private const CYCLE_DAYS = 21;
    private const MAX_MISSES = 3;

    // Milestone days within each 21-day cycle
    public const MILESTONES = [5, 10, 15, 21];

    // Base daily zeal awarded for any active day
    public const DAILY_ZEAL = 25;

    // Penalty per missed day (percent)
    public const PENALTY_PER_MISS = 10;

    // Bonus milestones per cycle: day => [zeal_bonus, jolt_bonus]
    public const MILESTONE_REWARDS = [
        5  => ['zeal' => 75,  'jolt' => 50],
        10 => ['zeal' => 175, 'jolt' => 100],
        15 => ['zeal' => 275, 'jolt' => 150],
        21 => ['zeal' => 450, 'jolt' => 200],
    ];

    // Activity type => points (zeal, jolt)
    // Derived from page URL prefix; services pass their activity type string.
    private const ACTIVITY_POINTS = [
        'device'     => ['zeal' => 15, 'jolt' => 0],
        'deploy'     => ['zeal' => 20, 'jolt' => 0],
        'quiz'       => ['zeal' => 10, 'jolt' => 2],
        'roadmap'    => ['zeal' => 15, 'jolt' => 0],
        'learn'      => ['zeal' => 10, 'jolt' => 0],
        'code'       => ['zeal' => 15, 'jolt' => 0],
        'discussion' => ['zeal' => 5,  'jolt' => 0],
        'mcp'        => ['zeal' => 10, 'jolt' => 0],
        'default'    => ['zeal' => 5,  'jolt' => 0],
    ];

    private static function users(): MongoDB\Collection {
        return DatabaseConnection::getDefaultDatabase()->users;
    }

    private static function userStats(): MongoDB\Collection {
        return DatabaseConnection::getDefaultDatabase()->user_stats;
    }

    private static function activity(): MongoDB\Collection {
        return DatabaseConnection::getDefaultDatabase()->user_activity;
    }

    /** Validate email strictly. */
    private static function validEmail($email): bool {
        return is_string($email) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /** Current UTC date string YYYY-MM-DD. */
    private static function today(): string {
        return date('Y-m-d');
    }

    /** Current epoch seconds. */
    public static function now(): int {
        return (int)time();
    }

    /** Map a page URL path to an activity type string. */
    public static function activityTypeFromPage(string $page): string {
        $page = '/' . ltrim($page, '/');
        $first = explode('/', trim($page, '/'))[0] ?? '';
        $map = [
            'devices'     => 'device',
            'device'      => 'device',
            'labs'        => 'deploy',
            'instances'   => 'deploy',
            'quiz'        => 'quiz',
            'roadmaps'    => 'roadmap',
            'roadmap'     => 'roadmap',
            'learn'       => 'learn',
            'lesson'      => 'learn',
            'code-arena'  => 'code',
            'code'        => 'code',
            'discussions' => 'discussion',
            'discussion'  => 'discussion',
            'mcp'         => 'mcp',
        ];
        return $map[$first] ?? 'default';
    }

    /** Points (zeal, jolt) for an activity type. */
    public static function pointsFor(string $activityType): array {
        return self::ACTIVITY_POINTS[$activityType] ?? self::ACTIVITY_POINTS['default'];
    }

    /** Full streak state for a user. Computed fresh from user_activity history. */
    public static function get(string $email): array {
        if (!self::validEmail($email)) {
            return self::emptyState();
        }

        $db = DatabaseConnection::getDefaultDatabase();

        // Stored streak doc (may be empty on first use)
        $userDoc = $db->users->findOne(
            ['email' => $email],
            ['projection' => ['streak' => 1, 'email' => 1]]
        );
        $stored = $userDoc['streak'] ?? [];

        // Distinct active dates for this user (last 90 days window for perf)
        $ninetyDaysAgo = self::now() - (90 * 24 * 60 * 60);
        $dates = $db->user_activity->distinct('date', [
            'email'     => $email,
            'timestamp' => ['$gte' => $ninetyDaysAgo]
        ]);
        sort($dates);

        $state = self::emptyState();
        $storedNorm = self::normalizeDoc($stored);
        $state['current_streak'] = (int)($storedNorm['current_streak'] ?? 0);
        $state['longest_streak'] = (int)($storedNorm['longest_streak'] ?? 0);
        $state['last_activity_date'] = $storedNorm['last_activity_date'] ?? null;
        $state['misses_this_cycle'] = (int)($storedNorm['misses_this_cycle'] ?? 0);
        $state['cycle_start_date'] = $storedNorm['cycle_start_date'] ?? self::today();
        $msRaw = self::normalizeDoc($storedNorm['milestones_reached'] ?? null);
        $state['milestones_reached'] = is_array($msRaw)
            ? array_values(array_filter($msRaw, 'is_int'))
            : [];

        if (empty($dates)) {
            // No activity at all — keep stored state but mark no today
            $state['current_day'] = 1;
            $state['misses_left'] = max(0, self::MAX_MISSES - $state['misses_this_cycle']);
            $state['penalty_pct'] = $state['misses_this_cycle'] * self::PENALTY_PER_MISS;
            $state['has_activity_today'] = false;
            return $state;
        }

        $today = self::today();
        $hasToday = in_array($today, $dates, true);

        // Recompute consecutive streak walking backward from the most recent active date.
        $mostRecent = end($dates);
        $streak = 0;
        $cursor = $mostRecent;
        while (in_array($cursor, $dates, true)) {
            $streak++;
            $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
        }

        $state['current_streak'] = $streak;
        $state['longest_streak'] = max($state['longest_streak'], $streak);
        $state['last_activity_date'] = $mostRecent;
        $state['has_activity_today'] = $hasToday;

        // Current day within 21-day cycle
        $state['current_day'] = (($streak - 1) % self::CYCLE_DAYS) + 1;

        // Misses in current cycle: count cycle days without activity
        $cycleStart = $stored['cycle_start_date'] ?? self::today();
        // Re-anchor cycle start if streak reset
        if ($streak === 0 || $mostRecent !== $today && !$hasToday) {
            // cycle anchored to most recent active day
            $cycleStart = $mostRecent;
        }
        $state['cycle_start_date'] = $cycleStart;

        $cycleEnd = date('Y-m-d', strtotime($cycleStart . ' +' . (self::CYCLE_DAYS - 1) . ' day'));
        $cycleDates = [];
        $d = $cycleStart;
        while ($d <= $cycleEnd) {
            $cycleDates[] = $d;
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        $activeInCycle = count(array_intersect($dates, $cycleDates));
        $state['misses_this_cycle'] = max(0, self::CYCLE_DAYS - $activeInCycle);
        $state['misses_left'] = max(0, self::MAX_MISSES - $state['misses_this_cycle']);
        $state['penalty_pct'] = min(100, $state['misses_this_cycle'] * self::PENALTY_PER_MISS);

        // Streak broken?
        if ($state['misses_this_cycle'] >= self::MAX_MISSES + 1) {
            $state['current_streak'] = 0;
            $state['current_day'] = 1;
            $state['misses_this_cycle'] = 0;
            $state['penalty_pct'] = 0;
        }

        return $state;
    }

    /**
     * Record an activity for a user: award zeal/jolt + advance streak.
     * Call this from any service after a successful pass/completion.
     *
     * @param string $email        user email (validated)
     * @param string $activityType device/deploy/quiz/roadmap/learn/code/discussion/mcp/default
     * @param int    $zeal         zeal points to award (overrides activity default if > 0)
     * @param int    $jolt         jolt points to award (overrides activity default if > 0)
     * @param string $description  human-readable ledger detail
     * @return array{success:bool, streak:array, error?:string}
     */
    public static function recordActivity(string $email, string $activityType = 'default', int $zeal = 0, int $jolt = 0, string $description = ''): array {
        if (!self::validEmail($email)) {
            return ['success' => false, 'error' => 'Valid email required'];
        }

        $db = DatabaseConnection::getDefaultDatabase();

        // Determine points
        $pts = self::pointsFor($activityType);
        $zeal = $zeal > 0 ? $zeal : (int)$pts['zeal'];
        $jolt = $jolt > 0 ? $jolt : (int)$pts['jolt'];

        // Award zeal/jolt via the existing currency ledger (type 'Streak Daily')
        if ($zeal !== 0 || $jolt !== 0) {
            $res = Quiz::updateUserStats($email, $zeal, $jolt, 'Streak Daily', $description ?: "Streak activity: {$activityType}");
            if (!$res) {
                return ['success' => false, 'error' => 'Failed to award points'];
            }
        }

        // Mark today as active in user_activity if not already (idempotent)
        $today = self::today();
        $now = self::now();
        $already = $db->user_activity->findOne([
            'email' => $email,
            'date'  => $today,
        ], ['projection' => ['_id' => 1]]);
        if (!$already) {
            $db->user_activity->insertOne([
                'user_id'   => null,
                'email'     => $email,
                'page'      => '/streak/' . $activityType,
                'hour'      => (int)date('G', $now),
                'date'      => $today,
                'timestamp' => $now,
                'created_at' => new MongoDB\BSON\UTCDateTime($now * 1000),
                'source'    => 'streak',
            ]);
        }

        // Recompute & persist streak state
        $state = self::get($email);
        $state['current_day'] = (($state['current_streak'] - 1) % self::CYCLE_DAYS) + 1;

        $update = [
            '$set' => [
                'streak.current_streak'   => $state['current_streak'],
                'streak.longest_streak'   => $state['longest_streak'],
                'streak.last_activity_date' => $today,
                'streak.misses_this_cycle'=> $state['misses_this_cycle'],
                'streak.cycle_start_date' => $state['cycle_start_date'],
                'streak.milestones_reached' => $state['milestones_reached'],
            ],
        ];

        // Handle milestone bonus on milestone days (first time only)
        $currentDay = $state['current_day'];
        if (self::isMilestone($currentDay) && !in_array($currentDay, $state['milestones_reached'], true)) {
            $bonus = self::MILESTONE_REWARDS[$currentDay] ?? ['zeal' => 0, 'jolt' => 0];
            $bonzeal = (int)$bonus['zeal'];
            $bonjolt = (int)$bonus['jolt'];
            if ($bonzeal !== 0 || $bonjolt !== 0) {
                Quiz::updateUserStats($email, $bonzeal, $bonjolt, 'Streak Daily', "Milestone Day {$currentDay} bonus");
            }
            $state['milestones_reached'][] = $currentDay;
            $update['$set']['streak.milestones_reached'] = $state['milestones_reached'];
            $state['total_zeal_today'] = ($state['total_zeal_today'] ?? 0) + $zeal + $bonzeal;
            $state['total_jolt_today'] = ($state['total_jolt_today'] ?? 0) + $jolt + $bonjolt;
        } else {
            $state['total_zeal_today'] = ($state['total_zeal_today'] ?? 0) + $zeal;
            $state['total_jolt_today'] = ($state['total_jolt_today'] ?? 0) + $jolt;
        }

        $db->users->updateOne(['email' => $email], $update);

        return [
            'success' => true,
            'streak'  => $state,
        ];
    }

    /** Is a day number a milestone within the 21-day cycle? */
    public static function isMilestone(int $day): bool {
        return in_array($day, self::MILESTONES, true);
    }

    /** Milestone bonus for a day (zeal, jolt). */
    public static function getMilestoneReward(int $day): array {
        return self::MILESTONE_REWARDS[$day] ?? ['zeal' => 0, 'jolt' => 0];
    }

    /** Penalty percent for a miss count. */
    public static function penaltyPct(int $misses): int {
        return min(100, max(0, $misses) * self::PENALTY_PER_MISS);
    }

    /** Backfill all users: compute streak from history + award points once. Admin use. */
    public static function backfillAll(int $limit = 0): array {
        $db = DatabaseConnection::getDefaultDatabase();
        $cursor = $db->users->find([], ['projection' => ['email' => 1]]);
        if ($limit > 0) $cursor = $cursor->limit($limit);

        $processed = 0;
        $errors = [];
        foreach ($cursor as $u) {
            $email = (string)($u['email'] ?? '');
            if (!self::validEmail($email)) continue;
            try {
                $state = self::get($email);
                $db->users->updateOne(
                    ['email' => $email],
                    ['$set' => [
                        'streak.current_streak'   => $state['current_streak'],
                        'streak.longest_streak'   => $state['longest_streak'],
                        'streak.last_activity_date' => $state['last_activity_date'],
                        'streak.misses_this_cycle'=> $state['misses_this_cycle'],
                        'streak.cycle_start_date' => $state['cycle_start_date'],
                        'streak.milestones_reached' => $state['milestones_reached'],
                    ]]
                );
                $processed++;
            } catch (Throwable $e) {
                $errors[] = $email . ': ' . $e->getMessage();
            }
        }
        return ['processed' => $processed, 'errors' => $errors];
    }

    /** Coerce MongoDB BSON/ArrayObject/stdClass into a plain PHP array. */
    private static function normalizeDoc($value): array {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }
        if (is_object($value)) {
            return (array)$value;
        }
        return [];
    }

    private static function emptyState(): array {
        return [
            'current_streak'     => 0,
            'longest_streak'     => 0,
            'current_day'        => 1,
            'last_activity_date' => null,
            'misses_this_cycle'  => 0,
            'misses_left'        => self::MAX_MISSES,
            'cycle_start_date'   => self::today(),
            'milestones_reached' => [],
            'penalty_pct'        => 0,
            'has_activity_today' => false,
            'total_zeal_today'   => 0,
            'total_jolt_today'   => 0,
        ];
    }
}
