<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/lib/core/VPN.class.php';
header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();

$apiResponse = VPN::request('wg', 'get_peers', ['device' => 'wg0']);
$peersByPk = [];
foreach (($apiResponse['peers'] ?? []) as $peer) {
    if (!empty($peer['peer'])) $peersByPk[$peer['peer']] = $peer;
}

$now = time();
$email = $user->email ?? null;
$uid = $user->getUserId();

$myDevices = $db->devices->find(['$and' => [
    ['$or' => [
        ['email' => $email],
        ['user_id' => ['$in' => [$uid, (string) $uid]]],
    ]],
    ['status' => ['$ne' => 'deleted']],
]])->toArray();

$results = [];
foreach ($myDevices as $device) {
    $pk = $device['public_key'] ?? '';
    if ($pk === '') continue;

    $peer = $peersByPk[$pk] ?? null;

    if (!$peer) {
        $results[] = [
            'peer' => $pk,
            'allowed ips' => (!empty($device['assigned_ip'])) ? $device['assigned_ip'] . '/32' : '',
            'status' => 'No Connection',
            'id' => (string) $device['_id'],
        ];
        continue;
    }

    $row = ['peer' => $pk];

    $row['endpoint'] = explode(':', $peer['endpoint'] ?? '')[0];
    $row['allowed ips'] = (!empty($device['assigned_ip'])) ? $device['assigned_ip'] . '/32' : '';

    $handshakeStr = $peer['latest handshake'] ?? '';
    $row['latest handshake'] = $handshakeStr;
    $row['transfer'] = $peer['transfer'] ?? '';

    $status = 'Offline';
    $handshakeTime = null;
    if ($handshakeStr !== '') {
        $parts = ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0];
        if (preg_match_all('/(\d+)\s+(\w+)/', $handshakeStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) $parts[$m[2]] = (int) $m[1];
        }
        $delta = $parts['days'] * 86400 + $parts['hours'] * 3600 + $parts['minutes'] * 60 + $parts['seconds'];
        if ($delta < 120) $status = 'Online';
        $handshakeTime = $now - $delta;
    }

    $row['status'] = $status;
    $row['handshake_time'] = $handshakeTime;
    $row['sync_time'] = $now;

    $transferParts = array_map('trim', explode(',', $row['transfer']));
    $row['sent'] = ($transferParts[0] ?? '') === '' ? '0 B' : preg_replace('/\s*received$/', '', $transferParts[0]);
    $row['received'] = isset($transferParts[1]) ? preg_replace('/\s*sent$/', '', $transferParts[1]) : '0 B';
    if ($row['sent'] === '') $row['sent'] = '0 B';
    if ($row['received'] === '') $row['received'] = '0 B';

    $row['id'] = (string) $device['_id'];

    $results[] = $row;
}

echo json_encode($results);
