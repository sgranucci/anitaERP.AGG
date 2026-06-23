#!/usr/bin/env bash
# Envía ZPL crudo a impresora Zebra en red (puerto 9100) o vía CUPS si no hay socket directo.
# Uso en tabla salida.comando:
#   /var/www/html/anitaERP/bin/imprimir-etiqueta-zebra.sh "%s" imp-labo2
#   /var/www/html/anitaERP/bin/imprimir-etiqueta-zebra.sh "%s" NOMBRE_COLA_CUPS
set -euo pipefail

FILE="${1:?falta ruta del archivo ZPL}"
TARGET="${2:?falta host de impresora o cola CUPS}"
PORT="${IMPRESION_ETIQUETA_PORT:-9100}"
TIMEOUT="${IMPRESION_ETIQUETA_TIMEOUT:-60}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

enviar_por_socket() {
  local host="$1"
  local socket_timeout="${IMPRESION_ETIQUETA_SOCKET_TIMEOUT:-20}"

  # bash /dev/tcp cierra al terminar cat; nc suele esperar idle mientras la Zebra mantiene el socket.
  if command -v timeout >/dev/null 2>&1 && command -v bash >/dev/null 2>&1; then
    timeout "$socket_timeout" bash -c 'exec 3<>"/dev/tcp/$0/$1" && cat "$2" >&3 && exec 3>&-' "$host" "$PORT" "$FILE"
    return 0
  fi
  if command -v bash >/dev/null 2>&1; then
    exec 3<>/dev/tcp/"$host"/"$PORT"
    cat "$FILE" >&3
    exec 3>&-
    return 0
  fi
  if command -v nc >/dev/null 2>&1; then
    nc -w 3 "$host" "$PORT" < "$FILE"
    return 0
  fi
  echo "Se requiere bash con /dev/tcp o nc para imprimir por red." >&2
  return 1
}

host_socket="$(getent hosts "$TARGET" 2>/dev/null | awk '{print $1; exit}')"
if [[ -n "$host_socket" ]]; then
  if timeout 3 bash -c "echo >/dev/tcp/${host_socket}/${PORT}" 2>/dev/null; then
    enviar_por_socket "$TARGET"
    exit 0
  fi
  echo "Aviso: «${TARGET}» (${host_socket}) no acepta socket ${PORT}; intentando CUPS…" >&2
fi

if ! command -v lp >/dev/null 2>&1; then
  echo "Comando lp no disponible (CUPS no instalado)." >&2
  exit 1
fi

if ! lpstat -p "$TARGET" >/dev/null 2>&1; then
  echo "La cola de impresión «${TARGET}» no existe o no es accesible." >&2
  exit 1
fi

STATE="$(lpstat -p "$TARGET" 2>&1 || true)"
if echo "$STATE" | grep -qiE 'disabled|rejecting|paused'; then
  echo "La impresora «${TARGET}» no acepta trabajos: ${STATE}" >&2
  exit 1
fi

LP_OUT="$(lp -d "$TARGET" -o raw "$FILE" 2>&1)" || {
  echo "Error al enviar ZPL a la cola «${TARGET}»: ${LP_OUT}" >&2
  exit 1
}

JOB_ID="$(echo "$LP_OUT" | sed -n 's/^request id is \([^ ]*\).*/\1/p')"
if [[ -z "$JOB_ID" ]]; then
  exit 0
fi

ELAPSED=0
while (( ELAPSED < TIMEOUT )); do
  if ! lpstat -W not-completed -o "$JOB_ID" 2>/dev/null | grep -q "$JOB_ID"; then
    exit 0
  fi
  sleep 1
  ELAPSED=$((ELAPSED + 1))
done

echo "Timeout (${TIMEOUT}s): el trabajo «${JOB_ID}» no terminó en la cola «${TARGET}»." >&2
cancel -a "$JOB_ID" 2>/dev/null || true
exit 1
