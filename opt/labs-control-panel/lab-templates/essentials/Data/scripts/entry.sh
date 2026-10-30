#!/bin/bash
# entry.sh - Container startup

# 1. Start SSH (keys already exist from image build)
# Persist per-instance SSH host keys under /var/labsstorage/.ssh_host: a
# container keeps its identity across stop/start instead of reusing the same
# template-baked keys as every other lab on this image. Falls back to the
# baked keys if the dir is not
# writable, so this can never leave the container without any host key.
HK_DIR=/var/labsstorage/.ssh_host
if mkdir -p "$HK_DIR" 2>/dev/null && [ -w "$HK_DIR" ]; then
    if [ ! -f "$HK_DIR/.initialized_ssh_keys" ]; then
        echo "[*] Generating unique SSH host keys for this instance..."
        rm -f "$HK_DIR"/ssh_host_*
        ssh-keygen -t rsa -b 4096 -f "$HK_DIR/ssh_host_rsa_key" -N "" -q 2>/dev/null
        ssh-keygen -t ecdsa -f "$HK_DIR/ssh_host_ecdsa_key" -N "" -q 2>/dev/null
        ssh-keygen -t ed25519 -f "$HK_DIR/ssh_host_ed25519_key" -N "" -q 2>/dev/null
        chmod 600 "$HK_DIR"/ssh_host_* 2>/dev/null
        echo "instance-lab" > "$HK_DIR/.initialized_ssh_keys"
    fi
    cp "$HK_DIR"/ssh_host_* /etc/ssh/ 2>/dev/null
    chmod 600 /etc/ssh/ssh_host* 2>/dev/null
    echo "[*] SSH host keys restored from $HK_DIR"
fi
service ssh start

# 2. Configure WireGuard if config exists (will be reconfigured by linkuser.sh)
if [ -f /etc/wireguard/wg0.conf ]; then
    echo "[*] Starting WireGuard..."
    ip link delete dev wg0 2>/dev/null || true
    
    for i in {1..5}; do
        if wg-quick up wg0 2>/dev/null; then
            echo "[+] WireGuard started successfully on attempt $i."
            break
        else
            echo "[-] WireGuard attempt $i failed, retrying in 2s..."
            ip link delete dev wg0 2>/dev/null || true
            sleep 2
        fi
    done
    
    TUNNEL_PREFIX=$(echo "${VPS_DOCKER_IP:-172.30.0.1}" | awk -F. '{print $1"."$2"."$3"."}')
    ip route add ${TUNNEL_PREFIX}0/16 dev wg0 metric 10 2>/dev/null || true
fi

# Keep container running
tail -f /dev/null