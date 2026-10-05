<?php
/**
 * Admin → Deleted Users.
 *
 * List view: every archived account (who deleted it, when, quick counts).
 * Detail view: read-only snapshot of everything the account owned, plus the
 * "Return Back" restore action. Admin-only (guarded by the controller).
 *
 * Templates are included inside SessionRenderer's function scope, so this
 * file fetches its own data — same pattern as admin/user_view.php.
 */

$udId = (string)($_GET['id'] ?? '');
$udDb = DatabaseConnection::getDefaultDatabase();
$record = null;
$records = [];

if ($udId !== '') {
    if (preg_match('/^[a-f0-9]{24}$/', $udId)) {
        $record = $udDb->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($udId)]);
    }
    if (!$record) {
        header('Location: /admin/deleted-users');
        exit;
    }
} else {
    $records = iterator_to_array($udDb->deleted_users->find([], [
        'sort'       => ['deleted_at' => -1],
        'limit'      => 200,
        'projection' => [
            '_id' => 1, 'email' => 1, 'username' => 1, 'status' => 1,
            'deleted_at' => 1, 'deleted_by' => 1,
            'restored_at' => 1, 'restored_by' => 1,
            'snapshot.summary' => 1,
        ],
    ]), false);
}

$udEsc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$udBytes = static function ($b): string {
    $b = (float)$b;
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return number_format($b / 1024, 1) . ' KB';
    return (string)(int)$b . ' B';
};

$udTimeField = static fn (string $f): bool =>
    (bool)preg_match('/(_at|^timestamp$|^created$|^expires$|^last_login$)/', $f);

$udSkipFields = ['password', 'session_tokens', 'two_factor_otp', 'verification_token',
                 'reset_token', 'token_hash', 'google_sub'];

$udDocKeys = static function ($doc): array {
    $arr = $doc instanceof ArrayObject ? $doc->getArrayCopy() : (array)$doc;
    return is_array($arr) ? array_keys($arr) : [];
};

/**
 * Scrollable read-only table card. $prefer = field=>label (kept only when the
 * data actually has those keys, otherwise columns are derived from the rows).
 */
$udTable = function (
    string $title,
    $docs,
    ?array $prefer = null,
    string $sortField = 'created_at',
    string $emptyText = 'Nothing stored for this account.'
) use ($udEsc, $udTimeField, $udSkipFields, $udDocKeys): void {
    $docs = is_array($docs) ? $docs : [];
    if ($docs && $sortField !== '') {
        $docs = ud_sort_desc($docs, $sortField);
    }

    $cols = [];
    if ($prefer) {
        $known = [];
        foreach (array_slice($docs, 0, 5, true) as $d) {
            foreach ($udDocKeys($d) as $k) {
                $known[$k] = true;
            }
        }
        foreach ($prefer as $field => $label) {
            if (isset($known[$field])) {
                $cols[$field] = $label;
            }
        }
    }
    if (!$cols && $docs) {
        foreach (array_slice($docs, 0, 5, true) as $d) {
            foreach ($udDocKeys($d) as $k) {
                if (!in_array($k, $udSkipFields, true) && !isset($cols[$k])) {
                    $cols[$k] = $k;
                }
            }
        }
        $cols = array_slice($cols, 0, 8, true);
    }
    ?>
    <div class="card border-0 rounded-4 blur shadow-sm mt-3">
        <div class="card-body py-3">
            <h6 class="fw-bold mb-2">
                <i class='bx bx-table me-2 text-primary'></i><?= $udEsc($title) ?>
                <span class="badge bg-primary-subtle text-primary ms-1"><?= count($docs) ?></span>
            </h6>
            <?php if (!$docs): ?>
                <div class="text-body-secondary small"><?= $udEsc($emptyText) ?></div>
            <?php else: ?>
                <div class="table-responsive" style="max-height:320px; overflow:auto;">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light position-sticky top-0">
                            <tr>
                                <?php foreach ($cols as $label): ?>
                                    <th class="text-nowrap"><?= $udEsc($label) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($docs as $doc): ?>
                            <tr>
                                <?php foreach ($cols as $field => $label): ?>
                                    <?php
                                    $v = $doc[$field] ?? null;
                                    $txt = $udTimeField((string)$field) ? ud_when($v) : ud_display($v);
                                    ?>
                                    <td class="text-wrap"><?= $udEsc($txt) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

$udStatusBadge = static function (string $status): string {
    return match ($status) {
        'restored' => '<span class="badge bg-success-subtle text-success">restored</span>',
        'partial'  => '<span class="badge bg-warning-subtle text-warning">partial</span>',
        default    => '<span class="badge bg-danger-subtle text-danger">deleted</span>',
    };
};

$udKv = static function (string $label, $value) use ($udEsc): void {
    ?>
    <div class="d-flex justify-content-between align-items-start py-1 border-bottom border-body-secondary border-opacity-10 small">
        <span class="text-body-secondary text-nowrap pe-3"><?= $udEsc($label) ?></span>
        <span class="fw-semibold text-end text-wrap"><?= $udEsc(ud_display($value)) ?></span>
    </div>
    <?php
};
?>

<?php if (!$record): ?>
<?php /* ---------------------------------------------------------- LIST VIEW */ ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class='bx bx-archive me-2'></i>Deleted Users</h4>
        <div class="small text-body-secondary">Archived accounts with full snapshots — open one to inspect, or use Return Back to restore it.</div>
    </div>
    <a href="/admin/users" class="btn btn-sm btn-outline-secondary rounded-pill">
        <i class='bx bx-group me-1'></i>Back to Users
    </a>
</div>

<div class="card border-0 rounded-4 blur shadow-sm">
    <div class="card-body">
        <?php if (!$records): ?>
            <div class="text-center text-body-secondary py-5">
                <i class='bx bx-archive display-6 d-block mb-2'></i>
                No deleted users yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Deleted</th>
                            <th>Deleted by</th>
                            <th>Restored by</th>
                            <th>Snapshot</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <?php
                        $rid     = (string)$r['_id'];
                        $rEmail  = (string)($r['email'] ?? '');
                        $rStatus = (string)($r['status'] ?? 'deleted');
                        $rSum    = $r['snapshot']['summary'] ?? [];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= $udEsc((string)($r['username'] ?? '—')) ?></td>
                            <td><?= $udEsc($rEmail) ?></td>
                            <td class="text-nowrap"><?= ud_when($r['deleted_at'] ?? null) ?></td>
                            <td><?= $udEsc((string)($r['deleted_by'] ?? '—')) ?></td>
                            <td><?= $udEsc((string)($r['restored_by'] ?? '—')) ?></td>
                            <td>
                                L:<?= (int)($rSum['labs'] ?? 0) ?>
                                I:<?= (int)($rSum['instances'] ?? 0) ?>
                                D:<?= (int)($rSum['devices'] ?? 0) ?>
                                Tx:<?= (int)($rSum['transactions'] ?? 0) ?>
                            </td>
                            <td><?= $udStatusBadge($rStatus) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/admin/deleted-users/<?= $udEsc($rid) ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class='bx bx-show me-1'></i>View
                                </a>
                                <?php if ($rStatus !== 'restored'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill"
                                            data-ud-restore="<?= $udEsc($rid) ?>" data-email="<?= $udEsc($rEmail) ?>">
                                        <i class='bx bx-undo me-1'></i>Return Back
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<?php /* --------------------------------------------------------- DETAIL VIEW */ ?>

<?php
$snap      = $record['snapshot'] ?? [];
$udUser    = $snap['user'] ?? [];
$udCols    = $snap['collections'] ?? [];
$udFiles   = $snap['files'] ?? [];
$udSum     = $snap['summary'] ?? [];
$udStatus  = (string)($record['status'] ?? 'deleted');
$udEmail   = (string)($record['email'] ?? '');
$udUserNm  = (string)($record['username'] ?? '');
// BSON arrays must become plain PHP arrays before array_merge / is_array checks.
$udGet     = static function (string $col) use ($udCols): array {
    $v = $udCols[$col] ?? [];
    if ($v instanceof Traversable) {
        $v = iterator_to_array($v, false);
    }
    return is_array($v) ? $v : [];
};
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="/admin/deleted-users" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class='bx bx-arrow-back me-1'></i>Deleted Users
        </a>
        <h4 class="fw-bold mb-0"><?= $udEsc($udUserNm ?: $udEmail) ?></h4>
        <?= $udStatusBadge($udStatus) ?>
        <span class="small text-body-secondary"><?= $udEsc($udEmail) ?></span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($udStatus !== 'restored'): ?>
            <button type="button" class="btn btn-sm btn-warning fw-semibold rounded-pill"
                    data-ud-restore="<?= $udEsc((string)$record['_id']) ?>" data-email="<?= $udEsc($udEmail) ?>">
                <i class='bx bx-undo me-1'></i>Return Back
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 rounded-4 blur shadow-sm mb-3">
    <div class="card-body py-3 small">
        <span class="text-body-secondary">Snapshot</span>
        <span class="fw-semibold"><?= $udEsc((string)$record['_id']) ?></span>
        <span class="mx-2">·</span>
        <span class="text-body-secondary">Deleted</span>
        <span class="fw-semibold"><?= ud_when($record['deleted_at'] ?? null) ?></span>
        <span class="text-body-secondary">by</span>
        <span class="fw-semibold"><?= $udEsc((string)($record['deleted_by'] ?? '—')) ?></span>
        <?php if ($udStatus === 'restored'): ?>
            <span class="mx-2">·</span>
            <span class="text-body-secondary">Restored</span>
            <span class="fw-semibold"><?= ud_when($record['restored_at'] ?? null) ?></span>
            <span class="text-body-secondary">by</span>
            <span class="fw-semibold"><?= $udEsc((string)($record['restored_by'] ?? '—')) ?></span>
        <?php elseif (!empty($record['error'])): ?>
            <span class="mx-2">·</span>
            <span class="text-danger"><?= $udEsc((string)$record['error']) ?></span>
        <?php endif; ?>
        <?php if ($udUserNm !== ''): ?>
            <span class="mx-2">·</span>
            <span class="text-body-secondary">Public URL (admin-only while deleted)</span>
            <a class="fw-semibold" href="/<?= $udEsc($udUserNm) ?>" target="_blank" rel="noopener">/<?= $udEsc($udUserNm) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="card border-0 rounded-4 blur shadow-sm h-100">
            <div class="card-body py-3">
                <h6 class="fw-bold mb-2"><i class='bx bx-user me-2 text-primary'></i>Account snapshot</h6>
                <?php
                $udKv('Email', $udUser['email'] ?? $udEmail);
                $udKv('Username', $udUser['username'] ?? $udUserNm);
                $udKv('User ID', $udUser['user_id'] ?? '—');
                $udKv('Name', trim(($udUser['first_name'] ?? '') . ' ' . ($udUser['last_name'] ?? '')) ?: '—');
                $udKv('Role', $udUser['role'] ?? 'user');
                $udKv('Plan', $udUser['plan'] ?? 'default');
                $udKv('Moderator', $udUser['moderator'] ?? 'no');
                $udKv('State', $udUser['state'] ?? '—');
                $udKv('Verified', $udUser['is_verified'] ?? '—');
                $udKv('2FA', ($udUser['two_factor_enabled'] ?? false) ? 'enabled' : 'disabled');
                $udKv('Created', ud_when($udUser['created_at'] ?? null));
                $udKv('Last sign-in', ud_when($udUser['last_login'] ?? null));
                $udKv('Last IPv4', $udUser['ip_address_v4'] ?? ($udUser['ip_address'] ?? '—'));
                $udKv('Last IPv6', $udUser['ip_address_v6'] ?? '—');
                $udKv('Failed logins', $udUser['failed_login_attempts'] ?? 0);
                $udKv('Locked until', ud_when($udUser['locked_until'] ?? null));
                $udKv('Quizzes completed', $udUser['quizzes_completed'] ?? 0);
                ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card border-0 rounded-4 blur shadow-sm h-100">
            <div class="card-body py-3">
                <h6 class="fw-bold mb-3"><i class='bx bx-purchase-tag me-2 text-warning'></i>Points &amp; activity</h6>
                <div class="row g-2">
                    <?php
                    $udTiles = [
                        ['Zeal 🔥', (int)($udSum['zeal'] ?? 0), 'bx-fire', 'text-danger'],
                        ['Jolt ⚡', (int)($udSum['jolt'] ?? 0), 'bx-bolt', 'text-warning'],
                        ['Labs', (int)($udSum['labs'] ?? 0), 'bx-laptop', 'text-primary'],
                        ['Instances', (int)($udSum['instances'] ?? 0), 'bx-server', 'text-info'],
                        ['Devices', (int)($udSum['devices'] ?? 0), 'bx-devices', 'text-success'],
                        ['IPs', (int)($udSum['ips'] ?? 0), 'bx-network-chart', 'text-secondary'],
                        ['Domains', (int)($udSum['domains'] ?? 0), 'bx-globe', 'text-primary'],
                        ['SSH keys', (int)($udSum['ssh_keys'] ?? 0), 'bx-key', 'text-warning'],
                        ['Transactions', (int)($udSum['transactions'] ?? 0), 'bx-transfer', 'text-info'],
                        ['Activity', (int)($udSum['activity'] ?? 0), 'bx-line-chart', 'text-success'],
                        ['Quizzes', (int)($udSum['quizzes'] ?? 0), 'bx-help-circle', 'text-danger'],
                        ['Challenges', (int)($udSum['challenges'] ?? 0), 'bx-trophy', 'text-warning'],
                    ];
                    foreach ($udTiles as [$label, $value, $icon, $color]): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <div class="border border-body-secondary border-opacity-10 rounded-3 p-2 text-center h-100">
                                <div class="fw-bold fs-5"><?= number_format($value) ?></div>
                                <div class="small text-body-secondary"><i class='<?= $icon ?> <?= $color ?> me-1'></i><?= $udEsc($label) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h6 class="fw-bold mt-4 mb-2"><i class='bx bx-folder me-2 text-info'></i>Home folder backup</h6>
                <div class="row g-2 small">
                    <div class="col-12 col-md-6">
                        <?php
                        $udKv('Live path', $udFiles['source'] ?? '—');
                        $udKv('Backup path', $udFiles['backup_path'] ?? ($udFiles['moved'] ? '—' : 'no folder existed'));
                        $udKv('Moved', !empty($udFiles['moved']) ? 'yes' : 'no');
                        ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <?php
                        $udKv('Files', number_format((int)($udFiles['files'] ?? 0)));
                        $udKv('Size', $udBytes($udFiles['bytes'] ?? 0));
                        $udKv('Folder note', ($udFiles['error'] ?? null) ?: '—');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$udInstDocs = array_merge($udGet('instances'), $udGet('instance_trash'));

$udTable('Active labs', $udGet('machine_labs'), [
    'lab_type' => 'Lab', 'status' => 'Status', 'internal_ip' => 'Internal IP',
    'tunnel_ip' => 'Tunnel IP', 'code_domain' => 'Code domain', 'created_at' => 'Created',
]);

$udTable('Instances & trash', $udInstDocs, [
    'name' => 'Name', 'slug' => 'Slug', 'status' => 'Status', 'visibility' => 'Visibility',
    'type' => 'Type', 'created_at' => 'Created',
]);

$udTable('Shared instances', $udGet('instance_shares'), [
    'instance_hash' => 'Instance', 'shared_with' => 'Shared with', 'shared_by' => 'Shared by',
]);

$udTable('Devices', $udGet('devices'), [
    'device_name' => 'Device', 'assigned_ip' => 'Assigned IP', 'status' => 'Status', 'created_at' => 'Added',
]);

$udTable('IP registry', $udGet('ip_registry'), [
    'ip_addr' => 'IP', 'status' => 'Status', 'reserved_to' => 'Reserved to',
    'allocated_to' => 'Allocated to', 'reserved_at' => 'Reserved',
]);

$udTable('Custom domains', $udGet('domains'), ['domain' => 'Domain', 'status' => 'Status', 'created_at' => 'Created']);
$udTable('SSH keys', $udGet('ssh_keys'), ['label' => 'Label', 'name' => 'Name', 'public_key' => 'Public key', 'created_at' => 'Added']);

$udTable('Currency ledger', $udGet('transactions'), [
    'created_at' => 'When', 'direction' => 'Dir', 'currency' => 'Currency', 'amount' => 'Amount',
    'type' => 'Type', 'reason' => 'Reason', 'actor' => 'Actor',
]);

$udTable('Activity history', $udGet('user_activity'), [
    'timestamp' => 'When', 'page' => 'Page', 'date' => 'Date', 'hour' => 'Hour',
], 'timestamp');

$udTable('Audit trail', $udGet('audit_log'), [
    'created_at' => 'When', 'action' => 'Action', 'entity_type' => 'Entity',
    'entity_id' => 'Entity ID', 'ip_address' => 'IP',
]);

$udTable('Quiz attempts', $udGet('quiz_attempts'), null, 'created_at');
$udTable('Challenge submissions', $udGet('challenge_submissions'), null, 'created_at');
$udTable('Error events', $udGet('error_events'), [
    'severity' => 'Severity', 'message' => 'Message', 'context' => 'Context', 'status' => 'Status',
]);
$udTable('Authored roadmaps', $udGet('ai_roadmaps'), null, 'created_at');
$udTable('Authored lessons', $udGet('ai_lessons'), null, 'created_at');

$udOther = [];
foreach ($udSum['collections'] ?? [] as $col => $n) {
    if (in_array($col, ['machine_labs', 'instances', 'instance_trash', 'devices', 'ip_registry',
        'domains', 'ssh_keys', 'transactions', 'user_activity', 'audit_log', 'quiz_attempts',
        'challenge_submissions', 'error_events', 'ai_roadmaps', 'ai_lessons'], true)) {
        continue;
    }
    $udOther[] = ['collection' => $col, 'documents' => $n];
}
$udTable('Other stored data', $udOther, ['collection' => 'Collection', 'documents' => 'Documents'], '');
?>
<?php endif; ?>

<!-- ====================== Restore modal (shared by list + detail) ====================== -->
<div class="modal fade" id="udRestoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 blur shadow-lg">
            <div class="modal-header border-bottom border-body-secondary border-opacity-10">
                <h5 class="modal-title fw-bold"><i class='bx bx-undo me-2 text-warning'></i>Return Back</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0 rounded-3 small mb-3 py-2">
                    Restores every document from the snapshot — profile, labs, instances, devices, IPs,
                    points and the home folder — and the account becomes live again.
                </div>
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1">Account</label>
                    <input type="text" class="form-control bg-transparent border-secondary border-opacity-25"
                           id="udRestoreEmail" disabled>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-body-secondary mb-1" for="udRestoreConfirm">
                        Type <code id="udRestorePhrase"></code> to confirm
                    </label>
                    <input type="text" id="udRestoreConfirm"
                           class="form-control bg-transparent border-secondary border-opacity-25"
                           placeholder="RESTORE someone@example.com" autocomplete="off">
                </div>
                <div class="form-text">Written to the audit log with your admin identity.</div>
            </div>
            <div class="modal-footer border-top border-body-secondary border-opacity-10">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-coreui-dismiss="modal">Cancel</button>
                <button type="button" id="udRestoreGo" class="btn btn-sm btn-warning fw-semibold rounded-pill px-3">Return Back</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF  = <?= json_encode(Session::csrfToken()) ?>;
    const notify = (m, t, ty) => { if (window.TomNotify) TomNotify.show(m, t, ty || 'success', 4000); };

    async function post(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload).toString()
        });
        return res.json();
    }

    let pendingId = null;

    document.querySelectorAll('[data-ud-restore]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingId = btn.getAttribute('data-ud-restore');
            const email = btn.getAttribute('data-email') || '';
            const modalEl = document.getElementById('udRestoreModal');
            document.getElementById('udRestoreEmail').value = email;
            document.getElementById('udRestorePhrase').textContent = 'RESTORE ' + email;
            document.getElementById('udRestoreConfirm').value = '';
            new coreui.Modal(modalEl).show();
        });
    });

    document.getElementById('udRestoreGo')?.addEventListener('click', async function () {
        const expected = 'RESTORE ' + (document.getElementById('udRestoreEmail').value || '');
        const typed = document.getElementById('udRestoreConfirm').value.trim();
        if (typed !== expected) {
            notify('Type exactly: ' + expected, 'Confirmation required', 'warning');
            return;
        }
        if (!pendingId) return;
        this.disabled = true;
        try {
            const res = await post('/api/admin/restore_user', { id: pendingId, confirm: typed });
            if (res.status === 'success') {
                const homeNote = res.home && res.home.moved ? 'Home folder restored' : 'Account restored';
                notify(homeNote + ' — the user is live again', 'Return Back complete');
                coreui.Modal.getInstance(document.getElementById('udRestoreModal'))?.hide();
                setTimeout(() => location.reload(), 900);
            } else {
                notify(res.error || 'Restore failed', 'Error', 'error');
            }
        } catch (e) {
            notify('Network error', 'Error', 'error');
        }
        this.disabled = false;
    });
})();
</script>
