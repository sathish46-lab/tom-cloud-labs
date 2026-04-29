FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV container docker

# 1. System Updates & Core Dependencies
RUN apt-get update && \
    apt-get install -y systemd systemd-sysv sudo iputils-ping curl wget nano iptables iproute2 kmod tzdata software-properties-common gnupg2 jq \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Delete systemd targets that prevent running cleanly in a container
RUN cd /lib/systemd/system/sysinit.target.wants/ || exit; \
    for i in *; do [ $i = systemd-tmpfiles-setup.service ] || rm -f $i; done; \
    rm -f /lib/systemd/system/multi-user.target.wants/*; \
    rm -f /etc/systemd/system/*.wants/*; \
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*; \
    rm -f /lib/systemd/system/anaconda.target.wants/*;

# 2. Add Repositories (PHP, Docker, MongoDB)
RUN add-apt-repository -y ppa:ondrej/php && \
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg --yes && \
    chmod a+r /etc/apt/keyrings/docker.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu noble stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null && \
    curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc | sudo gpg -o /usr/share/keyrings/mongodb-server-8.0.gpg --dearmor --yes && \
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/ubuntu noble/mongodb-org/8.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-8.0.list

# 3. Install Software Stack
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

# Install Python packages not available via apt
RUN pip3 install --break-system-packages google-generativeai requests pymongo

# Create symlink for labsctl
RUN ln -sf /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl && \
    chmod +x /opt/labs-control-panel/labsctl.py

# 4. Install Traefik
RUN wget https://github.com/traefik/traefik/releases/download/v2.10.6/traefik_v2.10.6_linux_amd64.tar.gz && \
    tar -zxvf traefik_v2.10.6_linux_amd64.tar.gz && \
    mv traefik /usr/local/bin/ && \
    chmod +x /usr/local/bin/traefik && \
    rm traefik_v2.10.6_linux_amd64.tar.gz

# 5. Install Node.js, Composer, Grunt
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    npm install -g grunt-cli

# 6. Configure Base Services
# MongoDB Auth
RUN sed -i 's/#security:/security:\n  authorization: enabled/' /etc/mongod.conf && \
    sed -i 's/bindIp: 127.0.0.1/bindIp: 0.0.0.0/' /etc/mongod.conf

# 7. Create Directories
RUN mkdir -p /var/www/labs /var/www/vpn-api /opt/labs-control-panel /etc/traefik/dynamic_conf /etc/wireguard

# 8. Static Configurations
# Apache Ports
RUN echo "Listen 8081\nListen 8082\n<IfModule ssl_module>\n    Listen 4431\n</IfModule>" > /etc/apache2/ports.conf
RUN touch /etc/apache2/code_server_map.txt

# Traefik configuration
RUN touch /etc/traefik/acme.json && chmod 600 /etc/traefik/acme.json
RUN echo "entryPoints:\n  web:\n    address: \":80\"\n  websecure:\n    address: \":443\"\nproviders:\n  file:\n    directory: \"/etc/traefik/dynamic_conf\"\n    watch: true" > /etc/traefik/traefik.yml

# Traefik Systemd Service
RUN echo "[Unit]\nDescription=Traefik Edge Router\nAfter=network-online.target\n[Service]\nRestart=on-failure\nExecStart=/usr/local/bin/traefik --configFile=/etc/traefik/traefik.yml\nLimitNOFILE=65536\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/traefik.service
RUN systemctl enable traefik

# Container Setup Systemd Service (runs after DB & RabbitMQ)
RUN echo "[Unit]\nDescription=Container Setup Script\nAfter=mongod.service rabbitmq-server.service network.target\n[Service]\nType=oneshot\nExecStart=/usr/local/bin/container-setup.sh\nRemainAfterExit=yes\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/container-setup.service
RUN systemctl enable mongod.service rabbitmq-server.service container-setup.service

# Sudoers & Git config
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

# Copy scripts
# Avoid internal docker conflict and ensure socket symlink persists
RUN systemctl mask docker.service docker.socket && \
    echo "L+ /run/docker.sock - - - - /var/docker.sock" > /etc/tmpfiles.d/docker-socket.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY container-setup.sh /usr/local/bin/container-setup.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/container-setup.sh

VOLUME [ "/sys/fs/cgroup" ]

# Set Entrypoint script up to run before systemd execution
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
