<?php
    $fullHash = Session::get('full_instance_hash');
    $db = DatabaseConnection::getDefaultDatabase();
    $labData = $db->deployed_labs->findOne(['instance_hash' => $fullHash]);
    
    // CRITICAL FIX: Define missing variables
    $user = Session::getUser();
    $currentUsername = $user->getUsername();

    if (!$labData) {
        if ($fullHash === $user->getLabHash('minio')) {
            $labType = 'minio';
        } elseif ($fullHash === $user->getLabHash('n8n')) {
            $labType = 'n8n';
        } else {
            $labType = 'essentials';
        }
        $status = 'not_deployed';
    } else {
        $labType = $labData['lab_type'] ?? 'essentials';
        $status = $labData['status'] ?? 'offline';
    }
    
    // Define isRunning for the status dot and buttons
    $isRunning = ($status === 'running');

    // 2. UI CONFIGS
    $uiConfigs = [
        'essentials' => [
            'title'   => 'Essentials Lab',
            'desc'    => 'Ubuntu 24.10 environment for general development.',
            'icon'    => 'bxl-tux',
            'color'   => '#E95420',
            'action'  => 'Code',
            'action_icon' => 'bx-code-alt'
        ],
        'minio' => [
            'title'   => 'MinIO S3 Storage',
            'desc'    => 'MinIO is a high-performance, S3-compatible object storage solution for machine learning, analytics, and application data workloads, released under the GNU AGPL v3.0.',
            'icon'    => Session::cdn3('icons/minio_avatar_small.png'),
            // 'icon' => 'bxl-docker',
            'color'   => '#00a6e0',
            'action'  => ' Launch',
            'action_icon' => 'bx-cloud'
        ],
        'n8n' => [
            'title'   => 'n8n Workflow Automation',
            'desc'    => 'n8n is an extendable workflow automation tool that enables you to connect anything to everything via its open, fair-code model.',
            'icon'    => 'bx-git-repo-forked',
            'color'   => '#ea4b71',
            'action'  => ' Launch',
            'action_icon' => 'bx-network-chart'
        ]
    ];

    $cfg = $uiConfigs[$labType] ?? $uiConfigs['essentials'];
    $creds = $labData['credentials'] ?? null;
    $deviceIp = isset($labData['internal_ip']) ? $labData['internal_ip'] : "0.0.0.0";
    $sshCommand = ($isRunning && isset($creds['tunnel_ip'])) ? "ssh " . $currentUsername . "@" . $creds['tunnel_ip'] : "#";
    $sudoPass = $creds['password'] ?? "********";

    // Lab configuration Load place
    $configData = (array)$labData;
    if (empty($configData['instance_hash'])) {
        $configData['instance_hash'] = $fullHash;
    }
    $labConfig = \TomLabs\Labs\LabTemplateConfig::getTemplate($labType, $configData, $currentUsername);
    
    // REUSABLE: Get domain usage map from DomainManager (works for ALL lab types)
    $dm = new DomainManager();
    $domainUsageMap = $dm->getDomainUsageMap($user->getUserId());
?>

<script>
    window.SESSION_HASH = "<?= $fullHash ?>";
    window.LAB_USER = "<?= htmlspecialchars($currentUsername) ?>";
    window.CODE_SERVER_URL = "<?= $creds['code_server_url'] ?? '' ?>";
    // CRITICAL: Set the global lab type
    window.LAB_TYPE = "<?= $labType ?>"; 
    // Inject Lab Config for JS Access
    window.LAB_CONFIG = <?= json_encode($labConfig) ?>;
    // CRITICAL: Inject cross-lab domain usage map
    window.DOMAIN_USAGE_MAP = <?= json_encode($domainUsageMap) ?>;
</script>

<?php 
    $current_page = 'dashboard';
    include __DIR__ . '/partials/lab_header.php'; 
?>

<!-- Modals moved to lab_modals.php -->
    <div class="container-fluid  py-3 p-0">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4 border-0 shadow-sm rounded-4" style="background-color: var(--cui-card-bg);">
                    <div class="card-header bg-transparent border-0 pt-4 ">
                        <h6 class="fw-bold mb-0 text-body-emphasis">Lab Information <span
                            class="small text-body-secondary ms-1">Readme</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <?php if($isRunning && $creds): ?>
                            <p class="text-body-secondary small mb-4 animate__animated animate__fadeIn">
                                Access your lab environment using the credentials below. Ensure you are connected to VPN.
                            </p>

                            <!-- Lab configuration Load place  -->

                            <div class="d-flex flex-column gap-3 animate__animated animate__fadeIn">
                                <?php foreach($labConfig['fields'] as $field): ?>
                                    <div class="row align-items-center">
                                        <div class="col-4 text-body-emphasis small fw-bold">
                                            <?php if(isset($field['icon'])): ?>
                                                <i class='bx <?= $field['icon'] ?> me-1'></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($field['label']) ?>
                                        </div>
                                        <div class="col-8">
                                            <?php if($field['type'] === 'link'): ?>
                                                <a href="<?= htmlspecialchars($field['value']) ?>" target="_blank" class="text-decoration-none small fw-bold">
                                                    <?= htmlspecialchars($field['value']) ?> <i class='bx bx-link-external ms-1'></i>
                                                </a>
                                            <?php else: ?>
                                                <div class="input-group input-group-sm">
                                                    <input type="<?= $field['type'] === 'password' ? 'password' : 'text' ?>" 
                                                        class="form-control rounded-pill border-secondary bg-body-tertiary text-body px-3 <?= isset($field['mono']) ? 'font-monospace' : '' ?>" 
                                                        value="<?= htmlspecialchars($field['value']) ?>" 
                                                        readonly style="opacity: 0.85;">
                                                    
                                                    <?php if(isset($field['copy']) && $field['copy']): ?>
                                                        <button class="btn btn-outline-secondary ms-2 rounded-pill px-3" 
                                                                onclick="copyText('<?= htmlspecialchars($field['value']) ?>')">
                                                            <i class='bx bx-copy'></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>
                            <p>This lab is not running, please deploy it to get the connection information.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">IO Stats <span class="small text-muted ms-1">Net and Block</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-2">Net IO</div>
                                    <div class="fw-bold text-white mb-2" id="stat-net-io">0B / 0B</div>
                                    <div style="height:40px;">
                                        <canvas id="chart-net-io"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-2">Block IO</div>
                                    <div class="fw-bold text-white mb-2" id="stat-block-io">0B / 0B</div>
                                    <div style="height:40px;">
                                        <canvas id="chart-block-io"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4 border-0 shadow-sm glass-card rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">
                        Container Load
                        <?php if ($isRunning): ?>
                            <span class="badge bg-success rounded-pill ms-2 pulse">Live</span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill ms-2">Offline</span>
                        <?php endif; ?>
                    </h6>

                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 text-start stat-card-inner">
                                    <div class="mb-1">
                                        <span class="small fw-bold text-white text-start">
                                            <span id="stat-cpu-usage">0.00%</span> <small class="text-muted ms-1">CPU LOAD</small>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-info" id="stat-cpu-bar" style="width: 0%"></div>
                                    </div>
                                    <div class="small text-muted mt-2 text-start" id="stat-pid-container" style="display: <?= $isRunning ? 'block' : 'none' ?>;">PID Count: <span id="stat-pid-count">0</span></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 text-start stat-card-inner">
                                    <div class="mb-1">
                                        <span class="small fw-bold text-white text-start">
                                            <span id="stat-mem-perc">0.00%</span> <small class="text-muted ms-1">MEMORY USAGE</small>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.1);">
                                        <div class="progress-bar bg-warning" id="stat-mem-bar" style="width: 0%"></div>
                                    </div>
                                    <div class="small text-muted mt-2 text-start" id="stat-mem-info"> </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">1 Min Avg
                                    </div>
                                    <div class="fw-bold text-white small" id="stat-load-1">0.0000</div>
                                    <div style="height:35px;">
                                        <canvas id="chart-avg-1"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">5 Min Avg
                                    </div>
                                    <div class="fw-bold text-white small" id="stat-load-5">0.0000</div>
                                    <div style="height:35px;">
                                        <canvas id="chart-avg-5"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">15 Min Avg
                                    </div>
                                    <div class="fw-bold text-white small" id="stat-load-15">0.0000</div>
                                    <div style="height:35px;">
                                        <canvas id="chart-avg-15"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0 ">Load History <span class="small text-muted ms-1">One Hour</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-4 text-center">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">CPU Peak
                                    </div>
                                    <div class="fw-bold text-white" id="stat-peak-cpu">0.00%</div>
                                    <div class="mt-2" style="height:40px;">
                                        <canvas id="chart-peak-cpu"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">PID Max
                                    </div>
                                    <div class="fw-bold text-white" id="stat-max-pid">0</div>
                                    <div class="mt-2" style="height:40px;">
                                        <canvas id="chart-max-pid"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="p-3 rounded-4 bg-dark bg-opacity-25 border border-white border-opacity-10 h-100 text-center stat-card-inner">
                                    <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 9px;">Memory High
                                    </div>
                                    <div class="fw-bold text-white" id="stat-high-mem">0.00 MB</div>
                                    <div class="mt-2" style="height:40px;">
                                        <canvas id="chart-high-mem"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/partials/lab_modals.php'; ?>
<div class="server-logs-panel shadow-lg">
    <div class="logs-header">
        <div class="logs-title d-flex align-items-center gap-2">
            <i class='bx bx-terminal fs-5'></i>
            <i class="bx bxs-circle" id="mq-status-dot" style="font-size: 8px;"></i>
            <span class="small fw-bold ls-1 opacity-75">Server Logs</span>
            
            <div class="terminal-info-wrapper ms-1">
                <i class='bx bx-info-circle opacity-50' style="font-size: 14px;"></i>
                <div class="terminal-tooltip">
                    You cannot type anything here, this is a terminal to watch server logs
                </div>
            </div>
        </div>
    </div>
    <div class="logs-body" id="terminal-viewport" style="overflow-y: auto;">
        <div id="live-logs-container" class="small"></div>
    </div>
</div>