#!/usr/bin/env bash
# Crown: prepara /srv/scan para UIF y (opcional) trae adjuntos desde ac-serverweb.
# Ejecutar en server1ng como root o con sudo real (fuera del agente Cursor).
#
# Uso:
#   sudo bash scripts/uif-crown-bootstrap-scan.sh
#   sudo bash scripts/uif-crown-bootstrap-scan.sh --rsync-from=sergio@192.168.1.4
#
set -euo pipefail

SCAN_ROOT="${SCAN_ROOT:-/srv/scan}"
REMOTE_HOST="${REMOTE_HOST:-}"
OWNER="${SCAN_OWNER:-nobody}"
GROUP="${SCAN_GROUP:-nogroup}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --rsync-from=*)
      REMOTE_HOST="${1#*=}"
      shift
      ;;
    --owner=*)
      OWNER="${1#*=}"
      shift
      ;;
    --group=*)
      GROUP="${1#*=}"
      shift
      ;;
    *)
      echo "Opción desconocida: $1" >&2
      exit 1
      ;;
  esac
done

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Correr como root (sudo)." >&2
  exit 1
fi

mkdir -p \
  "${SCAN_ROOT}/uif/archivos/clientes" \
  "${SCAN_ROOT}/uif/archivos/premios" \
  "${SCAN_ROOT}/tesoreria/fotos_clientes" \
  "${SCAN_ROOT}/tesoreria/dni_uif"

chown -R "${OWNER}:${GROUP}" "${SCAN_ROOT}/uif" "${SCAN_ROOT}/tesoreria"
chmod -R ug+rwX "${SCAN_ROOT}/uif" "${SCAN_ROOT}/tesoreria"

if [[ ! -e /scan ]]; then
  ln -s "${SCAN_ROOT}" /scan
  echo "Creado symlink /scan → ${SCAN_ROOT}"
elif [[ -L /scan ]]; then
  echo "Symlink /scan ya existe → $(readlink /scan)"
else
  echo "AVISO: /scan existe y no es symlink; las rutas del .env Crown usan ${SCAN_ROOT}"
fi

echo "Árbol UIF:"
find "${SCAN_ROOT}/uif" "${SCAN_ROOT}/tesoreria" -maxdepth 3 -type d | sort

if [[ -n "${REMOTE_HOST}" ]]; then
  echo "Rsync adjuntos desde ${REMOTE_HOST}…"
  # ac-serverweb (.4) solo ofrece KEX SHA1 legacy; OpenSSH moderno los rechaza sin esto.
  export RSYNC_RSH="${RSYNC_RSH:-ssh -o KexAlgorithms=+diffie-hellman-group14-sha1,diffie-hellman-group1-sha1,diffie-hellman-group-exchange-sha1 -o HostKeyAlgorithms=+ssh-rsa -o PubkeyAcceptedAlgorithms=+ssh-rsa -o StrictHostKeyChecking=accept-new}"
  echo "RSYNC_RSH=${RSYNC_RSH}"

  remote_path_exists() {
    local remote_path="$1"
    # shellcheck disable=SC2029
    ${RSYNC_RSH} "${REMOTE_HOST}" "test -d '${remote_path}'" >/dev/null 2>&1
  }

  pick_remote_dir() {
    local label="$1"
    shift
    local candidate
    for candidate in "$@"; do
      if [[ -z "${candidate}" ]]; then
        continue
      fi
      echo "  Probando ${label}: ${candidate}" >&2
      if remote_path_exists "${candidate}"; then
        echo "  → encontrada: ${candidate}" >&2
        printf '%s' "${candidate}"
        return 0
      fi
    done
    echo "  → NO encontrada ninguna ruta para ${label}" >&2
    return 1
  }

  sync_one() {
    local label="$1"
    local dest="$2"
    shift 2
    local src
    if ! src="$(pick_remote_dir "${label}" "$@")"; then
      return 0
    fi
    echo "Rsync ${label}: ${REMOTE_HOST}:${src}/ → ${dest}/"
    rsync -av --progress "${REMOTE_HOST}:${src}/" "${dest}/" || true
  }

  # Override opcionales: REMOTE_UIF_CLIENTES / REMOTE_UIF_PREMIOS / REMOTE_FOTOS / REMOTE_DNI
  # En Crown .4 el docroot a veces es htdocs y a veces http; uif/mod_fotos/dni pueden no existir.
  echo "Descubriendo carpetas remotas (pedir password SSH si hace falta)…"
  sync_one "adjuntos clientes" "${SCAN_ROOT}/uif/archivos/clientes" \
    "${REMOTE_UIF_CLIENTES:-}" \
    "/usr2/www/htdocs/uif/archivos/clientes" \
    "/usr2/www/http/uif/archivos/clientes" \
    "/usr2/www/htdocs/archivos/clientes" \
    "/usr2/casino/uif/archivos/clientes"

  sync_one "adjuntos premios" "${SCAN_ROOT}/uif/archivos/premios" \
    "${REMOTE_UIF_PREMIOS:-}" \
    "/usr2/www/htdocs/uif/archivos/premios" \
    "/usr2/www/http/uif/archivos/premios" \
    "/usr2/www/htdocs/archivos/premios" \
    "/usr2/casino/uif/archivos/premios"

  sync_one "fotos premios/clientes" "${SCAN_ROOT}/tesoreria/fotos_clientes" \
    "${REMOTE_FOTOS:-}" \
    "/usr2/www/htdocs/mod_fotos/fotos_clientes" \
    "/usr2/www/http/mod_fotos/fotos_clientes" \
    "/usr2/www/htdocs/fotos_clientes" \
    "/usr2/casino/mod_fotos/fotos_clientes"

  sync_one "DNI uif" "${SCAN_ROOT}/tesoreria/dni_uif" \
    "${REMOTE_DNI:-}" \
    "/usr2/www/htdocs/dni_uif" \
    "/usr2/www/http/dni_uif" \
    "/usr2/casino/dni_uif"

  chown -R "${OWNER}:${GROUP}" "${SCAN_ROOT}/uif" "${SCAN_ROOT}/tesoreria"
fi

echo
echo "Listo."
echo "Si ninguna carpeta remota existió, en el .4 buscá dónde están:"
echo "  ssh … sergio@192.168.1.4 \"find /usr2/www /usr2/casino -type d \\( -name dni_uif -o -name fotos_clientes -o -path '*/uif/archivos/*' \\) 2>/dev/null\""
echo "Luego reintentá con overrides, ej.:"
echo "  sudo REMOTE_UIF_CLIENTES=/ruta/real REMOTE_DNI=/ruta/real bash scripts/uif-crown-bootstrap-scan.sh --rsync-from=sergio@192.168.1.4"
echo "Tras copiar archivos: php artisan cliente-uif:sincronizar-anita-bulk --origen=crown --solo-persistir --force"
