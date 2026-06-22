#!/bin/bash
# Instala cron www-data para Laravel schedule:run (incluye queue:verificar-pico en hora pico).
# Ejecutar con sudo: sudo deploy/queue/aplicar-cron-schedule.sh
set -euo pipefail

ROOT="/var/www/html/anitaERP"
CRON_USER="${CRON_USER:-www-data}"
SCHEDULE_LINE="* * * * * cd ${ROOT} && /usr/bin/php artisan schedule:run >> ${ROOT}/storage/logs/schedule.log 2>&1"
MARKER="anitaERP schedule:run"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Ejecutá con sudo: sudo $0" >&2
    exit 1
fi

if ! id "$CRON_USER" &>/dev/null; then
    echo "Usuario $CRON_USER no existe." >&2
    exit 1
fi

mkdir -p "${ROOT}/storage/logs"
touch "${ROOT}/storage/logs/schedule.log" "${ROOT}/storage/logs/queue-verificar-pico.log"
chown "${CRON_USER}:${CRON_USER}" "${ROOT}/storage/logs/schedule.log" "${ROOT}/storage/logs/queue-verificar-pico.log" 2>/dev/null || true

TMP="$(mktemp)"
crontab -u "$CRON_USER" -l 2>/dev/null | grep -v "$MARKER" | grep -v 'artisan schedule:run' >"$TMP" || true
{
    cat "$TMP"
    echo "# $MARKER"
    echo "$SCHEDULE_LINE"
} | crontab -u "$CRON_USER" -
rm -f "$TMP"

echo "Crontab $CRON_USER:"
crontab -u "$CRON_USER" -l | grep -A1 "$MARKER"
echo ""
echo "Verificación cola pico: php artisan queue:verificar-pico (22–23 h, cada 5 min vía schedule)."
echo "Mail alertas: QUEUE_VERIFICACION_PICO_EMAIL en .env (SMTP Laravel)."
echo "Log: ${ROOT}/storage/logs/queue-verificar-pico.log"
