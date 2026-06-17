#!/usr/bin/env bash
# Envía un PDF a impresora CUPS con validación previa y espera del trabajo.
# Uso en tabla salida.comando:
#   /var/www/html/anitaERP/bin/imprimir-pedido.sh "%s" NOMBRE_COLA_CUPS
set -euo pipefail

FILE="${1:?falta ruta del PDF}"
COLA="${2:?falta nombre de cola CUPS}"

TIMEOUT="${IMPRESION_PEDIDO_TIMEOUT:-60}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

if ! command -v lp >/dev/null 2>&1; then
  echo "Comando lp no disponible (CUPS no instalado)." >&2
  exit 1
fi

if ! lpstat -p "$COLA" >/dev/null 2>&1; then
  echo "La cola de impresión «${COLA}» no existe o no es accesible." >&2
  exit 1
fi

STATE="$(lpstat -p "$COLA" 2>&1 || true)"
if echo "$STATE" | grep -qiE 'disabled|rejecting|paused'; then
  echo "La impresora «${COLA}» no acepta trabajos: ${STATE}" >&2
  exit 1
fi

LP_OUT="$(lp -d "$COLA" -o fit-to-page "$FILE" 2>&1)" || {
  echo "Error al enviar a la cola «${COLA}»: ${LP_OUT}" >&2
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

echo "Timeout (${TIMEOUT}s): el trabajo «${JOB_ID}» no terminó en la cola «${COLA}»." >&2
cancel -a "$JOB_ID" 2>/dev/null || true
exit 1
