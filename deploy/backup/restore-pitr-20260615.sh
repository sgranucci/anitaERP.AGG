#!/usr/bin/env bash
# Restore anitaERP: dump 14/jun 18:00 + PITR binlog continuo (sin padron_iibb_arba)
set -euo pipefail

ROOT="/var/www/html/anitaERP"
DUMP="${ROOT}/backups/anitaERP_20260614_180001.sql.gz"
MYCNF="${HOME}/.my.cnf"
DB="anitaERP"
LOG="${ROOT}/backups/restore_pitr_$(date +%Y%m%d_%H%M%S).log"
SKIP_TABLE="padron_iibb_arba"
BINLOG_HOST="127.0.0.1"
BINLOG_USER="sergio"
BINLOG_PASS="21Julio1968@"

exec > >(tee -a "$LOG") 2>&1

echo "=== RESTORE PITR $(date -Is) ==="

echo "--- 0) Backup seguridad ---"
mysqldump --defaults-file="$MYCNF" --single-transaction "$DB" 2>/dev/null | gzip > "${ROOT}/backups/anitaERP_antes_restore_$(date +%Y%m%d_%H%M%S).sql.gz" || true

echo "--- 1) Recrear base ---"
mysql --defaults-file="$MYCNF" -e "DROP DATABASE IF EXISTS \`${DB}\`; CREATE DATABASE \`${DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

echo "--- 2) Dump 18:00 sin ${SKIP_TABLE} ---"
gunzip -c "$DUMP" | perl "${ROOT}/deploy/backup/filter-skip-table.pl" "$SKIP_TABLE" | mysql --defaults-file="$MYCNF" "$DB"

echo "--- Conteos post-dump ---"
mysql --defaults-file="$MYCNF" "$DB" -e "
SELECT COUNT(*) ventas FROM venta;
SELECT COUNT(*) usuarios FROM usuario;
SELECT COUNT(*) padron_arba FROM padron_iibb_arba;
"

echo "--- 3) Binlog continuo: pos 542096112 -> 02:16 (antes 2do borrado) ---"
mysqlbinlog --read-from-remote-server --host="$BINLOG_HOST" --user="$BINLOG_USER" --password="$BINLOG_PASS" \
  --start-position=542096112 \
  --stop-datetime="2026-06-15 02:16:00" \
  binlog.000027 binlog.000028 binlog.000029 binlog.000030 binlog.000031 binlog.000032 binlog.000033 \
  | perl "${ROOT}/deploy/backup/filter-binlog-skip-table.pl" "$SKIP_TABLE" \
  | mysql --defaults-file="$MYCNF" "$DB"

echo "--- Conteos finales vs gap ---"
mysql --defaults-file="$MYCNF" -e "
SELECT 'prod' db, (SELECT COUNT(*) FROM ${DB}.venta) ventas, (SELECT COUNT(*) FROM ${DB}.usuario) usuarios,
  (SELECT COUNT(*) FROM ${DB}.articulo) articulos, (SELECT COUNT(*) FROM ${DB}.cliente) clientes,
  (SELECT COUNT(*) FROM ${DB}.cobranza) cobranzas, (SELECT COUNT(*) FROM ${DB}.padron_iibb_arba) padron_arba;
SELECT 'gap' db, (SELECT COUNT(*) FROM anitaERP_gap.venta) ventas, (SELECT COUNT(*) FROM anitaERP_gap.usuario) usuarios,
  (SELECT COUNT(*) FROM anitaERP_gap.articulo) articulos, (SELECT COUNT(*) FROM anitaERP_gap.cliente) clientes,
  (SELECT COUNT(*) FROM anitaERP_gap.cobranza) cobranzas;
SELECT MAX(created_at) ultima_venta_prod FROM ${DB}.venta;
SELECT MAX(created_at) ultima_venta_gap FROM anitaERP_gap.venta;
SELECT MAX(id) max_venta_id_prod FROM ${DB}.venta;
SELECT MAX(id) max_venta_id_gap FROM anitaERP_gap.venta;
"

echo "--- 4) Laravel cache ---"
cd "$ROOT" && php artisan cache:clear && php artisan config:clear

echo "=== FIN RESTORE — log: $LOG ==="
