#!/bin/bash

# Configuration
SERVICE_NAME="labs-worker"
MAX_LIMIT=50

# Check input
if [ -z "$1" ]; then
    echo "Usage: ./manage_workers.sh <number_of_workers>"
    echo "Example: ./manage_workers.sh 5"
    exit 1
fi

COUNT=$1

if ! [[ "$COUNT" =~ ^[0-9]+$ ]]; then
    echo "Error: Argument must be a number."
    exit 1
fi

if [ "$COUNT" -gt "$MAX_LIMIT" ]; then
    echo "Error: Max limit is $MAX_LIMIT."
    exit 1
fi

echo "Scaling $SERVICE_NAME to $COUNT instances..."

# 1. Get currently running count (approximate)
CURRENT_ACTIVE=$(systemctl list-units --type=service "${SERVICE_NAME}@*" | grep "loaded active" | wc -l)
echo "Current active workers: $CURRENT_ACTIVE"

# 2. Enable/Start the requested range
echo "Enabling workers 1 to $COUNT..."
# Generate list of services: labs-worker@1 labs-worker@2 ...
TARGETS=$(seq -f "${SERVICE_NAME}@%.0f" 1 $COUNT)
systemctl enable $TARGETS
systemctl start $TARGETS

# 3. Disable/Stop anything above the requested range (up to MAX_LIMIT)
NEXT=$((COUNT + 1))
if [ "$NEXT" -le "$MAX_LIMIT" ]; then
    echo "Stopping workers $NEXT to $MAX_LIMIT..."
    STOP_TARGETS=$(seq -f "${SERVICE_NAME}@%.0f" $NEXT $MAX_LIMIT)
    systemctl stop $STOP_TARGETS
    systemctl disable $STOP_TARGETS
fi

echo "Done! Verified active workers:"
systemctl status "${SERVICE_NAME}@*" --no-pager | grep "Active:" | wc -l
