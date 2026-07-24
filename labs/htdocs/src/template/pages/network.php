<?php
// Retrieve the merged device data from the Session
$resources = Session::get('network_resources', []); 
Session::addCustomJs('/assets/js/network.js');
?>

<div class="blur mb-3 rounded-0">
    <div class="container-fluid px-4">
        <div class="row align-items-center py-3">
            <div class="col">
                <h1 class="fw-bold theme-text m-0 network-header-title">Network</h1>
                <p class="text-secondary opacity-75 mt-2 mb-0 network-header-desc">
                    My Network is a section where you can manage IP Address Reservation for your devices. When you reserve an IP address, you will not lose it unless you delete the reservation.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    <div class="row g-4 mb-4">
    <?php foreach ($resources as $res): 
        $isAllocated = ($res['allocated'] == true);
        // Determine the label
        $serviceType = $res['service_type'] ?? 'vpn_device';
        $label = $res['label'] ?? (($res['service_type'] == 'essential_lab') ? 'Essential Lab' : 'VPN Device');
    ?>
    <div class="col-12 col-md-4 col-xl-3 card-entrance" id="ip-card-<?= str_replace('.', '-', $res['ip_addr']) ?>">
        <div class="card shadow-lg rounded-4 p-3 border-0 blur h-100">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold m-0 font-monospace ip-card-title"><?= htmlspecialchars($res['ip_addr']) ?></h5>
                    <span class="badge rounded-pill fw-bold border badge-ip-label"><?= $label ?></span>
                </div>
                
                <span class="badge rounded-pill bg-<?= $isAllocated ? 'success' : 'danger' ?> fw-bold badge-ip-status">
                    <?= $isAllocated ? 'Active' : 'Reserved' ?>
                </span>
            </div>

            <div class="mt-auto d-flex justify-content-end">
                <?php if ($isAllocated): ?>
                    <button class="btn btn-sm btn-outline-secondary border-0 fw-bold opacity-50 btn-ip-action" disabled 
                            title="This IP is in use. Delete the associated device or lab first.">
                        <i class='bx bx-lock-alt me-1'></i> In Use
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-danger border-0 fw-bold btn-ip-action"
                        onclick="releaseIp('<?= $res['ip_addr'] ?>', '<?= $serviceType ?>', '<?= str_replace('.', '-', $res['ip_addr']) ?>', this)">
                        <i class='bx bx-trash-alt me-1'></i> Release IP
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4 border-0 blur glass-modal-content">
        <div class="modal-header border-0 pb-2">
            <h4 class="modal-title fw-bold m-0 modal-title-delete">Confirm Delete</h4>
        </div>
        <div class="modal-body py-3 border-top border-bottom border-translucent">
            <p class="mb-0 opacity-75 modal-body-desc">
                You are about to delete the IP address: <span id="deleteModalIp" class="text-info fw-bold font-monospace"></span>. 
                You will lose this IP address and it will be allocated to someone else on demand. But this is not a bad thing :)
            </p>
        </div>
        <div class="modal-footer border-0 pt-3 pb-1 d-flex justify-content-end gap-3">
            <button type="button" class="btn px-4 rounded-pill fw-bold border-0 shadow-sm btn-modal-cancel" data-coreui-dismiss="modal">Cancel</button>
            <button type="button" class="btn text-white px-4 rounded-pill fw-bold border-0 btn-modal-delete" id="confirmDeleteBtn" onclick="confirmReleaseIp()">Delete</button>
        </div>
    </div>
  </div>
</div>