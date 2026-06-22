#!/bin/bash
# Worker manual (sin supervisor) — útil en desarrollo o si no hay sudo.
set -euo pipefail

ROOT="/var/www/html/anitaERP"
PID_FILE="$ROOT/storage/logs/queue-worker.pid"
LOG_FILE="$ROOT/storage/logs/queue-worker.log"
CMD=(php artisan queue:work database --sleep=3 --tries=3 --timeout=360 --max-time=3600)

cd "$ROOT"

case "${1:-}" in
    start)
        if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
            echo "Worker ya en ejecución (PID $(cat "$PID_FILE"))."
            exit 0
        fi
        nohup "${CMD[@]}" >>"$LOG_FILE" 2>&1 &
        echo $! >"$PID_FILE"
        echo "Worker iniciado PID $(cat "$PID_FILE"). Log: $LOG_FILE"
        ;;
    stop)
        if [[ ! -f "$PID_FILE" ]]; then
            echo "No hay PID file."
            exit 0
        fi
        kill "$(cat "$PID_FILE")" 2>/dev/null || true
        rm -f "$PID_FILE"
        echo "Worker detenido."
        ;;
    status)
        if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
            echo "Worker activo PID $(cat "$PID_FILE")"
        else
            echo "Worker no está corriendo."
        fi
        echo "QUEUE_CONNECTION=$(grep -E '^QUEUE_CONNECTION=' .env 2>/dev/null | cut -d= -f2- || echo '?')"
        php artisan queue:monitor database:default 2>/dev/null || true
        ;;
    *)
        echo "Uso: $0 {start|stop|status}"
        exit 1
        ;;
esac
