<?php
require_once __DIR__ . '/../../../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();

$fmtWhen = function ($v) {
    if ($v === null) return '—';
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y H:i');
    if (is_int($v) || is_float($v)) return (new DateTimeImmutable('@' . (string)(int)$v))->format('d M Y H:i');
    if (!is_scalar($v) && !($v instanceof Stringable)) return '—';
    try { return (new DateTimeImmutable((string)$v))->format('d M Y H:i'); } catch (Throwable $e) { return (string)$v; }
};
$ago = function ($v) {
    $dt = null;
    if ($v instanceof \MongoDB\BSON\UTCDateTime) $dt = $v->toDateTime();
    elseif ($v instanceof DateTimeInterface) $dt = $v;
    elseif (is_int($v) || is_float($v)) $dt = new DateTimeImmutable('@' . (string)(int)$v);
    elseif (is_string($v) && $v !== '') { try { $dt = new DateTimeImmutable($v); } catch (Throwable $e) {} }
    if (!$dt) return '—';
    $sec = time() - $dt->getTimestamp();
    if ($sec < 60) return max(0, $sec) . 's ago';
    if ($sec < 3600) return intdiv($sec, 60) . 'm ago';
    if ($sec < 86400) return intdiv($sec, 3600) . 'h ago';
    if ($sec < 2592000) return intdiv($sec, 86400) . 'd ago';
    return $dt->format('d M Y');
};

/* Secrets (credentials, deploy logs, keys) are deliberately never projected. */
$projection = [
    'instance_hash' => 1, 'lab_type' => 1, 'status' => 1, 'username' => 1, 'user_id' => 1,
    'email' => 1, 'docker_ip' => 1, 'tunnel_ip' => 1, 'internal_ip' => 1, 'domains' => 1,
    'expose_web' => 1, 'created_at' => 1, 'updated_at' => 1, 'last_error' => 1,
    'deploy.status' => 1, 'deploy.last_error' => 1, 'deploy_history' => ['$slice' => 1],
];

$instances = [];
$queue = [];
$byStatus = ['running' => 0, 'deploying' => 0, 'stopped' => 0, 'failed' => 0, 'other' => 0];
$queueActive = 0;
try {
    foreach ($db->machine_labs->find([], ['projection' => $projection, 'sort' => ['created_at' => -1]]) as $i) {
        $instances[] = $i;
        $s = strtolower((string)($i['status'] ?? ''));
        if (in_array($s, ['running', 'active'], true))        $byStatus['running']++;
        elseif (in_array($s, ['deploying', 'queued', 'processing'], true)) $byStatus['deploying']++;
        elseif (in_array($s, ['stopped', 'not_deployed'], true))           $byStatus['stopped']++;
        elseif (in_array($s, ['error', 'failed'], true))                   $byStatus['failed']++;
        else $byStatus['other']++;
    }
    foreach ($db->deploy_queue->find([], ['sort' => ['created_at' => -1], 'limit' => 50]) as $q) {
        $queue[] = $q;
        if (in_array(strtolower((string)($q['status'] ?? '')), ['queued', 'processing'], true)) $queueActive++;
    }
} catch (Throwable $e) { error_log('instances: ' . $e->getMessage()); }

$statusCls = function ($s) {
    $s = strtolower((string)$s);
    if (in_array($s, ['running', 'active'], true)) return 'success';
    if (in_array($s, ['deploying', 'queued', 'processing'], true)) return 'info';
    if (in_array($s, ['error', 'failed'], true)) return 'danger';
    return 'secondary';
};
?>

<style>
.inst-row:hover { background: rgba(var(--cui-body-bg-rgb, 11,30,54), .35); }
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
                                <i class='bx bx-server'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Instances</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Lab instances and the deploy queue — read only</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                    <span class="badge rounded-pill text-bg-<?= $queueActive ? 'warning' : 'secondary' ?>">
                        <?= (int)$queueActive ?> in queue
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <!-- ========================== Fleet tiles ========================== -->
    <div class="row g-3 mb-4">
        <?php
        $tiles = [
            ['Instances', count($instances), 'bx-server', 'text-primary'],
            ['Running',   $byStatus['running'],  'bx-play-circle', 'text-success'],
            ['Deploying', $byStatus['deploying'], 'bx-loader-circle', 'text-info'],
            ['Stopped',   $byStatus['stopped'],  'bx-pause-circle', 'text-secondary'],
            ['Failed',    $byStatus['failed'],   'bx-error-circle', 'text-danger'],
            ['Queued',    $queueActive,          'bx-time-five', 'text-warning'],
        ];
        foreach ($tiles as $t): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx <?= $t[2] ?> <?= $t[3] ?> me-1'></i><?= $t[0] ?></div>
                <div class="fs-4 fw-bold"><?= (int)$t[1] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ========================== Instances ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
            <h5 class="mb-0 fw-bold"><i class='bx bx-server me-2 text-primary'></i>Lab instances</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!$instances): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class='bx bx-server fs-1 d-block mb-2'></i>No instances have been provisioned yet.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark opacity-75">
                        <tr>
                            <th class="ps-4">Instance</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Owner</th>
                            <th>Addresses</th>
                            <th>Domains</th>
                            <th>Last deploy</th>
                            <th class="pe-4">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($instances as $i):
                        $hash = (string)($i['instance_hash'] ?? '');
                        $hist = (array)($i['deploy_history'] ?? []);
                        $last = $hist[0] ?? null;
                        $dStatus = $last['status'] ?? (($i['deploy']['status'] ?? '') ?: '—');
                        $dAction = $last['action'] ?? '';
                        $doms = (array)($i['domains'] ?? []);
                    ?>
                        <tr class="inst-row">
                            <td class="ps-4">
                                <code class="fw-semibold"><?= htmlspecialchars(substr($hash, 0, 12)) ?>…</code>
                                <?php if (!empty($i['expose_web'])): ?><span class="badge rounded-pill text-bg-info ms-1">web</span><?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= htmlspecialchars((string)($i['lab_type'] ?? '—')) ?></td>
                            <td><span class="badge rounded-pill text-bg-<?= $statusCls($i['status'] ?? '') ?>"><?= htmlspecialchars((string)($i['status'] ?? '—')) ?></span>
                                <?php if (!empty($i['last_error'])): ?>
                                    <i class='bx bx-error-circle text-danger ms-1' title="<?= htmlspecialchars((string)$i['last_error']) ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?= htmlspecialchars((string)($i['username'] ?? '—')) ?>
                                <div class="small text-body-secondary opacity-75"><?= htmlspecialchars((string)($i['email'] ?? '')) ?></div>
                            </td>
                            <td class="text-nowrap text-body-secondary">
                                <?php if (!empty($i['docker_ip'])): ?><div><i class='bx bx-wifi small'></i> <?= htmlspecialchars((string)$i['docker_ip']) ?></div><?php endif; ?>
                                <?php if (!empty($i['tunnel_ip'])): ?><div><i class='bx bx-network-chart small'></i> <?= htmlspecialchars((string)$i['tunnel_ip']) ?></div><?php endif; ?>
                            </td>
                            <td class="text-body-secondary" style="max-width:14rem;">
                                <?php foreach (array_slice($doms, 0, 2) as $d): ?><div class="text-truncate" style="max-width:13rem;"><?= htmlspecialchars((string)$d) ?></div><?php endforeach; ?>
                                <?php if (count($doms) > 2): ?><div class="small opacity-75">+<?= count($doms) - 2 ?> more</div><?php endif; ?>
                                <?php if (!$doms): ?><span class="text-body-secondary opacity-50">—</span><?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($last): ?>
                                    <span class="badge rounded-pill text-bg-<?= ((string)($dStatus ?? '')) === 'success' ? 'success' : (((string)($dStatus ?? '')) === 'failed' ? 'danger' : 'secondary') ?>"><?= htmlspecialchars((string)$dStatus) ?></span>
                                    <div class="small text-body-secondary"><?= htmlspecialchars((string)($dAction ?: '')) ?><?= !empty($last['duration']) ? ' · ' . htmlspecialchars((string)$last['duration']) : '' ?></div>
                                <?php else: ?>
                                    <span class="text-body-secondary opacity-50">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-nowrap text-body-secondary"><?= htmlspecialchars($ago($i['created_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================== Deploy queue ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-time-five me-2 text-warning'></i>Deploy queue</h5>
            <span class="small text-body-secondary"><?= (int)$queueActive ?> active of <?= (int)count($queue) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (!$queue): ?>
                <div class="text-center text-body-secondary py-4">The deploy queue is empty.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark opacity-75">
                        <tr>
                            <th class="ps-4">Queued</th>
                            <th>Instance</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Retries</th>
                            <th class="pe-4">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $q):
                        $qs = strtolower((string)($q['status'] ?? ''));
                        $qc = in_array($qs, ['processing', 'queued'], true) ? 'warning'
                            : (in_array($qs, ['cancelled', 'failed', 'error'], true) ? 'danger' : 'success');
                    ?>
                        <tr class="inst-row">
                            <td class="ps-4 text-nowrap text-body-secondary"><?= htmlspecialchars($fmtWhen($q['created_at'] ?? null)) ?></td>
                            <td><code class="small"><?= htmlspecialchars(substr((string)($q['instance_hash'] ?? ''), 0, 12)) ?>…</code></td>
                            <td class="text-nowrap"><?= htmlspecialchars((string)($q['lab_type'] ?? '—')) ?></td>
                            <td><code class="small"><?= htmlspecialchars((string)($q['reason'] ?? '—')) ?></code></td>
                            <td><span class="badge rounded-pill text-bg-<?= $qc ?>"><?= htmlspecialchars((string)($q['status'] ?? '—')) ?></span></td>
                            <td class="text-body-secondary"><?= (int)($q['retries'] ?? 0) ?> / <?= (int)($q['max_retries'] ?? 0) ?></td>
                            <td class="pe-4 text-body-secondary" style="max-width:18rem;"><?= htmlspecialchars(mb_substr((string)($q['error'] ?? ''), 0, 120)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
