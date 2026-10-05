<?php
if (!\TomLabs\Labs\LabFeatures::isAccountEnabled()) {
    echo '<div class="text-center py-5"><h5 class="text-muted">This feature is currently disabled.</h5></div>';
    return;
}

require_once __DIR__ . '/../../utils/profile.php';
require_once __DIR__ . '/../../utils/rank.php';
require_once __DIR__ . '/../../utils/achievements.php';

$pfDb    = DatabaseConnection::getDefaultDatabase();
$pfMe    = Session::getUser();
$pfTarget = profile_resolve($_GET['username'] ?? '');

if (!$pfTarget) {
    echo '<div class="text-center py-5"><h5 class="text-muted">Profile not found.</h5></div>';
    return;
}

$pfEsc    = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$pfEmail  = (string)($pfTarget['email'] ?? '');
$pfUser   = (string)($pfTarget['username'] ?? '');
$pfName   = trim(($pfTarget['first_name'] ?? '') . ' ' . ($pfTarget['last_name'] ?? ''));
$pfName   = $pfName !== '' ? $pfName : $pfUser;
$pfSelf   = $pfMe && $pfEmail !== '' && $pfEmail === $pfMe->getEmail();
$pfRole   = (string)($pfTarget['role'] ?? 'user');
$pfIsAdmin = in_array($pfMe?->getRole(), ['admin', 'superuser'], true);

// Owner (or staff reviewing the account) unlocks the private cards, the email
// and the infrastructure rows in the feed. Everyone else gets the public set.
$pfPrivate     = $pfSelf || $pfIsAdmin;
$pfCanSeeEmail = $pfPrivate;

$pfAvatar = Session::getAvatarForUsername($pfUser);
$pfPlan   = (string)($pfTarget['plan'] ?? 'default');
$pfPlanLabel = ['free' => 'Free', 'default' => 'Default', 'pro' => 'Pro'][$pfPlan] ?? ucfirst($pfPlan);
$pfState  = (string)($pfTarget['state'] ?? 'active');
$pfCreated = (int)($pfTarget['created_at'] ?? 0);
$pfLast   = (int)($pfTarget['last_login'] ?? 0);

$pfDeleted = !empty($pfTarget['is_deleted_snapshot']);
$pfDelId   = (string)($pfTarget['snapshot_id'] ?? '');
$pfDelAt   = (int)($pfTarget['deleted_at'] ?? 0);
$pfDelBy   = (string)($pfTarget['deleted_by'] ?? '');

$pfScores = profile_scores($pfTarget, $pfDb);
$pfGlobal = profile_global_rank($pfTarget, $pfDb);
$pfRank   = rank_for($pfScores['zeal']);
$pfFacts  = profile_facts($pfTarget, $pfDb);

$pfAllSkills = profile_skills($pfTarget, $pfDb, 40);
$pfSkills    = array_slice($pfAllSkills, 0, 8);

$pfMetrics       = achievements_metrics($pfTarget, $pfDb);
$pfMetrics['global_rank'] = $pfGlobal;
$pfGroups        = achievement_groups($pfMetrics);
$pfAchSummary    = achievement_summary($pfGroups);

$pfFeed = profile_feed($pfTarget, $pfDb, 40, $pfPrivate);

// Insight grid (Profile tab) — mirrors the activity picture without the
// analytics that used to live on the Activity tab.
$pfQuizSeries = profile_monthly_series($pfTarget, $pfDb, 'quiz_attempts', 12);
$pfCodeSeries = profile_monthly_series($pfTarget, $pfDb, 'code_submissions', 12);
$pfCtfSeries  = profile_monthly_series($pfTarget, $pfDb, 'challenge_submissions', 12);
$pfRadar      = profile_activity_months($pfTarget, $pfDb, 6);
$pfCtfCats    = profile_ctf_categories($pfTarget, $pfDb);

$pfSeriesEmpty = static fn (array $series): bool => array_sum(array_column($series, 'count')) === 0;
$pfRadarEmpty  = array_sum(array_column($pfRadar, 'count')) === 0;

$pfTabs = [
    'profile'      => ['label' => 'Profile',      'icon' => 'bx-user'],
    'activity'     => ['label' => 'Activity',     'icon' => 'bx-line-chart'],
    'achievements' => ['label' => 'Achievements', 'icon' => 'bx-trophy'],
];
$pfTab = strtolower(trim((string)($_GET['tab'] ?? 'profile')));
if (!isset($pfTabs[$pfTab])) {
    $pfTab = 'profile';
}

$pfChartPayload = [
    'quiz'  => $pfQuizSeries,
    'code'  => $pfCodeSeries,
    'ctf'   => $pfCtfSeries,
    'radar' => [
        'labels' => array_column($pfRadar, 'label'),
        'values' => array_column($pfRadar, 'count'),
    ],
    'cats'  => $pfCtfCats,
];

$pfFmtBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
};
$pfStorageBytes = (int)($pfMetrics['storage_bytes'] ?? 0);
$pfPresenceOn = $pfLast > 0 && (time() - $pfLast) <= 900;

/** One "Quiz Activity" style line card from the insight grid. */
$pfLineCard = function (string $key, string $label, string $icon, string $color, array $series) use ($pfEsc, $pfSeriesEmpty): void {
    $empty = $pfSeriesEmpty($series);
    ?>
    <div class="col-lg-4">
        <div class="pf-card card blur border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">
                <div class="pf-chart-head">
                    <h6 class="fw-bold mb-0"><i class="bx <?= $pfEsc($icon) ?> me-1" style="color: <?= $pfEsc($color) ?>"></i><?= $pfEsc($label) ?></h6>
                    <?php if (!$empty): ?>
                        <div class="pf-range">
                            <?php foreach ([1 => '1M', 3 => '3M', 12 => '1Y'] as $pfMonths => $pfRangeLabel): ?>
                                <button type="button" data-pf-range="<?= $pfEsc($key) ?>" data-months="<?= $pfMonths ?>"
                                        class="<?= $pfMonths === 3 ? 'active' : '' ?>"><?= $pfEsc($pfRangeLabel) ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($empty): ?>
                    <div class="pf-empty">
                        <i class="bx bx-line-chart"></i>
                        <span>No activity recorded yet.</span>
                    </div>
                <?php else: ?>
                    <div class="pf-chartbox pf-chartbox-sm"><canvas id="pfChart-<?= $pfEsc($key) ?>"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};
?>

<div id="pfApp" class="container-fluid px-3 py-3">

    <?php if ($pfDeleted): ?>
        <!-- Archived account: admin-only read-only snapshot -->
        <div class="alert alert-warning d-flex align-items-start gap-2 border-0 rounded-4 shadow-sm mb-3 py-2 px-3" role="alert">
            <i class='bx bx-archive fs-4 mt-1'></i>
            <div>
                <div class="fw-bold">Deleted account — admin-only snapshot</div>
                <div class="small mb-0">
                    This profile was deleted on <?= $pfEsc(date('d M Y, H:i', $pfDelAt)) ?>
                    by <?= $pfEsc($pfDelBy) ?>. You are viewing archived data (read-only).
                    <a href="/admin/deleted-users/<?= $pfEsc($pfDelId) ?>" class="fw-semibold">Open full snapshot &rarr;</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================= HERO ================= -->
    <div class="pf-hero card blur border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                <div class="pf-avatar-wrap">
                    <img class="pf-avatar" src="<?= $pfEsc($pfAvatar) ?>" alt="<?= $pfEsc($pfName) ?>">
                    <span class="pf-presence <?= $pfPresenceOn ? 'is-on' : '' ?>" title="<?= $pfPresenceOn ? 'Active now' : 'Offline' ?>"></span>
                </div>

                <div class="flex-grow-1 pf-id">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h3 class="fw-bold m-0 pf-name"><?= $pfEsc($pfName) ?></h3>
                        <span class="pf-rank-pill"><i class="bxs-shield"></i>Lv <?= (int)$pfRank['index'] ?> · <?= $pfEsc($pfRank['title']) ?></span>
                    </div>
                    <div class="pf-handle">
                        @<?= $pfEsc($pfUser) ?>
                        <?php if ($pfPrivate): ?>
                            <span class="pf-dot-sep">•</span>
                            <?= $pfEsc($pfPlanLabel) ?> plan
                            <?php if ($pfEmail !== ''): ?>
                                <span class="pf-dot-sep">•</span><?= $pfEsc($pfEmail) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="pf-sub">
                        <?php if ($pfCreated > 0): ?>
                            Joined <?= $pfEsc(date('M Y', $pfCreated)) ?>
                            <span class="pf-dot-sep">•</span>
                        <?php endif; ?>
                        <?php if ($pfLast > 0): ?>
                            <span class="pf-status-inline">
                                <span class="pf-presence-mini <?= $pfPresenceOn ? 'is-on' : '' ?>"></span>
                                <?= $pfPresenceOn ? 'Active now' : 'Last seen ' . $pfEsc(profile_time_ago($pfLast)) ?>
                            </span>
                        <?php else: ?>
                            Never signed in
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pf-strip">
                    <div class="pf-strip-item">
                        <div class="pf-strip-num"><?= $pfGlobal !== null ? '#' . number_format($pfGlobal) : '—' ?></div>
                        <div class="pf-strip-lbl">Global Rank</div>
                    </div>
                    <div class="pf-strip-item">
                        <div class="pf-strip-num pf-c-zeal"><?= number_format($pfScores['zeal']) ?></div>
                        <div class="pf-strip-lbl">Zeal</div>
                    </div>
                    <div class="pf-strip-item">
                        <div class="pf-strip-num pf-c-jolt"><?= number_format($pfScores['jolt']) ?></div>
                        <div class="pf-strip-lbl">Jolt</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- ================= LEFT RAIL ================= -->
        <div class="col-lg-4">

            <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-3">
                    <div class="pf-sec-title"><i class="bx bx-category"></i>Top Skills</div>
                    <?php if ($pfSkills): ?>
                        <div class="pf-skills">
                            <?php foreach ($pfSkills as $pfSkill): ?>
                                <span class="pf-skill"><?= $pfEsc($pfSkill['label']) ?><b><?= (int)$pfSkill['count'] ?></b></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary small mb-0">No roadmaps started yet — skills appear once you open a roadmap.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3 pf-meter-card pf-meter-zeal">
                <div class="card-body p-3">
                    <div class="pf-meter-head">
                        <span class="pf-meter-icon"><i class="bx bx-flame"></i></span>
                        <span class="pf-meter-lbl">Zeal</span>
                        <span class="pf-meter-note">earned through activity</span>
                    </div>
                    <div class="pf-meter-val"><?= number_format($pfScores['zeal']) ?></div>
                    <div class="pf-meter-bar"><span style="width: <?= min(100, max(2, (int)round($pfScores['zeal'] / 1000))) ?>%"></span></div>
                </div>
            </div>

            <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3 pf-meter-card pf-meter-jolt">
                <div class="card-body p-3">
                    <div class="pf-meter-head">
                        <span class="pf-meter-icon"><i class="bx bx-bolt-circle"></i></span>
                        <span class="pf-meter-lbl">Jolt</span>
                        <span class="pf-meter-note">fast-action points</span>
                    </div>
                    <div class="pf-meter-val"><?= number_format($pfScores['jolt']) ?></div>
                    <div class="pf-meter-bar"><span style="width: <?= min(100, max(2, (int)round($pfScores['jolt'] / 10))) ?>%"></span></div>
                </div>
            </div>

            <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3 pf-rankcard">
                <div class="card-body p-3">
                    <div class="pf-sec-title"><i class="bx bx-trophy"></i>Rank Progress</div>
                    <div class="pf-rank-now">
                        <span><?= $pfEsc($pfRank['title']) ?></span>
                        <span class="pf-rank-idx">#<?= (int)$pfRank['index'] ?> / <?= (int)$pfRank['rank_total'] ?></span>
                    </div>
                    <div class="pf-rankbar">
                        <span style="width: <?= $pfEsc($pfRank['progress']) ?>%"></span>
                    </div>
                    <div class="pf-rank-foot">
                        <?php if ($pfRank['is_max']): ?>
                            <span>Maximum rank reached</span>
                        <?php else: ?>
                            <span>Next: <b><?= $pfEsc($pfRank['next_title']) ?></b></span>
                            <span class="pf-rank-need"><?= number_format(max(0, (int)$pfRank['next'] - $pfScores['zeal'])) ?> zeal to go</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================= RIGHT: TABS ================= -->
        <div class="col-lg-8">

            <div class="pf-tabs rounded-4 px-2 py-2 d-flex flex-wrap gap-2 mb-3 shadow-sm">
                <?php foreach ($pfTabs as $pfKey => $pfDef): ?>
                    <a href="?tab=<?= $pfEsc($pfKey) ?>" class="pf-tab<?= $pfTab === $pfKey ? ' active' : '' ?>"
                       data-pf-tab="<?= $pfEsc($pfKey) ?>" hx-boost="false">
                        <i class="<?= $pfEsc($pfDef['icon']) ?>"></i><?= $pfEsc($pfDef['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- ---------- PROFILE ---------- -->
            <div class="pf-panel<?= $pfTab === 'profile' ? '' : ' d-none' ?>" id="pf-panel-profile">

                <?php if ($pfPrivate): ?>
                    <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-body p-3">
                            <div class="pf-sec-title"><i class="bx bx-id-card"></i>Account</div>
                            <div class="pf-facts">
                                <div class="pf-fact"><span>Username</span><b>@<?= $pfEsc($pfUser) ?></b></div>
                                <div class="pf-fact"><span>Display name</span><b><?= $pfEsc($pfName) ?></b></div>
                                <?php if ($pfCanSeeEmail && $pfEmail !== ''): ?>
                                    <div class="pf-fact"><span>Email</span><b><?= $pfEsc($pfEmail) ?></b></div>
                                <?php endif; ?>
                                <div class="pf-fact"><span>Role</span><b><?= $pfEsc(ucfirst($pfRole)) ?><?= !empty($pfTarget['moderator']) ? ' · Moderator' : '' ?></b></div>
                                <div class="pf-fact"><span>Plan</span><b><?= $pfEsc($pfPlanLabel) ?></b></div>
                                <div class="pf-fact"><span>Status</span><b class="<?= $pfState === 'active' ? 'pf-ok' : 'pf-bad' ?>"><?= $pfEsc(ucfirst($pfState)) ?></b></div>
                                <div class="pf-fact"><span>Email verified</span><b class="<?= !empty($pfTarget['is_verified']) ? 'pf-ok' : 'pf-bad' ?>"><?= !empty($pfTarget['is_verified']) ? 'Yes' : 'No' ?></b></div>
                                <div class="pf-fact"><span>Two-factor</span><b class="<?= !empty($pfTarget['two_factor_enabled']) ? 'pf-ok' : 'pf-bad' ?>"><?= !empty($pfTarget['two_factor_enabled']) ? 'Enabled' : 'Disabled' ?></b></div>
                                <div class="pf-fact"><span>Joined</span><b><?= $pfCreated > 0 ? $pfEsc(date('d M Y', $pfCreated)) : '—' ?></b></div>
                                <div class="pf-fact"><span>Storage used</span><b><?= $pfEsc($pfFmtBytes($pfStorageBytes)) ?></b></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="pf-card card blur border-0 shadow-sm rounded-4 h-100">
                                <div class="card-body p-3">
                                    <div class="pf-sec-title"><i class="bx bx-rocket"></i>Deployments</div>
                                    <div class="pf-mini-grid">
                                        <div><b><?= number_format($pfFacts['labs']) ?></b><span>Containers</span></div>
                                        <div><b><?= number_format($pfFacts['domains']) ?></b><span>Domains</span></div>
                                        <div><b><?= number_format($pfFacts['ssh_keys']) ?></b><span>SSH keys</span></div>
                                        <div><b><?= number_format($pfFacts['roadmaps']) ?></b><span>Roadmaps</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="pf-card card blur border-0 shadow-sm rounded-4 h-100">
                                <div class="card-body p-3">
                                    <div class="pf-sec-title"><i class="bx bx-plug"></i>Automation</div>
                                    <div class="pf-mini-grid">
                                        <div><b><?= number_format($pfFacts['mcp_clients']) ?></b><span>MCP clients</span></div>
                                        <div><b><?= number_format($pfFacts['mcp_tokens']) ?></b><span>MCP tokens</span></div>
                                        <div><b><?= number_format($pfFacts['events']) ?></b><span>Page views</span></div>
                                        <div><b><?= number_format($pfFacts['audit']) ?></b><span>Audit entries</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pf-card card blur border-0 shadow-sm rounded-4 mt-3 mb-3">
                        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <div class="pf-sec-title mb-1"><i class="bx bx-trophy"></i>Achievements</div>
                                <div class="text-body-secondary small"><?= (int)$pfAchSummary['unlocked'] ?> of <?= (int)$pfAchSummary['total'] ?> unlocked</div>
                            </div>
                            <div class="pf-progress pf-progress-sm" title="<?= (int)$pfAchSummary['percent'] ?>%">
                                <span style="width: <?= (int)$pfAchSummary['percent'] ?>%"></span>
                            </div>
                            <a href="?tab=achievements" class="btn btn-sm btn-outline-primary rounded-pill px-3" hx-boost="false">View all</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Insight grid: quizzes, code arena, CTF, zeal trend, skills -->
                <div class="row g-3">
                    <?php
                    $pfLineCard('quiz', 'Quiz Activity', 'bx-award', '#6366f1', $pfQuizSeries);
                    $pfLineCard('code', 'Code Arena Activity', 'bx-code-alt', '#06b6d4', $pfCodeSeries);
                    $pfLineCard('ctf', 'CTF Challenges', 'bx-flag', '#10b981', $pfCtfSeries);
                    ?>

                    <div class="col-md-6">
                        <div class="pf-card card blur border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-3">
                                <div class="pf-chart-head">
                                    <h6 class="fw-bold mb-0"><i class="bx bx-trending-up me-1" style="color: #f59e0b"></i>Zeal Progress</h6>
                                </div>
                                <?php if ($pfRadarEmpty): ?>
                                    <div class="pf-empty">
                                        <i class="bx bx-line-chart"></i>
                                        <span>No activity recorded yet.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="pf-chartbox"><canvas id="pfChart-radar"></canvas></div>
                                    <p class="pf-chart-note mb-0">Monthly activity history — zeal itself is only kept as a running total.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="pf-card card blur border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-3">
                                <div class="pf-chart-head">
                                    <h6 class="fw-bold mb-0"><i class="bx bx-bar-chart-alt-2 me-1" style="color: #ec4899"></i>CTF Challenges by Category</h6>
                                </div>
                                <?php if (!$pfCtfCats): ?>
                                    <div class="pf-empty">
                                        <i class="bx bx-flag"></i>
                                        <span>No solved challenges yet.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="pf-chartbox"><canvas id="pfChart-cats"></canvas></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="pf-card card blur border-0 shadow-sm rounded-4">
                            <div class="card-body p-3">
                                <div class="pf-sec-title"><i class="bx bx-category"></i>All Skills</div>
                                <?php if ($pfAllSkills): ?>
                                    <div class="pf-skills is-all">
                                        <?php foreach ($pfAllSkills as $pfIdx => $pfSkill): ?>
                                            <span class="pf-skill <?= $pfIdx < 3 ? 'is-hot' : 'is-cool' ?>">
                                                <i class="<?= $pfIdx < 3 ? 'bxs-star' : 'bx bx-rocket' ?>"></i><?= $pfEsc($pfSkill['label']) ?>
                                                <b><?= (int)$pfSkill['count'] ?></b>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="pf-empty">
                                        <i class="bx bx-category"></i>
                                        <span>No skills tracked yet — they appear once a roadmap is opened.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ---------- ACTIVITY ---------- -->
            <div class="pf-panel<?= $pfTab === 'activity' ? '' : ' d-none' ?>" id="pf-panel-activity">
                <?php if ($pfFeed): ?>
                    <div class="pf-feed">
                        <?php foreach ($pfFeed as $pfRow): ?>
                            <div class="pf-feed-row">
                                <img class="pf-feed-avatar" src="<?= $pfEsc($pfAvatar) ?>" alt="" loading="lazy">
                                <div class="pf-feed-main">
                                    <div class="pf-feed-head">
                                        <span class="pf-feed-name"><?= $pfEsc($pfName) ?></span>
                                        <span class="pf-feed-handle">@<?= $pfEsc($pfUser) ?></span>
                                        <span class="pf-feed-chip" title="Total Zeal"><i class="bxs-hot"></i><?= number_format($pfScores['zeal']) ?></span>
                                        <span class="pf-feed-chip" title="Available Jolt"><i class="bxs-zap"></i><?= number_format($pfScores['jolt']) ?></span>
                                        <span class="pf-feed-rank"><i class="bxs-shield"></i><?= $pfEsc($pfRank['title']) ?></span>
                                        <span class="pf-feed-time"><?= $pfEsc(profile_time_ago($pfRow['ts'])) ?></span>
                                    </div>
                                    <div class="pf-feed-text">
                                        <i class="<?= $pfEsc($pfRow['icon']) ?>"></i>
                                        <span><?= $pfRow['text'] ?></span>
                                        <?php if (!empty($pfRow['earned'])): ?>
                                            <span class="pf-feed-earned"><?= number_format((int)$pfRow['earned']) ?> <i class="bxs-hot"></i> Earned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-body-secondary small mb-0 mt-3">Showing the <?= count($pfFeed) ?> most recent events.</p>
                <?php else: ?>
                    <div class="pf-card card blur border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="pf-empty">
                                <i class="bx bx-history"></i>
                                <span>No activity recorded yet.</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ---------- ACHIEVEMENTS ---------- -->
            <div class="pf-panel<?= $pfTab === 'achievements' ? '' : ' d-none' ?>" id="pf-panel-achievements">

                <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div>
                                <div class="pf-sec-title mb-1"><i class="bx bx-medal"></i>Progress</div>
                                <div class="text-body-secondary small"><?= (int)$pfAchSummary['unlocked'] ?> of <?= (int)$pfAchSummary['total'] ?> badges unlocked</div>
                            </div>
                            <div class="pf-ach-pct"><?= (int)$pfAchSummary['percent'] ?>%</div>
                        </div>
                        <div class="pf-progress"><span style="width: <?= (int)$pfAchSummary['percent'] ?>%"></span></div>
                    </div>
                </div>

                <?php foreach ($pfGroups as $pfGroup): ?>
                    <div class="pf-card card blur border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-body p-3">
                            <div class="pf-group-title">
                                <i class="bx <?= $pfEsc($pfGroup['icon']) ?>" style="--gc: <?= $pfEsc($pfGroup['color']) ?>"></i>
                                <?= $pfEsc($pfGroup['label']) ?>
                                <span class="pf-group-count">
                                    <?= (int)count(array_filter($pfGroup['items'], static fn ($i) => $i['unlocked'])) ?>/<?= (int)count($pfGroup['items']) ?>
                                </span>
                            </div>
                            <div class="pf-medals">
                                <?php foreach ($pfGroup['items'] as $pfItem): ?>
                                    <div class="pf-medal<?= $pfItem['unlocked'] ? '' : ' is-locked' ?>"
                                         title="<?= $pfEsc($pfItem['desc']) ?>">
                                        <span class="pf-medal-ic" style="--mc: <?= $pfEsc($pfItem['color']) ?>">
                                            <i class="bx <?= $pfEsc($pfItem['icon']) ?>"></i>
                                            <?php if (!$pfItem['unlocked']): ?><i class="bx bx-lock pf-medal-lock"></i><?php endif; ?>
                                        </span>
                                        <span class="pf-medal-name"><?= $pfEsc($pfItem['name']) ?></span>
                                        <span class="pf-medal-desc"><?= $pfEsc($pfItem['hint'] !== '' ? $pfItem['hint'] : $pfItem['desc']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="text-body-secondary small mb-0">
                    Quiz and CTF badges unlock automatically as you complete quizzes and solve challenges.
                </p>
            </div>

        </div>
    </div>
</div>

<script type="application/json" id="pfData"><?= json_encode($pfChartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    var data = {};
    try { data = JSON.parse(document.getElementById('pfData').textContent || '{}'); } catch (e) { data = {}; }

    var COLORS = { quiz: '#6366f1', code: '#06b6d4', ctf: '#10b981' };
    var FILLS  = { quiz: 'rgba(99, 102, 241, 0.18)', code: 'rgba(6, 182, 212, 0.18)', ctf: 'rgba(16, 185, 129, 0.18)' };
    var GRID = 'rgba(148, 163, 184, 0.18)';
    var TICK = '#94a3b8';

    var charts = {};
    var activeTab = <?= json_encode($pfTab) ?>;
    var ranges = { quiz: 3, code: 3, ctf: 3 };
    var profileRendered = false;

    function el(id) { return document.getElementById(id); }

    function monthsFor(key) {
        var rows = Array.isArray(data[key]) ? data[key] : [];
        var n = ranges[key] || 3;
        return rows.slice(Math.max(0, rows.length - n));
    }

    function renderMonthly(key) {
        var canvas = el('pfChart-' + key);
        if (!canvas || !window.Chart) return;
        var rows = monthsFor(key);
        if (charts[key]) charts[key].destroy();
        charts[key] = new Chart(canvas, {
            type: 'line',
            data: {
                labels: rows.map(function (r) { return r.label; }),
                datasets: [{
                    data: rows.map(function (r) { return r.count; }),
                    borderColor: COLORS[key],
                    backgroundColor: FILLS[key],
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, ticks: { color: TICK, font: { size: 10 }, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: GRID }, ticks: { color: TICK, font: { size: 11 }, precision: 0 } }
                },
                plugins: { legend: { display: false }, tooltip: { displayColors: false } }
            }
        });
    }

    function renderRadar() {
        var canvas = el('pfChart-radar');
        if (!canvas || !window.Chart) return;
        var radar = data.radar || {};
        var labels = radar.labels || [];
        if (!labels.length) return;
        if (charts.radar) charts.radar.destroy();
        charts.radar = new Chart(canvas, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [{
                    data: radar.values || [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.22)',
                    borderWidth: 2,
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        grid: { color: GRID },
                        angleLines: { color: GRID },
                        pointLabels: { color: TICK, font: { size: 10 } },
                        ticks: { color: TICK, backdropColor: 'transparent', font: { size: 9 }, precision: 0 }
                    }
                },
                plugins: { legend: { display: false }, tooltip: { displayColors: false } }
            }
        });
    }

    function renderCats() {
        var canvas = el('pfChart-cats');
        if (!canvas || !window.Chart) return;
        var rows = Array.isArray(data.cats) ? data.cats : [];
        if (!rows.length) return;
        if (charts.cats) charts.cats.destroy();
        charts.cats = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (r) { return r.label; }),
                datasets: [{
                    data: rows.map(function (r) { return r.count; }),
                    backgroundColor: 'rgba(236, 72, 153, 0.65)',
                    borderColor: 'rgba(236, 72, 153, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, grid: { color: GRID }, ticks: { color: TICK, font: { size: 11 }, precision: 0 } },
                    y: { grid: { display: false }, ticks: { color: TICK, font: { size: 11 } } }
                },
                plugins: { legend: { display: false }, tooltip: { displayColors: false } }
            }
        });
    }

    function renderProfileCharts(tries) {
        if (profileRendered) return;
        if (!window.Chart) {
            if ((tries || 0) > 60) return;
            setTimeout(function () { renderProfileCharts((tries || 0) + 1); }, 50);
            return;
        }
        profileRendered = true;
        renderMonthly('quiz');
        renderMonthly('code');
        renderMonthly('ctf');
        renderRadar();
        renderCats();
    }

    function activate(tab) {
        var tabs = document.querySelectorAll('[data-pf-tab]');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('active', tabs[i].getAttribute('data-pf-tab') === tab);
        }
        ['profile', 'activity', 'achievements'].forEach(function (key) {
            var panel = el('pf-panel-' + key);
            if (panel) panel.classList.toggle('d-none', key !== tab);
        });
        activeTab = tab;
        if (tab === 'profile') renderProfileCharts();
        try {
            history.replaceState(null, '', location.pathname + '?tab=' + encodeURIComponent(tab));
        } catch (e) {}
    }

    document.addEventListener('click', function (e) {
        var tab = e.target.closest ? e.target.closest('[data-pf-tab]') : null;
        if (tab) {
            e.preventDefault();
            activate(tab.getAttribute('data-pf-tab'));
            return;
        }
        var btn = e.target.closest ? e.target.closest('button[data-pf-range]') : null;
        if (!btn) return;
        var key = btn.getAttribute('data-pf-range');
        ranges[key] = Number(btn.getAttribute('data-months')) || 3;
        var group = btn.parentNode;
        var all = group.querySelectorAll('button[data-pf-range="' + key + '"]');
        for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
        btn.classList.add('active');
        renderMonthly(key);
    });

    if (activeTab === 'profile') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { renderProfileCharts(); });
        } else {
            renderProfileCharts();
        }
    }
})();
</script>
