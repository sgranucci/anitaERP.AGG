#!/bin/bash
# Instala el sudoers que permite a sergio copiar binlogs sin password (solo ese script).
# Uso: sudo /var/www/html/anitaERP/deploy/backup/install-sudoers-binlog.sh

set -euo pipefail

SRC="/var/www/html/anitaERP/deploy/backup/sudoers.anitaERP-backup-binlog.example"
DST="/etc/sudoers.d/anitaERP-backup-binlog"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Correr como root: sudo $0" >&2
    exit 1
fi

install -m 440 -o root -g root "${SRC}" "${DST}"
visudo -c -f "${DST}"
echo "OK: ${DST}"
echo "Probar: sudo -n /var/www/html/anitaERP/deploy/backup/backup-binlog-copy-root.sh"
