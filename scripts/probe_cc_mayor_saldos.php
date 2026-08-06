<?php

/**
 * Compara el SALDO del mayor de la cuenta de deudores (subdiario cacheado) contra la
 * DEUDA pendiente de cuenta corriente (climov agregado), y ubica el día del descalce.
 *
 * Uso: php scripts/probe_cc_mayor_saldos.php [cuenta=113100000] [--refrescar]
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
$cacheDir = storage_path('app/probe_cc_mayor');
$objetivo = 3732.89;

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

// --- Subdiario cacheado ---
$subdiario = [];
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $fila) {
        $subdiario[] = $fila;
    }
}
echo 'subdiario cacheado: '.count($subdiario)." lineas\n";

// --- climov: pendiente por tipo (una llamada agregada) ---
$archivoPend = $cacheDir.'/climov_pendiente_por_tipo.json';
if (! $refrescar && is_readable($archivoPend)) {
    $pend = json_decode((string) file_get_contents($archivoPend), false) ?: [];
    echo 'climov pendiente: cache ('.count($pend)." tipos)\n";
} else {
    $api = new ApiAnita();
    $pend = [];
    for ($i = 1; $i <= 6; $i++) {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'climov',
            'campos' => 'cliv_tipo,SUM(cliv_monto) AS monto,SUM(cliv_t_cobrado) AS cobrado,COUNT(*) AS n',
            'groupBy' => 'cliv_tipo',
        ]);
        if (ApiAnita::extraerMensajeError($raw) === null) {
            $pend = ApiAnita::decodificarListaFilas($raw);
            if ($pend !== []) {
                break;
            }
        }
        usleep(300000);
    }
    file_put_contents($archivoPend, json_encode($pend));
    echo 'climov pendiente: bridge ('.count($pend)." tipos)\n";
}

$tiposHaber = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];

echo "\n=== climov: monto vs cobrado por tipo ===\n";
$deudaPendiente = 0.0;
$netoMovimiento = 0.0;
foreach ($pend as $p) {
    $tipo = strtoupper(trim((string) ($p->cliv_tipo ?? '')));
    $monto = (float) ($p->monto ?? 0);
    $cobrado = (float) ($p->cobrado ?? 0);
    $signo = in_array($tipo, $tiposHaber, true) ? -1 : 1;
    $deudaPendiente += $signo * ($monto - $cobrado);
    $netoMovimiento += $signo * $monto;
    echo sprintf(
        "  %-5s n=%-8s monto=%18s cobrado=%18s pendiente=%18s\n",
        $tipo,
        (string) ($p->n ?? ''),
        $fmt($monto),
        $fmt($cobrado),
        $fmt($monto - $cobrado),
    );
}

// --- Mayor: saldo acumulado y por día ---
$mayorDia = [];
$mayorNeto = 0.0;
foreach ($subdiario as $f) {
    $fecha = (int) ($f->subd_fecha ?? 0);
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $neto = (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
        $mayorDia[$fecha] = round(($mayorDia[$fecha] ?? 0) + $neto, 2);
        $mayorNeto += $neto;
    }
}
ksort($mayorDia);

echo "\n=== SALDOS ===\n";
echo 'MAYOR '.$cuenta.' (acumulado subdiario) = '.$fmt($mayorNeto)."\n";
echo 'CC deuda pendiente (monto - cobrado)   = '.$fmt($deudaPendiente)."\n";
echo 'CC neto movimientos (monto)            = '.$fmt($netoMovimiento)."\n";
echo 'DIFF (mayor - deuda pendiente)         = '.$fmt($mayorNeto - $deudaPendiente)."\n";
echo 'DIFF (mayor - neto movimientos)        = '.$fmt($mayorNeto - $netoMovimiento)."\n";

echo "\n=== MAYOR: primeros y últimos días con movimiento ===\n";
$fechas = array_keys($mayorDia);
foreach (array_slice($fechas, 0, 5) as $f) {
    echo sprintf("  %8d  %s\n", $f, $fmt($mayorDia[$f]));
}
echo "  …\n";
foreach (array_slice($fechas, -10) as $f) {
    echo sprintf("  %8d  %s\n", $f, $fmt($mayorDia[$f]));
}

// --- ¿Hay un día cuyo neto de mayor sea exactamente el objetivo? ---
echo "\n=== DÍAS CON NETO DE MAYOR = {$objetivo} ===\n";
foreach ($mayorDia as $f => $neto) {
    if (abs(abs($neto) - $objetivo) < 0.011) {
        echo sprintf("  %8d  %s\n", $f, $fmt($neto));
    }
}

// --- Líneas de subdiario cuyo importe cae cerca del objetivo ---
echo "\n=== LÍNEAS SUBDIARIO CON IMPORTE ≈ {$objetivo} (todo el historial) ===\n";
$n = 0;
foreach ($subdiario as $f) {
    if (abs(abs((float) ($f->subd_importe ?? 0)) - $objetivo) < 0.011) {
        $n++;
        echo sprintf(
            "  %s %s-%s-%s-%s mov=%s cta=%s contra=%s imp=%s %s\n",
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
    }
}
if ($n === 0) {
    echo "  (ninguna)\n";
}

// --- Descomposición del mayor por tipo de comprobante ---
echo "\n=== MAYOR por tipo de comprobante ===\n";
$porTipo = [];
foreach ($subdiario as $f) {
    $tipo = strtoupper(trim((string) ($f->subd_tipo ?? ''))) ?: '(vacio)';
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $porTipo[$tipo] ??= ['debe' => 0.0, 'haber' => 0.0, 'n' => 0];
        $porTipo[$tipo]['debe'] += (float) ($dh['debe'] ?? 0);
        $porTipo[$tipo]['haber'] += (float) ($dh['haber'] ?? 0);
        $porTipo[$tipo]['n']++;
    }
}
uasort($porTipo, static fn ($a, $b) => abs($b['debe'] - $b['haber']) <=> abs($a['debe'] - $a['haber']));
foreach ($porTipo as $tipo => $v) {
    echo sprintf(
        "  %-8s n=%-7d Debe=%18s Haber=%18s Neto=%18s\n",
        $tipo,
        $v['n'],
        $fmt($v['debe']),
        $fmt($v['haber']),
        $fmt($v['debe'] - $v['haber']),
    );
}
