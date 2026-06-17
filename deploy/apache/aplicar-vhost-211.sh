#!/bin/bash
# Aplica vhost de TEST en 10.20.30.211. Deshabilita 000-default y anitaERP.conf legacy.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST_IP="$(hostname -I | awk '{print $1}')"

if [[ "${HOST_IP}" != "10.20.30.211" ]]; then
	echo "aplicar-vhost-211: solo para 10.20.30.211 (IP actual: ${HOST_IP})" >&2
	exit 1
fi

DEST="/etc/apache2/sites-available/anitaERP-test.conf"

sudo cp "${SCRIPT_DIR}/anitaERP-test-211.conf" "${DEST}"
sudo a2enmod headers 2>/dev/null || true
sudo a2dissite 000-default 2>/dev/null || true
sudo a2dissite anitaERP 2>/dev/null || true
sudo a2ensite anitaERP-test
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "OK: vhost TEST activo — ServerName 10.20.30.211"
echo "Logs: /var/log/apache2/anitaERP-test-{access,error}.log"
apache2ctl -S 2>/dev/null | grep -E '10.20.30.211|anitaERP' || true
