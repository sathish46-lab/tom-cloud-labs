<?php
    $fullHash = Session::get('full_instance_hash');
    $db = DatabaseConnection::getDefaultDatabase();
    $labData = $db->deployed_labs->findOne(['instance_hash' => $fullHash]);
    
    // CRITICAL FIX: Define missing variables
    $user = Session::getUser();
    $currentUsername = $user->getUsername();

    $labType = 'essentials';
    if ($fullHash === $user->getLabHash('minio')) {
        $labType = 'minio';
    } elseif ($fullHash === $user->getLabHash('n8n')) {
        $labType = 'n8n';
    } elseif ($fullHash === $user->getLabHash('docker_lab')) {
        $labType = 'docker_lab';
    }

    if (!$labData) {
        $status = 'not_deployed';
    } else {
        $labType = $labData['lab_type'] ?? $labType;
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
        ],
        'docker_lab' => [
            'title'   => 'Tom Docker Lab',
            'desc'    => 'Ubuntu 24.10 environment equipped with full Docker-in-Docker capabilities.',
            'icon'    => 'bxl-docker',
            'color'   => '#2496ed',
            'action'  => 'Code',
            'action_icon' => 'bx-code-alt'
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
        <!-- Grid for Domains -->
        <div class="row row-cols-1 row-cols-md-3 g-4 align-items-start" id="masonry-area" data-masonry='{"percentPosition": true }'>
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
                <?php 
                    $usageStr = $info['usage'] ?? 'Public Exposure';
                    $isProxy = false;
                    $portStr = '';
                    $usageLabel = 'Port 80 Public';
                    $headerBg = '#22c55e'; // Green
                    $headerIcon = 'bx-globe';
                    $borderColor = 'rgba(34, 197, 94, 0.3)';

                    if (strpos($usageStr, 'HTTP Proxy') !== false) {
                        $isProxy = true;
                        $usageLabel = 'Your Proxy';
                        $headerBg = '#3b82f6'; // Blue
                        $headerIcon = 'bx-share';
                        $borderColor = 'rgba(59, 130, 246, 0.3)';
                        if (preg_match('/Port\s+(\d+)/', $usageStr, $matches)) {
                            $portStr = $matches[1];
                        }
                    } elseif (strpos($usageStr, 'VS Code Web') !== false) {
                        $usageLabel = 'VS Code Editor';
                        $headerBg = '#a855f7'; // Purple
                        $headerIcon = 'bx-code-alt';
                        $borderColor = 'rgba(168, 85, 247, 0.3)';
                    } elseif (strpos($usageStr, 'MinIO') !== false || strpos($usageStr, 'S3 API') !== false) {
                        $usageLabel = $usageStr;
                        $headerBg = '#eab308'; // Yellow
                        $headerIcon = 'bx-hdd';
                        $borderColor = 'rgba(234, 179, 8, 0.3)';
                    }
                    
                    // Determine if custom domain (simple check: does it not contain tomweb.fun or selfmade?)
                    $isCustom = (strpos($dom, 'tomweb') === false && strpos($dom, 'selfmade') === false && strpos($dom, 'zeal') === false);
                    $domainBadge = $isCustom ? 'custom' : 'selfmade';
                    $domainBadgeBg = $isCustom ? '#f59e0b' : '#22c55e';
                ?>
                <div class="col">
                    <div class="card border-0 rounded-4 position-relative overflow-hidden" style="background: rgba(20, 20, 20, 0.8); border: 1px solid <?= $borderColor ?> !important; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                        <div class="px-3 py-2 d-flex align-items-center gap-2" style="background: <?= $headerBg ?>;">
                            <i class="bx <?= $headerIcon ?> text-white fs-6"></i>
                            <span class="text-white fw-bold small"><?= htmlspecialchars($usageLabel) ?></span>
                        </div>
                        
                        <div class="card-body p-3 d-flex flex-column">
                            <h6 class="fw-bold mb-2" style="word-break: break-all;">
                                <a href="https://<?= htmlspecialchars($dom) ?>" target="_blank" class="text-decoration-none" style="color: #a5b4fc; font-size: 1rem;">
                                    <?= htmlspecialchars($dom) ?>
                                </a>
                            </h6>   
                            
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="badge rounded-pill" style="background: <?= $domainBadgeBg ?>; color: <?= $isCustom ? '#000' : '#fff' ?>; padding: 0.2rem 0.5rem; font-size: 0.65rem; letter-spacing: 0.5px;"><?= $domainBadge ?></span>
                                <span class="badge rounded-pill" style="background: #06b6d4; color: #000; padding: 0.2rem 0.5rem; font-size: 0.65rem; letter-spacing: 0.5px;">verified</span>
                                <span class="badge rounded-pill" style="background: #14b8a6; color: #000; padding: 0.2rem 0.5rem; font-size: 0.65rem; letter-spacing: 0.5px;">active</span>
                            </div>

                            <div class="mt-auto">
                                <div class="mb-1">
                                    <span class="small text-secondary fw-bold d-block" style="font-size: 0.7rem;">Service:</span>
                                    <span class="small fw-bold" style="color: #06b6d4; font-size: 0.75rem;">TomCloudLab</span>
                                </div>
                                <?php if ($isProxy && !empty($portStr)): ?>
                                <div>
                                    <span class="small text-secondary fw-bold d-block" style="font-size: 0.7rem;">Port:</span>
                                    <span class="small fw-bold" style="color: #06b6d4; font-size: 0.75rem;"><?= htmlspecialchars($portStr) ?></span>
                                </div>
                                <?php endif; ?>
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
<?php include __DIR__ . '/partials/server_logs.php'; ?>

<script>
    (function() {
        var initDomainsMasonry = function() {
            var grid = document.querySelector('#masonry-area');
            if (grid && typeof Masonry !== 'undefined') {
                new Masonry(grid, {
                    percentPosition: true
                });
            }
        };
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDomainsMasonry);
        } else {
            initDomainsMasonry();
        }
        // Fallback for dynamic content/images
        setTimeout(initDomainsMasonry, 500);
    })();
</script>
