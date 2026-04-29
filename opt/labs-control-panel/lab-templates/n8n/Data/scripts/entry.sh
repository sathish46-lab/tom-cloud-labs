#!/bin/bash
# entry.sh - n8n Lab Startup

# 1. Wait for linkuser.sh to finish if it's running
sleep 2

# 2. Force Apache (optional, but good for static status page)
echo "[*] Enabling persistent Apache sites..."
find /etc/apache2/sites-available -name "*.conf" -exec a2ensite {} +

# 3. Start services
service ssh start
service apache2 restart

# 4. Configure WireGuard if config exists
if [ -f /etc/wireguard/wg0.conf ]; then
    echo "[*] Starting WireGuard..."
    wg-quick up wg0 || true
    ip route add 172.30.0.0/16 dev wg0 metric 10 2>/dev/null || true
fi

# 5. Keep container running
tail -f /dev/null
