#!/bin/bash
# labs-worker-env.sh — bootstrap env for the labs worker, then exec its process.
# Credentials come from the central labs-ctl-env.sh (runtime env.json, no hardcoding).
source /opt/labs-control-panel/systemd/labs-ctl-env.sh

exec /usr/bin/python3 -u /var/www/labs/worker/labs_worker.py
