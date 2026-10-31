#!/bin/bash
# entry.sh - Container startup

# 0. Storage layout: the per-account storage bind is mounted at /var/labsstorage
# (see config.json storage.mount_target). The user's home lives under
# /var/labsstorage/home/<user>; /home is a symlink onto it so passwd entries,
# useradd and all engine paths (/home/<user>/...) keep working transparently.
# Re-established on every boot; safe when the bind is absent (plain dir).
mkdir -p /var/labsstorage/home
if [ -d /home ] && [ ! -L /home ]; then
    rm -rf /home
fi
ln -sfn /var/labsstorage/home /home
echo "[*] /home -> /var/labsstorage/home (per-account storage)"

# 1. Start SSH
# SSH host identity lives at the per-account storage volume root
# (/var/labsstorage/.ssh_host, root:root 700 — outside the user's home) and
# is generated once per account, then restored to /etc/ssh on every boot, so
# image rebuilds and redeploys never rotate the served fingerprint. Any key
# file that goes missing is regenerated individually; the restore copy only
# runs after generation, so the container can never end up without keys.
HK_DIR=/var/labsstorage/.ssh_host
if mkdir -p "$HK_DIR" 2>/dev/null && [ -w "$HK_DIR" ]; then
    chmod 700 "$HK_DIR" 2>/dev/null || true
    mkdir -p /etc/ssh
    for t in rsa ecdsa ed25519; do
        K="$HK_DIR/ssh_host_${t}_key"
        if [ ! -s "$K" ] || [ ! -s "$K.pub" ]; then
            echo "[*] Generating missing SSH host key: $t"
            rm -f "$K" "$K.pub"
            if [ "$t" = "rsa" ]; then
                ssh-keygen -q -t rsa -b 4096 -N "" -f "$K"
            else
                ssh-keygen -q -t "$t" -N "" -f "$K"
            fi
            chmod 600 "$K"
            chmod 644 "$K.pub"
            echo "[$(date '+%F %T')] generated ssh_host_${t}_key" >> "$HK_DIR/.generated.log"
        fi
        cp -f "$K" "/etc/ssh/ssh_host_${t}_key"
        cp -f "$K.pub" "/etc/ssh/ssh_host_${t}_key.pub"
    done
    touch "$HK_DIR/.initialized_ssh_keys"
    chmod 600 /etc/ssh/ssh_host_*_key 2>/dev/null
    chmod 644 /etc/ssh/ssh_host_*_key.pub 2>/dev/null
    echo "[*] SSH host identity restored from $HK_DIR"
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