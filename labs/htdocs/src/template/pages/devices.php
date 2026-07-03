<?php
// Retrieve active devices and reserved resources from the session
$devices = Session::get('devices', []); 
$resources = Session::get('network_resources', []); 

// LOGIC: Automatically select the last reserved IP for default selection
$defaultIp = !empty($resources) ? end($resources)['ip_addr'] : ""; 
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tweetnacl/1.0.3/nacl-fast.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>



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
    <?php foreach ($devices as $device): ?>
    <?php include __DIR__ . '/../partials/_device_card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="vpnAddForm" hx-boost="false">
                <div class="modal-header border-bottom border-light border-opacity-10 p-4">
                    <h5 class="modal-title fw-bold">Add Device</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                </div>
                <div id="addDeviceModalContent">
                    <div class="modal-body p-5 text-center">
                        <i class="bx bx-loader-alt bx-spin text-primary" style="font-size: 3rem;"></i>
                        <div class="mt-3 text-white opacity-75 fw-semibold tracking-widest uppercase" style="font-size: 0.8rem;">Loading form...</div>
                    </div>
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

<div class="modal fade" id="confirmDeleteDeviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4 border-0 glass-card" style="backdrop-filter: blur(15px);">
        <div class="modal-header border-0 pb-2">
            <h4 class="modal-title fw-bold m-0" style="color: var(--glass-text); letter-spacing: -0.5px; font-size: 1.5rem;">Delete Device: <span id="deleteDeviceModalTitleName"></span></h4>
        </div>
        <div class="modal-body py-3 border-top border-bottom" style="border-color: var(--cui-border-color-translucent) !important;">
            <p class="mb-2 opacity-75" style="color: var(--glass-text); font-size: 0.95rem; line-height: 1.6;">
                You are about to delete a registered device named <strong id="deleteDeviceModalBodyName" class="text-white"></strong>. 
                You will no longer be able to communicate via this device and you will lose your IP address If its not reserved.
            </p>
            <p class="mb-0 opacity-75" style="color: var(--glass-text); font-size: 0.95rem;">
                Current IP: <span id="deleteDeviceModalIp" class="text-info fw-bold font-monospace"></span>.<br>
                Are you sure to continue?
            </p>
        </div>
        <div class="modal-footer border-0 pt-3 pb-1 d-flex justify-content-end gap-3">
            <button type="button" class="btn px-4 rounded-pill fw-bold border-0 shadow-sm" data-coreui-dismiss="modal" style="background: var(--cui-secondary-bg); color: var(--glass-text); font-size: 0.9rem;">Cancel</button>
            <button type="button" class="btn text-white px-4 rounded-pill fw-bold border-0" id="confirmDeleteDeviceBtn" onclick="confirmDeleteDeviceAction()" style="background: #e63946; font-size: 0.9rem; box-shadow: 0 4px 15px rgba(230,57,70,0.3);">Delete</button>
        </div>
    </div>
  </div>
</div>