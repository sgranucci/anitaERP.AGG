<?php

declare(strict_types=1);

/*
 * Corrección puntual jornadas 2026-07-03 y 2026-07-06 BIYEMAS (empresa 1), circuito FLASH gastronomía.
 *
 * Motivo: 1 nota de crédito de cortesía ($0,01) por día se había grabado con venta.total POSITIVO
 * (+0,01) en vez de negativo. Se contabilizaba como venta en lugar de netear la cortesía a $0,
 * inflando facturación / asientos / mayor en 0,02 respecto del flash (que sí la netea con total_z - tot_nc).
 *
 * Las NC de julio ya se corrigieron en venta/venta_impuesto (venta.total -0,01). Este script cierra los
 * dos días BIYEMAS con difF=0,02 en la contabilidad ya posteada, replicando el fix aprobado de REBISCO 16/7:
 *
 *   3/7  NC #59122 (11:16, PV cfg 3, turno Mañana):
 *     - Asiento factura_día #38335 (nro 349581):
 *         413010001 VENTAS ALIMENTOS  -3.144.880,20 -> -3.144.880,18  (haber -0,02)
 *         521280004 DIFERENCIA DE CAJA         3,85 ->          3,83   (debe  -0,02)
 *     - Cierre Mañana #420: monto_facturacion_turno 697.160,80 -> 697.160,78 (-0,02)
 *
 *   6/7  NC #67751 (23:19, PV cfg 4, turno Noche):
 *     - Asiento factura_día #38435 (nro 349687):
 *         413010001 VENTAS ALIMENTOS  -2.863.193,87 -> -2.863.193,85  (haber -0,02)
 *         521280004 DIFERENCIA DE CAJA         3,51 ->          3,49   (debe  -0,02)
 *     - Cierre Noche #473: monto_facturacion_turno 1.941.240,10 -> 1.941.240,08 (-0,02)
 *
 *   + Resync ctamov Anita de cada asiento => mayor baja 0,02 (solo por la cuenta de ventas).
 *
 * Resultado: facturación = asientos = mayor = flash en ambos días.
 *
 * Uso:  php scripts/fix_cortesia_nc_biyemas_julio.php          (dry-run)
 *       php scripts/fix_cortesia_nc_biyemas_julio.php apply    (persiste ERP + ctamov Anita)
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

/** @var list<array{dia:string,nc:int,asiento:int,ventas:int,dif:int,cierre:int,ventasBase:float,difBase:float,cierreBase:float}> */
$casos = [
    [
        'dia' => '2026-07-03', 'nc' => 59122,
        'asiento' => 38335, 'ventas' => 40112, 'dif' => 40111, 'cierre' => 420,
        'ventasBase' => -3144880.20, 'difBase' => 3.85, 'cierreBase' => 697160.80,
    ],
    [
        'dia' => '2026-07-06', 'nc' => 67751,
        'asiento' => 38435, 'ventas' => 40407, 'dif' => 40406, 'cierre' => 473,
        'ventasBase' => -2863193.87, 'difBase' => 3.51, 'cierreBase' => 1941240.10,
    ],
];

echo '=== Fix cortesía NC julio BIYEMAS ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

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

    // balance
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

echo "Listo. Correr la conciliación para verificar difF=0 en 3/7 y 6/7 BIYEMAS.\n";
