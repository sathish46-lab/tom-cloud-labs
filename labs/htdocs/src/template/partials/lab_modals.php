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
                                <input type="password" id="code-server-pass" 
                                       class="form-control border-secondary rounded-start-pill border-opacity-25" 
                                       value="<?= htmlspecialchars($sudoPass ?? '') ?>" readonly>
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
                        style="background-color: #2ecc71 !important; border: none;"
                        onclick="launchCodeIDE(event)"> Launch Code IDE
                </button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" 
                        style="background-color: #4b5563 !important; border: none;"
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
                    $minioConsoleUrl = '#'; // Default
                    
                    if(isset($labConfig['fields'])) {
                        foreach($labConfig['fields'] as $f) {
                            if($f['label'] === 'MinIO Access Key' || $f['label'] === 'Minio Secret Key') {
                                $minioFields[] = $f;
                            }
                            // The label in LabTemplateConfig is "MinIO Console Endpoint"
                            if($f['label'] === 'MinIO Console Endpoint') {
                                $minioConsoleUrl = $f['value'];
                            }
                        }
                    }
                ?>

                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach($minioFields as $field): ?>
                        <div class="password-section p-3 rounded-3">
                            <label class="small fw-bold text-secondary mb-1"><?= htmlspecialchars($field['label']) ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control border-secondary border-opacity-25 bg-body-tertiary text-body" 
                                       value="<?= htmlspecialchars($field['value']) ?>" readonly>
                                <button class="btn btn-outline-secondary px-3" onclick="copyText('<?= htmlspecialchars($field['value']) ?>')">
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
                <a href="<?= htmlspecialchars($minioConsoleUrl ?? '#') ?>" target="_blank" 
                   class="btn btn-primary rounded-pill px-4 fw-bold text-dark d-flex align-items-center gap-2"
                   style="background-color: #00a6e0 !important; border: none;">
                    <i class='bx bx-window-open'></i> Open Console
                </a>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Redeploy Modal -->
<div class="modal fade" id="redeployModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Confirm Redeploy?</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <div class="mb-4">
                    <p class="mb-2 fw-bold small">When can you re-deploy?</p>
                    <ul class="small opacity-75 ps-3">
                        <li>If server is not responding</li>
                        <li>If you messed up badly</li>
                        <li>You want to change it's configuration</li>
                    </ul>
                    <p class="small text-danger mt-3 mb-0">
                        <strong>Note:</strong> When you redeploy, all the files outside your home directory will be destroyed.
                    </p>
                </div>

                <hr class="border-secondary opacity-25 mb-4">

                <div class="row mb-3 align-items-center">
                    <label class="col-sm-3 small fw-bold text-secondary text-sm-end">Reallocate IP</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control bg-body-tertiary border-0 shadow-none opacity-75" value="<?= $deviceIp ?? '' ?>" readonly>
                    </div>
                </div>

                <div id="vsc_domain_wrapper" class="row mb-3 align-items-center">
                    <label class="col-sm-3 small fw-bold text-secondary text-sm-end">Domain for VS Code Web</label>
                    <div class="col-sm-8">
                        <select id="vsc_domain_selector" class="form-select bg-body-tertiary border-0 shadow-none" onchange="updateDomainAvailability()">
                            <?php 
                                $fullHash = $labData['instance_hash'];
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

                <div id="expose_web_wrapper" class="row mb-3 align-items-center">
                    <label class="col-sm-3 small fw-bold text-secondary text-sm-end">Expose to Web</label>
                    <div class="col-sm-8">
                        <?php $isExposed = (isset($labData['expose_web']) && $labData['expose_web'] === true); ?>
                        <select id="expose_web_toggle" class="form-select bg-body-tertiary border-0  shadow-none" onchange="toggleDomainSection()">
                            <option value="false" <?= !$isExposed ? 'selected' : '' ?>>Private, not exposed</option>
                            <option value="true" <?= $isExposed ? 'selected' : '' ?>>Public, 80 exposed over 443</option>
                        </select>
                    </div>
                </div>

                <!-- Custom MinIO Domain Selection (Hidden by default, toggled via JS) -->
                <div id="minio_domain_wrapper" style="display: none;">
                    <hr class="border-secondary opacity-25 my-3">
                    <p class="small fw-bold text-info mb-3"><i class='bx bx-server me-1'></i> MinIO Configuration</p>
                    
                    <?php
                        // Helper to clean domains
                        if (!function_exists('cleanDomain')) {
                            function cleanDomain($url) {
                                $d = str_replace(['https://', 'http://'], '', $url);
                                return rtrim($d, '/');
                            }
                        }

                        $creds = $labData['credentials'] ?? [];
                        $hash = $labData['instance_hash'];

                        // 1. Define SYSTEM DEFAULTS
                        $sysConsole = "s3-{$hash}.tomweb.shop";
                        $sysApi = "api-{$hash}.tomweb.shop";

                        // 2. Determine CURRENT CONFIGURATION
                        $currConsole = cleanDomain($creds['minio_url_console'] ?? $sysConsole);
                        $currApi = cleanDomain($creds['minio_url_api'] ?? $sysApi);
                    ?>

                    <div class="row mb-3 align-items-center">
                        <label class="col-sm-3 small fw-bold text-secondary text-sm-end">Domain</label>
                        <div class="col-sm-8">
                            <select id="minio_console_domain" class="form-select bg-body-tertiary border-0 shadow-none" onchange="updateDomainAvailability()">
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

                    <!-- Hidden API Endpoint (Auto-managed) -->
                    <input type="hidden" id="minio_api_domain" value="<?= $sysApi ?>">
                    
                    <div class="form-text small opacity-50 mb-3">
                        MinIO Console will be available at the selected domain. API Endpoint is auto-configured to: <?= $sysApi ?>
                    </div>
                    <hr class="border-secondary opacity-25 my-3">
                </div>

                <!-- Custom n8n Domain Selection (Hidden by default, toggled via JS) -->
                <div id="n8n_domain_wrapper" style="display: none;">
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
                        <label class="col-sm-3 small fw-bold text-secondary text-sm-end">n8n Domain</label>
                        <div class="col-sm-8">
                            <select id="n8n_domain_selector" class="form-select bg-body-tertiary border-0 shadow-none" onchange="updateDomainAvailability()">
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

                <div id="domain_selection_wrapper" class="row mb-3 align-items-center" style="display: <?= $isExposed ? 'flex' : 'none' ?>;">
    <label class="col-sm-3 small fw-bold text-secondary text-sm-end">Choose Domains</label>
    
    <div class="col-sm-8 position-relative">
        <div class="form-control bg-body-tertiary border-secondary border-opacity-10 p-2 d-flex flex-column justify-content-center gap-2" 
             style="min-height: 50px; cursor: text;" onclick="document.getElementById('domain_search').focus()">
            
            <div id="selected_domains_display" class="d-flex flex-wrap gap-1"></div>

            <div class="d-flex align-items-center">
                <input type="text" id="domain_search" 
                       class="flex-grow-1 bg-transparent border-0 shadow-none small p-0" 
                       style="outline: none; color: var(--cui-body-color); line-height: 1.5;"
                       placeholder="Click to select domains..." 
                       onkeyup="filterDomains()"
                       onclick="event.stopPropagation()">
                
                <div class="ms-2" style="cursor: pointer;" onclick="toggleDomainDropdown(event)">
                    <i class='bx bx-chevron-down fs-5 opacity-50 transition-icon' id="dropdown_arrow"></i>
                </div>
            </div>
        </div>

        <div id="domain_dropdown" class="border border-secondary border-opacity-10 rounded-3 mt-1 p-2 shadow-lg bg-body-tertiary" 
             style="display: none; max-height: 250px; overflow-y: auto; position: absolute; z-index: 1050; width: calc(100% - 24px); left: 12px;">
            <div class="px-2 py-1 mb-1 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none small" onclick="selectAllDomains()">Select all</button>
                <span class="text-muted" style="font-size: 10px;">Verified Domains</span>
            </div>
            <hr class="border-secondary opacity-25 my-1">
            <div id="domain_list">
                <?php 
                    $currentLabDomains = (array)($labData['domains'] ?? []); 
                    $userDomains = $db->domains->find(['user_id' => Session::getUser()->getUserId(), 'verified' => true]);
                    foreach($userDomains as $d): 
                        $isChecked = in_array($d['domain'], $currentLabDomains);
                ?>
                    <div class="form-check domain-item p-2 rounded mx-1 mb-1" style="cursor: pointer;" onclick="toggleCheckbox('dom_<?= $d['_id'] ?>')">
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
        
        <div class="form-text small opacity-50 mt-2">
            Your lab's port 80 will be visible over the chosen domain with automatic SSL certificates.
        </div>
    </div>
</div>
            </div>

           <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" 
                        class="btn btn-warning fw-bold px-4 text-dark rounded-pill" 
                        id="redeploy-confirm-btn">
                    Confirm Redeploy
                </button>
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
