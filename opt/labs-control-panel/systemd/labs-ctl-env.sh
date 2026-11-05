#!/bin/bash
# labs-ctl-env.sh — central env for labsctl systemd services.
# Source it from a bash wrapper (values are computed at runtime from env.json
# so no credentials are hardcoded); not usable as a systemd EnvironmentFile
# (systemd does not run command substitution).
export DOCKER_HOST=unix:///var/docker.sock
export RABBITMQ_HOST=127.0.0.1
export RABBITMQ_PORT=5672
export MONGO_HOST=127.0.0.1
export MONGO_PORT=27017

if [ -f /var/www/env.json ]; then
    export RABBITMQ_USER=$(python3 -c "import json; print(json.load(open('/var/www/env.json'))['amqp_user'])")
    export RABBITMQ_PASS=$(python3 -c "import json; print(json.load(open('/var/www/env.json'))['amqp_pass'])")
    export MONGO_USER=$(python3 -c "import json; print(json.load(open('/var/www/env.json'))['mongo_user'])")
    export MONGO_PASS=$(python3 -c "import json; print(json.load(open('/var/www/env.json'))['mongo_pass'])")
fi

# MCP server: restricted credentials (readWrite on tom_labs_db only), colocated mongod
export MCP_MONGO_URI="mongodb://mcp_server:mcp_secure_2026@127.0.0.1:27017/tom_labs_db?authSource=admin"
