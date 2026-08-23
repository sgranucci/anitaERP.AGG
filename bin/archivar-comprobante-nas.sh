#!/usr/bin/env bash
# Copia un PDF al NAS. Destino completo en IMPRESION_NAS_DESTINO.
# Uso en salida.comando:
#   /var/www/html/anitaERP/bin/archivar-comprobante-nas.sh "%s"
set -euo pipefail

FILE="${1:?falta ruta del PDF}"
DEST="${IMPRESION_NAS_DESTINO:?falta IMPRESION_NAS_DESTINO}"
RAIZ="${IMPRESION_NAS_RAIZ:-/NAS}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

if [[ "$DEST" != "$RAIZ"/* ]]; then
  echo "Destino fuera de $RAIZ: $DEST" >&2
  exit 1
fi

if ! findmnt -n -t nfs,nfs4 --target "$RAIZ" >/dev/null 2>&1; then
  if ! grep -q " ${RAIZ} nfs" /proc/mounts && ! grep -q " ${RAIZ} nfs4" /proc/mounts; then
    echo "NAS no montado en $RAIZ" >&2
    exit 2
  fi
fi

DIR="$(dirname "$DEST")"
mkdir -p "$DIR"
cp -f "$FILE" "$DEST"
echo "OK $DEST"
