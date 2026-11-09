<?php
/**
 * POST /api/admin/ip_control
 *
 * Actions:
 *   release       {ip}             — release one reserved (non-system) IP back to the pool
 *   release_dead  {}               — release every "dead" reservation (owner gone / no active resource)
 *
 * Responses: {"status":"success", ...} | {"status":"error","error":"..."}
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$db = DatabaseConnection::getDefaultDatabase();
$instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');

$adminEmail = (string)($admin->getEmail() ?? 'unknown');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$action = $data['action'] ?? $_POST['action'] ?? '';

/**
 * IPs still referenced by live resources: active devices, machine labs, instances.
 * Returns ['ip' => 'Device'|'Machine Lab'|'Instance']
 */
function ip_control_active_ips($db, $instDb): array
{
    $used = [];
    foreach ($db->devices->find(['status' => ['$ne' => 'deleted']], ['projection' => ['assigned_ip' => 1]]) as $d) {
        if (!empty($d['assigned_ip'])) $used[$d['assigned_ip']] = 'Device';
    }
    foreach ($db->machine_labs->find([], ['projection' => ['deploy.internal_ip' => 1]]) as $lab) {
        $ip = $lab['deploy']['internal_ip'] ?? null;
        if ($ip) $used[$ip] = 'Machine Lab';
    }
    try {
        foreach ($instDb->instances->find([], ['projection' => ['deploy.internal_ip' => 1]]) as $inst) {
            $ip = $inst['deploy']['internal_ip'] ?? null;
            if ($ip) $used[$ip] = 'Instance';
        }
    } catch (Throwable $e) { /* instances DB optional */ }
    return $used;
}

/**
 * Reserved (non-system) registry docs that are dead: owner email missing from
 * users, or the IP not pointed at any live resource.
 */
function ip_control_dead_ips($db, $instDb): array
{
    $emails = [];
    foreach ($db->users->find([], ['projection' => ['email' => 1]]) as $u) {
        $emails[(string)$u['email']] = true;
    }
    $used = ip_control_active_ips($db, $instDb);

    $dead = [];
    foreach ($db->ip_registry->find(['status' => 'reserved'], ['sort' => ['ip_numeric' => 1]]) as $doc) {
        if (($doc['reserved_to'] ?? '') === 'server' || ($doc['email'] ?? '') === 'system@tomweb.in') continue;
        $ip = (string)$doc['ip_addr'];
        $ownerAlive = ($doc['email'] ?? '') !== '' && isset($emails[(string)$doc['email']]);
        $usedByOwner = isset($used[$ip]) && ($doc['email'] ?? '') !== '' && ($used[$ip] !== '') ;
        if ($ownerAlive || $usedByOwner) continue;
        $doc['dead_reason'] = $ownerAlive ? 'No active device/lab on this IP' : 'Owner account does not exist';
        $dead[] = $doc;
    }
    return $dead;
}

$ipManager = new TomLabs\Labs\IPManager();

switch ($action) {
    case 'release': {
        $ip = trim((string)($data['ip'] ?? $_POST['ip'] ?? ''));
        if (!preg_match('/^172\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'A valid pool IP is required', JSON_PRETTY_PRINT]);
            exit;
        }
        $doc = $db->ip_registry->findOne(['ip_addr' => $ip]);
        if (!$doc) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'IP not found in the registry', JSON_PRETTY_PRINT]);
            exit;
        }
        if (($doc['status'] ?? '') !== 'reserved') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'IP is not reserved (status: ' . ($doc['status'] ?? '?') . ')', JSON_PRETTY_PRINT]);
            exit;
        }
        if (($doc['reserved_to'] ?? '') === 'server' || ($doc['email'] ?? '') === 'system@tomweb.in') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'error' => 'System IP (server gateway) cannot be released', JSON_PRETTY_PRINT]);
            exit;
        }
        $ipManager->release($ip);
        AuditLog::log('release', 'ip_registry', $ip, ['released_by' => $adminEmail]);
        echo json_encode(['status' => 'success', 'message' => "$ip released to the pool", 'ip' => $ip, JSON_PRETTY_PRINT]);
        exit;
    }

    case 'release_dead': {
        $dead = ip_control_dead_ips($db, $instDb);
        $released = [];
        $failed = [];
        foreach ($dead as $doc) {
            $ip = (string)$doc['ip_addr'];
            try {
                $ipManager->release($ip);
                AuditLog::log('release', 'ip_registry', $ip, [
                    'released_by' => $adminEmail,
                    'dead_reason' => $doc['dead_reason'] ?? 'dead',
                    'bulk' => true,
                ]);
                $released[] = $ip;
            } catch (Throwable $e) {
                $failed[] = $ip;
            }
        }
        AuditLog::log('release_dead', 'ip_registry', 'bulk', [
            'released_by' => $adminEmail,
            'released' => count($released),
            'failed' => count($failed),
        ]);
        echo json_encode([
            'status' => 'success',
            'message' => count($released) . ' dead IP(s) released back to the pool',
            'released' => $released,
            'failed' => $failed,
            'remaining_dead' => count($failed),
        ]);
        exit;
    }

    case 'rows': {
        $offset = max(0, (int)($data['offset'] ?? $_POST['offset'] ?? 0));
        $limit  = min(200, max(1, (int)($data['limit'] ?? 15)));

        $epoch = static function ($v) {
            if (!$v) return null;
            if ($v instanceof MongoDB\BSON\UTCDateTime) return (int)floor($v->toDateTime()->getTimestamp());
            if (is_int($v) || is_float($v)) return (int)$v;
            return null;
        };

        $used = ip_control_active_ips($db, $instDb);
        $emails = [];
        try {
            foreach ($db->users->find([], ['projection' => ['email' => 1]]) as $u) {
                $emails[(string)$u['email']] = true;
            }
        } catch (Throwable $e) { /* non-fatal */ }

        $cursor = $db->ip_registry->find(
            ['status' => 'reserved'],
            ['sort' => ['ip_numeric' => 1], 'skip' => $offset, 'limit' => $limit]
        );

        $rows = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc + ['reserved_to' => '', 'email' => '', 'label' => '', 'service_type' => ''];
            $doc['usage'] = $used[$doc['ip_addr']] ?? '';
            $lu = $doc['last_usage'] ?? null;
            $lu_at = is_array($lu) ? $epoch($lu['at'] ?? null) : null;
            $lu_label = is_array($lu) && ($lu['label'] ?? '') !== '' ? (string)$lu['label'] : '';
            $reserved_at = $epoch($doc['reserved_at'] ?? null);
            if (($doc['reserved_to'] === 'server' || $doc['email'] === 'system@tomweb.in')) {
                $state = 'system'; $dead_reason = '';
            } else {
                $ownerAlive = $doc['email'] !== '' && isset($emails[$doc['email']]);
                $resAlive   = $doc['usage'] !== '';
                if ($ownerAlive || $resAlive) {
                    $state = 'in_use'; $dead_reason = '';
                } else {
                    $state = 'dead';
                    $dead_reason = $ownerAlive ? 'No active device/lab on this IP' : 'Owner account does not exist';
                }
            }
            $filter = strtolower(($doc['ip_addr'] ?? '') . ' ' . ($doc['email'] ?? '') . ' ' . ($doc['reserved_to'] ?? '') . ' ' . $state . ' ' . ($lu_label ?: 'never_used'));
            $rows[] = [
                'ip_addr'          => (string)($doc['ip_addr'] ?? ''),
                'email'            => ($state === 'dead' && $dead_reason === 'Owner account does not exist') ? '—' : (string)($doc['email'] ?? ''),
                'reserved_to'      => (string)($doc['reserved_to'] ?? ''),
                'label'            => (string)($doc['label'] ?? ''),
                'service_type'     => (string)($doc['service_type'] ?? ''),
                'usage'            => (string)($doc['usage'] ?? ''),
                'state'            => $state,
                'dead_reason'      => $dead_reason,
                'reserved_at'      => $reserved_at,
                'last_usage_at'    => $lu_at,
                'last_usage_label' => $lu_label,
                'data_filter'      => $filter,
            ];
        }

        $loaded = $offset + count($rows);
        $total  = $db->ip_registry->countDocuments(['status' => 'reserved']);
        echo json_encode([
            'status' => 'success',
            'rows'   => $rows,
            'total'  => $total,
            'loaded' => $loaded,
            'hasMore'=> $loaded < $total,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    case 'search': {
        $q = trim((string)($data['q'] ?? $_POST['q'] ?? ''));
        if ($q === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Search query is required'], JSON_PRETTY_PRINT);
            exit;
        }

        $limit = min(100, max(1, (int)($data['limit'] ?? 50)));

        $epoch = static function ($v) {
            if (!$v) return null;
            if ($v instanceof MongoDB\BSON\UTCDateTime) return (int)floor($v->toDateTime()->getTimestamp());
            if (is_int($v) || is_float($v)) return (int)$v;
            return null;
        };

        $used = ip_control_active_ips($db, $instDb);
        $emails = [];
        try {
            foreach ($db->users->find([], ['projection' => ['email' => 1]]) as $u) {
                $emails[(string)$u['email']] = true;
            }
        } catch (Throwable $e) { /* non-fatal */ }

        $qEsc = preg_quote($q, '/');
        $match = ['status' => 'reserved'];
        $or = [];
        if (preg_match('/^[\d\.]+$/', $q)) {
            $or[] = ['ip_addr' => new \MongoDB\BSON\Regex('^' . $qEsc, 'i')];
        }
        $or[] = ['email' => new \MongoDB\BSON\Regex($qEsc, 'i')];
        $or[] = ['label' => new \MongoDB\BSON\Regex($qEsc, 'i')];
        $match['$or'] = $or;

        $cursor = $db->ip_registry->find(
            $match,
            ['sort' => ['ip_numeric' => 1], 'limit' => $limit]
        );

        $rows = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc + ['reserved_to' => '', 'email' => '', 'label' => '', 'service_type' => ''];
            $doc['usage'] = $used[$doc['ip_addr']] ?? '';
            $lu = $doc['last_usage'] ?? null;
            $lu_at = is_array($lu) ? $epoch($lu['at'] ?? null) : null;
            $lu_label = is_array($lu) && ($lu['label'] ?? '') !== '' ? (string)$lu['label'] : '';
            $reserved_at = $epoch($doc['reserved_at'] ?? null);
            if (($doc['reserved_to'] === 'server' || $doc['email'] === 'system@tomweb.in')) {
                $state = 'system'; $dead_reason = '';
            } else {
                $ownerAlive = $doc['email'] !== '' && isset($emails[$doc['email']]);
                $resAlive   = $doc['usage'] !== '';
                if ($ownerAlive || $resAlive) {
                    $state = 'in_use'; $dead_reason = '';
                } else {
                    $state = 'dead';
                    $dead_reason = $ownerAlive ? 'No active device/lab on this IP' : 'Owner account does not exist';
                }
            }
            $filter = strtolower(($doc['ip_addr'] ?? '') . ' ' . ($doc['email'] ?? '') . ' ' . ($doc['reserved_to'] ?? '') . ' ' . $state . ' ' . ($lu_label ?: 'never_used'));
        $rows[] = [
            'ip_addr'          => (string)($doc['ip_addr'] ?? ''),
            'email'            => ($state === 'dead' && $dead_reason === 'Owner account does not exist') ? '—' : (string)($doc['email'] ?? ''),
            'reserved_to'      => (string)($doc['reserved_to'] ?? ''),
            'label'            => (string)($doc['label'] ?? ''),
            'service_type'     => (string)($doc['service_type'] ?? ''),
            'usage'            => (string)($doc['usage'] ?? ''),
            'state'            => $state,
            'dead_reason'      => $dead_reason,
            'reserved_at'      => $reserved_at,
            'last_usage_at'    => $lu_at,
            'last_usage_label' => $lu_label,
            'data_filter'      => $filter,
        ];
    }

    echo json_encode([
        'status' => 'success',
        'query'  => $q,
        'rows'   => $rows,
        'count'  => count($rows),
    ], JSON_PRETTY_PRINT);
    exit;
    }

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Unknown action', JSON_PRETTY_PRINT]);
        exit;
}

