<?php
require_once __DIR__ . '/../../../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
require_once __DIR__ . '/../../../../src/utils/errors.php';
$openErrors = function_exists('errors_count') ? errors_count(['status' => 'open']) : 0;

/* BSON-safe stringifier for audit detail / dates (BSONDocument has no __toString) */
$fmtWhen = function ($v) {
    if ($v === null) return '';
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y H:i');
    if (!is_scalar($v) && !($v instanceof Stringable)) return '';
    try { return (new DateTimeImmutable((string)$v))->format('d M Y H:i'); } catch (Throwable $e) { return (string)$v; }
};
$fmtDetail = function ($v) use (&$fmtDetail) {
    if ($v === null) return '';
    if (is_scalar($v)) return (string)$v;
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format(DateTimeInterface::ATOM);
    if ($v instanceof Stringable) return (string)$v;
    if ($v instanceof Traversable) $v = iterator_to_array($v);
    if (is_array($v)) {
        $parts = [];
        foreach ($v as $k => $x) $parts[] = $k . '=' . $fmtDetail($x);
        return implode(', ', $parts);
    }
    if (is_object($v)) $v = get_object_vars($v);
    $j = json_encode($v, JSON_UNESCAPED_SLASHES);
    return $j === false ? gettype($v) : $j;
};

$toDate = function ($v) {
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime();
    if ($v instanceof DateTimeInterface) return DateTimeImmutable::createFromInterface($v);
    if (is_string($v)) { try { return new DateTimeImmutable($v); } catch (Throwable $e) { return null; } }
    return null;
};

/* ---------------------------------------------------------------- Fleet */
$fleet = ['users' => 0, 'admins' => 0, 'labs' => 0, 'peers' => 0, 'domains' => 0, 'services' => 0];
$svcHealthy = 0; $svcTotal = 0;
try {
    $fleet['users']   = $db->users->countDocuments([]);
    $fleet['admins']  = $db->users->countDocuments(['role' => 'superuser']);
    $fleet['peers']   = $db->devices->countDocuments([]);
    $fleet['domains'] = $db->domains->countDocuments([]);
} catch (Throwable $e) { error_log('fleet: ' . $e->getMessage()); }

/* ---------------------------------------------- Labs grouped by status */
$bucket = function ($s) {
    $s = strtolower((string)$s);
    if (in_array($s, ['running', 'active'], true)) return 'running';
    if (in_array($s, ['error', 'failed'], true))    return 'failed';
    if (in_array($s, ['deploying', 'queued', 'processing'], true)) return 'deploying';
    if (in_array($s, ['stopped', 'not_deployed'], true)) return 'stopped';
    return 'other';
};
$byStatus = ['running' => 0, 'deploying' => 0, 'stopped' => 0, 'failed' => 0, 'other' => 0];
$incidents = 0; $failedDeploys = 0;
try {
    foreach ($db->machine_labs->find([], ['projection' => ['status' => 1]]) as $lab) {
        $b = $bucket($lab['status'] ?? '');
        $byStatus[$b]++;
        if ($b === 'failed') $incidents++;
    }
    $fleet['labs'] = array_sum($byStatus);
    $failedDeploys = $db->deploy_queue->countDocuments(['status' => ['$in' => ['failed', 'error', 'timeout']]]);
} catch (Throwable $e) { error_log('labs: ' . $e->getMessage()); }

/* --------------------------------- Services (from the Services page's set) */
$serviceNames = ['apache2', 'mongodb', 'rabbitmq-server', 'traefik'];
try {
    $svcTotal = count($serviceNames);
    foreach ($serviceNames as $sn) {
        exec('systemctl is-active ' . escapeshellarg($sn) . ' 2>/dev/null', $out, $rc);
        if (($out[0] ?? '') === 'active') $svcHealthy++;
        $out = [];
    }
} catch (Throwable $e) { /* non-fatal */ }
$fleet['services'] = $svcHealthy;

/* --------------------------------------------------- Storage / quota data */
require_once __DIR__ . '/../../../../src/utils/storage.php';
$storageBytes = 0; $overQuota = 0; $poolUsed = 0; $poolTotal = 0; $tenantsAudited = 0;
try {
    foreach ($db->storage_usage->find([], ['projection' => ['bytes' => 1, 'plan' => 1]]) as $r) {
        $b = (int)($r['bytes'] ?? 0);
        $storageBytes += $b;
        $tenantsAudited++;
        if ($b > storage_plan_limit((string)($r['plan'] ?? ''))) $overQuota++;
    }
    $pp = $db->storage_pools->findOne(['_id' => 'default']);
    if ($pp) { $poolUsed = (int)($pp['used_bytes'] ?? 0); $poolTotal = (int)($pp['total_bytes'] ?? 0); }
} catch (Throwable $e) { error_log('dashboard storage: ' . $e->getMessage()); }

/* --------------------------------------------------- Queued / policy work */
$scheduled = 0;
try {
    $scheduled += $db->deploy_queue->countDocuments(['status' => ['$in' => ['queued', 'processing', 'pending']]]);
    foreach (['quiz_jobs', 'ai_roadmap_jobs', 'ai_lesson_jobs'] as $jc) {
        $scheduled += $db->$jc->countDocuments(['status' => ['$in' => ['queued', 'pending', 'running', 'scheduled']]]);
    }
} catch (Throwable $e) { error_log('dashboard scheduled: ' . $e->getMessage()); }

$adminCount = 0;
try {
    $adminCount = $db->users->countDocuments(['role' => ['$in' => ['superuser', 'admin']]]);
} catch (Throwable $e) { /* non-fatal */ }

$vpnPeers = 0;
try {
    $vpnPeers = $db->ip_registry->countDocuments(['reserved_to' => ['$nin' => ['', null, 'server']]]);
} catch (Throwable $e) { /* non-fatal */ }

$deadLetter = $incidents;                 // labs whose latest deploy ended in error/failed
$modReports  = 0;                         // no moderation collection yet
$cheatFlags  = 0;                         // no submission-flag collection yet
$syllabusReq = 0;                         // no syllabus-request collection yet
$tlsExpiring = 0;                         // domains carry no expiry date yet

/* --------------------------------------------- Needs attention (all rows) */
$attention = [
    ['title' => 'Dead-letter deploys',     'why' => 'labs whose latest deploy failed',            'n' => $deadLetter,  'sev' => 'incident', 'icon' => 'bx-x-circle',       'href' => '/admin/instances'],
    ['title' => 'Failed deploys',          'why' => 'deployment attempts in a failed state',      'n' => $failedDeploys, 'sev' => 'incident', 'icon' => 'bx-error-circle',  'href' => '/admin/instances'],
    ['title' => 'Moderation reports',      'why' => 'quiz / code / message / discussion reports pending', 'n' => $modReports,  'sev' => 'warn', 'icon' => 'bx-message-error',  'href' => '/admin/users'],
    ['title' => 'Cheat flags',             'why' => 'flagged submissions awaiting review',        'n' => $cheatFlags,  'sev' => 'warn',     'icon' => 'bx-flag',           'href' => '/admin/users'],
    ['title' => 'Over-quota tenants',      'why' => 'accounts over their storage limit',          'n' => $overQuota,   'sev' => 'warn',     'icon' => 'bx-hdd',            'href' => '/admin/storage'],
    ['title' => 'Syllabus requests',       'why' => 'requests awaiting a decision',               'n' => $syllabusReq, 'sev' => 'warn',     'icon' => 'bx-book-open',      'href' => '/admin/users'],
    ['title' => 'TLS certificates expiring', 'why' => 'certs within their renewal window',        'n' => $tlsExpiring, 'sev' => 'warn',     'icon' => 'bx-lock-alt',       'href' => '/ssl'],
];
$activeItems = count(array_filter($attention, fn($r) => $r['n'] > 0));
$incidentsN  = count(array_filter($attention, fn($r) => $r['n'] > 0 && $r['sev'] === 'incident'));
$thingsN     = $activeItems - $incidentsN;
$verdict = $incidentsN > 0 ? ['t' => "{$incidentsN} incidents need attention", 'c' => 'danger']
        : ($thingsN  > 0 ? ['t' => "{$thingsN} things need attention",     'c' => 'warning']
                         : ['t' => 'All systems nominal',                   'c' => 'success']);
$activeLabel = $activeItems > 0
    ? $activeItems . ' item' . ($activeItems === 1 ? '' : 's')
    : 'all clear';

/* ----------------------------------------------------------- Trends 30d */
$days = [];
$cursor = new DateTimeImmutable('-29 days');
for ($i = 0; $i < 30; $i++) {
    $days[$cursor->modify("+{$i} days")->format('Y-m-d')] = ['users' => 0, 'labs' => 0, 'incidents' => 0];
}
$from = new DateTimeImmutable('-30 days');
try {
    foreach ($db->users->find(['created_at' => ['$gte' => $from]], ['projection' => ['created_at' => 1]]) as $u) {
        $k = $toDate($u['created_at'] ?? null);
        if ($k && isset($days[$k->format('Y-m-d')])) $days[$k->format('Y-m-d')]['users']++;
    }
    foreach ($db->machine_labs->find([], ['projection' => ['_id' => 1]]) as $l) {
        try { $k = $l['_id']->toDateTime()->format('Y-m-d'); } catch (Throwable $e) { continue; }
        if (isset($days[$k])) $days[$k]['labs']++;
    }
    foreach ($db->audit_log->find(['created_at' => ['$gte' => $from]], ['projection' => ['created_at' => 1, 'action' => 1]]) as $a) {
        if (!in_array($a['action'] ?? '', ['delete', 'revoke', 'destroy'], true)) continue;
        $k = $toDate($a['created_at'] ?? null);
        if ($k && isset($days[$k->format('Y-m-d')])) $days[$k->format('Y-m-d')]['incidents']++;
    }
} catch (Throwable $e) { error_log('trends: ' . $e->getMessage()); }

$series = [
    'users'     => ['label' => 'New users',     'color' => '#89b4fa'],
    'labs'      => ['label' => 'Labs launched', 'color' => '#a6e3a1'],
    'incidents' => ['label' => 'Incidents',     'color' => '#f38ba8'],
];
$seriesMax = [];
foreach ($series as $k => $s) {
    $m = 0; foreach ($days as $d) $m = max($m, $d[$k]);
    $seriesMax[$k] = max($m, 1);
}

/* ------------------------------------------------------ Activity (last 15) */
$activity = [];
$auditCount = 0;
try {
    foreach ($db->audit_log->find([], ['sort' => ['created_at' => -1], 'limit' => 15]) as $row) $activity[] = $row;
    $auditCount = $db->audit_log->countDocuments([]);
} catch (Throwable $e) { error_log('activity: ' . $e->getMessage()); }

$env = (Session::getEnvironment() ?: 'production');
$envCls = $env === 'production' ? 'danger' : 'info';

$quickJump = [
    ['Users', '/admin/users', 'bx-group'],
    ['Instances', '/admin/instances', 'bx-server'],
    ['Services', '/admin/services', 'bx-desktop'],
    ['Modules', '/admin/modules', 'bx-toggle-right'],
    ['Access Control', '/admin/acl', 'bx-shield-quarter'],
    ['Storage Quotas', '/admin/storage', 'bx-hdd'],
    ['MCP Tools', '/admin/mcp', 'bx-bot'],
    ['SSL', '/ssl', 'bx-lock-alt'],
    ['VPN Interfaces', '/network/interfaces', 'bx-wifi'],
];
?>

<style>
.adm-glow { border: 1px solid rgba(255,140,0,.42) !important;
            box-shadow: 0 0 0 1px rgba(255,120,0,.25), 0 0 26px rgba(255,90,0,.28), inset 0 0 60px rgba(255,90,0,.05); }
.adm-dot { width: 14px; height: 14px; border-radius: 50%; flex: 0 0 auto; display: inline-block; }
.adm-dot.red   { background: #ff2d55; box-shadow: 0 0 10px rgba(255,45,85,.85); }
.adm-dot.amber { background: #ffd200; box-shadow: 0 0 10px rgba(255,210,0,.75); }
.adm-dot.ok    { background: #2ecc71; box-shadow: 0 0 8px rgba(46,204,113,.65); }
.adm-chip { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.20); border-radius: 999px;
            padding: .18rem .7rem; font-size: .72rem; font-weight: 700; white-space: nowrap;
            background: transparent; color: var(--cui-body-color); }
.adm-chip-warn { border-color: rgba(255,210,0,.55); color: #ffd200; }
.adm-chip-bad  { border-color: rgba(255,90,90,.60); color: #ff6b6b; }
.adm-att-icon { width: 34px; height: 34px; border-radius: 9px; flex: 0 0 auto;
                display: inline-flex; align-items: center; justify-content: center;
                background: rgba(var(--cui-body-color-rgb,255,255,255),.07);
                border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.08); }
.adm-att { position: relative; padding-left: 1rem !important; }
.adm-att::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: transparent; }
.adm-att[data-tone="red"]::before   { background: #ff2d55; }
.adm-att[data-tone="amber"]::before { background: #ffd200; }
.adm-att[data-tone="ok"]::before    { background: #2ecc71; }
.adm-count { min-width: 2.6rem; text-align: center; border-radius: 999px; padding: .15rem .55rem;
             font-size: .74rem; font-weight: 800; }
.adm-count.is-red   { background: rgba(255,90,90,.20); color: #ff6b6b; border: 1px solid rgba(255,90,90,.45); }
.adm-count.is-amber { background: rgba(255,210,0,.16); color: #ffd200; border: 1px solid rgba(255,210,0,.40); }
.adm-count.is-zero  { background: rgba(var(--cui-body-color-rgb,255,255,255),.06); color: rgba(var(--cui-body-color-rgb,255,255,255),.45);
                      border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.10); }
.adm-stat:hover { border-color: rgba(var(--cui-primary-rgb, 33,150,243), .45); }
.adm-bar-row { display: flex; align-items: flex-end; gap: 2px; height: 74px; }
.adm-bar { flex: 1; min-width: 3px; border-radius: 2px 2px 0 0; transition: opacity .15s; }
.adm-bar:hover { opacity: .7; }
.adm-att-row:hover { background: rgba(var(--cui-body-bg-rgb, 11,30,54), .35); }
.adm-qj { display: flex; align-items: center; gap: .6rem; padding: .75rem 1rem; border-radius: .75rem;
          text-decoration: none; color: inherit; }
.adm-qj:hover { border-color: rgba(var(--cui-primary-rgb,33,150,243), .45); color: inherit; text-decoration: none; }
</style>

<!-- ============================== Banner ============================== -->
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-crown'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Admin Dashboard</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Platform control plane &mdash; health, queues, and scale at a glance.</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                    <span class="badge rounded-pill text-bg-<?= $envCls ?>"><?= htmlspecialchars(ucfirst($env)) ?></span>
                    <span class="badge rounded-pill text-bg-secondary" id="adm-clock" style="font-variant-numeric:tabular-nums;">--:--:--</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <?php if ($openErrors > 0): ?>
    <div class="alert alert-danger d-flex align-items-center justify-content-between gap-3 rounded-3 mb-4 py-3" role="alert">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <i class='bx bx-error-circle fs-4'></i>
            <div>
                <div class="fw-semibold"><?= number_format($openErrors) ?> error<?= $openErrors === 1 ? '' : 's' ?> awaiting review</div>
                <div class="small opacity-75 mb-0">Something failed for your learners and nobody has looked at it yet.</div>
            </div>
        </div>
        <a href="/admin/errors" hx-boost="false" class="btn btn-sm btn-light text-danger rounded-pill px-3 fw-semibold">
            Open Error Monitor
        </a>
    </div>
    <?php endif; ?>


    <!-- ========================== Status masthead ========================== -->
<div class="card border-0 rounded-4 blur shadow-sm mb-4 adm-glow">
    <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-start gap-3">
                <span class="adm-dot <?= $incidentsN > 0 ? 'red' : ($activeItems > 0 ? 'amber' : 'ok') ?> mt-2"></span>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="fw-bold mb-0 ls-tight"><?= htmlspecialchars($verdict['t']) ?></h4>
                        <span class="adm-chip" style="border-color:rgba(77,171,247,.55);color:#4dabf7;">BETA</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary mt-1">
                        <span><?= (int)$fleet['labs'] ?> labs running</span>
                        <span>&middot;</span>
                        <span><?= number_format((int)$svcTotal) ?> services</span>
                        <span>&middot;</span>
                        <span id="adm-clock" style="font-variant-numeric:tabular-nums;">--:--:--</span>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-end gap-2">
                <span class="adm-chip">cron <?= (int)$scheduled ?> scheduled</span>
                <span class="adm-chip<?= $overQuota ? ' adm-chip-warn' : '' ?>"><?= (int)$overQuota ?> over quota</span>
                <span class="adm-chip<?= $failedDeploys ? ' adm-chip-bad' : '' ?>"><?= (int)$failedDeploys ?> failed deploys</span>
                <span class="adm-chip<?= $incidentsN ? ' adm-chip-bad' : '' ?>"><?= (int)$incidentsN ?> incidents</span>
                <span class="adm-chip<?= $tlsExpiring ? ' adm-chip-warn' : '' ?>">ssl <?= (int)$tlsExpiring ?> expiring</span>
            </div>
        </div>
    </div>
</div>

<!-- =================== Needs attention  |  Fleet and scale =================== -->
<div class="row g-4 mb-4">

    <!-- ------------------------------ Needs attention ------------------------------ -->
    <div class="col-xl-7">
        <div class="card border-0 rounded-4 blur shadow-sm h-100 adm-glow">
            <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-uppercase ls-tight">Needs attention</h6>
                <span class="adm-chip"><?= htmlspecialchars($activeLabel) ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($attention as $i => $row):
                    $on   = $row['n'] > 0;
                    $tone = !$on ? 'ok' : ($row['sev'] === 'incident' ? 'red' : 'amber');
                ?>
                <a href="<?= htmlspecialchars($row['href']) ?>"
                   class="adm-att d-flex align-items-center gap-3 px-4 py-3 border-bottom border-body-secondary border-opacity-10 text-decoration-none text-body adm-att-row"
                   data-tone="<?= $tone ?>">
                    <span class="adm-dot <?= $tone ?>"></span>
                    <span class="adm-att-icon"><i class='bx <?= $row['icon'] ?>'></i></span>
                    <span class="flex-grow-1">
                        <span class="d-block fw-semibold"><?= htmlspecialchars($row['title']) ?></span>
                        <span class="d-block small text-body-secondary"><?= htmlspecialchars($row['why']) ?></span>
                    </span>
                    <span class="adm-count <?= $on ? ($tone === 'red' ? 'is-red' : 'is-amber') : 'is-zero' ?>">
                        <?= (int)$row['n'] ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- -------------------------------- Fleet and scale -------------------------------- -->
    <div class="col-xl-5">
        <div class="card border-0 rounded-4 blur shadow-sm h-100">
            <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                <h6 class="mb-0 fw-bold text-uppercase ls-tight">Fleet &amp; scale</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center mb-4">
                    <?php foreach ([
                        ['Users',            number_format((int)$fleet['users']),  'text-primary',  '/admin/users'],
                        ['Running labs',     number_format((int)$byStatus['running']), 'text-success', '/admin/instances'],
                        ['Managed services', number_format((int)$svcTotal),        'text-info',     '/admin/services'],
                        ['Storage',          storage_format_bytes($poolUsed),      'text-warning',  '/admin/storage'],
                        ['VPN peers',        number_format((int)$vpnPeers),        'text-danger',   '/network/interfaces'],
                        ['Admins',           number_format((int)$adminCount),      'text-danger',   '/admin/users'],
                    ] as $f): ?>
                    <div class="col-4">
                        <a href="<?= htmlspecialchars($f[3]) ?>" class="d-block text-decoration-none">
                            <div class="fs-4 fw-bold <?= $f[2] ?>"><?= htmlspecialchars($f[1]) ?></div>
                            <div class="small text-body-secondary"><?= htmlspecialchars($f[0]) ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="small text-body-secondary mb-2">Instances by status</div>
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <?php
                    $donutMeta = [
                        'running'   => ['Running',   '#2ecc71'],
                        'deploying' => ['Deploying', '#4dabf7'],
                        'stopped'   => ['Stopped',   '#868e96'],
                        'failed'    => ['Failed',    '#ff6b6b'],
                        'other'     => ['Other',     '#adb5bd'],
                    ];
                    $donutTotal = max(1, array_sum($byStatus));
                    $R = 54; $C = 2 * 3.14159265 * $R; $acc = 0;
                    ?>
                    <svg viewBox="0 0 140 140" width="150" height="150" role="img" aria-label="Instances by status">
                        <circle cx="70" cy="70" r="<?= $R ?>" fill="none" stroke="rgba(140,140,140,.22)" stroke-width="20"></circle>
                        <?php foreach ($donutMeta as $k => $m):
                            $v = (int)$byStatus[$k];
                            if ($v <= 0) continue;
                            $len = $v / $donutTotal * $C;
                        ?>
                        <circle cx="70" cy="70" r="<?= $R ?>" fill="none"
                                stroke="<?= $m[1] ?>" stroke-width="20" stroke-linecap="butt"
                                stroke-dasharray="<?= number_format($len, 2) ?> <?= number_format($C - $len, 2) ?>"
                                stroke-dashoffset="<?= number_format(-$acc, 2) ?>"
                                transform="rotate(-90 70 70)"></circle>
                        <?php $acc += $len; endforeach; ?>
                        <text x="70" y="66" text-anchor="middle" fill="currentColor"
                              style="font-size:22px;font-weight:700;"><?= (int)array_sum($byStatus) ?></text>
                        <text x="70" y="86" text-anchor="middle" fill="currentColor" opacity=".55"
                              style="font-size:10px;letter-spacing:.06em;">TOTAL</text>
                    </svg>
                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-1">
                        <?php foreach ($donutMeta as $k => $m): ?>
                        <li class="d-flex align-items-center gap-2">
                            <span style="width:9px;height:9px;border-radius:2px;background:<?= $m[1] ?>;"></span>
                            <span class="text-body-secondary"><?= $m[0] ?></span>
                            <span class="fw-semibold ms-auto ps-3"><?= (int)$byStatus[$k] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================ Trends ================================ -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-line-chart me-2 text-primary'></i>Trends</h5>
            <small class="text-body-secondary">Last 30 days</small>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($series as $key => $meta):
                    $vals = array_map(fn($d) => $d[$key], array_values($days));
                    $max  = $seriesMax[$key];
                    $has  = array_sum($vals) > 0;
                    $keys = array_keys($days);
                ?>
                <div class="col-12 col-xl-3">
                    <div class="adm-stat rounded-4 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold"><?= $meta['label'] ?></span>
                            <span class="badge rounded-pill adm-stat"><?= array_sum($vals) ?></span>
                        </div>
                        <div class="adm-bar-row">
                            <?php foreach ($vals as $i => $v): ?>
                                <div class="adm-bar" title="<?= htmlspecialchars($keys[$i]) ?>: <?= (int)$v ?>"
                                     style="height:<?= $has ? max(3, (int)round($v / $max * 100)) : 3 ?>%;
                                            background:<?= $has && $v > 0 ? $meta['color'] : 'rgba(140,140,140,.28)' ?>;"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-between small text-body-secondary mt-1">
                            <span><?= htmlspecialchars(substr($keys[0], 5)) ?></span>
                            <span><?= htmlspecialchars(substr($keys[29], 5)) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- =============================== Activity =============================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-time-five me-2 text-secondary'></i>Activity</h5>
            <a href="/admin/acl" class="small">Audit log <i class='bx bx-right-arrow-alt'></i></a>
        </div>
        <div class="card-body p-0">
            <?php if (!$activity): ?>
                <div class="text-center text-body-secondary py-4">No admin actions recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark opacity-75">
                            <tr>
                                <th class="ps-4">When</th><th>Actor</th><th>Event</th>
                                <th>Resource</th><th class="pe-4">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activity as $a):
                            $when = '';
                            $when = $fmtWhen($a['created_at'] ?? null);
                            $det = $a['details'] ?? null;
                            $detStr = $fmtDetail($det);
                        ?>
                            <tr>
                                <td class="ps-4 text-body-secondary text-nowrap"><?= htmlspecialchars($when) ?></td>
                                <td><?= htmlspecialchars((string)($a['user_id'] ?? '—')) ?></td>
                                <td><span class="badge rounded-pill text-bg-<?= ($a['action'] ?? '') === 'delete' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string)($a['action'] ?? '')) ?></span></td>
                                <td><?= htmlspecialchars((string)($a['entity_type'] ?? '')) ?> <code class="small"><?= htmlspecialchars((string)($a['entity_id'] ?? '')) ?></code></td>
                                <td class="pe-4 text-body-secondary"><?= htmlspecialchars(mb_substr($detStr, 0, 80)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================== Quick jump ============================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
            <h5 class="mb-0 fw-bold"><i class='bx bx-rocket me-2 text-warning'></i>Quick jump</h5>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($quickJump as $q): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <a class="adm-qj w-100 h-100" href="<?= htmlspecialchars($q[1]) ?>">
                        <i class='bx <?= $q[2] ?> fs-5 text-primary'></i>
                        <span class="fw-semibold small"><?= htmlspecialchars($q[0]) ?></span>
                        <i class='bx bx-chevron-right ms-auto text-body-secondary'></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var el = document.getElementById('adm-clock');
    if (!el) return;
    function tick() {
        var d = new Date();
        el.textContent = d.toLocaleTimeString([], { hour12: false });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
