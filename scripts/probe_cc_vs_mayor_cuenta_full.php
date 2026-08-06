<?php

/**
 * Descarga completa (una sola pasada por el bridge) del subdiario de una cuenta y de climov
 * agregado por día/tipo, con cache en disco para analizar sin volver a consultar Anita.
 *
 * Uso: php scripts/probe_cc_vs_mayor_cuenta_full.php [cuenta=113100000] [--refrescar]
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
$sistemaSub = (string) config('anita.subdiario_sistema', 'ventas');
$cacheDir = storage_path('app/probe_cc_mayor');
if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

/**
 * Trae una lista del bridge cacheando el resultado crudo en disco.
 */
function traerCache(string $archivo, bool $refrescar, callable $descargar): array
{
    if (! $refrescar && is_readable($archivo)) {
        $filas = json_decode((string) file_get_contents($archivo), false);
        if (is_array($filas)) {
            fwrite(STDERR, 'cache: '.basename($archivo).' ('.count($filas)." filas)\n");

            return $filas;
        }
    }

    $filas = $descargar();
    file_put_contents($archivo, json_encode($filas));
    fwrite(STDERR, 'bridge: '.basename($archivo).' ('.count($filas)." filas)\n");

    return $filas;
}

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

$api = new ApiAnita();

echo "cuenta={$cuenta} sistema_subdiario={$sistemaSub}\n";

// ---------------------------------------------------------------------------
// 1) Subdiario completo de la cuenta (una sola llamada, cacheada)
// ---------------------------------------------------------------------------
// El bridge no resuelve las ~318k filas en una sola UNLOAD: se trocea por año.
$anioDesde = 2016;
$anioHasta = (int) date('Y');
$subdiario = [];

for ($anio = $anioDesde; $anio <= $anioHasta; $anio++) {
    $tramo = traerCache(
        $cacheDir.'/subdiario_'.$cuenta.'_'.$anio.'.json',
        $refrescar,
        static fn (): array => listar($api, [
            'sistema' => $sistemaSub,
            'tabla' => 'subdiario',
            'campos' => 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,'
                .'subd_cuenta,subd_contrapartida,subd_importe,subd_emisor,subd_desc_mov',
            'whereArmado' => ' WHERE (subd_cuenta = '.$cuenta.' OR subd_contrapartida = '.$cuenta.')'
                .' AND subd_fecha BETWEEN '.($anio * 10000 + 101).' AND '.($anio * 10000 + 1231),
        ]),
    );

    foreach ($tramo as $fila) {
        $subdiario[] = $fila;
    }
}

// ---------------------------------------------------------------------------
// 2) climov agregado por fecha + tipo (una sola llamada, cacheada)
// ---------------------------------------------------------------------------
$climovDia = traerCache(
    $cacheDir.'/climov_por_dia_tipo.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => 'ventas',
        'tabla' => 'climov',
        'campos' => 'cliv_fecha,cliv_tipo,SUM(cliv_monto) AS monto,SUM(cliv_t_cobrado) AS cobrado,COUNT(*) AS n',
        'groupBy' => 'cliv_fecha,cliv_tipo',
    ]),
);

echo 'filas: subdiario='.count($subdiario).' climov_dia_tipo='.count($climovDia)."\n\n";

// ---------------------------------------------------------------------------
// 3) Mayor por día desde subdiario (cuenta + contrapartida, regla subd_tipo_mov)
// ---------------------------------------------------------------------------
$mayorDia = [];
$mayorDebe = 0.0;
$mayorHaber = 0.0;

foreach ($subdiario as $f) {
    $fecha = (int) ($f->subd_fecha ?? 0);
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $debe = (float) ($dh['debe'] ?? 0);
        $haber = (float) ($dh['haber'] ?? 0);

        $mayorDia[$fecha] ??= ['debe' => 0.0, 'haber' => 0.0, 'n' => 0];
        $mayorDia[$fecha]['debe'] += $debe;
        $mayorDia[$fecha]['haber'] += $haber;
        $mayorDia[$fecha]['n']++;
        $mayorDebe += $debe;
        $mayorHaber += $haber;
    }
}
ksort($mayorDia);

// ---------------------------------------------------------------------------
// 4) CC por día desde climov (signo por tipo de comprobante)
// ---------------------------------------------------------------------------
$tiposHaberCc = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];

$ccDia = [];
$ccDebe = 0.0;
$ccHaber = 0.0;

foreach ($climovDia as $f) {
    $fecha = (int) ($f->cliv_fecha ?? 0);
    $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    $monto = (float) ($f->monto ?? 0);

    $ccDia[$fecha] ??= ['debe' => 0.0, 'haber' => 0.0, 'n' => 0, 'tipos' => []];
    if (in_array($tipo, $tiposHaberCc, true)) {
        $ccDia[$fecha]['haber'] += $monto;
        $ccHaber += $monto;
    } else {
        $ccDia[$fecha]['debe'] += $monto;
        $ccDebe += $monto;
    }
    $ccDia[$fecha]['n'] += (int) ($f->n ?? 0);
    $ccDia[$fecha]['tipos'][$tipo] = round(($ccDia[$fecha]['tipos'][$tipo] ?? 0) + $monto, 2);
}
ksort($ccDia);

echo "=== TOTALES HISTÓRICOS ===\n";
echo 'MAYOR  Debe='.$fmt($mayorDebe).' Haber='.$fmt($mayorHaber).' Neto='.$fmt($mayorDebe - $mayorHaber)."\n";
echo 'CC     Debe='.$fmt($ccDebe).' Haber='.$fmt($ccHaber).' Neto='.$fmt($ccDebe - $ccHaber)."\n";
echo 'DIFF (CC - MAYOR) = '.$fmt(($ccDebe - $ccHaber) - ($mayorDebe - $mayorHaber))."\n\n";

// ---------------------------------------------------------------------------
// 5) Días con diferencia (foco del descalce)
// ---------------------------------------------------------------------------
$fechas = array_unique(array_merge(array_keys($mayorDia), array_keys($ccDia)));
sort($fechas);

$difs = [];
foreach ($fechas as $fecha) {
    $netoMayor = ($mayorDia[$fecha]['debe'] ?? 0) - ($mayorDia[$fecha]['haber'] ?? 0);
    $netoCc = ($ccDia[$fecha]['debe'] ?? 0) - ($ccDia[$fecha]['haber'] ?? 0);
    $diff = round($netoCc - $netoMayor, 2);
    if (abs($diff) > 0.009) {
        $difs[] = ['fecha' => $fecha, 'cc' => $netoCc, 'mayor' => $netoMayor, 'diff' => $diff];
    }
}

echo '=== DÍAS CON DIFERENCIA (CC - MAYOR) === total dias con diff: '.count($difs)."\n";
$ultimos = array_slice($difs, -30);
foreach ($ultimos as $d) {
    echo sprintf(
        "  %d  CC=%14s  MAYOR=%14s  DIFF=%14s\n",
        $d['fecha'],
        $fmt($d['cc']),
        $fmt($d['mayor']),
        $fmt($d['diff']),
    );
}

// ---------------------------------------------------------------------------
// 6) Búsqueda del importe reportado
// ---------------------------------------------------------------------------
$objetivo = 3732.89;
echo "\n=== DÍAS CUYA DIFERENCIA COINCIDE CON {$objetivo} ===\n";
foreach ($difs as $d) {
    if (abs(abs($d['diff']) - $objetivo) < 0.02) {
        echo sprintf("  %d  DIFF=%s  (CC=%s MAYOR=%s)\n", $d['fecha'], $fmt($d['diff']), $fmt($d['cc']), $fmt($d['mayor']));
    }
}

echo "\n=== LÍNEAS DE SUBDIARIO CON IMPORTE = {$objetivo} ===\n";
$hits = 0;
foreach ($subdiario as $f) {
    if (abs(abs((float) ($f->subd_importe ?? 0)) - $objetivo) < 0.02) {
        $hits++;
        echo sprintf(
            "  %s  %s-%s-%s-%s  mov=%s cta=%s contra=%s imp=%s  %s\n",
            (string) ($f->subd_fecha ?? ''),
            trim((string) ($f->subd_tipo ?? '')),
            trim((string) ($f->subd_letra ?? '')),
            trim((string) ($f->subd_sucursal ?? '')),
            trim((string) ($f->subd_nro ?? '')),
            trim((string) ($f->subd_tipo_mov ?? '')),
            trim((string) ($f->subd_cuenta ?? '')),
            trim((string) ($f->subd_contrapartida ?? '')),
            $fmt((float) ($f->subd_importe ?? 0)),
            trim((string) ($f->subd_desc_mov ?? '')),
        );
        if ($hits >= 40) {
            echo "  … (corte a 40)\n";
            break;
        }
    }
}
if ($hits === 0) {
    echo "  (ninguna)\n";
}

echo "\nCache en: {$cacheDir}\n";
