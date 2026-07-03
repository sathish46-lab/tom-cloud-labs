/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


async function addDomain() {
  const provider = document.getElementById("dns_provider").value;
  const prefix = document.getElementById("choose_domain").value.trim();
  const btn = document.getElementById("btn_verify_add");

  if (!prefix) return alert("Domain prefix is required.");

  let finalDomain =
    provider === "custom" ? prefix : prefix + provider.replace("*", "");
  let type = provider === "custom" ? "custom" : "tom";

  btn.disabled = true;
  btn.innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span> Verifying...';

  try {
    const response = await fetch("/api/domain/add_domain", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ domain: finalDomain, type: type }),
    });

    if (response.ok) {
      const htmlText = await response.text();
      
      const isVerified = response.headers.get('X-Domain-Verified') === 'true';
      if (!isVerified) {
        alert(
          `⚠️ Domain added but NOT verified yet.\n\nPlease point the A record to: ${window.SERVER_IP || "the server"}\n\nThen click "Verify DNS" to check again.`,
        );
      }
      
      if (htmlText.trim()) {
          const container = document.querySelector('.row.g-4.mb-4'); // domains container
          if (container) {
              container.insertAdjacentHTML('beforeend', htmlText);
          }
      }
      
      // Hide modal using CoreUI API
      const modalEl = document.getElementById('addDomainModal');
      if (modalEl) {
          const modal = coreui.Modal.getInstance(modalEl);
          if (modal) {
              modal.hide();
          } else {
              const dismissBtn = modalEl.querySelector('[data-coreui-dismiss="modal"]');
              if (dismissBtn) dismissBtn.click();
          }
          
          document.getElementById('choose_domain').value = '';
      } else {
          location.reload();
      }
    } else {
      const errorMsg = await response.text();
      alert("❌ Error: " + (errorMsg || "Unknown Error"));
    }
  } catch (e) {
    console.error("Error details:", e);
    alert("Connection error occurred. Check console for details.");
  } finally {
    btn.disabled = false;
    btn.innerHTML = "Verify and Add";
  }
}

let currentDeleteDomainId = null;

async function removeDomain(domainId, domainName) {
  currentDeleteDomainId = domainId;
  
  const nameSpan = document.getElementById('deleteDomainModalName');
  if (nameSpan) {
      nameSpan.textContent = domainName || 'this domain';
  }
  
  // Show Trash Bin (lid open, waiting)
  if (window.TrashBin) window.TrashBin.show();
  
  // Show Modal
  const modalEl = document.getElementById('confirmDeleteDomainModal');
  const deleteModal = new coreui.Modal(modalEl);
  deleteModal.show();
  
  // Hide bin if modal is dismissed (cancel / backdrop click / ESC)
  modalEl.addEventListener('hidden.coreui.modal', function onHide() {
      modalEl.removeEventListener('hidden.coreui.modal', onHide);
      if (window.TrashBin) window.TrashBin.hide();
  });
}

async function confirmDeleteDomainAction() {
    const confirmBtn = document.getElementById('confirmDeleteDomainBtn');
    if (!confirmBtn) return;
    
    const modalEl = document.getElementById('confirmDeleteDomainModal');
    const deleteModal = coreui.Modal.getInstance(modalEl) || new coreui.Modal(modalEl);
    
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin me-1'></i> Deleting...";

    try {
        const response = await fetch("/api/domain/remove_domain", {
            method: "POST",
            body: JSON.stringify({ domain_id: currentDeleteDomainId }),
        });

        const result = await response.json();
        if (result.success) {
            deleteModal.hide();
            
            const card = document.getElementById(`domain-card-${currentDeleteDomainId}`);
            if (card) {
                window.TrashBin.animateDelete(card, "Domain successfully deleted.");
            } else {
                location.reload();
            }
        } else {
            alert("❌ " + result.error);
        }
    } catch (e) {
        alert("Connection error.");
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    }
}
window.confirmDeleteDomainAction = confirmDeleteDomainAction;

// Add this function for manual re-verification
async function verifyDomain(domainId) {
  try {
    const response = await fetch("/api/domain/verify_domain", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ domain_id: domainId }),
    });

    const result = await response.json();

    if (result.success) {
      if (result.verified) {
        alert("✅ Domain verified successfully!");
      } else {
        alert(
          `❌ Domain NOT verified. Please ensure A record points to: ${window.SERVER_IP}`,
        );
      }
      location.reload();
    } else {
      alert("Error: " + result.error);
    }
  } catch (e) {
    alert("Connection error: " + e.message);
  }
}

window.onPageLoad( function() {
  const modal = document.getElementById('addDomainModal');
  if (modal) {
    modal.addEventListener('show.coreui.modal', function() {
      const content = document.getElementById('addDomainModalContent');
      if (!content || content.getAttribute('data-loaded') === 'true') return;
      
      fetch('/api/domain/add')
        .then(res => res.text())
        .then(html => {
          content.innerHTML = html;
          content.setAttribute('data-loaded', 'true');
        })
        .catch(err => {
          console.error(err);
          content.innerHTML = '<div class="p-4 text-danger text-center">Failed to load form.</div>';
        });
    });
  }
});

    // --- Explicit Window Exports for Inline HTML ---
    window.addDomain = addDomain;
    window.deleteDomain = removeDomain;
    window.verifyDomain = verifyDomain;

    window.updateDomainPreview = function() {
        const providerSelect = document.getElementById("dns_provider");
        const domainInput = document.getElementById("choose_domain");
        const previewSpan = document.getElementById("preview_domain");
        const customNote = document.getElementById("custom_domain_note");
        
        if (!providerSelect || !domainInput || !previewSpan || !customNote) return;

        const provider = providerSelect.value;
        const prefix = domainInput.value.trim();
        
        let finalDomain;
        if (provider === "custom") {
            finalDomain = prefix || "yourdomain.com";
            customNote.classList.remove("d-none");
        } else {
            const tld = provider.replace("*", "");
            finalDomain = prefix ? (prefix + tld) : ("*" + tld);
            customNote.classList.add("d-none");
        }
        
        previewSpan.textContent = "https://" + finalDomain;
    };

  })();
} catch (e) {
  console.error("[Fatal Error in domain.js]", e);
}
