<?php

/**
 * Drill-down sobre el cache de subdiario: detalle de un comprobante y cruce de comprobantes
 * que cargan cuenta corriente (FAC/NC/ND) contra su imputación a la cuenta de deudores.
 *
 * Uso: php scripts/probe_cc_mayor_drill.php [desde=20260801] [hasta=20260806] [cuenta=113100000]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$desde = (int) ($argv[1] ?? 20260801);
$hasta = (int) ($argv[2] ?? 20260806);
$cuenta = (int) ($argv[3] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$cacheDir = storage_path('app/probe_cc_mayor');
$objetivo = 3732.89;

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$clave = static fn (string $tipo, string $letra, $suc, $nro): string => strtoupper(trim($tipo)).'|'
    .strtoupper(trim($letra)).'|'.(int) $suc.'|'.(int) $nro;

$subdiario = [];
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $fila) {
        $fecha = (int) ($fila->subd_fecha ?? 0);
        if ($fecha >= $desde && $fecha <= $hasta) {
            $subdiario[] = $fila;
        }
    }
}

$archivoClimov = $cacheDir.'/climov_detalle_'.$desde.'_'.$hasta.'.json';
$climov = is_readable($archivoClimov) ? (json_decode((string) file_get_contents($archivoClimov), false) ?: []) : [];

echo 'subdiario='.count($subdiario).' climov='.count($climov)."\n";

// ---------------------------------------------------------------------------
// 1) Detalle completo de los comprobantes con una línea igual al importe buscado
// ---------------------------------------------------------------------------
$sospechosos = [];
foreach ($subdiario as $f) {
    if (abs(abs((float) ($f->subd_importe ?? 0)) - $objetivo) < 0.02) {
        $sospechosos[$clave((string) ($f->subd_tipo ?? ''), (string) ($f->subd_letra ?? ''), $f->subd_sucursal ?? 0, $f->subd_nro ?? 0)] = true;
    }
}

foreach (array_keys($sospechosos) as $k) {
    echo "\n=== DETALLE SUBDIARIO {$k} ===\n";
    $debe = 0.0;
    $haber = 0.0;
    foreach ($subdiario as $f) {
        $kk = $clave((string) ($f->subd_tipo ?? ''), (string) ($f->subd_letra ?? ''), $f->subd_sucursal ?? 0, $f->subd_nro ?? 0);
        if ($kk !== $k) {
            continue;
        }
        echo sprintf(
            "  %s mov=%s cta=%-10s contra=%-10s imp=%14s  emisor=%s  %s\n",
            (string) ($f->subd_fecha ?? ''),
            trim((string) ($f->subd_tipo_mov ?? '')),
            trim((string) ($f->subd_cuenta ?? '')),
            trim((string) ($f->subd_contrapartida ?? '')),
            $fmt((float) ($f->subd_importe ?? 0)),
            trim((string) ($f->subd_emisor ?? '')),
            trim((string) ($f->subd_desc_mov ?? '')),
        );
        foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
            if ((int) $imp['cuenta'] !== $cuenta) {
                continue;
            }
            $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
            $debe += (float) ($dh['debe'] ?? 0);
            $haber += (float) ($dh['haber'] ?? 0);
        }
    }
    echo '  --> mayor '.$cuenta.': Debe='.$fmt($debe).' Haber='.$fmt($haber).' Neto='.$fmt($debe - $haber)."\n";

    foreach ($climov as $c) {
        $kk = $clave((string) ($c->cliv_tipo ?? ''), (string) ($c->cliv_letra ?? ''), $c->cliv_sucursal ?? 0, $c->cliv_nro ?? 0);
        if ($kk === $k) {
            echo '  --> climov: cliente='.trim((string) ($c->cliv_cliente ?? ''))
                .' fecha='.(string) ($c->cliv_fecha ?? '')
                .' monto='.$fmt((float) ($c->cliv_monto ?? 0))
                .' cobrado='.$fmt((float) ($c->cliv_t_cobrado ?? 0))
                .' estado='.trim((string) ($c->cliv_estado ?? ''))."\n";
        }
    }
}

// ---------------------------------------------------------------------------
// 2) Cruce solo de comprobantes que cargan CC (facturas, notas de débito/crédito)
// ---------------------------------------------------------------------------
$tiposDoc = ['FAC', 'FAK', 'FAB', 'FAS', 'NDD', 'NDE', 'NDI', 'DEB', 'NCD', 'NCK', 'NCE', 'NCP'];
$tiposHaber = ['NCD', 'NCK', 'NCE', 'NCP'];

$mayor = [];
foreach ($subdiario as $f) {
    $tipo = strtoupper(trim((string) ($f->subd_tipo ?? '')));
    if (! in_array($tipo, $tiposDoc, true)) {
        continue;
    }
    $k = $clave($tipo, (string) ($f->subd_letra ?? ''), $f->subd_sucursal ?? 0, $f->subd_nro ?? 0);
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $mayor[$k] ??= ['fecha' => (int) ($f->subd_fecha ?? 0), 'neto' => 0.0];
        $mayor[$k]['neto'] += (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
    }
}

$cc = [];
foreach ($climov as $c) {
    $tipo = strtoupper(trim((string) ($c->cliv_tipo ?? '')));
    if (! in_array($tipo, $tiposDoc, true)) {
        continue;
    }
    $k = $clave($tipo, (string) ($c->cliv_letra ?? ''), $c->cliv_sucursal ?? 0, $c->cliv_nro ?? 0);
    $monto = (float) ($c->cliv_monto ?? 0);
    $cc[$k] ??= ['fecha' => (int) ($c->cliv_fecha ?? 0), 'cliente' => trim((string) ($c->cliv_cliente ?? '')), 'neto' => 0.0];
    $cc[$k]['neto'] += in_array($tipo, $tiposHaber, true) ? -$monto : $monto;
}

$filas = [];
foreach (array_unique(array_merge(array_keys($cc), array_keys($mayor))) as $k) {
    $netoCc = $cc[$k]['neto'] ?? 0.0;
    $netoMayor = $mayor[$k]['neto'] ?? 0.0;
    $diff = round($netoCc - $netoMayor, 2);
    if (abs($diff) <= 0.009) {
        continue;
    }
    $filas[] = [
        'clave' => $k,
        'fecha' => $cc[$k]['fecha'] ?? $mayor[$k]['fecha'] ?? 0,
        'cliente' => $cc[$k]['cliente'] ?? '',
        'cc' => $netoCc,
        'mayor' => $netoMayor,
        'diff' => $diff,
        'estado' => isset($cc[$k]) ? (isset($mayor[$k]) ? 'DIFF' : 'SOLO_CC') : 'SOLO_MAYOR',
    ];
}
usort($filas, static fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));

echo "\n=== COMPROBANTES DE CC (FAC/ND/NC) CON DIFERENCIA ===\n";
echo 'total='.count($filas).' suma='.$fmt(array_sum(array_column($filas, 'diff')))."\n";
foreach ($filas as $d) {
    echo sprintf(
        "  %-11s %8d %-20s cli=%-8s CC=%14s MAY=%14s DIFF=%14s%s\n",
        $d['estado'],
        $d['fecha'],
        $d['clave'],
        $d['cliente'],
        $fmt($d['cc']),
        $fmt($d['mayor']),
        $fmt($d['diff']),
        abs(abs($d['diff']) - $objetivo) < 0.011 ? '  <<< COINCIDE' : '',
    );
}

$porDia = [];
foreach ($filas as $d) {
    $porDia[$d['fecha']] = round(($porDia[$d['fecha']] ?? 0) + $d['diff'], 2);
}
ksort($porDia);
echo "\n=== NETO POR DÍA (solo comprobantes CC) ===\n";
foreach ($porDia as $fecha => $suma) {
    echo sprintf("  %8d  %s%s\n", $fecha, $fmt($suma), abs(abs($suma) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '');
}
