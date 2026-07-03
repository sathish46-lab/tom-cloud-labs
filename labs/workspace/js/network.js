let currentDeleteIp = null;
let currentDeleteType = null;
let currentDeleteCardId = null;
let currentDeleteBtn = null;

function releaseIp(ip, type, cardId, btn) {
    currentDeleteIp = ip;
    currentDeleteType = type;
    currentDeleteCardId = cardId;
    currentDeleteBtn = btn;
    
    // Update modal content
    const ipSpan = document.getElementById('deleteModalIp');
    if (ipSpan) {
        ipSpan.textContent = ip;
    }
    
    // Show Trash Bin (lid open, waiting)
    if (window.TrashBin) window.TrashBin.show();
    
    // Show Modal
    const modalEl = document.getElementById('confirmDeleteModal');
    const deleteModal = new coreui.Modal(modalEl);
    deleteModal.show();
    
    // Hide bin if modal is dismissed (cancel / backdrop click / ESC)
    modalEl.addEventListener('hidden.coreui.modal', function onHide() {
        modalEl.removeEventListener('hidden.coreui.modal', onHide);
        if (window.TrashBin) window.TrashBin.hide();
    });
}

async function confirmReleaseIp() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (!confirmBtn) return;
    
    const modalEl = document.getElementById('confirmDeleteModal');
    const deleteModal = coreui.Modal.getInstance(modalEl) || new coreui.Modal(modalEl);
    
    // UI feedback
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin me-1'></i> Deleting...";
    
    // Disable original button too
    if (currentDeleteBtn) {
        currentDeleteBtn.disabled = true;
        currentDeleteBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin me-1'></i> Releasing...";
    }

    try {
        const res = await fetch('/api/vpn/release-ip', {
            method: 'POST',
            body: JSON.stringify({ ip: currentDeleteIp, type: currentDeleteType }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();

        if (data.success) {
            deleteModal.hide(); // Hide the modal immediately
            
            const cardElement = document.getElementById("ip-card-" + currentDeleteCardId);
            if (cardElement) {
                window.TrashBin.animateDelete(cardElement, "IP " + currentDeleteIp + " successfully released.");
            }
        } else {
            alert("Error: " + (data.error || "Failed to release the IP address."));
            if (currentDeleteBtn) {
                currentDeleteBtn.disabled = false;
                currentDeleteBtn.innerHTML = "<i class='bx bx-trash-alt me-1'></i> Release IP";
            }
        }
    } catch (e) {
        console.error("Network error during IP release:", e);
        alert("Network error: Could not connect to the VPN API.");
        if (currentDeleteBtn) {
            currentDeleteBtn.disabled = false;
            currentDeleteBtn.innerHTML = "<i class='bx bx-trash-alt me-1'></i> Release IP";
        }
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    }
}
window.confirmReleaseIp = confirmReleaseIp;
