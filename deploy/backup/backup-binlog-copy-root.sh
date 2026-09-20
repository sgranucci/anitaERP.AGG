#!/bin/bash
# Copia binlogs CERRADOS (todos menos el activo) a backups/binlog/.
# Ejecutar como root (cron) o vía sudo NOPASSWD — ver sudoers.anitaERP-backup-binlog.example
#
# Corre con sudo: HOME=/root y mysql no encuentra /home/sergio/.my.cnf. Por eso se pasa
# --defaults-extra-file explícito. La lectura de /var/lib/mysql/binlog.* sí necesita root.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

MYSQL_DEFAULTS="${MYSQL_DEFAULTS_FILE:-/home/sergio/.my.cnf}"
mysql_q() {
    if [[ -f "${MYSQL_DEFAULTS}" ]]; then
        mysql --defaults-extra-file="${MYSQL_DEFAULTS}" "$@"
    else
        mysql "$@"
    fi
}

BINLOG_DIR="${BACKUP_DIR}/binlog"
mkdir -p "${BINLOG_DIR}"

# Directorio real de binlogs (p. ej. /var/lib/mysql si log_bin_basename=/var/lib/mysql/binlog)
BINLOG_BASE="$(mysql_q -N -e "SHOW VARIABLES LIKE 'log_bin_basename'" | awk '{print $2}')"
if [[ -z "${BINLOG_BASE}" ]]; then
    exit 0
fi
BINLOG_SRC_DIR="$(dirname "${BINLOG_BASE}")"

mapfile -t ALL_LOGS < <(mysql_q -N -e "SHOW BINARY LOGS" | awk '{print $1}')
if [[ ${#ALL_LOGS[@]} -lt 2 ]]; then
    exit 0
fi

COPIADOS=0
# Todos excepto el último (activo — MySQL lo sigue escribiendo)
for ((i = 0; i < ${#ALL_LOGS[@]} - 1; i++)); do
    logfile="${ALL_LOGS[$i]}"
    src="${BINLOG_SRC_DIR}/${logfile}"
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
    COPIADOS=$((COPIADOS + 1))
done

# -H: BACKUP_DIR puede ser symlink (→ /scan en .210); find no lo sigue sin -H.
find -H "${BINLOG_DIR}" -maxdepth 1 -type f -name 'binlog.*' -mtime +"${BINLOG_RETENTION_DAYS}" -delete 2>/dev/null || true

echo "binlogs copiados: ${COPIADOS}"
