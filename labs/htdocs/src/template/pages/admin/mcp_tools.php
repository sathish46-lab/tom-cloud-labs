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
    if ($v === null) return 'never';
    $dt = ($v instanceof \MongoDB\BSON\UTCDateTime) ? $v->toDateTime()
        : ($v instanceof DateTimeInterface ? $v : null);
    if (!$dt) return 'unknown';
    $sec = time() - $dt->getTimestamp();
    if ($sec < 60) return $sec . 's ago';
    if ($sec < 3600) return intdiv($sec, 60) . 'm ago';
    if ($sec < 86400) return intdiv($sec, 3600) . 'h ago';
    if ($sec < 2592000) return intdiv($sec, 86400) . 'd ago';
    return $dt->format('d M Y');
};
$counts = ['clients' => 0, 'tokens' => 0, 'grants' => 0, 'codes' => 0, 'conns' => 0, 'txns' => 0, 'calls' => 0, 'errors' => 0];
$clients = [];
$calls = [];
$switches = [];
try {
    $counts['clients'] = $db->mcp_clients->countDocuments([]);
    $counts['tokens']  = $db->mcp_tokens->countDocuments([]);
    $counts['grants']  = $db->mcp_grants->countDocuments([]);
    $counts['codes']   = $db->mcp_auth_codes->countDocuments([]);
    $counts['conns']   = $db->mcp_connections->countDocuments([]);
    $counts['txns']    = $db->mcp_transactions->countDocuments([]);
    $counts['calls']   = $db->mcp_activity->countDocuments([]);
    $counts['errors']  = $db->mcp_activity->countDocuments(['$or' => [['status' => ['$ne' => 'ok']], ['response.isError' => true]]]);
    foreach ($db->mcp_clients->find([], ['sort' => ['created_at' => -1], 'limit' => 200]) as $c) $clients[] = $c;
    foreach ($db->mcp_activity->find([], ['sort' => ['created_at' => -1], 'limit' => 25]) as $a) $calls[] = $a;
    foreach (['master_switches', 'lab_features', 'mcp_settings'] as $sid) {
        $doc = $db->global_settings->findOne(['_id' => $sid]);
        foreach ((array)$doc as $k => $v) {
            if ($k !== '_id' && is_bool($v)) $switches[$sid . '.' . $k] = $v;
        }
    }
} catch (Throwable $e) { error_log('mcp: ' . $e->getMessage()); }
$activeClients = 0;
foreach ($clients as $c) if (empty($c['revoked'])) $activeClients++;
$switchBadge = function ($on) {
    return $on
        ? '<span class="badge rounded-pill text-bg-success"><i class="bx bx-check-circle me-1"></i>On</span>'
        : '<span class="badge rounded-pill text-bg-secondary"><i class="bx bx-x-circle me-1"></i>Off</span>';
};
?>
<style>
.mcp-row:hover { background: rgba(var(--cui-body-bg-rgb, 11,30,54), .35); }
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
                                <i class='bx bx-bot'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">MCP Tools</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">OAuth clients, grants and tool invocations</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                    <span class="badge rounded-pill text-bg-<?= !empty($switches['master_switches.mcp']) ? 'success' : 'secondary' ?>">
                        Platform MCP <?= !empty($switches['master_switches.mcp']) ? 'on' : 'off' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container-fluid px-4">
    <!-- ========================== Summary ========================== -->
    <div class="row g-3 mb-4">
        <?php
        $tiles = [
            ['Clients',       $counts['clients'], 'bx-plug',     'text-primary'],
            ['Active tokens', $counts['tokens'],  'bx-key',      'text-success'],
            ['Grants',        $counts['grants'],  'bx-check-shield', 'text-info'],
            ['Auth codes',    $counts['codes'],   'bx-code',     'text-secondary'],
            ['Connections',   $counts['conns'],   'bx-link',     'text-warning'],
            ['Transactions',  $counts['txns'],    'bx-transfer', 'text-body-secondary'],
            ['Tool calls',    $counts['calls'],   'bx-run',      'text-primary'],
            ['Failed calls',  $counts['errors'],  'bx-error',    'text-danger'],
        ];
        foreach ($tiles as $t): ?>
        <div class="col-6 col-md-3" style="min-width:9rem;">
            <div class="adm-stat rounded-4 p-3 h-100">
                <div class="small text-body-secondary mb-1"><i class='bx <?= $t[2] ?> <?= $t[3] ?> me-1'></i><?= $t[0] ?></div>
                <div class="fs-4 fw-bold"><?= (int)$t[1] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- ========================== Switches (read-only) ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-toggle-square me-2 text-warning'></i>Feature switches</h5>
            <span class="small text-body-secondary">read-only — managed under Settings › Config</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-4 adm-stat">
                        <div>
                            <div class="fw-semibold small">Platform MCP</div>
                            <div class="text-body-secondary small">master_switches.mcp</div>
                        </div>
                        <?= $switchBadge(!empty($switches['master_switches.mcp'])) ?>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-4 adm-stat">
                        <div>
                            <div class="fw-semibold small">Labs MCP feature</div>
                            <div class="text-body-secondary small">lab_features.mcp</div>
                        </div>
                        <?= $switchBadge(!empty($switches['lab_features.mcp'])) ?>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-4 adm-stat">
                        <div>
                            <div class="fw-semibold small">Admin-only mode</div>
                            <div class="text-body-secondary small">mcp_settings.admin_only</div>
                        </div>
                        <?= $switchBadge(!empty($switches['mcp_settings.admin_only'])) ?>
                    </div>
                </div>
            </div>
            <div class="form-text mt-3">Revoking a client or rotating a secret takes effect on its next token refresh.</div>
        </div>
    </div>
    <!-- ========================== Clients ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-plug me-2 text-primary'></i>Registered clients</h5>
            <span class="small text-body-secondary"><?= (int)$activeClients ?> active of <?= (int)count($clients) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (!$clients): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class='bx bx-plug fs-1 d-block mb-2'></i>No MCP clients have registered yet.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark opacity-75">
                        <tr>
                            <th class="ps-4">Client</th>
                            <th>Owner</th>
                            <th>Scopes</th>
                            <th>Redirect URIs</th>
                            <th>Created</th>
                            <th>Last used</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clients as $c):
                        $cid = (string)($c['client_id'] ?? '');
                        $owner = trim((string)($c['username'] ?? '')) ?: '—';
                        if ($owner === '—' && !empty($c['user_id'])) $owner = 'user ' . $c['user_id'];
                        $scopes = implode(', ', (array)($c['scopes'] ?? [])) ?: '—';
                        $uris = (array)($c['redirect_uris'] ?? []);
                        $revoked = !empty($c['revoked']);
                    ?>
                        <tr class="mcp-row">
                            <td class="ps-4">
                                <div class="fw-semibold"><?= htmlspecialchars((string)($c['client_name'] ?? 'Unnamed')) ?></div>
                                <code class="small text-body-secondary"><?= htmlspecialchars($cid) ?></code>
                                <?php if (!empty($c['auto'])): ?><span class="badge rounded-pill text-bg-info ms-1">auto</span><?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= htmlspecialchars($owner) ?></td>
                            <td><code class="small"><?= htmlspecialchars($scopes) ?></code></td>
                            <td class="text-body-secondary" style="max-width:18rem;">
                                <?php foreach (array_slice($uris, 0, 2) as $u): ?><div class="text-truncate" style="max-width:16rem;"><?= htmlspecialchars((string)$u) ?></div><?php endforeach; ?>
                                <?php if (count($uris) > 2): ?><div class="small opacity-75">+<?= count($uris) - 2 ?> more</div><?php endif; ?>
                            </td>
                            <td class="text-nowrap text-body-secondary"><?= htmlspecialchars($fmtWhen($c['created_at'] ?? null)) ?></td>
                            <td class="text-nowrap text-body-secondary"><?= htmlspecialchars($ago($c['last_used_at'] ?? null)) ?></td>
                            <td class="pe-4">
                                <?= $revoked
                                    ? '<span class="badge rounded-pill text-bg-danger">Revoked</span>'
                                    : '<span class="badge rounded-pill text-bg-success">Active</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- ========================== Recent tool calls ========================== -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-run me-2 text-info'></i>Recent tool calls</h5>
            <span class="small text-body-secondary">last <?= count($calls) ?> of <?= (int)$counts['calls'] ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (!$calls): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class='bx bx-run fs-1 d-block mb-2'></i>No tool invocations recorded yet.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark opacity-75">
                        <tr>
                            <th class="ps-4">When</th>
                            <th>Tool</th>
                            <th>Actor</th>
                            <th>Client</th>
                            <th>Duration</th>
                            <th class="pe-4">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($calls as $a):
                        $status = (string)($a['status'] ?? '');
                        $err = $status !== 'ok' || !empty($a['response']['isError']);
                        $cid = (string)($a['client_id'] ?? '');
                    ?>
                        <tr class="mcp-row">
                            <td class="ps-4 text-nowrap text-body-secondary"><?= htmlspecialchars($fmtWhen($a['created_at'] ?? null)) ?></td>
                            <td><code class="small"><?= htmlspecialchars((string)($a['tool'] ?? '—')) ?></code></td>
                            <td class="text-nowrap"><?= htmlspecialchars((string)($a['username'] ?? ($a['user_id'] ? 'user ' . $a['user_id'] : '—'))) ?></td>
                            <td class="text-body-secondary"><code class="small"><?= htmlspecialchars($cid ? substr($cid, 0, 22) . (strlen($cid) > 22 ? '…' : '') : '—') ?></code></td>
                            <td class="text-nowrap text-body-secondary"><?= isset($a['duration_ms']) ? (int)$a['duration_ms'] . ' ms' : '—' ?></td>
                            <td class="pe-4">
                                <?= $err
                                    ? '<span class="badge rounded-pill text-bg-danger">' . htmlspecialchars($status ?: 'error') . '</span>'
                                    : '<span class="badge rounded-pill text-bg-success">ok</span>' ?>
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