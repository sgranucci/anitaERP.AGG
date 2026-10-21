#!/bin/bash
# Copia incremental de binlogs MariaDB, cada minuto.
# El archivo activo se manda por cola: solo los bytes nuevos.
#
# Copia local en el disco de scan (/srv/scan), aparte del binlog vivo.
# Destino NAS (Crown): el export /server1-backups no incluye esta IP.
# Se escribe por SSH en ac-server1, que ya tiene el NAS montado en NAS_DIR.
# Destino gateway (alternativo): //GATEWAY_HOST/GATEWAY_SHARE/GATEWAY_DIR
# Credenciales CIFS: GATEWAY_SMB_CREDENTIALS (username/password/domain), modo 600.
#
# Restaurar hasta un minuto:
#   mysqlbinlog --stop-datetime="AAAA-MM-DD HH:MM:SS" mysql-bin.00000N | mysql anitaERP
# La posición ya copiada está en binlog_position.txt del mismo directorio.
#
# Cron: /etc/cron.d/anitaERP-binlog-ship (root, flock).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

SCAN_BINLOG_DIR="${SCAN_BINLOG_DIR:-}"
NAS_SYNC_ENABLED="${NAS_SYNC_ENABLED:-0}"
NAS_SSH_HOST="${NAS_SSH_HOST:-192.168.1.2}"
NAS_SSH_USER="${NAS_SSH_USER:-sergio}"
NAS_SSH_KEY="${NAS_SSH_KEY:-/home/sergio/.ssh/id_rsa_ac-server1}"
NAS_DIR="${NAS_DIR:-/nas-backups/anitaERP/server1ng/binlog}"
GATEWAY_SYNC_ENABLED="${GATEWAY_SYNC_ENABLED:-0}"
GATEWAY_HOST="${GATEWAY_HOST:-192.168.1.1}"
GATEWAY_SHARE="${GATEWAY_SHARE:-backup}"
GATEWAY_DIR="${GATEWAY_DIR:-anitaERP/server1ng/binlog}"
GATEWAY_SMB_CREDENTIALS="${GATEWAY_SMB_CREDENTIALS:-/etc/anitaERP/backup-gateway.smb}"
GATEWAY_MOUNT="${GATEWAY_MOUNT:-/mnt/anitaERP-binlog-gateway}"
LOG_FILE="${BINLOG_SHIP_LOG:-/var/log/anitaERP-binlog-ship.log}"
STAMP_WARN="/run/anitaERP-binlog-ship.warn"
DRY=0
[[ "${1:-}" == "--dry-run" ]] && DRY=1

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

warn_throttled() {
    if [[ ! -f "${STAMP_WARN}" ]] || [[ -n "$(find "${STAMP_WARN}" -mmin +60 2>/dev/null)" ]]; then
        log "WARN: $*"
        touch "${STAMP_WARN}" 2>/dev/null || true
    fi
}

mysql_q() {
    mysql --protocol=socket -N "$@"
}

if [[ -z "${SCAN_BINLOG_DIR}" && "${NAS_SYNC_ENABLED}" != "1" && "${GATEWAY_SYNC_ENABLED}" != "1" ]]; then
    [[ "${DRY}" == "1" ]] && log "ningún destino habilitado"
    exit 0
fi

nas_ssh() {
    ssh -i "${NAS_SSH_KEY}" \
        -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new \
        -o User="${NAS_SSH_USER}" -o HostName="${NAS_SSH_HOST}" \
        -o KexAlgorithms=+diffie-hellman-group14-sha1 \
        -o HostKeyAlgorithms=+ssh-rsa -o PubkeyAcceptedAlgorithms=+ssh-rsa \
        -o ServerAliveInterval=30 \
        "${NAS_SSH_HOST}" "$@"
}

BINLOG_BASE="$(mysql_q -e "SHOW VARIABLES LIKE 'log_bin_basename'" | awk '{print $2}')"
if [[ -z "${BINLOG_BASE}" ]]; then
    log "ERROR: log_bin apagado — no hay binlogs para copiar"
    exit 1
fi
BINLOG_SRC="$(dirname "${BINLOG_BASE}")"

mapfile -t ALL_LOGS < <(mysql_q -e "SHOW BINARY LOGS" | awk '{print $1}')
if [[ ${#ALL_LOGS[@]} -eq 0 ]]; then
    log "ERROR: SHOW BINARY LOGS vacío"
    exit 1
fi
ACTIVE="${ALL_LOGS[-1]}"

if [[ "${DRY}" == "1" ]]; then
    [[ -n "${SCAN_BINLOG_DIR}" ]] && log "dry-run copia local ${SCAN_BINLOG_DIR}"
    if [[ "${NAS_SYNC_ENABLED}" == "1" ]]; then
        log "dry-run destino ${NAS_SSH_USER}@${NAS_SSH_HOST}:${NAS_DIR}"
    elif [[ "${GATEWAY_SYNC_ENABLED}" == "1" ]]; then
        log "dry-run destino //${GATEWAY_HOST}/${GATEWAY_SHARE}/${GATEWAY_DIR}"
    fi
    total=0
    for logfile in "${ALL_LOGS[@]}"; do
        src="${BINLOG_SRC}/${logfile}"
        if [[ -f "${src}" ]]; then
            bytes=$(stat -c '%s' "${src}")
            total=$((total + bytes))
            log "  ${logfile} ${bytes} bytes"
        else
            log "  WARN no existe ${src}"
        fi
    done
    log "total ${total} bytes (el activo ${ACTIVE} después solo suma la cola)"
    mysql_q -e "SHOW MASTER STATUS"
    exit 0
fi

ship_local() {
    local src="$1" dest="$2"
    local src_size dest_size
    [[ -f "${src}" ]] || return 0
    src_size="$(stat -c '%s' "${src}")"
    if [[ ! -f "${dest}" ]]; then
        dd if="${src}" of="${dest}" bs=8M status=none iflag=fullblock,count_bytes count="${src_size}"
        echo "${src_size}"
        return 0
    fi
    dest_size="$(stat -c '%s' "${dest}")"
    if [[ "${dest_size}" -eq "${src_size}" ]]; then
        echo 0
        return 0
    fi
    if [[ "${dest_size}" -gt "${src_size}" ]]; then
        rm -f "${dest}"
        dd if="${src}" of="${dest}" bs=8M status=none iflag=fullblock,count_bytes count="${src_size}"
        echo "${src_size}"
        return 0
    fi
    dd if="${src}" of="${dest}" bs=8M status=none conv=notrunc \
        iflag=fullblock,skip_bytes,count_bytes oflag=seek_bytes \
        skip="${dest_size}" seek="${dest_size}" count="$((src_size - dest_size))"
    echo $((src_size - dest_size))
}

if [[ -n "${SCAN_BINLOG_DIR}" ]]; then
    mkdir -p "${SCAN_BINLOG_DIR}"
    COPIADOS=0
    for logfile in "${ALL_LOGS[@]}"; do
        src="${BINLOG_SRC}/${logfile}"
        added="$(ship_local "${src}" "${SCAN_BINLOG_DIR}/${logfile}")"
        [[ -f "${src}.idx" ]] && ship_local "${src}.idx" "${SCAN_BINLOG_DIR}/${logfile}.idx" >/dev/null
        if [[ "${added:-0}" -gt 0 ]]; then
            COPIADOS=$((COPIADOS + 1))
            log "Copia scan ${logfile}: +${added} bytes"
        fi
    done
    ship_local "${BINLOG_SRC}/${ACTIVE}" "${SCAN_BINLOG_DIR}/${ACTIVE}" >/dev/null
    [[ -f "${BINLOG_SRC}/mysql-bin.index" ]] && cp -f "${BINLOG_SRC}/mysql-bin.index" "${SCAN_BINLOG_DIR}/mysql-bin.index"
    {
        echo "# Posición ya presente en este directorio. Servidor $(hostname) $(date -Iseconds)"
        mysql --protocol=socket -e "SHOW MASTER STATUS\G"
    } > "${SCAN_BINLOG_DIR}/binlog_position.txt"
    chown root:sergio "${SCAN_BINLOG_DIR}" "${SCAN_BINLOG_DIR}"/mysql-bin.* "${SCAN_BINLOG_DIR}/binlog_position.txt" 2>/dev/null || true
    chmod 750 "${SCAN_BINLOG_DIR}" 2>/dev/null || true
    chmod 640 "${SCAN_BINLOG_DIR}"/mysql-bin.* "${SCAN_BINLOG_DIR}/binlog_position.txt" 2>/dev/null || true
    deleted=0
    while IFS= read -r -d '' old; do
        base="$(basename "${old}")"
        [[ "${base}" == "${ACTIVE}" || "${base}" == "${ACTIVE}.idx" ]] && continue
        rm -f "${old}" && deleted=$((deleted + 1))
    done < <(find "${SCAN_BINLOG_DIR}" -maxdepth 1 -type f -name 'mysql-bin.*' -mtime +"${BINLOG_RETENTION_DAYS}" -print0 2>/dev/null || true)
    if [[ "${COPIADOS}" -gt 0 || "${deleted}" -gt 0 ]]; then
        log "Fin scan activos=${#ALL_LOGS[@]} actualizados=${COPIADOS} purgados=${deleted} destino ${SCAN_BINLOG_DIR}"
    fi
fi

if [[ "${NAS_SYNC_ENABLED}" == "1" ]]; then
    nas_ssh "mkdir -p '${NAS_DIR}'"
    remote_size() {
        nas_ssh "if [ -f '$1' ]; then stat -c %s '$1'; else echo 0; fi" | tr -cd '0-9'
    }
    ship_ssh() {
        local src="$1" name="$2"
        local src_size dest_size count
        [[ -f "${src}" ]] || return 0
        src_size="$(stat -c '%s' "${src}")"
        dest_size="$(remote_size "${NAS_DIR}/${name}")"
        dest_size="${dest_size:-0}"
        if [[ "${dest_size}" -eq "${src_size}" ]]; then
            echo 0
            return 0
        fi
        if [[ "${dest_size}" -gt "${src_size}" ]]; then
            nas_ssh "rm -f '${NAS_DIR}/${name}'"
            dest_size=0
        fi
        count=$((src_size - dest_size))
        dd if="${src}" bs=8M status=none iflag=fullblock,skip_bytes,count_bytes \
            skip="${dest_size}" count="${count}" \
            | nas_ssh "cat >> '${NAS_DIR}/${name}'"
        echo "${count}"
    }
    COPIADOS=0
    for logfile in "${ALL_LOGS[@]}"; do
        src="${BINLOG_SRC}/${logfile}"
        added="$(ship_ssh "${src}" "${logfile}")"
        [[ -f "${src}.idx" ]] && ship_ssh "${src}.idx" "${logfile}.idx" >/dev/null
        if [[ "${added:-0}" -gt 0 ]]; then
            COPIADOS=$((COPIADOS + 1))
            log "Enviado ${logfile}: +${added} bytes"
        fi
    done
    ship_ssh "${BINLOG_SRC}/${ACTIVE}" "${ACTIVE}" >/dev/null
    if [[ -f "${BINLOG_SRC}/mysql-bin.index" ]]; then
        nas_ssh "cat > '${NAS_DIR}/mysql-bin.index'" < "${BINLOG_SRC}/mysql-bin.index"
    fi
    {
        echo "# Posición ya presente en este directorio. Servidor $(hostname) $(date -Iseconds)"
        mysql --protocol=socket -e "SHOW MASTER STATUS\G"
    } | nas_ssh "cat > '${NAS_DIR}/binlog_position.txt'"
    deleted="$(nas_ssh "find '${NAS_DIR}' -maxdepth 1 -type f -name 'mysql-bin.*' -mtime +${BINLOG_RETENTION_DAYS} ! -name '${ACTIVE}' ! -name '${ACTIVE}.idx' -print -delete 2>/dev/null | wc -l" | tr -cd '0-9')"
    if [[ "${COPIADOS}" -gt 0 || "${deleted:-0}" -gt 0 ]]; then
        log "Fin activos=${#ALL_LOGS[@]} actualizados=${COPIADOS} purgados=${deleted:-0} (>${BINLOG_RETENTION_DAYS}d) destino ${NAS_SSH_HOST}:${NAS_DIR}"
    fi
    exit 0
fi

if [[ ! -f "${GATEWAY_SMB_CREDENTIALS}" ]]; then
    warn_throttled "falta ${GATEWAY_SMB_CREDENTIALS} — binlogs no salen al gateway"
    exit 0
fi
if ! grep -q '^password=.' "${GATEWAY_SMB_CREDENTIALS}"; then
    warn_throttled "password vacío en ${GATEWAY_SMB_CREDENTIALS}"
    exit 0
fi

mkdir -p "${GATEWAY_MOUNT}"
expected="//${GATEWAY_HOST}/${GATEWAY_SHARE}"
current="$(findmnt -n -o SOURCE --target "${GATEWAY_MOUNT}" 2>/dev/null || true)"
if [[ "${current}" != "${expected}" ]]; then
    if [[ -n "${current}" ]]; then
        log "ERROR: ${GATEWAY_MOUNT} está montado desde ${current}, no desde ${expected}"
        exit 1
    fi
    if ! mount -t cifs "${expected}" "${GATEWAY_MOUNT}" \
        -o "credentials=${GATEWAY_SMB_CREDENTIALS},uid=0,gid=0,file_mode=0640,dir_mode=0750,cache=none,noserverino,vers=3.0"; then
        log "ERROR: no se pudo montar ${expected}"
        exit 1
    fi
    log "Montado ${expected}"
fi

DEST="${GATEWAY_MOUNT}/${GATEWAY_DIR}"
mkdir -p "${DEST}"

ship_file() {
    local src="$1" dest="$2"
    local src_size dest_size
    [[ -f "${src}" ]] || return 0
    src_size="$(stat -c '%s' "${src}")"
    if [[ ! -f "${dest}" ]]; then
        dd if="${src}" of="${dest}" bs=8M status=none iflag=fullblock,count_bytes count="${src_size}"
        return 0
    fi
    dest_size="$(stat -c '%s' "${dest}")"
    if [[ "${dest_size}" -eq "${src_size}" ]]; then
        return 0
    fi
    if [[ "${dest_size}" -gt "${src_size}" ]]; then
        rm -f "${dest}"
        dd if="${src}" of="${dest}" bs=8M status=none iflag=fullblock,count_bytes count="${src_size}"
        return 0
    fi
    dd if="${src}" of="${dest}" bs=8M status=none conv=notrunc \
        iflag=fullblock,skip_bytes,count_bytes oflag=seek_bytes \
        skip="${dest_size}" seek="${dest_size}" count="$((src_size - dest_size))"
}

COPIADOS=0
for logfile in "${ALL_LOGS[@]}"; do
    src="${BINLOG_SRC}/${logfile}"
    dest="${DEST}/${logfile}"
    before=0
    [[ -f "${dest}" ]] && before="$(stat -c '%s' "${dest}")"
    ship_file "${src}" "${dest}"
    [[ -f "${src}.idx" ]] && ship_file "${src}.idx" "${dest}.idx"
    after=0
    [[ -f "${dest}" ]] && after="$(stat -c '%s' "${dest}")"
    if [[ "${after}" -gt "${before}" ]]; then
        COPIADOS=$((COPIADOS + 1))
        log "Enviado ${logfile}: +$((after - before)) bytes (total ${after})"
    fi
done

# Segunda pasada del activo: lo que se escribió mientras copiábamos.
ship_file "${BINLOG_SRC}/${ACTIVE}" "${DEST}/${ACTIVE}"
[[ -f "${BINLOG_SRC}/mysql-bin.index" ]] && cp -f "${BINLOG_SRC}/mysql-bin.index" "${DEST}/mysql-bin.index"

{
    echo "# Posición ya presente en este directorio. Servidor $(hostname) $(date -Iseconds)"
    mysql --protocol=socket -e "SHOW MASTER STATUS\G"
} > "${DEST}/binlog_position.txt"

# Retención en el gateway. No borrar el binlog activo.
deleted=0
while IFS= read -r -d '' old; do
    base="$(basename "${old}")"
    [[ "${base}" == "${ACTIVE}" || "${base}" == "${ACTIVE}.idx" ]] && continue
    rm -f "${old}" && deleted=$((deleted + 1))
done < <(find "${DEST}" -maxdepth 1 -type f \( -name 'mysql-bin.[0-9]*' -o -name 'mysql-bin.[0-9]*.idx' \) -mtime +"${BINLOG_RETENTION_DAYS}" -print0 2>/dev/null || true)

if [[ "${COPIADOS}" -gt 0 || "${deleted}" -gt 0 ]]; then
    log "Fin activos=${#ALL_LOGS[@]} actualizados=${COPIADOS} purgados=${deleted} (>${BINLOG_RETENTION_DAYS}d) destino ${expected}/${GATEWAY_DIR}"
fi
