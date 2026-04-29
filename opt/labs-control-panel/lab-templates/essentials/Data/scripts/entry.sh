#!/bin/bash
# entry.sh - Container startup

# 1. Wait for linkuser.sh to finish if it's running (prevents race conditions)
sleep 2

# 2. Force Apache to recognize symlinked configs
# We re-enable any .conf files found in the persistent htconfig
echo "[*] Enabling persistent Apache sites..."
find /etc/apache2/sites-available -name "*.conf" -exec a2ensite {} +

# 3. Start services
service ssh start
service apache2 restart

# Configure WireGuard if config exists
if [ -f /etc/wireguard/wg0.conf ]; then
    echo "[*] Starting WireGuard..."
    wg-quick up wg0 || true
    
    # Add routing for VPN network
    ip route add 172.30.0.0/16 dev wg0 metric 10 2>/dev/null || true
fi

# Keep container running
tail -f /dev/null