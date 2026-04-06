#!/bin/bash

# migrate.sh - Application Migration Component
# usage: sudo ./migrate.sh

# Exit on error
set -e
export COMPOSER_ALLOW_SUPERUSER=1

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Helper function for logging
log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check for root privileges
if [ "$EUID" -ne 0 ]; then
  error "Please run as root"
  exit 1
fi

# Interactive Configuration
echo "=================================================="
echo "      Interactive Configuration Setup"
echo "=================================================="

read -p "Enter Main Domain (default: awshosting.in): " MAIN_DOMAIN
MAIN_DOMAIN=${MAIN_DOMAIN:-awshosting.in}

read -p "Enter VPN API Domain (default: vpn.awshosting.in): " VPN_DOMAIN
VPN_DOMAIN=${VPN_DOMAIN:-vpn.awshosting.in}

read -p "Enter RabbitMQ Domain (default: mq.awshosting.in): " MQS_DOMAIN
MQS_DOMAIN=${MQS_DOMAIN:-mq.awshosting.in}

read -p "Enter Code Server Domain (default: tomweb.shop): " CODE_DOMAIN
CODE_DOMAIN=${CODE_DOMAIN:-tomweb.shop}

read -p "Enter Email for SSL generation (e.g., admin@example.com): " SSL_EMAIL

echo "--------------------------------------------------"
echo "Git Repositories"
echo "--------------------------------------------------"
read -p "Enter Web Repository URL (default: https://git.selfmade.ninja/sathish46/labs.git): " WEB_REPO
WEB_REPO=${WEB_REPO:-https://git.selfmade.ninja/sathish46/labs.git}

read -p "Enter Control Panel Repository URL (default: https://git.selfmade.ninja/sathish46/labsctl.git): " CTL_REPO
CTL_REPO=${CTL_REPO:-https://git.selfmade.ninja/sathish46/labsctl.git}

echo "Note: If these are private repositories, you will be prompted for Username and Password (or Token)."

echo "--------------------------------------------------"
echo "Configuration Summary:"
echo "  Main Domain: $MAIN_DOMAIN"
echo "  VPN Domain:  $VPN_DOMAIN"
echo "  MQS Domain:  $MQS_DOMAIN"
echo "  Code Domain: $CODE_DOMAIN"
echo "  SSL Email:   $SSL_EMAIL"
echo "  Web Repo:    $WEB_REPO"
echo "  Ctrl Repo:   $CTL_REPO"
echo "--------------------------------------------------"
read -p "Is this correct? (y/n): " CONFIRM
if [[ "$CONFIRM" != "y" ]]; then
    echo "Aborted."
    exit 0
fi

# 1. System Updates & Dependencies
log "Updating system and installing dependencies..."
apt update && apt upgrade -y
apt install -y software-properties-common

# Add PHP PPA for latest versions (fixes outdated mongodb extension)
log "Adding Ondrej PHP PPA..."
add-apt-repository -y ppa:ondrej/php
apt update
    
# Add Docker Official Repo
log "Adding Docker Repository..."
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg --yes
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
apt update

# Add MongoDB 8.0 Repo (Ubuntu 24.04 / Noble)
log "Adding MongoDB 8.0 Repository..."
curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc | sudo gpg -o /usr/share/keyrings/mongodb-server-8.0.gpg --dearmor --yes
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/ubuntu noble/mongodb-org/8.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-8.0.list
apt update

apt install -y \
    git curl unzip \
    apache2 libapache2-mod-php8.4 \
    php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-mongodb php8.4-amqp \
    rabbitmq-server \
    wireguard wireguard-tools \
    python3 python3-pip \
    docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# WireGuard Setup
if [ ! -f "/etc/wireguard/wg0.conf" ]; then
    log "Configuring WireGuard (wg0)..."
    mkdir -p /etc/wireguard
    chmod 700 /etc/wireguard
    
    PRIVATE_KEY=$(wg genkey)
    PUBLIC_KEY=$(echo "$PRIVATE_KEY" | wg pubkey)
    
    echo "$PRIVATE_KEY" > /etc/wireguard/privatekey
    echo "$PUBLIC_KEY" > /etc/wireguard/publickey
    chmod 600 /etc/wireguard/privatekey

    # Detect Docker bridge interface for tomlabs_net
    DOCKER_BRIDGE=$(docker network inspect tomlabs_net --format '{{.Id}}' 2>/dev/null | cut -c1-12)
    if [ -n "$DOCKER_BRIDGE" ]; then
        BRIDGE_IF="br-${DOCKER_BRIDGE}"
        log "Detected Docker bridge: $BRIDGE_IF"
    else
        BRIDGE_IF=""
        warn "tomlabs_net not found. Docker bridge routing will not be configured."
    fi

    cat <<EOF > /etc/wireguard/wg0.conf
[Interface]
Address = 172.30.0.1/16
SaveConfig = true
PostUp = ufw route allow in on wg0 out on eth0
PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PostUp = ufw route allow in on wg0 out on $BRIDGE_IF}
${BRIDGE_IF:+PostUp = iptables -t nat -I POSTROUTING -o $BRIDGE_IF -j MASQUERADE}
PreDown = ufw route delete allow in on wg0 out on eth0
PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PreDown = ufw route delete allow in on wg0 out on $BRIDGE_IF}
${BRIDGE_IF:+PreDown = iptables -t nat -D POSTROUTING -o $BRIDGE_IF -j MASQUERADE}
ListenPort = 51820
PrivateKey = $PRIVATE_KEY
EOF
    # Remove empty lines from conditional bridge expansion
    sed -i '/^$/d' /etc/wireguard/wg0.conf
    chmod 600 /etc/wireguard/wg0.conf
    systemctl enable wg-quick@wg0
    systemctl start wg-quick@wg0
    log "WireGuard configured with Public Key: $PUBLIC_KEY"
else
    log "WireGuard configuration already exists."
fi

apt install -y \
    ufw fail2ban \
    nmap

# Install Traefik
if ! command -v traefik &> /dev/null; then
    log "Installing Traefik..."
    # Download latest release (adjust version if needed)
    wget https://github.com/traefik/traefik/releases/download/v2.10.6/traefik_v2.10.6_linux_amd64.tar.gz
    tar -zxvf traefik_v2.10.6_linux_amd64.tar.gz
    mv traefik /usr/local/bin/
    chmod +x /usr/local/bin/traefik
    rm traefik_v2.10.6_linux_amd64.tar.gz
fi

# Install MongoDB
if ! command -v mongod &> /dev/null; then
    log "Installing MongoDB 8.0..."
    log "Installing MongoDB 8.0..."
    apt install -y mongodb-org
else
    log "MongoDB is already installed."
fi

# Configure MongoDB Auth & User (Always run to ensure correct state)
log "Configuring MongoDB Auth..."
AUTH_CHANGED=0
if grep -q "#security:" /etc/mongod.conf; then
    sed -i 's/#security:/security:\n  authorization: enabled/' /etc/mongod.conf
    AUTH_CHANGED=1
elif ! grep -q "authorization:.*enabled" /etc/mongod.conf; then
    echo -e "security:\n  authorization: enabled" >> /etc/mongod.conf
    AUTH_CHANGED=1
fi

# Enable Remote Access (bindIp: 0.0.0.0)
if grep -q "bindIp: 127.0.0.1" /etc/mongod.conf; then
    log "Enabling MongoDB Remote Access (0.0.0.0)..."
    sed -i 's/bindIp: 127.0.0.1/bindIp: 0.0.0.0/' /etc/mongod.conf
    AUTH_CHANGED=1
fi

if [ "$AUTH_CHANGED" -eq 1 ]; then
    log "Restarting MongoDB to apply auth changes..."
    systemctl restart mongod
    sleep 5
fi

# Create Admin User (idempotent check)
log "Ensuring MongoDB Admin User exists..."
# Attempt to create user without auth first (localhost exception), then with auth if it fails
mongosh admin --eval 'try { db.createUser({user: "admin", pwd: "Tombootroot", roles: [{ role: "root", db: "admin" }]}) } catch(e) { print("Creating without auth failed, trying with auth..."); try { db.getSiblingDB("admin").auth("admin", "Tombootroot"); db.createUser({user: "admin", pwd: "Tombootroot", roles: [{ role: "root", db: "admin" }]}) } catch(e2) { print("User likely already exists or auth failed: " + e2) } }'

# Install Node.js
if ! command -v node &> /dev/null; then
    log "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
fi

# Install Composer
if ! command -v composer &> /dev/null; then
    log "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Grunt CLI
log "Installing Grunt CLI..."
npm install -g grunt-cli

# 2. Application Setup (Git Clone)
log "Setting up application from Git..."

# Prepare temp directory for cloning web repo
TEMP_WEB="/tmp/labs_web_clone"
rm -rf "$TEMP_WEB"

log "Cloning Web Repository ($WEB_REPO)..."
git clone "$WEB_REPO" "$TEMP_WEB" || { error "Failed to clone Web Repo. Check access."; exit 1; }

# Create destinations
mkdir -p /var/www/labs
mkdir -p /var/www/vpn-api
mkdir -p /opt/labs-control-panel

log "Copying repo content to /var/www/labs and /var/www/vpn-api..."
# Logic to handle repo structure (root or nested)
if [ -d "$TEMP_WEB/labs" ]; then
    cp -R "$TEMP_WEB/labs/"* /var/www/labs/
else
    cp -R "$TEMP_WEB/"* /var/www/labs/
fi

if [ -d "$TEMP_WEB/vpn-api" ]; then
    cp -R "$TEMP_WEB/vpn-api/"* /var/www/vpn-api/
fi

# Copy test.json as env.json template
if [ -f "$TEMP_WEB/test.json" ]; then
    cp "$TEMP_WEB/test.json" /var/www/env.json
elif [ -f "$TEMP_WEB/labs/test.json" ]; then
    cp "$TEMP_WEB/labs/test.json" /var/www/env.json
else
    warn "test.json not found in repo. Please configure /var/www/env.json manually."
fi
rm -rf "$TEMP_WEB"

# Clone Control Panel Repo
log "Cloning Control Panel Repository ($CTL_REPO)..."
git clone "$CTL_REPO" /opt/labs-control-panel || warn "Failed to clone Control Panel Repo."


# 3. GENERATE CONFIGURATION FILES (Self-Contained)
log "Generating Configuration Files..."

# Apache Ports
cat <<EOF > /etc/apache2/ports.conf
# /etc/apache2/ports.conf
Listen 8081
Listen 8082
<IfModule ssl_module>
    Listen 4431
</IfModule>
EOF

# Code Server Map
cat <<EOF > /etc/apache2/code_server_map.txt
# Placeholder - Update with real mappings
EOF

# Apache Sites
# labs.conf
cat <<EOF > /etc/apache2/sites-available/labs.conf
<VirtualHost *:8081>
    ServerAdmin $SSL_EMAIL
    DocumentRoot "/var/www/labs/htdocs"
    ServerName $MAIN_DOMAIN

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined

    <Directory "/var/www/labs">
            Options -Indexes +FollowSymLinks -ExecCGI +Includes
            AllowOverride All
            Require all granted
    </Directory>
</VirtualHost>
EOF

# mqs.conf
cat <<EOF > /etc/apache2/sites-available/mqs.conf
<VirtualHost *:8081>
    ServerName $MQS_DOMAIN

    ProxyRequests Off
    ProxyPreserveHost On

    # 1. Handle the STOMP WebSocket for the Overview Stats
    ProxyPass /ws ws://127.0.0.1:15674/ws
    ProxyPassReverse /ws ws://127.0.0.1:15674/ws

    # 2. Handle standard RabbitMQ Management traffic
    ProxyPass / http://127.0.0.1:15672/
    ProxyPassReverse / http://127.0.0.1:15672/

    Header set Sec-WebSocket-Protocol "v10.stomp, v11.stomp, v12.stomp"
</VirtualHost>
EOF

# wg-api.conf
cat <<EOF > /etc/apache2/sites-available/wg-api.conf
<VirtualHost *:8082>
    ServerAdmin $SSL_EMAIL
    DocumentRoot "/var/www/vpn-api"
    ServerName $VPN_DOMAIN

    ErrorLog \${APACHE_LOG_DIR}/vpn_error.log
    CustomLog \${APACHE_LOG_DIR}/vpn_access.log combined

    <Directory "/var/www/vpn-api">
            Options Indexes FollowSymLinks ExecCGI Includes
            AllowOverride All
            Require all granted
    </Directory>
</VirtualHost>
EOF

# code.conf (For code-server logic via Apache)
cat <<EOF > /etc/apache2/sites-available/code.conf
RewriteMap code_map "txt:/etc/apache2/code_server_map.txt"
ProxyTimeout 600
ProxyBadHeader Ignore

# Note: This listens on 8081/HTTP, proxied by Traefik
<VirtualHost *:8081>
    ServerName $CODE_DOMAIN
    ServerAlias *.$CODE_DOMAIN
    
    # Logic adapted for non-SSL Apache (Traefik does SSL)
    RewriteEngine On

    # 1. Map subdomain hash -> IP
    RewriteCond %{HTTP_HOST} ^([a-z0-9]+)\.$CODE_DOMAIN$ [NC]
    RewriteRule ^ - [E=HASH:%1]
    RewriteCond \${code_map:%{ENV:HASH}} ^(.+)$
    RewriteRule ^ - [E=TARGET_IP:%1]

    # 2. WebSocket requests
    RewriteCond %{ENV:TARGET_IP} .
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/(.*)$ ws://%{ENV:TARGET_IP}:8080/\$1 [P,L]

    # 3. Normal HTTP requests
    RewriteCond %{ENV:TARGET_IP} .
    RewriteRule ^/(.*)$ http://%{ENV:TARGET_IP}:8080/\$1 [P,L]

    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"

    # 4. Fallback 404 when no mapping
    RewriteCond %{ENV:TARGET_IP} ^$
    RewriteRule ^ - [L,R=404]
</VirtualHost>
EOF


# Traefik Config
mkdir -p /etc/traefik/dynamic_conf

# traefik.yml
cat <<EOF > /etc/traefik/traefik.yml
entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https

  websecure:
    address: ":443"

providers:
  file:
    directory: "/etc/traefik/dynamic_conf"
    watch: true

certificatesResolvers:
  myresolver:
    acme:
      email: $SSL_EMAIL
      storage: "/etc/traefik/acme.json"
      httpChallenge:
        entryPoint: web
EOF

# dynamic_conf.yml
cat <<EOF > /etc/traefik/dynamic_conf/dynamic_conf.yml
http:
  middlewares:
    code-headers:
      headers:
        customRequestHeaders:
          X-Forwarded-Proto: "https"
    vpn-headers:
      headers:
        customRequestHeaders:
          X-Forwarded-Proto: "https"
        accessControlAllowMethods: ["GET", "POST", "OPTIONS"]
        accessControlAllowOriginList: ["*"]

  routers:
    labs-router:
      rule: "Host(\`$MAIN_DOMAIN\`)"
      service: apache-service
      entryPoints:
        - websecure
      tls:
        certResolver: myresolver

    vpns-router:
      rule: "Host(\`$VPN_DOMAIN\`)"
      service: vpn-api-service
      middlewares:
        - vpn-headers
      entryPoints:
        - websecure
      tls:
        certResolver: myresolver

    mqs-router:
      rule: "Host(\`$MQS_DOMAIN\`)"
      service: mqs-service
      entryPoints:
        - websecure
      tls:
        certResolver: myresolver

    code-server-router:
      rule: "HostRegexp(\`{subdomain:.+}.$CODE_DOMAIN\`)"
      service: code-server-service
      middlewares:
        - code-headers
      entryPoints:
        - websecure
      tls:
        certResolver: myresolver

  services:
    apache-service:
      loadBalancer:
        servers:
          - url: "http://127.0.0.1:8081"
    mqs-service:
      loadBalancer:
        servers:
          - url: "http://127.0.0.1:8081"
    vpn-api-service:
      loadBalancer:
        servers:
          - url: "http://127.0.0.1:8082"
    code-server-service:
      loadBalancer:
        servers:
          - url: "http://127.0.0.1:8081" 
          # Note: Pointing code-server to Apache (8081) because Apache handles the RewriteMap logic
          # The original dynamic_conf pointed to 172.40.0.240:8080 (loadbalancer?), but user implies Apache does it via code.conf?
          # Actually, the original dynamic_conf had code-server-service pointing to 172.40.0.240:8080 directly.
          # BUT code.conf exists in Apache. This is redundant or alternative.
          # Giving the user's setup, traffic likely goes Traefik -> Apache (for map) -> Container?
          # Or Traefik -> Container directly?
          # The Apache 'code.conf' does the mapping. Traefik supports HostRegexp but not RewriteMap easily.
          # So Traefik should point 'code-server-router' to Apache (8081).
          # In original file:
          # service: code-server-service -> loadBalancer -> servers -> url: "http://172.40.0.240:8080"
          # This contradicts Apache code.conf. 
          # I will default to pointing to Apache (8081) so Apache logic works.
          # If user wants direct, they can edit.
EOF

# Setup acme.json permission
touch /etc/traefik/acme.json
chmod 600 /etc/traefik/acme.json

# Traefik Service
cat <<EOF > /etc/systemd/system/traefik.service
[Unit]
Description=Traefik Edge Router
Documentation=https://doc.traefik.io/traefik/
After=network-online.target
Wants=network-online.target systemd-networkd-wait-online.service

[Service]
Restart=on-failure
ExecStart=/usr/local/bin/traefik --configFile=/etc/traefik/traefik.yml
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
EOF

log "Reloading systemd and enabling Traefik..."
systemctl daemon-reload
systemctl enable traefik

# 4. Config Domain Replacement (env.json)
log "Configuring env.json..."
sed -i "s/labs.tomweb.fun/$MAIN_DOMAIN/g" /var/www/env.json
sed -i "s/vpns.tomweb.fun/$VPN_DOMAIN/g" /var/www/env.json
sed -i "s/mqs.tomweb.fun/$MQS_DOMAIN/g" /var/www/env.json
# Replace Email/Secrets placeholders if needed (not interactive here to avoid complexity, user should check file)
# But we can try substituting known keys if they match patterns

# 5. Enable Apache Configs
log "Enabling Apache Configs..."
# Ensure correct PHP version (8.4) is used
a2dismod php8.3 || true
a2enmod php8.4 || true

a2enmod rewrite proxy proxy_http proxy_wstunnel headers ssl
a2dissite 000-default.conf
a2ensite labs.conf mqs.conf wg-api.conf code.conf

# 6. Permissions & Final Setup
chown -R www-data:www-data /var/www/env.json
mkdir -p /var/log/labs && chown -R www-data:www-data /var/log/labs
mkdir -p /var/cache/labs && chown -R www-data:www-data /var/cache/labs
touch /var/log/labs_deploy.log && chown www-data:www-data /var/log/labs_deploy.log && chmod 664 /var/log/labs_deploy.log

# Log Rotation
log "Configuring log rotation..."
cat <<EOF > /etc/logrotate.d/labs-deploy
/var/log/labs_deploy.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 664 www-data www-data
}
EOF

# Create Sudoers for www-data
log "Configuring sudoers for www-data..."
cat <<EOF > /etc/sudoers.d/labs-www-data
www-data ALL=(ALL) NOPASSWD: /usr/bin/python3 /opt/labs-control-panel/labsctl.py *
www-data ALL=(ALL) NOPASSWD: /usr/bin/python3 /opt/labs-control-panel/labsctl.py
www-data ALL=(ALL) NOPASSWD: /usr/bin/docker
www-data ALL=(ALL) NOPASSWD: /usr/bin/ip
www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick
www-data ALL=(ALL) NOPASSWD: /usr/bin/cat /etc/wireguard/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmap
EOF
chmod 440 /etc/sudoers.d/labs-www-data

# Allow git operations for www-data in /var/www
git config --system --add safe.directory /var/www
git config --system --add safe.directory /var/www/labs

# Python
if [ -f "/opt/labs-control-panel/requirements.txt" ]; then
    pip3 install -r /opt/labs-control-panel/requirements.txt --break-system-packages
fi

# Labsctl
if [ -f "/opt/labs-control-panel/labsctl.py" ]; then
    chmod +x /opt/labs-control-panel/labsctl.py
    rm -f /usr/local/bin/labsctl
    ln -s /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl

fi

# Install systemd services from repo
log "Installing systemd services from /opt/labs-control-panel/systemd/..."
for service_file in /opt/labs-control-panel/systemd/*.service; do
    service_name=$(basename "$service_file")
    # Skip template units (files ending in @.service)
    if [[ "$service_name" == *"@.service" ]]; then
        continue
    fi
    ln -sf "$service_file" "/etc/systemd/system/$service_name"
    log "Linked $service_name"
done

# Handle Template Units specifically
if [ -f "/opt/labs-control-panel/systemd/labs-worker@.service" ]; then
    ln -sf "/opt/labs-control-panel/systemd/labs-worker@.service" "/etc/systemd/system/labs-worker@.service"
    log "Linked labs-worker@.service template"
    
    # Enable specific instances (e.g., 1)
    systemctl enable labs-worker@1
    systemctl start labs-worker@1
    log "Enabled and started labs-worker@1"
fi

systemctl daemon-reload

for service_file in /opt/labs-control-panel/systemd/*.service; do
    service_name=$(basename "$service_file")
    # Skip template units for direct start
    if [[ "$service_name" == *"@.service" ]]; then
        continue
    fi
    
    # Strip .service for systemctl commands
    unit_name=$(basename "$service_file" .service)
    
    systemctl enable "$unit_name"
    systemctl start "$unit_name"
    log "Enabled and started $unit_name"
done

# Build
if [ -d "/var/www/labs/htdocs" ]; then
    cd /var/www/labs/htdocs
    composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/vpn-api" ]; then
    cd /var/www/vpn-api
    composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/labs/workspace/grunt" ]; then
    cd /var/www/labs/workspace/grunt
    npm install
    grunt build
fi

# Security
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8081/tcp
ufw allow 8082/tcp
ufw allow 5672/tcp
ufw allow 15672/tcp
ufw allow 27017/tcp
ufw allow 51820/udp
# ufw enable 

# Restart
systemctl restart apache2
systemctl start traefik
# 8. Configure RabbitMQ
log "Configuring RabbitMQ..."
rabbitmq-plugins enable rabbitmq_management rabbitmq_stomp rabbitmq_web_stomp || true
# Create RabbitMQ Admin User
if ! rabbitmqctl list_users | grep -qw "^admin"; then
    log "Creating RabbitMQ Admin User..."
    rabbitmqctl add_user admin RootTom@46
    rabbitmqctl set_user_tags admin administrator
else
    log "RabbitMQ Admin User already exists."
fi
# Always ensure permissions and tags are correct
rabbitmqctl set_user_tags admin administrator
rabbitmqctl set_permissions -p / admin ".*" ".*" ".*"

systemctl enable rabbitmq-server
systemctl start rabbitmq-server
systemctl enable mongod
systemctl start mongod

# 9. Initialize VPN Network
if [ -f "/var/www/vpn-api/syncnetwork.php" ]; then
    log "Initializing VPN Network Pool..."
    php /var/www/vpn-api/syncnetwork.php wg0
    php /var/www/labs/workspace/tools/lib/populate_ips.php
fi

log "=================================================="
log " Migration Complete! "
log "=================================================="
echo "Verify URLs:"
echo "  https://$MAIN_DOMAIN"
echo "  https://$VPN_DOMAIN"
echo "  https://$MQS_DOMAIN"
