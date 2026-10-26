#!/bin/bash
# Permite a www-data leer "supervisorctl status" sin contraseña.
# Uso: sudo /var/www/html/anitaERP/deploy/queue/install-sudoers-queue-status.sh

set -euo pipefail

SRC="/var/www/html/anitaERP/deploy/queue/sudoers.anitaerp-queue-status.example"
DST="/etc/sudoers.d/anitaerp-queue-status"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Correr como root: sudo $0" >&2
    exit 1
fi

install -m 440 -o root -g root "${SRC}" "${DST}"
visudo -c -f "${DST}"
echo "OK: ${DST}"
echo "Probar: sudo -u www-data sudo -n /usr/bin/supervisorctl status"
