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
            'icon'    => Session::cdn3('logo/logo.png'),
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
    $sudoPass = $creds['su_pass'] ?? $creds['password'] ?? "********";
    // Handle staged preferences for input fields
    $staged = $labData['staged_preferences'] ?? [];
    $stagedPasswordNames = [];
    
    if (!empty($staged['su_pass']) && $staged['su_pass'] !== $sudoPass) {
        $sudoPassInput = $staged['su_pass'];
        $stagedPasswordNames[] = '<span class="fw-bold" style="color: cyan;">Sudo Password</span>';
    } else {
        $sudoPassInput = $sudoPass;
    }

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
                                <input type="password" id="sudo-pass-input" class="form-control bg-transparent border-0 text-white fw-bold font-monospace" value="<?= htmlspecialchars($sudoPassInput) ?>">
                                <div class="d-flex align-items-center gap-1 pe-1">
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="togglePasswordVisibility('sudo-pass-input', this)">
                                        <i class='bx bx-hide fs-5'></i>
                                    </button>
                                    <div class="vr bg-secondary opacity-25" style="height: 18px;"></div>
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="generateNewPassword('sudo-pass-input')">
                                        <i class='bx bx-refresh fs-5'></i>
                                    </button>
                                    <div class="vr bg-secondary opacity-25" style="height: 18px;"></div>
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="copyFromInput('sudo-pass-input')">
                                        <i class='bx bx-copy fs-5'></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-text small opacity-50 mt-2 ms-2"><i class='bx bx-info-circle me-1'></i>Used for root access within the terminal.</div>
                        </div>

                        <?php 
                            $codeServerPass = $creds['code_server_pass'] ?? $creds['password'] ?? null;
                            if($codeServerPass): 
                                $codeServerPassInput = (!empty($staged['code_server_pass']) && $staged['code_server_pass'] !== $codeServerPass) ? $staged['code_server_pass'] : $codeServerPass;
                                if ($codeServerPassInput !== $codeServerPass) {
                                    $stagedPasswordNames[] = '<span class="fw-bold" style="color: cyan;">Code-Server Password</span>';
                                }
                        ?>
                        <div class="mb-4">
                            <label class="small fw-bold text-secondary mb-2 text-uppercase ls-1">Code-Server Password</label>
                            <div class="input-group p-1 bg-dark bg-opacity-50 rounded-pill border border-secondary border-opacity-25">
                                <span class="input-group-text bg-transparent border-0 text-secondary ps-3"><i class='bx bx-code-alt'></i></span>
                                <input type="password" id="code-server-pass-input" class="form-control bg-transparent border-0 text-white fw-bold font-monospace" value="<?= htmlspecialchars($codeServerPassInput) ?>">
                                <div class="d-flex align-items-center gap-1 pe-1">
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="togglePasswordVisibility('code-server-pass-input', this)">
                                        <i class='bx bx-hide fs-5'></i>
                                    </button>
                                    <div class="vr bg-secondary opacity-25" style="height: 18px;"></div>
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="generateNewPassword('code-server-pass-input')">
                                        <i class='bx bx-refresh fs-5'></i>
                                    </button>
                                    <div class="vr bg-secondary opacity-25" style="height: 18px;"></div>
                                    <button type="button" class="btn btn-link text-secondary p-0 d-flex align-items-center justify-content-center" 
                                            style="width: 32px; height: 32px; text-decoration: none;"
                                            onclick="copyFromInput('code-server-pass-input')">
                                        <i class='bx bx-copy fs-5'></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-text small opacity-50 mt-2 ms-2"><i class='bx bx-info-circle me-1'></i>Password for the web-based VS Code environment.</div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($stagedPasswordNames)): ?>
                        <?php $changedNamesStr = implode(' and ', $stagedPasswordNames); ?>
                        <div class="d-flex align-items-center p-3 mb-0 mt-3 border rounded-3" style="background-color: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.3) !important;">
                            <i class='bx bx-error-circle fs-4 me-3 text-warning'></i>
                            <div class="small text-warning fw-medium" style="line-height: 1.5;">
                                Your <?= $changedNamesStr ?> is not updated in your lab. You need to redeploy for changes to take effect. The currently active password is shown in your dashboard.
                            </div>
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
        
        <!-- Side-by-Side: HTTP Proxies & Lifecycle Section -->
        <div class="row mt-4">
            <!-- Left Side: Lifecycle -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm glass-card rounded-4 mb-4" style="height: calc(100% - 1.5rem);">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                        <h6 class="fw-bold mb-1 text-uppercase ls-1 small">Lifecycle</h6>
                    </div>
                    <div class="card-body p-4 pt-2 d-flex flex-column justify-content-between">
                        <div>
                            <div class="form-check form-switch mb-3">
                                <?php $alwaysOn = ($labData && isset($labData['always_on'])) ? (bool)$labData['always_on'] : false; ?>
                                <input class="form-check-input" type="checkbox" id="always-on-toggle" <?= $alwaysOn ? 'checked' : '' ?> style="transform: scale(1.1); margin-right: 8px;">
                                <label class="form-check-label fw-bold small" for="always-on-toggle">Keep this instance running (Always-on)</label>
                            </div>
                            <p class="text-body-secondary small mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                                When enabled, a background worker monitors this lab and automatically restarts it within ~10 minutes if it stops. The instance will not auto-expire.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: HTTP Proxies -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm glass-card rounded-4 mb-4" style="height: calc(100% - 1.5rem);">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                        <h6 class="fw-bold mb-1">HTTP Proxies</h6>
                        <p class="small text-muted mb-0">Reverse-proxy ports to your domains over HTTP. TLS is terminated at the edge.</p>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <!-- Headers -->
                        <div class="row align-items-center mb-2 text-muted small fw-bold d-none d-md-flex px-1">
                            <div class="col-md-4">Local Port</div>
                            <div class="col-md-7">Target Domain</div>
                            <div class="col-md-1"></div>
                        </div>

                        <div id="http-proxies-list">
                            <?php
                                $httpProxies = [];
                                if ($labData && isset($labData['http_proxies'])) {
                                    $httpProxies = (array)$labData['http_proxies'];
                                }
                                // Fetch user's domains for the select dropdown
                                $userDomains = [];
                                $domainsCursor = $db->domains->find(['user_id' => $user->getUserId()]);
                                foreach ($domainsCursor as $d) {
                                    $userDomains[] = (string)$d['domain'];
                                }
                                
                                if (empty($httpProxies)):
                            ?>
                            <div class="row align-items-center mb-3 proxy-row" data-index="0">
                                <div class="col-md-4 col-12 mb-2 mb-md-0">
                                    <input type="number" name="proxy_port[]" class="form-control bg-dark bg-opacity-50 rounded-pill border-secondary border-opacity-25 text-white px-3 proxy-port" placeholder="Port (e.g. 8080)" min="1" max="65535">
                                </div>
                                <div class="col-md-7 col-10">
                                    <select name="proxy_domain[]" class="form-select bg-dark bg-opacity-50 rounded-pill border-secondary border-opacity-25 text-white px-3 proxy-domain-select">
                                        <option value="">Select Domain...</option>
                                        <?php foreach ($userDomains as $ud): ?>
                                            <option value="<?= htmlspecialchars($ud) ?>"><?= htmlspecialchars($ud) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1 col-2 d-flex justify-content-end">
                                    <button type="button" class="btn rounded-circle d-flex align-items-center justify-content-center p-0 btn-remove-proxy" style="width: 36px; height: 36px; border: 1px solid #be185d; color: #be185d; background: transparent;" onclick="removeProxyRow(this)">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                                <?php foreach ($httpProxies as $idx => $proxy): ?>
                                <div class="row align-items-center mb-3 proxy-row" data-index="<?= $idx ?>">
                                    <div class="col-md-4 col-12 mb-2 mb-md-0">
                                        <input type="number" name="proxy_port[]" class="form-control bg-dark bg-opacity-50 rounded-pill border-secondary border-opacity-25 text-white px-3 proxy-port" placeholder="Port (e.g. 8080)" min="1" max="65535" value="<?= (int)($proxy['port'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-7 col-10">
                                        <select name="proxy_domain[]" class="form-select bg-dark bg-opacity-50 rounded-pill border-secondary border-opacity-25 text-white px-3 proxy-domain-select">
                                            <option value="">Select Domain...</option>
                                            <?php foreach ($userDomains as $ud): ?>
                                                <option value="<?= htmlspecialchars($ud) ?>" <?= ((string)($proxy['domain'] ?? '') === $ud) ? 'selected' : '' ?>><?= htmlspecialchars($ud) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-1 col-2 d-flex justify-content-end">
                                        <button type="button" class="btn rounded-circle d-flex align-items-center justify-content-center p-0 btn-remove-proxy" style="width: 36px; height: 36px; border: 1px solid #be185d; color: #be185d; background: transparent;" onclick="removeProxyRow(this)">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn rounded-pill px-4 py-1.5 d-inline-flex align-items-center gap-2" style="border: 1px solid #ea580c; color: #ea580c; background: transparent; font-size: 0.9rem;" onclick="addProxyRow()">
                                <i class='bx bx-message-square-add'></i> Add HTTP Proxy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Startup Script Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card border-0 shadow-sm glass-card rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-start">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class='bx bx-terminal text-muted'></i>
                                <span class="fw-bold small">Startup Script</span>
                                <span class="text-muted small font-monospace">/home/<?= htmlspecialchars($currentUsername) ?>/init.sh</span>
                            </div>
                        </div>
                        <span class="text-muted small fst-italic">Runs as root on every (re)deploy</span>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <?php
                            $initScript = '';
                            if ($labData && isset($labData['init_script'])) {
                                $initScript = (string)$labData['init_script'];
                            }
                            if (empty($initScript)) {
                                $initScript = "#!/bin/bash\n";
                            }
                        ?>
                        <div class="position-relative">
                            <textarea id="init-script-editor" class="form-control font-monospace bg-dark text-white border-0 rounded-3 p-3" rows="10" style="resize: vertical; font-size: 13px; line-height: 1.6; tab-size: 4;"><?= htmlspecialchars($initScript) ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-1" style="background: #16a34a; color: #fff; font-size: 0.85rem;" onclick="runInitScript()">
                                <i class='bx bx-play'></i> Run now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save / Apply Buttons -->
        <div class="row mt-2 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm glass-card rounded-4 p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; min-width: 36px; background: rgba(234, 179, 8, 0.15);">
                                <i class='bx bx-info-circle fs-4'></i>
                            </div>
                            <div style="max-width: 600px;">
                                <h6 class="mb-1 fw-bold text-warning" style="font-size: 0.85rem; letter-spacing: 0.5px; text-transform: uppercase;">Fast Apply Note</h6>
                                <p class="text-body-secondary mb-0 small" style="font-size: 0.82rem; line-height: 1.45;">
                                    Applying preferences updates HTTP proxy routes and runs <code class="text-warning font-monospace bg-dark px-1.5 py-0.5 rounded">init.sh</code> immediately. This action does not perform a slow, full container redeployment.
                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mt-3 mt-md-0 align-self-end align-self-md-center">
                            <button type="button" id="btn-save-preferences" class="btn btn-secondary rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 fw-bold" style="font-size: 0.9rem;" onclick="savePreferences()">
                                <i class='bx bx-save'></i> Save Preferences
                            </button>
                            <button type="button" id="btn-apply-redeploy" class="btn rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 fw-bold" style="background: #eab308; color: #1a1a1a; font-size: 0.9rem; border: none;" onclick="applyAndRedeploy()">
                                <i class='bx bx-refresh'></i> Apply & Redeploy now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

<!-- Inject user domains as JS array for dynamic proxy rows -->
<script>
    window.USER_DOMAINS = <?= json_encode($userDomains) ?>;
    window.EXISTING_PROXIES = <?= json_encode($httpProxies) ?>;
</script>

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