<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Support\Facades\DB;

$empresa = 1;
$fecha = '2026-06-01';
$excelSumatoria = 424487.56;
$excelMayor = 488401.77;
$excelCoefImpInterno = 4457.27;
$excelPrecio = 6100.0;

$emisiones = CierreJornadaFacturadoAnitaSupport::emisionesJornadaEmpresa($empresa, $fecha);
$totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresa)['id'] ?? 0);

$sumTotal = 0.0;
$sumII = 0.0;
$sumGrav = 0.0;
$sumKiosco = 0.0;
$n = 0;
$detalle = [];

foreach ($emisiones as $e) {
    if (CierreJornadaFacturadoAnitaSupport::esEmisionFacturaProcesoCierreJornada($e)) {
        continue;
    }
    if (CierreJornadaFacturadoAnitaSupport::esFacturaCobroTotemPublico($e, $empresa, $totemId)) {
        continue;
    }
    $v = $e->venta;
    if ($v === null) {
        continue;
    }
    $v->loadMissing(['venta_impuestos']);
    $monto = round((float) ($v->total ?? 0), 2);
    if (abs($monto) <= 0.0001) {
        continue;
    }
    $ii = 0.0;
    foreach ($v->venta_impuestos ?? [] as $vi) {
        if (str_contains(mb_strtolower((string) ($vi->concepto ?? '')), 'intern')) {
            $ii += (float) ($vi->importe ?? 0);
        }
    }
    $ii = round($ii, 2);
    if (abs($ii) <= 0.0001) {
        continue;
    }
    $neto = round(max(0.0, abs($monto) - abs($ii)), 2);
    $grav = round($neto / 1.21, 2);
    $kiosco = round($grav + $ii, 2);
    $n++;
    $sumTotal += $monto;
    $sumII += $ii;
    $sumGrav += $grav;
    $sumKiosco += $kiosco;
    $detalle[] = [
        'venta_id' => (int) $v->id,
        'total' => $monto,
        'ii' => $ii,
        'grav' => $grav,
        'kiosco' => $kiosco,
        'nc' => ($e->venta_factura_origen_id ?? null) !== null,
    ];
}

echo "=== Facturas con imp. interno jornada {$fecha} Biyemas ===\n";
echo "Cantidad facturas: {$n}\n";
echo 'Suma total factura: '.round($sumTotal, 2)."\n";
echo 'Suma imp. interno ERP: '.round($sumII, 2)."\n";
echo 'Suma gravado ERP: '.round($sumGrav, 2)."\n";
echo 'Suma ventas_kiosco (grav+ii): '.round($sumKiosco, 2)."\n";
echo "Excel Sumatoria: {$excelSumatoria}\n";
echo "Excel Mayor: {$excelMayor}\n";
echo 'Diff ERP kiosco vs Excel Sumatoria: '.round($sumKiosco - $excelSumatoria, 2)."\n";
echo 'Diff ERP kiosco vs Excel Mayor: '.round($sumKiosco - $excelMayor, 2)."\n";

usort($detalle, static fn (array $a, array $b): int => $b['kiosco'] <=> $a['kiosco']);

echo "\nTop 20 facturas por importe kiosco:\n";
foreach (array_slice($detalle, 0, 20) as $d) {
    echo sprintf(
        "  venta %d total=%.2f ii=%.2f kiosco=%.2f nc=%s\n",
        $d['venta_id'],
        $d['total'],
        $d['ii'],
        $d['kiosco'],
        $d['nc'] ? 'Y' : 'N',
    );
}

$tipoId = 4;
$cantExpr = GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
$lineas = DB::table('venta_emision as ve')
    ->join('venta as v', 'v.id', '=', 've.venta_id')
    ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
    ->join('articulo as a', 'a.id', '=', 've.articulo_id')
    ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
    ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
    ->where('pv.empresa_id', $empresa)
    ->whereDate('v.fechajornada', $fecha)
    ->where('a.tipoarticulo_id', $tipoId)
    ->select('ve.venta_id', 'a.sku')
    ->selectRaw('SUM('.$cantExpr.') as cant')
    ->selectRaw('SUM(ve.precio * '.$cantExpr.') as importe')
    ->groupBy('ve.venta_id', 'a.sku')
    ->get();

$byVenta = [];
foreach ($lineas as $l) {
    $byVenta[(int) $l->venta_id][] = $l;
}

$facturasConLineasCig = array_keys($byVenta);
$facturasConII = array_column($detalle, 'venta_id');
$sinLineas = array_diff($facturasConII, $facturasConLineasCig);
$sinII = array_diff($facturasConLineasCig, $facturasConII);

echo "\nFacturas con imp. interno pero SIN linea tipo CIGARRILLO: ".count($sinLineas)."\n";
foreach ($sinLineas as $vid) {
    $d = collect($detalle)->firstWhere('venta_id', $vid);
    echo "  venta {$vid} kiosco=".($d['kiosco'] ?? '?')." ii=".($d['ii'] ?? '?')."\n";
}

echo "\nFacturas con linea CIGARRILLO pero SIN imp. interno en cabecera: ".count($sinII)."\n";
foreach (array_slice($sinII, 0, 10) as $vid) {
    $packs = array_sum(array_map(static fn ($l) => (float) $l->cant, $byVenta[$vid] ?? []));
    echo "  venta {$vid} packs={$packs}\n";
}

echo "\nFacturas donde ii ERP difiere de packs * coef Excel (> \$1):\n";
$diffII = 0.0;
foreach ($detalle as $d) {
    $vid = $d['venta_id'];
    $lines = $byVenta[$vid] ?? [];
    $packs = 0.0;
    foreach ($lines as $l) {
        $packs += (float) $l->cant;
    }
    $iiExcel = round($packs * $excelCoefImpInterno, 2);
    $delta = round($d['ii'] - $iiExcel, 2);
    if (abs($delta) > 1.0) {
        $diffII += $delta;
        echo "  venta {$vid} packs={$packs} ii_erp={$d['ii']} ii_excel={$iiExcel} delta={$delta}\n";
    }
}
echo 'Suma deltas ii: '.round($diffII, 2)."\n";

// Excel-style sumatoria from line quantities
$excelGrav = 0.0;
$excelImp = 0.0;
$excelVenta = 0.0;
$gravUnit = 1357.6280991735541;
foreach ($lineas as $l) {
    $cant = (float) $l->cant;
    $excelVenta += round($cant * $excelPrecio, 2);
    $excelImp += round($cant * $excelCoefImpInterno, 2);
    $excelGrav += round($cant * $gravUnit, 2);
}
echo "\nExcel recalculado desde lineas ERP:\n";
echo '  venta caja: '.round($excelVenta, 2)."\n";
echo '  imp interno: '.round($excelImp, 2)."\n";
echo '  gravado: '.round($excelGrav, 2)."\n";
echo '  sumatoria grav+imp: '.round($excelGrav + $excelImp, 2)."\n";

// --- Exceso por facturas mixtas (cigarrillos + otros rubros) ---
$emisiones = CierreJornadaFacturadoAnitaSupport::emisionesJornadaEmpresa($empresa, $fecha);
$totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresa)['id'] ?? 0);

$lineasCig = DB::table('venta_emision as ve')
    ->join('venta as v', 'v.id', '=', 've.venta_id')
    ->join('articulo as a', 'a.id', '=', 've.articulo_id')
    ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
    ->where('pv.empresa_id', $empresa)
    ->whereDate('v.fechajornada', $fecha)
    ->where('a.tipoarticulo_id', $tipoId)
    ->select('ve.venta_id')
    ->selectRaw('SUM(ABS(ve.cantidad) * ve.precio) as importe')
    ->selectRaw('SUM(ABS(ve.cantidad)) as packs')
    ->groupBy('ve.venta_id')
    ->get();

$mapCig = [];
foreach ($lineasCig as $l) {
    $mapCig[(int) $l->venta_id] = ['importe' => (float) $l->importe, 'packs' => (float) $l->packs];
}

$kioscoActual = 0.0;
$kioscoCorrecto = 0.0;
$excesoGrav = 0.0;
$nFact = 0;
$top = [];

foreach ($emisiones as $e) {
    if (CierreJornadaFacturadoAnitaSupport::esEmisionFacturaProcesoCierreJornada($e)) {
        continue;
    }
    if (CierreJornadaFacturadoAnitaSupport::esFacturaCobroTotemPublico($e, $empresa, $totemId)) {
        continue;
    }
    $v = $e->venta;
    if ($v === null) {
        continue;
    }
    $v->loadMissing('venta_impuestos');
    $total = round((float) ($v->total ?? 0), 2);
    $ii = 0.0;
    foreach ($v->venta_impuestos ?? [] as $vi) {
        if (str_contains(mb_strtolower((string) ($vi->concepto ?? '')), 'intern')) {
            $ii += (float) ($vi->importe ?? 0);
        }
    }
    $ii = round($ii, 2);
    if (abs($ii) <= 0.0001) {
        continue;
    }
    $nFact++;
    $gravAct = round(max(0.0, abs($total) - abs($ii)) / 1.21, 2);
    $kioscoActual += round($gravAct + $ii, 2);

    $cig = $mapCig[(int) $v->id] ?? ['importe' => 0.0, 'packs' => 0.0];
    $gravCor = round(max(0.0, $cig['importe'] - $ii) / 1.21, 2);
    $kioscoCorrecto += round($gravCor + $ii, 2);
    $exc = round($gravAct - $gravCor, 2);
    $excesoGrav += $exc;
    if ($exc > 0.5) {
        $top[] = [
            'venta' => (int) $v->id,
            'total' => $total,
            'ii' => $ii,
            'importe_cig' => $cig['importe'],
            'packs' => $cig['packs'],
            'grav_act' => $gravAct,
            'grav_cor' => $gravCor,
            'exceso' => $exc,
        ];
    }
}

usort($top, static fn (array $a, array $b): int => $b['exceso'] <=> $a['exceso']);

echo "\n=== Facturas mixtas (causa del desvío) ===\n";
echo "Facturas con imp. interno: {$nFact}\n";
echo 'Kiosco ACTUAL (total factura): '.round($kioscoActual, 2)."\n";
echo 'Kiosco CORRECTO (solo líneas cig.): '.round($kioscoCorrecto, 2)."\n";
echo 'Exceso gravado en cuenta kiosco: '.round($excesoGrav, 2)."\n";
echo 'Diff ACTUAL vs Excel Sumatoria: '.round($kioscoActual - $excelSumatoria, 2)."\n";
echo 'Diff CORRECTO vs Excel Sumatoria: '.round($kioscoCorrecto - $excelSumatoria, 2)."\n";

echo "\nTop 15 facturas mixtas con mayor exceso de gravado:\n";
foreach (array_slice($top, 0, 15) as $t) {
    echo sprintf(
        "  venta %d total=%.2f cig=%.2f packs=%.0f ii=%.2f exceso_grav=%.2f\n",
        $t['venta'],
        $t['total'],
        $t['importe_cig'],
        $t['packs'],
        $t['ii'],
        $t['exceso'],
    );
}

// Cuenta contable usada vs configurada
$cuentaAutoId = (int) CuentaAutomaticaResolver::resolverId($empresa, CuentaAutomaticaClaves::CIERRE_WAITRY_VENTAS_KIOSCO);
$cuentaAuto = DB::table('cuentacontable')->where('id', $cuentaAutoId)->first(['codigo', 'nombre']);
$override = DB::table('contabilidad_cuenta_automatica')
    ->where('empresa_id', $empresa)
    ->where('clave', CuentaAutomaticaClaves::CIERRE_WAITRY_VENTAS_KIOSCO)
    ->first(['cuentacontable_id']);

echo "\n=== Cuenta contable ===\n";
echo 'Resolver devuelve: '.($cuentaAuto->codigo ?? '?').' '.($cuentaAuto->nombre ?? '')." (id={$cuentaAutoId})\n";
if ($override) {
    $ov = DB::table('cuentacontable')->where('id', $override->cuentacontable_id)->first(['codigo', 'nombre']);
    echo 'Override BD contabilidad_cuenta_automatica: '.($ov->codigo ?? '?').' '.($ov->nombre ?? '').' (id='.$override->cuentacontable_id.")\n";
}

$movAsiento = DB::table('asiento_movimiento as am')
    ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
    ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
    ->where('a.empresa_id', $empresa)
    ->whereDate('a.fecha', $fecha)
    ->where('am.observacion', 'like', '%kiosco%')
    ->select('a.numeroasiento', 'cc.codigo', 'cc.nombre', 'am.monto', 'am.observacion')
    ->first();
if ($movAsiento) {
    echo 'Asiento grabado: n='.$movAsiento->numeroasiento.' cuenta='.$movAsiento->codigo.' '.($movAsiento->monto * -1)." | {$movAsiento->observacion}\n";
}
