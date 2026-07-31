<?php

/**
 * Cruce CC (climov/aplmov) vs mayor subdiario (ventas) con regla subd_tipo_mov.
 *
 * Uso: php scripts/probe_cc_vs_mayor_3007.php [fecha=20260730] [cuenta=113100000]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$fecha = (int) ($argv[1] ?? 20260730);
$cuenta = (int) ($argv[2] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$sistemaSub = 'ventas';
$api = new ApiAnita();
$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$tol = 0.05;

function listar(ApiAnita $api, string $sistema, string $tabla, string $campos, string $where, int $intentos = 8): array
{
    for ($i = 1; $i <= $intentos; $i++) {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        $filas = ApiAnita::decodificarListaFilas($raw);
        if ($filas !== [] || $err !== null) {
            if ($err !== null) {
                fwrite(STDERR, "ERROR {$sistema}/{$tabla}: {$err}\n");
            }

            return $filas;
        }
        usleep(200000);
    }

    return [];
}

function claveComp(string $tipo, string $letra, $suc, $nro): string
{
    return strtoupper(trim($tipo)).'|'
        .strtoupper(trim($letra)).'|'
        .(int) $suc.'|'
        .(int) $nro;
}

echo "fecha={$fecha} cuenta={$cuenta} subdiario_sistema={$sistemaSub}\n\n";

$subdiario = listar(
    $api,
    $sistemaSub,
    'subdiario',
    'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_emisor,subd_desc_mov',
    ' WHERE subd_fecha = '.$fecha,
);
$climov = listar(
    $api,
    'ventas',
    'climov',
    'cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_monto,cliv_t_cobrado,cliv_estado,cliv_ref_tipo,cliv_ref_letra,cliv_ref_sucursal,cliv_ref_nro',
    ' WHERE cliv_fecha = '.$fecha,
);
$aplmov = listar(
    $api,
    'ventas',
    'aplmov',
    'aplv_tipo,aplv_letra,aplv_sucursal,aplv_nro,aplv_monto,aplv_tipo_cob,aplv_letra_cob,aplv_sucursal_cob,aplv_nro_cob,aplv_fecha,aplv_fecha_aplic',
    ' WHERE aplv_fecha = '.$fecha.' OR aplv_fecha_aplic = '.$fecha,
);

echo 'filas: subdiario='.count($subdiario).' climov='.count($climov).' aplmov='.count($aplmov)."\n";

// --- Mayor desde subdiario (regla tipo_mov: cuenta + contrapartida) ---
$mayorPorComp = [];
$mayorDebe = 0.0;
$mayorHaber = 0.0;
foreach ($subdiario as $f) {
    $clave = claveComp(
        (string) ($f->subd_tipo ?? ''),
        (string) ($f->subd_letra ?? ''),
        $f->subd_sucursal ?? 0,
        $f->subd_nro ?? 0,
    );
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        if ((int) $imp['cuenta'] !== $cuenta) {
            continue;
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        if (! isset($mayorPorComp[$clave])) {
            $mayorPorComp[$clave] = [
                'tipo' => strtoupper(trim((string) ($f->subd_tipo ?? ''))),
                'debe' => 0.0,
                'haber' => 0.0,
                'n' => 0,
                'lado' => ((int) ($f->subd_cuenta ?? 0) === $cuenta) ? 'cuenta' : 'contra',
            ];
        }
        $mayorPorComp[$clave]['debe'] += $dh['debe'];
        $mayorPorComp[$clave]['haber'] += $dh['haber'];
        $mayorPorComp[$clave]['n']++;
        $mayorDebe += $dh['debe'];
        $mayorHaber += $dh['haber'];
    }
}

echo "\n=== MAYOR subdiario→{$cuenta} (subd_cuenta + subd_contrapartida vía tipo_mov) ===\n";
echo 'Debe='.$fmt($mayorDebe).' Haber='.$fmt($mayorHaber).' Neto(D-H)='.$fmt($mayorDebe - $mayorHaber).' comps='.count($mayorPorComp)."\n";

// --- CC climov: neto por comprobante (monto; cobros/NC restan) ---
$tiposDebeCc = ['FAC', 'FAK', 'FAB', 'FAS', 'NDD', 'NDE', 'DEB', 'LIQ', 'TKT', 'APA'];
$tiposHaberCc = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];

$ccPorComp = [];
$ccDebe = 0.0;
$ccHaber = 0.0;
foreach ($climov as $f) {
    $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    $clave = claveComp($tipo, (string) ($f->cliv_letra ?? ''), $f->cliv_sucursal ?? 0, $f->cliv_nro ?? 0);
    $monto = (float) ($f->cliv_monto ?? 0);
    if (! isset($ccPorComp[$clave])) {
        $ccPorComp[$clave] = ['tipo' => $tipo, 'monto' => 0.0, 'cobrado' => 0.0, 'n' => 0, 'estado' => (string) ($f->cliv_estado ?? '')];
    }
    $ccPorComp[$clave]['monto'] += $monto;
    $ccPorComp[$clave]['cobrado'] += (float) ($f->cliv_t_cobrado ?? 0);
    $ccPorComp[$clave]['n']++;

    if (in_array($tipo, $tiposHaberCc, true)) {
        $ccHaber += $monto;
    } else {
        $ccDebe += $monto;
    }
}

echo "\n=== CC climov (monto del día; Haber=COB/NC*) ===\n";
echo 'Debe='.$fmt($ccDebe).' Haber='.$fmt($ccHaber).' Neto(D-H)='.$fmt($ccDebe - $ccHaber).' comps='.count($ccPorComp)."\n";
echo 'Diff neto CC-Mayor: '.$fmt(($ccDebe - $ccHaber) - ($mayorDebe - $mayorHaber))."\n";

// --- Comparación por comprobante: FAC/NCD/NCP (docs que cargan CC) vs mayor ---
$tiposCruce = ['FAC', 'FAK', 'FAB', 'FAS', 'NCD', 'NCK', 'NCP', 'NDD', 'COB', 'COA', 'APA'];
$diffs = [];
$soloCc = [];
$soloMayor = [];

$claves = array_unique(array_merge(array_keys($ccPorComp), array_keys($mayorPorComp)));
foreach ($claves as $clave) {
    $cc = $ccPorComp[$clave] ?? null;
    $my = $mayorPorComp[$clave] ?? null;
    $tipo = $cc['tipo'] ?? $my['tipo'] ?? '';
    if (! in_array($tipo, $tiposCruce, true)) {
        continue;
    }

    // En mayor: neto D-H (deudor sube con debe)
    $netoMayor = $my ? (($my['debe'] - $my['haber'])) : 0.0;
    // En CC: FAC/APA suman; COB/NC restan
    $netoCc = 0.0;
    if ($cc) {
        if (in_array($tipo, $tiposHaberCc, true)) {
            $netoCc = -$cc['monto'];
        } else {
            $netoCc = $cc['monto'];
        }
    }

    if ($cc === null) {
        $soloMayor[] = ['clave' => $clave, 'neto_mayor' => $netoMayor, 'tipo' => $tipo];
        continue;
    }
    if ($my === null) {
        $soloCc[] = ['clave' => $clave, 'neto_cc' => $netoCc, 'tipo' => $tipo, 'monto' => $cc['monto']];
        continue;
    }

    $diff = round($netoCc - $netoMayor, 2);
    if (abs($diff) > $tol) {
        $diffs[] = [
            'clave' => $clave,
            'tipo' => $tipo,
            'neto_cc' => $netoCc,
            'neto_mayor' => $netoMayor,
            'diff' => $diff,
            'mayor_debe' => $my['debe'],
            'mayor_haber' => $my['haber'],
            'cc_monto' => $cc['monto'],
        ];
    }
}

usort($diffs, static fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));

echo "\n=== Diffs por comprobante (|cc-mayor| > {$tol}) ===\n";
echo 'cantidad='.count($diffs)."\n";
$sumaDiff = 0.0;
foreach (array_slice($diffs, 0, 40) as $d) {
    $sumaDiff += $d['diff'];
    echo sprintf(
        "  %-22s CC=%s MAY=%s DIFF=%s (D=%s H=%s)\n",
        $d['clave'],
        $fmt($d['neto_cc']),
        $fmt($d['neto_mayor']),
        $fmt($d['diff']),
        $fmt($d['mayor_debe']),
        $fmt($d['mayor_haber']),
    );
}
if (count($diffs) > 40) {
    echo '  … +'.(count($diffs) - 40)." más\n";
}
echo 'Suma |diff| top: ver total abajo. Suma diff todos: ';
$totalDiff = 0.0;
foreach ($diffs as $d) {
    $totalDiff += $d['diff'];
}
echo $fmt($totalDiff)."\n";

echo "\n=== Solo en climov (sin mayor) ===\n";
echo 'cantidad='.count($soloCc)."\n";
$sumaSoloCc = 0.0;
foreach (array_slice($soloCc, 0, 25) as $d) {
    $sumaSoloCc += $d['neto_cc'];
    echo sprintf("  %-22s neto_cc=%s\n", $d['clave'], $fmt($d['neto_cc']));
}
foreach ($soloCc as $d) {
    $sumaSoloCc = ($sumaSoloCc === 0.0 && count($soloCc) > 25) ? array_sum(array_column($soloCc, 'neto_cc')) : $sumaSoloCc;
}
$sumaSoloCc = array_sum(array_map(static fn ($d) => $d['neto_cc'], $soloCc));
echo 'Suma solo CC: '.$fmt($sumaSoloCc)."\n";

echo "\n=== Solo en mayor (sin climov) ===\n";
echo 'cantidad='.count($soloMayor)."\n";
$sumaSoloMayor = array_sum(array_map(static fn ($d) => $d['neto_mayor'], $soloMayor));
foreach (array_slice($soloMayor, 0, 25) as $d) {
    echo sprintf("  %-22s neto_mayor=%s\n", $d['clave'], $fmt($d['neto_mayor']));
}
echo 'Suma solo mayor: '.$fmt($sumaSoloMayor)."\n";

// Aplmov vs COB climov
$sumaApl = 0.0;
foreach ($aplmov as $a) {
    $sumaApl += (float) ($a->aplv_monto ?? 0);
}
$sumaCob = 0.0;
foreach ($climov as $f) {
    $t = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    if (in_array($t, ['COB', 'COA'], true)) {
        $sumaCob += (float) ($f->cliv_monto ?? 0);
    }
}
echo "\n=== aplmov vs COB/COA climov ===\n";
echo 'aplmov suma='.$fmt($sumaApl).' COB+COA climov='.$fmt($sumaCob).' diff='.$fmt($sumaCob - $sumaApl)."\n";

echo "\nResumen diff esperable ~15k: revisar diffs + solo_cc + solo_mayor\n";
echo 'Balance: (solo_cc + diffs) - solo_mayor ≈ '.$fmt($sumaSoloCc + $totalDiff - $sumaSoloMayor)."\n";
