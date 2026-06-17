#!/usr/bin/env bash
# Configura 10.20.30.211 como entorno de prueba con BD anitaERP_test.
# NO borra anitaERP en .211 ni toca el servidor .210.
#
# Uso en .211:
#   chmod +x deploy/backup/setup-211-anitaERP-test.sh
#   ./deploy/backup/setup-211-anitaERP-test.sh
#
# Si falla CREATE DATABASE, ejecutar una vez (como sergio):
#   sudo mysql -e "CREATE DATABASE IF NOT EXISTS anitaERP_test CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci; GRANT ALL PRIVILEGES ON anitaERP_test.* TO 'anitaERP'@'localhost'; FLUSH PRIVILEGES;"
#   sudo supervisorctl stop anitaERP-queue

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

DB_TEST="anitaERP_test"
DB_PROD_NAME="anitaERP"
DUMP="${DUMP:-backups/anitaERP_20260614_180001.sql.gz}"

die() { echo "setup-211: $*" >&2; exit 1; }

HOST_IP="$(hostname -I | awk '{print $1}')"
if [[ "${HOST_IP}" != "10.20.30.211" ]]; then
  die "Este script solo debe ejecutarse en 10.20.30.211 (IP actual: ${HOST_IP})."
fi

[[ -f .env ]] || die "Falta .env en ${ROOT}"
DB_USER="$(grep -m1 '^DB_USERNAME=' .env | cut -d= -f2- | tr -d "\"'")"
DB_PASS="$(grep -m1 '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d "\"'")"
[[ -n "${DB_USER}" && -n "${DB_PASS}" ]] || die "DB_USERNAME/DB_PASSWORD no definidos en .env"

mysql_cmd() {
  mysql -u"${DB_USER}" -p"${DB_PASS}" "$@"
}

echo "=== [1/6] Verificación servidor .211 ==="
echo "IP=${HOST_IP} ROOT=${ROOT}"

echo "=== [2/6] Crear BD ${DB_TEST} (si no existe) ==="
if ! mysql_cmd -e "USE \`${DB_TEST}\`" 2>/dev/null; then
  echo "El usuario ${DB_USER} no puede crear ${DB_TEST}. Intentando con sudo mysql..."
  if ! sudo -n mysql -e "
    CREATE DATABASE IF NOT EXISTS \`${DB_TEST}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
    GRANT ALL PRIVILEGES ON \`${DB_TEST}\`.* TO '${DB_USER}'@'localhost';
    FLUSH PRIVILEGES;
  " 2>/dev/null; then
    die "Ejecutá manualmente:
  sudo mysql -e \"CREATE DATABASE IF NOT EXISTS ${DB_TEST} CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci; GRANT ALL PRIVILEGES ON ${DB_TEST}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;\"
  sudo supervisorctl stop anitaERP-queue
y volvé a correr este script."
  fi
fi
mysql_cmd -e "USE \`${DB_TEST}\`; SELECT 'BD ${DB_TEST} OK' AS estado;"

echo "=== [3/6] Detener cola (supervisor) si hay sudo ==="
sudo -n supervisorctl stop anitaERP-queue 2>/dev/null || echo "(aviso) No se pudo detener cola sin contraseña sudo — ejecutá: sudo supervisorctl stop anitaERP-queue"

echo "=== [4/6] Restaurar dump en ${DB_TEST} (sin padron_iibb_arba) ==="
[[ -f "${DUMP}" ]] || die "No existe dump: ${DUMP}"
VENTAS_TEST="$(mysql_cmd -N -e "SELECT COUNT(*) FROM \`${DB_TEST}\`.venta" 2>/dev/null || echo 0)"
if [[ "${VENTAS_TEST}" -gt 1000 ]]; then
  echo "anitaERP_test ya tiene ${VENTAS_TEST} ventas — omito restore (usar FORCE_RESTORE=1 para forzar)."
  if [[ "${FORCE_RESTORE:-}" != "1" ]]; then
    echo "Saltando restore."
  else
    echo "FORCE_RESTORE=1: restaurando de nuevo..."
    gunzip -c "${DUMP}" | sed "s/\`anitaERP\`/\`${DB_TEST}\`/g" | perl deploy/backup/filter-skip-table.pl padron_iibb_arba | mysql_cmd "${DB_TEST}"
  fi
else
  gunzip -c "${DUMP}" | sed "s/\`anitaERP\`/\`${DB_TEST}\`/g" | perl deploy/backup/filter-skip-table.pl padron_iibb_arba | mysql_cmd "${DB_TEST}"
fi

echo "=== [5/6] Ajustar .env (apunta a ${DB_TEST}; NO borra ${DB_PROD_NAME}) ==="
cp -a .env ".env.bak_211_test_$(date +%Y%m%d_%H%M%S)"
if grep -q '^DB_DATABASE=' .env; then
  sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_TEST}/" .env
else
  echo "DB_DATABASE=${DB_TEST}" >> .env
fi
if grep -q '^DB_DATABASE_PROTEGIDA=' .env; then
  sed -i "s/^DB_DATABASE_PROTEGIDA=.*/DB_DATABASE_PROTEGIDA=${DB_PROD_NAME}/" .env
else
  echo "DB_DATABASE_PROTEGIDA=${DB_PROD_NAME}" >> .env
fi
# sed -i on debian might need fixing - I used sed -id by mistake in one line - fix script

echo "=== [6/6] Laravel + validación ==="
php artisan config:clear
php artisan cache:clear

mysql_cmd "${DB_TEST}" -e "
  SELECT COUNT(*) proveedores FROM proveedor;
  SELECT COUNT(*) articulos FROM articulo;
  SELECT COUNT(*) ventas FROM venta;
  SELECT MAX(created_at) ultima_venta FROM venta;
"

php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'Laravel DB=' . config('database.connections.mysql.database') . PHP_EOL;
echo 'protegida=' . config('database.protegida') . PHP_EOL;
echo 'ventas=' . DB::table('venta')->count() . PHP_EOL;
"

mysql_cmd -e "SELECT COUNT(*) ventas_bd_anitaERP_original FROM \`${DB_PROD_NAME}\`.venta;" || true

echo ""
echo "Listo. anitaERP en .211 sigue intacta (no se borró)."
echo "App de prueba: http://10.20.30.211 — BD activa: ${DB_TEST}"
echo "Producción real sigue en .210 / BD anitaERP (sin cambios)."
