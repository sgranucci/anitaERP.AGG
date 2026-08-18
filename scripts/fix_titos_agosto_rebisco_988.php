<?php

declare(strict_types=1);

/**
 * Rebisco agosto 2026: TRCONT TITO a 9.88 (planilla Contaduría) + recepmov COM 159766
 * cotización 1375 → 1280 (ajuste contable que no estaba en recepmov).
 *
 * No toca julio ni Biyemas/Kandiko.
 *
 * Uso:
 *   php scripts/fix_titos_agosto_rebisco_988.php
 *   php scripts/fix_titos_agosto_rebisco_988.php apply
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Contable\AsientoRepository;
use App\Services\Stock\RecepcionProveedorAnitaBridgeService;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\ArticuloPrecioPromedioCompraSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

$apply = in_array('apply', $argv ?? [], true);

$precioObj = 9.88;
$empresaRebisco = 3;
$articuloTitoRebisco = 6254;
$recepcionId = 57195;
$cotizacionRecepmovObj = 1280.0;
$fmt = static fn (float $v): string => number_format($v, 6, '.', '');
$fmt2 = static fn (float $v): string => number_format($v, 2, ',', '.');

echo '=== TITO Rebisco agosto 9.88 + recepmov COM 159766 ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

$rows = DB::table('transferencia_mercaderia as tm')
    ->join('transferencia_mercaderia_articulo as tma', 'tma.transferencia_mercaderia_id', '=', 'tm.id')
    ->join('articulo as a', 'a.id', '=', 'tma.articulo_origen_id')
    ->leftJoin('tipotransaccion_stock as tt', 'tt.id', '=', 'tm.tipotransaccion_stock_id')
    ->leftJoin('asiento as asi', 'asi.id', '=', 'tm.asiento_id')
    ->where('tm.empresa_id', $empresaRebisco)
    ->where('tma.articulo_origen_id', $articuloTitoRebisco)
    ->whereBetween('tm.fecha', ['2026-08-01', '2026-08-31'])
    ->where('tm.estado', 'CONFIRMADA')
    ->where('tt.maneja_contabilidad', 1)
    ->whereNotNull('tm.asiento_id')
    ->orderBy('tm.id')
    ->get([
        'tm.id', 'tm.codigo', 'tm.fecha', 'tm.empresa_id', 'tm.asiento_id',
        'tm.movimientostock_salida_id', 'tm.movimientostock_entrada_id',
        'asi.numeroasiento', 'asi.empresa_id as asiento_empresa_id',
        'tma.id as linea_id', 'tma.articulo_origen_id', 'a.sku',
        'tma.cantidad_origen', 'tma.precio_costo_origen',
    ]);

if ($rows->isEmpty()) {
    fwrite(STDERR, "Sin TRCONT TITO Rebisco en agosto.\n");
    exit(1);
}

$ajustes = [];
echo "--- TM agosto ---\n";
foreach ($rows as $r) {
    $fecha = substr((string) $r->fecha, 0, 10);
    if ($fecha < '2026-08-01' || $fecha > '2026-08-31') {
        throw new RuntimeException('Guarda julio: TM#'.$r->id.' fecha '.$fecha);
    }
    $cant = (float) $r->cantidad_origen;
    $importeObj = round($cant * $precioObj, 2);
    $montoActual = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $r->asiento_id)
        ->where('monto', '>', 0)
        ->value('monto');

    $ajustes[] = [
        'tm_id' => (int) $r->id,
        'codigo' => (string) $r->codigo,
        'fecha' => $fecha,
        'asiento_id' => (int) $r->asiento_id,
        'asiento_empresa_id' => (int) $r->asiento_empresa_id,
        'numeroasiento' => (string) $r->numeroasiento,
        'linea_id' => (int) $r->linea_id,
        'articulo_id' => (int) $r->articulo_origen_id,
        'sku' => (string) $r->sku,
        'cantidad' => $cant,
        'precio_actual' => (float) $r->precio_costo_origen,
        'precio_objetivo' => $precioObj,
        'importe_actual' => $montoActual,
        'importe_objetivo' => $importeObj,
        'salida_id' => (int) ($r->movimientostock_salida_id ?? 0),
        'entrada_id' => (int) ($r->movimientostock_entrada_id ?? 0),
    ];

    echo sprintf(
        "RSA TM#%d %s %s cant=%s p=%s→%s imp=%s→%s asiento#%s nro=%s\n",
        $r->id,
        $r->codigo,
        $r->sku,
        $fmt($cant),
        $fmt((float) $r->precio_costo_origen),
        $fmt($precioObj),
        $fmt2($montoActual),
        $fmt2($importeObj),
        $r->asiento_id,
        $r->numeroasiento
    );
}

echo "\n--- recepmov COM X/3/159766 ---\n";
$lins = RecepcionProveedorAnitaImportSupport::listarRecepmov('COM', 'X', 3, 159766);
if ($lins === []) {
    fwrite(STDERR, "COM 159766 sin recepmov.\n");
    exit(1);
}
foreach ($lins as $lin) {
    $cot = (float) ($lin->recv_cotizacion ?? 0);
    $prec = (float) ($lin->recv_precio ?? 0);
    echo sprintf(
        "art=%s prec=%s mon=%s cot=%s→%s unit=%s→%s\n",
        trim((string) ($lin->recv_articulo ?? '')),
        $prec,
        (string) ($lin->recv_cod_mon ?? ''),
        $fmt($cot),
        $fmt($cotizacionRecepmovObj),
        $fmt($prec * $cot),
        $fmt($prec * $cotizacionRecepmovObj)
    );
}

$julio = DB::table('transferencia_mercaderia as tm')
    ->join('transferencia_mercaderia_articulo as tma', 'tma.transferencia_mercaderia_id', '=', 'tm.id')
    ->where('tm.empresa_id', $empresaRebisco)
    ->where('tma.articulo_origen_id', $articuloTitoRebisco)
    ->whereBetween('tm.fecha', ['2026-07-01', '2026-07-31'])
    ->get(['tm.id', 'tm.codigo', 'tma.precio_costo_origen']);
echo "\n--- Julio RSA (no se toca) ---\n";
foreach ($julio as $j) {
    echo sprintf("TM#%d %s p=%s\n", $j->id, $j->codigo, $fmt((float) $j->precio_costo_origen));
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov + recepmov.\n";
    exit(0);
}

echo "\n=== APLICANDO TM ===\n";
$repo = app(AsientoRepository::class);

DB::transaction(function () use ($ajustes) {
    foreach ($ajustes as $aj) {
        $precio = $aj['precio_objetivo'];
        $importe = $aj['importe_objetivo'];

        DB::table('transferencia_mercaderia_articulo')->where('id', $aj['linea_id'])->update([
            'precio_costo_origen' => $precio,
            'precio_costo_destino' => $precio,
            'updated_at' => now(),
        ]);

        foreach (['salida_id', 'entrada_id'] as $key) {
            $movId = $aj[$key];
            if ($movId <= 0) {
                continue;
            }
            DB::table('articulo_movimiento')
                ->where('movimientostock_id', $movId)
                ->where('articulo_id', $aj['articulo_id'])
                ->update([
                    'precio' => $precio,
                    'costo' => $precio,
                    'updated_at' => now(),
                ]);
        }

        $movs = DB::table('asiento_movimiento')
            ->where('asiento_id', $aj['asiento_id'])
            ->orderBy('id')
            ->get(['id', 'monto']);

        if ($movs->count() !== 2) {
            throw new RuntimeException('Asiento #'.$aj['asiento_id'].' no tiene exactamente 2 líneas (tiene '.$movs->count().').');
        }

        foreach ($movs as $mov) {
            $nuevo = ((float) $mov->monto) >= 0 ? $importe : -1 * $importe;
            DB::table('asiento_movimiento')->where('id', $mov->id)->update([
                'monto' => $nuevo,
                'updated_at' => now(),
            ]);
        }

        echo "ERP TM#{$aj['tm_id']}: p={$precio} importe={$importe}\n";
    }
});

echo "\n=== Sync ctamov Anita ===\n";
foreach ($ajustes as $aj) {
    $asiento = Asiento::with(['asiento_movimientos.monedas'])->findOrFail($aj['asiento_id']);
    $payload = $repo->armarPayloadAnitaDesdeModelo($asiento);
    $payload['omitir_validacion'] = true;
    $payload['alcance_cierre_contable'] = PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA;
    if (empty($payload['sistema_ctav'])) {
        $payload['sistema_ctav'] = 'S';
    }
    if (empty($payload['tipo'])) {
        $payload['tipo'] = 'TRA';
        $payload['letra'] = ' ';
        $payload['sucursal'] = 0;
        if (preg_match('/(\d{6,})$/', $aj['codigo'], $m)) {
            $payload['nro'] = (int) substr($m[1], -8);
        }
    }

    $repo->sincronizarCtamovAnita($payload);
    echo "ctamov emp={$aj['asiento_empresa_id']} nro={$aj['numeroasiento']} OK (importe {$aj['importe_objetivo']})\n";
}

echo "\n=== recepmov COM 159766 cotización {$cotizacionRecepmovObj} ===\n";
$recepcion = Recepcion_Proveedor::query()->findOrFail($recepcionId);
if ((int) $recepcion->anita_nro !== 159766 || (int) $recepcion->empresa_id !== $empresaRebisco) {
    throw new RuntimeException('Recepción #'.$recepcionId.' no es COM 159766 Rebisco.');
}
app(RecepcionProveedorAnitaBridgeService::class)->actualizarCotizacionRecepmov($recepcion, $cotizacionRecepmovObj);
echo "recepmov actualizado\n";

echo "\n=== Verificación ===\n";
foreach ($ajustes as $aj) {
    $pLin = (float) DB::table('transferencia_mercaderia_articulo')->where('id', $aj['linea_id'])->value('precio_costo_origen');
    $monto = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $aj['asiento_id'])
        ->where('monto', '>', 0)
        ->value('monto');
    $ok = abs($pLin - $aj['precio_objetivo']) < 0.000001 && abs($monto - $aj['importe_objetivo']) < 0.01;
    echo sprintf("TM#%d p=%s imp=%s %s\n", $aj['tm_id'], $fmt($pLin), $fmt2($monto), $ok ? 'OK' : 'FALLA');
}

foreach ($julio as $j) {
    $p = (float) DB::table('transferencia_mercaderia_articulo')
        ->where('transferencia_mercaderia_id', $j->id)
        ->where('articulo_origen_id', $articuloTitoRebisco)
        ->value('precio_costo_origen');
    $okJul = abs($p - 10.02) < 0.000001;
    echo sprintf("Julio TM#%d p=%s %s\n", $j->id, $fmt($p), $okJul ? 'OK intacto' : 'FALLA se tocó julio');
}

$lins = RecepcionProveedorAnitaImportSupport::listarRecepmov('COM', 'X', 3, 159766);
foreach ($lins as $lin) {
    $cot = (float) ($lin->recv_cotizacion ?? 0);
    $okCot = abs($cot - $cotizacionRecepmovObj) < 0.0005;
    echo sprintf("recepmov cot=%s %s\n", $fmt($cot), $okCot ? 'OK' : 'FALLA');
}

$art = Articulo::query()->findOrFail($articuloTitoRebisco);
$prom = ArticuloPrecioPromedioCompraSupport::resolverPorArticulo($art);
echo 'Promedio TITO Rebisco ahora: '.($prom['precio'] ?? 'null')." origen=".($prom['origen'] ?? '')."\n";
foreach ($prom['compras'] ?? [] as $c) {
    echo '  '.json_encode($c, JSON_UNESCAPED_UNICODE)."\n";
}

echo "Listo.\n";
