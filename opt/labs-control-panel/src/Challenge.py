import os
import json
import time
import secrets
import string
import sys
from src.DockerHelper import DockerHelper


class Challenge:
    """
    Challenge Manager for CTF-style challenge deployments.
    Handles build, deploy, remove, stop, and start for challenge containers.
    Challenges are lightweight containers accessible only via WireGuard VPN overlay.
    """

    def __init__(self, args, session_hash=None):
        self.args = args
        self.session_hash = session_hash
        self.docker = DockerHelper()
        self.config = self._load_global_config()
        self.env = self._load_env_config()
        self.db = None

        # Connect to MongoDB (same pattern as Lab.py)
        self._connect_db()

    def _load_env_config(self):
        """Load the global environment configuration (env.json)"""
        paths = ['/var/www/env.json', '/Users/sathish/Development/local_dev_lab/env.json', 'env.json']
        for path in paths:
            if os.path.exists(path):
                try:
                    with open(path, 'r') as f:
                        return json.load(f)
                except Exception:
                    continue
        return {}

    def _load_global_config(self):
        config_path = '/opt/labs-control-panel/config.json'
        return json.load(open(config_path)) if os.path.exists(config_path) else {}

    def _connect_db(self):
        """Establish MongoDB connection using env.json or fallback."""
        import pymongo

        mongo_uri = self.env.get('database_file')
        db_name = self.env.get('main_db', 'tom_labs_db')

        if mongo_uri:
            try:
                self.mongo_client = pymongo.MongoClient(mongo_uri, serverSelectionTimeoutMS=2000)
                self.mongo_client.admin.command('ping')
                self.db = self.mongo_client[db_name]
                return
            except Exception as e:
                self.log(f"Env DB connection failed: {e}", "warn")

        # Fallback: Docker internal network
        try:
            self.mongo_client = pymongo.MongoClient(
                "mongodb://admin:Tombootroot@docker_tomlabs_mongodb:27017/?authSource=admin",
                serverSelectionTimeoutMS=2000
            )
            self.mongo_client.admin.command('ping')
            self.db = self.mongo_client.tom_labs_db
        except Exception:
            try:
                self.mongo_client = pymongo.MongoClient(
                    "mongodb://admin:Tombootroot@localhost:27018/?authSource=admin",
                    serverSelectionTimeoutMS=2000
                )
                self.mongo_client.admin.command('ping')
                self.db = self.mongo_client.tom_labs_db
            except Exception:
                self.db = None

    def log(self, message, level="info"):
        prefixes = {"info": "[*]", "success": "[✓]", "error": "[!]", "warn": "[!]"}
        full_message = f"{prefixes.get(level, '[*]')} {message}"
        print(full_message)
        sys.stdout.flush()

    # ─────────────────────────────────────────────
    # BUILD
    # ─────────────────────────────────────────────
    def build(self):
        """Build a challenge Docker image from a lab-template."""
        if len(sys.argv) < 4:
            self.log("Usage: labsctl challenge build <template:tag>", "error")
            self.log("  Example: labsctl challenge build sql_injection:lab", "error")
            return

        image_tag = sys.argv[3]
        template_name = image_tag.split(':')[0]

        templates_dir = self.config.get('templates_dir', '/opt/labs-control-panel/lab-templates')
        template_path = os.path.join(templates_dir, template_name)

        if not os.path.exists(template_path):
            self.log(f"Template path not found: {template_path}", "error")
            return

        self.log(f"Building challenge image {image_tag}...", "info")
        build_cmd = self.config.get('docker_build', "docker build -t {image_tag} {path}")

        # Enable BuildKit for performance
        build_cmd = build_cmd.replace("docker build", "DOCKER_BUILDKIT=1 docker build")

        if self.args.hasFlag('no-cache'):
            build_cmd = build_cmd.replace("docker build", "docker build --no-cache")

        mapping = {"image_tag": image_tag, "path": template_path}
        exit_status = os.system(build_cmd.format(**mapping))

        if exit_status == 0:
            self.log(f"Challenge image {image_tag} built successfully.", "success")
            os.system("docker image prune -f")
        else:
            self.log(f"Build failed with exit code {exit_status}", "error")

    # ─────────────────────────────────────────────
    # DEPLOY
    # ─────────────────────────────────────────────
    def deploy(self):
        """Deploy a challenge container with WireGuard VPN networking."""
        self.log("Challenge deployment initiated...", "info")

        # 0. Verify Docker Network exists
        docker_network = self.config.get('docker_network_name', 'local_dev_lab_tomlabs_dev_net')
        network_check = os.system(f"docker network inspect {docker_network} > /dev/null 2>&1")
        if network_check != 0:
            self.log(f"FATAL: Docker network {docker_network} not found. Is docker-compose up?", "error")
            return

        instance_id = self.session_hash
        if not instance_id:
            self.log("FATAL: --hash is required. Cannot deploy without a valid instance hash.", "error")
            return

        username = self.args.getFlagValue('user')
        if not username:
            self.log("FATAL: --user is required.", "error")
            return

        challenge_id = self.args.getFlagValue('challenge')
        if not challenge_id:
            self.log("FATAL: --challenge is required. Example: --challenge=sql_injection", "error")
            return

        self.log(f"Deploying challenge '{challenge_id}' for user: {username}", "info")
        self.log(f"Instance ID: {instance_id}", "info")

        if self.db is None:
            self.log("Database connection failed. Aborting.", "error")
            return

        # 1. Load Challenge Template Config
        templates_dir = self.config.get('templates_dir', '/opt/labs-control-panel/lab-templates')
        template_config_path = os.path.join(templates_dir, challenge_id, 'config.json')

        if not os.path.exists(template_config_path):
            self.log(f"Challenge template config missing: {template_config_path}", "error")
            return

        with open(template_config_path, 'r') as f:
            challenge_spec = json.load(f)

        # 2. Fetch or create challenge assignment in DB
        challenge_data = self.db.challenge_instances.find_one({"instance_hash": instance_id})

        if not challenge_data:
            self.log("No existing assignment found. Creating new challenge assignment...", "info")

            # Allocate an IP from the VPN network pool
            vpn_db_name = self.env.get('vpn_db', 'tom_labs_vpn')
            vpn_db = self.mongo_client[vpn_db_name]

            # Find next available IP in the challenge IP range (172.30.1.x)
            challenge_ip = self._allocate_challenge_ip(vpn_db, username, challenge_id)
            if not challenge_ip:
                self.log("FATAL: Could not allocate IP for challenge.", "error")
                return

            # Generate a unique flag for this user's challenge instance
            flag = f"CTF{{{secrets.token_hex(16)}}}"

            challenge_data = {
                "instance_hash": instance_id,
                "username": username,
                "challenge_id": challenge_id,
                "internal_ip": challenge_ip,
                "status": "deploying",
                "flag": flag,
                "credentials": {
                    "docker_ip": f"{self.config.get('docker_ip_prefix', '172.19.0.')}{challenge_ip.split('.')[3]}",
                    "tunnel_ip": challenge_ip,
                    "access_url": f"http://{challenge_ip}"
                },
                "created_at": time.time(),
                "updated_at": time.time()
            }
            self.db.challenge_instances.insert_one(challenge_data)
            self.log(f"Challenge assigned. IP: {challenge_ip}", "success")
        else:
            self.log("Reusing existing challenge assignment.", "info")
            challenge_ip = challenge_data['internal_ip']

        # 3. Calculate Docker and Tunnel IPs
        ip_parts = challenge_ip.split('.')
        last_octet = ip_parts[3]

        docker_prefix = self.config.get('docker_ip_prefix', '172.19.0.')
        tunnel_prefix = self.config.get('tunnel_ip_prefix', '172.30.0.')

        docker_ip = f"{docker_prefix}{last_octet}"
        tunnel_ip = challenge_ip  # Already a tunnel IP (172.30.x.x)

        self.log(f"Docker IP (eth0): {docker_ip}", "info")
        self.log(f"Tunnel IP (wg0):  {tunnel_ip}", "info")

        # 4. Determine VPS container IP for WireGuard endpoint
        vps_docker_ip = os.popen(
            f"docker inspect docker_tomlabs_vps_dev "
            f"--format '{{{{.NetworkSettings.Networks.{docker_network}.IPAddress}}}}' 2>/dev/null"
        ).read().strip()
        if not vps_docker_ip:
            vps_docker_ip = f"{docker_prefix}2"
            self.log(f"WARNING: Using fallback VPS IP: {vps_docker_ip}", "warn")
        else:
            self.log(f"VPS Container IP: {vps_docker_ip}", "info")

        # 5. Cleanup existing container
        self.log("Checking for conflicting containers...", "info")
        container_name = f"ctf-{instance_id}"
        if self.docker.container_exists(container_name):
            try:
                os.system(f"docker network disconnect -f {docker_network} {container_name} 2>/dev/null")
            except Exception:
                pass
            os.system(f"docker stop {container_name} 2>/dev/null && docker rm -f {container_name} 2>/dev/null")
            self.log("Old challenge container removed.", "success")

        # 6. Generate the unique flag file content
        flag = challenge_data.get('flag', f"CTF{{{secrets.token_hex(16)}}}")

        # 7. Run Challenge Container (lightweight, no WG inside the container)
        res = challenge_spec.get('resources', {})
        mem = res.get('memory', '128m')
        cpu = res.get('cpus', '0.1')
        hostname = challenge_spec.get('network', {}).get('hostname', 'ctf-challenge')

        run_cmd = (
            f"docker run --detach --name {container_name} "
            f"--memory='{mem}' --cpus='{cpu}' "
            f"--network {docker_network} --ip {docker_ip} "
            f"--hostname {hostname} "
            f"{challenge_id}:lab"
        )

        self.log(f"Provisioning {challenge_spec.get('lab_name', challenge_id)}: {mem} RAM, {cpu} CPU", "info")
        self.log(f"Executing: {run_cmd}", "info")
        exit_code = os.system(run_cmd)

        if exit_code != 0:
            self.log("Container failed to start.", "error")
            return

        # 8. Wait for container initialization
        self.log("Waiting for container to start...", "info")
        for _ in range(10):
            if self.docker.is_container_running(container_name):
                break
            time.sleep(0.5)

        # 9. Inject the unique flag into the container
        self.log("Injecting unique flag into challenge...", "info")
        os.system(f"docker exec {container_name} bash -c 'echo \"{flag}\" > /var/www/html/flag.txt'")
        os.system(f"docker exec {container_name} chown www-data:www-data /var/www/html/flag.txt")

        # 10. Configure Host-Side Routing (WireGuard → Docker)
        self.log("Configuring WireGuard routing...", "info")
        bridge_id = os.popen(
            f"docker network inspect {docker_network} -f '{{{{index .Options \"com.docker.network.bridge.name\"}}}}' 2>/dev/null"
        ).read().strip()

        if not bridge_id or bridge_id == "<no value>":
            bridge_id = os.popen(
                f"echo br-$(docker network inspect {docker_network} -f '{{{{.Id}}}}' 2>/dev/null | cut -c1-12)"
            ).read().strip()

        # Fallback to eth0 inside the VPS container
        if not bridge_id or bridge_id == "br-" or os.system(f"ip link show {bridge_id} > /dev/null 2>&1") != 0:
            self.log(f"Bridge '{bridge_id}' not found, falling back to eth0", "warn")
            bridge_id = "eth0"

        os.system("sysctl -w net.ipv4.ip_forward=1")
        # Route cleaning (just in case)
        os.system(f"ip route del {tunnel_ip}/32 2>/dev/null || true")
        
        # Add DNAT so traffic to 172.30.1.x gets translated to the real Docker IP (172.19.0.x)
        os.system(f"iptables -t nat -A PREROUTING -d {tunnel_ip} -j DNAT --to-destination {docker_ip} 2>/dev/null || true")
        os.system(f"iptables -t nat -A OUTPUT -d {tunnel_ip} -j DNAT --to-destination {docker_ip} 2>/dev/null || true")
        
        os.system("iptables -A FORWARD -i wg0 -o wg0 -j ACCEPT 2>/dev/null || true")
        os.system(f"iptables -A FORWARD -i wg0 -o {bridge_id} -j ACCEPT 2>/dev/null || true")
        os.system(f"iptables -A FORWARD -i {bridge_id} -o wg0 -j ACCEPT 2>/dev/null || true")
        os.system("iptables -t nat -A POSTROUTING -s 172.30.0.0/16 -o eth0 -j MASQUERADE 2>/dev/null || true")

        self.log("Routing and firewall configured.", "success")

        # 10.5 Find duration from challenges.json
        duration_minutes = 15
        try:
            challenges_json_path = '/var/www/labs/htdocs/src/config/challenges.json'
            if os.path.exists(challenges_json_path):
                with open(challenges_json_path, 'r') as f:
                    challenges_list = json.load(f)
                    for item in challenges_list:
                        if item.get('lab_id') == challenge_id or item.get('lab_id') == challenge_id.replace('_', '-'):
                            # Find difficulty from tags
                            difficulty = 'easy'
                            tags = item.get('tags', [])
                            if isinstance(tags, list):
                                for tag in tags:
                                    if isinstance(tag, dict):
                                        t_text = str(tag.get('text', '')).lower()
                                        if t_text in ['easy', 'medium', 'hard']:
                                            difficulty = t_text
                                            break
                            duration_map = {
                                'easy': 15,
                                'medium': 30,
                                'hard': 45
                            }
                            duration_minutes = int(item.get('duration', duration_map.get(difficulty, 15)))
                            break
        except Exception as e:
            self.log(f"Error reading challenges.json for duration: {e}", "warn")

        # 11. Update Database
        self.db.challenge_instances.update_one(
            {"instance_hash": instance_id},
            {"$set": {
                "status": "running",
                "container_name": container_name,
                "credentials": {
                    "docker_ip": docker_ip,
                    "tunnel_ip": tunnel_ip,
                    "access_url": f"http://{tunnel_ip}"
                },
                "mission_started": False,
                "duration": duration_minutes,
                "expires_at": time.time() + (duration_minutes * 60),
                "created_at": time.time(),
                "updated_at": time.time()
            }}
        )

        self.log("═" * 50, "info")
        self.log("Challenge Deployment Complete!", "success")
        self.log(f"  Challenge:  {challenge_spec.get('lab_name', challenge_id)}", "info")
        self.log(f"  VPN Access: http://{tunnel_ip}", "info")
        self.log(f"  Docker IP:  {docker_ip} (internal)", "info")
        self.log(f"  Container:  {container_name}", "info")
        self.log("═" * 50, "info")

    # ─────────────────────────────────────────────
    # STOP
    # ─────────────────────────────────────────────
    def stop(self):
        """Stop a challenge container and clean up runtime networking."""
        instance_id = self.session_hash
        if not instance_id:
            self.log("FATAL: --hash is required.", "error")
            return

        container_name = f"ctf-{instance_id}"
        self.log(f"Stopping challenge: {container_name}", "info")

        # Fetch metadata
        challenge_data = self.db.challenge_instances.find_one({"instance_hash": instance_id}) if self.db else None

        if challenge_data:
            tunnel_ip = challenge_data.get('credentials', {}).get('tunnel_ip') or challenge_data.get('tunnel_ip')
            docker_ip = challenge_data.get('credentials', {}).get('docker_ip')
            if tunnel_ip:
                self.log(f"Cleaning host route: {tunnel_ip}", "info")
                os.system(f"ip route del {tunnel_ip}/32 2>/dev/null || true")
                if docker_ip:
                    os.system(f"iptables -t nat -D PREROUTING -d {tunnel_ip} -j DNAT --to-destination {docker_ip} 2>/dev/null || true")
                    os.system(f"iptables -t nat -D OUTPUT -d {tunnel_ip} -j DNAT --to-destination {docker_ip} 2>/dev/null || true")

        if self.docker.container_exists(container_name):
            os.system(f"docker rm -f {container_name}")

            if self.db:
                self.db.challenge_instances.update_one(
                    {"instance_hash": instance_id},
                    {"$set": {"status": "stopped", "updated_at": time.time()}}
                )
            self.log("Challenge container stopped and removed.", "success")
        else:
            self.log(f"Container {container_name} not found.", "warn")

    # ─────────────────────────────────────────────
    # START
    # ─────────────────────────────────────────────
    def start(self):
        """Start a stopped challenge container."""
        instance_id = self.session_hash
        if not instance_id:
            self.log("FATAL: --hash is required.", "error")
            return

        container_name = f"ctf-{instance_id}"
        self.log(f"Starting challenge: {container_name}", "info")

        if self.docker.container_exists(container_name):
            os.system(f"docker start {container_name}")
            self.log("Challenge container started.", "success")

            if self.db:
                self.db.challenge_instances.update_one(
                    {"instance_hash": instance_id},
                    {"$set": {"status": "running", "updated_at": time.time()}}
                )
        else:
            self.log(f"Container {container_name} not found. Deploy first.", "error")

    # ─────────────────────────────────────────────
    # REMOVE
    # ─────────────────────────────────────────────
    def remove(self):
        """Fully remove a challenge container and its DB record."""
        instance_id = self.session_hash
        if not instance_id:
            self.log("FATAL: --hash is required.", "error")
            return

        self.stop()

        if self.db:
            result = self.db.challenge_instances.delete_one({"instance_hash": instance_id})
            if result.deleted_count > 0:
                self.log("Challenge assignment removed from database.", "success")
            else:
                self.log("No challenge record found in database.", "warn")

        self.log("Challenge fully removed.", "success")

    # ─────────────────────────────────────────────
    # HELPERS
    # ─────────────────────────────────────────────
    def _allocate_challenge_ip(self, vpn_db, username, challenge_id):
        """
        Allocate a unique IP address for a challenge from the VPN network pool.
        Challenge IPs use the 10.20.0.x range to separate them from device VPN IPs (172.30.0.x).
        """
        tunnel_prefix = self.config.get('tunnel_ip_prefix', '172.30.0.')

        # Use a challenge-specific sub-range: 10.20.0.x (start at .10 to leave room)
        # Check existing allocations to find the next free IP
        existing = list(vpn_db.networks.find({
            "service_type": "challenge",
            "challenge_id": challenge_id
        }))

        used_octets = set()
        for alloc in existing:
            ip = alloc.get('ip_addr', '')
            if ip:
                parts = ip.split('.')
                if len(parts) == 4:
                    used_octets.add(int(parts[3]))

        # Find next available IP in range 10-254
        next_octet = None
        for candidate in range(10, 255):
            if candidate not in used_octets:
                next_octet = candidate
                break

        if next_octet is None:
            self.log("No IPs available in challenge pool!", "error")
            return None

        # Build the challenge tunnel IP: 10.20.0.<octet>
        challenge_ip = f"10.20.0.{next_octet}"

        # Register in the VPN network pool
        vpn_db.networks.insert_one({
            "email": username,
            "ip_addr": challenge_ip,
            "service_type": "challenge",
            "challenge_id": challenge_id,
            "allocated": True,
            "created_at": time.time()
        })

        self.log(f"Allocated challenge IP: {challenge_ip}", "success")
        return challenge_ip
