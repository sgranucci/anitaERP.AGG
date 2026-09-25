#!/usr/bin/env bash
# Envía un archivo raw/texto a una cola CUPS remota vía IPP (sin lp local).
# Uso en tabla salida.comando o desde empacar OT:
#   /var/www/html/anitaERP/bin/imprimir-cups-remoto.sh "%s" calidad
#   /var/www/html/anitaERP/bin/imprimir-cups-remoto.sh "%s" armado 160.132.0.209
#
# Por defecto (Ferli): host CUPS L8 = 160.132.0.209, puerto 631.
set -euo pipefail

FILE="${1:?falta ruta del archivo}"
COLA="${2:?falta nombre de cola CUPS (ej. calidad)}"
HOST="${3:-${CUPS_REMOTO_HOST:-160.132.0.209}}"
PORT="${CUPS_REMOTO_PORT:-631}"

if [[ ! -f "$FILE" ]]; then
  echo "Archivo no encontrado: $FILE" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = App\Support\Configuracion\CupsRemotoImpresionSupport::imprimirArchivo(
    $argv[1],
    $argv[2],
    $argv[3]
);
fwrite($r["ok"] ? STDOUT : STDERR, $r["mensaje"].PHP_EOL);
exit($r["ok"] ? 0 : 1);
' "$FILE" "$COLA" "$HOST"
