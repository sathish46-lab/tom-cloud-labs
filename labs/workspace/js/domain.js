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
    const response = await fetch("/src/api/domain/add_domain.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ domain: finalDomain, type: type }),
    });

    const result = await response.json();

    if (result.success) {
      if (result.verified) {
        alert(`✅ Success! ${finalDomain} has been verified and added.`);
      } else {
        alert(
          `⚠️ Domain added but NOT verified yet.\n\nPlease point the A record to: ${window.SERVER_IP}\n\nThen click "Verify DNS" to check again.`,
        );
      }
      location.reload();
    } else {
      alert("❌ Error: " + result.error);
    }
  } catch (e) {
    console.error("Error details:", e);
    alert("Connection error occurred. Check console for details.");
  } finally {
    btn.disabled = false;
    btn.innerHTML = "Verify and Add";
  }
}

async function removeDomain(domainId) {
  if (
    !confirm(
      "Are you sure? This will break any active web links for this domain.",
    )
  )
    return;

  try {
    const response = await fetch("/src/api/domain/remove_domain.php", {
      method: "POST",
      body: JSON.stringify({ domain_id: domainId }),
    });

    const result = await response.json();
    if (result.success) {
      location.reload(); // Refresh the grid
    } else {
      alert("❌ " + result.error);
    }
  } catch (e) {
    alert("Connection error.");
  }
}

// Add this function for manual re-verification
async function verifyDomain(domainId) {
  try {
    const response = await fetch("/src/api/domain/verify_domain.php", {
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

document.addEventListener('DOMContentLoaded', function() {
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
