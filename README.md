# Labs Infrastructure Migration Guide

Professional deployment and orchestration suite for the Labs environment. Use this guide to safely migrate your environment to a fresh server.

---

## ⚠️ Pre-Installation Security

Before running the migration, you **must** update the default security credentials within the script to protect your environment.

1. Open `server_migrate.sh` (for bare-metal) or `docker_migrate.sh` (for Docker) in a text editor.
2. Locate and modify the following default passwords:
* **MongoDB**: Change `Tombootroot` (around line 211).
* **RabbitMQ**: Change `RootTom@46` (around line 649).


3. Save the file.

---

## 🚀 Migration Process

We provide two distinct migration paths. Choose the one that fits your environment.

### Option 1: Bare-Metal Server Installation
For direct deployment on a VPS or dedicated Ubuntu server.

**Prerequisites:**
* A fresh Ubuntu 24.04 (Noble) server.
* Root or sudo privileges.
* Domains pointed to your server's IP (Main, VPN, RabbitMQ, Code).

**Installation Steps:**
1. **Download the Script:**
Transfer `server_migrate.sh` to your target server path.
2. **Grant Execution Permissions:**
```bash
chmod +x server_migrate.sh
```
3. **Execute the Orchestrator:**
```bash
sudo ./server_migrate.sh
```

---

### Option 2: Container-Based Installation (Cloudflare Tunnel)
For deploying locally or on an existing server via nested Docker and Cloudflare Zero Trust.

**Prerequisites:**
* Docker and Docker Compose installed on your local host or server (Mac, Windows, Linux).
* A Cloudflare account with a Zero Trust Tunnel created.

**Installation Steps:**
1. **Configure Cloudflare Tunnel:**
Open `docker-compose.yml` and replace the `TUNNEL_TOKEN` placeholder with your actual Cloudflare Tunnel token:
```yaml
    environment:
      - TUNNEL_TOKEN=your_token_here
```

2. **Start the Environment:**
Build and start the simulated VPS container alongside the Cloudflare Tunnel:
```bash
docker compose up -d --build
```

3. **Enter the VPS Container:**
Once the containers are running, open an interactive bash shell inside the VPS container:
```bash
docker exec -it tomlabs_vps bash
```

4. **Execute the Orchestrator:**
Navigate to the mounted directory (which reflects your current host directory) and run the Docker migration script:
```bash
cd /host_www
chmod +x docker_migrate.sh
./docker_migrate.sh
```

---

### Interactive Setup (Common for Both Methods)

Whether using Option 1 or Option 2, the script will prompt for:
* **Domains**: Configure your FQDNs for Labs, VPN API, RabbitMQ, and Code Server. For the Docker setup with Cloudflare, ensure these match your public hostnames configured in your tunnel.
* **SSL Email**: Used for Let's Encrypt certificate generation via Traefik.
* **Git Credentials**: Required for private repository cloning.


### ✅ Post-Migration Success

Upon successful completion, the script will output the following confirmation:

```text
[INFO] ==================================================
[INFO]  Migration Complete! 
[INFO] ==================================================
Verify URLs:
  https://awshosting.in
  https://vpn.awshosting.in
  https://mq.awshosting.in

```

---

## ⚙️ Configuration Setup (`env.json`)

After migration, you must verify and update the environment configuration file located at `/var/www/env.json`. Ensure the values match your specific deployment.

```json
{
  "amqp_host": "127.0.0.1",
  "amqp_port": 5672,
  "amqp_user": "admin",
  "amqp_pass": "RootTom@46",
  "google_oauth": {
    "client_id": "*****************.apps.googleusercontent.com",
    "client_secret": "********************",
    "redirect_uri": "https://awshosting.in/signin",
    "metadata_url": "https://accounts.google.com/.well-known/openid-configuration"
  },
  "smtp": {
    "host": "smtp.gmail.com",
    "port": 465,
    "user": "sathishp3223@gmail.com",
    "pass": "*************"
  },
  "exception_path": "/var/www/labs/htdocs/src/lib/exceptions/",
  "app_log": "/var/log/labs/log.txt",
  "app_cache": "/var/cache/labs",
  "database_file": "mongodb://admin:Tombootroot@127.0.0.1:27017/tom_labs_db?authSource=admin",
  "main_db": "tom_labs_db",
  "vpn_db": "tom_labs_vpn",
  "vpn_url": "https://vpn.awshosting.in/api",
  "api_secret": "your-super-secret-token-here",
  "wireguard": {
    "conf_path": "/etc/wireguard/",
    "interface": "wg0"
  },
  "s3": {
    "endpoint": "https://api-0a0fa067832614bb98336cde4230ad3b.tomweb.shop",
    "region": "us-east-1",
    "bucket": "labassets",
    "access_key": "sathish47",
    "secret_key": "gjgI7bKk3XjE",
    "use_path_style": true
  },
  "allowed_hosts": [
    "awshosting.in"
  ]
}

```

---

## 🏗️ Lab Image Building

Manage and build your lab environments using the `labsctl` utility located at `/opt/labs-control-panel/labsctl.py`.

| Lab Type | Build Command |
| --- | --- |
| **Essentials Lab** | `sudo labsctl build essentials:lab` |
| **n8n Automation** | `sudo labsctl build n8n:lab` |
| **MinIO S3 Storage** | `sudo labsctl build minio:lab` |

---

## 🔑 Security & Key Management

To ensure proper VPN handshakes, you must update the **Server Public Key** in the following critical locations:

1. **VPN Download Script**:
Update `$serverPubKey` in `/var/www/labs/htdocs/api/vpn/download.php`.
2. **Labs Control Panel**:
Ensure the public key is updated within the orchestration logic at `/opt/labs-control-panel/`.

---

## 🛠️ Service Overview

Once completed, the following stack will be active:

* **Traefik**: Edge router handling SSL (Let's Encrypt) and routing to services.
* **Apache2**: Backend web server (listening on 8081/8082) serving Labs and VPN API.
* **RabbitMQ**: Message broker with STOMP enabled for real-time log streaming.
* **MongoDB 8.0**: Primary data store with authentication enabled.
* **WireGuard**: Secure VPN tunnel for container networking.

---

*Version: 1.0.0 - Major Infrastructure & Security Alignment*
