<?php
/**
 * League of Ronin — zeal rank ladder.
 *
 * Pure functions, no DB access. The ladder is 25 leagues x 4 levels = 100
 * levels. Level L requires L * L * 10 zeal (L1 = 10 … L100 = 100000), so the
 * curve starts gentle and steepens towards the end. League N covers levels
 * (N-1)*4+1 … N*4.
 *
 * Balances come from `user_stats.zeal` (see utils/currency.php); the caller
 * passes the value in.
 */

const LEAGUE_COUNT            = 25;
const LEAGUE_LEVELS_PER_LEAGUE = 4;
const LEAGUE_LEVEL_COUNT      = 100;

/** Level badges live in a legacy public bucket (not the tom-labs-assets one). */
const LEAGUE_AVATAR_BASE = 'https://s3.selfmade.ninja/labassets/level_avatars';

const LEAGUE_GRADES = ['I', 'II', 'III', 'IV'];

const LEAGUES = [
    ['roman' => 'I',    'name' => 'Ninja Recruit'],
    ['roman' => 'II',   'name' => 'Nightcrawler'],
    ['roman' => 'III',  'name' => 'Phantom Pupil'],
    ['roman' => 'IV',   'name' => 'Cyber Cadet'],
    ['roman' => 'V',    'name' => 'Tech Trekker'],
    ['roman' => 'VI',   'name' => 'Digital Daredevil'],
    ['roman' => 'VII',  'name' => 'Code Corsair'],
    ['roman' => 'VIII', 'name' => 'Cyber Crusader'],
    ['roman' => 'IX',   'name' => 'Tech Tempest'],
    ['roman' => 'X',    'name' => 'Programmed Pirate'],
    ['roman' => 'XI',   'name' => 'Byte Bandit'],
    ['roman' => 'XII',  'name' => 'Data Dragon Slayer'],
    ['roman' => 'XIII', 'name' => 'Silicon Samurai'],
    ['roman' => 'XIV',  'name' => 'Cyber Knight'],
    ['roman' => 'XV',   'name' => 'Byte Hunter'],
    ['roman' => 'XVI',  'name' => 'Data Demon'],
    ['roman' => 'XVII', 'name' => 'Digital Dragon'],
    ['roman' => 'XVIII','name' => 'Silicon Sniper'],
    ['roman' => 'XIX',  'name' => 'Cyber Assassin'],
    ['roman' => 'XX',   'name' => 'Tech Ninja'],
    ['roman' => 'XXI',  'name' => 'Byte Bandit Lord'],
    ['roman' => 'XXII', 'name' => 'Data Dragon Slayer Supreme'],
    ['roman' => 'XXIII','name' => 'Digital Ronin Supreme'],
    ['roman' => 'XXIV', 'name' => 'Silicon Samurai Supreme'],
    ['roman' => 'XXV',  'name' => 'Cyber Knight Supreme'],
];

/** Zeal needed to complete level $level. */
function league_requirement(int $level): int
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    return $level * $level * 10;
}

/** 1-based league index that owns $level. */
function league_index_for_level(int $level): int
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    return (int)ceil($level / LEAGUE_LEVELS_PER_LEAGUE);
}

/** First and last level of a league (1-based league index). */
function league_bounds(int $index): array
{
    $index = max(1, min(LEAGUE_COUNT, $index));
    return [($index - 1) * LEAGUE_LEVELS_PER_LEAGUE + 1, $index * LEAGUE_LEVELS_PER_LEAGUE];
}

/** League definition (roman + name) for a 1-based index. */
function league_def(int $index): array
{
    $index = max(1, min(LEAGUE_COUNT, $index));
    return LEAGUES[$index - 1];
}

/** Grade inside the league, 1..4 (I..IV). */
function league_grade_for_level(int $level): int
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    $grade = $level % LEAGUE_LEVELS_PER_LEAGUE;
    return $grade === 0 ? LEAGUE_LEVELS_PER_LEAGUE : $grade;
}

/** e.g. level 91 → "Digital Ronin Supreme III". */
function league_level_title(int $level): string
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    $def   = league_def(league_index_for_level($level));
    return $def['name'] . ' ' . LEAGUE_GRADES[league_grade_for_level($level) - 1];
}

/** e.g. "League XXIII · Level 91". */
function league_level_label(int $level): string
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    $def   = league_def(league_index_for_level($level));
    return 'League ' . $def['roman'] . ' · Level ' . $level;
}

/** Badge image for one level (file index 0..3 inside the league folder). */
function league_avatar_url(int $level): string
{
    $level = max(1, min(LEAGUE_LEVEL_COUNT, $level));
    $league = league_index_for_level($level);
    $file   = league_grade_for_level($level) - 1;
    return LEAGUE_AVATAR_BASE . '/league_' . $league . '/' . $file . '.jpg';
}

function league_lock_url(): string
{
    return LEAGUE_AVATAR_BASE . '/lock.jpg';
}

/**
 * State of a single level against a zeal balance.
 *
 * completed — zeal already covers the requirement
 * progress  — the first level the user is working towards
 * locked    — everything after that
 *
 * @return array{level:int,status:string,percent:int,required:int,earned:int,text:string}
 */
function league_level_state(int $level, int $zeal): array
{
    $zeal     = max(0, $zeal);
    $required = league_requirement($level);
    $state    = [
        'level'    => $level,
        'status'   => 'locked',
        'percent'  => 0,
        'required' => $required,
        'earned'   => $zeal,
        'text'     => 'Locked',
    ];

    if ($zeal >= $required) {
        $state['status']  = 'completed';
        $state['percent'] = 100;
        $state['text']    = 'Completed 🎉';
        return $state;
    }

    $prevRequired = $level > 1 ? league_requirement($level - 1) : 0;
    if ($zeal >= $prevRequired) {
        $state['status']   = 'progress';
        $state['percent']  = (int)floor(($zeal / $required) * 100);
        $state['text']     = 'Progress (' . number_format($zeal) . ' / ' . number_format($required) . ') 🔥';
    }

    return $state;
}

/**
 * Where a zeal balance sits on the ladder.
 *
 * `level` is the highest completed level (0 when the user has not crossed
 * level 1 yet); `next_level` is the one being worked on.
 *
 * @return array{level:int,next_level:int,league:int,league_roman:string,
 *               league_name:string,title:string,progress:int,required:int,
 *               is_max:bool,reached:bool}
 */
function league_for_zeal(int $zeal): array
{
    $zeal = max(0, $zeal);

    $level = 0;
    for ($l = 1; $l <= LEAGUE_LEVEL_COUNT; $l++) {
        if ($zeal >= league_requirement($l)) {
            $level = $l;
        } else {
            break;
        }
    }

    $nextLevel = min(LEAGUE_LEVEL_COUNT, $level + 1);
    $isMax     = $level >= LEAGUE_LEVEL_COUNT;
    $required  = league_requirement($nextLevel);
    $progress  = $isMax
        ? 100
        : (int)floor(($zeal / $required) * 100);

    $displayLevel = max(1, $nextLevel);
    $def = league_def(league_index_for_level($displayLevel));

    return [
        'level'        => $level,
        'next_level'   => $nextLevel,
        'league'       => league_index_for_level($displayLevel),
        'league_roman' => $def['roman'],
        'league_name'  => $def['name'],
        'title'        => league_level_title($displayLevel),
        'progress'     => $progress,
        'required'     => $required,
        'is_max'       => $isMax,
        'reached'      => $level >= 1,
    ];
}

/**
 * The full 25-league ladder with per-level states for a zeal balance.
 *
 * @return array<int, array{index:int,roman:string,name:string,from:int,to:int,
 *                          is_current:bool,levels:array<int,array>}>
 */
function leagues_ladder(int $zeal): array
{
    $currentLevel = league_for_zeal($zeal);
    $currentLeague = league_index_for_level(max(1, $currentLevel['next_level']));

    $ladder = [];
    for ($i = 1; $i <= LEAGUE_COUNT; $i++) {
        [$from, $to] = league_bounds($i);
        $def = league_def($i);

        $levels = [];
        for ($level = $from; $level <= $to; $level++) {
            $state         = league_level_state($level, $zeal);
            $state['title'] = league_level_title($level);
            $state['grade'] = LEAGUE_GRADES[league_grade_for_level($level) - 1];
            $state['image'] = $state['status'] === 'locked' ? league_lock_url() : league_avatar_url($level);
            $levels[] = $state;
        }

        $ladder[] = [
            'index'      => $i,
            'roman'      => $def['roman'],
            'name'       => $def['name'],
            'from'       => $from,
            'to'         => $to,
            'is_current' => $i === $currentLeague,
            'levels'     => $levels,
        ];
    }

    return $ladder;
}
