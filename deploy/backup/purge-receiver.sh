#!/bin/bash
# Retención de dumps y binlogs en el lado receptor (típicamente .211).
#
# RETENTION_DAYS del backup-db.sh solo corre en el servidor que hace el dump. El destino
# acumula para siempre si nadie limpia: el 2026-09-20 el .211 llegó a /var al 100% con
# 72 dumps y rsyncs truncados.
#
# Uso:
#   ./purge-receiver.sh                  # usa backup.conf (+ local)
#   BACKUP_DIR=/home/sergio/anitaERP_Backup ./purge-receiver.sh
# Cron sugerido en el .211 (sergio):
#   15 7,19 * * * /var/www/html/anitaERP/deploy/backup/purge-receiver.sh >> /home/sergio/anitaERP_Backup/purge-receiver.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

# En el .211 BACKUP_DIR puede ser el symlink /var/www/html/anitaERP/backups → /home/...
BACKUP_DIR="${BACKUP_DIR:-/var/www/html/anitaERP/backups}"
DB_NAME="${DB_NAME:-anitaERP}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
BINLOG_RETENTION_DAYS="${BINLOG_RETENTION_DAYS:-7}"
BINLOG_DIR="${BACKUP_DIR}/binlog"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

if [[ ! -d "${BACKUP_DIR}" ]]; then
    log "ERROR: no existe ${BACKUP_DIR}"
    exit 1
fi

DELETED=0
FAILED=0
while IFS= read -r -d '' old; do
    if rm "${old}" 2>/dev/null; then
        DELETED=$((DELETED + 1))
    else
        FAILED=$((FAILED + 1))
    fi
done < <(find -H "${BACKUP_DIR}" -maxdepth 1 -name "${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print0 2>/dev/null || true)
log "Retención dumps: ${DELETED} eliminado(s) (> ${RETENTION_DAYS} días) en ${BACKUP_DIR}"
if [[ "${FAILED}" -ne 0 ]]; then
    log "WARN: ${FAILED} dump(s) no se pudieron borrar"
fi

BIN_DEL=0
if [[ -d "${BINLOG_DIR}" ]]; then
    while IFS= read -r -d '' old; do
        if rm "${old}" 2>/dev/null; then
            BIN_DEL=$((BIN_DEL + 1))
        fi
    done < <(find -H "${BINLOG_DIR}" -maxdepth 1 \( -name 'binlog.*' -o -name 'binlog_snapshot_*.txt' \) -mtime +"${BINLOG_RETENTION_DAYS}" -print0 2>/dev/null || true)
    log "Retención binlog: ${BIN_DEL} eliminado(s) (> ${BINLOG_RETENTION_DAYS} días) en ${BINLOG_DIR}"
fi
