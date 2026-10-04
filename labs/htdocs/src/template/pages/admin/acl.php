<?php
require_once __DIR__ . '/../../../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();

$fmtWhen = function ($v) {
    if ($v === null) return '';
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y H:i');
    if (is_int($v) || is_float($v)) return (new DateTimeImmutable('@' . (string)(int)$v))->format('d M Y H:i');
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

$entries = [];
$total = 0;
try {
    $total = $db->audit_log->countDocuments([]);
    foreach ($db->audit_log->find([], ['sort' => ['created_at' => -1], 'limit' => 500]) as $row) $entries[] = $row;
} catch (Throwable $e) { error_log('acl: ' . $e->getMessage()); }

$byAction = [];
$byEntity = [];
$actors = [];
foreach ($entries as $e2) {
    $a = (string)($e2['action'] ?? 'unknown');
    $t = (string)($e2['entity_type'] ?? 'unknown');
    $byAction[$a] = ($byAction[$a] ?? 0) + 1;
    $byEntity[$t] = ($byEntity[$t] ?? 0) + 1;
    $actors[(string)($e2['user_id'] ?? '')] = true;
}
ksort($byAction); ksort($byEntity);
$actorCount = count(array_filter(array_keys($actors), fn($k) => $k !== ''));

$actionCls = function ($a) {
    $a = strtolower((string)$a);
    if (str_contains($a, 'delete') || str_contains($a, 'revoke') || str_contains($a, 'destroy')) return 'danger';
    if (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'change')) return 'warning';
    if (str_contains($a, 'create') || str_contains($a, 'add')) return 'success';
    return 'secondary';
};
?>

<style>
.acl-row:hover { background: rgba(var(--cui-body-bg-rgb, 11,30,54), .35); }
.acl-filter { min-width: 10rem; }
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
                                <i class='bx bx-shield-quarter'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Access Control</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Who did what, where, and how it was recorded</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                    <span class="badge rounded-pill text-bg-secondary"><?= (int)$total ?> events</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <!-- ========================== Summary ========================== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx bx-history me-1 text-primary'></i>Recorded</div>
                <div class="fs-4 fw-bold"><?= (int)$total ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx bx-user me-1 text-info'></i>Actors</div>
                <div class="fs-4 fw-bold"><?= (int)$actorCount ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx bx-error-circle me-1 text-danger'></i>Destructive</div>
                <div class="fs-4 fw-bold"><?= (int)(($byAction['delete'] ?? 0) + ($byAction['revoke'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx bx-layer me-1 text-warning'></i>Resources</div>
                <div class="fs-4 fw-bold"><?= (int)count($byEntity) ?></div>
            </div>
        </div>
    </div>

    <!-- ========================== Filters ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
            <h5 class="mb-0 fw-bold"><i class='bx bx-filter-alt me-2 text-primary'></i>Filters</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-body-secondary mb-1" for="aclAction">Event</label>
                    <select id="aclAction" class="form-select form-select-sm acl-filter bg-transparent border-secondary border-opacity-25">
                        <option value="">All events</option>
                        <?php foreach ($byAction as $a => $n): ?>
                            <option value="<?= htmlspecialchars(strtolower($a)) ?>"><?= htmlspecialchars($a) ?> (<?= (int)$n ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-body-secondary mb-1" for="aclEntity">Resource</label>
                    <select id="aclEntity" class="form-select form-select-sm acl-filter bg-transparent border-secondary border-opacity-25">
                        <option value="">All resources</option>
                        <?php foreach ($byEntity as $t => $n): ?>
                            <option value="<?= htmlspecialchars(strtolower($t)) ?>"><?= htmlspecialchars($t) ?> (<?= (int)$n ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label small text-body-secondary mb-1" for="aclSearch">Search actor, subject, change or source</label>
                    <input id="aclSearch" type="search" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                           placeholder="e.g. 1001, vpn_device, 192.168…" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <button type="button" id="aclReset" class="btn btn-sm btn-outline-secondary w-100">Reset</button>
                </div>
            </div>
            <div class="small text-body-secondary mt-3" id="aclCount">
                Showing <?= count($entries) ?> of <?= (int)$total ?> events
            </div>
        </div>
    </div>

    <!-- ========================== Audit trail ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-table me-2 text-secondary'></i>Audit trail</h5>
            <span class="small text-body-secondary">newest first</span>
        </div>
        <div class="card-body p-0">
            <?php if (!$entries): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class='bx bx-shield-quarter fs-1 d-block mb-2'></i>
                    No audit events recorded yet. Admin actions will appear here as they happen.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small" id="aclTable">
                        <thead class="table-dark opacity-75">
                            <tr>
                                <th class="ps-4">When</th>
                                <th>Actor</th>
                                <th>Event</th>
                                <th>Resource</th>
                                <th>Subject</th>
                                <th>Change</th>
                                <th class="pe-4">Via</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $e2):
                            $action = (string)($e2['action'] ?? '');
                            $entity = (string)($e2['entity_type'] ?? '');
                            $subject = (string)($e2['entity_id'] ?? '');
                            $actor = (string)($e2['user_id'] ?? '—');
                            $when = $fmtWhen($e2['created_at'] ?? null);
                            $change = $fmtDetail($e2['details'] ?? null);
                            $method = (string)($e2['request_method'] ?? '');
                            $uri = (string)($e2['request_uri'] ?? '');
                            $ip = (string)($e2['ip_address'] ?? '');
                            $haystack = strtolower($actor . ' ' . $subject . ' ' . $change . ' ' . $ip . ' ' . $uri . ' ' . $entity . ' ' . $action);
                        ?>
                            <tr class="acl-row"
                                data-action="<?= htmlspecialchars(strtolower($action)) ?>"
                                data-entity="<?= htmlspecialchars(strtolower($entity)) ?>"
                                data-hay="<?= htmlspecialchars($haystack) ?>">
                                <td class="ps-4 text-body-secondary text-nowrap"><?= htmlspecialchars($when) ?></td>
                                <td><code><?= htmlspecialchars($actor) ?></code></td>
                                <td><span class="badge rounded-pill text-bg-<?= $actionCls($action) ?>"><?= htmlspecialchars($action ?: '—') ?></span></td>
                                <td><?= htmlspecialchars($entity ?: '—') ?></td>
                                <td><code class="small"><?= htmlspecialchars($subject ?: '—') ?></code></td>
                                <td class="text-body-secondary" style="max-width:22rem;">
                                    <?= htmlspecialchars(mb_substr($change, 0, 120)) ?><?= mb_strlen($change) > 120 ? '…' : '' ?>
                                </td>
                                <td class="pe-4 text-nowrap text-body-secondary">
                                    <?php if ($method || $uri): ?><code class="small"><?= htmlspecialchars($method) ?></code> <span class="small"><?= htmlspecialchars($uri) ?></span><?php endif; ?>
                                    <?php if ($ip): ?><div class="small text-body-secondary opacity-75"><?= htmlspecialchars($ip) ?></div><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="aclNoMatch" class="text-center text-body-secondary py-4 d-none">No events match these filters.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const action = document.getElementById('aclAction');
    const entity = document.getElementById('aclEntity');
    const search = document.getElementById('aclSearch');
    const reset  = document.getElementById('aclReset');
    const count  = document.getElementById('aclCount');
    const noHit  = document.getElementById('aclNoMatch');
    const table  = document.getElementById('aclTable');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const total = rows.length;

    function apply() {
        const a = (action && action.value) || '';
        const e = (entity && entity.value) || '';
        const q = ((search && search.value) || '').trim().toLowerCase();
        let shown = 0;
        rows.forEach(function (tr) {
            let ok = true;
            if (a && tr.dataset.action !== a) ok = false;
            if (ok && e && tr.dataset.entity !== e) ok = false;
            if (ok && q && (tr.dataset.hay || '').indexOf(q) === -1) ok = false;
            tr.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (count) count.textContent = 'Showing ' + shown + ' of ' + total + ' events';
        if (noHit) noHit.classList.toggle('d-none', shown !== 0);
    }

    [action, entity].forEach(function (el) { if (el) el.addEventListener('change', apply); });
    if (search) search.addEventListener('input', apply);
    if (reset) reset.addEventListener('click', function () {
        if (action) action.value = '';
        if (entity) entity.value = '';
        if (search) search.value = '';
        apply();
    });
})();
</script>
