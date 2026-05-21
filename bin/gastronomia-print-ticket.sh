#!/usr/bin/env bash
# Envía un ticket ESC/POS (binario) a impresora térmica en red (puerto raw, habitual 9100).
# Uso en tabla salida.comando:
#   /var/www/html/anitaERP/bin/gastronomia-print-ticket.sh "%s" IP_IMPRESORA [PUERTO]
set -euo pipefail

FILE="${1:?falta ruta del archivo ticket}"
HOST="${2:?falta IP/host de la impresora}"
PORT="${3:-9100}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

if command -v nc >/dev/null 2>&1; then
  nc -w 10 "$HOST" "$PORT" < "$FILE"
elif command -v bash >/dev/null 2>&1; then
  exec 3<>/dev/tcp/"$HOST"/"$PORT"
  cat "$FILE" >&3
  exec 3>&-
else
  echo "Se requiere nc o bash con /dev/tcp para imprimir por red." >&2
  exit 1
fi
