# /opt/labs-control-panel/service-templates/postgresql/Dockerfile
# Tom Labs PostgreSQL Service Template
# Built from the official latest PostgreSQL image with lab-optimized settings.
# Do NOT pull postgres:XX directly — always build from this template so we can
# apply consistent configuration, extensions, and security hardening.

FROM postgres:latest

# Lab-specific environment defaults (overridable via compose env)
ENV POSTGRES_PASSWORD=${POSTGRES_PASSWORD:-postgres} \
    POSTGRES_USER=${POSTGRES_USER:-postgres} \
    POSTGRES_DB=${POSTGRES_DB:-tomcloud} \
    PGDATA=/var/lib/postgresql/data

# Install lab-required extensions and tuning packages
RUN apt-get update && apt-get install -y --no-install-recommends \
        postgresql-contrib \
        postgresql-plpython3-${PG_MAJOR} \
        ca-certificates \
        curl \
        iproute2 \
        procps \
        net-tools \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/archives/*

# Copy lab-optimized postgresql.conf
COPY postgresql.conf /etc/postgresql/postgresql.conf.lab

# Enable lab-optimized config on first init (appended to postgresql.conf)
RUN echo "" >> /usr/share/postgresql/${PG_MAJOR}/postgresql.conf.sample \
    && echo "# === Tom Labs optimizations (appended by service-templates/postgresql/Dockerfile) ===" >> /usr/share/postgresql/${PG_MAJOR}/postgresql.conf.sample \
    && echo "include_if_exists = '/etc/postgresql/postgresql.conf.lab'" >> /usr/share/postgresql/${PG_MAJOR}/postgresql.conf.sample

# Create directory for init scripts (SQL files mounted via compose volumes)
RUN mkdir -p /docker-entrypoint-initdb.d

EXPOSE 5432

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}"

CMD ["postgres"]
