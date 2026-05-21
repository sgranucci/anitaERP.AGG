#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_SRC="$SCRIPT_DIR/anitaERP"
CONF_DEST="/etc/logrotate.d/anitaERP"

sudo cp "$CONF_SRC" "$CONF_DEST"
sudo chmod 644 "$CONF_DEST"

# Crear schedule.log si no existe (cron www-data)
sudo -u www-data touch /var/www/html/anitaERP/storage/logs/schedule.log 2>/dev/null || true

sudo logrotate -d "$CONF_DEST" 2>&1 | tail -5
echo ""
echo "Logrotate instalado en $CONF_DEST"
echo "  - schedule.log (cron schedule:run)"
echo "  - queue-worker.log (supervisor)"
echo "Rotación: diaria, 14 copias, comprimidas."
