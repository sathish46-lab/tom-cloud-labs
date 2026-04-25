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
            'action'  => 'Launch',
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
    $current_page = 'preferences';
    include __DIR__ . '/partials/lab_header.php'; 
?>

<!-- Modals moved to lab_modals.php -->
    <div class="container-fluid py-3 p-0">
        <div class="row g-4">
            <div class="col-lg-6">
                 <div class="card border-0 shadow-sm glass-card rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">Security & Access <span class="small text-muted ms-1">Passwords</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="small fw-bold text-secondary mb-2 text-uppercase ls-1">Sudo Password</label>
                            <div class="input-group p-1 bg-dark bg-opacity-50 rounded-pill border border-secondary border-opacity-25">
                                <span class="input-group-text bg-transparent border-0 text-secondary ps-3"><i class='bx bx-key'></i></span>
                                <input type="text" class="form-control bg-transparent border-0 text-white fw-bold font-monospace" value="<?= htmlspecialchars($sudoPass) ?>" readonly>
                                <button class="btn btn-dark rounded-circle m-1 d-flex align-items-center justify-content-center" 
                                        style="width: 32px; height: 32px;"
                                        onclick="copyText('<?= htmlspecialchars($sudoPass) ?>')">
                                    <i class='bx bx-copy'></i>
                                </button>
                            </div>
                            <div class="form-text small opacity-50 mt-2 ms-2"><i class='bx bx-info-circle me-1'></i>Used for root access within the terminal.</div>
                        </div>

                        <?php if($creds && isset($creds['code_server_password'])): ?>
                        <div class="mb-4">
                            <label class="small fw-bold text-secondary mb-2 text-uppercase ls-1">Code-Server Password</label>
                            <div class="input-group p-1 bg-dark bg-opacity-50 rounded-pill border border-secondary border-opacity-25">
                                <span class="input-group-text bg-transparent border-0 text-secondary ps-3"><i class='bx bx-code-alt'></i></span>
                                <input type="text" class="form-control bg-transparent border-0 text-white fw-bold font-monospace" value="<?= htmlspecialchars($creds['code_server_password']) ?>" readonly>
                                <button class="btn btn-dark rounded-circle m-1 d-flex align-items-center justify-content-center" 
                                        style="width: 32px; height: 32px;"
                                        onclick="copyText('<?= htmlspecialchars($creds['code_server_password']) ?>')">
                                    <i class='bx bx-copy'></i>
                                </button>
                            </div>
                            <div class="form-text small opacity-50 mt-2 ms-2"><i class='bx bx-info-circle me-1'></i>Password for the web-based VS Code environment.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
             <div class="col-lg-6">
                 <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">Lab Configuration <span class="small text-muted ms-1">Environment</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Lab configuration Load place  -->
                        <div class="d-flex flex-column gap-3">
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