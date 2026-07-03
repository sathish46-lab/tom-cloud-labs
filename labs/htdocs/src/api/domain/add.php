<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die("Unauthorized");
}
?>
            <div class="modal-body px-4 pt-4">
                <p class="small opacity-75 mb-3" style="line-height: 1.6;">
                    You can add one or more domains to your labs, so that you can use this domain to route labs HTTP(S) traffic from/to Internet. To get started, you can choose any of our curated Domain Name Services or use your custom domain.
                </p>
                
                <hr class="border-secondary opacity-25 mb-4">
                
                <div class="row mb-3 align-items-center">
                    <label class="col-sm-4 small fw-bold">Choose DNS Provider</label>
                    <div class="col-sm-8">
                        <select id="dns_provider" class="form-select bg-transparent text-white border-secondary shadow-none py-2 px-3 rounded-pill" style="border-radius: 12px !important; border: 1px solid rgba(255,255,255,0.2);" onchange="updateDomainPreview()">
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
                    <div class="col-sm-8">
                        <input type="text" id="choose_domain" class="form-control bg-transparent text-white border-secondary shadow-none py-2 px-3 rounded-pill" style="border-radius: 12px !important; border: 1px solid rgba(255,255,255,0.2);" placeholder="" oninput="updateDomainPreview()">
                    </div>
                </div>

                <div class="mb-4">
                    <p class="mb-2 fw-bold small">Confused what to name your domain?</p>
                    <ul class="small mb-0 ps-4 opacity-75">
                        <li class="mb-1">If it's for VS Code, try like <code class="text-info bg-transparent p-0 fw-bold">vscode.yourname</code> or <code class="text-info bg-transparent p-0 fw-bold">code.yourname</code> or <code class="text-info bg-transparent p-0 fw-bold">ide.yourname</code>.</li>
                        <li>If it's for a website, try like <code class="text-info bg-transparent p-0 fw-bold">yourname</code> or <code class="text-info bg-transparent p-0 fw-bold">anything.tld</code> in custom domains.</li>
                    </ul>
                </div>
                
                <hr class="border-secondary opacity-25 mb-3">
                
                <p class="small mb-0 opacity-75" style="line-height: 1.6;">
                    While redeploying your lab, you can choose to expose to web and then your lab's port 80 will be visible to the World-Wide Web over 
                    <span id="preview_domain" class="text-info fw-bold">https://*.tomweb.shop</span>. This will route traffic to your lab's port 80 over 443 by us. Worried about SSL certificates? We will take care of that for you!
                </p>

                <p id="custom_domain_note" class="small mb-0 opacity-75 mt-3 d-none" style="line-height: 1.6;">
                    <span class="fw-bold">Note:</span> Custom domains should have an A record pointing to this IP: <span class="text-info fw-bold"><?= htmlspecialchars(\TomLabs\Core\Env::get('SERVER_IP') ?? '106.51.76.75') ?></span> in the DNS management panel of your Domain Name Provider, if not, the domain cannot be registered here.
                </p>
            </div>

            <div class="modal-footer border-0 pb-4 px-4 pt-2 gap-2">
                <button type="button" id="btn_verify_add" class="btn btn-warning fw-bold px-4 text-dark rounded-pill" onclick="addDomain()">
                    Verify and Add
                </button>
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">
                    Cancel
                </button>
            </div>
