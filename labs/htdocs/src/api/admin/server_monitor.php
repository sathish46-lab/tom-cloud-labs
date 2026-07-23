<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}
$user = Session::getUser();
if ($user->getRole() !== 'superuser') {
    echo json_encode(['status' => 'error', 'error' => 'Forbidden']); exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'processes';

function runCmd($cmd) {
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    return ['output' => implode("\n", $output), 'exitCode' => $exitCode];
}

function parseProcessLine($line) {
    $parts = preg_split('/\s+/', trim($line), 11);
    if (count($parts) < 11) return null;
    if ($parts[0] === 'USER') return null;
    return [
        'user'    => $parts[0],
        'pid'     => (int)$parts[1],
        'cpu'     => (float)$parts[2],
        'mem'     => (float)$parts[3],
        'vsz'     => (int)$parts[4],
        'rss'     => (int)$parts[5],
        'stat'    => $parts[6],
        'start'   => $parts[7],
        'time'    => $parts[8],
        'command' => $parts[10]
    ];
}

switch ($action) {
    case 'processes':
        $container = $_GET['container'] ?? 'Dev_lab';
        $allowed = ['Dev_lab', 'TomCloudLab_mongodb', 'TomCloudLab', 'TomCloudLab_cloudflared', 'TomCloudLab_ddns_updater'];
        if (!in_array($container, $allowed)) {
            echo json_encode(['status' => 'error', 'error' => 'Invalid container']); exit;
        }

        $isCurrentContainer = ($container === 'Dev_lab');

        if ($isCurrentContainer) {
            $result = runCmd("ps aux --sort=-%mem");
            $memResult = runCmd("free -m | grep Mem");
            $loadResult = runCmd("cat /proc/loadavg");
            $uptimeResult = runCmd("uptime -p");
        } else {
            $escapedContainer = escapeshellarg($container);
            $result = runCmd("docker exec {$escapedContainer} ps aux --sort=-%mem");
            $memResult = runCmd("docker exec {$escapedContainer} free -m | grep Mem");
            $loadResult = runCmd("docker exec {$escapedContainer} cat /proc/loadavg");
            $uptimeResult = runCmd("docker exec {$escapedContainer} uptime -p");
        }

        $lines = explode("\n", $result['output']);
        $processes = [];
        foreach ($lines as $line) {
            $proc = parseProcessLine($line);
            if ($proc) $processes[] = $proc;
        }

        $memParts = preg_split('/\s+/', trim($memResult['output']));
        $memInfo = [
            'total'     => (int)($memParts[1] ?? 0),
            'used'      => (int)($memParts[2] ?? 0),
            'free'      => (int)($memParts[3] ?? 0),
            'available' => (int)($memParts[6] ?? 0),
        ];

        $loadParts = explode(' ', trim($loadResult['output']));
        $loadAvg = [
            '1min'  => (float)($loadParts[0] ?? 0),
            '5min'  => (float)($loadParts[1] ?? 0),
            '15min' => (float)($loadParts[2] ?? 0),
        ];

        $uptimeLines = explode("\n", trim($uptimeResult['output']));
        $uptime = trim($uptimeLines[0] ?? '--');

        echo json_encode([
            'status' => 'success',
            'data' => [
                'processes' => $processes,
                'memory'    => $memInfo,
                'load'      => $loadAvg,
                'uptime'    => $uptime,
                'container' => $container,
            ]
        ]);
        break;

    case 'services':
        $container = $_GET['container'] ?? 'Dev_lab';
        $allowed = ['Dev_lab', 'TomCloudLab_mongodb'];
        if (!in_array($container, $allowed)) {
            echo json_encode(['status' => 'error', 'error' => 'Invalid container']); exit;
        }

        $isCurrentContainer = ($container === 'Dev_lab');
        $services = ['mysql', 'rabbitmq-server', 'apache2', 'redis-server', 'fail2ban', 'docker'];
        if ($container === 'TomCloudLab_mongodb') {
            $services = ['mongodb'];
        }

        $results = [];
        foreach ($services as $svc) {
            if ($isCurrentContainer) {
                $statusResult = runCmd("sudo service {$svc} status 2>&1 | head -5");
            } else {
                $escapedContainer = escapeshellarg($container);
                $statusResult = runCmd("docker exec {$escapedContainer} service {$svc} status 2>&1 | head -5");
            }
            $isActive = (strpos($statusResult['output'], 'active (running)') !== false);

            if (!$isActive) {
                $procMap = [
                    'mysql' => 'mysqld',
                    'mongodb' => 'mongod',
                    'rabbitmq-server' => 'beam.smp',
                    'redis-server' => 'redis-server',
                    'fail2ban' => 'fail2ban',
                    'docker' => 'dockerd',
                    'apache2' => 'apache2',
                ];
                $procName = $procMap[$svc] ?? $svc;
                if ($isCurrentContainer) {
                    $procCheck = runCmd("pgrep -x {$procName} 2>/dev/null");
                } else {
                    $escapedContainer = escapeshellarg($container);
                    $procCheck = runCmd("docker exec {$escapedContainer} pgrep -x {$procName} 2>/dev/null");
                }
                $isActive = !empty(trim($procCheck['output']));
            }

            $results[] = [
                'name'   => $svc,
                'active' => $isActive,
                'raw'    => trim(substr($statusResult['output'], 0, 200)),
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $results]);
        break;

    case 'toggle_service':
        $container = $_POST['container'] ?? 'Dev_lab';
        $service   = $_POST['service'] ?? '';
        $enable    = $_POST['enable'] ?? '1';
        $allowed = ['Dev_lab', 'TomCloudLab_mongodb'];
        if (!in_array($container, $allowed)) {
            echo json_encode(['status' => 'error', 'error' => 'Invalid container']); exit;
        }
        $allowedServices = ['mysql', 'mongodb', 'rabbitmq-server', 'apache2', 'redis-server', 'fail2ban', 'docker'];
        if (!in_array($service, $allowedServices)) {
            echo json_encode(['status' => 'error', 'error' => 'Invalid service']); exit;
        }

        $isCurrentContainer = ($container === 'Dev_lab');

        if ($service === 'mongodb') {
            if ($enable === '1') {
                $cmd = "sudo mongod --config /etc/mongod.conf --fork 2>&1";
                $stopCmd = "sudo killall mongod 2>&1";
            } else {
                $cmd = "sudo killall mongod 2>&1";
                $stopCmd = $cmd;
            }
        } else {
            $cmd = ($enable === '1') ? "sudo service {$service} start" : "sudo service {$service} stop";
        }

        if ($isCurrentContainer) {
            $result = runCmd($cmd);
            sleep(2);
            $statusResult = runCmd("sudo service {$service} status 2>&1 | head -3");
        } else {
            $escapedContainer = escapeshellarg($container);
            if ($service === 'mongodb') {
                $result = runCmd("docker exec {$escapedContainer} " . ($enable === '1' ? "mongod --config /etc/mongod.conf --fork 2>&1" : "killall mongod 2>&1"));
            } else {
                if ($enable === '1') {
                    $result = runCmd("docker exec {$escapedContainer} service {$service} start");
                } else {
                    $result = runCmd("docker exec {$escapedContainer} service {$service} stop");
                }
            }
            sleep(2);
            $statusResult = runCmd("docker exec {$escapedContainer} service {$service} status 2>&1 | head -3");
        }

        $isActive = (strpos($statusResult['output'], 'active (running)') !== false);

        if (!$isActive) {
            $procMap = [
                'mysql' => 'mysqld',
                'mongodb' => 'mongod',
                'rabbitmq-server' => 'beam.smp',
                'redis-server' => 'redis-server',
                'fail2ban' => 'fail2ban',
                'docker' => 'dockerd',
                'apache2' => 'apache2',
            ];
            $procName = $procMap[$service] ?? $service;
            if ($isCurrentContainer) {
                $procCheck = runCmd("pgrep -x {$procName} 2>/dev/null");
            } else {
                $escapedContainer = escapeshellarg($container);
                $procCheck = runCmd("docker exec {$escapedContainer} pgrep -x {$procName} 2>/dev/null");
            }
            $isActive = !empty(trim($procCheck['output']));
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'service' => $service,
                'active'  => $isActive,
                'message' => $result['output'],
            ]
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'error' => 'Unknown action']);
}
