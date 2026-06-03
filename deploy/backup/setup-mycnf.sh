#!/bin/bash
# Crea ~/.my.cnf para backup-db.sh leyendo DB_* del .env de Laravel.
# Ejecutar una vez como el usuario del cron (sergio en .210):
#   chmod +x deploy/backup/setup-mycnf.sh && ./deploy/backup/setup-mycnf.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../../.env"
TARGET="${HOME}/.my.cnf"

read_env() {
    local key="$1"
    local default="${2:-}"
    if [[ ! -f "${ENV_FILE}" ]]; then
        echo "${default}"
        return
    fi
    local line
    line="$(grep -E "^${key}=" "${ENV_FILE}" | tail -1 || true)"
    if [[ -z "${line}" ]]; then
        echo "${default}"
        return
    fi
    local value="${line#*=}"
    value="${value%$'\r'}"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"
    echo "${value}"
}

DB_USER="$(read_env DB_USERNAME root)"
DB_PASS="$(read_env DB_PASSWORD '')"
DB_HOST="$(read_env DB_HOST 127.0.0.1)"
DB_PORT="$(read_env DB_PORT 3306)"

if [[ -z "${DB_PASS}" ]]; then
    echo "ERROR: DB_PASSWORD vacío en ${ENV_FILE}" >&2
    exit 1
fi

if [[ -f "${TARGET}" ]]; then
    cp "${TARGET}" "${TARGET}.bak.$(date +%Y%m%d_%H%M%S)"
    echo "Respaldo del .my.cnf anterior guardado."
fi

umask 077
cat > "${TARGET}" <<EOF
# Generado por deploy/backup/setup-mycnf.sh — no versionar
[client]
user=${DB_USER}
password=${DB_PASS}
host=${DB_HOST}
port=${DB_PORT}

[mysqldump]
single-transaction
quick
routines
triggers
events
EOF

chmod 600 "${TARGET}"
echo "OK: ${TARGET} creado (chmod 600)"

if mysql -e "SELECT 1" >/dev/null 2>&1; then
    echo "Conexión MySQL: OK"
else
    echo "WARN: mysql -e 'SELECT 1' falló — revisar credenciales en .env" >&2
    exit 1
fi
