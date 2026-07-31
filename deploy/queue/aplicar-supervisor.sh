#!/bin/bash
# Activa el worker de colas con Supervisor (persistente, usuario www-data).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_SRC="$SCRIPT_DIR/../supervisor/anitaERP-queue.conf"
CONF_DEST="/etc/supervisor/conf.d/anitaERP-queue.conf"
CONF_PADRONES_SRC="$SCRIPT_DIR/../supervisor/anitaERP-queue-padrones.conf"
CONF_PADRONES_DEST="/etc/supervisor/conf.d/anitaERP-queue-padrones.conf"

if ! command -v supervisorctl >/dev/null 2>&1; then
    echo "Instalando supervisor..."
    sudo apt-get update -qq
    sudo apt-get install -y supervisor
fi

sudo cp "$CONF_SRC" "$CONF_DEST"
sudo cp "$CONF_PADRONES_SRC" "$CONF_PADRONES_DEST"
sudo supervisorctl reread
# Con numprocs>1 el grupo es anitaERP-queue:* (start/restart sin : suele fallar)
sudo supervisorctl update anitaERP-queue
sudo supervisorctl update anitaERP-queue-padrones

echo ""
echo "Colas activas. Estado:"
sudo supervisorctl status 'anitaERP-queue:*' || true
sudo supervisorctl status anitaERP-queue-padrones || true
echo ""
echo "Log default: /var/www/html/anitaERP/storage/logs/queue-worker.log"
echo "Log padrones: /var/www/html/anitaERP/storage/logs/queue-padrones-worker.log"
