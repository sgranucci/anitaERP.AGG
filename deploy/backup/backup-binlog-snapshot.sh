#!/bin/bash
# Guarda posición y listado de binlogs (referencia PITR). No copia archivos binlog
# (requieren lectura de /var/lib/mysql → ver backup-binlog-copy-root.sh + sudoers).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

BINLOG_DIR="${BACKUP_DIR}/binlog"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
SNAPSHOT="${BINLOG_DIR}/binlog_snapshot_${TIMESTAMP}.txt"

mkdir -p "${BINLOG_DIR}"

{
    echo "# Snapshot binlog ${TIMESTAMP}"
    echo "# Servidor: $(hostname)"
    date -Iseconds
    echo ""
    echo "=== SHOW BINARY LOG STATUS ==="
    mysql -e "SHOW BINARY LOG STATUS;"
    echo ""
    echo "=== SHOW BINARY LOGS ==="
    mysql -e "SHOW BINARY LOGS;"
} > "${SNAPSHOT}"

# Retención snapshots de índice
find "${BINLOG_DIR}" -maxdepth 1 -name 'binlog_snapshot_*.txt' -mtime +"${BINLOG_RETENTION_DAYS}" -delete 2>/dev/null || true

echo "${SNAPSHOT}"
