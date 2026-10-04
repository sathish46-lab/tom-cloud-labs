<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/utils/storage.php';

$db = DatabaseConnection::getDefaultDatabase();

/* --------------------------------------------------------------- helpers */
$fmtWhen = function ($v) {
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y H:i');
    if (is_int($v) || is_float($v)) return (new DateTimeImmutable('@' . (int)$v))->format('d M Y H:i');
    if ($v === null || (!is_scalar($v) && !($v instanceof Stringable))) return '';
    try { return (new DateTimeImmutable((string)$v))->format('d M Y H:i'); } catch (Throwable $e) { return (string)$v; }
};
$toPlan = function ($v) {
    $p = strtolower(trim((string)$v));
    return in_array($p, ['free', 'default', 'pro'], true) ? $p : STORAGE_PLAN_FALLBACK;
};

/* ------------------------------------------------------------------ data */
$base = storage_base_path();
$err  = null;
$rows = [];
$pool = ['_id' => 'default', 'label' => 'default', 'path' => $base, 'fs' => 'overlay',
         'total_bytes' => 0, 'used_bytes' => 0, 'avail_bytes' => 0, 'tenants' => 0,
         'over_quota' => 0, 'tenant_bytes' => 0, 'updated_at' => 0];

try {
    $cached = $db->storage_usage->countDocuments([]);
    if ($cached === 0 && is_dir($base)) {
        // First visit: measure now (cheap when only a few tenants exist on disk).
        $summary = storage_audit_run($db);
        if (isset($summary['error'])) $err = $summary['error'];
    }

    $plans = [];
    foreach ($db->users->find([], ['projection' => ['email' => 1, 'username' => 1, 'plan' => 1, 'state' => 1, 'user_id' => 1]]) as $u) {
        $em = trim((string)($u['email'] ?? ''));
        if ($em !== '') $plans[$em] = storage_plan_of((array)$u);
    }

    foreach ($db->storage_usage->find([], ['sort' => ['bytes' => -1]]) as $r) {
        $em    = (string)($r['user_email'] ?? '');
        $plan  = $toPlan($r['plan'] ?? ($plans[$em] ?? null));
        $ov     = is_numeric($r['limit_bytes'] ?? null) && (int)$r['limit_bytes'] > 0 ? (int)$r['limit_bytes'] : null;
        $limit  = storage_effective_limit($ov, $plan);
        $bytes  = (int)($r['bytes'] ?? 0);
        $rows[] = [
            'email'   => $em,
            'name'    => (string)($r['username'] !== '' ? ($r['username'] ?? '') : $em),
            'plan'    => $plan,
            'bytes'   => $bytes,
            'limit'   => $limit,
            'custom'  => $ov !== null,
            'pct'     => $limit > 0 ? min(999, round($bytes / $limit * 100, 1)) : 0,
            'over'    => $bytes > $limit,
            'missing' => !($r['path_exists'] ?? true),
            'when'    => (int)($r['audited_at'] ?? 0),
        ];
    }

    $p = $db->storage_pools->findOne(['_id' => 'default']);
    if ($p) {
        foreach (['label','path','fs','quota_mode'] as $k) if (isset($p[$k])) $pool[$k] = (string)$p[$k];
        foreach (['total_bytes','used_bytes','avail_bytes','tenants','over_quota','tenant_bytes','updated_at'] as $k)
            if (isset($p[$k])) $pool[$k] = (int)$p[$k];
    } else {
        exec('df -kP ' . escapeshellarg($base) . ' 2>/dev/null', $df, $rc);
        if ($rc === 0 && isset($df[1])) {
            $c = preg_split('/\s+/', trim($df[1]));
            if (count($c) >= 4) {
                $pool['total_bytes'] = (int)$c[1] * 1024;
                $pool['used_bytes']  = (int)$c[2] * 1024;
                $pool['avail_bytes'] = (int)$c[3] * 1024;
            }
        }
        $pool['tenants'] = count($rows);
    }
} catch (Throwable $e) {
    $err = 'Could not read storage usage: ' . $e->getMessage();
    error_log('storage page: ' . $e->getMessage());
}

/* -------------------------------------------------------------- aggregates */
$totalUsed   = array_sum(array_column($rows, 'bytes'));
$limitBytes  = array_sum(array_column($rows, 'limit'));
$overRows    = array_values(array_filter($rows, fn($r) => $r['over']));
$topConsumers= array_slice(array_values($rows), 0, 10);
$maxTop      = 1;
foreach ($topConsumers as $tc) $maxTop = max($maxTop, $tc['bytes']);
$overPct     = $pool['total_bytes'] > 0 ? min(100, round($pool['used_bytes'] / $pool['total_bytes'] * 100, 1)) : 0;
$auditedAt   = $pool['updated_at'] ?: (count($rows) ? max(array_column($rows, 'when')) : 0);
$tenants     = count($rows);

$tabs = [
    'dash'  => ['Dashboard',     'bx-tachometer'],
    'top'   => ['Top consumers', 'bx-bar-chart-alt'],
    'over'  => ['Over quota',    'bx-error-circle'],
    'orgs'  => ['Organizations', 'bx-buildings'],
    'pools' => ['Pools',         'bx-hdd'],
    'mig'   => ['Migrations',    'bx-transfer'],
];
$activeTab = $_GET['tab'] ?? 'pools';
if (!isset($tabs[$activeTab])) $activeTab = 'pools';
?>
<style>
.adm-pool-card { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.10); border-radius: 1rem; }
.adm-pool-card:hover { border-color: rgba(var(--cui-primary-rgb,33,150,243),.45); }
.adm-tab { border: 1px solid transparent; border-radius: 999px; padding: .4rem .9rem; font-size:.85rem;
           font-weight:600; color: var(--cui-body-color); background: transparent; text-decoration:none;
           display:inline-flex; align-items:center; gap:.4rem; white-space:nowrap; }
.adm-tab:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.06); color: var(--cui-body-color); }
.adm-tab.active { background: rgba(var(--cui-body-color-rgb,255,255,255),.10);
                  border-color: rgba(var(--cui-body-color-rgb,255,255,255),.18); color: var(--cui-body-color); }
.adm-pct { height: 7px; border-radius: 999px; background: rgba(0,0,0,.35); overflow: hidden; }
.adm-pct > i { display:block; height:100%; border-radius:999px; background: linear-gradient(90deg,#ffd200,#ff8a00); }
.adm-pct.over > i { background: linear-gradient(90deg,#ff5f6d,#ff8a5c); }
.adm-row:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.05); }
.adm-empty { border: 1px dashed rgba(var(--cui-body-color-rgb,255,255,255),.16);
             border-radius: 1rem; padding: 2.5rem 1.5rem; text-align: center; }
.adm-chip { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.20); border-radius: 999px;
            padding: .18rem .7rem; font-size: .72rem; font-weight: 700; background: transparent;
            color: var(--cui-body-color); }
.adm-chip.active { background: rgba(var(--cui-body-color-rgb,255,255,255),.14); }

/* Row action buttons — same hit target, hover tint and glyph weight. */
.st-act { width: 28px; height: 28px; border-radius: 9px; line-height: 1; padding: 0 !important;
          border: 0 !important; background: transparent !important;
          display: inline-flex; align-items: center; justify-content: center;
          color: var(--cui-body-color) !important; opacity: .6; transition: opacity .15s, background-color .15s; }
.st-act:hover { opacity: 1; background: rgba(var(--cui-body-color-rgb,255,255,255),.10) !important; }
.st-act.st-limit { color: #ffb300 !important; opacity: .85; }
.st-act.st-limit.is-custom { opacity: 1; background: rgba(255,179,0,.14) !important; }
.adm-chip.st-custom { border-color: rgba(255,179,0,.55); color: #ffb300; }
</style>

<!-- ================================ Banner ================================ -->
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-hdd'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Storage Quotas</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Per-tenant quotas · substrate · consumption</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="stReaudit"
                            class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-refresh me-1'></i>Re-audit usage
                    </button>
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <?php if ($err): ?>
    <div class="alert alert-danger border-0 rounded-4 mb-4"><i class='bx bx-error me-1'></i><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- ================================= Tabs ================================= -->
    <div class="adm-nav rounded-4 px-2 py-2 d-flex flex-wrap align-items-center gap-2 mb-4 shadow-sm">
        <?php foreach ($tabs as $k => $t): ?>
            <a href="/admin/storage?tab=<?= $k ?>" hx-boost="false" class="adm-tab<?= $k === $activeTab ? ' active' : '' ?>" data-tab="<?= $k ?>">
                <i class='bx <?= $t[1] ?>'></i><?= htmlspecialchars($t[0]) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ============================== POOLS tab ============================== -->
    <div class="st-panel" data-panel="pools" style="display:block;">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Storage pools</h5>
                <div class="small text-body-secondary">New users provision on the default pool.</div>
            </div>
            <button type="button" class="btn btn-sm fw-semibold rounded-pill px-3"
                    style="background:#ff7a18;color:#fff;" id="stAddPool">
                <i class='bx bx-plus me-1'></i>Add pool
            </button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-4">
                <div class="adm-stat adm-pool-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($pool['label']) ?> <span class="text-warning">★</span></div>
                            <code class="small text-body-secondary"><?= htmlspecialchars($pool['path']) ?></code>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="adm-chip"><?= htmlspecialchars($pool['fs']) ?></span>
                            <button class="btn btn-sm p-0 border-0 bg-transparent text-body-secondary" title="Pool options"><i class='bx bx-dots-vertical-rounded'></i></button>
                        </div>
                    </div>
                    <div class="mt-2"><span class="adm-chip" style="border-color:rgba(255,140,0,.5);color:#ff8a00;">per-<?= htmlspecialchars($pool['quota_mode'] === 'per-user' ? 'user' : $pool['quota_mode']) ?> quota</span></div>
                    <div class="adm-pct mt-3" style="height:9px;"><i style="width:<?= (int)$overPct ?>%"></i></div>
                    <div class="small text-body-secondary mt-2">
                        <?= storage_format_bytes((int)$pool['used_bytes']) ?> / <?= storage_format_bytes((int)$pool['total_bytes']) ?>
                        · <?= number_format($overPct, 1) ?>% · <?= storage_format_bytes(max(0, (int)$pool['avail_bytes'])) ?> free
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="small text-body-secondary"><?= (int)$pool['tenants'] ?> tenant(s) · <?= (int)$tenants ?> audited</span>
                        <button class="btn btn-sm rounded-pill px-3 border-secondary border-opacity-25 bg-transparent text-body-secondary" disabled title="Reserved">Remove</button>
                    </div>
                </div>
            </div>

            <?php foreach ([
                ['Audited tenants', number_format($tenants), 'bx-group', 'text-primary'],
                ['Storage in use',  storage_format_bytes($totalUsed), 'bx-hdd', 'text-warning'],
                ['Over quota',      number_format(count($overRows)), 'bx-error-circle', count($overRows) ? 'text-danger' : 'text-success'],
                ['Last audit',      $auditedAt ? $fmtWhen($auditedAt) : 'never', 'bx-time-five', 'text-body-secondary'],
            ] as $t): ?>
            <div class="col-6 col-md-3">
                <div class="adm-stat rounded-4 p-3 h-100">
                    <div class="small text-body-secondary mb-1"><i class='bx <?= $t[2] ?> <?= $t[3] ?> me-1'></i><?= $t[0] ?></div>
                    <div class="fs-5 fw-bold text-truncate"><?= htmlspecialchars($t[1]) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================== Storage map ============================== -->
        <div class="card border-0 rounded-4 blur shadow-sm mb-4">
            <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold"><i class='bx bx-data text-warning me-2'></i>Storage map</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="btn-group" role="group" aria-label="Owner filter">
                        <button class="adm-chip active" data-scope="all">All</button>
                        <button class="adm-chip" data-scope="user">Users</button>
                        <button class="adm-chip" data-scope="org">Orgs</button>
                        <button class="adm-chip" data-scope="dept">Depts</button>
                    </div>
                    <select class="form-select form-select-sm w-auto rounded-pill" id="stPoolFilter">
                        <option>All pools</option>
                        <option><?= htmlspecialchars($pool['label']) ?></option>
                    </select>
                    <button class="adm-chip" id="stCsv"><i class='bx bx-download me-1'></i>CSV</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 border-bottom border-body-secondary border-opacity-10">
                    <div class="d-flex align-items-center gap-2 small text-body-secondary">
                        <span>Show</span>
                        <select class="form-select form-select-sm w-auto rounded-pill" id="stPageSize">
                            <option>25</option><option>50</option><option>100</option>
                        </select>
                        <span>entries</span>
                        <span class="ms-2" id="stCount"><?= (int)$tenants ?></span><span>tenant(s)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 small text-body-secondary">
                        <label for="stSearch" class="mb-0">Search:</label>
                        <input type="search" id="stSearch" class="form-control form-control-sm rounded-pill"
                               style="max-width:220px;" placeholder="email or username">
                    </div>
                </div>

                <?php if (!$rows): ?>
                    <div class="text-center text-body-secondary py-5">
                        <i class='bx bx-hdd fs-1 d-block mb-2 opacity-50'></i>
                        Nothing audited yet — press <strong>Re-audit usage</strong> to measure the pool.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small" id="stTable">
                        <thead class="table-dark opacity-75">
                            <tr>
                                <th class="ps-4">Pool</th>
                                <th>Owner</th><th>Plan</th>
                                <th class="text-end">Used</th><th class="text-end">Limit</th>
                                <th style="min-width:150px;">Usage</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr class="adm-row" data-email="<?= htmlspecialchars($r['email']) ?>">
                                <td class="ps-4"><i class='bx bx-hdd me-1 text-body-secondary'></i><?= htmlspecialchars($pool['label']) ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($r['email']) ?></td>
                                <td><span class="adm-chip"><?= htmlspecialchars(storage_plan_label($r['plan'])) ?></span></td>
                                <td class="text-end text-nowrap"><?= storage_format_bytes($r['bytes']) ?></td>
                                <td class="text-end text-nowrap">
                                    <?= storage_format_bytes($r['limit']) ?>
                                    <?php if (!empty($r['custom'])): ?><span class="adm-chip st-custom ms-1" title="Manual cap — not the plan default">custom</span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="adm-pct<?= $r['over'] ? ' over' : '' ?>"><i style="width:<?= (int)min(100, $r['pct']) ?>%"></i></div>
                                    <span class="<?= $r['over'] ? 'text-danger' : 'text-body-secondary' ?>" style="font-size:.7rem;">
                                        <?= number_format($r['pct'], 1) ?>%<?= $r['missing'] ? ' · no dir' : '' ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end text-nowrap">
                                    <a href="/admin/user/<?= rawurlencode($r['email']) ?>" class="st-act" title="Open profile"><i class='bx bx-search-alt'></i></a>
                                    <button class="st-act st-imp" data-email="<?= htmlspecialchars($r['email']) ?>" title="Impersonate"><i class='bx bx-user-circle'></i></button>
                                    <button class="st-act st-xfer" data-email="<?= htmlspecialchars($r['email']) ?>" title="Transfer entitlements"><i class='bx bx-transfer'></i></button>
                                    <button class="st-act st-limit<?= !empty($r['custom']) ? ' is-custom' : '' ?>"
                                            data-email="<?= htmlspecialchars($r['email']) ?>"
                                            data-limit="<?= (int)$r['limit'] ?>"
                                            data-bytes="<?= (int)$r['bytes'] ?>"
                                            data-custom="<?= !empty($r['custom']) ? '1' : '0' ?>"
                                            title="Edit storage limit"><i class='bx bx-edit-alt'></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top border-body-secondary border-opacity-10 small text-body-secondary">
                    <span id="stShowing">Showing 0 to 0 of 0 entries</span>
                    <div class="btn-group">
                        <button class="adm-chip" id="stPrev">‹ Prev</button>
                        <button class="adm-chip" id="stNext">Next ›</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================ DASHBOARD tab ============================ -->
    <div class="st-panel" data-panel="dash" style="display:none;">
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['Pool capacity', storage_format_bytes((int)$pool['total_bytes']), 'bx-server', 'text-primary'],
                ['Pool used',     storage_format_bytes((int)$pool['used_bytes']), 'bx-hdd', 'text-warning'],
                ['Tenant usage',  storage_format_bytes($totalUsed), 'bx-folder', 'text-info'],
                ['Allocated limits', storage_format_bytes($limitBytes), 'bx-lock-alt', 'text-secondary'],
                ['Audited tenants', number_format($tenants), 'bx-group', 'text-primary'],
                ['Over quota',    number_format(count($overRows)), 'bx-error-circle', count($overRows) ? 'text-danger' : 'text-success'],
            ] as $t): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="adm-stat rounded-4 p-3 h-100">
                    <div class="small text-body-secondary mb-1"><i class='bx <?= $t[2] ?> <?= $t[3] ?> me-1'></i><?= $t[0] ?></div>
                    <div class="fs-5 fw-bold text-truncate"><?= htmlspecialchars($t[1]) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 rounded-4 blur shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                        <h6 class="mb-0 fw-bold">Top consumers</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!$topConsumers): ?>
                            <div class="text-body-secondary small">No usage recorded yet.</div>
                        <?php else: foreach ($topConsumers as $r): ?>
                            <div class="d-flex align-items-center gap-3 py-2 border-bottom border-body-secondary border-opacity-10">
                                <div class="flex-grow-1">
                                    <div class="small text-truncate"><?= htmlspecialchars($r['email']) ?></div>
                                    <div class="adm-pct mt-1" style="height:6px;"><i style="width:<?= (int)min(100, $r['bytes'] / $maxTop * 100) ?>%"></i></div>
                                </div>
                                <div class="text-end text-nowrap small" style="min-width:6.5rem;">
                                    <div class="fw-semibold"><?= storage_format_bytes($r['bytes']) ?></div>
                                    <div class="<?= $r['over'] ? 'text-danger' : 'text-body-secondary' ?>" style="font-size:.7rem;">of <?= storage_format_bytes($r['limit']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 rounded-4 blur shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                        <h6 class="mb-0 fw-bold">Plan limits</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach (STORAGE_PLAN_LIMITS as $k => $lim): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-body-secondary border-opacity-10">
                            <span class="adm-chip"><?= htmlspecialchars(storage_plan_label($k)) ?></span>
                            <span class="small fw-semibold"><?= storage_format_bytes($lim) ?> per tenant</span>
                        </div>
                        <?php endforeach; ?>
                        <div class="small text-body-secondary mt-3">
                            Limits are enforced by the audit: any tenant whose measured usage exceeds its plan
                            appears under <a href="#" class="st-goto" data-tab="over">Over quota</a>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================== TOP CONSUMERS tab =========================== -->
    <div class="st-panel" data-panel="top" style="display:none;">
        <div class="card border-0 rounded-4 blur shadow-sm">
            <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                <h6 class="mb-0 fw-bold">Top consumers by measured bytes</h6>
            </div>
            <div class="card-body p-0">
                <?php if (!$rows): ?>
                    <div class="text-center text-body-secondary py-5"><i class='bx bx-bar-chart fs-1 d-block mb-2 opacity-50'></i>No usage recorded yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark opacity-75">
                            <tr><th class="ps-4">#</th><th>Owner</th><th>Plan</th>
                                <th class="text-end">Used</th><th class="text-end">Limit</th>
                                <th style="min-width:160px;">Usage</th><th class="pe-4 text-end">Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($rows, 0, 50) as $i => $r): ?>
                            <tr>
                                <td class="ps-4 text-body-secondary"><?= $i + 1 ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($r['email']) ?></td>
                                <td><span class="adm-chip"><?= htmlspecialchars(storage_plan_label($r['plan'])) ?></span></td>
                                <td class="text-end text-nowrap"><?= storage_format_bytes($r['bytes']) ?></td>
                                <td class="text-end text-nowrap"><?= storage_format_bytes($r['limit']) ?></td>
                                <td><div class="adm-pct<?= $r['over'] ? ' over' : '' ?>"><i style="width:<?= (int)min(100, $r['pct']) ?>%"></i></div></td>
                                <td class="pe-4 text-end">
                                    <?php if ($r['over']): ?><span class="adm-chip" style="border-color:rgba(255,90,90,.6);color:#ff6b6b;">over</span>
                                    <?php else: ?><span class="adm-chip" style="border-color:rgba(46,204,113,.5);color:#2ecc71;">ok</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================== OVER QUOTA tab ============================== -->
    <div class="st-panel" data-panel="over" style="display:none;">
        <div class="card border-0 rounded-4 blur shadow-sm">
            <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Tenants over their storage limit</h6>
                <span class="adm-chip"><?= count($overRows) ?> over quota</span>
            </div>
            <div class="card-body p-0">
                <?php if (!$overRows): ?>
                    <div class="text-center text-body-secondary py-5">
                        <i class='bx bx-check-shield fs-1 d-block mb-2 opacity-50'></i>
                        No tenant is over its limit right now.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark opacity-75">
                            <tr><th class="ps-4">Owner</th><th>Plan</th>
                                <th class="text-end">Used</th><th class="text-end">Limit</th>
                                <th class="text-end pe-4">Over by</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($overRows as $r): ?>
                            <tr>
                                <td class="ps-4"><?= htmlspecialchars($r['email']) ?></td>
                                <td><span class="adm-chip"><?= htmlspecialchars(storage_plan_label($r['plan'])) ?></span></td>
                                <td class="text-end text-nowrap text-danger"><?= storage_format_bytes($r['bytes']) ?></td>
                                <td class="text-end text-nowrap"><?= storage_format_bytes($r['limit']) ?></td>
                                <td class="text-end text-nowrap pe-4 fw-semibold text-danger"><?= storage_format_bytes($r['bytes'] - $r['limit']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================ ORGANIZATIONS tab ============================ -->
    <div class="st-panel" data-panel="orgs" style="display:none;">
        <div class="adm-empty">
            <i class='bx bx-buildings fs-1 d-block mb-2 opacity-50'></i>
            <div class="fw-semibold mb-1">No organizations yet</div>
            <div class="small text-body-secondary" style="max-width:34rem;margin:0 auto;">
                Organization-level quotas appear here once orgs exist. Tenants are currently
                provisioned individually on the default pool.
            </div>
        </div>
    </div>

    <!-- ============================= MIGRATIONS tab ============================= -->
    <div class="st-panel" data-panel="mig" style="display:none;">
        <div class="adm-empty">
            <i class='bx bx-transfer fs-1 d-block mb-2 opacity-50'></i>
            <div class="fw-semibold mb-1">No migrations in flight</div>
            <div class="small text-body-secondary" style="max-width:34rem;margin:0 auto;">
                Moving a tenant between pools is not available yet — every tenant stays on
                <code><?= htmlspecialchars($pool['label']) ?></code> for now.
            </div>
        </div>
    </div>
</div>

<!-- ============================ Storage limit modal ============================ -->
<div class="modal fade" id="stLimitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 blur shadow-lg">
            <div class="modal-header border-bottom border-body-secondary border-opacity-10">
                <h5 class="modal-title fw-bold"><i class='bx bx-edit-alt me-2 text-warning'></i>Storage limit</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1" for="slEmail">Tenant</label>
                    <input type="text" id="slEmail" class="form-control bg-transparent border-secondary border-opacity-25" disabled>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="slUsed">Used now</label>
                        <input type="text" id="slUsed" class="form-control bg-transparent border-secondary border-opacity-25" disabled>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="slCurrent">Current cap</label>
                        <input type="text" id="slCurrent" class="form-control bg-transparent border-secondary border-opacity-25" disabled>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-body-secondary mb-1" for="slGb">New limit (GB)</label>
                    <div class="input-group">
                        <input type="number" id="slGb" min="0.1" step="0.1" class="form-control bg-transparent border-secondary border-opacity-25">
                        <span class="input-group-text border-secondary border-opacity-25 bg-transparent text-body-secondary">GB</span>
                    </div>
                    <div class="form-text">Caps apply from the next audit. Use <strong>Reset to plan</strong> to fall back to the plan default.</div>
                </div>
            </div>
            <div class="modal-footer border-top border-body-secondary border-opacity-10 d-flex justify-content-between">
                <button type="button" id="slReset" class="btn btn-sm btn-outline-secondary rounded-pill">Reset to plan</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-coreui-dismiss="modal">Cancel</button>
                    <button type="button" id="slSave" class="btn btn-sm btn-warning fw-semibold rounded-pill px-3">Save limit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ------------------------------------------------------------- tabs */
    function show(name) {
        $$('.st-panel').forEach(function (p) { p.style.display = (p.dataset.panel === name) ? 'block' : 'none'; });
        $$('.adm-tab').forEach(function (t) { t.classList.toggle('active', t.dataset.tab === name); });
        if (history.replaceState) history.replaceState(null, '', location.pathname + '?tab=' + name);
    }
    $$('.adm-tab, .st-goto').forEach(function (t) {
        t.addEventListener('click', function (e) { e.preventDefault(); show(t.dataset.tab); });
    });
    var initial = new URLSearchParams(location.search).get('tab');
    if (initial && $('.st-panel[data-panel="' + initial + '"]')) show(initial);

    /* ------------------------------------------------------- re-audit */
    var btn = $('#stReaudit');
    if (btn) btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class=\'bx bx-loader-circle bx-spin me-1\'></i>Auditing…';
        fetch('/api/admin/audit_storage', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: 'x=1'
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.status === 'success') { location.reload(); return; }
            btn.disabled = false;
            btn.innerHTML = '<i class=\'bx bx-refresh me-1\'></i>Re-audit usage';
            if (window.TomNotify) TomNotify.show(d.error || 'Audit failed', 'Error', 'error', 4000);
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class=\'bx bx-refresh me-1\'></i>Re-audit usage';
        });
    });

    /* ------------------------------------------------- table search/paging */
    var table = $('#stTable');
    if (table) {
        var page = 0, size = 25;
        var rows = $$('tbody tr', table);
        function apply() {
            var q = ($('#stSearch').value || '').toLowerCase();
            var hits = rows.filter(function (r) {
                return (r.dataset.email || '').toLowerCase().indexOf(q) !== -1 || r.innerText.toLowerCase().indexOf(q) !== -1;
            });
            size = parseInt($('#stPageSize').value, 10) || 25;
            var start = page * size, end = Math.min(hits.length, start + size);
            rows.forEach(function (r) { r.style.display = 'none'; });
            hits.slice(start, end).forEach(function (r) { r.style.display = ''; });
            $('#stShowing').textContent = hits.length
                ? 'Showing ' + (start + 1) + ' to ' + end + ' of ' + hits.length + ' entries'
                : 'Showing 0 entries';
            $('#stCount').textContent = hits.length;
        }
        $('#stSearch').addEventListener('input', function () { page = 0; apply(); });
        $('#stPageSize').addEventListener('change', function () { page = 0; apply(); });
        $('#stPrev').addEventListener('click', function () { if (page > 0) { page--; apply(); } });
        $('#stNext').addEventListener('click', function () { page++; apply(); });
        apply();
    }

    /* ---------------------------------------------------------------- csv */
    var csv = $('#stCsv');
    if (csv) csv.addEventListener('click', function () {
        var lines = ['pool,owner,plan,used_bytes,limit,usage'];
        $$('#stTable tbody tr').forEach(function (r) {
            var c = r.querySelectorAll('td');
            if (!c.length || r.style.display === 'none') return;
            lines.push(['default', r.dataset.email, c[2].innerText.trim(), c[3].innerText.trim(),
                        c[4].innerText.trim(), c[5].innerText.trim().split('\n')[0]].join(','));
        });
        var blob = new Blob([lines.join('\n')], { type: 'text/csv' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'storage-map.csv';
        a.click();
    });

    /* -------------------------------------------------------- row actions */
    $$('.st-imp').forEach(function (b) {
        b.addEventListener('click', function () {
            fetch('/api/admin/impersonate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
                body: 'email=' + encodeURIComponent(b.dataset.email)
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d.status === 'success') { location.href = d.redirect || '/home'; return; }
                if (window.TomNotify) TomNotify.show(d.error || 'Could not impersonate', 'Error', 'error', 4000);
            }).catch(function () {
                if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
            });
        });
    });
    $$('.st-xfer').forEach(function (b) {
        b.addEventListener('click', function () {
            location.href = '/admin/user/' + encodeURIComponent(b.dataset.email);
        });
    });

    /* ------------------------------------------------------- limit editor */
    var limitModalEl = $('#stLimitModal');
    var limitEmail   = '';
    var toast = function (m, t, ty) {
        if (window.TomNotify) TomNotify.show(m, t, ty || 'success', 4000);
    };

    function fmtBytes(b) {
        var u = ['B', 'KB', 'MB', 'GB', 'TB'], v = +b, i = 0;
        while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
        var d = (i === 0 || v >= 100) ? 0 : (v >= 10 ? 1 : 2);
        return (Math.round(v * Math.pow(10, d)) / Math.pow(10, d)) + ' ' + u[i];
    }

    function postLimit(payload) {
        return fetch('/api/admin/set_storage_limit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
            body: new URLSearchParams(payload).toString()
        }).then(function (r) { return r.json(); });
    }

    $$('.st-limit').forEach(function (b) {
        b.addEventListener('click', function () {
            limitEmail = b.dataset.email;
            $('#slEmail').value    = limitEmail;
            $('#slUsed').value     = fmtBytes(+b.dataset.bytes);
            $('#slCurrent').value  = (b.dataset.custom === '1' ? 'Custom · ' : '') + fmtBytes(+b.dataset.limit);
            $('#slGb').value       = b.dataset.custom === '1' ? (+b.dataset.limit / 1073741824) : '';
            new coreui.Modal(limitModalEl).show();
        });
    });

    $('#slSave')?.addEventListener('click', function () {
        var gb = parseFloat($('#slGb').value);
        if (!limitEmail || !(gb > 0)) { toast('Enter a limit greater than 0', 'Error', 'error'); return; }
        var btn = this; btn.disabled = true;
        postLimit({ email: limitEmail, limit_gb: gb }).then(function (d) {
            btn.disabled = false;
            if (d.status !== 'success') { toast(d.error || 'Failed to update limit', 'Error', 'error'); return; }
            coreui.Modal.getInstance(limitModalEl)?.hide();
            toast('Storage limit set to ' + d.label, 'Limit saved');
            setTimeout(function () { location.reload(); }, 700);
        }).catch(function () { btn.disabled = false; toast('Network error', 'Error', 'error'); });
    });

    $('#slReset')?.addEventListener('click', function () {
        if (!limitEmail) return;
        var btn = this; btn.disabled = true;
        postLimit({ email: limitEmail, reset: '1' }).then(function (d) {
            btn.disabled = false;
            if (d.status !== 'success') { toast(d.error || 'Failed to reset limit', 'Error', 'error'); return; }
            coreui.Modal.getInstance(limitModalEl)?.hide();
            toast('Storage limit reset to the plan default', 'Limit reset');
            setTimeout(function () { location.reload(); }, 700);
        }).catch(function () { btn.disabled = false; toast('Network error', 'Error', 'error'); });
    });

    var addPool = $('#stAddPool');
    if (addPool) addPool.addEventListener('click', function () {
        if (window.TomNotify) TomNotify.show('Adding pools is not wired to a backend yet.', 'Coming soon', 'info', 4000);
    });
})();
</script>
