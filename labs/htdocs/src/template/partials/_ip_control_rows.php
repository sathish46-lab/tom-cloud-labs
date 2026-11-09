<?php
/**
 * Admin → IP Control — reserved-IP table rows.
 *
 * Renders <tr> markup for the Reserved IPs card. Expects pre-enriched docs
 * in $rows: state (system|in_use|dead), usage, dead_reason, last_usage_at,
 * last_usage_label. Pagination/collection belongs to the caller, so the
 * page template and the ip_control "rows" API action share identical markup.
 */

$_ipcEsc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$_ipcFmt = static function ($ts) {
    if (!$ts) return '—';
    if ($ts instanceof MongoDB\BSON\UTCDateTime) $ts = (int)floor($ts->toDateTime()->getTimestamp());
    if ($ts instanceof DateTimeInterface) $ts = $ts->getTimestamp();
    return date('d M Y, H:i', (int)$ts);
};
$_ipcUsageBadge = static function (string $u) {
    return match ($u) {
        'Device'      => '<span class="badge rounded-pill bg-info text-dark"><i class=\'bx bx-mobile-alt me-1\'></i>Device</span>',
        'Machine Lab' => '<span class="badge rounded-pill bg-success"><i class=\'bx bx-server me-1\'></i>Machine Lab</span>',
        'Instance'    => '<span class="badge rounded-pill bg-primary"><i class=\'bx bx-cube me-1\'></i>Instance</span>',
        default       => '<span class="badge rounded-pill bg-body-secondary">Unlinked</span>',
    };
};
$_ipcLastUsage = static function (array $r) use ($_ipcEsc, $_ipcFmt) {
    if (!empty($r['last_usage_at'])) {
        $detail = ($r['last_usage_label'] !== '' ? $r['last_usage_label'] . ' · ' : '') . $_ipcFmt($r['last_usage_at']);
        return '<span class="badge rounded-pill bg-success" title="This IP has been used by a lab deploy"><i class=\'bx bx-rocket me-1\'></i>Used</span>'
            . '<div class="opacity-50 small-x-tiny mt-1">' . $_ipcEsc($detail) . '</div>';
    }
    $acquired = !empty($r['reserved_at']) ? '<div class="opacity-50 small-x-tiny mt-1">acquired ' . $_ipcEsc($_ipcFmt($r['reserved_at'])) . '</div>' : '';
    return '<span class="badge rounded-pill bg-warning text-dark" title="Assigned but never used for a lab deploy"><i class=\'bx bx-pause me-1\'></i>Never used</span>' . $acquired;
};

foreach ($rows as $r) {
    $state = $r['state'] ?? 'in_use';
    $hay = strtolower(($r['ip_addr'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['reserved_to'] ?? '') . ' ' . $state . ' ' . (($r['last_usage_label'] ?? '') ?: 'never_used'));
    if ($state === 'system') { ?>
        <tr data-filter="<?= $_ipcEsc($hay) ?>">
            <td class="ps-4 ipc-ip"><?= $_ipcEsc($r['ip_addr'] ?? '') ?></td>
            <td><span class="badge rounded-pill bg-info text-dark">System</span></td>
            <td class="text-body-secondary"><?= $_ipcEsc($r['email'] ?? 'system') ?></td>
            <td><span class="badge rounded-pill bg-info text-dark"><i class='bx bx-server me-1'></i>Gateway</span></td>
            <td class="text-body-secondary"><?= $_ipcEsc(($r['label'] ?? '') ?: '—') ?></td>
            <td class="text-body-secondary"><?= $_ipcEsc($_ipcFmt($r['reserved_at'] ?? null)) ?></td>
            <td class="text-body-secondary">—</td>
            <td class="pe-4 text-end text-body-secondary">—</td>
        </tr>
    <?php } elseif ($state === 'dead') { ?>
        <tr data-filter="<?= $_ipcEsc($hay) ?>">
            <td class="ps-4 ipc-ip"><?= $_ipcEsc($r['ip_addr'] ?? '') ?></td>
            <td><span class="badge rounded-pill bg-danger">Dead</span><div class="opacity-50 small-x-tiny"><?= $_ipcEsc($r['dead_reason'] ?? '') ?></div></td>
            <td class="text-body-emphasis fw-semibold"><?= $_ipcEsc(($r['email'] ?? '') ?: ($r['reserved_to'] ?? '') ?: '—') ?></td>
            <td><span class="badge rounded-pill bg-body-secondary"><i class='bx bx-x me-1'></i>No live resource</span></td>
            <td class="text-body-secondary"><?= $_ipcEsc(($r['label'] ?? '') ?: ($r['service_type'] ?? '') ?: '—') ?></td>
            <td class="text-body-secondary"><?= $_ipcEsc($_ipcFmt($r['reserved_at'] ?? null)) ?></td>
            <td class="text-body-secondary"><?= $_ipcLastUsage($r) ?></td>
            <td class="pe-4 text-end">
                <button type="button" class="btn btn-sm btn-danger rounded-pill ipc-release" data-ip="<?= $_ipcEsc($r['ip_addr'] ?? '') ?>">Release dead IP</button>
            </td>
        </tr>
    <?php } else { ?>
        <tr data-filter="<?= $_ipcEsc($hay) ?>">
            <td class="ps-4 ipc-ip"><?= $_ipcEsc($r['ip_addr'] ?? '') ?></td>
            <td><span class="badge rounded-pill bg-success">In Use</span></td>
            <td><a class="text-decoration-none fw-semibold" href="/admin/user/<?= rawurlencode((string)($r['email'] ?? '')) ?>"><?= $_ipcEsc(($r['email'] ?? '') ?: ($r['reserved_to'] ?? '')) ?></a></td>
            <td><?= $_ipcUsageBadge((string)($r['usage'] ?? '')) ?></td>
            <td class="text-body-secondary"><?= $_ipcEsc(($r['label'] ?? '') ?: ($r['service_type'] ?? '') ?: '—') ?></td>
            <td class="text-body-secondary"><?= $_ipcEsc($_ipcFmt($r['reserved_at'] ?? null)) ?></td>
            <td class="text-body-secondary"><?= $_ipcLastUsage($r) ?></td>
            <td class="pe-4 text-end">
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill ipc-release" data-ip="<?= $_ipcEsc($r['ip_addr'] ?? '') ?>">Release</button>
            </td>
        </tr>
    <?php }
} unset($state, $hay, $r);
