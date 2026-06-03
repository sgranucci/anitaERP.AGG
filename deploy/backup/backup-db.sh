#!/bin/bash
# Backup anitaERP: mysqldump + gzip, validación, snapshot binlog, sync a réplica .211.
# Cron sugerido: 0 6,18 * * * (cada 12 h)

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

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
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
    exit 1
fi

log "Inicio backup ${DB_NAME} -> ${OUTPUT}"

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

# Sync a réplica .211
if [[ "${REMOTE_SYNC_ENABLED}" == "1" ]]; then
    SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15)
    [[ -f "${REMOTE_SSH_KEY}" ]] && SSH_OPTS+=(-i "${REMOTE_SSH_KEY}")
    RSYNC_SSH="ssh ${SSH_OPTS[*]}"
    REMOTE="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/"

    if ssh "${SSH_OPTS[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p '${REMOTE_DIR}/binlog'"; then
        if rsync -az -e "${RSYNC_SSH}" "${OUTPUT}" "${REMOTE}"; then
            log "Sync dump OK -> ${REMOTE_HOST}"
        else
            log_warn "rsync dump falló hacia ${REMOTE_HOST}"
        fi
        if [[ -d "${BINLOG_DIR}" ]] && compgen -G "${BINLOG_DIR}/binlog.*" >/dev/null; then
            rsync -az -e "${RSYNC_SSH}" "${BINLOG_DIR}/binlog."* "${REMOTE}binlog/" 2>/dev/null \
                && log "Sync binlog files OK -> ${REMOTE_HOST}" \
                || log_warn "rsync binlog files falló"
        fi
        rsync -az -e "${RSYNC_SSH}" "${BINLOG_DIR}/binlog_snapshot_"*.txt "${REMOTE}binlog/" 2>/dev/null || true
    else
        log_warn "SSH a ${REMOTE_HOST} falló — ejecutar una vez: ${SCRIPT_DIR}/setup-ssh-replica.sh"
    fi
fi

if [[ "${FAILED}" -ne 0 ]]; then
    exit 1
fi

log "Fin"
