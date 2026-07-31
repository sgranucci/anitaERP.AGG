#!/usr/bin/env bash
# Sube Timeout del vhost anitaERP por encima de MAYOR_CONCEPTO_MAX_EXECUTION_TIME (900 s).
# Uso: sudo ./scripts/apache-timeout-mayor-concepto.sh
set -euo pipefail

CONF="/etc/apache2/sites-available/anitaERP.conf"
NUEVO=1200

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ejecutar con sudo: sudo $0" >&2
  exit 1
fi

if [[ ! -f "$CONF" ]]; then
  echo "No existe $CONF" >&2
  exit 1
fi

if grep -qE '^\s*Timeout\s+[0-9]+' "$CONF"; then
  sed -i -E "s/^(\s*Timeout\s+)[0-9]+/\1${NUEVO}/" "$CONF"
else
  echo "No hay directiva Timeout en $CONF" >&2
  exit 1
fi

# Comentario aclaratorio (idempotente).
if grep -q 'Reportes pesados (mayor concepto' "$CONF"; then
  :
elif grep -q 'Exportaciones PDF/Excel con muchos registros' "$CONF"; then
  sed -i 's/# Exportaciones PDF\/Excel con muchos registros (global apache2.conf: 300 s)/# Reportes pesados (mayor concepto, etc.). Global apache2.conf: 300 s; PHP MAYOR_CONCEPTO hasta 900 s./' "$CONF"
fi

echo "Timeout en $CONF:"
grep -n -E 'Timeout|^[[:space:]]*#' "$CONF" | head -20

apache2ctl configtest
systemctl reload apache2
echo "OK: Apache Timeout=${NUEVO}s y reload aplicado."
