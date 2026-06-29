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
    $current_page = 'domains';
    include __DIR__ . '/partials/lab_header.php'; 
?>

<!-- Modals moved to lab_modals.php -->
    <div class="container-fluid py-3 p-0">
        <!-- Masonry Grid for Domains -->
        <div class="row row-cols-1 row-cols-md-3 g-3" id="masonry-area">
           <?php 
                // Filter DomainManager's map for this specific instance (includes http proxies)
                $instanceDomains = [];
                foreach ($domainUsageMap as $dom => $info) {
                    if (($info['instance_hash'] ?? '') === $fullHash) {
                       $instanceDomains[$dom] = $info;
                    }
                }
                if(!$isRunning || empty($instanceDomains)):
            ?>
                <div class="d-flex justify-content-center align-items-center vh-10 w-100">
                    <div class="card p-3 text-center shadow-lg border-0 rounded-4 bg-dark bg-opacity-25" 
                        style="max-width: 400px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1) !important;">
                        
                        <div class="mb-3 mx-auto">
                            <div class="empty-state-glow"></div>
                            <i class='bx bx-globe text-white opacity-50' style="font-size: 3.5rem;"></i>
                        </div>

                        <h4 class="fw-bold text-white mb-3">No Domains Associated Yet</h4>
                        
                        <p class="text-secondary mb-2 mx-auto small" style="line-height: 1.6; opacity: 0.8;">
                            Deploy this lab to see associated domains here. Once deployed, your domains will be automatically configured and displayed on this page.
                        </p>

                        <button class="btn btn-lg rounded-pill px-4 py-1 fw-bold hover-scale text-white border-0 shadow-sm bg-gradient mt-2" 
                                onclick="handleDeploy(this, '<?= $labType ?>')">
                            <i class='bx <?= $isRunning ? 'bx-refresh' : 'bx-cloud-upload' ?> me-2'></i> <?= $isRunning ? 'Redeploy' : 'Deploy' ?> Now
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($instanceDomains as $dom => $info): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm rounded-4 position-relative overflow-hidden bg-dark">
                        <div class="bg-success p-2 d-flex align-items-center gap-2">
                            <i class="bx bx-globe text-white fs-5"></i>
                            <span class="text-white fw-bold"><?= htmlspecialchars($info['usage'] ?? 'Public Exposure') ?></span>
                        </div>
                        
                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="fw-bold mb-3 text-truncate" title="<?= htmlspecialchars($dom) ?>">
                                <a href="https://<?= htmlspecialchars($dom) ?>" target="_blank" class="text-warning text-decoration-none">
                                    <?= htmlspecialchars($dom) ?>
                                </a>
                            </h5>   
                            
                            <div class="d-flex gap-2 mb-4">
                                <span class="badge rounded-pill bg-success px-3">TomLab</span>
                                <span class="badge rounded-pill bg-success px-3">verified</span>
                                <span class="badge rounded-pill bg-success px-3">active</span>
                            </div>

                            <div class="mt-auto">
                                <p class="small text-secondary mb-1 fw-bold">Service: <?= htmlspecialchars($info['lab_type'] ?? $labType) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <div class="card blur">
                    <div class="card-header">
                        <svg class="icon me-2">
                            <use xlink:href="/assets/icons/duotone.svg#cid-info"></use>
                        </svg>
                        <strong>Domain Information</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-2">Domain Types</h6>
                                <ul class="list-unstyled mb-0" style="line-height: 1.6;">
                                    <li class="mb-1">
                                        <span class="badge bg-success-gradient me-2">Port 80 Public</span>
                                        <span class="small">Port 80/443 (Essentials lab)</span>
                                    </li>
                                    <li class="mb-1">
                                        <span class="badge bg-primary-gradient me-2">VS Code Web</span>
                                        <span class="small">Code-server access</span>
                                    </li>
                                    <li class="mb-1">
                                        <span class="badge bg-info-gradient me-2">Public Expose Proxy</span>
                                        <span class="small">Lab services (n8n, MinIO, Node-RED)</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-2">How to Manage</h6>
                                <ul class="small mb-0" style="line-height: 1.6;">
                                    <li class="mb-1"><strong>Redeploy</strong> to change domains</li>
                                    <li class="mb-1">Tom Lab domains auto-configured with SSL</li>
                                    <li class="mb-1">Custom domains need DNS A record</li>
                                    <li class="mb-1"><a href="/domains">Domain Management</a> for more domains</li>
                                </ul>
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
