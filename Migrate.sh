#!/bin/bash

# Migrate.sh - Universal Setup (VPS & Docker)
# usage: sudo ./Migrate.sh

# Exit on error
set -e
export COMPOSER_ALLOW_SUPERUSER=1

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log() { echo -e "${GREEN}[INFO]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Professional Progress Bar Function
run_with_progress() {
    local cmd="$1"
    local desc="$2"
    
    # Run command in background
    eval "$cmd" > /tmp/migrate_cmd.log 2>&1 &
    local pid=$!
    
    local width=40
    local prog=0
    
    while kill -0 $pid 2>/dev/null; do
        prog=$((prog + 3))
        if [ $prog -gt 99 ]; then prog=99; fi
        
        local fill=$(( prog * width / 100 ))
        local empty=$(( width - fill ))
        
        printf "\r${GREEN}[INFO]${NC} %-35s [" "$desc"
        printf "%${fill}s" "" | tr ' ' '#'
        printf "%${empty}s" "" | tr ' ' '-'
        printf "] %3d%%" $prog
        
        sleep 0.2
    done
    
    wait $pid
    local status=$?
    
    if [ $status -eq 0 ]; then
        printf "\r${GREEN}[INFO]${NC} %-35s [" "$desc"
        printf "%${width}s" "" | tr ' ' '#'
        printf "] 100%%\n"
    else
        echo -e "\n${RED}[ERROR]${NC} Task failed! Check /tmp/migrate_cmd.log"
        cat /tmp/migrate_cmd.log
        exit 1
    fi
}

# Check for root privileges
if [ "$EUID" -ne 0 ]; then
  error "Please run as root"
  exit 1
fi

# Detect if we are already running inside the auto-vps mode (from Docker exec)
if [ "$1" == "--auto-vps" ]; then
    if [ -f "/tmp/migrate.env" ]; then
        source /tmp/migrate.env
    fi
    MODE="VPS"
    AUTO=1
else
    # Interactive Prompts
    echo "=================================================="
    echo "      Migrate.sh - Universal Setup"
    echo "=================================================="
    echo "Select Setup Type:"
    echo "  [1] VPS Bare-Metal Server Setup"
    echo "  [2] Docker Container Local Setup"
    read -p "Enter choice (1 or 2): " SETUP_CHOICE

    if [ "$SETUP_CHOICE" == "2" ]; then
        MODE="DOCKER"
    else
        MODE="VPS"
    fi
    AUTO=0

    read -p "Enter Main Domain (default: awshosting.in): " MAIN_DOMAIN
    export MAIN_DOMAIN=${MAIN_DOMAIN:-awshosting.in}

    read -p "Enter VPN API Domain (default: vpn.awshosting.in): " VPN_DOMAIN
    export VPN_DOMAIN=${VPN_DOMAIN:-vpn.awshosting.in}

    read -p "Enter RabbitMQ Domain (default: mq.awshosting.in): " MQS_DOMAIN
    export MQS_DOMAIN=${MQS_DOMAIN:-mq.awshosting.in}

    read -p "Enter Code Server Domain (default: tomweb.shop): " CODE_DOMAIN
    export CODE_DOMAIN=${CODE_DOMAIN:-tomweb.shop}

    read -p "Enter Work Domain (default: work.awshosting.in): " WORK_DOMAIN
    export WORK_DOMAIN=${WORK_DOMAIN:-work.awshosting.in}

    read -p "Enter Email for SSL generation (e.g., admin@example.com): " SSL_EMAIL
    export SSL_EMAIL=${SSL_EMAIL:-admin@example.com}

    echo "--------------------------------------------------"
    echo "Git Repository"
    echo "--------------------------------------------------"
    read -p "Enter Repository URL (default: https://git.selfmade.ninja/sathish46/labs.git): " MAIN_REPO
    export MAIN_REPO=${MAIN_REPO:-https://git.selfmade.ninja/sathish46/labs.git}

    echo "Note: If this is a private repository, you will be prompted for Username and Password (or Token)."

    echo "--------------------------------------------------"
    echo "Configuration Summary:"
    echo "  Mode:        $MODE"
    echo "  Main Domain: $MAIN_DOMAIN"
    echo "  VPN Domain:  $VPN_DOMAIN"
    echo "  MQS Domain:  $MQS_DOMAIN"
    echo "  Code Domain: $CODE_DOMAIN"
    echo "  SSL Email:   $SSL_EMAIL"
    echo "  Repository:  $MAIN_REPO"
    echo "--------------------------------------------------"
    read -p "Is this correct? (y/n): " CONFIRM
    if [[ "$CONFIRM" != "y" ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# ==========================================
# DOCKER MODE
# ==========================================
if [ "$MODE" == "DOCKER" ] && [ "$AUTO" == "0" ]; then
    log "Starting Docker Setup Mode..."

    # Create local directories for mapping
    mkdir -p ./traefik-conf/dynamic_conf ./opt/labs-control-panel ./var/www/vpn-api ./var/www/labs ./wireguard-conf ./rabbitmq-data ./mongo-data ./apache-logs

    # Clone the repo locally
    TEMP_WEB="/tmp/labs_clone_docker"
    rm -rf "$TEMP_WEB"
    run_with_progress "git clone \"$MAIN_REPO\" \"$TEMP_WEB\"" "Cloning Repository"
    
    # Copy repo files into mapped directories
    log "Populating mapped directories..."
    cp -R "$TEMP_WEB/labs/"* ./var/www/labs/ 2>/dev/null || true
    cp -R "$TEMP_WEB/vpn-api/"* ./var/www/vpn-api/ 2>/dev/null || true
    cp -R "$TEMP_WEB/opt/labs-control-panel/"* ./opt/labs-control-panel/ 2>/dev/null || true
    
    # Extract sample.json as env.json in local dir (will map to /var/www)
    if [ -f "$TEMP_WEB/sample.json" ]; then
        cp "$TEMP_WEB/sample.json" ./var/www/env.json
    elif [ -f "$TEMP_WEB/labs/sample.json" ]; then
        cp "$TEMP_WEB/labs/sample.json" ./var/www/env.json
    else
        echo "{}" > ./var/www/env.json
    fi
    
    # Extract session.json in local dir (will map to /var/www)
    if [ -f "$TEMP_WEB/session.json" ]; then
        cp "$TEMP_WEB/session.json" ./var/www/session.json
    elif [ -f "$TEMP_WEB/labs/session.json" ]; then
        cp "$TEMP_WEB/labs/session.json" ./var/www/session.json
    else
        echo "{}" > ./var/www/session.json
    fi
    
    rm -rf "$TEMP_WEB"

    log "Generating Dockerfile..."
    cat <<'OUTER_EOF_DOCKER' > Dockerfile
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV container docker

RUN apt-get update && \
    apt-get install -y systemd systemd-sysv sudo iputils-ping curl wget nano iptables iproute2 kmod tzdata software-properties-common gnupg2 jq \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN cd /lib/systemd/system/sysinit.target.wants/ || exit; \
    for i in *; do [ $i = systemd-tmpfiles-setup.service ] || rm -f $i; done; \
    rm -f /lib/systemd/system/multi-user.target.wants/*; \
    rm -f /etc/systemd/system/*.wants/*; \
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*; \
    rm -f /lib/systemd/system/anaconda.target.wants/*;

RUN add-apt-repository -y ppa:ondrej/php && \
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg --yes && \
    chmod a+r /etc/apt/keyrings/docker.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu noble stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null && \
    curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc | sudo gpg -o /usr/share/keyrings/mongodb-server-8.0.gpg --dearmor --yes && \
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/ubuntu noble/mongodb-org/8.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-8.0.list

RUN apt-get update && apt-get install -y \
    git curl unzip \
    apache2 libapache2-mod-php8.4 \
    php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-mongodb php8.4-amqp \
    rabbitmq-server \
    wireguard wireguard-tools \
    python3 python3-pip python3-pymongo python3-docker python3-redis python3-pika python3-psutil \
    docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
    ufw fail2ban nmap mongodb-org \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN pip3 install --break-system-packages google-generativeai requests pymongo

RUN ln -sf /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl && \
    chmod +x /opt/labs-control-panel/labsctl.py 2>/dev/null || true

RUN wget https://github.com/traefik/traefik/releases/download/v2.10.6/traefik_v2.10.6_linux_amd64.tar.gz && \
    tar -zxvf traefik_v2.10.6_linux_amd64.tar.gz && \
    mv traefik /usr/local/bin/ && \
    chmod +x /usr/local/bin/traefik && \
    rm traefik_v2.10.6_linux_amd64.tar.gz

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    npm install -g grunt-cli

RUN sed -i 's/#security:/security:\n  authorization: enabled/' /etc/mongod.conf && \
    sed -i 's/bindIp: 127.0.0.1/bindIp: 0.0.0.0/' /etc/mongod.conf

RUN mkdir -p /var/www/labs /var/www/vpn-api /opt/labs-control-panel /etc/traefik/dynamic_conf /etc/wireguard

RUN echo "Listen 8081\nListen 8082\n<IfModule ssl_module>\n    Listen 4431\n</IfModule>" > /etc/apache2/ports.conf
RUN touch /etc/apache2/code_server_map.txt

RUN echo "[Unit]\nDescription=Traefik Edge Router\nAfter=network-online.target\n[Service]\nRestart=on-failure\nExecStart=/usr/local/bin/traefik --configFile=/etc/traefik/traefik.yml\nLimitNOFILE=65536\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/traefik.service
RUN systemctl enable traefik

RUN echo "[Unit]\nDescription=Container Setup Script\nAfter=mongod.service rabbitmq-server.service network.target\n[Service]\nType=oneshot\nExecStart=/usr/local/bin/init-services.sh\nRemainAfterExit=yes\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/init-services.service
RUN systemctl enable mongod.service rabbitmq-server.service init-services.service

RUN echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/python3 /opt/labs-control-panel/labsctl.py *" > /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/local/bin/labsctl *" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/docker" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/ip" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/wg" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/cat /etc/wireguard/*" >> /etc/sudoers.d/labs-www-data && \
    echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/nmap" >> /etc/sudoers.d/labs-www-data && \
    chmod 440 /etc/sudoers.d/labs-www-data && \
    git config --system --add safe.directory /var/www && \
    git config --system --add safe.directory /var/www/labs

RUN systemctl mask docker.service docker.socket && \
    echo "L+ /run/docker.sock - - - - /var/docker.sock" > /etc/tmpfiles.d/docker-socket.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY init-services.sh /usr/local/bin/init-services.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/init-services.sh

VOLUME [ "/sys/fs/cgroup" ]
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
OUTER_EOF_DOCKER

    log "Generating entrypoint.sh..."
    cat <<'OUTER_EOF_ENTRYPOINT' > entrypoint.sh
#!/bin/bash
export MAIN_DOMAIN=${MAIN_DOMAIN:-awshosting.in}
export VPN_DOMAIN=${VPN_DOMAIN:-vpn.awshosting.in}
export MQS_DOMAIN=${MQS_DOMAIN:-mq.awshosting.in}
export CODE_DOMAIN=${CODE_DOMAIN:-tomweb.shop}
export WORK_DOMAIN=${WORK_DOMAIN:-work.awshosting.in}
export SSL_EMAIL=${SSL_EMAIL:-admin@example.com}

echo "[INFO] Running Pre-boot Initialization in Entrypoint..."

# Fix Traefik empty volume mount issues
mkdir -p /etc/traefik/dynamic_conf
touch /etc/traefik/acme.json && chmod 600 /etc/traefik/acme.json

echo "[INFO] Configuring WireGuard..."
sysctl -w net.ipv4.ip_forward=1 > /dev/null 2>&1

if [ ! -f /etc/wireguard/privatekey ]; then
    echo "[INFO] Generating new WireGuard keys..."
    PRIVATE_KEY=$(wg genkey)
    PUBLIC_KEY=$(echo "$PRIVATE_KEY" | wg pubkey)
    echo "$PRIVATE_KEY" > /etc/wireguard/privatekey
    echo "$PUBLIC_KEY" > /etc/wireguard/publickey
    chmod 600 /etc/wireguard/privatekey
else
    PRIVATE_KEY=$(cat /etc/wireguard/privatekey)
    PUBLIC_KEY=$(cat /etc/wireguard/publickey)
    echo "[INFO] Using existing WireGuard keys."
fi

EXISTING_PEERS=""
if [ -f /etc/wireguard/wg0.conf ]; then
    EXISTING_PEERS=$(awk '/^\[Peer\]/,0' /etc/wireguard/wg0.conf)
fi

DOCKER_BRIDGE=$(docker network inspect Prod_lab --format '{{.Id}}' 2>/dev/null | cut -c1-12)
if [ -n "$DOCKER_BRIDGE" ]; then
    BRIDGE_IF="br-${DOCKER_BRIDGE}"
else
    BRIDGE_IF=""
fi

cat <<EOF > /etc/wireguard/wg0.conf
[Interface]
Address = 172.30.0.1/16
PostUp = iptables -A FORWARD -i wg0 -o eth0 -j ACCEPT
PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PostUp = iptables -A FORWARD -i wg0 -o $BRIDGE_IF -j ACCEPT}
${BRIDGE_IF:+PostUp = iptables -t nat -I POSTROUTING -o $BRIDGE_IF -j MASQUERADE}
PreDown = iptables -D FORWARD -i wg0 -o eth0 -j ACCEPT
PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PreDown = iptables -D FORWARD -i wg0 -o $BRIDGE_IF -j ACCEPT}
${BRIDGE_IF:+PreDown = iptables -t nat -D POSTROUTING -o $BRIDGE_IF -j MASQUERADE}
ListenPort = 51820
PrivateKey = $PRIVATE_KEY
EOF

if [ -n "$EXISTING_PEERS" ]; then
    echo "" >> /etc/wireguard/wg0.conf
    echo "$EXISTING_PEERS" | awk '
        /^\[Peer\]/ { peer_block="[Peer]"; has_allowed=0; next }
        peer_block != "" && /^AllowedIPs/ { has_allowed=1; peer_block=peer_block"\n"$0; next }
        peer_block != "" && /^PublicKey/ { peer_block=peer_block"\n"$0; next }
        peer_block != "" && /^PersistentKeepalive/ { peer_block=peer_block"\n"$0; next }
        peer_block != "" && /^Endpoint/ { peer_block=peer_block"\n"$0; next }
        peer_block != "" && /^\[/ { if (has_allowed) print peer_block"\n"; peer_block="[Peer]"; has_allowed=0; next }
        peer_block != "" && /^$/ { if (has_allowed) print peer_block"\n"; peer_block=""; has_allowed=0; next }
        END { if (peer_block != "" && has_allowed) print peer_block }
    ' >> /etc/wireguard/wg0.conf
fi

sed -i '/^$/{ N; /^\n$/d }' /etc/wireguard/wg0.conf
chmod 600 /etc/wireguard/wg0.conf

echo "[INFO] Generating Apache VirtualHosts..."
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

cat <<EOF > /etc/apache2/sites-available/mqs.conf
<VirtualHost *:8081>
    ServerName $MQS_DOMAIN

    ProxyRequests Off
    ProxyPreserveHost On

    # 1. Handle the Native WebSocket for Overview Stats
    ProxyPass /stats-ws ws://127.0.0.1:8085/
    ProxyPassReverse /stats-ws ws://127.0.0.1:8085/
    
    ProxyPass /ws ws://127.0.0.1:15674/ws
    ProxyPassReverse /ws ws://127.0.0.1:15674/ws
    ProxyPass / http://127.0.0.1:15672/
    ProxyPassReverse / http://127.0.0.1:15672/
    Header set Sec-WebSocket-Protocol "v10.stomp, v11.stomp, v12.stomp"
</VirtualHost>
EOF

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

cat <<EOF > /etc/apache2/sites-available/code.conf
RewriteMap code_map "txt:/etc/apache2/code_server_map.txt"
ProxyTimeout 600
ProxyBadHeader Ignore

<VirtualHost *:8081>
    ServerName $CODE_DOMAIN
    ServerAlias *.$CODE_DOMAIN
    
    RewriteEngine On

    RewriteCond %{HTTP_HOST} ^([a-z0-9]+)\.$CODE_DOMAIN$ [NC]
    RewriteRule ^ - [E=HASH:%1]
    RewriteCond \${code_map:%{ENV:HASH}} ^(.+)$
    RewriteRule ^ - [E=TARGET_IP:%1]

    RewriteCond %{ENV:TARGET_IP} .
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/(.*)$ ws://%{ENV:TARGET_IP}:8080/\$1 [P,L]

    RewriteCond %{ENV:TARGET_IP} .
    RewriteRule ^/(.*)$ http://%{ENV:TARGET_IP}:8080/\$1 [P,L]

    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"

    RewriteCond %{ENV:TARGET_IP} ^$
    RewriteRule ^ - [L,R=404]
</VirtualHost>
EOF

cat <<EOF > /etc/apache2/sites-available/work.conf
<VirtualHost *:8081>
    ServerAdmin $SSL_EMAIL
    DocumentRoot "/var/www/work"
    ServerName $WORK_DOMAIN

    ErrorLog \${APACHE_LOG_DIR}/work_error.log
    CustomLog \${APACHE_LOG_DIR}/work_access.log combined

    <Directory "/var/www/work">
            Options -Indexes +FollowSymLinks -ExecCGI +Includes
            AllowOverride All
            Require all granted
    </Directory>
</VirtualHost>
EOF

echo "[INFO] Generating Traefik Configuration..."
cat <<EOF > /etc/traefik/traefik.yml
entryPoints:
  web:
    address: ":80"
  websecure:
    address: ":443"

certificatesResolvers:
  myresolver:
    acme:
      email: $SSL_EMAIL
      storage: /etc/traefik/acme.json
      httpChallenge:
        entryPoint: web

providers:
  file:
    directory: "/etc/traefik/dynamic_conf"
    watch: true
EOF

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
        - web

    vpns-router:
      rule: "Host(\`$VPN_DOMAIN\`)"
      service: vpn-api-service
      middlewares:
        - vpn-headers
      entryPoints:
        - web

    mqs-router:
      rule: "Host(\`$MQS_DOMAIN\`)"
      service: mqs-service
      entryPoints:
        - web

    code-server-router:
      rule: "HostRegexp(\`{subdomain:.+}.$CODE_DOMAIN\`)"
      service: code-server-service
      middlewares:
        - code-headers
      entryPoints:
        - web

    work-router:
      rule: "Host(\`$WORK_DOMAIN\`)"
      service: apache-service
      entryPoints:
        - web

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
EOF

echo "[INFO] Configuring env.json..."
if [ ! -f "/var/www/env.json" ]; then
    echo "{}" > /var/www/env.json
    chown www-data:www-data /var/www/env.json
fi

# Use sed to replace domains in a temp file to avoid bind mount "Device busy" error
sed "s/labs.tomweb.fun/$MAIN_DOMAIN/g; s/vpns.tomweb.fun/$VPN_DOMAIN/g; s/mqs.tomweb.fun/$MQS_DOMAIN/g" /var/www/env.json > /tmp/env.json
cp /tmp/env.json /var/www/env.json
rm /tmp/env.json

if [ -f /etc/wireguard/publickey ]; then
    WG_PUBKEY=$(cat /etc/wireguard/publickey)
    jq --arg key "$WG_PUBKEY" '.wireguard_public_key = $key' /var/www/env.json > /tmp/env.json
    cp /tmp/env.json /var/www/env.json
    rm /tmp/env.json
fi
chown www-data:www-data /var/www/env.json

echo "[INFO] Enabling Apache Sites..."
a2enmod rewrite proxy proxy_http proxy_wstunnel headers ssl
a2dissite 000-default.conf
a2ensite labs.conf mqs.conf wg-api.conf code.conf work.conf

echo "[INFO] Setting Directory Permissions..."
mkdir -p /var/log/labs && chown -R www-data:www-data /var/log/labs
mkdir -p /var/cache/labs && chown -R www-data:www-data /var/cache/labs
touch /var/log/labs_deploy.log && chown www-data:www-data /var/log/labs_deploy.log && chmod 664 /var/log/labs_deploy.log

if [ -f "/opt/labs-control-panel/labsctl.py" ]; then
    echo "[INFO] Linking Labs Control Panel..."
    chmod +x /opt/labs-control-panel/labsctl.py
    rm -f /usr/local/bin/labsctl
    ln -s /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl
fi

if [ -d "/opt/labs-control-panel/systemd/" ]; then
    echo "[INFO] Loading systemd units from Control Panel..."
    for service_file in /opt/labs-control-panel/systemd/*.service; do
        service_name=$(basename "$service_file")
        ln -sf "$service_file" "/etc/systemd/system/$service_name"
    done
    
    if [ -f "/opt/labs-control-panel/systemd/labs-worker@.service" ]; then
        ln -sf "/opt/labs-control-panel/systemd/labs-worker@.service" "/etc/systemd/system/labs-worker@.service"
        systemctl enable labs-worker@1 || true
    fi

    for service_file in /opt/labs-control-panel/systemd/*.service; do
        service_name=$(basename "$service_file")
        if [[ "$service_name" != *"@.service" ]]; then
            unit_name=$(basename "$service_file" .service)
            systemctl enable "$unit_name" || true
        fi
    done
fi

echo "[INFO] Setting up AI Worker systemd service..."
cat <<EOF > /etc/systemd/system/ai-worker.service
[Unit]
Description=LearnAI Worker
After=mongod.service rabbitmq-server.service network.target
Wants=mongod.service rabbitmq-server.service

[Service]
Type=simple
WorkingDirectory=/var/www/labs/worker
ExecStart=/usr/bin/python3 -u /var/www/labs/worker/ai_worker.py
Restart=always
RestartSec=5
StandardOutput=append:/var/www/labs/worker/ai_worker.log
StandardError=append:/var/www/labs/worker/ai_worker.log

[Install]
WantedBy=multi-user.target
EOF
systemctl enable ai-worker.service || true

echo "[INFO] Handing over control to systemd!"
exec /lib/systemd/systemd
OUTER_EOF_ENTRYPOINT
    
    # Inject variables
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/awshosting.in/$MAIN_DOMAIN/g" entrypoint.sh
        sed -i '' "s/vpn.awshosting.in/$VPN_DOMAIN/g" entrypoint.sh
        sed -i '' "s/mq.awshosting.in/$MQS_DOMAIN/g" entrypoint.sh
        sed -i '' "s/tomweb.shop/$CODE_DOMAIN/g" entrypoint.sh
        sed -i '' "s/admin@example.com/$SSL_EMAIL/g" entrypoint.sh
    else
        sed -i "s/awshosting.in/$MAIN_DOMAIN/g" entrypoint.sh
        sed -i "s/vpn.awshosting.in/$VPN_DOMAIN/g" entrypoint.sh
        sed -i "s/mq.awshosting.in/$MQS_DOMAIN/g" entrypoint.sh
        sed -i "s/tomweb.shop/$CODE_DOMAIN/g" entrypoint.sh
        sed -i "s/admin@example.com/$SSL_EMAIL/g" entrypoint.sh
    fi

    log "Generating init-services.sh..."
    cat <<'OUTER_EOF_SETUP' > init-services.sh
#!/bin/bash
export MAIN_DOMAIN=${MAIN_DOMAIN:-awshosting.in}
export VPN_DOMAIN=${VPN_DOMAIN:-vpn.awshosting.in}
export MQS_DOMAIN=${MQS_DOMAIN:-mq.awshosting.in}

echo "[INFO] Running post-boot container setup..."

if [ -S /var/docker.sock ]; then
    ln -sf /var/docker.sock /var/run/docker.sock
fi

if systemctl is-active --quiet rabbitmq-server; then
    rabbitmq-plugins enable rabbitmq_management rabbitmq_stomp rabbitmq_web_stomp || true
    if ! rabbitmqctl list_users | grep -qw "^admin"; then
        rabbitmqctl add_user admin RootTom@46
        rabbitmqctl set_user_tags admin administrator
    fi
    rabbitmqctl set_user_tags admin administrator
    rabbitmqctl set_permissions -p / admin ".*" ".*" ".*"
fi

if [ -d "/var/www/labs/htdocs" ]; then
    cd /var/www/labs/htdocs && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader || true
fi
if [ -d "/var/www/vpn-api" ]; then
    cd /var/www/vpn-api && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader || true
fi
if [ -d "/var/www/labs/workspace/grunt" ]; then
    cd /var/www/labs/workspace/grunt && npm install --unsafe-perm && grunt build || true
fi

if [ -f "/opt/labs-control-panel/requirements.txt" ]; then
    pip3 install -r /opt/labs-control-panel/requirements.txt --break-system-packages
fi

if [ -f "/var/www/vpn-api/syncnetwork.php" ]; then
    php /var/www/vpn-api/syncnetwork.php wg0 || echo "[WARN] Failed to sync network."
    if [ -f "/var/www/labs/workspace/tools/populate_ips.php" ]; then
        php /var/www/labs/workspace/tools/populate_ips.php || echo "[WARN] Failed to populate IPs."
    fi
fi

if [ -f "/etc/wireguard/wg0.conf" ]; then
    sysctl -w net.ipv4.ip_forward=1 > /dev/null 2>&1
    systemctl start wg-quick@wg0 || true
    sleep 2
    if ! wg show wg0 > /dev/null 2>&1; then
        wg-quick down wg0 2>/dev/null || true
        sleep 1
        wg-quick up wg0 || true
        sleep 2
    fi
fi

echo "[INFO] Setup complete!"
OUTER_EOF_SETUP
    
    # Inject variables
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/awshosting.in/$MAIN_DOMAIN/g" init-services.sh
        sed -i '' "s/vpn.awshosting.in/$VPN_DOMAIN/g" init-services.sh
        sed -i '' "s/mq.awshosting.in/$MQS_DOMAIN/g" init-services.sh
    else
        sed -i "s/awshosting.in/$MAIN_DOMAIN/g" init-services.sh
        sed -i "s/vpn.awshosting.in/$VPN_DOMAIN/g" init-services.sh
        sed -i "s/mq.awshosting.in/$MQS_DOMAIN/g" init-services.sh
    fi

    log "Generating .env for Docker Compose..."
    cat <<OUTER_EOF_ENV > .env
MAIN_DOMAIN=$MAIN_DOMAIN
VPN_DOMAIN=$VPN_DOMAIN
MQS_DOMAIN=$MQS_DOMAIN
CODE_DOMAIN=$CODE_DOMAIN
WORK_DOMAIN=$WORK_DOMAIN
SSL_EMAIL=$SSL_EMAIL
OUTER_EOF_ENV

    log "Generating docker-compose.yml..."
    cat <<'OUTER_EOF_COMPOSE' > docker-compose.yml
services:
  Prod_lab:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: Prod_lab
    privileged: true
    environment:
      - MAIN_DOMAIN=$MAIN_DOMAIN
      - VPN_DOMAIN=$VPN_DOMAIN
      - MQS_DOMAIN=$MQS_DOMAIN
      - CODE_DOMAIN=$CODE_DOMAIN
      - WORK_DOMAIN=$WORK_DOMAIN
      - SSL_EMAIL=$SSL_EMAIL
      - DOCKER_HOST=unix:///var/docker.sock
    extra_hosts:
      - "$MAIN_DOMAIN:127.0.0.1"
      - "$VPN_DOMAIN:127.0.0.1"
      - "$MQS_DOMAIN:127.0.0.1"
      - "$CODE_DOMAIN:127.0.0.1"
      - "$WORK_DOMAIN:127.0.0.1"
    volumes:
      - /sys/fs/cgroup:/sys/fs/cgroup:rw
      - ./var/www:/var/www
      - /var/run/docker.sock:/var/docker.sock
      - ./opt/labs-control-panel:/opt/labs-control-panel
      - ./rabbitmq-data:/var/lib/rabbitmq
      - ./wireguard-conf:/etc/wireguard
      - ./apache-logs:/var/log/apache2
      - ./traefik-conf:/etc/traefik
    tmpfs:
      - /run
      - /run/lock
      - /tmp
    networks:
      - Prod_lab
    ports:
      - "80:80"
      - "443:443"
      - "8081:8081"
      - "8082:8082"
      - "51820:51820/udp"
    stop_signal: SIGRTMIN+3

  mongodb:
    image: mongo:8.0
    container_name: docker_tomlabs_mongodb
    environment:
      MONGO_INITDB_ROOT_USERNAME: admin
      MONGO_INITDB_ROOT_PASSWORD: Tombootroot
    volumes:
      - ./mongo-data:/data/db
    networks:
      - Prod_lab
    ports:
      - "27018:27017"
    restart: always

networks:
  Prod_lab:
    name: Prod_lab
    driver: bridge
OUTER_EOF_COMPOSE

    # Fix ownership of all extracted/generated files so the user can edit them in their IDE
    if [ -n "$SUDO_USER" ]; then
        log "Fixing file ownership for user $SUDO_USER..."
        chown -R "$SUDO_USER" ./var ./opt ./traefik-conf ./wireguard-conf ./rabbitmq-data ./mongo-data ./apache-logs docker-compose.yml Dockerfile entrypoint.sh init-services.sh 2>/dev/null || true
    fi

    log "Generating config.json..."
    cat <<OUTER_EOF_CONFIG > ./opt/labs-control-panel/config.json
{
  "labctl_path": "/usr/local/bin/labsctl",
  "templates_dir": "/opt/labs-control-panel/lab-templates",
  "storage_path": "/var/tomlabs/storage/{user}",
  "app_log": "/var/log/labs/labctl.log",
  "config_path": "/etc/labs-control-panel/config.json",
  "docker_ip": "172.19.0.",
  "tunnel_ip": "172.30.0.",
  "docker_network_name": "Prod_lab",
  "orchestrator_container": "Prod_lab",
  "docker_build": "docker build -t {image_tag} {path}",
  "docker_run": "docker run --detach --name {lab_name} --memory='{memory}' --cpus='{cpus}' --network {network_name} --ip {ip} --cap-add=NET_ADMIN --device=/dev/net/tun --sysctl net.ipv6.conf.all.disable_ipv6=1 -v {storage}:{mount_target} --hostname {host_name} {image}",
  "docker_stop_rm": "docker stop {lab_name} && sudo docker rm -f {lab_name}",
  "docker_ps": "docker ps --format '{{.Names}}'",
  "docker_exec": "docker exec -d {lab_name} {script}",
  "docker_drop_shell": "docker exec -it {lab_name} {shell}",
  "traefik_conf_dir": "/etc/traefik/dynamic_conf",
  "acme_json_path": "/etc/traefik/acme.json",
  "wg_interface": "wg0",
  "log_format": "json",
  "log_file": "/var/log/labs/labctl.log"
}
OUTER_EOF_CONFIG

    log "Bringing up Docker Container..."
    docker compose up -d --build

    log "Ensuring composer dependencies are installed..."
    docker exec Prod_lab bash -c "cd /var/www/labs/htdocs && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader" || true
    docker exec Prod_lab bash -c "cd /var/www/vpn-api && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --optimize-autoloader" || true

    echo "=================================================="
    echo " Docker Local Setup Complete! "
    echo "=================================================="
    exit 0
fi

# ==========================================
# VPS MODE (or running inside Docker)
# ==========================================

# 1. System Updates & Dependencies
if command -v apt &> /dev/null; then
    log "Debian/Ubuntu detected ('apt' found). Updating system and installing dependencies..."
    export DEBIAN_FRONTEND=noninteractive
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

# Configure Docker for Docker-in-Docker compatability
log "Configuring Docker daemon..."
mkdir -p /etc/docker
cat <<EOF > /etc/docker/daemon.json
{
  "storage-driver": "vfs",
  "features": {
    "buildkit": false
  }
}
EOF
systemctl restart docker
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

    # Detect Docker bridge interface for tom_net
    DOCKER_BRIDGE=$(docker network inspect tom_net --format '{{.Id}}' 2>/dev/null | cut -c1-12 || true)
    if [ -n "$DOCKER_BRIDGE" ]; then
        BRIDGE_IF="br-${DOCKER_BRIDGE}"
        log "Detected Docker bridge: $BRIDGE_IF"
    else
        BRIDGE_IF=""
        warn "tom_net not found. Docker bridge routing will not be configured."
    fi

    cat <<EOF > /etc/wireguard/wg0.conf
[Interface]
Address = 172.30.0.1/16
SaveConfig = true
PostUp = iptables -A FORWARD -i wg0 -o eth0 -j ACCEPT
PostUp = iptables -t nat -I POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PostUp = iptables -A FORWARD -i wg0 -o $BRIDGE_IF -j ACCEPT}
${BRIDGE_IF:+PostUp = iptables -t nat -I POSTROUTING -o $BRIDGE_IF -j MASQUERADE}
PreDown = iptables -D FORWARD -i wg0 -o eth0 -j ACCEPT
PreDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
${BRIDGE_IF:+PreDown = iptables -D FORWARD -i wg0 -o $BRIDGE_IF -j ACCEPT}
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
    apt install -y mongodb-org
else
    log "MongoDB is already installed."
fi

# Ensure MongoDB is started before configuration
log "Starting MongoDB..."
systemctl start mongod || true
sleep 3

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
npm install -g grunt-cli || true

else
    warn "Unsupported OS or 'apt' not found. Skipping Linux system package installation."
    warn "Ensure dependencies (Docker, Apache, PHP, MongoDB, Node, etc.) are installed manually."
fi

# 2. Application Setup (Git Clone)
if [ "$AUTO" == "0" ]; then
    log "Setting up application from Git..."

    # Prepare temp directory for cloning web repo
    TEMP_WEB="/tmp/labs_clone"
    rm -rf "$TEMP_WEB"

    run_with_progress "git clone \"$MAIN_REPO\" \"$TEMP_WEB\"" "Cloning Repository"

    # Create destinations
    mkdir -p /var/www/labs
    mkdir -p /var/www/vpn-api
    mkdir -p /opt/labs-control-panel

    log "Copying repo content to destinations..."
    # Logic to handle repo structure (root or nested)
    if [ -d "$TEMP_WEB/labs" ]; then
        cp -R "$TEMP_WEB/labs/"* /var/www/labs/
    else
        cp -R "$TEMP_WEB/"* /var/www/labs/
    fi

    if [ -d "$TEMP_WEB/vpn-api" ]; then
        cp -R "$TEMP_WEB/vpn-api/"* /var/www/vpn-api/
    fi

    if [ -d "$TEMP_WEB/opt/labs-control-panel" ]; then
        cp -R "$TEMP_WEB/opt/labs-control-panel/"* /opt/labs-control-panel/
    fi

    # Copy sample.json as env.json template
    if [ -f "$TEMP_WEB/sample.json" ]; then
        cp "$TEMP_WEB/sample.json" /var/www/env.json
    elif [ -f "$TEMP_WEB/labs/sample.json" ]; then
        cp "$TEMP_WEB/labs/sample.json" /var/www/env.json
    else
        warn "sample.json not found in repo. Please configure /var/www/env.json manually."
    fi
    rm -rf "$TEMP_WEB"
else
    log "Auto VPS mode: Repositories are already mounted."
    # Ensure env.json exists since it was mapped
    if [ ! -f "/var/www/env.json" ]; then
        if [ -f "/var/www/labs/sample.json" ]; then
            cp "/var/www/labs/sample.json" "/var/www/env.json"
        fi
    fi
fi

# 3. GENERATE CONFIGURATION FILES (Self-Contained)
if command -v apt &> /dev/null; then
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

    # 1. Handle the Native WebSocket for Overview Stats
    ProxyPass /stats-ws ws://127.0.0.1:8085/
    ProxyPassReverse /stats-ws ws://127.0.0.1:8085/

    # 1.5 Handle the STOMP WebSocket for the Overview Stats
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
  websecure:
    address: ":443"

providers:
  file:
    directory: "/etc/traefik/dynamic_conf"
    watch: true
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
        - web

    vpns-router:
      rule: "Host(\`$VPN_DOMAIN\`)"
      service: vpn-api-service
      middlewares:
        - vpn-headers
      entryPoints:
        - web

    mqs-router:
      rule: "Host(\`$MQS_DOMAIN\`)"
      service: mqs-service
      entryPoints:
        - web

    code-server-router:
      rule: "HostRegexp(\`{subdomain:.+}.$CODE_DOMAIN\`)"
      service: code-server-service
      middlewares:
        - code-headers
      entryPoints:
        - web

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

else
    log "Skipping Apache/Traefik config generation on non-Ubuntu OS."
fi

# 4. Config Domain Replacement (env.json)
log "Configuring env.json..."
sed -i "s/labs.tomweb.fun/$MAIN_DOMAIN/g" /var/www/env.json
sed -i "s/vpns.tomweb.fun/$VPN_DOMAIN/g" /var/www/env.json
sed -i "s/mqs.tomweb.fun/$MQS_DOMAIN/g" /var/www/env.json

# 5. Enable Apache Configs
if command -v apt &> /dev/null; then
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

else
    log "Skipping Apache enable and system permissions on non-Ubuntu OS."
fi

# Allow git operations for www-data in /var/www
git config --system --add safe.directory /var/www || true
git config --system --add safe.directory /var/www/labs || true

# Python
if [ -f "/opt/labs-control-panel/requirements.txt" ]; then
    pip3 install -r /opt/labs-control-panel/requirements.txt --break-system-packages || true
fi

# Labsctl
if [ -f "/opt/labs-control-panel/labsctl.py" ]; then
    chmod +x /opt/labs-control-panel/labsctl.py
    rm -f /usr/local/bin/labsctl
    ln -s /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl
fi

# Install systemd services from repo
if command -v apt &> /dev/null; then
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

else
    log "Skipping systemd service installation on non-Ubuntu OS."
fi

# Build
if [ -d "/var/www/labs/htdocs" ]; then
    cd /var/www/labs/htdocs
    COMPOSER_ALLOW_SUPERUSER=1 /usr/local/bin/composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/vpn-api" ]; then
    cd /var/www/vpn-api
    COMPOSER_ALLOW_SUPERUSER=1 /usr/local/bin/composer install --no-interaction --optimize-autoloader
fi
if [ -d "/var/www/labs/workspace/grunt" ]; then
    cd /var/www/labs/workspace/grunt
    npm install
    grunt build
fi

# Security
if command -v apt &> /dev/null; then
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8081/tcp
ufw allow 8082/tcp
ufw allow 5672/tcp
ufw allow 15672/tcp
ufw allow 27017/tcp
ufw allow 51820/udp

# Restart
systemctl restart apache2
systemctl start traefik

# 8. Configure RabbitMQ
log "Configuring RabbitMQ..."
systemctl enable rabbitmq-server
systemctl start rabbitmq-server

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

systemctl enable mongod
systemctl start mongod

else
    log "Skipping UFW and service restarts on non-Ubuntu OS."
fi

# 9. Initialize VPN Network
if [ -f "/var/www/vpn-api/syncnetwork.php" ]; then
    log "Initializing VPN Network Pool..."
    php /var/www/vpn-api/syncnetwork.php wg0 || true
    php /var/www/labs/workspace/tools/populate_ips.php || true
fi

log "=================================================="
log " Migration Complete! "
log "=================================================="
echo "Verify URLs:"
echo "  https://$MAIN_DOMAIN"
echo "  https://$VPN_DOMAIN"
echo "  https://$MQS_DOMAIN"
echo "  MongoDB: mongodb://admin:Tombootroot@127.0.0.1:27018/admin"
