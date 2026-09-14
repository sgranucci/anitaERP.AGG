#!/usr/bin/env bash
# Imprime un PDF en láser Ferli (HP JetDirect puerto 9100).
# Uso en tabla salida.comando (un solo %s = ruta del PDF):
#   /var/www/html/anitaERP/bin/imprimir-pdf-laser.sh "%s" 160.132.0.201
#   /var/www/html/anitaERP/bin/imprimir-pdf-laser.sh "%s" 160.132.0.200
#
# Destinos conocidos:
#   160.132.0.201 | P1     → Monica (pserver)
#   160.132.0.200 | hp1300 → Laura
#
# Preferencia: envío directo TCP/9100 (sin SSH; usable por www-data).
# Respaldo SSH (opcional): IMPRESION_PDF_FORCE_SSH=1
#   IMPRESION_PDF_SSH_HOST  (default sergio@160.132.0.254)
#   IMPRESION_PDF_SSH_KEY   (default /etc/id_rsa si existe)
set -euo pipefail

FILE="${1:?falta ruta del PDF}"
DEST="${2:?falta IP o cola (ej. 160.132.0.200 o P1)}"
PORT="${IMPRESION_PDF_PORT:-9100}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

case "$DEST" in
  160.132.0.201|pserver|P1|p1)
    IP="160.132.0.201"
    COLA="P1"
    ;;
  160.132.0.200|hp1300|HP1300)
    IP="160.132.0.200"
    COLA="hp1300"
    ;;
  *)
    if [[ "$DEST" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      IP="$DEST"
      COLA=""
    else
      IP=""
      COLA="$DEST"
    fi
    ;;
esac

enviar_jetdirect() {
  local host="$1"
  if ! command -v nc >/dev/null 2>&1; then
    echo "Comando nc no disponible para JetDirect." >&2
    return 1
  fi
  # HP LaserJet P3010 Series acepta PDF directo en 9100.
  if ! nc -z -w 3 "$host" "$PORT" >/dev/null 2>&1; then
    echo "Impresora ${host}:${PORT} no responde." >&2
    return 1
  fi
  # -N cierra el socket al terminar el PDF (si no, la impresora deja nc colgado).
  nc -N -w 15 "$host" "$PORT" < "$FILE"
}

enviar_ssh_cups() {
  local cola="$1"
  if [[ -z "$cola" ]]; then
    echo "Sin cola CUPS para respaldo SSH." >&2
    return 1
  fi
  if ! command -v ssh >/dev/null 2>&1 || ! command -v scp >/dev/null 2>&1; then
    echo "ssh/scp no disponibles." >&2
    return 1
  fi

  local remote="${IMPRESION_PDF_SSH_HOST:-sergio@160.132.0.254}"
  local tmp_remote="anita_pdf_$$.pdf"
  local known_hosts="${IMPRESION_PDF_KNOWN_HOSTS:-/dev/null}"
  local -a ssh_opts=(
    -o BatchMode=yes
    -o StrictHostKeyChecking=no
    -o UserKnownHostsFile="$known_hosts"
    -o GlobalKnownHostsFile=/dev/null
    -o ConnectTimeout=10
    -o HostKeyAlgorithms=+ssh-rsa
    -o PubkeyAcceptedAlgorithms=+ssh-rsa
    -o KexAlgorithms=+diffie-hellman-group1-sha1
  )

  local key="${IMPRESION_PDF_SSH_KEY:-}"
  if [[ -z "$key" && -f /etc/id_rsa ]]; then
    key=/etc/id_rsa
  fi
  if [[ -n "$key" ]]; then
    if [[ ! -f "$key" ]]; then
      echo "Clave SSH inexistente: $key" >&2
      return 1
    fi
    ssh_opts+=(-i "$key")
  fi

  scp "${ssh_opts[@]}" "$FILE" "${remote}:/tmp/${tmp_remote}" >/dev/null
  ssh "${ssh_opts[@]}" "$remote" \
    "lp -d $(printf '%q' "$cola") -o fit-to-page /tmp/${tmp_remote}; EC=\$?; rm -f /tmp/${tmp_remote}; exit \$EC"
}

FORCE_SSH="${IMPRESION_PDF_FORCE_SSH:-0}"

if [[ "$FORCE_SSH" == "1" ]]; then
  if [[ -z "$COLA" ]]; then
    echo "IMPRESION_PDF_FORCE_SSH=1 requiere cola CUPS (P1 / hp1300)." >&2
    exit 1
  fi
  enviar_ssh_cups "$COLA"
  echo "PDF enviado por SSH a cola «${COLA}»."
  exit 0
fi

if [[ -n "$IP" ]]; then
  if enviar_jetdirect "$IP"; then
    echo "PDF enviado a ${IP}:${PORT} (JetDirect)."
    exit 0
  fi
  echo "JetDirect falló; intento respaldo SSH…" >&2
fi

if [[ -n "$COLA" ]]; then
  enviar_ssh_cups "$COLA"
  echo "PDF enviado por SSH a cola «${COLA}»."
  exit 0
fi

echo "No se pudo resolver destino de impresión: $DEST" >&2
exit 1
