<?php
// Retrieve the merged device data from the Session
$resources = Session::get('network_resources', []); 
?>

<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Network</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                My Network is a section where you can manage IP Address Reservation for your devices. When you reserve an IP address, you will not lose it unless you delete the reservation.
            </p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach ($resources as $res): 
        $isAllocated = ($res['allocated'] == true);
        // Determine the label
        $serviceType = $res['service_type'] ?? 'vpn_device';
        $label = $res['label'] ?? (($res['service_type'] == 'essential_lab') ? 'Essential Lab' : 'VPN Device');
    ?>
    <div class="col-12 col-md-4 col-xl-3">
        <div class="card shadow-lg rounded-4 p-3 border-0 glass-card h-100">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold m-0 font-monospace" style="color: var(--glass-text); font-size: 1.1rem;"><?= htmlspecialchars($res['ip_addr']) ?></h5>
                    <span class="badge bg-secondary-gradient rounded-pill fw-bold" style="font-size: 10px; padding: 3px 10px;"><?= strtoupper($label) ?></span>
                </div>
                
                <span class="badge rounded-pill bg-<?= $isAllocated ? 'success' : 'danger' ?>-gradient fw-bold" style="font-size: 10px; padding: 3px 10px;">
                    <?= $isAllocated ? 'ACTIVE' : 'RESERVED' ?>
                </span>
            </div>

            <div class="mt-auto d-flex justify-content-end">
                <?php if ($isAllocated): ?>
                    <button class="btn btn-sm btn-outline-secondary border-0 fw-bold opacity-50" disabled 
                            style="font-size: 0.75rem;"
                            title="This IP is in use. Delete the associated device or lab first.">
                        <i class='bx bx-lock-alt me-1'></i> In Use
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-danger border-0 fw-bold"
                        style="font-size: 0.75rem;"
                        onclick="releaseIp('<?= $res['ip_addr'] ?>', '<?= $serviceType ?>')">
                        <i class='bx bx-trash-alt me-1'></i> Release IP
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
/**
 * Professional IP Release Logic
 * This triggers a full ownership wipe in the VPN database.
 */
async function releaseIp(ip, type) {
    if (!confirm("Permanently release " + ip +
            "? This IP will be removed from your account and available for others.")) return;

    try {
        const res = await fetch('/api/vpn/release-ip', {
            method: 'POST',
            body: JSON.stringify({
                ip: ip,
                type: type
            }),
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const data = await res.json();

        if (data.success) {
            // Smooth reload to update the UI grid
            window.location.reload();
        } else {
            alert("Error: " + (data.error || "Failed to release the IP address."));
        }
    } catch (e) {
        console.error("Network error during IP release:", e);
        alert("Network error: Could not connect to the VPN API.");
    }
}
</script>