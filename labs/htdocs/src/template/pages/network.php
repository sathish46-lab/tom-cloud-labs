<?php
// Retrieve the merged device data from the Session
$resources = Session::get('network_resources', []); 
?>

<div class="lab-header-section mb-4">
    <h1 class="fw-bold theme-title">Network Resources</h1>
    <p class="text-secondary opacity-75">Manage your reserved IP addresses. Deleting a device keeps the IP here for
        reuse.</p>
</div>

<div class="row g-4">
    <?php foreach ($resources as $res): 
        $isAllocated = ($res['allocated'] == true);
        // Determine the label
        $serviceType = $res['service_type'] ?? 'vpn_device';
        $label = $res['label'] ?? (($res['service_type'] == 'essential_lab') ? 'Essential Lab' : 'VPN Device');
    ?>
    <div class="col-12 col-md-4 col-xl-3">
        <div class="card shadow-lg rounded-4 p-3 border-0">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold theme-title mb-0 font-monospace"><?= htmlspecialchars($res['ip_addr']) ?></h5>
                    <small class="text-muted fw-bold" style="font-size: 0.6rem;"><?= strtoupper($label) ?></small>
                </div>
                
                <span class="badge rounded-pill <?= $isAllocated ? 'bg-success' : 'bg-danger' ?> opacity-90">
                    <?= $isAllocated ? 'Active' : 'Reserved' ?>
                </span>
            </div>

            <div class="mt-auto d-flex justify-content-end">
                <button class="btn btn-sm btn-outline-danger border-0 fw-bold"
                    onclick="releaseIp('<?= $res['ip_addr'] ?>', '<?= $serviceType ?>')">
                    <i class='bx bx-trash-alt me-1'></i> Release IP
                </button>
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
async function releaseIp(ip) {
    if (!confirm("Permanently release " + ip +
            "? This IP will be removed from your account and available for others.")) return;

    try {
        const res = await fetch('/api/vpn/release-ip.php', {
            method: 'POST',
            body: JSON.stringify({
                ip: ip
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