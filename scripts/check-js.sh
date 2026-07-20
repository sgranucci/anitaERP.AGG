#!/usr/bin/env bash
# Chequea la sintaxis de los .js del ERP con `node --check` (sin ejecutar nada).
# Node se usa solo como herramienta de desarrollo: no cambia el runtime ni los assets servidos.
#
# Uso:
#   ./scripts/check-js.sh                 # revisa public/assets/pages/scripts/sueldos
#   ./scripts/check-js.sh --all           # revisa todo public/assets/pages/scripts
#   ./scripts/check-js.sh <ruta>          # revisa una carpeta o archivo .js puntual
#
# Salida: lista los archivos con error de sintaxis y termina con código != 0 si hay fallos.

set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

die() {
  echo "check-js: $*" >&2
  exit 1
}

command -v node >/dev/null 2>&1 || die "node no está instalado. Instalar con: sudo apt install -y nodejs"

BASE_SCRIPTS="public/assets/pages/scripts"

case "${1:-}" in
  --all)
    TARGET="$BASE_SCRIPTS"
    ;;
  "")
    TARGET="$BASE_SCRIPTS/sueldos"
    ;;
  *)
    TARGET="$1"
    ;;
esac

[[ -e "$TARGET" ]] || die "No existe la ruta: $TARGET"

echo "check-js: node $(node --version) — revisando $TARGET"

TOTAL=0
FALLIDOS=0
ERRORES=()

revisar_archivo() {
  local archivo="$1"
  TOTAL=$((TOTAL + 1))
  if ! salida="$(node --check "$archivo" 2>&1)"; then
    FALLIDOS=$((FALLIDOS + 1))
    ERRORES+=("$archivo")
    echo "  ✗ $archivo"
    echo "$salida" | sed 's/^/      /'
  fi
}

if [[ -f "$TARGET" ]]; then
  revisar_archivo "$TARGET"
else
  while IFS= read -r -d '' archivo; do
    revisar_archivo "$archivo"
  done < <(find "$TARGET" -type f -name '*.js' -print0 | sort -z)
fi

echo "check-js: $TOTAL archivo(s) revisado(s), $FALLIDOS con error(es)."

if [[ "$FALLIDOS" -gt 0 ]]; then
  echo "check-js: revisá los archivos listados arriba." >&2
  exit 1
fi

echo "check-js: sintaxis OK."
