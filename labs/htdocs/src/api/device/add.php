<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die("Unauthorized");
}

$resources = Session::get('network_resources', []); 
$defaultIp = !empty($resources) ? end($resources)['ip_addr'] : "";
?>
                <div class="modal-body p-4">
                    <p class="text-secondary small mb-4">You can add devices to connect to your labs, allowing them to
                        communicate with our services. To get started, please provide the details of the device below.
                    </p>

                    <div class="row mb-4 align-items-center">
                        <div class="col-md-4"><label class="form-label-horizontal">Device Name</label></div>
                        <div class="col-md-8"><input type="text" class="form-control" name="device_name" required
                                placeholder="e.g. My Laptop"></div>
                    </div>

                    <div class="row mb-4 align-items-center">
                        <div class="col-md-4"><label class="form-label-horizontal">Device Type</label></div>
                        <div class="col-md-8">
                            <select class="form-select" name="device_type">
                                <option value="Mobile">Mobile</option>
                                <option value="Laptop">Laptop</option>
                                <option value="Desktop">Desktop</option>
                                <option value="Tablet">Tablet</option>
                                <option value="Server">Server</option>
                                <option value="IoT">IoT</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4 align-items-center">
                        <div class="col-md-4"><label class="form-label-horizontal">Reallocate IP</label></div>
                        <div class="col-md-8">
                            <select class="form-select" name="reallocate_ip">
                                <?php if(empty($resources)): ?>
                                <option value="">Assign New IP Address</option>
                                <?php else: ?>
                                <?php foreach ($resources as $res): ?>
                                <option value="<?= $res['ip_addr'] ?>"
                                    <?= $res['ip_addr'] == $defaultIp ? 'selected' : '' ?>>
                                    <?= $res['ip_addr'] ?>
                                </option>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="autoGenCheck" checked
                                onchange="toggleManualKey()">
                            <label class="form-check-label small text-secondary" for="autoGenCheck">
                                Auto Generate Keypair (Note: Your PrivateKey will be generated and managed by us, inturn
                                you can download the tunnel file or scan QR to configure VPN without hassle).
                            </label>
                        </div>
                    </div>

                    <div id="manualKeyArea" style="display:none" class="mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-4"><label class="form-label-horizontal">Wireguard Public Key</label>
                            </div>
                            <div class="col-md-8"><textarea class="form-control font-monospace" id="inputPubKey"
                                    rows="2" placeholder="Paste your Public Key here"></textarea></div>
                        </div>
                    </div>

                    <input type="hidden" name="public_key" id="hiddenPubKey">
                    <input type="hidden" name="private_key" id="hiddenPrivKey">

                    <div class="mt-4 border-top border-light border-opacity-10 pt-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_primary" id="primaryCheck">
                            <label class="form-check-label small text-secondary" for="primaryCheck">Primary
                                Device</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_trusted" id="trustedCheck">
                            <label class="form-check-label small text-secondary" for="trustedCheck">Trusted
                                Device</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="reserve_allocated" id="reserveCheck">
                            <label class="form-check-label small text-secondary" for="reserveCheck">Reserve IP when
                                Allocated</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                    <button type="submit" class="btn btn-warning fw-bold px-5 py-2 text-dark">Verify and Add</button>
                    <button type="button" class="btn btn-secondary px-4 py-2"
                        data-coreui-dismiss="modal">Cancel</button>
                </div>
