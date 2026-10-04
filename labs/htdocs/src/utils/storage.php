<?php
/**
 * Storage quota helpers — shared by the admin Storage Quotas page and its API.
 *
 * Per-tenant usage lives on disk under {storage_base}/{md5(email)}; there is no
 * usage column anywhere else, so usage is measured with `du` and cached in the
 * `storage_usage` collection (re-measured from the page's "Re-audit usage").
 */

const STORAGE_PLAN_LIMITS = [
    'free'    => 2684354560,    // 2.5 GB
    'default' => 10737418240,   // 10 GB
    'pro'     => 53687091200,   // 50 GB
];
const STORAGE_PLAN_FALLBACK = 'default';

function storage_base_path(): string
{
    $cfg = get_config('storage_base');
    return (is_string($cfg) && $cfg !== '') ? rtrim($cfg, '/') : '/var/tomlabs/storage';
}

function storage_plan_label(string $plan): string
{
    return ['free' => 'Free', 'default' => 'Default', 'pro' => 'Pro'][$plan] ?? ucfirst($plan);
}

function storage_plan_limit(string $plan): int
{
    return STORAGE_PLAN_LIMITS[$plan] ?? (STORAGE_PLAN_LIMITS[STORAGE_PLAN_FALLBACK] ?? 0);
}

/** Absent/unknown plan fields fall back to `default` (same rule as the user profile page). */
function storage_plan_of(array $u): string
{
    $p = strtolower(trim((string)($u['plan'] ?? '')));
    return in_array($p, ['free', 'default', 'pro'], true) ? $p : STORAGE_PLAN_FALLBACK;
}

function storage_format_bytes(int $b): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $v = (float)$b;
    $i = 0;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    $dec = ($i === 0 || $v >= 100) ? 0 : ($v >= 10 ? 1 : 2);
    $n = rtrim(rtrim(number_format($v, $dec), '0'), '.');
    return $n . ' ' . $units[$i];
}

/** Hard ceiling for an admin-set cap so a bad value can't strand a tenant. */
const STORAGE_LIMIT_MAX = 1099511627776; // 1 TB

/**
 * Per-user cap stored on `users.storage_limit_bytes` by the "Storage limit"
 * button on the admin user profile. Absent/null means "use the plan limit".
 */
function storage_limit_override_of(array $u): ?int
{
    $v = $u['storage_limit_bytes'] ?? null;
    if ($v === null || $v === '' || !is_numeric($v)) {
        return null;
    }
    $n = (int)$v;
    return ($n > 0 && $n <= STORAGE_LIMIT_MAX) ? $n : null;
}

/** Manual cap wins over the plan cap. */
function storage_effective_limit(?int $override, string $plan): int
{
    return $override ?? storage_plan_limit($plan);
}

/**
 * Measure every tenant, persist the result and refresh the pool record.
 *
 * @return array{tenants:int,present:int,missing:int,bytes:int,pool:array,at:int,error?:string}
 */
function storage_audit_run(object $db): array
{
    $base = storage_base_path();
    $now  = time();
    $sum  = ['tenants' => 0, 'present' => 0, 'missing' => 0, 'bytes' => 0, 'pool' => [], 'at' => $now];

    if (!is_dir($base)) {
        $sum['error'] = 'Storage base not found: ' . $base;
        return $sum;
    }

    // Pool capacity from df (one row, no per-tenant cost).
    $df = [];
    exec('df -kP ' . escapeshellarg($base) . ' 2>/dev/null', $df, $rc);
    if ($rc === 0 && isset($df[1])) {
        $cols = preg_split('/\s+/', trim($df[1]));
        if (count($cols) >= 4) {
            $sum['pool'] = [
                'total' => (int)$cols[1] * 1024,
                'used'  => (int)$cols[2] * 1024,
                'avail' => (int)$cols[3] * 1024,
            ];
        }
    }

    $projection = ['email' => 1, 'plan' => 1, 'username' => 1, 'user_id' => 1, 'state' => 1, 'storage_limit_bytes' => 1];
    foreach ($db->users->find([], ['projection' => $projection]) as $u) {
        $email = trim((string)($u['email'] ?? ''));
        if ($email === '') {
            continue;
        }
        $sum['tenants']++;

        $plan     = storage_plan_of((array)$u);
        $override = storage_limit_override_of((array)$u);
        $limit    = storage_effective_limit($override, $plan);

        $path   = $base . '/' . md5($email);
        $exists = is_dir($path);
        $bytes  = 0;
        if ($exists) {
            $sum['present']++;
            $r = [];
            exec('du -sb ' . escapeshellarg($path) . ' 2>/dev/null', $r, $rc2);
            if ($rc2 === 0 && preg_match('/^(\d+)/', $r[0] ?? '', $m)) {
                $bytes = (int)$m[1];
            }
        } else {
            $sum['missing']++;
        }
        $sum['bytes'] += $bytes;

        try {
            $db->storage_usage->updateOne(
                ['user_email' => $email],
                ['$set' => [
                    'user_email' => $email,
                    'username'   => (string)($u['username'] ?? ''),
                    'user_id'    => (int)($u['user_id'] ?? 0),
                    'plan'       => $plan,
                    'state'      => (string)($u['state'] ?? ''),
                    'bytes'      => $bytes,
                    'path_exists'=> $exists,
                    'limit_bytes'=> $limit,
                    'limit_custom'=> $override !== null,
                    'audited_at' => $now,
                ]],
                ['upsert' => true]
            );
        } catch (Throwable $e) {
            error_log('storage_audit_run: ' . $e->getMessage());
        }
    }

    $tenantsOver = 0;
    try {
        foreach ($db->storage_usage->find([], ['projection' => ['plan' => 1, 'bytes' => 1, 'limit_bytes' => 1]]) as $r) {
            $ov = $r['limit_bytes'] ?? null;
            $cap = (is_numeric($ov) && (int)$ov > 0)
                ? (int)$ov
                : storage_plan_limit((string)($r['plan'] ?? ''));
            if ((int)($r['bytes'] ?? 0) > $cap) {
                $tenantsOver++;
            }
        }
        $db->storage_pools->updateOne(
            ['_id' => 'default'],
            ['$set' => [
                'label'        => 'default',
                'path'         => $base,
                'fs'           => 'overlay',
                'quota_mode'   => 'per-user',
                'tenants'      => $sum['tenants'],
                'over_quota'   => $tenantsOver,
                'total_bytes'  => (int)($sum['pool']['total'] ?? 0),
                'used_bytes'   => max((int)($sum['pool']['used'] ?? 0), (int)$sum['bytes']),
                'avail_bytes'  => (int)($sum['pool']['avail'] ?? 0),
                'tenant_bytes' => (int)$sum['bytes'],
                'updated_at'   => $now,
            ]],
            ['upsert' => true]
        );
    } catch (Throwable $e) {
        error_log('storage_audit_run pool: ' . $e->getMessage());
    }

    $sum['over_quota'] = $tenantsOver;
    return $sum;
}
