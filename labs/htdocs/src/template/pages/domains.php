<?php
// Auto-refresh verification for custom domains on page load
$domainManager = new DomainManager();
$user = Session::getUser();
if ($user) {
    $domainManager->refreshUserDomains($user->getUserId());
}

// Helper function to show time ago
function timeAgo($timestamp) {
    if (empty($timestamp)) return 'Never';
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $timestamp);
}
?>
<div class="lab-header-section mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="fw-bold theme-text m-0">Domains</h1>
        <p class="text-secondary opacity-75 small">Reserve stylish subdomains or register 3rd party domains to access your lab over Internet.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success fw-bold px-4 rounded-pill shadow-sm" data-coreui-toggle="modal" data-coreui-target="#addDomainModal">
            <i class="bx bx-plus"></i> Add New Domain
        </button>
    </div>
</div>

<div class="row g-4 px-4 mb-4">
    <?php foreach (Session::get('user_domains') as $d): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 shadow-lg rounded-4 h-100 domain-card">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div style="margin-bottom: 10px;">
                    <div class="fs-4 fw-semibold mb-2">
                        <a style="text-decoration: none; color: #3498db;" target="_blank" href="https://<?= $d['domain'] ?>">
                            <?= $d['domain'] ?>
                        </a>
                    </div>

                    <div style="margin-bottom: 15px;" class="d-flex gap-2">
                        <?php if (strtolower($d['type']) == 'custom'): ?>
                            <span class="badge bg-info rounded-pill px-1 py-1 fw-bold ls-1">custom</span>
                        <?php else: ?>
                            <span class="badge bg-primary rounded-pill px-1 py-1 fw-bold ls-1"><?= $d['type'] ?></span>
                        <?php endif; ?>
                        
                        <?php if ($d['verified']): ?>
                            <span class="badge bg-success rounded-pill px-1 py-1">verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark rounded-pill px-1 py-1">dns pending</span>
                        <?php endif; ?>
                        
                        <?php 
                            // Add usage status badge
                            $dm = new DomainManager();
                            $usageInfo = $dm->getDomainUsage(Session::getUser()->getUserId(), $d['domain']);
                            if ($usageInfo): 
                        ?>
                            <span class="badge bg-danger text-white rounded-pill px-1 py-1">used</span>
                        <?php else: ?>
                            <span class="badge bg-info text-white rounded-pill px-1 py-1">available</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (strtolower($d['type']) == 'custom' && isset($d['last_checked'])): ?>
                    <div class="small text-secondary mb-2">
                        <!-- <i class="bx bx-time-five"></i> Last verified: <?= timeAgo($d['last_checked']) ?> -->
                    </div>
                    <?php endif; ?>

                    <div style="font-size: 0.8rem; line-height: 1.6;">
                        <div class="mb-1">
                            <b class="text-body-secondary opacity-75">Common Name:</b> 
                            <span class="ms-1"><?= explode('.', $d['domain'])[0] ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b class="text-body-secondary opacity-75">Domain Name:</b> 
                            <span class="text-info ms-1"><?= $d['domain'] ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b class="text-body-secondary opacity-75">A Record:</b> 
                            <span class="ms-1"><?php $dm = new DomainManager(); echo $dm->getServerIP(); ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b class="text-body-secondary opacity-75">Service:</b> 
                            <span class="ms-1"><?= (strtolower($d['type']) == 'tom') ? 'Tom Lab' : 'Custom' ?></span>
                        </div>
                        
                        <?php 
                            $usageInfo = $dm->getDomainUsage(Session::getUser()->getUserId(), $d['domain']);
                            if ($usageInfo): 
                        ?>
                            <div class="mb-1">
                                <b class="text-body-secondary opacity-75">Currently Used:</b> 
                                <span class="text-success ms-1"><?= $usageInfo['usage'] ?> (<?= $usageInfo['lab_type'] ?> lab)</span>
                            </div>
                        <?php else: ?>
                            <div class="mb-1">
                                <b class="text-body-secondary opacity-75">Currently Used:</b> 
                                <span class="text-secondary ms-1">Not in use</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-link  p-0 opacity-50 shadow-none" data-coreui-toggle="dropdown">
                        <i class='bx bx-dots-vertical-rounded fs-4'></i>
                    </button>
                    <ul class="dropdown-menu  dropdown-menu-end shadow">
                        <?php if (!$d['verified']): ?>
                            <li><a class="dropdown-item" href="#" onclick="verifyDomain('<?= $d['_id'] ?>')"><i class='bx bx-check-shield me-2'></i> Verify DNS</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="#" onclick="removeDomain('<?= $d['_id'] ?>')"><i class='bx bx-trash me-2'></i> Remove Domain</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h4 class="modal-title fw-bold">Add Domain</h4>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4">
                <p class="small opacity-75 mb-4">
                    You can add one or more domains to your labs to route labs HTTP(S) traffic from/to Internet.
                </p>
                
                <div class="row mb-4 align-items-center">
                    <label class="col-sm-4 small fw-bold">Choose DNS Provider</label>
                    <div class="col-sm-7">
                        <select id="dns_provider" class="form-select bg-body-tertiary border-0  shadow-none py-2 px-3 rounded-3">
                            <?php 
                            $domainManager = new DomainManager();
                            $availableDomains = $domainManager->getAvailableDomains();
                            foreach ($availableDomains as $domain) {
                                echo "<option value=\"{$domain}\">{$domain}</option>";
                            }
                            ?>
                            <option value="custom">Custom Domain</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-4 align-items-center">
                    <label class="col-sm-4 small fw-bold">Choose Domain</label>
                    <div class="col-sm-7">
                        <input type="text" id="choose_domain" class="form-control bg-body-tertiary border-0 shadow-none py-2 px-3 rounded-3" placeholder="">
                    </div>
                </div>

                <div class="p-3 rounded-4 mb-3" style="background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                    <p class="mb-2 fw-bold small">Confused what to name your domain?</p>
                    <ul class="text-secondary small mb-3 ps-3">
                        <li>If it's for VS Code, try like <code class="text-info">vscode.yourname</code> or <code class="text-info">code.yourname</code></li>
                        <li>If it's for a website, try like <code class="text-info">yourname</code> or <code class="text-info">anything.tld</code></li>
                    </ul>
                    
                    <p class="small text-secondary mb-0 mt-3 border-top border-secondary border-opacity-25 pt-3">
                        While redeploying your lab, you can choose to expose to web and then your lab's port 80 will be visible to the World-Wide Web over 
                        <span class="text-info fw-bold">https://*.tomweb.shop</span>. We will take care of SSL for you!
                    </p>
                </div>
            </div>

            <div class="modal-footer border-0 pb-4 px-4 gap-2">
                <button type="button" id="btn_verify_add" class="btn btn-warning fw-bold px-4 text-dark rounded-pill" onclick="addDomain()">
                    Verify and Add
                </button>
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>