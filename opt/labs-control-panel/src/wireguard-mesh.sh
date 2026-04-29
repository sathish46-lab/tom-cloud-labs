#!/bin/bash
# setup-wireguard-mesh-fixed.sh
# Fixed setup for Ubuntu 22.04+ with correct MongoDB service name

set -e

echo "=========================================="
echo "  WireGuard Mesh Lab System Setup"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (sudo)"
    exit 1
fi

# Detect OS
echo "[0/7] Detecting system..."
OS_VERSION=$(lsb_release -rs 2>/dev/null || echo "unknown")
echo "    OS: Ubuntu $OS_VERSION"
echo ""

# Step 1: Install dependencies
echo "[1/7] Installing dependencies..."

# Check what's already installed
if command -v docker &>/dev/null; then
    echo "    ✓ Docker already installed: $(docker --version | cut -d' ' -f3)"
else
    apt update
    apt install -y docker.io docker-compose
    systemctl enable docker
    systemctl start docker
    echo "    ✓ Docker installed"
fi

if command -v wg &>/dev/null; then
    echo "    ✓ WireGuard already installed"
else
    apt install -y wireguard
    echo "    ✓ WireGuard installed"
fi

# MongoDB installation varies by version
if command -v mongod &>/dev/null; then
    echo "    ✓ MongoDB already installed: $(mongod --version | grep 'db version' | cut -d' ' -f3)"
    
    # Detect correct service name
    if systemctl list-unit-files | grep -q "^mongod.service"; then
        MONGO_SERVICE="mongod"
    elif systemctl list-unit-files | grep -q "^mongodb.service"; then
        MONGO_SERVICE="mongodb"
    else
        echo "    ! MongoDB installed but service not found"
        echo "    ! Checking if it's running..."
        if pgrep -x mongod > /dev/null; then
            echo "    ✓ MongoDB is running (no systemd service)"
            MONGO_SERVICE="none"
        else
            echo "    ! Starting MongoDB manually..."
            mongod --fork --logpath /var/log/mongodb.log --dbpath /var/lib/mongodb
            MONGO_SERVICE="manual"
        fi
    fi
else
    echo "    Installing MongoDB..."
    apt install -y mongodb-org 2>/dev/null || apt install -y mongodb 2>/dev/null || {
        echo "    ! MongoDB package not found in repos"
        echo "    ! Installing from official MongoDB repo..."
        
        # Import MongoDB public key
        curl -fsSL https://www.mongodb.org/static/pgp/server-7.0.asc | apt-key add -
        
        # Add MongoDB repository
        echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-7.0.list
        
        apt update
        apt install -y mongodb-org
        MONGO_SERVICE="mongod"
    }
    echo "    ✓ MongoDB installed"
fi

# Enable and start MongoDB service
if [ "$MONGO_SERVICE" != "none" ] && [ "$MONGO_SERVICE" != "manual" ]; then
    echo "    Starting MongoDB service: $MONGO_SERVICE"
    systemctl enable $MONGO_SERVICE 2>/dev/null || true
    systemctl restart $MONGO_SERVICE 2>/dev/null || true
    sleep 2
    
    if systemctl is-active --quiet $MONGO_SERVICE; then
        echo "    ✓ MongoDB service is running"
    else
        echo "    ! MongoDB service failed to start, trying manual start..."
        mongod --fork --logpath /var/log/mongodb.log --dbpath /var/lib/mongodb 2>/dev/null || true
    fi
fi

# Verify MongoDB is accessible
if mongosh --eval "db.version()" &>/dev/null 2>&1 || mongo --eval "db.version()" &>/dev/null 2>&1; then
    echo "    ✓ MongoDB is accessible"
else
    echo "    ! Warning: MongoDB may not be running correctly"
fi

# Install Python packages
apt install -y python3-pip python3-pymongo net-tools
echo "    ✓ Python packages installed"
echo ""

# Step 2: Configure WireGuard server
echo "[2/7] Configuring WireGuard server..."

if [ ! -f /etc/wireguard/wg0.conf ]; then
    # Generate server keys
    SERVER_PRIVATE_KEY=$(wg genkey)
    SERVER_PUBLIC_KEY=$(echo $SERVER_PRIVATE_KEY | wg pubkey)
    
    # Detect public interface
    PUBLIC_IFACE=$(ip route | grep default | awk '{print $5}' | head -1)
    
    cat > /etc/wireguard/wg0.conf <<EOF
[Interface]
PrivateKey = $SERVER_PRIVATE_KEY
Address = 172.30.0.1/16
ListenPort = 51820
PostUp = sysctl -w net.ipv4.ip_forward=1
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT
PostUp = iptables -A FORWARD -o wg0 -j ACCEPT
PostUp = iptables -t nat -A POSTROUTING -o $PUBLIC_IFACE -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT
PostDown = iptables -D FORWARD -o wg0 -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -o $PUBLIC_IFACE -j MASQUERADE

# Peers will be added dynamically
EOF
    
    chmod 600 /etc/wireguard/wg0.conf
    
    # Save public key for later
    mkdir -p /opt/labs-control-panel
    echo $SERVER_PUBLIC_KEY > /opt/labs-control-panel/.wg-server-pubkey
    
    echo "    ✓ WireGuard configured"
    echo "    Server Public Key: $SERVER_PUBLIC_KEY"
else
    echo "    ✓ WireGuard already configured"
fi
echo ""

# Step 3: Start WireGuard
echo "[3/7] Starting WireGuard..."

# Enable IP forwarding permanently
sysctl -w net.ipv4.ip_forward=1
echo "net.ipv4.ip_forward=1" > /etc/sysctl.d/99-wireguard.conf

systemctl enable wg-quick@wg0
systemctl restart wg-quick@wg0
sleep 2

if wg show wg0 &>/dev/null; then
    echo "    ✓ WireGuard is running"
    wg show wg0 | head -3 | sed 's/^/    /'
else
    echo "    ✗ WireGuard failed to start"
    echo "    Checking logs..."
    journalctl -u wg-quick@wg0 --no-pager -n 20
    exit 1
fi
echo ""

# Step 4: Create Docker networks
echo "[4/7] Creating Docker networks..."

if ! docker network inspect labs_management &>/dev/null; then
    docker network create \
        --driver bridge \
        --subnet 10.11.0.0/16 \
        --gateway 10.11.0.1 \
        labs_management
    echo "    ✓ Created labs_management (10.11.0.0/16)"
else
    echo "    ✓ labs_management already exists"
fi

if ! docker network inspect labs_data &>/dev/null; then
    docker network create \
        --driver bridge \
        --subnet 10.13.0.0/16 \
        --gateway 10.13.0.1 \
        labs_data
    echo "    ✓ Created labs_data (10.13.0.0/16)"
else
    echo "    ✓ labs_data already exists"
fi
echo ""

# Step 5: Configure firewall
echo "[5/7] Configuring firewall..."
if command -v ufw &>/dev/null; then
    ufw allow 51820/udp comment "WireGuard" 2>/dev/null || true
    ufw allow 22/tcp comment "SSH" 2>/dev/null || true
    ufw allow 80/tcp comment "HTTP" 2>/dev/null || true
    ufw allow 443/tcp comment "HTTPS" 2>/dev/null || true
    echo "    ✓ UFW firewall rules added"
else
    # Use iptables directly
    iptables -A INPUT -p udp --dport 51820 -j ACCEPT
    iptables -A INPUT -p tcp --dport 22 -j ACCEPT
    iptables -A INPUT -p tcp --dport 80 -j ACCEPT
    iptables -A INPUT -p tcp --dport 443 -j ACCEPT
    echo "    ✓ iptables rules added (install ufw for persistent rules)"
fi
echo ""

# Step 6: Create helper scripts
echo "[6/7] Creating helper scripts..."
mkdir -p /opt/labs-control-panel/scripts
mkdir -p /opt/labs-control-panel/user-configs

# Create simple user registration script
cat > /opt/labs-control-panel/scripts/add-wireguard-peer.sh <<'SCRIPT_EOF'
#!/bin/bash
# add-wireguard-peer.sh - Add a WireGuard peer to the server
# Usage: ./add-wireguard-peer.sh <username> <wg-ip> <public-key>

if [ $# -ne 3 ]; then
    echo "Usage: $0 <username> <wg-ip> <public-key>"
    echo "Example: $0 alice 172.30.0.5 AbCd1234...="
    exit 1
fi

USERNAME=$1
WG_IP=$2
PUBLIC_KEY=$3

echo "Adding WireGuard peer..."
echo "  Username: $USERNAME"
echo "  IP: $WG_IP"
echo "  Public Key: ${PUBLIC_KEY:0:20}..."

# Add peer to running WireGuard
wg set wg0 peer "$PUBLIC_KEY" allowed-ips "$WG_IP/32"

# Save configuration
wg-quick save wg0

echo "✓ Peer added successfully"
echo ""
echo "Verify with: wg show wg0"
SCRIPT_EOF

chmod +x /opt/labs-control-panel/scripts/add-wireguard-peer.sh

# Create user config generator
cat > /opt/labs-control-panel/scripts/generate-user-config.sh <<'SCRIPT_EOF'
#!/bin/bash
# generate-user-config.sh - Generate WireGuard config for a user
# Usage: ./generate-user-config.sh <username> <wg-ip>

if [ $# -ne 2 ]; then
    echo "Usage: $0 <username> <wg-ip>"
    echo "Example: $0 alice 172.30.0.5"
    exit 1
fi

USERNAME=$1
WG_IP=$2

# Generate keys
PRIVATE_KEY=$(wg genkey)
PUBLIC_KEY=$(echo $PRIVATE_KEY | wg pubkey)

# Get server details
SERVER_PUBKEY=$(wg show wg0 public-key)
SERVER_IP=$(curl -s ifconfig.me || curl -s icanhazip.com || echo "YOUR_SERVER_IP")

# Create config directory
mkdir -p /opt/labs-control-panel/user-configs

# Generate client config
cat > /opt/labs-control-panel/user-configs/${USERNAME}.conf <<EOF
[Interface]
PrivateKey = $PRIVATE_KEY
Address = $WG_IP/32
DNS = 8.8.8.8, 1.1.1.1

[Peer]
PublicKey = $SERVER_PUBKEY
AllowedIPs = 172.30.0.0/16
Endpoint = $SERVER_IP:51820
PersistentKeepalive = 25
EOF

echo "✓ Configuration generated for $USERNAME"
echo "✓ WireGuard IP: $WG_IP"
echo "✓ Config saved to: /opt/labs-control-panel/user-configs/${USERNAME}.conf"
echo "✓ User Public Key: $PUBLIC_KEY"
echo ""
echo "Next step: Add peer to server"
echo "  sudo /opt/labs-control-panel/scripts/add-wireguard-peer.sh $USERNAME $WG_IP $PUBLIC_KEY"
SCRIPT_EOF

chmod +x /opt/labs-control-panel/scripts/generate-user-config.sh

echo "    ✓ Created helper scripts"
echo ""

# Step 7: System check
echo "[7/7] Verifying installation..."
ISSUES=0

# Check Docker
if docker ps &>/dev/null; then
    echo "    ✓ Docker is working"
else
    echo "    ✗ Docker is not working"
    ((ISSUES++))
fi

# Check WireGuard
if wg show wg0 &>/dev/null; then
    echo "    ✓ WireGuard is working"
else
    echo "    ✗ WireGuard is not working"
    ((ISSUES++))
fi

# Check MongoDB
if pgrep -x mongod > /dev/null; then
    echo "    ✓ MongoDB is running"
elif pgrep -x mongo > /dev/null; then
    echo "    ✓ MongoDB is running"
else
    echo "    ⚠ MongoDB may not be running"
    echo "      (This is OK if you're using a different database)"
fi

# Check IP forwarding
if [ "$(cat /proc/sys/net/ipv4/ip_forward)" == "1" ]; then
    echo "    ✓ IP forwarding is enabled"
else
    echo "    ✗ IP forwarding is disabled"
    ((ISSUES++))
fi

echo ""

if [ $ISSUES -eq 0 ]; then
    echo "=========================================="
    echo "  ✓ Setup Complete!"
    echo "=========================================="
    echo ""
    echo "WireGuard Server:"
    echo "  Interface: wg0"
    echo "  IP: 172.30.0.1/16"
    echo "  Port: 51820"
    echo "  Public Key: $(cat /opt/labs-control-panel/.wg-server-pubkey 2>/dev/null || wg show wg0 public-key)"
    echo ""
    echo "Docker Networks:"
    docker network ls | grep labs_ | sed 's/^/  /'
    echo ""
    echo "Quick Start:"
    echo "  1. Generate user config:"
    echo "     sudo /opt/labs-control-panel/scripts/generate-user-config.sh alice 172.30.0.5"
    echo ""
    echo "  2. Add peer to server (use the public key from step 1):"
    echo "     sudo /opt/labs-control-panel/scripts/add-wireguard-peer.sh alice 172.30.0.5 <PUBLIC_KEY>"
    echo ""
    echo "  3. Deploy container with labsctl (update Lab.py first)"
    echo ""
    echo "=========================================="
else
    echo "=========================================="
    echo "  ⚠ Setup completed with $ISSUES issue(s)"
    echo "=========================================="
    echo ""
    echo "Please resolve the issues above before proceeding."
fi