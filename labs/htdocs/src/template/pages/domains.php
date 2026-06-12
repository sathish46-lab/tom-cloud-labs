<?php
// Auto-refresh verification for custom domains on page load
$domainManager = new DomainManager();
$user = Session::getUser();
if ($user) {
    $domainManager->refreshUserDomains($user->getUserId());
}
$certs = Session::get('ssl_certificates') ?: [];
$serverIP = $domainManager->getServerIP();

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
<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Domains</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                My Domains is a section where you can reserve stylish Tom Lab Domains or register 3rd party domains to access your lab over Internet.
                In case of 3rd party domains, you will have to manually modify the DNS records of your domain to point to your lab. Domains are used to
                show your work to the WWW over SSL seemlessly. Your online presence makes you powerful 🔥
            </p>
        </div>
        <div class="col-auto text-end">
            <button class="btn btn-success fw-bold px-4 rounded-pill shadow-sm" style="font-size: 0.8rem; height: 38px; white-space: nowrap;" data-coreui-toggle="modal" data-coreui-target="#addDomainModal">
                <i class="bx bx-plus"></i> Add New Domain
            </button>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach (Session::get('user_domains') as $d): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 shadow-lg rounded-4 h-100 domain-card glass-card">
            <div class="card-body d-flex justify-content-between align-items-start p-3">
                <div class="w-100" style="margin-bottom: 6px;">
                    <div class="fw-bold mb-2" style="font-size: 1.15rem; letter-spacing: -0.3px;">
                        <a style="text-decoration: none; color: #3498db;" target="_blank" href="https://<?= $d['domain'] ?>">
                            <?= $d['domain'] ?>
                        </a>
                    </div>

                    <div style="margin-bottom: 14px;" class="d-flex gap-1 flex-wrap">
                        <?php if (strtolower($d['type']) == 'custom'): ?>
                            <span class="badge bg-info text-dark rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">Custom</span>
                        <?php else: ?>
                            <span class="badge bg-primary rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;"><?= strtolower($d['type']) == 'Tom' ? 'Tom' : $d['type'] ?></span>
                        <?php endif; ?>
                        
                        <?php if ($d['verified']): ?>
                            <span class="badge bg-success rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">DNS Pending</span>
                        <?php endif; ?>
                        
                        <?php 
                            // Add usage status badge
                            $dm = new DomainManager();
                            $usageInfo = $dm->getDomainUsage(Session::getUser()->getUserId(), $d['domain']);
                            if ($usageInfo): 
                        ?>
                            <span class="badge bg-danger text-white rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">In Use</span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark rounded-pill fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">Available</span>
                        <?php endif; ?>
                        
                        <?php
                            // Check if this domain has a valid SSL certificate
                            $certIndex = -1;
                            $hasValidSsl = false;
                            foreach ($certs as $idx => $cert) {
                                if (in_array($d['domain'], $cert['sans'])) {
                                    $certIndex = $idx;
                                    $hasValidSsl = $cert['is_valid'];
                                    break;
                                }
                            }
                            if ($certIndex >= 0):
                        ?>
                            <a href="/ssl" class="badge text-white rounded-pill fw-bold border border-secondary text-decoration-none" style="background-color: #1a1b1e; font-size: 8px; padding: 2px 6px; transition: all 0.2s;">
                                SSL <?= $hasValidSsl ? 'valid' : 'invalid' ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 0.8rem; line-height: 1.2; color: var(--glass-text);">
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">Common Name:</b> 
                            <span class="ms-1"><?= explode('.', $d['domain'])[0] ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">Domain Name:</b> 
                            <span class="ms-1" style="color: #3498db;"><?= $d['domain'] ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">A Record:</b> 
                            <span class="ms-1"><?php $dm = new DomainManager(); echo $dm->getServerIP(); ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">Service:</b> 
                            <span class="ms-1"><?= (strtolower($d['type']) == 'tom') ? 'Tom Lab' : 'Custom' ?></span>
                        </div>
                        
                        <?php 
                            $usageInfo = $dm->getDomainUsage(Session::getUser()->getUserId(), $d['domain']);
                            if ($usageInfo): 
                        ?>
                            <div class="mb-1">
                                <b style="color: var(--glass-text-muted); font-weight: 600;">Currently Used:</b> 
                                <span class="text-success ms-1"><?= $usageInfo['usage'] ?> (<?= $usageInfo['lab_type'] ?> lab)</span>
                            </div>
                        <?php else: ?>
                            <div class="mb-1">
                                <b style="color: var(--glass-text-muted); font-weight: 600;">Currently Used:</b> 
                                <span class="ms-1 opacity-50">Not in use</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="action-dots p-0 opacity-50 shadow-none border-0 d-flex align-items-center justify-content-center" 
                            data-coreui-toggle="dropdown" 
                            style="width: 32px; height: 32px; transition: all 0.2s; background: none; border: none;">
                        <i class='bx bx-dots-vertical-rounded fs-4' style="color: var(--glass-icon);"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 8rem; border-radius: 12px; padding: 8px; background: var(--cui-dropdown-bg); backdrop-filter: blur(10px);">
                        <?php if (!$d['verified']): ?>
                            <li><a class="dropdown-item rounded-3 mb-1 px-3 py-2 d-flex align-items-center" href="#" onclick="verifyDomain('<?= $d['_id'] ?>')" style="font-size: 0.8rem;"><i class='bx bx-check-shield me-2 text-primary'></i> Verify</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger rounded-3 px-3 py-2 d-flex align-items-center" href="#" onclick="removeDomain('<?= $d['_id'] ?>')" style="font-size: 0.8rem;"><i class='bx bx-trash me-2'></i> Delete</a></li>
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