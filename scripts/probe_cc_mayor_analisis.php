<?php

/**
 * Analiza el descalce CC (climov) vs mayor (subdiario) por comprobante usando el cache
 * ya descargado por probe_cc_vs_mayor_cuenta_full.php (no vuelve a leer subdiario del bridge).
 *
 * Uso: php scripts/probe_cc_mayor_analisis.php [desde=20260801] [hasta=20260806] [cuenta=113100000]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$desde = (int) ($argv[1] ?? 20260801);
$hasta = (int) ($argv[2] ?? 20260806);
$cuenta = (int) ($argv[3] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$cacheDir = storage_path('app/probe_cc_mayor');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$clave = static fn (string $tipo, string $letra, $suc, $nro): string => strtoupper(trim($tipo)).'|'
    .strtoupper(trim($letra)).'|'.(int) $suc.'|'.(int) $nro;

// --- Subdiario desde cache (por año) ---
$subdiario = [];
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $fila) {
        $fecha = (int) ($fila->subd_fecha ?? 0);
        if ($fecha >= $desde && $fecha <= $hasta) {
            $subdiario[] = $fila;
        }
    }
}
echo "subdiario cacheado en rango {$desde}-{$hasta}: ".count($subdiario)." lineas\n";

// --- climov detalle del rango (una sola llamada) ---
$archivoClimov = $cacheDir.'/climov_detalle_'.$desde.'_'.$hasta.'.json';
if (is_readable($archivoClimov)) {
    $climov = json_decode((string) file_get_contents($archivoClimov), false) ?: [];
    echo 'climov cache: '.count($climov)." filas\n";
} else {
    $api = new ApiAnita();
    $climov = [];
    for ($i = 1; $i <= 6; $i++) {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'climov',
            'campos' => 'cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_fecha,'
                .'cliv_monto,cliv_t_cobrado,cliv_estado,cliv_cod_mon,cliv_cotizacion',
            'whereArmado' => ' WHERE cliv_fecha BETWEEN '.$desde.' AND '.$hasta,
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            fwrite(STDERR, "ERROR climov: {$err}\n");
        } else {
            $climov = ApiAnita::decodificarListaFilas($raw);
            if ($climov !== []) {
                break;
            }
        }
        usleep(300000);
    }
    file_put_contents($archivoClimov, json_encode($climov));
    echo 'climov bridge: '.count($climov)." filas\n";
}

// --- Mayor por comprobante ---
$mayor = [];
foreach ($subdiario as $f) {
    $k = $clave((string) ($f->subd_tipo ?? ''), (string) ($f->subd_letra ?? ''), $f->subd_sucursal ?? 0, $f->subd_nro ?? 0);
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $mayor[$k] ??= ['fecha' => (int) ($f->subd_fecha ?? 0), 'debe' => 0.0, 'haber' => 0.0, 'n' => 0];
        $mayor[$k]['debe'] += (float) ($dh['debe'] ?? 0);
        $mayor[$k]['haber'] += (float) ($dh['haber'] ?? 0);
        $mayor[$k]['n']++;
    }
}

// --- CC por comprobante ---
$tiposHaberCc = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];
$cc = [];
foreach ($climov as $f) {
    $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    $k = $clave($tipo, (string) ($f->cliv_letra ?? ''), $f->cliv_sucursal ?? 0, $f->cliv_nro ?? 0);
    $monto = (float) ($f->cliv_monto ?? 0);
    $cc[$k] ??= [
        'fecha' => (int) ($f->cliv_fecha ?? 0),
        'tipo' => $tipo,
        'neto' => 0.0,
        'cliente' => (string) ($f->cliv_cliente ?? ''),
        'estado' => (string) ($f->cliv_estado ?? ''),
        'n' => 0,
    ];
    $cc[$k]['neto'] += in_array($tipo, $tiposHaberCc, true) ? -$monto : $monto;
    $cc[$k]['n']++;
}

// --- Cruce ---
$objetivo = 3732.89;
$filas = [];
foreach (array_unique(array_merge(array_keys($cc), array_keys($mayor))) as $k) {
    $netoCc = $cc[$k]['neto'] ?? 0.0;
    $netoMayor = ($mayor[$k]['debe'] ?? 0) - ($mayor[$k]['haber'] ?? 0);
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

echo "\n=== COMPROBANTES CON DIFERENCIA (CC - MAYOR) ===\n";
echo 'total='.count($filas).'  suma_diff='.$fmt(array_sum(array_column($filas, 'diff')))."\n\n";
foreach (array_slice($filas, 0, 60) as $d) {
    echo sprintf(
        "  %-11s %8d %-22s cli=%-8s CC=%14s MAY=%14s DIFF=%14s\n",
        $d['estado'],
        $d['fecha'],
        $d['clave'],
        $d['cliente'],
        $fmt($d['cc']),
        $fmt($d['mayor']),
        $fmt($d['diff']),
    );
}
if (count($filas) > 60) {
    echo '  … +'.(count($filas) - 60)." más\n";
}

echo "\n=== COINCIDENCIAS EXACTAS CON {$objetivo} ===\n";
$hay = false;
foreach ($filas as $d) {
    if (abs(abs($d['diff']) - $objetivo) < 0.011) {
        $hay = true;
        echo sprintf(
            "  %-11s %8d %-22s cli=%-8s CC=%s MAY=%s DIFF=%s\n",
            $d['estado'],
            $d['fecha'],
            $d['clave'],
            $d['cliente'],
            $fmt($d['cc']),
            $fmt($d['mayor']),
            $fmt($d['diff']),
        );
    }
}
if (! $hay) {
    echo "  (ninguna a nivel comprobante)\n";
}

// --- Combinaciones de diferencias por día que sumen el objetivo ---
echo "\n=== DIFERENCIA NETA POR DÍA ===\n";
$porDia = [];
foreach ($filas as $d) {
    $porDia[$d['fecha']] = round(($porDia[$d['fecha']] ?? 0) + $d['diff'], 2);
}
ksort($porDia);
foreach ($porDia as $fecha => $suma) {
    echo sprintf("  %8d  %s%s\n", $fecha, $fmt($suma), abs(abs($suma) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '');
}
