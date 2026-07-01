#!/usr/bin/env python3
"""
wgconfig.py - WireGuard peer registration for container
Usage: python3 wgconfig.py <tunnel_ip>
Output: private_key|public_key
"""

import subprocess
import os
import sys

def generate_keys():
    """Generate WireGuard keypair"""
    priv_key = subprocess.check_output("wg genkey", shell=True).decode().strip()
    pub_key = subprocess.check_output(f"echo '{priv_key}' | wg pubkey", shell=True).decode().strip()
    return priv_key, pub_key

def remove_stale_peer(ip):
    """Remove any existing peer using this IP"""
    try:
        # Find existing peer with this IP
        result = os.popen(f"wg show wg0 allowed-ips | grep '{ip}/32'").read().strip()
        if result:
            old_peer_key = result.split()[0]
            os.system(f"wg set wg0 peer {old_peer_key} remove")
            print(f"[*] Removed stale peer for {ip}", file=sys.stderr)
    except:
        pass

def register_peer(pub_key, ip):
    """Register new peer on host WireGuard"""
    # Clean up old peer
    remove_stale_peer(ip)
    
    # Add new peer
    result = os.system(f"wg set wg0 peer {pub_key} allowed-ips {ip}/32")
    if result == 0:
        print(f"[✓] Registered peer: {ip}", file=sys.stderr)
    else:
        print(f"[!] Failed to register peer for {ip}", file=sys.stderr)

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 wgconfig.py <tunnel_ip>", file=sys.stderr)
        sys.exit(1)
    
    tunnel_ip = sys.argv[1]
    
    # Generate keys
    priv_key, pub_key = generate_keys()
    
    # Register peer on host
    register_peer(pub_key, tunnel_ip)
    
    # Output keys for Lab.py to capture
    print(f"{priv_key}|{pub_key}")