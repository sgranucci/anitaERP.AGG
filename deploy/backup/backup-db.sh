#!/bin/bash
# Backup anitaERP: mysqldump + gzip, validación, snapshot binlog, sync remoto opcional.
# Cron sugerido (usuario sergio): 0 6,18 * * * /var/www/html/anitaERP/deploy/backup/backup-db.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
OUTPUT="${BACKUP_DIR}/${DB_NAME}_${TIMESTAMP}.sql.gz"
BINLOG_DIR="${BACKUP_DIR}/binlog"
FAILED=0

mkdir -p "${BACKUP_DIR}" "${BINLOG_DIR}"
mkdir -p "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}" 2>/dev/null || true
chmod 664 "${LOG_FILE}" 2>/dev/null || true

log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
    echo "${msg}"
    echo "${msg}" >> "${LOG_FILE}" 2>/dev/null || echo "${msg}" >> "${BACKUP_DIR}/backup-db.log"
}

log_error() {
    log "ERROR: $*"
    FAILED=1
}

log_warn() {
    log "WARN: $*"
}

if [[ ! -f "${HOME}/.my.cnf" ]]; then
    log_error "no existe ${HOME}/.my.cnf (chmod 600)"
    log_error "Ejecutar: ${SCRIPT_DIR}/setup-mycnf.sh  (lee DB_* del .env del proyecto)"
    exit 1
fi

log "Inicio backup ${DB_NAME} -> ${OUTPUT} (host $(hostname))"

if ! mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "${DB_NAME}" | gzip > "${OUTPUT}"; then
    log_error "mysqldump falló"
    rm -f "${OUTPUT}"
    exit 1
fi

BYTES="$(stat -c '%s' "${OUTPUT}" 2>/dev/null || echo 0)"
if [[ "${BYTES}" -lt "${MIN_BACKUP_BYTES}" ]]; then
    log_error "dump demasiado chico (${BYTES} bytes < ${MIN_BACKUP_BYTES})"
    rm -f "${OUTPUT}"
    exit 1
fi

if ! gzip -t "${OUTPUT}"; then
    log_error "gzip -t falló (archivo corrupto)"
    rm -f "${OUTPUT}"
    exit 1
fi

SIZE="$(du -h "${OUTPUT}" | awk '{print $1}')"
log "Backup OK (${SIZE}, ${BYTES} bytes)"

# Posición binlog (referencia PITR)
if SNAPSHOT="$("${SCRIPT_DIR}/backup-binlog-snapshot.sh" 2>&1)"; then
    log "Binlog snapshot: ${SNAPSHOT}"
else
    log_warn "snapshot binlog no generado"
fi

# Copia binlogs cerrados si sudo NOPASSWD está configurado
if sudo -n "${SCRIPT_DIR}/backup-binlog-copy-root.sh" 2>/dev/null; then
    log "Binlog files: copia root OK"
else
    log_warn "binlog files no copiados (opcional: sudoers → deploy/backup/sudoers.anitaERP-backup-binlog.example)"
fi

# Retención dumps
DELETED=0
while IFS= read -r -d '' old; do
    rm -f "${old}"
    DELETED=$((DELETED + 1))
done < <(find "${BACKUP_DIR}" -maxdepth 1 -name "${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print0 2>/dev/null || true)
log "Retención dumps: ${DELETED} eliminado(s) (> ${RETENTION_DAYS} días)"

# Sync remoto (solo si REMOTE_SYNC_ENABLED=1 y REMOTE_HOST definido — típ. AGG .210)
if [[ "${REMOTE_SYNC_ENABLED}" == "1" && -n "${REMOTE_HOST}" ]]; then
    SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15)
    [[ -f "${REMOTE_SSH_KEY}" ]] && SSH_OPTS+=(-i "${REMOTE_SSH_KEY}")
    RSYNC_SSH="ssh ${SSH_OPTS[*]}"
    REMOTE="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/"
    REMOTE_BINLOG="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/binlog/"

    sync_with_rsync_or_scp() {
        local label="$1"
        local remote_path="$2"
        shift 2
        if command -v rsync >/dev/null 2>&1 \
            && rsync -az -e "${RSYNC_SSH}" "$@" "${remote_path}" 2>/dev/null; then
            log "Sync ${label} OK (rsync) -> ${REMOTE_HOST}"
            return 0
        fi
        if scp "${SSH_OPTS[@]}" "$@" "${remote_path}" 2>/dev/null; then
            log "Sync ${label} OK (scp) -> ${REMOTE_HOST}"
            return 0
        fi
        log_warn "sync ${label} falló hacia ${REMOTE_HOST} (rsync y scp)"
        return 1
    }

    if ssh "${SSH_OPTS[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p '${REMOTE_DIR}/binlog'"; then
        sync_with_rsync_or_scp "dump" "${REMOTE}" "${OUTPUT}" || true

        if [[ "${REMOTE_SYNC_BINLOG_FILES}" == "1" ]] && [[ -d "${BINLOG_DIR}" ]] && compgen -G "${BINLOG_DIR}/binlog.[0-9]*" >/dev/null; then
            sync_with_rsync_or_scp "binlog files" "${REMOTE_BINLOG}" "${BINLOG_DIR}"/binlog.[0-9]* || true
        fi

        if compgen -G "${BINLOG_DIR}/binlog_snapshot_"*.txt >/dev/null; then
            sync_with_rsync_or_scp "binlog snapshots" "${REMOTE_BINLOG}" "${BINLOG_DIR}"/binlog_snapshot_*.txt || true
        fi
    else
        log_warn "SSH a ${REMOTE_HOST} falló — ejecutar una vez: ${SCRIPT_DIR}/setup-ssh-replica.sh"
    fi
fi

if [[ "${FAILED}" -ne 0 ]]; then
    exit 1
fi

log "Fin"
