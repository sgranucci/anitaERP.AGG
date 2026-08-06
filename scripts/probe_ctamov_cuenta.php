<?php

/**
 * Trae a memoria toda la cuenta desde ctamov (asientos contables) y busca movimientos que
 * expliquen un descalce contra la cuenta corriente: importes puntuales y netos por día.
 *
 * Uso: php scripts/probe_ctamov_cuenta.php [cuenta=113100000] [objetivo=3732.89] [--refrescar]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$args = array_slice($argv, 1);
$refrescar = in_array('--refrescar', $args, true);
$args = array_values(array_filter($args, static fn ($a) => ! str_starts_with($a, '--')));

$cuenta = (int) ($args[0] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$objetivo = (float) ($args[1] ?? 3732.89);
$cacheDir = storage_path('app/probe_cc_mayor');
if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$api = new ApiAnita();

function listar(ApiAnita $api, array $payload, int $intentos = 6): array
{
    for ($i = 1; $i <= $intentos; $i++) {
        $raw = (string) $api->apiCall($payload + ['acc' => 'list']);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            fwrite(STDERR, "ERROR {$payload['tabla']}: {$err}\n");
            if ($i === $intentos) {
                return [];
            }
        } else {
            $filas = ApiAnita::decodificarListaFilas($raw);
            if ($filas !== [] || $i === $intentos) {
                return $filas;
            }
        }
        usleep(300000);
    }

    return [];
}

// ctamov vive en el sistema contable.
$sistema = 'contab';

$fila = listar($api, [
    'sistema' => $sistema,
    'tabla' => 'ctamov',
    'campos' => 'COUNT(*) AS n, MIN(ctav_fecha) AS fmin, MAX(ctav_fecha) AS fmax',
    'whereArmado' => ' WHERE ctav_cuenta = '.$cuenta,
]);
$meta = $fila[0] ?? null;
echo 'ctamov cuenta '.$cuenta.': n='.($meta->n ?? '?').' fmin='.($meta->fmin ?? '?').' fmax='.($meta->fmax ?? '?')."\n";

$archivo = $cacheDir.'/ctamov_'.$cuenta.'.json';
if (! $refrescar && is_readable($archivo)) {
    $ctamov = json_decode((string) file_get_contents($archivo), false) ?: [];
    echo 'ctamov cache: '.count($ctamov)." filas\n";
} else {
    $ctamov = listar($api, [
        'sistema' => $sistema,
        'tabla' => 'ctamov',
        'campos' => 'ctav_fecha,ctav_nro_asiento,ctav_cuenta,ctav_d_h,ctav_importe,ctav_desc_mov,ctav_tipo_asiento',
        'whereArmado' => ' WHERE ctav_cuenta = '.$cuenta,
    ]);
    file_put_contents($archivo, json_encode($ctamov));
    echo 'ctamov bridge: '.count($ctamov)." filas\n";
}

if ($ctamov === []) {
    echo "Sin filas de ctamov para la cuenta.\n";

    return;
}

$porDia = [];
$debe = 0.0;
$haber = 0.0;
foreach ($ctamov as $f) {
    $imp = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($f);
    if ($imp === null) {
        continue;
    }
    $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
    $d = (float) ($dh['debe'] ?? 0);
    $h = (float) ($dh['haber'] ?? 0);
    $debe += $d;
    $haber += $h;
    $fecha = (int) ($f->ctav_fecha ?? 0);
    $porDia[$fecha] = round(($porDia[$fecha] ?? 0) + $d - $h, 2);
}
ksort($porDia);

echo "\n=== CTAMOV cuenta {$cuenta} ===\n";
echo 'Debe='.$fmt($debe).' Haber='.$fmt($haber).' Neto='.$fmt($debe - $haber)."\n";

echo "\n=== Últimos 20 días con movimiento en ctamov ===\n";
foreach (array_slice($porDia, -20, 20, true) as $fecha => $neto) {
    echo sprintf("  %8d  %s%s\n", $fecha, $fmt($neto), abs(abs($neto) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '');
}

echo "\n=== Días cuyo neto en ctamov = {$objetivo} ===\n";
$hit = 0;
foreach ($porDia as $fecha => $neto) {
    if (abs(abs($neto) - $objetivo) < 0.011) {
        $hit++;
        echo sprintf("  %8d  %s\n", $fecha, $fmt($neto));
    }
}
if ($hit === 0) {
    echo "  (ninguno)\n";
}

echo "\n=== Líneas de ctamov con importe ≈ {$objetivo} ===\n";
$hit = 0;
foreach ($ctamov as $f) {
    if (abs(abs((float) ($f->ctav_importe ?? 0)) - $objetivo) < 0.011) {
        $hit++;
        echo sprintf(
            "  %s asiento=%-10s tipo=%-6s %s imp=%14s  %s\n",
            (string) ($f->ctav_fecha ?? ''),
            trim((string) ($f->ctav_nro_asiento ?? '')),
            trim((string) ($f->ctav_tipo_asiento ?? '')),
            trim((string) ($f->ctav_d_h ?? '')),
            $fmt((float) ($f->ctav_importe ?? 0)),
            trim((string) ($f->ctav_desc_mov ?? '')),
        );
    }
}
if ($hit === 0) {
    echo "  (ninguna)\n";
}

echo "\n=== Movimientos de ctamov de los últimos 5 días ===\n";
$fechasOrden = array_keys($porDia);
$corte = (int) ($fechasOrden[max(0, count($fechasOrden) - 5)] ?? 0);
foreach ($ctamov as $f) {
    if ((int) ($f->ctav_fecha ?? 0) >= $corte) {
        echo sprintf(
            "  %s asiento=%-10s tipo=%-6s %s imp=%16s  %s\n",
            (string) ($f->ctav_fecha ?? ''),
            trim((string) ($f->ctav_nro_asiento ?? '')),
            trim((string) ($f->ctav_tipo_asiento ?? '')),
            trim((string) ($f->ctav_d_h ?? '')),
            $fmt((float) ($f->ctav_importe ?? 0)),
            trim((string) ($f->ctav_desc_mov ?? '')),
        );
    }
}
