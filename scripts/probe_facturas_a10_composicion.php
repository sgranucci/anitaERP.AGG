<?php

/**
 * Control de composición contable de las facturas A-10: verifica que deudores = ventas + IVA
 * + percepciones y que las alícuotas de IVA y percepción caigan en las bandas esperadas.
 *
 * Uso: php scripts/probe_facturas_a10_composicion.php [sucursal=10] [letra=A]
 */

declare(strict_types=1);

use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$sucursal = (int) ($argv[1] ?? 10);
$letra = strtoupper((string) ($argv[2] ?? 'A'));
$cacheDir = storage_path('app/probe_cc_mayor');

$deudores = (int) config('cliente.DEUDORES_POR_VENTAS');
$ctaVenta = (int) config('facturacion.CUENTACONTABLE_VENTA');
$ctaIva = (int) config('facturacion.CUENTACONTABLE_IVA');
$ctaPercepIva = (int) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA');
$tasaPercep = (float) env('ANITA_TASA_PERCEPCION_IVA', 3);

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

$subdiario = json_decode((string) file_get_contents($cacheDir.'/subdiario_fac_'.$letra.'_'.$sucursal.'.json'), false) ?: [];

$comp = [];
foreach ($subdiario as $f) {
    $nro = (int) ($f->subd_nro ?? 0);
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        $cta = (int) $imp['cuenta'];
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $neto = (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
        $comp[$nro]['fecha'] ??= (int) ($f->subd_fecha ?? 0);
        $comp[$nro]['ctas'][$cta] = round(($comp[$nro]['ctas'][$cta] ?? 0) + $neto, 2);
    }
}

$anomalias = [];
$ok = 0;
$analizadas = 0;

foreach ($comp as $nro => $d) {
    $ctas = $d['ctas'];
    $deb = (float) ($ctas[$deudores] ?? 0);
    if ($deb <= 0.009) {
        continue; // no es venta en cuenta corriente
    }
    $analizadas++;

    $base = -(float) ($ctas[$ctaVenta] ?? 0);
    $iva = -(float) ($ctas[$ctaIva] ?? 0);
    $percepIva = -(float) ($ctas[$ctaPercepIva] ?? 0);

    $otras = 0.0;
    $otrasCtas = [];
    foreach ($ctas as $cta => $v) {
        if (in_array((int) $cta, [$deudores, $ctaVenta, $ctaIva, $ctaPercepIva], true)) {
            continue;
        }
        $otras += -(float) $v;
        $otrasCtas[] = $cta;
    }

    $motivos = [];

    // 1) La suma de las cuentas de crédito debe dar el importe a deudores.
    $suma = round($base + $iva + $percepIva + $otras, 2);
    if (abs($suma - round($deb, 2)) > 0.011) {
        $motivos[] = 'composicion≠deudores (dif '.$fmt($suma - $deb).')';
    }

    // 2) Alícuota de IVA dentro de las bandas legales.
    if ($base > 0.01) {
        $alic = $iva / $base * 100;
        if ($iva > 0.01 && ($alic < 9.5 || ($alic > 11.5 && $alic < 20.0) || $alic > 21.9)) {
            $motivos[] = 'alicuota IVA '.number_format($alic, 2, ',', '.').'%';
        }
        // 3) Percepción de IVA a la tasa configurada.
        if ($percepIva > 0.01) {
            $tasa = $percepIva / $base * 100;
            if (abs($tasa - $tasaPercep) > 0.15) {
                $motivos[] = 'percep IVA '.number_format($tasa, 2, ',', '.').'% (esperada '.$tasaPercep.'%)';
            }
        }
    } elseif ($iva > 0.01) {
        $motivos[] = 'IVA sin base de venta';
    }

    // 4) Toda factura en cuenta corriente debe tener venta imputada.
    if ($base <= 0.01) {
        $motivos[] = 'sin cuenta de venta '.$ctaVenta;
    }

    if ($motivos === []) {
        $ok++;
        continue;
    }

    $anomalias[] = [
        'nro' => $nro,
        'fecha' => $d['fecha'],
        'deudores' => $deb,
        'base' => $base,
        'iva' => $iva,
        'percep' => $percepIva,
        'otras' => $otras,
        'otras_ctas' => $otrasCtas,
        'motivos' => $motivos,
    ];
}

echo "Facturas {$letra}-{$sucursal} en cuenta corriente analizadas: {$analizadas}  OK: {$ok}  con observaciones: ".count($anomalias)."\n\n";

foreach ($anomalias as $a) {
    echo sprintf(
        "  nro=%-6s %s deudores=%14s venta=%14s iva=%13s percep=%11s otras=%11s [%s]\n     -> %s\n",
        $a['nro'],
        (string) $a['fecha'],
        $fmt($a['deudores']),
        $fmt($a['base']),
        $fmt($a['iva']),
        $fmt($a['percep']),
        $fmt($a['otras']),
        implode(',', $a['otras_ctas']),
        implode('; ', $a['motivos']),
    );
}
