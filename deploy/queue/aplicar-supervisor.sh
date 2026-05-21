#!/bin/bash
# Activa el worker de colas con Supervisor (persistente, usuario www-data).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_SRC="$SCRIPT_DIR/../supervisor/anitaERP-queue.conf"
CONF_DEST="/etc/supervisor/conf.d/anitaERP-queue.conf"

if ! command -v supervisorctl >/dev/null 2>&1; then
    echo "Instalando supervisor..."
    sudo apt-get update -qq
    sudo apt-get install -y supervisor
fi

sudo cp "$CONF_SRC" "$CONF_DEST"
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start anitaERP-queue || sudo supervisorctl restart anitaERP-queue

echo ""
echo "Colas activas. Estado:"
sudo supervisorctl status anitaERP-queue
echo ""
echo "Log: /var/www/html/anitaERP/storage/logs/queue-worker.log"
