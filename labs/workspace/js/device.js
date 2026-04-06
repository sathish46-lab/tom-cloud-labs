
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

function buildConfigString(ip, privKey) {
    const key = privKey || "<PASTE_YOUR_PRIVATE_KEY>";
    return `[Interface]\nPrivateKey = ${key}\nAddress = ${ip}/32\nDNS = 1.1.1.1\n\n[Peer]\nPublicKey = d5fV23F8CsH603vBs+z70c/q7iN9ZK6dWU5vsdh5SDE=\nAllowedIPs = 172.30.0.0/16\nEndpoint = vpns.tomweb.fun:51820\nPersistentKeepalive = 25`;
}

function showConfig(name, ip, privKey) {
    activeConfigRaw = buildConfigString(ip, privKey);
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

function downloadTunnel(name, ip, privKey) {
    const blob = new Blob([buildConfigString(ip, privKey)], {
        type: 'text/plain'
    });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${name.replace(/\s+/g, '_')}.conf`;
    link.click();
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
            const res = await fetch('/api/vpn/add.php', {
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
    const res = await fetch('/api/vpn/delete.php', {
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
                    const pill = card.querySelector('.status-pill:last-child');
                    pill.innerText = live.status;

                    // Update Color Class
                    pill.className = `badge rounded-pill small status-pill bg-${live.color} px-2 py-1`;

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
