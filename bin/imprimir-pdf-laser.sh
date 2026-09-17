#!/usr/bin/env bash
# Imprime un PDF en láser Ferli (HP JetDirect puerto 9100).
# Uso en tabla salida.comando (un solo %s = ruta del PDF):
#   /var/www/html/anitaERP/bin/imprimir-pdf-laser.sh "%s" 160.132.0.201
#   /var/www/html/anitaERP/bin/imprimir-pdf-laser.sh "%s" 160.132.0.200
#
# Destinos conocidos:
#   160.132.0.201 | P1     → Monica (acepta PDF Direct)
#   160.132.0.200 | hp1300 → Laura (HP LaserJet 1300: solo PCL, NO PDF)
#
# Por defecto convierte PDF→PCL con Ghostscript antes de JetDirect
# (impresoras viejas imprimen basura si reciben PDF crudo).
# Excepción: IPs en IMPRESION_PDF_DIRECT_IPS (default Monica).
# Forzar conversión siempre: IMPRESION_PDF_FORCE_PCL=1
# Forzar PDF crudo:        IMPRESION_PDF_FORCE_DIRECT=1
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

# IPs que aceptan PDF nativo en 9100 (HP PDF Direct Print).
# Laura (.200 / hp1300) NO está acá: es LaserJet 1300 (PCL 5e).
DIRECT_IPS="${IMPRESION_PDF_DIRECT_IPS:-160.132.0.201}"

debe_enviar_pdf_directo() {
  local host="$1"
  if [[ "${IMPRESION_PDF_FORCE_PCL:-0}" == "1" ]]; then
    return 1
  fi
  if [[ "${IMPRESION_PDF_FORCE_DIRECT:-0}" == "1" ]]; then
    return 0
  fi
  local ip
  IFS=',' read -r -a lista <<< "$DIRECT_IPS"
  for ip in "${lista[@]}"; do
    ip="${ip// /}"
    [[ -n "$ip" && "$ip" == "$host" ]] && return 0
  done
  return 1
}

payload_para_jetdirect() {
  local host="$1"
  if debe_enviar_pdf_directo "$host"; then
    echo "$FILE"
    return 0
  fi

  if ! command -v gs >/dev/null 2>&1; then
    echo "Ghostscript (gs) no está instalado: no se puede convertir el PDF a PCL" >&2
    echo "para la impresora ${host} (no acepta PDF Direct). Instalar: sudo apt install -y ghostscript" >&2
    return 1
  fi

  local tmp
  tmp="$(mktemp --suffix=.pcl)"
  # ljet4 = PCL5 compatible con HP LaserJet 1300 / 4 / 5 / 6.
  # A4 fijo: sin FIXEDMEDIA la 1300 a veces imprime 1 sola hoja o recorta.
  if ! gs -q -dSAFER -dNOPAUSE -dBATCH -dNOTRANSPARENCY \
      -sDEVICE=ljet4 -r600 \
      -sPAPERSIZE=a4 -dFIXEDMEDIA \
      -dAutoRotatePages=/None \
      -sOutputFile="$tmp" "$FILE" >/dev/null 2>&1; then
    rm -f "$tmp"
    echo "Ghostscript no pudo convertir el PDF a PCL." >&2
    return 1
  fi
  if [[ ! -s "$tmp" ]]; then
    rm -f "$tmp"
    echo "Ghostscript generó un PCL vacío." >&2
    return 1
  fi
  echo "$tmp"
}

enviar_jetdirect() {
  local host="$1"
  local payload="$2"
  local cleanup="${3:-0}"

  if ! command -v nc >/dev/null 2>&1; then
    echo "Comando nc no disponible para JetDirect." >&2
    [[ "$cleanup" == "1" ]] && rm -f "$payload"
    return 1
  fi
  if ! nc -z -w 3 "$host" "$PORT" >/dev/null 2>&1; then
    echo "Impresora ${host}:${PORT} no responde." >&2
    [[ "$cleanup" == "1" ]] && rm -f "$payload"
    return 1
  fi
  # -N cierra el socket al terminar (si no, la impresora deja nc colgado).
  local ec=0
  nc -N -w 30 "$host" "$PORT" < "$payload" || ec=$?
  [[ "$cleanup" == "1" ]] && rm -f "$payload"
  return "$ec"
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
  PAYLOAD="$(payload_para_jetdirect "$IP")" || exit 1
  CLEANUP=0
  if [[ "$PAYLOAD" != "$FILE" ]]; then
    CLEANUP=1
  fi
  if enviar_jetdirect "$IP" "$PAYLOAD" "$CLEANUP"; then
    if [[ "$CLEANUP" == "1" ]]; then
      echo "PDF→PCL enviado a ${IP}:${PORT} (JetDirect)."
    else
      echo "PDF enviado a ${IP}:${PORT} (JetDirect PDF Direct)."
    fi
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
