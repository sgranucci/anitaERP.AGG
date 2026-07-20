<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Support\Facades\DB;

$empresa = 1;
$fecha = '2026-07-01';
$excelSumatoria = 418672.66;
$excelMayor = 498507.52;
$tipoId = 4; // tipoarticulo CIGARRILLO

echo "=== Auditoría cigarrillos Biyemas jornada {$fecha} ===\n";
echo "Excel Sumatoria (control ventas cig.): {$excelSumatoria}\n";
echo "Excel Mayor (ctamov 414020001):        {$excelMayor}\n";
echo 'Diferencia Excel (Mayor - Sumatoria):  '.round($excelMayor - $excelSumatoria, 2)."\n\n";

$emisiones = CierreJornadaFacturadoAnitaSupport::emisionesJornadaEmpresa($empresa, $fecha);
$totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresa)['id'] ?? 0);

// Líneas cigarrillo por venta
$lineasCig = DB::table('venta_emision as ve')
    ->join('venta as v', 'v.id', '=', 've.venta_id')
    ->join('articulo as a', 'a.id', '=', 've.articulo_id')
    ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
    ->whereNull('v.deleted_at')
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

$kioscoActual = 0.0;   // gravado(total factura) + ii  -> lo que hoy va a la cuenta 414020001
$kioscoCorrecto = 0.0; // gravado(solo lineas cig) + ii
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
    if (abs($exc) > 0.5) {
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

echo "=== Facturas mixtas (posible causa del desvío) ===\n";
echo "Facturas con imp. interno: {$nFact}\n";
echo 'Kiosco ACTUAL (base = total factura):     '.round($kioscoActual, 2)."\n";
echo 'Kiosco CORRECTO (base = solo líneas cig.): '.round($kioscoCorrecto, 2)."\n";
echo 'Exceso gravado en cuenta 414020001:        '.round($excesoGrav, 2)."\n";
echo 'Diff ACTUAL vs Excel Sumatoria:            '.round($kioscoActual - $excelSumatoria, 2)."\n";
echo 'Diff CORRECTO vs Excel Sumatoria:          '.round($kioscoCorrecto - $excelSumatoria, 2)."\n";
echo 'Diff ACTUAL vs Excel Mayor:                '.round($kioscoActual - $excelMayor, 2)."\n\n";

echo "Top 20 facturas mixtas con mayor exceso de gravado:\n";
foreach (array_slice($top, 0, 20) as $t) {
    echo sprintf(
        "  venta %d total=%.2f cig=%.2f packs=%.0f ii=%.2f grav_act=%.2f grav_cor=%.2f exceso=%.2f\n",
        $t['venta'],
        $t['total'],
        $t['importe_cig'],
        $t['packs'],
        $t['ii'],
        $t['grav_act'],
        $t['grav_cor'],
        $t['exceso'],
    );
}

// Asiento(s) grabados de cierre y su línea kiosco/cigarrillos
echo "\n=== Asientos de cierre jornada (línea kiosco/cigarrillos) ===\n";
$movs = DB::table('asiento_movimiento as am')
    ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
    ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
    ->where('a.empresa_id', $empresa)
    ->whereDate('a.fecha', $fecha)
    ->where(function ($q) {
        $q->where('cc.codigo', '414020001')
            ->orWhere('am.observacion', 'like', '%kiosco%')
            ->orWhere('am.observacion', 'like', '%cigarril%');
    })
    ->select('a.numeroasiento', 'a.id as asiento_id', 'cc.codigo', 'cc.nombre', 'am.monto', 'am.observacion')
    ->get();
$totalCuenta = 0.0;
foreach ($movs as $m) {
    echo sprintf(
        "  asiento %s (#%d) cuenta=%s %s monto=%.2f haber=%.2f | %s\n",
        $m->numeroasiento,
        $m->asiento_id,
        $m->codigo,
        $m->nombre,
        (float) $m->monto,
        (float) $m->monto * -1,
        (string) $m->observacion,
    );
    if ((string) $m->codigo === '414020001') {
        $totalCuenta += (float) $m->monto * -1;
    }
}
echo 'Total haber en cuenta 414020001 (asientos ERP): '.round($totalCuenta, 2)."\n";
