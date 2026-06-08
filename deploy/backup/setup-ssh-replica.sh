#!/bin/bash
# Configura acceso SSH sin contraseña desde .210 hacia .211 (réplica) para rsync de backups.
# Ejecutar UNA VEZ de forma interactiva: ./setup-ssh-replica.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=backup.conf
source "${SCRIPT_DIR}/backup.conf"
[[ -f "${SCRIPT_DIR}/backup.local.conf" ]] && source "${SCRIPT_DIR}/backup.local.conf"

if [[ -z "${REMOTE_HOST}" || -z "${REMOTE_USER}" ]]; then
    echo "ERROR: REMOTE_HOST / REMOTE_USER vacíos." >&2
    echo "Crear ${SCRIPT_DIR}/backup.local.conf (ver backup.local.conf.example, bloque AGG)." >&2
    exit 1
fi

KEY="${REMOTE_SSH_KEY:-${HOME}/.ssh/id_rsa}"
PUB="${KEY}.pub"

if [[ ! -f "${PUB}" ]]; then
    echo "Generando clave SSH..."
    ssh-keygen -t rsa -b 4096 -f "${KEY}" -N ""
fi

echo "Copiando clave pública a ${REMOTE_USER}@${REMOTE_HOST}..."
echo "(se pedirá la contraseña Linux de sergio en el .211)"
ssh-copy-id -i "${PUB}" -o StrictHostKeyChecking=accept-new "${REMOTE_USER}@${REMOTE_HOST}"

echo "Probando conexión..."
ssh -i "${KEY}" -o BatchMode=yes "${REMOTE_USER}@${REMOTE_HOST}" \
    "mkdir -p '${REMOTE_DIR}/binlog' && echo 'SSH OK — directorio backup listo'"

echo "Listo. El cron de backup-db.sh podrá sincronizar dumps al .211."
