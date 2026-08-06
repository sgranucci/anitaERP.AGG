<?php

/**
 * Detecta líneas de subdiario imputadas a la cuenta de deudores que no tienen emisor
 * (cliente) asignado: entran al mayor pero no a la deuda de ningún cliente.
 *
 * Uso: php scripts/probe_subdiario_sin_emisor.php [cuenta=113100000] [desde=20260701]
 */

declare(strict_types=1);

use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$cuenta = (int) ($argv[1] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$desde = (int) ($argv[2] ?? 20260701);
$cacheDir = storage_path('app/probe_cc_mayor');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

$subdiario = [];
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $f) {
        $subdiario[] = $f;
    }
}
echo 'subdiario cuenta '.$cuenta.': '.count($subdiario)." lineas\n";

$porDia = [];
$totalSinEmisor = 0.0;
$nSinEmisor = 0;
$detalle = [];

foreach ($subdiario as $f) {
    // Solo las líneas que efectivamente imputan a la cuenta de deudores.
    $neto = 0.0;
    $toca = false;
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] === $cuenta) {
            $toca = true;
            $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
            $neto += (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
        }
    }
    if (! $toca) {
        continue;
    }

    $emisor = trim((string) ($f->subd_emisor ?? ''));
    if ($emisor !== '' && ltrim($emisor, '0') !== '') {
        continue;
    }

    $fecha = (int) ($f->subd_fecha ?? 0);
    $nSinEmisor++;
    $totalSinEmisor += $neto;
    $porDia[$fecha]['suma'] = round(($porDia[$fecha]['suma'] ?? 0) + $neto, 2);
    $porDia[$fecha]['n'] = ($porDia[$fecha]['n'] ?? 0) + 1;

    if ($fecha >= $desde) {
        $detalle[] = [
            'fecha' => $fecha,
            'comp' => trim((string) ($f->subd_tipo ?? '')).'-'.trim((string) ($f->subd_letra ?? ''))
                .'-'.(int) ($f->subd_sucursal ?? 0).'-'.(int) ($f->subd_nro ?? 0),
            'mov' => trim((string) ($f->subd_tipo_mov ?? '')),
            'cta' => (int) ($f->subd_cuenta ?? 0),
            'contra' => (int) ($f->subd_contrapartida ?? 0),
            'imp' => (float) ($f->subd_importe ?? 0),
            'neto' => $neto,
            'desc' => trim((string) ($f->subd_desc_mov ?? '')),
        ];
    }
}

ksort($porDia);

echo "\n=== LÍNEAS SIN EMISOR imputadas a {$cuenta} ===\n";
echo 'cantidad total='.$nSinEmisor.'  neto total='.$fmt($totalSinEmisor)."\n";

echo "\n=== Por día ===\n";
foreach ($porDia as $fecha => $v) {
    echo sprintf("  %8d  n=%-4d neto=%16s\n", $fecha, $v['n'], $fmt($v['suma']));
}

echo "\n=== Detalle desde {$desde} ===\n";
foreach ($detalle as $d) {
    echo sprintf(
        "  %8d %-16s mov=%s cta=%-10s contra=%-10s imp=%14s neto=%14s  desc='%s'\n",
        $d['fecha'],
        $d['comp'],
        $d['mov'],
        $d['cta'],
        $d['contra'],
        $fmt($d['imp']),
        $fmt($d['neto']),
        $d['desc'],
    );
}

// Comparación: ¿cómo viene el emisor en el resto de las líneas de percepción de IVA?
$ctaPercep = (int) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA');
$conEmisor = 0;
$sinEmisor = 0;
foreach ($subdiario as $f) {
    if ((int) ($f->subd_cuenta ?? 0) !== $ctaPercep) {
        continue;
    }
    $emisor = trim((string) ($f->subd_emisor ?? ''));
    if ($emisor === '' || ltrim($emisor, '0') === '') {
        $sinEmisor++;
    } else {
        $conEmisor++;
    }
}
echo "\n=== Líneas con subd_cuenta = {$ctaPercep} (percepción IVA) ===\n";
echo "  con emisor={$conEmisor}  sin emisor={$sinEmisor}\n";
