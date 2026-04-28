<?php
// Retrieve active devices and reserved resources from the session
$devices = Session::get('devices', []); 
$resources = Session::get('network_resources', []); 

// LOGIC: Automatically select the last reserved IP for default selection
$defaultIp = !empty($resources) ? end($resources)['ip_addr'] : ""; 
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tweetnacl/1.0.3/nacl-fast.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>


.bg-success {
    background-color: #2eb85c !important;
    color: #fff !important;
}

.config-view {
    background: #000;
    border: 1px solid #363131;
    padding: 22px;
    border-radius: 12px;
    font-family: monospace;
    color: #fff;
}

.config-header {
    color: #50fa7b;
}

.config-label {
    color: #00d4ff;
}

.config-value {
    color: #ffcc00;
}


</style>

<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Devices</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                My Devices is a section where you can register your device to access our labs to learn, develop tools and play challenges and many more features in a secure way. 
                We have launched a new VPN app where you can connect to our network with one click to access your labs and do much more, now available for Windows and Ubuntu. 
                <a href="#" class="text-info fw-bold" style="text-decoration: none;">Download here</a> or 
                <a href="#" class="text-info fw-bold" style="text-decoration: none;">watch how to use this tool here</a>.
            </p>
        </div>
        <div class="col-auto text-end">
            <button class="btn btn-success fw-bold px-4 rounded-pill shadow-sm" style="font-size: 0.8rem; height: 38px; white-space: nowrap;" data-coreui-toggle="modal" data-coreui-target="#addDeviceModal">
                <i class="bx bx-plus"></i> Add Device
            </button>
        </div>
    </div>
</div>

<div class="row g-4" id="devices-container">
    <?php foreach ($devices as $device): 
        $dbId = is_array($device['_id']) ? ($device['_id']['$oid'] ?? '') : (string)$device['_id'];
        $displayStatus = ucfirst(strtolower($device['status'])); 
    ?>
    <div class="col-12 col-md-4 device-row" data-pubkey="<?= $device['public_key'] ?>">
        <div class="card shadow-lg rounded-4 overflow-hidden border-0 glass-card h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="fw-bold m-0 text-truncate" style="text-transform: none; color: var(--glass-text); font-size: 1.15rem; letter-spacing: -0.3px;">
                        <?= htmlspecialchars($device['device_name']) ?></h5>
                    <div class="dropdown">
                        <button class="btn btn-link p-0 opacity-50 shadow-none border-0 d-flex align-items-center justify-content-center rounded-circle" 
                                data-coreui-toggle="dropdown" 
                                style="width: 32px; height: 32px; transition: all 0.2s; text-decoration: none !important;">
                            <i class='bx bx-dots-vertical-rounded fs-4' style="color: var(--glass-icon); text-decoration: none !important;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 glass-card" style="min-width: 4rem; border-radius: 14px; padding: 6px; overflow: hidden;">
                            <li><a class="dropdown-item rounded-3 mb-1 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                    onclick="openVPNConnectionModal('<?= $dbId ?>', '<?= htmlspecialchars($device['device_name']) ?>')"><i
                                        class='bx bx-qr-scan me-2 text-primary'></i> Config</a></li>
                            <li><a class="dropdown-item rounded-3 mb-1 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                    onclick="downloadTunnel('<?= htmlspecialchars($device['device_name']) ?>', '<?= $dbId ?>')"><i
                                        class='bx bx-download me-2 text-info'></i> Download</a></li>
                            <li><a class="dropdown-item text-danger rounded-3 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                    onclick="deleteDevice('<?= $dbId ?>', '<?= $device['public_key'] ?>')"><i
                                        class='bx bx-trash me-2'></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-wrap mb-2">
                    <?php 
                        $type = strtolower($device['device_type'] ?? 'mobile');
                        $typeClass = 'bg-primary';
                        $typeIcon = 'bx-mobile-alt';
                        
                        if ($type === 'laptop') { $typeClass = 'bg-info text-dark'; $typeIcon = 'bx-laptop'; }
                        elseif ($type === 'desktop') { $typeClass = 'bg-success'; $typeIcon = 'bx-desktop'; }
                        elseif ($type === 'tablet') { $typeClass = 'bg-warning text-dark'; $typeIcon = 'bx-tab'; }
                        elseif ($type === 'server') { $typeClass = 'bg-dark border border-secondary'; $typeIcon = 'bx-server'; }
                        elseif ($type === 'iot') { $typeClass = 'bg-danger'; $typeIcon = 'bx-chip'; }
                    ?>
                    <span class="badge rounded-pill <?= $typeClass ?> fw-bold" style="font-size: 10px; padding: 3px 8px;">
                        <i class='bx <?= $typeIcon ?> me-1'></i> <?= strtoupper($type) ?>
                    </span>
                    <?php 
                        $status = strtolower($device['status'] ?? 'offline');
                        $statusClass = ($status === 'online') ? 'bg-success' : 'bg-danger';
                        $statusIcon = ($status === 'online') ? 'bx-wifi' : 'bx-wifi-off';
                    ?>
                    <span class="badge rounded-pill <?= $statusClass ?> fw-bold" style="font-size: 10px; padding: 3px 8px;">
                        <i class='bx <?= $statusIcon ?> me-1'></i> <?= strtoupper($status) ?>
                    </span>
                </div>
                <div class="small stats-area" style="font-size: 0.75rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color: var(--glass-text-muted);">Device IP:</span> 
                        <span class="theme-text font-monospace" style="color: var(--glass-text);"><?= $device['assigned_ip'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color: var(--glass-text-muted);">Origin:</span> 
                        <span class="origin-val theme-text" style="color: var(--glass-text);"><?= $device['origin_ip'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color: var(--glass-text-muted);">Received:</span> 
                        <span class="rx-val theme-text"><?= $device['rx'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="color: var(--glass-text-muted);">Sent:</span> 
                        <span class="tx-val theme-text"><?= $device['tx'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="vpnAddForm">
                <div class="modal-header border-bottom border-light border-opacity-10 p-4">
                    <h5 class="modal-title fw-bold">Add Device</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                </div>
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
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom border-light border-opacity-10 p-4">
                <h5 class="modal-title fw-bold" id="configTitle">Wireguard Configuration</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="config-view mb-3" id="configText"></div><button
                            class="btn btn-primary btn-sm w-100 fw-bold" onclick="copyConfig()"><i
                                class='bx bx-copy'></i> Copy Configuration</button>
                    </div>
                    <div class="col-md-5 text-center mt-3 mt-md-0">
                        <div id="qrcode" class="d-inline-block"></div>
                        <p class="small text-secondary mt-3 mb-0">Scan QR for Mobile VPN.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button
                    class="btn btn-warning w-100 rounded-pill text-dark fw-bold"
                    data-coreui-dismiss="modal">Okay</button></div>
        </div>
    </div>
</div>