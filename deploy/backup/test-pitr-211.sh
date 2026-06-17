#!/usr/bin/env bash
# Prueba integral PITR en .211: dump 14/jun 18:00 + binlog hasta antes del migrate:fresh (23:51:54).
# Ejecutar desde .210 (lee binlog local, aplica en MySQL del .211).
# NO toca anitaERP en .210 ni anitaERP en .211.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
REMOTE_HOST="${REMOTE_HOST:-10.20.30.211}"
REMOTE_ROOT="${REMOTE_ROOT:-/var/www/html/anitaERP}"
DB_TEST="${DB_TEST:-anitaERP_test}"
DUMP_NAME="${DUMP_NAME:-anitaERP_20260614_180001.sql.gz}"
SKIP_TABLE="${SKIP_TABLE:-padron_iibb_arba}"

# Snapshot del backup 14/jun 18:00 (deploy/backup/RESTORE.md)
BINLOG_START_FILE="${BINLOG_START_FILE:-binlog.000027}"
BINLOG_START_POS="${BINLOG_START_POS:-542096112}"
BINLOG_STOP_DATETIME="${BINLOG_STOP_DATETIME:-2026-06-14 23:51:54}"
BINLOG_FILES="${BINLOG_FILES:-binlog.000027 binlog.000028}"

# Validación esperada (conteos de referencia del binlog en .210)
EXPECT_VENTAS_DUMP="${EXPECT_VENTAS_DUMP:-14911}"
EXPECT_VENTAS_PITR_MIN="${EXPECT_VENTAS_PITR_MIN:-15500}"
EXPECT_VENTAS_PITR_MAX="${EXPECT_VENTAS_PITR_MAX:-15650}"
EXPECT_MAX_VENTA_ID_MIN="${EXPECT_MAX_VENTA_ID_MIN:-16100}"

LOG="${ROOT}/backups/test_pitr_211_$(date +%Y%m%d_%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

die() { echo "test-pitr-211: $*" >&2; exit 1; }

echo "=== TEST PITR .211 $(date -Is) ==="
echo "Origen binlog: $(hostname) | Destino: ${REMOTE_HOST} / ${DB_TEST}"
echo "Dump: ${DUMP_NAME} | Binlog: pos ${BINLOG_START_POS} -> ${BINLOG_STOP_DATETIME}"
echo "Log: ${LOG}"

remote_mysql() {
  local cmd
  cmd="$(printf '%q ' "$@")"
  ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" \
    "DB_PASS=\$(grep -m1 '^DB_PASSWORD=' /var/www/html/anitaERP/.env | cut -d= -f2- | tr -d \"\\\"'\"); \
     mysql -u anitaERP -p\"\${DB_PASS}\" ${cmd}"
}

echo "--- [1/5] Pre-check .211 ---"
remote_mysql -e "SELECT 'mysql_211_ok' estado;"
ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" "test -f '${REMOTE_ROOT}/backups/${DUMP_NAME}'" \
  || die "Falta dump en .211: ${REMOTE_ROOT}/backups/${DUMP_NAME}"

echo "--- [2/5] Restaurar dump en ${DB_TEST} (sin ${SKIP_TABLE}) ---"
remote_mysql -e "DROP DATABASE IF EXISTS \`${DB_TEST}\`; CREATE DATABASE \`${DB_TEST}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;"
ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" bash -s -- "${REMOTE_ROOT}" "${DUMP_NAME}" "${DB_TEST}" "${SKIP_TABLE}" <<'EOSH'
set -euo pipefail
ROOT="$1"
DUMP="$2"
DB="$3"
SKIP="$4"
DB_PASS="$(grep -m1 '^DB_PASSWORD=' "${ROOT}/.env" | cut -d= -f2- | tr -d "\"'")"
gunzip -c "${ROOT}/backups/${DUMP}" \
  | sed "s/\`anitaERP\`/\`${DB}\`/g" \
  | perl "${ROOT}/deploy/backup/filter-skip-table.pl" "${SKIP}" \
  | mysql -u anitaERP -p"${DB_PASS}" "${DB}"
EOSH

echo "--- Conteos post-dump ---"
remote_mysql "${DB_TEST}" -e "
SELECT COUNT(*) ventas FROM venta;
SELECT MAX(id) max_venta_id FROM venta;
SELECT MAX(created_at) ultima_venta FROM venta;
SELECT COUNT(*) tablas FROM information_schema.tables WHERE table_schema='${DB_TEST}';
"

VENTAS_DUMP="$(remote_mysql -N "${DB_TEST}" -e "SELECT COUNT(*) FROM venta;")"
if [[ "${VENTAS_DUMP}" != "${EXPECT_VENTAS_DUMP}" ]]; then
  echo "WARN: ventas post-dump=${VENTAS_DUMP} (esperado ${EXPECT_VENTAS_DUMP})"
fi

echo "--- [3/5] Aplicar binlog desde .210 (--rewrite-db -> ${DB_TEST}) ---"
echo "NOTA: requiere REPLICATION_APPLIER en .211 (sudo mysql < deploy/backup/grant-binlog-applier-211.sql)"
BINLOG_ERR="/tmp/pitr_211_binlog_errors_$$.log"
: > "${BINLOG_ERR}"

set +o pipefail
mysqlbinlog --defaults-file="${HOME}/.my.cnf" \
  --read-from-remote-server --host=127.0.0.1 --user=sergio \
  --rewrite-db="anitaERP->${DB_TEST}" \
  --start-position="${BINLOG_START_POS}" \
  --stop-datetime="${BINLOG_STOP_DATETIME}" \
  ${BINLOG_FILES} \
  | perl "${ROOT}/deploy/backup/filter-binlog-skip-table.pl" "${SKIP_TABLE}" \
  | ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" bash -s -- "${DB_TEST}" "${BINLOG_ERR}" <<'EOSH'
set -euo pipefail
DB="$1"
ERRLOG="$2"
DB_PASS="$(grep -m1 '^DB_PASSWORD=' /var/www/html/anitaERP/.env | cut -d= -f2- | tr -d "\"'")"
if mysql -u anitaERP -p"${DB_PASS}" -N -e "SELECT 1 FROM mysql.user WHERE user='anitaERP' LIMIT 0" 2>/dev/null; then
  :
fi
# --force: continúa ante FK/duplicados puntuales (típico en replay ROW)
mysql --force --binary-mode -u anitaERP -p"${DB_PASS}" "${DB}" 2>"${ERRLOG}" || true
EOSH
BINLOG_RC=${PIPESTATUS[0]}
set -o pipefail
if [[ "${BINLOG_RC}" -ne 0 && "${BINLOG_RC}" -ne 141 ]]; then
  echo "WARN: mysqlbinlog terminó con código ${BINLOG_RC} (revisar errores abajo)"
fi

echo "--- Errores binlog (muestra) ---"
ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" "wc -l ${BINLOG_ERR} 2>/dev/null; grep -v '^$' ${BINLOG_ERR} 2>/dev/null | head -20 || true"
ssh -o BatchMode=yes "sergio@${REMOTE_HOST}" "rm -f ${BINLOG_ERR}" 2>/dev/null || true

echo "--- [4/5] Validación post-PITR ---"
remote_mysql "${DB_TEST}" -e "
SELECT COUNT(*) ventas_total FROM venta;
SELECT COUNT(*) ventas_gap FROM venta WHERE created_at >= '2026-06-14 18:01:00' AND created_at < '2026-06-14 23:52:00';
SELECT MAX(id) max_venta_id FROM venta;
SELECT MAX(created_at) ultima_venta FROM venta;
SELECT COUNT(*) cobranzas FROM cobranza;
SELECT COUNT(*) cuentas_gastro FROM cuenta_gastronomia;
SELECT COUNT(*) usuarios FROM usuario;
SELECT COUNT(*) proveedores FROM proveedor;
SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_TEST}') tablas;
SHOW TABLES LIKE '${SKIP_TABLE}';
"

VENTAS_PITR="$(remote_mysql -N "${DB_TEST}" -e "SELECT COUNT(*) FROM venta;")"
MAX_ID="$(remote_mysql -N "${DB_TEST}" -e "SELECT MAX(id) FROM venta;")"
VENTAS_GAP="$(remote_mysql -N "${DB_TEST}" -e "SELECT COUNT(*) FROM venta WHERE created_at >= '2026-06-14 18:01:00' AND created_at < '2026-06-14 23:52:00';")"
ULTIMA="$(remote_mysql -N "${DB_TEST}" -e "SELECT MAX(created_at) FROM venta;")"
TABLAS="$(remote_mysql -N "${DB_TEST}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_TEST}';")"

PASS=1
[[ "${VENTAS_PITR}" -ge "${EXPECT_VENTAS_PITR_MIN}" && "${VENTAS_PITR}" -le "${EXPECT_VENTAS_PITR_MAX}" ]] || { echo "FAIL ventas PITR: ${VENTAS_PITR} (rango ${EXPECT_VENTAS_PITR_MIN}-${EXPECT_VENTAS_PITR_MAX})"; PASS=0; }
[[ "${MAX_ID}" -ge "${EXPECT_MAX_VENTA_ID_MIN}" ]] || { echo "FAIL max venta id: ${MAX_ID} (min ${EXPECT_MAX_VENTA_ID_MIN})"; PASS=0; }
[[ "${VENTAS_GAP}" -gt 500 ]] || { echo "FAIL ventas en gap 18-23:51: ${VENTAS_GAP} (min 500)"; PASS=0; }
[[ "${ULTIMA}" > "2026-06-14 22:00:00" ]] 2>/dev/null || { echo "FAIL ultima venta: ${ULTIMA}"; PASS=0; }
[[ "${TABLAS}" -gt 300 ]] || { echo "FAIL tablas: ${TABLAS}"; PASS=0; }

echo "--- [5/5] Verificar que NO hubo migrate:fresh (tablas críticas con datos) ---"
remote_mysql "${DB_TEST}" -e "
SELECT COUNT(*) menu FROM menu;
SELECT COUNT(*) migrations FROM migrations;
"

MENU="$(remote_mysql -N "${DB_TEST}" -e "SELECT COUNT(*) FROM menu;")"
[[ "${MENU}" -gt 10 ]] || { echo "FAIL menu vacío (${MENU}) — posible wipe"; PASS=0; }

if [[ "${PASS}" -eq 1 ]]; then
  echo "=== RESULTADO: OK — PITR verificado en ${REMOTE_HOST}/${DB_TEST} ==="
else
  echo "=== RESULTADO: FALLOS — revisar log ${LOG} ==="
  exit 1
fi

echo "BD de prueba: ${DB_TEST} en ${REMOTE_HOST} (anitaERP en .211 intacta)"
echo "=== FIN $(date -Is) ==="
