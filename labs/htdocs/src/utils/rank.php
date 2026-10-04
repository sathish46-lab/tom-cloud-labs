<?php
/**
 * Zeal rank ladder — pure functions, no DB access.
 *
 * The ladder is 10 tiers x 4 grades (I..IV) = 40 titles. Each tier has a zeal
 * floor; grades inside a tier are spaced evenly between that floor and the next
 * tier's floor. Titles are absolute (not percentile based) so they stay stable
 * while the user base changes.
 */

const RANK_TIERS = [
    ['name' => 'Ninja Initiate',    'base' => 0],
    ['name' => 'Shadow Shinobi',    'base' => 150],
    ['name' => 'Cyber Knight',      'base' => 450],
    ['name' => 'Digital Dragon',    'base' => 1100],
    ['name' => 'Cyber Crusader',    'base' => 2400],
    ['name' => 'Digital Ronin',     'base' => 5000],
    ['name' => 'Silicon Samurai',   'base' => 10000],
    ['name' => 'Quantum Shogun',    'base' => 20000],
    ['name' => 'Neon Warlord',      'base' => 45000],
    ['name' => 'Eternal Sovereign', 'base' => 90000],
];

const RANK_GRADES = 4;
const RANK_TIER_STEP_LAST = 30000;

function rank_roman(int $grade): string
{
    static $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
    return $map[$grade] ?? (string)$grade;
}

function rank_tier_floor(int $tierIndex): int
{
    return (int)(RANK_TIERS[$tierIndex]['base'] ?? 0);
}

function tier_next_base(int $tierIndex): int
{
    $count = count(RANK_TIERS);
    if ($tierIndex + 1 < $count) {
        return (int)RANK_TIERS[$tierIndex + 1]['base'];
    }
    return rank_tier_floor($tierIndex) + (RANK_GRADES * RANK_TIER_STEP_LAST);
}

function tier_grade_step(int $tierIndex): int
{
    $gap = tier_next_base($tierIndex) - rank_tier_floor($tierIndex);
    return max(1, (int)floor($gap / RANK_GRADES));
}

/** Full 40-entry ladder, ascending. */
function rank_ladder(): array
{
    $ladder = [];
    $index = 1;
    foreach (RANK_TIERS as $tier => $tierDef) {
        $floor = (int)$tierDef['base'];
        $step  = tier_grade_step($tier);
        for ($grade = 1; $grade <= RANK_GRADES; $grade++) {
            $min = $floor + (($grade - 1) * $step);
            $ladder[] = [
                'index' => $index++,
                'tier'  => $tier,
                'grade' => $grade,
                'title' => $tierDef['name'] . ' ' . rank_roman($grade),
                'min'   => $min,
            ];
        }
    }
    return $ladder;
}

/**
 * Resolve a zeal value to its rank.
 *
 * @return array{index:int,rank_total:int,title:string,short:string,tier:int,grade:int,
 *               min:int,next:int,next_title:?string,progress:float,is_max:bool}
 */
function rank_for(int $zeal): array
{
    $zeal  = max(0, $zeal);
    $ladder = rank_ladder();
    $total  = count($ladder);

    $current = $ladder[0];
    foreach ($ladder as $entry) {
        if ($zeal >= $entry['min']) {
            $current = $entry;
        } else {
            break;
        }
    }

    $nextEntry  = $ladder[$current['index']] ?? null;   // index is 1-based, so this is the next rank
    $nextFloor  = $nextEntry ? (int)$nextEntry['min'] : null;
    $nextTitle  = $nextEntry['title'] ?? null;

    if ($nextFloor !== null && $nextFloor > $current['min']) {
        $progress = (($zeal - $current['min']) / ($nextFloor - $current['min'])) * 100;
    } elseif ($nextFloor === null) {
        $progress = 100.0;
    } else {
        $progress = 0.0;
    }

    return [
        'index'      => $current['index'],
        'rank_total' => $total,
        'title'      => $current['title'],
        'short'      => 'R' . $current['index'],
        'tier'       => $current['tier'],
        'grade'      => $current['grade'],
        'min'        => $current['min'],
        'next'       => $nextFloor,
        'next_title' => $nextTitle,
        'progress'   => round(min(100.0, max(0.0, $progress)), 1),
        'is_max'     => $nextTitle === null,
    ];
}
