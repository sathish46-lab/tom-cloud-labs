let activeConfigRaw = "";

function toggleManualKey() {
    document.getElementById('manualKeyArea').style.display = document.getElementById('autoGenCheck').checked ? 'none' :
        'block';
}

function generateWGKeypair() {
    const keyPair = nacl.box.keyPair();
    return {
        private: btoa(String.fromCharCode(...keyPair.secretKey)),
        public: btoa(String.fromCharCode(...keyPair.publicKey))
    };
}

async function openVPNConnectionModal(id, name) {
    // 1. Show Loading State (could use a spinner in modal if needed)
    console.log("Fetching VPN config for:", id);

    try {
        const response = await fetch(`/api/vpn/connection_info?id=${id}`);
        const data = await response.json();

        if (data.status === 'success') {
            showConfig(name, data.data.config_raw);
        } else {
            alert('Failed to load configuration: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Network error occurred.');
    }
}

function showConfig(name, configRaw) {
    activeConfigRaw = configRaw;
    const html = activeConfigRaw
        .replace(/\[Interface\]/g, '<span class="config-header">[Interface]</span>')
        .replace(/\[Peer\]/g, '<span class="config-header">[Peer]</span>')
        .replace(/(PrivateKey|Address|DNS|PublicKey|AllowedIPs|Endpoint|PersistentKeepalive)/g,
            '<span class="config-label">$1</span>')
        .replace(/ = (.*)/g, ' = <span class="config-value">$1</span>');

    document.getElementById('configText').innerHTML = html.replace(/\n/g, '<br>');
    document.getElementById('configTitle').innerText = "Wireguard Config: " + name;

    const qrEl = document.getElementById("qrcode");
    qrEl.innerHTML = "";
    const qrcode = new QRCode(qrEl, {
        text: activeConfigRaw,
        width: 200,
        height: 200,
        correctLevel: QRCode.CorrectLevel.H
    });

    setTimeout(() => {
        const canvas = qrEl.querySelector('canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const center = canvas.width / 2;
            const bSize = canvas.width * 0.28;
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(center - bSize / 2, center - bSize / 2, bSize, bSize);
            ctx.fillStyle = "#0b0e14";
            ctx.font = "bold 16px Arial";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("TOM", center, center);
        }
    }, 120);
    new coreui.Modal(document.getElementById('configModal')).show();
}

function copyConfig() {
    navigator.clipboard.writeText(activeConfigRaw);
    alert("Config Copied!");
}

async function downloadTunnel(name, deviceId) {
    try {
        const response = await fetch(`/api/vpn/connection_info?id=${deviceId}`);
        const data = await response.json();

        if (data.status === 'success') {
            const blob = new Blob([data.data.config_raw], {
                type: 'text/plain'
            });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${name.replace(/\s+/g, '_')}.conf`;
            link.click();
        } else {
            alert("Download failed: " + data.error);
        }
    } catch (err) {
        alert("Network Error during download");
    }
}

const vpnForm = document.getElementById('vpnAddForm');
if (vpnForm) {
    vpnForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        if (document.getElementById('autoGenCheck').checked) {
            const keys = generateWGKeypair();
            document.getElementById('hiddenPrivKey').value = keys.private;
            document.getElementById('hiddenPubKey').value = keys.public;
        } else {
            document.getElementById('hiddenPubKey').value = document.getElementById('inputPubKey').value;
            document.getElementById('hiddenPrivKey').value = "";
        }
        btn.disabled = true;
        btn.innerText = 'Provisioning...';
        try {
            const res = await fetch('/api/vpn/add', {
                method: 'POST',
                body: new FormData(this)
            });
            if ((await res.json()).status === 'success') window.location.reload();
        } catch (err) {
            alert("Network Error");
        } finally {
            btn.disabled = false;
            btn.innerText = 'Verify and Add';
        }
    });
}

async function deleteDevice(dbId, pubKey) {
    if (!confirm("Delete this device?")) return;
    const res = await fetch('/api/vpn/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: dbId,
            public_key: pubKey
        })
    });
    if ((await res.json()).status === 'success') window.location.reload();
}

document.addEventListener('DOMContentLoaded', () => {
    let statsInterval = null;

    async function fetchStats() {
        // 1. Page Check: Only run if device rows exist
        if (document.querySelectorAll('.device-row').length === 0) return;

        try {
            // 2. Clean URL
            const response = await fetch('/api/vpn/stats');
            const data = await response.json();

            document.querySelectorAll('.device-row').forEach(card => {
                const live = data.find(d => d.id === card.dataset.pubkey);
                // 3. Auto-detect Offline/Online
                if (live) {
                    const pill = card.querySelector('.status-pill');
                    const icon = live.status === 'Online' ? 'bx-wifi' : (live.status === 'Unreachable' ? 'bx-time-five' : 'bx-wifi-off');

                    pill.innerHTML = `<i class='bx ${icon} me-1'></i> ${live.status.toUpperCase()}`;
                    pill.className = `badge rounded-pill status-pill bg-${live.color} fw-bold d-flex align-items-center`;

                    // Update Stats
                    const rxEl = card.querySelector('.rx-val');
                    const txEl = card.querySelector('.tx-val');
                    const originEl = card.querySelector('.origin-val');

                    if (rxEl) rxEl.innerText = live.rx;
                    if (txEl) txEl.innerText = live.tx;
                    if (originEl) originEl.innerText = live.origin;
                }
            });
        } catch (e) {
            console.warn("VPN Stats Error:", e);
        }
    }

    // 4. Smart Polling Controller
    function startPolling() {
        if (statsInterval) clearInterval(statsInterval);
        fetchStats(); // Capture fast (Immediate run)
        statsInterval = setInterval(fetchStats, 3000); // 3s for faster updates
    }

    function stopPolling() {
        if (statsInterval) {
            clearInterval(statsInterval);
            statsInterval = null;
        }
    }

    // 5. Visibility Handler
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling(); // Resumes and fetches immediately
        }
    });

    // Start if visible
    if (!document.hidden && document.querySelectorAll('.device-row').length > 0) {
        startPolling();
    }
});
