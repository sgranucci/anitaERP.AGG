<?php

declare(strict_types=1);

/*
 * Corrección puntual jornada 2026-07-16 REBISCO (empresa 3), circuito FLASH gastronomía.
 *
 * Motivo: 2 notas de crédito de cortesía ($0,01 c/u) se habían grabado con venta.total POSITIVO
 * (+0,01) en vez de negativo. Se contabilizaban como venta en lugar de netear la cortesía a $0,
 * inflando facturación / asientos / mayor en 0,04 respecto del flash (que sí las netea).
 *
 * Las 8 NC de julio ya se corrigieron en venta/venta_impuesto (venta.total -0,01). Este script
 * cierra el 16/7 —único día que superaba la tolerancia (0,04)— en la contabilidad ya posteada:
 *
 *   1) Asiento factura del día #38974 (nro 226875): baja la cortesía exenta
 *        - 413010001 VENTAS ALIMENTOS Y BEBIDAS  -1.032.314,80 -> -1.032.314,76  (haber -0,04)
 *        - 521280004 DIFERENCIA DE CAJA                    1,06 ->         1,02   (debe  -0,04)
 *      => total débitos 1.413.801,06 -> 1.413.801,02  (balanceado)
 *   2) Cierre noche PV34 (#628): monto_facturacion_turno 931.680,54 -> 931.680,50 (-0,04)
 *   3) Resync ctamov Anita del asiento => mayor baja 0,04 (solo por la cuenta de ventas).
 *
 * Resultado: facturación = asientos = mayor = flash = 1.442.001,02  (conciliación OK).
 *
 * Uso:  php scripts/fix_cortesia_nc_rebisco_1607.php          (dry-run)
 *       php scripts/fix_cortesia_nc_rebisco_1607.php apply    (persiste ERP + ctamov Anita)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Contable\AsientoRepository;

$apply = in_array('apply', $argv, true);
$tol = 0.01;
$delta = 0.04; // 2 NC de cortesía x 0,02
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');

$asientoId = 38974;
$idVentas = 41969;        // 413010001 VENTAS ALIMENTOS  -1.032.314,80
$idDifCaja = 41968;       // 521280004 DIFERENCIA DE CAJA         1,06
$cierreNocheId = 628;     // Noche RSA PV34  monto 931.680,54

echo '=== Fix cortesía NC 16/07 REBISCO ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

$asiento = Asiento::with('asiento_movimientos.cuentacontables')->findOrFail($asientoId);
$byId = [];
foreach ($asiento->asiento_movimientos as $m) {
    $byId[(int) $m->id] = $m;
}

$errores = [];
$assert = static function (bool $cond, string $msg) use (&$errores): void {
    if (! $cond) { $errores[] = $msg; }
};

$mVentas = $byId[$idVentas] ?? null;
$mDif = $byId[$idDifCaja] ?? null;
$assert($mVentas !== null, "No existe la línea VENTAS id={$idVentas}");
$assert($mDif !== null, "No existe la línea DIFERENCIA DE CAJA id={$idDifCaja}");
if ($mVentas) {
    $assert(abs((float) $mVentas->monto - (-1032314.80)) <= 0.05,
        'VENTAS no vale -1.032.314,80 (vale '.$fmt((float) $mVentas->monto).')');
}
if ($mDif) {
    $assert(abs((float) $mDif->monto - 1.06) <= 0.05,
        'DIFERENCIA DE CAJA no vale 1,06 (vale '.$fmt((float) ($mDif->monto ?? 0)).')');
}

$cierre = TurnoOperativoGastronomia::query()->find($cierreNocheId);
$assert($cierre !== null, "No existe el cierre #{$cierreNocheId}");
if ($cierre) {
    $assert(abs((float) $cierre->monto_facturacion_turno - 931680.54) <= 0.05,
        'Cierre #'.$cierreNocheId.' no vale 931.680,54 (vale '.$fmt((float) $cierre->monto_facturacion_turno).')');
}

if ($errores !== []) {
    fwrite(STDERR, "Abortado, estado inesperado:\n - ".implode("\n - ", $errores)."\n");
    exit(1);
}

// Balance actual/nuevo del asiento
$debeAntes = 0.0; $haberAntes = 0.0;
foreach ($asiento->asiento_movimientos as $m) {
    $mo = round((float) $m->monto, 2);
    if ($mo > 0) { $debeAntes += $mo; } else { $haberAntes += abs($mo); }
}
$ventasNuevo = round((float) $mVentas->monto + $delta, 2);  // -1.032.314,80 -> -1.032.314,76
$difNuevo = round((float) $mDif->monto - $delta, 2);        //          1,06 ->          1,02
$debeDespues = round($debeAntes - $delta, 2);
$haberDespues = round($haberAntes - $delta, 2);
$montoCierreNuevo = round((float) $cierre->monto_facturacion_turno - $delta, 2);

echo "Asiento #{$asiento->numeroasiento} (id {$asientoId}):\n";
echo "  413010001 VENTAS ALIMENTOS   ".$fmt((float) $mVentas->monto)." -> ".$fmt($ventasNuevo)."  (haber -".$fmt($delta).")\n";
echo "  521280004 DIFERENCIA DE CAJA ".$fmt((float) $mDif->monto)." -> ".$fmt($difNuevo)."  (debe -".$fmt($delta).")\n";
echo "  Débitos:  ".$fmt($debeAntes)." -> ".$fmt($debeDespues)."\n";
echo "  Créditos: ".$fmt($haberAntes)." -> ".$fmt($haberDespues)."\n";
echo "  Balanceado: ".(abs($debeDespues - $haberDespues) <= $tol ? 'SÍ' : 'NO ***')."\n\n";
echo "Cierre noche PV34 #{$cierreNocheId}: monto_facturacion_turno ".$fmt((float) $cierre->monto_facturacion_turno)." -> ".$fmt($montoCierreNuevo)."\n\n";
echo "Objetivo conciliación 16/7: facturación = asientos = mayor = flash = 1.442.001,02\n";

if (abs($debeDespues - $haberDespues) > $tol) {
    fwrite(STDERR, "\nNo balancea, no se aplica nada.\n");
    exit(1);
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov Anita.\n";
    exit(0);
}

echo "\n=== APLICANDO ===\n";
DB::transaction(function () use ($mVentas, $mDif, $ventasNuevo, $difNuevo, $cierre, $montoCierreNuevo) {
    $mVentas->monto = $ventasNuevo;
    $mVentas->observacion = trim((string) $mVentas->observacion).' (-0,04 NC cortesía 16/7 neteada a $0)';
    $mVentas->save();

    $mDif->monto = $difNuevo;
    $mDif->observacion = trim((string) $mDif->observacion).' (-0,04 NC cortesía 16/7)';
    $mDif->save();

    $cierre->monto_facturacion_turno = $montoCierreNuevo;
    $cierre->save();
});
echo "ERP actualizado. Resincronizando ctamov Anita...\n";

$repo = app(AsientoRepository::class);
$asiento->refresh()->load(['asiento_movimientos.monedas']);
$payload = $repo->armarPayloadAnitaDesdeModelo($asiento);
$repo->sincronizarCtamovAnita($payload);
echo "ctamov Anita resincronizado para asiento {$asiento->numeroasiento}.\n";
