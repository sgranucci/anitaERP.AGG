#!/bin/bash
# Copia binlogs CERRADOS (todos menos el activo) a backups/binlog/.
# Ejecutar como root (cron) o vía sudo NOPASSWD — ver sudoers.anitaERP-backup-binlog.example

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

MYSQL_DATADIR="${MYSQL_DATADIR:-/var/lib/mysql}"
BINLOG_DIR="${BACKUP_DIR}/binlog"
mkdir -p "${BINLOG_DIR}"

mapfile -t ALL_LOGS < <(mysql -N -e "SHOW BINARY LOGS" | awk '{print $1}')
if [[ ${#ALL_LOGS[@]} -lt 2 ]]; then
    exit 0
fi

# Todos excepto el último (activo)
for ((i = 0; i < ${#ALL_LOGS[@]} - 1; i++)); do
    logfile="${ALL_LOGS[$i]}"
    src="${MYSQL_DATADIR}/${logfile}"
    dest="${BINLOG_DIR}/${logfile}"
    if [[ -f "${dest}" ]]; then
        continue
    fi
    if [[ ! -f "${src}" ]]; then
        echo "WARN: no existe ${src}" >&2
        continue
    fi
    cp -a "${src}" "${dest}"
    chown sergio:sergio "${dest}" 2>/dev/null || true
done

find "${BINLOG_DIR}" -maxdepth 1 -type f -name 'binlog.*' -mtime +"${BINLOG_RETENTION_DAYS}" -delete 2>/dev/null || true
