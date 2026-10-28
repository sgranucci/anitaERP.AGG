#!/usr/bin/env bash
# Envía un PDF a impresora CUPS con validación previa y espera del trabajo.
# Uso en tabla salida.comando:
#   /var/www/html/anitaERP/bin/imprimir-pedido.sh "%s" NOMBRE_COLA_CUPS
set -euo pipefail

FILE="${1:?falta ruta del PDF}"
COLA="${2:?falta nombre de cola CUPS}"

TIMEOUT="${IMPRESION_PEDIDO_TIMEOUT:-60}"
PDF_ADAPTADO=""

limpiar_pdf_adaptado() {
  if [[ -n "${PDF_ADAPTADO}" && -f "${PDF_ADAPTADO}" ]]; then
    rm -f "${PDF_ADAPTADO}"
  fi
}
trap limpiar_pdf_adaptado EXIT

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

if ! command -v lp >/dev/null 2>&1; then
  echo "Comando lp no disponible (CUPS no instalado)." >&2
  exit 1
fi

# Cola BIE_PS_231 → socket://192.168.59.231:9100. Si CUPS no tiene la cola
# pero el equipo responde en JetDirect, se manda el PDF igual (VersaLink acepta PDF).
enviar_jetdirect_bie() {
  local cola="$1"
  local file="$2"
  local subred="${IMPRESION_BIE_SUBRED:-192.168.59}"
  local octeto host tmp ec

  if [[ ! "$cola" =~ ^BIE_PS_([0-9]+)$ ]]; then
    return 1
  fi
  if ! command -v nc >/dev/null 2>&1; then
    return 1
  fi

  octeto="${BASH_REMATCH[1]}"
  host="${subred}.${octeto}"
  if ! nc -z -w 3 "$host" 9100 >/dev/null 2>&1; then
    return 1
  fi

  tmp="$(mktemp)"
  {
    printf '\033%%-12345X@PJL JOB NAME="anitaERP"\r\n'
    printf '@PJL ENTER LANGUAGE = PDF\r\n'
    cat "$file"
    printf '\r\n\033%%-12345X@PJL EOJ\r\n\033%%-12345X'
  } > "$tmp"

  ec=0
  nc -N -w 60 "$host" 9100 < "$tmp" || ec=$?
  rm -f "$tmp"
  if [[ "$ec" -ne 0 ]]; then
    echo "La cola «${cola}» no está en CUPS y falló el envío a ${host}:9100." >&2
    return 1
  fi

  echo "Cola «${cola}» ausente en CUPS. PDF enviado a ${host}:9100."
  return 0
}

# Xerox B230 acepta el PDF y CUPS da el trabajo por terminado, pero no dibuja
# fuentes CID embebidas (DejaVu del pedido). La factura usa Times y sí sale.
# Se convierten esas fuentes a trazos antes de enviar.
adaptar_pdf_xerox_b230() {
  local info
  info="$(lpoptions -p "$COLA" 2>/dev/null || true)"
  if ! grep -q 'B230' <<<"$info"; then
    return 0
  fi
  if command -v pdffonts >/dev/null 2>&1; then
    if ! pdffonts "$FILE" 2>/dev/null | awk 'NR>2 { print }' | grep -qE 'CID|TrueType'; then
      return 0
    fi
  fi
  if ! command -v gs >/dev/null 2>&1; then
    echo "La impresora «${COLA}» (Xerox B230) no imprime el pedido: faltan las fuentes que esa impresora puede dibujar y no está Ghostscript." >&2
    return 1
  fi

  PDF_ADAPTADO="$(mktemp --suffix=.pdf)"
  if ! gs -dSAFER -dBATCH -dNOPAUSE -dNoOutputFonts -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile="$PDF_ADAPTADO" "$FILE" >/dev/null 2>&1; then
    echo "No se pudo adaptar el PDF del pedido para la Xerox B230 («${COLA}»)." >&2
    return 1
  fi
  if [[ ! -s "$PDF_ADAPTADO" ]]; then
    echo "El PDF adaptado para la Xerox B230 («${COLA}») quedó vacío." >&2
    return 1
  fi
  FILE="$PDF_ADAPTADO"
  return 0
}

if ! lpstat -p "$COLA" >/dev/null 2>&1; then
  if enviar_jetdirect_bie "$COLA" "$FILE"; then
    exit 0
  fi
  echo "La cola de impresión «${COLA}» no existe o no es accesible." >&2
  exit 1
fi

STATE="$(lpstat -p "$COLA" 2>&1 || true)"
if echo "$STATE" | grep -qiE 'disabled|rejecting|paused'; then
  echo "La impresora «${COLA}» no acepta trabajos: ${STATE}" >&2
  exit 1
fi

adaptar_pdf_xerox_b230

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
