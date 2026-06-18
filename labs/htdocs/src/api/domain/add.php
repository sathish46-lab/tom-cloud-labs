<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die("Unauthorized");
}
?>
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
