<!-- VS Code Launch Modal -->
<div class="modal fade" id="vscModal" tabindex="-1" aria-labelledby="vscModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4" >
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="vscModalLabel">Visual Studio Code on Web</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <div class="mb-4">
                    <p class=" small fw-bold mb-2">What can you do here?</p>
                    <ul class="text-secondary small mb-0 ps-3">
                        <li>Code Effortlessly on Browser</li>
                        <li>Browse the filesystem and do CRUD</li>
                        <li>Access linux shell CLI</li>
                        <li>Develop effortlessly on the go</li>
                    </ul>
                </div>

                <div class="password-section p-3 rounded-3 mb-3" >
                    <p class=" small mb-3">You need this password in the next screen to login to VS Code on Web - Happy Coding!</p>
                    
                    <div class="row align-items-center g-2">
                        <div class="col-4">
                            <span class="text-secondary small fw-bold">Code Server Password</span>
                        </div>
                        <div class="col-8">
                            <div class="input-group input-group-sm">
                                <?php $modalCodeServerPass = $creds['code_server_pass'] ?? $creds['password'] ?? '********'; ?>
                                <input type="password" id="code-server-pass" 
                                       class="form-control border-secondary rounded-start-pill border-opacity-25" 
                                       value="<?= htmlspecialchars($modalCodeServerPass) ?>" readonly>
                                <button class="btn btn-outline-secondary rounded-end-pill px-3" 
                                        onclick="copyValue('code-server-pass')">
                                    <i class='bx bx-copy'></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-secondary small italic mb-0">
                    <span class="fw-bold ">Tip:</span> If there is an error while logging in with the password above or launcher doesn't work, redeploy and try again.
                </p>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 gap-2">
                <button type="button" class="btn btn-success rounded-pill px-4 text-dark fw-bold" 
                        class="btn btn-sm btn-modal-green"
                        onclick="launchCodeIDE(event)"> Launch Code IDE
                </button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" 
                        class="btn btn-sm btn-modal-gray"
                        data-coreui-dismiss="modal">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MinIO Launch Modal -->
<div class="modal fade" id="minioModal" tabindex="-1" aria-labelledby="minioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="minioModalLabel">MinIO Console Access</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <p class="small opacity-75 mb-4">
                    Use the credentials below to log in to the MinIO Console.
                </p>

                <?php 
                    // Extract specific MinIO fields from the config for the modal
                    $minioFields = [];
                    
                    if(isset($labConfig['fields'])) {
                        foreach($labConfig['fields'] as $f) {
                            if($f['label'] === 'MinIO Access Key' || $f['label'] === 'Minio Secret Key') {
                                $minioFields[] = $f;
                            }
                        }
                    }

                    // Helper to clean domains
                    if (!function_exists('cleanDomain')) {
                        function cleanDomain($url) {
                            $d = str_replace(['https://', 'http://'], '', $url);
                            return rtrim($d, '/');
                        }
                    }

                    $creds = $labData['credentials'] ?? [];
                    $hash = $labData['instance_hash'] ?? $fullHash;

                    // 1. Define SYSTEM DEFAULTS
                    $sysConsole = "s3-{$hash}.tomweb.shop";
                    $sysApi = "api-{$hash}.tomweb.shop";

                    // 2. Determine CURRENT CONFIGURATION
                    $currConsole = cleanDomain($creds['minio_url_console'] ?? $sysConsole);
                    $currApi = cleanDomain($creds['minio_url_api'] ?? $sysApi);
                    
                    $correctMinioConsoleUrl = "https://" . $currConsole;
                ?>

                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach($minioFields as $field): ?>
                        <div class="password-section p-3 rounded-3">
                            <label class="small fw-bold text-secondary mb-1"><?= htmlspecialchars($field['label']) ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control border-secondary border-opacity-25 bg-body-tertiary text-body" 
                                       value="<?= htmlspecialchars($field['value']) ?>" readonly>
                                <button class="btn btn-outline-secondary px-3" data-copy="<?= htmlspecialchars($field['value']) ?>">
                                    <i class='bx bx-copy'></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-info border-0 d-flex align-items-center gap-2 small">
                    <i class='bx bx-info-circle fs-5'></i>
                    <span>The console requires HTTPS. Ensure you accept self-signed certificates if prompted.</span>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 gap-2">
                <button type="button" 
                        class="btn btn-primary rounded-pill px-4 fw-bold text-dark d-flex align-items-center gap-2"
                        class="btn btn-sm btn-modal-blue"
                        onclick="launchCodeIDE(event, '<?= htmlspecialchars($correctMinioConsoleUrl) ?>')">
                    <i class='bx bx-window-open'></i> Open Console
                </button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Redeploy Modal -->
<div class="modal fade" id="redeployModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Confirm <?= $isRunning ? 'Redeploy' : 'Deploy' ?>?</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                
                <p class="mb-3 mt-2 modal-section-title">NETWORKING</p>

                <div class="row mb-2 align-items-center">
                    <label class="col-sm-4 small fw-bold text-secondary">Reallocate IP</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" value="<?= $deviceIp ?? '' ?>" readonly>
                    </div>
                </div>

                <div id="vsc_domain_wrapper" class="row mb-3 align-items-center">
                    <label class="col-sm-4 small fw-bold text-secondary">Domain for VS Code Web</label>
                    <div class="col-sm-8">
                        <select id="vsc_domain_selector" class="form-select bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" onchange="updateDomainAvailability()">
                            <?php 
                                $fullHash = $labData['instance_hash'] ?? $fullHash;
                                $currentCodeDomain = $labData['code_domain'] ?? ($fullHash . '.tomweb.shop');
                                $isDefaultSelected = ($currentCodeDomain === ($fullHash . '.tomweb.shop'));
                            ?>
                            <option value="<?= $fullHash ?>.tomweb.shop" <?= $isDefaultSelected ? 'selected' : '' ?>>
                                <?= $fullHash ?>.tomweb.shop
                            </option>
                            <?php 
                                $userDomains = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                                foreach($userDomains as $d): 
                                    $isSelectedVSC = ($currentCodeDomain === $d['domain']);
                            ?>
                                <option value="<?= $d['domain'] ?>" <?= $isSelectedVSC ? 'selected' : '' ?>>
                                    <?= $d['domain'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php $isExposed = (isset($labData['expose_web']) && $labData['expose_web'] === true); ?>
                <?php if (\TomLabs\Labs\LabFeatures::supports($labType, 'expose_web')): ?>
                
                <p class="mb-2 mt-3 modal-section-title">PUBLIC EXPOSURE</p>

                <div id="expose_web_wrapper" class="row mb-3 align-items-center">
                    <label class="col-sm-4 small fw-bold text-secondary">Expose to Web (port 80)</label>
                    <div class="col-sm-8">
                        <select id="expose_web_toggle" class="form-select bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" onchange="toggleDomainSection()">
                            <option value="false" <?= !$isExposed ? 'selected' : '' ?>>Private, not exposed</option>
                            <option value="true" <?= $isExposed ? 'selected' : '' ?>>Public, 80 exposed over 443</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Custom MinIO Domain Selection (Hidden by default, toggled via JS) -->
                <div id="minio_domain_wrapper" class="initially-hidden">
                    <hr class="border-secondary opacity-25 my-3">
                    <p class="small fw-bold text-info mb-3"><i class='bx bx-server me-1'></i> MinIO Configuration</p>
                    
                    <?php
                        // Logic moved to the top of the modal file
                    ?>

                    <div class="row mb-3 align-items-center">
                        <label class="col-sm-4 small fw-bold text-secondary">Domain for MinIO Console<br><span class="fw-normal opacity-75">(Port 9001)</span></label>
                        <div class="col-sm-8">
                            <select id="minio_console_domain" class="form-select bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" onchange="updateDomainAvailability()">
                                <?php 
                                    $mDomains = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                                    $foundConsole = false;
                                    
                                    // A. User Domains
                                    foreach($mDomains as $d) {
                                        $isSel = ($d['domain'] === $currConsole);
                                        if($isSel) $foundConsole = true;
                                        echo "<option value=\"{$d['domain']}\" ".($isSel ? 'selected' : '').">{$d['domain']}</option>";
                                    }

                                    // B. System Default (Always available)
                                    $isSysSel = ($currConsole === $sysConsole);
                                    if($isSysSel) $foundConsole = true;
                                    echo "<option value=\"{$sysConsole}\" ".($isSysSel ? 'selected' : '')."> {$sysConsole}</option>";

                                    // C. Recovery (If current is weird/custom but unverified/deleted)
                                    if(!$foundConsole) {
                                        echo "<option value=\"{$currConsole}\" selected> {$currConsole}</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3 align-items-center">
                        <label class="col-sm-4 small fw-bold text-secondary">Domain for MinIO S3 Endpoint<br><span class="fw-normal opacity-75">(Port 9000)</span></label>
                        <div class="col-sm-8">
                            <select id="minio_api_domain" class="form-select bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" onchange="updateDomainAvailability()">
                                <?php 
                                    $mDomainsApi = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                                    $foundApi = false;
                                    
                                    // A. User Domains
                                    foreach($mDomainsApi as $d) {
                                        $isSel = ($d['domain'] === $currApi);
                                        if($isSel) $foundApi = true;
                                        echo "<option value=\"{$d['domain']}\" ".($isSel ? 'selected' : '').">{$d['domain']}</option>";
                                    }

                                    // B. System Default (Always available)
                                    $isSysSelApi = ($currApi === $sysApi);
                                    if($isSysSelApi) $foundApi = true;
                                    echo "<option value=\"{$sysApi}\" ".($isSysSelApi ? 'selected' : '')."> {$sysApi}</option>";

                                    // C. Recovery
                                    if(!$foundApi) {
                                        echo "<option value=\"{$currApi}\" selected> {$currApi}</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-text small opacity-50 mb-3">
                        MinIO Console and S3 API can optionally use the same custom domain.
                    </div>
                    <hr class="border-secondary opacity-25 my-3">
                </div>

                <!-- Custom n8n Domain Selection (Hidden by default, toggled via JS) -->
                <div id="n8n_domain_wrapper" class="initially-hidden">
                    <hr class="border-secondary opacity-25 my-3">
                    <p class="small fw-bold text-danger mb-3"><i class='bx bx-network-chart me-1'></i> n8n Configuration</p>
                    
                    <?php
                        $creds = $labData['credentials'] ?? [];
                        $hash = $labData['instance_hash'] ?? $fullHash;
                        $sysN8n = "n8n-{$hash}.tomweb.shop";
                        
                        // Check if function exists to avoid redeclaration error
                        if (!function_exists('cleanDomainN8n')) {
                            function cleanDomainN8n($url) {
                                $d = str_replace(['https://', 'http://'], '', $url);
                                return rtrim($d, '/');
                            }
                        }

                        $currN8n = cleanDomainN8n($creds['n8n_url'] ?? $sysN8n);
                    ?>

                    <div class="row mb-3 align-items-center">
                        <label class="col-sm-4 small fw-bold text-secondary">n8n Domain</label>
                        <div class="col-sm-8">
                            <select id="n8n_domain_selector" class="form-select bg-transparent border-secondary border-opacity-25 shadow-none rounded-pill px-3 text-white" onchange="updateDomainAvailability()">
                                <?php 
                                    $nDomains = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                                    $foundN8n = false;
                                    
                                    // A. User Domains
                                    foreach($nDomains as $d) {
                                        $isSel = ($d['domain'] === $currN8n);
                                        if($isSel) $foundN8n = true;
                                        echo "<option value=\"{$d['domain']}\" ".($isSel ? 'selected' : '').">{$d['domain']}</option>";
                                    }

                                    // B. System Default
                                    $isSysSel = ($currN8n === $sysN8n);
                                    if($isSysSel) $foundN8n = true;
                                    echo "<option value=\"{$sysN8n}\" ".($isSysSel ? 'selected' : '')."> {$sysN8n}</option>";

                                    // C. Recovery
                                    if(!$foundN8n) {
                                        echo "<option value=\"{$currN8n}\" selected> {$currN8n}</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25 my-3">
                </div>

                <div id="domain_selection_wrapper" class="row mb-3 align-items-start" style="display: <?= $isExposed ? 'flex' : 'none' ?>;">
    <label class="col-sm-4 small fw-bold text-secondary mt-2">Choose Domains</label>
    
    <div class="col-sm-8 position-relative">
        <div class="form-control bg-transparent border-secondary border-opacity-25 rounded-4 p-2 d-flex flex-wrap align-content-start gap-1 transition-all" 
             class="form-control domain-search-container" onclick="document.getElementById('domain_search').focus()" id="domain_search_container">
            
            <div id="selected_domains_display" class="domain-display-contents"></div>

            <input type="text" id="domain_search" 
                   class="flex-grow-1 bg-transparent border-0 shadow-none small px-1 m-0 text-white" 
                   class="border-0 bg-transparent text-white domain-input-field"
                   placeholder="Click to select domains..." 
                   onkeyup="filterDomains()"
                   onclick="event.stopPropagation()">
            
            <div class="ms-auto pe-1 d-flex align-items-start cursor-pointer" onclick="toggleDomainDropdown(event)">
                <i class='bx bx-chevron-down fs-5 opacity-50 transition-icon mt-1' id="dropdown_arrow"></i>
            </div>
        </div>

        <div id="domain_dropdown" class="border border-secondary border-opacity-10 rounded-3 mt-1 p-2 shadow-lg bg-body-tertiary" 
             class="initially-hidden domain-dropdown-menu">
            <div class="px-2 py-1 mb-1 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none small" onclick="selectAllDomains()">Select all</button>
                <span class="text-muted text-micro">Verified Domains</span>
            </div>
            <hr class="border-secondary opacity-25 my-1">
            <div id="domain_list">
                <?php 
                    $currentLabDomains = (array)($labData['domains'] ?? []); 
                    $userDomains = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                    foreach($userDomains as $d): 
                        $isChecked = in_array($d['domain'], $currentLabDomains);
                ?>
                    <div class="form-check domain-item p-2 rounded mx-1 mb-1 cursor-pointer" onclick="toggleCheckbox('dom_<?= $d['_id'] ?>')">
                        <input class="form-check-input domain-selector ms-0 me-2" type="checkbox" 
                               value="<?= $d['domain'] ?>" id="dom_<?= $d['_id'] ?>"
                               <?= $isChecked ? 'checked' : '' ?> onchange="updateSelectedDomains()" onclick="event.stopPropagation()">
                        <label class="form-check-label small" for="dom_<?= $d['_id'] ?>" onclick="event.stopPropagation()">
                            <?= $d['domain'] ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-text small opacity-50 mt-2 px-1">
            Your lab's port 80 will be visible over the chosen domain with automatic SSL certificates.
        </div>
    </div> <!-- Close col-sm-8 -->
</div> <!-- Close domain_selection_wrapper row -->
        
<?php if (\TomLabs\Labs\LabFeatures::supports($labType, 'http_proxies')): ?>
<div id="http_proxies_wrapper">
<p class="mb-2 mt-3 modal-section-title">HTTP PROXIES</p>
<div class="form-text small opacity-50 mb-3 px-1">
    Reverse-proxy any port to one or more of your domains over HTTP &mdash; TLS is terminated for you at the edge.
</div>
        
        <div id="deploy-proxy-container">
            <?php
                $httpProxies = [];
                if (isset($labData['http_proxies'])) {
                    $httpProxies = (array)$labData['http_proxies'];
                }
                
                // Fetch user's domains for the select dropdown
                $proxyUserDomains = [];
                $proxyDomainsCursor = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                foreach ($proxyDomainsCursor as $d) {
                    $proxyUserDomains[] = (string)$d['domain'];
                }
                
                if (empty($httpProxies)):
            ?>
            <div class="row align-items-center mb-3 proxy-row" data-index="0">
                <label class="col-sm-4 small fw-bold text-secondary">Port & Domains</label>
                <div class="col-sm-8">
                    <div class="row g-2">
                        <div class="col-md-4 col-12 mb-2 mb-md-0">
                            <input type="number" name="deploy_proxy_port[]" class="form-control bg-transparent rounded-pill border-secondary border-opacity-25 shadow-none px-3 proxy-port text-white" placeholder="Port" min="1" max="65535">
                        </div>
                        <div class="col-md-6 col-10">
                            <select name="deploy_proxy_domain[]" class="form-select bg-transparent rounded-pill border-secondary border-opacity-25 shadow-none px-3 proxy-domain-select text-white">
                                <option value="">Select Domain...</option>
                                <?php foreach ($proxyUserDomains as $ud): ?>
                                    <option value="<?= htmlspecialchars($ud) ?>"><?= htmlspecialchars($ud) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-2 d-flex justify-content-end">
                            <button type="button" class="btn rounded-circle d-flex align-items-center justify-content-center p-0 btn-remove-proxy border-secondary border-opacity-25 bg-body-tertiary btn-remove-proxy-icon" onclick="removeProxyRow(this)">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($httpProxies as $idx => $proxy): ?>
                <div class="row align-items-center mb-3 proxy-row" data-index="<?= $idx ?>">
                    <label class="col-sm-4 small fw-bold text-secondary">Port & Domains</label>
                    <div class="col-sm-8">
                        <div class="row g-2">
                            <div class="col-md-4 col-12 mb-2 mb-md-0">
                                <input type="number" name="deploy_proxy_port[]" class="form-control bg-transparent rounded-pill border-secondary border-opacity-25 shadow-none px-3 proxy-port text-white" placeholder="Port" min="1" max="65535" value="<?= (int)($proxy['port'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 col-10">
                                <select name="deploy_proxy_domain[]" class="form-select bg-transparent rounded-pill border-secondary border-opacity-25 shadow-none px-3 proxy-domain-select text-white">
                                    <option value="">Select Domain...</option>
                                    <?php foreach ($proxyUserDomains as $ud): ?>
                                        <option value="<?= htmlspecialchars($ud) ?>" <?= ((string)($proxy['domain'] ?? '') === $ud) ? 'selected' : '' ?>><?= htmlspecialchars($ud) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-2 d-flex justify-content-end">
                                <button type="button" class="btn rounded-circle d-flex align-items-center justify-content-center p-0 btn-remove-proxy border-secondary border-opacity-25 bg-body-tertiary btn-remove-proxy-icon" onclick="removeProxyRow(this)">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-2 row mb-2">
            <div class="col-sm-4"></div>
            <div class="col-sm-8">
                <button type="button" class="btn rounded-pill px-4 py-1.5 d-inline-flex align-items-center gap-2 btn-add-proxy-row" onclick="addDeployProxyRow()">
                    <i class='bx bx-message-square-add'></i> Add HTTP Proxy
                </button>
            </div>
        </div>
        </div>
        <?php endif; ?>

        <div class="d-flex align-items-start gap-2 mt-4 mb-2 px-1">
            <i class='bx bxs-info-square text-secondary opacity-50 info-icon-micro'></i>
            <div class="text-secondary opacity-75 info-text-micro">
                <?= $isRunning ? 'Redeploy' : 'Deploy' ?> gives your lab a fresh instance &mdash; files outside your home directory are wiped, and changes here take effect on this <?= $isRunning ? 'redeploy' : 'deploy' ?>. More settings &mdash; passwords, startup script &mdash; live in the <span class="text-decoration-underline">Preferences</span> tab.
            </div>
        </div>

    </div>
    
    <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" 
                        class="btn <?= $isRunning ? 'btn-warning' : 'btn-success' ?> fw-bold px-4 text-dark rounded-pill" 
                        id="redeploy-confirm-btn">
                    Confirm <?= $isRunning ? 'Redeploy' : 'Deploy' ?>
                </button>
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
<!-- Stop Lab Confirmation Modal -->
<div class="modal fade" id="stopModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden modal-stop-content">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-white mb-0">Decommission Lab?</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 stop-icon-wrapper">
                        <i class='bx bx-power-off text-danger stop-icon-lg'></i>
                    </div>
                    <h6 class="text-white fw-bold mb-2">Are you sure you want to stop this instance?</h6>
                    <p class="text-secondary small mb-0 px-3">
                        Stopping the lab will terminate all active processes and release CPU/RAM resources. 
                        <span class="text-info fw-bold">Your files and IP address will remain safe and reserved.</span>
                    </p>
                </div>

                <div class="p-3 rounded-3 bg-dark bg-opacity-25 border border-white border-opacity-10 mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-secondary fw-bold">Target Instance</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= strtoupper($labType) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary fw-bold">Reserved IP</span>
                        <span class="small font-monospace text-white"><?= $deviceIp ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold flex-grow-1" 
                        id="stop-confirm-btn" onclick="executeStop()">
                    Stop Instance
                </button>
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill" 
                        data-coreui-dismiss="modal">
                    Keep Running
                </button>
            </div>
        </div>
    </div>
</div>
