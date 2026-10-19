#!/bin/bash
set -u
# /usr/local/bin/init-services.sh

# This script is executed by systemd after MongoDB and RabbitMQ start.

# Default values if not set
export MAIN_DOMAIN=${MAIN_DOMAIN:-tomweb.in}
export VPN_DOMAIN=${VPN_DOMAIN:-vpn.tomweb.in}
export MQS_DOMAIN=${MQS_DOMAIN:-mq.tomweb.in}

echo "[INFO] Running post-boot container setup..."

# Docker Socket Symlink (Ensures tools like labsctl find the API after systemd boot)
if [ -S /var/docker.sock ]; then
    echo "[INFO] Creating Docker socket symlink..."
    ln -sf /var/docker.sock /var/run/docker.sock
fi

# 1. RabbitMQ Configuration
if systemctl is-active --quiet rabbitmq-server; then
    echo "[INFO] Waiting for RabbitMQ to be fully ready..."
    until rabbitmqctl status >/dev/null 2>&1; do
        sleep 2
    done
    # Erlang cookie must be rabbitmq-owned and owner-only so the web app can
    # escalate to the rabbitmq user (sudo -u rabbitmq rabbitmqctl) for CLI calls.
    if [ -f /var/lib/rabbitmq/.erlang.cookie ]; then
        chown rabbitmq:rabbitmq /var/lib/rabbitmq/.erlang.cookie
        chmod 0400 /var/lib/rabbitmq/.erlang.cookie
    fi
    rabbitmq-plugins enable rabbitmq_management rabbitmq_stomp rabbitmq_web_stomp || true
    # Create RabbitMQ Admin User and verify it exists
    until rabbitmqctl list_users | grep -qw "^admin"; do
        echo "[INFO] Creating RabbitMQ Admin User..."
        rabbitmqctl add_user "${RABBITMQ_USER:-admin}" "${RABBITMQ_PASS}" || true
        sleep 2
    done

    # Set and verify permissions for the Admin User
    until rabbitmqctl list_permissions -p / | grep -qw "^admin"; do
        echo "[INFO] Setting RabbitMQ Admin Permissions..."
        rabbitmqctl set_user_tags admin administrator || true
        rabbitmqctl set_permissions -p / admin ".*" ".*" ".*" || true
        sleep 2
    done
    echo "[INFO] RabbitMQ Admin User verified and permissions applied."
fi

# 1.5. MySQL Configuration (root@localhost password + root@'%' so TCP drivers work)
if systemctl is-active --quiet mysql; then
    echo "[INFO] Configuring MySQL root password..."
    MYSQL_ROOT_PASS=$(jq -r '.mysql_root_pass // empty' /var/www/env.json 2>/dev/null)
    if [ -n "$MYSQL_ROOT_PASS" ]; then
        MYSQL_SQL="ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_ROOT_PASS}'; CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MYSQL_ROOT_PASS}'; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"
        mysql --protocol=socket -u root -p"${MYSQL_ROOT_PASS}" -e "${MYSQL_SQL}" 2>/dev/null || \
        mysql --protocol=socket -u root -e "${MYSQL_SQL}" 2>/dev/null || true
        echo "[INFO] MySQL root password verified."
    else
        echo "[WARN] mysql_root_pass missing in env.json — skipping MySQL root password."
    fi
fi

# 1.6. MariaDB-labs Configuration (Port 3307, binaries in /opt/mariadb-labs)
if systemctl is-active --quiet mariadb-labs.service; then
    MARIADB_ROOT_PASS=$(jq -r '.mysql_root_pass // empty' /var/www/env.json 2>/dev/null)
    echo "[INFO] Waiting for MariaDB-labs socket..."
    until { [ -n "$MARIADB_ROOT_PASS" ] && /opt/mariadb-labs/usr/bin/mariadb-admin --defaults-file=/etc/mysql/mariadb-labs.cnf -p"${MARIADB_ROOT_PASS}" ping >/dev/null 2>&1; } || \
          /opt/mariadb-labs/usr/bin/mariadb-admin --defaults-file=/etc/mysql/mariadb-labs.cnf ping >/dev/null 2>&1; do
        sleep 2
    done
    # Driver and labsctl connect over TCP as root; 127.0.0.1 matches root@localhost,
    # so root@localhost must carry the password (root@'%' alone is not enough).
    if [ -n "$MARIADB_ROOT_PASS" ]; then
        MARIADB_SQL="ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASS}'; CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASS}'; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;"
        /opt/mariadb-labs/usr/bin/mariadb --defaults-file=/etc/mysql/mariadb-labs.cnf -p"${MARIADB_ROOT_PASS}" -e "${MARIADB_SQL}" 2>/dev/null || \
        /opt/mariadb-labs/usr/bin/mariadb --defaults-file=/etc/mysql/mariadb-labs.cnf -e "${MARIADB_SQL}" 2>/dev/null || true
        echo "[INFO] MariaDB-labs root password verified."
    else
        echo "[WARN] mysql_root_pass missing in env.json — skipping MariaDB-labs root password."
    fi
fi

# 1.7. PostgreSQL Configuration (admin password must match mysql_root_pass — PostgreSqlDriver uses it)
if systemctl is-active --quiet postgresql; then
    echo "[INFO] Configuring PostgreSQL Admin password..."
    PG_ADMIN_PASS=$(jq -r '.mysql_root_pass // empty' /var/www/env.json 2>/dev/null)
    if [ -n "$PG_ADMIN_PASS" ]; then
        sudo -u postgres psql -c "CREATE ROLE tomlabs_admin WITH LOGIN SUPERUSER PASSWORD '${PG_ADMIN_PASS}';" 2>/dev/null || true
        sudo -u postgres psql -c "ALTER ROLE tomlabs_admin WITH PASSWORD '${PG_ADMIN_PASS}';" 2>/dev/null || true
        echo "[INFO] PostgreSQL tomlabs_admin password verified."
    else
        echo "[WARN] mysql_root_pass missing in env.json — skipping PostgreSQL admin password."
    fi
fi

# 1.8. Redis Configuration
if systemctl is-active --quiet redis-server; then
    echo "[INFO] Configuring Redis Admin password..."
    redis-cli ACL SETUSER default on '>tomlabs_redis_secret' ~* +@all 2>/dev/null || true
    redis-cli ACL SAVE 2>/dev/null || true
fi

# 1.9. MongoDB learner admin user (fresh datadir → localhost exception allows bootstrap)
if systemctl is-active --quiet mongod; then
    MONGO_USER=$(jq -r '.mongo_user // "admin"' /var/www/env.json 2>/dev/null)
    MONGO_PASS=$(jq -r '.mongo_pass // empty' /var/www/env.json 2>/dev/null)
    if [ -n "$MONGO_USER" ] && [ -n "$MONGO_PASS" ]; then
        echo "[INFO] Waiting for mongod..."
        for i in $(seq 1 30); do
            mongosh --quiet --eval 'db.runCommand({ping:1})' >/dev/null 2>&1 && break
            sleep 1
        done
        MONGO_BOOT=$(mongosh --quiet --eval "
            const admin = db.getSiblingDB('admin');
            try {
                admin.runCommand({createUser: '${MONGO_USER}', pwd: '${MONGO_PASS}', roles: [{role: 'root', db: 'admin'}]});
                print('MONGO_BOOTSTRAP_CREATED');
            } catch (e) {
                if (/already exists/i.test(e.message)) { print('MONGO_BOOTSTRAP_EXISTS'); }
                else { print('MONGO_BOOTSTRAP_ERR ' + e.message); }
            }" 2>/dev/null | tail -1)
        case "$MONGO_BOOT" in
            *CREATED*) echo "[INFO] MongoDB learner admin user '${MONGO_USER}' created." ;;
            *EXISTS*)  echo "[INFO] MongoDB learner admin user '${MONGO_USER}' already present." ;;
            *)         echo "[WARN] MongoDB bootstrap: ${MONGO_BOOT:-no output}" ;;
        esac
    else
        echo "[WARN] mongo_pass missing in env.json — skipping MongoDB admin bootstrap."
    fi
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

# Detect the bridge interface from config or env.json
DOCKER_NETWORK=$(jq -r '.docker_network_name // empty' /opt/labs-control-panel/config.json 2>/dev/null)
if [ -z "$DOCKER_NETWORK" ] || [ "$DOCKER_NETWORK" = "null" ]; then
    DOCKER_NETWORK=$(jq -r '.docker_network_name // empty' /var/www/env.json 2>/dev/null)
fi
if [ -z "$DOCKER_NETWORK" ] || [ "$DOCKER_NETWORK" = "null" ]; then
    DOCKER_NETWORK=$(docker network ls --format '{{.Name}}' | grep -E "(dev_lab_frontend|Dev_lab)" | head -n 1)
fi
if [ -z "$DOCKER_NETWORK" ] || [ "$DOCKER_NETWORK" = "null" ]; then
    DOCKER_NETWORK="dev_lab_frontend"
fi

BRIDGE_ID=$(docker network inspect "$DOCKER_NETWORK" -f '{{.Id}}' 2>/dev/null | cut -c1-12)
if [ -n "$BRIDGE_ID" ]; then
    BRIDGE_IF="br-${BRIDGE_ID}"
    
    TUNNEL_PREFIX=$(jq -r '.tunnel_ip // empty' /opt/labs-control-panel/config.json 2>/dev/null)
    if [ -z "$TUNNEL_PREFIX" ] || [ "$TUNNEL_PREFIX" = "null" ]; then
        TUNNEL_PREFIX=$(jq -r '.tunnel_ip // empty' /var/www/env.json 2>/dev/null)
    fi
    if [ -z "$TUNNEL_PREFIX" ] || [ "$TUNNEL_PREFIX" = "null" ]; then
        echo "FATAL: tunnel_ip not set in config.json or env.json"
        exit 1
    fi
    TUNNEL_SUBNET="${TUNNEL_PREFIX}0/16"

    # Ensure forwarding rules between wg0 and Docker bridge
    iptables -C FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null
    iptables -C FORWARD -i wg0 -o "$BRIDGE_IF" -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i wg0 -o "$BRIDGE_IF" -j ACCEPT 2>/dev/null
    iptables -C FORWARD -i "$BRIDGE_IF" -o wg0 -j ACCEPT 2>/dev/null || \
        iptables -A FORWARD -i "$BRIDGE_IF" -o wg0 -j ACCEPT 2>/dev/null
    iptables -t nat -C POSTROUTING -s "$TUNNEL_SUBNET" -o eth0 -j MASQUERADE 2>/dev/null || \
        iptables -t nat -A POSTROUTING -s "$TUNNEL_SUBNET" -o eth0 -j MASQUERADE 2>/dev/null
    
    # For each WireGuard peer, re-create the host route to its Docker container
    # Peer AllowedIPs maps to Docker IP
    if wg show wg0 allowed-ips 2>/dev/null | grep -q '/32'; then
        wg show wg0 allowed-ips | while read -r _pubkey allowed_ip_cidr; do
            # Extract just the IP (strip /32)
            tunnel_ip=$(echo "$allowed_ip_cidr" | sed 's|/32||')
            if [ -n "$tunnel_ip" ] && [ "$tunnel_ip" != "${TUNNEL_PREFIX}1" ]; then
                # Derive Docker IP: last octet of tunnel IP -> {DOCKER_IP_PREFIX}{last_octet}
                DOCKER_IP_PREFIX=$(jq -r '.docker_ip // empty' /opt/labs-control-panel/config.json 2>/dev/null)
                if [ -z "$DOCKER_IP_PREFIX" ] || [ "$DOCKER_IP_PREFIX" = "null" ]; then
                    DOCKER_IP_PREFIX=$(jq -r '.docker_ip // empty' /var/www/env.json 2>/dev/null)
                fi
                if [ -z "$DOCKER_IP_PREFIX" ] || [ "$DOCKER_IP_PREFIX" = "null" ]; then
                    DOCKER_IP_PREFIX="172.19.0."
                fi
                last_octet=$(echo "$tunnel_ip" | awk -F. '{print $4}')
                docker_ip="${DOCKER_IP_PREFIX}${last_octet}"
                
                # Check if the container with this Docker IP exists and is running
                if docker network inspect "$DOCKER_NETWORK" --format '{{range .Containers}}{{.IPv4Address}} {{end}}' 2>/dev/null | grep -q "$docker_ip"; then
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
    echo "[WARN] $DOCKER_NETWORK bridge not found. Lab routes will be created at deploy time."
fi

# 7. Start SSH Server
echo "[INFO] Starting SSH server..."
mkdir -p /run/sshd
/usr/sbin/sshd || true
fail2ban-client stop sshd 2>/dev/null || true

# 8. Reload Traefik

echo "[INFO] Setup complete!"
