<?php

declare(strict_types=1);

/*
 * Corrección puntual jornadas 2026-07-08 y 2026-07-13 REBISCO (empresa 3), circuito FLASH gastronomía.
 *
 * Misma causa/patrón que el fix de REBISCO 16/7 y BIYEMAS 3-6/7: 1 NC de cortesía ($0,01) por día
 * quedó con venta.total positivo en el monto_facturacion_turno / asientos / mayor ya posteados,
 * inflando facturación en 0,02 respecto del flash (que sí la netea con total_z - tot_nc).
 * Las NC de julio ya se corrigieron en venta/venta_impuesto (venta.total -0,01).
 *
 *   8/7  NC #72971 (jornada, PV cfg 7, turno Noche):
 *     - Asiento factura_día #38588 (nro 226585):
 *         413010001 VENTAS ALIMENTOS  -1.756.233,33 -> -1.756.233,31  (haber -0,02)
 *         521280004 DIFERENCIA DE CAJA         1,70 ->          1,68   (debe  -0,02)
 *     - Cierre Noche #506: monto_facturacion_turno 1.569.080,88 -> 1.569.080,86 (-0,02)
 *
 *   13/7 NC #83690 (jornada, PV cfg 7, turno Noche):
 *     - Asiento factura_día #38774 (nro 226640):
 *         413010001 VENTAS ALIMENTOS  -820.546,59 -> -820.546,57  (haber -0,02)
 *         521280004 DIFERENCIA DE CAJA       1,31 ->        1,29   (debe  -0,02)
 *     - Cierre Noche #581: monto_facturacion_turno 509.280,61 -> 509.280,59 (-0,02)
 *
 *   + Resync ctamov Anita de cada asiento => mayor baja 0,02 (solo por la cuenta de ventas).
 *
 * Resultado: facturación = asientos = mayor = flash en ambos días.
 *
 * Uso:  php scripts/fix_cortesia_nc_rebisco_0813_julio.php          (dry-run)
 *       php scripts/fix_cortesia_nc_rebisco_0813_julio.php apply    (persiste ERP + ctamov Anita)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Contable\AsientoRepository;

$apply = in_array('apply', $argv, true);
$tol = 0.01;
$delta = 0.02; // 1 NC de cortesía x 0,02
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');

$casos = [
    [
        'dia' => '2026-07-08', 'nc' => 72971,
        'asiento' => 38588, 'ventas' => 40806, 'dif' => 40805, 'cierre' => 506,
        'ventasBase' => -1756233.33, 'difBase' => 1.70, 'cierreBase' => 1569080.88,
    ],
    [
        'dia' => '2026-07-13', 'nc' => 83690,
        'asiento' => 38774, 'ventas' => 41330, 'dif' => 41329, 'cierre' => 581,
        'ventasBase' => -820546.59, 'difBase' => 1.31, 'cierreBase' => 509280.61,
    ],
];

echo '=== Fix cortesía NC julio REBISCO 8/7 y 13/7 ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

$errores = [];
$plan = [];

foreach ($casos as $c) {
    $asiento = Asiento::with('asiento_movimientos.cuentacontables')->find($c['asiento']);
    if (! $asiento) { $errores[] = "No existe asiento #{$c['asiento']} ({$c['dia']})"; continue; }

    $byId = [];
    foreach ($asiento->asiento_movimientos as $m) { $byId[(int) $m->id] = $m; }

    $mVentas = $byId[$c['ventas']] ?? null;
    $mDif = $byId[$c['dif']] ?? null;
    if (! $mVentas) { $errores[] = "No existe línea VENTAS id={$c['ventas']} ({$c['dia']})"; }
    if (! $mDif) { $errores[] = "No existe línea DIF DE CAJA id={$c['dif']} ({$c['dia']})"; }
    if (! $mVentas || ! $mDif) { continue; }

    if (abs((float) $mVentas->monto - $c['ventasBase']) > 0.05) {
        $errores[] = "VENTAS {$c['dia']} no vale ".$fmt($c['ventasBase']).' (vale '.$fmt((float) $mVentas->monto).')';
    }
    if (abs((float) $mDif->monto - $c['difBase']) > 0.05) {
        $errores[] = "DIF DE CAJA {$c['dia']} no vale ".$fmt($c['difBase']).' (vale '.$fmt((float) $mDif->monto).')';
    }

    $cierre = TurnoOperativoGastronomia::query()->find($c['cierre']);
    if (! $cierre) { $errores[] = "No existe cierre #{$c['cierre']} ({$c['dia']})"; continue; }
    if (abs((float) $cierre->monto_facturacion_turno - $c['cierreBase']) > 0.05) {
        $errores[] = "Cierre #{$c['cierre']} {$c['dia']} no vale ".$fmt($c['cierreBase'])
            .' (vale '.$fmt((float) $cierre->monto_facturacion_turno).')';
    }

    $debeAntes = 0.0; $haberAntes = 0.0;
    foreach ($asiento->asiento_movimientos as $m) {
        $mo = round((float) $m->monto, 2);
        if ($mo > 0) { $debeAntes += $mo; } else { $haberAntes += abs($mo); }
    }
    $ventasNuevo = round((float) $mVentas->monto + $delta, 2);
    $difNuevo = round((float) $mDif->monto - $delta, 2);
    $debeDespues = round($debeAntes - $delta, 2);
    $haberDespues = round($haberAntes - $delta, 2);
    $montoCierreNuevo = round((float) $cierre->monto_facturacion_turno - $delta, 2);

    echo "Jornada {$c['dia']} (NC #{$c['nc']}):\n";
    echo "  Asiento #{$asiento->numeroasiento} (id {$c['asiento']}):\n";
    echo '    413010001 VENTAS ALIMENTOS   '.$fmt((float) $mVentas->monto).' -> '.$fmt($ventasNuevo)."  (haber -".$fmt($delta).")\n";
    echo '    521280004 DIFERENCIA DE CAJA '.$fmt((float) $mDif->monto).' -> '.$fmt($difNuevo)."  (debe -".$fmt($delta).")\n";
    echo '    Débitos:  '.$fmt($debeAntes).' -> '.$fmt($debeDespues)."\n";
    echo '    Créditos: '.$fmt($haberAntes).' -> '.$fmt($haberDespues)."\n";
    echo '    Balanceado: '.(abs($debeDespues - $haberDespues) <= $tol ? 'SÍ' : 'NO ***')."\n";
    echo "  Cierre #{$c['cierre']}: monto_facturacion_turno ".$fmt((float) $cierre->monto_facturacion_turno).' -> '.$fmt($montoCierreNuevo)."\n\n";

    if (abs($debeDespues - $haberDespues) > $tol) {
        $errores[] = "Asiento {$c['dia']} no balancea tras el ajuste";
        continue;
    }

    $plan[] = compact('c', 'asiento', 'mVentas', 'mDif', 'cierre', 'ventasNuevo', 'difNuevo', 'montoCierreNuevo');
}

if ($errores !== []) {
    fwrite(STDERR, "Abortado, estado inesperado:\n - ".implode("\n - ", $errores)."\n");
    exit(1);
}

if (! $apply) {
    echo "DRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov Anita.\n";
    exit(0);
}

echo "=== APLICANDO ===\n";
$repo = app(AsientoRepository::class);

foreach ($plan as $p) {
    $c = $p['c'];
    DB::transaction(function () use ($p, $delta) {
        $p['mVentas']->monto = $p['ventasNuevo'];
        $p['mVentas']->observacion = trim((string) $p['mVentas']->observacion)." (-{$delta} NC cortesía {$p['c']['dia']} neteada a \$0)";
        $p['mVentas']->save();

        $p['mDif']->monto = $p['difNuevo'];
        $p['mDif']->observacion = trim((string) $p['mDif']->observacion)." (-{$delta} NC cortesía {$p['c']['dia']})";
        $p['mDif']->save();

        $p['cierre']->monto_facturacion_turno = $p['montoCierreNuevo'];
        $p['cierre']->save();
    });
    echo "ERP actualizado {$c['dia']}. Resincronizando ctamov Anita asiento #{$p['asiento']->numeroasiento}...\n";

    $p['asiento']->refresh()->load(['asiento_movimientos.monedas']);
    $payload = $repo->armarPayloadAnitaDesdeModelo($p['asiento']);
    $repo->sincronizarCtamovAnita($payload);
    echo "ctamov Anita resincronizado {$c['dia']} (asiento {$p['asiento']->numeroasiento}).\n\n";
}

echo "Listo. Correr la conciliación para verificar difF=0 en 8/7 y 13/7 REBISCO.\n";
