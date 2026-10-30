#!/usr/bin/env bash
# Heartbeat Ping Client for Armbian / Linux / Raspberry Pi
# Cron: * * * * * /home/monitoring/heartbeat.sh >/dev/null 2>&1

TARGET_URL="https://netmon.silverlightning.my.id/api/ping"
PING_TOKEN="f7dd1cc238caf6f940050419744b95ec"

# Execute HTTP GET ping with 10-second timeout, discard output
/usr/bin/curl -s -m 10 "${TARGET_URL}?token=${PING_TOKEN}" >/dev/null 2>&1
