# /opt/labs-control-panel/service-templates/postgresql/Dockerfile
# Tom Labs PostgreSQL Service Template
# Builds PostgreSQL from source using ARG POSTGRES_VERSION
# Usage: docker build --build-arg POSTGRES_VERSION=16.10 -t tomcloud/postgresql:16.10 .
#        docker compose up postgres          (uses compose POSTGRES_VERSION arg, default 17)

ARG POSTGRES_VERSION=17

FROM debian:12-slim

ARG POSTGRES_VERSION

ENV DEBIAN_FRONTEND=noninteractive \
    PATH=/usr/local/pgsql/bin:$PATH \
    PGDATA=/var/lib/postgresql/data \
    LANG=en_US.UTF-8 \
    LC_ALL=en_US.UTF-8 \
    POSTGRES_PASSWORD=${POSTGRES_PASSWORD:-Sathish47@2026} \
    POSTGRES_USER=${POSTGRES_USER:-sathish47} \
    POSTGRES_DB=${POSTGRES_DB:-test4_test}

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libreadline-dev \
        zlib1g-dev \
        libssl-dev \
        libxml2-dev \
        libxslt1-dev \
        wget \
        ca-certificates \
        libssl3 \
        libxml2 \
        libxslt1.1 \
        libreadline8 \
        locales \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i '/en_US.UTF-8/s/^# //g' /etc/locale.gen && \
    locale-gen en_US.UTF-8

WORKDIR /tmp
RUN set -ex && \
    wget -q https://ftp.postgresql.org/pub/source/v${POSTGRES_VERSION}/postgresql-${POSTGRES_VERSION}.tar.gz && \
    tar -xzf postgresql-${POSTGRES_VERSION}.tar.gz && \
    cd postgresql-${POSTGRES_VERSION} && \
    ./configure --prefix=/usr/local/pgsql --with-openssl --with-libxml --with-libxslt --without-icu && \
    make -j$(nproc) && \
    make install && \
    cd contrib && \
    make -j$(nproc) && \
    make install

RUN groupadd -r postgres && useradd -r -g postgres -d /var/lib/postgresql -s /bin/bash postgres && \
    mkdir -p /var/lib/postgresql/data /docker-entrypoint-initdb.d /etc/postgresql && \
    chown -R postgres:postgres /var/lib/postgresql /docker-entrypoint-initdb.d /etc/postgresql

COPY postgresql.conf.lab /etc/postgresql/postgresql.conf.lab

# Initialize database, create user sathish47 + database test4_test with password auth
RUN cat > /tmp/init_pg.sh << INITEOF && \
    chmod +x /tmp/init_pg.sh && \
    su postgres -c "/tmp/init_pg.sh" && \
    rm -f /tmp/init_pg.sh
#!/bin/bash
set -e
initdb -D /var/lib/postgresql/data --username=postgres --auth=trust --locale=C
echo "" >> /var/lib/postgresql/data/postgresql.conf
echo "# === Tom Labs optimizations ===" >> /var/lib/postgresql/data/postgresql.conf
echo "include_if_exists = '/etc/postgresql/postgresql.conf.lab'" >> /var/lib/postgresql/data/postgresql.conf
pg_ctl -D /var/lib/postgresql/data -o "-c listen_addresses='localhost'" -w start
until pg_isready -U postgres -q; do sleep 1; done
psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE USER ${POSTGRES_USER} WITH PASSWORD '${POSTGRES_PASSWORD}';"
psql -U postgres -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE ${POSTGRES_DB} OWNER ${POSTGRES_USER};"
pg_ctl -D /var/lib/postgresql/data -w stop
sed -i 's/^host\s*all\s*all\s*127.0.0.1\/32\s*trust/host all all 127.0.0.1\/32 scram-sha-256/' /var/lib/postgresql/data/pg_hba.conf
sed -i 's/^host\s*all\s*all\s*::1\/128\s*trust/host all all ::1\/128 scram-sha-256/' /var/lib/postgresql/data/pg_hba.conf
echo "host    all             all             0.0.0.0/0               scram-sha-256" >> /var/lib/postgresql/data/pg_hba.conf
chown -R postgres:postgres /var/lib/postgresql/data
INITEOF

EXPOSE 5432

USER postgres

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}"

CMD ["postgres", "-D", "/var/lib/postgresql/data"]
