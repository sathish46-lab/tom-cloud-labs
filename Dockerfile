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
    php8.4 php8.4-cli php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-mongodb php8.4-amqp php8.4-mysql php8.4-pgsql php8.4-redis \
    rabbitmq-server \
    wireguard wireguard-tools \
    openssh-server \
    python3 python3-pip python3-pymongo python3-docker python3-redis python3-pika python3-psutil \
    docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
    ufw fail2ban nmap mongodb-org mysql-server postgresql redis-server \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Python packages not available via apt
RUN pip3 install --break-system-packages google-generativeai requests pymongo markdown

# Install MCP server dependencies (separate layer to avoid dep conflicts)
RUN pip3 install --break-system-packages --ignore-installed PyJWT \
    mcp==1.27.1 starlette==0.41.3 sse-starlette==2.2.1 uvicorn==0.46.0 bcrypt

# Create symlink for labsctl
RUN ln -sf /opt/labs-control-panel/labsctl.py /usr/local/bin/labsctl && \
    chmod +x /opt/labs-control-panel/labsctl.py 2>/dev/null || true

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

# MySQL Bind Address
RUN sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true

# PostgreSQL Config — listen on all interfaces (lab apps reach it cross-container)
RUN for conf in /etc/postgresql/*/main/postgresql.conf; do sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/" "$conf"; done && \
    for conf in /etc/postgresql/*/main/pg_hba.conf; do echo "host all all 127.0.0.1/32 md5" >> "$conf"; echo "host all all all trust" >> "$conf"; done && \
    mkdir -p /etc/systemd/system/postgresql@.service.d && \
    echo "[Service]\nPIDFile=" > /etc/systemd/system/postgresql@.service.d/override.conf

# Redis Config — bind on all interfaces, protected-mode stays on (ACL password required).
# aclfile so ACL SAVE persists learner users (otherwise SAVE errors and creates abort).
RUN sed -i 's/bind 127.0.0.1 ::1/bind 0.0.0.0 ::1/' /etc/redis/redis.conf && \
    echo "aclfile /var/lib/redis/users.acl" >> /etc/redis/redis.conf && \
    touch /var/lib/redis/users.acl && chown redis:redis /var/lib/redis/users.acl && \
    mkdir -p /etc/systemd/system/redis-server.service.d && \
    echo "[Service]\nPrivateUsers=no" > /etc/systemd/system/redis-server.service.d/override.conf

# MariaDB — port 3307. Extracted with dpkg -x to /opt/mariadb-labs (NOT apt install):
# mysql-server-8.0 and mariadb-server hard-conflict on Ubuntu 24.04, and the mariadb
# package hijacks /etc/mysql/my.cnf, /etc/init.d/mysql and mysql.service.
RUN apt-get update && \
    apt-get install -y --no-install-recommends liburing2 libnuma1 libedit2 libncurses6 && \
    cd /tmp && \
    apt-get download mariadb-server mariadb-server-core mariadb-client mariadb-client-core && \
    for deb in /tmp/mariadb*.deb; do dpkg -x "$deb" /opt/mariadb-labs; done && \
    rm -f /tmp/mariadb*.deb && apt-get clean && rm -rf /var/lib/apt/lists/*

# MariaDB-labs config, systemd unit and datadir init (own socket/datadir; MySQL 8 untouched)
RUN printf '%s\n' \
        '# MariaDB server config for the labs stack (port 3307, isolated datadir/socket).' \
        '# Loaded exclusively via --defaults-file by the mariadb-labs systemd unit,' \
        '# so MySQL 8 and MariaDB never read each others settings.' \
        '' \
        '[client]' \
        'default-character-set = utf8mb4' \
        '' \
        '[client-server]' \
        'port = 3307' \
        'socket = /run/mysqld/mysqld-mariadb.sock' \
        '' \
        '[mariadb]' \
        'user = mysql' \
        'basedir = /opt/mariadb-labs/usr' \
        'bind-address = 0.0.0.0' \
        'port = 3307' \
        'datadir = /var/lib/mysql-mariadb' \
        'socket = /run/mysqld/mysqld-mariadb.sock' \
        'pid-file = /run/mysqld/mysqld-mariadb.pid' \
        'character-set-server = utf8mb4' \
        'collation-server = utf8mb4_general_ci' \
        'expire_logs_days = 10' \
        > /etc/mysql/mariadb-labs.cnf && \
    printf '%s\n' \
        '[Unit]' \
        'Description=Labs MariaDB database server (port 3307, /opt/mariadb-labs extraction)' \
        'After=network.target' \
        '' \
        '[Service]' \
        'Type=notify' \
        'ExecStartPre=/bin/sh -c "install -m 755 -o mysql -g root -d /run/mysqld"' \
        'ExecStart=/opt/mariadb-labs/usr/sbin/mariadbd --defaults-file=/etc/mysql/mariadb-labs.cnf' \
        'Restart=on-failure' \
        'RestartSec=5s' \
        'LimitNOFILE=65536' \
        '' \
        '[Install]' \
        'WantedBy=multi-user.target' \
        > /etc/systemd/system/mariadb-labs.service && \
    mkdir -p /var/lib/mysql-mariadb && chown mysql:mysql /var/lib/mysql-mariadb && \
    /opt/mariadb-labs/usr/bin/mysql_install_db --defaults-file=/etc/mysql/mariadb-labs.cnf \
        --auth-root-authentication-method=socket --user=mysql \
        --basedir=/opt/mariadb-labs/usr --skip-test-db

# 7. Create Directories
RUN mkdir -p /var/www/labs /var/www/vpn-api /opt/labs-control-panel /etc/traefik/dynamic /etc/wireguard /var/www/adminer

# Install Adminer
RUN wget -O /var/www/adminer/index.php https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1.php && \
    echo '<VirtualHost *:8080>\n    ServerName adminer.tomweb.in\n    DocumentRoot /var/www/adminer\n    <Directory /var/www/adminer>\n        AllowOverride All\n        Require all granted\n    </Directory>\n    ErrorLog ${APACHE_LOG_DIR}/adminer_error.log\n</VirtualHost>' > /etc/apache2/sites-available/adminer.conf && \
    a2ensite adminer
# 8. Static Configurations

# SSH Server Configuration
RUN mkdir -p /run/sshd && \
    sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin yes/' /etc/ssh/sshd_config && \
    sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication yes/' /etc/ssh/sshd_config && \
    sed -i 's/^#\?KbdInteractiveAuthentication.*/KbdInteractiveAuthentication yes/' /etc/ssh/sshd_config && \
    sed -i 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' /etc/ssh/sshd_config && \
    sed -i 's/^#\?UsePAM.*/UsePAM no/' /etc/ssh/sshd_config && \
    echo "root:root" | chpasswd && \
    systemctl enable ssh

# Disable fail2ban for SSH (blocks Docker internal IPs)
RUN echo "" > /etc/fail2ban/jail.d/sshd.conf 2>/dev/null || true

# Apache Ports
RUN echo "Listen 80\nListen 8080\nListen 8081\nListen 8082\n<IfModule ssl_module>\n    Listen 443\n</IfModule>" > /etc/apache2/ports.conf
RUN touch /etc/apache2/code_server_map.txt

# Increase Apache MaxRequestWorkers to prevent 502 Bad Gateway under load
RUN sed -i 's/MaxRequestWorkers.*/MaxRequestWorkers 400/' /etc/apache2/mods-available/mpm_prefork.conf

# Traefik configuration
RUN touch /etc/traefik/acme.json && chmod 600 /etc/traefik/acme.json
RUN echo "entryPoints:\n  websecure:\n    address: \":443\"\nproviders:\n  file:\n    directory: \"/etc/traefik/dynamic\"\n    watch: true" > /etc/traefik/traefik.yml

# Traefik Systemd Service
RUN echo "[Unit]\nDescription=Traefik Edge Router\nAfter=network-online.target\n[Service]\nRestart=on-failure\nExecStart=/usr/local/bin/traefik --configFile=/etc/traefik/traefik.yml\nLimitNOFILE=65536\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/traefik.service
RUN systemctl enable traefik

# Container Setup Systemd Service (runs after DBs)
RUN echo "[Unit]\nDescription=Container Setup Script\nAfter=mongod.service rabbitmq-server.service postgresql.service redis-server.service mariadb-labs.service network.target\n[Service]\nType=oneshot\nTimeoutStartSec=infinity\nExecStart=/usr/local/bin/init-services.sh\nRemainAfterExit=yes\n[Install]\nWantedBy=multi-user.target" > /etc/systemd/system/init-services.service
RUN systemctl enable mongod.service rabbitmq-server.service postgresql.service redis-server.service mariadb-labs.service init-services.service
# Disable unused DBs to save RAM (~600MB)
RUN rm -f /etc/systemd/system/multi-user.target.wants/mysql.service \
          /etc/systemd/system/multi-user.target.wants/mariadb.service || true

# Fix PostgreSQL systemd bug in containers (refusing PIDFile)
RUN mkdir -p /etc/systemd/system/postgresql@.service.d/ && \
    printf "[Service]\nPIDFile=\n" > /etc/systemd/system/postgresql@.service.d/override.conf

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
    echo "www-data ALL=(rabbitmq) NOPASSWD: /usr/sbin/rabbitmqctl, /usr/sbin/rabbitmq-plugins" > /etc/sudoers.d/rabbitmq && \
    chmod 440 /etc/sudoers.d/rabbitmq && \
    git config --system --add safe.directory /var/www && \
    git config --system --add safe.directory /var/www/labs

# Copy scripts
# Avoid internal docker conflict and ensure socket symlink persists
RUN systemctl mask docker.service docker.socket && \
    echo "L+ /run/docker.sock - - - - /var/docker.sock" > /etc/tmpfiles.d/docker-socket.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY init-services.sh /usr/local/bin/init-services.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/init-services.sh

VOLUME [ "/sys/fs/cgroup" ]

# Set Entrypoint script up to run before systemd execution
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
