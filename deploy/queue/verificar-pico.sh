#!/bin/bash
# Verificación rápida del worker de colas y salud en hora pico (gastronomía / Waitry).
# Uso: deploy/queue/verificar-pico.sh [--json] [--strict]
# Códigos de salida: 0 OK | 1 CRÍTICO | 2 ADVERTENCIA
set -euo pipefail

ROOT="/var/www/html/anitaERP"
LOG_FILE="$ROOT/storage/logs/queue-worker.log"
LARAVEL_LOG="$ROOT/storage/logs/laravel.log"
SUPERVISOR_PROGRAM="anitaERP-queue"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-}"
PENDING_WARN="${PENDING_WARN:-5}"
PENDING_CRITICAL="${PENDING_CRITICAL:-20}"
RESERVED_STUCK_SEC="${RESERVED_STUCK_SEC:-180}"
FAILED_24H_WARN="${FAILED_24H_WARN:-1}"
WORKERS_EXPECTED="${WORKERS_EXPECTED:-1}"
OUTPUT_JSON=false
STRICT=false

usage() {
    cat <<'EOF'
Uso: verificar-pico.sh [opciones]

Opciones:
  --json              Salida JSON (para cron / alertas)
  --strict            Advertencias también devuelven exit 1
  -h, --help          Esta ayuda

Variables de entorno (umbrales):
  PENDING_WARN=5          Jobs pendientes → advertencia
  PENDING_CRITICAL=20     Jobs pendientes → crítico
  RESERVED_STUCK_SEC=180  Job reservado más de N seg → crítico
  FAILED_24H_WARN=1       Fallos en 24 h ≥ N → advertencia
  WORKERS_EXPECTED=1      Cantidad de procesos queue:work esperados

Códigos de salida: 0 OK | 1 CRÍTICO | 2 ADVERTENCIA
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --json) OUTPUT_JSON=true ;;
        --strict) STRICT=true ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Opción desconocida: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

cd "$ROOT"

if [[ -z "$QUEUE_CONNECTION" ]]; then
    QUEUE_CONNECTION="$(grep -E '^QUEUE_CONNECTION=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
fi
QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"

# --- Worker process(es) ---
mapfile -t WORKER_LINES < <(ps -eo pid,user,etime,cmd --no-headers 2>/dev/null \
    | grep '[q]ueue:work database' || true)
WORKER_COUNT="${#WORKER_LINES[@]}"
WORKER_PIDS=()
WORKER_ETIMES=()
for line in "${WORKER_LINES[@]}"; do
    WORKER_PIDS+=("$(echo "$line" | awk '{print $1}')")
    WORKER_ETIMES+=("$(echo "$line" | awk '{print $3}')")
done

# --- Supervisor (opcional, sin sudo puede fallar) ---
SUPERVISOR_STATE=""
SUPERVISOR_RAW=""
if command -v supervisorctl >/dev/null 2>&1; then
    if SUPERVISOR_RAW="$(supervisorctl status "$SUPERVISOR_PROGRAM" 2>/dev/null)"; then
        SUPERVISOR_STATE="$(echo "$SUPERVISOR_RAW" | awk '{print $2}')"
    elif SUPERVISOR_RAW="$(sudo supervisorctl status "$SUPERVISOR_PROGRAM" 2>/dev/null)"; then
        SUPERVISOR_STATE="$(echo "$SUPERVISOR_RAW" | awk '{print $2}')"
    else
        SUPERVISOR_STATE="UNKNOWN"
        SUPERVISOR_RAW="supervisorctl no disponible (sin permisos o sin supervisor)"
    fi
else
    SUPERVISOR_STATE="NO_SUPERVISOR"
    SUPERVISOR_RAW="supervisorctl no instalado"
fi

# --- Cola Laravel (jobs + failed) ---
read -r JOBS_PENDING JOBS_RESERVED JOBS_DELAYED JOBS_OLDEST_RESERVED_SEC FAILED_24H FAILED_TOTAL \
    <<< "$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$now = time();
\$pending = (int) DB::table('jobs')->count();
\$reserved = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
\$delayed = (int) DB::table('jobs')->where('available_at', '>', \$now)->count();
\$oldestReserved = DB::table('jobs')->whereNotNull('reserved_at')->min('reserved_at');
\$stuckSec = \$oldestReserved ? max(0, \$now - (int) \$oldestReserved) : 0;
\$failed24 = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
\$failedTotal = (int) DB::table('failed_jobs')->count();
echo \$pending.' '.\$reserved.' '.\$delayed.' '.\$stuckSec.' '.\$failed24.' '.\$failedTotal;
")"

# --- queue:monitor ---
MONITOR_JSON=""
if MONITOR_JSON="$(php artisan queue:monitor database:default --json 2>/dev/null)"; then
    :
else
    MONITOR_JSON="{}"
fi

# --- Log worker ---
LOG_LINES=0
LOG_MTIME=""
if [[ -f "$LOG_FILE" ]]; then
    LOG_LINES="$(wc -l <"$LOG_FILE" | tr -d ' ')"
    LOG_MTIME="$(stat -c '%y' "$LOG_FILE" 2>/dev/null | cut -d. -f1 || date -r "$LOG_FILE" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || echo '?')"
fi
LOG_TAIL=""
if [[ -f "$LOG_FILE" && "$LOG_LINES" -gt 0 ]]; then
    LOG_TAIL="$(tail -3 "$LOG_FILE" 2>/dev/null | tr '\n' ' | ' | sed 's/ | $//')"
fi

# --- Señales Waitry / emisión (últimos 60 min en laravel.log) ---
WAITRY_OK_60M=0
WAITRY_FAIL_60M=0
ANITA_FAIL_60M=0
if [[ -f "$LARAVEL_LOG" ]]; then
    CUTOFF="$(date -d '60 minutes ago' '+%Y-%m-%d %H:%M' 2>/dev/null || date -v-60M '+%Y-%m-%d %H:%M' 2>/dev/null || echo '')"
    if [[ -n "$CUTOFF" ]]; then
        RECENT="$(awk -v cut="$CUTOFF" '
            \$0 >= cut { print }
        ' "$LARAVEL_LOG" 2>/dev/null | tail -50000 || true)"
        WAITRY_OK_60M="$(echo "$RECENT" | grep -c 'waitry\.comanda\.ok' || true)"
        WAITRY_FAIL_60M="$(echo "$RECENT" | grep -cE 'waitry\.comanda\.(fallo|error|reintento)' || true)"
        ANITA_FAIL_60M="$(echo "$RECENT" | grep -c 'facturacion\.anita_bridge\.fallo' || true)"
    fi
fi

# --- Evaluación ---
ISSUES_CRITICAL=()
ISSUES_WARN=()

if [[ "$QUEUE_CONNECTION" != "database" && "$QUEUE_CONNECTION" != "redis" ]]; then
    ISSUES_WARN+=("QUEUE_CONNECTION=$QUEUE_CONNECTION (cola desactivada; worker no procesa jobs Laravel)")
fi

if [[ "$WORKER_COUNT" -eq 0 ]]; then
    ISSUES_CRITICAL+=("Sin proceso queue:work database")
elif [[ "$WORKER_COUNT" -ne "$WORKERS_EXPECTED" ]]; then
    ISSUES_WARN+=("Workers activos=$WORKER_COUNT (esperados=$WORKERS_EXPECTED)")
fi

if [[ "$SUPERVISOR_STATE" == "RUNNING" && "$WORKER_COUNT" -eq 0 ]]; then
    ISSUES_CRITICAL+=("Supervisor RUNNING pero no hay proceso queue:work")
elif [[ "$SUPERVISOR_STATE" =~ ^(FATAL|BACKOFF|STOPPED|EXITED)$ ]]; then
    ISSUES_CRITICAL+=("Supervisor $SUPERVISOR_PROGRAM en estado $SUPERVISOR_STATE")
fi

if [[ "$JOBS_PENDING" -ge "$PENDING_CRITICAL" ]]; then
    ISSUES_CRITICAL+=("Cola pending=$JOBS_PENDING (≥$PENDING_CRITICAL)")
elif [[ "$JOBS_PENDING" -ge "$PENDING_WARN" ]]; then
    ISSUES_WARN+=("Cola pending=$JOBS_PENDING (≥$PENDING_WARN)")
fi

if [[ "$JOBS_RESERVED" -gt 0 && "$JOBS_OLDEST_RESERVED_SEC" -ge "$RESERVED_STUCK_SEC" ]]; then
    ISSUES_CRITICAL+=("Job reservado ${JOBS_OLDEST_RESERVED_SEC}s (≥${RESERVED_STUCK_SEC}s)")
fi

if [[ "$FAILED_24H" -ge "$FAILED_24H_WARN" ]]; then
    ISSUES_WARN+=("failed_jobs últimas 24h=$FAILED_24H")
fi

if [[ "$WAITRY_FAIL_60M" -gt 0 && "$WAITRY_OK_60M" -eq 0 && "$JOBS_PENDING" -gt 0 ]]; then
    ISSUES_WARN+=("Waitry: fallos recientes sin OK en 60 min y cola con pendientes")
fi

EXIT_CODE=0
if [[ ${#ISSUES_CRITICAL[@]} -gt 0 ]]; then
    EXIT_CODE=1
elif [[ ${#ISSUES_WARN[@]} -gt 0 ]]; then
    EXIT_CODE=2
fi
if [[ "$STRICT" == true && "$EXIT_CODE" -eq 2 ]]; then
    EXIT_CODE=1
fi

STATUS="OK"
if [[ "$EXIT_CODE" -eq 1 ]]; then
    STATUS="CRITICO"
elif [[ "$EXIT_CODE" -eq 2 ]]; then
    STATUS="ADVERTENCIA"
fi

TIMESTAMP="$(date '+%Y-%m-%d %H:%M:%S %z')"

if [[ "$OUTPUT_JSON" == true ]]; then
    python3 - <<PY
import json
print(json.dumps({
    "timestamp": "$TIMESTAMP",
    "status": "$STATUS",
    "exit_code": $EXIT_CODE,
    "queue_connection": "$QUEUE_CONNECTION",
    "worker_count": $WORKER_COUNT,
    "worker_pids": $(printf '%s\n' "${WORKER_PIDS[@]:-}" | python3 -c 'import json,sys; print(json.dumps([x for x in sys.stdin.read().splitlines() if x]))'),
    "supervisor_state": "$SUPERVISOR_STATE",
    "jobs": {
        "pending": $JOBS_PENDING,
        "reserved": $JOBS_RESERVED,
        "delayed": $JOBS_DELAYED,
        "oldest_reserved_sec": $JOBS_OLDEST_RESERVED_SEC,
    },
    "failed_jobs_24h": $FAILED_24H,
    "failed_jobs_total": $FAILED_TOTAL,
    "queue_monitor": json.loads('''$MONITOR_JSON''') if '''$MONITOR_JSON'''.strip() else {},
    "log_worker": {"lines": $LOG_LINES, "mtime": "$LOG_MTIME"},
    "laravel_60m": {
        "waitry_ok": $WAITRY_OK_60M,
        "waitry_fail": $WAITRY_FAIL_60M,
        "anita_bridge_fail": $ANITA_FAIL_60M,
    },
    "issues_critical": $(printf '%s\n' "${ISSUES_CRITICAL[@]:-}" | python3 -c 'import json,sys; print(json.dumps([x for x in sys.stdin.read().splitlines() if x]))'),
    "issues_warn": $(printf '%s\n' "${ISSUES_WARN[@]:-}" | python3 -c 'import json,sys; print(json.dumps([x for x in sys.stdin.read().splitlines() if x]))'),
}, ensure_ascii=False))
PY
    exit "$EXIT_CODE"
fi

echo "=== anitaERP cola — verificación pico ==="
echo "Fecha:              $TIMESTAMP"
echo "Estado:             $STATUS (exit $EXIT_CODE)"
echo "QUEUE_CONNECTION:   $QUEUE_CONNECTION"
echo ""
echo "--- Worker ---"
if [[ "$WORKER_COUNT" -eq 0 ]]; then
    echo "Procesos queue:work: 0 (NINGUNO)"
else
    echo "Procesos queue:work: $WORKER_COUNT"
    for i in "${!WORKER_LINES[@]}"; do
        echo "  PID ${WORKER_PIDS[$i]}  uptime ${WORKER_ETIMES[$i]}"
    done
fi
echo "Supervisor:         $SUPERVISOR_RAW"
echo ""
echo "--- Cola database:default ---"
php artisan queue:monitor database:default 2>/dev/null || echo "(queue:monitor no disponible)"
echo "pending=$JOBS_PENDING  reserved=$JOBS_RESERVED  delayed=$JOBS_DELAYED  reservado_mas_viejo=${JOBS_OLDEST_RESERVED_SEC}s"
echo "failed_jobs 24h=$FAILED_24H  total=$FAILED_TOTAL"
echo ""
echo "--- Log worker ---"
echo "Archivo: $LOG_FILE ($LOG_LINES líneas, modificado $LOG_MTIME)"
if [[ -n "$LOG_TAIL" ]]; then
    echo "Últimas líneas: $LOG_TAIL"
fi
echo ""
echo "--- Laravel últimos 60 min ---"
echo "waitry.comanda.ok: $WAITRY_OK_60M | waitry fallos/reintentos: $WAITRY_FAIL_60M | anita_bridge.fallo: $ANITA_FAIL_60M"
echo ""

if [[ ${#ISSUES_CRITICAL[@]} -gt 0 ]]; then
    echo "CRÍTICO:"
    printf '  - %s\n' "${ISSUES_CRITICAL[@]}"
fi
if [[ ${#ISSUES_WARN[@]} -gt 0 ]]; then
    echo "ADVERTENCIA:"
    printf '  - %s\n' "${ISSUES_WARN[@]}"
fi
if [[ "$EXIT_CODE" -eq 0 ]]; then
    echo "Todo OK."
fi

echo ""
echo "Schedule Laravel (recomendado): queue:verificar-pico cada 5 min 22–23 h vía schedule:run (www-data)."
echo "Mail alertas: QUEUE_VERIFICACION_PICO_EMAIL (SMTP Office365, mismo stack que auditoría Anita)."
echo "Log: $ROOT/storage/logs/queue-verificar-pico.log"

exit "$EXIT_CODE"
