#!/bin/bash
# /usr/local/bin/container-setup.sh

# This script is executed by systemd after MongoDB and RabbitMQ start.

# Default values if not set
export MAIN_DOMAIN=${MAIN_DOMAIN:-awshosting.in}
export VPN_DOMAIN=${VPN_DOMAIN:-vpn.awshosting.in}
export MQS_DOMAIN=${MQS_DOMAIN:-mq.awshosting.in}

echo "[INFO] Running post-boot container setup..."

# Docker Socket Symlink (Ensures tools like labsctl find the API after systemd boot)
if [ -S /var/docker.sock ]; then
    echo "[INFO] Creating Docker socket symlink..."
    ln -sf /var/docker.sock /var/run/docker.sock
fi

# 1. RabbitMQ Configuration
if systemctl is-active --quiet rabbitmq-server; then
    rabbitmq-plugins enable rabbitmq_management rabbitmq_stomp rabbitmq_web_stomp || true
    if ! rabbitmqctl list_users | grep -qw "^admin"; then
        echo "[INFO] Creating RabbitMQ Admin User..."
        rabbitmqctl add_user admin RootTom@46
        rabbitmqctl set_user_tags admin administrator
    fi
    rabbitmqctl set_user_tags admin administrator
    rabbitmqctl set_permissions -p / admin ".*" ".*" ".*"
fi

# 2. Build dependencies (if directories exist)
if [ -d "/var/www/labs/htdocs" ]; then
    echo "[INFO] Installing Composer dependencies for labs..."
    cd /var/www/labs/htdocs && composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/vpn-api" ]; then
    echo "[INFO] Installing Composer dependencies for vpn-api..."
    cd /var/www/vpn-api && composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/labs/workspace/grunt" ]; then
    echo "[INFO] Installing Node attributes for grunt..."
    cd /var/www/labs/workspace/grunt && npm install && grunt build
fi

# 3. Python Requirements
if [ -f "/opt/labs-control-panel/requirements.txt" ]; then
    echo "[INFO] Installing Python requirements..."
    pip3 install -r /opt/labs-control-panel/requirements.txt --break-system-packages
fi

# 4. VPN Network Sync
if [ -f "/var/www/vpn-api/syncnetwork.php" ]; then
    echo "[INFO] Initializing VPN Network Pool..."
    php /var/www/vpn-api/syncnetwork.php wg0 || echo "[WARN] Failed to sync network."
    if [ -f "/var/www/labs/workspace/tools/populate_ips.php" ]; then
        php /var/www/labs/workspace/tools/populate_ips.php || echo "[WARN] Failed to populate IPs."
    fi
fi

# 5. WireGuard Start & Health Check
if [ -f "/etc/wireguard/wg0.conf" ]; then
    echo "[INFO] Starting WireGuard (wg0)..."
    
    # Ensure IP forwarding is enabled
    sysctl -w net.ipv4.ip_forward=1 > /dev/null 2>&1
    
    # Bring up WireGuard
    systemctl start wg-quick@wg0 || true
    sleep 2
    
    # Health check - if wg0 isn't working, bounce it
    if ! wg show wg0 > /dev/null 2>&1; then
        echo "[WARN] WireGuard failed to start, bouncing interface..."
        wg-quick down wg0 2>/dev/null || true
        sleep 1
        wg-quick up wg0 || true
        sleep 2
    fi
    
    if wg show wg0 > /dev/null 2>&1; then
        echo "[INFO] WireGuard is running."
        wg show wg0 | head -4
    else
        echo "[ERROR] WireGuard failed to start after retry."
    fi
fi

# 6. Re-apply Host Routing for Existing Lab Peers
echo "[INFO] Recovering host routes for deployed labs..."

# Detect the tomlabs_net bridge interface
BRIDGE_ID=$(docker network inspect tomlabs_net -f '{{.Id}}' 2>/dev/null | cut -c1-12)
if [ -n "$BRIDGE_ID" ]; then
    BRIDGE_IF="br-${BRIDGE_ID}"
    
    # Ensure forwarding rules between wg0 and Docker bridge
    iptables -C FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null
    iptables -C FORWARD -i wg0 -o "$BRIDGE_IF" -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i wg0 -o "$BRIDGE_IF" -j ACCEPT 2>/dev/null
    iptables -C FORWARD -i "$BRIDGE_IF" -o wg0 -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i "$BRIDGE_IF" -o wg0 -j ACCEPT 2>/dev/null
    iptables -t nat -C POSTROUTING -s 172.30.0.0/16 -o eth0 -j MASQUERADE 2>/dev/null || \
        iptables -t nat -A POSTROUTING -s 172.30.0.0/16 -o eth0 -j MASQUERADE 2>/dev/null
    
    # For each WireGuard peer, re-create the host route to its Docker container
    # Peer AllowedIPs (172.30.x.y) maps to Docker IP (10.30.0.y) where y is the last octet
    if wg show wg0 allowed-ips 2>/dev/null | grep -q '/32'; then
        wg show wg0 allowed-ips | while read -r _pubkey allowed_ip_cidr; do
            # Extract just the IP (strip /32)
            tunnel_ip=$(echo "$allowed_ip_cidr" | sed 's|/32||')
            if [ -n "$tunnel_ip" ] && [ "$tunnel_ip" != "172.30.0.1" ]; then
                # Derive Docker IP: last octet of tunnel IP -> 10.30.0.{last_octet}
                last_octet=$(echo "$tunnel_ip" | awk -F. '{print $4}')
                docker_ip="10.30.0.${last_octet}"
                
                # Check if the container with this Docker IP exists and is running
                if docker network inspect tomlabs_net --format '{{range .Containers}}{{.IPv4Address}} {{end}}' 2>/dev/null | grep -q "$docker_ip"; then
                    ip route del "$tunnel_ip/32" 2>/dev/null || true
                    ip route add "$tunnel_ip/32" via "$docker_ip" dev "$BRIDGE_IF" 2>/dev/null || true
                    echo "[INFO] Route restored: $tunnel_ip -> $docker_ip via $BRIDGE_IF"
                else
                    echo "[INFO] No container at $docker_ip for peer $tunnel_ip, skipping route."
                fi
            fi
        done
    fi
else
    echo "[WARN] tomlabs_net bridge not found. Lab routes will be created at deploy time."
fi

# 7. Reload Traefik

echo "[INFO] Setup complete!"
