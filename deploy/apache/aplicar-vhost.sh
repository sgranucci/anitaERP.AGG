#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST="/etc/apache2/sites-available/anitaERP.conf"

sudo cp "$SCRIPT_DIR/anitaERP.conf" "$DEST"
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "Virtual host aplicado: Timeout 600 en $DEST"
