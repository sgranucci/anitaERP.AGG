#!/bin/bash
# Aplica vhost de PRODUCCIÓN en 10.20.30.210.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"

if [[ "${HOST_IP}" != "10.20.30.210" ]]; then
	echo "aplicar-vhost-210: solo para 10.20.30.210 (IP actual: ${HOST_IP})" >&2
	exit 1
fi

DEST="/etc/apache2/sites-available/anitaERP.conf"

sudo cp "${SCRIPT_DIR}/anitaERP-prod-210.conf" "${DEST}"
sudo a2enmod headers 2>/dev/null || true
sudo a2dissite 000-default 2>/dev/null || true
sudo a2ensite anitaERP
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "OK: vhost PROD activo — ServerName 10.20.30.210"
apache2ctl -S 2>/dev/null | grep -E '10.20.30.210|anitaERP' || true
