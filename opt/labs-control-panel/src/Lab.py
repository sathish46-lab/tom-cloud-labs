import os
import json
import pymongo
import sys
import time
import secrets
import string
from src.DockerHelper import DockerHelper

class Lab:
    def __init__(self, args, session_hash=None):
        self.args = args 
        self.session_hash = session_hash
        self.docker = DockerHelper()
        self.config = self._load_global_config()
        self.env = self._load_env_config()
        
        # 1. Prioritize Connection from env.json (Source of Truth)
        mongo_uri = self.env.get('database_file')
        db_name = self.env.get('main_db', 'tom_labs_db')
        
        if mongo_uri:
            try:
                self.mongo_client = pymongo.MongoClient(mongo_uri, serverSelectionTimeoutMS=2000)
                self.mongo_client.admin.command('ping')
                self.db = self.mongo_client[db_name]
                return # Connection Successful
            except Exception as e:
                print(f"[DEBUG] Env Connection Failed: {e}")

        # 2. Fallback Logic for local development/bootstrapping
        try:
            # Try Docker Internal Network
            self.mongo_client = pymongo.MongoClient("mongodb://admin:Tombootroot@docker_tomlabs_mongodb:27017/?authSource=admin", serverSelectionTimeoutMS=2000)
            self.mongo_client.admin.command('ping')
            self.db = self.mongo_client.tom_labs_db 
        except Exception:
            try:
                # Try Local Host (Mapped Port)
                self.mongo_client = pymongo.MongoClient("mongodb://admin:Tombootroot@localhost:27018/?authSource=admin", serverSelectionTimeoutMS=2000)
                self.mongo_client.admin.command('ping')
                self.db = self.mongo_client.tom_labs_db
            except Exception:
                self.db = None

    def _load_env_config(self):
        """Load the global environment configuration (env.json)"""
        paths = ['/var/www/env.json', '/Users/sathish/Development/local_dev_lab/env.json', 'env.json']
        for path in paths:
            if os.path.exists(path):
                try:
                    with open(path, 'r') as f:
                        return json.load(f)
                except:
                    continue
        return {}

    def _load_global_config(self):
        config_path = '/opt/labs-control-panel/config.json'
        return json.load(open(config_path)) if os.path.exists(config_path) else {}

    def log(self, message, level="info"):
        prefixes = {"info": "[*]", "success": "[✓]", "error": "[!]", "warn": "[!]"}
        full_message = f"{prefixes.get(level, '[*]')} {message}"
        print(full_message)
        sys.stdout.flush()

    def build(self):
        """Build a Docker image from a specified template"""
        if len(sys.argv) < 3:
            self.log("Usage: labsctl build <template:tag>", "error")
            return

        image_tag = sys.argv[2]
        template_name = image_tag.split(':')[0]
        
        templates_dir = self.config.get('templates_dir', '/opt/labs-control-panel/lab-templates')
        template_path = os.path.join(templates_dir, template_name)
        
        if not os.path.exists(template_path):
            self.log(f"Template path not found: {template_path}", "error")
            return

        self.log(f"Building image {image_tag}...", "info")
        build_cmd = self.config.get('docker_build', "docker build -t {image_tag} {path}")
        
        # Enable BuildKit for performance
        build_cmd = build_cmd.replace("docker build", "DOCKER_BUILDKIT=1 docker build")

        if self.args.hasFlag('no-cache'):
            build_cmd = build_cmd.replace("docker build", "docker build --no-cache")

        mapping = {"image_tag": image_tag, "path": template_path}
        exit_status = os.system(build_cmd.format(**mapping))
        
        if exit_status == 0:
            self.log(f"Image {image_tag} built successfully.", "success")
            self.log("Cleaning up old image layers...", "info")
            os.system("docker image prune -f")
        else:
            self.log(f"Build failed with exit code {exit_status}", "error")

    def deploy(self):
        """Deploy a container with WireGuard mesh networking and Template-based Config"""
        self.log("Deployment initiated (WireGuard Mesh Mode)...", "info")
        
        # 0. Verify Docker Network exists (created by docker-compose, subnet 172.19.0.0/16)
        docker_network = self.config.get('docker_network_name', 'local_dev_lab_tomlabs_dev_net')
        network_check = os.system(f"docker network inspect {docker_network} > /dev/null 2>&1")
        if network_check != 0:
            self.log(f"FATAL: Docker network {docker_network} not found. Is docker-compose up?", "error")
            self.log("Run: docker-compose up -d  (from local_dev_lab directory)", "error")
            return

        instance_id = self.session_hash
        if not instance_id:
            self.log("FATAL: session_hash is empty. Cannot deploy without a valid instance hash.", "error")
            raise ValueError("session_hash is required for deployment")
        username = self.args.getFlagValue('user')
        
        self.log(f"Starting deployment for user: {username}", "info")
        self.log(f"Instance ID: {instance_id}", "info")
        
        if self.db is None:
            self.log("Database connection failed. Aborting.", "error")
            return

        # 1. Fetch Metadata
        self.log("Fetching lab metadata from database...", "info")
        lab_data = self.db.deployed_labs.find_one({"instance_hash": instance_id})
        
        if not lab_data:
            self.log(f"Metadata missing for {instance_id}", "error")
            return

        # 2. Load the specific Lab Template Configuration (Dynamic Allocation)
        template_name = lab_data['lab_type']  # e.g., 'essentials'
        template_config_path = os.path.join(self.config.get('templates_dir'), template_name, 'config.json')
        
        if not os.path.exists(template_config_path):
            self.log(f"Template config missing: {template_config_path}", "error")
            return

        with open(template_config_path, 'r') as f:
            lab_spec = json.load(f)

        link_script = lab_spec.get('scripts', {}).get('linkuser', '/var/labsdata/scripts/linkuser.sh')

        # 3. Extract Resources & IPs
        res = lab_spec.get('resources', {})
        mem = res.get('memory', '512m')
        cpu = res.get('cpus', '0.2')
        mount_target = lab_spec.get('storage', {}).get('mount_target', '/home/{user}').replace('{user}', username)

        base_ip = lab_data['internal_ip'] 
        ip_parts = base_ip.split('.')
        last_octet = ip_parts[3]
        
        docker_prefix = self.config.get('docker_ip_prefix', '172.19.0.')
        tunnel_prefix = self.config.get('tunnel_ip_prefix', '172.30.0.')

        docker_ip = f"{docker_prefix}{last_octet}"      # Physical Docker IP (on compose network)
        tunnel_ip = f"{tunnel_prefix}{last_octet}"     # Virtual VPN IP (WireGuard overlay)
        
        # Determine the VPS container's IP on the Docker network (for WireGuard endpoint)
        docker_network = self.config.get('docker_network_name', 'local_dev_lab_tomlabs_dev_net')
        vps_docker_ip = os.popen(
            f"docker inspect docker_tomlabs_vps_dev "
            f"--format '{{{{.NetworkSettings.Networks.{docker_network}.IPAddress}}}}' 2>/dev/null"
        ).read().strip()
        if not vps_docker_ip:
            vps_docker_ip = f"{docker_prefix}2"  # Fallback: first usable IP after gateway
            self.log(f"WARNING: Could not detect VPS container IP, using fallback: {vps_docker_ip}", "warn")
        else:
            self.log(f"VPS Container IP (WG Endpoint): {vps_docker_ip}", "info")
        
        self.log(f"Assigned Docker IP (eth0): {docker_ip}", "info")
        self.log(f"Assigned Tunnel IP (wg0): {tunnel_ip}", "info")
        
        storage_path = lab_data['storage_path']
        
        # 4. Cleanup existing container (FIXED - don't prune networks!)
        self.log("Checking for conflicting containers...", "info")
        if self.docker.container_exists(instance_id):
            docker_network = self.config.get('docker_network_name', 'bridge')
            try:
                self.log(f"Disconnecting {instance_id} from {docker_network}...", "info")
                os.system(f"docker network disconnect -f {docker_network} {instance_id} 2>/dev/null")
            except Exception as e:
                pass
            os.system(f"docker stop {instance_id} 2>/dev/null && docker rm -f {instance_id} 2>/dev/null")
            self.log("Container removed.", "success")
            self.log("Container removed.", "success")
        else:
            self.log("No existing container found.", "info")
        
        # 5. Verify storage
        if not os.path.exists(storage_path):
            self.log("Setting up storage...", "info")
            os.makedirs(storage_path, exist_ok=True)
        else:
            self.log("Volume verified.", "success")
        
        # 6. Clean up stale WireGuard peer
        self.log(f"Clearing stale VPN sessions for {tunnel_ip}...", "info")
        wgfree_script = os.path.join(self.config.get('templates_dir'), template_name, "Data/scripts/wgfree.sh")
        if os.path.exists(wgfree_script):
            os.system(f"bash {wgfree_script} {tunnel_ip}")
        
        # 7. Generate or Reuse WireGuard Keys
        credentials = lab_data.get('credentials', {})
        lab_pub_key = credentials.get('wg_pubkey')
        lab_priv_key = credentials.get('wg_privkey')

        if not lab_priv_key or not lab_pub_key:
            self.log("Generating fresh WireGuard keys...", "info")
            wg_script = os.path.join(self.config.get('templates_dir'), template_name, "Data/scripts/wgconfig.py")
            try:
                wg_output = os.popen(f"python3 {wg_script} {tunnel_ip}").read().strip()
                lab_priv_key, lab_pub_key = wg_output.split('|')
            except Exception as e:
                self.log("WireGuard key generation failed", "error")
                return
        else:
            self.log("Reusing existing keys for stable connection...", "info")
            # Remove any stale peer using this IP first
            os.system(f"wg show wg0 allowed-ips | grep '{tunnel_ip}/32' | awk '{{print $1}}' | xargs -I{{}} wg set wg0 peer {{}} remove 2>/dev/null || true")
            # Re-register the peer with its existing public key
            os.system(f"wg set wg0 peer {lab_pub_key} allowed-ips {tunnel_ip}/32")
            # Verify registration
            check = os.popen(f"wg show wg0 allowed-ips | grep '{lab_pub_key}'").read().strip()
            if check:
                self.log(f"Peer re-registered: {tunnel_ip}", "success")
            else:
                self.log(f"WARNING: Peer registration may have failed for {tunnel_ip}", "warn")
            
        # 8. Get server WireGuard public key
        server_pub_key = os.popen("wg show wg0 public-key 2>/dev/null").read().strip()
        if not server_pub_key:
            self.log("WARNING: Could not get server WireGuard public key", "warn")
            server_pub_key = "d5fV23F8CsH603vBs+z70c/q7iN9ZK6dWU5vsdh5SDE="

        # 9. Start Container with Template-based Mapping
        self.log(f"Provisioning {lab_spec.get('lab_name', template_name)}: {mem} RAM, {cpu} CPU", "info")
        mapping = {
            "lab_name": instance_id, 
            "memory": mem,
            "cpus": cpu,
            "storage": storage_path, 
            "mount_target": mount_target,
            "user": username, 
            "image": f"{template_name}:lab", 
            "ip": docker_ip, 
            "host_name": lab_spec.get('network', {}).get('hostname', 'essentials'),
            'network_name': self.config.get('docker_network_name', 'bridge')
        }
        
        self.docker.run_command(self.config.get('docker_run'), mapping)
        
        # 10. Wait for container initialization (Smart Polling)
        self.log("Waiting for container services to initialize...", "info")
        for i in range(10):
            if self.docker.is_container_running(instance_id):
                break
            time.sleep(0.5)
        
        # 11. Configure Host Routing
        self.log("Configuring network routing and firewall...", "info")
        docker_network = self.config.get('docker_network_name', 'local_dev_lab_tomlabs_dev_net')
        bridge_id = os.popen(
            f"docker network inspect {docker_network} -f '{{{{index .Options \"com.docker.network.bridge.name\"}}}}' 2>/dev/null"
        ).read().strip()
        
        if not bridge_id or bridge_id == "<no value>":
            bridge_id = os.popen(
                f"echo br-$(docker network inspect {docker_network} -f '{{{{.Id}}}}' 2>/dev/null | cut -c1-12)"
            ).read().strip()

        # In a local docker environment, the bridge might not exist inside the vps_dev namespace.
        # Fallback to eth0 which is the standard outbound interface to the docker network.
        if not bridge_id or bridge_id == "br-" or os.system(f"ip link show {bridge_id} > /dev/null 2>&1") != 0:
            self.log(f"Bridge '{bridge_id}' not found in current namespace, falling back to eth0", "warn")
            bridge_id = "eth0"
        
        os.system("sysctl -w net.ipv4.ip_forward=1")
        os.system(f"ip route del {tunnel_ip}/32 2>/dev/null || true")
        os.system(f"ip route add {tunnel_ip}/32 via {docker_ip} dev {bridge_id} 2>/dev/null || true")
        os.system("iptables -A FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null || true")
        os.system(f"iptables -A FORWARD -i wg0 -o {bridge_id} -j ACCEPT 2>/dev/null || true")
        os.system(f"iptables -A FORWARD -i {bridge_id} -o wg0 -j ACCEPT 2>/dev/null || true")
        os.system("iptables -t nat -A POSTROUTING -s 172.30.0.0/16 -o eth0 -j MASQUERADE 2>/dev/null || true")
        
        self.log("Routing and firewall configured.", "success")
        
        # 12. Configure Apache Optimization (Low Memory/PID Footprint)
        self.log("Optimizing Apache for single-user environment...", "info")
        apache_opt_cmd = (
            f"docker exec {instance_id} bash -c \""
            "cat <<EOF > /etc/apache2/mods-available/mpm_event.conf\\n"
            "<IfModule mpm_event_module>\\n"
            "        StartServers             1\\n"
            "        MinSpareThreads          2\\n"
            "        MaxSpareThreads          5\\n"
            "        ThreadsPerChild          10\\n"
            "        MaxRequestWorkers        20\\n"
            "        MaxConnectionsPerChild   0\\n"
            "</IfModule>\\n"
            "EOF\\n"
            "&& service apache2 reload\"" # Reload to apply
        )
        os.system(apache_opt_cmd)

        # 13. Configure User Environment
        self.log(f"Configuring user environment for {username}...", "info")
        
        # Security: Fetch User's VPN IPs to restrict SSH access
        user_vpn_ips = []
        try:
            vpn_db_name = self.env.get('vpn_db', 'tom_labs_vpn')
            vpn_db = self.mongo_client[vpn_db_name]
            # In tom_labs_vpn, the 'networks' collection contains 1 document PER IP allocation
            # The 'email' field holds the username
            allocations = vpn_db.networks.find({'email': username})
            for alloc in allocations:
                if 'ip_addr' in alloc:
                    user_vpn_ips.append(alloc['ip_addr'])
        except Exception as e:
            self.log(f"Warning: Could not fetch VPN IPs for restriction: {e}", "warn")

        # Format restriction string (e.g., from="172.30.0.12,172.30.0.13")
        restriction = ""
        if user_vpn_ips:
            restriction = f'from="{",".join(user_vpn_ips)}" '
            self.log(f"Restricting SSH access to: {', '.join(user_vpn_ips)}", "info")
        else:
            self.log("No VPN IPs found for user. SSH will be unrestricted (internal-only).", "warn")

        user_keys = list(self.db.ssh_keys.find({"username": username}))
        # Prepend restriction to each key
        auth_content = "\\n".join([f"{restriction}{k['public_key']}" for k in user_keys if 'public_key' in k])

        dynamic_pass = ''.join(secrets.choice(string.ascii_letters + string.digits) for i in range(12))
        
        # Custom Domain Logic for n8n
        selected_n8n_domain = None
        if template_name == 'n8n':
            custom_n8n = self.args.getFlagValue('n8n-domain')
            # Sanitize inputs
            if custom_n8n and (custom_n8n == 'default_n8n' or 'default' in custom_n8n):
                custom_n8n = None
            selected_n8n_domain = custom_n8n if custom_n8n else f"n8n-{instance_id}.tomweb.shop"

        # Pass email to linkuser.sh (8th argument)
        user_profile = self.db.users.find_one({"username": username})
        user_email = user_profile.get('email', username) if user_profile else username
        
        # Pass n8n Domain (9th argument) for Webhook URL
        n8n_domain_arg = selected_n8n_domain if selected_n8n_domain else ""

        link_cmd = f'docker exec {instance_id} {link_script} "{username}" "{auth_content}" "{docker_ip}" "{dynamic_pass}" "{lab_priv_key}" "{tunnel_ip}" "{server_pub_key}" "{user_email}" "{n8n_domain_arg}" "{vps_docker_ip}"'
        
        if os.system(link_cmd) != 0:
            self.log("linkuser.sh failed", "error")
            return
        
        # 13. Update Metadata
        self.log("[*] Finalizing routing metadata...", "info")
        self.log("[*] Finalizing routing metadata...", "info")
        
        lab_data = self.db.deployed_labs.find_one({"instance_hash": instance_id})
        db_domain = lab_data.get('code_domain')
        self.log(f"DEBUG: Found domain in DB: {db_domain}", "info")
        
        selected_code_domain = self.args.getFlagValue('vsc_domain') or db_domain or f"{instance_id}.tomweb.shop"
        code_server_url = f"https://{selected_code_domain}"

        # Calculate MinIO Domains early for DB storage
        # User requested: Console -> s3-..., API -> api-...
        # Check for overrides from CLI flags
        custom_console = self.args.getFlagValue('minio-console-domain')
        custom_api = self.args.getFlagValue('minio-api-domain')

        # Sanitize inputs (prevent "default" strings from UI bugs)
        if custom_console and (custom_console == 'default_console' or 'default' in custom_console):
            custom_console = None
        if custom_api and (custom_api == 'default_api' or 'default' in custom_api):
            custom_api = None

        s3_ui_domain = custom_console if custom_console else f"s3-{instance_id}.tomweb.shop"
        s3_api_domain = custom_api if custom_api else f"api-{instance_id}.tomweb.shop"
        
        credentials = {
            "ssh": f"ssh {username}@{tunnel_ip}",
            "password": dynamic_pass,
            "docker_ip": docker_ip,
            "tunnel_ip": tunnel_ip,
            "port": 22,
            "sshKey": len(user_keys) > 0,
            "code_server_url": code_server_url,
            "wg_pubkey": lab_pub_key,
            "wg_privkey": lab_priv_key
        }

        # Add MinIO Specifics if applicable
        if template_name == 'minio':
            credentials.update({
                "minio_access_key": username,
                "minio_secret_key": dynamic_pass,
                "minio_url_console": f"https://{s3_ui_domain}",
                "minio_url_api": f"https://{s3_api_domain}",
                "minio_port_console": 9001,
                "minio_port_api": 9000
            })
        
        # Add n8n Specifics if applicable
        if template_name == 'n8n':
            credentials.update({
                "n8n_username": user_email,
                "n8n_password": dynamic_pass,
                "n8n_url": f"https://{selected_n8n_domain}",
                "n8n_port": 5678
            })

        self.db.deployed_labs.update_one(
            {"instance_hash": instance_id}, 
            {"$set": {
                "status": "running", 
                "credentials": credentials, 
                "updated_at": time.time()
            }}
        )

       # 14. Configure Traefik 
        self.log("[*] Finalizing Traefik routing...", "info")

        # FORCE RE-FETCH to ensure PHP updates for domains/expose_web are present
        lab_data = self.db.deployed_labs.find_one({"instance_hash": instance_id})

        user_domains = lab_data.get('domains', [])
        is_exposed = lab_data.get('expose_web', False)

        # DEBUG LOG: Verify these values in your terminal logs!
        self.log(f"DEBUG: expose_web={is_exposed}, domains={user_domains}", "info")
        
        traefik_config = "http:\n  routers:\n"

        # A. VS Code Router (Always needed)
        traefik_config += f"""    router-{instance_id}-code:
      rule: "Host(`{selected_code_domain}`)"
      service: service-{instance_id}-code
      entryPoints: [websecure]
      middlewares: [code-headers@file]
      tls: {{certResolver: myresolver}}
"""
        # B. MinIO UI Router (Only if template is minio)
        if template_name == 'minio':
            # Domains already defined above
            traefik_config += f"""    router-{instance_id}-s3-ui:
      rule: "Host(`{s3_ui_domain}`)"
      service: service-{instance_id}-s3-ui
      entryPoints: [websecure]
      tls: {{certResolver: myresolver}}
"""
            traefik_config += f"""    router-{instance_id}-s3-api:
      rule: "Host(`{s3_api_domain}`)"
      service: service-{instance_id}-s3-api
      entryPoints: [websecure]
      tls: {{certResolver: myresolver}}
"""

        # C. SERVICES SECTION
        # CUSTOM DOMAINS (Web Exposure)
        if is_exposed and user_domains:
            for idx, domain in enumerate(user_domains):
                # Use lstrip() or adjust indentation to be exactly 4 spaces
                traefik_config += f"""    router-{instance_id}-custom-{idx}:
      rule: "Host(`{domain}`)"
      service: service-{instance_id}-web
      entryPoints: [websecure]
      tls: {{certResolver: myresolver}}\n"""
      
        if template_name == 'n8n':
            traefik_config += f"""    router-{instance_id}-n8n:
      rule: "Host(`{selected_n8n_domain}`)"
      service: service-{instance_id}-n8n
      entryPoints: [websecure]
      tls: {{certResolver: myresolver}}
"""

        traefik_config += "\n  services:\n"
        traefik_config += f"""    service-{instance_id}-code:
      loadBalancer:
        servers: [{{url: "http://{docker_ip}:8080"}}]
    service-{instance_id}-web:
      loadBalancer:
        servers: [{{url: "http://{docker_ip}:80"}}]
"""

        if template_name == 'minio':
            traefik_config += f"""    service-{instance_id}-s3-ui:
      loadBalancer:
        servers: [{{url: "http://{docker_ip}:9001"}}]
    service-{instance_id}-s3-api:
      loadBalancer:
        servers: [{{url: "http://{docker_ip}:9000"}}]
"""

        if template_name == 'n8n':

            traefik_config += f"""    service-{instance_id}-n8n:
      loadBalancer:
        servers: [{{url: "http://{docker_ip}:5678"}}]
"""

        # D. WRITE THE FILE
        traefik_file_path = f"/etc/traefik/dynamic_conf/{instance_id}.yml"
        try:
            with open(traefik_file_path, "w") as f:
                f.write(traefik_config)
            self.log(f"[✓] Traefik configuration applied", "success")
        except Exception as e:
            self.log(f"[!] Traefik config failed: {str(e)}", "error")
        
        self.log("Deployment Complete. Ready for connections.", "success")
        self.log(f"Access URL: {code_server_url}", "info")
        self.log(f"VPN Access: ssh {username}@{tunnel_ip}", "info")

    def stop(self):
        """Stop container and clean up runtime networking without releasing the IP pool."""
        lab_name = self.session_hash
        self.log(f"Initiating graceful shutdown for: {lab_name}", "info")
        
        # 1. Fetch metadata to find IPs for cleanup
        lab_data = self.db.deployed_labs.find_one({"instance_hash": lab_name})
        
        if lab_data:
            credentials = lab_data.get('credentials', {})
            tunnel_ip = credentials.get('tunnel_ip')
            
            # 2. Remove Host-Side Routing for this specific lab
            if tunnel_ip:
                self.log(f"[*] Cleaning host route: {tunnel_ip}", "info")
                os.system(f"ip route del {tunnel_ip}/32 2>/dev/null || true")

        # 3. Terminate Docker Container
        if self.docker.container_exists(lab_name):
            self.log(f"[*] Stopping Docker container...", "warn")
            # We are already root, so no need for sudo
            os.system(f"docker stop {lab_name} && docker rm -f {lab_name}")
            
            # 4. Update Status
            self.db.deployed_labs.update_one(
                {"instance_hash": lab_name}, 
                {"$set": {"status": "stopped"}}
            )
            self.log("[✓] Container and process-space cleared.", "success")

        # 5. Purge Traefik Configuration
        traefik_file = f"/etc/traefik/dynamic_conf/{lab_name}.yml"
        if os.path.exists(traefik_file):
            try:
                os.remove(traefik_file)
                self.log(f"[✓] Traefik route removed for {lab_name}", "success")
            except Exception as e:
                self.log(f"[!] Traefik cleanup error: {str(e)}", "warn")

        self.log(f"[✓] Lab {lab_name} is now offline. IP remains reserved.", "success")

    def start(self):
        """Start a stopped container"""
        lab_name = self.session_hash
        self.log(f"Starting container: {lab_name}", "info")
        
        if self.docker.container_exists(lab_name):
            os.system(f"docker start {lab_name}")
            self.log("Container started.", "success")
            
            if self.db is not None:
                self.db.deployed_labs.update_one(
                    {"instance_hash": lab_name}, 
                    {"$set": {"status": "running"}}
                )
        else:
            self.log(f"Container {lab_name} not found", "error")

    def remove(self):
        """Remove a container completely"""
        self.stop()

    def shell(self):
        """Drop into a container shell"""
        lab_name = self.session_hash
        shell_cmd = self.config.get('docker_drop_shell', f"docker exec -it {lab_name} /bin/bash")
        os.system(shell_cmd.format(lab_name=lab_name, shell="/bin/bash"))

    def info(self):
        """Show lab metadata"""
        lab_name = self.session_hash
        
        if self.db is None:
            self.log("Database connection failed", "error")
            return
        
        lab_data = self.db.deployed_labs.find_one({"instance_hash": lab_name})
        
        if lab_data:
            import pprint
            pprint.pprint(lab_data)
        else:
            self.log(f"No metadata found for {lab_name}", "error")

    def sync_user(self, username):
        """Synchronize SSH keys and permissions for a user's running labs"""
        self.log(f"Syncing permissions for user: {username}...", "info")
        
        if self.db is None:
            self.log("Database connection failed", "error")
            return

        # 1. Fetch User Profile to get Email
        user_profile = self.db.users.find_one({'username': username})
        search_emails = [username]
        if user_profile and 'email' in user_profile:
            search_emails.append(user_profile['email'])
            self.log(f"Linked email found: {user_profile['email']}", "info")

        # 2. Fetch User's VPN IPs (by username OR email)
        user_vpn_ips = []
        try:
            vpn_db_name = self.env.get('vpn_db', 'tom_labs_vpn')
            vpn_db = self.mongo_client[vpn_db_name]
            allocations = vpn_db.networks.find({'email': {'$in': search_emails}})
            for alloc in allocations:
                if 'ip_addr' in alloc:
                    user_vpn_ips.append(alloc['ip_addr'])
        except Exception as e:
            self.log(f"Warning: Could not fetch VPN IPs: {e}", "warn")

        # 3. Construct Restriction String
        restriction = "" 
        if user_vpn_ips:
            # unique sorted list
            user_vpn_ips = sorted(list(set(user_vpn_ips)))
            restriction = f'from="{",".join(user_vpn_ips)}" '
            self.log(f"Active VPN IPs: {', '.join(user_vpn_ips)}", "info")
        else:
             self.log("No VPN IPs found. SSH will be unrestricted (internal-only).", "warn")

        # 4. Fetch User's SSH Keys
        user_keys = list(self.db.ssh_keys.find({"username": username}))
        if not user_keys:
            self.log(f"No SSH keys found for {username}", "warn")
            
        # 4. Generate Authorized Keys Content
        auth_content = "\n".join([f"{restriction}{k['public_key']}" for k in user_keys if 'public_key' in k])
        
        # 5. Update Storage for All Deployments
        # We update the central storage location for the user, which is mounted into containers
        
        # We need to know the storage path. We can search for any lab owned by this user to find the storage path
        # or use the template default.
        
        # Checking one of the user's labs to get the storage path
        user_lab = self.db.deployed_labs.find_one({"username": username})
        if user_lab:
            storage_path = user_lab.get('storage_path')
        else:
            # Fallback to default pattern
            storage_path = f"/var/tomlabs/storage/{username}"
            
        ssh_dir = os.path.join(storage_path, ".ssh")
        auth_file = os.path.join(ssh_dir, "authorized_keys")
        
        if os.path.exists(storage_path):
            if not os.path.exists(ssh_dir):
                os.makedirs(ssh_dir, mode=0o700, exist_ok=True)
                # potentially chown if we knew the uid/gid, but usually host root owns it or it's permissive
                
            try:
                with open(auth_file, "w") as f:
                    f.write(auth_content)
                os.chmod(auth_file, 0o600)
                self.log(f"[✓] Updated {auth_file}", "success")
            except Exception as e:
                self.log(f"[!] Failed to write authorized_keys: {e}", "error")
        else:
             self.log(f"Storage path {storage_path} does not exist. No labs deployed?", "warn")

    def ensure_codeserver(self):
        """Ensure code-server is running inside the container"""
        lab_name = self.session_hash
        username = self.args.getFlagValue('user')
        
        self.log(f"Ensuring code-server is running for {lab_name}...", "info")
        
        if not self.docker.container_exists(lab_name):
            self.log(f"Container {lab_name} not found", "error")
            return

        # Check if code-server is already running
        check_cmd = f"docker exec {lab_name} pgrep -u {username} -f code-server"
        if os.system(check_cmd) == 0:
            self.log("Code-server is already running.", "success")
            return

        self.log("Starting code-server...", "info")
        
        # Construct startup command matching linkuser.sh
        # We use strict paths to avoid environment issues
        user_home = f"/home/{username}"
        config_file = f"{user_home}/.config/code-server/config.yaml"
        log_file = f"{user_home}/.code-server.log"
        
        # Command to run as user inside container
        start_cmd = f"nohup code-server --config {config_file} > {log_file} 2>&1 &"
        
        # Wrap in docker exec as user
        docker_cmd = f"docker exec -u {username} {lab_name} bash -c '{start_cmd}'"
        
        if os.system(docker_cmd) == 0:
            time.sleep(2) # Wait for startup
            if os.system(check_cmd) == 0:
                self.log("Code-server started successfully.", "success")
                
                # ALSO ensure the monitor is running
                self.log("Ensuring idle monitor is active...", "info")
                monitor_check = f"docker exec {lab_name} pgrep -f monitor_codeserver"
                if os.system(monitor_check) != 0:
                    # Start monitor as root (it handles switching to user for checks)
                    monitor_start = f"nohup /var/labsdata/scripts/monitor_codeserver.sh {username} > /var/log/code-monitor.log 2>&1 &"
                    docker_monitor = f"docker exec {lab_name} bash -c '{monitor_start}'"
                    os.system(docker_monitor)
                    self.log("Idle monitor started.", "success")
                else:
                    self.log("Idle monitor already running.", "info")

            else:
                self.log("Failed to verify code-server startup.", "error")
        else:
            self.log("Failed to execute startup command.", "error")
    def list_images(self):
        """List all lab templates and their build status"""
        templates_dir = self.config.get('templates_dir', '/opt/labs-control-panel/lab-templates')
        if not os.path.exists(templates_dir):
            self.log("Templates directory not found.", "error")
            return

        templates = [d for d in os.listdir(templates_dir) if os.path.isdir(os.path.join(templates_dir, d)) and not d.startswith('__')]
        
        self.log("Checking Lab Image Status:", "info")
        print("-" * 50)
        print(f"{'Template Name':<20} | {'Image Tag':<15} | {'Status'}")
        print("-" * 50)

        for t in templates:
            tag = f"{t}:lab"
            status = "✅ Built" if self.docker.image_exists(tag) else "❌ Missing"
            print(f"{t:<20} | {tag:<15} | {status}")
        print("-" * 50 + "\n")
    
    def get_workers(self):
        """Check how many Python worker processes are currently alive via systemd."""
        self.log("Querying systemd for active labs-worker instances...", "info")
        try:
            # Query systemctl specifically for running instances of your worker
            cmd = "systemctl list-units --type=service --state=running | grep 'labs-worker' | awk '{print $1}'"
            output = os.popen(cmd).read().strip()
            
            if not output:
                self.log("No active workers found. (Check if 'systemctl start labs-worker@1' was run)", "warn")
                return

            workers = output.split('\n')
            count = len(workers)
            
            self.log(f"Total Active Workers: {count}", "success")
            
            # Print a clean, CLI-friendly list matching your list_images format
            print("-" * 40)
            print(f"{'Worker Instance Name':<25} | {'Status'}")
            print("-" * 40)
            for w in workers:
                print(f"{w:<25} | 🟢 Alive")
            print("-" * 40 + "\n")
            
        except Exception as e:
            self.log(f"Failed to check workers: {e}", "error")
