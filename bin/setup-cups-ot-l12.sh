#!/usr/bin/env bash
# Opcional: crear colas CUPS locales en el L12 (requiere root).
# La emisión OT actual NO necesita esto: usa JetDirect (imprimir-pdf-laser.sh + IP).
#
# Uso:
#   sudo bash bin/setup-cups-ot-l12.sh
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecutar como root: sudo bash $0" >&2
  exit 1
fi

if ! command -v lpadmin >/dev/null 2>&1; then
  apt-get update
  apt-get install -y cups cups-client cups-bsd
  systemctl enable --now cups
fi

declare -A PRINTERS=(
  [hp-diego]="socket://160.132.0.203:9100"
  [HP4250GABY]="socket://160.132.0.183:9100"
  [P1]="socket://160.132.0.201:9100"
  [hp1300]="socket://160.132.0.200:9100"
)

for name in "${!PRINTERS[@]}"; do
  uri="${PRINTERS[$name]}"
  echo "Alta cola «${name}» → ${uri}"
  lpadmin -p "$name" -E -v "$uri" -m raw \
    || lpadmin -p "$name" -E -v "$uri" -m everywhere
  cupsenable "$name" || true
  cupsaccept "$name" || true
done

lpstat -a
echo "Listo. Las salidas OT del ERP pueden seguir en JetDirect; estas colas son opcionales."
